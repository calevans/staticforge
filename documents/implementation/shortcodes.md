# Implementation Plan: Shortcode System

## 1. Overview
Implement a Shortcode system to allow users to inject dynamic or complex HTML components into their content (Markdown or HTML) using a simple, WordPress-style syntax with double brackets.

**Syntax:** `[[shortcode attr="value"]]` or `[[shortcode]]content[[/shortcode]]`

## 2. Architecture

### 2.1 Core Components
*   **Namespace:** `EICC\StaticForge\Shortcodes`
*   **Directory:** `src/Shortcodes/`

#### `ShortcodeInterface`
Defines the contract for all shortcodes.
```php
interface ShortcodeInterface
{
    public function getName(): string; // e.g., 'youtube'
    public function handle(array $attributes, string $content = ''): string;
}
```

#### `BaseShortcode`
Abstract base class providing helper methods.
*   `render(string $template, array $context)`: Renders a Twig template.
*   `processMarkdown(string $content)`: Helper to parse inner content as Markdown (if needed).
*   Dependencies: `TemplateRenderer`, `MarkdownProcessor` (optional).

#### `ShortcodeManager`
Service responsible for:
*   Registering shortcodes.
*   Parsing content for shortcode tags.
*   Executing the appropriate shortcode handler.
*   Handling escaping: `[[[tag]]]` -> `[[tag]]`.

### 2.2 Integration
*   **New Feature:** `ShortcodeProcessor` (in `src/Features/ShortcodeProcessor/`)
*   **Event:** Listens to `PRE_RENDER` event.
*   **Priority:** High priority (e.g., 100) to ensure it runs before `MarkdownRenderer` or `HtmlRenderer` (which usually run on `RENDER`).
*   **Logic:**
    1.  Receives content from `PRE_RENDER`.
    2.  Passes content to `ShortcodeManager->process()`.
    3.  Returns modified content.

## 3. Parsing Strategy
*   **Regex:** Use a regex to identify `[[tag ...]]` patterns.
*   **Attributes:** Parse attributes string (e.g., `id="123" autoplay="true"`) into an associative array.
*   **Enclosed Content:** Support both self-closing and enclosing tags.

## 4. User Customization
*   **Location:** Users can define custom shortcodes in their local `src/Shortcodes/` directory (if configured) or we can reuse the `ExtensionRegistry` concept or a specific `ShortcodeRegistry`.
*   **Templates:** Shortcode templates will live in `templates/shortcodes/` (e.g., `templates/shortcodes/youtube.html.twig`).

## 5. Example Implementation: `[[youtube]]`

**Usage:** `[[youtube id="dQw4w9WgXcQ"]]`

**Class:** `YoutubeShortcode`
```php
public function handle(array $attributes, string $content = ''): string
{
    $id = $attributes['id'] ?? '';
    if (empty($id)) return '';

    return $this->render('shortcodes/youtube.html.twig', ['id' => $id]);
}
```

**Template:** `templates/shortcodes/youtube.html.twig`
```html
<div class="video-embed">
    <iframe src="https://www.youtube.com/embed/{{ id }}" ...></iframe>
</div>
```

## 6. Reference Shortcodes
We will ship three reference shortcodes to demonstrate different capabilities.

### 6.1 `[[youtube]]` (Simple / Self-Closing)
*   **Syntax:** `[[youtube id="dQw4w9WgXcQ"]]`
*   **Purpose:** Demonstrates basic attribute handling and template rendering.
*   **Logic:** Extracts `id`, renders `templates/shortcodes/youtube.html.twig`.

### 6.2 `[[alert]]` (Enclosing / Content Processing)
*   **Syntax:** `[[alert type="warning"]]**Caution:** This is hot![[/alert]]`
*   **Purpose:** Demonstrates handling wrapped content and nested Markdown processing.
*   **Logic:**
    1.  Extracts `type` (info, warning, error).
    2.  Processes inner content via `processMarkdown()`.
    3.  Renders `templates/shortcodes/alert.html.twig` (uses existing `components/alerts.css`).

### 6.3 `[[weather]]` (Advanced / API Integration)
*   **Syntax:** `[[weather zip="33409"]]`
*   **Purpose:** Demonstrates backend logic, API fetching, and error handling.
*   **Logic:**
    1.  Calls `api.zippopotam.us` to convert Zip to Lat/Lon.
    2.  Calls `api.open-meteo.com` to get current weather.
    3.  Renders `templates/shortcodes/weather.html.twig`.
    4.  Handles API failures gracefully (returns empty string or error message).

## 7. Tasks
1.  [ ] Create `src/Shortcodes/` directory structure.
2.  [ ] Define `ShortcodeInterface` and `BaseShortcode`.
3.  [ ] Implement `ShortcodeManager` with parsing logic (Regex for `[[...]]`).
4.  [ ] Create `ShortcodeProcessor` Feature and register it on `PRE_RENDER`.
5.  [ ] Implement Reference Shortcodes:
    *   `YoutubeShortcode`
    *   `AlertShortcode`
    *   `WeatherShortcode`
6.  [ ] Create default templates in `templates/shortcodes/`.
7.  [ ] Add unit tests for parsing, attribute extraction, and escaping.
8.  [ ] Update documentation with examples.
