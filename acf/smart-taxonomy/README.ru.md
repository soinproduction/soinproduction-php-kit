# Smart Taxonomy

ACF taxonomy field/factory `smart_taxonomy()` с режимами manual/all, поиском, сортировкой выбранных terms, фильтрацией таксономий и thumbnails терминов.

```php
->addFields( smart_taxonomy( 'service_areas', [
	'taxonomy'      => [ 'city', 'region' ],
	'return_format' => 'id',
	'modes'         => [ 'manual', 'all' ],
] ) )
```

Поддерживаются `min`/`max`, возврат IDs или `WP_Term` и настраиваемое thumbnail field. Полный контракт описан в `README.en.md`.
