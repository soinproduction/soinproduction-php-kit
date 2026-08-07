# SP Related Posts

`sp_related_posts` is an ACF field type for automatic related-post selection. It scores candidate posts by shared taxonomies, ACF text values, title tokens, and optionally content tokens.

The field implementation lives in:

```text
acf/related-posts/index.php
```

## Field Config

Use the Builder helper:

```php
->addFields( sp_related_posts( 'related_posts', [
    'label'                => __( 'Related Posts', 'ACF' ),
    'count'                => 3,
    'candidate_post_types' => [ 'case_study' ],
    'post_status'          => [ 'publish' ],
    'candidate_limit'      => 250,
    'use_taxonomies'       => 1,
    'use_acf'              => 1,
    'use_title'            => 1,
    'use_content'          => 0,
    'taxonomy_weight'      => 45,
    'acf_weight'           => 35,
    'title_weight'         => 15,
    'content_weight'       => 5,
    'minimum_score'        => 0,
    'date_limit_days'      => 0,
    'excluded_fields'      => [ 'hero_title', 'seo_description' ],
] ) )
```

Or use a raw ACF local field:

```php
[
    'key'   => 'field_related_posts',
    'label' => 'Related Posts',
    'name'  => 'related_posts',
    'type'  => 'sp_related_posts',
    'count' => 3,
]
```

## Config Options

`count`:
Number of related posts to store and display in the field UI. Clamped from `1` to `24`.

`candidate_post_types`:
Allowed candidate post types. If empty, the current post type is used.

`post_status`:
Candidate statuses. Defaults to `publish`.

`candidate_limit`:
Maximum number of candidate posts loaded for scoring. Clamped from `10` to `1000`.

`use_taxonomies`, `use_acf`, `use_title`, `use_content`:
Enable or disable scoring sources.

`taxonomy_weight`, `acf_weight`, `title_weight`, `content_weight`:
Score weights from `0` to `100`.

`minimum_score`:
Minimum score required for a candidate to be included.

`date_limit_days`:
Optional recency window. `0` means no date limit.

`excluded_fields`:
ACF field keys/names to ignore while extracting tokens. Accepts an array, comma-separated string, or newline-separated string.

## Returned Value

`get_field( 'related_posts' )` returns:

```php
[
    'count'     => 3,
    'post_type' => 'case_study',
    'ids'       => [ 123, 456, 789 ],
]
```

## Frontend Helpers

Get normalized IDs:

```php
$ids = sp_acf_related_posts_ids( get_field( 'related_posts' ) );
```

Build a query:

```php
$query = sp_acf_related_posts_query( get_field( 'related_posts' ), [
    'posts_per_page' => 3,
] );

if ( $query->have_posts() ) {
    while ( $query->have_posts() ) {
        $query->the_post();
        // Render card.
    }
    wp_reset_postdata();
}
```

Normalize a raw value:

```php
$value = sp_acf_related_posts_normalize_value( get_field( 'related_posts' ) );
```

## Admin Behavior

The field renders suggested posts in the editor and stores selected IDs. The Refresh button recalculates suggestions through the `sp_acf_related_posts_refresh` AJAX action.

The value is recalculated against the current post when the field is empty or refreshed. Manual changes are saved as IDs inside the field value.

