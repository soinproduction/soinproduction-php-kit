# SP Archive Pages

Maps real WordPress pages to custom post type archives so editors can manage archive content while URLs and single-post bases remain coherent.

## Configuration

Open **Settings → CPT Archives** and assign a page to each supported post type. Enable **Individual archive pages** when entries of that type may use different URL bases. The setting is stored independently for every WPML/Polylang language.

## How It Works

- The selected page ID is stored in WordPress options, with language-aware keys when a multilingual integration is available.
- `get_fake_archive_page()` exposes the assigned page to templates and helpers.
- Assigned pages receive a visible post state and are protected from trash/deletion while active.
- Archive and public taxonomy permalinks use the selected page hierarchy and language as their base.
- Single permalinks use `archive / first public category / post`; hierarchical category ancestors are preserved.
- An enabled post type gets an **Archive Page** metabox on every entry. Its selected published page overrides the type-level archive for that entry only.
- Explicit rewrite rules are registered for every language assignment; `parse_request` preserves real child-page routes when they collide.
- A post is resolved only below its own assigned base; the same slug below another entry's individual base returns 404.
- Link search results are adjusted so editors choose the logical archive destination.

## Operational Notes

Assignment and archive-page slug changes schedule one soft rewrite refresh on the next request. Do not assign the same page to unrelated archives, and do not delete an assigned page before removing the mapping.

Templates should read the assigned page through the helper instead of duplicating the option lookup.

## Supported Post Types and Filters

The default `fake_archive_supported_post_types` filter returns the theme constant `ARCHIVE_POSTS`. `get_supported_fake_archive_post_types()` sanitizes the result, removes non-public/unknown types and exposes the final list to the settings page and runtime. Integrations can add or remove a type with the same filter before the module initializes.

## Storage and Language Resolution

Assignments are stored as WordPress options keyed by post type and current language. `fa_current_lang()` and `fa_get_post_language()` use the native Polylang API first and the WPML filters second. This prevents an English archive page from replacing the Russian assignment.

`fa_get_archive_map_for_current_lang()` returns the complete validated map for the current language. Individual assignments use `_fa_archive_page_id` post meta and accept only a published page in the entry language. Invalid/missing assignments fall back to the type-level archive page.

## URL and Rewrite Lifecycle

The module changes several WordPress layers together:

| Integration | Purpose |
| --- | --- |
| `register_taxonomy_args` | Aligns public taxonomy rewrite bases with the assigned archive page. |
| `post_type_link` | Rebuilds single permalinks using the archive page, the first public category and the post slug. |
| `parse_request` | Recognizes archive, taxonomy and categorized single routes and populates the matching query vars. |
| `init` | Registers rewrite rules for all assigned language-specific archive bases. |
| `wp_loaded`, `post_updated` | Refreshes rules after an assignment, slug, parent or status change. |
| `add_meta_boxes`, `save_post` | Displays and saves the per-entry archive override. |
| `pll_translated_slugs` | Keeps Polylang CPT bases aligned with the assigned archive pages. |
| `body_class` | Adds archive/page context classes expected by theme styles. |
| `wp_link_query` | Makes the assigned destination clearer in editor link search. |
| `display_post_states` | Labels the page as a CPT archive in Pages list. |
| `before_delete_post`, `wp_trash_post` | Blocks destructive actions while a page is assigned. |

`fa_get_single_base_from_fake_archive_if_has_parent()` and `fa_get_archive_base_for_post_type()` centralize the base calculation. Do not reproduce this path logic in templates or custom rewrite callbacks.

Use `fa_get_archive_page_for_post( $post )` when entry-level overrides must be respected. `get_fake_archive_page( $post_type )` intentionally returns only the language-specific type default.

## Template Usage

Typical archive code can call:

```php
$archive_page = get_fake_archive_page( 'case_study' );
if ( $archive_page ) {
	setup_postdata( $archive_page );
	// Read ACF/page content from the assigned page.
	wp_reset_postdata();
}
```

Always restore global post data. When using ACF, pass the page ID explicitly if the archive query's global post must remain untouched.

## Changing an Assignment Safely

1. Create and publish the replacement page.
2. Assign it in **Settings → CPT Archives**.
3. Load the site once so the scheduled rewrite refresh runs.
4. Test archive pagination, taxonomy links, singles and editor link search.
5. Add a redirect from the previous archive base when the public URL changed.
6. Remove or repurpose the old page only after the assignment is gone.

## Troubleshooting

- **Archive returns 404:** load another request so a pending refresh can run; then resave Permalinks and verify the type is public and included in `ARCHIVE_POSTS`.
- **Wrong language page:** confirm the multilingual current-language function and the assignment saved in that language.
- **Individual selector is missing:** enable **Individual archive pages** for that post type in the current language's CPT Archives settings.
- **Individual URL returns 404:** verify that the selected page and entry have the same WPML/Polylang language.
- **Single URLs use the old base:** flush rewrite rules and page/cache layers; inspect competing `post_type_link` filters.
- **Page cannot be trashed:** this is intentional protection; unassign it first.
- **Template shows wrong ACF data:** use the assigned page ID explicitly rather than relying on the archive global query.
