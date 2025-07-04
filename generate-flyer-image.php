<?php
// generate-flyer-image.php
// Generates a PNG image of the monthly flyer by converting the PDF.

require_once('calendar-functions.php'); // For app_log and terminate_script_with_error

// --- Input Validation for Month and Year ---
$current_year_img = (int)date('Y');
$min_year_img = $current_year_img - 5; // Allow 5 years in the past
$max_year_img = $current_year_img + 5; // Allow 5 years in the future

// Validate Year
$year_input_img = $_GET['year'] ?? date('Y');
if (!filter_var($year_input_img, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min_year_img, 'max_range' => $max_year_img]])) {
    app_log('WARNING', "Invalid year provided for flyer image generation.", ['year_input' => $year_input_img]);
    terminate_script_with_error(
        "Error: Invalid year provided. Please specify a year between $min_year_img and $max_year_img.",
        "Invalid year parameter for flyer image generation.",
        ['year_provided' => $year_input_img],
        400
    );
}
$year = (int)$year_input_img; // Use $year as it's used later

// Validate Month
$month_input_img = $_GET['month'] ?? date('F');
$month_sanitized_img = filter_var($month_input_img, FILTER_SANITIZE_STRING);

$allowed_months_img = [
    'january', 'february', 'march', 'april', 'may', 'june',
    'july', 'august', 'september', 'october', 'november', 'december'
];
if (!in_array(strtolower($month_sanitized_img), $allowed_months_img, true)) {
    $month_number_from_input_img = filter_var($month_sanitized_img, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 12]]);
    if ($month_number_from_input_img === false) {
        app_log('WARNING', "Invalid month name or number provided for flyer image generation.", ['month_input' => $month_input_img]);
        terminate_script_with_error(
            "Error: Invalid month provided. Please use a full month name (e.g., 'January') or a number (1-12).",
            "Invalid month parameter for flyer image generation.",
            ['month_provided' => $month_input_img],
            400
        );
    }
    $month_name = date('F', mktime(0, 0, 0, $month_number_from_input_img, 1)); // Use $month_name
} else {
    $month_name = ucfirst(strtolower($month_sanitized_img)); // Use $month_name
}
// No need for $month_num here as it's passed to download-flyer.php which does its own validation

$use_color_logo = isset($_GET['color']) && $_GET['color'] === 'true';


// Initialize error reporting
ini_set('display_errors', 0); // Don't display errors directly to user
error_reporting(E_ALL);
set_error_handler(function($severity, $message, $file, $line) {
    // Log all errors
    app_log('ERROR', "Unhandled error: {$message}", ['file' => $file, 'line' => $line, 'severity' => $severity]);
    // If the error is severe enough to halt script execution
    if (error_reporting() & $severity) {
        // Clear any output that might have already been sent
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code(500);
        header('Content-Type: text/plain'); // Ensure plain text for error message
        echo "An unexpected error occurred while generating the image. Please try again later.";
        exit;
    }
    return false; // Let PHP's internal error handler run if not handled
});

// --- Check for Imagick ---
if (!extension_loaded('imagick')) {
    terminate_script_with_error(
        "Error: Image processing library (Imagick) is not available. Please contact the site administrator.",
        "Imagick PHP extension is not installed or enabled."
    );
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
$pdf_content = file_get_contents($pdf_url); // Removed @ suppression

if ($pdf_content === false) {
    $last_error = error_get_last();
    $error_details = $last_error ? $last_error['message'] : 'Unknown reason.';
    terminate_script_with_error(
        "Error: Could not fetch the flyer PDF content to convert to an image. Please ensure the flyer can be generated.",
        "Failed to fetch PDF content from local URL: " . $pdf_url,
        ['php_error' => $error_details]
    );
}

// --- Convert PDF to PNG using Imagick ---
try {
    $imagick = new Imagick();
    $imagick->setResolution(300, 300); // Set DPI for good quality

    // Read the PDF content from the string
    if (!$imagick->readImageBlob($pdf_content)) {
        // Attempt to get more specific Imagick error if available
        $imagickError = 'Imagick generic error during readImageBlob.';
        // ImagickException might not be thrown for all readImageBlob failures,
        // so we check if there's a more specific message.
        // This part is speculative as Imagick's error reporting can vary.
        // We'll rely on the generic exception's message primarily.
        throw new Exception("Imagick failed to read PDF blob. " . $imagickError);
    }

    $imagick->setIteratorIndex(0); // Select the first page
    $imagick->setImageBackgroundColor('white'); // Set a white background

    // Flatten image to apply the background color and remove alpha channel
    // This can throw an ImagickException on error
    $imagick = $imagick->flattenImages();
    if (!$imagick) { // Check if flattenImages returned a valid object
        throw new Exception("Imagick failed to flatten images.");
    }

    if (!$imagick->setImageFormat('png')) { // Set output format to PNG
        throw new Exception("Imagick failed to set image format to PNG.");
    }

    // --- Output PNG ---
    $filename_month = strtolower($month_name);
    $filename_year = $year;
    $filename_color_suffix = $use_color_logo ? '-color' : '';
    $download_filename = "outfront-flyer-{$filename_month}-{$filename_year}{$filename_color_suffix}.png";

    header('Content-Type: image/png');
    header('Content-Disposition: attachment; filename="' . $download_filename . '"');
    $imageData = $imagick->getImageBlob();

    if ($imageData === false || $imageData === null || empty($imageData)) {
        throw new Exception("Imagick failed to get image blob or generated empty image.");
    }

    header('Content-Type: image/png');
    header('Content-Disposition: attachment; filename="' . $download_filename . '"');
    echo $imageData;

    // --- Cleanup ---
    $imagick->clear();
    $imagick->destroy();

} catch (ImagickException $e) { // Catch specific Imagick exceptions first
    terminate_script_with_error(
        "Error: Image processing failed (Imagick library error). Please try again or contact support.",
        "ImagickException during PNG generation: " . $e->getMessage(),
        ['trace' => $e->getTraceAsString()]
    );
} catch (Exception $e) { // Catch general exceptions
    terminate_script_with_error(
        "Error: An unexpected error occurred during PNG generation. Please try again or contact support.",
        "Exception during PNG generation: " . $e->getMessage(),
        ['trace' => $e->getTraceAsString()]
    );
}

?>
