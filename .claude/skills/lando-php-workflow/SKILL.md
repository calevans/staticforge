---
name: lando-php-workflow
description: Development workflow for StaticForge using strict PHP and the Lando environment wrapper.
applyTo: "**/*.php"
---

# Lando & PHP 8.4 Workflow

This skill encapsulates the fundamental environment and language execution constraints for the StaticForge project.

## Core Directives

1. **Lando Environment Prefix**:
   Every single command MUST be prefixed with `lando` when working locally.
   - Good: `lando php bin/staticforge.php site:render` | `lando composer install` | `lando phpunit`
   - Bad: `php bin/staticforge.php` | `composer require phpunit/phpunit`

2. **Prohibited Lando Commands**:
   You may **never** run: `lando start`, `lando restart`, `lando destroy`, or `lando rebuild`.

3. **Strict Constraints / Anti-Patterns**:
   - **NO NPM / NODE**: The project strictly prohibits JavaScript build tools, `npm`, or `package.json` for managing the source. Do not install or suggest them.
   - **NO CHained Script Deletion**: Do not execute a script via `lando php script.php` and immediately chain an `&& rm script.php` on the same line. Race conditions with Lando bindings will cause fails. Run execution and deletion as distinct steps.

4. **PHP Coding Standards (PSR-12)**:
   - Every file must start with `declare(strict_types=1);`.
   - Use `PascalCase` for classes, `camelCase` for methods, and `UPPER_SNAKE_CASE` for constants.
   - Adhere strictly to PSR-4 autoloading.

5. **Dependency Management**:
   Never use `new ServiceName()` to instantiate dependencies when they should be injected or accessed via the global `EICC\Utils\Container`.

6. **Absolute Pathways**:
   Rely on absolute paths inside the code to avoid cwd mismatches. Use `$container->getVariable('app_root')`.