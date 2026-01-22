# StaticForge Theme Ideas & Agent Specifications

## General StaticForge Developer Guide
*(COPY THIS SECTION TO EVERY THEME AGENT)*

**System Overview:**
You are building a theme for **StaticForge**, a PHP 8.4+ static site generator. The template engine is **Twig**.

**Technical Constraints (Non-Negotiable):**
1.  **No CSS Frameworks:** Do NOT use Tailwind, Bootstrap, or any externally hosted CSS framework. You must write Vanilla CSS (Modern CSS3+ with Variables/Custom Properties).
2.  **Semantic HTML:** Use `<article>`, `<nav>`, `<aside>`, `<main>`, etc.
3.  **Responsive:** Mobile-first approach.

**Directory Structure:**
All files must govern the specific theme directory: `templates/{theme_name}/`.
Typical required file structure:
```
templates/{theme_name}/
├── assets/
│   ├── css/
│   │   └── main.css       # Primary stylesheet
│   └── js/
│       └── main.js        # Optional vanilla JS
├── base.html.twig         # Master Layout (Everything extends this)
├── index.html.twig        # Landing Page (extends base)
├── standard_page.html.twig # Default single page (extends base)
├── category.html.twig     # Category listing (extends base)
└── partials/              # Reusable snippets (header, footer, etc.)
```

**Cheatsheet: Essential Twig Variables:**
*   `{{ site_base_url }}`: Root URL with trailing slash. *Always prefix assets with this.* (e.g., `{{ site_base_url }}assets/css/main.css`).
*   `{{ content }}`: The main body content (HTML) of the page.
*   `{{ title }}`: The page title.
*   `{{ site_name }}`: The global site name.
*   `{{ description }}`: Meta description.
*   `{{ menu1 }}`: The primary sidebar menu (HTML list).
*   `{{ menu_top }}`: The header menu (HTML list).
*   `{{ cache_buster }}`: A string hash for asset versioning (e.g., `main.css?{{ cache_buster }}`).

**Base Layout Boilerplate:**
```twig
<!DOCTYPE html>
<html lang="en">
<head>
    <title>{{ title }} | {{ site_name }}</title>
    <link rel="stylesheet" href="{{ site_base_url }}assets/css/main.css?{{ cache_buster }}">
    {% block extra_head %}{% endblock %}
</head>
<body>
    <header>{# Logo & menu_top #}</header>
    <main>
        {% block body %}
            {{ content|raw }}
        {% endblock %}
    </main>
    <footer>{# Copy & Links #}</footer>
</body>
</html>
```

---

## 1. The "Aperture" (Photographer Portfolio)

### Agent Brief
**Objective:** Build a visual-first "canvas" theme for photographers. No scroll fatigue, immersive.
**Target Audience:** Photographers, Visual Artists.

### Design Specifications
*   **Colors:** Dark Mode Default. Background: `#111111`, Text: `#eeeeee`. Accent: `#FFD700` (Gold - minimal use).
*   **Typography:** [Google Fonts] Headers: 'Montserrat' (Light/300), Body: 'Open Sans'.
*   **Layout:**
    *   **Home:** Full-viewport CSS Grid Masonry. No visible scrollbar if possible (or styled minimally).
    *   **Nav:** Fixed overlay "Icon only" sidebar on the left `width: 60px`. Expands on hover.

### Technical Implementation Queries
*   **Grid:** Use `display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 4px;`.
*   **Images:** All thumbnails must have `object-fit: cover; aspect-ratio: 1 / 1;`.
*   **Interactivity:** CSS `:hover` on grid items should scale them `transform: scale(1.02); z-index: 10;`.

### Required Files
1.  `templates/aperture/base.html.twig`: The dark canvas wrapper.
2.  `templates/aperture/index.html.twig`: The masonry grid. Assume a variable `files` or `category_files` is passed.
3.  `templates/aperture/assets/css/main.css`: The dark theme styles.

---

## 2. The "Fanzine" (Brutalist / Punk)

### Agent Brief
**Objective:** A rebellious, "anti-design" blog theme. Loud, raw, and high contrast.
**Target Audience:** Personal bloggers, Music reviewers, Artists.

### Design Specifications
*   **Colors:** Monochrome + 1 Neon. BG: `#ffffff`, Text: `#000000`, Accent: `#FF00FF` (Magenta).
*   **Typography:** [Google Fonts] Headers: 'Rubik Mono One' (Slab/Blocky). Body: 'Space Mono' (Monospace).
*   **Layout:**
    *   **Borders:** Everything gets `border: 3px solid #000;`. No rounded corners (`border-radius: 0`).
    *   **Alignment:** Intentional misalignment. Header left, content centered, images overlapping text.
