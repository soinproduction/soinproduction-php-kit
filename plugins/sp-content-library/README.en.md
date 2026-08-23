# SP Content Library

A shared library for two compatible reusable content types:

- **Reusable Sections** — the existing `widgets` post type, `builder` ACF field and `widgets_category` taxonomy;
- **Editor Blocks** — the existing `for-editor` post type and `blocks` ACF flexible field.

Both pages live under **Appearance**. Only visible admin labels change: internal slugs, meta keys, REST identifiers, WPML/Polylang relationships, the `[widget]` shortcode and all existing records remain unchanged.

The module also owns the complete reusable-content workflow:

- the `section_widgets` Builder layout, catalog UI, import and “save as reusable section” AJAX actions;
- package-owned rendering for `template_parts/section-widgets/index`;
- the `sp_widgets` TinyMCE plugin, `[widget]` renderer, catalog/preview/create/duplicate AJAX actions and ACF editing iframe.

Theme-specific Builder layouts, their frontend template parts, `display_blocks()`, editor CSS and preview images remain in the theme. Existing TinyMCE configuration can keep mapping `sp_widgets` to the global compatibility class `SP_Widgets_Plugin`.

## Configuration

```php
'sp-content-library' => [
	'menu_parent' => 'themes.php',
	'editor_layouts' => [
		'author_quote',
		'blockquote',
	],
],
```

`editor_layouts` contains theme callback names. Every callback must return a callable that adds its layout to ACF Flexible Content. Per-layout arguments can be supplied explicitly:

```php
'editor_layouts' => [
	'author_quote',
	[
		'callback' => 'editor',
		'args'     => [ 'media_upload' => 0 ],
	],
],
```

The field factory defaults to `blocks`, while Reusable Sections use `sp_builder_add_flexible_field`. Set `editor_field_factory` and `builder_field_callback` for a different theme architecture.

Removing a layout from the configuration hides it from new Editor Blocks. Ensure saved records no longer use it before removing it.

The package is the single runtime source. Theme directories `core/mce/sp-widgets/` and `template_parts/section-widgets/` must not coexist with this module.
