<?php

/**
 * Focused regression checks for owned .htaccess marker handling.
 * Run directly with: php _tests/server.php
 */

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

$fixture = sys_get_temp_dir() . '/sp-accelerator-test-server-' . bin2hex( random_bytes( 5 ) );
if ( ! @mkdir( $fixture, 0700, true ) ) {
	exit( 1 );
}
define( 'ABSPATH', $fixture . '/' );
$GLOBALS['sp_server_fixture'] = $fixture;
$GLOBALS['sp_insert_called']  = false;

function trailingslashit( $path ) { return rtrim( (string) $path, '/\\' ) . '/'; }
function get_home_path() { return trailingslashit( $GLOBALS['sp_server_fixture'] ); }
function is_multisite() { return false; }
function is_super_admin() { return true; }
function insert_with_markers( $filename, $marker, $lines ) {
	$GLOBALS['sp_insert_called'] = true;
	return false;
}

final class WP_Error {
	/** @var string */
	private $message;
	public function __construct( $code = '', $message = '' ) { $this->message = (string) $message; }
	public function get_error_message() { return $this->message; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }

require dirname( __DIR__ ) . '/includes/class-server.php';

$_SERVER['SERVER_SOFTWARE'] = 'Apache/2.4';
$server = new SP_Accelerator_Server();
$path   = $server->path();
$checks = [];

$foreign_before = "# foreign before\nRewriteEngine On\n";
$foreign_after  = "# foreign after\nHeader set X-Test yes\n";
$owned_block    = "# BEGIN SP Accelerator\n# old owned policy\n# END SP Accelerator\n";
file_put_contents( $path, $foreign_before . $owned_block . $foreign_after );
$checks['owned outdated marker is recognized'] = $server->status()['code'] === 'outdated';
$checks['owned marker removal succeeds'] = $server->remove() === true;
$checks['removal preserves all foreign bytes'] = file_get_contents( $path ) === $foreign_before . $foreign_after;
$checks['removal deletes both marker lines'] = strpos( (string) file_get_contents( $path ), 'SP Accelerator' ) === false;

$duplicate = $foreign_before . $owned_block . $owned_block . $foreign_after;
file_put_contents( $path, $duplicate );
$GLOBALS['sp_insert_called'] = false;
$checks['duplicate boundaries are broken'] = $server->status()['code'] === 'broken';
$checks['install refuses ambiguous boundaries'] = is_wp_error( $server->install() ) && $GLOBALS['sp_insert_called'] === false;
$checks['refused install leaves foreign file untouched'] = file_get_contents( $path ) === $duplicate;

$partial = $foreign_before . "# BEGIN SP Accelerator\n# truncated\n" . $foreign_after;
file_put_contents( $path, $partial );
$checks['partial marker is broken'] = $server->status()['code'] === 'broken';
$checks['remove refuses a partial marker'] = is_wp_error( $server->remove() );
$checks['refused remove leaves partial file untouched'] = file_get_contents( $path ) === $partial;

@unlink( $path );
@rmdir( $fixture );

$failed = array_keys( array_filter( $checks, static function ( bool $passed ): bool {
	return ! $passed;
} ) );
if ( $failed ) {
	fwrite( STDERR, 'SP Accelerator server failures: ' . implode( ', ', $failed ) . PHP_EOL );
	exit( 1 );
}

echo 'SP Accelerator server: ' . count( $checks ) . " checks passed.\n";
