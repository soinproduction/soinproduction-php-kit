# Table Builder

[Back to MCE plugins](../README.md)

## RU

### Что делает

Добавляет расширенный TinyMCE table builder под кнопкой `table`.

### Как работает

- `index.php` регистрирует TinyMCE-плагин и отдает `script.js` через `?sp_table_builder_js=1`.
- `script.js` содержит набор команд для таблиц: insert/delete table, rows, columns, cells, merge/split и настройки таблицы.
- `index.php` отключает стандартный `table_toolbar`, чтобы не конфликтовать с кастомным UX.
- Фильтр `content_save_pre` дополнительно нормализует переносы вокруг таблиц при сохранении.

### Назначение

Используется там, где стандартных возможностей TinyMCE для таблиц недостаточно.

## EN

### What it does

Adds an advanced TinyMCE table builder under the `table` button.

### How it works

- `index.php` registers the TinyMCE plugin and serves `script.js` through `?sp_table_builder_js=1`.
- `script.js` provides table commands: insert/delete table, rows, columns, cells, merge/split, and table settings.
- `index.php` disables the default `table_toolbar` to avoid conflicts with the custom UX.
- The `content_save_pre` filter normalizes line breaks around tables on save.

### Purpose

Use it when default TinyMCE table editing is not enough.
