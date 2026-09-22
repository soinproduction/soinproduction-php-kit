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

### Поля по категориям (синтаксис `post_types`)

```php
post_relationships([[
    'post_types' => ['leadership', 'news_insights'],
    'field_prefix' => 'linked_',
    'split_by_taxonomy' => ['news_insights' => 'news_insights_category'],
    'show_taxonomies' => ['news_insights' => ['news_insights_category']],
]]);
```

Настройки задаются по **целевому** типу записей. `show_taxonomies` добавляет названия терминов к записям в списке выбора и в выбранных значениях; работает и без разделения. `split_by_taxonomy` создаёт отдельный Relationship для каждого термина, включая пустые категории, и поле «Without category». Поля фильтруются по точному термину, без дочерних. При разделении подписи выбранной таксономии включаются автоматически.

По умолчанию обе настройки выключены. Общий `linked_news_insights` остаётся источником данных: старые связи сразу видны в новых полях, обратная синхронизация и `get_field()` продолжают работать. Отключение настройки возвращает обычное поле без миграции. Переименование/смена категории автоматически меняет представление при следующей загрузке. Запись с несколькими категориями видна в каждой; при сохранении связи объединяются (для удаления снимите её во всех показанных категориях). Программное обновление общего поля через `update_field()` работает как раньше. Виртуальные поля не хранят отдельные копии связей.

Явное `'show_taxonomies' => ['news_insights' => false]` скрывает подписи терминов даже при включённом `split_by_taxonomy`. Значение `'show_taxonomies' => false` отключает подписи для всех типов в этой конфигурации. Названия отдельных полей категорий сохраняются.

При `split_by_taxonomy` в форме добавления/редактирования категории автоматически появляется переключатель **Show Relationship field**. Он управляет отдельным полем этой категории на всех связанных типах записей, использующих разделение по этой таксономии. По умолчанию включён, в том числе у существующих категорий. Отключение не удаляет связи и не меняет вывод на сайте; скрывает только поле выбора в редакторе. Повторное включение возвращает поле с прежними связями. Настройка хранится в term meta `pr_relationship_enabled`. Поле «Without category» остаётся доступным, если не отключено через `show_uncategorized`.

`'show_uncategorized' => ['news_insights' => false]` скрывает поле **Without category** при `split_by_taxonomy`. По умолчанию `true`. Настройка задаётся по целевому типу записей; существующие связи с записями без категории сохраняются.

### Полный пример: отдельные категории без подписей и Without category

```php
post_relationships([[
    'post_types'         => ['leadership', 'news_insights'],
    'field_prefix'       => 'linked_',
    'width'              => 100,
    'featured_image'     => true,
    'group_title'        => 'Related News & Insights',
    'split_by_taxonomy'  => ['news_insights' => 'news_insights_category'],
    'show_taxonomies'    => ['news_insights' => false],
    'show_uncategorized' => ['news_insights' => false],
]]);
```

У Leadership появятся поля включённых категорий News & Insights. На обратной стороне — у News & Insights — остаётся общее поле Leadership. Новые категории включены по умолчанию; переключатель находится на форме добавления/редактирования категории.

### Только подписи категорий, без разделения

```php
post_relationships([[
    'post_types'      => ['leadership', 'news_insights'],
    'show_taxonomies' => ['news_insights' => ['news_insights_category']],
]]);
```

Примеры выше — альтернативы: одну и ту же пару не нужно регистрировать повторно. Опции таксономий относятся к синтаксису `post_types`/`types`, а не к старому `from`/`to`.

### Получение данных

```php
$news_ids = get_field('linked_news_insights', $leader_id) ?: [];
```

Используйте общее поле, а не имена виртуальных полей категорий. Оно содержит все связи, включая скрытые категории и записи без категории. Фильтрацию по публикации, категориям и сортировку для сайта задаёт шаблон. Для первой программной записи через `update_field()` передавайте ключ общего ACF-поля; после появления reference meta можно использовать его имя.

### Проверки

`php tests/post-relationships.php` проверяет регистрацию обычных полей без WordPress. Интеграционные проверки запускаются через `wp eval-file tests/post-relationships-taxonomy-wp.php` и `wp eval-file tests/post-relationships-taxonomy-toggle-wp.php` в тестовом WordPress с конфигурацией Leadership / News & Insights из примера (минимум две включённые категории). Они создают временные черновики/категорию и удаляют их в `finally`.
