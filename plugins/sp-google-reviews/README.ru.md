# Google Reviews

Импортирует отзывы Google Maps через SerpAPI в custom post type `review`, создаёт связанные языковые версии для Polylang/WPML, сохраняет аватары и фотографии отзывов, рассчитывает статистику и выводит shortcode widget.

## Требования и поток данных

Тема должна зарегистрировать post type `review`. Модуль сам добавляет этот CPT в Polylang и устанавливает WPML translation mode `translate`. ACF необязателен: если он доступен, rating дополнительно записывается через `update_field('stars', ...)`; иначе используется обычная post meta `stars`.

Синхронизация проходит так:

1. Проверяется admin request и очищенные provider settings.
2. Определяются активные языки Polylang/WPML; без мультиязычного плагина используется настройка `language`.
3. Для каждого языка страницы `google_maps_reviews` запрашиваются у SerpAPI с соответствующим `hl` и сортировкой newest first.
4. Отзывы ниже minimum rating отбрасываются, текущий ответ дедуплицируется.
5. Existing review ищется по provider, стабильному `review_id` и языку; старый hash поддерживается для миграции.
6. Запись создаётся, обновляется или пропускается, затем версии одного отзыва связываются как переводы.
7. Reviewer avatar загружается как featured image, а review images — как дочерние attachments.
8. Статистика пересчитывается отдельно по языкам и сохраняется время успешного fetch.

Встроенного регулярного cron нет. **Sync now** — ручная административная операция; автоматизацию следует строить отдельно с учётом квоты provider.

## Настройки

Все значения находятся в `sp_reviews_importer_options`.

| Ключ | По умолчанию | Проверка / поведение |
| --- | ---: | --- |
| `api_key` | пусто | Сохраняются только буквы и цифры; обязателен. |
| `place_id` | пусто | Буквы, цифры, `_` и `-`; обязателен. |
| `language` | prefix locale сайта | Fallback без Polylang/WPML. При активной мультиязычности синхронизируются все языки сайта. |
| `min_rating` | `1` | Целое 1–5. |
| `limit` | `30` | Цель импорта 1–200. |
| `overwrite` | `1` | Обновляет текст, avatar и gallery совпавших отзывов. |
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

Максимум страниц равен `ceil(limit / 10)`. Квота расходуется отдельно на каждый язык: при limit 30 и трёх языках возможно до девяти запросов. Фильтрация minimum rating происходит после ответа.

Ошибка WordPress HTTP или non-2xx ломает импорт, если ничего ещё не собрано; ошибка поздней страницы оставляет уже собранные элементы ответа текущего языка.

## Идентичность и хранение

Основной source ID — поле SerpAPI `review_id`. Если provider его не вернул, используется legacy fallback:

```text
md5(user link + "|" + ISO/date value + "|" + review snippet)
```

Existing post находится по provider + `_sp_review_source_id` + `_sp_review_language`. Старые записи без этих meta ищутся по прежнему `_sp_review_external_id={hash}` и мигрируют при следующей синхронизации.

| Поле WordPress | Значение |
| --- | --- |
| `post_type` / status | `review` / `publish` |
| title | очищенное имя либо `Anonymous` |
| content | snippet через `wp_kses_post` |
| date | provider timestamp либо текущее site time |
| `stars` | 1–5 в post meta и при наличии ACF |
| `_sp_review_provider` | `serpapi` |
| `_sp_review_external_id` | стабильный source ID; у legacy imports до миграции может быть старый hash |
| `_sp_review_place_id` | текущий Place ID |
| `_sp_review_source_id` | стабильный `review_id` либо legacy hash |
| `_sp_review_language` | код языка Polylang/WPML |
| `_sp_review_url` | source/user URL |
| `_sp_review_images` | массив ID загруженных фотографий |
| featured image | reviewer thumbnail |

Если provider не вернул `review_id`, изменение snippet или даты всё ещё меняет fallback hash и может создать новую запись.

## Медиа и SSRF-защита

URL должен пройти `wp_http_validate_url()`, использовать HTTP/HTTPS, иметь не-local host и не указывать на private/reserved IP. Файл загружается через `download_url()` и `media_handle_sideload()` под именем `review-avatar-{post_id}.jpg`.

Строки и объекты из SerpAPI `images` нормализуются, загружаются как `review-image-{post_id}-{n}.jpg` и сохраняются в `_sp_review_images`. Все принадлежащие review медиа скрываются из обычных Media Library grid/list. Удаление review навсегда удаляет принадлежащие ему avatar и gallery attachments.

В редакторе review эти attachments выводятся в read-only metabox **Review Photos**. По thumbnail можно перейти к attachment, а заменять gallery следует повторной синхронизацией, поскольку файлы принадлежат importer lifecycle.

При выключенном overwrite существующие avatar и gallery остаются. При включённом они заменяются только после успешной новой загрузки; сетевой сбой не уничтожает старую gallery. Не используйте эти attachments вручную в других местах без учёта lifecycle.

## Агрегированные options

| Option | Назначение |
| --- | --- |
| `sp_google_reviews_rating` | Provider rating или local average с одним знаком. |
| `sp_google_reviews_count` | Provider count или число published local reviews. |
| `sp_google_reviews_last_fetch` | MySQL timestamp site time после upsert. |
| `sp_google_reviews_stats_by_language` | Local count/rating по коду языка. |

После импорта count и average пересчитываются по published `review` отдельно для каждого языка. Widget выбирает статистику текущего языка. Manual fallback имеет приоритет.

## Admin endpoints и безопасность

