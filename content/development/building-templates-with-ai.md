---
menu: '4.1.6'
title: 'AI-Assisted Design'
description: 'Guide to creating custom StaticForge themes and templates using AI assistance and Copilot.'
template: docs
url: "https://calevans.com/staticforge/development/building-templates-with-ai.html"
---

# AI-Assisted Design: Building Templates with a Co-Pilot

So, you want to build a custom theme for StaticForge, but you don't want to write every single `<div>` and CSS class by hand.

Good news: StaticForge speaks the same language as your AI assistant. Because we use standard, boring technologies—PHP, Twig, and raw CSS—tools like GitHub Copilot and ChatGPT are surprisingly good at generating high-quality themes for us.

This guide isn't just a tutorial; it's a "cheat code" for building themes fast.

---

## The "Copycat" Strategy

The biggest mistake people make with AI is asking it to "make a website." That's too vague. The AI will hallucinate a bunch of complex frameworks you don't need.

The secret is the **Reference Implementation Strategy**.

Instead of teaching the AI how StaticForge works from scratch, you simply point it to our "Gold Standard" theme (`staticforce`) and say:

> *"See this? Do it exactly like that, but make it look like [Your Vision]."*

---

## The Workflow: From Zero to Hero

Here is the exact workflow we use to build themes in minutes, not days.

### Step 1: The Briefing (Set the Context)

First, you need to orient the AI. Tell it what tools we are using so it doesn't try to give you React or Vue code.

**The Prompt:**
> "I am building a new theme for a static site generator. We are using **Twig** for templating and **Raw CSS** (no frameworks). I want to create a theme named 'my-new-theme'."

### Step 2: The Blueprint (Grounding)

This is the most critical step. You must force the AI to look at the existing code structure.

**The Prompt:**
> "Before writing any code, I want you to examine the `templates/staticforce` directory. This is the reference implementation.
>
> Study how `base.html.twig` sets up the HTML shell. Note how `standard_page.html.twig` extends it. Look at how the assets are linked using `{{ site_base_url }}`.
>
> Use this structure as the blueprint for my new theme."

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

Don't ask for the whole site at once. The AI will get overwhelmed and give you garbage. Build it piece by piece.

### Phase 1: The Skeleton (`base.html.twig`)

This is your master template. Get this right first.

> "Start by creating the `base.html.twig`. It needs:
> 1. The HTML5 boilerplate.
> 2. A sticky navigation bar.
> 3. A footer with copyright info.
> 4. The `{% block body %}` where content will go."

### Phase 2: The Homepage (`index.html.twig`)

> "Now create `index.html.twig`. It should extend `base.html.twig`. Give me a big Hero section with a 'Get Started' button, and a grid of 3 feature boxes below it."

### Phase 3: The Content (`standard_page.html.twig`)

> "Create `standard_page.html.twig` for my blog posts. It should extend `base.html.twig`. Keep it simple: just a title (`<h1>`) and the content block."

---

## Pro Tips for Smooth Sailing

*   **The "No Framework" Rule**: AI loves Tailwind. If you don't want a 5MB CSS file, explicitly tell it: *"Write raw CSS only."*
*   **Asset Paths**: AI often forgets our specific variable names. If links break, remind it: *"Use `{{ site_base_url }}` for all links and images."*
*   **Mobile First**: Remind it to make things responsive. *"Make sure the nav bar turns into a hamburger menu on mobile."*

By following this script, you act as the **Architect**, and the AI acts as the **Bricklayer**. You provide the vision and the blueprints; it does the heavy lifting.
