# Future Improvements

## 2. Template Slots (Zero-Config Features)
Instead of hardcoding features like `{{ reading_time }}` into templates, introduce specific "Slots" or "Hooks" in base themes.

*   **Concept:** Templates include hooks like `{{ render_hook('post_title_meta') }}`.
*   **Mechanism:** Features register renderers for these hooks.
*   **Benefit:** Plugins like "Reading Time" automatically appear in the correct spot when installed, without user manual template editing.

## 3. Interactive Configuration (`config:setup`)
Enhance the CLI to guide users through configuration, as `siteconfig.yaml` grows in complexity.

*   **Command:** `staticforge config:setup`
*   **Interaction:** Interactive Q&A prompts (e.g., "Enable Estimated Reading Time? [Y/n]", "Words per minute? [200]").
*   **Output:** Generates or updates a valid `siteconfig.yaml` file.

## AEO package `.md` twins contain raw shortcode text
Logged 2026-09-28. `calevans/answer-engine-optimization` writes a `.md` copy of every page into `public/` (e.g. `public/contact-us.md`). Those copies are the source Markdown, so shortcodes and `{{ form('contact') }}` are published unexpanded. Fix is in that package (or a PRE_RENDER-expanded body), not in core. To be fixed 2026-09-29.
