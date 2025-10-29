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

## Step 18. Integration Testing & End-to-End Validation
- Review `documents/idea.md`, `documents/design.md`, `documents/technical.md`, and `documents/plan.md` completely to understand the plan and scope.
- Review all code about to be edited and any related code.
- Create comprehensive integration test suite for full site generation
- Test mixed content scenarios (HTML, Markdown, PDF together)
- Validate feature interaction and event pipeline processing
- Test error scenarios and graceful failure modes
- Create test fixtures for various site configurations and content types
  - Apply YAGNI: Essential integration scenarios only, realistic test data
  - Apply KISS: Clear test scenarios, straightforward validation
  - Apply SOLID: Tests validate component integration without implementation details
  - Apply DRY: Reusable test fixtures and validation patterns
- Update `documents/plan.md` to show completed tasks and step with ✅ after verification.
- Wait for further instructions.

---

## Step 19. Documentation & Usage Examples
- Review `documents/idea.md`, `documents/design.md`, `documents/technical.md`, and `documents/plan.md` completely to understand the plan and scope.
- Review all code about to be edited and any related code.
- Create comprehensive README with installation and usage instructions
- Document feature system and event hooks for extensibility
- Create example content files and templates
- Write feature development guide for future extensions
- Document configuration options and environment variables
  - Apply YAGNI: Essential documentation only, clear examples
  - Apply KISS: Straightforward examples, practical usage scenarios
  - Apply SOLID: Documentation reflects clean system boundaries
  - Apply DRY: Reusable documentation patterns and examples
- Update `documents/plan.md` to show completed tasks and step with ✅ after verification.
- Wait for further instructions.

---

## Step 20. Final Package Polish & Distribution Preparation
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