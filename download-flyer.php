<?php
// This script is the single source for all calendar-related actions:
// 1. Provides JSON data to the main website's calendar.
// 2. Generates a PDF flyer for download.

require_once('config.php'); // Include the central configuration file
require_once('calendar-functions.php'); // For app_log and terminate_script_with_error, get_calendar_events

// --- No need to edit below this line (mostly) ---
// $ical_url is now ICAL_URL from config.php
// $regular_time_string is now DEFAULT_EVENT_TIME_STRING from config.php
// $regular_location_string is now DEFAULT_EVENT_LOCATION_STRING from config.php


// Initialize error reporting for development; consider changing for production
ini_set('display_errors', 0); // Don't display errors directly to user in production
error_reporting(E_ALL);
// Set a global error handler to catch fatal errors and log them
set_error_handler(function($severity, $message, $file, $line) {
    app_log('ERROR', "Unhandled error: {$message}", ['file' => $file, 'line' => $line, 'severity' => $severity]);
    // Don't call exit here if you want PHP's default handler to take over for fatal errors
    // but for notices/warnings, you might want to just log and continue.
    // For this script, if any error occurs, it's probably best to terminate gracefully.
    if (error_reporting() & $severity) { // Only if the error level is reportable
         // Clear any output that might have already been sent
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code(500);
        header('Content-Type: text/plain');
        echo "An unexpected error occurred. Please try again later.";
        exit;
    }
    return false; // Let PHP's internal error handler run if it wasn't a handled severity
});


// --- Action Router ---
if (isset($_GET['json']) && $_GET['json'] === 'true') {
    header('Content-Type: application/json');
    $all_events = get_calendar_events(ICAL_URL); // Use ICAL_URL from config
    if ($all_events === false) {
        terminate_script_with_error(
            '{"error": "Could not retrieve calendar events."}',
            'get_calendar_events failed for JSON output.',
            ['url' => ICAL_URL], // Log ICAL_URL
            503 // Service Unavailable
        );
    }
    echo json_encode($all_events);
    exit;
}

// --- PDF and Print Logic (for flyer generation) ---
require('fpdf/fpdf.php'); // FPDF might have its own error handling or produce warnings.

// --- Input Validation for Month and Year ---
$current_year = (int)date('Y');
$min_year = $current_year - 5; // Allow 5 years in the past
$max_year = $current_year + 5; // Allow 5 years in the future

// Validate Year
$year_input = $_GET['year'] ?? date('Y');
if (!filter_var($year_input, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min_year, 'max_range' => $max_year]])) {
    app_log('WARNING', "Invalid year provided for flyer generation.", ['year_input' => $year_input]);
    terminate_script_with_error(
        "Error: Invalid year provided. Please specify a year between $min_year and $max_year.",
        "Invalid year parameter.",
        ['year_provided' => $year_input],
        400
    );
}
$year = (int)$year_input;

// Validate Month
$month_input = $_GET['month'] ?? date('F');
// Sanitize first to remove potentially harmful characters, though validation is key
$month_sanitized = filter_var($month_input, FILTER_SANITIZE_STRING);

$allowed_months = [
    'january', 'february', 'march', 'april', 'may', 'june',
    'july', 'august', 'september', 'october', 'november', 'december'
];
if (!in_array(strtolower($month_sanitized), $allowed_months, true)) {
    // Try to parse it as a number (1-12) as a fallback, e.g. if "01" or "1" is passed
    $month_number_from_input = filter_var($month_sanitized, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 12]]);
    if ($month_number_from_input === false) {
        app_log('WARNING', "Invalid month name or number provided for flyer generation.", ['month_input' => $month_input]);
        terminate_script_with_error(
            "Error: Invalid month provided. Please use a full month name (e.g., 'January') or a number (1-12).",
            "Invalid month parameter.",
            ['month_provided' => $month_input],
            400
        );
    }
    // Convert valid month number back to full month name for consistency
    $month_name = date('F', mktime(0, 0, 0, $month_number_from_input, 1));
} else {
    $month_name = ucfirst(strtolower($month_sanitized)); // Ensure consistent capitalization
}

// Now that $month_name and $year are validated, proceed to get month_num
$month_num = date('m', strtotime("$month_name 1, $year"));
if (!$month_num) { // Should not happen if above validation is correct, but as a safeguard
    app_log('ERROR', "Failed to derive month number from validated month and year.", ['month_name' => $month_name, 'year' => $year]);
    terminate_script_with_error("Internal error processing date.", "strtotime failed for validated month/year.", [], 500);
}


