<?php

/**
 * Focused regression checks for the base SP Admin UI assets.
 * Run directly with: php tests/admin-ui.php
 */

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'THEME_DIR', dirname( __DIR__ ) );
define( 'THEME_URI', 'https://example.test/theme' );

$GLOBALS['sp_admin_ui_hooks'] = [];
$GLOBALS['sp_admin_ui_scripts'] = [];

function add_action( string $hook, $callback, int $priority = 10 ): void {
	$GLOBALS['sp_admin_ui_hooks'][] = compact( 'hook', 'callback', 'priority' );
}

function apply_filters( string $hook, $value ) {
	return $value;
}

function trailingslashit( string $value ): string {
	return rtrim( $value, '/\\' ) . '/';
}

function wp_enqueue_script( string $handle, string $src, array $dependencies, string $version, bool $footer ): void {
	$GLOBALS['sp_admin_ui_scripts'][] = compact( 'handle', 'src', 'dependencies', 'version', 'footer' );
}

require dirname( __DIR__ ) . '/src/Bootstrapper.php';

$config = new ReflectionProperty( \SoinProduction\Kit\Bootstrapper::class, 'moduleConfigs' );
$config->setAccessible( true );
$config->setValue( null, [
	'plugins' => [
		'sp-admin-ui' => [],
	],
] );

require dirname( __DIR__ ) . '/plugins/sp-admin-ui/index.php';

$enqueue_hook = null;
foreach ( $GLOBALS['sp_admin_ui_hooks'] as $registered ) {
	if ( $registered['hook'] === 'admin_enqueue_scripts' ) {
		$enqueue_hook = $registered['callback'];
		break;
	}
}

if ( is_callable( $enqueue_hook ) ) {
	$enqueue_hook( 'edit.php' );
	$enqueue_hook( 'post.php' );
}

$script = $GLOBALS['sp_admin_ui_scripts'][0] ?? [];
$checks = [
	'Builder Widgets asset hook is registered'       => is_callable( $enqueue_hook ),
	'asset loads only once on the post editor'       => count( $GLOBALS['sp_admin_ui_scripts'] ) === 1,
	'asset has a stable WordPress handle'             => ( $script['handle'] ?? '' ) === 'sp-admin-ui-builder-widget-selection',
	'asset URL resolves through the theme vendor URL' => ( $script['src'] ?? '' ) === 'https://example.test/theme/plugins/sp-admin-ui/assets/builder-widget-selection.js',
	'asset waits for jQuery and loads in the footer'  => ( $script['dependencies'] ?? [] ) === [ 'jquery' ] && ! empty( $script['footer'] ),
];

$failed = array_keys( array_filter( $checks, static fn( bool $passed ): bool => ! $passed ) );
if ( $failed ) {
	fwrite( STDERR, 'Admin UI failures: ' . implode( ', ', $failed ) . PHP_EOL );
	exit( 1 );
}

echo 'Admin UI: ' . count( $checks ) . " checks passed.\n";
