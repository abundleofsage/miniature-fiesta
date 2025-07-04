<?php
// This script generates a social media post image for a single calendar event.
// It's designed to work with the same Nextcloud calendar as your flyer script.

// This script generates a social media post image for a single calendar event.
// It's designed to work with the same Nextcloud calendar as your flyer script.

require_once('config.php'); // Include the central configuration file
require_once('calendar-functions.php'); // For app_log and terminate_script_with_error, get_calendar_events

// $ical_url is now ICAL_URL from config.php
// $font_regular_path is now FONT_REGULAR_PATH from config.php
// $font_bold_path is now FONT_BOLD_PATH from config.php

// Initialize error reporting
ini_set('display_errors', 0); // Don't display errors directly to user
error_reporting(E_ALL);
set_error_handler(function($severity, $message, $file, $line) {
    app_log('ERROR', "Unhandled error: {$message}", ['file' => $file, 'line' => $line, 'severity' => $severity]);
    if (error_reporting() & $severity) {
        if (ob_get_level() > 0) ob_end_clean();
        http_response_code(500);
        // Try to send a valid PNG error image if possible, else text
        if (!headers_sent()) {
             // Attempt to create a minimal error image
            $error_img_width = 600; $error_img_height = 300;
            $err_image = @imagecreatetruecolor($error_img_width, $error_img_height);
            if ($err_image) {
                $err_bg = @imagecolorallocate($err_image, 20, 20, 20);
                $err_text_color = @imagecolorallocate($err_image, 230, 230, 230);
                if ($err_bg !== false && $err_text_color !== false) {
                    @imagefill($err_image, 0, 0, $err_bg);
                    // Try to use a system font if custom fonts failed to load
                    @imagestring($err_image, 3, $error_img_width / 2 - 120, $error_img_height / 2 - 10, "Image Generation Error", $err_text_color);
                    @imagestring($err_image, 2, $error_img_width / 2 - 120, $error_img_height / 2 + 10, "Please try again later.", $err_text_color);
                    header('Content-Type: image/png');
                    @imagepng($err_image);
                    @imagedestroy($err_image);
                    exit;
                }
            }
        }
        // Fallback if image error generation fails or headers sent
        header('Content-Type: text/plain');
        echo "An unexpected error occurred while generating the image.";
        exit;
    }
    return false;
});

// Check if font files exist and are readable using constants from config.php
if (!file_exists(FONT_REGULAR_PATH) || !is_readable(FONT_REGULAR_PATH) || !file_exists(FONT_BOLD_PATH) || !is_readable(FONT_BOLD_PATH)) {
    terminate_script_with_error(
        "Error: Essential font files are missing or not readable. Please contact the site administrator.",
        "Font files not found or not readable.",
        ['regular_font' => FONT_REGULAR_PATH, 'bold_font' => FONT_BOLD_PATH]
    );
}

// --- Template and Color Configuration ---
// Validate Template
$template_input = $_GET['template'] ?? 1;
$selected_template = filter_var($template_input, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 3]]); // Assuming 3 templates
if ($selected_template === false) {
    app_log('WARNING', "Invalid template number provided.", ['template_input' => $template_input]);
    $selected_template = 1; // Default to template 1
}

// Validate Accent Color (if provided)
$custom_accent_color_hex = null;
if (isset($_GET['accent_color'])) {
    $color_input = ltrim($_GET['accent_color'], '#');
    if (preg_match('/^[a-fA-F0-9]{3}$/', $color_input) || preg_match('/^[a-fA-F0-9]{6}$/', $color_input)) {
        $custom_accent_color_hex = $color_input;
    } else {
        app_log('WARNING', "Invalid accent color hex provided.", ['color_input' => $_GET['accent_color']]);
        // Do not set $custom_accent_color_hex, so random color will be used.
    }
}


// --- Date Input Validation ---
$target_date_input = $_GET['date'] ?? null;
$validated_target_date = null;

if ($target_date_input !== null) {
    // Validate YYYY-MM-DD format
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $target_date_input)) {
        $dt = DateTime::createFromFormat('Y-m-d', $target_date_input);
        if ($dt && $dt->format('Y-m-d') === $target_date_input) {
            $validated_target_date = $target_date_input;
        } else {
            app_log('WARNING', "Invalid date provided (failed DateTime validation).", ['date_input' => $target_date_input]);
        }
    } else {
        app_log('WARNING', "Invalid date format provided.", ['date_input' => $target_date_input]);
    }
    if (!$validated_target_date) {
        // If date is provided but invalid, it's an error, don't fall back to "next event"
        terminate_script_with_error(
            "Error: Invalid date parameter. Please use YYYY-MM-DD format.",
            "Invalid date parameter for post image.",
            ['date_provided' => $target_date_input],
            400
        );
    }
}
// $validated_target_date will be null if no date was provided (then script finds next event),
// or will hold the validated date string.


