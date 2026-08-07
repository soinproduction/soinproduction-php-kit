# Google Reviews

Импортирует отзывы Google Maps через SerpAPI в custom post type `review`, сохраняет provider identity и аватары, рассчитывает агрегированную статистику и выводит компактный shortcode widget.

## Требования и поток данных

Тема должна зарегистрировать post type `review`. ACF необязателен: если он доступен, rating дополнительно записывается через `update_field('stars', ...)`; иначе используется обычная post meta `stars`.

Синхронизация проходит так:

1. Проверяется admin request и очищенные provider settings.
2. Страницы `google_maps_reviews` запрашиваются у SerpAPI с сортировкой newest first.
3. Отзывы ниже minimum rating отбрасываются, текущий ответ дедуплицируется.
4. Existing review ищется по provider + generated external ID.
5. Запись создаётся, обновляется или пропускается согласно **Overwrite existing reviews**.
6. Безопасный reviewer avatar загружается как featured image.
7. Локальная статистика пересчитывается и сохраняется время успешного fetch.

Встроенного регулярного cron нет. **Sync now** — ручная административная операция; автоматизацию следует строить отдельно с учётом квоты provider.

## Настройки

Все значения находятся в `sp_reviews_importer_options`.

| Ключ | По умолчанию | Проверка / поведение |
| --- | ---: | --- |
| `api_key` | пусто | Сохраняются только буквы и цифры; обязателен. |
| `place_id` | пусто | Буквы, цифры, `_` и `-`; обязателен. |
| `language` | prefix locale сайта | Строчные буквы; передаётся как `hl`. |
| `min_rating` | `1` | Целое 1–5. |
| `limit` | `30` | Цель импорта 1–200. |
| `overwrite` | `1` | Обновляет совпавшие отзывы и их avatar. |
| `fallback_rating` | пусто | Ручной numeric rating widget; допускается запятая. |
| `fallback_count` | пусто | Ручной положительный count widget. |

Статус Ready появляется только при наличии API key и Place ID.

## Контракт SerpAPI

Каждый запрос идёт на `https://serpapi.com/search.json` со следующими параметрами:

- `engine=google_maps_reviews`;
- настроенные `place_id` и `api_key`;
- `hl={language}`;
- `sort_by=newestFirst`;
- `next_page_token` для следующих страниц.

Timeout — 25 секунд.

Максимум страниц равен `ceil(limit / 10)`: limit 30 может стоить три SerpAPI query. Фильтрация minimum rating происходит после ответа, поэтому итог может быть меньше заданного limit.

Первые доступные `place_info.rating` и `place_info.reviews` сохраняются в aggregate options. Ошибка WordPress HTTP или non-2xx ломает импорт, если ничего ещё не собрано; ошибка поздней страницы оставляет уже собранные элементы текущего запуска.

## Идентичность и хранение

В этой интеграции строки отзывов SerpAPI не предоставляют стабильный source ID. External ID вычисляется как:

```text
md5(user link + "|" + ISO/date value + "|" + review snippet)
```

Existing post находится по `_sp_review_provider=serpapi` и `_sp_review_external_id={hash}` среди publish/future/draft/pending/private.

| Поле WordPress | Значение |
| --- | --- |
| `post_type` / status | `review` / `publish` |
| title | очищенное имя либо `Anonymous` |
| content | snippet через `wp_kses_post` |
| date | provider timestamp либо текущее site time |
| `stars` | 1–5 в post meta и при наличии ACF |
| `_sp_review_provider` | `serpapi` |
| `_sp_review_external_id` | generated hash |
| `_sp_review_place_id` | текущий Place ID |
| `_sp_review_url` | source/user URL |
| featured image | reviewer thumbnail |

Изменение snippet или даты меняет hash и может создать новую запись. При duplicate после sync сравнивайте именно эти identity inputs.

## Аватары и SSRF-защита

URL должен пройти `wp_http_validate_url()`, использовать HTTP/HTTPS, иметь не-local host и не указывать на private/reserved IP. Файл загружается через `download_url()` и `media_handle_sideload()` под именем `review-avatar-{post_id}.jpg`.

