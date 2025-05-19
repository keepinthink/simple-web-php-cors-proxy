<?php
// Set CORS headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Ambil URL dari PATH_INFO atau REQUEST_URI
$request_uri = $_SERVER['REQUEST_URI'];
$script_name = $_SERVER['SCRIPT_NAME'];
$path = substr($request_uri, strlen($script_name));

// Decode URL target (misalnya: /https://example.com/api/data)
$target_url = ltrim(urldecode($path), '/');
if (!filter_var($target_url, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid or missing target URL in path"]);
    exit();
}

// Inisialisasi cURL
$ch = curl_init();
$method = $_SERVER['REQUEST_METHOD'];
$input_data = file_get_contents('php://input');

curl_setopt($ch, CURLOPT_URL, $target_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);

// Set headers forwarder
$headers = [
    'X-Forwarded-For: ' . $_SERVER['REMOTE_ADDR'],
    'X-Requested-With: XMLHttpRequest'
];
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

// Set method dan payload
switch ($method) {
    case 'POST':
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $input_data);
        break;
    case 'PUT':
    case 'DELETE':
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $input_data);
        break;
}

// Eksekusi request
$response = curl_exec($ch);
$info = curl_getinfo($ch);
$header_size = $info['header_size'];
$headers_raw = substr($response, 0, $header_size);
$body = substr($response, $header_size);

// Handle redirect 302
if (in_array($info['http_code'], [301, 302])) {
    if (preg_match('/Location:\s*(.*)/i', $headers_raw, $matches)) {
        $location = trim($matches[1]);
        http_response_code(302);
        header("Location: $location");
        echo json_encode([
            'redirect' => true,
            'location' => $location,
            'info' => $info
        ]);
    }
} else {
    http_response_code($info['http_code']);
    header("Content-Type: " . ($info['content_type'] ?? "application/octet-stream"));
    echo $body;
}

curl_close($ch);
?>
