# **OutFront Website Documentation**

Woohoo\! Welcome to the final documentation for the OutFront Youth Group website. This document explains how the site works, how to update its content, and how its special features function.

### **I. File Structure Overview**

Your website consists of a few key files and folders:

* **index.html**: This is the main file for the website. It contains the structure and layout, but most of the content is now pulled from config.js.  
* **config.js**: **This is the new "control panel" for the website.** This file holds all the important information that changes often, like meeting times, phone numbers, and form links.  
* **download-flyer.php**: This is a server-side script that acts as the "brains" for the calendar. It fetches event data from your iCal feed and generates the downloadable PDF flyer.  
* **resource-proxy.php**: This is a helper script that allows the website to securely fetch the Community Resources list from your Nextcloud server, bypassing browser security (CORS) errors.  
* **fpdf/**: This directory contains the FPDF library, which is the tool the download-flyer.php script uses to create PDF files. You should not need to edit anything in this folder.  
* **favicon.ico**: The icon that appears in the browser tab.  
* **outfront-logo.png**: Your main color logo.  
* **outfront-logo-bw.png**: Your black-and-white logo for the PDF flyer.

### **II. How to Update the Website**

#### **A. The Easy Way: Editing the config.js File**

For most common updates, you only need to edit **config.js**.

* **To change meeting time/location:** Edit the meetingDetails object.  
* **To change the phone number:** Edit the contactInfo.phone value.  
* **To change volunteer/contact form links:** Edit the URLs in the formLinks object.  
* **To change the source of the dynamic resources:** Update the resourcesUrl.

#### **B. Updating the Community Resources (The Dynamic Part\!)**

The "Community Resources" section is designed to be easily updated without touching the main website code.

1. **Log in to your Nextcloud server.**  
2. **Navigate to and open the community-resources.csv file.**  
3. **Edit the list directly in Nextcloud.**  
   * Each row in the spreadsheet represents one resource card.  
   * The columns must be: icon, title, description, url.  
   * The icon name must be a valid icon name from the [Lucide Icons](https://lucide.dev/) library.  
4. **Save the CSV file.** The website will automatically fetch the updated list the next time a visitor loads the page.  
   * **Fallback:** If the site can't reach Nextcloud, it will automatically display the list from the fallbackResources array in config.js.

#### **C. Updating the Calendar Events**

All event information is pulled directly from your public Nextcloud calendar. **You do not need to edit any website files to manage events.**

* **To add an event:** Simply create a new event in your Nextcloud calendar.  
* **To edit or remove an event:** Just edit or delete the event in Nextcloud.

If you ever need to change the source calendar itself, you only need to edit one line in **download-flyer.php**. Find the $ical\_url variable at the top of the file and replace the URL with your new iCal link.

### **III. Website Design & Features**

The website has several custom design features to give it a fun, energetic feel.

* **Custom Font:** The site uses the "Poppins" font for a friendly, modern look.  
* **Angled Section Dividers:** The sections are separated by colorful, angled dividers to create a sense of movement. The colors follow a muted rainbow progression down the page. This is controlled by the CSS in the \<style\> block at the top of index.html.  
* **On-Scroll Animations:** Content fades in as the user scrolls down the page, making the experience more interactive.  
* **Interactive Cards:** The resource and volunteer cards have a fun "tilt" effect when you hover over them.