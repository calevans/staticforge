---
description: Project-wide instructions
applyTo: '**'
---
# Honor the following programming concepts
- KISS (Keep It Simple, Stupid)
- SOLID principles
- DRY (Don't Repeat Yourself)
- YAGNI (You Aren't Gonna Need It)
- Separation of Concerns
- Single Responsibility Principle
- Dependency Injection
- Composition over Inheritance
- Favor composition over inheritance
- Favor interfaces over concrete classes

# Infrastructure and Environment
- We use twig
- We use lando
- DB creds are in the .env file
- We use EiccUtils for logging
- non-unit test test code goes in example_code/
- executable code that is part of the project goes in bin/
- Unit tests go in tests/
- Integration tests go in tests/integration/
- Do not use scripts/ that is for lando-specific scripts.

# Lando Specific Instructions
- The database name is lamp
- Always use lando php to run php commands
- Always use lando mysql to run database commands
- Always use lando ssh -c to run commands in the container
- Always use lando composer to run composer commands

# General Coding Standards
- Use prepared statements for all database queries
- Follow migration best practices (never modify existing migrations)
- Use transactions for multi-step database operations
- Sanitize all user inputs
- Use proper indexing strategies
- Validate and sanitize all inputs
- Use CSRF protection
- Implement proper authentication/authorization
- Never commit secrets or credentials
- Use HTTPS in production configurations
- Write tests before implementing features (TDD when appropriate)
- Document API endpoints in docs/api/
- Log errors with appropriate severity levels using EiccUtils
- Include context in log messages (user ID, request ID, etc.)
- Use structured logging (JSON format preferred)
- Never log sensitive information (passwords, tokens, etc.)
- Optimize database queries (avoid N+1 problems)
- Minimize external API calls
- Maintain >80% code coverage
- Mock external dependencies in tests
- Use descriptive test method names

# Behavior
- Be concise in your responses. I do not need to see your process, just the outcome. So no checklists or task receipts.