<?php
declare(strict_types=1);

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'THEME_SLUG', 'keramida-test' );

class WP_Term {
	public int $term_id;
	public string $name;
	public string $slug;
	public int $parent;

	public function __construct( int $term_id, string $name, string $slug, int $parent = 0 ) {
		$this->term_id = $term_id;
		$this->name    = $name;
		$this->slug    = $slug;
		$this->parent  = $parent;
	}
}

class acf_field {}

$GLOBALS['actions'] = [];
function add_action( string $hook, $callback ): void { $GLOBALS['actions'][ $hook ][] = $callback; }
function acf_register_field_type( string $class_name ): void { $GLOBALS['registered_field_type'] = $class_name; }
function __( string $text, string $domain = '' ): string { return $text; }
function _n( string $single, string $plural, int $number, string $domain = '' ): string { return $number === 1 ? $single : $plural; }
function sanitize_key( string $value ): string { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ) ?? ''; }
function sanitize_text_field( string $value ): string { return trim( strip_tags( $value ) ); }
function sanitize_title( string $value ): string { return strtolower( preg_replace( '/[^a-z0-9-]+/i', '-', trim( $value ) ) ?? '' ); }
function absint( $value ): int { return abs( (int) $value ); }
function taxonomy_exists( string $taxonomy ): bool { return in_array( $taxonomy, [ 'category', 'service_category' ], true ); }
function get_taxonomies( array $args = [], string $output = 'names' ): array { return []; }
function wp_parse_args( $args, array $defaults = [] ): array { return array_merge( $defaults, is_array( $args ) ? $args : [] ); }
function is_wp_error( $value ): bool { return false; }
function get_term_meta( int $term_id, string $key, bool $single = false ) { return $GLOBALS['term_order'][ $term_id ] ?? ''; }
function sp_archive_normalize_per_page( $value, int $fallback = 9 ): int {
	if ( $value === 'all' || (int) $value === -1 ) { return -1; }
	return (int) $value > 0 ? (int) $value : $fallback;
}
function sp_archive_normalize_mode( $value ): string {
	return in_array( $value, [ 'pagination', 'load_more', 'infinity_scroll' ], true ) ? $value : 'pagination';
}
function sp_archive_normalize_sort( $value, string $fallback = 'newest' ): string {
	return in_array( $value, [ 'newest', 'oldest', 'az', 'za', 'menu_order', 'manual' ], true ) ? $value : $fallback;
}
function sp_archive_normalize_filter_terms( $value ): array {
	if ( is_string( $value ) && str_contains( $value, '|' ) ) { $value = explode( '|', $value ); }
	return array_values( array_unique( array_filter( array_map( 'sanitize_title', is_array( $value ) ? $value : [ $value ] ) ) ) );
}
function sp_archive_filter_scope_slugs( string $taxonomy, array $slugs, string $mode ): array { return $slugs; }
function sp_archive_sanitize_template( $template ): string { return trim( (string) $template, '/' ); }
function sp_archive_template_args_for_index( array $args, int $index ): array { return $args; }
function sp_archive_render_template( string $template, array $args = [] ): string {
	return $template === 'term-card' ? (string) ( $args['term_id'] ?? '' ) . ':' . ( $args['term']->name ?? '' ) . ';' : 'empty';
}
function get_terms( array $args ): array {
	$GLOBALS['last_term_args'] = $args;
	if ( isset( $args['include'] ) ) {
		$include = array_map( 'absint', (array) $args['include'] );
		return array_values( array_filter( $GLOBALS['terms'], static fn( WP_Term $term ): bool => in_array( $term->term_id, $include, true ) ) );
	}
	return $GLOBALS['terms'];
}
function get_term( int $term_id, string $taxonomy = '' ) {
	foreach ( $GLOBALS['terms'] as $term ) {
		if ( $term->term_id === $term_id ) { return $term; }
	}
	return null;
}

