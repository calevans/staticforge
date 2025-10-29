# StaticForge CLI Commands

This document describes the available CLI commands for StaticForge.

## Available Commands

### render:site

Generate the complete static site from all content files.

**Usage:**
```bash
php bin/console.php render:site [options]
```

**Options:**
- `-c, --clean` - Clean output directory before generation
- `-t, --template=TEMPLATE` - Override the template theme (e.g., sample, terminal, vaulttech)
- `-v, --verbose` - Enable verbose output with detailed progress and statistics

**Examples:**

Basic site generation:
```bash
php bin/console.php render:site
```

Clean build with verbose output:
```bash
php bin/console.php render:site --clean -v
```

Use different template:
```bash
php bin/console.php render:site --template=vaulttech
```

**Verbose Output Includes:**
- Configuration settings (content dir, output dir, template, etc.)
- Event pipeline steps
- Files processed count
- Active features list
- Generation time and performance metrics

---

### render:page

Render a single file or files matching a pattern. Useful for quick testing or partial site updates.

**Usage:**
```bash
php bin/console.php render:page [options] [--] <pattern>
```

**Arguments:**
- `pattern` - File path or glob pattern to render (e.g., `content/about.md` or `content/*.md`)

**Options:**
- `-c, --clean` - Clean output directory before generation
- `-t, --template=TEMPLATE` - Override the template theme
- `-v, --verbose` - Enable verbose output with file list

**Examples:**

Render a single file:
```bash
php bin/console.php render:page content/about.md
```

Render all markdown files:
```bash
php bin/console.php render:page "content/*.md"
```

Render all HTML files with verbose output:
```bash
php bin/console.php render:page "content/*.html" -v
```

Render specific category with custom template:
```bash
php bin/console.php render:page "content/business/*" --template=terminal
```

**Pattern Resolution:**
- Absolute paths work directly
- Relative paths are resolved from content directory
- Glob patterns (`*`, `?`) are supported
- File extension can be omitted (will try `.md` and `.html`)

**Verbose Output Includes:**
- Pattern resolution details
- List of matched files
- File processing count

---

## Global Options

All commands support these Symfony Console options:

- `-h, --help` - Display help for the command
- `-q, --quiet` - Do not output any message
- `-V, --version` - Display application version
- `--ansi|--no-ansi` - Force (or disable) ANSI output
- `-n, --no-interaction` - Do not ask any interactive question
- `-v|vv|vvv, --verbose` - Increase verbosity (normal, verbose, debug)

---

## Use Cases

### Development Workflow

Quick iteration on a single page:
```bash
# Edit content/about.md
php bin/console.php render:page content/about.md
# Check public/about.html
```

### Content Preview

Preview all pages in a category before full build:
```bash
php bin/console.php render:page "content/blog/*.md" -v
```

### Template Testing

Test a new template on a subset of pages:
```bash
php bin/console.php render:page "content/index.md" --template=experimental
```

### Production Build

Full site generation with clean output:
```bash
php bin/console.php render:site --clean -v
```

### Debugging

Use verbose mode to troubleshoot issues:
```bash
php bin/console.php render:site -vvv  # Debug level verbosity
```

---

## Performance Notes

- `render:page` only processes matched files, making it faster for testing
- `render:site` processes all files and runs all features (menus, tags, categories)
- Both commands support the same template and clean options
- Verbose mode adds minimal overhead
- Average processing time shown in verbose output helps identify bottlenecks

---

## Exit Codes

- `0` - Success
- `1` - Failure (check error messages and logs)

---

## Tips

1. **Use patterns for batch operations**: `content/blog/*.md` renders all blog posts
2. **Test before full build**: Use `render:page` to verify changes quickly
3. **Enable verbose for debugging**: `-v` shows what's happening
4. **Clean builds**: Use `--clean` when changing templates or structure
5. **Template switching**: Test different themes without modifying `.env`

---

## Integration with Build Tools

You can integrate these commands into your build pipeline:

**package.json scripts:**
```json
{
  "scripts": {
    "build": "php bin/console.php render:site --clean",
    "dev": "php bin/console.php render:site -v",
    "preview": "php bin/console.php render:page 'content/*.md'"
  }
}
```

**Makefile:**
```makefile
.PHONY: build dev preview

build:
	php bin/console.php render:site --clean

dev:
	php bin/console.php render:site -v

preview:
	php bin/console.php render:page "content/*.md"
```

**Shell script:**
```bash
#!/bin/bash
# deploy.sh
php bin/console.php render:site --clean --template=production
if [ $? -eq 0 ]; then
    rsync -avz public/ user@server:/var/www/html/
fi
```
