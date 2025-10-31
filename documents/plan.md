# StaticForge Implementation Plan



## Overview
Build StaticForge as a PHP-based static site generator with event-driven architecture. Focus on getting to MVP (basic workflow + HTML renderer) quickly, then layer on features building on each previous step. Use TDD with PHPUnit throughout.

## Step Completion Format
When marking steps as complete, add ✅ to the step title and mark each individual task with ✅. Follow this exact pattern:

```
## Step X. Step Title ✅
- ✅ Task 1
- ✅ Task 2
  - ✅ Apply PRINCIPLE: Description
- ✅ Update documents/plan.md with ✅ after verification
- ✅ Wait for further instructions
```

**CRITICAL**: When implementing steps, ACTUALLY CREATE the required files using create_file tool. Do not describe what files to create - create them immediately.

## Testing Policy
**DO NOT CREATE TRIVIAL UNIT TESTS.** Tests must verify meaningful behavior and business logic, not simple getters/setters or interface contracts. Focus on:
- Core system functionality that could break
- Complex business logic and edge cases
- Integration between components
- Error handling and validation
- Avoid testing: autoloading, interface implementations, trivial property access
---

## Step 0. Composer Project Setup & Dependencies ✅
- Review `documents/idea.md`, `documents/design.md`, `documents/technical.md`, and `documents/plan.md` completely to understand the plan and scope.
✅ Review all code about to be edited and any related code.
✅ Create complete `composer.json` with all required dependencies identified from plan analysis:
  ✅ **vlucas/phpdotenv**: Environment configuration loading (Step 2)
  ✅ **twig/twig**: Template rendering engine (Step 9)
  ✅ **symfony/console**: CLI command framework (Step 8)
  ✅ **symfony/yaml**: YAML metadata parsing (Step 5)
  ✅ **league/commonmark**: Markdown processing (Step 10)
  ✅ **eicc/utils**: Logging and container utilities (Step 2, 17)
  ✅ **phpunit/phpunit**: Unit testing framework (dev dependency)
✅ Set up PSR-4 autoloading for `EICC\StaticForge` namespace and include `EICC\Utils` mapping
✅ Add proper project metadata (name, description, type, require PHP 8.1+)
✅ Run `composer install` to establish dependency management
✅ Create `.gitignore` to exclude vendor directory and other generated files
✅ Apply YAGNI: Only packages explicitly needed by the steps, no extras
✅ Apply KISS: Standard composer.json structure, essential dependencies only
✅ Apply SOLID: Clear namespace organization from the beginning
✅ Update `documents/plan.md` to show completed tasks and step with ✅ after verification.
✅ Wait for further instructions.

---

## Step 1. Project Foundation & Core Structure ✅
- ✅ Review `documents/idea.md`, `documents/design.md`, `documents/technical.md`, and `documents/plan.md` completely to understand the plan and scope.
- ✅ Review all code about to be edited and any related code.
- ✅ Create basic directory structure: `src/`, `tests/`, `templates/`, `.env.example`
- ✅ Create basic `.env.example` with required configuration variables from technical spec
- ✅ Initialize PHPUnit configuration with test directory structure
- ✅ Test that composer autoloading works correctly for project namespaces
  - ✅ Apply YAGNI: Only essential directories and config
  - ✅ Apply KISS: Simple directory structure, leverage composer autoloading
  - ✅ Apply SOLID: Clear separation of source, tests, and templates
  - ✅ Apply DRY: Establish patterns for directory structure early
- ✅ Update `documents/plan.md` to show completed tasks and step with ✅ after verification.
- ✅ Wait for further instructions.

---

## Step 2. Environment Configuration & Container Foundation ✅
- ✅ Review `documents/idea.md`, `documents/design.md`, `documents/technical.md`, and `documents/plan.md` completely to understand the plan and scope.
- ✅ Review all code about to be edited and any related code.
- ✅ Create `Container` class for dependency injection (simple array-based storage) Use EICC\Utils\Container for the container.
- ✅ Create environment loader that reads `.env` file and populates container use vlucas/phpdotenv for loading. All environment variables should bs pushed into the container using setVariable() and they should alwats be read using getVariable().
- ✅ Add validation for required environment variables (fail fast if missing)
- ✅ Write unit tests for container and environment loading
- ✅ Create simple error handling for missing/invalid configuration
  - ✅ Apply YAGNI: Basic container only, no advanced DI features
  - ✅ Apply KISS: Array-based storage, straightforward validation
  - ✅ Apply SOLID: Container handles single responsibility of object storage
  - ✅ Apply DRY: Centralize configuration loading logic
- ✅ Update `documents/plan.md` to show completed tasks and step with ✅ after verification.
- ✅ Wait for further instructions.

---

## Step 3. Event System Foundation ✅
- ✅ Review `documents/idea.md`, `documents/design.md`, `documents/technical.md`, and `documents/plan.md` completely to understand the plan and scope.
- ✅ Review all code about to be edited and any related code.
- ✅ Create `EventManager` class with register/fire/unregister methods
- ✅ Implement priority-based event listener ordering (0-999, default 100)
- ✅ Create event data structure that passes container + parameters
- ✅ Write unit tests for event registration, firing, and parameter passing
- ✅ Test event chain processing (multiple listeners modifying parameters)
  - ✅ Apply YAGNI: Core event functionality only, no complex features
  - ✅ Apply KISS: Simple priority queue, clear event data flow
  - ✅ Apply SOLID: EventManager single responsibility, clean interfaces
  - ✅ Apply DRY: Reusable event processing pattern
- ✅ Update `documents/plan.md` to show completed tasks and step with ✅ after verification.
- ✅ Wait for further instructions.

---

