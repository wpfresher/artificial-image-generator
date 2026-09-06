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
npm run plugin-zip             # Create distribution ZIP
bin/build-zip.sh               # Alternative ZIP build (respects .distignore)
bin/release.sh -u USER -p PASS # Deploy to WordPress.org SVN
```

**PHP i18n:**
```bash
composer run makepot   # Regenerate languages/artificial-image-generator.pot
```

**PHP code standards:** `phpcs.xml` enforces the "WpFresher" ruleset with text domain `artificial-image-generator`.

## Architecture

### Entry Point & Bootstrap

`artificial-image-generator.php` loads the Composer PSR-4 autoloader then calls `artificial_image_generator()`, which returns the `Plugin` singleton via `Plugin::create()`. The singleton fires three hooks: `admin_notices` (flash notices), `init` (class instantiation), and `admin_menu` (admin UI registration).

**PSR-4 namespace:** `ArtificialImageGenerator\`

### Core Classes

| Class | File | Role |
|---|---|---|
| `Plugin` | `includes/Plugin.php` | Singleton bootstrap; defines `AIMG_*` constants; manages flash notice queue |
| `PostTypes` | `includes/PostTypes.php` | Registers the hidden `aimg_template` custom post type |
| `Generator` | `includes/Generator.php` | Template → render args → file → attachment; stamps provenance meta |
| `GenerateImages` | `includes/GenerateImages.php` | Hooks `save_post`; auto-generates featured images when none exists |
| `RestAPI` | `includes/RestAPI.php` | `aimg/v1/generate` and `aimg/v1/templates` endpoints; OpenAI Images calls |
| `Admin\Admin` | `includes/Admin/Admin.php` | Admin menu, page routing (list / add / edit), script enqueuing |
| `Admin\Settings` | `includes/Admin/Settings.php` | Settings page UI and option validation |
| `Admin\Actions` | `includes/Admin/Actions.php` | Processes template CRUD via `admin_post_aimg_update_template` |
| `Admin\Editor` | `includes/Admin/Editor.php` | Enqueues the block editor integration |
| `Admin\MediaLibrary` | `includes/Admin/MediaLibrary.php` | Enqueues the generator modal on `upload.php` / `media-new.php` |
| `Admin\ListTables\TemplatesTable` | `includes/Admin/ListTables/TemplatesTable.php` | Extends `WP_List_Table` for template management |

### Data Model

Templates are stored as the hidden CPT `aimg_template`. Configuration lives in post meta on each
template post: `_aimg_bg_colors` (comma separated list, one picked at random per render),
`_aimg_width`, `_aimg_height`, `_aimg_title_font_size`, `_aimg_is_overlay_image`,
`_aimg_overlay_images` (JSON array of attachment IDs), `_aimg_overlay_position`, and
`_aimg_preview_image_url`.

Title **text color is not per template** — it is a single site-wide setting
(`aimg_settings['default_text_color']`), as is the fallback background color.

Generated attachments are stamped with `_aimg_generated` (`'1'`, queryable) and
`_aimg_generated_data` (source, template ID, prompt, provider, model, plugin version, timestamp).

### Image Generation Pipeline

1. `GenerateImages` catches `save_post` for posts/pages (per settings) that have no featured image.
2. `Generator::get_random_template_id()` picks a published template; `Generator::get_render_args()`
   maps its meta to render arguments (this mapping lives only here — the REST endpoint uses it too).
3. `aimg_generate_thumbnail()` in `includes/functions.php` uses PHP's **GD library** to:
   - Fill background with one of the template's colors, chosen at random
   - Composite an optional PNG overlay at the chosen position
   - Lay a 30% scrim over it
   - Render the post title using the bundled Roboto Bold font (`assets/fonts/`)
4. `Generator::create_attachment()` imports the file, sets alt text, stamps provenance meta, and
   fires `aimg_generated_image`. `GenerateImages` then sets it as the post thumbnail.

`aimg_generate_preview()` runs the same pipeline on-demand for the template editor preview, and
deletes the preview file it replaces.

**Windows note:** file paths handed to `wp_insert_attachment()` must come from `aimg_uploads_path()`.
`wp_upload_dir()` mixes separators there, so a `wp_normalize_path()`ed path fails the `strpos()`
check in `_wp_relative_upload_path()` and WordPress stores an unusable absolute `_wp_attached_file`.

### Build Pipeline

Webpack is configured in `webpack.config.js` extending `@wordpress/scripts`:
- **Entry:** `src/css/admin.scss` → `assets/css/admin.css` (+ RTL), `src/js/admin.js` → `assets/js/admin.js`
- **Fonts:** `CopyWebpackPlugin` copies `src/fonts/` → `assets/fonts/`
- `RemoveEmptyScriptsPlugin` strips empty `.js` stubs from CSS-only entries

The `assets/` directory is **built output** — do not edit files there directly.

### Admin UI Structure

- **Image Generator** (top-level menu, `dashicons-format-image`)
  - **Image Templates** — list, add, and edit templates; bulk delete; search by title
  - **Settings** — default BG/text colors; toggle auto-generation for posts and pages

Form submissions use the `admin_post_aimg_update_template` action with nonce verification.

### Key Helper Functions (`includes/functions.php`)

- `aimg_get_template($data)` — fetch a single template post
- `aimg_get_templates($args, $count)` — paginated template query
- `aimg_get_settings($option, $default)` — retrieve plugin options
- `aimg_get_js_data()` — REST endpoints, nonce and settings passed to the editor scripts
- `aimg_generate_thumbnail($args)` — GD image generation (background → overlay → scrim → text).
  Takes `template_id`; the legacy `post_id` key is still accepted
- `aimg_generate_preview()` — template editor preview (same pipeline, immediate output)
- `aimg_uploads_path($path)` — rewrite an uploads path into the separator style WordPress expects
- `aimg_delete_upload_by_url($url)` — delete a file inside uploads, given its URL
