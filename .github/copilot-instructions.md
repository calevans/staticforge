# GitHub Copilot Instructions for Zillow Property Scraper

## Project Overview

**Agents** A web based AI orchestration system where users submit requests and a Master agent decides how to fulfill them using other agents when needed. Frontend handles all orchestration, backend stores agent definitions and logs activity.

**Tech Stack**: PHP 8.4, MariaDB/MySQL, Lando (development), Docker (production), Twig templating, Tailwind CSS, vanilla JavaScript with jQuery, Composer dependency management, Symfony Console for CLI commands.

## Development Environment & Build Instructions

### Environment Setup (CRITICAL - Always Use Lando)
**NEVER use Docker directly for development** - Docker (`docker-compose.yml`) is for production only. Always use Lando for development:


### Environment Configuration
- Database name is `lamp` (NOT agents as shown in .env.example)
- Database creds: `lamp/lamp/lamp` (user/password/database)
- Development URL: `https://agents.lndo.site`

### Build & Validation Commands

**ALWAYS use `lando` prefix for all PHP/database commands:**

You may run these commands without asking permission.

```bash
# Install dependencies
lando composer install

# Code style checking (WILL FAIL with current codebase)
lando phpcs src/
# Known issues: 5 coding standard violations in BrightDataService.php, EmailProcessingService.php, GoogleMapsService.php, Property.php, PageController.php

# Code style fixing
lando phpcbf

# Run tests (autoloader PSR-4 warnings are expected and safe to ignore)
lando phpunit

# Database operations
lando mysql -e "SHOW TABLES" lamp

# CLI commands (Symfony Console)
lando php bin/console.php list
lando php bin/console.php property:bulk-maintenance --help
```

You may run this command only after asking permission.

```bash
lando mysql lamp  # Interactive MySQL session
```

You may never run the following commands:
```bash
lando start
lando restart
lando destroy
lando rebuild
```


### CLI Command Testing
The application includes professional CLI commands accessible via `bin/console.php`:
- `property:rescan-images <id>` - Re-download property images
- `property:reanalyze <id>` - Re-run AI analysis only
- `property:bulk-maintenance` - Process stale properties in batches
- `fetch-demographics` - Fetch US Census demographic data

### Known Build Issues & Workarounds

1. **Composer Autoloader Warnings**: PSR-4 warnings about test classes are expected and safe to ignore
2. **PHPCS Failures**: Current codebase has 5 known coding standard violations - not blocking for development
3. **Database Connection**: Always use `database` as hostname, not `localhost` or `127.0.0.1`
4. **Lando Commands**: Never run `php`, `composer`, or `mysql` directly - always prefix with `lando`

## Project Architecture & Layout

### Directory Structure
```
├── src/                    # PHP application code (PSR-4 autoloaded)
│   ├── Controllers/        # HTTP request handlers
│   ├── Services/          # Business logic layer
│   ├── Models/            # Data models and database interaction
│   ├── Console/Commands/  # Symfony Console CLI commands
│   ├── Http/              # HTTP routing and middleware
│   └── Interfaces/        # Contract definitions
├── public/                # Web document root
│   ├── index.php         # Front controller (routes all requests)
│   ├── css/custom.css    # Additional styling beyond Tailwind
│   └── js/               # Vanilla JavaScript modules
├── templates/             # Twig templates for HTML rendering
├── bin/console.php       # CLI application entry point
├── bootstrap.php         # Dependency injection container setup
├── config/routes.php     # Route definitions
├── migrations/           # Database schema migrations (run in order)
└── .lando.yml           # Development environment configuration
```

### Key Configuration Files
- **`.lando.yml`**: Development environment (PHP 8.4, MariaDB 11.3, Apache)
- **`composer.json`**: Dependencies and autoloading (PSR-4, PSR-12 standards)
- **`phpunit.xml`**: Test configuration with test database
- **`phpcs.xml`**: PHP coding standards (PSR-2, PSR-12)
- **`.env`**: Environment variables (database creds, API keys)

### Database Schema
Core tables: `property`, `property_image`, `property_score`,`scoring_criteria`, `property_demographics`

### Frontend Architecture
- **Twig templating** with base layout (`templates/base.html.twig`)
- **Tailwind CSS** for styling with `public/css/custom.css` for extensions
- **Vanilla JavaScript** organized in modules (`public/js/`)
- **jQuery JavaScript** for DOM manipulation and AJAX requests
- **No cache busting** currently implemented (noted in TODO)

### Services Layer
- `PropertyScraperService`: Data collection from Zillow/BrightData
- `PropertyScoringService`: AI analysis using OpenAI
- `PropertyMaintenanceService`: Background maintenance operations
- `BrightDataService`: External API integration
- `GoogleMapsService`: Mapping and location services

## Development Guidelines

### Code Standards
- Follow PSR-12 coding standards (enforced via `phpcs`)
- Use 2-space indentation (configured in `phpcs.xml`)
- Dependency injection via `EICC\Utils\Container`
- All database queries use prepared statements
- Log errors using `EiccUtils` logger

### Database Operations
- Always use `lando mysql` for database access
- Run migrations in sequential order
- Use database name `lamp` for all connections
- Foreign key constraints are disabled in migrations
- table names are always siungular (e.g. `property`, not `properties`)

### File Modification Rules
- **PHP files**: Follow instructions in `.github/instructions/php.instructions.md`
- **CSS files**: Follow instructions in `.github/instructions/css.instructions.md`
- **JavaScript files**: Follow instructions in `.github/instructions/js.instructions.md`
- **Project-wide**: Follow instructions in `.github/instructions/project.instructions.md`
- Unit tests go in `tests/unit`, integration tests in `tests/integration/`
- Example/demo code goes in `example_code/`
- Always update `documents/prompt.txt` when tasks are completed

### API Endpoints
- `GET/POST /api/properties` - Property CRUD operations
- `POST /api/zillow-email` - Email processing (IP whitelisted)
- Authentication required for most endpoints (Session based e.g curl -b "example_code/cookies.txt" "https://zillowscraper.lndo.site/api/properties/94")

### Testing Strategy
- PHPUnit for unit testing (tests may have autoloader warnings - ignore)
- Manual testing via web interface and CLI commands
- Integration testing for API endpoints

## Critical Information for Copilot

### Always Required Steps
1. **Use Lando**: Never run PHP/database commands without `lando` prefix
2. **Database Name**: Use `lamp`, not environment variable values
3. **Migrations**: Run in numerical order for clean database state ONLY WHEN INSTRUCTED TO DO SO.
4. **Container**: Bootstrap sets up dependency injection - always use it

### Error Prevention
- Check Lando is running before any development work: `lando info`
- Environment file must exist and be configured: `.env`
- Database migrations must be applied before running application
- Coding standards violations are known and documented - not blocking

### Performance Considerations
- CLI bulk operations support batching via `--limit` parameter
- Image processing can be memory intensive - monitor for large datasets
- Database queries are optimized with indexes for maintenance operations

### Known Technical Debt
- TODO: Add cache buster for static assets
- TODO: Refactor JavaScript modules for better maintainability
- Property.php model is oversized and needs refactoring
- Test namespace PSR-4 compliance issues (safe to ignore)

**Trust these instructions** - they are comprehensive and tested. Only search for additional information if instructions are incomplete or found to be incorrect.
