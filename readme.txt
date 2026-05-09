=== Image Generator – AI Featured Image, Thumbnail and Automatic Image Creator for WordPress ===
Contributors: beautifulplugins, kawsarahmedr
Tags: ai, ai image, image generator, featured image, block editor
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI image generator for WordPress. Auto-create featured images, post thumbnails & post images with templates or custom AI prompts.

== Description ==

**Image Generator** is the all-in-one **AI image generator and automatic featured image plugin for WordPress**. Create eye-catching **featured images, post thumbnails, hero banners, and social media images** in two clicks — directly inside the **Gutenberg block editor**, or automatically every time you publish a post or page.

Stop wasting hours on stock photo sites. Stop publishing posts with broken thumbnail spots. With **Image Generator** you can:

- **Generate AI images** from a custom prompt using **OpenAI DALL·E 3** (your own API key) — right inside the block editor.
- **Auto-generate featured images** from reusable **image templates** whenever a post, page, or custom post type is saved without one.
- **Insert AI-generated images** straight into Image and Media & Text blocks while you write.
- **Set a Featured Image** with a single click from the new **AI Featured Image** sidebar panel.

Whether you run a blog, news site, magazine, portfolio, affiliate site, online store, or membership platform, this plugin gives every post a polished, **SEO-friendly featured image** — without the manual work.

= Two Powerful Ways to Create Images =

1. **Inside the block editor (on demand)** — generate an image while you're writing and drop it straight into an Image block, a Media & Text block, or set it as the post's Featured Image.
2. **Automatic on publish (hands-off)** — when a post or page has no featured image, the plugin picks one of your saved templates and renders a custom thumbnail using the post title, your brand colors, fonts, and overlay images.

Every generated image — whether AI-created or template-rendered — is saved to the **WordPress Media Library** with proper alt text, so it works with any theme, page builder, CDN, image-optimization plugin, lazy loader, or SEO plugin.

= Why Featured Images Matter for SEO and Engagement =

- **Higher search rankings** — Google rewards visually rich content with better placement in search results and Google Discover.
- **Better click-through rates** — posts with attractive thumbnails get noticeably more clicks from search results, archive pages, and category listings.
- **More social shares** — Facebook, Twitter/X, LinkedIn, and Pinterest all use the featured image when someone shares your URL. No featured image = no preview = fewer clicks.
- **Pinterest traffic** — pin-worthy custom images can drive long-tail referral traffic for years.
- **Brand consistency** — reusable templates keep every post on-brand without hiring a designer.
- **Improved Core Web Vitals** — properly attached, optimized featured images load predictably and avoid layout shift.

= Perfect For =

- Bloggers who publish daily and never want to think about featured images again.
- News sites and magazines that need every article to have a thumbnail.
- WooCommerce stores that need product placeholder images.
- Affiliate marketers who want unique hero images for every roundup post.
- Agencies and freelancers managing multiple client sites.
- Membership and learning platforms that publish lots of lessons.
- Anyone who has ever shipped a post with a missing or generic thumbnail.

== Key Features ==

✅ **AI Image Generation with DALL·E**
Plug in your **OpenAI API key** (or define the `AIMG_API_KEY` constant in `wp-config.php`) and generate unique images from natural-language prompts — *"a sunlit forest path in autumn, photorealistic, soft lighting"* — without leaving WordPress.

✅ **Block Editor (Gutenberg) Integration**
Adds an **"AI Generate" sparkle button** to the toolbar of core Image and Media & Text blocks, plus a dedicated **AI Featured Image panel** in the document sidebar. No setup, no shortcodes — it just shows up.

✅ **Two Generation Modes in One Modal**
A single, unified modal lets editors switch between **Templates** (fast, on-brand, free) and **Custom Prompt** (AI-generated, unique). Pick the right tool for each post.

✅ **Unlimited Reusable Image Templates**
Build as many image templates as you want with custom **background colors, text colors, dimensions, fonts, and PNG overlays**. Each template is rendered server-side using PHP's GD library — no external dependency.

✅ **Automatic Featured Image on Save**
When a post, page, or custom post type is saved without a featured image, the plugin renders one from a random template using the post title. Hands-off, instant, every time.

✅ **Custom Post Type & WooCommerce Support**
Works with **posts, pages, WooCommerce products**, and any public custom post type that supports featured images — including LearnDash lessons, BuddyBoss content, Easy Digital Downloads products, and more.

