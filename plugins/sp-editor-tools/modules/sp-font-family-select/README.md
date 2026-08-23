# Font Family Select

[Back to MCE plugins](../README.md)

## RU

### Что делает

Добавляет в TinyMCE listbox `font_family_select` для выбора font-family.

### Как работает

- `index.php` регистрирует TinyMCE-плагин и отдает `script.js` через `?sp_font_family_select_js=1`.
- В dropdown доступны заранее заданные семейства шрифтов, например IBM Plex Sans и IBM Plex Mono.
- Выбор применяется через inline `font-family`.

### Когда использовать

Для редких мест, где нужен ручной font-family override внутри редактора.

## EN

### What it does

Adds the `font_family_select` TinyMCE listbox for choosing a font family.

### How it works

- `index.php` registers the TinyMCE plugin and serves `script.js` through `?sp_font_family_select_js=1`.
- The dropdown contains predefined font families such as IBM Plex Sans and IBM Plex Mono.
- The selected value is applied through inline `font-family`.

### Use case

Useful for rare cases where a manual font-family override is needed inside the editor.
