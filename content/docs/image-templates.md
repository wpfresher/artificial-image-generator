<!--
Title: Image Templates
Slug: image-templates
Parent: Image Generator Documentation (https://beautifulplugins.com/docs/image-generator/)
Permalink: https://beautifulplugins.com/docs/image-generator/image-templates/
SEO Title: Managing Image Templates in Image Generator — Add, Duplicate, Default & Export
Meta Description: How to manage image templates in Image Generator for WordPress — create templates from ready-made designs, duplicate them, set the default for featured images, export, import and delete.
Focus Keyword: WordPress featured image templates
-->

# Image Templates

An image template is a reusable design — background, images, shapes and text — that turns any post into an image. Templates are drawn on your own server with PHP's GD library, so they're free to use as often as you like and nothing is sent to an outside service (unless a layer uses a [stock photo or AI image](https://beautifulplugins.com/docs/image-generator/photo-templates/)).

## The templates list

Go to **Image Generator → Image Templates**. Your templates are shown as a grid of large previews, each drawn with the template's own name as the title. Badges mark the **Default** template and any **Draft**.

Each card has these actions below its preview:

- **Edit** — open the template in the [Template Studio](https://beautifulplugins.com/docs/image-generator/template-studio/).
- **Duplicate** — make a copy to experiment with or to build a variation.
- **Set as default** — use this template for [automatic featured images](https://beautifulplugins.com/docs/image-generator/automatic-featured-images/). On the default template the action reads **Use a random template**, which switches back to rotating through all of them.
- **Export** — download the design as a JSON file.
- **Delete** — delete the template and its preview. Images already generated from it stay in your Media Library.

Use **Search templates** to find one by name, or tick several and choose **Bulk actions → Delete**.

## Creating a template

Click **Add New**. The Studio opens with **Start from a design**, a choice of 10 ready-made designs:

| Design | Good for |
|---|---|
| Bold headline | Big, punchy titles on a solid color |
| Classic centered | The classic centered title look |
| Photo with fade | An image fading into a color behind the title |
| Magazine serif | Editorial, serif headlines |
| Minimal frame | A clean frame around the title |
| Highlighted lines | Title lines on highlight boxes |
| Split with image | Half image, half title |
| Quote card | Quotes and testimonials |
| Stock photo with headline | A stock photo for each post, headline on top |
| Stock photo with card | A stock photo for each post, title on a card |

Each design is a normal template — change anything. Prefer a blank canvas? Skip the designs and start empty. [Pro](https://beautifulplugins.com/docs/image-generator/pro-features/) adds five more designs: Product review with rating, Sale announcement, Course or series part, Recipe card and Product price tag.

## Draft and active templates

Every template has a **Status** in the Studio toolbar: **Active** or **Draft (not used)**. Only active templates are used for automatic featured images and offered in the editor's Templates tab — keep a template in draft while you work on it.

## Moving templates between sites

1. On the source site, click **Export** on the template card (or **More → Export design (JSON)** in the Studio).
2. On the other site, create a template, then choose **More → Import design (JSON)** in the Studio and pick the file.
3. Click **Save** to keep it.

Images chosen from the Media Library point to attachments on the original site, so pick them again after importing. Fonts you uploaded need to be uploaded on the new site too.

## Who can manage templates

By default, administrators (users with the `manage_options` capability). Developers can change this with the `aimg_manage_templates_capability` filter — see the [Developer Reference](https://beautifulplugins.com/docs/image-generator/developers/). Everyone who can upload files can *use* templates in the editor.

## Templates made before version 1.7.0

Older templates (made with the classic form) are converted to the Studio format automatically in the background — only when the result looks exactly the same. Templates that couldn't be converted keep working exactly as before, open in the Studio as usual, and are listed under **Tools → Site Health**. Their old settings are kept either way.

## Related

- [Template Studio](https://beautifulplugins.com/docs/image-generator/template-studio/)
- [Merge Tags](https://beautifulplugins.com/docs/image-generator/merge-tags/)
