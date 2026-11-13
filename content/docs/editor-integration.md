<!--
Title: Block Editor & Media Library
Slug: editor-integration
Parent: Image Generator Documentation (https://beautifulplugins.com/docs/image-generator/)
Permalink: https://beautifulplugins.com/docs/image-generator/editor-integration/
SEO Title: Generate Images in the WordPress Block Editor & Media Library — Image Generator
Meta Description: Generate featured images and post images inside the Gutenberg block editor and the Media Library — from templates, AI prompts or free Unsplash, Pexels and Pixabay stock photos.
Focus Keyword: AI image generator Gutenberg block editor
-->

# Block Editor & Media Library

Image Generator adds its tools where you already work: the block editor and the Media Library. Both open the same image modal.

## The AI Featured Image panel

In the block editor, open the document sidebar (**Post** tab) and find the **AI Featured Image** panel:

- **Generate** — create the featured image with the method set under **Settings → Automatic Featured Images**. When the post already has one, the button reads **Regenerate**; after a failure, **Try again**. Save the post first — the saved title is used. When the method is an AI one, the button only shows for users allowed to use AI, once an API key is set.
- **Choose a template, a stock photo or a prompt…** — open the image modal and pick exactly what you want.

The panel also shows the current featured image and the progress of background jobs, so an AI image or stock photo appears as soon as it's ready.

The panel shows on every post type that supports featured images — including WooCommerce products and custom post types.

## The ✨ button on blocks

Select an **Image** or **Media & Text** block and click the ✨ sparkle button in its toolbar. The image you pick in the modal is added to the Media Library and inserted into the block.

## The image modal

The modal has a tab for each image source. It remembers the tab you used last.

### Templates

Pick one of your active templates and see a preview with the post title. Change **Title text (optional)** to draw different text — handy for a shorter headline. Click **Generate** to create the image.

### Custom Prompt

Describe the image you want, or click **Write a prompt from this post** to build one from the post's title and excerpt with your AI Prompt setting. Then choose:

- **Shape** — square, landscape or portrait,
- **Style** — Photorealistic, Flat illustration, 3D render, Watercolor, Minimal line art, Cinematic, Isometric or none,
- **Quality** — higher costs more,
- **Variations** — up to 4.

Click **Generate** (or press Ctrl + Enter / ⌘ + Enter). With several variations, pick the one you want — the others are removed from the Media Library. The tab stays disabled until an OpenAI API key is set, and for users the **Who Can Generate AI Images** setting doesn't include (they see a note and can still use templates and stock photos). See [AI Images](https://beautifulplugins.com/docs/image-generator/ai-images/).

### Unsplash, Pexels and Pixabay

One tab per library that has an API key. The search starts with keywords from the post title; type your own, filter by **Orientation** and **Color**, and click **Load more** for more results. Pick a photo and click **Insert Photo** — it's downloaded to your Media Library with alt text and the photographer's credit. See [Stock Photos](https://beautifulplugins.com/docs/image-generator/stock-photos/).

## The Media Library

Go to **Media → Library** (or **Media → Add New**) and click **Generate Image**. The same modal opens, so you can create template, AI and stock images without opening a post — and then use them in the Classic Editor, Elementor, Divi, Beaver Builder, Bricks or anywhere else.

## Who sees the tools

The buttons appear for users who can upload files (Authors and above by default). The **Custom Prompt** tab follows the **Who Can Generate AI Images** setting. Contributors, who can't upload files, don't see generate buttons. Images can only be attached to posts the user is allowed to edit.

## Related

- [Automatic Featured Images](https://beautifulplugins.com/docs/image-generator/automatic-featured-images/)
- [AI Images](https://beautifulplugins.com/docs/image-generator/ai-images/)
