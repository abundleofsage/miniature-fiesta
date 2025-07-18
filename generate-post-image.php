<?php
// This script generates a social media post image for a single calendar event.
// It's designed to work with the same Nextcloud calendar as your flyer script.

// --- ACTION REQUIRED ---
// Paste your private Nextcloud iCal subscription URL here.
// IMPORTANT: Please verify this is the correct and active iCal URL.
$ical_url = 'https://cloud.outfrontyouth.org/remote.php/dav/public-calendars/o4BqHRSHaDJjiqjs?export';

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

// --- Template and Color Configuration ---
$selected_template = isset($_GET['template']) ? intval($_GET['template']) : 1; // Default to template 1
$custom_accent_color_hex = isset($_GET['accent_color']) ? $_GET['accent_color'] : null;


// --- No need to edit below this line ---

// --- Data Fetching and Parsing (Re-used from your original script) ---
require_once('calendar-functions.php');

/**
 * Converts a HEX color string to an RGB array.
 *
 * @param string $hex_color The hex color string (e.g., "FF0000").
 * @return array|null RGB array [r, g, b] or null if invalid hex.
 */
function hex_to_rgb($hex_color) {
    $hex_color = ltrim($hex_color, '#');
    if (strlen($hex_color) == 6) {
        list($r, $g, $b) = sscanf($hex_color, "%02x%02x%02x");
        return [$r, $g, $b];
    } elseif (strlen($hex_color) == 3) {
        list($r, $g, $b) = sscanf($hex_color, "%1x%1x%1x");
        return [$r * 16 + $r, $g * 16 + $g, $b * 16 + $b];
    }
    return null; // Invalid hex
}


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
$height = 1200;
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
$accent_rgb = $random_color_rgb; // Default to random

// Override with custom color if provided and valid
if ($custom_accent_color_hex) {
    $parsed_rgb = hex_to_rgb($custom_accent_color_hex);
    if ($parsed_rgb) {
        $accent_rgb = $parsed_rgb;
    }
}

// 2. Define Colors
$color_bg_dark = imagecolorallocate($image, 23, 23, 23); // Almost black
$color_text_light = imagecolorallocate($image, 245, 245, 245); // Off-white
$color_text_medium = imagecolorallocate($image, 160, 160, 160); // Gray
$color_accent = imagecolorallocate($image, $accent_rgb[0], $accent_rgb[1], $accent_rgb[2]);


// 3. Draw Background
imagefill($image, 0, 0, $color_bg_dark);

// This variable will track the vertical position for the next element.
$y_pos = $padding;

