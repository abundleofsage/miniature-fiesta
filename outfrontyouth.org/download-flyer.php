<?php
// This script is the single source for all calendar-related actions:
// 1. Provides JSON data to the main website's calendar.
// 2. Generates a PDF flyer for download.

// --- ACTION REQUIRED ---
// Paste your private Nextcloud iCal subscription URL here.
$ical_url = 'https://cloud.outfrontyouth.org/remote.php/dav/public-calendars/o4BqHRSHaDJjiqjs?export'; // <-- PASTE YOUR URL HERE


// --- No need to edit below this line ---

// --- Configuration for default event info ---
$regular_time_string = '5:30 PM - 7:00 PM';
$regular_location_string = '128 S. Union Ave, Pueblo, CO 81003';

// --- Data Fetching and Parsing (Common to all actions) ---
function get_calendar_events($url) {
    $ical_raw = @file_get_contents($url);
    $events = [];
    if (!$ical_raw) return [];

    $ical_unfolded = str_replace("\r\n ", '', $ical_raw);

    $lines = explode("\n", $ical_unfolded);
    $current_event = null;
    foreach ($lines as $line) {
        $line = trim($line);
        if (strpos($line, 'BEGIN:VEVENT') === 0) {
            $current_event = ['summary' => '', 'date' => '', 'description' => '', 'location' => '', 'startTime' => null, 'endTime' => null];
        } elseif (strpos($line, 'END:VEVENT') === 0) {
            if ($current_event !== null && !empty($current_event['date'])) {
                $replacements = ['\\n', '\\,', '\\;'];
                $clean_chars = ["\n", ',', ';'];
                $current_event['summary'] = str_replace($replacements, $clean_chars, $current_event['summary']);
                $current_event['description'] = str_replace($replacements, $clean_chars, $current_event['description']);
                $current_event['location'] = str_replace($replacements, $clean_chars, $current_event['location']);
                $events[] = $current_event;
            }
            $current_event = null;
        } elseif ($current_event !== null) {
            $parts = explode(':', $line, 2);
            if (count($parts) < 2) continue;
            $key = $parts[0]; $value = $parts[1];
            if (strpos($key, 'SUMMARY') === 0) $current_event['summary'] = $value;
            if (strpos($key, 'DTSTART') === 0) {
                $date_str = substr($value, 0, strpos($value, 'T') ?: strlen($value));
                $current_event['date'] = date("Y-m-d", strtotime($date_str));
                $current_event['startTime'] = strpos($value, 'T') !== false ? date("g:i A", strtotime($value)) : null;
            }
            if (strpos($key, 'DTEND') === 0) $current_event['endTime'] = strpos($value, 'T') !== false ? date("g:i A", strtotime($value)) : null;
            if (strpos($key, 'DESCRIPTION') === 0) $current_event['description'] = $value;
            if (strpos($key, 'LOCATION') === 0) $current_event['location'] = $value;
        }
    }
    return $events;
}

// --- Action Router ---
if (isset($_GET['json']) && $_GET['json'] === 'true') {
    header('Content-Type: application/json');
    $all_events = get_calendar_events($ical_url);
    echo json_encode($all_events);
    exit;
}

// --- PDF and Print Logic (for flyer generation) ---
require('fpdf/fpdf.php');

$month_name = isset($_GET['month']) ? filter_var($_GET['month'], FILTER_SANITIZE_STRING) : date('F');
$year = isset($_GET['year']) ? filter_var($_GET['year'], FILTER_SANITIZE_NUMBER_INT) : date('Y');
$month_num = date('m', strtotime("$month_name 1, $year"));
$use_color_logo = isset($_GET['color']) && $_GET['color'] === 'true'; // Check for the new parameter