## Step 4. Feature System & Base Classes ✅
- ✅ Review `documents/idea.md`, `documents/design.md`, `documents/technical.md`, and `documents/plan.md` completely to understand the plan and scope.
- ✅ Review all code about to be edited and any related code.
- ✅ Create `FeatureInterface` defining feature contract (register, getEventListeners)
- ✅ Create abstract `BaseFeature` class with common functionality (only protected/public methods, no private or static methods)
- ✅ Implement feature discovery and instantiation from features directory
- ✅ Add feature registration with container and event manager
- ✅ Write unit tests for feature loading and registration process
  - ✅ Apply YAGNI: Basic feature interface only, no complex lifecycle
  - ✅ Apply KISS: Simple feature discovery, clear registration pattern
  - ✅ Apply SOLID: Feature interface defines clear contract
  - ✅ Apply DRY: Base feature class for common functionality
- ✅ Update `documents/plan.md` to show completed tasks and step with ✅ after verification.
- ✅ Wait for further instructions.

---

## Step 5. File Discovery & Content Processing Pipeline ✅
- ✅ Review `documents/idea.md`, `documents/design.md`, `documents/technical.md`, and `documents/plan.md` completely to understand the plan and scope.
- ✅ Review all code about to be edited and any related code.
- ✅ Create `ExtensionRegistry` class for renderer features to register supported file extensions
- ✅ Create `FileDiscovery` class to discover content files in configured directories
- ✅ Create `FileProcessor` class as the main processing loop (PRE-RENDER, RENDER, POST-RENDER events)
- ✅ Implement workflow: PRE-GLOB → FileDiscovery → POST-GLOB → FileProcessor loop
- ✅ Update `BaseFeature` to support extension registration for renderer features
- ✅ Write comprehensive unit tests for all new classes (20 tests, 52 assertions)
  - ✅ Apply YAGNI: Essential file discovery and processing workflow only
  - ✅ Apply KISS: Clear separation between discovery and processing
  - ✅ Apply SOLID: Each class handles single responsibility (registry, discovery, processing)
  - ✅ Apply DRY: Centralized extension registry and event-driven processing
- ✅ Update `documents/plan.md` to show completed tasks and step with ✅ after verification.
- ✅ Wait for further instructions.

---

## Step 6. Core Application & Event Pipeline ✅
- ✅ Review `documents/idea.md`, `documents/design.md`, `documents/technical.md`, and `documents/plan.md` completely to understand the plan and scope.
- ✅ Review all code about to be edited and any related code.
- ✅ Create main `Application` class that orchestrates generation process
- ✅ Implement the 9-step event pipeline (CREATE, PRE_GLOB, POST_GLOB, etc.)
- ✅ Add error handling with graceful feature failure and core failure modes
- ✅ Create logging integration using EiccUtils Log class
- ✅ Write unit tests for application workflow and event sequence
  - ✅ Apply YAGNI: Core pipeline only, basic error handling
  - ✅ Apply KISS: Straightforward event sequence, simple logging
  - ✅ Apply SOLID: Application orchestrates, doesn't implement details
  - ✅ Apply DRY: Reusable pipeline pattern for all generation runs
- ✅ Update `documents/plan.md` to show completed tasks and step with ✅ after verification.
- ✅ Wait for further instructions.

---

## Step 7. HTML Renderer Feature (MVP Complete) ✅
- ✅ Review `documents/idea.md`, `documents/design.md`, `documents/technical.md`, and `documents/plan.md` completely to understand the plan and scope.
- ✅ Review all code about to be edited and any related code.
- ✅ Create `HtmlRendererFeature` that processes .html files during RENDER event
- ✅ Implement content extraction (remove YAML header, keep HTML body)
- ✅ Add basic template integration (simple string replacement for now)
- ✅ Create output file writing with proper directory structure
- ✅ Write unit tests for HTML processing and output generation
- ✅ Test end-to-end: HTML file in → static site out
  - ✅ Apply YAGNI: Basic HTML processing only, no advanced features
  - ✅ Apply KISS: Simple template substitution, straightforward file output
  - ✅ Apply SOLID: Renderer handles single responsibility of HTML processing
  - ✅ Apply DRY: Reusable content processing pattern for other renderers
- ✅ Update `documents/plan.md` to show completed tasks and step with ✅ after verification.
- ✅ Wait for further instructions.

---

## Step 8. CLI Interface with Symfony Console ✅
- ✅ Review `documents/idea.md`, `documents/design.md`, `documents/technical.md`, and `documents/plan.md` completely to understand the plan and scope.
- ✅ Review all code about to be edited and any related code.
- ✅ Add Symfony Console dependency and create `bin/console.php` entry point
- ✅ Create `RenderSiteCommand` that triggers full site generation
- ✅ Add command options: `--clean` (remove output directory before generation), `--verbose` for basic control
- ✅ Implement proper exit codes and error reporting
- ✅ Write integration tests for CLI command execution
  - ✅ Apply YAGNI: Basic command interface only, essential options
  - ✅ Apply KISS: Single command for now, clear success/failure reporting
  - ✅ Apply SOLID: Command handles CLI concerns, delegates to Application
  - ✅ Apply REST: Command represents resource action (render site)
  - ✅ Apply DRY: Reusable command pattern for additional commands
- ✅ Update `documents/plan.md` to show completed tasks and step with ✅ after verification.
- ✅ Wait for further instructions.

---

## Step 9. Twig Template Integration ✅
- ✅ Review `documents/idea.md`, `documents/design.md`, `documents/technical.md`, and `documents/plan.md` completely to understand the plan and scope.
- ✅ Review all code about to be edited and any related code.
- ✅ Replace simple string template replacement with Twig engine
- ✅ Create base template with content, title, and metadata variables
- ✅ Add template loading and rendering to HTML renderer feature
- ✅ Implement template security (auto-escaping enabled)
- ✅ Write unit tests for template rendering and variable injection
  - ✅ Apply YAGNI: Basic template functionality, essential variables only
  - ✅ Apply KISS: Single base template, straightforward variable passing
  - ✅ Apply SOLID: Template rendering separated from content processing
  - ✅ Apply DRY: Reusable template patterns for all content types
