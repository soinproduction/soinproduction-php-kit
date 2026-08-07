# Smart Relationship

`smart_relationship` is an ACF relational field for selecting posts with a richer admin UI: tabs for manual/favorites/all modes, searchable available posts, selected ordering, optional taxonomy filtering, and optional thumbnails.

The field implementation lives in:

```text
core/acf/smart-relationship/index.php
```

## Field Config

Use the Builder helper:

```php
->addFields( smart_relationship( 'team_members', [
    'label'         => __( 'Team Members', 'ACF' ),
    'post_type'     => [ 'team' ],
    'taxonomy'      => [ 'department' ],
    'return_format' => 'id',
    'modes'         => [ 'manual', 'favorites', 'all' ],
    'default_mode'  => 'manual',
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

`return_format`:
`id` returns post IDs. `object` returns `WP_Post` objects.

`modes`:
Available editor modes:

```php
'manual'
'favorites'
'all'
```

`default_mode`:
Initial mode for a new value.

`thumb_field`:
ACF image field name used for thumbnails. Use `none` to hide thumbnails. Empty/`featured_image` falls back to featured image.

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

The request is protected by the `sp_srel` nonce generated in the field config.

