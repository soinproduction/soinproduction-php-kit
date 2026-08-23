# List Columns

[Back to MCE plugins](../README.md)

## RU

### Что делает

Добавляет кнопку `list_columns`, которая переключает список в двухколоночный режим.

### Как работает

- `index.php` регистрирует TinyMCE-плагин и отдает `script.js` через `?sp_list_columns_js=1`.
- При клике на кнопку выбранному `ul` или `ol` добавляется/убирается `data-column="2"`.
- `configure_tinymce()` расширяет valid elements для `ul[data-column]` и `ol[data-column]`.
- `register_editor_styles()` подключает `style.css` в редактор.

### Фронтенд

Фронтенд должен иметь CSS, который читает `data-column="2"` и раскладывает список в колонки.

## EN

### What it does

Adds the `list_columns` button, toggling a list into a two-column mode.

### How it works

- `index.php` registers the TinyMCE plugin and serves `script.js` through `?sp_list_columns_js=1`.
- Clicking the button toggles `data-column="2"` on the selected `ul` or `ol`.
- `configure_tinymce()` allows `ul[data-column]` and `ol[data-column]`.
- `register_editor_styles()` loads `style.css` into the editor.

### Frontend

Frontend CSS should read `data-column="2"` and render the list as columns.
