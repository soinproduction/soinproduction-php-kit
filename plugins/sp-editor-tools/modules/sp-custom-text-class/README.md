# Custom Text Class

[Back to MCE plugins](../README.md)

## RU

### Что делает

Добавляет кнопку `tag_style_selector` для выбора typography-класса у выбранного текстового блока.

### Как работает

- `index.php` регистрирует TinyMCE-плагин и отдает `script.js` через `?sp_custom_text_class_js=1`.
- Доступные классы приходят из `TAG_STYLE_SELECTOR.classes`.
- `script.js` применяет класс к ближайшему блочному элементу: `a`, `p`, `h1-h6`, `li`, `div`.
- Выбор `default` очищает ранее назначенный typography-класс.

### Когда использовать

Когда текст должен использовать готовый стиль из дизайн-системы, а не ручные inline-настройки.

## EN

### What it does

Adds the `tag_style_selector` button for applying typography classes to the selected text block.

### How it works

- `index.php` registers the TinyMCE plugin and serves `script.js` through `?sp_custom_text_class_js=1`.
- Available classes are read from `TAG_STYLE_SELECTOR.classes`.
- `script.js` applies the class to the nearest block element: `a`, `p`, `h1-h6`, `li`, or `div`.
- Choosing `default` clears the previously applied typography class.

### Use case

Use it when text should follow a design-system style instead of manual inline formatting.
