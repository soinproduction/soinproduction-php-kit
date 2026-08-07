# Uploads WebP Convert

Media maintenance module that converts JPEG, PNG and GIF attachments to WebP, repairs WordPress attachment metadata, migrates stored URLs in posts/meta, detects conservatively unused image attachments, deletes confirmed candidates in batches and replaces an image file without changing its attachment identity.

Several operations modify both the database and `wp-content/uploads`. Always use staging plus database/uploads backups before bulk conversion, URL replacement or deletion.

## Feature Map

| Feature | Scope | Destructive potential |
| --- | --- | --- |
| Convert on upload | Newly handled JPEG/PNG/GIF file | May remove the just-uploaded source when enabled. |
| Bulk conversion | Existing supported attachments | Changes MIME/path/metadata and can delete source/sizes. |
| URL replacement | `posts` content/excerpt and `postmeta` | Rewrites matching strings, including serialized arrays. |
| Unused scan | All image attachments, database and static text files | Read-only scan. |
| Delete unused | Selected scan results | Permanently calls `wp_delete_attachment(..., true)`. |
| Replace file | One existing image attachment | Overwrites file and regenerates its image sizes. |

## Configuration

Settings are stored in non-autoloaded option `sp_webp_convert_cfg`.

| Key | Default | Sanitized range / effect |
| --- | ---: | --- |
| `enabled_upload` | `1` | Converts supported files in `wp_handle_upload`. |
| `quality` | `90` | WebP encoder quality, clamped to 60–100. |
| `max_side` | `2560` | Maximum width or height, clamped to 320–8000 px. |
| `delete_original` | `1` | Removes obsolete original/generated files only after valid output exists. |
| `skip_animated_gif` | `1` | Detects more than one GIF frame and preserves the animation. |
| `batch_size` | `20` | Conversion batch, 1–100; also influences unused-scan batch size. |
| `db_batch_size` | `200` | Stored URL replacement preference, 20–500; runtime replacement is capped at 120 rows/request. |

The module adds WebP to allowed upload MIME types and repairs filetype detection for `.webp`. `wp_unique_filename` checks both the uploaded source name and its future `.webp` target so `photo.jpg` does not overwrite an existing `photo.webp`.

## Conversion Engine

Supported conversion source MIME types are `image/jpeg`, `image/png` and `image/gif`. Existing WebP, SVG, AVIF and other formats are not bulk conversion targets.

For every source the engine:

1. Resolves MIME from actual image data, extension and WordPress fallback.
2. Verifies the source is readable.
3. Skips an animated GIF when configured. Animation detection reads chunks until at least two frame markers are found.
4. Reads dimensions and proportionally limits the longest side.
5. For a large image, first attempts a memory-aware GD pre-resize; it raises WordPress’s image memory limit and estimates source + target memory before allocating.
6. Opens the working file with `wp_get_image_editor()`, applies quality and saves into a UUID temporary WebP path.
7. Validates non-zero size and readable image dimensions before moving the temporary file into the final `.webp` name.
8. Validates the final file again, then optionally removes the different source path.

PNG/GIF transparency is preserved during the GD pre-resize through disabled alpha blending and saved alpha. If GD functions/memory are unavailable for the safe pre-resize, the large file is skipped rather than forcing a likely fatal allocation.

Common result statuses are `converted`, `skipped` and `error`. Skips include existing WebP, unsupported MIME, animated GIF, missing attachment file and inability to safely pre-resize. Errors include image-editor failure, invalid generated output or filesystem move failure.

## Convert on Upload

The filter runs after WordPress handles the upload but before the attachment is fully created. On successful conversion it replaces the returned `file`, `url` and MIME with the WebP values, so later WordPress attachment creation naturally targets the new file.

If conversion is skipped or fails, the original `$upload` array is returned unchanged; a conversion problem must not make an otherwise accepted upload disappear. Check PHP/ImageMagick/GD WebP support when newly uploaded files remain JPEG/PNG.

## Bulk Conversion Lifecycle

**Scan media** counts inherited attachments whose MIME is JPEG, PNG or GIF. Conversion reads IDs in ascending order using an `ID > last_id` cursor, avoiding expensive page offsets. Each Ajax request has a 20-second limit and uses the configured batch, with the browser allowed to reduce it after network failures.

For each successfully converted existing attachment the module:

