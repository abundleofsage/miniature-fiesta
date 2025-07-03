<?php
// This script generates a social media post image for a single calendar event.
// It's designed to work with the same Nextcloud calendar as your flyer script.

// --- ACTION REQUIRED ---
// Paste your private Nextcloud iCal subscription URL here.
$ical_url = 'https://cloud.outfrontyouth.org/remote.php/dav/public-calendars/o4BqHRSHaDJjiqjs?export'; // <-- PASTE YOUR URL HERE

// --- FONT CONFIGURATION ---
// IMPORTANT: You must have a TrueType Font (.ttf) file for this to work.
// Download a font from Google Fonts (e.g., "Fredoka") and place the .ttf file
// in the same directory as this script.
// https://fonts.google.com/specimen/Fredoka
$font_regular_path = __DIR__ . '/Fredoka-Regular.ttf';
$font_bold_path = __DIR__ . '/Fredoka-Bold.ttf';

// Check if font files exist, otherwise stop with an error.
if (!file_exists($font_regular_path) || !file_exists($font_bold_path)) {
    die("Error: Font files not found. Please download Fredoka-Regular.ttf and Fredoka-Bold.ttf and place them in the same directory as this script.");
}


// --- No need to edit below this line ---

// --- Data Fetching and Parsing (Re-used from your original script) ---
require_once('calendar-functions.php');

/**
 * Wraps text to a specific width for GD image functions.
 *
 * @param int $fontSize The font size.
 * @param int $angle The angle of the text.
 * @param string $fontFile The path to the TTF font file.
 * @param string $string The text to wrap.
 * @param int $width The width in pixels to wrap the text to.
 * @return string The wrapped text.
 */
function wrap_text($fontSize, $angle, $fontFile, $string, $width) {
    $ret = '';
    $arr = explode(' ', $string);
    foreach ($arr as $word) {
        $testbox = imagettfbbox($fontSize, $angle, $fontFile, $ret . ' ' . $word);
        if ($testbox[2] > $width && $ret !== '') {
            $ret .= "\n" . $word;
        } else {
            $ret .= ($ret === '' ? '' : ' ') . $word;
        }
    }
    return $ret;
}


// --- Event Selection ---
// This script generates an image for a SINGLE event.
// It finds the event based on a 'date' parameter in the URL.
// Example: yourwebsite.com/generate-post-image.php?date=2025-07-11
// If no date is provided, it will try to find the next upcoming event.

$all_events = get_calendar_events($ical_url);
$target_event = null;

if (isset($_GET['date'])) {
    $target_date = $_GET['date'];
    foreach ($all_events as $event) {
        if ($event['date'] == $target_date) {
            $target_event = $event;
            break;
        }
    }
} else {
    // If no date is specified, find the next upcoming event
    $today = date("Y-m-d");
    usort($all_events, function($a, $b) { return strtotime($a['date']) - strtotime($b['date']); });
    foreach ($all_events as $event) {
        if (strtotime($event['date']) >= strtotime($today)) {
            $target_event = $event;
            break;
        }
    }
}

// If no event is found, display an error image.
if ($target_event === null) {
    header("Content-type: image/png");
    $error_image = imagecreatetruecolor(1200, 1200);
    $bg_color = imagecolorallocate($error_image, 30, 30, 30);
    $text_color = imagecolorallocate($error_image, 255, 255, 255);
    imagefill($error_image, 0, 0, $bg_color);
    imagettftext($error_image, 50, 0, 150, 600, $text_color, $font_bold_path, "Event Not Found");
    imagepng($error_image);
    imagedestroy($error_image);
    exit;
}


// --- Image Generation using GD library ---

// 1. Setup Canvas
$width = 1200;
$height = 1300;
$image = imagecreatetruecolor($width, $height);
$padding = 80;

// --- Define a palette of bright accent colors ---
$accent_colors_palette = [
    [236, 72, 153],  // Hot Pink
    [52, 211, 153],  // Bright Teal
    [251, 191, 36],  // Bright Amber
    [139, 92, 246],  // Bright Violet
    [249, 115, 22]   // Bright Orange
];
// Pick a random color from the palette
$random_color_rgb = $accent_colors_palette[array_rand($accent_colors_palette)];

// 2. Define Colors
$color_bg_dark = imagecolorallocate($image, 23, 23, 23); // Almost black
$color_text_light = imagecolorallocate($image, 245, 245, 245); // Off-white
$color_text_medium = imagecolorallocate($image, 160, 160, 160); // Gray
$color_accent_random = imagecolorallocate($image, $random_color_rgb[0], $random_color_rgb[1], $random_color_rgb[2]);


// 3. Draw Background
imagefill($image, 0, 0, $color_bg_dark);

// This variable will track the vertical position for the next element.
$y_pos = $padding;

