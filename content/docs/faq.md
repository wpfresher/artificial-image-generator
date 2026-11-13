<!--
Title: FAQ & Troubleshooting
Slug: faq
Parent: Image Generator Documentation (https://beautifulplugins.com/docs/image-generator/)
Permalink: https://beautifulplugins.com/docs/image-generator/faq/
SEO Title: Image Generator FAQ & Troubleshooting — Featured Images, AI & Stock Photos
Meta Description: Answers to common Image Generator questions — API keys, costs, automatic featured images not appearing, AI errors, stock photo limits, fonts and languages, page builders, WooCommerce and Pro licensing.
Focus Keyword: Image Generator FAQ
-->

# FAQ & Troubleshooting

## General

**Is the plugin free?**
Yes. The free plugin on WordPress.org includes the full Template Studio, automatic featured images, AI images and stock photos. [Image Generator Pro](https://beautifulplugins.com/plugins/image-generator-pro/) adds Rating, Progress bar and Badge layers, WooCommerce tags and premium designs.

**Do I need an API key?**
Only for AI images (OpenAI) and stock photos (a free Unsplash, Pexels or Pixabay key). Template images — including automatic featured images — are drawn on your own server with no key and no cost.

**Do you charge for AI usage?**
No. You use your own OpenAI key and pay OpenAI directly at their prices. The plugin adds no markup and your key stays on your server.

**Will it slow down my site?**
No. Nothing runs on the front end. Template images are drawn only when a post is saved without a featured image or when you generate one; AI images and stock photos are fetched in the background, so saving never waits.

**Is it GDPR-friendly?**
Template images never leave your server (unless a template uses a stock or AI photo layer). Stock searches send only search terms; AI generation sends only the prompt. No visitor data is ever sent, and the plugin sets no cookies. If your posts contain personal information, keep it out of your AI Prompt — see [Merge Tags](https://beautifulplugins.com/docs/image-generator/merge-tags/).

**Does it work on multisite?**
Yes. Each site has its own templates, settings and API keys.

## Automatic featured images

**A post didn't get a featured image.**
Check in order: ① **Enable Post Thumbnails** (or Page Thumbnails) is ticked under **Settings → Automatic Featured Images**; ② the post had no featured image when it was saved; ③ with **Image template**, at least one template is **Active** (drafts aren't used); ④ with an AI or stock method, the post is **published or scheduled** (AI images aren't made for drafts) and the key works — use **Test Connection** for stock keys; ⑤ the **AI Featured Image** panel in the editor — it shows the progress and any error message.

**It worked before, but not on this one post.**
If someone removed a generated featured image from that post, the plugin respects that and won't add a new one automatically. Use **Generate** in the AI Featured Image panel to create one on demand.

**Background images take a long time, or never arrive.**
Background jobs start right after the save, with Action Scheduler or WP-Cron as a backup. If your host blocks requests from your site to itself, the backup takes over — on low-traffic sites WP-Cron only runs when someone visits. Hook WP-Cron to a [real system cron](https://developer.wordpress.org/plugins/cron/hooking-wp-cron-into-the-system-task-scheduler/) for punctual jobs, or use **Generate** in the editor panel to make the image straight away.

**How do I add featured images to old posts?**
Open the post in the block editor and click **Generate** in the AI Featured Image panel, or just update the post — any post saved without a featured image gets one.

**Can it cover products and custom post types?**
Automatic generation covers posts and pages. On any post type with featured images — WooCommerce products included — generate on demand from the AI Featured Image panel or the Media Library.

## Templates

**The title is too small or cut off with "…".**
With **When the text is long** set to *Shrink it to fit the box*, the text shrinks down to the **Smallest size**, then ends with "…". Make the text box bigger, lower the Smallest size, or raise **Most lines** (0 for no limit). Use **Preview with → A long title** to test.

**Letters show as empty boxes.**
The font doesn't contain those characters. The font picker shows which scripts each font covers; for Arabic, Hebrew, Chinese, Japanese, Thai and others, upload a .ttf/.otf font that supports your language.

**My uploaded font is drawn in the wrong weight.**
It's probably a variable font, which can't choose a weight when the image is drawn. Upload the static font files instead (e.g. `Inter-Bold.ttf`).

**The image looks slightly different from the canvas.**
The canvas closely matches the final image, but your server draws the real one. Click **Exact preview** to see the image exactly as posts will get it.

**Templates don't render at all / "GD" or "FreeType" error.**
Your server's PHP needs the GD extension with FreeType support. Ask your host to enable it; check under **Tools → Site Health → Info → Media Handling**. AI images and stock photos work without it.

**An old template is listed in Site Health.**
It was made before version 1.7.0 and couldn't be converted to the Studio format without changing how it looks, so it was left as it is. It keeps working; open and save it in the Studio when you're ready.

## AI images

**"Incorrect API key" or no images.**
Re-paste the full key (after saving only the last 4 characters are shown), and make sure billing is set up on your OpenAI account — image generation needs it.

**The request timed out.**
High-quality images can take a while. The plugin waits up to 3 minutes; if it still times out, try a lower **Image Quality** or GPT Image 2.5 Flare.

**"You can generate 0 more AI images this hour."**
Each user can create a set number of AI images per hour (default 20). Raise or remove it under **Settings → AI Service → Hourly Limit per User**.

**The AI image has garbled text in it.**
AI models are bad at drawing text. Keep the default **AI Instructions** (no text or logos), and put your title on the image with a [photo template](https://beautifulplugins.com/docs/image-generator/photo-templates/) instead.

**The Custom Prompt tab is disabled.**
Either no API key is set, or your role isn't included in **Who Can Generate AI Images**.

## Stock photos

**Unsplash stops returning results after a while.**
New Unsplash keys are limited to 50 requests an hour until Unsplash approves the app for production. Apply for production access in your Unsplash dashboard, or add a Pexels or Pixabay key too.

**Do I have to credit photographers?**
The libraries ask you to credit them where you can. The plugin adds the credit to the image caption by default (**Settings → Stock Photos → Credit**) and always saves it with the image.

## Editors & compatibility

**Does it work with the Classic Editor, Elementor, Divi or Bricks?**
Automatic featured images work with every editor. The editor buttons are for the block editor, but **Media → Library → Generate Image** opens the same modal for any builder.

**Does it work with SEO plugins?**
Yes. Generated images are normal Media Library attachments with alt text, so Yoast SEO, Rank Math and AIOSEO use them in Open Graph tags and sitemaps.

**I moved a site from a Windows server and old images point to the old folder.**
Versions before 1.4.9 saved the full file path on Windows. Back up your database, then remove the old uploads prefix with WP-CLI, e.g. `wp search-replace 'C:/sites/example/wp-content/uploads/' '' wp_postmeta --dry-run` (run again without `--dry-run` once it looks right).

## Pro

**Do I need the free plugin?**
Yes — Pro is an add-on. Run free v1.8.0+ and Pro together.

**What happens to my templates if my license expires?**
They keep working. Pro layers and tags still render; editing Pro layers in the Studio, the premium designs, updates and support need an active license.

**My license won't activate.**
See [License Activation](https://beautifulplugins.com/docs/image-generator/license-activation/) — the common causes are copy/paste whitespace and used-up site activations.

## Still stuck?

[Open a support ticket](https://beautifulplugins.com/support/) with your WordPress and PHP versions, the plugin version(s), your **Generate With** method, and any message from the AI Featured Image panel. We read every ticket.
