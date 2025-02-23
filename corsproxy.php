<?php  
// Function to clean and validate the entered URL  
function clean_url($url) {  
    // Remove unnecessary characters (e.g., spaces) and check if the URL is valid  
    $url = trim($url);  
    if (!filter_var($url, FILTER_VALIDATE_URL)) {  
        return false;  
    }  
    return $url;  
}  
  
// Get the URL provided in the path  
$path = $_SERVER['REQUEST_URI'];  
  
// Remove '/corsproxy.php' from the path to get the target URL  
$target_url = str_replace('/corsproxy.php/', '', $path);  
  
// Ensure the target URL is valid  
$target_url = clean_url($target_url);  
if (!$target_url) {  
    echo "ngopi dulu bwang.....";  
    exit;  
}  
  
// Set CORS header to allow access from other domains  
header('Access-Control-Allow-Origin: *');  
header('X-Forwarded-For: ' . $_SERVER['REMOTE_ADDR']); // Send the client's original IP in the header  
  
// Using cURL to make a request to the target URL  
$ch = curl_init();  
  
// Set cURL options  
curl_setopt($ch, CURLOPT_URL, $target_url);            // Target URL  
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);        // Return the response as a string  
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);        // Follow redirects if any  
curl_setopt($ch, CURLOPT_HEADER, false);               // Do not include header in the output  
  
// Execute cURL and get the response  
$response = curl_exec($ch);  
  
// If there's a cURL error, display it  
if (curl_errno($ch)) {  
    echo 'Curl error: ' . curl_error($ch);  
    curl_close($ch);  
    exit;  
}  
  
// Close the cURL session  
curl_close($ch);  
  
// Display the response result  
echo $response;  
?>