$GLOBALS['terms'] = [
	new WP_Term( 7, 'Zulu', 'zulu', 0 ),
	new WP_Term( 2, 'Alpha', 'alpha', 0 ),
	new WP_Term( 4, 'Bravo', 'bravo', 2 ),
];
$GLOBALS['term_order'] = [ 7 => 2, 2 => 1 ];

require dirname( __DIR__ ) . '/acf/sp-archive-builder/taxonomy.php';

foreach ( $GLOBALS['actions']['acf/include_field_types'] ?? [] as $callback ) {
	$callback();
}

$normalized = sp_taxonomy_archive_builder_normalize( [
	'taxonomy'        => 'service_category',
	'per_page'        => 'all',
	'pagination_type' => 'bad-mode',
	'order_mode'      => 'bad-sort',
] );

$page = sp_taxonomy_archive_prepare_query( [
	'taxonomy'        => 'service_category',
	'per_page'        => 2,
	'pagination_type' => 'pagination',
	'order_mode'      => 'az',
	'top_level_only'  => 1,
], 2, 'az', true );

$load_more = sp_taxonomy_archive_prepare_query( [
	'taxonomy'        => 'service_category',
	'per_page'        => 2,
	'pagination_type' => 'load_more',
	'order_mode'      => 'az',
], 2, 'az', false );

$manual = sp_taxonomy_archive_sort_terms( $GLOBALS['terms'], 'menu_order' );
$cards  = sp_taxonomy_archive_render_cards( array_slice( $manual, 0, 2 ), 'term-card' );

$filtered = sp_taxonomy_archive_prepare_query( [
	'taxonomy'        => 'service_category',
	'filters_enabled' => 1,
	'filter_values'   => 'alpha|zulu',
	'per_page'        => 1,
	'pagination_type' => 'pagination',
	'order_mode'      => 'az',
], 2, 'az', true );
$filter_config = sp_taxonomy_archive_filter_config( [
	'taxonomy'        => 'service_category',
	'filters_enabled' => 1,
	'filter_ui'       => 'checkbox',
	'filter_arg'      => 'service_filter',
] );
$show_all = sp_taxonomy_archive_prepare_query( [
	'taxonomy'        => 'service_category',
	'filters_enabled' => 1,
	'filter_values'   => [],
	'per_page'        => -1,
], 1, 'az', true );
$manual_selection = sp_taxonomy_archive_prepare_query( [
	'taxonomy'        => 'service_category',
	'source_mode'     => 'manual',
	'manual_terms'    => [ 'mode' => 'manual', 'ids' => [ 2, 7 ] ],
	'per_page'        => 1,
	'pagination_type' => 'pagination',
], 1, 'az', true );
$empty_manual_selection = sp_taxonomy_archive_prepare_query( [
	'taxonomy'     => 'service_category',
	'source_mode'  => 'manual',
	'manual_terms' => [ 'mode' => 'manual', 'ids' => [] ],
	'per_page'     => 3,
], 1, 'az', true );
$field_type = new SP_ACF_Field_Taxonomy_Archive_Builder();
$saved_field = $field_type->update_value( [
	'per_page'         => 2,
	'pagination_type'  => 'load_more',
	'order_mode'       => 'newest',
	'filter_ui'        => 'multiselect',
	'filter_terms_mode' => 'selected',
], 1, [ 'per_page_choices' => [] ] );
$saved_manual_field = $field_type->update_value( [
	'source_mode'  => 'manual',
	'manual_terms' => [ 'mode' => 'manual', 'ids' => [ 7, 2, 999, 7 ] ],
	'per_page'     => 1,
], 1, [ 'taxonomy' => 'service_category', 'per_page_choices' => [] ] );
$formatted_field = $field_type->format_value( array_merge( $saved_field, [
	'filter_ui'         => 'multiselect',
	'filter_terms_mode' => 'parent',
	'all_label'         => 'Stale label',
] ), 1, [
	'taxonomy'          => 'service_category',
	'filters_enabled'   => 1,
	'filter_ui'         => 'buttons',
	'filter_terms_mode' => 'selected',
	'all_label'         => 'All Services',
	'per_page'          => 9,
] );

