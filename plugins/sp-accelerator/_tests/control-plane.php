<?php

/**
 * Dependency-free regression checks for storage migration and warmer control.
 * Run directly with: php _tests/control-plane.php
 */

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

$sp_cp_script = __FILE__;
$sp_cp_root   = dirname( __DIR__ );
$sp_cp_mode   = isset( $argv[1] ) ? (string) $argv[1] : 'run';

$GLOBALS['sp_cp_options']   = [];
$GLOBALS['sp_cp_scheduled'] = [];
$GLOBALS['sp_cp_password']  = 0;

function get_option( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['sp_cp_options'] ) ? $GLOBALS['sp_cp_options'][ $key ] : $default;
}

function update_option( $key, $value, $autoload = null ) {
	$GLOBALS['sp_cp_options'][ $key ] = $value;
	return true;
}

function add_option( $key, $value, $deprecated = '', $autoload = 'yes' ) {
	if ( array_key_exists( $key, $GLOBALS['sp_cp_options'] ) ) {
		return false;
	}
	$GLOBALS['sp_cp_options'][ $key ] = $value;
	return true;
}

function delete_option( $key ) {
	unset( $GLOBALS['sp_cp_options'][ $key ] );
	return true;
}

function trailingslashit( $path ) { return rtrim( (string) $path, '/\\' ) . '/'; }
function wp_mkdir_p( $path ) { return is_dir( $path ) || ( ! file_exists( $path ) && @mkdir( $path, 0755, true ) ); }
function wp_json_encode( $value, $flags = 0, $depth = 512 ) { return json_encode( $value, $flags, $depth ); }
function home_url( $path = '' ) { return 'https://example.test' . $path; }
function site_url( $path = '' ) { return 'https://example.test' . $path; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function absint( $value ) { return abs( (int) $value ); }

function wp_generate_password( $length = 12, $special_chars = true, $extra_special_chars = false ) {
	$GLOBALS['sp_cp_password']++;
	return substr( str_repeat( 'token' . $GLOBALS['sp_cp_password'], $length ), 0, $length );
}

function sp_cp_schedule_key( $hook, $args = [] ) {
	return (string) $hook . ':' . hash( 'sha256', serialize( (array) $args ) );
}

function wp_next_scheduled( $hook, $args = [] ) {
	$key = sp_cp_schedule_key( $hook, $args );
	return isset( $GLOBALS['sp_cp_scheduled'][ $key ] ) ? $GLOBALS['sp_cp_scheduled'][ $key ] : false;
}

function wp_schedule_single_event( $timestamp, $hook, $args = [] ) {
	$GLOBALS['sp_cp_scheduled'][ sp_cp_schedule_key( $hook, $args ) ] = (int) $timestamp;
	return true;
}

function wp_clear_scheduled_hook( $hook, $args = [] ) {
	if ( func_num_args() > 1 ) {
		unset( $GLOBALS['sp_cp_scheduled'][ sp_cp_schedule_key( $hook, $args ) ] );
		return true;
	}
	foreach ( array_keys( $GLOBALS['sp_cp_scheduled'] ) as $key ) {
		if ( strpos( $key, (string) $hook . ':' ) === 0 ) {
			unset( $GLOBALS['sp_cp_scheduled'][ $key ] );
		}
	}
	return true;
}

function wp_remote_get( $url, $args = [] ) {
	$GLOBALS['sp_cp_remote_started'] = true;
	$GLOBALS['sp_cp_remote_args']    = $args;
	if ( isset( $GLOBALS['sp_cp_warmer'] ) && is_object( $GLOBALS['sp_cp_warmer'] ) ) {
		$GLOBALS['sp_cp_warmer']->cancel();
	}
	return [ 'response' => [ 'code' => 200 ], 'headers' => [ 'x-sp-cache' => 'MISS' ] ];
}

function wp_remote_retrieve_response_code( $response ) {
	return isset( $response['response']['code'] ) ? (int) $response['response']['code'] : 0;
}

function wp_remote_retrieve_header( $response, $name ) {
	return isset( $response['headers'][ $name ] ) ? $response['headers'][ $name ] : '';
}

function is_wp_error( $value ) { return $value instanceof WP_Error; }

if ( ! class_exists( 'WP_Error', false ) ) {
	class WP_Error {
		private $code;
		private $message;

		public function __construct( $code = '', $message = '' ) {
			$this->code    = (string) $code;
			$this->message = (string) $message;
		}

		public function get_error_message() { return $this->message; }
	}
}

function sp_cp_is_fixture( string $directory ): bool {
	$temporary = realpath( sys_get_temp_dir() );
	$fixture   = realpath( $directory );
	return $temporary !== false
		&& $fixture !== false
		&& strpos( $fixture, $temporary . DIRECTORY_SEPARATOR . 'sp-accelerator-control-test-' ) === 0;
}

function sp_cp_remove_tree( string $directory ): void {
	if ( ! sp_cp_is_fixture( $directory ) ) {
		return;
	}
	$items = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ( $items as $item ) {
		$item->isDir() && ! $item->isLink() ? @rmdir( $item->getPathname() ) : @unlink( $item->getPathname() );
	}
	@rmdir( $directory );
}

function sp_cp_finish( array $checks ): void {
	$failed = [];
	foreach ( $checks as $name => $passed ) {
		if ( ! $passed ) {
			$failed[] = (string) $name;
		}
	}
	echo json_encode( [ 'checks' => count( $checks ), 'failed' => $failed ], JSON_UNESCAPED_SLASHES ), PHP_EOL;
	exit( $failed ? 1 : 0 );
}

/** @return array{output:string,error:string,status:int} */
function sp_cp_run( array $command ): array {
	$pipes   = [];
	$process = @proc_open( $command, [ 0 => [ 'pipe', 'r' ], 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ], $pipes );
	if ( ! is_resource( $process ) ) {
		return [ 'output' => '', 'error' => 'proc_open failed', 'status' => 1 ];
	}
	fclose( $pipes[0] );
	$output = stream_get_contents( $pipes[1] );
	$error  = stream_get_contents( $pipes[2] );
	fclose( $pipes[1] );
	fclose( $pipes[2] );
	$status = proc_close( $process );
	return [
		'output' => is_string( $output ) ? $output : '',
		'error'  => is_string( $error ) ? $error : '',
		'status' => (int) $status,
	];
}

if ( $sp_cp_mode !== 'run' ) {
	$fixture = isset( $argv[2] ) ? (string) $argv[2] : '';
	if ( ! sp_cp_is_fixture( $fixture ) ) {
		fwrite( STDERR, "unsafe control-plane fixture\n" );
		exit( 2 );
	}
}

if ( $sp_cp_mode === 'storage' ) {
	@mkdir( $fixture . '/web/site', 0755, true );
	@mkdir( $fixture . '/web/wp-content/cache/sp-accelerator/pages/g1', 0755, true );
	define( 'ABSPATH', $fixture . '/web/site/' );
	define( 'WP_CONTENT_DIR', $fixture . '/web/wp-content' );
	define( 'WEEK_IN_SECONDS', 604800 );
	define( 'DAY_IN_SECONDS', 86400 );
	define( 'HOUR_IN_SECONDS', 3600 );
	$_SERVER['DOCUMENT_ROOT'] = $fixture . '/web';

	$cache_root = WP_CONTENT_DIR . '/cache/sp-accelerator';
	file_put_contents( $cache_root . '/config.json', json_encode( [
		'signature' => 'SP Accelerator cache config',
		'version'   => '2.0.0',
		'enabled'   => true,
	] ) );
	file_put_contents( $cache_root . '/pages/g1/page.html', 'unsafe page cache' );
	file_put_contents( $cache_root . '/object-cache.sqlite', 'object database' );
	require $sp_cp_root . '/includes/class-config.php';
	$config = new SP_Accelerator_Config();
	$checks = [];
	foreach ( [ 'nginx/1.25', 'Apache/2.4', 'LiteSpeed', '', 'unknown-proxy' ] as $software ) {
		$_SERVER['SERVER_SOFTWARE'] = $software;
		$checks[ 'unsafe storage rejected for SERVER_SOFTWARE=' . $software ] = $config->storage_is_safe_for_server() === false;
	}
	$checks['page cache remains fail-closed'] = $config->enabled( 'page_cache' ) === false;
	$checks['unsafe config synchronizes as disabled'] = $config->sync_dropin_config() === true;
	$stored = json_decode( (string) file_get_contents( $config->config_file() ), true );
	$checks['unsafe config file is disabled'] = is_array( $stored ) && isset( $stored['enabled'] ) && $stored['enabled'] === false;
	$checks['unsafe page files are removed'] = ! is_dir( $cache_root . '/pages' );
	$checks['unsafe cleanup preserves object cache database'] = file_get_contents( $cache_root . '/object-cache.sqlite' ) === 'object database';
	sp_cp_finish( $checks );
}

if ( $sp_cp_mode === 'migration' ) {
	@mkdir( $fixture . '/web/site', 0755, true );
	@mkdir( $fixture . '/web/wp-content/cache/sp-accelerator/pages/g1/nested', 0755, true );
	define( 'ABSPATH', $fixture . '/web/site/' );
	define( 'WP_CONTENT_DIR', $fixture . '/web/wp-content' );
	define( 'SP_ACCELERATOR_CACHE_DIR', $fixture . '/private-sp-accelerator' );
	define( 'SP_ACCELERATOR_DOCUMENT_ROOT', $fixture . '/web' );
	define( 'SP_ACCELERATOR_OBJECT_CACHE_WEB_PROTECTED', true );
	define( 'WEEK_IN_SECONDS', 604800 );
	define( 'DAY_IN_SECONDS', 86400 );
	define( 'HOUR_IN_SECONDS', 3600 );

	$legacy = WP_CONTENT_DIR . '/cache/sp-accelerator';
	file_put_contents( $legacy . '/config.json', json_encode( [
		'signature' => 'SP Accelerator cache config',
		'version'   => '2.0.0',
		'enabled'   => true,
	] ) );
	file_put_contents( $legacy . '/pages/g1/nested/page.html', 'legacy page' );
	$database_files = [
		'object-cache.sqlite'         => 'db',
		'object-cache.sqlite-wal'     => 'wal',
		'object-cache.sqlite-shm'     => 'shm',
		'object-cache.sqlite-journal' => 'journal',
	];
	foreach ( $database_files as $name => $contents ) {
		file_put_contents( $legacy . '/' . $name, $contents );
	}
	file_put_contents( SP_ACCELERATOR_CACHE_DIR, 'blocked target' );

	require $sp_cp_root . '/includes/class-config.php';
	$config = new SP_Accelerator_Config();
	$checks = [];
	$checks['migration refuses an unavailable new target'] = $config->sync_dropin_config() === false;
	$legacy_config = json_decode( (string) file_get_contents( $legacy . '/config.json' ), true );
	$checks['invalid target blocks migration before mutation'] = is_array( $legacy_config ) && ! empty( $legacy_config['enabled'] );
	$checks['invalid target leaves legacy page tree untouched'] = is_dir( $legacy . '/pages' );
	@unlink( SP_ACCELERATOR_CACHE_DIR );
	$checks['migration completes after target becomes writable'] = $config->sync_dropin_config() === true;
	$legacy_config = json_decode( (string) file_get_contents( $legacy . '/config.json' ), true );
	$checks['owned legacy config is disabled during migration'] = is_array( $legacy_config ) && isset( $legacy_config['enabled'] ) && $legacy_config['enabled'] === false;
	$checks['owned legacy page tree is cleaned during migration'] = ! is_dir( $legacy . '/pages' );
	$database_preserved = true;
	foreach ( $database_files as $name => $contents ) {
		$database_preserved = $database_preserved && is_file( $legacy . '/' . $name ) && file_get_contents( $legacy . '/' . $name ) === $contents;
	}
	$checks['explicitly protected legacy SQLite and sidecars are preserved'] = $database_preserved;
	$current = json_decode( (string) file_get_contents( $config->config_file() ), true );
	$checks['new safe root receives enabled config'] = is_array( $current ) && ! empty( $current['enabled'] );
	sp_cp_finish( $checks );
}

if ( $sp_cp_mode === 'migration-unsafe-sqlite' ) {
	@mkdir( $fixture . '/web/site', 0755, true );
	@mkdir( $fixture . '/web/wp-content/cache/sp-accelerator/pages/g1', 0755, true );
	define( 'ABSPATH', $fixture . '/web/site/' );
	define( 'WP_CONTENT_DIR', $fixture . '/web/wp-content' );
	define( 'SP_ACCELERATOR_CACHE_DIR', $fixture . '/safe-sp-accelerator' );
	define( 'SP_ACCELERATOR_DOCUMENT_ROOT', $fixture . '/web' );
	define( 'WEEK_IN_SECONDS', 604800 );
	define( 'DAY_IN_SECONDS', 86400 );
	define( 'HOUR_IN_SECONDS', 3600 );

	$legacy = WP_CONTENT_DIR . '/cache/sp-accelerator';
	file_put_contents( $legacy . '/config.json', json_encode( [
		'signature' => 'SP Accelerator cache config',
		'version'   => '2.0.0',
		'enabled'   => true,
	] ) );
	file_put_contents( $legacy . '/pages/g1/page.html', 'legacy page' );
	$database_files = [
		'object-cache.sqlite',
		'object-cache.sqlite-wal',
		'object-cache.sqlite-shm',
		'object-cache.sqlite-journal',
	];
	foreach ( $database_files as $name ) {
		file_put_contents( $legacy . '/' . $name, $name );
	}

	require $sp_cp_root . '/includes/class-config.php';
	$config = new SP_Accelerator_Config();
	$checks = [];
	$checks['owned legacy migration completes'] = $config->sync_dropin_config() === true;
	$checks['unsafe legacy page tree is removed'] = ! is_dir( $legacy . '/pages' );
	$all_removed = true;
	foreach ( $database_files as $name ) {
		$all_removed = $all_removed && ! is_file( $legacy . '/' . $name );
	}
	$checks['confirmed exposed unprotected SQLite and sidecars are removed'] = $all_removed;
	sp_cp_finish( $checks );
}

if ( $sp_cp_mode === 'runtime-disable' ) {
	@mkdir( $fixture . '/public/wp-content/cache/sp-accelerator', 0755, true );
	define( 'ABSPATH', $fixture . '/public/' );
	define( 'WP_CONTENT_DIR', $fixture . '/public/wp-content' );
	define( 'SP_ACCELERATOR_CACHE_WEB_PROTECTED', true );
	define( 'WEEK_IN_SECONDS', 604800 );
	define( 'DAY_IN_SECONDS', 86400 );
	define( 'HOUR_IN_SECONDS', 3600 );

	require $sp_cp_root . '/includes/class-config.php';
	$config = new SP_Accelerator_Config();
	$checks = [];
	$checks['runtime is fail-closed before signed config exists'] = $config->enabled( 'page_cache' ) === false;
	$checks['initial signed config synchronizes'] = $config->sync_dropin_config() === true;
	$checks['runtime activates after signed config exists'] = $config->enabled( 'page_cache' ) === true;
	$generation = (string) $config->get( 'generation', '1' );
	$config->mark_runtime_disabled();
	$checks['persistent theme-switch marker disables page cache'] = $config->enabled( 'page_cache' ) === false;
	$checks['disabled runtime cannot bump generation'] = $config->bump_generation() === $generation;
	$checks['disabled config is synchronized'] = $config->disable_dropin_config() === true;
	$stored = json_decode( (string) file_get_contents( $config->config_file() ), true );
	$checks['early drop-in receives enabled=false'] = is_array( $stored ) && isset( $stored['enabled'] ) && $stored['enabled'] === false;
	$config->activate_runtime();
	$checks['returning to the theme clears the marker'] = $config->enabled( 'page_cache' ) === true;
	$checks['active config can be synchronized again'] = $config->sync_dropin_config() === true;
	sp_cp_finish( $checks );
}

if ( $sp_cp_mode === 'warmer' ) {
	define( 'ABSPATH', $fixture . '/' );
	if ( ! class_exists( 'SP_Accelerator_Config', false ) ) {
		class SP_Accelerator_Config {
			public const VERSION = '2.0.0';
			public function enabled( string $key = 'enabled' ): bool { return true; }
			public function get( string $key, $default = null ) { return $key === 'generation' ? 'g1' : $default; }
			public function warm_request_token( string $url ): string { return 'control-plane-signed:' . hash( 'sha256', $url ); }
		}
	}
	if ( ! class_exists( 'SP_Accelerator_Cache', false ) ) {
		class SP_Accelerator_Cache {}
	}

	require $sp_cp_root . '/includes/class-warmer.php';
	$state_key   = 'sp_accelerator_warm_state';
	$lock_key    = 'sp_accelerator_warm_lock';
	$restart_key = 'sp_accelerator_warm_restart_generation';
	$cancel_key  = 'sp_accelerator_warm_cancel_epoch';
	$cron_hook   = 'sp_accelerator_warm_batch';
	$old_epoch   = 'epoch-before-cancel';
	$GLOBALS['sp_cp_options'][ $cancel_key ] = $old_epoch;
	$GLOBALS['sp_cp_options'][ $state_key ] = [
		'status'      => 'running',
		'total'       => 1,
		'done'        => 0,
		'failed'      => 0,
		'generation'  => 'g1',
		'urls'        => [ 'https://example.test/' ],
		'queue'       => [ 'https://example.test/' ],
		'failed_urls' => [],
		'started_at'  => time(),
		'finished_at' => 0,
	];
	$GLOBALS['sp_cp_options'][ $restart_key ] = [
		'generation' => 'g1',
		'epoch'      => $old_epoch,
		'token'      => 'restart-token',
	];
	$old_schedule_key = sp_cp_schedule_key( $cron_hook, [ $old_epoch ] );
	$GLOBALS['sp_cp_scheduled'][ $old_schedule_key ] = time() + 5;
	$warmer = new SP_Accelerator_Warmer( new SP_Accelerator_Cache(), new SP_Accelerator_Config() );
	$GLOBALS['sp_cp_warmer'] = $warmer;
	$warmer->process_batch( 1 );

	$checks = [];
	$checks['worker reached remote request'] = ! empty( $GLOBALS['sp_cp_remote_started'] );
	$checks['cancel rotates epoch'] = isset( $GLOBALS['sp_cp_options'][ $cancel_key ] ) && $GLOBALS['sp_cp_options'][ $cancel_key ] !== $old_epoch;
	$checks['cancelled worker does not resurrect state'] = ! array_key_exists( $state_key, $GLOBALS['sp_cp_options'] );
	$checks['cancelled worker does not resurrect restart'] = ! array_key_exists( $restart_key, $GLOBALS['sp_cp_options'] );
	$checks['cancelled worker does not resurrect cron'] = empty( $GLOBALS['sp_cp_scheduled'] );
	$checks['cancelled worker releases its lock'] = ! array_key_exists( $lock_key, $GLOBALS['sp_cp_options'] );
	$checks['warmer forbids redirects'] = isset( $GLOBALS['sp_cp_remote_args']['redirection'] ) && $GLOBALS['sp_cp_remote_args']['redirection'] === 0;
	$checks['warmer sends a URL-bound token'] = (string) ( $GLOBALS['sp_cp_remote_args']['headers']['X-SP-Cache-Warm'] ?? '' ) === 'control-plane-signed:' . hash( 'sha256', 'https://example.test/' );
	$schedule = new ReflectionMethod( SP_Accelerator_Warmer::class, 'schedule_next_batch' );
	$schedule->setAccessible( true );
	$schedule->invoke( $warmer, $old_epoch );
	$checks['stale epoch cannot schedule a later cron'] = empty( $GLOBALS['sp_cp_scheduled'] );
	sp_cp_finish( $checks );
}

try {
	$sp_cp_suffix = bin2hex( random_bytes( 8 ) );
} catch ( Throwable $error ) {
	$sp_cp_suffix = str_replace( '.', '-', uniqid( '', true ) );
}
$sp_cp_fixture = sys_get_temp_dir() . '/sp-accelerator-control-test-' . $sp_cp_suffix;
if ( ! @mkdir( $sp_cp_fixture, 0700, true ) || ! sp_cp_is_fixture( $sp_cp_fixture ) ) {
	fwrite( STDERR, "could not create control-plane fixture\n" );
	exit( 1 );
}
register_shutdown_function( 'sp_cp_remove_tree', $sp_cp_fixture );

$failures = [];
$checks   = 0;
foreach ( [ 'storage', 'migration', 'migration-unsafe-sqlite', 'runtime-disable', 'warmer' ] as $case ) {
	$case_root = $sp_cp_fixture . '/' . $case;
	@mkdir( $case_root, 0700, true );
	$result  = sp_cp_run( [ PHP_BINARY, $sp_cp_script, $case, $case_root ] );
	$payload = json_decode( trim( $result['output'] ), true );
	if ( ! is_array( $payload ) || ! isset( $payload['checks'], $payload['failed'] ) || ! is_array( $payload['failed'] ) ) {
		$checks++;
		$failures[] = $case . ' returned invalid output: ' . trim( $result['error'] . ' ' . $result['output'] );
		continue;
	}
	$checks += max( 1, (int) $payload['checks'] );
	foreach ( $payload['failed'] as $failed ) {
		$failures[] = $case . ': ' . (string) $failed;
	}
	if ( $result['status'] !== 0 && empty( $payload['failed'] ) ) {
		$failures[] = $case . ' exited with status ' . $result['status'] . ': ' . trim( $result['error'] );
	}
}

sp_cp_remove_tree( $sp_cp_fixture );
if ( $failures ) {
	fwrite( STDERR, "SP Accelerator control-plane tests failed:\n- " . implode( "\n- ", $failures ) . "\n" );
	exit( 1 );
}

echo 'SP Accelerator control plane: ' . $checks . " checks passed.\n";
