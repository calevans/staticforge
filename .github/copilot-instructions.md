# GitHub Copilot Instructions for StaticForge

## Project Overview

**Agents** StaticForge is a PHP-based static site generator that addresses the language barrier and extensibility limitations of existing tools like Jekyll, Hugo, and Gatsby. By building in PHP, it opens static site generation to the large PHP developer community who can easily create and customize features. The system generates completely static websites that require no PHP or database at runtime, while providing a flexible, event-driven architecture for content processing.

**Tech Stack**: PHP 8.4, Lando (development), Docker (production), Twig templating, Composer dependency management, Symfony Console for CLI commands.

## Development Environment & Build Instructions

### Environment Setup (CRITICAL - Always Use Lando)
**NEVER use Docker directly for development** - Docker (`docker-compose.yml`) is for production only. Always use Lando for development:


### Environment Configuration
- Development URL: `https://static-forge.lndo.site/`

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

# CLI commands (Symfony Console)
lando php bin/console.php list
```


You may never run the following commands:
```bash
lando start
lando restart
lando destroy
lando rebuild
```



### Known Build Issues & Workarounds

## Project Architecture & Layout


### Key Configuration Files
- **`.lando.yml`**: Development environment (PHP 8.4, MariaDB 11.3, Apache)
- **`composer.json`**: Dependencies and autoloading (PSR-4, PSR-12 standards)
- **`phpunit.xml`**: Test configuration with test database
- **`phpcs.xml`**: PHP coding standards (PSR-2, PSR-12)
- **`.env`**: Environment variables (database creds, API keys)


### Code Standards
- Follow PSR-12 coding standards (enforced via `phpcs`)
- Use 2-space indentation (configured in `phpcs.xml`)
- Dependency injection via `EICC\Utils\Container`
- All database queries use prepared statements
- Log errors using `EiccUtils` logger


### File Modification Rules
- **PHP files**: Follow instructions in `.github/instructions/php.instructions.md`
- Unit tests go in `tests/unit`, integration tests in `tests/integration/`
- Example/demo code goes in `example_code/`


### Testing Strategy
- PHPUnit for unit testing (tests may have autoloader warnings - ignore)
- Manual testing via web interface and CLI commands
- Integration testing for API endpoints

## Critical Information for Copilot

### Always Required Steps
1. **Use Lando**: Never run PHP/database commands without `lando` prefix
4. **Container**: Bootstrap sets up dependency injection - always use it

### Error Prevention
- Check Lando is running before any development work: `lando info`
- Environment file must exist and be configured: `.env`

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