*   **Vibe:** Xeroxed paper, cut-and-paste aesthetic.

### Technical Implementation Queries
*   **Mix-Blend-Mode:** Use `mix-blend-mode: exclusion;` for text overlaying images.
*   **CSS Grid:** Overlap content. `grid-area: 1 / 1 / 3 / 3;` so text flows *over* images.
*   **Shadows:** Hard shadows, no blur. `box-shadow: 5px 5px 0px #000;`.

### Required Files
1.  `templates/fanzine/base.html.twig`
2.  `templates/fanzine/standard_page.html.twig`
3.  `templates/fanzine/assets/css/main.css`

---

## 3. The "Horizon" (Horizontal Scrolling)

### Agent Brief
**Objective:** A horizontal-scroll narrative theme. Ideally suited for storytelling or timelines.
**Target Audience:** Writers, Travel Blogs.

### Design Specifications
*   **Colors:** Earth tones. Beige `#F5F5DC`, Deep Brown `#4A3B32`.
*   **Typography:** [Google Fonts] Headers: 'Playfair Display' (Elegant Serif). Body: 'Lato'.
*   **Layout:**
    *   **Container:** `main` is `display: flex; flex-direction: row; overflow-x: auto; height: 100vh;`.
    *   **Sections:** Each `<article>` is `min-width: 80vw; height: 100%;`.

### Technical Implementation Queries
*   **Scroll Snap:** Mandatory. `scroll-snap-type: x mandatory;`. Children get `scroll-snap-align: start;`.
*   **Navigation:** Fixed bottom bar.
*   **Mouse Wheel:** (Optional) Add simple JS to convert vertical scroll to horizontal scroll.

### Required Files
1.  `templates/horizon/base.html.twig`
2.  `templates/horizon/index.html.twig` (The horizontal track)
3.  `templates/horizon/assets/css/main.css`
4.  `templates/horizon/assets/js/hscroll.js` (Wheel jacking - minimal)

---

## 4. The "Console" (Modern Developer)

### Agent Brief
**Objective:** Simulate a modern IDE (VS Code-like). Productive, clean, recognizable context for devs.
**Target Audience:** Tech bloggers, Code tutorial sites.

### Design Specifications
*   **Colors:** "Dracula" Theme. BG: `#282a36`, Sidebar: `#21222c`, Text: `#f8f8f2`, Pink: `#ff79c6`.
*   **Typography:** [Google Fonts] 'Fira Code' or 'JetBrains Mono' for EVERYTHING.
*   **Layout:**
    *   **Activity Bar (Left):** 50px wide, icons only.
    *   **Explorer (Sidebar):** 250px wide, File tree (Render `{{ menu1 }}` here as a tree).
    *   **Editor (Main):** The rest. Tab at the top with file name.
    *   **Status Bar (Bottom):** Blue strip, branch name, line info.

### Technical Implementation Queries
*   **Flexbox:** Perfect for the `row` layout (Sidebar | Editor).
*   **Details:** Add line numbers to code blocks automatically via CSS `counter` if possible, or assume Markdown renderer handles headers.

### Required Files
1.  `templates/console/base.html.twig`
2.  `templates/console/assets/css/main.css`
3.  `templates/console/assets/css/syntax-highlighting.css` (Dracula colors)

---

## 5. The "Gazette" (Classic Newspaper)

### Agent Brief
**Objective:** Heavy information density. Academic, authoritative, classic newspaper feel.
**Target Audience:** Journalists, Essayists.

### Design Specifications
*   **Colors:** "Newsprint". BG: `#f4f1ea` (Off-white), Text: `#222`, Links: `#8b0000` (Dark Red).
*   **Typography:** [Google Fonts] Headlines: 'UnifrakturMaguntia' (Blackletter) or 'Playfair Display'. Body: 'Merriweather'.
*   **Layout:**
    *   **Columns:** CSS Columns `column-count: 2; column-gap: 2rem; column-rule: 1px solid #ccc;` on desktop.
    *   **Header:** Centered, massive, double-bordered.

### Technical Implementation Queries
*   **Justification:** `text-align: justify; hyphens: auto;`.
*   **First Letter:** Use `::first-letter` pseudo-element for Drop Caps (Float left, 3 lines high, large serif).

### Required Files
1.  `templates/gazette/base.html.twig`
2.  `templates/gazette/standard_page.html.twig`
3.  `templates/gazette/assets/css/main.css`

---

## 6. The "Split-Persona" (Dichotomy)

### Agent Brief
**Objective:** 50/50 Vertical Split. Brand on left, Content on right.
**Target Audience:** Personal Branding, Speakers.