- stores the original main URL in `_sp_webp_original_url`;
- stores the original extension in `_sp_webp_original_ext`;
- changes `_wp_attached_file` through `update_attached_file()`;
- changes `post_mime_type` to `image/webp`;
- regenerates attachment metadata and intermediate sizes;
- falls back to minimal width/height/file metadata if generation fails;
- deletes stale original sizes only when **Delete original** is enabled;
- clears the URL-map transient;
- reports non-negative bytes saved.

Browser progress tracks total, last ID, processed/converted/skipped/errors and saved bytes in local storage. **Stop** prevents another browser batch; it does not roll back completed attachments. The interface retries transient network failures, reduces conversion batch size and can resume stored progress after reload. **Reset saved progress** clears client state, not converted files or database changes.

Do not run the same bulk operation simultaneously in multiple tabs: cursor state is browser-local while attachments are shared.

## URL Replacement Map

After conversion, old hard-coded URLs may remain in editor content or ACF/post meta. The module scans all inherited WebP attachments in pages of 500 and builds a map from old URL to current WebP URL using:

- `_sp_webp_original_url` when recorded;
- `_sp_webp_original_ext` when recorded;
- otherwise possible `.jpg`, `.jpeg`, `.png` and `.gif` variants;
- generated intermediate-size WebP filenames and their old-extension variants.

The map is cached for ten minutes in `sp_webp_url_replace_map_cache` and invalidated by conversion/file replacement.

## Database URL Replacement

Preparation reports the number of map entries plus every row in `wp_posts` and `wp_postmeta`. Processing has two cursor phases:

1. `posts` — replaces exact URL strings in `post_content` and `post_excerpt`.
2. `postmeta` — replaces strings by `meta_id`, preserving serialized arrays through safe unserialize, recursive string replacement and reserialization.

Serialized objects are deliberately not modified. Malformed serialized values remain unchanged. The endpoint supports a `dry_run` flag at API level; the current admin button performs the real operation. Counts distinguish processed rows, changed rows, individual URL hits and update errors.

This operation does not rewrite arbitrary custom tables, term/user/options data, PHP/JS/CSS files, external systems or CDN caches. It replaces exact strings from its generated map, not relative URLs unless those exact variants are in the map. Search custom code and third-party storage separately.

## Unused Image Scan: Conservative Model

The scanner examines every inherited attachment with MIME starting `image/`. Its batch is `ceil(batch_size / 10)` clamped to 1–4; each Ajax request is capped at 20 items and 45 seconds because each item can trigger many searches.

An attachment is kept when the file/path is missing or the scanner cannot build reliable search needles. Needles include:

- exact and structured attachment-ID patterns (`wp-image-ID`, data attributes, gallery forms and media-like JSON/serialized keys);
- current and old upload-relative paths;
- current, old and generated-size URLs;
- escaped slash, HTML-encoded and URL/path variants.

Exact numeric ID matches are accepted only in meta/option keys that look media-related or correspond to ACF `image`, `gallery` or `file` fields. This reduces false positives from unrelated numbers.

### Database Coverage

The scan checks relevant text columns in posts, postmeta, options excluding transients, termmeta, term taxonomy descriptions, usermeta/users, comments/commentmeta, links and sitemeta when available. It also discovers text-like columns in non-core custom tables from `information_schema` and caches that schema for one hour in `sp_webp_unused_schema_cache`.

Known log/cache/backup/analytics/plugin-internal table families are excluded from custom-table discovery to reduce false “used” matches and query cost. If schema discovery or any database search errors, the attachment is kept conservatively.

### Filesystem Coverage

Static searchable files are collected from child/parent themes, mu-plugins, active plugins and uploads text files. Recognized extensions include PHP/PHTML, JS/TS/JSX/TSX, CSS preprocessors, HTML, JSON, XML, YAML, Markdown, CSV, SVG, manifests and plain text. Cache, backup, logs, vendor, node_modules, language and known generated directories are skipped; symlinks are not followed.

Files are streamed in 1 MB chunks with overlap so large files do not need to fit in memory. The attachment’s own file and generated sizes are excluded from matching themselves. Any unreadable root/file or empty searchable-file set causes a conservative “used/keep” result.

Only after no exact ID, database, custom-table or filesystem reference is found does an attachment appear in the unused results. Dynamic references assembled at runtime, remote databases, encrypted/compressed values and unsupported binary formats can still escape detection.

