# SP Term Selector

ACF taxonomy field/factory `smart_taxonomy()` с режимами manual/all, поиском, сортировкой выбранных terms, фильтрацией таксономий и thumbnails терминов.

```php
->addFields( smart_taxonomy( 'service_areas', [
	'taxonomy'      => [ 'city', 'region' ],
	'return_format' => 'id',
	'modes'         => [ 'manual', 'all' ],
] ) )
```

Поддерживаются `min`/`max`, возврат IDs или `WP_Term` и настраиваемое thumbnail field. Полный контракт описан в `README.en.md`.

AJAX защищён nonce `sp_stax`; каждая taxonomy проверяется и требует её capability `assign_terms`. Picker загружается лениво около viewport, пропускает ACF clone templates и разделяет одинаковые ответы в течение 60 секунд.
