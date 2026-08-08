# Menu Title Item

Adds a **Menu Title** metabox to Appearance → Menus. It creates a regular draggable menu item marked with the `menu-item-heading` class, removes link behavior, and exposes frontend rendering helpers.

## Public API

- `sp_menu_title_item_is_heading( $item ): bool` identifies heading items.
- `sp_menu_title_item_render( $item, $args, $depth ): string` renders the heading.
- `sp_menu_title_item_attributes` filters the rendered wrapper attributes.
- `_sp_menu_title_hide_on_front` stores the per-item frontend visibility flag.

Custom menu walkers can call the predicate and renderer. The module uses WordPress nav-menu permissions and save flow.
