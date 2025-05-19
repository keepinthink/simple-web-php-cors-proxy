<?php
// Set header CORS untuk mengizinkan akses dari semua origin
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Max-Age: 3600");

// Tangani preflight request (OPTIONS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Ambil URL target dari parameter ?url=
$targetUrl = $_GET['url'] ?? null;
if (!$targetUrl) {
    http_response_code(400);
    echo json_encode(['error' => 'Parameter ?url= harus disediakan.']);
    exit();
}

// Ambil metode dan body dari request
$method = $_SERVER['REQUEST_METHOD'];
$body = file_get_contents('php://input');

// Inisialisasi cURL
$ch = curl_init();

// Set opsi cURL
curl_setopt($ch, CURLOPT_URL, $targetUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Kembalikan sebagai string
curl_setopt($ch, CURLOPT_HEADER, true); // Sertakan header dalam respons
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // Nonaktifkan redirect otomatis
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method); // Gunakan metode sesuai

// Jika POST, PUT, DELETE, kirim body data
if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
}

// Set header Content-Type sesuai request asli
$contentType = $_SERVER['CONTENT_TYPE'] ?? 'application/json';
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: $contentType"
]);

// Jalankan cURL
$response = curl_exec($ch);

// Tangani error jika terjadi
if (curl_errno($ch)) {
    http_response_code(500);
    echo json_encode([
        'error' => curl_error($ch)
    ]);
} else {
    // Ambil status kode HTTP dari response
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    // Ambil URL akhir (final URL) setelah redirect
    $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

    // Jika ada redirect, tampilkan final URL dan response
    if ($httpCode == 301 || $httpCode == 302) {
        echo json_encode([
            'status_code' => $httpCode,
            'final_url' => $finalUrl,
            'response' => $response
        ]);
    } else {
        http_response_code($httpCode);
        echo $response; // Respons dari URL akhir
    }
}

// Tutup cURL
curl_close($ch);
