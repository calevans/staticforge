---
title: 'External Features'
description: 'Catalog of external, community, and official add-on features available via Composer for StaticForge.'
template: docs
menu: '3.2'
url: "https://calevans.com/staticforge/features/external-features.html"
og_image: "Bridge connecting two digital islands, plug connecting into a socket, data integration, seamless connection, glowing energy flow, --ar 16:9"
---
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