$checks = [
	'all normalizes to unlimited'          => $normalized['per_page'] === -1,
	'invalid pagination uses pagination'   => $normalized['pagination_type'] === 'pagination',
	'invalid sort uses alphabetical order' => $normalized['order_mode'] === 'az',
	'second page contains final term'       => count( $page['terms'] ) === 1 && $page['terms'][0]->name === 'Zulu',
	'pagination reports total terms'        => $page['total_found'] === 3 && $page['total_pages'] === 2,
	'top-level scope reaches term query'    => ( $GLOBALS['last_term_args']['parent'] ?? null ) === null,
	'load more includes previous pages'     => count( $load_more['terms'] ) === 3,
	'menu order uses Content Manager meta'  => array_map( static fn( WP_Term $term ): int => $term->term_id, $manual ) === [ 2, 7, 4 ],
	'ACF field type is registered'          => ( $GLOBALS['registered_field_type'] ?? '' ) === 'SP_ACF_Field_Taxonomy_Archive_Builder',
	'card receives term id and object'       => $cards === '2:Alpha;7:Zulu;',
	'filter applies before pagination'       => $filtered['total_found'] === 2 && $filtered['terms'][0]->slug === 'zulu',
	'filter config exposes selected UI'      => ( $filter_config[0]['ui'] ?? '' ) === 'checkbox',
	'filter config exposes custom URL arg'   => ( $filter_config[0]['query_arg'] ?? '' ) === 'service_filter',
	'show all keeps every category loaded'   => count( $show_all['terms'] ) === 3 && $show_all['total_pages'] === 1,
	'show all enables tab-like filtering'    => sp_taxonomy_archive_uses_client_filter( [ 'filter_terms_mode' => 'selected' ], -1, $filter_config ),
	'ACF preserves selected category count'  => ( $saved_field['per_page'] ?? 0 ) === 2,
	'ACF stores only per-instance controls'   => array_keys( $saved_field ) === [ 'source_mode', 'manual_terms', 'per_page', 'pagination_type', 'order_mode' ],
	'ACF field owns filter settings'          => ( $formatted_field['filter_ui'] ?? '' ) === 'buttons' && ( $formatted_field['filter_terms_mode'] ?? '' ) === 'selected',
	'ACF field owns the all label'             => ( $formatted_field['all_label'] ?? '' ) === 'All Services',
	'manual terms paginate within selection'   => $manual_selection['total_found'] === 2 && $manual_selection['total_pages'] === 2 && count( $manual_selection['terms'] ) === 1,
	'manual terms preserve editor order'        => ( $manual_selection['terms'][0]->term_id ?? 0 ) === 2,
	'empty manual terms return no categories'  => $empty_manual_selection['total_found'] === 0 && $empty_manual_selection['terms'] === [],
	'ACF sanitizes the manual term selection'   => ( $saved_manual_field['manual_terms']['ids'] ?? [] ) === [ 7, 2 ],
];

// The last query above has no parent restriction; run the scoped query once more
// before checking its captured arguments.
sp_taxonomy_archive_prepare_query( [
	'taxonomy'       => 'service_category',
	'per_page'       => 2,
	'top_level_only' => 1,
], 1, 'az', true );
$checks['top-level scope reaches term query'] = ( $GLOBALS['last_term_args']['parent'] ?? null ) === 0;

$failed = array_keys( array_filter( $checks, static fn( bool $passed ): bool => ! $passed ) );
if ( $failed ) {
	fwrite( STDERR, 'Taxonomy Archive Builder failures: ' . implode( ', ', $failed ) . PHP_EOL );
	exit( 1 );
}

echo 'Taxonomy Archive Builder: ' . count( $checks ) . " checks passed.\n";