// 4. Add Logo
$logo_path = 'outfront-logo.png'; // Make sure the color logo is in the same directory
if (file_exists($logo_path)) {
    $logo_img = imagecreatefrompng($logo_path);
    list($logo_w_orig, $logo_h_orig) = getimagesize($logo_path);
    
    // Define a new, more manageable width for the logo
    $new_logo_w = 800;
    // Calculate the new height to maintain the aspect ratio
    $new_logo_h = $new_logo_w * ($logo_h_orig / $logo_w_orig);
    // Calculate the X coordinate to center the logo
    $logo_x = ($width - $new_logo_w) / 2;
    
    // Place the centered logo on the canvas
    imagecopyresampled($image, $logo_img, $logo_x, $y_pos, 0, 0, $new_logo_w, $new_logo_h, $logo_w_orig, $logo_h_orig);
    imagedestroy($logo_img);

    // Update the Y position to be below the logo for the next element
    $y_pos += $new_logo_h;

    // --- Add "youth group" tagline ---
    $y_pos += 40; // Small space below the logo
    $tagline_text = "youth group";
    $tagline_font_size = 38;
    $tagline_bbox = imagettfbbox($tagline_font_size, 0, $font_regular_path, $tagline_text);
    $tagline_width = $tagline_bbox[2] - $tagline_bbox[0];
    $tagline_x = ($width - $tagline_width) / 2;
    imagettftext($image, $tagline_font_size, 0, $tagline_x, $y_pos, $color_text_medium, $font_regular_path, $tagline_text);
    $y_pos += abs($tagline_bbox[7] - $tagline_bbox[1]); // Add tagline height to y_pos
}

// 5. Prepare Text Content from the event data
$date_formatted = date('l, F jS, Y', strtotime($target_event['date']));
$time_string = $target_event['startTime'] ? $target_event['startTime'] . ' - ' . $target_event['endTime'] : 'All-day event';
$title = htmlspecialchars_decode($target_event['summary']);
$description = htmlspecialchars_decode($target_event['description']);
$location = htmlspecialchars_decode($target_event['location']);
if (empty($location)) {
    $location = '128 S. Union Ave, Pueblo, CO'; // Default location if not specified
}


// 6. Draw Text onto Image

// Add some space between the logo/tagline and the title
$y_pos += 80;

// --- Title ---
$title_font_size = 70;
$wrapped_title = wrap_text($title_font_size, 0, $font_bold_path, $title, $width - ($padding * 2));
imagettftext($image, $title_font_size, 0, $padding, $y_pos, $color_text_light, $font_bold_path, $wrapped_title);

// Calculate the height of the title block and update the Y position
$title_bbox = imagettfbbox($title_font_size, 0, $font_bold_path, $wrapped_title);
$y_pos += abs($title_bbox[7] - $title_bbox[1]); // Add the height of the title text

// --- Date & Time ---
$y_pos += 50; // Add space before the details
$details_font_size = 42;
imagettftext($image, $details_font_size, 0, $padding, $y_pos, $color_accent_random, $font_bold_path, $date_formatted);
$y_pos += 70;
imagettftext($image, $details_font_size, 0, $padding, $y_pos, $color_text_light, $font_regular_path, $time_string);
$y_pos += 70;
imagettftext($image, $details_font_size, 0, $padding, $y_pos, $color_text_light, $font_regular_path, $location);

// --- Description ---
if (!empty($description)) {
    $y_pos += 60; // Space before separator line
    // Draw a separator line
    imageline($image, $padding, $y_pos, $width - $padding, $y_pos, $color_text_medium);
    $y_pos += 40; // Space after separator line
    
    $desc_font_size = 32;
    $wrapped_desc = wrap_text($desc_font_size, 0, $font_regular_path, $description, $width - ($padding * 2));
    imagettftext($image, $desc_font_size, 0, $padding, $y_pos, $color_text_medium, $font_regular_path, $wrapped_desc);
}

// --- Footer ---
$scea_logo_url = 'https://images.squarespace-cdn.com/content/v1/5b5776b2af20962f0511952c/e6e59ae4-1fb2-4679-8f03-4da69779a43c/SCEAlogo300dpiPRINT.png?format=1500w';
// Use @ to suppress warnings if the URL is inaccessible
$scea_logo_img = @imagecreatefrompng($scea_logo_url);

if ($scea_logo_img) {
    // Get original logo dimensions from the URL
    list($scea_w_orig, $scea_h_orig) = getimagesize($scea_logo_url);

    // Set the desired width for the SCEA logo in the footer
    $new_scea_w = 125;
    // Calculate the new height to maintain the aspect ratio
    $new_scea_h = $new_scea_w * ($scea_h_orig / $scea_w_orig);

    // Calculate position for bottom-right corner, respecting padding
    $scea_x = $width - $padding - $new_scea_w;
    $scea_y = $height - $padding - $new_scea_h;

    // Place the SCEA logo on the canvas
    imagecopyresampled($image, $scea_logo_img, $scea_x, $scea_y, 0, 0, $new_scea_w, $new_scea_h, $scea_w_orig, $scea_h_orig);
    imagedestroy($scea_logo_img);
} else {
    // Fallback to text if the logo can't be loaded for any reason
    $footer_text = "Outfront is a program of the Southern Colorado Equality Alliance";
    imagettftext($image, 20, 0, $padding, $height - $padding, $color_text_medium, $font_regular_path, $footer_text);
}


// 7. Output the image
header("Content-type: image/png");
imagepng($image);

// 8. Clean up memory
imagedestroy($image);

?>
