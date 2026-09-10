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

Модуль также предоставляет семейство `sp_archive_*` для подготовки query, filters, cards и pagination. Объединённая версия поддерживает `term_scope`, режимы терминов `selected` / `children` / `parent`, группировку по первому фильтру, собственные подписи, `favorite_first`, confirm/reset и настраиваемые query args. Полный список опций и helpers приведён в `README.en.md`.

Для `per_page` поддерживаются положительное число, `-1` и строка `all`; два последних значения выводят все найденные записи без pagination. Шаблоны карточек могут находиться в `template_parts/`, `templates/`, `php/cards/` или `php/templates/`. UI-шаблоны автоматически ищутся в структурах Alexandra, Keramida и LDW, а путь можно переопределить через `sp_archive_component_template`.

Текущий язык Polylang/WPML сохраняется в серверной конфигурации и применяется также к AJAX-запросам. По умолчанию проверяется nonce `ajax_global`; для LDW или другой темы с отдельным nonce добавьте `add_filter('sp_archive_nonce_action', static fn(): string => 'sp_ajax_nonce');`.
