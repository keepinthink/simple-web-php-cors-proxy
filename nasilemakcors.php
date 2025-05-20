<?php

// CORS Headers to allow cross-origin resource sharing
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

// Main logic
$scriptName = $_SERVER['SCRIPT_NAME'];
$requestUri = $_SERVER['REQUEST_URI'];
$targetUrl = substr($requestUri, strlen($scriptName) + 1);

// Jika URL target kosong
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

// Jika semua valid, lakukan redirect langsung
header("Location: $validatedUrl", true, 302);
exit;

?>
