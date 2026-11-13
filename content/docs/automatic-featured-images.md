<!--
Title: Automatic Featured Images
Slug: automatic-featured-images
Parent: Image Generator Documentation (https://beautifulplugins.com/docs/image-generator/)
Permalink: https://beautifulplugins.com/docs/image-generator/automatic-featured-images/
SEO Title: Automatic Featured Images for WordPress Posts — Templates, AI or Stock Photos
Meta Description: Automatically generate a featured image for every WordPress post or page saved without one — from an image template, an AI image or a stock photo, with fallbacks and background processing.
Focus Keyword: automatically generate featured images WordPress
-->

# Automatic Featured Images

When a post or page is saved without a featured image, Image Generator creates one, saves it to your Media Library and sets it as the featured image. Posts that already have a featured image are never touched.

## Turning it on

Go to **Image Generator → Settings → Automatic Featured Images**:

1. Tick **Enable Post Thumbnails** and/or **Enable Page Thumbnails**.
2. Choose how images are made with **Generate With** (below).
3. Click **Save Changes**.

It works with every editor — block editor, Classic Editor, Elementor, Divi, Beaver Builder, Bricks — and with posts created by other plugins, the REST API or WP-CLI, because it runs whenever WordPress saves a post.

## Methods

| Generate With | What happens | Cost |
|---|---|---|
| **Image template** (default) | The post is drawn with a template, right away. | Free |
| **AI image** | An AI image is made from your **AI Prompt** in the background. | OpenAI usage |
| **Image template, AI if no template is available** | Template; AI only when you have no active template. | Free / OpenAI usage |
| **AI image, template if AI fails** | AI first; if it fails (no key, an error, the hourly limit), a template is used. | OpenAI usage |
| **Stock photo (Unsplash, Pexels or Pixabay)** | A stock photo is found with keywords from the post title, in the background. | Free |
| **Stock photo, template if none is found** | Stock photo; a template when no photo matches. | Free |

### Which template is used?

The one chosen under **Image Template** (or marked **Default** on the templates list). With **A random template**, each post gets one of your active templates at random — build a few so your archive has variety.

## Background generation

Template images are drawn instantly while the post saves. AI images, stock photos, and templates with a [stock or AI photo layer](https://beautifulplugins.com/docs/image-generator/photo-templates/) depend on an outside service, so they're made in a **background job** instead — saving a post never waits on one.

- The job starts right after the save. Action Scheduler (included with WooCommerce and many other plugins) or WP-Cron is used as a backup, so jobs finish even if the first attempt is interrupted.
- When many posts are published at once, their images are made one after another.
- In the block editor, the **AI Featured Image** panel shows *Generating featured image…* and the image appears when it's ready — no reload needed.

## When AI images are made

AI images cost money, so they're only made for posts that are **published**, **scheduled** or **private** — never for drafts. Write and save drafts freely; the image is created when you publish. Each automatic AI image counts against the post author's [hourly limit](https://beautifulplugins.com/docs/image-generator/ai-images/).

A failed AI image is **not retried on every save** (each retry would be a paid request). The AI Featured Image panel shows what went wrong, with a **Try again** button.

## Removing a generated image

If you remove a generated featured image from a post, the plugin remembers that and won't add a new one on the next save. You can still generate one on demand from the AI Featured Image panel.

## Existing posts

Automatic generation runs when a post is saved. For posts published before you installed the plugin, open them in the block editor and click **Generate** in the AI Featured Image panel — or simply update them.

## Which post types?

Automatic generation covers **posts** and **pages**. For other post types that support featured images — WooCommerce products, courses, custom post types — generate on demand from the [block editor or the Media Library](https://beautifulplugins.com/docs/image-generator/editor-integration/).

## What's saved

Each generated image is a normal Media Library attachment, attached to its post, with the post title as alt text. SEO plugins (Yoast SEO, Rank Math, AIOSEO), image optimizers and CDNs treat it like any other image. The plugin also records how each image was made (template, AI or stock, the prompt and model) — see the [Developer Reference](https://beautifulplugins.com/docs/image-generator/developers/).

## Related

- [Settings](https://beautifulplugins.com/docs/image-generator/settings/)
- [AI Images](https://beautifulplugins.com/docs/image-generator/ai-images/)
- [Stock Photos](https://beautifulplugins.com/docs/image-generator/stock-photos/)
