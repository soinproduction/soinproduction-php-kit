# Social Share

Настраиваемый frontend-компонент share buttons для выбранных public post types. Ordered networks хранятся отдельно от глобального visual config; поддерживаются SVG/Media Library icons, per-post visibility, PHP helper и shortcode.

## Хранение и defaults

| Storage | Назначение |
| --- | --- |
| `sp_share_cfg` | Label, post types, CSS output и dimensions. |
| `sp_share_networks` | Ordered rows, enabled, URL, colors и icons. |
| `sp_share_link_preset_added` | Одноразовый marker миграции Link preset. |
| `_sp_share_enabled` | Post meta; строка `0` запрещает output. |

По умолчанию выбраны `post` и `page`. Активны Link, Facebook, LinkedIn, X; Instagram, WhatsApp, Telegram, Pinterest и Email есть, но выключены. Reddit доступен как preset. Старым установкам Link добавляется один раз.

## Глобальные настройки

| Ключ | Default | Диапазон / эффект |
| --- | ---: | --- |
| `label` | `Share to social media` | Текст в `data-title`. |
| `post_types` | `post,page` | Sanitized public keys. |
| `output_styles` | `1` | Bundled + dynamic CSS. |
| `btn_size` | `52` | Desktop 20–200, делится на 10 для rem. |
| `btn_size_min` | `40` | Mobile 20–200. |
| `icon_size` | `22` | Desktop 8–120. |
| `icon_size_min` | `16` | Mobile 8–120. |
| `border_radius` | `12` | 0–100. |
| `border_width` | `1` | 0–10. |
| `border_opacity` | `20` | Fallback border 0–100%. |
| `bg_opacity` | `12` | Fallback background 0–100%. |
| `gap` | `10` | Gap 0–60. |

Mobile breakpoint — `767.98px`. Hover увеличивает fallback background на 10, border на 20 percentage points, максимум 100.

Выключайте **Output frontend CSS** только если тема полностью определяет `.sp-share`, `.sp-share__btns`, `.sp-share__btns-item`, `.sp-share__btn` и visually hidden text. Copy JS может остаться при активном Link.

## Схема network row

| Поле | Значение |
| --- | --- |
| `key` | Slug; `link` включает clipboard. |
| `label` | Title/accessibility label. |
| `enabled` | Работает только с непустым URL. |
| `url` | Endpoint с placeholders. |
| `color` | Accent fallback. |
| `background_color` | Explicit background. |
| `icon_color` | Icon/currentColor. |
| `border_color` | Explicit border. |
| `icon_type` | `svg` или `img`. |
| `icon_svg` | Sanitized inline SVG. |
| `icon_img`, `icon_img_id` | Media URL и attachment ID. |

Допустимы hex, `transparent`, `currentColor` и ограниченный `rgb/rgba/hsl/hsla(...)`. SVG проходит allowlist простых shapes/attributes; script/events удаляются. Image URL проходит `esc_url_raw()`.

## URL templates и encoding

- `{url}` — `rawurlencode()` permalink;
- `{title}` — `rawurlencode()` title;
- `{url_raw}` — raw permalink;
- `{title_raw}` — raw title.

```text
https://example.com/share?u={url}&text={title}
```

В query используйте encoded placeholders. Raw легче ломается на пробелах, `&` и Unicode. Старые LinkedIn `shareArticle` и Twitter intent нормализуются к современным endpoint. Для key `link` при output всегда используется raw permalink.

## Условия рендера

`render()` возвращает пустую строку, если нет post ID, post type не выбран, `_sp_share_enabled` равен `0`, нет enabled network с URL либо все rows дают пустой href.

Meta box появляется только на выбранных post types. Без meta состояние enabled. Save проверяет nonce, пропускает autosave и требует `edit_post`.

## Frontend API

```php
<?php sp_social_share(); ?>
<?php sp_social_share(123); ?>
```

```text
[sp_social_share]
[sp_social_share id="123"]
```

Каждая network выводится как list item/link с CSS custom properties. External links получают `target=_blank` и `noopener noreferrer`; `mailto:` и Link — нет. Есть visually hidden label.

Для image icon при valid attachment ID и theme helper `display_image()` используется responsive renderer, иначе обычный escaped `<img>`. Inline SVG берётся из уже sanitized storage.

## Copy Link

Link получает `data-sp-share-copy`. Delegated listener сначала вызывает `navigator.clipboard.writeText()`, затем fallback textarea + `document.execCommand('copy')`, а при полной ошибке переходит по permalink.

Успех добавляет `.is-copied` и `data-copied=1` на 1,4 секунды. Современный Clipboard API обычно требует HTTPS или localhost.

## Asset loading

CSS/JS подключаются только если queried post допустим и есть enabled networks. CSS version берётся из filemtime, dynamic sizing добавляется inline. Copy JS регистрируется только при Link.

Если shortcode выводит другой ID на странице, чей queried post не прошёл enqueue check, HTML может появиться без CSS. Для таких cross-post сценариев enqueue делается явно либо post type контейнера тоже включается.

## Admin Ajax

| Action | Запись | Защита |
| --- | --- | --- |
| `sp_share_save_settings` | `sp_share_cfg` | nonce `sp_share_admin` + `manage_options`. |
| `sp_share_save_networks` | `sp_share_networks` | тот же nonce/capability. |

Порядок равен порядку submitted array. Все поля повторно очищаются сервером.

## Добавление сети

1. **Add network**, preset или blank.
2. Уникальный lowercase key и label.
3. Актуальный provider endpoint с encoded placeholders.
4. SVG либо Media Library icon.
5. Colors и enabled state.
6. Drag order и **Save networks**.
7. Тест title с пробелами, `&`, кавычками и Unicode.

Сервисы без общего web-share composer, например Instagram, лучше оставить disabled либо использовать как обычную ссылку на профиль.

## Диагностика

- **Нет output:** проверить post type, per-post toggle, enabled network и URL.
- **Нет стилей:** включить output styles либо реализовать selectors/variables темы.
- **Clipboard error:** HTTPS/localhost и permission; должен сработать fallback.
- **Ломаный share text:** применять `{url}`/`{title}` в query.
- **SVG исчезает:** элемент не разрешён allowlist — упростить icon.
- **Broken image icon:** повторно выбрать существующий attachment.
- **Нет meta box:** post type не включён глобально.
- **Старый LinkedIn/X URL:** output нормализуется; пересохранить preset для storage.
- **Shortcode без CSS:** queried post не прошёл enqueue eligibility; подключить CSS явно.