$use_color_logo = isset($_GET['color']) && $_GET['color'] === 'true';

$all_events = get_calendar_events(ICAL_URL); // Use ICAL_URL from config
if ($all_events === false) {
    terminate_script_with_error(
        "Error: Could not retrieve calendar events to generate the flyer. Please check the calendar source or try again later.",
        "get_calendar_events failed for PDF generation.",
        ['url' => ICAL_URL] // Log ICAL_URL
    );
}

$eventsThisMonth = array_filter($all_events, function($event) use ($month_num, $year) {
    // Add error handling for strtotime if event date is malformed
    $event_date_timestamp = strtotime($event['date']);
    if ($event_date_timestamp === false) {
        app_log('WARNING', "Invalid date format in event, skipping.", ['event_date' => $event['date'], 'event_summary' => $event['summary'] ?? 'N/A']);
        return false;
    }
    return date('m', $event_date_timestamp) == $month_num && date('Y', $event_date_timestamp) == $year;
});
usort($eventsThisMonth, function($a, $b) { return strtotime($a['date']) - strtotime($b['date']); });

$qr_url = "https://codes.outfrontyouth.org/go/outfront-flyer-".strtolower($month_name)."-".$year;
$qr_code_image_url = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' . urlencode($qr_url);
$fb_qr_url = "https://facebook.com/outfrontyouth";
$fb_qr_code_image_url = 'https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=' . urlencode($fb_qr_url);
$month_name_display = ucfirst($month_name);

// --- PDF Creation with FPDF ---
class PDF extends FPDF {
    public $qr_code_image_url;
    public $fb_qr_code_image_url;
    public $month_name_display;
    public $year_display;
    public $use_color_logo; // New property to hold the choice

    function Header() {
        $this->SetFont('Helvetica', 'B', 30);
        $this->Cell(0, 3, $this->month_name_display . ' ' . $this->year_display, 0, 0, 'R');
        
        // UPDATED: Logic to choose which logo to display
        $logo_to_use = $this->use_color_logo ? 'outfront-logo.png' : 'outfront-logo-bw.png';
        if (file_exists($logo_to_use) && getimagesize($logo_to_use) !== false) {
            $this->Image($logo_to_use, 15, 14, 95);
        } else {
            app_log('WARNING', "Main logo not found or invalid image: " . $logo_to_use, ['context' => 'PDF Header']);
        }

        if ($this->qr_code_image_url) {
            // Check if QR code URL is accessible and a valid image
            // Use a custom function to check remote image to handle potential errors gracefully
            $qr_image_check = @getimagesize($this->qr_code_image_url); // Suppress errors for remote check, log manually
            if ($qr_image_check !== false) {
                $link_url = 'https://codes.outfrontyouth.org/go/outfront-flyer-' . strtolower($this->month_name_display) . '-' . $this->year_display;
                $this->Image($this->qr_code_image_url, 168, 32, 26, 26, 'PNG', $link_url);

                $currentY = $this->GetY();
                $this->SetFont('Helvetica','',8);
                $this->SetY(27);
                $this->SetX(168);
                $this->Cell(26, 5, 'outfrontyouth.org', 0, 1, 'C');
                $this->SetY(58);
                $this->SetX(168);
                $this->Cell(26, 5, 'Scan for more info!', 0, 1, 'C');
                $this->SetY($currentY);
            } else {
                app_log('WARNING', "QR code image not found or invalid.", ['url' => $this->qr_code_image_url, 'context' => 'PDF Header']);
            }
        }
        $this->SetFont('Helvetica', '', 11);
        $this->Ln(15);
		$this->SetFillColor(230);
        $this->MultiCell(142, 6, 'Looking for a supportive community? OutFront is a welcoming and fun group for LGBTQ+ youth and allies (ages 13-20) to connect, share experiences, and build friendships. Groups are confidential. What\'s shared here stays here. Join us!', 1, 'C', true);

        $this->SetFont('Helvetica', 'B', 10);
        $this->Ln(2); 
        $this->Cell(142, 5, 'Unless otherwise noted, all events are at our regular location:', 0, 1, 'C');
        $this->Cell(142, 5, '128 S. Union Ave, Pueblo, CO from 5:30 to 7:00PM', 0, 1, 'C');
	$this->SetLineWidth(0.8);
        $this->Line(15, 64, 195, 64);
        $this->Ln(4);
	//$this->SetFont('Helvetica','',10);
        //$this->MultiCell(0,15,"Unless otherwise noted, all events are at our regular location: 128 S. Union Ave, Pueblo, CO from 5:30 to 7:00PM",0,'L');
        //$this->Ln(2);
    }
function Footer() {
    // Set the position 25 points from the bottom of the page
    $this->SetY(-25);

    // Draw a horizontal line to visually separate the footer
    $this->SetLineWidth(0.8);
    $this->Line(15, $this->GetY(), 195, $this->GetY());
    $this->Ln(2); // Add a little space after the line

    // --- Footer Content ---
    $current_y = $this->GetY();

    // LEFT COLUMN: Facebook QR Code and simplified text
    if ($this->fb_qr_code_image_url) {
        $fb_qr_image_check = @getimagesize($this->fb_qr_code_image_url); // Suppress errors for remote check
        if ($fb_qr_image_check !== false) {
            // Place the QR Code, making it the primary visual element on the left
            $this->Image($this->fb_qr_code_image_url, 15, $current_y, 15, 15, 'PNG', 'https://facebook.com/outfrontyouth');

            // Add a single, simple call to action next to it
            $this->SetFont('Helvetica', '', 8);
            // The SetXY function lets us precisely place the text centered vertically with the QR code
            $this->SetXY(32, $current_y + 5);
            $this->Cell(50, 5, 'Find us on Facebook', 0, 0, 'L');
        } else {
            app_log('WARNING', "Facebook QR code image not found or invalid.", ['url' => $this->fb_qr_code_image_url, 'context' => 'PDF Footer']);
        }
    }

    // RIGHT COLUMN: Program info and document ID, right-aligned and stacked cleanly
    $this->SetY($current_y); // Reset Y position to align with the top of the QR code
    
    // SCEA Affiliation (most important info, bold)
    $this->SetFont('Helvetica', 'B', 9);
    $this->Cell(0, 5, "OutFront is a program of the Southern Colorado Equality Alliance", 0, 2, 'R', false, 'https://www.socoequality.org/outfront');
    
    // Friendly closing message
    $this->SetFont('Helvetica', '', 8);
    $this->Cell(0, 5, "Check back next month for more fun!", 0, 2, 'R');
    
    // Flyer ID (smallest, italicized, and labeled to be unobtrusive)
    $this->SetFont('Helvetica', 'I', 7); 
    $this->Cell(0, 5, "Flyer ID: outfront-flyer-" . strtolower($this->month_name_display) . '-' . $this->year_display, 0, 0, 'R');
}
}

