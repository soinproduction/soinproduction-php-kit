# Menu Title Item

Добавляет metabox **Menu Title** в «Внешний вид → Меню». Он создаёт обычный перетаскиваемый пункт с классом-маркером `menu-item-heading`, отключает ссылку и предоставляет helpers для frontend-рендеринга.

## Публичный API

- `sp_menu_title_item_is_heading( $item ): bool` определяет заголовок.
- `sp_menu_title_item_render( $item, $args, $depth ): string` рендерит заголовок.
- `sp_menu_title_item_attributes` фильтрует атрибуты обёртки.
- `_sp_menu_title_hide_on_front` хранит флаг скрытия пункта на frontend.

Кастомный menu walker может вызывать predicate и renderer. Модуль использует нативные permissions и процесс сохранения меню WordPress.
