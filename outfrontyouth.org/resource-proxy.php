<?php
// This script acts as a simple proxy to bypass CORS issues using cURL.

// IMPORTANT: Specify the allowed origin. For best security, this should be your website's domain.
header("Access-Control-Allow-Origin: https://outfrontyouth.org");
header("Content-Type: text/plain"); // Serve as plain text

// The URL of the resource to fetch is passed as a query parameter.
$url = $_GET['url'];

// Basic validation to ensure a URL is provided.
if (!isset($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    echo "Error: A valid 'url' parameter is required.";
    exit;
}

// Check if cURL is installed
if (!function_exists('curl_init')) {
    http_response_code(500);
    echo "Error: cURL is not installed on this server. The proxy cannot function.";
    exit;
}

// Initialize a cURL session
$ch = curl_init();

// Set the cURL options
curl_setopt($ch, CURLOPT_URL, $url); // Set the URL to fetch
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // Return the transfer as a string
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow redirects (important for share links)
curl_setopt($ch, CURLOPT_TIMEOUT, 15); // Set a 15-second timeout
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Verify the SSL certificate
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

// Execute the cURL session
$content = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

// Close the cURL session
curl_close($ch);

// Check for cURL errors or non-200 HTTP status codes
if ($error) {
    http_response_code(502); // Bad Gateway
    echo "cURL Error: " . $error;
    exit;
}

if ($httpcode !== 200) {
     http_response_code(502); // Bad Gateway
     echo "Error: The remote server responded with HTTP status code " . $httpcode;
     exit;
}

// Output the fetched content
echo $content;

?>