- ✅ Update `documents/plan.md` to show completed tasks and step with ✅ after verification.
- ✅ Wait for further instructions.

---

## Step 10. Markdown Renderer Feature ✅
- ✅ Review `documents/idea.md`, `documents/design.md`, `documents/technical.md`, and `documents/plan.md` completely to understand the plan and scope.
- ✅ Review all code about to be edited and any related code.
- ✅ Create `MarkdownRendererFeature` that processes .md files during RENDER event
- ✅ Add Markdown parsing library and implement conversion to HTML
- ✅ Handle YAML frontmatter extraction from Markdown files
- ✅ Integrate with existing template system for consistent output
- ✅ Write unit tests for Markdown processing and template integration
  - ✅ Apply YAGNI: Basic Markdown support, standard frontmatter format
  - ✅ Apply KISS: Standard Markdown parser, reuse existing template patterns
  - ✅ Apply SOLID: Markdown renderer handles single file type responsibility
  - ✅ Apply DRY: Reuse metadata extraction and template rendering patterns
- ✅ Update `documents/plan.md` to show completed tasks and step with ✅ after verification.
- ✅ Wait for further instructions.

---

## Step 11. Menu Generation Feature ✅
- ✅ Review `documents/idea.md`, `documents/design.md`, `documents/technical.md`, and `documents/plan.md` completely to understand the plan and scope.
- ✅ Review all code about to be edited and any related code.
- ✅ Create `MenuFeature` that listens to POST_GLOB event
- ✅ Implement menu structure building from content metadata (position field)
- ✅ Generate HTML menu structure and store in features array
- ✅ Integrate menu HTML into template rendering process
- ✅ Write unit tests for menu generation and template integration
  - ✅ Apply YAGNI: Basic hierarchical menu only, position-based ordering
  - ✅ Apply KISS: Simple menu structure, straightforward HTML generation
  - ✅ Apply SOLID: Menu feature handles single responsibility of navigation
  - ✅ Apply DRY: Reusable menu generation pattern
- ✅ Added dropdown menu support (up to 3 levels: 1, 1.2, 1.2.3)
- ✅ Created generic, composable CSS classes (.menu, .dropdown, .dropdown-menu)
- ✅ Integrated menus into all three template themes (terminal, sample, vaulttech)
- ✅ Fixed URL generation to exclude content directory from paths
- ✅ Created menu1.html.twig and menu2.html.twig partials for each theme
- ✅ Updated README.md with comprehensive menu documentation and CSS examples
- ✅ Update `documents/plan.md` to show completed tasks and step with ✅ after verification.
- ✅ Wait for further instructions.

---

## Step 12. PDF Renderer Feature ❌ (NOT IMPLEMENTING)
- ~~Review `documents/idea.md`, `documents/design.md`, `documents/technical.md`, and `documents/plan.md` completely to understand the plan and scope.~~
- ~~Review all code about to be edited and any related code.~~
- ~~Create `PdfRendererFeature` that processes .pdf files during RENDER event~~
- ~~Implement .pdf.ini metadata file reading for PDF documents~~
- ~~Generate HTML link pages for PDF files (no content conversion)~~
- ~~Add PDF files to menu system when appropriate metadata exists~~
- ~~Write unit tests for PDF link generation and metadata handling~~
  - ~~Apply YAGNI: Link generation only, no PDF content processing~~
  - ~~Apply KISS: External .ini files for metadata, simple link pages~~
  - ~~Apply SOLID: PDF renderer handles single responsibility for PDF linking~~
  - ~~Apply DRY: Reuse metadata patterns and template rendering~~
- ~~Update `documents/plan.md` to show completed tasks and step with ✅ after verification.~~
- ~~Wait for further instructions.~~

---

## Step 13. Categories Feature ✅
- ✅ Review `documents/idea.md`, `documents/design.md`, `documents/technical.md`, and `documents/plan.md` completely to understand the plan and scope.
- ✅ Review all code about to be edited and any related code.
- ✅ Modified HtmlRenderer to generate content only, not write files
- ✅ Modified MarkdownRenderer to generate content only, not write files
- ✅ Updated FileProcessor to write files after POST-RENDER event
- ✅ Create `CategoriesFeature` that listens to POST_RENDER event
- ✅ Implement category-based output directory organization
- ✅ Move generated files to category subdirectories based on metadata
- ✅ Maintain proper relative links and asset paths after moving
- ✅ Write unit tests for category organization and link maintenance
  - ✅ Apply YAGNI: Basic category organization only, single category per file
  - ✅ Apply KISS: Directory-based organization, straightforward file moving
  - ✅ Apply SOLID: Categories feature handles single organization responsibility
  - ✅ Apply DRY: Reusable file organization patterns
- ✅ Update `documents/plan.md` to show completed tasks and step with ✅ after verification.
- ✅ Wait for further instructions.

---

## Step 14. Category Index Pages Feature ✅
- ✅ Review `documents/idea.md`, `documents/design.md`, `documents/technical.md`, and `documents/plan.md` completely to understand the plan and scope.
- ✅ Review all code about to be edited and any related code.
- ✅ Create `CategoryIndexFeature` that listens to POST_LOOP event
- ✅ Generate index.html pages for each category directory
- ✅ Implement client-side pagination for categories with many items using jQuery
- ✅ Add category index pages to menu system via .ini file configuration
- ✅ Implement .ini file scanning at POST_GLOB event (priority 200, after MenuBuilder at 100)
- ✅ Inject category menu entries before files are generated
- ✅ Write INI frontmatter to generated index.html files
- ✅ Write unit tests for index generation and pagination logic (7 tests, 20 assertions)
  - ✅ Apply YAGNI: Basic pagination only, simple index page format
  - ✅ Apply KISS: Standard index page template, straightforward pagination
  - ✅ Apply SOLID: Index feature handles single responsibility for category indexes
  - ✅ Apply DRY: Reuse template rendering and pagination patterns