✅ **Native WordPress Media Library**
Every image — AI or template — is **sideloaded into the Media Library** with attachment ID and alt text. So image optimization plugins (Smush, ShortPixel, Imagify, EWWW), CDNs (Cloudflare, BunnyCDN, KeyCDN), and SEO plugins (Yoast, Rank Math, AIOSEO) all see it as a regular attachment.

✅ **REST API for Headless & Custom Workflows**
Public endpoints (`/wp-json/aimg/v1/generate`, `/wp-json/aimg/v1/templates`) with capability checks (`edit_posts`, `upload_files`) and nonce protection — perfect for **headless WordPress, decoupled frontends, and bulk automation scripts**.

✅ **Developer-Friendly Filters**
Swap the AI endpoint, change the model, customize the request body, and hook into the generation pipeline with `aimg_generate_endpoint` and `aimg_generate_request_body` filters.

✅ **Translation Ready (i18n)**
Fully translatable via the bundled `.pot` file — both PHP and editor JavaScript strings are registered with `wp_set_script_translations`. Compatible with WPML, Polylang, Loco Translate, and TranslatePress.

✅ **Lightweight, Fast, and Secure**
No bloated dependencies. Uses native WordPress components, the GD library (already part of PHP), and the REST API. API keys can be stored outside the database via the `AIMG_API_KEY` constant.

✅ **No Coding Required**
Simple admin UI under **Image Generator** in the WordPress dashboard. Build a template, save your settings, you're done.

== How It Works ==

= Generate from the Block Editor (On Demand) =

1. Open any post or page in the **Gutenberg editor**.
2. Click the ✨ sparkle icon in the toolbar of an **Image** or **Media & Text** block — *or* open the **AI Featured Image** panel in the document sidebar.
3. Choose a tab in the modal:
   - **Templates** — pick a saved image template. The title text defaults to the current post title.
   - **Custom Prompt** — describe the image you want and press *Generate* (or Ctrl + Enter / ⌘ + Enter).
4. The image is generated, added to the Media Library, and **inserted into the block** or **assigned as the featured image** automatically.

= Auto-Generate Featured Images on Publish =

1. Write your post or page as normal.
2. Hit **Publish** or **Update**.
3. If you already set a featured image, nothing happens.
4. If you didn't, the plugin picks a random image template and renders a custom thumbnail from the post title — saved to the Media Library and attached as the post thumbnail.

== Use Cases ==

= Daily Bloggers =
Publish a post, hit Update, and walk away. Your readers and Google get a polished featured image every time, without you ever opening Canva.

= News and Magazine Sites =
Generate consistent on-brand thumbnails for breaking news pieces and category archives — no designer in the loop.

= WooCommerce Stores =
Auto-generate placeholder product images for new SKUs while real photos are still being shot. Then replace at your own pace.

= Affiliate Roundups and Listicles =
Use **Custom Prompt** mode to generate unique hero images for *"Best Standing Desks 2026"* style posts that don't look like every other affiliate site.

= Agencies and Multi-Site Networks =
Configure templates once, set them as defaults across all client sites, and stop fielding *"why doesn't this post have an image?"* tickets.

= Headless and Decoupled Sites =
Use the REST API endpoints to trigger image generation from a Next.js, Astro, or Gatsby frontend, a CLI script, or a CI pipeline.

== Image Generator vs. Manual Workflows ==

| Task | Without Image Generator | With Image Generator |
|---|---|---|
| Add a featured image to a new post | Find stock photo, download, upload, set as featured | Publish — done. |
| Generate a unique hero image | Open Midjourney/ChatGPT, save, upload, insert | Click ✨ in the toolbar, type a prompt, done. |
| Keep thumbnails on-brand across the site | Manual design work, every post | Build a template once. Reused forever. |
| Generate placeholders for 50 imported posts | Tedious manual upload | Bulk-saving triggers auto-generation. |

== Installation ==

= Automatic Installation =
1. Go to **Plugins → Add New** in your WordPress admin.
2. Search for **"Image Generator"** by BeautifulPlugins.
3. Click **Install Now**, then **Activate**.

= Manual Installation =
1. Download the plugin ZIP file.
2. Upload the `artificial-image-generator` folder to `/wp-content/plugins/`.
3. Activate **Image Generator** through the **Plugins** menu in WordPress.

