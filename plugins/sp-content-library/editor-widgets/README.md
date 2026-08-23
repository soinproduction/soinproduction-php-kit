# Widgets

[Back to MCE plugins](../README.md)

## RU

### Что делает

Добавляет кнопку `sp_widgets` для вставки reusable widgets/sections в редактор с визуальным preview вместо голого shortcode.

### Как работает

- `index.php` регистрирует shortcode `[widget id="..."]` и AJAX endpoints.
- `script.js` открывает модалку `Insert Widget / Section`, показывает доступные widgets и позволяет вставить или продублировать их.
- Изображение карточки сначала берётся из featured thumbnail виджета; fallback ищется по имени первого доступного layout в `admin/acf-flex-preview/<layout-name>.png`.
- В редактор вставляется shortcode, но в Visual mode он превращается в preview-блок `.sp-editor-widget`.
- Preview можно редактировать через встроенную кнопку-карандаш.
- Поддерживается `align`, чтобы frontend мог выровнять widget.

### AJAX endpoints

- `sp_get_widgets_list` — список доступных widgets.
- `sp_create_new_widget` — создание нового widget.
- `sp_duplicate_widget` — создание editable копии.
- `sp_render_widget_preview` — HTML preview для редактора.

### Важно

Этот плагин тесно связан с `sp-editor-row`: вместе они позволяют собирать несколько widgets и текстовых элементов в одну строку.

## EN

### What it does

Adds the `sp_widgets` button for inserting reusable widgets/sections into the editor with visual previews instead of raw shortcodes.

### How it works

- `index.php` registers the `[widget id="..."]` shortcode and AJAX endpoints.
- `script.js` opens the `Insert Widget / Section` modal, lists available widgets, and allows inserting or duplicating them.
- A card image uses the widget featured thumbnail first; its fallback is resolved from the first available layout name at `admin/acf-flex-preview/<layout-name>.png`.
- The editor stores a shortcode, but Visual mode renders it as a `.sp-editor-widget` preview block.
- The preview can be edited through an inline pencil button.
- `align` is supported so frontend output can be aligned.

### AJAX endpoints

- `sp_get_widgets_list` — list available widgets.
- `sp_create_new_widget` — create a new widget.
- `sp_duplicate_widget` — create an editable copy.
- `sp_render_widget_preview` — render editor preview HTML.

### Notes

This plugin is closely related to `sp-editor-row`: together they allow multiple widgets and text elements to be composed in one row.