- ✅ Update `documents/plan.md` to show completed tasks and step with ✅ after verification.
- ✅ Wait for further instructions.

---

## Step 15. Tags Feature ✅
- ✅ Review `documents/idea.md`, `documents/design.md`, `documents/technical.md`, and `documents/plan.md` completely to understand the plan and scope.
- ✅ Review all code about to be edited and any related code.
- ✅ Create `TagsFeature` that listens to POST_GLOB event
- ✅ Extract tag metadata from content files and store in features array
- ✅ Add tag information to template variables for display via PRE_RENDER event
- ✅ Implement tag index (tag → file mapping) and tag counts
- ✅ Implement related files feature (find files with shared tags)
- ✅ Write unit tests for tag extraction and template integration (10 tests, 42 assertions)
  - ✅ Apply YAGNI: Basic tag support only, simple display functionality
  - ✅ Apply KISS: Tag array in templates, straightforward tag indexing
  - ✅ Apply SOLID: Tags feature handles single responsibility for tag processing
  - ✅ Apply DRY: Reuse metadata extraction and template patterns
- ✅ Update `documents/plan.md` to show completed tasks and step with ✅ after verification.
- ✅ Wait for further instructions.

---

## Step 16. Enhanced CLI Commands ✅
- ✅ Review `documents/idea.md`, `documents/design.md`, `documents/technical.md`, and `documents/plan.md` completely to understand the plan and scope.
- ✅ Review all code about to be edited and any related code.
- ✅ Create `RenderPageCommand` for single file/pattern processing
- ✅ Add verbose logging and progress reporting for large sites
- ✅ Write integration tests for all CLI command variations
  - ✅ Apply YAGNI: Essential command options only, clear parameter handling
  - ✅ Apply KISS: Standard CLI patterns, straightforward option processing
  - ✅ Apply SOLID: Commands handle CLI concerns, delegate processing
  - ✅ Apply REST: Commands represent clear resource actions
  - ✅ Apply DRY: Reusable command patterns and option handling
- ✅ Update `documents/plan.md` to show completed tasks and step with ✅ after verification.
- ✅ Wait for further instructions.

---

## Step 17. Error Handling & Logging Refinement ✅
- ✅ Review `documents/idea.md`, `documents/design.md`, `documents/technical.md`, and `documents/plan.md` completely to understand the plan and scope.
- ✅ Review all code about to be edited and any related code.
- ✅ Replace simple error_log with proper EiccUtils logging integration
- ✅ Implement comprehensive error categorization (feature vs. core failures)
- ✅ Add detailed logging for generation progress and feature activity
- ✅ Create error recovery and continuation logic for resilient processing
- ✅ Write unit tests for error handling and logging behavior
  - ✅ Apply YAGNI: Essential logging levels only, clear error categorization
  - ✅ Apply KISS: Straightforward logging integration, simple error recovery
  - ✅ Apply SOLID: Error handling separated from business logic
  - ✅ Apply DRY: Centralized logging patterns across all components
- ✅ Update `documents/plan.md` to show completed tasks and step with ✅ after verification.
- ✅ Wait for further instructions.

---

## Step 18. Integration Testing & End-to-End Validation ✅
- ✅ Review `documents/idea.md`, `documents/design.md`, `documents/technical.md`, and `documents/plan.md` completely to understand the plan and scope.
- ✅ Review all code about to be edited and any related code.
- ✅ Create comprehensive integration test suite for full site generation
- ✅ Test mixed content scenarios (HTML, Markdown, PDF together)
- ✅ Validate feature interaction and event pipeline processing
- ✅ Test error scenarios and graceful failure modes
- ✅ Create test fixtures for various site configurations and content types
  - ✅ Apply YAGNI: Essential integration scenarios only, realistic test data
  - ✅ Apply KISS: Clear test scenarios, straightforward validation
  - ✅ Apply SOLID: Tests validate component integration without implementation details
  - ✅ Apply DRY: Reusable test fixtures and validation patterns
- ✅ Update `documents/plan.md` to show completed tasks and step with ✅ after verification.
- ✅ Wait for further instructions.

---

## Step 19. Documentation & Usage Examples ✅
- ✅ Review `documents/idea.md`, `documents/design.md`, `documents/technical.md`, and `documents/plan.md` completely to understand the plan and scope.
- ✅ Review all code about to be edited and any related code.
- ✅ Create comprehensive README with installation and usage instructions
- ✅ Document feature system and event hooks for extensibility
- ✅ Create example content files and templates
- ✅ Write feature development guide for future extensions
- ✅ Document configuration options and environment variables
  - ✅ Apply YAGNI: Essential documentation only, clear examples
  - ✅ Apply KISS: Straightforward examples, practical usage scenarios
  - ✅ Apply SOLID: Documentation reflects clean system boundaries
  - ✅ Apply DRY: Reusable documentation patterns and examples
- ✅ Update `documents/plan.md` to show completed tasks and step with ✅ after verification.
- ✅ Wait for further instructions.

---

## Step 20. Chapter Navigation Feature ✅
- ✅ Review `documents/idea.md`, `documents/design.md`, `documents/technical.md`, and `documents/plan.md` completely to understand the plan and scope.
- ✅ Review all code about to be edited and any related code.

### Overview
Create a ChapterNav feature that generates sequential prev/next navigation for documentation-style content based on menu ordering. This allows pages in a menu to have automatic "previous chapter" and "next chapter" links without manual configuration.

