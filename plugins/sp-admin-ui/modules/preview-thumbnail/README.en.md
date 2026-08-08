# Preview Thumbnail

Provides `register_acf_thumb_column()` for editable image previews in WordPress post-type and taxonomy list tables. Images can come from an ACF image field or the native featured image.

```php
add_action( 'after_setup_theme', static function (): void {
	register_acf_thumb_column(
		type: 'post',
		object: 'book',
		column_label: 'Cover',
		after: 'title',
		acf_field: 'cover',
		size: '60x80',
	);
} );
```

`type` accepts `post` or `term`. An empty `acf_field` selects the native featured image. Editing requires the WordPress media library and, for ACF-backed fields, ACF's `get_field()` and `update_field()` functions.
