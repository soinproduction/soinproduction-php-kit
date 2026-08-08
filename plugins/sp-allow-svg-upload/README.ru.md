# Загрузка SVG

Добавляет контролируемую поддержку SVG в Media Library и преобразует известные UI-иконки в эффективный inline/sprite markup на frontend.

## Как Это Работает

- Загружать SVG могут только пользователи с правом загрузки файлов.
- Фильтр `upload_mimes` разрешает SVG MIME type.
- `wp_check_filetype_and_ext` проверяет расширение и реальный MIME до принятия файла.
- XML очищается: удаляются scripts, event handlers, небезопасные URL, внешние ссылки и опасные CSS-декларации.
- Admin CSS исправляет SVG thumbnails в Media Library.
- Frontend output buffer может заменять зарегистрированные изображения UI icons на безопасный inline SVG или `<use>`.

## Определение UI Icons

Модуль читает manifest UI-иконок из настроек темы и нормализует локальные URL. Helpers определяют slug, локальный файл и ссылку на sprite без дополнительного внешнего запроса.

## Использование

Загружайте SVG через обычную Media Library. Для вывода используйте media/icon helpers темы, чтобы размеры, accessibility attributes и sprite-поведение оставались единообразными.

## Безопасность

SVG — исполняемый XML. Не обходите sanitizer и не разрешайте произвольные удалённые SVG URL. После изменения правил очистки загрузите asset повторно: сохранённые ранее файлы автоматически не переписываются.

## Bootstrap и Последовательность Hooks

| Hook | Callback | Назначение |
| --- | --- | --- |
| `upload_mimes` | `allow_svg_upload()` | Добавляет `svg => image/svg+xml` пользователям с правом загрузки. |
| `wp_check_filetype_and_ext` | `sanitize_svg()` | Проверяет extension/MIME и очищает временный файл до создания attachment. |
| `admin_head` | `fix_svg_display()` | Добавляет правила размеров thumbnails в Media Library. |
| `template_redirect` | `start_output_buffering()` | Запускает frontend transformation buffer. |

Модуль загружается через PHP Kit; собственной admin page и option у него нет. Для отключения добавьте `_` к имени `sp-allow-svg-upload` в секции `plugins` конфигурации PHP Kit. Существующие SVG останутся в Media Library, но новые uploads и frontend transformations прекратятся.

## Pipeline Sanitizer

Upload validator сначала подтверждает `.svg` и допустимый реальный MIME (`image/svg+xml`, `text/xml`, `application/xml`). Затем читает temporary file и отклоняет пустой или повреждённый XML. Удаляются XML processing instructions, DOCTYPE, comments, активные/embed nodes, неизвестные опасные elements, event attributes вроде `onclick`, JavaScript/data URLs и внешние ссылки.

Attribute values проверяются отдельно. Локальные fragment references вида `#icon-id` разрешаются там, где нужны; опасные protocols и CSS expressions блокируются. Inline `style` сокращается до безопасного набора declarations. Очищенный markup записывается обратно только после успешной проверки. При отказе WordPress получает пустые extension/type и показывает штатную upload error.

## Frontend Transformation

`inline_svg_processing()` получает итоговый HTML buffer и обрабатывает только SVG URL, которые разрешаются в локальный файл или известный UI asset. Он умеет:

- загружать и повторно sanitizе локальный SVG markup;
- применять безопасные `class`, `width`, `height`, `role`, `aria-*` и presentation attributes;
- превращать UI icon в sprite reference `<svg><use href="…#symbol"></use></svg>`;
- сохранять обычный `<img>`, если файл не найден или небезопасен.

UI manifest строится из options темы и кешируется в памяти текущего request. URL normalization удаляет query strings и сопоставляет absolute URLs с uploads path. Удалённые URL transformer не скачивает.

## Public Helpers

- `sp_svg_ui_icons_manifest()` — нормализованный registry UI icons.
- `sp_svg_ui_icon_slug_from_url()` — slug настроенной иконки по URL.
- `sp_svg_sprite_href_for_ui_icon()` — ссылка на соответствующий sprite symbol.
- `sp_svg_inline_markup_from_url()` — безопасный inline markup локального SVG.
- `sp_svg_build_sprite_markup()` — accessible sprite markup.

Результат helpers может быть пустым: это означает недоступный или небезопасный source, и caller должен оставить fallback.

## Troubleshooting

- **WordPress запрещает тип файла:** проверьте capability `upload_files`, extension `.svg` и MIME, который сообщает сервер.
- **Upload отклонён после MIME check:** ищите scripts, external resources, malformed namespaces или неподдерживаемый XML.
- **Иконка остаётся `<img>`:** убедитесь, что она есть в UI Assets manifest и разрешается внутри filesystem сайта.
- **Нет sprite symbol:** пересохраните UI Assets и проверьте соответствие symbol ID и manifest slug.
- **Старый опасный markup не изменился:** sanitizer не выполняет массовую миграцию; замените или загрузите файл повторно.
