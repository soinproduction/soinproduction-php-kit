# Shortcode Button

[Back to MCE plugins](../README.md)

## RU

### Что делает

Добавляет кнопку `shortcode_button`, которая вставляет доступные shortcode в редактор.

### Как работает

- `index.php` регистрирует TinyMCE-плагин и отдает `script.js` через `?sp_shortcode_button_js=1`.
- `script.js` читает список shortcode из `window.ajax_params.shortcodes`.
- После выбора элемент вставляется в текущую позицию caret.

### Важно

Список shortcode должен быть заранее передан в `window.ajax_params.shortcodes`.

## EN

### What it does

Adds the `shortcode_button` TinyMCE button for inserting available shortcodes.

### How it works

- `index.php` registers the TinyMCE plugin and serves `script.js` through `?sp_shortcode_button_js=1`.
- `script.js` reads available shortcodes from `window.ajax_params.shortcodes`.
- The selected shortcode is inserted at the current caret position.

### Notes

The shortcode list must be provided through `window.ajax_params.shortcodes`.
