# Decor Span Tag

[Back to MCE plugins](../README.md)

## RU

### Что делает

Добавляет кнопку `decor_toggle`, которая оборачивает выделение в `<span class="decor">`.

### Как работает

- `index.php` регистрирует TinyMCE-плагин и отдает `script.js` через `?sp_decor_span_tag_js=1`.
- Если выделение уже внутри `.decor`, плагин снимает эту обертку.
- Если выделения нет, может вставить пустой decor-span для дальнейшего ввода.
- Также добавляется пункт в контекстное меню форматирования.

### Назначение

Для коротких декоративных фрагментов текста, которые должны получить фронтенд-стиль `.decor`.

## EN

### What it does

Adds the `decor_toggle` button, wrapping the selection in `<span class="decor">`.

### How it works

- `index.php` registers the TinyMCE plugin and serves `script.js` through `?sp_decor_span_tag_js=1`.
- If the selection is already inside `.decor`, the wrapper is removed.
- If there is no selection, it can insert an empty decor span for further typing.
- A format context-menu item is also registered.

### Purpose

Use it for short decorative text fragments that should receive the frontend `.decor` style.
