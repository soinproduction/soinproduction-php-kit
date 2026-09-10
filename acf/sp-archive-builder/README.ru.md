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

Для `per_page` поддерживаются положительное число, `-1` и строка `all`; два последних значения выводят все найденные записи без pagination. Шаблоны карточек могут находиться в `template_parts/`, `templates/`, `php/cards/` или `php/templates/`. Список путей расширяется фильтром `sp_archive_template_prefixes`, а шаблон pagination переопределяется через `sp_archive_pagination_template`.
