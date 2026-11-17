# SP Media Renderer

Включается как `sp-media-renderer` в `platform`. Выводит обычные изображения и видео без зависимости от ACF. Модуль независим от `acf/sp-media`: `display_universal_media()` работает со структурой поля `sp_universal_media` и сохраняет собственную разметку и параметры.

## API

- `get_media_html($media, int $width, int $height, string $class = '', string $loading = 'lazy'): string` — HTML.
- `display_media(...)` — вывод HTML; `display_image(...)` — совместимый алиас. Одноимённые actions сохранены.
- `sp_get_post_thumbnail_data(int $post_id = 0, $size = 'large'): ?array` и алиас `get_thumb(...)` — данные миниатюры записи.

Принимает ID вложения, числовую строку, объект вложения `WP_Post`, URL или обычный массив ACF image (`ID`, `url`, `alt`). Для URL регистрация в медиатеке не обязательна. Пустое значение возвращает пустую строку. Размеры выбирают ближайший зарегистрированный размер изображения. Для SVG сохраняется `data-sp-svg-mode` (`auto`, `sprite`, `inline`, `img`); обработку inline/sprite выполняет потребитель.

`sizes_attr` или строковый `sizes` задают адаптивные размеры. Иначе фильтр `display_media_default_sizes` получает `100vw` и нормализованный массив медиа. Через него тема сохраняет свои размеры по умолчанию. `img_attrs` переопределяет атрибуты изображения. `video_attrs` поддерживает autoplay, loop, muted, playsinline, controls, preload, style и class. Сохранены метаданные `_sp_video_*` и необязательная интеграция `sp_video_poster_url()`. По умолчанию видео: autoplay, loop, muted, playsinline включены, controls выключен, preload `auto`.

Фильтры `display_media_data`, `display_media_poster`, `display_media_attrs`, `display_media_html`, `display_media_output` сохраняют порядок аргументов. Функции защищены `function_exists`. Модуль подключается явно: при загрузке до защищённых legacy-хелперов темы реализация берётся из Kit. После переноса проектных настроек в фильтры дублирующие хелперы темы можно удалить.
