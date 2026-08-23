# Custom Underline

[Back to MCE plugins](../README.md)

## RU

### Что делает

Добавляет кнопку `underline_toggle_elem` для настройки underline/text-decoration у выделения или текущего элемента.

### Как работает

- `index.php` регистрирует TinyMCE-плагин и отдает `script.js` через `?sp_custom_underline_js=1`.
- `script.js` открывает диалог `Text Decoration`.
- Поддерживаются типы линии вроде `solid`, `double`, `dotted`, `dashed`, `wavy`, а также сброс настроек.
- Результат сохраняется как inline `text-decoration` стили.

### Ограничение

Плагин работает на уровне редактора и не создает отдельный reusable CSS-class.

## EN

### What it does

Adds the `underline_toggle_elem` button for configuring underline/text-decoration on the current selection or element.

### How it works

- `index.php` registers the TinyMCE plugin and serves `script.js` through `?sp_custom_underline_js=1`.
- `script.js` opens the `Text Decoration` dialog.
- It supports line styles such as `solid`, `double`, `dotted`, `dashed`, `wavy`, plus reset controls.
- The result is stored as inline `text-decoration` styles.

### Limitation

This is editor-level formatting and does not create a reusable CSS class.
