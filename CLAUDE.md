# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

**Development:**
```bash
npm run start          # Webpack dev mode with file watching
npm run build          # Compile SCSS/JS assets + generate .pot translation file
```

**Linting:**
```bash
npm run lint:js        # Lint JavaScript
npm run lint:css       # Lint CSS/SCSS
npm run format         # Auto-format code via wp-scripts
```

**Distribution:**
```bash
npm run build && npm run plugin-zip   # Build assets, then create the distribution ZIP
```

**PHP i18n:**
```bash
composer run makepot   # Regenerate languages/artificial-image-generator.pot
```

**PHP code standards:** `phpcs.xml` enforces the "WpFresher" ruleset with text domain `artificial-image-generator`.

**Tests:**
```bash
bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version]   # once; use a dedicated DB, it gets emptied
composer test                                                                 # PHPUnit (tests/test-*.php)
```

**CI** (`.github/workflows/ci.yml`) runs phpcs, `wp-scripts lint-js src/js`, `wp-scripts lint-style`,
the asset build and PHPUnit (PHP 7.4 + 8.3) on every PR. `npm run build` also regenerates the `.pot`;
for asset-only rebuilds during development use `npx wp-scripts build --webpack-src-dir=src`.

## Architecture

### Entry Point & Bootstrap

`artificial-image-generator.php` loads the Composer PSR-4 autoloader then calls `artificial_image_generator()`, which returns the `Plugin` singleton via `Plugin::create()`. The singleton fires three hooks: `admin_notices` (flash notices), `init` (class instantiation), and `admin_menu` (admin UI registration).

