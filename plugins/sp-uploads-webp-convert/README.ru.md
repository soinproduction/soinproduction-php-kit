# Uploads WebP Convert

Инструмент Media Library: конвертирует JPEG/PNG/GIF в WebP, обновляет attachment metadata, мигрирует URL в posts/meta, консервативно ищет unused images, пакетно удаляет подтверждённые candidates и заменяет файл без смены attachment identity.

Операции меняют database и `wp-content/uploads`. Перед bulk conversion, URL replace и delete обязательны staging и backup DB/uploads.

## Карта функций

| Функция | Scope | Риск |
| --- | --- | --- |
| Convert on upload | Новый JPEG/PNG/GIF | Может удалить source после успешной проверки. |
| Bulk conversion | Existing attachments | Меняет MIME/path/metadata и может удалить source/generated sizes. |
| URL replacement | content/excerpt/postmeta | Переписывает строки и serialized arrays. |
| Unused scan | Images, DB, static text files | Read-only. |
| Delete unused | Selected results | Permanent `wp_delete_attachment(..., true)`. |
| Replace file | Один attachment | Перезаписывает original и regenerates sizes. |

## Configuration

Опция `sp_webp_convert_cfg`, autoload=false.

| Ключ | Default | Диапазон / эффект |
| --- | ---: | --- |
| `enabled_upload` | `1` | Conversion в `wp_handle_upload`. |
| `quality` | `90` | 60–100. |
| `max_side` | `2560` | 320–8000 px. |
| `delete_original` | `1` | Удаляет obsolete files только после valid output. |
| `skip_animated_gif` | `1` | Сохраняет GIF с несколькими frames. |
| `batch_size` | `20` | Conversion 1–100, влияет на unused batch. |
| `db_batch_size` | `200` | 20–500; runtime URL batch максимум 120 rows. |

WebP разрешается как upload MIME. `wp_unique_filename` проверяет одновременно source и будущий `.webp`, чтобы `photo.jpg` не перезаписал `photo.webp`.

## Conversion engine

Sources: `image/jpeg`, `image/png`, `image/gif`. Existing WebP, SVG, AVIF и другие не являются bulk target.

Алгоритм:

1. Определить MIME по image data, extension и WP fallback.
2. Проверить readability.
3. При настройке пропустить animated GIF; читаются chunks до двух frame markers.
4. Пропорционально ограничить longest side.
5. Для большого файла выполнить memory-aware GD pre-resize: поднять WP image limit и оценить memory до allocation.
6. Открыть `wp_get_image_editor()`, применить quality, сохранить UUID temp WebP.
7. Проверить size и dimensions до перемещения.
8. Переместить в final `.webp`, проверить повторно и только затем удалить другой source path.

При GD resize сохраняется transparency PNG/GIF. Если GD/memory недостаточно, большой файл безопасно skipped, а не доводится до fatal.

Статусы: `converted`, `skipped`, `error`. Skip — existing WebP, unsupported, animation, missing attachment, unsafe resize. Error — image editor, invalid output, filesystem move.

## Conversion on upload

Filter выполняется после принятия upload, но до полного создания attachment. При успехе заменяет `file`, `url`, MIME на WebP, поэтому WordPress сразу создаёт attachment для нового файла.

При skip/error исходный `$upload` возвращается без изменений: ошибка оптимизации не должна терять accepted upload.

## Bulk conversion

**Scan media** считает inherited JPEG/PNG/GIF. IDs читаются по `ID > last_id`, без offset. Каждый Ajax request имеет 20-second limit и использует настроенный batch; browser при network failures может уменьшить его.

После успеха:

- `_sp_webp_original_url` хранит старый main URL;
- `_sp_webp_original_ext` хранит extension;
- `_wp_attached_file` обновляется через `update_attached_file()`;
- `post_mime_type` становится `image/webp`;
- regenerates metadata/intermediate sizes;
- при failure metadata создаётся минимальный fallback;
- stale source/sizes удаляются только при **Delete original**;
- очищается URL map transient;
- считается non-negative saved bytes.

Progress (cursor/totals/errors/bytes) находится в localStorage. **Stop** не откатывает готовые attachments. UI retries network failures, может уменьшать batch и resume после reload. **Reset progress** удаляет только browser state.

Не запускайте bulk одновременно в нескольких tabs: cursor local, attachments общие.

## URL map

Все inherited WebP сканируются pages по 500. Map old → new строится из:

- `_sp_webp_original_url`, когда значение было сохранено;
- `_sp_webp_original_ext`, когда extension была сохранена;
- возможных вариантов `.jpg`, `.jpeg`, `.png` и `.gif`;
- WebP-файлов generated intermediate sizes и вариантов тех же имён со старыми extensions.

Map кешируется на 10 минут в `sp_webp_url_replace_map_cache` и инвалидируется conversion/replacement.

## Database URL replacement

Подготовка сообщает количество записей map и общее число строк в `wp_posts` и `wp_postmeta`. Обработка состоит из двух cursor phases:

1. `posts`: exact strings в `post_content` и `post_excerpt`.
2. `postmeta`: rows по `meta_id`, с безопасным unserialize, recursive replace и serialize.

Serialized objects и malformed serialized data не меняются. Endpoint поддерживает `dry_run`, текущая admin button запускает real mode. Считаются processed rows, changed rows, URL hits и errors.

