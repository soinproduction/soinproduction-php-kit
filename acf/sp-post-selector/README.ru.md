# SP Post Selector

ACF relationship field/factory `smart_relationship()` с режимами manual/favorites/all, поиском, сортировкой выбранных записей, taxonomy filters и thumbnails.

```php
->addFields( smart_relationship( 'team_members', [
	'post_type'     => [ 'team' ],
	'return_format' => 'id',
	'modes'         => [ 'manual', 'favorites', 'all' ],
] ) )
```

Поддерживаются ограничения `min`/`max`, taxonomy filters и разные источники thumbnail. `thumb_field` принимает имя ACF-поля или упорядоченный массив имён; используется первое найденное изображение. Полный список опций находится в `README.en.md`.

AJAX защищён nonce `sp_srel`; каждый post type проверяется и требует его capability `edit_posts`. Picker загружается лениво около viewport, пропускает ACF clone templates и разделяет одинаковые ответы в течение 60 секунд.
