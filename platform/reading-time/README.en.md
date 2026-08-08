# Reading Time

Calculates reading time from post content and recursively collected ACF values. Enable it with `reading-time`.

Primary API:

- `sp_reading_time_for_post( int $post_id = 0, string $label = 'min read', array $acf_fields = [] ): string`
- `sp_reading_time( $content = null, string $label = 'min read' ): string`

Use `sp_reading_time_skip_keys` and `sp_reading_time_acf_fields` to control which ACF values contribute to the result.
