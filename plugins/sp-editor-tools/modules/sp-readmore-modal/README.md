# Read More Modal

[Back to MCE plugins](../README.md)

## RU

### Что делает

Добавляет кнопку `sp_read_more_modal_img` для вставки и редактирования read-more кнопки.

### Как работает

- `index.php` регистрирует TinyMCE-плагин и отдает `script.js` через `?sp_readmore_modal_js=1`.
- `script.js` поддерживает TinyMCE 4 и TinyMCE 5 API.
- Модалка создает или обновляет `<button type="button" data-read-more>`.
- В button сохраняются тексты `data-more-text`, `data-less-text`, класс и выбранная картинка/иконка.
- При редактировании существующей кнопки данные читаются обратно из DOM.

### Назначение

Для интерактивных блоков, где часть контента должна раскрываться по кнопке.

## EN

### What it does

Adds the `sp_read_more_modal_img` button for inserting and editing a read-more button.

### How it works

- `index.php` registers the TinyMCE plugin and serves `script.js` through `?sp_readmore_modal_js=1`.
- `script.js` supports both TinyMCE 4 and TinyMCE 5 APIs.
- The modal creates or updates `<button type="button" data-read-more>`.
- The button stores `data-more-text`, `data-less-text`, class, and selected image/icon data.
- When editing an existing button, settings are read back from the DOM.

### Purpose

Use it for interactive content blocks where part of the content should expand on click.
