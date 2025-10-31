# Test Site Fixtures

This directory contains reusable test fixtures for integration tests.

## Structure

- `content/` - Sample content files (HTML, Markdown)
- `templates/` - Sample Twig templates
- `configs/` - Sample .env configurations

## Usage

Integration tests can copy files from these fixtures to set up test scenarios without duplicating content across multiple test classes.

## Available Fixtures

### Basic Blog Site
- Mixed HTML and Markdown content
- Blog posts with categories and tags
- Menu structure with nested items
- Category index pages

### Documentation Site
- Nested directory structure
- Technical documentation
- API reference pages
- Search integration ready

### Portfolio Site
- Project showcase pages
- Image galleries
- Contact forms
- Client testimonials

## Adding New Fixtures

1. Create content in appropriate subdirectory
2. Ensure frontmatter uses INI format (key = value)
3. Document fixture in this README
4. Add helper method to IntegrationTestCase if needed
