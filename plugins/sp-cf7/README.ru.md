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
| `sp-cf7-messages` | Rich success/error сообщения отдельной формы в модальном редакторе. |
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
		'sp-cf7-messages',
		'sp-cf7-select-field',
		'sp-cf7-icon-generator',
	],
],
```

Пустой массив `sp-cf7` не загружает ни одного подмодуля. Для динамической настройки остаётся фильтр `sp_cf7_modules`.

## Настройка Submit Action

```php
'sp-cf7' => [
    'submit_actions' => ['none', 'redirect', 'modal', 'message'],
    // Optional: 'modules' => ['sp-cf7-core', 'sp-cf7-redirects', 'sp-cf7-messages'],
],
```

Удалите ненужное действие из `submit_actions`. `none` (Default) always remains
as the safe fallback. Omitting `submit_actions` preserves all actions provided by
loaded modules; `message` requires `sp-cf7-messages`. The restriction applies to
the editor choices and fields, saving, and frontend behavior. Previously saved
disabled actions behave as Default; their target metadata is retained.

With this named configuration, omitting `modules` loads all default modules.
`modules => []` disables all modules. Legacy numeric module lists, including an
empty list, keep their original behavior.

## Frontend component

`components.json` declares `assets/form-validate.js` with the `.wpcf7` selector. A manifest-aware theme build imports it lazily; there is no separate PHP enqueue and no theme adapter is needed. Remove the old theme component to avoid duplicate registration.

The runtime preserves loader states, CF7 success/error handling, redirect/modal/message actions and `message_target` replacement/restoration (5 seconds). WordPress Contact Form 7 still performs form submission and validation. Modal actions use the optional `window.modalManager`; styling stays in the theme.

Build dependency: `@soinproduction/kit` (tested with 1.1.14), providing `fadeIn`, `fadeOut` and `loaderInstanse`. Configure the bundler to resolve imports from the theme's node_modules even for entries under Composer vendor, for example:

```js
resolve: {
  modules: [path.resolve(themeRoot, 'node_modules'), 'node_modules'],
}
```

Here `themeRoot` is the frontend source directory containing package.json. Rebuild frontend assets after Composer updates. No WordPress REST calls or form markup changes are required for this migration.
