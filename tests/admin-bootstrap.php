<?php

/**
 * Focused regression checks for the shared admin JSON bootstrap.
 * Run directly with: php tests/admin-bootstrap.php
 */

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

function is_admin(): bool {
	return true;
}

function add_action( string $hook, $callback, int $priority = 10 ): void {
	$GLOBALS['sp_admin_bootstrap_hooks'][] = compact( 'hook', 'callback', 'priority' );
}

function rest_url(): string {
	return 'https://example.test/wp-json/';
}

function wp_create_nonce( string $action ): string {
	return $action . '-nonce';
}

function esc_url_raw( string $url ): string {
	return $url;
}

function get_current_screen(): object {
	return (object) [
		'id'        => 'page',
		'base'      => 'post',
		'post_type' => 'page',
		'taxonomy'  => '',
	];
}

function apply_filters( string $hook, $value ) {
	return $value;
}

function wp_json_encode( $value, int $flags = 0 ): string {
	return (string) json_encode( $value, $flags );
}

function esc_attr( string $value ): string {
	return htmlspecialchars( $value, ENT_QUOTES );
}

require dirname( __DIR__ ) . '/src/AdminBootstrap.php';

use SoinProduction\Kit\AdminBootstrap;

AdminBootstrap::set( 'editorWidgets', [
	'nonce' => 'widget-nonce',
	'html'  => '</script><script>alert(1)</script>',
] );
AdminBootstrap::set( 'invalid key', [ 'ignored' => true ] );
AdminBootstrap::exposeLegacyGlobal( 'SP_WIDGETS_NONCE', 'editorWidgets', 'nonce' );

ob_start();
AdminBootstrap::print();
$output = (string) ob_get_clean();

ob_start();
AdminBootstrap::print();
$second_output = (string) ob_get_clean();

$scheduledHooks = array_column( $GLOBALS['sp_admin_bootstrap_hooks'], 'priority', 'hook' );
$checks = [
	'head output is scheduled before head scripts'      => ( $scheduledHooks['admin_print_scripts'] ?? 0 ) === 19,
	'footer fallback precedes footer scripts'           => ( $scheduledHooks['admin_print_footer_scripts'] ?? 0 ) === 9,
	'payload contains the registered feature'          => str_contains( $output, 'editorWidgets' ),
	'invalid feature names are ignored'                => ! str_contains( $output, 'invalid key' ),
	'legacy global mapping is emitted'                 => str_contains( $output, 'SP_WIDGETS_NONCE' ),
	'ready event is dispatched for deferred consumers' => str_contains( $output, 'sp-admin-bootstrap-ready' ),
	'embedded closing script tags are hex escaped'     => ! str_contains( $output, '</script><script>alert(1)' ),
	'bootstrap is emitted once'                        => $second_output === '',
];

$failed = array_keys( array_filter( $checks, static fn( bool $passed ): bool => ! $passed ) );
if ( $failed ) {
	fwrite( STDERR, 'Admin bootstrap failures: ' . implode( ', ', $failed ) . PHP_EOL );
	exit( 1 );
}

echo 'Admin bootstrap: ' . count( $checks ) . " checks passed.\n";
