<!--
Title: AI Images
Slug: ai-images
Parent: Image Generator Documentation (https://beautifulplugins.com/docs/image-generator/)
Permalink: https://beautifulplugins.com/docs/image-generator/ai-images/
SEO Title: AI Image Generation in WordPress with OpenAI GPT Image — Image Generator
Meta Description: Generate AI images in WordPress with OpenAI GPT Image — set up your API key, choose a model, write prompts with tags and styles, control quality and cost, and limit who can use AI.
Focus Keyword: OpenAI image generation WordPress
-->

# AI Images

Image Generator creates unique images with **OpenAI GPT Image**, using your own OpenAI API key. Use them in the editor, from the Media Library, as automatic featured images, or as the photo inside a [template](https://beautifulplugins.com/docs/image-generator/photo-templates/).

## Setting up your API key

1. Sign in at [platform.openai.com](https://platform.openai.com/) and create an API key under **API keys**.
2. Add a payment method to your OpenAI account — image generation needs billing, even for small amounts.
3. Go to **Image Generator → Settings → AI Service**, paste the key into **API Key**, and click **Save Changes**.

After saving, only the last 4 characters of the key are shown. Leave the field empty to keep it, enter a new key to replace it, or tick **Remove the saved API key**.

**More secure:** define the key in `wp-config.php` instead, so it's never stored in the database:

```php
define( 'AIMG_API_KEY', 'sk-your-key-here' );
```

The settings page detects the constant and disables the field.

## Models

Choose under **Settings → AI Service → Image Model**:

| Model | Best for |
|---|---|
| **GPT Image 2.5 Flare** (default) | Fast, everyday images at a lower cost |
| **GPT Image 2.5 Sunburst** | OpenAI's most capable model, for hero images |
| **GPT Image 2** | The previous generation |

Models OpenAI has retired (GPT Image 1, DALL·E 2 and DALL·E 3) are no longer offered; sites that used them switch to GPT Image 2.5 Flare automatically.

## Writing prompts

In the editor's **Custom Prompt** tab, describe the image — the more specific, the better. *"a sunlit forest path in autumn, photorealistic, soft lighting"* beats *"forest"*. Click **Write a prompt from this post** to start from the post's title and excerpt.

For automatic images, the **AI Prompt** setting is a template filled in for each post. The default is:

> A featured image for an article titled "{title}". The article is about: {excerpt}

Use `{title}`, `{excerpt}`, `{category}`, `{tags}`, `{site_name}` and `{custom_field:key}` — see [Merge Tags](https://beautifulplugins.com/docs/image-generator/merge-tags/). Two more settings shape every automatic prompt:

- **AI Style** — Photorealistic (default), Flat illustration, 3D render, Watercolor, Minimal line art, Cinematic, Isometric or None. Each adds a short style description to the prompt.
- **AI Instructions** — added to the end of every prompt. The default, *"Do not include any text, letters, words, logos or watermarks in the image."*, avoids the garbled text AI models tend to draw. Want text on the image? Use a [photo template](https://beautifulplugins.com/docs/image-generator/photo-templates/) and add the title as a text layer — it's always spelled right.

## Shape and quality

- **Shape** — square, landscape or portrait. Set the default for the editor under **Default Image Shape** and for automatic images under **AI Image Shape**; it can be changed per image in the editor.
- **Quality** — Automatic, Low (cheapest), Medium, High, Extra high or Maximum (most expensive). Extra high and Maximum need GPT Image 2.5; GPT Image 2 uses High instead.
- **Variations** — in the editor, generate up to 4 at once and keep the one you like; the rest are removed from the Media Library.

High-quality images can take a while; the plugin waits up to 3 minutes for OpenAI.

## Controlling costs

You pay OpenAI directly, per image, at their [current prices](https://openai.com/api/pricing/) — larger shapes, higher quality and more variations cost more. The plugin adds no markup. Three settings keep spending predictable:

- **Who Can Generate AI Images** — Authors and above (default), Editors and above, or Administrators only. Templates and stock photos stay available to everyone who can upload files.
- **Hourly Limit per User** — default 20 AI images per user per hour; 0 for no limit. Automatic AI images count against the post author's limit.
- **Drafts are free** — automatic AI images are only created once a post is published or scheduled, and a failed image isn't retried on every save.

> **Tip:** Use templates for everyday posts and save AI for standout articles — or use **Image template, AI if no template is available** as your automatic method.

## Privacy

Only the prompt is sent to OpenAI: the text you type, or — for automatic images and **Write a prompt from this post** — the prompt built from your AI Prompt setting, which by default includes the post's title and excerpt. No visitor or user data is sent. Nothing is sent at all until you add an API key and generate. See OpenAI's [terms of use](https://openai.com/policies/terms-of-use) and [privacy policy](https://openai.com/policies/privacy-policy).

## Other AI services

Developers can point the plugin at another OpenAI-compatible service with the `aimg_generate_endpoint` and `aimg_generate_request_body` filters, or register a completely different provider with `aimg_providers` — see the [Developer Reference](https://beautifulplugins.com/docs/image-generator/developers/).

## Related

- [Automatic Featured Images](https://beautifulplugins.com/docs/image-generator/automatic-featured-images/)
- [Block Editor & Media Library](https://beautifulplugins.com/docs/image-generator/editor-integration/)
