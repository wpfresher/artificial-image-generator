## Image Generator Plugin Architecture

```
artificial-image-generator/
│
├── artificial-image-generator.php   # Bootstrap: autoloader + Plugin::create()
├── uninstall.php                    # Data removal (opt-in via settings)
│
├── assets/                          # BUILT OUTPUT — do not edit by hand
│   ├── css/                         # admin, block-editor, media-library (+ RTL)
│   ├── fonts/                       # Roboto-Bold.ttf, copied from src/fonts
│   └── js/                          # admin, block-editor, media-library
│
├── includes/
│   ├── functions.php                # Template helpers, settings accessor, GD renderer
│   ├── Plugin.php                   # Singleton, AIMG_* constants, flash notices
│   ├── PostTypes.php                # Registers the hidden aimg_template CPT
│   ├── Generator.php                # Template → file → attachment service
│   ├── GenerateImages.php           # save_post hook for automatic featured images
│   ├── RestAPI.php                  # aimg/v1 endpoints (generate, templates)
│   └── Admin/
│       ├── Admin.php                # Menu, page routing, script enqueuing
│       ├── Actions.php              # admin_post_aimg_update_template handler
│       ├── Settings.php             # Settings page and option validation
│       ├── Editor.php               # Block editor asset enqueuing
│       ├── MediaLibrary.php         # upload.php / media-new.php asset enqueuing
│       ├── ListTables/
│       │   └── TemplatesTable.php   # WP_List_Table for templates
│       └── views/
│           ├── img-templates.php    # List screen
│           ├── add-img-template.php # Add screen
│           └── edit-img-template.php# Edit screen
│
├── src/                             # Build sources (SCSS + JS)
│   ├── css/                         # admin, block-editor, media-library, _aimg-modal
│   ├── fonts/                       # Roboto-Bold.ttf
│   └── js/                          # admin, block-editor, media-library, components/
│
├── languages/                       # artificial-image-generator.pot
├── composer.json                    # PSR-4: ArtificialImageGenerator\ → includes/
├── package.json                     # wp-scripts build pipeline
├── blueprint.json                   # WordPress Playground preview
└── readme.txt                       # WordPress.org readme
```

### Generation flow

```
save_post ──► GenerateImages ──┐
                               ├──► Generator::render()  ──► aimg_generate_thumbnail()  ──► PNG in uploads/
REST /generate ──► RestAPI ────┘                                    (GD: background → overlay → scrim → title)
                               │
                               └──► Generator::create_attachment() ──► Media Library + _aimg_generated meta
```

Prompt-based requests skip the renderer: `RestAPI::generate_from_prompt()` calls the OpenAI Images
API, sideloads the result, and stamps the same provenance meta via `Generator::mark_generated()`.
