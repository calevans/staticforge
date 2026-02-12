---
name: documentation-style-guide
description: Style guide and tone rules for writing StaticForge documentation. Use when writing guides, READMEs, or updating content/ files.
---

# Documentation Style Guide

## When to use this
Use this skill when you are:
- Writing or updating documentation in `content/guide/` or `documents/`.
- Creating `README.md` files for features.
- Reviewing documentation for consistency.

## Voice & Tone
*   **Conversational but Professional**: Write like a helpful senior colleague.
*   **Direct**: Use active voice. "Run the command" instead of "The command should be run".
*   **Concise**: Avoid fluff. Get to the point.
*   **Encouraging**: Use phrases like "Best practice" or "Pro tip" to guide users.

## Formatting Rules

### Headers
*   Use Sentence case for headers (e.g., "Getting started with StaticForge", not "Getting Started With StaticForge").
*   Hierarchy: `# Title` > `## Section` > `### Subsection`.

### Code Blocks
*   Always specify the language for syntax highlighting (e.g., `bash`, `php`, `yaml`).
*   For CLI commands, include the prompt `$` only if distinguishing input from output is necessary. Otherwise, omit it for easier copy-pasting.

### Links
*   Use relative links for internal documentation: `[Configuration](configuration.md)`.
*   Use descriptive link text: "See [Installation Guide](install.md)" instead of "Click [here](install.md)".

## Structure for Guides
1.  **Overview**: 1-2 sentences explaining what this guide covers.
2.  **Prerequisites** (Optional): What they need before starting.
3.  **Steps/Content**: Logical progression of the topic.
4.  **Troubleshooting/FAQ** (Optional): Common pitfalls.

## Terminology
*   **StaticForge**: Always CamelCase.
*   **Frontmatter**: One word.
*   **Shortcode**: One word.
