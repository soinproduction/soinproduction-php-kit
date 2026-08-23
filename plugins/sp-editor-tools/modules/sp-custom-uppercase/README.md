# Custom Uppercase

[Back to MCE plugins](../README.md)

## RU

### Что делает

Добавляет кнопку `textcase_elem` для управления регистром текста.

### Как работает

- `index.php` регистрирует TinyMCE-плагин и отдает `script.js` через `?sp_custom_uppercase_js=1`.
- `script.js` открывает диалог `Text case`.
- Доступны варианты: reset, uppercase, lowercase, capitalize.
- Выбранный режим применяется через inline `text-transform`.

### Когда использовать

Для точечной настройки регистра текста без добавления новых CSS-классов.

## EN

### What it does

Adds the `textcase_elem` button for controlling text casing.

### How it works

- `index.php` registers the TinyMCE plugin and serves `script.js` through `?sp_custom_uppercase_js=1`.
- `script.js` opens the `Text case` dialog.
- Available options: reset, uppercase, lowercase, capitalize.
- The selected mode is applied through inline `text-transform`.

### Use case

Useful for local text casing adjustments without adding new CSS classes.
