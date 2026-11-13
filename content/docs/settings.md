<!--
Title: Settings
Slug: settings
Parent: Image Generator Documentation (https://beautifulplugins.com/docs/image-generator/)
Permalink: https://beautifulplugins.com/docs/image-generator/settings/
SEO Title: Image Generator Settings Explained — Featured Images, AI & Stock Photos
Meta Description: Every Image Generator setting explained — default colors, automatic featured images, the AI service (OpenAI key, model, shape, quality, limits) and stock photo libraries.
Focus Keyword: Image Generator settings
-->

# Settings

All settings live under **Image Generator → Settings**, in four sections. Click **Save Changes** at the bottom to save them.

## General Settings

- **Default Background Color** — the background color new templates start with. Existing templates keep the colors they were saved with; change those in the Template Studio.
- **Default Text Color** — the title color new templates start with.
- **Remove Data on Uninstall** — delete your templates and settings when the plugin is deleted. Off by default, so a reinstall keeps everything. Images already in the Media Library are never removed.

## Automatic Featured Images

When a post or page is saved without a featured image, one is generated for it. See [Automatic Featured Images](https://beautifulplugins.com/docs/image-generator/automatic-featured-images/).

- **Enable Post Thumbnails / Enable Page Thumbnails** — turn automatic generation on for posts and for pages.
- **Generate With** — the method:
  - *Image template* (default) — instant and free.
  - *AI image* — created in the background with your OpenAI key.
  - *Image template, AI if no template is available*
  - *AI image, template if AI fails*
  - *Stock photo (Unsplash, Pexels or Pixabay)* — found with keywords from the post title.
  - *Stock photo, template if none is found*
- **Image Template** — the template automatic images use, or *A random template* from your published templates. You can also set it with **Set as default** on the templates list.
- **AI Image Shape** — square, landscape or portrait for automatic AI images.
- **AI Prompt** — the prompt template for automatic AI images. Leave it empty to use the default (*A featured image for an article titled "{title}". The article is about: {excerpt}*). Available tags are listed below the field — see [Merge Tags](https://beautifulplugins.com/docs/image-generator/merge-tags/).
- **AI Style** — a style added to the prompt: None, Photorealistic, Flat illustration, 3D render, Watercolor, Minimal line art, Cinematic or Isometric.
- **AI Instructions** — added to the end of every automatic AI prompt. The default asks for no text, letters, logos or watermarks, because AI models often draw garbled text.
## AI Service

The AI service used for prompts in the editor and the Media Library, and for automatic AI featured images. See [AI Images](https://beautifulplugins.com/docs/image-generator/ai-images/).

- **API Key** — your OpenAI API key. After saving, only the last 4 characters are shown; leave the field empty to keep your key, or tick **Remove the saved API key**. If `AIMG_API_KEY` is defined in `wp-config.php`, the field is disabled and the constant is used.
- **Image Model** — GPT Image 2.5 Flare (default, fast, suited to everyday images), GPT Image 2.5 Sunburst (OpenAI's most capable) or GPT Image 2.
- **Default Image Shape** — the shape the editor's Custom Prompt tab starts with; it can be changed per image.
- **Image Quality** — Automatic, Low (cheapest), Medium, High, Extra high or Maximum (most expensive). Extra high and Maximum need GPT Image 2.5; GPT Image 2 uses High instead.
- **Who Can Generate AI Images** — Authors and above (default), Editors and above, or Administrators only. Templates and stock photos stay available to everyone who can upload files.
- **Hourly Limit per User** — the maximum number of AI images each user can generate per hour (default 20; 0 for no limit).

## Stock Photos

Search free photos from Unsplash, Pexels and Pixabay in the editor and the Media Library, or use them for automatic featured images. See [Stock Photos](https://beautifulplugins.com/docs/image-generator/stock-photos/).

- **Unsplash / Pexels / Pixabay API Key** — one field per library, each with a **Test Connection** button. Keys can also be defined as `AIMG_UNSPLASH_KEY`, `AIMG_PEXELS_KEY` and `AIMG_PIXABAY_KEY` in `wp-config.php`.
- **Library for Automatic Images** — which library automatic stock featured images come from, or *The first library with a key*.
- **Photo Orientation** — Landscape (default), Portrait or Square, for automatic stock images.
- **Import Size** — Regular (about 1080–1900 px wide), Large (up to 2400 px wide, default) or Original (largest file).
- **Credit** — add the photographer credit to the image caption. The credit is always saved with the image, even when this is off.

## Keeping keys out of the database

For maximum security, define your keys in `wp-config.php` instead of saving them on this page:

```php
define( 'AIMG_API_KEY', 'sk-your-key-here' );
define( 'AIMG_UNSPLASH_KEY', 'your-unsplash-access-key' );
define( 'AIMG_PEXELS_KEY', 'your-pexels-key' );
define( 'AIMG_PIXABAY_KEY', 'your-pixabay-key' );
```

The settings page detects each constant and disables its field.

## Related

- [Getting Started](https://beautifulplugins.com/docs/image-generator/getting-started/)
- [Automatic Featured Images](https://beautifulplugins.com/docs/image-generator/automatic-featured-images/)
