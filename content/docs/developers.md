<!--
Title: Developer Reference
Slug: developers
Parent: Image Generator Documentation (https://beautifulplugins.com/docs/image-generator/)
Permalink: https://beautifulplugins.com/docs/image-generator/developers/
SEO Title: Image Generator Developer Reference — REST API, Hooks & Filters
Meta Description: Extend Image Generator for WordPress — REST API endpoints, PHP filters and actions for AI providers, stock libraries, template layers, merge tags and generation methods, plus JS filters for the Studio and modal.
Focus Keyword: Image Generator hooks and filters
-->

# Developer Reference

Image Generator is built to be extended — the Pro plugin itself uses only these public hooks. Text domain: `artificial-image-generator`; PHP namespace: `ArtificialImageGenerator\`; option: `aimg_settings`; meta prefix: `_aimg_`; REST namespace: `aimg/v1`.

## Constants

Define in `wp-config.php` to keep keys out of the database:

- `AIMG_API_KEY` — OpenAI API key.
- `AIMG_UNSPLASH_KEY`, `AIMG_PEXELS_KEY`, `AIMG_PIXABAY_KEY` — stock photo keys.

## REST API

All endpoints use cookie authentication with a `wp_rest` nonce (or application passwords) and check capabilities: generating needs `upload_files` and, for a post, permission to edit it; AI also follows the AI access setting and hourly limit.

| Endpoint | Purpose |
|---|---|
| `POST /aimg/v1/generate` | Generate from a template or a prompt; returns the attachment(s) |
| `GET /aimg/v1/templates` | Active templates for the editor |
| `POST /aimg/v1/templates/{id}/preview` | Preview a saved template with a title |
| `POST /aimg/v1/prompt` | Build the AI prompt for a post |
| `POST /aimg/v1/featured/{post}` | Generate a post's featured image with the automatic method |
| `GET /aimg/v1/status/{post}` | Background job status for a post |
| `GET /aimg/v1/stock` | Stock libraries and whether each has a key |
| `GET /aimg/v1/stock/keywords` | Search keywords for a post |
| `GET /aimg/v1/stock/{library}/search` | Search a library |
| `POST /aimg/v1/stock/{library}/import` | Import a photo into the Media Library |
| `POST /aimg/v1/stock/{library}/test` | Test a key |
| `POST /aimg/v1/templates`, `GET/PUT/PATCH/DELETE /aimg/v1/templates/{id}` | Template CRUD (needs the manage-templates capability) |
| `POST /aimg/v1/templates/{id}/duplicate` | Duplicate a template |
| `POST /aimg/v1/templates/preview` | Render an unsaved template document (60 a minute per user) |
| `GET /aimg/v1/capabilities` | Server capabilities (output formats, fonts) for the Studio |
| `POST /aimg/v1/fonts`, `DELETE /aimg/v1/fonts/{id}` | Upload or delete a font |
| `GET /aimg/v1/merge-tags/{post}` | Merge tag values for a post |

## Generation

- `aimg_generation_methods` *(filter)* — add methods to **Generate With**, as ID => label.
- `aimg_pre_generate_for_post` *(filter)* — `( $result, $post_id, $method )`: return an attachment ID or `WP_Error` to replace generation (pair with a custom method).
- `aimg_template_for_post` *(filter)* — `( $template_id, $post_id )`: choose the template per post, e.g. by category.
- `aimg_auto_generate_statuses` *(filter)* — post statuses that get automatic AI images (default `publish`, `future`, `private`).
- `aimg_generated_image` *(action)* — `( $attachment_id, $provenance )` after an image is created.
- `aimg_use_action_scheduler` / `aimg_kick_queue` *(filters)* — control the background queue.

```php
// Use a different template for each category.
add_filter( 'aimg_template_for_post', function ( $template_id, $post_id ) {
    if ( has_category( 'recipes', $post_id ) ) {
        return 123;
    }
    return $template_id;
}, 10, 2 );
```

## AI

- `aimg_providers` *(filter)* — register a provider implementing `ArtificialImageGenerator\Providers\ProviderInterface` (keyed by ID).
- `aimg_post_prompt` *(filter)* — `( $prompt, $post_id )`: the final prompt for a post.
- `aimg_style_presets` *(filter)* — add styles, as ID => `array( label, prompt suffix )`.
- `aimg_generate_endpoint` / `aimg_generate_request_body` *(filters)* — point the OpenAI provider at a compatible service or change the request.
- `aimg_generate_timeout` *(filter)* — request timeout (receives the model and quality).
- `aimg_can_generate_from_prompt` / `aimg_ai_hourly_limit` *(filters)* — AI access and the per-user hourly limit.

```php
add_filter( 'aimg_style_presets', function ( $styles ) {
    $styles['pixel'] = array( 'Pixel art', 'Pixel art, 16-bit, limited palette.' );
    return $styles;
} );
```

## Stock photos

- `aimg_stock_providers` *(filter)* — register a library (extend `ArtificialImageGenerator\Stock\Provider`).
- `aimg_stock_keywords` / `aimg_stock_stop_words` *(filters)* — how keywords are taken from a title.
- `aimg_stock_max_bytes` *(filter)* — the maximum download size.
- `aimg_unsplash_app_name` *(filter)* — the app name in Unsplash attribution links.

## Templates

- `aimg_template_layers` *(filter)* — register a layer type (a class with `sanitize()` and `draw()`). Pair it with the JS filter `aimg.studio.layerTypes` for the Studio, from a script enqueued on the `aimg_enqueue_template_studio` action.
- `aimg_merge_tags` *(filter)* — `( $values, $post_id )`: tag values; `aimg_merge_tag_names` lists them in the Studio; `aimg_studio_sample_tags` sets their sample values.
- `aimg_template_starters` *(filter)* — add designs to **Start from a design**.
- `aimg_template_document` *(filter)* — the template document before rendering.
- `aimg_fonts` *(filter)* — add fonts (static .ttf/.otf files).
- `aimg_manage_templates_capability` *(filter)* — who can manage templates (default `manage_options`).

```php
// A {subtitle} tag from a custom field.
add_filter( 'aimg_merge_tags', function ( $values, $post_id ) {
    $values['subtitle'] = $post_id ? (string) get_post_meta( $post_id, 'subtitle', true ) : '';
    return $values;
}, 10, 2 );

add_filter( 'aimg_merge_tag_names', function ( $names ) {
    $names['subtitle'] = 'Subtitle';
    return $names;
} );
```

## Image modal

Add a tab with the JS filter `aimgModal.tabs`, from a script enqueued on the `aimg_enqueue_modal` action. A tab provides a payload `{ endpoint, data }`; the modal POSTs `data` to `endpoint`, which must return `{ id, url, alt }`.

## Settings

- `aimg_sanitize_settings` *(filter)* — sanitize extra settings saved with `aimg_settings`.
- `aimg_get_settings( $key, $default )` — read a setting.

## Data

- Templates are the hidden post type `aimg_template`; the design is a versioned JSON document in `_aimg_template_data`.
- Generated attachments get `_aimg_generated` (`'1'`, queryable) and `_aimg_generated_data` (source, template ID, prompt, provider, model, plugin version, time).
- Stock imports store their credit in `_aimg_stock_data`.
- Posts store `_aimg_disable_auto` when an author removes a generated featured image.

```php
// All generated images.
$query = new WP_Query( array(
    'post_type'   => 'attachment',
    'post_status' => 'inherit',
    'meta_key'    => '_aimg_generated',
    'meta_value'  => '1',
) );
```

## Related

- [Pro Features](https://beautifulplugins.com/docs/image-generator/pro-features/)
- [FAQ & Troubleshooting](https://beautifulplugins.com/docs/image-generator/faq/)
