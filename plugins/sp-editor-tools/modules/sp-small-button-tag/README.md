# Small Button Tag

[Back to MCE plugins](../README.md)

## RU

### Что делает

Добавляет кнопку `small_toggle`, которая оборачивает выделение в `<small>`.

### Как работает

- `index.php` регистрирует TinyMCE-плагин и отдает `script.js` через `?sp_small_button_tag_js=1`.
- Если выделение уже внутри `<small>`, плагин снимает wrapper.
- Если выделения нет, может вставить пустой `<small></small>`.
- Состояние кнопки синхронизируется с текущей позицией caret.

### Назначение

Для компактного secondary text без ручного перехода в HTML.

## EN

### What it does

Adds the `small_toggle` button, wrapping the selection in `<small>`.

### How it works

- `index.php` registers the TinyMCE plugin and serves `script.js` through `?sp_small_button_tag_js=1`.
- If the selection is already inside `<small>`, the wrapper is removed.
- If there is no selection, it can insert an empty `<small></small>`.
- Button active state follows the current caret position.

### Purpose

Useful for compact secondary text without switching to HTML mode.
