<?php

/**
 * Regression checks for automatic WP_CACHE marker management.
 * Run directly with: php tests/dropin-config.php
 */

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

$fixture = sys_get_temp_dir() . '/sp-accelerator-test-wp-config-' . bin2hex( random_bytes( 5 ) );
@mkdir( $fixture . '/public/wp-content', 0700, true );
define( 'ABSPATH', $fixture . '/public/' );
define( 'WP_CONTENT_DIR', $fixture . '/public/wp-content' );

function trailingslashit( $path ) { return rtrim( (string) $path, '/\\' ) . '/'; }
function is_multisite() { return false; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }

final class WP_Error {
	private $message;
	public function __construct( $code = '', $message = '' ) { $this->message = (string) $message; }
	public function get_error_message() { return $this->message; }
}

final class SP_Accelerator_Config {
	public $atomic_writes_enabled = true;
	public function has_legacy_accelerator_conflict(): bool { return false; }
	public function storage_is_safe_for_server(): bool { return true; }
	public function storage_safety_message(): string { return ''; }
	public function sync_dropin_config(): bool { return true; }
	public function atomic_write( string $path, string $contents ): bool {
		if ( ! $this->atomic_writes_enabled ) { return false; }
		$temp = tempnam( dirname( $path ), '.sp-test-' );
		return is_string( $temp )
			&& file_put_contents( $temp, $contents ) !== false
			&& rename( $temp, $path );
	}
}

require dirname( __DIR__ ) . '/includes/class-dropin.php';

$wp_config = ABSPATH . 'wp-config.php';
$original  = "<?php\n// foreign configuration\n\$foreign = true;\nrequire_once ABSPATH . 'wp-settings.php';\n";
file_put_contents( $wp_config, $original );

$config = new SP_Accelerator_Config();
$dropin = new SP_Accelerator_Dropin( $config, dirname( __DIR__ ) );
$checks = [];
$checks['WP_CACHE marker is inserted automatically'] = $dropin->ensure_wp_cache_enabled() === true;
$configured = (string) file_get_contents( $wp_config );
$checks['WP_CACHE marker appears before WordPress bootstrap'] = strpos( $configured, 'BEGIN SP Accelerator WP_CACHE' ) !== false
	&& strpos( $configured, 'BEGIN SP Accelerator WP_CACHE' ) < strpos( $configured, "require_once ABSPATH . 'wp-settings.php';" );
$checks['foreign wp-config bytes are preserved'] = strpos( $configured, "// foreign configuration\n\$foreign = true;" ) !== false;
$checks['WP_CACHE marker maintenance is idempotent'] = $dropin->ensure_wp_cache_enabled() === true
	&& substr_count( (string) file_get_contents( $wp_config ), 'BEGIN SP Accelerator WP_CACHE' ) === 1;

copy( dirname( __DIR__ ) . '/templates/advanced-cache.php', $dropin->path() );
$checks['managed drop-in is removable'] = $dropin->remove() === true && ! is_file( $dropin->path() );
$checks['removing managed drop-in removes owned WP_CACHE marker'] = strpos( (string) file_get_contents( $wp_config ), 'SP Accelerator WP_CACHE' ) === false;
$checks['removal still preserves foreign wp-config bytes'] = strpos( (string) file_get_contents( $wp_config ), "// foreign configuration\n\$foreign = true;" ) !== false;

file_put_contents( $wp_config, "<?php\nrequire_once( ABSPATH . '/wp-settings.php' );\n" );
$config->atomic_writes_enabled = false;
$checks['writable wp-config falls back when atomic rename is unavailable'] = $dropin->ensure_wp_cache_enabled() === true
	&& strpos( (string) file_get_contents( $wp_config ), 'BEGIN SP Accelerator WP_CACHE' ) !== false;
$config->atomic_writes_enabled = true;

$broken = "<?php\n/* BEGIN SP Accelerator WP_CACHE */\nrequire_once ABSPATH . 'wp-settings.php';\n";
file_put_contents( $wp_config, $broken );
$result = $dropin->ensure_wp_cache_enabled();
$checks['partial marker fails closed'] = is_wp_error( $result ) && file_get_contents( $wp_config ) === $broken;

@unlink( $wp_config );
@rmdir( WP_CONTENT_DIR );
@rmdir( ABSPATH );
@rmdir( $fixture );

$failed = array_keys( array_filter( $checks, static function ( bool $passed ): bool {
	return ! $passed;
} ) );
if ( $failed ) {
	fwrite( STDERR, 'SP Accelerator wp-config failures: ' . implode( ', ', $failed ) . PHP_EOL );
	exit( 1 );
}

echo 'SP Accelerator wp-config: ' . count( $checks ) . " checks passed.\n";
