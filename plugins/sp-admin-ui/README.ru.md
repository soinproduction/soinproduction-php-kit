# SP Admin UI

Набор переиспользуемых компонентов интерфейса админки WordPress. Подключается именем `sp-admin-ui` в секции `plugins` конфигурации PHP Kit.

Базовая часть пакета также улучшает совместимый каталог Builder Widgets (`.wsb-radio-field`): при открытии строки прокручивает список к сохранённому виджету и показывает его название в заголовке ACF layout в формате `Widget: Название`. Выбор другого виджета немедленно обновляет заголовок.

| Модуль | Назначение |
| --- | --- |
| `sp-admin-ui-menu-heading` | Добавляет перетаскиваемые некликабельные заголовки в «Внешний вид → Меню». |
| `sp-admin-ui-text-column` | Добавляет редактируемые по месту колонки ACF-текста или нативного excerpt/description в списки записей и терминов. |
| `sp-admin-ui-thumbnail-column` | Добавляет редактируемые колонки изображений в списки записей и терминов. |
| `sp-admin-ui-taxonomy-checklist` | Выводит и сохраняет metabox таксономии с множественным выбором. |
| `sp-admin-ui-taxonomy-radio` | Выводит и сохраняет metabox таксономии с одиночным выбором. |

По умолчанию загружаются все подмодули. Управлять ими можно прямо в массиве `plugins`; префикс `_` оставляет модуль в списке, но отключает его:

```php
'plugins' => [
	'sp-admin-ui' => [
		'sp-admin-ui-menu-heading',
		'sp-admin-ui-text-column',
		'_sp-admin-ui-thumbnail-column',
		'sp-admin-ui-taxonomy-checklist',
		'_sp-admin-ui-taxonomy-radio',
	],
],
```

Для динамической настройки остаётся фильтр `sp_admin_ui_modules`:

```php
add_filter( 'sp_admin_ui_modules', static fn(): array => [
	'sp-admin-ui-menu-heading',
	'sp-admin-ui-taxonomy-radio',
] );
```

Пустой массив `sp-admin-ui` не загружает ни одного подмодуля. Taxonomy-модули используют защищённые общие helpers из `includes/taxonomy.php`. Пакет предоставляет поведение и семантические CSS-классы; оформление может оставаться на стороне проекта.
