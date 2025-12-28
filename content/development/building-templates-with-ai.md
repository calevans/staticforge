---
menu: '4.1.6'
name: 'Building Templates with AI'
template: docs
---

# Building Templates with AI

Creating a custom template for StaticForge is a straightforward process, especially when you leverage the power of AI tools like GitHub Copilot. Because StaticForge uses standard technologies—PHP, Twig, and raw CSS—AI assistants are particularly good at generating high-quality themes for it.

This guide outlines a proven workflow for using AI to build your templates. The key is not to ask the AI to "make a website," but to give it a specific structural reference and a clear visual goal.

## The Strategy

The most effective way to build a StaticForge template with AI is to use the **Reference Implementation Strategy**. Instead of explaining every rule of the StaticForge templating engine to the AI, you simply point it to an existing, well-structured theme and say, "Do it like that, but look like this."

We use the `staticforce` theme as our "Gold Standard" reference. It demonstrates:
- Proper Twig inheritance (`extends 'base.html.twig'`)
- Correct block usage (`{% block body %}`)
- Asset organization
- Menu implementation

## Step-by-Step Workflow

### 1. Set the Context

Start by telling the AI exactly what you are trying to achieve. Be specific about the type of site and the tools involved.

> "I am building a new theme for a StaticForge site. This is a static site generator that uses Twig for templating. I want to create a theme named 'my-new-theme'."

### 2. Establish the Reference

This is the most critical step. You must ground the AI in the existing codebase so it understands the *structure* before it starts writing the *style*.

> "Before writing any code, I want you to examine the `templates/staticforce` directory. This is the reference implementation. Study how `base.html.twig` sets up the HTML shell and how other files like `standard_page.html.twig` extend it. Note how assets are linked and how the menus are rendered. Use this structure as the blueprint for my new theme."

### 3. Define the Visuals

Now that the AI understands *how* to build the code, tell it *what* to build. Since we avoid CSS frameworks to keep things lightweight and clean, you need to describe the visual style clearly.

> "I want the visual style to be [describe your style].
> - **Layout**: A clean, single-column layout for blog posts, but a two-column layout for documentation.
> - **Colors**: Dark mode by default, using deep blues and slate grays.
> - **Typography**: Large, readable serif fonts for headings and a clean sans-serif for body text.
> - **Constraints**: Do not use any CSS frameworks like Bootstrap or Tailwind. Write plain, efficient CSS. Use CSS Grid and Flexbox for layout."

### 4. Iterative Building

Don't ask for the entire theme at once. Build it piece by piece.

**Phase 1: The Base**
Ask the AI to create the `base.html.twig` file first. This ensures the foundation is correct.
> "Start by creating the `base.html.twig` for my new theme. It should include the HTML skeleton, the main navigation bar, and the footer. Ensure it defines the necessary blocks for child templates to fill in."

**Phase 2: The Homepage**
Once the base is ready, ask for the homepage.
> "Now create `index.html.twig`. It should extend `base.html.twig`. I want a large hero section at the top with a call to action, followed by a grid of recent features."

**Phase 3: Inner Pages**
Finally, create the generic page template.
> "Create `standard_page.html.twig` for handling regular content pages. It should also extend `base.html.twig` but keep the layout simple and focused on readability."

## Tips for Success

*   **CSS Organization**: Ask the AI to organize CSS logically. For example, "Put all layout styles in `layout.css` and all component styles in `components.css`," or simply "Keep all CSS in `style.css` but use comments to section it clearly."
*   **Mobile Responsiveness**: Explicitly remind the AI to make it responsive. "Ensure the navigation collapses into a hamburger menu on mobile devices."
*   **Asset Paths**: If the AI gets confused about linking assets, remind it: "Remember to use the `site_base_url` variable for all CSS and image links, just like in the `staticforce` reference."

By following this narrative approach, you allow the AI to handle the heavy lifting of coding while you act as the architect, ensuring the final result is structurally sound and visually unique.
