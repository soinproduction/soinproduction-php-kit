# Taxonomy Radio

Provides `sp_taxonomy_radio_all_terms()` as a single-select taxonomy metabox callback. It lists all terms, supports clearing the relationship, and links authorized users to the taxonomy management screen.

```php
add_meta_box(
	'taxonomy-genre',
	'Genre',
	'sp_taxonomy_radio_all_terms',
	'book',
	'side',
	'default',
	[ 'taxonomy' => 'genre' ],
);
```

The save handler verifies request context and permissions, then writes at most one term relationship.
