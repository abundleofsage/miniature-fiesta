<?php
// This script provides a user interface to select and download event images.

// --- ACTION REQUIRED ---
// Paste your private Nextcloud iCal subscription URL here.
$ical_url = 'https://cloud.outfrontyouth.org/remote.php/dav/public-calendars/o4BqHRSHaDJjiqjs?export'; // <-- PASTE YOUR URL HERE

// --- Data Fetching and Parsing (Copied from your image generator script) ---
require_once('calendar-functions.php');

// Get all events and filter for only upcoming ones
$all_events = get_calendar_events($ical_url);
$today = date("Y-m-d");
$upcoming_events = array_filter($all_events, function($event) use ($today) {
    return strtotime($event['date']) >= strtotime($today);
});
usort($upcoming_events, function($a, $b) { return strtotime($a['date']) - strtotime($b['date']); });

$events_by_month = [];
foreach ($upcoming_events as $event) {
    $month_year = date('F Y', strtotime($event['date']));
    $events_by_month[$month_year][] = $event;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Outfront Post Image Generator</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-900 text-gray-200">

    <div class="container mx-auto p-4 md:p-8 max-w-4xl">
        <header class="text-center mb-8">
            <h1 class="text-4xl font-bold text-white">Social Media Post Generator</h1>
            <p class="text-gray-400 mt-2">Select an upcoming event to generate a promotional image.</p>
        </header>

        <main class="space-y-12">
            <?php if (empty($events_by_month)): ?>
                <div class="bg-gray-800 rounded-lg p-8 text-center">
                    <h2 class="text-2xl font-bold text-white">No Upcoming Events Found</h2>
                    <p class="text-gray-400 mt-2">Check the iCal feed or add new events to the calendar.</p>
                </div>
            <?php else: ?>
                <?php foreach ($events_by_month as $month => $events): ?>
                    <section>
                        <h2 class="text-2xl font-bold text-pink-500 border-b-2 border-gray-700 pb-2 mb-6"><?php echo $month; ?></h2>
                        <div class="space-y-4">
                            <?php foreach ($events as $event): ?>
                                <?php
                                    $event_date = $event['date'];
                                    $image_url = "generate-post-image.php?date=" . urlencode($event_date);
                                    // Sanitize summary for the filename
                                    $filename_summary = strtolower(preg_replace('/[^a-zA-Z0-9\-]/', '', str_replace(' ', '-', $event['summary'])));
                                    $download_filename = "outfront-post-{$event_date}-{$filename_summary}.png";
                                ?>
                                <div class="bg-gray-800 rounded-lg p-4 flex flex-col md:flex-row items-center justify-between gap-4">
                                    <div class="flex-grow text-center md:text-left">
                                        <p class="font-bold text-lg text-white"><?php echo htmlspecialchars($event['summary']); ?></p>
                                        <p class="text-gray-400 text-sm"><?php echo date('l, F jS', strtotime($event_date)); ?></p>
                                    </div>
                                    <div class="flex items-center gap-4 flex-shrink-0">
                                        <a href="<?php echo $image_url; ?>" target="_blank" class="bg-gray-700 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg transition-colors duration-200">
                                            Preview
                                        </a>
                                        <a href="<?php echo $image_url; ?>" download="<?php echo $download_filename; ?>" class="bg-pink-600 hover:bg-pink-700 text-white font-bold py-2 px-4 rounded-lg transition-colors duration-200">
                                            Download
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            <?php endif; ?>
        </main>

        <footer class="text-center mt-12 text-gray-500">
            <p>&copy; <?php echo date('Y'); ?> Outfront Youth Group</p>
        </footer>

    </div>

</body>
</html>