= Setup =
1. Go to **Image Generator → Image Templates** and create at least one template (background color, text color, dimensions, optional overlay).
2. (Optional) Open **Image Generator → Settings** and add your **OpenAI API key** to enable AI prompt-based generation.
3. (Optional, more secure) Define the API key in `wp-config.php` instead:
   `define( 'AIMG_API_KEY', 'sk-your-key-here' );`
4. Done. Generate from the block editor, or just publish a post and let the plugin handle it.

== Frequently Asked Questions ==

= Do I need an API key to use this plugin? =
**No — only for AI prompt-based generation.** Template-based image creation, including the automatic featured image on publish, runs entirely on your own server using PHP's built-in GD library. No external services, no API calls, no recurring costs.

= Which AI image generation service is supported? =
Out of the box, the plugin calls **OpenAI's DALL·E 3** API. Developers can swap the endpoint, model, or request body via the `aimg_generate_endpoint` and `aimg_generate_request_body` filters — so you can point it at compatible services (Stability AI, self-hosted SDXL via a compatible proxy, etc.).

= How much does AI image generation cost? =
You pay OpenAI directly for usage. As of 2026, DALL·E 3 standard images cost roughly $0.04 per generation. The plugin itself is free; you only pay your AI provider for the prompts you trigger.

= Where is my OpenAI API key stored? =
By default, it's stored in the `aimg_settings` option in the WordPress database. **For maximum security**, define `AIMG_API_KEY` in `wp-config.php` instead — the settings UI will detect the constant and disable the input field, so the key never sits in the database.

= Will this plugin generate featured images for existing posts? =
Automatic generation only fires when a post is saved without a featured image. **For existing content**, open the post in the block editor and use the **AI Featured Image** panel to generate one on demand. Bulk generation across the entire site is on the roadmap.

= Does it work with the Gutenberg block editor? =
**Yes.** The block editor integration is the plugin's flagship feature. You'll find the ✨ button on Image and Media & Text block toolbars and a dedicated **AI Featured Image** panel in the document sidebar.

= Does it work with the Classic Editor, Elementor, Divi, Beaver Builder, or Bricks? =
The on-demand generation modal is built for Gutenberg. **Automatic featured image generation works with every editor**, because it hooks into the WordPress `save_post` action — so Classic Editor, Elementor, Divi, Beaver Builder, Bricks, and any other builder all benefit from auto-generated thumbnails.

= Does it work with WooCommerce products? =
**Yes.** Both the automatic generation and the editor integration apply to any public post type that supports featured images, including WooCommerce products, downloads, courses, and custom post types from third-party plugins.

= Does it work with SEO plugins like Yoast SEO, Rank Math, and All in One SEO? =
**Yes.** Generated images are stored as standard Media Library attachments with proper alt text, so SEO plugins can read them, expose them in Open Graph and Twitter Card tags, and include them in sitemaps.

= Will it slow down my site? =
**No.** Image rendering uses PHP's GD library (already loaded) and only runs when a post is saved without a featured image — or when you explicitly trigger it from the editor. There's no background polling, no cron jobs, and no frontend overhead.

= Is it compatible with multisite? =
Yes. Each site on the network can configure its own templates and API key.

= Is the plugin GDPR-friendly? =
Template-based generation runs entirely on your server — nothing leaves your site. AI prompt-based generation sends only the prompt you type to OpenAI; no visitor data, post content, or personal information is transmitted unless you put it in the prompt.

= Can I translate the plugin? =
**Yes.** The plugin is fully translation-ready with a bundled `.pot` file. Both PHP and JavaScript strings are translatable.

