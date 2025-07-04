# **OutFront Website Documentation**

Woohoo! Welcome to the updated documentation for the OutFront Youth Group website. This document explains how the site works, how to update its content, and how its special features function.

### **Table of Contents**
1.  [File Structure Overview](#i-file-structure-overview)
2.  [How to Update the Website](#ii-how-to-update-the-website)
    *   [Editing config.js](#a-the-easy-way-editing-the-configjs-file)
    *   [Updating Community Resources](#b-updating-the-community-resources-the-dynamic-part)
    *   [Updating Calendar Events](#c-updating-the-calendar-events)
3.  [Key Website Features](#iii-key-website-features)
    *   [Interactive Web Calendar](#a-interactive-web-calendar-on-indexhtml)
    *   [PDF Event Flyer](#b-pdf-event-flyer-generation)
    *   [Image Generation Tools](#c-image-generation-tools)
    *   [Design & Other Features](#d-design--other-features)
4.  [Developer Information](#iv-developer-information)

---

### **I. File Structure Overview**

Your website consists of several key files and folders:

*   **`index.html`**: The main page of the website. It structures the layout and pulls dynamic content (like meeting details, contact info, and form links) from `config.js`. It also includes the interactive web calendar.
*   **`config.js`**: This is the primary "control panel" for easily updating website information. It contains:
    *   `meetingDetails`: For regular meeting time, day, and location.
    *   `contactInfo`: For the group's phone number.
    *   `resourcesUrl`: Link to the CSV file for community resources.
    *   `formLinks`: URLs for various contact and volunteer forms.
    *   `fallbackResources`: A default list of resources if the live CSV can't be fetched.
    *   `konamiLogoUrl`: URL for the image used in the Konami code easter egg.
*   **`download-flyer.php`**: A server-side script with two main roles:
    1.  It generates downloadable PDF flyers for monthly events (with an option for color or B&W logo).
    2.  It provides event data in JSON format to the interactive web calendar on `index.html`.
*   **`calendar-functions.php`**: A helper script for `download-flyer.php`; it contains functions to fetch and process iCalendar (.ics) data from the Nextcloud calendar.
*   **`resource-proxy.php`**: A helper script that allows `index.html` to securely fetch the Community Resources list (a CSV file) from your Nextcloud server, bypassing browser cross-origin (CORS) errors.
*   **`image-generator-ui.php`**: A web interface for creating custom shareable images for events or social media posts.
*   **`generate-flyer-image.php`**: A server-side script used by the image generator UI to create image versions of event flyers (e.g., for social media).
*   **`generate-post-image.php`**: A server-side script used by the image generator UI to create other types of social media images or general posts.
*   **`fpdf/`**: This directory contains the FPDF library, which `download-flyer.php` uses to create PDF files. You generally shouldn't need to edit anything here.
*   **Font Files:**
    *   `Fredoka-Bold.ttf`, `Fredoka-Regular.ttf`: Custom font files. (Currently, "Poppins" is the primary font loaded in `index.html`; these Fredoka fonts might be used by the image generation tools or are available for future use.)
*   **Image Assets:**
    *   `favicon.ico`: The icon that appears in the browser tab.
    *   `outfront-logo.png`: The main color logo for OutFront.
    *   `outfront-logo-bw.png`: A black-and-white version of the logo, used primarily for PDF flyers.
*   **`LICENSE`**: Contains the software license for this project.
*   **`.github/workflows/`**: Contains YAML files (`deploy.yml`, `pr-deploy.yml`) that define GitHub Actions for automating website deployment. (Developer-focused)
*   **`.gitignore`**: A standard Git file specifying files and directories that Git should ignore. (Developer-focused)

---

### **II. How to Update the Website**

#### **A. The Easy Way: Editing the `config.js` File**

For most common updates, you only need to edit **`config.js`**. This file acts as a central place for information that appears throughout the site.

*   **To change meeting time/location:** Edit the `meetingDetails` object (e.g., `time`, `day`, `locationName`, `locationAddress`).
*   **To change the phone number:** Edit the `contactInfo.phone` value.
*   **To change volunteer/contact form links:** Edit the URLs in the `formLinks` object (e.g., `prideVolunteer`, `facilitatorVolunteer`, `generalContact`).
*   **To change the source of the dynamic resources:** Update the `resourcesUrl`. This should be a direct link to your CSV file on Nextcloud (see below).
    *   *Note on `resourcesUrl`*: The current URL in `config.js` might include `?accept=zip`. While the proxy fetches this, ensure the linked resource is directly providing CSV data.
*   **To change the Konami code easter egg logo:** Update the `konamiLogoUrl` with a new image URL.

#### **B. Updating the Community Resources (The Dynamic Part!)**

The "Community Resources" section on `index.html` is designed to be easily updated without touching the main website code.

1.  **Log in to your Nextcloud server.**
2.  **Navigate to and open the `community-resources.csv` file** (the one linked in `config.js`'s `resourcesUrl`).
3.  **Edit the list directly in Nextcloud.**
    *   Each row in the spreadsheet represents one resource card.
    *   The columns **must** be: `icon`, `title`, `description`, `url`.
    *   The `icon` name must be a valid icon name from the [Lucide Icons](https://lucide.dev/) library (e.g., `shield-alert`, `phone`, `users`).
4.  **Save the CSV file.** The website will automatically fetch the updated list via `resource-proxy.php` the next time a visitor loads the page.
    *   **Fallback:** If the site can't reach Nextcloud or there's an error fetching the CSV, it will automatically display the list from the `fallbackResources` array in `config.js`.

#### **C. Updating the Calendar Events**

All event information for both the interactive web calendar and the downloadable PDF flyer is pulled directly from your public Nextcloud calendar. **You do not need to edit any website files to manage events.**

*   **To add an event:** Simply create a new event in your Nextcloud calendar that is public or shared on the calendar feed.
*   **To edit or remove an event:** Just edit or delete the event in Nextcloud.

If you ever need to change the source iCalendar feed itself (e.g., if the Nextcloud calendar URL changes):
1.  Open **`download-flyer.php`**.
2.  Find the `$ical_url` variable near the top of the file.
3.  Replace the existing URL with your new public iCal subscription link.

---

### **III. Key Website Features**

#### **A. Interactive Web Calendar (on `index.html`)**

The main page (`index.html`) features an interactive calendar:

*   **Monthly View:** Displays events in a familiar calendar grid.
*   **Navigation:** Users can move to the previous or next month.
*   **Event Indicators:** Days with events are highlighted.
*   **Event Details Modal:** Clicking on a day with events opens a pop-up window showing details for each event on that day (summary, time, location, description).
*   **Data Source:** Event data is fetched dynamically from `download-flyer.php` (which in turn gets it from your Nextcloud calendar).

#### **B. PDF Event Flyer Generation**

The website can generate a downloadable PDF flyer for any given month's events:

*   **Access:**
    *   Through the "Download Flyer as PDF" icon <i data-lucide="download"></i> on the interactive web calendar in `index.html`. The month/year of the currently viewed calendar will be used for the flyer.
    *   By directly accessing `download-flyer.php` (though typically done via the UI).
*   **Content:** Includes event dates, summaries, times, locations, and descriptions, plus OutFront branding and QR codes.
*   **Logo Choice (New!):** The interactive calendar on `index.html` has a "Use Color Logo on Flyer" checkbox.
    *   If checked, the PDF flyer will use the color `outfront-logo.png`.
    *   If unchecked (default), it will use the `outfront-logo-bw.png`.
*   **Source:** Event data is pulled from the Nextcloud calendar via the `$ical_url` in `download-flyer.php`.

#### **C. Image Generation Tools**

A new suite of tools allows for creating shareable images:

*   **UI Access:** Navigate to `image-generator-ui.php` in your browser.
*   **Purpose:** To generate images for social media, event promotion, or other visual communication.
*   **Backend Scripts:**
    *   `generate-flyer-image.php`: Creates image versions of event flyers (similar to the PDF, but as a graphic).
    *   `generate-post-image.php`: Likely used for creating other types of social media cards or posts.
*   *(Note: Specific features and usage details of the image generator would be best explored by using the UI itself.)*

#### **D. Design & Other Features**

The website incorporates several design elements and functionalities:

*   **Custom Font:** Primarily uses the "Poppins" font for a friendly, modern look. (Additional "Fredoka" font files are present, potentially for use in the image generators).
*   **Angled Section Dividers:** Sections are separated by colorful, angled dividers, creating a dynamic visual flow with a muted rainbow color progression.
*   **On-Scroll Animations:** Content elements subtly fade in as the user scrolls down the page.
*   **Interactive Cards:** Resource cards (in the "Community Resources" section) and volunteer opportunity cards (in the "Contact" section) have a slight "tilt" effect on hover.
*   **Konami Code Easter Egg:**
    *   **Trigger:** Press the following keys in sequence: `Up Arrow`, `Up Arrow`, `Down Arrow`, `Down Arrow`, `Left Arrow`, `Right Arrow`.
    *   **Effect:** An animated logo (specified by `konamiLogoUrl` in `config.js`) will fly across the screen.
*   **Header:** Features the Southern Colorado Equality Alliance (SCEA) logo (linking to SCEA's website) and the OutFront logo, along with main navigation and a "Donate" button.
*   **Embedded Map:** The "Meetings" section includes an embedded Google Map showing the meeting location.
*   **Donate Section:** Provides information on how donations to SCEA support OutFront, with a direct link to PayPal.
*   **Dynamic Footer:** The copyright year in the footer updates automatically. Includes a link to OutFront's Facebook page.

---

### **IV. Developer Information**

*   **Deployment:** Website deployment is automated using GitHub Actions. Configuration files are located in the `.github/workflows/` directory.
    *   `deploy.yml`: Typically handles deployments for the main/production site.
    *   `pr-deploy.yml`: May handle preview deployments for pull requests.
*   **Dependencies:**
    *   PHP (for backend scripts like `download-flyer.php`, `resource-proxy.php`, image generators).
    *   cURL PHP extension (required by `resource-proxy.php`).
    *   FPDF library (included in `fpdf/` for PDF generation).
*   **Styling:** Uses Tailwind CSS for utility-first styling, with custom CSS for unique design elements in `index.html`.
*   **Icons:** Uses [Lucide Icons](https://lucide.dev/) for most icons on the site.