// 4. Add Logo (Common to all templates)
$logo_path = 'outfront-logo.png'; // Make sure the color logo is in the same directory
if (file_exists($logo_path)) {
    $logo_img = imagecreatefrompng($logo_path);
    list($logo_w_orig, $logo_h_orig) = getimagesize($logo_path);
    
    $new_logo_w = ($selected_template == 3) ? 600 : 800; // Smaller logo for template 3
    $new_logo_h = $new_logo_w * ($logo_h_orig / $logo_w_orig);
    $logo_x = ($width - $new_logo_w) / 2;
    if ($selected_template == 3) {
        $logo_x = $padding + 350; // Position logo to the right for template 3
    }
    
    imagecopyresampled($image, $logo_img, $logo_x, $y_pos, 0, 0, $new_logo_w, $new_logo_h, $logo_w_orig, $logo_h_orig);
    imagedestroy($logo_img);

    $y_pos += $new_logo_h;

    $tagline_y_pos = $y_pos + 40;
    $tagline_text = "youth group";
    $tagline_font_size = 38;
    $tagline_bbox = imagettfbbox($tagline_font_size, 0, $font_regular_path, $tagline_text);
    $tagline_width = $tagline_bbox[2] - $tagline_bbox[0];
    $tagline_x = ($width - $tagline_width) / 2;
    if ($selected_template == 3) {
        $tagline_x = $padding + 350 + (($new_logo_w - $tagline_width)/2); // Center under smaller logo
    }
    imagettftext($image, $tagline_font_size, 0, $tagline_x, $tagline_y_pos, $color_text_medium, $font_regular_path, $tagline_text);

    if ($selected_template != 3) { // For template 1 and 2, tagline pushes main content down
         $y_pos = $tagline_y_pos + abs($tagline_bbox[7] - $tagline_bbox[1]); // y_pos is now below tagline
    } else { // For template 3, tagline is in the right column, under its logo.
        // $y_pos should be set to be below the tagline in the right-hand column.
        $y_pos = $tagline_y_pos + abs($tagline_bbox[7] - $tagline_bbox[1]) + 30; // Add 30px padding below tagline
    }
} else { // If logo file doesn't exist, set a default starting y_pos
    $y_pos = $padding;
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

// 6. Draw Text onto Image based on selected template

// Template-specific variables
$title_font_size = 70;
$details_font_size = 42;
$desc_font_size = 32;


// --- TEMPLATE LOGIC ---

if ($selected_template == 1) { // Template 1: Classic
    $y_pos += 60; // Space after logo/tagline

    // --- Title ---
    $wrapped_title = wrap_text($title_font_size, 0, $font_bold_path, $title, $width - ($padding * 2));
    imagettftext($image, $title_font_size, 0, $padding, $y_pos, $color_text_light, $font_bold_path, $wrapped_title);
    $title_bbox = imagettfbbox($title_font_size, 0, $font_bold_path, $wrapped_title);
    $y_pos += abs($title_bbox[7] - $title_bbox[1]);

    // --- Date & Time & Location ---
    $y_pos += 40;
    imagettftext($image, $details_font_size, 0, $padding, $y_pos, $color_accent, $font_bold_path, $date_formatted);
    $y_pos += 60;
    imagettftext($image, $details_font_size, 0, $padding, $y_pos, $color_text_light, $font_regular_path, $time_string);
    $y_pos += 60;
    imagettftext($image, $details_font_size, 0, $padding, $y_pos, $color_text_light, $font_regular_path, $location);

    // --- Description ---
    if (!empty($description)) {
        $y_pos += 50;
        imageline($image, $padding, $y_pos, $width - $padding, $y_pos, $color_text_medium);
        $y_pos += 40;
        $wrapped_desc = wrap_text($desc_font_size, 0, $font_regular_path, $description, $width - ($padding * 2));
        imagettftext($image, $desc_font_size, 0, $padding, $y_pos, $color_text_medium, $font_regular_path, $wrapped_desc);
    }

} elseif ($selected_template == 2) { // Template 2: Bottom-Title
    $y_pos += 90; // Space after logo/tagline

    // --- Date & Time & Location (Top) ---
    imagettftext($image, $details_font_size, 0, $padding, $y_pos, $color_accent, $font_bold_path, $date_formatted);
    $y_pos += 70;
    imagettftext($image, $details_font_size, 0, $padding, $y_pos, $color_text_light, $font_regular_path, $time_string);
    $y_pos += 70;
    imagettftext($image, $details_font_size, 0, $padding, $y_pos, $color_text_light, $font_regular_path, $location);

    // --- Description (Middle) ---
    if (!empty($description)) {
        $y_pos += 60;
        imageline($image, $padding, $y_pos, $width - $padding, $y_pos, $color_text_medium);
        $y_pos += 40;
        $wrapped_desc = wrap_text($desc_font_size, 0, $font_regular_path, $description, $width - ($padding * 2));
        imagettftext($image, $desc_font_size, 0, $padding, $y_pos, $color_text_medium, $font_regular_path, $wrapped_desc);
        $desc_bbox = imagettfbbox($desc_font_size, 0, $font_regular_path, $wrapped_desc);
        $y_pos += abs($desc_bbox[7] - $desc_bbox[1]);
    }

    // --- Title (Bottom, above footer) ---
    // The following lines for drawing the title are commented out for Template 2
    // $title_y_pos = $height - $padding - 150; // Positioned from bottom, adjust 150 based on SCEA logo and desired spacing
    // $wrapped_title = wrap_text($title_font_size, 0, $font_bold_path, $title, $width - ($padding * 2));
    // $title_bbox = imagettfbbox($title_font_size, 0, $font_bold_path, $wrapped_title);
    // $title_actual_height = abs($title_bbox[7] - $title_bbox[1]);
    // // Adjust Y to make sure the entire title block is visible and centered in its allocated space
    // $title_y_pos = $title_y_pos - $title_actual_height;
    // imagettftext($image, $title_font_size, 0, $padding, $title_y_pos, $color_text_light, $font_bold_path, $wrapped_title);

    // Since title is removed, we might want to ensure description takes up available space or adjust other elements.
    // For now, removing the title is the primary change.
    // If the description was the last element, its y_pos would determine the bottom of the content.
    // The footer SCEA logo is positioned from the bottom of the image, so it's not directly dependent on text flow from above in this template.

} elseif ($selected_template == 3) { // Template 3: Side-Banner
    // Banner on the left side
    $banner_width = 350;
    imagefilledrectangle($image, 0, 0, $banner_width, $height, $color_accent);

    // Reset y_pos for content within the banner
    $y_banner_pos = $padding;

    // --- Date & Time & Location (In Banner) ---
    $details_font_size_banner = 38;
    $banner_text_color = imagecolorallocate($image, 255, 255, 255); // White text for banner

    $wrapped_date_banner = wrap_text($details_font_size_banner, 0, $font_bold_path, $date_formatted, $banner_width - ($padding / 2));
    imagettftext($image, $details_font_size_banner, 0, $padding / 2, $y_banner_pos, $banner_text_color, $font_bold_path, $wrapped_date_banner);
    $date_bbox_banner = imagettfbbox($details_font_size_banner, 0, $font_bold_path, $wrapped_date_banner);
    $y_banner_pos += abs($date_bbox_banner[7] - $date_bbox_banner[1]) + 40;

    $wrapped_time_banner = wrap_text($details_font_size_banner, 0, $font_regular_path, $time_string, $banner_width - ($padding / 2));
    imagettftext($image, $details_font_size_banner, 0, $padding / 2, $y_banner_pos, $banner_text_color, $font_regular_path, $wrapped_time_banner);
    $time_bbox_banner = imagettfbbox($details_font_size_banner, 0, $font_regular_path, $wrapped_time_banner);
    $y_banner_pos += abs($time_bbox_banner[7] - $time_bbox_banner[1]) + 40;

    $wrapped_location_banner = wrap_text($details_font_size_banner, 0, $font_regular_path, $location, $banner_width - ($padding / 2));
    imagettftext($image, $details_font_size_banner, 0, $padding / 2, $y_banner_pos, $banner_text_color, $font_regular_path, $wrapped_location_banner);


    // Content to the right of the banner (Title, Description, Logo)
    $content_x_offset = $banner_width + $padding;
    $content_width = $width - $content_x_offset - $padding;
    
    // y_pos for this section is already set after logo drawing.
    // The logo is positioned to the right in this template. $y_pos is below the logo.
   
    // $y_pos is now correctly set globally to be after the tagline in the right column (or default if no logo).
    $y_pos += 60;
    $y_pos_main_content = $y_pos; // Use the globally updated y_pos as the starting point for the title.

    // --- Title (Right of Banner) ---
    // $y_pos_main_content is already set to be below the logo and tagline.

    $title_font_size_main = 60;
    $wrapped_title_main = wrap_text($title_font_size_main, 0, $font_bold_path, $title, $content_width);
    imagettftext($image, $title_font_size_main, 0, $content_x_offset, $y_pos_main_content, $color_text_light, $font_bold_path, $wrapped_title_main);
    $title_bbox_main = imagettfbbox($title_font_size_main, 0, $font_bold_path, $wrapped_title_main);
    $y_pos_main_content += abs($title_bbox_main[7] - $title_bbox_main[1]);

    // --- Description (Right of Banner) ---
    if (!empty($description)) {
        $y_pos_main_content += 40; // Add space between title and description separator
        imageline($image, $content_x_offset, $y_pos_main_content, $width - $padding, $y_pos_main_content, $color_text_medium);
        $y_pos_main_content += 30; // Add space after separator line
        $desc_font_size_main = 28;
        $wrapped_desc_main = wrap_text($desc_font_size_main, 0, $font_regular_path, $description, $content_width);
        imagettftext($image, $desc_font_size_main, 0, $content_x_offset, $y_pos_main_content, $color_text_medium, $font_regular_path, $wrapped_desc_main);
    }
}


// --- Footer (Common to all templates, but position might vary slightly if template 3 banner is too tall) ---
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
    $footer_text = "OutFront is a program of the Southern Colorado Equality Alliance";
    imagettftext($image, 20, 0, $padding, $height - $padding, $color_text_medium, $font_regular_path, $footer_text);
}


// 7. Output the image
header("Content-type: image/png");
imagepng($image);

// 8. Clean up memory
imagedestroy($image);

?>
