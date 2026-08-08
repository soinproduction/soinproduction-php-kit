# SP CF7

Единый набор переиспользуемых интеграций Contact Form 7. Подключается именем `sp-cf7` в списке `plugins` PHP Kit.

Каждый подмодуль имеет одинаковую структуру: `modules/<name>/index.php`, `README.en.md` и `README.ru.md`.

| Модуль | Назначение |
| --- | --- |
| `base` | Общее поведение CF7 и ACF-список форм. |
| `mail-viewer` | Приватный журнал подготовленных писем CF7. |
| `flowchimp` | Синхронизация с Mailchimp audience. |
| `webhook` | Исходящий HTTP webhook отдельной формы. |
| `redirects` | Redirect/modal metadata в разметке форм. |
| `ui-select` | Custom Select shortcode, form tag и mail tags. |
| `icon-generator` | Генератор UI Icon в редакторе CF7. |

По умолчанию загружаются все подмодули. Управлять ими можно прямо в массиве `plugins`; префикс `_` оставляет подмодуль в списке, но отключает его:

```php
'plugins' => [
	'sp-cf7' => [
		'base',
		'mail-viewer',
		'_flowchimp',
		'_webhook',
		'redirects',
		'ui-select',
		'icon-generator',
	],
],
```

Пустой массив `sp-cf7` не загружает ни одного подмодуля. Для динамической настройки остаётся фильтр `sp_cf7_modules`.
