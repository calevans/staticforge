# Plan: Extract Terminal Template

This plan outlines the steps to extract the `terminal` template from the `staticforge` repository into a new, standalone git repository located in the parent directory, and subsequently clean up the `staticforge` codebase.

## 1. Create New Project
*   **Location:** `../terminal-template` (Relative to project root) / `/home/calevans/Projects/terminal-template` (Absolute)
*   **Actions:**
    *   Create the directory.
    *   Initialize a new Git repository (`git init`).
    *   Create a basic `README.md` for the new template project.

## 2. Migrate Files
*   **Source:** `templates/terminal/`
*   **Destination:** `/home/calevans/Projects/terminal-template/`
*   **Actions:**
    *   Copy all files and subdirectories from `templates/terminal/` to the new project root.
    *   *Note:* The structure in the new repo will likely be flat (e.g., `base.html.twig` at the root) or maintain the template structure depending on how StaticForge loads external templates. Assuming standard template structure is required, we will copy the contents of `templates/terminal` directly into the root of the new repo so it can be cloned into a `templates/` dir elsewhere.

## 3. Clean up StaticForge Repository
*   **Remove Files:**
    *   Delete directory: `templates/terminal/`

*   **Update Documentation:**
    *   `content/guide/configuration.md`: Remove references to `terminal` template.
    *   `content/development/templates.md`: Remove `terminal` template description.
    *   `content/guide/quick-start.md`: Remove `terminal` from the list of available built-in templates.
    *   `src/Features/SiteBuilder/Commands/RenderSiteCommand.php`: Update help text example `(e.g., sample, terminal)` to `(e.g., sample, custom)`.

*   **Update Tests:**
    *   `tests/Integration/Commands/RenderSiteCommandTest.php`:
        *   Rename the test case `testRenderSiteCommandWithTemplateOverride` internal directory from `terminal` to `custom_override` to decouple it from the removed theme name and avoid confusion.
    *   `tests/Unit/Features/CategoryIndexFeatureTest.php`:
        *   Update `$this->setContainerVariable('TEMPLATE', 'terminal');` to use `sample` or a generic name, ensuring the test doesn't rely on the physical existence of the folder (which it currently mocks, but best to be safe/clean).

## 4. Verification
*   **New Project:** Verify files exist in `/home/calevans/Projects/terminal-theme`.
*   **StaticForge:** Run full test suite (`lando phpunit`) to ensure no regressions were introduced by removing the directory or updating the test references.
