<?php

/**
 * Focused regression checks for script delay classification.
 * Run directly with: php tests/accelerator-assets.php
 */

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

function get_option( string $key, $default = [] ) {
	return $key === 'sp_accelerator_settings' ? [ 'delay_section_scripts' => 1 ] : $default;
}

function is_admin(): bool {
	return false;
}

function wp_parse_url( string $url, int $component = -1 ) {
	return parse_url( $url, $component );
}

function apply_filters( string $hook, $value ) {
	return $value;
}

final class SP_Accelerator_Test_Scripts {
	/** @var array<string, object> */
	public array $registered = [];

	public function get_data( string $handle, string $key ) {
		return false;
	}
}

$GLOBALS['sp_accelerator_test_scripts'] = new SP_Accelerator_Test_Scripts();

function wp_scripts(): SP_Accelerator_Test_Scripts {
	return $GLOBALS['sp_accelerator_test_scripts'];
}

require dirname( __DIR__ ) . '/plugins/sp-accelerator/includes/class-config.php';
require dirname( __DIR__ ) . '/plugins/sp-accelerator/includes/class-assets.php';

$scripts = wp_scripts();
$assets  = new SP_Accelerator_Assets( new SP_Accelerator_Config() );

$scripts->registered['theme-hero'] = (object) [
	'src' => 'https://example.test/wp-content/themes/site/assets/js/modules/section-hero.a1b2c3d4.js',
];
$heroTag = '<script src="https://example.test/wp-content/themes/site/assets/js/modules/section-hero.a1b2c3d4.js"></script>';
$heroResult = $assets->delay_noncritical_theme_scripts( $heroTag, 'theme-hero' );

$scripts->registered['theme-slider'] = (object) [
	'src' => 'https://example.test/wp-content/themes/site/assets/js/modules/section-slider.a1b2c3d4.js',
];
$sliderTag = '<script src="https://example.test/wp-content/themes/site/assets/js/modules/section-slider.a1b2c3d4.js"></script>';
$sliderResult = $assets->delay_noncritical_theme_scripts( $sliderTag, 'theme-slider' );

$checks = [
	'hashed hero script remains critical'      => $heroResult === $heroTag,
	'noncritical section script stays delayed' => str_contains( $sliderResult, 'data-sp-accelerator-src=' ),
];

$failed = array_keys( array_filter( $checks, static fn ( bool $passed ): bool => ! $passed ) );
if ( $failed ) {
	fwrite( STDERR, 'Accelerator asset failures: ' . implode( ', ', $failed ) . PHP_EOL );
	exit( 1 );
}

echo 'Accelerator assets: ' . count( $checks ) . " checks passed.\n";
