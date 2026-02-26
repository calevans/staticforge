# Generative Engine Optimization (GEO) Features for StaticForge

Based on the principles of Generative Engine Optimization (GEO) for AI search engines (like ChatGPT, Perplexity, and Google AI Overviews), here are several feature ideas that could be built into StaticForge to make sites like heartsntales.com more "GEO friendly".

AI engines prioritize structured data, clear entity relationships, high-quality authoritative content, conversational tone, direct answers, semantic HTML, and verifiable citations.

## 1. Structured Data / Schema.org Generator (`SchemaGeneratorFeature`)
*   **Concept:** Automatically generate rich JSON-LD blocks based on Markdown frontmatter.
*   **Why it's GEO friendly:** AI engines rely heavily on structured data to understand entities, relationships, and the context of the content without having to guess from the text.
*   **Implementation:** A `POST_RENDER` feature that reads frontmatter (e.g., `type: article`, `author: John Doe`) and injects the corresponding `<script type="application/ld+json">` into the `<head>`. Support for `Article`, `FAQPage`, `HowTo`, `Organization`, and `Person`.

## 2. "TL;DR" / Direct Answer Injector (`KeyTakeawaysFeature`)
*   **Concept:** A feature that takes a `tldr` or `key_takeaways` array from the frontmatter and injects a semantically marked-up "Direct Answer" block at the top of the page.
*   **AI Enablement:** Can optionally use an LLM API (e.g., OpenAI, Anthropic) during the build process to automatically generate a 3-bullet summary if the frontmatter is missing. Falls back to manual frontmatter if no API key is provided.
*   **Why it's GEO friendly:** AI engines love concise summaries and direct answers to user queries. Providing this explicitly makes it highly likely the AI will extract and cite this exact block.
*   **Implementation:** A `PRE_RENDER` or Twig extension that formats this data into a specific `<aside class="geo-summary">` block.

## 3. FAQ Auto-Schema Generator (`FaqSchemaFeature`)
*   **Concept:** Parses specific Markdown structures (e.g., H2/H3 headings that end in a question mark, followed by a paragraph) and automatically generates `FAQPage` JSON-LD schema.
*   **AI Enablement:** Can optionally use an LLM API to read the article and automatically generate 3-4 relevant "Frequently Asked Questions" and answers to inject into the Schema.org JSON-LD if none are explicitly defined. Falls back to parsing manual H2/H3 structures.
*   **Why it's GEO friendly:** Crucial for capturing conversational AI queries and "People Also Ask" style prompts.
*   **Implementation:** A `POST_RENDER` DOM parser that looks for question/answer patterns in the generated HTML and builds the JSON-LD array.

## 4. Citation and Source Formatter (`CitationFeature`)
*   **Concept:** A shortcode or Markdown extension to easily add, manage, and format citations, footnotes, and external authoritative links.
*   **Why it's GEO friendly:** AI engines prioritize verifiable facts and authoritative sources. Making citations machine-readable and prominent boosts credibility (E-E-A-T).
*   **Implementation:** A custom Shortcode (e.g., `[cite url="..."]`) that generates properly formatted footnotes and potentially adds them to the page's structured data.

## 5. Content Freshness Injector (`FreshnessFeature`)
*   **Concept:** Automatically injects `<meta property="article:modified_time">` and visible "Last Updated" badges.
*   **Why it's GEO friendly:** AI engines strongly prefer up-to-date, fresh content.
*   **Implementation:** A `PRE_RENDER` feature that checks the file's `mtime` or git commit history (if available) and adds it to the template variables, ensuring the `<head>` always has accurate modification dates.

## 6. Authoritativeness (E-E-A-T) Enhancer (`AuthorProfileFeature`)
*   **Concept:** Automatically links articles to detailed author profiles with credentials, social links, and schema.org `Person` data.
*   **Why it's GEO friendly:** Boosts the perceived authority and trustworthiness of the content for AI evaluators.
*   **Implementation:** A feature that cross-references an `author` frontmatter field with a central `authors.yaml` data file, injecting full author bios and schema into the page.

## 7. Semantic HTML Linter (`SemanticHtmlFeature`)
*   **Concept:** A build-time step that checks or enforces strict semantic HTML5 tags (`<article>`, `<section>`, `<aside>`, `<nav>`).
*   **Why it's GEO friendly:** Ensures the DOM is easily parsable by AI bots, helping them understand the document's hierarchy and main content vs. boilerplate.
*   **Implementation:** A `POST_RENDER` step that uses a DOM parser to warn if main content isn't wrapped in `<article>` or `<main>`.

## 8. Entity Tagging and Internal Linking (`EntityLinkerFeature`)
*   **Concept:** Analyzes content at build time to automatically generate meta tags for key entities and create internal links to related content.
*   **AI Enablement:** Can optionally use an NLP/LLM API to scan the text, identify the most important concepts/entities, and automatically link them to other pages. Falls back to a hardcoded taxonomy list or manual frontmatter tags.
*   **Why it's GEO friendly:** Helps AI build a knowledge graph of the site's domain and understand topical authority.
*   **Implementation:** A `POST_GLOB` or `PRE_RENDER` feature that scans for predefined keywords (from a taxonomy file) and auto-links them to category/tag pages.

---

## Architectural Notes for AI Enablement

To implement the AI features mentioned above, we must adhere to the project's SOLID principles and favor composition over inheritance. 

**Do NOT add AI logic to `BaseFeature`.** Doing so would violate the Single Responsibility Principle (SRP) and bloat every feature in the system with unnecessary dependencies.

Instead, we will use **Dependency Injection** and create a dedicated service:

1.  **Create `src/Services/AiService.php`**: This service will be responsible for:
    *   Managing API keys (read from `.env` or `siteconfig.yaml`).
    *   Handling HTTP requests to the LLM provider (e.g., OpenAI, Anthropic).
    *   Caching responses (crucial to avoid hitting the API repeatedly for unchanged content across builds).
    *   Handling rate limits and API errors gracefully.
2.  **Register in Container**: Initialize `AiService` during the bootstrap phase and store it in the `EICC\Utils\Container`.
3.  **Inject via Composition**: Only the specific features that require AI capabilities (e.g., `KeyTakeawaysFeature`, `FaqSchemaFeature`) will request the `AiService` via their constructor or retrieve it from the container.

**Example Implementation Pattern:**

```php
namespace EICC\StaticForge\Features\KeyTakeaways;

use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Services\AiService;

class KeyTakeawaysFeature implements FeatureInterface 
{
    public function __construct(private AiService $aiService) {}

    // ... inside the PRE_RENDER event hook ...
    public function onPreRender(Event $event): void 
    {
        $page = $event->getPage();
        $frontmatter = $page->getFrontmatter();

        // Fallback to manual frontmatter first
        if (empty($frontmatter['tldr']) && $this->aiService->isEnabled()) {
            // Use AI to generate the TL;DR if missing and AI is enabled
            $frontmatter['tldr'] = $this->aiService->generateTldr($page->getContent());
            $page->setFrontmatter($frontmatter);
        }
    }
}
```
This approach ensures the codebase remains decoupled, the AI logic is easily mockable for unit tests, and we strictly follow the project's architectural guidelines.
