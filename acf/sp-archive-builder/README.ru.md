# SP Archive Builder

ACF field/factory `archive_builder()` для настройки архивов: taxonomy filters, сортировка, количество записей, pagination/load more/infinite scroll, empty state и confirm/reset.

```php
->addFields( archive_builder( 'archive', [
	'post_type'       => 'case_study',
	'filters_enabled' => 1,
	'per_page'        => 9,
	'pagination_type' => 'pagination',
] ) )
```

Модуль также предоставляет семейство `sp_archive_*` для подготовки query, filters, cards и pagination. Полный список опций и helpers приведён в `README.en.md`.
