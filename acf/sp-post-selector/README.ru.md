# SP Post Selector

ACF relationship field/factory `smart_relationship()` с режимами manual/favorites/all, поиском, сортировкой выбранных записей, taxonomy filters и thumbnails.

```php
->addFields( smart_relationship( 'team_members', [
	'post_type'     => [ 'team' ],
	'return_format' => 'id',
	'modes'         => [ 'manual', 'favorites', 'all' ],
] ) )
```

Поддерживаются ограничения `min`/`max`, taxonomy filters и разные источники thumbnail. Полный список опций находится в `README.en.md`.