// --- No need to edit below this line (for this section)---

// --- Data Fetching and Parsing (Re-used from your original script) ---
// require_once('calendar-functions.php'); // Already included above for error handling setup

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

$all_events = get_calendar_events(ICAL_URL); // Use ICAL_URL from config

if ($all_events === false) {
    // Create a generic error image as get_calendar_events already logged the specific error
    $error_message_user = "Error: Could not load calendar data. Please try again later.";
    // Log this specific context for generate-post-image.php, using the constant
    app_log('ERROR', 'get_calendar_events failed within generate-post-image.php.', ['ical_url' => ICAL_URL]);

    // Output generic error image
    header("Content-type: image/png");
    $error_image = imagecreatetruecolor(1200, 675); // Standard social media size
    $bg_color = imagecolorallocate($error_image, 30, 30, 30);
    $text_color = imagecolorallocate($error_image, 220, 220, 220);
    imagefill($error_image, 0, 0, $bg_color);
    // Simplified error text rendering, assuming font paths might be an issue if we got here.
    // Use imagestring if imagettftext with custom fonts is risky.
    imagestring($error_image, 5, 50, (675/2) - 20, "Calendar Data Error", $text_color);
    imagestring($error_image, 3, 50, (675/2) + 10, "Could not load event information.", $text_color);
    imagepng($error_image);
    imagedestroy($error_image);
    exit;
}

$target_event = null;

if ($validated_target_date !== null) { // A specific, valid date was provided
    foreach ($all_events as $event) {
        if ($event['date'] == $validated_target_date) {
            $target_event = $event;
            break;
        }
    }
} else { // No valid date provided, or no date at all; find the next upcoming event
    $today = date("Y-m-d");
    usort($all_events, function($a, $b) {
        $timeA = strtotime($a['date']);
        $timeB = strtotime($b['date']);
        if ($timeA === false || $timeB === false) return 0; // Should not happen if data is clean
        return $timeA - $timeB;
    });
    foreach ($all_events as $event) {
        $eventTime = strtotime($event['date']);
        if ($eventTime === false) continue; // Skip malformed event dates
        if ($eventTime >= strtotime($today)) {
            $target_event = $event;
            break;
        }
    }
}

// If no event is found, display an error image.
if ($target_event === null) {
    $log_message = isset($_GET['date']) ? "Event not found for specific date." : "No upcoming event found.";
    app_log('INFO', $log_message, ['target_date' => $_GET['date'] ?? 'None']);
    header("Content-type: image/png");
    $error_image = imagecreatetruecolor(1200, 675);
    $bg_color = imagecolorallocate($error_image, 30, 30, 30);
    $text_color = imagecolorallocate($error_image, 220, 220, 220);
    imagefill($error_image, 0, 0, $bg_color);
    imagettftext($error_image, 40, 0, 50, (675/2), $text_color, FONT_BOLD_PATH, "Event Not Found");
    imagettftext($error_image, 20, 0, 50, (675/2) + 50, $text_color, FONT_REGULAR_PATH, isset($_GET['date']) ? "No event scheduled for this date." : "No upcoming events found.");
    imagepng($error_image);
    imagedestroy($error_image);
    exit;
}


// --- Image Generation using GD library ---

// 1. Setup Canvas
$width = 1200;
$height = 1200;
$image = @imagecreatetruecolor($width, $height);
if (!$image) {
    terminate_script_with_error(
        "Error: Could not initialize image canvas. GD library error.",
        "imagecreatetruecolor failed.",
        ['width' => $width, 'height' => $height]
    );
}
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
// Use @ to suppress warnings if image is not valid, then check return values
$color_bg_dark = @imagecolorallocate($image, 23, 23, 23);
$color_text_light = @imagecolorallocate($image, 245, 245, 245);
$color_text_medium = @imagecolorallocate($image, 160, 160, 160);
$color_accent = @imagecolorallocate($image, $accent_rgb[0], $accent_rgb[1], $accent_rgb[2]);

if ($color_bg_dark === false || $color_text_light === false || $color_text_medium === false || $color_accent === false) {
    if ($image) imagedestroy($image); // Clean up image resource
    terminate_script_with_error(
        "Error: Could not allocate colors for the image. GD library error.",
        "imagecolorallocate failed for one or more colors.",
        ['accent_rgb' => $accent_rgb]
    );
}


