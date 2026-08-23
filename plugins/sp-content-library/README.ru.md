# SP Content Library

Универсальная библиотека двух совместимых типов переиспользуемого контента:

- **Reusable Sections** — существующий post type `widgets`, ACF-поле `builder` и taxonomy `widgets_category`;
- **Editor Blocks** — существующий post type `for-editor` и ACF flexible field `blocks`.

Обе страницы располагаются внутри меню **Внешний вид**. Меняются только видимые admin labels: внутренние slug, meta keys, REST identifiers, связи WPML/Polylang, shortcode `[widget]` и существующие записи остаются прежними.

## Конфигурация

```php
'sp-content-library' => [
	'menu_parent' => 'themes.php',
	'editor_layouts' => [
		'author_quote',
		'blockquote',
	],
],
```

`editor_layouts` содержит имена theme callbacks. Каждый callback должен вернуть callable, добавляющий layout в ACF Flexible Content. Аргументы конкретного layout можно передать явно:

```php
'editor_layouts' => [
	'author_quote',
	[
		'callback' => 'editor',
		'args'     => [ 'media_upload' => 0 ],
	],
],
```

По умолчанию factory поля называется `blocks`, а Builder Sections использует `sp_builder_add_flexible_field`. Для другой архитектуры укажите `editor_field_factory` и `builder_field_callback`.

Удаление layout из конфигурации скрывает его для новых Editor Blocks. Перед удалением убедитесь, что сохранённые записи больше его не используют.
