# Content Manager

Central admin tool for duplicating content and controlling post, taxonomy and WordPress admin-menu ordering.

## Features

- Adds a secure duplicate action to enabled post types.
- Enables drag-and-drop ordering in post lists and taxonomy term lists.
- Applies saved ordering to admin queries without changing public frontend queries.
- Can reorder or hide top-level and submenu items in the WordPress admin sidebar.
- Supports quick settings changes through authenticated Ajax requests.

## How Ordering Works

Post ordering uses the native `menu_order` field. Taxonomy ordering is stored in term meta `_sp_cm_order`. The module modifies admin queries only for post types and taxonomies explicitly enabled in **Settings → Content Manager**.

Admin menu configuration is stored in the `sp_content_manager_cfg` option. Runtime callbacks execute late on `admin_menu` so the final menu assembled by WordPress and other modules can be reordered reliably.

## Duplicate Action

The duplicate handler checks capabilities and a nonce, copies post data and supported metadata, creates a draft, and redirects to the new editor. It supports every eligible UI post type, including private custom types. Review relationships and unique external IDs before publishing a duplicate.

## Safety

Use **Reset to current WordPress order** after installing or removing plugins that add admin menus. Hiding a menu item does not revoke capabilities or disable its URL; it changes navigation only.

## Configuration Schema

All settings live in option `sp_content_manager_cfg` with autoload disabled. Defaults enable duplicate/post/term sorting, disable menu/submenu sorting, and select every eligible UI post type/taxonomy.

| Key | Meaning |
| --- | --- |
| `enable_duplicate` | Adds Duplicate row actions and permits the handler. |
| `enable_post_sort` | Enables drag-and-drop and admin `menu_order` sorting. |
| `enable_term_sort` | Enables taxonomy drag-and-drop and `_sp_cm_order`. |
| `enable_menu_sort` | Applies custom top-level WordPress menu order. |
| `enable_submenu_sort` | Applies stored child order inside each parent menu. |
| `post_types`, `taxonomies` | Sanitized enabled slugs. |
| `menu_order`, `submenu_order` | Normalized admin menu slugs in desired order. |
| `hidden_menu`, `hidden_submenu` | Navigation items removed from the rendered admin menu. |

Attachments, revisions, nav-menu items and the `nav_menu` taxonomy are excluded. Choices must have `show_ui=true`; runtime also checks the object's edit/manage capability.

## Duplicate Lifecycle

The row action appears only when duplication is enabled and the user can edit the source and create content in its post type. The URL uses action `sp_cm_duplicate_post` with a post-specific nonce.

The handler creates a draft containing content, excerpt, parent, menu order, discussion/password settings and current timestamps. The current user becomes author and the slug is left empty. Shared `PostDuplicator` logic copies all post meta except `_edit_lock`, `_edit_last`, `_wp_old_slug` and WPML duplicate ownership state, preserving serialized values through `maybe_unserialize()`, and assigns all ordinary taxonomy term IDs.

With Polylang or WPML active for the post type, the copy receives the source language through the plugin's public API. It intentionally starts a new translation group: two posts in the same language cannot occupy one translation group. Polylang's `language`/`post_translations` taxonomies are never copied directly.

Other duplication entry points can use the same behavior after inserting their target post:

```php
\SoinProduction\Kit\PostDuplicator::copyAssociatedData( $source_id, $target_id );
```

Use `sp_post_duplicator_excluded_meta_keys`, `sp_post_duplicator_excluded_taxonomies` and `sp_post_duplicator_language_providers` to customize copying. `sp_post_duplicator_after_copy` fires after the shared copy step; the older `sp_cm_after_duplicate` action remains available for Content Manager-specific integrations.

After copying, `sp_cm_after_duplicate` fires with source and target IDs:

```php
add_action( 'sp_cm_after_duplicate', function ( int $source_id, int $target_id ): void {
	delete_post_meta( $target_id, '_external_unique_id' );
}, 10, 2 );
```

Featured images and ACF fields are post meta and are copied. External files, comments, revisions and custom-table rows are not copied unless another callback handles them.

## Post and Term Ordering

Drag-and-drop on `edit.php` sends `post_type` and ordered IDs to `sp_cm_save_post_order`. Each ID is checked against type and `edit_post`; valid posts receive sequential `menu_order` from zero. On enabled list screens `pre_get_posts` changes only the main admin query, and only without another `orderby`: `menu_order ASC`, `date DESC`, `ID DESC`. Frontend queries are not changed.

`sp_cm_save_term_order` validates taxonomy/manage capability and saves positions in `_sp_cm_order`. The admin `get_terms_args` filter builds a complete ID list with `orderby=include`, so terms without ordering meta remain visible. Unordered terms are appended by term ID. Frontend term queries are not modified.

## Admin Menu Ordering and Visibility

Top-level order uses `custom_menu_order`/`menu_order`. Submenus are reordered on `admin_menu` priority `999`; visibility is applied at `1000`, after plugins register their rows. Slugs are normalized and volatile arguments such as `return`, nonces, locale and referer are removed. Newly installed items absent from saved order are appended.

Hidden entries are removed from global `$menu`/`$submenu` for navigation only. Always use roles/capabilities for access control.

## Ajax API

| Action | Authorization | Payload |
| --- | --- | --- |
| `sp_cm_save_settings` | `manage_options` | `nonce`, nested `cfg`. |
| `sp_cm_save_post_order` | Logged-in plus post-type/edit checks | `nonce`, `post_type`, `order[]`. |
| `sp_cm_save_term_order` | Logged-in plus taxonomy/manage checks | `nonce`, `taxonomy`, `order[]`. |

All endpoints use nonce action `sp_content_manager_admin`, sanitize slugs/IDs and return JSON with an `updated` count or an error status.

## Troubleshooting

- Save settings before expecting drag handles for a new type.
- If manual order is ignored, remove explicit `orderby` from that list request.
- If new terms disappear, inspect competing `get_terms_args` filters.
- Reset to current WordPress order after installing/removing menu-producing code.
- A hidden page remaining accessible is expected; revoke capability to block access.
- Remove unwanted copied integration meta on `sp_cm_after_duplicate`.
- Back up the database before bulk-changing business-critical `menu_order`.
