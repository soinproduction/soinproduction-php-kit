# Icon Link List

Пользовательское ACF-поле `sp_icon_link_list` для сортируемого списка пар icon + нативная ACF link.

```php
->addFields( sp_icon_link_list( 'social_links', [
	'label'        => 'Social links',
	'max_items'    => 6,
	'button_label' => 'Add link',
] ) )
```

Значение нормализуется в `icon_id` и `link` (`url`, `title`, `target`). Публичный API: `sp_acf_icon_link_list_normalize()`, `sp_acf_icon_link_list_format()` и Builder factory `sp_icon_link_list()`.
