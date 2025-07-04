<?php
// Calendar Functions

// --- Data Fetching and Parsing ---
function get_calendar_events($url) {
    $context = ['url' => $url];
    $ical_raw = file_get_contents($url); // Removed @ suppression

    if ($ical_raw === false) {
        app_log('ERROR', 'Failed to fetch iCalendar data.', $context);
        return false; // Return false on failure
    }

    $events = [];
    // if (!$ical_raw) return []; // Already handled by the check above

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

// --- Basic Error Logging ---
define('LOG_FILE', __DIR__ . '/application_errors.log'); // Defines the log file path

/**
 * Logs a message to the defined log file.
 * Prepends a timestamp and includes the script name.
 *
 * @param string $level   Log level (e.g., ERROR, WARNING, INFO)
 * @param string $message The message to log.
 * @param array  $context Optional context array to include in the log.
 */
function app_log($level, $message, $context = []) {
    $timestamp = date('Y-m-d H:i:s');
    $script_name = basename($_SERVER['PHP_SELF'] ?? 'CLI'); // Get script name or 'CLI' if not in web context
    $log_entry = "[{$timestamp}] [{$script_name}] [{$level}]: {$message}";
    if (!empty($context)) {
        $log_entry .= " | Context: " . json_encode($context);
    }
    $log_entry .= PHP_EOL;

    // Use error_log to append to the specified file.
    // Ensure the log file directory is writable by the web server.
    error_log($log_entry, 3, LOG_FILE);
}

// --- Utility to handle script termination with error ---
/**
 * Displays a user-friendly error message and logs the detailed error.
 * Terminates the script.
 *
 * @param string $user_message The message to show to the user.
 * @param string $log_message  The detailed message for the log.
 * @param array  $log_context  Optional context for the log.
 * @param int    $http_status_code HTTP status code to send.
 */
function terminate_script_with_error($user_message, $log_message, $log_context = [], $http_status_code = 500) {
    app_log('ERROR', $log_message, $log_context);
    http_response_code($http_status_code);
    // In a real app, you might render an HTML error page.
    // For these scripts, plain text or JSON is often the output type.
    // Check expected content type or keep it simple.
    // header('Content-Type: text/plain'); // Or application/json as appropriate
    echo $user_message;
    exit;
}

?>
