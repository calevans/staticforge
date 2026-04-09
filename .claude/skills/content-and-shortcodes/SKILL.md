---
name: content-and-shortcodes
description: Protocols for handling content, shortcodes, YAML frontmatter, and Twig within StaticForge.
applyTo: "content/**/*.md, templates/**/*.twig"
---

# Content & Shortcode Protocols

This skill dictates how to process, sanitize, and inject `content/` input and Twig template logic safely and properly.

## Core Directives

1. **Format Requirements**:
   Content must be authored as `.md` or `.html` inside `content/` and must include a YAML frontmatter block for metadata mapping.

2. **Template Variables**:
   Twig templates should avoid overly complex logic. Logic belongs in Features, while presentation bindings belong in Twig (`{{ variable }}`). Look into `TemplateVariableBuilder` and `BaseRendererFeature` when mapping variables to Twig output.

3. **Input Sanitization & Output Encoding**:
   All user inputs from content frontmatter variables must be sanitized before being mapped to layouts. Use the `EiccUtils` sanitation patterns and ensure proper CSRF protection handles any generated HTML forms.

4. **Event Lifecycle Hooks for Shortcodes**:
   When implementing custom shortcodes inside the code (`PRE_RENDER`), ensure they inject correctly over the HTML/Markdown prior to full transformation (`RENDER`). Maintain strict string replacements. Do not assume HTML encoding. Always check against missing variables so a build process doesn't fatal due to incomplete frontmatter.