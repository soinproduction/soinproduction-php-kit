# TOC Item

[Back to MCE plugins](../README.md)

## RU

### Что делает

Добавляет кнопку `toc_item`, которая помечает заголовки как элементы table of contents.

### Как работает

- `index.php` регистрирует TinyMCE-плагин и отдает `script.js` через `?sp_toc_item_js=1`.
- `script.js` добавляет меню: Main TOC Item, Sub TOC Item и Remove TOC marker.
- Выбранным заголовкам назначается `data-toc-item="parent"` или `data-toc-item="child"`.
- PHP-класс `SP_TOC` сканирует только `h1`–`h6` с `data-toc-item`, генерирует недостающие ID и заменяет `[toc]` готовой разметкой.
- Обработка `SP_TOC::process_content_and_generate_toc()` сейчас вызывается шаблоном `acf/blocks-layout/block-editor/index.php`. В другом WYSIWYG обычный WordPress shortcode handler возвращает пустую строку.
- TinyMCE может поставить marker и на paragraph, но PHP scanner не включает `p` в TOC.

### Назначение

Для длинных страниц и статей, где оглавление должно собираться из выбранных заголовков, а не из всех подряд.

## EN

### What it does

Adds the `toc_item` button for marking headings as table-of-contents items.

### How it works

- `index.php` registers the TinyMCE plugin and serves `script.js` through `?sp_toc_item_js=1`.
- `script.js` adds a menu: Main TOC Item, Sub TOC Item, and Remove TOC marker.
- Selected headings receive `data-toc-item="parent"` or `data-toc-item="child"`.
- The PHP `SP_TOC` class scans only marked `h1`–`h6` elements, generates missing IDs, and replaces `[toc]` with the built markup.
- `SP_TOC::process_content_and_generate_toc()` is currently invoked by `acf/blocks-layout/block-editor/index.php`. In another WYSIWYG context, the ordinary WordPress shortcode handler returns an empty string.
- TinyMCE can put the marker on a paragraph, but the PHP scanner does not include `p` in the TOC.

### Purpose

Use it for long pages and posts where the TOC should be built from selected headings rather than every heading.
