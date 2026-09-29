# SP Archive Builder

ACF-поля/factories `archive_builder()` и `taxonomy_archive_builder()` настраивают архивы записей и терминов таксономий. Они используют единый модуль, одинаковый интерфейс в админке и общий frontend runtime: taxonomy filters, сортировка, количество элементов, pagination/load more/infinite scroll, empty state и confirm/reset.

```php
->addFields( archive_builder( 'archive', [
	'post_type'       => 'case_study',
	'filters_enabled' => 1,
	'per_page'        => 9,
	'pagination_type' => 'pagination',
] ) )
```

Оба поля поддерживают `source_mode => all|manual`. В Manual редактор выбирает записи через Smart Relationship или термины через Smart Taxonomy и выставляет порядок перетаскиванием. Фильтры и пагинация работают только внутри выбранного набора, а Sorting order скрывается, потому что manual-порядок является приоритетным.

```php
->addFields( taxonomy_archive_builder( 'industries', [
	'taxonomy'        => 'case_study_industry',
	'source_mode'     => 'all',
	'per_page'        => 9,
	'pagination_type' => 'infinity_scroll',
	'order_mode'      => 'az',
] ) )
```

Модуль также предоставляет семейство `sp_archive_*` для подготовки query, filters, cards и pagination. Объединённая версия поддерживает `term_scope`, режимы терминов `selected` / `children` / `parent`, группировку по первому фильтру, собственные подписи, `favorite_first`, confirm/reset и настраиваемые query args. Полный список опций и helpers приведён в `README.en.md`.

Для `per_page` поддерживаются положительное число, `-1` и строка `all`; два последних значения выводят все найденные записи без pagination. Шаблоны карточек могут находиться в `template_parts/`, `templates/`, `php/cards/` или `php/templates/`. UI-шаблоны автоматически ищутся в структурах Alexandra, Keramida и LDW, а путь можно переопределить через `sp_archive_component_template`.

Текущий язык Polylang/WPML сохраняется в серверной конфигурации и применяется также к AJAX-запросам. По умолчанию проверяется nonce `ajax_global`; для LDW или другой темы с отдельным nonce добавьте `add_filter('sp_archive_nonce_action', static fn(): string => 'sp_ajax_nonce');`.

## Фильтры по связанным записям

Для архивов записей доступна настройка `relationship_filters` в `archive_builder()`,
конфигурации ACF-поля или третьем аргументе `sp_archive_setup()`. Это настройка
разработчика, отдельной панели в редакторе ACF нет. Требуется `filters_enabled => 1`.

```php
sp_archive_setup($archive, 'php/cards/testimonial-card', [
    'relationship_filters' => [
        'testimonial_industry' => [
            'post_type' => 'markets',
            'reverse_field' => 'linked_testimonials',
            'all_label' => 'Industries',
            'ui' => 'select',
        ],
        'testimonial_service' => [
            'post_type' => 'services',
            'reverse_field' => 'linked_testimonials',
            'all_label' => 'Service',
            'ui' => 'select',
        ],
    ],
]);
```

Варианты — опубликованные записи указанного типа. `reverse_field` — метаполе
на выбранной записи Service/Market с массивом ID отзывов. Модуль
`sp-post-relationships` поддерживает эти обратные связи автоматически.
Несколько значений одного фильтра объединяются; разные фильтры пересекаются.
Результат также ограничивается ручной выборкой и фильтрами таксономий.
Сортировка, пагинация, Show More и `disable_empty` применяются к итоговой выборке.
Пустое пересечение возвращает ноль записей, ручной порядок сохраняется.

Значения опций и параметры URL — слаги (`post_name`) записей, например
`?testimonial_service=energy-audits-for-buildings`. Старые ссылки с ID также
принимаются и преобразуются в слаги в состоянии архива. Для названий опций
используется непустое метаполе `short_title`, иначе — `get_the_title()`.

Кнопка сброса требует и включённого `reset` в настройках ACF, и вызова
`sp_archive_reset()` в шаблоне секции: переключатель сам не добавляет разметку.

Вывод выполняет обычный `sp_archive_filters()`. Поддерживаются все пять видов
контролов. Можно задать `label` и `query_arg`; имена не должны совпадать с
параметрами сортировки, пагинации, другими фильтрами или служебными AJAX-полями.

**Нужна совместимая JS-логика темы:** при чтении конфигурации разрешите
`type === "relationship"` наряду с `taxonomy`, затем пересоберите JS:

```js
.filter(({name, taxonomy, type}) => name && (taxonomy || type === "relationship"))
```

Полный контракт, пример и команда интеграционного теста — в `README.en.md`.