$all_events = get_calendar_events($ical_url);
$eventsThisMonth = array_filter($all_events, function($event) use ($month_num, $year) {
    return date('m', strtotime($event['date'])) == $month_num && date('Y', strtotime($event['date'])) == $year;
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
        if (@getimagesize($logo_to_use)) {
            $this->Image($logo_to_use, 15, 14, 95);
        }

        if ($this->qr_code_image_url && @getimagesize($this->qr_code_image_url)) {
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
        }
        $this->SetFont('Helvetica', '', 11);
        $this->Ln(15); 
		$this->SetFillColor(230);
        $this->MultiCell(142, 6, 'Looking for a supportive community? Outfront is a welcoming and fun group for LGBTQ+ youth and allies (ages 13-20) to connect, share experiences, and build friendships. Groups are confidential. What\'s shared here stays here. Join us!', 1, 'C', true);

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
    if ($this->fb_qr_code_image_url && @getimagesize($this->fb_qr_code_image_url)) {
        // Place the QR Code, making it the primary visual element on the left
        $this->Image($this->fb_qr_code_image_url, 15, $current_y, 15, 15, 'PNG', 'https://facebook.com/outfrontyouth');
        
        // Add a single, simple call to action next to it
        $this->SetFont('Helvetica', '', 8);
        // The SetXY function lets us precisely place the text centered vertically with the QR code
        $this->SetXY(32, $current_y + 5); 
        $this->Cell(50, 5, 'Find us on Facebook', 0, 0, 'L');
    }

    // RIGHT COLUMN: Program info and document ID, right-aligned and stacked cleanly
    $this->SetY($current_y); // Reset Y position to align with the top of the QR code
    
    // SCEA Affiliation (most important info, bold)
    $this->SetFont('Helvetica', 'B', 9);
    $this->Cell(0, 5, "Outfront is a program of the Southern Colorado Equality Alliance", 0, 2, 'R', false, 'https://www.socoequality.org/outfront');
    
    // Friendly closing message
    $this->SetFont('Helvetica', '', 8);
    $this->Cell(0, 5, "Check back next month for more fun!", 0, 2, 'R');
    
    // Flyer ID (smallest, italicized, and labeled to be unobtrusive)
    $this->SetFont('Helvetica', 'I', 7); 
    $this->Cell(0, 5, "Flyer ID: outfront-flyer-" . strtolower($this->month_name_display) . '-' . $this->year_display, 0, 0, 'R');
}

    
/*    function Footer() {
        $this->SetY(-35);
        if ($this->fb_qr_code_image_url && @getimagesize($this->fb_qr_code_image_url)) {
            $this->Image($this->fb_qr_code_image_url, 15, $this->GetY() - 2, 15, 15, 'PNG', 'https://facebook.com/outfrontyouth');
        }
        $this->SetFont('Helvetica','B',9);
        $this->SetX(32);
        $this->Cell(0,5,"Find us on Facebook!",0,1);
        $this->SetFont('Helvetica','',9);
        $this->SetX(32);
        $this->Cell(0,5,"facebook.com/outfrontyouth",0,1);
        $this->SetFont('Helvetica','B',12);
        $this->SetY(-35);
        $this->Cell(0,5,"Check back next month for more fun!",0,1,'R');
        $this->SetFont('Helvetica','B',9);
        $this->SetY(-25);
        $this->Cell(0,5,"Outfront is a program of the Southern Colorado Equality Alliance",0,1,'R',0,'https://www.socoequality.org/outfront');
	$this->Ln(2);
        $this->SetFont('Helvetica','',8);
	//$this->Cell(0,5,"outfront-flyer-" . strtolower($this->month_name_display) . '-' . $this->year_display;,0,1,'R')
	$this->Cell(0, 5, "ID: outfront-flyer-" . strtolower($this->month_name_display) . '-' . $this->year_display, 0, 1, 'R');
    }
*/
}

$pdf = new PDF();
$pdf->use_color_logo = $use_color_logo; // Pass the choice to the PDF class
$pdf->qr_code_image_url = $qr_code_image_url;
$pdf->fb_qr_code_image_url = $fb_qr_code_image_url;
$pdf->month_name_display = $month_name_display;
$pdf->year_display = $year;
$pdf->SetTitle('Outfront Flyer - ' . $month_name_display . ' ' . $year);
$pdf->SetAuthor('Outfront Youth Group');
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
        
        if ($time_string !== $regular_time_string) {
            $pdf->MultiCell(0, 5, "    Time: " . htmlspecialchars_decode($time_string), 0, 'L');
        }
        if ($event['location'] && trim($event['location']) !== $regular_location_string) {
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
