<?php
// This script acts as a simple proxy to bypass CORS issues using cURL.

// IMPORTANT: Specify the allowed origin. For best security, this should be your website's domain.
header("Access-Control-Allow-Origin: https://outfrontyouth.org"); // This should be fine for now.
header("Content-Type: text/plain"); // Serve as plain text. Consider if other types are needed.

require_once('calendar-functions.php'); // For app_log and terminate_script_with_error

// Initialize error reporting
ini_set('display_errors', 0);
error_reporting(E_ALL);
set_error_handler(function($severity, $message, $file, $line) {
    app_log('ERROR', "Unhandled error in resource-proxy: {$message}", ['file' => $file, 'line' => $line, 'severity' => $severity]);
    if (error_reporting() & $severity) {
        if (ob_get_level() > 0) ob_end_clean();
        http_response_code(500);
        echo "An unexpected error occurred in the proxy service.";
        exit;
    }
    return false;
});

// The URL of the resource to fetch is passed as a query parameter.
$url = $_GET['url'] ?? null; // Use null coalescing operator

// Basic validation to ensure a URL is provided.
// Stricter validation (allowlist) will be part of the security step.
if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
    terminate_script_with_error(
        "Error: A valid 'url' parameter is required for the proxy service.",
        "Invalid or missing 'url' parameter in resource-proxy.",
        ['url_provided' => $url],
        400 // Bad Request
    );
}

// Check if cURL is installed
if (!function_exists('curl_init')) {
    terminate_script_with_error(
        "Error: The proxy service is currently unavailable due to a server configuration issue (cURL missing).",
        "cURL extension is not installed or enabled on the server."
        // No need for HTTP status code here as terminate_script_with_error defaults to 500
    );
}

// Initialize a cURL session
$ch = curl_init();
if ($ch === false) {
    terminate_script_with_error(
        "Error: Could not initialize the proxy service (cURL init failed).",
        "curl_init() failed."
    );
}

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
    curl_close($ch); // Ensure cURL handle is closed
    terminate_script_with_error(
        "Error: The proxy service could not retrieve the requested resource (cURL execution error).",
        "cURL error during execution for URL: " . $url,
        ['curl_error' => $error, 'url' => $url],
        502 // Bad Gateway
    );
}

if ($httpcode !== 200) {
    curl_close($ch); // Ensure cURL handle is closed
    terminate_script_with_error(
        "Error: The remote server hosting the resource responded with an error (HTTP " . $httpcode . ").",
        "Remote server returned non-200 HTTP status code.",
        ['http_code' => $httpcode, 'url' => $url, 'response_preview' => substr($content, 0, 200)],
        502 // Bad Gateway - as we are the gateway to that resource
    );
}

// Output the fetched content
// It's already plain text as per header set at the beginning.
echo $content;

?>