## Deleting Unused Attachments

Deletion requests contain at most four IDs. Immediately before deleting each item the server verifies it still exists, is an image and repeats the entire usage inspection. A newly discovered reference moves the item to skipped/kept. Confirmed items are permanently deleted with `wp_delete_attachment($id, true)`, including WordPress-managed files and metadata.

There is no trash/undo for attachments. Never interpret a scan result as proof; inspect the preview, attachment URL, parent/context and custom application behavior, then keep a recoverable backup.

## Replace File In Place

**Replace file** is available in Media Library rows and attachment fields for users with both `upload_files` and `edit_post` for that attachment. It supports GIF, JPEG, PNG, SVG and WebP but requires the replacement MIME to match the current MIME (with `image/jpg` treated as JPEG). This requirement preserves filename and URL.

The upload must be a real PHP upload, readable, under `wp_max_upload_size()` and a valid raster image; SVG is limited to a basic `<svg` presence check, not a full security sanitizer. Site-wide SVG upload security remains the responsibility of the SVG module/policy.

Replacement flow:

1. Ensure the current file exists, is writable and resides inside uploads.
2. Copy the uploaded file to a UUID temporary sibling, preserve file permissions and atomically rename it over the current path.
3. Keep attachment ID, title, slug, filename, URL, alt, caption and other post/meta fields.
4. Update MIME, regenerate metadata/sizes, remove obsolete old sizes and clear caches/transients.
5. Return a cache-busted thumbnail preview.

The old original is overwritten and is not retained as a backup. Back up the file first if rollback is required.

## Ajax Actions and Permissions

All admin maintenance actions verify nonce `sp_webp_convert_admin`.

| Action | Capability |
| --- | --- |
| `sp_webp_save_settings` | `manage_options` |
| `sp_webp_scan_media` | `manage_options` |
| `sp_webp_convert_batch` | `manage_options` |
| `sp_webp_prepare_url_replace` | `manage_options` |
| `sp_webp_replace_urls_batch` | `manage_options` |
| `sp_webp_prepare_unused_scan` | `manage_options` |
| `sp_webp_scan_unused_batch` | `manage_options` |
| `sp_webp_delete_unused_batch` | `manage_options` |
| `sp_webp_replace_attachment_file` | `upload_files` plus `edit_post` for the attachment |

## Safe Operating Procedure

1. Back up database and uploads; test restore access.
2. On staging, save conservative quality/max-side settings and keep originals initially.
3. Convert a small batch and inspect transparency, EXIF-dependent orientation, thumbnails and frontend crops.
4. Run URL replacement, then search the database/code for the old extension/domain.
5. Clear page/object/CDN caches and regenerate any external image cache.
6. Run unused scan, manually review candidates and delete a tiny selection first.
7. Only enable source deletion after verifying the complete workflow and backup retention.

## Troubleshooting

- **All files skipped/failed:** verify GD or Imagick WebP write support through `wp_get_image_editor()`.
- **Large file skipped for memory:** increase the image memory limit or lower `max_side`; the module refuses unsafe GD allocation.
- **Animated GIF became static:** ensure **Skip animated GIF** was enabled before conversion; restoring requires the original backup.
- **Attachment shows WebP but frontend still requests JPEG:** run URL replacement, search custom tables/code and clear CDN/page caches.
- **URL map is empty:** no inherited WebP attachments or no inferable original URL/extensions exist.
- **Replacement progress stalls:** reload to resume client state, lower batch settings and inspect PHP/web-server timeout/error logs.
- **Unused scan is slow:** expected on many custom tables/static files; batches are deliberately small.
- **Everything is reported used:** inspect reason strings; unreadable filesystem/schema/database errors intentionally keep files.
- **A used image is reported unused:** do not delete it; identify the unsupported storage/runtime reference and extend or avoid the scanner for that system.
- **Delete skips a selected item:** the repeated pre-delete scan found a reference or the attachment changed.
- **Replace file rejects upload:** format differs, file is not a real upload, exceeds size, is unreadable or the current file is not writable/in uploads.
- **Old sizes remain:** verify filesystem permissions and compare regenerated metadata with actual files.
- **Recovery required:** restore both the database and corresponding uploads snapshot; restoring only one can leave broken attachment metadata.