Classic `admin-post.php?action=sp_reviews_import` и Ajax `sp_reviews_import` требуют `manage_options` и nonce. Settings проходят Settings API sanitizer. API key хранится в WordPress options — защищайте DB export и administrator accounts.

Результат показывает inserted, updated и skipped. В skipped входят malformed rows, пустой ID, совпадение при disabled overwrite и ошибки записи.

## Конструктор виджетов

Откройте **Настройки → Google Reviews → Widget Builder**. Конструктор хранит несколько переиспользуемых виджетов в option `sp_google_reviews_widgets`. У каждого виджета стабильный ID и строго проверяемая схема: произвольные HTML и CSS в базу не записываются.

Доступны пресеты Banner, Compact и Minimal. Для каждого виджета отдельно настраиваются:

- аватары, звёзды, числовой рейтинг, подпись рейтинга и количество отзывов;
- включение компонентов; их структура определяется выбранным пресетом;
- количество, размер и перекрытие аватаров;
- размеры звёзд и текста, интервалы, padding и radius;
- цвета текста, вторичного текста, звёзд и фона;
- фоновое изображение из Media Library и opacity затемнения.

В редакторе можно создавать и дублировать любое количество независимо настроенных карточек; для каждой работает адаптивный live preview. На frontend используются scoped CSS custom properties, семантическая разметка и автономные SVG-звёзды: зависимости от theme sprite и запросов к remote fallback avatars больше нет. Если у отзыва нет фотографии, выводится инициал автора.

Используйте shortcode из карточки нужного виджета:

```text
[google_reviews_widget id="hero-rating"]
[google_reviews_widget id="footer-rating"]
```

Старый `[google_reviews_widget]` продолжает работать и выводит виджет `default` либо первый сохранённый. Атрибуты `show_count` и `show_stars` сохранены как совместимые overrides для конкретного вызова.

Рейтинг, count и avatars выбираются для текущего языка Polylang/WPML. Подписи регистрируются в Polylang/WPML String Translation в группе `SP Google Reviews`. Дополнительно доступны filters `sp_google_reviews_widget_rating_label` и `sp_google_reviews_widget_count_label`; вторым аргументом они получают ID виджета.

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
    'images'    => ['https://…'],
    'image_ids' => [456],
    'url'       => 'https://…',
    'provider'  => 'serpapi',
    'language'  => 'uk',
]
```

Для custom cards используйте этот helper вместо прямой зависимости от private meta и деталей её хранения.

### Пример разметки отзыва

Рекомендуемый вариант использует `image_ids` и `wp_get_attachment_image()`: WordPress сам формирует `srcset`, размеры и локальные URL.

```php
<?php
$reviews = new WP_Query( [
    'post_type'      => 'review',
    'post_status'    => 'publish',
    'posts_per_page' => 6,
] );
?>

<?php while ( $reviews->have_posts() ) : $reviews->the_post(); ?>
    <?php $review = SP_Reviews_Importer::get_review_data( get_the_ID() ); ?>
    <?php if ( $review === null ) { continue; } ?>

    <article class="review-card">
        <header class="review-card__header">
            <?php if ( $review['thumb'] !== '' ) : ?>
                <img
                    class="review-card__avatar"
                    src="<?php echo esc_url( $review['thumb'] ); ?>"
                    alt=""
                    width="64"
                    height="64"
                    loading="lazy"
                >
            <?php endif; ?>

            <div>
                <h3><?php echo esc_html( $review['name'] ); ?></h3>
                <span><?php echo esc_html( number_format_i18n( $review['stars'], 1 ) ); ?>/5</span>
            </div>
        </header>

        <div class="review-card__body">
            <?php echo wp_kses_post( $review['content'] ); ?>
        </div>

        <?php if ( $review['image_ids'] !== [] ) : ?>
            <div class="review-card__gallery">
                <?php foreach ( $review['image_ids'] as $image_id ) : ?>
                    <figure class="review-card__photo">
                        <?php
                        echo wp_get_attachment_image(
                            $image_id,
                            'large',
                            false,
                            [ 'loading' => 'lazy' ]
                        );
                        ?>
                    </figure>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </article>
<?php endwhile; ?>

<?php wp_reset_postdata(); ?>
```

Polylang и WPML автоматически фильтруют `WP_Query` по текущему языку. Если используется custom query с `suppress_filters => true`, языковую фильтрацию нужно добавить самостоятельно.

### Упрощённый вывод картинок по URL

Если responsive WordPress markup не нужен, можно использовать готовый массив `images`:

```php
<?php foreach ( $review['images'] as $image_url ) : ?>
    <img
        src="<?php echo esc_url( $image_url ); ?>"
        alt=""
        loading="lazy"
    >
<?php endforeach; ?>
```

Для production-разметки предпочтителен `image_ids`: этот вариант поддерживает зарегистрированные image sizes, `srcset` и оптимизацию WordPress.

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
- **Меньше отзывов:** rating filter, отсутствие next token, duplicate source IDs или поздняя ошибка.
- **Duplicates:** проверить `_sp_review_source_id`; только строки без provider `review_id` зависят от fallback hash URL/date/snippet.
- **Count/rating не как в Google:** local recalc или fallback переопределил totals `place_info`.
- **Нет avatar:** URL не прошёл safety/download либо uploads недоступен для записи.
- **Review media не видны в Media Library:** это намеренная фильтрация owned avatars и photos.
- **Нет stars:** проверить `sprite()` и `Stars{n}`.
- **Timeout:** уменьшить limit; каждый remote page ждёт до 25 секунд.
