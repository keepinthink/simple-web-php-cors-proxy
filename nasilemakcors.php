<?php

// CORS Headers to allow cross-origin resource sharing for all origins, methods, and headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Forwarded-For");

// Handle preflight OPTIONS request for CORS
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
        CURLOPT_FOLLOWLOCATION => true,
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
        return json_encode([
            'error' => true,
            'message' => 'cURL error: ' . curl_error($ch)
        ]);
    }

    if (!$response) {
        return json_encode([
            'error' => true,
            'message' => 'No response from the destination server.',
            'url' => $url
        ]);
    }

    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL); // Tambahan: ambil URL akhir

    $body = substr($response, $headerSize);

    curl_close($ch);

    header("Content-Type: " . $contentType);
    header("X-Final-Url: " . $finalUrl); // Tambahan fitur: header untuk URL redirect akhir
    http_response_code(200);
    return $body;
}

// Main logic
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
    echo json_encode([
        'error' => true,
        'message' => 'Invalid URL format',
        'url' => $targetUrl
    ]);
    exit;
}

if (!resolveDomain($validatedUrl)) {
    http_response_code(404);
    echo json_encode([
        'error' => true,
        'message' => 'The domain cannot be resolved or is unreachable.',
        'domain' => parse_url($validatedUrl, PHP_URL_HOST)
    ]);
    exit;
}

$response = forwardRequest($validatedUrl);

if (strpos($response, '{"error":true') !== false) {
    echo $response;
    exit;
}

if (empty($response)) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'The target server did not respond or the response was empty.',
        'url' => $validatedUrl
    ]);
    exit;
}

echo $response;

?>
