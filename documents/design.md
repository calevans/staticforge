# StaticForge

## 1. Purpose & Vision

StaticForge is a PHP-based static site generator that addresses the language barrier and extensibility limitations of existing tools like Jekyll, Hugo, and Gatsby. By building in PHP, it opens static site generation to the large PHP developer community who can easily create and customize features. The system generates completely static websites that require no PHP or database at runtime, while providing a flexible, event-driven architecture for content processing.

This matters now because power users need a static site generator they can fully understand, modify, and extend without learning new languages or fighting opaque build systems.

## 2. Target Users & Personas

**Primary Users**: Power users comfortable with CLI tools who want to build static websites
- PHP developers seeking a static site generator in their preferred language
- Technical users who need full control over their site generation process
- Developers who want to extend functionality without language barriers

**Secondary Users**: Teams and agencies building client sites
- Need predictable, extensible tooling for various site types
- Want to leverage existing PHP expertise for customization

**Key Needs**:
- Complete control over content processing and output
- Ability to extend functionality without learning new languages
- Reliable, understandable tooling for diverse site types
- Clear separation between content, processing, and output

## 3. Goals & Outcomes

**Primary Goal**: Enable the creator to build the sites they want to build using PHP expertise

**Measurable Outcomes**:
- Successfully generates static sites from various content types (HTML, Markdown, PDF)
- Processes content through configurable event-driven pipeline
- Produces deployment-ready static files requiring no runtime dependencies
- Allows feature customization through clear extension points
- Maintains separation between core system and extensible features

**Success Indicators**:
- Creator can build their ecommerce site frontend
- System handles mixed content types gracefully
- Feature system allows customization without core modifications

## 4. Non-Goals & Boundaries

**Out of Scope for V1**:
- Separate Composer packages for features (V2 goal)
- Real-time content editing or admin interface
- Dynamic content requiring server-side processing
- Built-in hosting or deployment services
- Visual/GUI site building tools
- Performance optimization beyond basic efficiency

**Explicit Boundaries**:
- Generated sites must be completely static (no PHP runtime required)
- Features are built-in for V1, not externally distributed
- Error handling is logging-based, not user-facing

## 5. Core Concepts & Domain Model

**Feature**: Self-contained functionality unit that listens to events and processes content. Each feature handles a specific aspect of site generation (rendering, menu creation, categorization).

**Event**: Named trigger points in the generation pipeline where features can hook in to process content or modify system state. Events fire in predetermined sequence with data passed between listeners.

**Content File**: Source material (HTML, Markdown, PDF) with optional metadata header (INI section) that guides processing and output.

**Container**: Central registry holding configuration, feature instances, and shared data accessible throughout the generation process.

**Template**: Twig-based layout files that wrap processed content for final output.

**Renderer**: Feature component responsible for converting specific content types into HTML output.

## 6. User Journeys & Key Scenarios

**Scenario 1: Basic Site Generation**
- User places content files in source directory
- Runs `render:site` command
- System discovers files, processes through feature pipeline
- Outputs complete static site ready for deployment

**Scenario 2: Custom Menu Structure**
- User adds menu metadata to content file headers
- Menu feature processes metadata during generation
- Generated site includes navigation structure based on content metadata

**Scenario 3: Categorized Content**
- User assigns categories to content via metadata
- Categories feature organizes output into category directories
- Category Index feature generates index pages with pagination

**Scenario 4: Mixed Content Types**
- User has HTML pages, Markdown posts, and PDF documents
- Appropriate renderer features process each type
- System generates unified site with consistent templating

**Scenario 5: Unhandled Content**
- User places file type with no corresponding renderer
- System logs error and continues processing other files
- Generation completes successfully for supported content

## 7. Capabilities & Feature Set

**Core Pipeline Management**
- Event-driven processing with fixed execution sequence
- Feature registration and dependency injection
- Configuration loading and environment management
- File discovery and content inventory

**Content Processing**
- HTML file rendering with template integration
- Markdown to HTML conversion and rendering
- PDF link generation without conversion
- Metadata extraction from content headers

**Site Organization**
- Menu generation from content metadata
- Category-based content organization
- Tag support for content classification
- Automated index page creation with pagination

**Output Generation**
- Template-based final rendering using Twig
- Static file output requiring no runtime dependencies
- Proper URL structure and asset linking
- Clean separation of content and presentation

## 8. Constraints & Assumptions

**Technical Constraints**:
- Must use PHP 8.1+ for development
- Generated output must be completely static
- All database-like functionality handled through files
- Templates restricted to Twig engine

**Operational Constraints**:
- CLI-only interface for generation commands
- File-based configuration and content management
- Local development environment assumed

**Assumptions**:
- Users are comfortable with command-line tools
- Content creators can work with metadata headers
- PHP ecosystem provides sufficient template and utility libraries
- Static hosting is the intended deployment target

## 9. Phasing & Milestones

**Phase 0: Foundation** (~1-2 months)
- Core application structure and dependency injection
- Event system implementation and testing
- Basic file discovery and processing pipeline
- Command-line interface with Symfony Console

**Phase 1: Core Features** (~1-2 months)
- HTML, Markdown, and PDF renderer features
- Template system integration with Twig
- Menu generation from metadata
- Basic site generation workflow

**Phase 2: Organization Features** (~1 month)
- Categories and category index generation
- Tag support and organization
- Enhanced metadata processing
- Error handling and logging refinement

**Phase 3: Polish & Documentation** (~1 month)
- Comprehensive testing and debugging
- Documentation and usage examples
- Performance optimization
- Package preparation for distribution

## 10. Risks & Open Questions

**Top Risks**:
- Feature interdependency complexity could make system brittle
  - Mitigation: Clear event contracts and minimal feature coupling
- Event system performance with many features
  - Mitigation: Priority-based execution and efficient event handling
- Template security with user-provided content
  - Mitigation: Twig auto-escaping and input sanitization

**Open Questions**:
- How should feature conflicts be resolved when multiple features handle same content?
- What metadata validation is needed to prevent generation errors?
- How granular should event hooks be for optimal extensibility?
- What level of template customization should be exposed to users?

## 11. Alternatives Considered

**Alternative 1: Extend existing static site generator**
- Rejected because goal is PHP ecosystem accessibility and full control
- Existing tools require learning new languages or build systems

**Alternative 2: WordPress static generation plugin**
- Rejected because adds unnecessary complexity and WordPress dependencies
- Goal is clean, minimal system without CMS overhead

**Alternative 3: Custom build scripts without framework**
- Rejected because lacks extensibility and reusability
- Event-driven architecture provides better long-term flexibility