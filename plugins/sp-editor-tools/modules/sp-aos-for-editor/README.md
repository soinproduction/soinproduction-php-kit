# AOS For Editor

[Back to MCE plugins](../README.md)

## RU

### Что делает

Добавляет в TinyMCE кнопку `aosanimate`, через которую можно назначать AOS-анимации выбранному элементу прямо в редакторе.

### Как работает

- `index.php` регистрирует TinyMCE-плагин и отдает `script.js` через endpoint `?sp_aos_for_editor_js=1`.
- `script.js` открывает диалог `AOS Animation`.
- Настройки записываются в `data-aos`, `data-aos-duration`, `data-aos-delay`, `data-aos-offset`, `data-aos-once`, `data-aos-anchor-placement`, `data-aos-easing`.
- Плагин ищет ближайший подходящий host-элемент: `p`, `h1-h6`, `li`, `div`, `section`, `article`, `figure`.

### Где включается

Ключ инструмента: `aosanimate`. Чтобы добавить кнопку в toolbar по умолчанию, добавь ключ в `sp_get_default_editor_tools()`.

## EN

### What it does

Adds the `aosanimate` TinyMCE button, allowing AOS animation attributes to be configured on the selected element directly inside the editor.

### How it works

- `index.php` registers the TinyMCE plugin and serves `script.js` through `?sp_aos_for_editor_js=1`.
- `script.js` opens the `AOS Animation` dialog.
- Settings are stored as `data-aos`, `data-aos-duration`, `data-aos-delay`, `data-aos-offset`, `data-aos-once`, `data-aos-anchor-placement`, and `data-aos-easing`.
- The plugin targets the nearest suitable host element: `p`, `h1-h6`, `li`, `div`, `section`, `article`, or `figure`.

### Activation

Tool key: `aosanimate`. Add it to `sp_get_default_editor_tools()` to show the button in the default toolbar.
