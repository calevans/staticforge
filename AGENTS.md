# StaticForge Agent Guidelines

This document provides essential instructions for AI agents working on the StaticForge repository.

## 1. Project Context
**StaticForge** is a PHP-based static site generator. It uses a component-based architecture with dependency injection.
- **Language**: PHP 8.4+
- **Environment**: Lando (Docker wrapper) is REQUIRED for all runtime operations.
- **Dependency Management**: Composer
- **Testing Framework**: PHPUnit

## 2. Build, Test, and Lint Commands
**CRITICAL**: All commands must be prefixed with `lando` to run inside the container.

### Testing
- **Run all tests**:
  ```bash
  lando phpunit
  ```
- **Run a specific test file**:
  ```bash
  lando phpunit tests/Unit/Path/To/Test.php
  ```
- **Run a specific test method**:
  ```bash
  lando phpunit --filter methodName tests/Unit/Path/To/Test.php
  ```

### Linting & Formatting
- **Check coding standards**:
  ```bash
  lando phpcs src/
  ```
- **Fix coding standards automatically**:
  ```bash
  lando phpcbf
  ```
- **Note**: The project follows PSR-12 standards. 

### Composer
- **Install dependencies**:
  ```bash
  lando composer install
  ```
- **Update dependencies**:
  ```bash
  lando composer update
  ```

### CLI
- **Run application commands**:
  ```bash
  lando php bin/staticforge.php [command]
  ```

## 3. Code Style & Conventions

### PHP Standards
- **Standard**: PSR-12.
- **Indentation**: 4 spaces (Note: `copilot-instructions.md` mentions 2 spaces, but standard PSR-12 and most PHP projects use 4. Adhere to the `.editorconfig` or existing file indentation if different. Default to 4 if unsure).
- **Strict Types**: Always use `declare(strict_types=1);` at the top of PHP files.

### Naming Conventions
- **Classes**: PascalCase (e.g., `PageController`)
- **Methods**: camelCase (e.g., `generatePage`)
- **Variables**: camelCase (e.g., `$siteConfig`)
- **Constants**: UPPER_CASE_SNAKE (e.g., `DEFAULT_TIMEOUT`)
- **Namespaces**: Follow PSR-4. Map `EICC\StaticForge\` to `src/`.

### Architecture & Dependency Injection
- **Container**: Use `EICC\Utils\Container` for DI.
- **Bootstrap**: Always use the bootstrap file (`src/bootstrap.php`) to initialize the container in entry points.
- **Services**: Prefer creating services for logic rather than putting it in controllers or commands.

### Error Handling
- **Exceptions**: Use custom exceptions where appropriate.
- **Logging**: Use `EiccUtils` logger. Do not use `echo` or `print` for logging.

## 4. Specific Rules from Copilot Instructions
*(Adapted from `.github/copilot-instructions.md`)*

- **Lando is Mandatory**: NEVER use Docker directly. ALWAYS use `lando` prefix.
- **Golden Rules**:
  - Write tools ONLY in PHP.
  - Read classes fully before using them.
  - NEVER modify files in `vendor/`.
- **Testing**:
  - Unit tests: `tests/Unit`
  - Integration tests: `tests/Integration`
  - Ignore PSR-4 autoloader warnings during tests if they occur.

## 5. File Operations
- **Paths**: Always use absolute paths for file operations.
- **Modifications**: When modifying a file, read it first to preserve context and style.

## 6. Example Workflow
1. **Explore**: Use `ls`, `grep`, or `glob` to find relevant files.
2. **Read**: Read the content of the files to understand the logic.
3. **Plan**: Formulate a plan for the changes.
4. **Implement**: Edit the code.
5. **Verify**: Run `lando phpunit` or specific tests to verify. Run `lando phpcs src/` to check style.
