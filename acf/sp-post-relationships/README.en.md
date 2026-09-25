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

### Taxonomy-specific fields (`post_types` syntax)

```php
post_relationships([[
    'post_types' => ['leadership', 'news_insights'],
    'field_prefix' => 'linked_',
    'split_by_taxonomy' => ['news_insights' => 'news_insights_category'],
    'show_taxonomies' => ['news_insights' => ['news_insights_category']],
]]);
```

Both options are keyed by the **target** post type and disabled by default. `show_taxonomies` appends term names to available and selected posts, independently of splitting. `split_by_taxonomy` creates one Relationship per term (including empty terms) plus “Without category”, filtering by the exact term without descendants. Splitting automatically includes that taxonomy's labels.

The original `linked_news_insights` remains canonical: existing links, reverse synchronization and `get_field()` continue working without migration. Disable splitting to restore the original UI. Renamed/reassigned terms are reflected on the next load. Multi-category posts appear in each matching field; selections are merged during saving, so remove a post from every displayed category to unlink it. Programmatic `update_field()` of the canonical field remains supported. Virtual fields never store duplicate relationship metadata.

Explicit `'show_taxonomies' => ['news_insights' => false]` hides term labels even with `split_by_taxonomy` enabled. Set `'show_taxonomies' => false` to hide labels for every type in this configuration. Category field labels remain visible.

With `split_by_taxonomy`, term add/edit forms automatically receive a **Show Relationship field** toggle. It controls that category’s field across related post types using this taxonomy split. It defaults to enabled, including existing terms. Disabling only hides the editor selection field: existing links and frontend output remain unchanged. Re-enabling restores the field with its existing links. The setting is stored in term meta `pr_relationship_enabled`. “Without category” remains available unless disabled by `show_uncategorized`.

`'show_uncategorized' => ['news_insights' => false]` hides **Without category** when using `split_by_taxonomy`. Defaults to `true`. The option is keyed by target post type; existing links to uncategorized posts are preserved.

### Complete example: split categories without labels or Without category

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

Leadership receives one field per enabled News & Insights category. The reverse Leadership selector on News & Insights stays unsplit. New categories default to enabled; their toggle is available on the term add/edit form.

### Category labels without splitting

```php
post_relationships([[
    'post_types'      => ['leadership', 'news_insights'],
    'show_taxonomies' => ['news_insights' => ['news_insights_category']],
]]);
```

These examples are alternatives: do not register the same pair twice. Taxonomy options apply to `post_types`/`types` syntax, not legacy `from`/`to` definitions.

### Retrieving values

```php
$news_ids = get_field('linked_news_insights', $leader_id) ?: [];
```

Use the canonical field, not virtual category field names. It includes all links, including hidden categories and uncategorized posts. Frontend publication status, category filtering and ordering belong in the template. For the first programmatic `update_field()` call, use the canonical ACF field key; once reference metadata exists, the field name can also be used.

### Verification

`php tests/post-relationships.php` checks ordinary field registration without WordPress. Run integration checks with `wp eval-file tests/integration/post-relationships-taxonomy-wp.php` and `wp eval-file tests/integration/post-relationships-taxonomy-toggle-wp.php` in a test WordPress using the Leadership / News & Insights configuration above (at least two enabled categories). They create temporary drafts/a category and remove them in `finally`.
