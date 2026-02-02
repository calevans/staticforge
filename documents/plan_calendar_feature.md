# Plan: Calendar Feature

## Goal
Implement a flexible calendar feature for StaticForge that allows non-technical users to manage events via individual Markdown files and display them using a shortcode. The feature will eventually be extracted as an external plugin.

## 1. Data Structure (User Experience)
To ensure ease of use for non-technical users and avoid merge conflicts/data loss, each event will be a separate file.

*   **Location:** `content/calendars/{calendar_name}/{date}-{slug}.md`
    *   Example: `content/calendars/work/2023-10-31-halloween-party.md`
    *   Example: `content/calendars/community/2023-11-05-meetup.md`
*   **File Format:** Markdown with Frontmatter.
*   **Frontmatter Fields:**
    *   `title` (required): Event title.
    *   `date` (required): Event date (YYYY-MM-DD).
    *   `time` (optional): Start time (free text allowed, e.g., "2pm", "Afternoon").
    *   `end_time` (optional): End time.
    *   `location` (optional): Venue or link.
*   **Body Content:**
    *   Full description of the event.
    *   Supports standard Markdown (links, bold, lists, etc.).

## 2. Shortcode Interface
The feature will use the existing StaticForge shortcode system.

*   **Syntax:** `[[calendar name="calendar_name" view="month" start="today" end="+6 months"]]`
*   **Parameters:**
    *   `name` (required): Corresponds to the folder name in `content/calendars/`.
    *   `view` (optional): Initial view. Options: `month` (default), `week`, `year`.
    *   `start` (optional): Start date limit (relative PHP formats allowed, e.g., "today", "2023-01-01"). Default: "today".
    *   `end` (optional): End date limit (e.g., "+6 months", "2023-12-31"). Default: "+1 year".
    *   `template` (optional): Override the HTML wrapper.

## 3. Technical Implementation

### A. Backend (PHP)
1.  **`CalendarService`**:
    *   Scans `content/calendars/{name}/`.
    *   Parses Markdown files.
    *   **Filtering:** Filters events based on the `start` and `end` parameters *before* sending to frontend to reduce payload and enforce limits.
2.  **`CalendarShortcode` Class**:
    *   Accepts new attributes (`view`, `start`, `end`).
    *   Calls `CalendarService` to get filtered data.
    *   Outputs the HTML container and injects the Event Data as a JSON object.
    *   Enqueues/Includes the `calendar.js` script.

### B. Frontend (HTML/CSS/JS)
This will be an **Interactive JS Widget**.

1.  **`calendar.js`**:
    *   **Class:** `StaticForgeCalendar`
    *   **State:** Tracks `currentDate`, `currentView` (Month/Week/Year), and `events` array.
    *   **Views:**
        *   **Month:** Standard 7x5 grid.
        *   **Week:** 7 columns (Sun-Sat), list of events in each column. (Not an hourly grid).
        *   **Year:** 12 mini-month grids.
    *   **Navigation:** "Next", "Previous", "Today" buttons. Respects the `start` and `end` limits passed from PHP (disabling buttons if out of range).
    *   **Modal:** Clicking an event opens a modal with full details.
2.  **Templates (Twig)**:
    *   `templates/shortcodes/calendar-wrapper.twig`: The container div and JSON data injection.
3.  **Styles**:
    *   CSS Grid/Flexbox for the layouts.
    *   Responsive design for mobile.

## 4. Work Plan
1.  Create `src/Features/Calendar/` directory structure.
2.  Implement `CalendarService` with date range filtering logic.
3.  Implement `CalendarShortcode` to output the container + JSON.
4.  Develop `calendar.js` to handle the rendering of Week/Month/Year views and navigation.
5.  Create CSS for the 3 views.
6.  Test date limits and view switching.
