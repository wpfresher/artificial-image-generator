<!--
Title: Photo Templates
Slug: photo-templates
Parent: Image Generator Documentation (https://beautifulplugins.com/docs/image-generator/)
Permalink: https://beautifulplugins.com/docs/image-generator/photo-templates/
SEO Title: Photo Templates — Branded Featured Images from Stock Photos & AI Images
Meta Description: Combine a stock photo or an AI image with your own template design in Image Generator, so every WordPress post gets a different picture with your logo, colors and title on top.
Focus Keyword: branded featured images stock photos
-->

# Photo Templates

A photo template puts a different picture under the same design for every post: a **stock photo** from Unsplash, Pexels or Pixabay, or an **AI image** made for the post — with your logo, colors and title on top. You get the variety of photos and the consistency of a brand.

## Creating one

The quickest way: **Image Generator → Image Templates → Add New**, then pick **Stock photo with headline** or **Stock photo with card**.

To turn any template into a photo template:

1. Select the **Background** layer (or an **Image** layer).
2. Set **Fill with** to an image, and choose **Stock photo** or **AI image** as its source.
3. For a stock photo, set **Search for** — the default `{title}` searches with keywords from the post title. Merge tags work here, so `{category}` or `{custom_field:photo_keywords}` are good options too.
4. For an AI image, write an **AI prompt**, or leave it empty to use the **AI Prompt** from Settings.
5. Add an **Overlay** layer (a see-through color or gradient fade) above the photo so your text stays readable.

In the Studio, the canvas shows a sample photo — the real one is picked when an image is generated for a post.

## What you need

- **Stock photos** — a free API key for at least one library under **Settings → Stock Photos**. Photos come from the library set in **Library for Automatic Images**. See [Stock Photos](https://beautifulplugins.com/docs/image-generator/stock-photos/).
- **AI images** — an OpenAI API key under **Settings → AI Service**. Each AI image is paid for with your key and counts against the post author's hourly limit. See [AI Images](https://beautifulplugins.com/docs/image-generator/ai-images/).

## How it works

- **In the background.** Fetching a photo or making an AI image takes a moment, so posts that use a photo template get their featured image from a background job — saving a post never waits. The AI Featured Image panel in the editor shows the progress.
- **Once per post.** The photo is fetched once and kept with the post. Regenerating the image (for example after changing the design) reuses the same photo — no new search, no new AI cost. A new photo is fetched only when the search text or prompt changes, e.g. when you edit a title that `{title}` searches with.
- **Shaped to the canvas.** The photo's orientation follows the template — landscape, portrait or square.
- **Fresh photos first.** Stock searches prefer a photo you haven't already used on your site, so posts on similar topics don't all get the same picture.
- **Credited.** The stock photo is imported into the Media Library with the photographer's credit, like any stock photo you insert yourself.

If no photo can be found (or no key is set), the background falls back to its color and the rest of the design is drawn as usual.

## Related

- [Template Studio](https://beautifulplugins.com/docs/image-generator/template-studio/)
- [Automatic Featured Images](https://beautifulplugins.com/docs/image-generator/automatic-featured-images/)
