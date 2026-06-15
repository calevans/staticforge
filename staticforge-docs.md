# FILE: calendars/example_events/lunch-learn.md

Internal training session with pizza.
---

# FILE: calendars/example_events/project-deadline.md

Final submission deadline for Q2 projects.
---

# FILE: calendars/example_events/team-meeting.md

Weekly team synchronization.
---

# FILE: contact-us.md

# Get in Touch

Have questions about StaticForge? Want to contribute? Or just want to say hi? Fill out the form below and we'll get back to you as soon as possible.

{{ form('contact') }}
---

# FILE: development/architecture.md

# Under the Hood: Architecture & Data Flow

Welcome to the engine room. If you're looking to understand how StaticForge actually *works*—how it takes a pile of Markdown files and turns them into a polished website—you're in the right place.

This document breaks down the internal architecture, the data flow, and the "why" behind our design decisions.

---

## Core Philosophy

We built StaticForge on four simple architectural pillars. These aren't just rules; they're the reason the system is fast and predictable.

1.  **Single Source of Truth**: We parse your file metadata once, and only once. We don't re-read files a dozen times.
2.  **Event-Driven Processing**: Everything is an event. Features sit back and wait for the right moment to jump in and do their job.
3.  **Immutable Discovery**: We figure out what files exist and what their URLs are *before* we start rendering. This prevents broken links and circular dependencies.
4.  **Lazy Rendering**: We don't do the heavy lifting of converting Markdown to HTML until we absolutely have to.

---

## Phase 1: The Discovery Phase (Getting the Lay of the Land)

Before we render a single pixel, StaticForge needs to know what it's working with. We call this the **Discovery Phase**.

Located in `src/Core/FileDiscovery.php`, this component acts like a surveyor:

1.  **Scans**: It recursively walks through your `content/` directory finding every `.md` and `.html` file.
2.  **Parses**: It reads the Frontmatter (that block at the top of your files) to extract metadata like titles, categories, and tags.
3.  **Maps**: It calculates the final URL for every file based on its filename and category.

### The Result: `discovered_files`

Once the surveyor is done, we have a master map called `discovered_files`. It looks something like this:

```php
[
    [
        'path' => 'content/tutorials/intro.md',
        'url' => '/tutorials/intro.html',
        'metadata' => [
            'title' => 'Introduction to PHP',
            'category' => 'tutorials',
            'menu' => '1.1',
            'tags' => 'php,beginner',
            // ... all other frontmatter fields
        ]
    ],
    // ... more files
]
```

This map is stored in the **Container**, meaning every feature in the system can look at it to see the "big picture" of your site.

---

## Phase 2: The Event Lifecycle (The Assembly Line)

Think of StaticForge as an assembly line. Your content moves down the line, and different stations (Features) add things to it, polish it, or move it around.

Here is the sequence of events that happens every time you run `site:render`:

### The Flow

```
CREATE
  ↓
PRE_GLOB (prepare for discovery)
  ↓
POST_GLOB (The Planning Stage)
  ├─ CategoryIndex (priority 50) - Generates index pages so they can be in menus
  ├─ MenuBuilder (priority 100)
  ├─ Tags (priority 150)
  ├─ RobotsTxt (priority 150)
  └─ Categories (priority 250) - Applies category templates
  ↓
PRE_RENDER (before each file)
  ↓
RENDER (The Heavy Lifting)
  ├─ MarkdownRenderer
  └─ HtmlRenderer
  ↓
POST_RENDER (after each file)
  └─ Categories - Moves files to category directories
  ↓
POST_LOOP (The Wrap Up)
  ├─ RSSFeed (priority 90) - Only needs rendered content
  ├─ RobotsTxt (priority 100)
  └─ TemplateAssets (priority 100)
  ↓
UPLOAD_CHECK_FILE (Deployment)
  └─ S3 Offloader / Incremental Logic
  ↓
DESTROY (cleanup)
```

### The Priority System

StaticForge uses a simple numeric priority system to decide who goes first. We sort **ascending**, so lower numbers run first.

*   **Priority 50**: Runs very early (First Responders).
*   **Priority 100**: The Standard (Most features).
*   **Priority 250**: Runs late (Final Polish).

This ensures, for example, that `CategoryIndex` runs at **50** (creating pages) so that `MenuBuilder` at **100** can see them and add them to the navigation.

---

## Phase 3: The Deployment Phase (Going Live)

Once the site is built, we have to get it to the world. Deployment isn't just "copy/paste"; it's an intelligent pipeline of its own.

### The Upload Pipeline

When you run `site:upload`, we enter the Deployment Phase.

1.  **Manifest Sync**: We download the `staticforge-manifest.json` from the server to see what's already there.
2.  **Hashing**: We calculate the hash of every local file.
    *   **Smart Hashing**: For text files, we strip out timestamp parameters (`?sfcb=...`) so we don't re-upload files just because the cache buster changed.
3.  **The Hook (`UPLOAD_CHECK_FILE`)**:
    This is where plugins can intervene. Before any file is uploaded via SFTP, we fire this event.
    *   **Data**: You get the local path, remote path, and hashes.
    *   **Power**: You can say "I handled this" (e.g., uploaded to S3) or "Skip this".
4.  **Upload/Cleanup**: If no plugin objects, we upload changed files via SFTP and delete old ones.

---

## Deep Dive: The Planning Stage (POST_GLOB)

The `POST_GLOB` event is the most critical part of the process. This is where the "magic" happens before we write a single HTML file.

At this stage, we have a list of all your files, but we haven't processed them yet. This is the perfect time for features to:

1.  **Analyze the whole site**: Look at all the files to build menus, tag lists, or category indexes.
2.  **Modify Metadata**: Change the title, layout, or output path of a file based on rules.

### Example: How Categories Work

The Categories feature is a great example of this. It listens to `POST_GLOB` with a high priority (250) so it runs *after* most other things.

1.  It looks at every file in the list.
2.  If a file has `category: blog`, it changes the file's `layout` to `blog-post`.
3.  It changes the file's `outputPath` to include the category folder (e.g., `/blog/my-post.html`).

This is why `POST_GLOB` is so powerful. It lets you change the destiny of a file before it's even rendered.

---

## Metadata Flow (The Data Journey)

Every file in your `content/` directory starts with some basic data (frontmatter) and picks up more as it travels through the system.

1.  **Frontmatter**: The data you write at the top of your Markdown file.
    ```yaml
    title: My Post
    layout: default
    ```
2.  **Discovery**: StaticForge adds system data like `sourcePath` and `filename`.
3.  **Features**: Features add their own data.
    *   `MenuBuilder` adds `menu_structure`.
    *   `Tags` adds `tag_list`.
4.  **Rendering**: When the template engine (Twig) runs, it gets a merged array of **all** this data.

So, inside your template, you have access to everything: what you wrote, what the system found, and what the features calculated.

---

## Building Your Own Features

Features are the plugins of StaticForge. They are self-contained classes that hook into the system to do cool stuff.

To build a feature, you just need to:

1.  **Extend `BaseFeature`**: This gives you access to the Container and the Event Manager.
2.  **Listen for Events**: Tell the system "Hey, wake me up when `POST_GLOB` happens."
3.  **Do Your Thing**: Write your logic to modify files or add data.

### A Simple Example

Here is a feature that runs during the planning stage (`POST_GLOB`) to look at files.

```php
class MyFeature extends BaseFeature
{
    // Tell the system we want to run during the Planning Stage
    protected array $eventListeners = [
        'POST_GLOB' => ['method' => 'handlePostGlob', 'priority' => 150]
    ];

    public function handlePostGlob(Container $container, array $parameters): array
    {
        // Get the list of all files
        $discoveredFiles = $container->getVariable('discovered_files') ?? [];

        foreach ($discoveredFiles as $fileData) {
            // Look at the metadata
            $metadata = $fileData['metadata'];

            // Do something useful!
        }

        return $parameters;
    }
}
```

### Sharing Data with Templates

If your feature calculates something useful (like a list of related posts), you can pass it to your templates.

```php
// Inside your handlePostGlob method...
$parameters['features']['MyFeature'] = [
    'related_posts' => $relatedPosts
];
```

Then in your Twig template:

```twig
{% for post in features.MyFeature.related_posts %}
    <a href="{{ post.url }}">{{ post.title }}</a>
{% endfor %}
```

---

## Why We Built It This Way

We designed StaticForge with a few key goals:

1.  **Single Parsing**: We only read your files once. This makes the system fast.
2.  **Memory Efficient**: We keep the metadata in memory so we don't have to read the disk over and over.
3.  **Predictable**: Because of the priority system, you always know what order things will happen in.
4.  **Extensible**: You can add new features without touching the core code. Just listen for an event and go!

---

[← Back to Documentation](index.html)
---

# FILE: development/asset-manager.md

# The Asset Manager: Orchestrating Chaos

Managing CSS and Javascript manually is a recipe for disaster. You end up with duplicate tags, scripts loading in the wrong order, and `jQuery is not defined` errors.

The **Asset Manager** is the traffic cop of StaticForge. It ensures your scripts and styles load exactly when and where they should.

## The Core Problem

Imagine two different features (like a Photo Gallery and a Slider) both need jQuery.
*   **Without Asset Manager:** Both features add `<script src="jquery.js">`. Your page loads jQuery twice. Chaos ensue.
*   **With Asset Manager:** Both features tell the manager "I need jQuery." The manager nods, checks its list, and outputs the script tag **once**.

---

## How it Works

The Asset Manager is a core service available in the dependency injection container. You can grab it anywhere in your code.

### 1. Adding a Script

```php
use EICC\StaticForge\Core\AssetManager;

// Get the manager
$assetManager = $container->get(AssetManager::class);

// addScript(handle, path, dependencies, inFooter)
$assetManager->addScript(
    'my-script',               // The Handle: A unique name
    '/assets/js/app.js',       // The Path
    ['jquery'],                // Dependencies: Load 'jquery' first
    true                       // Footer: Load at the bottom (default)
);
```

### 2. Adding a Style

```php
// addStyle(handle, path, dependencies)
$assetManager->addStyle(
    'bootstrap',
    '/assets/css/bootstrap.min.css',
    []
);
```

---

## Dependency Resolution (The Magic)

This is the killer feature. You don't need to know *when* jQuery was added or if it was added at all. You just say "I depend on `jquery`".

The Asset Manager uses **Topological Sorting** to figure out the perfect order.

*   You add `app.js` (depends on `bootstrap`)
*   You add `bootstrap.js` (depends on `popper`)
*   You add `popper.js` (no deps)

**Output Order:** `popper.js` -> `bootstrap.js` -> `app.js`.

---

## Integration with Templates

You don't need to write `<script>` tags in your templates anymore. The Asset Manager injects three magic variables into every Twig template.

*   `{{ styles }}`: All CSS link tags.
*   `{{ head_scripts }}`: Critical JS that must be in `<head>`.
*   `{{ scripts }}`: All other JS (typically for the footer).

### Your Base Template

Make sure your `base.html.twig` looks something like this:

```twig
<!DOCTYPE html>
<html>
<head>
    <title>{{ title }}</title>

    <!-- CSS Injection Point -->
    {% if styles %}{{ styles | raw }}{% endif %}

    <!-- Critical JS Injection Point -->
    {% if head_scripts %}{{ head_scripts | raw }}{% endif %}
</head>
<body>
    {% block content %}{% endblock %}

    <!-- Footer JS Injection Point -->
    {% if scripts %}{{ scripts | raw }}{% endif %}
</body>
</html>
```
---

# FILE: development/bootstrap.md

# The Ignition Sequence: Bootstrapping

Before StaticForge can build a single page, it has to wake up, stretch, and get its tools ready. We call this the **Bootstrap Process**.

It's not magic; it's just a single file (`src/bootstrap.php`) that sets the stage for everything else.

---

## What Happens When You Hit Enter?

When you run a command like `site:render`, the system doesn't just start processing files immediately. First, it has to "pack its bags."

Here is the checklist it runs through:

1.  **Autoloading**: "Where are all my classes?" (Thanks, Composer!)
2.  **Environment Loading**: "What are the secrets?" (Reads `.env`)
3.  **Container Creation**: "I need a bag to hold my tools." (Creates the Dependency Injection Container)
4.  **Logger Setup**: "I need a notebook to write down what happens." (Sets up Logging)

Once this checklist is complete, the system hands you a fully loaded **Container** and says, "I'm ready."

---

## The Bootstrap File (`src/bootstrap.php`)

This file is unique. It's not a class; it's a procedural script. You give it an environment file, and it gives you back a Container.

### The Code Explained (Simplified)

```php
<?php
// src/bootstrap.php

// 1. Find and Load the Autoloader
// We check common paths (vendor/autoload.php) to find where Composer put the classes.

// 2. Load Environment Variables (.env)
// We look for .env in your current folder.
$dotenv = Dotenv\Dotenv::createUnsafeMutable(dirname($path), basename($path));
$dotenv->load();

// 3. Normalize Paths
// We turn relative paths like 'content/' into absolute paths like '/var/www/site/content/'
// so the system never gets lost.
$_ENV['SOURCE_DIR'] = $normalizePath($_ENV['SOURCE_DIR'] ?? 'content');

// 4. Create the Container
$container = new EICC\Utils\Container();

// 5. Load siteconfig.yaml
// This is your main configuration file (menus, site title).
// We load it and store it in the container for features to use.
$siteConfig = Yaml::parseFile('siteconfig.yaml');
$container->setVariable('site_config', $siteConfig);

// 6. Register Core Services
// We fire up the big engines:
$container->add(EventManager::class, new EventManager($container));
$container->add(AssetManager::class, new AssetManager());
// ... and others (FeatureManager, fileDiscovery, etc.)

// 7. Return the ready-to-use Container
return $container;
```

---

## Starting the Engine (Console Usage)

The most common way to start StaticForge is via the command line. The `bin/staticforge.php` script is the key.

It simply requires the bootstrap file and then hands the container to the application.

```php
#!/usr/bin/env php
<?php
// bin/staticforge.php

// 1. Run the Ignition Sequence
$container = require_once __DIR__ . '/../src/bootstrap.php';

// 2. Create the Console Application
$app = new Symfony\Component\Console\Application('StaticForge', '1.0.0');

// 3. Register Commands
// We register the core commands. Features will register their own commands later.
$app->add(new EICC\StaticForge\Commands\InitCommand());
// ...

// 4. Fire Console Init Event
$container->get(EICC\StaticForge\Core\EventManager::class)->fire('CONSOLE_INIT', ['application' => $app]);

// 5. Run the App
$app->run();
```

**Key Points:**
- Bootstrap returns container
- Features register their own commands via `CONSOLE_INIT` event
- Single initialization point

---

## Using Bootstrap in Tests

### Unit Tests

Unit tests extend `UnitTestCase` which handles bootstrap:

```php
<?php
namespace EICC\StaticForge\Tests\Unit;

use PHPUnit\Framework\TestCase;

abstract class UnitTestCase extends TestCase
{
    protected Container $container;

    protected function setUp(): void
    {
        parent::setUp();

        // Bootstrap with test environment
        $envPath = __DIR__ . '/../.env.testing';
        $this->container = include __DIR__ . '/../../src/bootstrap.php';
    }
}
```

**Usage in tests:**
```php
class MyFeatureTest extends UnitTestCase
{
    public function testSomething(): void
    {
        // Container already available
        $logger = $this->container->get('logger');

        // Use helper methods
        $this->setContainerVariable('SITE_BASE_URL', 'http://test.com');
        $this->addToContainer('my_service', new MyService());
    }
}
```

---

## The Toolbox (Container Services)

Once the bootstrap is done, the **Container** is your toolbox. It holds everything you need.

### The Logger

We use a singleton logger so we don't have 50 different log files open.

```php
// Get the logger from the toolbox
$logger = $container->get('logger');

// Write to it
$logger->info('Engine started successfully.');
```

### Environment Variables

Need to know the site URL? It's in the container too.

```php
$siteUrl = $container->getVariable('SITE_BASE_URL');
```

---

## The Golden Rules of Bootstrapping

Follow these rules to keep your code clean and your sanity intact.

### Rule #1: Only Bootstrap Once
In production code, `src/bootstrap.php` should be called exactly **one time**: inside `bin/staticforge.php`.

*   **NEVER** call it inside a Command.
*   **NEVER** call it inside a Feature.
*   **NEVER** call it inside a Helper class.

### Rule #2: Pass the Container
If your class needs the container, ask for it in the constructor. Don't try to build a new one.

**✅ Do This:**
```php
class MyCommand {
    public function __construct(Container $container) {
        $this->container = $container;
    }
}
```

**❌ NOT This:**
```php
class MyCommand {
    public function __construct() {
        // BAD! This creates a whole new universe!
        $this->container = require 'src/bootstrap.php';
    }
}
```

### Rule #3: Don't Create Loggers
The bootstrap file already made a logger for you. Use it.

**✅ Do This:**
```php
$logger = $container->get('logger');
```

**❌ NOT This:**
```php
// BAD! Now you have two loggers fighting over the file.
$logger = new Log(...);
```

---

## Troubleshooting

### "Service 'logger' already exists"
**Cause:** You (or some code) called bootstrap twice.
**Fix:** Find the extra `require 'src/bootstrap.php'` and delete it.

### "vendor/autoload.php not found"
**Cause:** You haven't installed dependencies.
**Fix:** Run `lando composer install`.

---

[← Back to Documentation](index.html)
---

# FILE: development/building-templates-with-ai.md

# AI-Assisted Design: Building Templates with a Co-Pilot

So, you want to build a custom template for StaticForge, but you don't want to write every single `<div>` and CSS class by hand.

Good news: StaticForge speaks the same language as your AI assistant. Because we use standard, boring technologies—PHP, Twig, and raw CSS—tools like GitHub Copilot and ChatGPT are surprisingly good at generating high-quality templates for us.

This guide isn't just a tutorial; it's a "cheat code" for building templates fast.

---

## The "Copycat" Strategy

The biggest mistake people make with AI is asking it to "make a website." That's too vague. The AI will hallucinate a bunch of complex frameworks you don't need.

The secret is the **Reference Implementation Strategy**.

Instead of teaching the AI how StaticForge works from scratch, you simply point it to our "Gold Standard" template (`staticforce`) and say:

> *"See this? Do it exactly like that, but make it look like [Your Vision]."*

---

## The Workflow: From Zero to Hero

Here is the exact workflow we use to build templates in minutes, not days.

### Step 1: The Briefing (Set the Context)

First, you need to orient the AI. Tell it what tools we are using so it doesn't try to give you React or Vue code.

