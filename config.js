// ===================================================================
// === OutFront Website Configuration File                         ===
// ===================================================================
// This file contains all the key information that might change over time.
// Edit the values here to update the website without touching the main HTML.
// ===================================================================

const config = {
    // --- Meeting Details ---
    // Update these when the regular meeting time or location changes.
    meetingDetails: {
        time: "5:30 PM - 7:00 PM",
        day: "Every Friday",
        locationName: "Colorado Wins",
        locationAddress: "128 S Union Ave, Pueblo, CO 81003"
    },

    // --- Contact Information ---
    contactInfo: {
        phone: "(719) 501-3278"
    },

    // --- Dynamic Resource URL ---
    // This is the link to the CSV file on Nextcloud for the community resources.
    resourcesUrl: 'https://cloud.outfrontyouth.org/public.php/dav/files/XqHdffmmrkPRXeM/?accept=zip',

    // --- Volunteer & Contact Form Links ---
    formLinks: {
        prideVolunteer: 'https://cloud.outfrontyouth.org/apps/forms/s/qYYcnZckpqw9cKjMEZ7ZDK6N',
        facilitatorVolunteer: 'https://cloud.outfrontyouth.org/apps/forms/s/Zfob8D79EKi5E3tQrac5jf5i',
        generalContact: 'https://cloud.outfrontyouth.org/apps/forms/s/FpmyS9A4MPrc3t5HYiEFtAFE'
    },

    // --- Fallback Resources ---
    // This list will be used if the website cannot fetch the list from Nextcloud.
    fallbackResources: [
        {
            icon: 'shield-alert',
            title: '988 Lifeline',
            description: '24/7 crisis support. Call or text 988 and press 3 for an LGBTQI+-trained counselor.',
            url: 'https://988lifeline.org/chat/'
        },
        {
            icon: 'phone-forwarded',
            title: 'Trans Lifeline',
            description: 'Peer support for trans people, run by trans people. Call (877) 565-8860.',
            url: 'https://translifeline.org/'
        },
        {
            icon: 'message-circle',
            title: 'The Trevor Project',
            description: 'Crisis support and suicide prevention for LGBTQ young people.',
            url: 'https://www.thetrevorproject.org/'
        },
        {
            icon: 'phone',
            title: "LGBT Nat'l Youth Talkline",
            description: 'Confidential peer support. Call (800) 246-7743.',
            url: 'tel:1-800-246-7743'
        },
        {
            icon: 'users',
            title: 'PFLAG',
            description: 'Support, education, and advocacy for LGBTQ+ people, their families, and allies.',
            url: 'https://pflag.org/'
        },
        {
            icon: 'heart-pulse',
            title: 'Colorado Dept. of Health',
            description: 'State-level health and wellness information for LGBTQ Coloradans.',
            url: 'https://cdphe.colorado.gov/LGBTQ-health'
        }
    ],

    // --- Easter Egg Configuration ---
    konamiLogoUrl: "https://images.squarespace-cdn.com/content/v1/5b5776b2af20962f0511952c/e6e59ae4-1fb2-4679-8f03-4da69779a43c/SCEAlogo300dpiPRINT.png?format=1500w" // Image used for the Konami code easter egg. Can be a local path or a full URL.
};