// 3. Draw Background
if (!@imagefill($image, 0, 0, $color_bg_dark)) {
    if ($image) imagedestroy($image);
    terminate_script_with_error(
        "Error: Could not fill image background. GD library error.",
        "imagefill failed."
    );
}

// This variable will track the vertical position for the next element.
$y_pos = $padding;

// 4. Add Logo (Common to all templates)
$logo_path = 'outfront-logo.png'; // Make sure the color logo is in the same directory
if (file_exists($logo_path) && is_readable($logo_path)) {
    $logo_img_size = @getimagesize($logo_path);
    if ($logo_img_size === false) {
        app_log('WARNING', "Local logo file getimagesize failed or not a valid image.", ['path' => $logo_path]);
    } else {
        $logo_img = @imagecreatefrompng($logo_path);
        if (!$logo_img) {
            app_log('WARNING', "imagecreatefrompng failed for local logo.", ['path' => $logo_path]);
        } else {
            list($logo_w_orig, $logo_h_orig) = $logo_img_size;
            $new_logo_w = ($selected_template == 3) ? 600 : 800; // Smaller logo for template 3
    $new_logo_h = $new_logo_w * ($logo_h_orig / $logo_w_orig);
    $logo_x = ($width - $new_logo_w) / 2;
    if ($selected_template == 3) {
        $logo_x = $padding + 350; // Position logo to the right for template 3
    }
    
    imagecopyresampled($image, $logo_img, $logo_x, $y_pos, 0, 0, $new_logo_w, $new_logo_h, $logo_w_orig, $logo_h_orig);
            if (!@imagecopyresampled($image, $logo_img, $logo_x, $y_pos, 0, 0, $new_logo_w, $new_logo_h, $logo_w_orig, $logo_h_orig)) {
                app_log('WARNING', "imagecopyresampled failed for local logo.", ['path' => $logo_path]);
            }
            imagedestroy($logo_img);

            $y_pos += $new_logo_h;

            $tagline_y_pos = $y_pos + 40;
            $tagline_text = "youth group";
            $tagline_font_size = 38;
            $tagline_bbox_check = @imagettfbbox($tagline_font_size, 0, FONT_REGULAR_PATH, $tagline_text);
            if($tagline_bbox_check === false){
                app_log('WARNING', "imagettfbbox failed for tagline.", ['text' => $tagline_text]);
            } else {
                $tagline_bbox = $tagline_bbox_check;
                $tagline_width = $tagline_bbox[2] - $tagline_bbox[0];
                $tagline_x = ($width - $tagline_width) / 2;
                if ($selected_template == 3) {
                    $tagline_x = $padding + 350 + (($new_logo_w - $tagline_width)/2); // Center under smaller logo
                }
                if(@imagettftext($image, $tagline_font_size, 0, $tagline_x, $tagline_y_pos, $color_text_medium, FONT_REGULAR_PATH, $tagline_text) === false){
                    app_log('WARNING', "imagettftext failed for tagline.", ['text' => $tagline_text]);
                }

                if ($selected_template != 3) { // For template 1 and 2, tagline pushes main content down
                     $y_pos = $tagline_y_pos + abs($tagline_bbox[7] - $tagline_bbox[1]); // y_pos is now below tagline
                } else { // For template 3, tagline is in the right column, under its logo.
                    $y_pos = $tagline_y_pos + abs($tagline_bbox[7] - $tagline_bbox[1]) + 30; // Add 30px padding
                }
            }
        }
    }
} else {
    app_log('INFO', "Local logo file not found or not readable, skipping.", ['path' => $logo_path]);
    $y_pos = $padding; // Default y_pos if no logo
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
    $wrapped_title = wrap_text($title_font_size, 0, FONT_BOLD_PATH, $title, $width - ($padding * 2));
    @imagettftext($image, $title_font_size, 0, $padding, $y_pos, $color_text_light, FONT_BOLD_PATH, $wrapped_title);
    $title_bbox = @imagettfbbox($title_font_size, 0, FONT_BOLD_PATH, $wrapped_title);
    if ($title_bbox) $y_pos += abs($title_bbox[7] - $title_bbox[1]);

    // --- Date & Time & Location ---
    $y_pos += 40;
    @imagettftext($image, $details_font_size, 0, $padding, $y_pos, $color_accent, FONT_BOLD_PATH, $date_formatted);
    $y_pos += 60;
    @imagettftext($image, $details_font_size, 0, $padding, $y_pos, $color_text_light, FONT_REGULAR_PATH, $time_string);
    $y_pos += 60;
    @imagettftext($image, $details_font_size, 0, $padding, $y_pos, $color_text_light, FONT_REGULAR_PATH, $location);

    // --- Description ---
    if (!empty($description)) {
        $y_pos += 50;
        @imageline($image, $padding, $y_pos, $width - $padding, $y_pos, $color_text_medium);
        $y_pos += 40;
        $wrapped_desc = wrap_text($desc_font_size, 0, FONT_REGULAR_PATH, $description, $width - ($padding * 2));
        @imagettftext($image, $desc_font_size, 0, $padding, $y_pos, $color_text_medium, FONT_REGULAR_PATH, $wrapped_desc);
    }

} elseif ($selected_template == 2) { // Template 2: Bottom-Title
    $y_pos += 90; // Space after logo/tagline

    // --- Date & Time & Location (Top) ---
    @imagettftext($image, $details_font_size, 0, $padding, $y_pos, $color_accent, FONT_BOLD_PATH, $date_formatted);
    $y_pos += 70;
    @imagettftext($image, $details_font_size, 0, $padding, $y_pos, $color_text_light, FONT_REGULAR_PATH, $time_string);
    $y_pos += 70;
    @imagettftext($image, $details_font_size, 0, $padding, $y_pos, $color_text_light, FONT_REGULAR_PATH, $location);

    // --- Description (Middle) ---
    if (!empty($description)) {
        $y_pos += 60;
        @imageline($image, $padding, $y_pos, $width - $padding, $y_pos, $color_text_medium);
        $y_pos += 40;
        $wrapped_desc = wrap_text($desc_font_size, 0, FONT_REGULAR_PATH, $description, $width - ($padding * 2));
        @imagettftext($image, $desc_font_size, 0, $padding, $y_pos, $color_text_medium, FONT_REGULAR_PATH, $wrapped_desc);
        $desc_bbox = @imagettfbbox($desc_font_size, 0, FONT_REGULAR_PATH, $wrapped_desc);
        if ($desc_bbox) $y_pos += abs($desc_bbox[7] - $desc_bbox[1]);
    }

    // --- Title (Bottom, above footer) ---
    // The following lines for drawing the title are commented out for Template 2
    // $title_y_pos = $height - $padding - 150; // Positioned from bottom, adjust 150 based on SCEA logo and desired spacing
    // $wrapped_title = wrap_text($title_font_size, 0, FONT_BOLD_PATH, $title, $width - ($padding * 2));
    // $title_bbox = @imagettfbbox($title_font_size, 0, FONT_BOLD_PATH, $wrapped_title);
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
    $banner_text_color = @imagecolorallocate($image, 255, 255, 255); // White text for banner
    if ($banner_text_color === false) {
        app_log('WARNING', "Failed to allocate banner text color. Using text_light.", ['template' => 3]);
        $banner_text_color = $color_text_light; // Fallback color
    }

    $wrapped_date_banner = wrap_text($details_font_size_banner, 0, FONT_BOLD_PATH, $date_formatted, $banner_width - ($padding / 2));
    @imagettftext($image, $details_font_size_banner, 0, $padding / 2, $y_banner_pos, $banner_text_color, FONT_BOLD_PATH, $wrapped_date_banner);
    $date_bbox_banner = @imagettfbbox($details_font_size_banner, 0, FONT_BOLD_PATH, $wrapped_date_banner);
    if ($date_bbox_banner) $y_banner_pos += abs($date_bbox_banner[7] - $date_bbox_banner[1]) + 40;

    $wrapped_time_banner = wrap_text($details_font_size_banner, 0, FONT_REGULAR_PATH, $time_string, $banner_width - ($padding / 2));
    @imagettftext($image, $details_font_size_banner, 0, $padding / 2, $y_banner_pos, $banner_text_color, FONT_REGULAR_PATH, $wrapped_time_banner);
    $time_bbox_banner = @imagettfbbox($details_font_size_banner, 0, FONT_REGULAR_PATH, $wrapped_time_banner);
    if ($time_bbox_banner) $y_banner_pos += abs($time_bbox_banner[7] - $time_bbox_banner[1]) + 40;

    $wrapped_location_banner = wrap_text($details_font_size_banner, 0, FONT_REGULAR_PATH, $location, $banner_width - ($padding / 2));
    @imagettftext($image, $details_font_size_banner, 0, $padding / 2, $y_banner_pos, $banner_text_color, FONT_REGULAR_PATH, $wrapped_location_banner);


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
    $wrapped_title_main = wrap_text($title_font_size_main, 0, FONT_BOLD_PATH, $title, $content_width);
    @imagettftext($image, $title_font_size_main, 0, $content_x_offset, $y_pos_main_content, $color_text_light, FONT_BOLD_PATH, $wrapped_title_main);
    $title_bbox_main = @imagettfbbox($title_font_size_main, 0, FONT_BOLD_PATH, $wrapped_title_main);
    if ($title_bbox_main) $y_pos_main_content += abs($title_bbox_main[7] - $title_bbox_main[1]);

    // --- Description (Right of Banner) ---
    if (!empty($description)) {
        $y_pos_main_content += 40; // Add space between title and description separator
        @imageline($image, $content_x_offset, $y_pos_main_content, $width - $padding, $y_pos_main_content, $color_text_medium);
        $y_pos_main_content += 30; // Add space after separator line
        $desc_font_size_main = 28;
        $wrapped_desc_main = wrap_text($desc_font_size_main, 0, FONT_REGULAR_PATH, $description, $content_width);
        @imagettftext($image, $desc_font_size_main, 0, $content_x_offset, $y_pos_main_content, $color_text_medium, FONT_REGULAR_PATH, $wrapped_desc_main);
    }
}