**The Prompt:**
> "I am building a new template for a static site generator. We are using **Twig** for templating and **Raw CSS** (no frameworks). I want to create a template named 'my-new-template'."

### Step 2: The Blueprint (Grounding)

This is the most critical step. You must force the AI to look at the existing code structure.

**The Prompt:**
> "Before writing any code, I want you to examine the `templates/staticforce` directory. This is the reference implementation.
>
> Study how `base.html.twig` sets up the HTML shell. Note how `standard_page.html.twig` extends it. Look at how the assets are linked using `{{ site_base_url }}`.
>
> Use this structure as the blueprint for my new template."

### Step 3: The Vision (Style)

Now that the AI knows *how* to code it, tell it *what* to design. Be descriptive.

**The Prompt:**
> "I want the visual style to be **Cyberpunk Minimalist**.
> *   **Colors**: Dark background (#0a0a0a), Neon Green accents.
> *   **Layout**: Single column, very wide margins.
> *   **Typography**: Monospace fonts for everything.
> *   **Constraint**: Do NOT use Bootstrap or Tailwind. Write plain, efficient CSS using Grid and Flexbox."

---

## Step 4: The Construction (One Brick at a Time)

Don't ask for the whole site at once. The AI will get overwhelmed and give you trash. Build it piece by piece.

### Phase 1: The Skeleton (`base.html.twig`)

This is your master template. Get this right first.

> "Start by creating the `base.html.twig`. It needs:
> 1. The HTML5 boilerplate.
> 2. A sticky navigation bar.
> 3. A footer with copyright info using `{{ site_name }} {{ "now"|date("Y") }}`.
> 4. The `{% block body %}` where content will go.
> 5. Use `{{ styles|raw }}` in the head and `{{ scripts|raw }}` in the footer."

### Phase 2: The Homepage (`index.html.twig`)

> "Now create `index.html.twig`. It should extend `base.html.twig`. Give me a big Hero section with the `{{ site_tagline }}` and a 'Get Started' button."

### Phase 3: The Content (`standard_page.html.twig`)

> "Create `standard_page.html.twig` for my blog posts. It should extend `base.html.twig`. Keep it simple: just a title (`<h1>`) and the content block."

---

## Pro Tips for Smooth Sailing

*   **The "No Framework" Rule**: AI loves Tailwind and Bootstrap. If you don't want a massive dependency chain, explicitly tell it: *"Write raw CSS only. Do not use classes I haven't defined."*
*   **Asset Paths**: AI often forgets our specific variable names. If links break, remind it: *"Use `{{ site_base_url }}` for all links and images."*
*   **Magic Variables**: Remind it that `{{ styles }}` and `{{ scripts }}` are handled by the Asset Manager, so it doesn't need to manually link CSS files.
*   **Mobile First**: Remind it to make things responsive. *"Make sure the nav bar turns into a hamburger menu on mobile."*

By following this script, you act as the **Architect**, and the AI acts as the **Bricklayer**. You provide the vision and the blueprints; it does the heavy lifting.
---

# FILE: development/events.md

# The Nervous System: Events

If the Container is the "Toolbox" of StaticForge, the **Event Manager** is its nervous system. It sends signals throughout the application, telling different parts of the system when to wake up and do their job.

Instead of one giant script that does everything (read files -> parse markdown -> write html), StaticForge is a collection of small, independent features that listen for specific signals.

---

## How It Works (The Radio Station Analogy)

Think of the Event Manager as a radio station.

1.  **The Station (Event Manager)** broadcasts a signal: *"Attention everyone! We are about to start rendering files! (PRE_RENDER)"*
2.  **The Listeners (Features)** are tuned in.
    *   The **Markdown Feature** hears this and thinks, "Not my job yet."
    *   The **Reading Time Feature** hears this and says, "Ooh! That's me! I need to count the words before we render!"

This architecture allows you to add new functionality without ever touching the core code. You just add a new listener.

---

## The Event Lifecycle (The Broadcast Schedule)

Here is the sequence of signals that go out every time you build your site.

### 1. The Setup Phase
*   **CREATE**: "The application is alive." (Used to set up initial variables and feature defaults)

### 2. The Discovery Phase
*   **PRE_GLOB**: "We are about to look for files."
*   **POST_GLOB**: "We found all the files! Here is the list." (Used to build menus, sitemaps, and category lists)

### 3. The Processing Phase (The Loop)
*   **PRE_LOOP**: "Starting the file processing loop."
*   **PRE_RENDER**: "About to render **one specific file**." (Used to modify frontmatter or add computed data)
*   **RENDER**: "Rendering the file now." (Used to convert Markdown to HTML)
*   **POST_RENDER**: "File is rendered." (Used to minify HTML or add analytics tags)

### 4. The Cleanup Phase
*   **POST_LOOP**: "All files are done." (Used to generate RSS feeds or search indexes)
*   **DESTROY**: "Shutting down." (Used to close connections or write logs)

### 5. The Deployment Phase
*   **UPLOAD_CHECK_FILE**: "Checking a specific file before upload."
    *   **Triggered By**: `site:upload` command.
    *   **Purpose**: Allows external tools to control the upload process.
    *   **Data**: Contains `local_path`, `target_path`, `current_hash`, `remote_hash`, and `should_upload`.
    *   **Action**:
        *   Set `'handled' => true` if you uploaded it yourself (e.g., to S3).
        *   Set `'skip_upload' => true` to ignore the file entirely.
        *   Set `'should_upload' => true` to force a standard SFTP upload even if hashes match.

---

## Deep Dive: Common Events

### POST_GLOB (The Planner)
This is where you see the "Big Picture." You have a list of every file in the system, but nothing has been rendered yet.
*   **Use it for:** Building menus, creating tag clouds, or generating "Next/Previous" links.

### PRE_RENDER (The Editor)
This happens right before a single file is turned into HTML. You have access to its raw content and metadata.
*   **Use it for:** Calculating reading time, fixing typos, or adding default images.

### POST_RENDER (The Polisher)
The HTML is generated but not saved to disk yet.
*   **Use it for:** Minifying CSS/JS, injecting Google Analytics scripts, or adding copyright notices.

---

## Creating Your Own Listener

To make your feature listen for an event, you just need to register it in your Feature class.

```php
class MyFeature extends BaseFeature
{
    // 1. Register the listener
    protected array $eventListeners = [
        'PRE_RENDER' => ['method' => 'addReadingTime', 'priority' => 500]
    ];

    // 2. Define the method
    public function addReadingTime(Container $container, array $data): array
    {
        // Get the content
        $content = $data['content'];

        // Calculate reading time
        $wordCount = str_word_count(strip_tags($content));
        $minutes = ceil($wordCount / 200);

        // Add it to the metadata
        $data['metadata']['reading_time'] = $minutes . ' min read';

        // IMPORTANT: Always return the data!
        return $data;
    }
}
```

---

## The Priority System (Who Goes First?)

Sometimes multiple features listen to the same event. Who goes first?

We use a **Priority Number** (0-999).
*   **Lower Numbers (0-100)**: Run First. (e.g., "System Critical" stuff)
*   **Higher Numbers (900-999)**: Run Last. (e.g., "Cleanup" stuff)
*   **Default**: 500.

**Example:**
If you want to *modify* the menu before it's used, you need to run **after** the MenuBuilder.
*   MenuBuilder runs at `POST_GLOB` priority **100**.
*   You should run at `POST_GLOB` priority **200**.

---

## Feature-Specific Events

Some features are so polite they even let you interrupt *them*.

### COLLECT_MENU_ITEMS
**Fired By:** MenuBuilder
**When:** During `POST_GLOB`
**Why:** You want to add a link to the menu that doesn't exist as a file (e.g., an external link to Twitter).

### MARKDOWN_CONVERTED
**Fired By:** MarkdownRenderer
**When:** During `RENDER`
**Why:** You want to modify the HTML *after* Markdown has done its job but *before* it gets wrapped in a template. (e.g., Adding `class="table"` to all tables).

### RSS_ITEM_BUILDING
**Fired By:** RSSFeed
**When:** During `POST_LOOP`
**Why:** You want to add custom tags to your RSS feed (e.g., Podcast enclosures).

---

## Event Data Flow (The Bucket Brigade)

When an event fires, it passes a `$data` array to the first listener. That listener modifies it and passes it to the next one.

```php
// Listener 1 (Priority 100)
$data['title'] = "Hello";
return $data;

// Listener 2 (Priority 200)
$data['title'] .= " World";
return $data;

// Result: "Hello World"
```

**Critical Rule:** If you break the chain (by not returning `$data`), the next listener gets nothing, and the system crashes. **Always return the data.**

---

## Best Practices

1.  **Always Return Data**: The event chain is like a bucket brigade. If you don't pass the bucket (return `$data`), the fire doesn't get put out (the app crashes).
2.  **Don't Be Greedy**: Only listen to the events you actually need.
3.  **Check for Existence**: Don't assume `metadata['title']` exists. Always check `isset()` or use the null coalescing operator (`??`).

---

[← Back to Documentation](index.html)
---

# FILE: development/extending-seo-audit.md

# Extending the SEO Audit (For Type A Personalities)

The `audit:seo` command is great. It catches the basics like missing titles and overly verbose descriptions. But if you have specific requirements—like checking for Open Graph tags, verifying Twitter Cards, or ensuring specialized Schema.org data—you need more power.

StaticForge has you covered with the `SEO_AUDIT_PAGE` event.

## The Hook: `SEO_AUDIT_PAGE`

This event fires for **every single HTML file** during an audit. It hands you the DOM and asks, "Do you have any complaints?"

### The Data payload

You receive an array with three keys:

| Key | Type | Description |
| :--- | :--- | :--- |
| `crawler` | `Symfony\Component\DomCrawler\Crawler` | The DOM crawler instance. This is your scalpel. Use it to inspect the HTML. |
| `filename` | `string` | The path of the file you are looking at (e.g., `blog/my-post.html`). |
| `issues` | `array` | The list of problems found so far. Your job is to add to this list. |

---

## How to Implement a Custom Check

Let's say you want to enforce a rule that every page must have a strict Content Security Policy (CSP) meta tag.

### Step 1: Register the Listener

In your Feature class, tell the EventManager you want to help with the audit.

```php
// src/Features/SecurityAudit/Feature.php

public function register(EventManager $eventManager, Container $container): void
{
    $eventManager->registerListener('SEO_AUDIT_PAGE', [$this, 'auditSecurityHeaders']);
}
```

### Step 2: Write the Logic

Now, implement the method. It receives the data, checks the DOM, and reports any failures.

```php
public function auditSecurityHeaders(Container $container, array $params): array
{
    // Unpack the tools
    $crawler = $params['crawler'];
    $filename = $params['filename'];
    $issues = $params['issues'];

    // Check for the meta tag
    $csp = $crawler->filter('meta[http-equiv="Content-Security-Policy"]');

    if ($csp->count() === 0) {
        // REPORT THE CRIME!
        $issues[] = [
            'file' => $filename,
            'type' => 'error', // Use 'error' to fail the build, 'warning' to just yell.
            'message' => 'Missing Content-Security-Policy meta tag.'
        ];
    }

    // Pack it back up and return it
    $params['issues'] = $issues;
    return $params;
}
```

---

## The Issue Structure

When you report an issue, follow this format strictly:

*   **`file`**: The filename (passed in params).
*   **`type`**:
    *   `'error'`: Critical failure. If the build server sees this, it should fail.
    *   `'warning'`: Something to fix, but not a showstopper.
*   **`message`**: A concise, helpful description of what went wrong.

> **Pro Tip:** Don't be annoying with your warnings. If you flag every single page for a minor issue, users will just ignore all your warnings. Be precise.
---

# FILE: development/features.md

# The Plugin System: Features

If StaticForge is the operating system, **Features** are the apps.

Almost everything StaticForge does—converting Markdown, building menus, generating RSS feeds—is actually just a Feature. The core system is tiny; it just loads features and fires events.

This means you can change *anything*. Don't like how we handle Markdown? Disable our renderer and write your own. Want to add a "Reading Time" calculator? Just write a feature.

---

## Anatomy of a Feature

A Feature is just a PHP class that extends `BaseFeature`. It has one main job: to tell the system which events it cares about.

### The Basic Structure

```php
namespace App\Features\MyCoolFeature;

use EICC\StaticForge\Core\BaseFeature;
use EICC\Utils\Container;

class Feature extends BaseFeature
{
    // Define your listeners here in the $eventListeners array.
    // The BaseFeature class will automatically handle registration.
    protected array $eventListeners = [
        // Event Name => [Method Name, Priority]
        'PRE_RENDER' => ['doSomethingCool', 500],
    ];

    public function doSomethingCool(Container $container, array $data): array
    {
        // Do the cool thing
        return $data;
    }
}
```

---

## Creating a Feature

### The Easy Way (CLI)

Don't write boilerplate. Let the robot do it.

```bash
lando php vendor/bin/staticforge.php feature:create MyNewFeature
```

Boom. You have a new feature structure in `src/Features/MyNewFeature/`. Go fill in the blanks.

### The Manual Way

1.  Create a folder: `src/Features/MyNewFeature`.
2.  Create a `Feature.php` file inside it.
3.  Make sure it extends `BaseFeature`.
4.  Make sure your `composer.json` autoloads it (usually mapped to `App\`).

---

## Hooking into Events

The `register` method is where you subscribe to the "Radio Station" (see [Events](events.html)).

```php
public function register(EventManager $eventManager, Container $container): void
{
    // Run early (Priority 100)
    $eventManager->on('POST_GLOB', [$this, 'scanFiles'], 100);

    // Run late (Priority 900)
    $eventManager->on('POST_RENDER', [$this, 'cleanup'], 900);
}
```

### Common Use Cases

*   **Need to build a list of files?** Listen to `POST_GLOB`.
*   **Need to change content?** Listen to `PRE_RENDER`.
*   **Need to add analytics?** Listen to `POST_RENDER`.
*   **Need to generate a new file (like sitemap.xml)?** Listen to `POST_LOOP`.

---

## Configuration

You don't want to hardcode settings in your PHP files. Instead, put them in `siteconfig.yaml`.

**siteconfig.yaml:**
```yaml
features:
  MyCoolFeature:
    enabled: true
    show_author: true
    prefix: "Written by: "
```

**In your Feature:**
```php
public function doSomethingCool(Container $container, array $data): array
{
    // Get the config
    $config = $this->getConfig();

    if ($config['show_author']) {
        $prefix = $config['prefix'] ?? 'By: ';
        // ...
    }

    return $data;
}
```

---

## Library vs. User Features

StaticForge comes with a set of "Built-in" features (like MarkdownRenderer). These live in the `vendor/` folder.

Your custom features live in `src/Features/`.

### Overriding Core Features

Here is the cool part: **You can replace built-in features.**

If you create a feature with the **exact same name** as a built-in feature (e.g., `MarkdownRenderer`), the system will load yours instead of the built-in one.

This allows you to completely swap out core functionality without hacking the vendor folder.

---

## Best Practices

1.  **Keep it Focused**: A feature should do one thing well. Don't make a "GeneralUtils" feature.
2.  **Use Services**: If your feature has complex logic, move it into a separate Service class (`MyService.php`) and inject it. Don't put 500 lines of code in the `Feature.php` file.
3.  **Always Return Data**: Remember the Bucket Brigade. Your event listeners must return the `$data` array.

---

[← Back to Documentation](index.html)
---

# FILE: development/index.md

# Developer Guide

So, you want to see how the sausage is made? You've come to the right place.

This isn't the "How do I write a blog post?" section. This is the **"How do I bend StaticForge to my will?"** section. Here, we pop the hood, void the warranty, and show you exactly how this machine works.

## The Blueprint

If you want to hack on the core or build your own plugins (Features), start here.

*   **[Architecture](architecture.html)**
    The big picture. How does a request become a static HTML file? It's not magic; it's a pipeline.

*   **[The Technology Stack](tech-stack.html)**
    The giants whose shoulders we stand on. PHP 8.4, Symfony Console, Twig, and more.

*   **[Bootstrap & Initialization](bootstrap.html)**
    The "Ignition Sequence." What actually happens when you type `bin/staticforge`?

*   **[Events](events.html)**
    The nervous system of StaticForge. If you want to change behavior, you need to know which synapse to zap.

## Extending the System

*   **[Feature Development](features.html)**
    Don't fork the core. Build a Feature. It's the plugin system that powers everything.

*   **[Asset Manager](asset-manager.html)**
    The "Traffic Cop" for your CSS and JS. Stop worrying about dependency order.

## The Frontend

*   **[Template Development](templates.html)**
    How to make it pretty. Twig, inheritance, and the "Master Slide" concept.

*   **[Building Templates with AI](building-templates-with-ai.html)**
    Because writing HTML by hand is *so* 2010. Let the robots do the heavy lifting.
---

# FILE: development/tech-stack.md

# Technology Stack

StaticForge is built on the shoulders of giants. We believe in using the best tools for the job, which is why we've chosen a robust stack of open-source technologies to power our generator.

Here is a look under the hood at the libraries and tools that make StaticForge tick.

---

## The Foundation

### [PHP](https://www.php.net/)
**Version:** 8.4+

At its core, StaticForge is a PHP application. We chose PHP for its ubiquity, ease of use, and massive ecosystem. But this isn't your grandfather's PHP. We require PHP 8.4 or higher to leverage modern features like typed properties, enums, and readonly classes. This ensures our codebase remains clean, strict, and maintainable.

---

## Powering the Core

These are the libraries that do the heavy lifting every time you run a command.

### [Symfony Console](https://symfony.com/doc/current/components/console.html)
**The CLI Experience**
When you run `lando php vendor/bin/staticforge.php`, you're talking to Symfony Console. It handles the commands, the colorful output, and the interactive prompts. It's the industry standard for PHP CLIs for a reason.

### [Twig](https://twig.symfony.com/)
**The Template Engine**
We didn't want to invent our own templating language, so we went with the best: Twig. It's fast, secure, and incredibly flexible. It allows you to build complex layouts with inheritance, macros, and filters without writing a line of PHP.

### [League CommonMark](https://commonmark.thephpleague.com/)
**The Markdown Parser**
Your content lives in Markdown, and League CommonMark turns it into HTML. It's fully compliant with the CommonMark spec and highly extensible, which allows us to support things like frontmatter and custom shortcodes.

### [Symfony YAML](https://symfony.com/doc/current/components/yaml.html)
**The Configuration Handler**
Whether it's your `siteconfig.yaml` or the frontmatter in your posts, Symfony YAML parses it all. It ensures that your configuration is human-readable and easy to manage.

### [PHP Dotenv](https://github.com/vlucas/phpdotenv)
**The Environment Manager**
Security matters. PHP Dotenv loads your environment variables from `.env`, keeping your sensitive data (like API keys and database credentials) out of your code and safe from prying eyes.

### [phpseclib](https://phpseclib.com/)
**The Deployment Engine**
When you run `site:upload`, phpseclib handles the secure connection. It provides pure PHP implementations of SSH2 and SFTP, meaning you can deploy your site securely without needing external system binaries or complex server configurations.

### [dindent](https://github.com/gajus/dindent)
**The HTML Formatter**
We believe generated code should be beautiful too. Dindent takes the raw HTML output and formats it with proper indentation, making it clean and readable for debugging.

### EICC Utils
**The Utility Belt**
A collection of battle-tested utility classes used across our projects. It handles logging, container management, and other low-level tasks so we don't have to reinvent the wheel.

---

## Client-Side Magic

We try to keep client-side JavaScript to a minimum, but sometimes you need a little sparkle.

### [MiniSearch](https://lucaong.github.io/minisearch/)
**The Search Engine**
How do you search a static site without a database? With MiniSearch. It's a tiny, powerful full-text search engine that runs entirely in the user's browser. It powers our Search feature, giving your users instant results without a round-trip to a server.

---

## Built for Quality

These are the tools we use internally to develop StaticForge. They are installed as development dependencies (`--dev`) and ensure that the project remains stable, bug-free, and maintainable.

### [PHPUnit](https://phpunit.de/)
**The Testing Framework**
We don't just hope our code works; we prove it. PHPUnit is the industry standard for testing PHP applications. We use it for both unit testing (testing individual classes in isolation) and integration testing (ensuring different parts of the system work together).

### [PHPStan](https://phpstan.org/)
**The Static Analyzer**
PHPStan reads our code and finds bugs before we even run it. It enforces strict typing and catches potential issues like accessing undefined methods or passing wrong argument types. We run it at a high level to ensure our codebase is solid.

### [PHP_CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer)
**The Style Enforcer**
Code is read much more often than it is written. We use PHP_CodeSniffer to enforce the PSR-12 coding standard. This ensures that whether you're reading code written by Cal or a contributor, it all looks and feels consistent.

### [vfsStream](https://github.com/bovigo/vfsStream)
**The Virtual File System**
StaticForge does a lot of file manipulation. Testing this on a real hard drive is slow and messy. vfsStream allows us to mock the file system in memory during our tests. This makes our test suite fast, reliable, and clean—no leftover files cluttering up your drive.

### Dead Code Detector
**The Cleanup Crew**
As projects grow, it's easy to leave behind unused functions or classes. We use ShipMonk's Dead Code Detector to scan our codebase and identify code that is no longer being used, keeping the project lean and efficient.
---

# FILE: development/templates.md

# The Face of the Operation: Templates

If Features are the brains of StaticForge, **Templates** are the face. They determine what your users actually see.

We use **Twig**, a powerful templating engine for PHP. If you know HTML, you already know 90% of Twig. The other 10% is just "filling in the blanks."

---

## The "Master Slide" Concept (Inheritance)

The most powerful feature of Twig is **Inheritance**.

Think of it like a PowerPoint "Master Slide." You define the layout (Header, Footer, Sidebar) in *one place*, and every other page just fills in the content area.

### 1. The Master Layout (`base.html.twig`)

This file contains the HTML skeleton that is shared by every page on your site.

```twig
{# templates/mytemplate/base.html.twig #}
<!DOCTYPE html>
<html>
<head>
    <title>{% block title %}My Site{% endblock %}</title>
    <link rel="stylesheet" href="{{ site_base_url }}/assets/css/style.css">
</head>
<body>
    <nav>
        <!-- Menu goes here -->
    </nav>

    <main>
        {# This is the "Slot" where child templates will inject content #}
        {% block body %}
        {% endblock %}
    </main>

    <footer>
        &copy; {{ "now"|date("Y") }} {{ site_name }}
    </footer>
</body>
</html>
```

### 2. The Child Page (`standard_page.html.twig`)

This file doesn't need to rewrite the `<html>` or `<body>` tags. It just says, "I want to use the Master Layout, and here is my content."

```twig
{# templates/mytemplate/standard_page.html.twig #}
{% extends 'base.html.twig' %}

{% block title %}{{ title }} - {{ site_name }}{% endblock %}

{% block body %}
    <h1>{{ title }}</h1>
    <div class="content">
        {{ content|raw }}
    </div>
{% endblock %}
```

---

## Variables: Filling in the Blanks

StaticForge passes a lot of data to your templates. You access it using double curly braces: `{{ variable_name }}`.

### The Essentials

*   `{{ content }}`: The HTML content of your Markdown file.
*   `{{ title }}`: The title from your Frontmatter.
*   `{{ site_base_url }}`: The URL of your site (e.g., `https://mysite.com`). **Always use this for assets!**
*   `{{ site_name }}`: The name of your site (from `siteconfig.yaml`).

### The "Features" Array

Remember how features can expose data? It all lives in the `features` array.

*   `{{ features.MenuBuilder.html.1 }}`: The main menu HTML.
*   `{{ features.Tags.cloud }}`: The tag cloud HTML.

---

## Control Structures: Logic in HTML

Sometimes you need to show things only if they exist, or loop through a list.

### The `if` Statement

```twig
{% if image %}
    <img src="{{ site_base_url }}/assets/images/{{ image }}" alt="{{ title }}">
{% endif %}
```

### The `for` Loop

```twig
<ul>
{% for tag in tags %}
    <li><a href="{{ site_base_url }}/tags/{{ tag }}.html">{{ tag }}</a></li>
{% endfor %}
</ul>
```

---

## Asset Management (CSS & JS)

StaticForge includes an `AssetManager` that allows features (like Photo Galleries) to automatically inject the CSS and JavaScript they need.

To support this, your `base.html.twig` should include the following variables:

*   `{{ styles }}`: Outputs `<link>` tags for all registered stylesheets. Place this in `<head>`.
*   `{{ head_scripts }}`: Outputs `<script>` tags that must run in the head. Place this in `<head>`.
*   `{{ scripts }}`: Outputs `<script>` tags for the footer. Place this before `</body>`.

**Example `base.html.twig`:**

```twig
<head>
    ...
    {# Your main styles #}
    <link rel="stylesheet" href="{{ site_base_url }}/assets/css/style.css">

    {# Feature styles (e.g. Gallery CSS) #}
    {% if styles %}{{ styles|raw }}{% endif %}
    {% if head_scripts %}{{ head_scripts|raw }}{% endif %}
</head>
<body>
    ...
    {# Feature scripts (e.g. jQuery, Gallery JS) #}
    {% if scripts %}{{ scripts|raw }}{% endif %}
</body>
```

### Automatic Injection

If you forget to include these variables, StaticForge attempts to **automatically inject** them for you:
*   Styles and Head Scripts are injected before the closing `</head>` tag.
*   Footer Scripts are injected before the closing `</body>` tag.

*Note: While automatic injection works, it is recommended to explicitly place the variables in your template for better control over load order.*

---

## The "Asset Trap" (Critical Warning)

This is the #1 mistake people make.

Because StaticForge generates static HTML files that live in different folders (e.g., `/index.html` vs `/blog/my-post.html`), **relative paths do not work**.

❌ **WRONG:**
```html
<link rel="stylesheet" href="css/style.css">
```
*   Works on homepage.
*   Breaks on `/blog/post.html` (looks for `/blog/css/style.css`).

✅ **RIGHT:**
```twig
<link rel="stylesheet" href="{{ site_base_url }}/assets/css/style.css">
```
*   Always points to the root.

---

## Templates

StaticForge uses the term **Templates** to refer to the collection of Twig files that define your site's look and feel.

### Built-in Templates

We include a few templates to get you started. You can find them in the `templates/` directory.

*   **`sample`**: A clean, modern default.
*   **`staticforce`**: The documentation template you are reading right now.

To switch templates, change the `template` setting in your `siteconfig.yaml` file (or `TEMPLATE` in `.env`).

```yaml
site:
  template: "staticforce"
```

### Installing Templates

You can find more StaticForge templates on Packagist. Installing them is as easy as running a composer command:

```bash
composer require vendor/template-name
```

**How it works:**
1.  Composer installs the package to your `vendor/` directory.
2.  The **StaticForge Installer** automatically copies the template files from `vendor/` to your `templates/` directory (e.g., `templates/template-name/`).
3.  **Safety First**: If a directory with that name already exists in `templates/`, the installer will **NOT** overwrite it.

**Why copy?**
We copy the files so you can customize them! Once a template is in your `templates/` directory, it is yours. You can edit the Twig files, CSS, and JS to your heart's content.

**Uninstalling:**
If you remove the package (`composer remove vendor/template-name`), the files in `vendor/` are removed, but your copy in `templates/` **remains**. This ensures you never lose your customizations.

### Developing & Distributing Templates

Want to share your design with the world? Creating a distributable StaticForge template is simple.

#### 1. Package Structure
A standard template package looks like this:

```text
my-template/
├── composer.json
└── templates/          # Contains your template files
    ├── assets/
    ├── base.html.twig
    ├── index.html.twig
    └── ...
```

#### 2. `composer.json` Configuration
To tell StaticForge this is a template, you must set the `type` to `staticforge-template`.

```json
{
    "name": "my-vendor/my-template",
    "description": "A beautiful template for StaticForge",
    "type": "staticforge-template",
    "license": "MIT",
    "require": {
        "eicc/staticforge-installer": "^1.0"
    }
}
```

**Advanced Configuration:**
If you need to store your templates in a different directory (not `templates/`), you can specify it in `extra`:

```json
{
    ...
    "extra": {
        "staticforge": {
            "template": {
                "name": "custom-template-name",  // Directory name in user's templates/ folder
                "source": "src/template-files"   // Source directory in your package
            }
        }
    }
}
```

#### 3. Publish
Submit your package to [Packagist.org](https://packagist.org). Use the keyword `staticforge-template` to help users find it!

---

[← Back to Documentation](index.html)
---

# FILE: development/testing.md

# Testing Your Code

If it isn't tested, it doesn't exist.

StaticForge relies heavily on automated testing to ensure stability. When you build a new Feature, you should write tests to prove it works.

## Integration Tests

The easiest way to test a Feature is with an Integration Test. This spins up the full StaticForge container, allowing you to test your feature in a real environment.

### Basic Test Structure

Create a test file in `tests/Integration/Features/MyFeature/MyFeatureTest.php`.

```php
<?php

namespace EICC\StaticForge\Tests\Integration\Features\MyFeature;

use EICC\StaticForge\Tests\Integration\IntegrationTestCase;
use EICC\StaticForge\Core\FeatureManager;

class MyFeatureTest extends IntegrationTestCase
{
    public function testFeatureIsLoaded(): void
    {
        // 1. Boot the application
        // This loads .env, siteconfig, and all features.
        $container = $this->createContainer(__DIR__ . '/../../../../.env');

        // 2. Get the Feature Manager
        $featureManager = $container->get(FeatureManager::class);

        // 3. Assert your feature is running
        $this->assertTrue($featureManager->hasFeature('MyFeature'));
    }

    public function testFeatureDoesThing(): void
    {
        // Setup container
        $container = $this->createContainer(__DIR__ . '/../../../../.env');

        // Define some mock data
        $data = ['content' => 'Hello World'];

        // ... Trigger your event or call your service directly ...

        // Assert the result
        $this->assertArrayHasKey('modified_content', $data);
    }
}
```

### Running Your Test

You must use Lando to run tests.

```bash
# Run all tests (Good luck!)
lando phpunit

# Run just YOUR test (Much faster)
lando phpunit tests/Integration/Features/MyFeature/MyFeatureTest.php
```

## Unit Tests

If you have complex logic (like a math calculation or string parser) that doesn't need the whole system, use a standard Unit Test.

Place these in `tests/Unit/Features/MyFeature/`.

```php
<?php

namespace EICC\StaticForge\Tests\Unit\Features\MyFeature;

use PHPUnit\Framework\TestCase;
use App\Features\MyFeature\Services\Calculator;

class CalculatorTest extends TestCase
{
    public function testItAddsNumbers(): void
    {
        $start = 1;
        $end = 1;

        // No container, no bloat. Just pure logic.
        $result = $start + $end;

        $this->assertEquals(2, $result);
    }
}
```
---

# FILE: docs-examples/index.md

Welcome to the showroom. While the documentation tells you how the engine works, sometimes you just want to see the car drive.

We've included a collection of example files in the `content/examples/` directory of your project. These aren't just placeholders; they are fully functional pages generated by StaticForge during the build process. They demonstrate different content types, layouts, and features in action.

## Live Demos

Click through to see how StaticForge renders different types of content:

*   [**Blog Post**](/examples/tutorials/blog-post.html)
    A classic blog layout complete with tags, author metadata, and reading time calculations.
*   [**Documentation Page**](/examples/documentation/documentation-page.html)
    A technical guide layout featuring syntax-highlighted code blocks and alert boxes.
*   [**Portfolio Item**](/examples/portfolio/portfolio-item.html)
    A visual-heavy layout designed to showcase projects or case studies.
*   [**RSS Enabled Article**](/examples/tutorials/rss-enabled-article.html)
    An example of content that automatically gets picked up by the RSS feed generator.
*   [**Shortcodes Demo**](/examples/docs-examples/shortcodes.html)
    See our built-in shortcodes in action—embedding YouTube videos, Tweets, and GitHub Gists with ease.
*   [**Simple Page**](/examples/simple-page.html)
    The bare necessities. A minimal page showing that you don't need complex metadata to get a page online.
*   [**Landing Page**](/examples/landing-page.html)
    A raw HTML page that bypasses the standard Markdown processing, perfect for custom landing pages.

## Steal These Blueprints

The best way to learn is to tinker. These examples are designed to be copied and modified. Think of them as blueprints we've left on the workbench for you.

Want to start a blog? Don't start from scratch. Grab the blog post example and make it your own:

```bash
# Copy the blog post blueprint to your blog directory
cp content/examples/blog-post.md content/blog/my-first-post.md
```

Once you've copied a file, open it up and play with the FrontMatter. Change the `template`, tweak the `category`, and see how the StaticForge engine responds on your next build.
---

# FILE: examples/blog-post.md

# Getting Started with StaticForge

Welcome to this comprehensive tutorial on building static sites with **StaticForge**, a powerful PHP-based static site generator.

## What You'll Learn

In this tutorial, you'll learn:

- How to install and configure StaticForge
- Creating content with Markdown
- Using frontmatter metadata
- Organizing content with categories
- Adding tags to your posts
- Customizing templates

## Prerequisites

Before you begin, make sure you have:

- PHP 8.4 or higher installed
- Composer for dependency management
- Basic knowledge of Markdown
- A text editor of your choice

## Installation

First, install StaticForge using Composer:

```bash
composer create-project staticforge/staticforge my-site
cd my-site
```

## Configuration

Copy the example environment file:

```bash
cp .env.example .env
```

Edit `.env` to configure your site:

```bash
CONTENT_PATH=content/
OUTPUT_PATH=public/
TEMPLATE_NAME=staticforce
```

## Creating Content

Create your first content file using the CLI:

```bash
php vendor/bin/staticforge.php make:content "Hello World"
```

This creates `content/hello-world.md` with the necessary frontmatter. Open it and add your content:

```markdown
---
title: "Hello World"
date: "2026-02-12"
---

# Hello World!

This is my first post with StaticForge.
```

## Generating Your Site

Run the render command:

```bash
php bin/staticforge.php site:render
```

Your site is now in the `public/` directory!

## Next Steps

- Explore the [Configuration Guide](/guide/configuration.html)
- Learn about [custom features](/development/features.html)
- Check out more [examples](/examples.html)

Happy building! 🚀
---

# FILE: examples/calendar-demo.md

# Calendar Feature Demo

This page demonstrates the calendar functionality.

[[calendar name="example_events"]]
---

# FILE: examples/documentation-page.md

# API Authentication

This is a dummy guide
This guide explains how to authenticate requests to our API.

## Overview

All API requests require authentication using an API key. The key must be included in the `Authorization` header of each request.

## Getting an API Key

1. Log in to your account dashboard
2. Navigate to **Settings** → **API Keys**
3. Click **Generate New Key**
4. Copy your key and store it securely

> **Warning**: Never share your API key or commit it to version control. Treat it like a password.

## Making Authenticated Requests

### Using cURL

```bash
curl -H "Authorization: Bearer YOUR_API_KEY" \
     https://api.example.com/v1/users
```

### Using PHP

```php
<?php

$apiKey = 'YOUR_API_KEY';

$ch = curl_init('https://api.example.com/v1/users');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $apiKey,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$data = json_decode($response, true);

curl_close($ch);
```

### Using JavaScript

```javascript
const apiKey = 'YOUR_API_KEY';

fetch('https://api.example.com/v1/users', {
  headers: {
    'Authorization': `Bearer ${apiKey}`,
    'Content-Type': 'application/json'
  }
})
.then(response => response.json())
.then(data => console.log(data))
.catch(error => console.error('Error:', error));
```

## Response Codes

The API uses standard HTTP status codes:

| Code | Description |
|------|-------------|
| `200` | Success |
| `201` | Created |
| `400` | Bad Request |
| `401` | Unauthorized (invalid API key) |
| `403` | Forbidden (insufficient permissions) |
| `404` | Not Found |
| `429` | Too Many Requests (rate limit exceeded) |
| `500` | Internal Server Error |

## Rate Limiting

API requests are rate-limited to:

- **Free tier**: 100 requests per hour
- **Pro tier**: 1,000 requests per hour
- **Enterprise**: Custom limits

Rate limit headers are included in all responses:

```
X-RateLimit-Limit: 1000
X-RateLimit-Remaining: 999
X-RateLimit-Reset: 1640995200
```

## Error Responses

Errors are returned in JSON format:

```json
{
  "error": {
    "code": "unauthorized",
    "message": "Invalid API key",
    "details": "The API key provided is not valid or has been revoked"
  }
}
```

## Best Practices

1. **Store keys securely**: Use environment variables, never hardcode
2. **Rotate keys regularly**: Generate new keys periodically
3. **Use HTTPS**: Always use HTTPS for API requests
4. **Handle errors**: Implement proper error handling in your code
5. **Respect rate limits**: Implement backoff strategies

## Next Steps

- API Reference
- [Code Examples](/examples.html)
- Webhooks Guide

## Support

Need help? Contact our support team:

- **Email**: api-support@example.com
- **Docs**: Full documentation
---

# FILE: examples/portfolio-item.md

# E-Commerce Platform Redesign

A complete modernization of a legacy e-commerce platform, resulting in 40% faster page loads and 25% increase in conversions.

## Project Overview

**Client**: RetailCo Inc.
**Duration**: 6 months
**Team Size**: 5 developers
**Budget**: $150,000

## Challenge

RetailCo's existing e-commerce platform was built in 2010 and suffered from:

- Slow page load times (5-8 seconds)
- Poor mobile experience
- Outdated checkout flow
- Difficult to maintain codebase
- No integration with modern payment gateways

The company was losing customers to competitors with better online experiences.

## Solution

We designed and implemented a complete platform redesign with:

### Technical Stack

- **Backend**: PHP 8.4 with Laravel framework
- **Frontend**: Vue.js 3 with Tailwind CSS
- **Database**: MySQL 8.0 with Redis caching
- **Hosting**: AWS with CloudFront CDN
- **Payments**: Stripe and PayPal integration

### Key Features

1. **Performance Optimization**
   - Server-side rendering for critical pages
   - Redis caching layer
   - CDN for static assets
   - Optimized database queries
   - Image lazy loading and WebP format

2. **Mobile-First Design**
   - Responsive design across all breakpoints
   - Touch-optimized interface
   - Progressive Web App capabilities
   - Offline browsing support

3. **Improved Checkout**
   - One-page checkout process
   - Guest checkout option
   - Multiple payment methods
   - Address autocomplete
   - Real-time shipping calculations

4. **Admin Dashboard**
   - Real-time analytics
   - Inventory management
   - Order processing workflow
   - Customer insights
   - Marketing automation

## Results

### Performance Metrics

- **Page Load Time**: Reduced from 5-8s to 1.2s (85% improvement)
- **Mobile Performance**: Google PageSpeed score increased from 42 to 94
- **Server Response**: Reduced from 800ms to 120ms

### Business Metrics

- **Conversion Rate**: Increased by 25%
- **Cart Abandonment**: Decreased from 78% to 62%
- **Mobile Sales**: Increased by 45%
- **Customer Satisfaction**: NPS score improved from 32 to 68

### Cost Savings

- **Hosting Costs**: Reduced by 30% through optimization
- **Development Time**: New features deploy 3x faster
- **Maintenance**: Bug fixes reduced by 60%

## Technologies Used

```
Backend:
- PHP 8.4
- Laravel 11
- MySQL 8.0
- Redis 7

Frontend:
- Vue.js 3
- Tailwind CSS
- Vite
- TypeScript

Infrastructure:
- AWS EC2
- AWS RDS
- CloudFront CDN
- GitHub Actions CI/CD
```

## Project Highlights

### Architecture

We implemented a modern layered architecture:

```
┌─────────────────────────────────┐
│     CDN (CloudFront)           │
└─────────────────────────────────┘
            ↓
┌─────────────────────────────────┐
│     Load Balancer              │
└─────────────────────────────────┘
            ↓
┌─────────────────────────────────┐
│     Web Servers (EC2)          │
│     - PHP-FPM                  │
│     - Nginx                    │
└─────────────────────────────────┘
            ↓
┌────────────────┬────────────────┐
│  Database (RDS)│  Cache (Redis) │
└────────────────┴────────────────┘
```

### Code Quality

- **Test Coverage**: 87%
- **PSR-12**: Coding standards compliance
- **Static Analysis**: PHPStan level 8
- **Security**: Regular audits and dependency updates

### Timeline

**Month 1-2**: Discovery and Planning
- Requirements gathering
- Technical architecture design
- Wireframes and prototypes

**Month 3-4**: Development
- Backend API development
- Frontend implementation
- Payment gateway integration

**Month 5**: Testing and Optimization
- Load testing
- Security audits
- Performance optimization
- User acceptance testing

**Month 6**: Launch and Handoff
- Staged rollout
- Team training
- Documentation
- Post-launch support

## Client Testimonial

> "The new platform has transformed our business. We've seen significant improvements in sales, customer satisfaction, and our team's ability to manage the site. The development team was professional, responsive, and delivered beyond our expectations."
>
> **— Sarah Johnson, CTO, RetailCo Inc.**

## Lessons Learned

1. **Performance is Critical**: Every 100ms of load time affects conversions
2. **Mobile-First**: Over 60% of traffic came from mobile devices
3. **Iterative Testing**: A/B testing helped optimize the checkout flow
4. **Documentation**: Comprehensive docs saved time in handoff and maintenance
5. **Security**: Regular audits caught issues before they became problems

## Links

- **Live Site**: retailco.example.com
- **Case Study**: Download PDF
- **GitHub**: Private repository (NDA protected)

## Related Projects

- SaaS Dashboard Redesign
- Mobile App Development
- API Integration Platform

---

**Interested in a similar project?** [Contact us](/contact-us.html) to discuss your needs.
---

# FILE: examples/rss-enabled-article.md

# Building a REST API with PHP

In this comprehensive tutorial, you'll learn how to build a RESTful API from scratch using pure PHP.

## What You'll Learn

- HTTP methods (GET, POST, PUT, DELETE)
- Request and response handling
- JSON encoding and decoding
- Error handling and status codes
- Authentication basics

## Prerequisites

Before starting, you should have:

- Basic PHP knowledge
- Understanding of HTTP
- A local development environment

## Getting Started

Let's start by creating a simple endpoint that returns JSON...

## Benefits of Including RSS

This article will automatically be included in the **Tutorials** RSS feed because it has `category = "Tutorials"` in its frontmatter.

The RSS feed will use:
- **Title**: "Building a REST API with PHP"
- **Description**: The custom description from frontmatter
- **Publication Date**: 2024-01-15
- **Author**: dev@example.com
- **Link**: Full URL to this article

Users can subscribe to `/tutorials/rss.xml` to get notified when new tutorial articles are published!
---

# FILE: examples/shortcodes.md

This page demonstrates the new Shortcode system.

## Youtube Shortcode

Here is a video:

[[youtube id="dQw4w9WgXcQ" title="Rick Roll"]]

## Alert Shortcode

[[alert type="info"]]
This is an **info** alert with markdown support.
[[/alert]]

[[alert type="warning"]]
This is a **warning** alert!
[[/alert]]

[[alert type="error"]]
This is an **error** alert.
[[/alert]]

[[alert type="success"]]
This is a **success** alert.
[[/alert]]

## Weather Shortcode

Note: This shortcode calls external HTTPS APIs and caches results to avoid repeated requests.

Weather in West Palm Beach, FL (using Zip, Fahrenheit):
[[weather zip="33409" country="us" scale="F"]]

Weather in London (using Lat/Long, Celsius):
[[weather lat="51.5074" long="-0.1278" scale="C"]]

## Escaping

This should show the shortcode text, not render it:
[[[youtube id="123"]]]
---

# FILE: examples/simple-page.md

# About Us

We are a team of passionate developers building tools that make web development easier.

## Our Mission

To empower developers with simple, powerful tools that let them focus on creating great content and applications.

## Contact

- **Email**: hello@example.com
- **GitHub**: [github.com/example](https://github.com/example)
---

# FILE: features/cache-buster.md

# Cache Buster

**What it does:** Automatically appends a unique build timestamp to your CSS file references to ensure browsers always load the latest version of your styles.

**Events:** `CREATE` (priority 10)

**How it works:**
1. During the `CREATE` event, the feature generates a unique `build_id` based on the current timestamp.
2. This `build_id` is stored in the container, along with a `cache_buster` variable (formatted as `sfcb=TIMESTAMP`).
3. In your Twig templates, you can append `?{{ cache_buster }}` to your asset URLs.

**Example Usage:**

In your `base.html.twig`:

```twig
<link rel="stylesheet" href="assets/css/main.css?{{ cache_buster }}">
```

**Result:**

The rendered HTML will look like:

```html
<link rel="stylesheet" href="assets/css/main.css?sfcb=1732134567">
```

This forces the browser to treat the file as a new resource whenever you rebuild your site, preventing stale cache issues.
---

# FILE: features/categories.md

# Categories

**What it does:** Organizes content into subdirectories and applies category-specific templates

**Events:** `POST_GLOB` (priority 250), `POST_RENDER` (priority 100)

**How to use:** Add a `category` field to your frontmatter, or create category definition files

---

## Basic Usage

Add a `category` field to your content frontmatter:

```markdown
---
title: "Learning PHP Basics"
category: "tutorials"
---

# Learning PHP Basics

Welcome to our PHP tutorial series!
```

---

## Category Definition Files

Create a category definition file to specify templates for all content in that category:

**File:** `content/tutorials.md`

```markdown
---
type: category
template: tutorial
---
```

Now all files with `category: "tutorials"` will automatically use the `tutorial.html.twig` template.

---

## Template Inheritance

Categories feature applies templates via POST_GLOB event with the following priority:

1. **File frontmatter template** - If file has `template = "xyz"`, use it
2. **Category template** - If file belongs to category with defined template, use it
3. **Default template** - Falls back to base template

This happens automatically during the discovery phase, before rendering.

---

## What Happens During POST_GLOB

1. **Scan for category definitions** - Finds files with `type = "category"`
2. **Extract category templates** - Stores mapping of category slug → template name
3. **Apply to content files** - Iterates all discovered files and applies category templates

---

## What Happens During POST_RENDER

1. StaticForge sanitizes the category name:
   - `tutorials` → `tutorials`
   - `Web Development` → `web-development`
   - `PHP & MySQL` → `php-mysql`
   - `Cool_Stuff!` → `cool-stuff`

2. Creates the category directory: `output/tutorials/`

3. Moves your file there: `output/tutorials/learning-php-basics.html`

---

## Sanitization Rules

- Converts to lowercase
- Replaces spaces and special characters with hyphens
- Removes leading/trailing hyphens
- Keeps only letters, numbers, and hyphens

---

## Double-Nesting Prevention

StaticForge automatically prevents double-nesting when your source directory structure matches your category name:

- Source file: `docs/configuration.md` with `category = "docs"`
- Output: `public/docs/configuration.html` (not `public/docs/docs/configuration.html`)

This smart detection ensures clean URL structures when you organize both your source files and categories logically.

## Why Use Categories

- Keep related content together
- Create logical URL structures (`/blog/`, `/tutorials/`, `/docs/`)
- Organize large sites into sections
- Enable category-specific styling or templates

**Important:** This is the **only** way to create subdirectories in your output. Without categories, all pages go in the root.

---

[← Back to Features Overview](index.html)
---

# FILE: features/category-index.md

# Category Index Pages

**What it does:** Creates index pages that list all files in each category

**Events:**
- `POST_GLOB` (priority 200)
- `PRE_RENDER` (priority 150)
- `POST_RENDER` (priority 50)
- `POST_LOOP` (priority 100)

**How to use:** Create a `.md` file named after your category

## Example - Create `content/tutorials.md`

```markdown
---
type: category
title: Tutorials
description: Learn with our step-by-step guides
template: category-index
menu: 1.3
sort_by: published_date
sort_direction: desc
---

Browse all our tutorials below. This text will be replaced with the file listing.
```

## Sorting Options

You can control the order of files in the category index using the `sort_by` and `sort_direction` frontmatter keys.

**`sort_by` options:**
- `published_date` (Default)
- `title`
- `random`

**`sort_direction` options:**
- `asc` (Ascending)
- `desc` (Descending)
- `random`

**Defaults:**
- If `sort_by` is `published_date`, default direction is `desc` (Newest first).
- If `sort_by` is `title`, default direction is `asc` (A-Z).

**Note:** If any file within the category has a `menu` property in its frontmatter, the sorting settings will be ignored to preserve the menu structure order.

## What You Get

StaticForge generates `output/tutorials/index.html` containing:
- All files with `category = "tutorials"`
- Sorted, styled listing
- Pagination (if you have many files)
- Your custom template styling

The public URL for the category index is `/tutorials/`.

## Template Variables Available

```twig
{{ category }}           {# "tutorials" #}
{{ total_files }}        {# 23 #}
{{ files }}              {# Array of file objects #}

{% for file in files %}
  <article>
    <h2><a href="{{ file.url }}">{{ file.title }}</a></h2>

    {% if file.image %}
      <img src="{{ file.image }}" alt="{{ file.title }}">
    {% endif %}

    {% if file.metadata.description %}
      <p>{{ file.metadata.description }}</p>
    {% endif %}

    <time>{{ file.date }}</time>
  </article>
{% endfor %}
```

## File Object Properties

- `file.title` - The page title
- `file.url` - Relative URL to the page
- `file.image` - Hero/featured image (if any)
- `file.date` - Publication or modification date
- `file.metadata` - All frontmatter from the file

## Example Category Index Template

```twig
{% extends "base.html.twig" %}

{% block content %}
<div class="category-page">
  <h1>{{ category|title }}</h1>
  <p class="count">{{ total_files }} articles</p>

  <div class="article-grid">
    {% for file in files %}
      <article class="card">
        <h2><a href="{{ file.url }}">{{ file.title }}</a></h2>
        <p>{{ file.metadata.description|default('') }}</p>
        <a href="{{ file.url }}" class="read-more">Read more →</a>
      </article>
    {% endfor %}
  </div>
</div>
{% endblock %}
```

---

[← Back to Features Overview](index.html)
---

# FILE: features/estimated-reading-time.md

# Estimated Reading Time

**What it does:** Calculates the estimated reading time for content files (Markdown/HTML) and exposes this data to Twig templates.

**Events:** `PRE_RENDER` (priority 50)

**How to use:** Enable the feature and use the `{{ reading_time_label }}` variable in your templates.

---

## Configuration

StaticForge enables this feature by default. You can configure it in your `siteconfig.yaml` file to adjust the words-per-minute (WPM) or the label text.

```yaml
reading_time:
  wpm: 200              # Words per minute (default: 200)
  exclude:              # Paths to ignore
    - /contact
    - /search
  label_singular: "min read"
  label_plural: "min read"
```

## Template variables

The feature automatically injects the following variables into your file's metadata, available in your Twig templates:

*   `reading_time_minutes` (int): The raw number of minutes (e.g., `5`).
*   `reading_time_label` (string): The formatted label (e.g., `"5 min read"`).

### Example usage

```twig
<article>
    <header>
        <h1>{{ title }}</h1>
        <div class="meta">
            <span>By {{ author }}</span>
            <span>&bull;</span>
            <span>{{ reading_time_label }}</span>
        </div>
    </header>

    <div class="content">
        {{ content }}
    </div>
</article>
```

## How calculation works

1.  **Strips HTML**: All HTML tags are removed from the content to count only the actual text.
2.  **Counts Words**: Uses a standard word count algorithm.
3.  **Calculates Time**: Divides total words by the configured `wpm` (default 200).
4.  **Rounds Up**: Always rounds up the nearest minute (e.g., 20 seconds is "1 min read").
---

# FILE: features/external-features.md

# External Features

While StaticForge comes with a robust set of built-in features, its true power lies in its extensibility. The community and the core team maintain a collection of external features that can be installed via Composer to add specialized functionality to your site.

These features are not included in the core installation to keep the base lightweight, but they can be easily added to any project.

## Available Packages

### **[Chapter Navigation](https://github.com/calevans/staticforge-chapternav)**
(`calevans/staticforge-chapternav`)<br />
Automatically generates sequential prev/next navigation links for documentation pages based on menu ordering.<br /><br />
### **[Google Analytics](https://github.com/calevans/staticforge-google-analytics)**
(`calevans/staticforge-google-analytics`)<br />
Adds Google Analytics tracking code to your site.<br /><br />
### **[Podcast Feature](https://github.com/calevans/staticforge-podcast)**
(`calevans/staticforge-podcast`)<br />
Adds full podcasting support to StaticForge. It extends the RSS Feed feature to support iTunes/Apple Podcast tags, manages media file enclosures, and includes tools for inspecting media files.<br /><br />
### **[Popup Feature](https://github.com/calevans/staticforge-popup)**
(`calevans/staticforge-popup`)<br />
Adds support for popups on your site.<br /><br />
### **[S3 Media Offload](https://github.com/calevans/StaticForgeS3)**
(`calevans/staticforge-s3`)<br />
Move media files (images, audio, video) to an AWS S3 bucket. (or compatible service) It updates your content to point to the CDN/S3 URLs, keeping your repository small and your site fast.<br /><br />
### **[Social Metadata](https://github.com/calevans/staticforge-social-metadata)**
(`calevans/staticforge-social-metadata`)<br />
Automatically generates Open Graph and Twitter Card metadata tags for your pages. It supports site-wide defaults and per-page overrides via frontmatter to ensure your content looks great when shared on social media.<br /><br />
### **[Answer Engine Optimization](https://github.com/calevans/staticforge-answer-engine-optimization)**
(`calevans/answer-engine-optimization`)<br />
Prepares your site for AI-powered search engines and LLM agents (ChatGPT, Perplexity, Claude, Gemini). It automatically injects JSON-LD structured data (Article and FAQPage schemas), generates an `/llms.txt` AI sitemap, publishes clean `.md` mirrors of your Markdown pages for machine consumption, and adds AI-crawler-friendly rules to your `robots.txt`. FAQ schema can be defined in frontmatter or via the `[aeo_faq]` shortcode.<br /><br />

## Installing External Features

To install an external feature, follow these steps:

1.  **Install via Composer:**
    Run the following command in your project root:
    ```bash
    composer require vendor/package-name
    ```

2.  **Run Setup Command:**
    Most external features come with example configuration files. Run the setup command to copy them to your project root:
    ```bash
    php bin/staticforge.php feature:setup vendor/package-name
    ```
    This will create example files like `.env.example.package-name` or `siteconfig.yaml.example.package-name`.

3.  **Configure:**
    Review the generated example files and add the necessary configuration settings to your main `.env` file or `siteconfig.yaml`.

Once installed and configured, StaticForge will automatically discover and load the feature.
---

# FILE: features/forms.md

# Forms Feature

The Forms feature allows you to easily embed contact forms and other types of forms into your static pages using a simple shortcode. It handles form rendering, configuration, and even includes spam protection via Altcha.

## Recommended Backend

While StaticForge can work with any form backend, we highly recommend [SendPoint](https://github.com/calevans/sendpoint). It is a lightweight, self-hosted form processor that handles email notifications, webhooks, and integrates seamlessly with StaticForge's built-in Altcha spam protection.

## Configuration

Forms are configured in your `siteconfig.yaml` file. You can define multiple forms, each with its own fields and submission endpoint.

```yaml
forms:
  contact:
    provider_url: "https://eicc.com/f/"
    form_id: "YOUR_FORM_ID"
    challenge_url: "https://sendpoint.lndo.site/?action=challenge" # Optional: For Altcha spam protection
    submit_text: "Send Message"
    success_message: "Thanks! We've received your message."
    error_message: "Oops! Something went wrong. Please try again."
    fields:
      - name: "name"
        label: "Your Name"
        type: "text"
        required: true
        placeholder: "John Doe"
      - name: "email"
        label: "Email Address"
        type: "email"
        required: true
        placeholder: "john@example.com"
      - name: "message"
        label: "Message"
        type: "textarea"
        rows: 7
        required: true
        placeholder: "How can we help you?"
```

### Configuration Options

| Option | Description |
|Str|---|
| `provider_url` | The base URL of your form processing service. |
| `form_id` | The unique ID for this specific form. Appended to `provider_url`. |
| `challenge_url` | (Optional) The URL for the Altcha challenge service. If provided, an Altcha widget will be added to the form. |
| `template` | (Optional) The name of a custom template to use for this form (e.g., `contact_us` for `templates/active_template/contact_us.html.twig`). |
| `submit_text` | The text to display on the submit button. Default: "Submit". |
| `success_message` | The message to display when the form is successfully submitted. |
| `error_message` | The message to display if submission fails. |
| `fields` | A list of fields to include in the form. |

### Field Options

| Option | Description |
|---|---|
| `name` | The `name` attribute of the input field. |
| `label` | The label text for the field. Defaults to capitalized name. |
| `type` | The input type (e.g., `text`, `email`, `textarea`). Default: `text`. |
| `required` | Boolean. Whether the field is required. |
| `placeholder` | Placeholder text for the input. |
| `rows` | (Textarea only) Number of rows. Default: 5. |

## Usage

To insert a form into your content (Markdown or HTML), use the `form()` shortcode with the name of the form defined in `siteconfig.yaml`.

```markdown
# Contact Us

Have questions? Fill out the form below!

{{ form('contact') }}
```

## Custom Templates

You can customize the look and feel of your forms by creating a custom Twig template.

1.  Create a new template file in your active template directory (e.g., `templates/staticforce/contact_us.html.twig`).
2.  In your `siteconfig.yaml`, add the `template` option to your form configuration:

    ```yaml
    forms:
      contact:
        template: contact_us
        # ... other options
    ```

The system will look for `templates/YOUR_TEMPLATE/contact_us.html.twig`. If it doesn't exist, it will fall back to the default form template.

Your custom template will receive the following variables:
- `endpoint`: The submission URL.
- `fields`: The array of field definitions.
- `submit_text`: The text for the submit button.
- `success_message`: The success message.
- `error_message`: The error message.
- `challenge_url`: The Altcha challenge URL (if configured).

## Spam Protection (Altcha)

The Forms feature supports [Altcha](https://altcha.org/) for privacy-friendly spam protection. This feature is **completely optional**.

### Enabling Altcha
To enable spam protection:
1.  Ensure you have an Altcha challenge server running or use a hosted service.
2.  Add the `challenge_url` key to your form configuration in `siteconfig.yaml`.

```yaml
forms:
  contact:
    # ... other config ...
    challenge_url: "https://your-altcha-server.com/challenge"
```

When `challenge_url` is present, the system will automatically:
- Include the Altcha widget in the form.
- Load the necessary Altcha JavaScript.

### Disabling Altcha
To disable spam protection, simply **remove or comment out** the `challenge_url` line in your `siteconfig.yaml`. If this key is missing, no Altcha code or widgets will be generated.

## Styling

The form comes with default styling that is injected automatically. You can override these styles in your site's CSS. The form uses the following classes:

- `.sf-form-wrapper`: Container for the form and messages.
- `.sf-form`: The form element itself.
- `.sf-form-group`: Wrapper for each label/input pair.
- `.sf-label`: The field label.
- `.sf-input`: The input or textarea element.
- `.sf-button`: The submit button.
- `.sf-form-message`: The container for success/error messages.
- `.sf-message-success`: Applied to the message container on success.
- `.sf-message-error`: Applied to the message container on error.
---

# FILE: features/html-renderer.md

# HTML Renderer

**What it does:** Processes `.html` files and wraps them in templates

**File types:** `.html`, `.htm`

**Events:** `RENDER` (priority 100)

**How it works:**

1. Reads frontmatter from `<!-- ... -->` comment block (YAML)
2. Extracts the HTML content
3. Applies your chosen Twig template
4. Outputs the final HTML file

## Example

**Example input file (`content/about.html`):**

```html
<!--
---
title: "About Us"
description: "Learn about our company"
template: "about-page"
---
-->
<div class="about-section">
  <h1>About Our Company</h1>
  <p>We build amazing websites with StaticForge!</p>

  <h2>Our Mission</h2>
  <p>To make static site generation accessible to everyone.</p>
</div>
```

## Key Points

- Use `<!-- --- ... --- -->` for frontmatter (YAML within HTML comment)
- Write regular HTML for content
- Great for custom layouts or when you need precise HTML control
- Still gets wrapped in your template like Markdown files

## When to Use HTML Instead of Markdown

- Complex layouts requiring specific HTML structure
- Embedding custom JavaScript or CSS
- Pages with forms or interactive elements
- Landing pages with specific design requirements

---

[← Back to Features Overview](index.html)
---

# FILE: features/index.md

# Built-in Features

StaticForge comes with several powerful features that add functionality to your site. Each feature is documented in detail on its own page.

## What Are Features?

Features are plugins that extend StaticForge's capabilities. They listen to events during site generation and perform specific tasks like converting Markdown to HTML, building menus, or organizing content by category.

**Good to know:**
- All features are optional - you can disable any feature by deleting its directory
- Features are loaded automatically from `src/Features/`
- You can create your own custom features (see [Feature Development](../development/features.html))

---

## Content Processing Features

These features handle converting your content files into HTML.

### [Markdown Renderer](markdown-renderer.html)

Converts `.md` files to HTML using Markdown syntax. Perfect for writing blog posts, articles, and documentation in a simple, readable format.

[Read more about Markdown Renderer →](markdown-renderer.html)

### [HTML Renderer](html-renderer.html)

Processes `.html` files and wraps them in templates. Ideal for custom layouts, landing pages, or when you need precise HTML control.

[Read more about HTML Renderer →](html-renderer.html)

---

## Interactive Features

These features add interactivity to your static pages.

### [Forms](forms.html)

Embed contact forms and other input forms using simple shortcodes. Supports configuration via `siteconfig.yaml`, AJAX submission, and Altcha spam protection.

[Read more about Forms →](forms.html)

### [Search](search.html)

Adds full-text search capability to your site using MiniSearch. Generates a client-side index and provides a fast, static search experience.

[Read more about Search →](search.html)

---

## Organization Features

These features help you organize and structure your content.

### [Menu Builder](menu-builder.html)

Automatically creates navigation menus from your content using a simple dot-notation system. Supports multiple menus, dropdowns, and flexible positioning.

[Read more about Menu Builder →](menu-builder.html)

### [Chapter Navigation](https://github.com/calevans/staticforge-chapternav)

Generates sequential prev/next navigation links for documentation pages. Perfect for tutorials, guides, and any content that follows a specific order.

[Read more about Chapter Navigation →](https://github.com/calevans/staticforge-chapternav)

### [Categories](categories.html)

Organizes content into subdirectories based on category metadata. The only way to create subdirectories in your output.

[Read more about Categories →](categories.html)

### [Category Index Pages](category-index.html)

Creates index pages that list all files in each category. Automatically generates organized directory listings with pagination support.

[Read more about Category Index Pages →](category-index.html)

### [Tags](tags.html)

Extracts tags from frontmatter and makes them available site-wide. Great for SEO, tag clouds, and content filtering.

[Read more about Tags →](tags.html)

---

## SEO & Search Engine Features

These features help optimize your site for search engines.

### [Robots.txt Generator](robots-txt.html)

Automatically generates a `robots.txt` file to control search engine crawling. Keep private pages out of search results effortlessly.

[Read more about Robots.txt Generator →](robots-txt.html)

### [RSS Feed](rss-feed.html)

Automatically generates RSS feeds for each category. Enable readers to subscribe to your content updates.

[Read more about RSS Feed →](rss-feed.html)

### [Sitemap Generator](sitemap.html)

Automatically generates a `sitemap.xml` file for search engines. Critical for SEO to help search engines index your site correctly.

[Read more about Sitemap Generator →](sitemap.html)

---

## Managing Features

### Disabling Features

Don't need a feature? Just delete or rename its directory:

```bash
# Disable categories completely
rm -rf src/Features/Categories

# Temporarily disable (can re-enable by renaming back)
mv src/Features/Categories src/Features/Categories.disabled
```

StaticForge will continue working without that feature.

### Which Features Can I Disable?

**You can safely disable:**
- RssFeed - if you don't need RSS/Atom syndication
- Categories - if you don't need subdirectories
- CategoryIndex - if you don't want category listing pages
- Tags - if you don't use tags
- MenuBuilder - if you build menus manually
- ChapterNav - if you don't need sequential navigation

**Don't disable these (unless you know what you're doing):**
- MarkdownRenderer - needed to process `.md` files
- HtmlRenderer - needed to process `.html` files

### Creating Custom Features

Want to add your own functionality? See the [Feature Development Guide](../development/features.html) for step-by-step instructions on creating custom features.

---

## Feature Comparison Table

| Feature | Input Required | Output Created | Use Case |
|---------|---------------|----------------|----------|
| **[Markdown Renderer](markdown-renderer.html)** | `.md` files | HTML files | Writing content in Markdown |
| **[HTML Renderer](html-renderer.html)** | `.html` files | HTML files | Custom layouts, precise HTML control |
| **[Menu Builder](menu-builder.html)** | `menu` in frontmatter | Navigation HTML | Automatic menu generation |
| **[Chapter Navigation](https://github.com/calevans/staticforge-chapternav)** | `menu` in frontmatter | Prev/Next links | Sequential page navigation |
| **[Categories](categories.html)** | `category` in frontmatter | Subdirectories | Organizing content into sections |
| **[Category Index](category-index.html)** | Category `.md` file | Index page | Listing all category files |
| **[Tags](tags.html)** | `tags` in frontmatter | Meta tags, tag data | SEO, tag clouds, related content |
| **[Robots.txt Generator](robots-txt.html)** | `robots` in frontmatter | robots.txt file | SEO, search engine control |
| **[RSS Feed](rss-feed.html)** | `category` in frontmatter | `rss.xml` per category | Syndication, feed readers, notifications |
| **[Sitemap Generator](sitemap.html)** | `sitemap` in frontmatter | `sitemap.xml` file | SEO, search engine indexing |
| **[Search](search.html)** | Content files | `search.json` & assets | Client-side full-text search |

---

## External Features

Looking for more? Check out our [External Features](external-features.html) page for a list of community-maintained packages that extend StaticForge with specialized functionality like Podcasting and Media Inspection.
---

# FILE: features/markdown-renderer.md

# Markdown Renderer

**What it does:** Converts `.md` files to HTML using Markdown syntax

**File types:** `.md`

**Events:** `RENDER` (priority 100)

**How it works:**

1. Reads frontmatter between `---` markers
2. Converts Markdown content to HTML using CommonMark
3. Applies your chosen Twig template
4. Outputs the final HTML file

## Example

**Example input file (`content/blog-post.md`):**

```markdown
---
title: "My First Blog Post"
description: "An introduction to my blog"
---

# Welcome to My Blog

This is my **first post** using StaticForge!

## What I'll Write About

- Web development
- PHP tutorials
- Static site generation

Pretty *exciting*, right?
```

**What you get:**

- Frontmatter is extracted and available to templates
- Markdown is converted to semantic HTML
- The title becomes `{{ title }}` in your template
- Content is wrapped in your chosen template
- File saved as `output/blog-post.html`

**No configuration needed** - just create `.md` files and go!

## Draft Content

You can mark a file as a draft to exclude it from the build (unless `SHOW_DRAFTS=true` is set in `.env`).

```markdown
---
title: "Work In Progress"
draft: true
---
```

This is useful for working on content that isn't ready to be published yet.

---

[← Back to Features Overview](index.html)
---

# FILE: features/menu-builder.md

# Menu Builder

**What it does:** Automatically creates navigation menus from your content

**Events:** `POST_GLOB` (priority 100)

**How to use:** Add a `menu` field to your frontmatter

## Menu Types

StaticForge supports two types of menus:

### Numbered Menus (Content-Based)

Defined in content file frontmatter using the `menu` field. These menus are automatically discovered from your content files.

**Access in templates:** `{{ menu1 }}`, `{{ menu2 }}`, etc.

### Named Menus (Static)

Defined in `siteconfig.yaml` for static/external links. See [Site Configuration](/guide/site-config.html) for details.

**Access in templates:** `{{ menu_top }}`, `{{ menu_footer }}`, etc.

**Use case:** External links, hardcoded navigation, links to non-StaticForge sections.

---

## Numbered Menu Positioning System

The `menu` value uses a dot-notation system: `menu.position.dropdown-position`

### Single Menu Position

```markdown
---
title: "Home"
menu: 1.1
---
```
Creates: First item in menu 1

### Multiple Menu Positions

Want a page to appear in multiple menus? Just list the positions separated by commas:

```markdown
---
title: "Privacy Policy"
menu: 1.5, 2.1
---
```
Creates: Item appears in menu 1 at position 5 AND menu 2 at position 1

```markdown
---
title: "Contact Us"
menu: 1.6, 2.3, 3.1
---
```
Creates: Item appears in three different menus

### Format Options
```markdown
menu: 1.2, 2.3         # Recommended - simple and clean
menu: [1.2, 2.3]       # Also works - brackets optional
menu: ["1.2", "2.3"]   # Also works - quotes optional
```

## More Examples

```markdown
---
title: "About"
menu: 1.2
---
```
Creates: Second item in menu 1

```markdown
---
title: "Services"
menu: 1.3.0
---
```
Creates: Dropdown title at position 3 in menu 1 (`.0` means it's the dropdown label)

```markdown
---
title: "Web Development"
menu: 1.3.1
---
```
Creates: First item inside the "Services" dropdown

## Visual Example

```
Menu 1 (Main Navigation):
├─ Home (1.1)
├─ About (1.2)
├─ Services (1.3.0) ▼
│  ├─ Web Development (1.3.1)
│  ├─ Mobile Apps (1.3.2)
│  └─ Consulting (1.3.3)
├─ Contact (1.4)         # Also in menu 2
└─ Privacy (1.5)         # Also in menu 2

Menu 2 (Footer):
├─ Privacy (2.1)         # Same page as 1.5
├─ Terms (2.2)
└─ Contact (2.3)         # Same page as 1.4
```

## Using Menus in Templates

Menus are available in templates through the `features.MenuBuilder` object.

### Option 1 - Pre-rendered HTML

Use the pre-rendered HTML menu:

```twig
<nav>
  {{ features.MenuBuilder.html.1|raw }}
</nav>
```

This outputs the complete `<ul>` structure with all menu items sorted by position.

### Option 2 - Manual Iteration (More Control)

Access the raw menu data to build custom markup:

```twig
<nav>
  <ul class="my-custom-menu">
    {% if features.MenuBuilder.files[1] is defined %}
      {% for item in features.MenuBuilder.files[1] %}
        <li><a href="{{ item.url }}">{{ item.title }}</a></li>
      {% endfor %}
    {% endif %}
  </ul>
</nav>
```

### Menu Data Structure

Each menu item in `features.MenuBuilder.files[X]` contains:

- `title` - Page title (falls back to H1 or filename if not set)
- `url` - Generated URL (includes category prefix if applicable)
- `file` - Source file path
- `position` - Menu position string (e.g., "1.2")

Items are automatically sorted by position number.

### Multiple Menus Example

```twig
{# Top navigation - Menu 1 #}
<nav class="topnav">
  {{ features.MenuBuilder.html.1|raw }}
</nav>

{# Sidebar - Menu 2 #}
<aside class="sidebar">
  <ul class="nav">
    {% for item in features.MenuBuilder.files[2] %}
      <li><a href="{{ item.url }}">{{ item.title }}</a></li>
    {% endfor %}
  </ul>
</aside>

{# Footer - Menu 3 #}
<footer>
  {{ features.MenuBuilder.html.3|raw }}
</footer>
```

---

## How MenuBuilder Works

1. **POST_GLOB Event** - MenuBuilder listens at priority 100
2. **Scan discovered_files** - Iterates pre-parsed metadata from FileDiscovery
3. **Extract menu positions** - Finds all files with `menu` metadata
4. **Build menu structure** - Organizes items by menu number and position
5. **Sort by position** - Ensures items appear in correct order (1, 2, 3... not filesystem order)
6. **Generate HTML** - Creates rendered `<ul>` markup
7. **Store in features** - Makes both raw data and HTML available to templates

---

## Tips

- Use commas to place a page in multiple menus
- Menu items are automatically sorted by position number
- Position `0` is reserved for dropdown titles
- URLs include category prefixes automatically
- No need for brackets or quotes in frontmatter (but they work if you prefer them)
- Menu data is pre-parsed during discovery phase for performance

---

[← Back to Features Overview](index.html)
---

# FILE: features/robots-txt.md

# Robots.txt Generator

**What it does:** Automatically generates a `robots.txt` file to control search engine crawling

**File types:** Works with all content files (`.md`, `.html`) and category definition files

**Events:**
- `POST_GLOB` (priority 150) - Scans files for robots metadata
- `POST_LOOP` (priority 100) - Generates robots.txt file

## How It Works

1. Scans all content files for `robots` metadata during site generation
2. Collects paths that should be disallowed
3. Generates `robots.txt` in the output directory after processing all files
4. Automatically includes sitemap reference if `SITE_BASE_URL` is configured

## Controlling Individual Pages

Add `robots` field to any content file's frontmatter:

### Disallow a Page (robots=no)

```markdown
---
title: "Private Page"
robots: "no"
---

# This page won't be crawled by search engines

This content is hidden from search engines via robots.txt.
```

### Allow a Page (robots=yes or omit field)

```markdown
---
title: "Public Page"
robots: "yes"
---

# This page is visible to search engines

Default behavior - search engines can crawl this page.
```

## Controlling Entire Categories

Create a category definition file with `type=category` and `robots=no`:

```markdown
---
type: category
category: private
title: "Private Category"
robots: "no"
---

# Private Category

All files in this category will be disallowed in robots.txt.
```

## Generated Robots.txt Examples

### When You Have Pages with robots=no

```
# robots.txt generated by StaticForge
# 2025-01-15 10:30:00

User-agent: *
Disallow: /private-page.html
Disallow: /secret.html
Disallow: /private-category/

# Sitemap location
Sitemap: https://example.com/sitemap.xml
```

### When All Pages Are Allowed

```
# robots.txt generated by StaticForge
# 2025-01-15 10:30:00

User-agent: *
# No disallowed paths
Disallow:

# Sitemap location
Sitemap: https://example.com/sitemap.xml
```

## Key Features

- **Automatic:** robots.txt is generated on every site build
- **Smart defaults:** Pages without `robots` field default to "yes" (allow)
- **Case insensitive:** `robots="NO"`, `robots="No"`, and `robots="no"` all work
- **Category support:** Disallow entire categories via category definition files
- **Sorted output:** Paths are alphabetically sorted for consistency
- **Sitemap reference:** Automatically includes sitemap URL if configured

## Configuration

No configuration needed! The feature uses `SITE_BASE_URL` from your `.env` file:

```ini
# .env
SITE_BASE_URL="https://example.com"
```

## Why Use This Feature

- **Privacy:** Keep development/internal pages out of search results
- **SEO control:** Prevent duplicate content issues
- **Compliance:** Follow SEO best practices automatically
- **No manual work:** robots.txt updates automatically when you add/remove pages

## Important Notes

- robots.txt is a *suggestion* to search engines, not enforcement
- For real security, use authentication or don't publish sensitive content
- The feature generates `robots.txt` in your output directory on every build
- Changes take effect when you deploy the updated robots.txt file

---

[← Back to Features Overview](index.html)
---

# FILE: features/rss-feed.md

# RSS Feed

**What it does:** Automatically generates RSS feeds for each category

**Events:**
- `POST_RENDER` (priority 40) - Collects categorized files
- `POST_LOOP` (priority 90) - Generates RSS XML files
- `RSS_ITEM_BUILDING` - Fired for each item before adding to feed

**How to use:** Just add a `category` to your frontmatter - RSS feeds are generated automatically!

## Basic Usage

StaticForge automatically generates an RSS feed for every category on your site. Any content file (post, page, etc.) that has a `category` defined in its frontmatter will be included in that category's feed.

### 1. Categorize Your Content

Add a `category` field to your content's frontmatter.

```markdown
---
title: "Getting Started with PHP"
category: "Tutorials"
description: "A beginner-friendly introduction to PHP programming"
author: "Jane Doe"
date: "2024-01-15"
---

# Getting Started with PHP

This tutorial will teach you the basics of PHP...
```

### 2. Generate Your Site

Run the build command:

```bash
php bin/staticforge.php site:render
```

### 3. Find Your Feeds

StaticForge creates an `rss.xml` file in each category's directory.

```
public/
  tutorials/
    getting-started-with-php.html
    rss.xml                        ← RSS feed for Tutorials category
  news/
    latest-updates.html
    rss.xml                        ← RSS feed for News category
```

Your feed URLs will be:
- `https://yoursite.com/tutorials/rss.xml`
- `https://yoursite.com/news/rss.xml`

## Metadata

You can control how your content appears in the feed using frontmatter.

| Frontmatter Field | RSS Element | Required | Default |
|-------------------|-------------|----------|---------|
| `title` | `<title>` | Yes | "Untitled" |
| `description` | `<description>` | No | Auto-extracted from content (first 200 chars) |
| `author` | `<author>` | No | Not included |
| `date` or `published_date` | `<pubDate>` | No | File modification time |
| `category` | Determines feed | Yes | File not included |

## Adding RSS Links to Your Site

In your category index or base template:

```twig
<!-- Link to RSS feed in <head> -->
{% if category %}
<link rel="alternate" type="application/rss+xml"
      title="{{ site_name }} - {{ category }}"
      href="/{{ category|lower|replace({' ': '-'}) }}/rss.xml" />
{% endif %}

<!-- Display RSS link in content -->
{% if category %}
<a href="/{{ category|lower|replace({' ': '-'}) }}/rss.xml" class="rss-link">
  Subscribe to {{ category }} RSS Feed
</a>
{% endif %}
```

## Best Practices

1. **Always add dates:** Use `published_date` for consistent sorting
2. **Write good descriptions:** Either in frontmatter or first paragraph
3. **Include author emails:** Use email format for `author` field (RSS spec)
4. **Use consistent categories:** Keep category names standardized

## Testing Your RSS Feed

1. Generate your site: `php bin/staticforge.php site:render`
2. Check the feed: `cat public/tutorials/rss.xml`
3. Validate it: Use [W3C Feed Validator](https://validator.w3.org/feed/)
4. Subscribe in a reader: Try Feedly, NewsBlur, or another RSS reader

## No Categories = No RSS

Files without a `category` are not included in any RSS feed. This is intentional - only categorized content appears in feeds.

---

[← Back to Features Overview](index.html)
---

# FILE: features/search.md

# Search

**What it does:** Adds full-text search capability to your static site using [MiniSearch](https://github.com/lucaong/minisearch) or [Fuse.js](https://github.com/krisk/fuse).

**Events:** `POST_RENDER` (priority 100), `POST_LOOP` (priority 100)

**How to use:** Enable in `siteconfig.yaml` and include the search assets in your template.

---

## Overview

The Search feature brings powerful, client-side search functionality to your static site without requiring a backend server or database. It works by generating a comprehensive JSON index of your content during the build process and using a lightweight JavaScript library ([MiniSearch](https://github.com/lucaong/minisearch) or [Fuse.js](https://github.com/krisk/fuse)) to perform searches directly in the user's browser.

This approach ensures your site remains fast and completely static while still offering a dynamic search experience.

---

## Search Engines

StaticForge supports two search engines:

1.  **[MiniSearch](https://github.com/lucaong/minisearch) (Default):** Best for exact matches, prefix search ("stat" finds "static"), and performance on larger sites.
2.  **[Fuse.js](https://github.com/krisk/fuse):** Best for fuzzy matching (finding "static" even if you type "sttaic").

You can switch engines in your `siteconfig.yaml`:

```yaml
search:
  engine: fuse # or 'minisearch'
```

---

## How It Works

When you build your site, the Search feature performs two main tasks:

1.  **Indexing:** As each page is rendered, the feature collects its title, content, tags, and category. It strips out HTML tags to create a clean text representation of your content.
2.  **Generation:** After all pages are processed, it compiles this data into a `search.json` file and copies the necessary JavaScript libraries to your output directory.

---

## Configuration

You can configure the search behavior globally in your `siteconfig.yaml` file or on a per-page basis using frontmatter.

### Global Configuration

In `siteconfig.yaml`, you can control which paths are excluded from the search index. This is useful for hiding utility pages, tag archives, or other content you don't want appearing in search results.

```yaml
search:
  enabled: true
  exclude_paths:
    - /tags/
    - /categories/
    - /404.html
  exclude_content_in: []
```

*   **exclude_paths:** A list of URL paths to completely exclude from the index.
*   **exclude_content_in:** (Optional) A list of paths where content should be excluded, but the page might still be indexed (depending on implementation details, currently behaves similarly to exclude_paths).

### Per-Page Configuration

You can exclude individual pages from the search index by adding `search_index: false` to the page's frontmatter.

```markdown
---
title: "Hidden Page"
search_index: false
---
```

---

## Implementing Search in Your Template

To add the search bar to your site, you need to include the generated JavaScript assets and add the HTML markup for the search input.

### 1. Add the HTML

Add the following HTML where you want the search bar to appear (e.g., in your header or sidebar):

```html
<div class="search-container">
    <input type="text" id="search-input" placeholder="Search...">
    <div id="search-results"></div>
</div>
```

### 2. Include the Scripts

Add the following script tags to your template, typically before the closing `</body>` tag:

```html
<script src="/assets/js/minisearch.min.js"></script>
<script src="/assets/js/search.js"></script>
```

The `search.js` script automatically initializes MiniSearch, loads the `search.json` index, and handles user input to display results in the `#search-results` container.

---

## Customizing the Search Experience

The default `search.js` provides a basic implementation. You can customize the look and feel by styling the `#search-input` and `#search-results` elements with CSS.

If you need more advanced behavior (like custom result rendering or different search options), you can modify the `search.js` file or create your own script that utilizes the `minisearch.min.js` library and the generated `search.json` index.
---

# FILE: features/sitemap.md

# Sitemap Generator

**What it does:** Automatically generates a `sitemap.xml` file for search engines.

**File types:** Generates `sitemap.xml`

**Events:**
- `POST_RENDER` (priority 100): Collects URLs
- `POST_LOOP` (priority 100): Generates XML file

**How it works:**

1. Listens as each page is rendered
2. Collects the URL and last modification date
3. Generates a standard XML sitemap at the end of the build process
4. Saves the file to `output/sitemap.xml`

## Configuration

The Sitemap Generator uses the `SITE_URL` (or `SITE_BASE_URL`) from your `.env` file to generate absolute URLs.

```bash
# .env
SITE_URL="https://example.com"
```

## Output Example

The generated `sitemap.xml` looks like this:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url>
    <loc>https://example.com/index.html</loc>
    <lastmod>2023-11-25</lastmod>
  </url>
  <url>
    <loc>https://example.com/about.html</loc>
    <lastmod>2023-11-24</lastmod>
  </url>
</urlset>
```

## Customizing Last Modified Date

By default, the generator uses the file's modification time. You can override this by adding a `date` field to your content's frontmatter:

```markdown
---
title: "My Page"
date: "2023-12-01"
---
```

---

[← Back to Features Overview](index.html)
---

# FILE: features/table-of-contents.md

# Table of Contents

**What it does:** Automatically generates a Table of Contents (TOC) for your pages based on headings.

**Events:** `MARKDOWN_CONVERTED` (priority 500)

**How to use:** The TOC is automatically generated for any Markdown file with `<h2>` or `<h3>` headings.

## How It Works

1.  **Listens for `MARKDOWN_CONVERTED`**: This feature waits until the Markdown Renderer has converted your content to HTML.
2.  **Parses HTML**: It scans the generated HTML for `<h2>` and `<h3>` tags.
3.  **Generates List**: It builds a nested HTML list (`<ul><li>...</li></ul>`) representing the document structure.
4.  **Injects Variable**: The generated HTML is available in your Twig templates as `{{ toc }}`.

## Usage in Templates

To display the Table of Contents in your template, use the `{{ toc }}` variable.

```twig
<!-- Right Sidebar (Table of Contents) -->
<aside class="toc">
    {% if toc %}
        <div class="toc-title">On This Page</div>
        {{ toc|raw }}
    {% endif %}
</aside>
```

## Styling

The generated HTML uses the class `.toc-list` for the main `<ul>`. Here is the CSS used in the `staticforce` template to style it:

```css
/* Table of Contents */
.toc {
    position: sticky;
    top: 100px;
}

.toc-title {
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--gray-500);
    margin-bottom: var(--spacing-md);
}

.toc-list {
    list-style: none;
    padding: 0;
}

.toc-list li {
    margin-bottom: var(--spacing-xs);
}

.toc-list a {
    display: block;
    padding: var(--spacing-sm);
    color: var(--gray-600);
    text-decoration: none;
    font-size: 0.85rem;
    border-left: 2px solid transparent;
    transition: all var(--transition-base);
}

.toc-list a:hover {
    color: var(--primary);
    border-left-color: var(--primary);
    transform: translateX(3px);
}

/* Nested Levels (h3) */
.toc-list ul {
    padding-left: var(--spacing-lg);
    list-style: none;
}

.toc-list ul a {
    font-size: 0.8rem;
}
```

## Dependencies

This feature relies on the **Markdown Renderer** adding IDs to headings. The Markdown Renderer uses the `HeadingPermalinkExtension` to ensure every heading has a unique ID (e.g., `<h2 id="introduction">`).
---

# FILE: features/tags.md

# Tags

**What it does:** Extracts tags from frontmatter and makes them available site-wide

**Events:**
- `POST_GLOB` (priority 100)
- `POST_RENDER` (priority 100)

**How to use:** Add a `tags` field to your frontmatter

## Example

```markdown
---
title: "Introduction to PHP"
tags:
  - php
  - tutorial
  - beginner
  - web-development
---

# Introduction to PHP

Learn PHP from scratch!
```

## What Happens

1. Tags are extracted from each file during processing
2. Tags are normalized (lowercase, sanitized)
3. Tags are added to the HTML as `<meta name="keywords">`
4. Tags are available to templates for tag clouds, filtering, etc.

## Using Tags in Templates

### Display Tags on a Page

```twig
{% if tags is iterable and tags|length > 0 %}
  <div class="tags">
    {% for tag in tags %}
      <span class="tag">{{ tag }}</span>
    {% endfor %}
  </div>
{% endif %}
```

### Access All Site Tags

```twig
{% if features.Tags.all_tags is defined %}
  <div class="tag-cloud">
    {% for tag, count in features.Tags.all_tags %}
      <a href="/tags/{{ tag }}.html" class="tag-{{ count }}">
        {{ tag }} ({{ count }})
      </a>
    {% endfor %}
  </div>
{% endif %}
```

## Tag Format Options

```markdown
# Array format (recommended)
tags: ["php", "tutorial", "beginner"]

# Comma-separated string (also works)
tags: "php, tutorial, beginner"
```

## Why Use Tags

- Improve SEO with keyword meta tags
- Create tag-based navigation
- Find related content
- Build tag clouds
- Enable filtering and search

---

[← Back to Features Overview](index.html)
---

# FILE: features/template-assets.md

# Assets Management

**What it does:** Automatically copies static assets (CSS, JS, images) from both your template and your content directory to the public output directory.

**Events:** `POST_LOOP` (priority 100)

**How to use:**
1. Place template-specific files (dependencies like CSS frameworks, JS libs) in `templates/<template_name>/assets`.
2. Place content-specific files (custom CSS, hero images, custom JS) in `content/assets`.

**Conflict Resolution:** Files in `content/assets` will overwrite files with the same name in `templates/<template_name>/assets`. This allows you to override template styles or scripts on a per-site basis.

## Directory Structure

### Template Assets
If your template is named `oom`, organize your files like this:

```
templates/
  oom/
    assets/        <-- Template dependencies
      css/
        style.css
      js/
        app.js
      images/
        card_bg.jpg
    index.html.twig
```

### Content Assets
For site-specific assets:

```
content/
  assets/          <-- Site-specific overrides and images
    css/
      custom.css
    images/
      hero.jpg
  index.md
```

## Output Structure

When StaticForge builds your site, it merges both directories into `public/assets/`.

```
public/
  assets/
    css/
      style.css    (from template)
      custom.css   (from content)
    js/
      app.js       (from template)
    images/
      card_bg.jpg  (from template)
      hero.jpg     (from content)
  index.html
```

## Referencing Assets in Templates

Since the files are flattened into `public/assets/`, you should reference them in your Twig templates using the absolute path `/assets/...`.

```html
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="stylesheet" href="/assets/css/custom.css">
<script src="/assets/js/app.js"></script>
<img src="/assets/images/hero.jpg" alt="Hero Image">
```
---

# FILE: guide/auditing.md

# Site Auditing & Quality Assurance

StaticForge isn't just about building sites; it's about building *correct* sites. The system includes a comprehensive suite of audit tools designed to catch issues—from broken links to missing SEO tags—before your visitors do.

---

## Why Audit?

In the dynamic world of CMSs (like WordPress), errors often happen at runtime. In the static world, we have the luxury of checking everything *before* deployment.

StaticForge divides auditing into four distinct phases:
1.  **Configuration**: Is the environment set up correctly?
2.  **Content**: Is the source content valid?
3.  **Build**: Does the generated HTML work (links, images)?
4.  **Live**: Is the production server secure and performant?

---

## Phase 1: Configuration Audit

The `audit:config` command validates your project structure, environment variables (`.env`), and feature settings. It ensures you haven't missed critical settings like `SITE_BASE_URL`.

**When to run:** When setting up a new machine, deploying for the first time, or troubleshooting weird behavior.

```bash
php bin/staticforge.php audit:config
```

---

## Phase 2: Content Audit

The `audit:content` command scans your **source** markdown files (`content/`). It validates Frontmatter syntax and ensures required fields (like `title` and `layout`) are present.

**When to run:** While writing or integrating content from other people.

```bash
php bin/staticforge.php audit:content
```

---

## Phase 3: Link & SEO Audit

These checks happen **after** you run `site:render`. They check the final HTML output.

### Link Validation (`audit:links`)
This tool crawls your `output/` directory and checks every `<a>` tag.

*   **Internal Links**: Verifies that links to other pages on your site actually exist.
*   **External Links**: (Optional) Pings external websites to ensure they are still up.

**TLS Note:** External checks validate HTTPS certificates by default. If you are testing a local or self-signed site, use `--insecure`.

**Best Practice:** Run internal checks on every build. Run external checks weekly to prevent "link rot."

```bash
# Fast: Check internal links only
php bin/staticforge.php audit:links --internal

# Thorough: Check everything (may be slow)
php bin/staticforge.php audit:links

# Allow self-signed or local certs (not recommended for production checks)
php bin/staticforge.php audit:links --insecure
```

### SEO Validation (`audit:seo`)
Ensures your pages are search-engine friendly. It checks for:
*   Unique Page Titles
*   Meta Descriptions (presence and length)
*   Canonical URLs

```bash
php bin/staticforge.php audit:seo
```

---

## Phase 4: Live Site Audit

The `audit:live` command is unique because it checks your **hosted** website, not your local files. It verifies that your web server is sending the correct security headers.

**Checks performed:**
*   **HSTS**: Ensures SSL is enforced.
*   **X-Content-Type-Options**: Prevents MIME-sniffing attacks.
*   **X-Frame-Options**: Prevents clickjacking.

**When to run:** Immediately after deploying to production.

```bash
php bin/staticforge.php audit:live

# Allow self-signed or local certs (not recommended for production checks)
php bin/staticforge.php audit:live --insecure
```
---

# FILE: guide/cli-commands.md

# Command Line Interface

StaticForge is primarily a CLI tool. This section details the complete command reference.

## Contents

*   [Site Management](site-management.html) - Rendering and deploying (`site:*`).
*   [Content Creation](content-creation.html) - Scaffolding new content (`make:*`).
*   [Auditing](auditing.html) - Verifying site health (`audit:*`).
*   [System Commands](commands.html) - Utilities and debugging (`system:*`).
---

# FILE: guide/commands.md

# System Commands

While `site:render` builds your site and `site:upload` deploys it, StaticForge also includes a set of system utilities to help you manage your installation.

Think of these as the dashboard for your engine. They help you see what's running under the hood.

---

## Managing Features

StaticForge is built on a plugin architecture called "Features." Everything from RSS feeds to Sitemap generation is a feature.

### Checking Feature Status

Sometimes you need to know exactly what is running. Did you successfully disable the Sitemap? Is the CacheBuster active? The `system:features` command gives you a live look at your configuration.

```bash
php bin/staticforge.php system:features
```

This will output a clean table showing every available feature and whether it is currently **Enabled** or **Disabled** based on your `siteconfig.yaml`.

```text
+--------------------+----------+
| Feature Name       | Status   |
+--------------------+----------+
| CacheBuster        | Enabled  |
| Categories         | Enabled  |
| Sitemap            | Disabled |
| ...                | ...      |
+--------------------+----------+
```

**Pro Tip:** If you are old school, you can also use `system:plugins`. It does the exact same thing.
---

# FILE: guide/configuration.md

# Configuration Guide

Learn how to configure StaticForge to work exactly the way you want. This guide covers environment settings, directory structure, and built-in features.

---

## Environment Configuration

StaticForge uses a `.env` file for all configuration. This keeps your settings separate from your code and makes it easy to use different settings for development and production.

### Setting Up Your Configuration

1. Copy the example file:
   ```bash
   cp .env.example .env
   ```

2. Open `.env` in your text editor

3. Adjust the settings to match your needs

Here's what a typical `.env` file looks like:

```bash
# StaticForge Environment Configuration

# Site Information
SITE_BASE_URL="https://example.com"
TEMPLATE="staticforce"

# Directory Paths (relative to project root)
SOURCE_DIR="content"
OUTPUT_DIR="output"
TEMPLATE_DIR="templates"
FEATURES_DIR="src/Features"

# Optional Configuration
LOG_LEVEL="INFO"
LOG_FILE="logs/staticforge.log"

# SFTP Upload Configuration
UPLOAD_URL="https://www.mysite.com"
SFTP_HOST="example.com"
SFTP_PORT=22
SFTP_USERNAME="your-username"
SFTP_PASSWORD="your-password"
SFTP_REMOTE_PATH="/var/www/html"
```

> **Note:** Site Name and Tagline are configured in `siteconfig.yaml`, not `.env`. See the [Site Configuration Guide](site-config.html) for details.

---

## Required Settings

These settings **must** be present in your `.env` file or StaticForge won't run.

### SITE_BASE_URL

**What it does:** The full URL where your site will be hosted

**Why it matters:**
- Used for generating absolute URLs
- Critical for production deployments

**Examples:**
```bash
# For local development
SITE_BASE_URL="http://localhost:8000/"

# For production
SITE_BASE_URL="https://www.mysite.com/"
```

**Important:** Always include the trailing slash!

**Template usage:**
```twig
{# Use for assets only #}
<link rel="stylesheet" href="{{ site_base_url }}assets/css/style.css">
```

---

### SOURCE_DIR

**What it does:** Where StaticForge looks for your content files

**Default:** `content`

**Examples:**
```bash
SOURCE_DIR="content"      # Standard
SOURCE_DIR="src/pages"    # Custom location
SOURCE_DIR="docs"         # For documentation sites
```

**What goes here:** Your `.md` and `.html` files with frontmatter

---

### OUTPUT_DIR

**What it does:** Where StaticForge writes the generated HTML files

**Default:** `output` (common alternatives: `public`, `dist`, `build`)

**Examples:**
```bash
OUTPUT_DIR="output"   # Clean, simple
OUTPUT_DIR="public"   # Traditional web server convention
OUTPUT_DIR="dist"     # Common for build systems
```

**Important:** This is the directory you'll:
- Point your web server at
- Upload to your hosting provider
- Serve with `php -S localhost:8000 -t output/`

---

### TEMPLATE_DIR

**What it does:** Where your Twig templates live

**Default:** `templates`

**Example:**
```bash
TEMPLATE_DIR="templates"
```

**Directory structure:**
```
templates/
├── sample/          # Each subdirectory is a template
└── staticforce/
```

You typically won't change this unless you have a custom project structure.

---

### TEMPLATE

**What it does:** Which template to use from your TEMPLATE_DIR

**Default:** `sample`

**Built-in options:**
- `sample` - Clean, modern design
- `staticforce` - Documentation-focused

**Example:**
```bash
TEMPLATE="staticforce"
```

**How it works:** StaticForge looks for `templates/{TEMPLATE}/base.html.twig`

---

### FEATURES_DIR

**What it does:** Where StaticForge finds feature plugins

**Default:** `src/Features`

**Example:**
```bash
FEATURES_DIR="src/Features"
```

**What's in here:** Each subdirectory contains a feature like:
- `MenuBuilder/` - Builds navigation menus
- `Categories/` - Organizes content by category
- `MarkdownRenderer/` - Converts Markdown to HTML
- `HtmlRenderer/` - Processes HTML files

You won't typically change this setting.

---

## Optional Settings

These settings enhance StaticForge but aren't required to run.

### LOG_LEVEL

**What it does:** Controls how much information is logged

**Default:** `INFO`

**Options:**
- `DEBUG` - Everything (useful for troubleshooting)
- `INFO` - Standard operations (default)
- `WARNING` - Only potential issues
- `ERROR` - Only critical failures

**Example:**
```bash
LOG_LEVEL="DEBUG"
```

---

### LOG_FILE

**What it does:** Where to write log messages

**Default:** `logs/staticforge.log`

**Example:**
```bash
LOG_FILE="logs/site-build.log"
```

---

### UPLOAD_URL

**What it does:** The public URL used when uploading to production

**Why it matters:**
- Used by the `site:upload` command
- Ensures links are correct in the production environment

**Example:**
```bash
UPLOAD_URL="https://www.mysite.com"
```

---

## SFTP Configuration

These settings are used by the `site:upload` command to deploy your site.

### SFTP_HOST

**What it does:** The hostname or IP address of your server

**Example:**
```bash
SFTP_HOST="example.com"
```

### SFTP_USERNAME

**What it does:** The username to log in with

**Example:**
```bash
SFTP_USERNAME="deploy"
```

### SFTP_REMOTE_PATH

**What it does:** The directory on the server where files should be uploaded

**Example:**
```bash
SFTP_REMOTE_PATH="/var/www/html"
```

See the [Going Live](site-management.html#going-live) section for more details on setting up SFTP.

---

### LOG_LEVEL

**What it does:** Controls how much detail goes in the log file

**Options:**
- `DEBUG` - Everything (very verbose)
- `INFO` - Normal operations (recommended for development)
- `WARNING` - Only warnings and errors
- `ERROR` - Only errors
- `CRITICAL` - Only critical failures

**Example:**
```bash
LOG_LEVEL="INFO"
```

**Tip:** Use `DEBUG` when troubleshooting, `INFO` for development, and `WARNING` or `ERROR` for production.

---

### LOG_FILE

**What it does:** Where to save the log file

**Default:** `staticforge.log` (in the project root)

**Example:**
```bash
LOG_FILE="logs/staticforge.log"
```

**Note:** The directory must exist or StaticForge will create it.

---

### SHOW_DRAFTS

**What it does:** Controls whether draft content is included in the build

**Values:**
- `true`: Include files marked as `draft: true`
- `false` (default): Skip draft files

**Example:**
```bash
# Show drafts during development
SHOW_DRAFTS=true
```

---

### SITE_URL

**What it does:** The base URL for your site (alias for SITE_BASE_URL)

**Used by:**
- Sitemap Generator
- RSS Feed

**Example:**
```bash
SITE_URL="https://example.com"
```

---

## Directory Structure

Understanding how StaticForge organizes files helps you structure your content effectively.

### Input Structure (SOURCE_DIR)

```
content/
├── index.md              # Homepage (→ output/index.html)
├── about.md              # About page (→ output/about.html)
├── contact.html          # HTML page (→ output/contact.html)
├── blog/                 # Subdirectory
│   ├── post-1.md        # (→ output/blog/post-1.html)
│   └── post-2.md        # (→ output/blog/post-2.html)
└── docs/
    └── guide.md         # (→ output/docs/guide.html)
```

**Key points:**
- StaticForge recursively searches all subdirectories
- Directory structure IS preserved in output (unless using categories)
- Both `.md` and `.html` files are processed
- Files with `.md` extension become `.html` in output

---

### Output Structure (OUTPUT_DIR)

**Without categories:**
```
output/
├── index.html           # From content/index.md
├── about.html           # From content/about.md
├── blog/
│   ├── post-1.html
│   └── post-2.html
└── docs/
    └── guide.html
```

**With categories:**
```
output/
├── index.html
├── web-dev/             # Category subdirectory
│   ├── index.html      # Category index page
│   ├── article-1.html  # Has category = "web-dev"
│   └── article-2.html
└── tutorials/           # Another category
    ├── index.html
    └── beginner.html
```

---

## Built-in Features

StaticForge comes with several powerful features out of the box:

- **Markdown Renderer** - Converts `.md` files to HTML
- **HTML Renderer** - Processes `.html` files with templates
- **Menu Builder** - Automatically creates navigation menus
- **Categories** - Organizes content into subdirectories
- **Category Index** - Creates index pages for categories
- **Tags** - Extracts and manages content tags

For detailed information about each feature, including examples and template usage, see the **[Built-in Features Guide](../features/index.html)**.

### Quick Feature Reference

**Using features in your content:**

```markdown
---
title = "My Page"
menu = 1.1              # Add to navigation menu
category = "blog"       # Organize into category
tags = ["php", "web"]   # Add tags
---
```

**Disabling features:**

Delete or rename the feature's directory:

```bash
# Disable a feature
rm -rf src/Features/Categories

# Or disable temporarily
mv src/Features/Categories src/Features/Categories.disabled
```

**Creating custom features:**

See the [Feature Development Guide](../development/features.html) for step-by-step instructions.

---

## Production vs Development Settings

Here are recommended settings for different environments:

### Development `.env`

```bash
SITE_BASE_URL="http://localhost:8000/"
TEMPLATE="terminal"
OUTPUT_DIR="output"
LOG_LEVEL="DEBUG"
LOG_FILE="logs/dev.log"
```

### Production `.env`

```bash
SITE_BASE_URL="https://www.mysite.com/"
TEMPLATE="staticforce"
OUTPUT_DIR="public"
LOG_LEVEL="ERROR"
LOG_FILE="logs/production.log"
```

**Tip:** Use version control to track your `.env.example` but add `.env` to `.gitignore` to keep environment-specific settings out of your repository.
---

# FILE: guide/content-creation.md

# Content Creation

## Overview
StaticForge provides a convenient CLI command to generate new content files with pre-configured frontmatter. This ensures your files are correctly formatted and placed in the right directories without manual copy-pasting.

## The `make:content` Command

Use `make:content` to create a new Markdown file.

```bash
php vendor/bin/staticforge.php make:content "My Post Title"
```

### Options

| Option | Shorthand | Description | Default |
| :--- | :--- | :--- | :--- |
| `--type` | `-t` | Specify a subdirectory/category (e.g., `blog`, `docs`) | `(root content dir)` |
| `--date` | `-d` | Set a custom publish date (YYYY-MM-DD) | `(Today)` |
| `--draft` | `-D` | Mark the content as a draft | `false` |

### Examples

**Create a standard page:**
```bash
php vendor/bin/staticforge.php make:content "About Us"
# Creates: content/about-us.md
```

**Create a blog post:**
```bash
php vendor/bin/staticforge.php make:content "Release Notes v1.0" --type=blog
# Creates: content/blog/release-notes-v1-0.md
# Adds 'category: blog' to frontmatter
```

**Create a draft documentation page:**
```bash
php vendor/bin/staticforge.php make:content "Advanced Guide" --type=docs --draft
# Creates: content/docs/advanced-guide.md
# Adds 'draft: true' to frontmatter
```

## Structure of Generated Files

The command generates a file with valid YAML frontmatter and a starting header:

```markdown
---
title: "Release Notes v1.0"
date: "2026-02-12"
category: "blog"
---

# Release Notes v1.0

Write your content here...
```
---

# FILE: guide/frontmatter.md

# Frontmatter Guide

Frontmatter is the metadata block at the top of your content files. It tells StaticForge how to handle your content, what template to use, and provides data to your templates.

## The Basics

Every content file in StaticForge (Markdown or HTML) starts with a frontmatter block. This block is written in YAML and is enclosed by three dashes `---`.

```markdown
---
title: "My Awesome Page"
description: "This is a description for SEO"
template: "standard_page"
---
```

### Required Fields

While you can put anything you want in your frontmatter, there are a few fields that StaticForge looks for:

*   **`title`**: The title of your page. This is usually used in the `<title>` tag and `<h1>` of your template.
*   **`template`**: (Optional) The name of the Twig template to use (without `.html.twig`). If omitted, it defaults to `base` or whatever your template uses as default.
*   **`menu`**: (Optional) If you want this page to appear in a menu, specify the position here (e.g., `'1.0'`, `'2.1'`).

## It's Just Data

The most powerful thing about frontmatter is that **it is just data**. You are not limited to the standard fields. You can add any custom data using standard YAML formatting, and it will be available in your Twig templates.

For example, if you are using an AI image generator like Midjourney to create hero images for your blog posts, you might want to store the prompt you used right in the file for future reference.

```markdown
---
title: "The Future of AI"
midjourneyPrompt: "A futuristic city with flying cars, cyberpunk style, neon lights --ar 16:9"
---
```

In your template, you can then access this data. If the data is not used by any feature or template, it is simply ignored.

```twig
{% if midjourneyPrompt %}
  <!-- Image generated with: {{ midjourneyPrompt }} -->
{% endif %}
```

## Feature Configuration

Many StaticForge features rely on frontmatter to work. Here are a few common examples:

### Tags & Categories
Organize your content by adding tags or categories.

```markdown
---
category: "blog"
tags:
  - php
  - static-site
  - tutorial
---
```

### SEO Metadata
Control how your page looks in search engines and social media.

```markdown
---
description: "A detailed guide to StaticForge frontmatter."
image: "/assets/images/frontmatter-guide.jpg"
author: "Cal Evans"
---
```

### Draft Status
Prevent a page from being published by marking it as a draft.

```markdown
---
draft: true
---
```

## HTML Files

You can also use frontmatter in `.html` files! Just wrap the YAML block in an HTML comment:

```html
<!--
---
title: "Custom HTML Page"
template: "landing"
---
-->
<h1>Welcome</h1>
```

This allows you to use StaticForge's powerful templating system even when you need to write raw HTML.
---

# FILE: guide/index.md

# User Guide

Welcome to the StaticForge User Guide. This section will walk you through everything from installation to deployment.

## What is a "Page"?

In StaticForge, a "page" is just a text file. You don't need a database. You write your content in a simple file, add a little metadata at the top, and save it.

Here is an example of what a blog post looks like:

```yaml
---
title: My First Post
date: 2023-10-01
description: "This is a summary of my post"
template: default
---

# Hello World

This is the content of my page. I can use **bold** text, *italics*, and lists.

*   Item 1
*   Item 2
```

The part between the `---` lines is the **Frontmatter** (metadata). The rest is your content. That's it!

If you are new to writing in this format, check out the original [Markdown Syntax Guide](https://daringfireball.net/projects/markdown/syntax) by John Gruber. It covers everything you need to know.

## Contents

*   [Quick Start](quick-start.html) - Installation and building your first page.
*   [Local Configuration](configuration.html) - Setting up your environment (`.env`).
*   [Site Configuration](site-config.html) - Configuring your site (`siteconfig.yaml`).
*   [System Commands](commands.html) - Utility and reference commands.
*   [Frontmatter Guide](frontmatter.html) - How to add metadata to your content.
*   [CLI Commands](cli-commands.html) - Reference for rendering, auditing, and system commands.*   Control exactly where your page appears in the menu

---

## Building & Deploying

Ready to show the world?

### [Command Reference](commands.html)
StaticForge is a CLI-first tool, which means you have a lot of power at your fingertips. This reference page documents every command available to you, including:
*   `site:render`: The command that builds your site.
*   `site:devserver`: A built-in server to preview your work locally.
*   `site:upload`: The magic command that deploys your site to production via SFTP.

---

## Next Steps

Once you've mastered the basics, you can start exploring the really cool stuff:

*   **[Features](../features/index.html)**: Discover built-in superpowers like Search, Forms, and SEO generators.
*   **[Development](../development/index.html)**: Want to go deeper? Dive into the code to create custom templates, build your own features, or even contribute to the core.
---

# FILE: guide/quick-start.md

# Quick Start Guide

Ready to build something fast? You're in the right place. This guide will take you from zero to a fully functional static site in just a few minutes. We like to call it **The 2-Minute Install**.

## What You'll Need

Just a few things before we start:

- **PHP 8.4 or higher** installed on your system
- **Composer** (PHP's package manager)
- A text editor (VS Code, Sublime, or your favorite)
- A command line/terminal

> **Using Lando?** We've included a `.lando.yml` file for Lando users, but this guide focuses on the standard PHP setup. Check the project README for Lando-specific instructions.

---

## Installation

### Step 1: Get the Files

First, let's create a home for your new project and pull in StaticForge via Composer.

```bash
mkdir my-static-site
cd my-static-site
composer require eicc/staticforge
```

### Step 2: Initialize Your Project

Now, let's set the stage. Run the initialization command to set up your directory structure, configuration, and templates:

```bash
php vendor/bin/staticforge.php site:init
```

You will see output similar to this:

```text
StaticForge Initialization
==========================

[OK] Created directory: content
[OK] Created directory: templates
[OK] Created directory: public
[OK] Created configuration: .env
[OK] Created configuration: siteconfig.yaml
[OK] Installed default template: staticforce
[OK] Created sample content: content/index.md

Success! Your project is ready.
```

This command does the heavy lifting for you:
- Creates the necessary directories (`content/`, `templates/`, `public/`, etc.)
- Copies example configuration files (`.env.example` to `.env`, `siteconfig.yaml.example` to `siteconfig.yaml`)
- Installs bundled templates
- Creates a sample homepage so you're not starting with a blank screen

### Step 3: Configure Your Site (Optional)

Authentication secrets and environment-specific settings (like URLs) live in your `.env` file, but your site identity lives in `siteconfig.yaml`.

**1. Environment Settings (.env):**
Use this for things that change between environments (Dev vs Production).

```bash
SITE_BASE_URL="http://localhost:8000"
TEMPLATE="staticforce"
```

**2. Site Identity (siteconfig.yaml):**
Use this for public information about your site. Open `siteconfig.yaml` and edit:

```yaml
site:
  name: "My Static Site"
  tagline: "Built with ❤️ and PHP"
  description: "A super fast site built with StaticForge"
```

### Step 4: Generate Your Site

This is the moment of truth. Build your static site using the render command:

```bash
php vendor/bin/staticforge.php site:render
```

You'll see output confirming the generation:

```text
Building Site...
================

[+] Discovered 5 files
[+] Processing content/index.md... OK
[+] Processing content/about.md... OK
[+] Generating Sitemap... OK
[+] Generating RSS Feed... OK

[OK] Site generation complete! (0.42s)
```

Your site is now ready in the `public/` directory!

### Step 5: See It Live

Let's take a look at what you built. Start the built-in development server:

```bash
php vendor/bin/staticforge.php site:devserver
```

Open your browser to `http://localhost:8000` to see your new site!

---

## Creating Your First Page

StaticForge includes a starter homepage (`content/index.md`), but let's make your mark by creating a new page.

### Step 1: Create a Content File

You can create content files manually, or use the handy CLI command. Let's use the CLI:

```bash
php vendor/bin/staticforge.php make:content "Hello World"
```

This creates a new file at `content/hello-world.md`. Open it in your editor, and you'll see it's ready for you:

```markdown
---
title: "Hello World"
date: "2026-02-12"
---

# Hello World

Write your content here...
```

**Understanding the Structure:**

- **Lines 1-5** (between `---`) - This is the **frontmatter**. It contains metadata about your page using `key: "value"` YAML format.
- **Everything after** - This is your content, written in Markdown.

Feel free to edit the content to say whatever you like!

### Step 2: Regenerate Your Site

Now tell StaticForge to regenerate your site with the new page:

```bash
php vendor/bin/staticforge.php site:render
```

You'll see:

```text
✓ Site generation complete!
  Generated 2 pages
```

StaticForge just:
1. Read your content files
2. Converted the Markdown to HTML
3. Applied your chosen template
4. Saved them in the `public/` directory

### Step 3: View Your Page

If you started the local server in Step 5:

1. Open your browser
2. Go to `http://localhost:8000/hello-world.html`
3. See your beautiful new page! 🎉

**Not using the local server?** Just open `public/hello-world.html` directly in your browser.

### Step 4: Go Live

Built something you're proud of? It's time to share it.

StaticForge includes a smart deployment tool (`site:upload`) that handles everything for you. It optimizes your build for production and syncs only the changes to your server.

👉 **[See the Deployment Guide](site-management.html#going-live)** to configure your server and launch your site.

---

## Try Different Content Formats

StaticForge supports both Markdown and HTML. Let's try an HTML page for when you need more control.

Create `content/about.html`:

```html
<!--
---
title: "About Me"
description: "Learn more about me"
---
-->

<h1>About Me</h1>

<p>I'm building a static site with StaticForge!</p>

<h2>Why StaticForge?</h2>
<ul>
  <li>It's fast</li>
  <li>It's simple</li>
  <li>It's built with PHP</li>
</ul>
```

**Notice the difference:**
- HTML files use `<!-- ... -->` for frontmatter (inside an HTML comment)
- The content is plain HTML instead of Markdown

Generate your site again:

```bash
php vendor/bin/staticforge.php site:render
```

Now visit `http://localhost:8000/about.html` to see it!

---

## Adding Images and Assets

You can add images, custom CSS, or JavaScript to your site by placing them in the `content/assets` directory.

1. Create the directory structure:
   ```bash
   mkdir -p content/assets/images
   ```

2. Add an image (e.g., `my-photo.jpg`) to `content/assets/images/`.

3. Reference it in your content:
   ```markdown
   ![My Photo](/assets/images/sf_quickstart_hero.jpg)
   ```

StaticForge will automatically copy everything from `content/assets` to `public/assets` when you build your site.

---

## Adding Pages to Your Menu

Want your pages to show up in the navigation menu? Add a `menu` value to the frontmatter:

```markdown
---
title: "Blog"
menu: 1.1
---

# My Blog

Check out my latest posts!
```

**How menu positioning works:**
- `1.1` - First item in menu 1
- `1.2` - Second item in menu 1
- `2.1` - First item in menu 2 (footer menu)
- `1.1.1` - First dropdown item under menu 1, position 1

**Want a page in multiple menus?** Just list them with commas:

```markdown
---
title: "Contact"
menu: 1.5, 2.3
---
```

This puts your Contact page in the main menu AND the footer menu!

Regenerate your site to see the menu update!

---

## Organizing with Categories

Keep related content together using categories:

```markdown
---
title: "My First Blog Post"
category: "blog"
---

# My First Blog Post

This is a blog post about StaticForge!
```

StaticForge automatically:
- Creates a `blog/` directory
- Moves the page to `public/blog/my-first-blog-post.html`
- Groups all blog posts together

---

## What's Next?

**Congratulations!** 🎉 You've created your first static site with StaticForge!

### Want a Different Look?

The default template gets you started, but you can easily switch things up. You can install new templates via Composer:

```bash
composer require vendor/template-name
```

StaticForge copies the template to your `templates/` directory. Activate it by setting `TEMPLATE="template-name"` in your `.env` file.


### Quick Tips

**Regenerate after every change:** StaticForge doesn't watch for changes. Run `php vendor/bin/staticforge.php site:render` after editing content or templates.

**Try different templates:** Change `TEMPLATE` in `.env` to try out different templates - staticforce (default) or sample.

**Keep frontmatter simple:** Only add metadata you actually need. At minimum, just set a `title`.

**Use Markdown for content:** Markdown is easier to write and read than HTML. Save HTML for special layouts.

### Need Help?

- Check out the other documentation pages
- Look at the example content in the `content/` directory
- Explore the templates in `templates/` to see how they work

Happy site building! 🚀
---

# FILE: guide/site-config.md

# Site Configuration (siteconfig.yaml)

StaticForge supports an optional `siteconfig.yaml` file for defining site-wide configuration that can be safely committed to version control. Unlike `.env` which contains sensitive credentials, `siteconfig.yaml` contains non-sensitive site settings like menu definitions, site metadata, and other configuration.

## File Location

Place `siteconfig.yaml` in your project root directory (same level as `content/`, `templates/`, `.env`).

The file is **completely optional** - your site will work fine without it.

## Configuration Options

### Site Information

You can define your site's name and tagline here. These values are available in templates as `{{ site_name }}` and `{{ site_tagline }}`.

```yaml
site:
  name: "My Awesome Site"
  tagline: "Built with StaticForge"
```

### Static Menus

The primary use case for `siteconfig.yaml` is defining static menu items that don't correspond to content files. This is useful for:

- External links (e.g., to an ecommerce section not managed by StaticForge)
- Links to dynamic sections of your site
- Hardcoded navigation structure
- Footer menus, utility menus, etc.

#### Menu Syntax

```yaml
menu:
  top:
    Home: /
    About: /about
    Shop: /shop
    Contact: /contact
  footer:
    Privacy Policy: /privacy
    Terms of Service: /terms
    Contact Us: /contact
  utility:
    Login: /login
    Account: /account
```

#### Menu Structure

- **Named menus**: Each menu has a name (e.g., `top`, `footer`, `utility`)
- **Simple key/value pairs**: `Title: /url`
- **Order matters**: Items appear in the order defined in the YAML file

#### Template Access

Static menus are available in templates using the naming convention `menu_{name}`:

```twig
{# Top navigation #}
{{ menu_top }}

{# Footer navigation #}
{{ menu_footer }}

{# Utility menu #}
{{ menu_utility }}
```

#### HTML Output

Static menus generate the same HTML structure as content-based menus:

```html
<ul class="menu">
  <li><a href="/">Home</a></li>
  <li><a href="/about">About</a></li>
  <li><a href="/shop">Shop</a></li>
  <li><a href="/contact">Contact</a></li>
</ul>
```

### Static vs. Numbered Menus

StaticForge supports two types of menus:

**Numbered Menus** (from frontmatter):
- Defined in content file frontmatter: `menu: 1.5`
- Accessed as `{{ menu1 }}`, `{{ menu2 }}`, etc.
- Position-based ordering (1.0, 1.5, 2.0)
- Automatically discovered from content files

**Named Menus** (from siteconfig.yaml):
- Defined in `siteconfig.yaml` under `menu:`
- Accessed as `{{ menu_top }}`, `{{ menu_footer }}`, etc.
- YAML order determines display order
- Manually defined, not tied to content files

These are **completely separate** systems. Use numbered menus for content-based navigation and named menus for static/external links.

### Disabling Features

You can disable specific features (both core and custom) by adding them to the `disabled_features` list. This is useful for turning off functionality you don't need or troubleshooting issues.

```yaml
disabled_features:
  - WeatherShortcode
  - Sitemap
  - SomeOtherFeature
```

When a feature is disabled:
- It is not loaded by the system
- Its event listeners are not registered
- Other features that depend on it may also be skipped (if they use `requireFeatures`)

### Site Information

Configure site-wide metadata that appears in templates:

```yaml
site:
  name: "StaticForge"
  tagline: "Built with ❤️ and PHP"
  description: "A flexible static site generator"
  author: "Cal Evans"
```

### Forms Configuration

Define forms that can be embedded in your content using the `{{ form('name') }}` shortcode.

```yaml
forms:
  contact:
    provider_url: "https://eicc.com/f/"
    form_id: "YOUR_FORM_ID"
    challenge_url: "https://sendpoint.lndo.site/?action=challenge"
    submit_text: "Send Message"
    success_message: "Thanks! We've received your message."
    error_message: "Oops! Something went wrong. Please try again."
    fields:
      - name: "name"
        label: "Your Name"
        type: "text"
        required: true
        placeholder: "John Doe"
      - name: "email"
        label: "Email Address"
        type: "email"
        required: true
        placeholder: "john@example.com"
      - name: "message"
        label: "Message"
        type: "textarea"
        rows: 7
        required: true
```

See the [Forms Feature documentation](../features/forms.html) for full details.


```yaml
site:
  name: "My Awesome Site"
  tagline: "Building amazing things with PHP"
```

**Configuration Options:**

- `name`: Your site's name (appears in titles, headers, footers)
- `tagline`: A short phrase describing your site

**Template Access:**

```twig
<title>{{ title }} - {{ site_name }}</title>
<h1>{{ site_name }}</h1>
<p>{{ site_tagline }}</p>
```

`SITE_BASE_URL` should remain in `.env` as it's environment-specific (different for dev/staging/production).

### Chapter Navigation

Configure sequential navigation (previous/next links) for numbered menus:

```yaml
chapter_nav:
  menus: "2"           # Comma-separated menu numbers (e.g., "2,3")
  prev_symbol: "←"     # Symbol for previous link
  next_symbol: "→"     # Symbol for next link
  separator: "|"       # Separator between nav elements
```

**Configuration Options:**

- `menus`: Which numbered menus to generate chapter navigation for
  - Single menu: `"2"`
  - Multiple menus: `"2,3,4"`
  - Must be quoted as a string
- `prev_symbol`: Symbol/text for "previous" links (default: `←`)
- `next_symbol`: Symbol/text for "next" links (default: `→`)
- `separator`: Character between navigation elements (default: `|`)

**Fallback Behavior:**

If `chapter_nav` is not in `siteconfig.yaml`, the ChapterNav feature will look for these environment variables in `.env`:
- `CHAPTER_NAV_MENUS`
- `CHAPTER_NAV_PREV_SYMBOL`
- `CHAPTER_NAV_NEXT_SYMBOL`
- `CHAPTER_NAV_SEPARATOR`

**Migration Note:** Moving these settings from `.env` to `siteconfig.yaml` is recommended for better version control, but both locations are supported.

## Complete Example

```yaml
# siteconfig.yaml - Site-wide configuration

# Site Information
site:
  name: "My Awesome Site"
  tagline: "Building amazing things with PHP"

# Static menu definitions
menu:
  # Main navigation
  top:
    Home: /
    Products: /products
    Shop: https://shop.example.com  # External link
    Blog: /blog
    Contact: /contact

  # Footer navigation
  footer:
    About Us: /about
    Privacy Policy: /privacy
    Terms: /terms
    Sitemap: /sitemap.xml

  # User account menu
  account:
    Dashboard: /dashboard
    Settings: /settings
    Logout: /logout

# Chapter navigation for sequential page navigation
chapter_nav:
  menus: "2"           # Generate prev/next links for menu 2
  prev_symbol: "←"
  next_symbol: "→"
  separator: "|"
```

## Implementation Details

### Loading Process

1. **Bootstrap phase**: `siteconfig.yaml` is loaded during application bootstrap (after `.env`)
2. **Location search**: Checks current working directory, then application root
3. **Error handling**: Parse errors are logged but don't stop site generation
4. **Storage**: Configuration is stored in the container as `site_config`

### Menu Generation

1. **POST_GLOB event**: MenuBuilder processes static menus during the POST_GLOB event
2. **HTML generation**: Uses the same HTML generation logic as numbered menus
3. **Container storage**: Each named menu is stored as `menu_{name}` in the container
4. **Template access**: Templates can access menus via `{{ menu_{name} }}` variables

## Use Cases

### External Shop Example

You have a StaticForge site with an external ecommerce platform:

```yaml
menu:
  top:
    Home: /
    About: /about
    Products: /products  # StaticForge page
    Shop: https://shop.mysite.com  # External ecommerce
    Contact: /contact
```

### Separate Footer Menu

Different navigation for your footer:

```yaml
menu:
  top:
    Home: /
    Features: /features
    Pricing: /pricing
    Blog: /blog

  footer:
    Company: /about
    Careers: /careers
    Press: /press
    Legal: /legal
    Privacy: /privacy
    Contact: /contact
```

### Multi-Menu Template

Template using multiple named menus:

```twig
<!DOCTYPE html>
<html>
<head>
  <title>{{ title }}</title>
</head>
<body>
  <header>
    <nav class="main-nav">
      {{ menu_top }}
    </nav>
  </header>

  <main>
    {{ content }}
  </main>

  <footer>
    <nav class="footer-nav">
      {{ menu_footer }}
    </nav>
    <nav class="utility-nav">
      {{ menu_utility }}
    </nav>
  </footer>
</body>
</html>
```

### Search Configuration

Configure the search engine and behavior.

```yaml
search:
  # Search engine to use: 'minisearch' (default) or 'fuse'
  engine: minisearch

  # Paths to exclude from search index
  exclude_paths:
    - /tags/
    - /categories/
    - /404.html
```

## Version Control

**DO commit `siteconfig.yaml` to version control** - it contains no sensitive information.

**DO NOT commit `.env`** - it contains credentials and environment-specific settings.

## Troubleshooting

### Menu Not Appearing

1. Check file location (must be in project root)
2. Verify YAML syntax is valid
3. Check logs for parsing errors
4. Confirm menu name matches template variable (`menu_top` for `top:`)

### YAML Parse Errors

If your YAML is invalid:
- Error is logged but site generation continues
- Menu will not be available
- Check logs for specific parse error message

### Menu Name Conflicts

- Named menus use `menu_{name}` pattern
- Numbered menus use `menu{number}` pattern
- No conflicts possible between the two systems
---

# FILE: guide/site-management.md

# Site Management & Deployment

Building your site shouldn't be a chore. StaticForge gives you powerful tools to generate your pages locally and seamless ways to push them to the world.

Whether you are iterating on a single blog post or launching a major update, we've got you covered.

## Generating Your Site

At its heart, StaticForge is a compiler. It takes your raw content and templates and turns them into a beautiful, static website.

### The Heavy Lifter: `site:render`

When you're ready to see your whole site, this is the command you'll reach for. It processes everything: markdown files, assets, template logic, and more.

```bash
# Build everything
php vendor/bin/staticforge.php site:render
```

**Need a fresh start?**
Sometimes caches get stale or old files linger. Use the `--clean` flag to wipe the slate clean before rebuilding. This is especially useful for production builds.

```bash
php vendor/bin/staticforge.php site:render --clean
```

**Testing a new look?**
If you're experimenting with different templates, you can switch them on the fly without changing your configuration files.

```bash
php vendor/bin/staticforge.php site:render --template=experimental-template
```

---

## Going Live

So you've built a site you're proud of. Now what?

StaticForge includes a built-in "Smart Uploader." It's not just a dumb file copy; it understands your site.

### Why not just use FileZilla?

You certainly can! But `site:upload` does three critical things for you:

1.  **Production Build**: It automatically re-renders your site using your *production* URL (defined in `.env`), ensuring no `localhost` links leak into the wild.
2.  **Smart Sync**: It checks what's changed. It uploads new files and—crucially—**deletes old ones** that are no longer part of your site.
3.  **Safety**: It tracks its own files using a manifest, so it won't accidentally delete other files on your server (like your server logs or other applications).

### Configuration

Before you deploy, tell StaticForge where to go. Open your `.env` file and set up your credentials.

**Pro Tip:** We highly recommend using SSH Keys for authentication. It's more secure and means you don't have to put passwords in text files.

```bash
# Where is this site going to live?
UPLOAD_URL="https://www.mysite.com"

# Server Details
SFTP_HOST="example.com"
SFTP_USERNAME="your-username"
SFTP_REMOTE_PATH="/var/www/html"

# Authentication (Choose one)
SFTP_PRIVATE_KEY_PATH="/home/user/.ssh/id_rsa"
# OR
# SFTP_PASSWORD="your-password"
```

### Deploying

Once configured, going live is a single command.

```bash
php vendor/bin/staticforge.php site:upload
```

If you need to deploy to a staging server first, you can override the URL on the fly:

```bash
php vendor/bin/staticforge.php site:upload --url="https://staging.mysite.com"
```

> **Note:** The first time you run this, it will upload everything. Subsequent runs will only upload changes, making updates lightning fast.
*   **Safety**: It *only* touches files it knows about. It will not delete your manually uploaded `.htaccess` or images folders unless they were part of a previous build.

#### Troubleshooting
*   **Connection Failed**: Check hostname, port (22), and firewall rules.
*   **Permission Denied**: Ensure the SFTP user has write permissions to `SFTP_REMOTE_PATH`.
*   **SSH Keys**: Ensure your private key file has strict permissions (`chmod 600`).
---

# FILE: privacy.md

# Privacy Policy

Let's keep this simple.

We believe that privacy is important. Because of that, we built this site differently than most of the web.

## WHAT WE COLLECT

**Minimal Data.**

We respect your privacy and only collect what is necessary to improve this site.

*   **Analytics:** We use Google Analytics to understand how visitors engage with our site. This helps us improve the content and user experience.
*   **Cookies:** This site uses cookies to support Google Analytics. We do not use cookies for any other purpose.

## THE CONTACT FORM

If you use the contact form to send us a message, we will obviously receive your name, email address, and whatever you type in the message box.

*   We use this information only to read your message and reply to you.
*   We do not add you to any marketing lists.
*   We do not sell, trade, or give your information to anyone else.

## EXTERNAL LINKS

Sometimes we link to other websites (like YouTube, GitHub, or other projects). Once you leave this site, we can't control what those other sites do. Please check their privacy policies if you're concerned.

## CHANGES

If we ever decide to change this policy, we will update this page clearly.

Last Updated: January 6, 2026
---

