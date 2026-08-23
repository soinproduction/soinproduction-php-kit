# Editor Row

[Back to MCE plugins](../README.md)

## RU

### Что делает

Добавляет кнопку `sp_editor_row`, которая объединяет выбранные inline-элементы, виджеты и текст в строку.

### Как работает

- `index.php` регистрирует TinyMCE-плагин и подключает `script.js` с cache-busting через `filemtime`.
- `script.js` работает с выделением редактора и создает wrapper `.sp-editor-inline-row` / `.row[data-editor-row]`.
- Плагин содержит защиту от вложенных строк, cleanup-проходы и обработку caret вокруг виджетов.
- На фронте строка уже может стилизоваться существующей логикой `.row`.

### Важно

Это редакторский UX-механизм для осознанного inline layout. Если меняется HTML-структура строки, нужно проверять сохранение в Visual/Text режимах.

## EN

### What it does

Adds the `sp_editor_row` button, grouping selected inline elements, widgets, and text into a row.

### How it works

- `index.php` registers the TinyMCE plugin and serves `script.js` with `filemtime` cache busting.
- `script.js` works with the editor selection and creates `.sp-editor-inline-row` / `.row[data-editor-row]` wrappers.
- The plugin includes nested-row protection, cleanup passes, and caret handling around widgets.
- Frontend rendering can rely on the existing `.row` behavior.

### Notes

This is an editor UX mechanism for intentional inline layout. Any HTML structure changes should be checked in both Visual and Text modes.
