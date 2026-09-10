# SP Post Relationships

Registers reusable bidirectional ACF relationship fields between two or more post types. The module provides `post_relationships()`; each theme keeps only its project-specific initialization call.

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

Pair definitions with `from`, `to`, custom field names, labels and `to_existing` remain supported. Values are arrays of post IDs. Reverse relationships, ACF reference meta and administration columns are maintained automatically.

Enable `sp-post-relationships` in the PHP Kit `acf` configuration before loading the theme initialization file.
