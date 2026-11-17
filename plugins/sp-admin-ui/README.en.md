# SP Admin UI

A collection of reusable WordPress administration UI components. Enable it with `sp-admin-ui` in the PHP Kit `plugins` configuration.

The package base also enhances a compatible Builder Widgets catalogue (`.wsb-radio-field`): opening a row scrolls its list to the saved widget and shows its name in the ACF layout heading as `Widget: Name`. Selecting another widget updates the heading immediately.

| Module | Purpose |
| --- | --- |
| `sp-admin-ui-menu-heading` | Adds draggable, non-clickable headings to Appearance → Menus. |
| `sp-admin-ui-text-column` | Adds inline-editable ACF text or native excerpt/description columns to post-type and taxonomy list tables. |
| `sp-admin-ui-thumbnail-column` | Adds editable image columns to post-type and taxonomy list tables. |
| `sp-admin-ui-taxonomy-checklist` | Renders and saves a multi-select taxonomy metabox. |
| `sp-admin-ui-taxonomy-radio` | Renders and saves a single-select taxonomy metabox. |

All submodules load by default. Configure individual submodules directly in the PHP Kit `plugins` array; prefix a name with `_` to keep it listed but disabled:

```php
'plugins' => [
	'sp-admin-ui' => [
		'sp-admin-ui-menu-heading',
		'sp-admin-ui-text-column',
		'_sp-admin-ui-thumbnail-column',
		'sp-admin-ui-taxonomy-checklist',
		'_sp-admin-ui-taxonomy-radio',
	],
],
```

The `sp_admin_ui_modules` filter remains available for runtime customization:

```php
add_filter( 'sp_admin_ui_modules', static fn(): array => [
	'sp-admin-ui-menu-heading',
	'sp-admin-ui-taxonomy-radio',
] );
```

An empty `sp-admin-ui` array loads no submodules. The taxonomy modules share guarded helpers from `includes/taxonomy.php`. The package provides behavior and semantic class names; project-specific admin CSS may style those classes.

### Ancestor terms

Taxonomy controls keep their default selection behavior. Projects that store
selected terms together with their ancestors can opt in:

```php
add_filter('sp_taxonomy_include_ancestors', '__return_true');
```

The filter receives the default `false`, taxonomy slug and post ID. With it
enabled for a hierarchical taxonomy, checklist and radio saves retain ancestor
terms, radio controls display the deepest selected term, and other post-save
taxonomy assignments are expanded at `save_post` priority 20. Existing saved
terms are not migrated. Return `false` to retain the default behavior.
