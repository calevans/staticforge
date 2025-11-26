# Technical Specification

## 0. Context & Scope

StaticForge is a PHP-based static site generator that processes content files (HTML, Markdown, PDF) through an event-driven pipeline to produce deployment-ready static websites. The system targets power users comfortable with CLI tools and provides extensible functionality through a built-in feature system.

**In Scope**: CLI-based site generation, event-driven content processing, template rendering, built-in features for common tasks (menus, categories, rendering, sitemap, drafts, image optimization, asset minification), error logging and resilience.

**Out of Scope**: Real-time content editing, web-based administration, dynamic content requiring server-side processing, external feature distribution (V1), performance optimization beyond basic efficiency.

## 1. Users, Roles, and Permissions

**Site Builder (Primary Role)**:
- Full access to all CLI commands and configuration
- Can modify templates, content, and .env configuration
- Responsible for managing source content and deployment

**No additional roles required** - this is a single-user development tool with no runtime access control needs.

**Privacy Expectations**: All content and configuration is local to the development environment. Generated static sites contain no sensitive system information.

## 2. Data & Information

**Core Entities**:

**Content File**: Source material with optional YAML metadata header
- Types: .html, .md, .pdf files
- Metadata includes: title, menu position, category, tags, draft status
- Source of truth: Local filesystem in configured source directory
- Retention: Managed by user, no automatic deletion

**Generated Site**: Static HTML/CSS/JS output
- Source of truth: Output directory (overwritten on each generation)
- Retention: Managed by user deployment process
- No sensitive data beyond what user includes in source content

**Sitemap Data**: XML file listing all public URLs
- Source of truth: Generated from processed content files
- Contains: URL, last modification date
- Retention: Overwritten on each generation

**Optimized Assets**: Processed images and minified CSS/JS
- Source of truth: Generated from source assets
- Contains: WebP/AVIF images, minified code
- Retention: Overwritten on each generation

**Configuration Data**: Environment variables and feature settings
- Source of truth: .env file and feature configuration
- Contains: directory paths, site metadata, feature settings
- Retention: Persistent until user modification

**Event Data**: Runtime processing information
- Source of truth: In-memory during generation only
- Contains: processing state, feature communication data
- Retention: Discarded after generation completes

**Audit Trail**: Error and processing logs via EiccUtils
- Contains: generation events, feature errors, processing warnings
- Retention: Based on logging configuration (user-controlled)

## 3. Workflows & Event Triggers

**Site Generation Workflow**:
1. Load .env configuration (fail if missing/invalid)
2. Instantiate and register features
3. Fire CREATE event (feature initialization)
4. Fire PRE_GLOB event (pre-discovery hooks)
5. Discover content files (filter out drafts unless overridden)
6. Fire POST_GLOB event (post-discovery processing)
7. Fire PRE_LOOP event (pre-processing initialization)
8. For each content file:
   - Fire PRE_RENDER event (image tag detection)
   - Fire RENDER event (content processing chain)
   - Fire POST_RENDER event (sitemap URL collection)
9. Fire POST_LOOP event (sitemap generation, asset minification)
10. Fire DESTROY event (final cleanup)

**Error Handling Events**:
- Feature failures: Log error, continue processing
- Core failures: Halt generation with error code
- Missing renderers: Log error, skip file, continue
- Template errors: Log error, attempt to continue or fail based on severity

**Event Processing Rules**:
- Events fire in priority order (0-999, default 100)
- Each listener receives container + parameters array
- Modified parameters pass to next listener in chain
- Final result used by event originator

## 4. Performance & Reliability Targets

**Generation Performance**:
- Target content processing: Up to thousands of files
- Generation time: Not critical (batch process)
- Memory usage: Reasonable for content set size
- File I/O: Efficient but not optimized for speed

**Reliability Expectations**:
- Feature failures should not stop site generation
- Core system failures should halt with clear error messages
- Acceptable for generation to fail if configuration is invalid
- Must complete successfully for valid content and configuration

**Content Freshness**:
- Uses snapshot of content at generation start time
- No requirement for real-time content updates during generation
- Generated output reflects state at generation time

## 5. Access, Security, and Compliance

**Access Control**: Local development tool only - no network access controls needed

**Security Considerations**:
- Template rendering: Use Twig auto-escaping for user content
- File operations: Restrict to configured directories only
- Content processing: Sanitize metadata parsing
- No execution of user-provided code outside templates

