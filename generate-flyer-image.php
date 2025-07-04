<?php
// generate-flyer-image.php
// Generates a PNG image of the monthly flyer by converting the PDF.

// --- Parameters ---
$month_name = isset($_GET['month']) ? filter_var($_GET['month'], FILTER_SANITIZE_STRING) : date('F');
$year = isset($_GET['year']) ? filter_var($_GET['year'], FILTER_SANITIZE_NUMBER_INT) : date('Y');
$use_color_logo = isset($_GET['color']) && $_GET['color'] === 'true';

// --- Error Reporting (useful for debugging Imagick issues) ---
ini_set('display_errors', 1);
error_reporting(E_ALL);

// --- Check for Imagick ---
if (!extension_loaded('imagick')) {
    http_response_code(500);
    header('Content-Type: text/plain');
    echo "Error: The Imagick PHP extension is not installed or enabled. Please contact the site administrator.";
    exit;
}

// --- Construct PDF URL ---
// Build the query string for download-flyer.php
$pdf_query_params = http_build_query([
    'month' => $month_name,
    'year' => $year,
    'color' => $use_color_logo ? 'true' : 'false' // Pass color parameter
]);

// It's generally better to use absolute URLs if your server configuration allows file_get_contents with URLs.
// If this server is the same one, using a relative path might be okay, but can be tricky depending on PHP's include_path and current working directory.
// Assuming download-flyer.php is in the same directory.
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$uri = rtrim(dirname($_SERVER['PHP_SELF']), '/\\'); // Get current directory URI
$pdf_url = $protocol . $host . $uri . "/download-flyer.php?" . $pdf_query_params;


// --- Fetch PDF Content ---
$pdf_content = @file_get_contents($pdf_url);

if ($pdf_content === false) {
    http_response_code(500);
    header('Content-Type: text/plain');
    // Try to get more detailed error information if possible
    $error = error_get_last();
    $error_message = "Error: Could not fetch the PDF content from " . htmlspecialchars($pdf_url) . ".";
    if ($error !== null) {
        $error_message .= " PHP Error: " . $error['message'];
    }
    echo $error_message;
    exit;
}

// --- Convert PDF to PNG using Imagick ---
try {
    $imagick = new Imagick();
    $imagick->setResolution(300, 300); // Set DPI for good quality

    // Read the PDF content from the string
    if (!$imagick->readImageBlob($pdf_content)) {
        throw new Exception("Imagick failed to read PDF blob.");
    }

    $imagick->setIteratorIndex(0); // Select the first page
    $imagick->setImageBackgroundColor('white'); // Set a white background
    // Flatten image to apply the background color and remove alpha channel
    $imagick = $imagick->flattenImages(); // This returns a new Imagick object
    $imagick->setImageFormat('png'); // Set output format to PNG

    // --- Output PNG ---
    $filename_month = strtolower($month_name);
    $filename_year = $year;
    $filename_color_suffix = $use_color_logo ? '-color' : '';
    $download_filename = "outfront-flyer-{$filename_month}-{$filename_year}{$filename_color_suffix}.png";

    header('Content-Type: image/png');
    header('Content-Disposition: attachment; filename="' . $download_filename . '"');
    echo $imagick->getImageBlob();

    // --- Cleanup ---
    $imagick->clear();
    $imagick->destroy();

} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: text/plain');
    echo "Error during PNG generation: " . htmlspecialchars($e->getMessage());
    // If $e is an ImagickException, it might have more specific details,
    // but the main message from getMessage() is usually sufficient.
    exit;
}

?>
