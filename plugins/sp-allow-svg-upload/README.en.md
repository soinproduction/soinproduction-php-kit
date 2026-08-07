# SVG Uploads

Adds controlled SVG support to the Media Library and converts known UI icons to efficient inline or sprite markup on the frontend.

## How It Works

- SVG upload permission is limited to users who can upload files.
- `upload_mimes` permits the SVG MIME type.
- `wp_check_filetype_and_ext` verifies the extension and real MIME before accepting a file.
- Uploaded XML is sanitized: scripts, event handlers, unsafe URLs, external references and dangerous style declarations are removed.
- The admin CSS fixes SVG thumbnails in the Media Library.
- Frontend output buffering can replace registered UI icon images with sanitized inline SVG or `<use>` references.

## UI Icon Resolution

The module reads the UI icon manifest configured by the theme and normalizes local URLs. Helper functions resolve an icon slug, local file and sprite reference without another remote request.

## Usage

Upload SVG files through the normal Media Library. Use the theme media/icon helpers rather than reading the file directly, so sizing, accessibility attributes and sprite behavior remain consistent.

## Security Notes

SVG is executable XML. Do not bypass the sanitizer or allow arbitrary remote SVG URLs. Re-upload an asset after changing sanitizer rules because already stored files are not automatically rewritten.

## Bootstrap and Hook Sequence

| Hook | Callback | Responsibility |
| --- | --- | --- |
| `upload_mimes` | `allow_svg_upload()` | Adds `svg => image/svg+xml` for users allowed to upload files. |
| `wp_check_filetype_and_ext` | `sanitize_svg()` | Validates extension/MIME and sanitizes the temporary upload before WordPress creates the attachment. |
| `admin_head` | `fix_svg_display()` | Adds Media Library thumbnail sizing rules. |
| `template_redirect` | `start_output_buffering()` | Starts the frontend transformation buffer. |

The module is loaded by `core/bootstrap.php`; there is no settings record and no admin page. Disabling it means prefixing the module folder with `_` or changing the bootstrap rules. Existing SVG attachments remain in the library, but new uploads and frontend transformations stop.

## Sanitizer Pipeline

The upload validator first confirms a `.svg` extension and an allowed real MIME (`image/svg+xml`, `text/xml`, or `application/xml`). It then reads the temporary file and rejects malformed or empty XML. The sanitizer removes XML processing instructions, document type declarations, comments, active/embed nodes, unknown unsafe elements, event attributes such as `onclick`, JavaScript/data URLs, and external references that could load remote content.

Attribute values are inspected separately. Local fragment references such as `#icon-id` are allowed where needed; dangerous protocols and CSS expressions are rejected. Inline `style` is reduced to a conservative declaration set. The cleaned markup is written back only when validation succeeds. A rejected filetype is returned to WordPress as an empty extension/type so the normal upload error is shown.

## Frontend Transformation

`inline_svg_processing()` receives the final HTML buffer and only handles SVG URLs that resolve to local files or known UI assets. It can:

- load and sanitize local SVG markup;
- apply safe `class`, `width`, `height`, `role`, `aria-*` and presentation attributes;
- convert a configured UI icon into a sprite `<svg><use href="…#symbol"></use></svg>` reference;
- preserve ordinary `<img>` output when resolution or sanitization fails.

The UI manifest is built from the theme options and cached in request memory. URL normalization removes query strings and reconciles absolute URLs with local upload paths. No remote URL is downloaded by the transformer.

## Public Helpers

The module exposes helpers used by theme rendering code:

- `sp_svg_ui_icons_manifest()` returns the normalized UI icon registry.
- `sp_svg_ui_icon_slug_from_url()` resolves a configured icon slug from a URL.
- `sp_svg_sprite_href_for_ui_icon()` returns the matching sprite symbol reference.
- `sp_svg_inline_markup_from_url()` returns sanitized inline markup for a local SVG.
- `sp_svg_build_sprite_markup()` builds accessible sprite markup.

Treat these functions as nullable: an empty result means the source is unavailable or unsafe and the caller should retain its fallback.

## Troubleshooting

- **WordPress says the file type is not permitted:** confirm the user has `upload_files`, the extension is `.svg`, and the server reports an XML/SVG MIME.
- **Upload is rejected after MIME validation:** inspect the SVG for scripts, external resources, malformed namespaces or unsupported XML.
- **Icon remains an `<img>`:** verify it belongs to the UI Assets manifest and resolves inside the site filesystem.
- **Sprite symbol is missing:** rebuild or resave UI Assets and confirm the symbol ID matches the manifest slug.
- **Old unsafe markup remains:** the sanitizer runs at upload/read time; replace or re-upload historical assets.
