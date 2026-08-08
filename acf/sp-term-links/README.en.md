# SP Term Links

`taxonomy_urls` is an ACF relational field that renders one URL input per term assigned to the current post. It is useful when selected taxonomy terms need custom outbound links on a per-post basis.

The field implementation lives in:

```text
acf/sp-term-links/index.php
```

## Field Config

Use a raw ACF field:

```php
->addField( 'industry_urls', 'taxonomy_urls', [
    'label'      => __( 'Industry URLs', 'ACF' ),
    'taxonomy'   => 'case_study_industry',
    'icon_field' => 'icon',
] )
```

Or local field array:

```php
[
    'key'        => 'field_industry_urls',
    'label'      => 'Industry URLs',
    'name'       => 'industry_urls',
    'type'       => 'taxonomy_urls',
    'taxonomy'   => 'case_study_industry',
    'icon_field' => 'icon',
]
```

## Config Options

`taxonomy`:
Taxonomy used to pull currently assigned terms from the edited post.

`icon_field`:
ACF image field name on the term. Empty string hides icons.

## Admin Behavior

The field watches the selected taxonomy terms in the post edit screen. When terms are checked or unchecked, rows are synced through AJAX.

If the post has no assigned terms, the field shows an empty message.

## Returned Value

`get_field( 'industry_urls' )` returns an associative array keyed by term ID:

```php
[
    12 => 'https://example.com/industry-a',
    34 => 'https://example.com/industry-b',
]
```

Only non-empty URLs are saved.

## Template Example

```php
$urls  = get_field( 'industry_urls' );
$terms = get_the_terms( get_the_ID(), 'case_study_industry' );

if ( ! is_wp_error( $terms ) && $terms ) {
    foreach ( $terms as $term ) {
        $url = $urls[ $term->term_id ] ?? '';

        if ( $url ) {
            echo '<a href="' . esc_url( $url ) . '">' . esc_html( $term->name ) . '</a>';
        } else {
            echo '<span>' . esc_html( $term->name ) . '</span>';
        }
    }
}
```

## AJAX

Rows are refreshed through:

```text
wp_ajax_sp_tax_urls_get_rows
```

The AJAX response returns rendered row HTML for the currently checked term IDs.

