# Custom Lists

[Back to MCE plugins](../README.md)

## RU

### Что делает

Добавляет кнопку `custom_lists`, которая применяет визуальные стили к спискам или отдельным пунктам списка.

### Как работает

- `index.php` регистрирует TinyMCE-плагин и отдает `script.js` через `?sp_custom_lists_js=1`.
- `script.js` открывает диалог с наборами стилей и SVG-превью.
- Стили применяются через классы вида `list-*` для всего списка или `item-*` для выбранных `li`.
- Есть отдельные действия для list-level и item-level оформления.

### Использование

Выдели список или пункт списка, нажми кнопку и выбери стиль. Повторное применение другого стиля заменяет старый стиль этого типа.

## EN

### What it does

Adds the `custom_lists` TinyMCE button for applying visual styles to full lists or individual list items.

### How it works

- `index.php` registers the TinyMCE plugin and serves `script.js` through `?sp_custom_lists_js=1`.
- `script.js` opens a dialog with style groups and SVG previews.
- Styles are applied through `list-*` classes for whole lists or `item-*` classes for selected `li` elements.
- List-level and item-level styling are handled separately.

### Usage

Select a list or list item, click the button, and choose a style. Applying another style of the same type replaces the previous one.
