# Favorite Posts

Adds an editor-controlled favorite flag to selected post types, together with admin workflows, REST support and frontend rendering.

## Data Model

Favorite state is stored in post meta `_sp_favorite_post` with value `1`. Module settings are stored in the `sp_favorite_posts_cfg` option.

## Admin Features

From **Settings → Favorite Posts** choose enabled post types and features:

- list-table column, views and dropdown filter;
- bulk mark/unmark actions;
- Quick Edit and editor-side checkbox;
- row action link;
- REST field and request filtering;
- single-favorite mode per post type.

Single-favorite mode removes the flag from other posts of the same type when a new favorite is selected.

## Frontend Usage

Use `[sp_favorite_posts post_type="post" card="card-favorite.php" posts_per_page="6"]`. The shortcode queries only favorite posts and loads the requested template from the theme.

Templates can use `$post_id` as the current favorite post ID. Keep custom card files inside the theme and escape their output normally.

## REST API

When enabled, supported post types expose an `is_favorite` field. Requests can filter with `?sp_favorite=1` or `?sp_favorite=0`; updates still require the normal post-edit capability.

## Configuration Reference

Settings live in option `sp_favorite_posts_cfg`:

| Key | Default | Effect |
| --- | --- | --- |
| `enabled_post_types` | All supported UI types | Types receiving favorite behavior. |
| `single_favorite_post_types` | Empty | Types where only one post may be favorite. |
| `enable_admin_filter` | `1` | All/Favorites/Not favorites dropdown. |
| `enable_bulk_actions` | `1` | Bulk mark and unmark actions. |
| `enable_quick_edit` | `1` | Quick Edit checkbox. |
| `enable_views_tab` | `1` | Favorites view link with count. |
| `enable_editor_metabox` | `1` | Side editor toggle. |
| `enable_row_action` | `1` | Favorite/Unfavorite below titles. |
| `enable_rest_api` | `1` | REST field and collection filter. |

Only types with a visible WordPress UI are offered. Submitted slugs are intersected with the supported list, preventing a crafted request from enabling arbitrary internal types.

## State Changes and Single Mode

`set_favorite_flag()` writes string `1` to `_sp_favorite_post` and deletes the key when disabled. Absence and non-`1` values mean not favorite. When enabling a post in single mode, `sp_favorite_posts_clear_other_favorites()` removes the flag from other posts of the same type. This invariant is enforced from Ajax, Quick Edit, editor save, row actions and REST.

Public helpers:

- `sp_is_favorite_post( int $post_id ): bool`
- `sp_favorite_posts_single_post_types(): array`
- `sp_favorite_posts_is_single_mode( string $post_type ): bool`
- `sp_favorite_posts_clear_other_favorites( int $post_id, string $post_type = '' ): void`

## Admin Integration Details

At `init` the module registers list-table hooks only for enabled types. The favorite column is sortable; an `EXISTS`/`NOT EXISTS` meta query keeps unmarked posts in results. Query variable `sp_favorite_filter` accepts `favorite` or `not_favorite`, and existing meta queries are combined with `AND`.

Ajax action `sp_favorite_post_toggle` accepts `post_id`, `value` and a nonce bound to the post. It checks `edit_post` and enabled type, returning `is_favorite` and `single_favorite`. Quick Edit uses native `_inline_edit`; editor metabox uses nonce action `sp_favorite_post_editor`; row URLs use `sp_favorite_post_row_action`; bulk operations use native list-table protection.

## REST Contract

For every enabled type `register_rest_field()` adds integer `is_favorite` in `view` and `edit` contexts. GET returns `1` or `0`. Update accepts boolean-compatible values; invalid input returns `rest_invalid_param`, and users without `edit_post` receive `403`.

```text
GET /wp-json/wp/v2/<type>?sp_favorite=1
GET /wp-json/wp/v2/<type>?sp_favorite=0
```

`false` matches missing meta and values other than `1`. Existing REST `meta_query` clauses are retained.

## Shortcode Contract

```text
[sp_favorite_posts post_type="case_study" card="card-favorite.php" posts_per_page="6"]
```

| Attribute | Behavior |
| --- | --- |
| `post_type` | Sanitized slug; default `any`. |
| `card` | Required basename in theme `templates/`; `.php` is appended if missing. |
| `posts_per_page` | Integer; default `-1`; invalid values below `-1` become `-1`. |

The real template path must stay inside `templates`, preventing path traversal. Query order is `menu_order ASC`, then `date DESC`. During each include `$post_id` and normal Loop globals are available. Empty results or invalid templates return an empty string; global post data is restored.

## Troubleshooting and Removal

- **Type absent in settings:** it must have `show_ui=true` and register before settings render.
- **REST field missing:** enable module REST and `show_in_rest` on the post type.
- **Not-favorite filter misses rows:** inspect competing meta queries/relations.
- **Shortcode empty:** provide a readable card in `templates` and published favorites.
- **Several favorites remain in single mode:** legacy data may predate the setting; toggle one favorite or call the clear helper.
- Disabling a type does not delete its meta; re-enabling restores state.
- Removing the module leaves option/meta data; clean it only as a deliberate migration.
