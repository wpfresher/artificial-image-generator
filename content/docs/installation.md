<!--
Title: Installation
Slug: installation
Parent: Image Generator Documentation (https://beautifulplugins.com/docs/image-generator/)
Permalink: https://beautifulplugins.com/docs/image-generator/installation/
SEO Title: Installing Image Generator for WordPress — Requirements & Setup
Meta Description: How to install the free Image Generator plugin for WordPress, the server requirements (PHP GD and FreeType), and how to install the Image Generator Pro add-on.
Focus Keyword: install Image Generator WordPress plugin
-->

# Installation

## Requirements

- WordPress **6.0** or later
- PHP **7.4** or later
- The PHP **GD** extension with **FreeType** support — templates are drawn with it. It's standard on almost every host; if it's missing, template images can't be rendered (AI images and stock photos still work).
- (Optional) An [OpenAI API key](https://platform.openai.com/api-keys) for AI images
- (Optional) A free API key from [Unsplash](https://unsplash.com/oauth/applications), [Pexels](https://www.pexels.com/api/new/) or [Pixabay](https://pixabay.com/api/docs/) for stock photos

> **Not sure if your server has GD and FreeType?** Go to **Tools → Site Health → Info → Media Handling**. "GD version" should be listed. Your host can enable it if it's not.

## Installing the free plugin

**From your dashboard (recommended):**

1. Go to **Plugins → Add New**.
2. Search for **"Image Generator"** by BeautifulPlugins.
3. Click **Install Now**, then **Activate**.

**Manual upload:**

1. Download the ZIP from [WordPress.org](https://wordpress.org/plugins/artificial-image-generator/).
2. Go to **Plugins → Add New → Upload Plugin**, choose the ZIP, and click **Install Now**.
3. Activate the plugin.

After activation you'll find an **Image Generator** menu in your admin sidebar with **Image Templates** and **Settings**.

## Installing Image Generator Pro

The Pro add-on extends the free plugin — you need **both** active:

1. Make sure the free **Image Generator** plugin (v1.8.0 or later) is installed and active.
2. Download the Pro ZIP from your [BeautifulPlugins account](https://beautifulplugins.com/account/).
3. Go to **Plugins → Add New → Upload Plugin**, upload the ZIP, and activate it.
4. [Activate your license](https://beautifulplugins.com/docs/image-generator/license-activation/) to unlock the Pro features and automatic updates.

## Updating

The free plugin updates through WordPress.org like any other plugin. Pro updates appear on your Plugins screen once your license is active. Keep both plugins on their latest versions — Pro releases are matched to free releases.

Updates never change your existing images. Templates made before version 1.7.0 are converted to the Template Studio format in the background, and only when they will look exactly the same; any that can't be converted keep working as before and are listed under **Tools → Site Health**.

## Uninstalling

Deactivating or deleting the plugin keeps your templates and settings, so a reinstall picks up where you left off. To remove them when the plugin is deleted, tick **Remove Data on Uninstall** under **Image Generator → Settings** first. Images already in your Media Library are never removed.

## Next step

Head to the [Getting Started guide](https://beautifulplugins.com/docs/image-generator/getting-started/) to create your first template and switch on automatic featured images.
