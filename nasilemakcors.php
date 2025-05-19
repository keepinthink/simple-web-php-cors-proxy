<?php

// CORS Headers to allow cross-origin resource sharing for all origins, methods, and headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Forwarded-For");

// Handle preflight OPTIONS request for CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200); // Success response for preflight request
    exit;
}

/**
 * Clean and validate a provided URL.
 * 
 * This function trims any leading or trailing whitespace from the URL and validates its format.
 * 
 * @param string $url The URL to be validated.
 * @return string|false The cleaned URL if valid, or false if the URL is invalid.
 */
function cleanAndValidateUrl($url) {
    $url = trim($url); // Remove any extraneous whitespace
    return filter_var($url, FILTER_VALIDATE_URL) ? $url : false; // Return the URL if valid, otherwise false
}

/**
 * Resolve the domain of the given URL to an IP address.
 * 
 * This function checks if the domain of the URL is reachable.
 * 
 * @param string $url The URL to resolve.
 * @return string|false The resolved IP address, or false if the domain cannot be resolved.
 */
function resolveDomain($url) {
    $host = parse_url($url, PHP_URL_HOST); // Extract the host from the URL
    $resolved = gethostbyname($host); // Resolve the domain name to an IP address
    return ($resolved !== $host) ? $resolved : false; // If resolution is successful, return the IP address, otherwise false
}

/**
 * Forward the incoming request to the target server using cURL.
 * 
 * This function forwards various types of HTTP requests (GET, POST, PUT, DELETE) to the target URL and returns the response.
 * 
 * @param string $url The target URL to forward the request to.
 * @return string|false The response from the target server, or false if the request fails.
 */
function forwardRequest($url) {
    $ch = curl_init($url);
    
    // Set cURL options to return the response, include headers, and follow redirects
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => $_SERVER['HTTP_USER_AGENT'] ?? 'PHP Proxy',
        CURLOPT_TIMEOUT => 30, // Timeout after 30 seconds
        CURLOPT_CONNECTTIMEOUT => 10, // Connection timeout after 10 seconds
    ]);

    $method = $_SERVER['REQUEST_METHOD']; // Get the HTTP method (GET, POST, etc.)
    $body = file_get_contents("php://input"); // Get the body of the request (if any)

    // Handle different HTTP methods
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

    // Add client's IP address to the 'X-Forwarded-For' header for logging
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-Forwarded-For: ' . $clientIp
    ]);

    // Execute the request and capture the response
    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        // If there was an error with cURL, return a JSON error message
        return json_encode([
            'error' => true,
            'message' => 'cURL error: ' . curl_error($ch)
        ]);
    }

    if (!$response) {
        // If there was no response from the target server, return an error message
        return json_encode([
            'error' => true,
            'message' => 'No response from the destination server.',
            'url' => $url
        ]);
    }

    // Extract headers and body from the cURL response
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $body = substr($response, $headerSize);

    curl_close($ch);

    // Return the body of the response, and set the appropriate content type
    header("Content-Type: " . $contentType);
    http_response_code(200); // Indicate successful response
    return $body;
}

// Main logic for handling the incoming request

// Updated logic to extract target URL directly from the request path
$scriptName = $_SERVER['SCRIPT_NAME'];
$requestUri = $_SERVER['REQUEST_URI'];
$targetUrl = substr($requestUri, strlen($scriptName) + 1); // Remove script name and trailing slash

// Validate the presence of the target URL
if (empty($targetUrl)) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => true, 'message' => 'Target URL is missing']); // Return an error if the target URL is not provided
    exit;
}

// Clean and validate the target URL
$validatedUrl = cleanAndValidateUrl($targetUrl);
if (!$validatedUrl) {
    http_response_code(400); // Bad Request
    echo json_encode([
        'error' => true,
        'message' => 'Invalid URL format',
        'url' => $targetUrl
    ]); // Return a JSON response indicating that the URL format is invalid
    exit;
}

// Check if the domain of the URL is resolvable
if (!resolveDomain($validatedUrl)) {
    http_response_code(404); // Not Found
    echo json_encode([
        'error' => true,
        'message' => 'The domain cannot be resolved or is unreachable.',
        'domain' => parse_url($validatedUrl, PHP_URL_HOST)
    ]); // Return a JSON error message if the domain is not resolvable
    exit;
}

// Forward the request to the validated target URL and capture the response
$response = forwardRequest($validatedUrl);

// Handle any errors returned by the forwardRequest function
if (strpos($response, '{"error":true') !== false) {
    echo $response; // Return the error response from the forwardRequest function
    exit;
}

// If the response from the target server is empty, return an error message
if (empty($response)) {
    http_response_code(500); // Internal Server Error
    echo json_encode([
        'error' => true,
        'message' => 'The target server did not respond or the response was empty.',
        'url' => $validatedUrl
    ]); // Return a JSON error message if the response is empty
    exit;
}

echo $response; // Return the successful response from the target server
?>
