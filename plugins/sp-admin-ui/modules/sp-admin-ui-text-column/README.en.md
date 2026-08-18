# SP Admin UI Text Column

Provides `register_acf_text_column()` for read-only text columns in WordPress post-type and taxonomy list tables. A non-empty `acf_field` displays a scalar ACF value. An empty `acf_field` displays the native post excerpt or term description.

```php
add_action( 'after_setup_theme', static function (): void {
	register_acf_text_column(
		type: 'post',
		object: 'service',
		column_label: 'Price',
		after: 'title',
		acf_field: 'service_price',
		column_key: 'service_price',
		width: 120,
		prefix: 'AED ',
	);

	register_acf_text_column(
		type: 'post',
		object: 'service',
		column_label: 'Excerpt',
		after: 'service_price',
		column_key: 'service_excerpt',
		width: 320,
		max_words: 24,
	);
} );
```

Register the function more than once with unique `column_key` values to show both ACF fields and native excerpts. `type` accepts `post` or `term`; terms use their description when `acf_field` is empty. `width` is measured in pixels, and `max_words: 0` disables trimming. `prefix` and `suffix` are applied only to non-empty values.
