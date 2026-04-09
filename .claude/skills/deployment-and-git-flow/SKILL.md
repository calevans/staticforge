---
name: deployment-and-git-flow
description: Critical security and deployment protocols for editing code, handling credentials, and publishing StaticForge.
applyTo: "**/*.md"
---

# Deployment & Security Workflow

This skill strictly enforces the boundaries around data security, deployment architecture, and local diagnostics.

## Core Directives

1. **NO AWS S3**: The project explicitly DOES NOT use AWS S3. All files must be hosted on the static server (`washington`). Ensure no implementation incorrectly relies on S3.
2. **NO Production SSH Fixes**: We NEVER fix things directly on the production server (`jeevs`). All software fixes flow through local repo commits to Git.
3. **NO Permanent Temp Scripts**: If a temporary or diagnostic PHP script MUST be created locally, it CAN ONLY go in `tmp/`. Never put temporary scripts in the root (`/`) or `public/` folder on local or production. They must be rigorously deleted immediately to avoid polluting the host's `git status` or blocking `git pull`.
4. **Environment Handling**: Credentials must be stored in `.env` exclusively and injected via `$container->getVariable()`. Never hardcode secrets.
5. **No npm/node**: DO NOT commit any scripts relying on Node/NPM for the project build process. Node/npm are STRICTLY allowed *only inside* Lando containers to be used solely as diagnostic/debugging utilities. The system itself never uses them.
6. **Path Traversal Security**: Ensure path traversal vulnerabilities are mitigated when accessing paths in `content/` and `templates/`.
7. **Write Access**: Never attempt to construct permanent output inside `public/` directly in code if you can instead hook into the standard Event loop payload (`RENDER`).