<!--
Title: Template Studio
Slug: template-studio
Parent: Image Generator Documentation (https://beautifulplugins.com/docs/image-generator/)
Permalink: https://beautifulplugins.com/docs/image-generator/template-studio/
SEO Title: Template Studio — Design Featured Image Templates in WordPress
Meta Description: Design featured image templates visually in WordPress with the Image Generator Template Studio — layers, text that fits its box, fonts, image sources, previews and keyboard shortcuts.
Focus Keyword: featured image template designer WordPress
-->

# Template Studio

The Template Studio is a visual editor for image templates. You design on a canvas; your server draws every image the same way, so what you see is what your posts get. Open it with **Image Generator → Image Templates → Add New**, or **Edit** on any template.

## The layout

- **Layers panel** (left) — every layer in the template, top to bottom. Click to select, use **Add layer** to add one, and the buttons to show or hide, move up or down, duplicate or delete.
- **Canvas** (center) — drag layers to move them, pull the handles to resize and rotate. Snapping guides help you line things up with the canvas and each other.
- **Inspector** (right) — the settings of the selected layer. With nothing selected, it shows the template's **Canvas** settings: size, file format and status.
- **Toolbar** — **Template name**, **Status** (Active, or Draft — not used), undo/redo, **Preview with**, **More** (export and import), and **Save**.

## Canvas settings

- **Size** — Featured image (1200 × 800), Open Graph (1200 × 630, ideal for social sharing), 16:9 (1920 × 1080), Square (1080 × 1080), Pinterest (1000 × 1500), Story (1080 × 1920), or a custom width and height.
- **File format** — PNG, JPEG or WebP (when your server supports it), with a **Quality** setting for JPEG and WebP.

## Layer types

| Layer | What it does |
|---|---|
| **Background** | Fills the canvas with a color, a random color from a palette (each image gets one at random), a linear or radial gradient, or an image. |
| **Image** | An image with fit options (fill the box, fit, stretch), rounded or circle masks, opacity and adjustments: brightness, contrast, blur, black and white and duotone. |
| **Overlay** | A see-through color or a gradient fade over an image — keeps text readable on photos. |
| **Text** | Text with [merge tags](https://beautifulplugins.com/docs/image-generator/merge-tags/), fitted to its box. |
| **Shape** | Rectangles (with corner radius), ellipses and lines. |
| **Pattern** | Repeating dots, lines, grids and more, for texture. |
| **Frame** | A border around the canvas. |

[Pro](https://beautifulplugins.com/docs/image-generator/pro-features/) adds **Rating**, **Progress bar** and **Badge** layers. Every layer has a **Layer name** and a **Show only when** condition; most also have **Opacity**, and layers you place in a box have **Rotation**.

## Image sources

Background and image layers can show:

- an image from your **Media Library**,
- the **post's featured image**, or the **first image in the post**,
- the **site logo** or **site icon**,
- the **author's avatar** — uploaded to this site only; avatars are never fetched from Gravatar,
- a **stock photo** picked for each post, or an **AI image** made for each post — see [Photo Templates](https://beautifulplugins.com/docs/image-generator/photo-templates/).

When a post has no image for a source (no featured image, no images in the content), an image layer is simply left out and a background falls back to its color — so the result still looks good.

## Text that fits its box

A text layer fills the box you draw. Set the **Largest size** and **Smallest size**: the text starts large and shrinks until it fits, wrapping onto more lines, and ends with "…" if it still doesn't. **Most lines** caps the number of lines (0 for no limit), and **When the text is long** chooses between *Shrink it to fit the box* and *Only shrink words that are too wide*. Other options:

- **Font** — 11 bundled fonts (Roboto, Inter, Montserrat, Poppins, Playfair Display and Noto Sans, in regular and bold weights), or **Upload a font**.
- **Letter case** — As written, UPPERCASE, lowercase or Title Case.
- **Align** and **Vertical** position inside the box, **Line height** and **Letter spacing**.
- **Outline and shadow** — outline color and width, shadow opacity and distance.
- **Highlight** — a colored box behind each line, with padding and corner radius.

### Uploading fonts

Click **Upload a font** in the font picker and choose a **.ttf** or **.otf** file (up to 10 MB). Web fonts (.woff, .woff2) can't be used to draw images. The plugin checks that your server can draw with the font before accepting it. Use static fonts — the regular and the bold file separately — because a variable font can't choose its weight when the image is drawn.

The font picker shows which scripts each font covers (Latin, Cyrillic, Greek, Vietnamese, Devanagari). For other scripts, such as Arabic, Hebrew, Chinese, Japanese or Thai, upload a font that supports them.

## Previewing

The canvas shows sample text for each tag. Use **Preview with** to check your design with:

- **A short title** or **A long title** — make sure long titles still fit,
- **A real post…** — search your posts and see the template with that post's title, category, images and custom fields.

**Exact preview** asks your server to render the image, exactly as your posts will get it.

## Keyboard shortcuts

| Shortcut | Action |
|---|---|
| Ctrl/⌘ + S | Save |
| Ctrl/⌘ + Z | Undo |
| Ctrl/⌘ + Shift + Z, or Ctrl + Y | Redo |
| Ctrl/⌘ + D | Duplicate the selected layer |
| Delete / Backspace | Delete the selected layer |
| Ctrl/⌘ + ] / Ctrl/⌘ + [ | Move the selected layer up / down |
| Escape | Deselect |

Shortcuts other than Save don't run while you're typing in a field.

## Your theme's colors

Color pickers show your theme's color palette as swatches, so templates match your site.

## Related

- [Image Templates](https://beautifulplugins.com/docs/image-generator/image-templates/)
- [Merge Tags](https://beautifulplugins.com/docs/image-generator/merge-tags/)
- [Photo Templates](https://beautifulplugins.com/docs/image-generator/photo-templates/)
