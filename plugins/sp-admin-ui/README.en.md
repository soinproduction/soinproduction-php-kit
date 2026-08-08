# SP Admin UI

A collection of reusable WordPress administration UI components. Enable it with `sp-admin-ui` in the PHP Kit `plugins` configuration.

| Module | Purpose |
| --- | --- |
| `menu-title-item` | Adds draggable, non-clickable headings to Appearance → Menus. |
| `preview-thumbnail` | Adds editable image columns to post-type and taxonomy list tables. |
| `taxonomy-checkbox` | Renders and saves a multi-select taxonomy metabox. |
| `taxonomy-radio` | Renders and saves a single-select taxonomy metabox. |

All submodules load by default. Configure individual submodules directly in the PHP Kit `plugins` array; prefix a name with `_` to keep it listed but disabled:

```php
'plugins' => [
	'sp-admin-ui' => [
		'menu-title-item',
		'_preview-thumbnail',
		'taxonomy-checkbox',
		'_taxonomy-radio',
	],
],
```

The `sp_admin_ui_modules` filter remains available for runtime customization:

```php
add_filter( 'sp_admin_ui_modules', static fn(): array => [
	'menu-title-item',
	'taxonomy-radio',
] );
```

An empty `sp-admin-ui` array loads no submodules. The taxonomy modules share guarded helpers from `support/taxonomy.php`. The package provides behavior and semantic class names; project-specific admin CSS may style those classes.
