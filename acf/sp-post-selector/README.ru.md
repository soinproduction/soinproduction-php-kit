# SP Post Selector

ACF relationship field/factory `smart_relationship()` с режимами manual/favorites/related/all, поиском, сортировкой выбранных записей, taxonomy filters, ограничением по термам и thumbnails.

```php
->addFields( smart_relationship( 'team_members', [
	'post_type'     => [ 'team' ],
	'taxonomy'      => [ 'department' ],
	'taxonomy_terms' => [ 'department:12', 'department:18' ],
	'return_format' => 'id',
	'modes'         => [ 'manual', 'favorites', 'related', 'all' ],
	'related_fields' => [ 'linked_team' ],
] ) )
```

Сначала в `taxonomy` выбираются таксономии; после сохранения группы их термы становятся доступны в настройке `taxonomy_terms`. Она опционально ограничивает доступные и возвращаемые записи выбранными термами в формате `taxonomy:term_id`. Несколько термов одной таксономии объединяются как `IN`, а ограничения разных таксономий — как `AND`. В самом picker дополнительный dropdown таксономии не выводится.

Режим `related` читает выбранные `related_fields`; если список пуст, автоматически используются поля `linked_{post_type}`. Также поддерживаются ограничения `min`/`max` и разные источники thumbnail. `thumb_field` принимает имя ACF-поля или упорядоченный массив имён; используется первое найденное изображение. Полный список опций находится в `README.en.md`.

AJAX защищён nonce `sp_srel`; каждый post type проверяется и требует его capability `edit_posts`. Picker загружается лениво около viewport, пропускает ACF clone templates и разделяет одинаковые ответы в течение 60 секунд.
