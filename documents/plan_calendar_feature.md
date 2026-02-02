# Plan: Calendar Feature

## Goal
Implement a flexible calendar feature for StaticForge that allows non-technical users to manage events via individual Markdown files and display them using a shortcode. The feature will eventually be extracted as an external plugin.

**Gold Standard Compliance**: This feature will adhere to the patterns established in `src/Features/RssFeed`, specifically:
*   Implementing `ConfigurableFeatureInterface` for validation.
*   Separating Logic (`Service`) from Registration (`Feature`).
*   Using Dependency Injection via the Container.

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

## 2. Configuration (siteconfig.yaml)
To keep the shortcode simple and clean (`[[calendar name="work"]]`), configuration will be centralized in `siteconfig.yaml`.

```yaml
calendars:
  work:
    view: month          # Default view (month, week, year)
    start: "today"       # Start date limit
    end: "+1 year"       # End date limit
    template: default    # Template name
  community:
    view: week
    start: "-1 month"
```

## 3. Shortcode Interface
The feature will use the existing StaticForge shortcode system.

*   **Syntax:** `[[calendar name="calendar_name"]]`
*   **Parameters:**
    *   `name` (required): Corresponds to the key in `siteconfig.yaml` and the folder name in `content/calendars/`.
    *   *(Optional overrides)*: While config is central, shortcode attributes can override `siteconfig.yaml` settings if specific one-off changes are needed.

## 4. Technical Implementation

### A. Backend (PHP)
1.  **`CalendarService`**:
    *   Responsible for scanning `content/calendars/{name}/`.
    *   Parses Markdown files to extract frontmatter and body.
    *   **Filtering:** Filters events based on the `start` and `end` parameters (from config or shortcode) *before* sending to frontend.
2.  **`Feature` Class**:
    *   Implements `ConfigurableFeatureInterface`.
    *   **Validation:** Requires `calendars` key in `siteconfig.yaml` if calendars are used.
    *   Registers `CalendarShortcode`.
3.  **`CalendarShortcode` Class**:
    *   Accepts `name` attribute.
    *   Retrieves config for that calendar from `siteconfig.yaml`.
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
    *   **Navigation:** "Next", "Previous", "Today" buttons. Respects the `start` and `end` limits passed from PHP.
    *   **Modal:** Clicking an event opens a modal with full details.
2.  **Templates (Twig)**:
    *   `templates/shortcodes/calendar-wrapper.twig`: The container div and JSON data injection.
3.  **Styles**:
    *   CSS Grid/Flexbox for the layouts.
    *   Responsive design for mobile.

## 5. Work Plan
1.  Create `src/Features/Calendar/` directory structure.
2.  Implement `CalendarService` with date range filtering logic.
3.  Implement `Feature` class with `ConfigurableFeatureInterface` support.
4.  Implement `CalendarShortcode` to output the container + JSON.
5.  Develop `calendar.js` to handle the rendering of Week/Month/Year views and navigation.
6.  Create CSS for the 3 views.
7.  Test date limits, view switching, and config loading.
