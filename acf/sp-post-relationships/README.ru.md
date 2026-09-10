# SP Post Relationships

Регистрирует переиспользуемые двусторонние ACF relationship-поля между двумя или несколькими post types. Реализация `post_relationships()` находится в PHP Kit, а в теме остаётся только проектный вызов:

```php
post_relationships([
    [
        'post_types'     => ['services', 'projects', 'markets'],
        'field_prefix'   => 'linked_',
        'width'          => 33,
        'featured_image' => false,
        'group_title'    => 'Relations',
    ],
]);
```

Поддерживаются pair-конфигурации `from`/`to`, собственные имена и labels полей, а также `to_existing`. Модуль автоматически синхронизирует обратные связи, ACF reference meta и admin columns.

До загрузки файла инициализации темы включите `sp-post-relationships` в секции `acf` PHP Kit.
