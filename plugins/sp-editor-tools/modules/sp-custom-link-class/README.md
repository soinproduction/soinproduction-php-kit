# Custom Link Class

[Back to MCE plugins](../README.md)

## RU

### Что делает

Расширяет стандартный WordPress link picker и ACF link field: вместо выбора класса вручную показывает визуальные варианты кнопок, before/after иконки или изображения.

### Как работает

- `index.php` сам подключает `script.js` и CSS-превью из `assets/css/for-link-picker.css`.
- В админку передаются настройки через `ACF_LINK_EXTRAS`, `TAG_STYLE_SELECTOR` и `UIA_LINKPICKER`.
- `script.js` улучшает `wpLink` modal и ACF link field, добавляя drawer с preview кнопок и выбором иконок.
- Выбранные данные сохраняются в структуре ACF link value: `_class`, `_before_icon_url`, `_after_icon_url`.
- Превью кнопок рендерится в iframe, чтобы стили подтягивались ближе к фронтенду и не ломались админскими `rem`.

### Особые случаи

Если у ACF link field в wrapper class стоит `default`, расширенный picker не показывается, остается стандартное поведение ссылки.

## EN

### What it does

Enhances the default WordPress link picker and ACF link field with visual button previews plus before/after icons or images.

### How it works

- `index.php` enqueues `script.js` and the preview stylesheet from `assets/css/for-link-picker.css`.
- Admin settings are passed through `ACF_LINK_EXTRAS`, `TAG_STYLE_SELECTOR`, and `UIA_LINKPICKER`.
- `script.js` enhances the `wpLink` modal and ACF link field with a visual drawer and media/icon selection.
- Selected data is stored inside the ACF link value: `_class`, `_before_icon_url`, `_after_icon_url`.
- Button previews are rendered inside iframes so frontend-like styles are isolated from admin `rem` sizing.

### Special cases

If an ACF link field wrapper has the `default` class, the enhanced picker is skipped and the field behaves like a plain WordPress link.