### Configuration
- ✅ Add new environment variables to `.env.example`:
  - ✅ `CHAPTER_NAV_MENUS="2,3"` - Comma-separated list of menu numbers to build chapter navigation for
  - ✅ `CHAPTER_NAV_PREV_SYMBOL="←"` - Symbol/text for previous links (default: left arrow)
  - ✅ `CHAPTER_NAV_NEXT_SYMBOL="→"` - Symbol/text for next links (default: right arrow)
  - ✅ `CHAPTER_NAV_SEPARATOR="|"` - Separator between navigation elements (default: pipe)
- ✅ Update `tests/.env.testing` with test values

### Feature Implementation
- ✅ Create `src/Features/ChapterNav/Feature.php`:
  - ✅ Extend `BaseFeature` and implement `FeatureInterface`
  - ✅ Protected properties for configuration with defaults:
    - ✅ `$configuredMenus = []` - Array of menu numbers from `CHAPTER_NAV_MENUS`
    - ✅ `$prevSymbol = '←'` - From `CHAPTER_NAV_PREV_SYMBOL`
    - ✅ `$nextSymbol = '→'` - From `CHAPTER_NAV_NEXT_SYMBOL`
    - ✅ `$separator = '|'` - From `CHAPTER_NAV_SEPARATOR`
  - ✅ Private property `$chapterNavData = []` - Stores navigation data per file per menu

- ✅ Event Listeners:
  - ✅ `POST_GLOB` (priority 150) - Runs AFTER MenuBuilder (priority 100)
    - ✅ Read configured menus from environment
    - ✅ Get menu data from `$parameters['features']['MenuBuilder']['files']`
    - ✅ For each configured menu number:
      - ✅ Extract all pages with positions like `X.Y` (ignore `X.Y.Z` dropdown items)
      - ✅ Sort by position (numeric comparison on X, then Y)
      - ✅ Build sequential array of pages in order
      - ✅ For each page in sequence:
        - ✅ Determine prev page (if not first)
        - ✅ Determine next page (if not last)
        - ✅ Store in `$chapterNavData[source_file_path][menu_number]`:
          - ✅ `prev` => `['title' => '...', 'url' => '...']` or null
          - ✅ `current` => `['title' => '...', 'url' => '...']`
          - ✅ `next` => `['title' => '...', 'url' => '...']` or null
          - ✅ `html` => Generated HTML string
        - ✅ Generate HTML with `buildChapterNavHtml()` method
    - ✅ Add to parameters: `$parameters['features']['ChapterNav']['pages'] = $chapterNavData`
    - ✅ Return updated parameters

- ✅ Helper Methods:
  - ✅ `parseConfiguredMenus()`: Parse comma-separated menu numbers from environment
  - ✅ `extractSequentialPages($menuData, $menuNumber)`: Get ordered pages from menu, ignore dropdowns
  - ✅ `buildChapterNavHtml($prev, $current, $next)`: Generate HTML navigation
    - ✅ Return `<nav class="chapter-nav">` wrapper
    - ✅ If `$prev` exists: `<a href="{url}" class="chapter-nav-prev">{symbol} {title}</a>`
    - ✅ Always include: `<span class="chapter-nav-current">{title}</span>`
    - ✅ If `$next` exists: `<a href="{url}" class="chapter-nav-next">{title} {symbol}</a>`
    - ✅ Use configured symbols from class properties
    - ✅ Handle missing prev/next gracefully (first/last pages)

### Renderer Updates
- ✅ Update `src/Features/MarkdownRenderer/Feature.php`:
  - ✅ In `applyTemplate()` method, add `source_file` variable to template context
  - ✅ Pass the original source file path (e.g., `content/docs/FEATURES.md`)

- ✅ Update `src/Features/HtmlRenderer/Feature.php`:
  - ✅ In `applyTemplate()` method, add `source_file` variable to template context
  - ✅ Pass the original source file path

### Template Creation
- ✅ Create `templates/staticforce/_chapter_nav.html.twig` snippet:
  - ✅ Standalone snippet for reusability
  - ✅ Includes CSS styles for chapter navigation
  - ✅ Iterates through configured menus
  - ✅ Renders navigation HTML with proper escaping

- ✅ Update `templates/staticforce/docs.html.twig`:
  - ✅ Include chapter navigation snippet above footer
  - ✅ Proper placement for UX (after content, before footer)

### Testing
- ✅ Create `tests/Unit/Features/ChapterNavFeatureTest.php`:
  - ✅ Test configuration parsing from environment
  - ✅ Test sequential page extraction (verify dropdowns ignored)
  - ✅ Test prev/next determination for first, middle, and last pages
  - ✅ Test HTML generation with all combinations (prev only, next only, both, neither)
  - ✅ Test multiple menus for same page
  - ✅ Test custom symbols from configuration
  - ✅ Test edge cases:
    - ✅ Empty menu configuration (feature skips processing)
    - ✅ Single page in menu (no prev/next)
    - ✅ Page appears in multiple configured menus

- ✅ Integration testing verified through full test suite (183 tests passing)

### Documentation
- ✅ Update `docs/FEATURES.md`:
  - ✅ Add "Chapter Navigation" section after Menu Builder
  - ✅ Explain purpose: sequential documentation navigation
  - ✅ Show configuration options
  - ✅ Provide template usage examples (include snippet and direct access)
  - ✅ Explain how it works with MenuBuilder
  - ✅ Show HTML structure and CSS classes for styling
  - ✅ Document disable behavior (empty CHAPTER_NAV_MENUS or not set)
  - ✅ Include tips and best practices

- ✅ Configuration documented in FEATURES.md (CONFIGURATION.md can be updated later if needed)

- ✅ Update `.env.example`:
  - ✅ Add chapter navigation configuration section with defaults
  - ✅ Include helpful comments

