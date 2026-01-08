---
title: Developer Guide
description: 'Landing page for the StaticForge Developer Guide, covering architecture, events, features, and customization.'
template: docs
menu: '4.1'
url: "https://calevans.com/staticforge/development/index.html"
og_image: "Developer guide book glowing on a dark desk, digital compass, map of code structures, technical schematic background, dramatic lighting, --ar 16:9"
---

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
