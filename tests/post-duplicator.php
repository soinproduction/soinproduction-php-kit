<?php

/**
 * Focused regression checks for shared post-associated data duplication.
 * Run directly with: php tests/post-duplicator.php
 */

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

final class WP_Post {
	public function __construct(
		public int $ID,
		public string $post_type
	) {
	}
}

$GLOBALS['sp_duplicate_posts'] = [
	10 => new WP_Post( 10, 'widgets' ),
	20 => new WP_Post( 20, 'widgets' ),
	30 => new WP_Post( 30, 'for-editor' ),
];
$GLOBALS['sp_duplicate_meta'] = [
	10 => [
		'acf_field'              => [ 'a:1:{s:5:"color";s:4:"blue";}' ],
		'_thumbnail_id'          => [ '44' ],
		'_edit_lock'             => [ 'old-lock' ],
		'_icl_lang_duplicate_of' => [ '5' ],
	],
	20 => [
		'acf_field' => [ 'stale' ],
	],
];
$GLOBALS['sp_duplicate_terms'] = [
	10 => [
		'widgets_category'  => [ 7, 9 ],
		'language'          => [ 2 ],
		'post_translations' => [ 12 ],
	],
];
$GLOBALS['sp_duplicate_term_writes']   = [];
$GLOBALS['sp_duplicate_actions']       = [];
$GLOBALS['sp_duplicate_provider']      = 'polylang';
$GLOBALS['sp_duplicate_pll_languages'] = [ 10 => 'uk' ];

function get_post( int $post_id ): ?WP_Post {
	return $GLOBALS['sp_duplicate_posts'][ $post_id ] ?? null;
}

function apply_filters( string $hook, $value, ...$args ) {
	if ( $hook === 'sp_post_duplicator_language_providers' ) {
		return [ $GLOBALS['sp_duplicate_provider'] ];
	}

	if ( $hook === 'wpml_is_translated_post_type' ) {
		return true;
	}

	if ( $hook === 'wpml_element_language_details' ) {
		return (object) [ 'language_code' => 'de', 'trid' => 81 ];
	}

	if ( $hook === 'wpml_element_type' ) {
		return 'post_' . $value;
	}

	return $value;
}

function get_post_meta( int $post_id ): array {
	return $GLOBALS['sp_duplicate_meta'][ $post_id ] ?? [];
}

function delete_post_meta( int $post_id, string $meta_key ): void {
	unset( $GLOBALS['sp_duplicate_meta'][ $post_id ][ $meta_key ] );
}

function add_post_meta( int $post_id, string $meta_key, $value ): void {
	$GLOBALS['sp_duplicate_meta'][ $post_id ][ $meta_key ][] = $value;
}

function maybe_unserialize( $value ) {
	if ( ! is_string( $value ) || ! str_starts_with( $value, 'a:' ) ) {
		return $value;
	}

	return unserialize( $value, [ 'allowed_classes' => false ] );
}

function get_object_taxonomies( string $post_type, string $output ): array {
	return [ 'widgets_category', 'language', 'post_translations' ];
}

function wp_get_object_terms( int $post_id, string $taxonomy, array $args ): array {
	return $GLOBALS['sp_duplicate_terms'][ $post_id ][ $taxonomy ] ?? [];
}

function is_wp_error( $value ): bool {
	return false;
}

function wp_set_object_terms( int $post_id, array $term_ids, string $taxonomy, bool $append ): void {
	$GLOBALS['sp_duplicate_term_writes'][ $taxonomy ] = compact( 'post_id', 'term_ids', 'append' );
}

function do_action( string $hook, ...$args ): void {
	$GLOBALS['sp_duplicate_actions'][] = compact( 'hook', 'args' );
}

function pll_is_translated_post_type( string $post_type ): bool {
	return true;
}

function pll_get_post_language( int $post_id, string $field ): string|false {
	return $GLOBALS['sp_duplicate_pll_languages'][ $post_id ] ?? false;
}

function pll_set_post_language( int $post_id, string $language ): void {
	$GLOBALS['sp_duplicate_pll_languages'][ $post_id ] = $language;
}

require dirname( __DIR__ ) . '/src/PostDuplicator.php';

use SoinProduction\Kit\PostDuplicator;

$copied = PostDuplicator::copyAssociatedData( 10, 20 );
$after_copy = array_values( array_filter(
	$GLOBALS['sp_duplicate_actions'],
	static fn( array $action ): bool => $action['hook'] === 'sp_post_duplicator_after_copy'
) );

$checks = [
	'associated data copies for matching post types'     => $copied,
	'ACF serialized meta is preserved as a PHP value'   => ( $GLOBALS['sp_duplicate_meta'][20]['acf_field'][0]['color'] ?? '' ) === 'blue',
	'featured image meta is copied'                     => ( $GLOBALS['sp_duplicate_meta'][20]['_thumbnail_id'][0] ?? '' ) === '44',
	'editor locks are not copied'                       => ! isset( $GLOBALS['sp_duplicate_meta'][20]['_edit_lock'] ),
	'WPML duplicate ownership meta is not copied'       => ! isset( $GLOBALS['sp_duplicate_meta'][20]['_icl_lang_duplicate_of'] ),
	'ordinary widget categories are copied'             => ( $GLOBALS['sp_duplicate_term_writes']['widgets_category']['term_ids'] ?? [] ) === [ 7, 9 ],
	'Polylang language taxonomy is not copied directly' => ! isset( $GLOBALS['sp_duplicate_term_writes']['language'] ),
	'Polylang translation group is not cloned'          => ! isset( $GLOBALS['sp_duplicate_term_writes']['post_translations'] ),
	'Polylang source language is assigned to the copy'  => ( $GLOBALS['sp_duplicate_pll_languages'][20] ?? '' ) === 'uk',
	'provider is reported to extension hooks'           => ( $after_copy[0]['args'][2] ?? '' ) === 'polylang',
];

$GLOBALS['sp_duplicate_provider'] = 'wpml';
$GLOBALS['sp_duplicate_actions']  = [];
$wpml_provider = PostDuplicator::copyLanguage( $GLOBALS['sp_duplicate_posts'][10], 20 );
$wpml_action = array_values( array_filter(
	$GLOBALS['sp_duplicate_actions'],
	static fn( array $action ): bool => $action['hook'] === 'wpml_set_element_language_details'
) );
$wpml_args = $wpml_action[0]['args'][0] ?? [];

$checks += [
	'WPML provider is detected'                         => $wpml_provider === 'wpml',
	'WPML source language is assigned to the copy'      => ( $wpml_args['language_code'] ?? '' ) === 'de',
	'WPML receives its normalized custom element type'  => ( $wpml_args['element_type'] ?? '' ) === 'post_widgets',
	'WPML copy starts an independent translation group' => array_key_exists( 'trid', $wpml_args ) && $wpml_args['trid'] === false,
	'WPML copy is marked as an original element'         => array_key_exists( 'source_language_code', $wpml_args ) && $wpml_args['source_language_code'] === null,
	'mismatched post types are rejected'                 => PostDuplicator::copyAssociatedData( 10, 30 ) === false,
];

$failed = array_keys( array_filter( $checks, static fn( bool $passed ): bool => ! $passed ) );
if ( $failed ) {
	fwrite( STDERR, 'Post duplicator failures: ' . implode( ', ', $failed ) . PHP_EOL );
	exit( 1 );
}

echo 'Post duplicator: ' . count( $checks ) . " checks passed.\n";