### Edge Cases & Validation
- ✅ Handle pages that appear in multiple menus (generate nav for each)
- ✅ Ignore third-level menu positions (dropdowns) - only use X.Y positions
- ✅ Handle missing MenuBuilder data gracefully (feature dependency)
- ✅ Allow empty configuration (feature does nothing if no menus configured)
- ✅ Handle missing configuration (getVariable returns null, use defaults)
- ✅ Generate valid HTML even with missing prev/next (first/last pages)
- ✅ Properly escape all HTML output (htmlspecialchars)
- ✅ Handle missing titles gracefully (uses MenuBuilder titles)

### Principles Applied
- ✅ Apply YAGNI: Only generate navigation for configured menus, simple HTML structure
- ✅ Apply KISS: Straightforward sequential ordering, clean HTML output, snippet-based templates
- ✅ Apply SOLID:
  - ✅ Single Responsibility: Feature only handles chapter navigation
  - ✅ Dependency on MenuBuilder through event system (loose coupling)
  - ✅ Template decides where/how to display navigation
- ✅ Apply DRY: Reuse MenuBuilder's menu data, centralized HTML generation, reusable snippet
- ✅ Apply Separation of Concerns: Feature builds data, template renders it

### Completion Checklist
- ✅ Create ChapterNav feature class with event listeners
- ✅ Update both renderers to pass `source_file` to templates
- ✅ Create `_chapter_nav.html.twig` snippet in staticforce theme
- ✅ Update `docs.html.twig` to include snippet above footer
- ✅ Add comprehensive unit tests (14 test cases, 44 assertions)
- ✅ Run full test suite - all 183 tests passing
- ✅ Generate test site and verify chapter navigation appears correctly
- ✅ Verify navigation works for first page (no prev), last page (no next), and middle pages
- ✅ Verify empty/missing configuration disables feature with no overhead
- ✅ Update documentation in FEATURES.md with complete usage guide
- ✅ Update `documents/plan.md` to show completed tasks and step with ✅ after verification.
- ✅ Wait for further instructions.
- Add comprehensive unit tests (10+ test cases)
- Add integration tests with MenuBuilder
- Update `.env.example` with new configuration
- Update documentation (FEATURES.md, CONFIGURATION.md)
- Run full test suite - all tests must pass
- Generate test site and verify chapter navigation appears correctly
- Verify navigation works for first page (no prev), last page (no next), and middle pages
- Update `documents/plan.md` to show completed tasks and step with ✅ after verification.
- Wait for further instructions.

---

## Step 21. Final Package Polish & Distribution Preparation
- Review `documents/idea.md`, `documents/design.md`, `documents/technical.md`, and `documents/plan.md` completely to understand the plan and scope.
- Review all code about to be edited and any related code.
- Verify composer.json is complete and optimized for distribution
- Add project creation template and enhanced .env.example with documentation
- Prepare package for `composer create-project` distribution
- Test package installation and setup process from scratch
- Create release preparation checklist and versioning strategy
- Validate all dependencies and autoloading work in clean environment
  - Apply YAGNI: Essential package refinements only, standard Composer patterns
  - Apply KISS: Standard package structure, clear installation process
  - Apply SOLID: Package structure reflects clean system architecture
  - Apply DRY: Leverage Composer standards and conventions
- Update `documents/plan.md` to show completed tasks and step with ✅ after verification.
- Wait for further instructions.

---

## Quality Gates
Each step must result in:
- ✅ All unit tests passing
- ✅ System remains in working state
- ✅ New functionality demonstrated with example
- ✅ Error handling tested and documented
- ✅ Code follows SOLID principles and project conventions

---

## Step 23. Bootstrap Architecture Refactoring

### Overview
Refactor the bootstrap architecture to eliminate the redundant Core/Bootstrap class and create a proper procedural bootstrap file that handles autoloading, environment loading, and container initialization in one place.

### Step 23.1. Create New Bootstrap File
- Review current `src/Core/Bootstrap.php` to understand what needs to be preserved
- Create `src/bootstrap.php` as a procedural script (not a class)
- Structure:
  - Require Composer autoloader (`vendor/autoload.php`)
  - Accept optional `$envPath` parameter (defaults to `.env`)
  - Load environment variables using Dotenv
  - Create Container instance
  - Load all environment variables into container using `setVariable()`
  - Register logger using `stuff()` as singleton
  - Return fully configured Container instance
- Follow the pattern from zillowScraper example
- Apply KISS: Simple procedural bootstrap, no class wrapper
- Apply DRY: Single source of truth for container initialization

### Step 23.2. Update Environment Loading
- Read all environment variables from `.env`:
  - `SITE_NAME`, `SITE_TAGLINE`, `SITE_BASE_URL`
  - `TEMPLATE`, `SOURCE_DIR`, `OUTPUT_DIR`, `TEMPLATE_DIR`, `FEATURES_DIR`
  - `LOG_LEVEL`, `LOG_FILE`
  - Chapter navigation variables
  - SFTP configuration variables
- Store each in container using `$container->setVariable('key', $_ENV['KEY'])`
- Apply YAGNI: Only load variables that exist in .env
- Apply SOLID: Single responsibility - bootstrap only initializes

### Step 23.3. Register Logger Service
- Use `$container->stuff('logger', function() use ($container) {...})`
- Logger should:
  - Read log directory from container (default: `logs/`)
  - Read log filename from container (default from `LOG_FILE` variable or `staticforge.log`)
  - Read log level from container (default from `LOG_LEVEL` or `info`)
  - Return `new Log('staticforge', $logPath . $logFileName, $logLevel)`
