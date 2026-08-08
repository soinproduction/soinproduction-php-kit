# SP Archive Pages

Maps real WordPress pages to custom post type archives so editors can manage archive content while URLs and single-post bases remain coherent.

## Configuration

Open **Settings → CPT Archives** and assign a page to each supported post type. Supported types come from `ARCHIVE_POSTS` and the `fake_archive_supported_post_types` filter.

## How It Works

- The selected page ID is stored in WordPress options, with language-aware keys when a multilingual integration is available.
- `get_fake_archive_page()` exposes the assigned page to templates and helpers.
- Assigned pages receive a visible post state and are protected from trash/deletion while active.
- Archive and single permalinks use the selected page hierarchy as their base.
- Rewrite rules and `parse_request` map archive URLs back to the custom post type query.
- Link search results are adjusted so editors choose the logical archive destination.

## Operational Notes

Save Permalinks after changing assignments if rewrite rules have not refreshed. Do not assign the same page to unrelated archives, and do not delete an assigned page before removing the mapping.

Templates should read the assigned page through the helper instead of duplicating the option lookup.

## Supported Post Types and Filters

The default `fake_archive_supported_post_types` filter returns the theme constant `ARCHIVE_POSTS`. `get_supported_fake_archive_post_types()` sanitizes the result, removes non-public/unknown types and exposes the final list to the settings page and runtime. Integrations can add or remove a type with the same filter before the module initializes.

## Storage and Language Resolution

Assignments are stored as WordPress options keyed by post type and current language. `fa_current_lang()` uses the active multilingual integration when available and otherwise falls back to the WordPress locale/default language. This prevents an English archive page from replacing the Russian assignment.

`fa_get_archive_map_for_current_lang()` returns the complete validated map for the current language. Each saved ID is checked against an existing published page before it is used. Invalid/missing assignments fall back to the native post type archive behavior.

## URL and Rewrite Lifecycle

The module changes several WordPress layers together:

| Integration | Purpose |
| --- | --- |
| `post_type_link` | Rebuilds single permalinks using the selected archive page and its parent hierarchy. |
| `parse_request` | Recognizes the fake archive route and populates the matching post-type query vars. |
| `init` | Registers/reconciles rewrite rules for assigned archive bases. |
| `body_class` | Adds archive/page context classes expected by theme styles. |
| `wp_link_query` | Makes the assigned destination clearer in editor link search. |
| `display_post_states` | Labels the page as a CPT archive in Pages list. |
| `before_delete_post`, `wp_trash_post` | Blocks destructive actions while a page is assigned. |

`fa_get_single_base_from_fake_archive_if_has_parent()` and `fa_get_archive_base_for_post_type()` centralize the base calculation. Do not reproduce this path logic in templates or custom rewrite callbacks.

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
3. Visit **Settings → Permalinks** and save if routes do not update immediately.
4. Test archive pagination, taxonomy links, singles and editor link search.
5. Add a redirect from the previous archive base when the public URL changed.
6. Remove or repurpose the old page only after the assignment is gone.

## Troubleshooting

- **Archive returns 404:** resave Permalinks; then verify the type is public and included in `ARCHIVE_POSTS`.
- **Wrong language page:** confirm the multilingual current-language function and the assignment saved in that language.
- **Single URLs use the old base:** flush rewrite rules and page/cache layers; inspect competing `post_type_link` filters.
- **Page cannot be trashed:** this is intentional protection; unassign it first.
- **Template shows wrong ACF data:** use the assigned page ID explicitly rather than relying on the archive global query.
