<?php

/**
 * Focused regression checks for the shared development admin-bar actions.
 * Run directly with: php tests/admin-bar-tools.php
 */

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'DEV_MODE', true );
define( 'WP_PLUGIN_DIR', '/nonexistent-wordpress-plugins' );

$GLOBALS['sp_admin_bar_hooks'] = [];

function add_action( string $hook, $callback, int $priority = 10 ): void {
	$GLOBALS['sp_admin_bar_hooks'][] = compact( 'hook', 'callback', 'priority' );
}

function is_admin_bar_showing(): bool {
	return true;
}

function current_user_can( string $capability ): bool {
	return in_array( $capability, [ 'manage_options', 'install_plugins', 'activate_plugins' ], true );
}

function admin_url( string $path = '' ): string {
	return 'https://example.test/wp-admin/' . ltrim( $path, '/' );
}

function self_admin_url( string $path = '' ): string {
	return admin_url( $path );
}

function wp_nonce_url( string $url, string $action ): string {
	return $url . '&_wpnonce=' . rawurlencode( $action . '-nonce' );
}

function trailingslashit( string $path ): string {
	return rtrim( $path, '/\\' ) . '/';
}

function wp_unslash( $value ) {
	return $value;
}

class SP_Accelerator_Config {
	public bool $active = true;

	public function enabled( string $feature = 'enabled' ): bool {
		return $this->active;
	}
}

class SP_Accelerator_Cache {
	public function purge_all(): bool {
		return true;
	}
}

class SP_Admin_Bar_Test_Double {
	/** @var array<string, array<string, mixed>> */
	public array $nodes = [];

	/** @param array<string, mixed> $node */
	public function add_node( array $node ): void {
		$this->nodes[(string) $node['id']] = $node;
	}
}

require dirname( __DIR__ ) . '/plugins/sp-accelerator/includes/class-admin-bar.php';
require dirname( __DIR__ ) . '/plugins/sp-debug-toolbar/includes/admin-bar.php';

$config = new SP_Accelerator_Config();
$cache = new SP_Accelerator_Cache();
$toolbar = new SP_Accelerator_Admin_Bar( $config, $cache );
$toolbar->register();
$bar = new SP_Admin_Bar_Test_Double();
$toolbar->menu( $bar );
sp_debug_toolbar_query_monitor_link( $bar );

$config->active = false;
$inactiveBar = new SP_Admin_Bar_Test_Double();
$toolbar->menu( $inactiveBar );

$registeredHooks = array_column( $GLOBALS['sp_admin_bar_hooks'], 'hook' );
$checks = [
	'Accelerator registers the toolbar purge handler' => in_array( 'admin_post_sp_accelerator_toolbar_purge', $registeredHooks, true ),
	'active Accelerator adds a cache toolbar menu'    => isset( $bar->nodes['sp-accelerator-cache'] ),
	'cache purge action is nonce protected'           => str_contains( (string) ( $bar->nodes['sp-accelerator-cache-purge']['href'] ?? '' ), 'sp_accelerator_toolbar_purge-nonce' ),
	'inactive Accelerator stays out of the toolbar'   => $inactiveBar->nodes === [],
	'DEV_MODE offers official Query Monitor install'  => str_contains( (string) ( $bar->nodes['sp-query-monitor']['href'] ?? '' ), 'plugin=query-monitor' ),
];

$failed = array_keys( array_filter( $checks, static fn ( bool $passed ): bool => ! $passed ) );
if ( $failed ) {
	fwrite( STDERR, 'Admin bar tool failures: ' . implode( ', ', $failed ) . PHP_EOL );
	exit( 1 );
}

echo 'Admin bar tools: ' . count( $checks ) . " checks passed.\n";
