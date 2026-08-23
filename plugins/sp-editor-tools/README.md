# SP Editor Tools

## RU

Эта папка содержит расширения для Classic Editor / TinyMCE и несколько админских enhancer-модулей, которые работают рядом с редактором.

### Как это подключается

- Модуль `sp-editor-tools` загружает все PHP-модули из `modules/`; тема не должна содержать копию `core/mce`.
- Активные инструменты редактора задаются в `sp_get_default_editor_tools()` внутри `core/helpers/custom-editor.php`.
- Соответствие ключа инструмента и PHP-класса хранится в `sp_get_tinymce_plugin_class_map()`.
- Большинство TinyMCE-плагинов отдают свой `script.js` через query endpoint из `index.php`, а затем регистрируются через `mce_external_plugins`.
- Некоторые модули не добавляют кнопку в toolbar, а только меняют поведение редактора или админского интерфейса.

### Навигация

| Плагин | Ключ / кнопка | Что делает |
| --- | --- | --- |
| [sp-aos-for-editor](./modules/sp-aos-for-editor/README.md) | `aosanimate` | AOS-анимации для выбранного элемента |
| [sp-cf7-button](./modules/sp-cf7-button/README.md) | `cf7_button` | Вставка Contact Form 7 shortcode |
| [sp-custom-link-class](./modules/sp-custom-link-class/README.md) | enhancer | Визуальный link picker с классами и иконками |
| [sp-custom-lists](./modules/sp-custom-lists/README.md) | `custom_lists` | Стилизация списков и отдельных пунктов |
| [sp-custom-text-class](./modules/sp-custom-text-class/README.md) | `tag_style_selector` | Применение typography-классов к блокам |
| [sp-custom-underline](./modules/sp-custom-underline/README.md) | `underline_toggle_elem` | Настройка text-decoration |
| [sp-custom-uppercase](./modules/sp-custom-uppercase/README.md) | `textcase_elem` | Управление регистром текста |
| [sp-dark-mode](./modules/sp-dark-mode/README.md) | `dark_mode` | Light/Dark preview внутри редактора |
| [sp-decor-span-tag](./modules/sp-decor-span-tag/README.md) | `decor_toggle` | Обертка выделения в `<span class="decor">` |
| [sp-editor-row](./modules/sp-editor-row/README.md) | `sp_editor_row` | Обертка выделенных элементов в inline row |
| [sp-font-family-select](./modules/sp-font-family-select/README.md) | `font_family_select` | Выбор font-family в редакторе |
| [sp-list-columns](./modules/sp-list-columns/README.md) | `list_columns` | Двухколоночные списки |
| [sp-readmore-modal](./modules/sp-readmore-modal/README.md) | `sp_read_more_modal_img` | Read more button с модалкой настроек |
| [sp-shortcode-button](./modules/sp-shortcode-button/README.md) | `shortcode_button` | Вставка доступных shortcode |
| [sp-small-button-tag](./modules/sp-small-button-tag/README.md) | `small_toggle` | Обертка выделения в `<small>` |
| [sp-social-list](./modules/sp-social-list/README.md) | `social_list` | Вставка `[social_list]` |
| [sp-table-builder](./modules/sp-table-builder/README.md) | `table` | Расширенный builder таблиц |
| [sp-toc-item](./modules/sp-toc-item/README.md) | `toc_item` | Разметка заголовков для TOC |
| [sp-ul-align-redirect](./modules/sp-ul-align-redirect/README.md) | `ul_align_redirect` | Корректное выравнивание списков |
| `sp_widgets` (php-kit `sp-content-library`) | `sp_widgets` | Вставка и предпросмотр reusable widgets |

## EN

This directory contains Classic Editor / TinyMCE extensions plus several admin-side enhancer modules that work around the editor.

### How it is loaded

- The `sp-editor-tools` module loads every PHP module from `modules/`; the theme must not contain a `core/mce` copy.
- Default editor tools are controlled by `sp_get_default_editor_tools()` in `core/helpers/custom-editor.php`.
- Tool keys are mapped to PHP classes in `sp_get_tinymce_plugin_class_map()`.
- Most TinyMCE plugins serve their `script.js` through a query endpoint in `index.php`, then register it with `mce_external_plugins`.
- Some modules do not add a toolbar button and only enhance editor/admin behavior.

### Navigation

| Plugin | Key / button | Purpose |
| --- | --- | --- |
| [sp-aos-for-editor](./modules/sp-aos-for-editor/README.md) | `aosanimate` | AOS animation controls for a selected element |
| [sp-cf7-button](./modules/sp-cf7-button/README.md) | `cf7_button` | Inserts Contact Form 7 shortcodes |
| [sp-custom-link-class](./modules/sp-custom-link-class/README.md) | enhancer | Visual link picker with classes and icons |
| [sp-custom-lists](./modules/sp-custom-lists/README.md) | `custom_lists` | Styles lists and individual list items |
| [sp-custom-text-class](./modules/sp-custom-text-class/README.md) | `tag_style_selector` | Applies typography classes to blocks |
| [sp-custom-underline](./modules/sp-custom-underline/README.md) | `underline_toggle_elem` | Configures text decoration |
| [sp-custom-uppercase](./modules/sp-custom-uppercase/README.md) | `textcase_elem` | Controls text casing |
| [sp-dark-mode](./modules/sp-dark-mode/README.md) | `dark_mode` | Light/Dark preview inside the editor |
| [sp-decor-span-tag](./modules/sp-decor-span-tag/README.md) | `decor_toggle` | Wraps selection in `<span class="decor">` |
| [sp-editor-row](./modules/sp-editor-row/README.md) | `sp_editor_row` | Wraps selected content into an inline row |
| [sp-font-family-select](./modules/sp-font-family-select/README.md) | `font_family_select` | Adds a font-family selector |
| [sp-list-columns](./modules/sp-list-columns/README.md) | `list_columns` | Adds two-column list support |
| [sp-readmore-modal](./modules/sp-readmore-modal/README.md) | `sp_read_more_modal_img` | Read more button with settings modal |
| [sp-shortcode-button](./modules/sp-shortcode-button/README.md) | `shortcode_button` | Inserts available shortcodes |
| [sp-small-button-tag](./modules/sp-small-button-tag/README.md) | `small_toggle` | Wraps selection in `<small>` |
| [sp-social-list](./modules/sp-social-list/README.md) | `social_list` | Inserts `[social_list]` |
| [sp-table-builder](./modules/sp-table-builder/README.md) | `table` | Advanced table builder |
| [sp-toc-item](./modules/sp-toc-item/README.md) | `toc_item` | Marks headings for table of contents |
| [sp-ul-align-redirect](./modules/sp-ul-align-redirect/README.md) | `ul_align_redirect` | Fixes alignment behavior for lists |
| `sp_widgets` (php-kit `sp-content-library`) | `sp_widgets` | Inserts and previews reusable widgets |