Не обрабатываются произвольные custom tables, term/user/options, code files, external systems и CDN caches. Это exact replacement строк из сгенерированного map; relative URL не изменится, если его точного варианта нет в map. Остальные storage и custom code проверяются отдельно.

## Unused scan: консервативная модель

Проверяются все inherited attachments с MIME, начинающимся на `image/`. Batch равен `ceil(batch_size / 10)`, ограничен 1–4; request максимум 20 items и 45 секунд.

Attachment сохраняется, если file/path отсутствует либо нельзя построить надёжные needles. Ищутся:

- ID patterns (`wp-image-ID`, data attributes, gallery, media-like JSON/serialized keys);
- current/old relative paths;
- current/old/size URLs;
- escaped slash, HTML encoded и URL/path variants.

Чистое numeric ID считается reference только в media-like meta/option keys либо ACF `image`, `gallery`, `file`, что уменьшает false positives.

### Database coverage

Проверяются posts, postmeta, options без transients, termmeta, term descriptions, usermeta/users, comments/commentmeta, links, sitemeta. Текстовые columns custom tables обнаруживаются через `information_schema`; schema кешируется на час в `sp_webp_unused_schema_cache`.

Из discovery исключены известные log/cache/backup/analytics/plugin-internal families. При schema/database error файл conservatively kept.

### Filesystem coverage

Сканируются searchable static files child/parent theme, mu-plugins, active plugins и uploads text files: PHP, JS/TS, CSS/preprocessors, HTML, JSON/XML/YAML, Markdown, CSV, SVG, manifest, txt. Cache/backup/log/vendor/node_modules/languages/generated dirs пропускаются, symlinks не follow.

Файлы stream-ятся chunks по 1 MB с overlap. Сам attachment и его sizes исключены. Unreadable root/file или отсутствие searchable files означает keep.

Только когда нет ID, DB, custom-table и filesystem reference, image попадает в unused results. Runtime-generated, remote DB, encrypted/compressed и unsupported binary reference могут остаться невидимыми.

## Delete unused

Один delete request принимает максимум четыре ID. Перед каждым удалением server повторно проверяет existence, image MIME и весь usage scan. Новый reference переводит item в skipped. Подтверждённый attachment удаляется permanent через `wp_delete_attachment($id, true)`.

Trash/undo нет. Preview, URL, context и application behavior проверяются вручную; backup должен быть recoverable.

## Replace file in place

**Replace file** доступен в строках Media Library и attachment fields пользователю с `upload_files` и `edit_post` для конкретного attachment. Formats: GIF/JPEG/PNG/SVG/WebP, но replacement MIME обязан совпадать с current MIME (`image/jpg` считается JPEG). Так сохраняются filename и URL.

Файл должен быть реальным PHP upload, readable, меньше `wp_max_upload_size()`. Raster проверяется `getimagesize`; SVG — только наличием `<svg`, это не полноценный security sanitizer. За общую безопасность загрузки SVG по-прежнему отвечает site-wide SVG module/policy.

Flow:

1. Current file существует, writable и внутри uploads.
2. Upload копируется во временный UUID sibling с сохранением file permissions и atomic rename поверх current.
3. ID/title/slug/filename/URL/alt/caption и прочие fields остаются.
4. MIME/metadata/sizes regenerates, obsolete sizes удаляются, caches очищаются.
5. Возвращается cache-busted preview.

Старый original не сохраняется. Для rollback сначала сделайте backup.

## Ajax permissions

Все admin maintenance actions проверяют nonce `sp_webp_convert_admin`.

| Action | Capability |
| --- | --- |
| `sp_webp_save_settings` | `manage_options` |
| `sp_webp_scan_media` | `manage_options` |
| `sp_webp_convert_batch` | `manage_options` |
| `sp_webp_prepare_url_replace` | `manage_options` |
| `sp_webp_replace_urls_batch` | `manage_options` |
| `sp_webp_prepare_unused_scan` | `manage_options` |
| `sp_webp_scan_unused_batch` | `manage_options` |
| `sp_webp_delete_unused_batch` | `manage_options` |
| `sp_webp_replace_attachment_file` | `upload_files` вместе с `edit_post` конкретного attachment |

## Безопасный порядок

1. Backup DB/uploads и тест restore.
2. Staging, conservative settings, сначала сохранить originals.
3. Small batch: transparency, orientation, thumbnails, crops.
4. URL replace и поиск old extension/domain.
5. Очистить page/object/CDN caches.
6. Unused scan, manual review, сначала несколько deletes.
7. Source deletion включать только после полной проверки.

## Диагностика

- **Все skip/error:** проверить GD/Imagick WebP support в `wp_get_image_editor()`.
- **Large skipped:** увеличить image memory либо уменьшить max side.
- **GIF стал static:** восстановить original backup; skip animation должен быть включён заранее.
- **Frontend всё ещё JPEG:** URL replace, custom storage/code, CDN/page cache.
- **Map empty:** нет WebP attachments или inferable originals.
- **Progress stalls:** reload/resume, меньший batch, PHP/server logs.
- **Unused scan медленный:** это ожидаемо при custom tables/filesystem.
- **Всё used:** смотреть reason; errors намеренно дают keep.
- **Used отмечен unused:** не удалять, определить unsupported reference.
- **Delete skipped:** повторная проверка нашла reference/изменение.
- **Replace rejected:** format mismatch, upload/size/readability/permissions/path.
- **Old sizes остались:** filesystem permissions и metadata.
- **Recovery:** восстанавливать согласованные DB и uploads snapshots вместе.
