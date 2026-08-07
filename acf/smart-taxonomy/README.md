# Smart Taxonomy

`smart_taxonomy` is an ACF relational field for selecting taxonomy terms with a richer admin UI: manual/all modes, searchable available terms, selected ordering, optional taxonomy filtering, and optional term thumbnails.

The field implementation lives in:

```text
core/acf/smart-taxonomy/index.php
```

## Field Config

Use the Builder helper:

```php
->addFields( smart_taxonomy( 'service_areas', [
    'label'         => __( 'Service Areas', 'ACF' ),
    'taxonomy'      => [ 'city', 'region' ],
    'return_format' => 'id',
    'modes'         => [ 'manual', 'all' ],
    'default_mode'  => 'manual',
    'thumb_field'   => 'none',
    'min'           => 0,
    'max'           => 0,
] ) )
```

## Config Options

`taxonomy`:
Allowed taxonomies. Empty means public taxonomies.

`return_format`:
`id` returns term IDs. `object` returns `WP_Term` objects.

`modes`:
Available editor modes:

```php
'manual'
'all'
```

`default_mode`:
Initial mode for a new value.

`thumb_field`:
ACF image field name on the term. Use `none` or empty string to hide thumbnails.

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
[ WP_Term, WP_Term, WP_Term ]
```

## Mode Behavior

`manual`:
Returns manually selected terms in saved order.

`all`:
Returns all terms from configured taxonomies, ordered by name ascending.

## Template Example

```php
$terms = get_field( 'service_areas' );

foreach ( $terms as $item ) {
    $term = $item instanceof WP_Term ? $item : get_term( (int) $item );
    if ( ! $term || is_wp_error( $term ) ) {
        continue;
    }

    echo esc_html( $term->name );
}
```

## AJAX

The admin picker searches terms through:

```text
wp_ajax_sp_stax_search
```

The request is protected by the `sp_stax` nonce generated in the field config.

