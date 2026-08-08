# Icon Link List

Custom ACF field `sp_icon_link_list` for a sortable collection of icon and native ACF link pairs.

```php
->addFields( sp_icon_link_list( 'social_links', [
	'label'        => 'Social links',
	'max_items'    => 6,
	'button_label' => 'Add link',
] ) )
```

Values are normalized to `icon_id` plus `link` (`url`, `title`, `target`). Public helpers are `sp_acf_icon_link_list_normalize()`, `sp_acf_icon_link_list_format()` and the Builder factory `sp_icon_link_list()`.
