# SP Archive Pages

Связывает реальные WordPress pages с архивами custom post types, чтобы редакторы управляли содержимым архива, а URL и база single posts оставались согласованными.

## Настройка

Откройте **Settings → CPT Archives** и назначьте страницу каждому поддерживаемому post type. Включите **Individual archive pages**, если отдельным записям этого типа нужны разные URL bases. Настройка хранится отдельно для каждого языка WPML/Polylang.

## Как Это Работает

- ID выбранной страницы хранится в WordPress options; при наличии multilingual integration используются language-aware keys.
- `get_fake_archive_page()` отдаёт назначенную страницу templates и helpers.
- Назначенная страница получает видимый post state и защищается от trash/delete.
- Archive и single permalinks используют иерархию и язык выбранной страницы как URL base.
- Для включённого post type каждая запись получает metabox **Archive Page**; выбранная там опубликованная page переопределяет общий archive только для этой записи.
- Явные rewrite rules регистрируются для каждого языкового assignment; `parse_request` сохраняет реальные child-page routes при конфликте.
- Запись открывается только под собственным назначенным base; тот же slug под чужим individual base возвращает 404.
- Результаты поиска ссылок корректируются, чтобы редактор выбирал логический archive destination.

## Эксплуатация

Изменение assignment, slug, parent или status архивной страницы планирует один soft rewrite refresh на следующем запросе. Не назначайте одну страницу несвязанным архивам и сначала удалите mapping, если страницу нужно удалить.

Templates должны получать страницу через helper, а не дублировать чтение option.

## Поддерживаемые Post Types и Filters

Default фильтра `fake_archive_supported_post_types` берётся из theme constant `ARCHIVE_POSTS`. `get_supported_fake_archive_post_types()` sanitizе список, исключает неизвестные/non-public types и отдаёт итог settings/runtime. Integration может добавить или удалить тип тем же filter до initialization модуля.

## Storage и Определение Языка

Назначения сохраняются в WordPress options с учётом post type и текущего языка. `fa_current_lang()` и `fa_get_post_language()` сначала используют native API Polylang, затем filters WPML. Поэтому English archive page не перезаписывает Russian assignment.

`fa_get_archive_map_for_current_lang()` возвращает проверенную карту текущего языка. Individual assignment хранится в post meta `_fa_archive_page_id` и принимает только published page на языке записи. При пустом/невалидном значении используется общий archive post type.

## URL и Rewrite Lifecycle

Модуль согласованно меняет несколько уровней:

| Integration | Назначение |
| --- | --- |
| `post_type_link` | Перестраивает single permalink через назначенную archive page и её parent hierarchy. |
| `parse_request` | Распознаёт fake archive route и заполняет query vars post type. |
| `init` | Регистрирует rewrite rules всех языковых archive bases. |
| `wp_loaded`, `post_updated` | Обновляет rules после изменения assignment, slug, parent или status. |
| `add_meta_boxes`, `save_post` | Показывает и сохраняет индивидуальный archive override записи. |
| `pll_translated_slugs` | Синхронизирует Polylang CPT bases с назначенными archive pages. |
| `body_class` | Добавляет archive/page context classes для theme styles. |
| `wp_link_query` | Делает archive destination понятным в editor link search. |
| `display_post_states` | Помечает страницу как CPT archive в Pages list. |
| `before_delete_post`, `wp_trash_post` | Блокирует удаление, пока page назначена. |

`fa_get_single_base_from_fake_archive_if_has_parent()` и `fa_get_archive_base_for_post_type()` централизуют вычисление base. Не дублируйте эту логику в templates или custom rewrites.

Используйте `fa_get_archive_page_for_post( $post )`, когда нужно учитывать индивидуальный override. `get_fake_archive_page( $post_type )` намеренно возвращает только общий archive текущего языка.

## Использование в Template

```php
$archive_page = get_fake_archive_page( 'case_study' );
if ( $archive_page ) {
	setup_postdata( $archive_page );
	// Чтение ACF/page content назначенной страницы.
	wp_reset_postdata();
}
```

Всегда восстанавливайте global post data. Для ACF лучше передать page ID явно, если archive query должна сохранить собственный global post.

## Безопасная Смена Assignment

1. Создайте и опубликуйте replacement page.
2. Назначьте её в **Settings → CPT Archives**.
3. Откройте сайт ещё раз, чтобы выполнился запланированный rewrite refresh.
4. Проверьте archive pagination, taxonomy links, singles и editor link search.
5. При смене публичного URL добавьте redirect со старого base.
6. Удаляйте старую page только после снятия assignment.

## Troubleshooting

- **Archive отдаёт 404:** выполните ещё один запрос для pending refresh, затем пересохраните Permalinks и проверьте public type и `ARCHIVE_POSTS`.
- **Открывается другой язык:** проверьте current-language function multilingual integration и assignment этого языка.
- **Нет individual selector:** включите **Individual archive pages** для post type в CPT Archives текущего языка.
- **Individual URL отдаёт 404:** убедитесь, что выбранная page и запись имеют одинаковый язык WPML/Polylang.
- **Single URLs используют старый base:** очистите rewrite/page caches и competing `post_type_link` filters.
- **Page нельзя удалить:** это защита; сначала уберите assignment.
- **Template читает неверный ACF:** передавайте ID archive page явно.