$pdf = new PDF();
$pdf->use_color_logo = $use_color_logo; // Pass the choice to the PDF class
$pdf->qr_code_image_url = $qr_code_image_url;
$pdf->fb_qr_code_image_url = $fb_qr_code_image_url;
$pdf->month_name_display = $month_name_display;
$pdf->year_display = $year;
$pdf->SetTitle('OutFront Flyer - ' . $month_name_display . ' ' . $year);
$pdf->SetAuthor('OutFront Youth Group');
$pdf->SetMargins(15, 17, 15);
$pdf->AddPage();

if (empty($eventsThisMonth)) {
    $pdf->SetFont('Helvetica','',14);
    $pdf->Cell(0,10, 'No events scheduled for this month.',0,1,'C');
} else {
    foreach($eventsThisMonth as $event){
        $date_string = date('l \t\h\e jS', strtotime($event['date']));
        $time_string = $event['startTime'] ? $event['startTime'] . ' - ' . $event['endTime'] : 'All-day event';

        $pdf->SetFont('Helvetica','B',14);
        $pdf->MultiCell(0, 6, htmlspecialchars_decode($date_string) . ' - ' . htmlspecialchars_decode($event['summary']), 0, 'L');
        $pdf->SetFont('Helvetica','',11);
        
        // Use constants from config.php
        if ($time_string !== DEFAULT_EVENT_TIME_STRING) {
            $pdf->MultiCell(0, 5, "    Time: " . htmlspecialchars_decode($time_string), 0, 'L');
        }
        if ($event['location'] && trim($event['location']) !== DEFAULT_EVENT_LOCATION_STRING) {
            $pdf->MultiCell(0, 5, "    Location: " . htmlspecialchars_decode($event['location']), 0, 'L');
        }

        if($event['description']) {
            $pdf->SetFont('Helvetica','I',10);
            $pdf->MultiCell(0, 5, htmlspecialchars_decode($event['description']), 0, 'L');
        }
        $pdf->Ln(5);
    }
}
    
$pdf->Output('D', 'outfront-flyer-'.strtolower($month_name).'-'.$year.'.pdf');
?>