**PSR-4 namespace:** `ArtificialImageGenerator\`

### Core Classes

| Class | File | Role |
|---|---|---|
| `Plugin` | `includes/Plugin.php` | Singleton bootstrap; defines `AIMG_*` constants; manages flash notice queue |
| `PostTypes` | `includes/PostTypes.php` | Registers the hidden `aimg_template` custom post type |
| `Generator` | `includes/Generator.php` | `generate_for_post()` (all methods), template rendering, `generate_ai()`, Media Library import; stamps provenance meta |
| `PromptBuilder` | `includes/PromptBuilder.php` | Builds AI prompts from a post: prompt template + merge tags, style presets, negative instructions |
| `Queue` | `includes/Queue.php` | Background jobs for AI featured images: started at once by a non-blocking loopback (`aimg_run_job`, single-use token), with Action Scheduler or WP-Cron as backup; atomic queued→running claim; status in post meta |
| `Providers\*` | `includes/Providers/` | `ProviderInterface`, `Result`, `OpenAI`, `Registry` (`aimg_providers` filter) |
| `Stock\*` | `includes/Stock/` | Stock photos: `Unsplash`, `Pexels`, `Pixabay` (base `Provider`: key from `AIMG_{ID}_KEY` or `{id}_key` setting, cached GETs; Pixabay 24 h as its terms require), `Registry` (`aimg_stock_providers`), `Importer` (allowlisted hosts, size/type check, reuse by `_aimg_stock_key`, credit in caption + `_aimg_stock_data`), `Keywords`, `RestController` (`/stock`, `/stock/keywords`, `/stock/{p}/search`, `/import`, `/test`) |
| `Rendering\Hybrid` | `includes/Rendering/Hybrid.php` | `stock` / `ai` layer sources: fetched only inside `Hybrid::fetching()` (generation), cached per post in `_aimg_hybrid_images`; previews and the Studio use a GD-made sample in `uploads/aimg-cache/` |
| `Templates\Migrator` | `includes/Templates/Migrator.php` | 1.8.0 background migration of 1.x templates to v2 (only when byte-identical per color × overlay); state in `aimg_templates_migrated`, Site Health test |
| `GenerateImages` | `includes/GenerateImages.php` | Hooks `wp_after_insert_post`; auto-generates featured images when none exists (per-post opt-out `_aimg_disable_auto`) |
| `RestAPI` | `includes/RestAPI.php` | `aimg/v1/generate`, `/templates`, `/templates/{id}/preview`, `/prompt`, `/status/{post}`, `/featured/{post}` |
| `Templates\RestController` | `includes/Templates/RestController.php` | Template CRUD (`POST /templates`, `GET/PUT/PATCH/DELETE /templates/{id}`), `POST /templates/preview` (unsaved document → data URI, 60/min per user), `GET /capabilities`; capability filter `aimg_manage_templates_capability` (default `manage_options`) |
| `Admin\Admin` | `includes/Admin/Admin.php` | Admin menu, page routing (list / add / edit), script enqueuing |
| `Admin\Settings` | `includes/Admin/Settings.php` | Settings page UI and option validation |
| `Admin\Actions` | `includes/Admin/Actions.php` | Template list actions (duplicate, set default, export, delete) via `admin_post_aimg_template_action` |
| `Admin\Editor` | `includes/Admin/Editor.php` | Enqueues the block editor integration |
| `Admin\MediaLibrary` | `includes/Admin/MediaLibrary.php` | Enqueues the generator modal on `upload.php` / `media-new.php` |
| `Admin\ListTables\TemplatesTable` | `includes/Admin/ListTables/TemplatesTable.php` | Since 1.7.0 only used for bulk-delete handling; the list is a card grid (`views/img-templates.php`, actions via `Admin\Actions::template_action()`) |
| `Templates\Starters` | `includes/Templates/Starters.php` | Starter designs for new templates (`aimg_template_starters`) |
| `Templates\Schema` | `includes/Templates/Schema.php` | Template document v2 and its sanitizer; layer type registry (`aimg_template_layers`) |
| `Templates\Repository` | `includes/Templates/Repository.php` | v2 document in `_aimg_template_data`; falls back to `Migration::from_template()` (never writes on read) |
| `Templates\Migration` | `includes/Templates/Migration.php` | Builds v2 documents from 1.x meta (`from_template`) or render args (`from_render_args`, the bridge `aimg_generate_thumbnail()` uses) |
| `Rendering\GdRenderer` | `includes/Rendering/GdRenderer.php` | Draws a document layer by layer; `save()` writes PNG/JPEG/WebP into uploads |
| `Rendering\Layers\*` | `includes/Rendering/Layers/` | One class per layer type (`sanitize()` + `draw()`): `Background`, `Image`, `Overlay`, `Text`, `Shape`, `Pattern`, `Frame` |
| `Rendering\Paint` / `Rendering\Images` | `includes/Rendering/` | Gradients, supersampled shapes, masks, opacity, rotated compositing, adjustments / image sources (incl. post, logo, local avatar), loading and fitting |
| `Templates\MergeTags` | `includes/Templates/MergeTags.php` | `{title}` … `{custom_field:key}` (protected meta excluded), one-pass replace, `showIf` conditions; filter `aimg_merge_tags` |
| `Rendering\TextLayout` | `includes/Rendering/TextLayout.php` | `wrap()` (the 1.x title wrapping) and `fit()` (shrink to a box, max lines, ellipsis) |

### Data Model

Templates are stored as the hidden CPT `aimg_template`. A template saved in the Template Studio holds a
v2 document in `_aimg_template_data` (`Templates\Repository`). The Studio is the only editor (the
classic form was removed in 1.7.1); 1.8.0 migrates 1.x templates in the background (`Templates\Migrator`);
ones skipped there are still read from their 1.x meta, and that meta is kept for downgrades (read path
removed in 2.0.0). The 1.x meta on each
template post: `_aimg_bg_colors` (comma separated list, one picked at random per render),
`_aimg_width`, `_aimg_height`, `_aimg_title_font_size`, `_aimg_is_overlay_image`,
`_aimg_overlay_images` (JSON array of attachment IDs), `_aimg_overlay_position`, and
`_aimg_preview_image_url`.

1.x templates have **no text color of their own**: they use the site-wide `default_text_color` (and
`default_bg_color` when they have no colors). Building a v2 document (Studio save, duplicate, 1.8.0
migration) writes the current values into it, so afterwards those settings only seed new templates.

Generated attachments are stamped with `_aimg_generated` (`'1'`, queryable) and
`_aimg_generated_data` (source, template ID, prompt, provider, model, plugin version, timestamp).

### Image Generation Pipeline

1. `GenerateImages` catches `wp_after_insert_post` (not `save_post`: the block editor sets the chosen
   featured image after `save_post`) for posts/pages (per settings) that have no featured image and
   no `_aimg_disable_auto` opt-out. Removing a generated featured image sets that opt-out.
   The `generation_method` setting decides what happens: `template` (default) renders inline, as
   before; `ai` / `ai_template` / `stock` / `stock_template` queue a background job via `Queue` (only for
   published/scheduled posts); `template_ai` renders inline and queues AI when no template exists.
   A template with a `stock`/`ai` layer source is queued too (`Generator::runs_in_background_for_post()`). Jobs run
   `Generator::generate_for_post()`, which also backs the editor's Generate button.
2. `Generator::get_template_id_for_post()` uses the `default_template_id` setting or a random
   published template; `Generator::get_render_args()`
   maps its meta to render arguments (this mapping lives only here — the REST endpoint uses it too);
   it returns `false` for templates that are not published.
3. `Generator::render()` renders a template's v2 document when it has one (`Repository`);
   otherwise `aimg_generate_thumbnail()` turns the render args into a v2 document
   (`Migration::from_render_args()`) and draws it with `Rendering\GdRenderer`. The 1.x look:
   - Fill background with one of the template's colors, chosen at random
   - Composite an optional PNG overlay at the chosen position
   - Tint the whole canvas with the same background colour at ~70% opacity (GD alpha 38), so
     overlays show through at ~30%. Keep this value: changing it changes every existing image
   - Render the post title using the bundled Roboto Bold font (`assets/fonts/`)

   **Golden tests** (`tests/test-renderer-parity.php`) compare every v1 path byte-for-byte with the
   frozen 1.6.0 renderer in `tests/legacy/` (excluded from phpcs; never edit it). Text `size` in
   documents uses the template font size field's unit (GD size, ≈ 96/72 CSS px) so v1 values map 1:1.
4. `Generator::create_attachment()` imports the file, sets alt text, stamps provenance meta, and
   fires `aimg_generated_image`. `GenerateImages` then sets it as the post thumbnail.

AI images: `PromptBuilder::build()` → `Providers\Registry::get()->generate()` (size and quality keys
are provider-neutral: square/landscape/portrait, auto/low/medium/high) → `Generator::sideload_*()` →
provenance. With default settings the OpenAI request body keeps the 1.5.x shape (`model`, `prompt`, `n`, `size`);
only the model changed. Models in `Providers\OpenAI::get_models()` must be current per OpenAI's
deprecations page (https://developers.openai.com/api/docs/deprecations); saved models that are no longer
listed fall back to `get_default_model()` at runtime.

`Templates\Repository::update_preview()` renders a template's list preview with its own title, and
deletes the preview file it replaces.

**Windows note:** file paths handed to `wp_insert_attachment()` must come from `aimg_uploads_path()`.
`wp_upload_dir()` mixes separators there, so a `wp_normalize_path()`ed path fails the `strpos()`
check in `_wp_relative_upload_path()` and WordPress stores an unusable absolute `_wp_attached_file`.

### Build Pipeline

Webpack is configured in `webpack.config.js` extending `@wordpress/scripts`:
- **Entry:** `src/css/admin.scss` → `assets/css/admin.css` (+ RTL)
- **Fonts:** `CopyWebpackPlugin` copies `src/fonts/` → `assets/fonts/`. Bundled fonts (static TTFs from Google Fonts with extended subsets, OFL/Apache licences in `src/fonts/licenses/`) are listed in `Rendering\Fonts::bundled()`; uploads go to `uploads/aimg-fonts/` (option `aimg_uploaded_fonts`, checked by file signature and a FreeType test render). Variable fonts cannot pick a weight in GD — bundle static instances only
- `RemoveEmptyScriptsPlugin` strips empty `.js` stubs from CSS-only entries
- **Template Studio** (`src/js/template-studio/` → `assets/js/template-studio.js`, plus
  `src/css/template-studio.scss`): loaded on the template add/edit screens with data inlined as
  `window.aimgStudio` (`Admin::enqueue_studio()`). Uses the `wp.*` globals plus bundled **Konva**
  (not react-konva: react-konva is pinned to one React major, WordPress ships 17–19). It mounts on
  `#aimg-template-studio`, replacing a server-rendered "Loading…" notice that stays if it cannot start.
  `canvas/text-layout.js` and `canvas/draw.js` mirror the PHP renderer; keep them in step.
  Layer types come from `registry.js` (core definitions in `layers.js`); other plugins add types
  with the JS filter `aimg.studio.layerTypes` from a script enqueued on `aimg_enqueue_template_studio`,
  plus the PHP filter `aimg_template_layers`. Layers of an unregistered type are kept
  (`Schema::sanitize()` cleans them generically) but not drawn.
