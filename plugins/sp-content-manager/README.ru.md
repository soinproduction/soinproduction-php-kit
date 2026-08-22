# Content Manager

Центральный admin tool для дублирования контента и управления порядком записей, терминов и меню WordPress.

## Возможности

- Добавляет безопасное действие Duplicate для включённых post types.
- Включает drag-and-drop порядок в списках записей и taxonomy terms.
- Применяет сохранённый порядок к admin queries, не меняя публичные frontend queries.
- Может переставлять и скрывать top-level и submenu items в боковом меню WordPress.
- Сохраняет настройки и порядок через авторизованные Ajax-запросы.

## Как Работает Сортировка

Для записей используется нативное поле `menu_order`. Порядок taxonomy хранится в term meta `_sp_cm_order`. Модуль меняет admin queries только для post types и taxonomies, включённых в **Settings → Content Manager**.

Конфигурация меню хранится в option `sp_content_manager_cfg`. Runtime callbacks выполняются поздно на `admin_menu`, когда WordPress и остальные модули уже собрали финальный список.

## Дублирование

Handler проверяет capability и nonce, копирует данные записи и поддерживаемые meta, создаёт draft и открывает новый editor. Перед публикацией проверьте relationships и уникальные внешние ID.

## Безопасность

После установки или удаления модулей, добавляющих пункты меню, используйте **Reset to current WordPress order**. Скрытие пункта не отбирает capability и не блокирует прямой URL — меняется только навигация.

## Schema Конфигурации

Все settings находятся в option `sp_content_manager_cfg` с отключённым autoload. Defaults включают duplicate/post/term sorting, выключают menu/submenu sorting и выбирают все допустимые UI post types/taxonomies.

| Key | Назначение |
| --- | --- |
| `enable_duplicate` | Добавляет Duplicate row actions и разрешает handler. |
| `enable_post_sort` | Drag-and-drop и admin sorting по `menu_order`. |
| `enable_term_sort` | Taxonomy drag-and-drop и `_sp_cm_order`. |
| `enable_menu_sort` | Custom top-level WordPress menu order. |
| `enable_submenu_sort` | Stored order children внутри parent menu. |
| `post_types`, `taxonomies` | Sanitized enabled slugs. |
| `menu_order`, `submenu_order` | Нормализованные menu slugs. |
| `hidden_menu`, `hidden_submenu` | Items, удаляемые из rendered admin menu. |

Attachments, revisions, nav-menu items и `nav_menu` исключены. Choices должны иметь `show_ui=true`; runtime проверяет edit/manage capability.

## Duplicate Lifecycle

Row action виден только при включённой функции и правах edit source + create posts. URL использует action `sp_cm_duplicate_post` и post-specific nonce.

Handler создаёт draft с content, excerpt, parent, menu order, discussion/password settings и текущими timestamps. Current user становится author, slug пустой. Общий механизм `PostDuplicator` копирует всю meta кроме `_edit_lock`, `_edit_last`, `_wp_old_slug` и служебного состояния WPML-дубликата; serialized values проходят `maybe_unserialize()`. Назначаются все обычные taxonomy terms.

Если post type переведён через Polylang или WPML, копии назначается язык исходника через публичный API плагина. Копия намеренно начинает новую translation group: две записи одного языка не могут находиться в одной группе переводов. Служебные taxonomies Polylang `language` и `post_translations` напрямую не копируются.

Другие обработчики дублирования могут использовать тот же механизм после создания целевой записи:

```php
\SoinProduction\Kit\PostDuplicator::copyAssociatedData( $source_id, $target_id );
```

Копирование настраивается фильтрами `sp_post_duplicator_excluded_meta_keys`, `sp_post_duplicator_excluded_taxonomies` и `sp_post_duplicator_language_providers`. После общего шага вызывается `sp_post_duplicator_after_copy`; прежний action `sp_cm_after_duplicate` остаётся доступным для интеграций Content Manager.

После копирования вызывается `sp_cm_after_duplicate`:

```php
add_action( 'sp_cm_after_duplicate', function ( int $source_id, int $target_id ): void {
	delete_post_meta( $target_id, '_external_unique_id' );
}, 10, 2 );
```

Featured image и ACF fields копируются как post meta. External files, comments, revisions и custom-table rows требуют отдельного callback.

## Сортировка Posts и Terms

Drag-and-drop на `edit.php` отправляет `post_type` и IDs в `sp_cm_save_post_order`. После проверки type/`edit_post` posts получают последовательный `menu_order`. `pre_get_posts` меняет только main admin query без явного `orderby`: `menu_order ASC`, `date DESC`, `ID DESC`. Frontend queries не меняются.

`sp_cm_save_term_order` проверяет taxonomy/manage capability и пишет `_sp_cm_order`. Admin `get_terms_args` строит полный ID list с `orderby=include`, поэтому terms без meta остаются видимыми. Frontend term queries не меняются.

## Admin Menu Order и Visibility

Top-level использует `custom_menu_order`/`menu_order`. Submenus меняются на `admin_menu` priority `999`, visibility — `1000`. Slugs нормализуются; удаляются volatile `return`, nonces, locale, referer. Новые items добавляются в конец.

Hidden entries удаляются только из `$menu`/`$submenu`. Security всегда реализуйте capabilities.

## Ajax API

| Action | Authorization | Payload |
| --- | --- | --- |
| `sp_cm_save_settings` | `manage_options` | `nonce`, nested `cfg`. |
| `sp_cm_save_post_order` | Logged-in + type/edit checks | `nonce`, `post_type`, `order[]`. |
| `sp_cm_save_term_order` | Logged-in + taxonomy/manage checks | `nonce`, `taxonomy`, `order[]`. |

Все endpoints используют nonce `sp_content_manager_admin`, sanitizе slugs/IDs и возвращают JSON `updated` либо error status.

## Troubleshooting

- Сохраните settings перед ожиданием drag handles нового type.
- Если order игнорируется, уберите explicit `orderby`.
- Если terms исчезли, проверьте competing `get_terms_args` filters.
- После menu plugins сделайте reset к текущему WordPress order.
- Hidden page доступна по URL ожидаемо; блокируйте capability.
- Unwanted copied meta удаляйте на `sp_cm_after_duplicate`.
- Перед массовой сменой `menu_order` сделайте database backup.
