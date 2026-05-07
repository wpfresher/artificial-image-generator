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
| `GenerateImages` | `includes/GenerateImages.php` | Hooks `save_post`; auto-generates featured images when none exists |
| `Admin\Admin` | `includes/Admin/Admin.php` | Admin menu, page routing (list / add / edit), script enqueuing |
| `Admin\Settings` | `includes/Admin/Settings.php` | Settings page UI and option validation |
| `Admin\Actions` | `includes/Admin/Actions.php` | Processes template CRUD via `admin_post_aimg_update_template` |
| `Admin\ListTables\TemplatesTable` | `includes/Admin/ListTables/TemplatesTable.php` | Extends `WP_List_Table` for template management |

### Data Model

Templates are stored as the hidden CPT `aimg_template`. Configuration (BG color, text color, width, height, font size, overlay image IDs, overlay position) lives in post meta on each template post.

### Image Generation Pipeline

1. `GenerateImages` catches `save_post` for posts/pages (per settings) that have no featured image.
2. It picks a random `aimg_template` and reads its meta.
3. `aimg_generate_thumbnail()` in `includes/functions.php` uses PHP's **GD library** to:
   - Fill background with the template's color
   - Composite an optional PNG overlay at the chosen position
   - Render the post title using the bundled Roboto Bold font (`assets/fonts/`)
4. The resulting image is saved to `wp-uploads/` and attached as the post thumbnail.

`aimg_generate_preview()` runs the same pipeline on-demand for the template editor preview.

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
- `aimg_generate_thumbnail($args)` — GD image generation (background → overlay → text)
- `aimg_generate_preview()` — template editor preview (same pipeline, immediate output)