### Design Specifications
*   **Colors:** High Contrast. Left: Brand Color (e.g., Deep Blue `#000080`), Right: White.
*   **Layout:**
    *   **Left Pane:** `position: fixed; width: 50vw; height: 100vh;`. Vertically centered logo/intro.
    *   **Right Pane:** `margin-left: 50vw; width: 50vw; min-height: 100vh;`. Scrollable.
    *   **Mobile:** Stacked. Left pane becomes `height: 40vh; width: 100vw; relative;`.

### Technical Implementation Queries
*   **Responsive:** Media query at 768px breaks the split.
*   **Menu:** `{{ menu1 }}` goes in the Left Pane (Fixed).

### Required Files
1.  `templates/split/base.html.twig`
2.  `templates/split/assets/css/main.css`

---

## 7. The "Swiss" (International Style)

### Agent Brief
**Objective:** Clean, mathematical grid. Inspired by Müller-Brockmann. Whitespace is the hero.
**Target Audience:** Architects, Designers.

### Design Specifications
*   **Colors:** White `#fff` background. Black `#000` text. One primary color (e.g., Swiss Red `#ff0000`).
*   **Typography:** [Google Fonts] 'Inter' or 'Helvetica'.
*   **Layout:**
    *   **Grid System:** Make the grid *visible* sometimes? No, imply it with strict alignment.
    *   **Hierarchy:** Font sizes are extreme. Title: `5rem`. Meta: `0.8rem`.

### Technical Implementation Queries
*   **CSS Grid:** Define a 12-column grid. `display: grid; grid-template-columns: repeat(12, 1fr);`.
*   **Alignment:** Content usually occupies col 4-12. Meta data in col 1-3.
*   **Images:** Always full width of their container.

### Required Files
1.  `templates/swiss/base.html.twig`
2.  `templates/swiss/assets/css/main.css`

---

## 8. The "Card Deck" (Material/Spatial)

### Agent Brief
**Objective:** Content exists on distinct "cards" floating above a fixed background.
**Target Audience:** Curators, Link lists.

### Design Specifications
*   **Colors:** Background: Light Grey Pattern. Cards: White.
*   **Layout:**
    *   **Container:** Centered column, max-width 800px.
    *   **Cards:** Articles are white boxes, `border-radius: 8px`, `box-shadow: 0 4px 6px rgba(0,0,0,0.1)`.
*   **Interactivity:** Hovering a card lifts it: `transform: translateY(-5px)`.

### Technical Implementation Queries
*   **Category Index:** `templates/carddeck/category.html.twig` should loop through `category_files` and render each as a card snippet.
*   **Pagination:** Style the pagination as floating buttons.

### Required Files
1.  `templates/carddeck/base.html.twig`
2.  `templates/carddeck/category.html.twig` (The main view)
3.  `templates/carddeck/assets/css/main.css`

---

## 9. The "Wiki" (Knowledge Base)

### Agent Brief
**Objective:** Pure utility. Information density. Link-heavy.
**Target Audience:** Documentation, Technical Manuals.

### Design Specifications
*   **Colors:** Wikipedia-ish. White BG, Black text, Blue Links (`#0645ad`).
*   **Layout:**
    *   3 Column: Sidebar (Nav) | Content | Related (Info box).
*   **Typography:** System Fonts (`-apple-system`, `Segoe UI`, `Roboto`).

### Technical Implementation Queries
*   **Right Sidebar:** Use `{{ tags }}` or `related_content` (if available) to populate a "See Also" box on the right.
*   **Table Of Contents:** `{{ toc }}` must be prominent, either floating right or top of content.

### Required Files
1.  `templates/wiki/base.html.twig`
2.  `templates/wiki/docs.html.twig`
3.  `templates/wiki/assets/css/main.css`

---

## 10. The "Journal" (Handwritten/Personal)

### Agent Brief
**Objective:** A digital Moleskine notebook. Intimate and cozy.
**Target Audience:** Poets, Diarists.

### Design Specifications
*   **Colors:** Paper Texture (Pale Yellow/Cream `#fdfbf7`). Blue Pen text (`#2a2a55`).
*   **Typography:** [Google Fonts] Headings: 'Patrick Hand' or 'Caveat'. Body: 'Crimson Text' (Serif).
*   **Layout:**
    *   **Lines:** Use CSS `background-image: repeating-linear-gradient(...)` to draw ruled lines on the paper.
    *   **Container:** A central "Notebook" div with a shadow.

### Technical Implementation Queries
*   **Imperfect:** Rotate images slightly `transform: rotate(-1deg);`.
*   **Date:** Make the post date look like a stamp. `border: 2px solid; border-radius: 50%;`.

### Required Files
1.  `templates/journal/base.html.twig`
2.  `templates/journal/assets/css/main.css`
