<?php  

// Always send CORS headers  
header("Access-Control-Allow-Origin: *");  
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");  
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Forwarded-For");  

// Handle preflight  
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

function forwardRequest($url, $scriptName) {  
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
            'body' => 'Proxy Error: ' . curl_error($ch)  
        ];  
    }  

    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);  
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);  
    $headers = substr($response, 0, $headerSize);  
    $body = substr($response, $headerSize);  

    // If redirect  
    if ($httpCode >= 300 && $httpCode < 400) {  
        preg_match('/Location:\s*(.+)/i', $headers, $matches);  
        $location = trim($matches[1] ?? '');  
        curl_close($ch);  

        if ($location) {  
            // Buat redirect tetap lewat proxy  
            $proxiedLocation = $scriptName . '/' . $location;

            // Kirim ulang CORS headers  
            header("Access-Control-Allow-Origin: *");  
            header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");  
            header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Forwarded-For");  

            header("Location: $proxiedLocation", true, 302);  
            exit;  
        }  
    }  

    // Extract content-type  
    if (preg_match('/Content-Type:\s*([^\r\n]+)/i', $headers, $matches)) {  
        header("Content-Type: " . trim($matches[1]));  
    } else {  
        header("Content-Type: application/octet-stream");  
    }  

    curl_close($ch);  

    http_response_code($httpCode);  
    echo $body;  
    exit;  
}  

// MAIN  
$scriptName = $_SERVER['SCRIPT_NAME'];  
$requestUri = $_SERVER['REQUEST_URI'];  
$targetUrl = substr($requestUri, strlen($scriptName) + 1);  

// If no URL  
if (empty($targetUrl)) {  
    http_response_code(400);  
    header("Content-Type: application/json");  
    echo json_encode(['error' => true, 'message' => 'Target URL is missing']);  
    exit;  
}  

// Validate URL  
$validatedUrl = cleanAndValidateUrl($targetUrl);  
if (!$validatedUrl) {  
    http_response_code(400);  
    header("Content-Type: application/json");  
    echo json_encode(['error' => true, 'message' => 'Invalid URL']);  
    exit;  
}  

// Check domain  
if (!resolveDomain($validatedUrl)) {  
    http_response_code(404);  
    header("Content-Type: application/json");  
    echo json_encode(['error' => true, 'message' => 'Domain cannot be resolved']);  
    exit;  
}  

// Forward  
$result = forwardRequest($validatedUrl, $scriptName);  

// If there was a cURL error  
if (!empty($result['error'])) {  
    http_response_code($result['status']);  
    header("Content-Type: text/plain");  
    echo $result['body'];  
    exit;  
}
