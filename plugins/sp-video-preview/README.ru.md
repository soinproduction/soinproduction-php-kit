# Video Preview

Добавляет захват poster frame, выбор существующего изображения и playback attributes для video attachments в WordPress Media Library.

## Работа Редактора

Откройте video attachment в Media Library:

1. Переместите preview на нужный timestamp.
2. Захватите текущий frame или выберите существующее image.
3. Настройте controls, muted, autoplay и loop.
4. Сохраните attachment.

UI инициализируется только для video attachments и обновляет Media Library preview после завершения Ajax action.

## Модель Данных

- `_sp_video_poster_id` связывает video с image attachment.
- `_sp_video_poster_generated` отмечает frames, созданные модулем.
- `_sp_video_controls`, `_sp_video_muted`, `_sp_video_autoplay` и `_sp_video_loop` хранят playback flags.

При замене или удалении generated poster модуль может удалить его старый attachment, не затрагивая изображения, выбранные вручную.

## Интеграция с Темой

Используйте `sp_video_poster_id()` или `sp_video_poster_url()` в custom templates. Фильтр `display_media_attrs` добавляет сохранённые playback flags в media renderer темы.

Современные браузеры обычно разрешают autoplay только с muted. Всегда предусматривайте poster или визуальный fallback для медленных соединений и reduced-data contexts.

## Hook и Asset Lifecycle

На admin requests модуль регистрирует Media Library field filters, три Ajax actions и inline CSS/JavaScript. `wp_enqueue_media()` подключает native image selector. JavaScript динамически сканирует attachment modals, потому что WordPress переиспользует modal DOM и рендерит video fields после загрузки страницы.

На всех requests фильтр `display_media_attrs` передаёт playback flags в theme media pipeline. Non-video media и неверные IDs возвращаются без изменений.

## Процесс Capture Poster

1. Browser перемещает `<video>` на выбранный timestamp.
2. JavaScript рисует frame в canvas и отправляет PNG/JPEG/WebP data URL в `sp_video_preview_save_frame`.
3. Server проверяет `upload_files` и nonce `sp_video_preview_action`.
4. Разрешён только `data:image/(png|jpeg|jpg|webp);base64`, decoded data ограничена 25 MB.
5. `wp_upload_bits()` записывает poster с timestamp в имени.
6. `wp_insert_attachment()` создаёт дочерний image attachment, `wp_generate_attachment_metadata()` — размеры.
7. `_sp_video_poster_generated = 1` отмечает владельца; video получает `_sp_video_poster_id` и стандартную featured-image связь.

При новом capture старый generated poster удаляется навсегда. Изображение, выбранное вручную, не получает generated marker и сохраняется.

## Ajax API

| Action | Payload | Результат |
| --- | --- | --- |
| `sp_video_preview_save_frame` | `nonce`, `attachment_id`, `image_data` | Создаёт и назначает generated image. |
| `sp_video_preview_set_existing` | `nonce`, `attachment_id`, `poster_id` | Проверяет и назначает existing image. |
| `sp_video_preview_remove` | `nonce`, `attachment_id` | Удаляет poster meta и featured-image relation. |

Все actions требуют authenticated user с `upload_files`. Неверный video/image MIME возвращает `400`, authorization — `403`, upload/creation failure — `500`.

## Playback Defaults

Пока meta не сохранена: `controls=false`, `muted=true`, `autoplay=true`, `loop=true`. Сохранение attachment записывает явный `0` или `1` каждого toggle.

Filter добавляет boolean `controls`, `muted`, `autoplay`, `loop` только для media type `video`. Templates вне theme media helper должны применить значения самостоятельно.

## Public PHP API

```php
$poster_id  = sp_video_poster_id( $video );
$poster_url = sp_video_poster_url( $video, 'large' );
```

`$video` может быть attachment ID, URL или ACF media array с `ID`/`url`. Неверный/non-video input возвращает `0` или пустую строку. Size сначала ищет WordPress image size, затем возвращает original attachment URL.

## Cleanup и Troubleshooting

- Remove очищает связь, но не удаляет вручную выбранное image.
- Замена captured frame удаляет старый generated attachment при наличии ownership meta.
- Удаление video вне модуля может оставить manual poster; применяйте media retention policy.
- **Capture disabled:** дождитесь metadata; для другого origin проверьте CORS.
- **Canvas пустой/blocked:** remote video должен разрешать canvas access; используйте **Choose image**.
- **Poster не виден на frontend:** используйте theme media helper или public helper.
- **Autoplay не работает:** браузеру могут требоваться `muted`, `playsinline` и user interaction.