// --- Footer (Common to all templates, but position might vary slightly if template 3 banner is too tall) ---
$scea_logo_url = 'https://images.squarespace-cdn.com/content/v1/5b5776b2af20962f0511952c/e6e59ae4-1fb2-4679-8f03-4da69779a43c/SCEAlogo300dpiPRINT.png?format=1500w';
$scea_logo_img_data = @file_get_contents($scea_logo_url); // Suppress direct error, check result

if ($scea_logo_img_data !== false) {
    $scea_logo_img = @imagecreatefromstring($scea_logo_img_data);
    if ($scea_logo_img) {
        $scea_w_orig = imagesx($scea_logo_img);
        $scea_h_orig = imagesy($scea_logo_img);

        if ($scea_w_orig > 0 && $scea_h_orig > 0) { // Check for valid dimensions
            $new_scea_w = 125;
            $new_scea_h = $new_scea_w * ($scea_h_orig / $scea_w_orig);
            $scea_x = $width - $padding - $new_scea_w;
            $scea_y = $height - $padding - $new_scea_h;

            if (!@imagecopyresampled($image, $scea_logo_img, $scea_x, $scea_y, 0, 0, $new_scea_w, $new_scea_h, $scea_w_orig, $scea_h_orig)) {
                app_log('WARNING', "imagecopyresampled failed for SCEA logo.", ['url' => $scea_logo_url]);
            }
            imagedestroy($scea_logo_img);
        } else {
            app_log('WARNING', "SCEA logo from URL had invalid dimensions.", ['url' => $scea_logo_url, 'w' => $scea_w_orig, 'h' => $scea_h_orig]);
            // Fallback to text
            $footer_text = "OutFront is a program of the Southern Colorado Equality Alliance";
            @imagettftext($image, 20, 0, $padding, $height - $padding, $color_text_medium, FONT_REGULAR_PATH, $footer_text);
        }
    } else {
        app_log('WARNING', "imagecreatefromstring failed for SCEA logo.", ['url' => $scea_logo_url]);
        // Fallback to text
        $footer_text = "OutFront is a program of the Southern Colorado Equality Alliance";
        @imagettftext($image, 20, 0, $padding, $height - $padding, $color_text_medium, FONT_REGULAR_PATH, $footer_text);
    }
} else {
    app_log('WARNING', "Failed to download SCEA logo.", ['url' => $scea_logo_url]);
    // Fallback to text
    $footer_text = "OutFront is a program of the Southern Colorado Equality Alliance";
    // Suppress error for imagettftext as a last resort
    @imagettftext($image, 20, 0, $padding, $height - $padding, $color_text_medium, FONT_REGULAR_PATH, $footer_text);
}


// 7. Output the image
if (!headers_sent()) {
    header("Content-type: image/png");
} else {
    // This case should ideally not be reached if error handling is correct
    app_log('ERROR', "Headers already sent before image output.", ['script' => __FILE__]);
    // Cannot send image, image might be corrupted or mixed with error output
    if ($image) imagedestroy($image);
    exit;
}

if (!@imagepng($image)) {
    // Log error, but tricky to send a response if headers are already image/png
    app_log('ERROR', "imagepng failed.", ['script' => __FILE__]);
}

// 8. Clean up memory
if ($image) { // Ensure $image is a valid resource before destroying
    imagedestroy($image);
}

?>