- **Image modal** (`src/js/components/aimg-modal.js`, used by `block-editor.js` and `media-library.js`):
  tabs come from the JS filter `aimgModal.tabs` (core: Templates, Custom Prompt, one per stock library in
  `stock-panel.js`); add-ons enqueue on `aimg_enqueue_modal`. A tab's payload `{ endpoint, data }` is POSTed
  and must return `{ id, url, alt }`.
- `src/js/settings.js` → Test Connection buttons on the settings page.

The `assets/` directory is **built output** — do not edit files there directly.

### Admin UI Structure

- **Image Generator** (top-level menu, `dashicons-format-image`)
  - **Image Templates** — list, add, and edit templates; bulk delete; search by title
  - **Settings** — default BG/text colors; auto-generation; AI service; Stock Photos (keys, Test Connection)
  - **Upgrade to Pro** — external link, only while `AIMG_PRO_VERSION` is undefined (also a "Go Pro" plugin action link)

Templates are saved through the REST API (`Templates\RestController`); list actions use
`admin_post_aimg_template_action` with per-action nonces.

### Key Helper Functions (`includes/functions.php`)

- `aimg_get_template($data)` — fetch a single template post
- `aimg_get_templates($args, $count)` — paginated template query
- `aimg_get_settings($option, $default)` — retrieve plugin options
- `aimg_get_js_data()` — REST endpoints, nonce and settings passed to the editor scripts
- `aimg_generate_thumbnail($args)` — GD image generation (background → overlay → scrim → text).
  Takes `template_id`; the legacy `post_id` key is still accepted
- `aimg_uploads_path($path)` — rewrite an uploads path into the separator style WordPress expects
- `aimg_upload_url($path)` — URL of a file inside uploads ('' outside it)
- `aimg_delete_upload_by_url($url)` — delete a file inside uploads, given its URL
- `aimg_get_plain_title($post_id)` — post title without entities; use it for anything drawn or used as alt text
- `aimg_plain_text($text)` — the same for any text (REST `title` params use it as their sanitizer)
- `aimg_wrap_title(...)` — title line wrapping; shrinks the font only when a single word is too wide
- `aimg_can_render()` — GD + FreeType available; the renderer returns `false` without them
- `aimg_user_can_use_ai()` / `aimg_consume_ai_quota()` — AI access setting (`ai_access`) and per-user
  hourly limit (`ai_hourly_limit`, default 20); filters `aimg_can_generate_from_prompt`,
  `aimg_ai_hourly_limit`, `aimg_generate_timeout`
