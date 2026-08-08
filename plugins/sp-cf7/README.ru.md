# SP CF7

Единый набор переиспользуемых интеграций Contact Form 7. Подключается именем `sp-cf7` в списке `plugins` PHP Kit.

Каждый подмодуль имеет одинаковую структуру: `modules/<name>/index.php`, `README.en.md` и `README.ru.md`.

| Модуль | Назначение |
| --- | --- |
| `sp-cf7-core` | Общее поведение CF7 и ACF-список форм. |
| `sp-cf7-mail-viewer` | Приватный журнал подготовленных писем CF7. |
| `sp-cf7-mailchimp-sync` | Синхронизация с Mailchimp audience. |
| `sp-cf7-webhook` | Исходящий HTTP webhook отдельной формы. |
| `sp-cf7-redirects` | Redirect/modal metadata в разметке форм. |
| `sp-cf7-select-field` | Custom Select shortcode, form tag и mail tags. |
| `sp-cf7-icon-generator` | Генератор UI Icon в редакторе CF7. |

По умолчанию загружаются все подмодули. Управлять ими можно прямо в массиве `plugins`; префикс `_` оставляет подмодуль в списке, но отключает его:

```php
'plugins' => [
	'sp-cf7' => [
		'sp-cf7-core',
		'sp-cf7-mail-viewer',
		'_sp-cf7-mailchimp-sync',
		'_sp-cf7-webhook',
		'sp-cf7-redirects',
		'sp-cf7-select-field',
		'sp-cf7-icon-generator',
	],
],
```

Пустой массив `sp-cf7` не загружает ни одного подмодуля. Для динамической настройки остаётся фильтр `sp_cf7_modules`.
