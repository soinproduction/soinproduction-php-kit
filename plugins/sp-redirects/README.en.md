# SP Redirects

Manages exact-path redirects inside WordPress and supports bulk migration maps from CSV, TSV or TXT files.

## How Matching Works

The module runs on `template_redirect` with an early priority. It compares the normalized request path with enabled source paths and redirects to the configured destination using the selected HTTP status.

Rules are stored in the `sp_redirects_rules` option. They contain source, destination, status and active state. Exact matching avoids unexpected wildcard behavior.

## Admin Usage

Open **Settings → SP Redirects**:

1. Add a rule.
2. Enter a source path such as `/old-url/`.
3. Enter a destination path or safe URL.
4. Choose the redirect type and active state.
5. Save redirects.

## Import Format

Upload `.csv`, `.tsv` or `.txt` with columns `OLD`, `NEW`, `STATUS`. The header row is optional. **Replace existing rules** overwrites the complete stored map; leave it disabled to merge imports.

## Safety

Avoid redirecting a path to itself or creating chains/loops. Prefer `301` only for permanent migrations; use a temporary code while testing. Clear upstream/CDN cache after changing production redirects.

## Runtime Lifecycle

`SP_Redirects::init()` registers the runtime on every request. `maybe_redirect()` runs on `template_redirect` at priority `1`, before normal template rendering. It reads `sp_redirects_rules`, skips disabled/incomplete entries, normalizes the current request path, and compares it with each normalized source. The first exact match wins.

The destination is normalized during settings sanitization. Internal paths remain site-relative; absolute `http`/`https` destinations are retained only when they pass WordPress URL sanitization. Redirect output uses `wp_safe_redirect()` with the stored status.

## Rule Schema and Validation

The option is an array of rows:

| Key | Type | Meaning |
| --- | --- | --- |
| `old` | string | Exact source path, normalized with a leading slash. |
| `new` | string | Internal path or safe absolute destination. |
| `status` | integer | Allowed redirect code (`301`, `302`, `307`, or `308`). |
| `enabled` | boolean/integer | Whether runtime matching includes the row. |

Empty rows and unsupported status codes are removed or replaced by the default. Duplicate sources should be avoided because only the first matching row is operational.

## Admin and Import Request Flow

The settings page requires `manage_options`. Standard Settings API nonces protect rule saves. Import uses `admin-post.php?action=sp_redirects_import`, verifies the import nonce/capability, validates the uploaded file extension, parses the delimiter, sanitizes every row and redirects back with a result notice.

Accepted input behavior:

- CSV uses comma-aware parsing; TSV uses tabs; TXT may use detected tab/comma separators.
- Column order is `OLD`, `NEW`, `STATUS`.
- A recognizable header is skipped; otherwise the first row is data.
- Missing status falls back to `301`.
- **Replace existing** writes only imported rules; merge mode appends and then sanitizes the combined array.

## Deployment Checklist

1. Export or record the current map.
2. Test new rules with `302`/`307` and browser cache disabled.
3. Verify query strings: matching is path-based, while the destination controls what is preserved.
4. Check canonical/SEO plugins for a competing redirect.
5. Change permanent migrations to `301`/`308` only after verification.
6. Purge page cache/CDN and test logged-out.

## Troubleshooting

- **Rule does not fire:** verify it is enabled, starts with `/`, and matches the normalized path exactly including trailing-slash behavior.
- **Redirect loop:** compare the normalized source and final destination after WordPress canonical redirects.
- **Wrong rule wins:** remove duplicate `old` paths or reorder the stored rows.
- **Import returns no rows:** check delimiter, UTF-8 text, maximum upload limits and the first three columns.
- **Status appears cached:** clear browser, WordPress page cache, reverse proxy and CDN caches.
