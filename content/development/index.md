---
title: Developer Guide
description: 'Landing page for the StaticForge Developer Guide, covering architecture, events, features, and customization.'
template: docs
menu: '4.1'
og_image: "Developer guide book glowing on a dark desk, digital compass, map of code structures, technical schematic background, dramatic lighting, --ar 16:9"
---

# Developer Guide

So, you want to see how the sausage is made? You've come to the right place.

This isn't the "How do I write a blog post?" section. This is the **"How do I bend StaticForge to my will?"** section. Here, we pop the hood, void the warranty, and show you exactly how this machine works.

## Upgrading?

*   **[Migrating to 3.0](../migrating-to-3-0.html)**
    Have a custom Feature written against 2.x or earlier? The event contract changed. Start here before anything else on this page.

## The Blueprint

If you want to hack on the core or build your own plugins (Features), start here.

*   **[Architecture](architecture.html)**
    The big picture. How does a request become a static HTML file? It's not magic; it's a pipeline.

*   **[The Technology Stack](tech-stack.html)**
    The giants whose shoulders we stand on. PHP 8.5, Symfony Console, Twig, and more.

*   **[Bootstrap & Initialization](bootstrap.html)**
    The "Ignition Sequence." What actually happens when you type `bin/staticforge`?

*   **[Events](events.html)**
    The nervous system of StaticForge. If you want to change behavior, you need to know which synapse to zap.

## Extending the System

*   **[Feature Development](features.html)**
    Don't fork the core. Build a Feature. It's the plugin system that powers everything.

*   **[Extending SEO Audit](extending-seo-audit.html)**
    Add your own checks to `audit:seo` via the `SEO_AUDIT_PAGE` event.

*   **[Testing Your Code](testing.html)**
    Unit and integration tests for the Feature you just built — don't ship it untested.

*   **[Asset Manager](asset-manager.html)**
    The "Traffic Cop" for your CSS and JS. Stop worrying about dependency order.

## The Frontend

*   **[Template Development](templates.html)**
    How to make it pretty. Twig, inheritance, and the "Master Slide" concept.

*   **[Building Templates with AI](building-templates-with-ai.html)**
    Because writing HTML by hand is *so* 2010. Let the robots do the heavy lifting.