= How can I report a bug or request a feature? =
Use the [plugin support forum on WordPress.org](https://wordpress.org/support/plugin/artificial-image-generator). We read every post.

== Troubleshooting ==

- **"No API key configured" notice in the editor** — add a key under **Image Generator → Settings**, or define `AIMG_API_KEY` in `wp-config.php`.
- **Thumbnails not displaying on the frontend** — make sure your theme calls `add_theme_support( 'post-thumbnails' )` in `functions.php`.
- **AI generation fails or times out** — check that your server can make outbound HTTPS requests, that PHP's `max_execution_time` is at least 60 seconds, and that your OpenAI API key has billing enabled.
- **"No templates available" in the editor modal** — create at least one template under **Image Generator → Image Templates**.
- **Image looks wrong / wrong colors** — check your template's background color, text color, and overlay PNG transparency.
- **Cache plugins showing stale images** — clear page, object, and CDN caches after generating new images.
- **Compatibility issues** — temporarily disable other plugins to identify conflicts and let us know via the support forum.

== Best Practices ==

✅ Build **3–5 well-designed templates** so the random fallback always looks on-brand.
✅ Use **Custom Prompt** mode for hero images and standout posts; use **Templates** for everyday post thumbnails.
✅ Keep prompts **specific** — *"a sunlit forest path in autumn, photorealistic, soft lighting"* beats *"forest"*.
✅ Define `AIMG_API_KEY` in `wp-config.php` rather than storing it in the database.
✅ Pair this plugin with an image optimization plugin (Smush, ShortPixel, Imagify) so generated images load fast.
✅ Set a recognizable brand color and font in your templates so visitors associate the look with your site.

== Third-Party Assets and Licenses ==

= Roboto Font =
Author: Christian Robertson
License: Apache License 2.0
License URI: https://www.apache.org/licenses/LICENSE-2.0
Source: https://fonts.google.com/specimen/Roboto

The Roboto font is bundled under the Apache License 2.0. Full license text available at the URI above.

= AI Image Generation =
Prompt-based image generation calls a third-party API (**OpenAI** by default). You are responsible for the API key, usage costs, and compliance with the provider's terms of service. **No prompts or images are sent to any external service unless you explicitly trigger generation from a custom prompt.** Template-based generation never leaves your server.

== Privacy ==

- **Template-based generation** runs entirely on your server. No data is sent to third parties.
- **AI prompt-based generation** sends the prompt you type to OpenAI (or your configured endpoint). The plugin does not transmit post content, user data, or visitor information.
- The plugin does not set cookies, track users, or load any external scripts on the frontend.

== Screenshots ==
1. Block editor toolbar — generate an image directly into an Image block with one click.
2. AI Featured Image panel in the Gutenberg document sidebar.
3. Generation modal — Templates and Custom Prompt tabs in a single workflow.
4. Image Templates list screen.
5. Template editor — colors, dimensions, overlays, and live preview.
6. Plugin settings — defaults and AI API key configuration.

== Changelog ==

= 1.3.0 ( 9th May 2026 ) =
* New: Full **block editor (Gutenberg) integration** — toolbar button on Image and Media & Text blocks, plus an AI Featured Image panel in the document sidebar.
* New: Generate images directly inside the editor from a saved template or a custom AI prompt.
* New: **AI image generation via OpenAI DALL·E 3** (configurable API key, swappable endpoint and model via filters).
* New: REST API endpoints (`aimg/v1/generate`, `aimg/v1/templates`) with capability and nonce checks for headless and automation use.
* New: `AIMG_API_KEY` PHP constant for storing the API key outside the database.
* New: Featured-image generation now supports **every public post type**, including WooCommerce products and custom post types.
* Fix: Resolved minor bugs in the template image generation pipeline.
* Compatibility: Tested up to **WordPress 6.9**.

= 1.1.0 ( 3rd March 2026 ) =
* New: Unlimited image templates with custom styles, dimensions, and overlay images.
* Fix: Compatibility issues with the latest WordPress release.
* Enhance: Faster image generation with reduced server load.

= 1.0.0 ( 22nd December 2025 ) =
* Initial release.

== Upgrade Notice ==

= 1.3.0 =
Major release: block editor integration and AI prompt-based generation. Back up your site before updating and review the new Settings → AI Service section.

= 1.1.0 =
Major update with new features and improvements. Please backup your site before updating.

== Support and Feedback ==

Have questions, need help, or want to suggest a feature? We'd love to hear from you!
- **Plugin Support Forum:** [https://wordpress.org/support/plugin/artificial-image-generator](https://wordpress.org/support/plugin/artificial-image-generator)
- **Plugin Homepage:** [https://beautifulplugins.com/image-generator/](https://beautifulplugins.com/image-generator/)

== License ==
This plugin is released under the GPLv2 or later. See [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html) for details.

**Thank you for using Image Generator!**
We're dedicated to helping you create visually stunning, SEO-optimized posts and pages with ease — powered by AI when you want it, and reliable image templates when you don't.
