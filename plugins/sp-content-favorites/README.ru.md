# SP Content Favorites

Добавляет управляемый редактором favorite flag выбранным post types, а также admin workflows, REST support и frontend rendering.

## Модель Данных

Favorite state хранится в post meta `_sp_favorite_post` со значением `1`. Настройки модуля находятся в option `sp_favorite_posts_cfg`.

## Возможности в Админке

В **Settings → Favorite Posts** выберите post types и функции:

- колонка, views и dropdown filter в list table;
- bulk mark/unmark actions;
- Quick Edit и checkbox в editor sidebar;
- row action link;
- REST field и фильтрация запросов;
- single-favorite mode для отдельных post types.

Single-favorite mode снимает флаг с остальных записей того же типа при выборе нового favorite.

## Использование на Frontend

Шорткод: `[sp_favorite_posts post_type="post" card="card-favorite.php" posts_per_page="6"]`. Он запрашивает только favorite posts и подключает указанный template из темы.

Внутри template доступен `$post_id` — ID текущей favorite-записи. Custom card files храните в теме и экранируйте output обычным способом.

## REST API

Если функция включена, поддерживаемые post types получают поле `is_favorite`. Запросы фильтруются через `?sp_favorite=1` или `?sp_favorite=0`; для изменения всё равно нужна обычная capability редактирования записи.

## Reference Конфигурации

Settings находятся в option `sp_favorite_posts_cfg`:

| Key | Default | Эффект |
| --- | --- | --- |
| `enabled_post_types` | Все supported UI types | Типы с favorite behavior. |
| `single_favorite_post_types` | Empty | Типы с одним favorite. |
| `enable_admin_filter` | `1` | Dropdown All/Favorites/Not favorites. |
| `enable_bulk_actions` | `1` | Bulk mark/unmark. |
| `enable_quick_edit` | `1` | Quick Edit checkbox. |
| `enable_views_tab` | `1` | Favorites view с count. |
| `enable_editor_metabox` | `1` | Side editor toggle. |
| `enable_row_action` | `1` | Favorite/Unfavorite под title. |
| `enable_rest_api` | `1` | REST field и collection filter. |

Предлагаются только types с visible UI. Submitted slugs пересекаются с supported list, поэтому crafted request не включит internal type.

## State и Single Mode

`set_favorite_flag()` пишет string `1` в `_sp_favorite_post`, а при disable удаляет key. Missing/non-`1` означает not favorite. В single mode `sp_favorite_posts_clear_other_favorites()` снимает flag с остальных posts типа. Invariant применяется из Ajax, Quick Edit, editor save, row actions и REST.

Public helpers:

- `sp_is_favorite_post( int $post_id ): bool`
- `sp_favorite_posts_single_post_types(): array`
- `sp_favorite_posts_is_single_mode( string $post_type ): bool`
- `sp_favorite_posts_clear_other_favorites( int $post_id, string $post_type = '' ): void`

## Admin Integration

На `init` hooks list table регистрируются только для enabled types. Sortable column использует `EXISTS`/`NOT EXISTS`, сохраняя posts без meta. Query var `sp_favorite_filter` принимает `favorite`/`not_favorite`; existing meta queries объединяются через `AND`.

Ajax `sp_favorite_post_toggle` принимает `post_id`, `value`, post-bound nonce, проверяет `edit_post`/type и возвращает `is_favorite`, `single_favorite`. Quick Edit использует `_inline_edit`; editor metabox — `sp_favorite_post_editor`; row URL — `sp_favorite_post_row_action`; bulk — native list-table protection.

## REST Contract

Для enabled type создаётся integer `is_favorite` в contexts `view`/`edit`. GET возвращает `1`/`0`. Update принимает boolean-compatible values; invalid — `rest_invalid_param`, без `edit_post` — `403`.

```text
GET /wp-json/wp/v2/<type>?sp_favorite=1
GET /wp-json/wp/v2/<type>?sp_favorite=0
```

`false` включает missing meta и values не `1`. Existing `meta_query` сохраняется.

## Shortcode Contract

```text
[sp_favorite_posts post_type="case_study" card="card-favorite.php" posts_per_page="6"]
```

| Attribute | Поведение |
| --- | --- |
| `post_type` | Sanitized slug, default `any`. |
| `card` | Обязательный basename в `templates/`; `.php` добавляется. |
| `posts_per_page` | Integer, default `-1`; ниже `-1` → `-1`. |

Real path обязан быть внутри `templates`, блокируя traversal. Order: `menu_order ASC`, `date DESC`. При include доступны `$post_id` и Loop globals. Empty/invalid возвращает пустую строку; post data восстанавливается.

## Troubleshooting и Removal

- **Type отсутствует:** нужен `show_ui=true` и ранняя регистрация.
- **REST field отсутствует:** включите module REST и `show_in_rest` type.
- **Not-favorite теряет rows:** проверьте competing meta query/relation.
- **Shortcode пуст:** нужен readable card и published favorites.
- **В single mode несколько:** toggle один favorite либо вызовите clear helper.
- Disable type не удаляет meta; re-enable восстановит state.
- Removal оставляет option/meta; чистите отдельной migration.
