# Answer Engine / Agent Experience Optimization (AXO) Feature Plan

## 1. Overview
The AEO/AXO feature will be built as an **External Feature** in `Features/AnswerEngineOptimization/`. Its purpose is to prepare websites for Large Language Models (LLMs) and AI Agents (like ChatGPT, Perplexity, Claude, Google Gemini). It targets making sites "agent-ready" through structured data, machine-readable data foundation, clean semantic layouts, verifiable facts, content freshness, and dedicated AI sitemaps.

## 2. Directory Structure
```
Features/AnswerEngineOptimization/
├── Feature.php
├── Services/
│   ├── SchemaGeneratorService.php
│   ├── AeoExtractorService.php
│   └── LlmsTxtGeneratorService.php
└── Shortcodes/
    └── FaqShortcode.php
```

## 3. Configuration & Dependency Injection
- `Feature.php`: Implement `EICC\StaticForge\Core\ConfigurableFeatureInterface` to allow global toggling and default publisher data configuration in `siteconfig.yaml`.
- **DI Registration**: Register the `SchemaGeneratorService`, `AeoExtractorService`, and `LlmsTxtGeneratorService` in the `EICC\Utils\Container`. Do not use `new` to instantiate these services.
- **State Management**: Because the processing loop iterates through many files, services MUST clear their state (e.g., accrued JSON-LD data) at the start of each file's processing.

## 4. Content Authoring & Extractability
- **Frontmatter**: Support an `aeo:` array in YAML to define FAQs, `key_takeaways` summary, and structured metadata for the Knowledge Graph.
- **Shortcodes**: Register `[aeo_faq question="..."]` to output `<details>`/`<summary>` Semantic HTML while queuing data for JSON-LD.
- **Content Freshness**: Automatically extract the file's `mtime` (or Git commit time if available) to inject `<time datetime="...">` tags and `<meta property="article:modified_time">` to satisfy RAG models that require current data.
- **Sanitization (Security)**: All extracted values from shortcodes and frontmatter MUST be strictly escaped using `htmlspecialchars($value, ENT_QUOTES, 'UTF-8')` before being injected into HTML attributes or text nodes.

## 5. Event Hooks Pipeline
- **`PRE_RENDER`**:
  - Register shortcodes to process AEO specific embedded content.
  - Pull file modification times to prepare freshness headers.
- **`MARKDOWN_CONVERTED`**:
  - Utilize `AeoExtractorService` to parse raw HTML via native `DOMDocument` for auto-generating schemas from `<h2>`/`<h3>` question tags if no explicit frontmatter is provided.
  - Ensure extracted chunk data is sanitized (`strip_tags`) and type-checked before building schema.
- **`POST_RENDER`**:
  - **Schema Injection**: Inject the final `<script type="application/ld+json">...</script>` payload (using `json_encode` strict flags: `JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT`) into the document's `<head>`.
  - **Raw Markdown Output**: For every parsed `.md` file, save the clean text/markdown representation alongside the `.html` file (e.g., `public/page/index.md`). This allows AI Agents that prefer "machine-readable" experiences to consume the raw markdown directly, avoiding HTML bloat. **Crucially, all internal YAML frontmatter must be stripped** out (or selectively allowed to only show safe SEO-related fields) before writing the markdown mirror to `public/`, to prevent leaking sensitive editor metadata (draft statuses, reviewer notes).
- **`POST_LOOP`**:
  - **llms.txt Generation**: Use `LlmsTxtGeneratorService` to curate an `/llms.txt` and `/llms-full.txt` footprint at the root of the site. It acts as an AI-specific sitemap summarizing the core pages and prioritizing important/authoritative content directly as markdown links.
- **`SEO_AUDIT_PAGE`**:
  - Hook into the existing SEO Command's custom event to ensure high-priority pages have AEO signals (FAQ schema, summary block), check if core content is wrapped in semantic HTML (e.g., `<article>`, `<main>`), and warn against "content bloat" that hinders LLM extraction.

## 6. Access Control & Agents (Prerequisite: Core Modification)
- **This feature DOES NOT own `robots.txt`**, but requires its rules to be updated for AI crawlers.
- **Core Refactor**: The core `RobotsTxt` feature must be refactored to emit a `ROBOTS_TXT_BUILDING` event immediately before it compiles the `robots.txt` string.
- **Mechanism**: The `ROBOTS_TXT_BUILDING` event payload will contain a structured array of rules (e.g., `['User-agent: *' => ['Disallow: /private']]`).
- **AXO Integration**: The AEO/AXO feature will listen for the `ROBOTS_TXT_BUILDING` event and inject its own rules (e.g., `['User-agent: OAI-SearchBot' => ['Allow: /']]`) into the payload, allowing the `RobotsTxt` feature to seamlessly compile and write the final file without tight coupling.

## 7. Coding Standards
- **Strict Typing**: Every new PHP file must begin with `declare(strict_types=1);`.
- **Namespacing**: Ensure PSR-4 maps `Features\` correctly in `composer.json` without conflicting with the core `EICC\StaticForge\Features\` namespace.
- **Paths**: Any file I/O must use absolute paths prefixed with `$container->getVariable('app_root')`.
- **No Node/NPM**: No modern JS build tools are permitted for the frontend output.

## 8. Testing Strategy (QA)
- **Coverage**: Must maintain >80% test coverage for all new classes.
- **Unit Tests**: Place in `tests/Unit/Features/AnswerEngineOptimization/`. Mock the `EICC\Utils\Container` and other core services. Test edge cases (missing `aeo:`, malformed headers, empty bodies).
- **Integration Tests**: Place in `tests/Integration/Features/AnswerEngineOptimization/`. Must validate:
  1. The output JSON-LD string is syntactically valid JSON.
  2. The `/llms.txt` file is generated correctly in `public/`.
  3. The raw `.md` representations are saved.
- **Execution**:
  - `lando phpunit tests/Unit/Features/AnswerEngineOptimization/`
  - `lando php bin/staticforge.php site:render`

## 9. Deliverables
1. Core PHP classes in `Features/AnswerEngineOptimization/`.
2. Secure event listeners for Schema Generation, Markdown representation mirroring, and `llms.txt` compiling.
3. Unit and Integration tests meeting >80% coverage.
4. Updates to `siteconfig.yaml.example` documenting AI crawler settings and publisher schema defaults.