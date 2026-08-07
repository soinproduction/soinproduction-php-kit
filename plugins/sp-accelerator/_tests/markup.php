<?php

/**
 * Focused regression checks for conservative LCP image selection.
 * Run directly with: php _tests/markup.php
 */

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $path ) { return rtrim( (string) $path, '/\\' ) . '/'; }
}
$wp_root = dirname( __DIR__, 7 );
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', trailingslashit( $wp_root ) );
}
if ( ! defined( 'MB_IN_BYTES' ) ) {
	define( 'MB_IN_BYTES', 1024 * 1024 );
}
if ( ! function_exists( '__' ) ) {
	function __( $text ) { return $text; }
}
if ( ! function_exists( '_doing_it_wrong' ) ) {
	function _doing_it_wrong() {}
}
if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) { return abs( (int) $value ); }
}
if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
}
if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) { return 'https://example.test' . $path; }
}
if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) { return htmlspecialchars( (string) $url, ENT_QUOTES, 'UTF-8' ); }
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
}
if ( ! function_exists( 'wp_has_noncharacters' ) ) {
	function wp_has_noncharacters( string $text ): bool { return false; }
}
if ( ! function_exists( 'wp_kses_uri_attributes' ) ) {
	function wp_kses_uri_attributes(): array { return [ 'href', 'src', 'srcset' ]; }
}

if ( ! class_exists( 'WP_HTML_Processor', false ) ) {
	require ABSPATH . 'wp-includes/class-wp-token-map.php';
	$html_api = ABSPATH . 'wp-includes/html-api/';
	foreach ( [
		'html5-named-character-references.php',
		'class-wp-html-attribute-token.php',
		'class-wp-html-span.php',
		'class-wp-html-doctype-info.php',
		'class-wp-html-text-replacement.php',
		'class-wp-html-decoder.php',
		'class-wp-html-tag-processor.php',
		'class-wp-html-unsupported-exception.php',
		'class-wp-html-active-formatting-elements.php',
		'class-wp-html-open-elements.php',
		'class-wp-html-token.php',
		'class-wp-html-stack-event.php',
		'class-wp-html-processor-state.php',
		'class-wp-html-processor.php',
	] as $file ) {
		require $html_api . $file;
	}
}

final class SP_Accelerator_Config {
	public function enabled( string $key = 'enabled' ): bool {
		return in_array( $key, [ 'enabled', 'optimize_markup', 'preload_lcp_image' ], true );
	}
}

require dirname( __DIR__ ) . '/includes/class-markup.php';

function sp_markup_document( string $body ): string {
	return '<!doctype html><html><head><title>Markup test</title></head><body>'
		. $body
		. str_repeat( '<!-- markup-padding -->', 20 )
		. '</body></html>';
}

function sp_markup_optimize( string $body ): string {
	$optimizer = new SP_Accelerator_Markup( new SP_Accelerator_Config() );
	$property  = new ReflectionProperty( SP_Accelerator_Markup::class, 'capturing' );
	$property->setAccessible( true );
	$property->setValue( $optimizer, true );
	return $optimizer->optimize( sp_markup_document( $body ) );
}

function sp_markup_image_tag( string $html, string $src ): string {
	$quoted = preg_quote( $src, '~' );
	return preg_match( '~<img\\b(?=[^>]*\\bsrc=["\']' . $quoted . '["\'])[^>]*>~i', $html, $match ) ? $match[0] : '';
}

function sp_markup_is_prioritized( string $html, string $src ): bool {
	$tag = sp_markup_image_tag( $html, $src );
	return $tag !== '' && strpos( $tag, 'fetchpriority="high"' ) !== false && strpos( $tag, 'loading="eager"' ) !== false;
}

function sp_markup_has_preload( string $html, string $src ): bool {
	$quoted = preg_quote( $src, '~' );
	return preg_match( '~<link\\b(?=[^>]*\\brel="preload")(?=[^>]*\\bas="image")(?=[^>]*\\bhref="' . $quoted . '")[^>]*>~i', $html ) === 1;
}

$checks = [];

$picture = sp_markup_optimize(
	'<main><picture><source media="(max-width: 600px)" srcset="mobile.webp"><img src="desktop.jpg" width="1200" height="700"></picture>'
	. '<img src="hero.jpg" width="1200" height="700"></main>'
);
$checks['PICTURE image is never prioritized'] = ! sp_markup_is_prioritized( $picture, 'desktop.jpg' );
$checks['PICTURE image is never preloaded'] = ! sp_markup_has_preload( $picture, 'desktop.jpg' );
$checks['eligible image after PICTURE is prioritized'] = sp_markup_is_prioritized( $picture, 'hero.jpg' );
$checks['eligible image after PICTURE is preloaded'] = sp_markup_has_preload( $picture, 'hero.jpg' );

$ancestors = sp_markup_optimize(
	'<main><div class="desktop-only"><img src="desktop-only.jpg" width="1200" height="700"></div>'
	. '<section style="display:none"><img src="display-none.jpg" width="1200" height="700"></section>'
	. '<div class="md:hidden"><img src="responsive.jpg" width="1200" height="700"></div>'
	. '<div class="mob"><img src="theme-mobile.jpg" width="1200" height="700"></div>'
	. '<img src="visible.jpg" width="1200" height="700"></main>'
);
$checks['desktop-only ancestor is skipped'] = ! sp_markup_is_prioritized( $ancestors, 'desktop-only.jpg' ) && ! sp_markup_has_preload( $ancestors, 'desktop-only.jpg' );
$checks['inline-hidden ancestor is skipped'] = ! sp_markup_is_prioritized( $ancestors, 'display-none.jpg' ) && ! sp_markup_has_preload( $ancestors, 'display-none.jpg' );
$checks['responsive utility ancestor is skipped'] = ! sp_markup_is_prioritized( $ancestors, 'responsive.jpg' ) && ! sp_markup_has_preload( $ancestors, 'responsive.jpg' );
$checks['theme responsive ancestor is skipped'] = ! sp_markup_is_prioritized( $ancestors, 'theme-mobile.jpg' ) && ! sp_markup_has_preload( $ancestors, 'theme-mobile.jpg' );
$checks['visible sibling remains eligible'] = sp_markup_is_prioritized( $ancestors, 'visible.jpg' ) && sp_markup_has_preload( $ancestors, 'visible.jpg' );

$closed_details = sp_markup_optimize(
	'<main><details><img src="closed.jpg" width="1200" height="700"></details>'
	. '<img src="after-details.jpg" width="1200" height="700"></main>'
);
$checks['closed DETAILS ancestor is skipped'] = ! sp_markup_is_prioritized( $closed_details, 'closed.jpg' ) && sp_markup_is_prioritized( $closed_details, 'after-details.jpg' );

$unsupported = sp_markup_optimize(
	'<table><div>unsupported foster parenting</div></table>'
	. '<main><img src="fallback.jpg" width="1200" height="700"></main>'
);
$checks['aborted full parser falls back without guessing LCP'] = ! sp_markup_is_prioritized( $unsupported, 'fallback.jpg' ) && ! sp_markup_has_preload( $unsupported, 'fallback.jpg' );

$failed = array_keys( array_filter( $checks, static function ( bool $passed ): bool {
	return ! $passed;
} ) );

if ( $failed ) {
	fwrite( STDERR, 'SP Accelerator markup failures: ' . implode( ', ', $failed ) . PHP_EOL );
	exit( 1 );
}

echo 'SP Accelerator markup: ' . count( $checks ) . " checks passed.\n";