ID аватаров кешируются на час в `sp_review_avatar_ids` и скрываются из обычных Media Library grid/list. Удаление review навсегда удаляет его featured avatar; удаление attachment очищает transient.

При выключенном overwrite existing thumbnail остаётся. При включённом новый provider thumbnail может быть загружен. Не используйте такой avatar вручную в других местах без учёта этого lifecycle.

## Агрегированные options

| Option | Назначение |
| --- | --- |
| `sp_google_reviews_rating` | Provider rating или local average с одним знаком. |
| `sp_google_reviews_count` | Provider count или число published local reviews. |
| `sp_google_reviews_last_fetch` | MySQL timestamp site time после upsert. |

После импорта count и average пересчитываются по всем published `review`. Поэтому они могут отражать локальную выборку, а не все live Google reviews. Manual fallback имеет приоритет в widget.

## Admin endpoints и безопасность

Classic `admin-post.php?action=sp_reviews_import` и Ajax `sp_reviews_import` требуют `manage_options` и nonce. Settings проходят Settings API sanitizer. API key хранится в WordPress options — защищайте DB export и administrator accounts.

Результат показывает inserted, updated и skipped. В skipped входят malformed rows, пустой ID, совпадение при disabled overwrite и ошибки записи.

## Frontend widget

```text
[google_reviews_widget]
[google_reviews_widget show_count="false" show_stars="true"]
```

`show_count` и `show_stars` считают значения `0`, `false`, `no`, `off` выключенными; остальные значения включают параметр. Widget:

- берёт valid manual fallback, иначе сохранённую статистику;
- при нуле fallback-ит на rating `5.0` и count `1`;
- округляет visual sprite до 1–5, но печатает rating с одним знаком;
- загружает до трёх последних review thumbnails;
- при нехватке использует remote Unsplash fallback avatars;
- добавляет inline CSS в handle `sp-google-reviews-widget`.

Звёзды зависят от theme helper `sprite()` и assets `Stars1`…`Stars5`. При переносе модуля за пределы этой архитектуры темы предоставьте совместимый helper либо замените renderer.

## Публичный PHP API

`SP_Google_Reviews_Importer::get_review_data($post_id)` возвращает `null`, если ID не принадлежит записи `review`. Для валидного отзыва возвращается массив следующей формы:

```php
[
    'id'        => 123,
    'name'      => 'Reviewer name',
    'content'   => 'Filtered review HTML',
    'raw'       => 'Raw stored content',
    'stars'     => 5.0,
    'date'      => '2026-08-01 12:00:00',
    'timestamp' => 1785585600,
    'thumb'     => 'https://…',
    'url'       => 'https://…',
    'provider'  => 'serpapi',
]
```

Для custom cards используйте этот helper вместо прямой зависимости от private meta и деталей её хранения.

## Чек-лист

1. Проверить CPT `review` и поле/meta `stars`.
2. Сохранить key, Place ID, язык, threshold и небольшой test limit.
3. Оценить стоимость SerpAPI до увеличения limit.
4. Запустить sync и проверить totals.
5. Открыть записи и проверить date/text/rating/avatar.
6. Проверить aggregate и shortcode logged out.
7. Сделать backup DB/uploads перед массовыми удалениями.

## Диагностика

- **Sync disabled:** key или Place ID пуст после sanitization.
- **Provider error:** проверить key, quota, Place ID, outbound HTTPS и текст SerpAPI.
- **Меньше отзывов:** rating filter, отсутствие next token, duplicate hashes или поздняя ошибка.
- **Duplicates:** user URL, date или snippet изменились и дали новый hash.
- **Count/rating не как в Google:** local recalc или fallback переопределил totals `place_info`.
- **Нет avatar:** URL не прошёл safety/download либо uploads недоступен для записи.
- **Avatar не виден в Media Library:** это намеренная фильтрация.
- **Нет stars:** проверить `sprite()` и `Stars{n}`.
- **Timeout:** уменьшить limit; каждый remote page ждёт до 25 секунд.
