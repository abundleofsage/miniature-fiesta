<?php
// Application Configuration

// --- iCalendar URL ---
// Paste your private Nextcloud iCal subscription URL here.
define('ICAL_URL', 'https://cloud.outfrontyouth.org/remote.php/dav/public-calendars/o4BqHRSHaDJjiqjs?export');

// --- Font Configuration ---
// IMPORTANT: Ensure these TrueType Font (.ttf) files exist in the specified paths.
// These paths are relative to the script that includes this config file,
// or absolute paths can be used. For simplicity, using __DIR__ assumes fonts are in the same directory.
define('FONT_REGULAR_PATH', __DIR__ . '/Fredoka-Regular.ttf');
define('FONT_BOLD_PATH', __DIR__ . '/Fredoka-Bold.ttf');

// --- Default Flyer Event Info ---
// Used in download-flyer.php if event details are missing or match these.
define('DEFAULT_EVENT_TIME_STRING', '5:30 PM - 7:00 PM');
define('DEFAULT_EVENT_LOCATION_STRING', '128 S. Union Ave, Pueblo, CO 81003');

// --- Add other global configurations here as needed ---

?>
