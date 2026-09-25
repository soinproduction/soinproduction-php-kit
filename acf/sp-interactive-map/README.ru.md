# Interactive Map

Opt-in ACF-модуль `sp-interactive-map`, тип поля `sp_interactive_map`.
Подключение в массиве `acf` конфигурации Bootstrapper:

```php
'acf' => ['sp-interactive-map'],
```

В редакторе поля задайте изображение по умолчанию, режим одной/нескольких точек и произвольные Sub Fields, как у Group. При заполнении карты можно менять изображение, добавлять, перемещать, блокировать и удалять точки. `zoom_enabled` включается отдельно у каждой карты.

```php
display_interactive_map(get_sub_field('map'), [
    'class' => 'w-full',
    'marker_class' => 'w-[3rem] aspect-square rounded-full overflow-hidden transition-[filter] duration-300 data-[muted=true]:grayscale',
    'zoom_controls_class' => 'position-[absolute] right-[1.6rem] bottom-[1.6rem] z-30 d-[flex] flex-col gap-[.8rem]',
    'tooltip_template' => 'php/templates/map-tooltips/default',
    'loading' => 'lazy',
]);
```

Шаблон тултипа остаётся в теме. В нём используйте `get_sub_field('title')`, `get_sub_field('image')`, `get_sub_field('link')` — только поля, которые заданы в вашей схеме. `$args` содержит `point`, `index`, `map`. ACF-контекст восстанавливается после каждой точки. Маркер использует `image` и `title`, если эти поля заданы; без изображения выводится точка. Для изображения маркера нужен хелпер темы `display_image()`.

Формат значения: `map_id`, `zoom_enabled`, `points`. Координаты `x/y` — проценты; стабильный `_id` сохраняет связь с полями при перестановке. Значения Sub Fields сохраняются средствами ACF; для рендера передавайте форматированное значение ACF, содержащее контекст полей.

JS `assets/map-module.js` подключается автоматически в footer при выводе карты. Нужны `THEME_DIR`, `THEME_URI` и вызов `wp_footer()`. Не подключайте второй экземпляр скрипта в теме. Тултипы выводятся вне масштабируемого слоя, выбирают сторону по месту на экране, остальные маркеры получают `data-muted`. Зум 1–3, кнопки, перетаскивание, pinch на тачпаде (Ctrl+wheel / Safari gestures) работают только при включённом зуме. Обычный скролл страницы не перехватывается.

Оформление фронта — Tailwind utilities, JS использует data-атрибуты. Добавьте каталог модуля в источники Tailwind (путь относительно вашего CSS):

```css
@source "../../vendor/soinproduction/php-kit/acf/sp-interactive-map";
```

Тема должна поддерживать используемые utilities `d-[...]`, `position-[...]` и цветовую переменную `--bg-a`. Админские стили и JS встроены в поле и не требуют сборки темы.

При переносе из темы удалите локальный PHP-регистратор поля и локальный `map-module.js`, сохранив шаблоны тултипов и ACF JSON. Имена полей и формат хранения не меняются; миграция записей не нужна.
