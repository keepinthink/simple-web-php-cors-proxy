<?php

// CORS Headers – selalu dikirim
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Forwarded-For");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function cleanAndValidateUrl($url) {
    $url = trim($url);
    return filter_var($url, FILTER_VALIDATE_URL) ? $url : false;
}

function resolveDomain($url) {
    $host = parse_url($url, PHP_URL_HOST);
    $resolved = gethostbyname($host);
    return ($resolved !== $host) ? $resolved : false;
}

function forwardRequest($url) {
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_USERAGENT => $_SERVER['HTTP_USER_AGENT'] ?? 'PHP Proxy',
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);

    $method = $_SERVER['REQUEST_METHOD'];
    $body = file_get_contents("php://input");

    switch ($method) {
        case 'POST':
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            break;
        case 'PUT':
        case 'DELETE':
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            break;
    }

    $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-Forwarded-For: ' . $clientIp
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        return [
            'error' => true,
            'status' => 500,
            'body' => json_encode(['error' => true, 'message' => 'cURL error: ' . curl_error($ch)])
        ];
    }

    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);

    // Cek apakah redirect
    if ($httpCode >= 300 && $httpCode < 400) {
        preg_match('/Location:\s*(.*)/i', $headers, $matches);
        $location = trim($matches[1] ?? '');
        curl_close($ch);

        if ($location) {
            // Redirect tapi tetap kirim header CORS
            header("Access-Control-Allow-Origin: *");
            header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
            header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Forwarded-For");

            header("Location: $location", true, 302);
            exit;
        }
    }

    curl_close($ch);
    return [
        'error' => false,
        'status' => $httpCode,
        'body' => $body
    ];
}

// Main
$scriptName = $_SERVER['SCRIPT_NAME'];
$requestUri = $_SERVER['REQUEST_URI'];
$targetUrl = substr($requestUri, strlen($scriptName) + 1);

if (empty($targetUrl)) {
    http_response_code(400);
    echo json_encode(['error' => true, 'message' => 'Target URL is missing']);
    exit;
}

$validatedUrl = cleanAndValidateUrl($targetUrl);
if (!$validatedUrl) {
    http_response_code(400);
    echo json_encode(['error' => true, 'message' => 'Invalid URL format']);
    exit;
}

if (!resolveDomain($validatedUrl)) {
    http_response_code(404);
    echo json_encode(['error' => true, 'message' => 'Unresolvable domain']);
    exit;
}

$result = forwardRequest($validatedUrl);

http_response_code($result['status']);
header("Content-Type: application/json"); // You can adjust content-type based on result if needed
echo $result['body'];
