<?php
// Calendar Functions

// --- Data Fetching and Parsing ---
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
?>