- Logger is created once and reused (that's what `stuff()` does)
- Apply SOLID: Logger factory encapsulated in closure

### Step 23.4. Update Console Entry Point
- Modify `bin/console.php`:
  - Remove `require vendor/autoload.php`
  - Add `$container = require __DIR__ . '/../src/bootstrap.php';` at the top
  - Pass container to all command constructors
  - Commands that currently create their own container should accept it as parameter
- Apply DRY: Single bootstrap path for all entry points
- Apply SOLID: Console delegates to bootstrap

### Step 23.5. Update Command Constructors
- Modify `RenderSiteCommand`:
  - Add `public function __construct(Container $container)`
  - Store container as protected property
  - Remove internal Application bootstrap (Application should receive configured container)
- Modify `RenderPageCommand`:
  - Add `public function __construct(Container $container)`
  - Store container as protected property
  - Remove internal Application bootstrap
- Modify `UploadSiteCommand`:
  - Change constructor from `?Container $container = null` to `Container $container`
  - Remove conditional container initialization
  - Always use provided container
- Apply SOLID: Dependency injection, no self-initialization
- Apply KISS: Commands receive what they need, don't create it

### Step 23.6. Update Application Class
- Review `src/Core/Application.php` to see if it needs container passed in
- If Application creates its own container/bootstrap, refactor to accept container
- Ensure Application uses container's logger via `getVariable('logger')`
- Apply SOLID: Application receives configured dependencies

### Step 23.7. Remove Old Bootstrap Class
- Delete `src/Core/Bootstrap.php`
- Delete `src/Environment/EnvironmentLoader.php` (functionality moved to bootstrap.php)
- Clean up any imports referencing these classes
- Apply YAGNI: Remove unused code
- Apply KISS: Fewer classes to maintain

### Step 23.8. Update Tests
- Modify test files that currently use Bootstrap class
- Tests should either:
  - Require bootstrap.php with test .env path: `$container = require __DIR__ . '/../../src/bootstrap.php';`
  - Or create minimal test container manually for unit tests
- Update `tests/.env.testing` path handling
- Ensure all command tests pass container correctly
- Apply SOLID: Tests use same bootstrap as production

### Step 23.9. Update Documentation
- Update `README.md` if it references Bootstrap class
- Update `docs/CONFIGURATION.md` if needed
- Document new bootstrap.php location and usage
- Show example of how entry points should use it
- Apply KISS: Clear, simple documentation

### Step 23.10. Verification & Testing
- Run full test suite - all 196 tests must pass
- Verify `bin/console.php list` works
- Verify `bin/console.php site:render` works
- Verify `bin/console.php site:upload` works
- Test with different .env configurations
- Apply Quality Gates: All tests passing, system working

### Principles Applied
- Apply YAGNI: Single bootstrap file, no class wrapper
- Apply KISS: Procedural bootstrap is simpler than class-based
- Apply SOLID: Single responsibility, dependency injection throughout
- Apply DRY: One bootstrap used by all entry points
- Apply Separation of Concerns: Bootstrap initializes, commands execute

### Completion Checklist
- Create `src/bootstrap.php` with full implementation
- Update `bin/console.php` to use new bootstrap
- Update all command constructors to require Container
- Update Application class if needed
- Remove `src/Core/Bootstrap.php`
- Remove `src/Environment/EnvironmentLoader.php`
- Update all affected tests
- Update documentation
- Run full test suite - all tests passing
- Manual verification of all commands
- Update `documents/plan.md` with ✅ after verification
- Wait for further instructions

---

## Quality Gates
Each step must result in:
- ✅ All unit tests passing
- ✅ System remains in working state
- ✅ New functionality demonstrated with example
- ✅ Error handling tested and documented
- ✅ Code follows SOLID principles and project conventions

---

## Step 22. SFTP Upload Command ✅
- ✅ Review `documents/idea.md`, `documents/design.md`, `documents/technical.md`, and `documents/plan.md` completely to understand the plan and scope.
- ✅ Review all code about to be edited and any related code.

### Overview
Create a `site:upload` command that uploads the generated static site to a remote server via SFTP. This enables one-command deployment from local development to production hosting.

### Dependencies
- Add `phpseclib/phpseclib` to `composer.json` (pure PHP SFTP implementation, no system dependencies)
  - Latest version 3.x provides SSH2/SFTP support
  - No need for PHP ssh2 extension or external tools

### Environment Configuration
- Update `.env.example` with SFTP configuration section:
  - `SFTP_HOST` - Remote server hostname or IP address (required)
  - `SFTP_PORT` - SFTP port number (default: 22)
  - `SFTP_USERNAME` - Username for authentication (required)
  - `SFTP_PASSWORD` - Password for authentication (optional, use OR key-based auth)
  - `SFTP_PRIVATE_KEY_PATH` - Path to SSH private key file (optional, use OR password auth)
  - `SFTP_PRIVATE_KEY_PASSPHRASE` - Passphrase for encrypted private key (optional)
  - `SFTP_REMOTE_PATH` - Remote directory path to upload site to (required, e.g., `/var/www/html`)
- Update `tests/.env.testing` with test SFTP configuration (can use dummy values)
- Add documentation comments explaining key-based vs password authentication

### Command Implementation
- Create `src/Commands/UploadSiteCommand.php`:
  - Extend `Symfony\Component\Console\Command\Command`
  - Command name: `site:upload`
  - Command description: "Upload generated static site to remote server via SFTP"

- Command Options:
  - `--input` - Optional input directory override (default: `OUTPUT_DIR` from .env)
    - Type: `InputOption::VALUE_REQUIRED`
    - Description: "Override output directory to upload (default from OUTPUT_DIR in .env)"

- Protected Properties:
  - `$container` - EICC\Utils\Container instance
  - `$logger` - Logger instance from container
  - `$sftp` - phpseclib3\Net\SFTP instance
  - `$uploadedCount` - Track successful uploads
  - `$errorCount` - Track failed uploads
  - `$errors` - Array to store error messages

- Execute Method Workflow:
  1. **Load Configuration**:
     - Get input directory from `--input` option or `OUTPUT_DIR` from container
     - Validate input directory exists and is readable
     - Get SFTP configuration from container variables
     - Validate required SFTP settings (host, username, remote_path)
     - Validate authentication method (must have password OR private key)

  2. **Establish SFTP Connection**:
     - Create `phpseclib3\Net\SFTP` instance with host and port
     - Attempt authentication (try key-based first if configured, fall back to password)
     - If connection fails, log error and exit with error code
     - Log successful connection

  3. **Prepare for Upload**:
     - Verify/create remote base directory (`SFTP_REMOTE_PATH`)
     - Initialize counters (`$uploadedCount = 0`, `$errorCount = 0`)
     - Get list of all files in input directory (recursive)

  4. **Upload Files**:
     - For each file in input directory:
       - Calculate relative path from input directory
       - Determine remote file path (remote_path + relative path)
       - Create remote directory structure if needed (recursive mkdir)
       - Upload file via SFTP put method
       - On success: increment `$uploadedCount`, log if verbose
       - On failure: increment `$errorCount`, log error, add to `$errors` array, continue
     - Show progress output for user feedback

  5. **Report Results**:
     - Log summary: "Uploaded X files, Y errors"
     - If errors occurred, list all error messages
     - Return exit code: 0 if no errors, 1 if any errors occurred

- Helper Methods:
  - `loadConfiguration(InputInterface $input): array` - Load and validate all configuration
  - `connectSftp(array $config): bool` - Establish SFTP connection with authentication
  - `authenticateWithKey(string $keyPath, ?string $passphrase): bool` - Key-based auth
  - `authenticateWithPassword(string $password): bool` - Password-based auth
  - `ensureRemoteDirectory(string $path): bool` - Create remote directory recursively
  - `getFilesToUpload(string $directory): array` - Get recursive file list
  - `uploadFile(string $localPath, string $remotePath): bool` - Upload single file
  - `disconnect(): void` - Close SFTP connection cleanly

### Error Handling
- Connection failures: Log error, exit immediately with code 1
- Missing configuration: Log error, exit immediately with code 1
- Authentication failures: Log error, exit immediately with code 1
- File upload failures: Log error, continue with remaining files
- Directory creation failures: Log error, continue with remaining files
- All errors use EiccUtils logger with appropriate severity levels

### Testing
- Create `tests/Unit/Commands/UploadSiteCommandTest.php`:
  - Test configuration loading and validation
  - Test missing required configuration (host, username, remote_path)
  - Test authentication method validation (password OR key required)
  - Test input directory validation
  - Mock SFTP connection for testing without real server
  - Test file list generation from directory
  - Test remote path calculation
  - Test error counting and reporting
  - Test exit codes (0 on success, 1 on errors)

- Integration testing considerations:
  - Manual testing with real SFTP server recommended
  - Document test server setup in test file comments
  - Can use Docker SFTP container for local integration testing

### Documentation
- Update `docs/ADDITIONAL_COMMANDS.md`:
  - Add "site:upload" section after "site:render"
  - Explain SFTP upload functionality
  - Show configuration examples for both authentication methods
  - Provide usage examples: `php bin/console.php site:upload`
  - Document `--input` option usage
  - Explain error handling behavior (continues on errors)
  - Include security best practices (use key-based auth, secure .env file)

- Update `README.md`:
  - Add upload command to deployment workflow section
  - Show typical workflow: `site:render` then `site:upload`
  - Link to detailed documentation in ADDITIONAL_COMMANDS.md

### Security Considerations
- `.env` file must be secured (not committed to git, proper file permissions)
- Private key files should have restrictive permissions (0600)
- Document recommendation for key-based auth over password auth
- Sensitive configuration loaded from environment only
- No credentials logged or displayed in output
- SFTP connection uses standard SSH security (encryption, authentication)

### Edge Cases & Validation
- Empty output directory: Log warning, exit gracefully (nothing to upload)
- Missing remote directory: Create it recursively
- Existing remote files: Overwrite (upload always replaces)
- Special characters in filenames: Handle properly via SFTP protocol
- Large files: Stream upload (phpseclib handles this)
- Network interruption: Log error, report failed files
- Permission issues on remote server: Log error, continue with accessible files
- Symbolic links in local directory: Follow links, upload target files

### Principles Applied
- Apply YAGNI: Essential SFTP upload only, no sync/diff logic, no delete operations
- Apply KISS: Direct full upload every time, clear error reporting, straightforward authentication
- Apply SOLID:
  - Single Responsibility: Command handles SFTP upload only
  - Dependency Injection: Uses container for configuration and logger
  - Interface Segregation: Uses Symfony Command interface
- Apply DRY: Reusable helper methods, centralized error handling
- Apply Separation of Concerns: Command handles CLI, phpseclib handles SFTP protocol

### Completion Checklist
- ✅ Add `phpseclib/phpseclib` to composer.json dependencies
- ✅ Run `composer update` to install phpseclib
- ✅ Create `UploadSiteCommand` class with full implementation
- ✅ Add SFTP configuration variables to `.env.example`
- ✅ Update `tests/.env.testing` with test SFTP config
- ✅ Create comprehensive unit tests (13 test cases, 29 assertions)
- ✅ Update `docs/ADDITIONAL_COMMANDS.md` with upload command documentation
- ✅ Update `README.md` with deployment workflow
- Manual integration testing with real SFTP server (requires server setup)
- Manual verification: key-based authentication works
- Manual verification: password-based authentication works
- Manual verification: directory structure creation on remote server
- Manual verification: all file types upload correctly (html, css, js, images, pdf)
- Manual verification: error handling and reporting works correctly
- ✅ Run full test suite - all 196 tests passing
- ✅ Update `documents/plan.md` to show completed tasks and step with ✅ after verification
- ✅ Wait for further instructions

---

## Quality Gates
Each step must result in:
- ✅ All unit tests passing
- ✅ System remains in working state
- ✅ New functionality demonstrated with example
- ✅ Error handling tested and documented
- ✅ Code follows SOLID principles and project conventions