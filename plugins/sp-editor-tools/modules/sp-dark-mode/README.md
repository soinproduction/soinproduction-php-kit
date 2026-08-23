# Dark Mode

[Back to MCE plugins](../README.md)

## RU

### Что делает

Добавляет Light/Dark preview для визуального редактора, чтобы проверить контент на темном фоне прямо в админке.

### Как работает

- `index.php` регистрирует TinyMCE-плагин и отдает `script.js` через `?sp_dark_mode_js=1`.
- `script.js` переключает классы редактора, включая `mce-dark`.
- Плагин не добавляется как обычная toolbar-кнопка через loader, а работает как вспомогательный режим редактора.

### Где используется

Нужен для проверки контраста, иконок, ссылок и блоков, которые на фронте могут оказаться на темном фоне.

## EN

### What it does

Adds a Light/Dark preview mode for the visual editor so content can be checked against a dark background inside the admin UI.

### How it works

- `index.php` registers the TinyMCE plugin and serves `script.js` through `?sp_dark_mode_js=1`.
- `script.js` toggles editor classes, including `mce-dark`.
- The plugin is not added as a regular toolbar button by the loader; it acts as an editor helper mode.

### Use case

Useful for checking contrast, icons, links, and blocks that may appear on dark frontend sections.