**Input Validation**:
- Validate .env file format and required values
- Validate content file metadata structure
- Validate file paths to prevent directory traversal

**No specific compliance requirements** - tool operates entirely in local development environment.

## 6. Integrations

**Required Dependencies**:
- vlucas/phpdotenv: Environment configuration loading
- symfony/twig: Template rendering engine
- eicc/utils: Logging and utility functions and DIC
- symfony/console: CLI command framework

**No external service integrations** - all processing is local and self-contained.

**File System Integration**:
- Source directory: Read-only access to content files
- Output directory: Full write access (overwrite mode)
- Template directory: Read-only access to Twig templates
- Features directory: Read-only access to feature classes

## 7. Interfaces & Devices

**CLI Interface Only**:
- Primary commands: `render:site`, `render:page <pattern>`
- Command options: `--clean`, `--verbose`, `--output=<dir>`
- Exit codes: 0 for success, non-zero for errors
- Output: Progress messages to stdout, errors to stderr

**No web interface, mobile support, or offline requirements** - development tool runs in terminal environment.

**Accessibility**: Standard CLI accessibility through terminal/shell accessibility features.

## 8. Observability & Operations

**Required Logging** (via EiccUtils):
- Generation start/completion with timing
- Feature registration and event firing
- File processing progress and results
- Feature errors and warnings (continue processing)
- Core errors (halt processing)
- Configuration loading success/failure

**Log Levels**:
- ERROR: Core failures, missing configuration, critical issues
- WARNING: Feature failures, unhandled content types, processing issues
- INFO: Generation progress, feature registration, major milestones
- DEBUG: Event firing, detailed processing steps

**No monitoring dashboards or alerts needed** - development tool with local execution.

## 9. Reporting & Analytics

**CLI Output Requirements**:
- Generation summary: files processed, features loaded, errors encountered
- Verbose mode: Detailed processing information
- Error reporting: Clear error messages with context
- Progress indication for large content sets

**No analytics collection or external reporting** - local development tool only.

## 10. Environments & Change Management

**Single Environment**: Local development only
- No staging/production environments for the generator itself
- Generated output deployed to separate hosting environment

**Configuration Management**:
- .env file for local configuration
- Version control friendly (exclude .env, include .env.example)
- No configuration synchronization between environments needed

**Release Management**:
- Composer package distribution
- Semantic versioning for core system
- Feature compatibility maintained within major versions

## 11. Open Technical Questions

**Q1**: How should feature dependency conflicts be resolved when multiple features modify the same content?
- Need: Conflict resolution strategy for overlapping feature functionality.
**A1**: The last feature to touch a piece of content will take precedence. Fatures should fully document their behavior and where in the system they hook in.

**Q2**: What level of template customization validation is needed?
**A2**: None. Assume the users are adults. If they screw the template, they can fix it and re-gen the site.
- Need: Balance between flexibility and error prevention in user templates

**Q3**: Should the system provide built-in content validation beyond metadata parsing?
**A3**: No built-in content validation. Users are responsible for content quality.
- Need: Determine scope of content quality checks during processing

## 12. Risks & Mitigations

**R1: Feature System Complexity**
- Risk: Complex feature interactions create brittleness
- Mitigation: Clear event contracts, minimal feature coupling, comprehensive testing of event chains

**R2: Template Security**
- Risk: User-provided templates could execute unsafe code
- Mitigation: Twig sandboxing, input sanitization, template validation

**R3: Large Content Set Performance**
- Risk: Memory exhaustion or excessive processing time with thousands of files
- Mitigation: Streaming file processing, memory monitoring, batch processing options

**R4: Configuration Management**
- Risk: Missing or invalid configuration causes cryptic failures
- Mitigation: Comprehensive validation with clear error messages, .env.example template

## 13. Decision Log

**D1: Event-Driven Architecture** — Provides extensibility without tight coupling between features — Oct 2025 — Project Lead

**D2: Built-in Features for V1** — Simplifies initial development and testing before external package distribution — Oct 2025 — Project Lead

**D3: Graceful Feature Failure** — Resilient processing allows partial site generation when individual features fail — Oct 2025 — Project Lead

**D4: Overwrite Output Directory** — Simple, predictable behavior eliminates state management complexity — Oct 2025 — Project Lead

**D5: Snapshot Content Consistency** — Process files as discovered at generation start to avoid mid-generation inconsistencies — Oct 2025 — Project Lead