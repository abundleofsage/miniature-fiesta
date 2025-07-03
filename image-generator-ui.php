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
    <title>OutFront Post Image Generator</title>
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
            <section>
                <h2 class="text-2xl font-bold text-sky-500 border-b-2 border-gray-700 pb-2 mb-6">Monthly Flyer Generator</h2>
                <div class="bg-gray-800 rounded-lg p-6 shadow-lg space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="flyer-month-select" class="block text-sm font-medium text-gray-300 mb-1">Month:</label>
                            <select id="flyer-month-select" name="flyer_month" class="bg-gray-700 border border-gray-600 text-white text-sm rounded-lg focus:ring-sky-500 focus:border-sky-500 block w-full p-2.5">
                                <?php
                                    for ($m = 1; $m <= 12; $m++) {
                                        $month_value = strtolower(date('F', mktime(0, 0, 0, $m, 1)));
                                        $month_display = date('F', mktime(0, 0, 0, $m, 1));
                                        $selected = (strtolower(date('F')) == $month_value) ? 'selected' : '';
                                        echo "<option value=\"$month_value\" $selected>$month_display</option>";
                                    }
                                ?>
                            </select>
                        </div>
                        <div>
                            <label for="flyer-year-input" class="block text-sm font-medium text-gray-300 mb-1">Year:</label>
                            <input type="number" id="flyer-year-input" name="flyer_year" value="<?php echo date('Y'); ?>" class="bg-gray-700 border border-gray-600 text-white text-sm rounded-lg focus:ring-sky-500 focus:border-sky-500 block w-full p-2.5">
                        </div>
                    </div>
                    <div>
                        <label for="flyer-color-logo-toggle" class="flex items-center cursor-pointer text-sm text-gray-300">
                            <input type="checkbox" id="flyer-color-logo-toggle" class="form-checkbox h-4 w-4 text-sky-500 bg-gray-700 border-gray-600 rounded focus:ring-sky-500">
                            <span class="ml-2">Use Color Logo</span>
                        </label>
                    </div>
                    <p class="text-sm text-gray-400 pt-1">Flyer generation may take a few moments. Please click once and wait.</p>
                    <div class="flex items-center gap-4 flex-shrink-0 pt-2">
                        <a href="#" id="flyer-download-pdf-link" target="_blank" class="bg-sky-600 hover:bg-sky-700 text-white font-bold py-2 px-4 rounded-lg transition-colors duration-200 w-full text-center md:w-auto">
                            Download PDF
                        </a>
                        <a href="#" id="flyer-download-png-link" target="_blank" class="bg-teal-600 hover:bg-teal-700 text-white font-bold py-2 px-4 rounded-lg transition-colors duration-200 w-full text-center md:w-auto">
                            Download PNG
                        </a>
                    </div>
                </div>
            </section>

            <?php if (empty($events_by_month)): ?>
                <div class="bg-gray-800 rounded-lg p-8 text-center">
                    <h2 class="text-2xl font-bold text-white">No Upcoming Event Post Images Found</h2>
                    <p class="text-gray-400 mt-2">Check the iCal feed or add new events to the calendar for individual post images.</p>
                </div>
            <?php else: ?>
                <?php foreach ($events_by_month as $month => $events): ?>
                    <section>
                        <h2 class="text-2xl font-bold text-pink-500 border-b-2 border-gray-700 pb-2 mb-6">Upcoming Event Posts for <?php echo $month; ?></h2>
                        <div class="space-y-4">
                            <?php foreach ($events as $event): ?>
                                <?php
                                    $event_date = $event['date'];
                                    // Base image URL
                                    $base_image_url = "generate-post-image.php?date=" . urlencode($event_date);
                                    // Sanitize summary for the filename
                                    $filename_summary = strtolower(preg_replace('/[^a-zA-Z0-9\-]/', '', str_replace(' ', '-', $event['summary'])));
                                    $download_filename_base = "outfront-post-{$event_date}-{$filename_summary}";
                                ?>
                                <div class="bg-gray-800 rounded-lg p-6 shadow-lg event-card space-y-4" data-event-date="<?php echo urlencode($event_date); ?>" data-filename-base="<?php echo $download_filename_base; ?>">
                                    <div class="flex-grow text-center md:text-left">
                                        <p class="font-bold text-xl text-white"><?php echo htmlspecialchars($event['summary']); ?></p>
                                        <p class="text-gray-400 text-md"><?php echo date('l, F jS', strtotime($event_date)); ?></p>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label for="template-select-<?php echo $event_date; ?>" class="block text-sm font-medium text-gray-300 mb-1">Template:</label>
                                            <select id="template-select-<?php echo $event_date; ?>" name="template" class="template-select bg-gray-700 border border-gray-600 text-white text-sm rounded-lg focus:ring-pink-500 focus:border-pink-500 block w-full p-2.5">
                                                <option value="1" selected>Classic</option>
                                                <option value="2">No Title</option>
                                                <option value="3">Side Banner</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label for="color-select-<?php echo $event_date; ?>" class="block text-sm font-medium text-gray-300 mb-1">Accent Color:</label>
                                            <select id="color-select-<?php echo $event_date; ?>" name="accent_color" class="color-select bg-gray-700 border border-gray-600 text-white text-sm rounded-lg focus:ring-pink-500 focus:border-pink-500 block w-full p-2.5">
                                                <option value="">Random (Default)</option>
                                                <option value="EC4899">Hot Pink</option>
                                                <option value="34D399">Bright Teal</option>
                                                <option value="FBBF24">Bright Amber</option>
                                                <option value="8B5CF6">Bright Violet</option>
                                                <option value="F97316">Bright Orange</option>
                                                <option value="EF4444">Red</option>
                                                <option value="3B82F6">Blue</option>
                                                <option value="10B981">Green</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-4 flex-shrink-0 pt-2">
                                        <a href="<?php echo $base_image_url; ?>" target="_blank" class="preview-link bg-gray-700 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg transition-colors duration-200 w-full text-center md:w-auto">
                                            Preview
                                        </a>
                                        <a href="<?php echo $base_image_url; ?>" download="<?php echo $download_filename_base . ".png"; ?>" class="download-link bg-pink-600 hover:bg-pink-700 text-white font-bold py-2 px-4 rounded-lg transition-colors duration-200 w-full text-center md:w-auto">
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
            <p>&copy; <?php echo date('Y'); ?> OutFront Youth Group</p>
        </footer>

    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Event post image generator logic
            const eventCards = document.querySelectorAll('.event-card');
            eventCards.forEach(card => {
                const templateSelect = card.querySelector('.template-select');
                const colorSelect = card.querySelector('.color-select');
                const previewLink = card.querySelector('.preview-link');
                const downloadLink = card.querySelector('.download-link');
                const eventDate = card.dataset.eventDate;
                const filenameBase = card.dataset.filenameBase;

                function updateEventPostLinks() {
                    const selectedTemplate = templateSelect.value;
                    const selectedColor = colorSelect.value;
                    let baseUrl = `generate-post-image.php?date=${eventDate}`;
                    let newFilename = `${filenameBase}`;
                    if (selectedTemplate) {
                        baseUrl += `&template=${selectedTemplate}`;
                        newFilename += `-template${selectedTemplate}`;
                    }
                    if (selectedColor) {
                        baseUrl += `&accent_color=${selectedColor}`;
                        newFilename += `-color${selectedColor}`;
                    }
                    newFilename += '.png';
                    previewLink.href = baseUrl;
                    downloadLink.href = baseUrl;
                    downloadLink.download = newFilename;
                }

                if (templateSelect && colorSelect && previewLink && downloadLink) {
                    templateSelect.addEventListener('change', updateEventPostLinks);
                    colorSelect.addEventListener('change', updateEventPostLinks);
                    updateEventPostLinks(); // Initial call
                }
            });

            // Monthly flyer generator logic
            const flyerMonthSelect = document.getElementById('flyer-month-select');
            const flyerYearInput = document.getElementById('flyer-year-input');
            const flyerColorLogoToggle = document.getElementById('flyer-color-logo-toggle');
            const flyerDownloadPdfLink = document.getElementById('flyer-download-pdf-link');
            const flyerDownloadPngLink = document.getElementById('flyer-download-png-link');

            function updateFlyerDownloadLinks() {
                const month = flyerMonthSelect.value;
                const year = flyerYearInput.value;
                const color = flyerColorLogoToggle.checked;

                const pdfUrl = `download-flyer.php?month=${month}&year=${year}${color ? '&color=true' : ''}`;
                flyerDownloadPdfLink.href = pdfUrl;
                flyerDownloadPdfLink.download = `outfront-flyer-${month}-${year}${color ? '-color' : ''}.pdf`;

                const pngUrl = `generate-flyer-image.php?month=${month}&year=${year}${color ? '&color=true' : ''}`;
                flyerDownloadPngLink.href = pngUrl;
                flyerDownloadPngLink.download = `outfront-flyer-${month}-${year}${color ? '-color' : ''}.png`;
            }

            if (flyerMonthSelect && flyerYearInput && flyerColorLogoToggle && flyerDownloadPdfLink && flyerDownloadPngLink) {
                flyerMonthSelect.addEventListener('change', updateFlyerDownloadLinks);
                flyerYearInput.addEventListener('input', updateFlyerDownloadLinks); // Use input for number field
                flyerColorLogoToggle.addEventListener('change', updateFlyerDownloadLinks);
                updateFlyerDownloadLinks(); // Initial call
            }
        });
    </script>
</body>
</html>
