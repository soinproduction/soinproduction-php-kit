# Social List

[Back to MCE plugins](../README.md)

## RU

### Что делает

Добавляет кнопку `social_list`, которая вставляет `[social_list]`.

### Как работает

- `index.php` регистрирует TinyMCE-плагин и отдает `script.js` через `?sp_social_list_js=1`.
- `script.js` вставляет shortcode в текущую позицию редактора.
- Вывод реализован темой в `core/helpers/shortcodes_fields.php`: shortcode рендерит menu location `social_list` через `Custom_Nav_Walker`.
- Если menu location не назначен или не содержит items, shortcode не создаёт полезный список.

### Назначение

Быстрая вставка блока социальных ссылок без ручного набора shortcode.

## EN

### What it does

Adds the `social_list` button, inserting `[social_list]`.

### How it works

- `index.php` registers the TinyMCE plugin and serves `script.js` through `?sp_social_list_js=1`.
- `script.js` inserts the shortcode at the current editor position.
- The theme implements output in `core/helpers/shortcodes_fields.php`: the shortcode renders the `social_list` menu location through `Custom_Nav_Walker`.
- If that menu location is unassigned or empty, the shortcode cannot render a useful list.

### Purpose

Quick insertion of a social-links block without manually typing the shortcode.
