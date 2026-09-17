# SP Post Selector

`smart_relationship` is an ACF relational field for selecting posts with a richer admin UI: tabs for manual/favorites/related/all modes, searchable available posts, selected ordering, optional taxonomy and allowed-term filtering, and optional thumbnails.

The field implementation lives in:

```text
acf/sp-post-selector/index.php
```

## Field Config

Use the Builder helper:

```php
->addFields( smart_relationship( 'team_members', [
    'label'         => __( 'Team Members', 'ACF' ),
    'post_type'     => [ 'team' ],
    'taxonomy'      => [ 'department' ],
    'taxonomy_terms' => [ 'department:12', 'department:18' ],
    'return_format' => 'id',
    'modes'         => [ 'manual', 'favorites', 'related', 'all' ],
    'default_mode'  => 'manual',
    'related_fields' => [ 'linked_team' ],
    'thumb_field'   => 'none',
    'min'           => 0,
    'max'           => 0,
] ) )
```

## Config Options

`post_type`:
Allowed post types. Empty means public post types in the picker, and `any` during formatting fallback.

`taxonomy`:
Optional taxonomy filters shown in the picker.

`taxonomy_terms`:
Optional allowed terms written as `taxonomy:term_id`. When configured, only posts assigned to one of the selected terms in each taxonomy are available and returned. Multiple terms from the same taxonomy use `IN` matching; restrictions from different taxonomies are combined with `AND`. The picker dropdown is limited to the allowed terms.

`return_format`:
`id` returns post IDs. `object` returns `WP_Post` objects.

`modes`:
Available editor modes:

```php
'manual'
'favorites'
'related'
'all'
```

`related_fields`:
ACF Relationship fields stored on the current post and used by `related` mode. They can be selected in the field settings. When empty, the field automatically reads `linked_{post_type}` for every configured post type, such as `linked_testimonials`.

`default_mode`:
Initial mode for a new value.

`thumb_field`:
ACF image field name used for thumbnails. An ordered array of field names is also supported; the first image found is used. Use `none` to hide thumbnails. Empty/`featured_image` falls back to the featured image.

`min`, `max`:
Selection limits. `max => 0` means unlimited.

## Returned Value

Saved raw value shape:

```php
[
    'mode' => 'manual',
    'ids'  => [ 12, 34, 56 ],
]
```

Formatted value from `get_field()` depends on `return_format`:

```php
// return_format => 'id'
[ 12, 34, 56 ]

// return_format => 'object'
[ WP_Post, WP_Post, WP_Post ]
```

## Mode Behavior

`manual`:
Returns the manually selected IDs in saved order.

`favorites`:
Returns posts marked as favorite. If `sp_get_favorite_post_ids()` exists, that helper is used. Otherwise the field queries posts with `_sp_favorite_post = 1`.

`related`:
Returns posts selected in the configured ACF Relationship fields on the current post. Source order is retained and duplicate IDs are removed. Results are limited to the Smart Relationship field's configured `post_type` and `taxonomy_terms` values.

`all`:
Returns all published posts for configured post types ordered by `menu_order ASC, date DESC`.

## Template Example

```php
$posts = get_field( 'team_members' );

foreach ( $posts as $item ) {
    $post_id = $item instanceof WP_Post ? $item->ID : (int) $item;
    if ( ! $post_id ) {
        continue;
    }

    echo esc_html( get_the_title( $post_id ) );
}
```

## AJAX

The admin picker searches posts through:

```text
wp_ajax_sp_srel_search
```

The request is protected by the `sp_srel` nonce generated in the field config. Every requested post type is validated and requires its `edit_posts` capability. Pickers initialize lazily near the viewport, skip ACF clone templates, and share identical successful responses for 60 seconds.
