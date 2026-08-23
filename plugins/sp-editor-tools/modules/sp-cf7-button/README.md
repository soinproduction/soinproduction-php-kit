# CF7 Button

[Back to MCE plugins](../README.md)

## RU

### Что делает

Добавляет кнопку `cf7_button`, которая вставляет shortcode Contact Form 7 в редактор.

### Как работает

- `index.php` регистрирует TinyMCE-плагин и отдает `script.js` через `?sp_cf7_button_js=1`.
- `script.js` берет список форм из `window.ajax_params.cf7_forms`.
- После выбора формы в редактор вставляется соответствующий CF7 shortcode.

### Важно

Плагин зависит от того, что список форм уже передан в `window.ajax_params.cf7_forms`.

## EN

### What it does

Adds the `cf7_button` TinyMCE button for inserting Contact Form 7 shortcodes.

### How it works

- `index.php` registers the TinyMCE plugin and serves `script.js` through `?sp_cf7_button_js=1`.
- `script.js` reads available forms from `window.ajax_params.cf7_forms`.
- Once a form is selected, the matching CF7 shortcode is inserted into the editor.

### Notes

The plugin expects `window.ajax_params.cf7_forms` to be available on the admin page.
