---
title: 'Technology Stack'
description: 'Overview of the technologies, libraries, and tools that power the StaticForge generator.'
template: docs
menu: '4.1.7'
url: "https://calevans.com/staticforge/development/tech-stack.html"
og_image: "Modern technology stack layers, isometric server rack, PHP logo, database icons, glass morphism style, sleek data center background, --ar 16:9"
---

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
