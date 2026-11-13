<!--
Title: Merge Tags
Slug: merge-tags
Parent: Image Generator Documentation (https://beautifulplugins.com/docs/image-generator/)
Permalink: https://beautifulplugins.com/docs/image-generator/merge-tags/
SEO Title: Merge Tags — Dynamic Post Title, Category & Custom Fields on Featured Images
Meta Description: Use merge tags like {title}, {category}, {author}, {date} and {custom_field:key} to put post details on Image Generator templates and AI prompts, and show layers only when a tag has a value.
Focus Keyword: dynamic featured image text WordPress
-->

# Merge Tags

Merge tags are placeholders in curly braces. When an image is generated, each one is replaced with a detail of the post — so one template gives every post its own text.

## Tags in template text

Use these in any **Text** layer (and in a stock photo layer's **Search for** field):

| Tag | Replaced with |
|---|---|
| `{title}` | The post title |
| `{excerpt}` | The post excerpt |
| `{category}` | The post's categories |
| `{tags}` | The post's tags |
| `{author}` | The author's display name |
| `{date}` | The publish date, in your site's date format |
| `{site_name}` | Your site title |
| `{reading_time}` | Estimated reading time, e.g. "4 min read" |
| `{custom_field:key}` | The value of the custom field `key` |

Mix tags and plain text freely: `{category} · {reading_time}` or `By {author}`. A tag that has no value for a post (an empty excerpt, a missing custom field) becomes empty text.

In the Studio, the canvas shows sample values. Use **Preview with → A real post…** to see your template with a real post's values.

[Pro](https://beautifulplugins.com/docs/image-generator/pro-features/) adds WooCommerce tags: `{price}`, `{regular_price}`, `{sale_percent}` and `{rating}`.

## Custom fields

`{custom_field:key}` reads any post meta field by its key — for example `{custom_field:subtitle}` or `{custom_field:episode_number}`. Fields from ACF, Meta Box, Pods and similar plugins work as long as they're stored as plain post meta.

Protected fields — keys starting with an underscore, such as `_edit_lock` — are never shown, so private data can't end up on a public image.

## Showing a layer only when a tag has a value

Every layer has a **Show only when** setting. Choose a tag and the layer is drawn only for posts where that tag is not empty. For example:

- A "Part {custom_field:part}" badge that only appears on posts in a series.
- A category label with its colored box behind it, both hidden on uncategorized posts.

Set the same condition on the text and its box so they appear and disappear together.

## Tags in AI prompts

The **AI Prompt** setting (and a template's AI image layer) supports:

| Tag | Replaced with |
|---|---|
| `{title}` | The post title |
| `{excerpt}` | The post excerpt |
| `{category}` | The post's categories |
| `{tags}` | The post's tags |
| `{site_name}` | Your site title |
| `{custom_field:key}` | A custom field value |

Example: `An editorial photo for a {category} article titled "{title}". Context: {excerpt}`. See [AI Images](https://beautifulplugins.com/docs/image-generator/ai-images/).

> **Privacy tip:** Whatever a tag contains is sent to OpenAI as part of the prompt. If your excerpts or custom fields contain personal information, leave those tags out of your prompt.

## Adding your own tags

Developers can add tags with the `aimg_merge_tags` filter (values) and `aimg_merge_tag_names` (names shown in the Studio) — see the [Developer Reference](https://beautifulplugins.com/docs/image-generator/developers/).

## Related

- [Template Studio](https://beautifulplugins.com/docs/image-generator/template-studio/)
- [Photo Templates](https://beautifulplugins.com/docs/image-generator/photo-templates/)
