<?php

/**
 * Dependency-free regression checks for the SQLite object-cache drop-in.
 * Run directly with: php tests/object-cache.php
 */

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

$sp_oc_test_script = __FILE__;
$sp_oc_test_dropin = dirname( __DIR__ ) . '/templates/object-cache.php';

function sp_oc_test_is_fixture( string $directory ): bool {
	$temporary = realpath( sys_get_temp_dir() );
	$fixture   = realpath( $directory );
	if ( $temporary === false || $fixture === false ) {
		return false;
	}

	return strpos( $fixture, $temporary . DIRECTORY_SEPARATOR . 'sp-accelerator-object-cache-test-' ) === 0;
}

function sp_oc_test_remove_tree( string $directory ): void {
	if ( ! sp_oc_test_is_fixture( $directory ) ) {
		return;
	}

	$items = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ( $items as $item ) {
		if ( $item->isDir() && ! $item->isLink() ) {
			@rmdir( $item->getPathname() );
		} else {
			@unlink( $item->getPathname() );
		}
	}
	@rmdir( $directory );
}

/** @return array{process:resource,stdout:resource,stderr:resource}|null */
function sp_oc_test_start_process( array $command ) {
	$pipes   = [];
	$process = @proc_open(
		$command,
		[ 0 => [ 'pipe', 'r' ], 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ],
		$pipes
	);
	if ( ! is_resource( $process ) ) {
		return null;
	}

	fclose( $pipes[0] );
	return [
		'process' => $process,
		'stdout'  => $pipes[1],
		'stderr'  => $pipes[2],
	];
}

/** @return array{output:string,error:string,status:int} */
function sp_oc_test_finish_process( array $running ): array {
	$output = stream_get_contents( $running['stdout'] );
	$error  = stream_get_contents( $running['stderr'] );
	fclose( $running['stdout'] );
	fclose( $running['stderr'] );
	$status = proc_close( $running['process'] );

	return [
		'output' => is_string( $output ) ? $output : '',
		'error'  => is_string( $error ) ? $error : '',
		'status' => (int) $status,
	];
}

/** @return array{output:string,error:string,status:int} */
function sp_oc_test_run_process( array $command ): array {
	$running = sp_oc_test_start_process( $command );
	return $running === null
		? [ 'output' => '', 'error' => 'proc_open failed', 'status' => 1 ]
		: sp_oc_test_finish_process( $running );
}

/** @return array<string,mixed>|null */
function sp_oc_test_payload( array $result ) {
	$payload = json_decode( trim( $result['output'] ), true );
	return is_array( $payload ) ? $payload : null;
}

function sp_oc_test_assert( bool $condition, string $message, array &$failures, int &$checks ): void {
	$checks++;
	if ( ! $condition ) {
		$failures[] = $message;
	}
}

function sp_oc_test_merge_child( string $label, array $result, array &$failures, int &$checks ): void {
	$payload = sp_oc_test_payload( $result );
	if ( ! is_array( $payload ) || ! isset( $payload['checks'], $payload['failed'] ) || ! is_array( $payload['failed'] ) ) {
		sp_oc_test_assert( false, $label . ' returned invalid output: ' . trim( $result['error'] . ' ' . $result['output'] ), $failures, $checks );
		return;
	}

	$checks += max( 1, (int) $payload['checks'] );
	foreach ( $payload['failed'] as $failed ) {
		$failures[] = $label . ': ' . (string) $failed;
	}
	if ( $result['status'] !== 0 && empty( $payload['failed'] ) ) {
		$failures[] = $label . ' exited with status ' . $result['status'] . ': ' . trim( $result['error'] );
	}
}

function sp_oc_test_record( array &$checks, string $name, bool $condition ): void {
	$checks[ $name ] = $condition;
}

function sp_oc_test_emit_checks( array $checks ): void {
	$failed = [];
	foreach ( $checks as $name => $passed ) {
		if ( ! $passed ) {
			$failed[] = (string) $name;
		}
	}

	echo json_encode( [ 'checks' => count( $checks ), 'failed' => $failed ], JSON_UNESCAPED_SLASHES ), PHP_EOL;
	exit( $failed ? 1 : 0 );
}

if ( ! function_exists( 'wp_suspend_cache_addition' ) ) {
	function wp_suspend_cache_addition() {
		return ! empty( $GLOBALS['sp_oc_test_suspend_addition'] );
	}
}

function sp_oc_test_boot( string $root, string $salt, string $dropin ): void {
	$GLOBALS['blog_id'] = 1;
	define( 'ABSPATH', $root . '/public/' );
	define( 'WP_CONTENT_DIR', $root . '/wp-content' );
	define( 'WP_CACHE_KEY_SALT', $salt );
	define( 'SP_ACCELERATOR_CACHE_DIR', $root . '/wp-content/cache/sp-accelerator' );
	// The fixture is a CLI-only temporary directory with no web route. Production
	// deployments must make this assertion only after checking the real path/deny.
	define( 'SP_ACCELERATOR_OBJECT_CACHE_WEB_PROTECTED', true );
	require $dropin;
	wp_cache_init();
}

function sp_oc_test_boot_without_persistence( string $root, string $salt, string $dropin ): void {
	$GLOBALS['blog_id'] = 1;
	$GLOBALS['sp_oc_test_using_ext_object_cache'] = true;
	define( 'ABSPATH', $root . '/public/' );
	define( 'WP_CONTENT_DIR', $root . '/wp-content' );
	define( 'WP_CACHE_KEY_SALT', $salt );
	// A system temporary root is deliberately rejected by the drop-in's safety
	// checks, which provides a deterministic non-persistent backend fixture.
	define( 'SP_ACCELERATOR_CACHE_DIR', sys_get_temp_dir() );
	require $dropin;
	wp_cache_init();
}

if ( ! function_exists( 'wp_using_ext_object_cache' ) ) {
	function wp_using_ext_object_cache( $using = null ) {
		$current = $GLOBALS['sp_oc_test_using_ext_object_cache'] ?? false;
		if ( $using !== null ) {
			$GLOBALS['sp_oc_test_using_ext_object_cache'] = (bool) $using;
		}
		return $current;
	}
}

/** @return string|null */
function sp_oc_test_file_mode( string $path ) {
	clearstatcache( true, $path );
	$permissions = @fileperms( $path );
	return $permissions === false ? null : substr( sprintf( '%o', $permissions ), -4 );
}

/** @return int|null */
function sp_oc_test_expiry( string $root, string $salt, string $key, string $group ) {
	$path = $root . '/wp-content/cache/sp-accelerator/object-cache.sqlite';
	try {
		$db   = new SQLite3( $path, SQLITE3_OPEN_READONLY );
		$stmt = @$db->prepare( 'SELECT expires FROM sp_cache WHERE scope=:scope AND group_name=:group_name AND item_key=:item_key LIMIT 1' );
		if ( ! $stmt ) {
			$db->close();
			return null;
		}
		$stmt->bindValue( ':scope', 'sp:' . hash( 'sha256', 'salt:' . $salt ) . ':blog:1', SQLITE3_TEXT );
		$stmt->bindValue( ':group_name', $group, SQLITE3_TEXT );
		$stmt->bindValue( ':item_key', hash( 'sha256', $key ), SQLITE3_TEXT );
		$result = @$stmt->execute();
		$row    = $result instanceof SQLite3Result ? $result->fetchArray( SQLITE3_NUM ) : false;
		if ( $result instanceof SQLite3Result ) {
			$result->finalize();
		}
		$stmt->close();
		$db->close();
		return is_array( $row ) && isset( $row[0] ) ? (int) $row[0] : null;
	} catch ( Throwable $error ) {
		return null;
	}
}

function sp_oc_test_contract( string $root, string $salt, string $dropin ): void {
	sp_oc_test_boot( $root, $salt, $dropin );
	$checks = [];

	sp_oc_test_record( $checks, 'persistent backend initialized', method_exists( $GLOBALS['wp_object_cache'], 'is_persistent' ) && $GLOBALS['wp_object_cache']->is_persistent() );
	sp_oc_test_record( $checks, 'invalid keys rejected', wp_cache_set( '', 'invalid', 'keys', 60 ) === false && wp_cache_set( false, 'invalid', 'keys', 60 ) === false );
	sp_oc_test_record( $checks, 'integer key stored', wp_cache_set( 1, 'one', 'keys', 60 ) );
	sp_oc_test_record( $checks, 'integer and canonical string keys match', wp_cache_get( '1', 'keys' ) === 'one' );
	sp_oc_test_record( $checks, 'leading-zero string key remains distinct', wp_cache_set( '01', 'zero-one', 'keys', 60 ) && wp_cache_get( 1, 'keys' ) === 'one' && wp_cache_get( '01', 'keys' ) === 'zero-one' );

	$GLOBALS['sp_oc_test_suspend_addition'] = true;
	$blocked = wp_cache_add( 'blocked', 'value', 'contract', 60 ) === false;
	$GLOBALS['sp_oc_test_suspend_addition'] = false;
	$found = null;
	wp_cache_get( 'blocked', 'contract', false, $found );
	sp_oc_test_record( $checks, 'suspended addition writes nothing', $blocked && $found === false );

	sp_oc_test_record( $checks, 'persistent value stored', wp_cache_set( 'persistent', [ 'value' => 42 ], 'contract', 60 ) );
	wp_cache_close();
	wp_cache_init();
	$found = null;
	$value = wp_cache_get( 'persistent', 'contract', false, $found );
	sp_oc_test_record( $checks, 'value survives cache reinitialization', $found === true && is_array( $value ) && isset( $value['value'] ) && $value['value'] === 42 );
	sp_oc_test_record( $checks, 'add rejects a live key', wp_cache_add( 'persistent', 'wrong', 'contract', 60 ) === false );
	sp_oc_test_record( $checks, 'replace updates a live key', wp_cache_replace( 'persistent', 43, 'contract', 60 ) && wp_cache_get( 'persistent', 'contract' ) === 43 );

	sp_oc_test_record( $checks, 'false value stored', wp_cache_set( 'false-value', false, 'contract', 60 ) );
	$found = null;
	$value = wp_cache_get( 'false-value', 'contract', false, $found );
	sp_oc_test_record( $checks, 'false value retains found semantics', $value === false && $found === true );
	sp_oc_test_record(
		$checks,
		'multiple operations work',
		wp_cache_set_multiple( [ 'a' => 1, 'b' => 2 ], 'multiple', 60 ) === [ 'a' => true, 'b' => true ]
			&& wp_cache_get_multiple( [ 'a', 'b' ], 'multiple' ) === [ 'a' => 1, 'b' => 2 ]
	);

	wp_cache_add_global_groups( 'global-contract' );
	sp_oc_test_record( $checks, 'global value stored', wp_cache_set( 'shared', 'network', 'global-contract', 60 ) );
	wp_cache_switch_to_blog( 2 );
	wp_cache_flush_runtime();
	sp_oc_test_record( $checks, 'global group crosses blog scopes', wp_cache_get( 'shared', 'global-contract' ) === 'network' );

	wp_cache_switch_to_blog( 1 );
	wp_cache_set( 'scoped', 'blog-one', 'scoped-group', 60 );
	wp_cache_switch_to_blog( 2 );
	wp_cache_set( 'scoped', 'blog-two', 'scoped-group', 60 );
	$flushed = wp_cache_flush_group( 'scoped-group' );
	$found   = null;
	wp_cache_get( 'scoped', 'scoped-group', true, $found );
	$current_missing = $found === false;
	wp_cache_switch_to_blog( 1 );
	wp_cache_flush_runtime();
	sp_oc_test_record( $checks, 'group flush stays in current blog scope', $flushed && $current_missing && wp_cache_get( 'scoped', 'scoped-group' ) === 'blog-one' );

	wp_cache_add_non_persistent_groups( 'volatile' );
	wp_cache_set( 'request-only', 'yes', 'volatile', 60 );
	wp_cache_close();
	wp_cache_init();
	wp_cache_add_non_persistent_groups( 'volatile' );
	$found = null;
	wp_cache_get( 'request-only', 'volatile', false, $found );
	sp_oc_test_record( $checks, 'non-persistent group stays request-local', $found === false );

	$cache = $root . '/wp-content/cache/sp-accelerator';
	sp_oc_test_record( $checks, 'storage protection files exist', is_file( $cache . '/.htaccess' ) && is_file( $cache . '/web.config' ) && is_file( $cache . '/index.php' ) );
	if ( DIRECTORY_SEPARATOR === '/' ) {
		sp_oc_test_record( $checks, 'storage permissions hardened', sp_oc_test_file_mode( $cache ) === '0700' && sp_oc_test_file_mode( $cache . '/object-cache.sqlite' ) === '0600' );
	}

	wp_cache_close();
	sp_oc_test_emit_checks( $checks );
}

function sp_oc_test_ttl( string $root, string $salt, string $dropin ): void {
	sp_oc_test_boot( $root, $salt, $dropin );
	$checks = [];

	sp_oc_test_record( $checks, 'short-lived value stored', wp_cache_set( 'short', 'old', 'ttl', 1 ) );
	sleep( 2 );
	$found = null;
	$value = wp_cache_get( 'short', 'ttl', true, $found );
	sp_oc_test_record( $checks, 'expired value misses', $value === false && $found === false );
	sp_oc_test_record( $checks, 'add replaces expired row', wp_cache_add( 'short', 'fresh', 'ttl', 60 ) && wp_cache_get( 'short', 'ttl' ) === 'fresh' );

	sp_oc_test_record( $checks, 'counter with TTL stored', wp_cache_set( 'ttl-counter', 1, 'ttl', 60 ) );
	$before = sp_oc_test_expiry( $root, $salt, 'ttl-counter', 'ttl' );
	sp_oc_test_record( $checks, 'counter increments', wp_cache_incr( 'ttl-counter', 2, 'ttl' ) === 3 );
	$after = sp_oc_test_expiry( $root, $salt, 'ttl-counter', 'ttl' );
	sp_oc_test_record( $checks, 'increment preserves absolute TTL', $before !== null && $before > time() && $after === $before );

	wp_cache_close();
	sp_oc_test_emit_checks( $checks );
}

function sp_oc_test_non_persistent_fallback( string $root, string $salt, string $dropin ): void {
	sp_oc_test_boot_without_persistence( $root, $salt, $dropin );
	$checks = [];

	sp_oc_test_record( $checks, 'persistent backend remains disabled', ! $GLOBALS['wp_object_cache']->is_persistent() );
	sp_oc_test_record( $checks, 'WordPress external cache flag is disabled', wp_using_ext_object_cache() === false );
	sp_oc_test_record( $checks, 'request-local cache remains available', wp_cache_set( 'local', 'value', 'fallback', 60 ) && wp_cache_get( 'local', 'fallback' ) === 'value' );

	wp_cache_close();
	sp_oc_test_emit_checks( $checks );
}

function sp_oc_test_write_value( string $root, string $salt, string $dropin, string $value ): void {
	sp_oc_test_boot( $root, $salt, $dropin );
	$ok = method_exists( $GLOBALS['wp_object_cache'], 'is_persistent' )
		&& $GLOBALS['wp_object_cache']->is_persistent()
		&& wp_cache_set( 'isolation', $value, 'salt', 300 );
	wp_cache_close();
	echo json_encode( [ 'ok' => $ok ] ), PHP_EOL;
	exit( $ok ? 0 : 1 );
}

function sp_oc_test_read_value( string $root, string $salt, string $dropin ): void {
	sp_oc_test_boot( $root, $salt, $dropin );
	$found = null;
	$value = wp_cache_get( 'isolation', 'salt', false, $found );
	wp_cache_close();
	echo json_encode( [ 'found' => $found === true, 'value' => $value ] ), PHP_EOL;
	exit( 0 );
}

function sp_oc_test_flush( string $root, string $salt, string $dropin ): void {
	sp_oc_test_boot( $root, $salt, $dropin );
	$ok = wp_cache_flush();
	wp_cache_close();
	echo json_encode( [ 'ok' => $ok ] ), PHP_EOL;
	exit( $ok ? 0 : 1 );
}

function sp_oc_test_counter_init( string $root, string $salt, string $dropin ): void {
	sp_oc_test_boot( $root, $salt, $dropin );
	$ok = method_exists( $GLOBALS['wp_object_cache'], 'is_persistent' )
		&& $GLOBALS['wp_object_cache']->is_persistent()
		&& wp_cache_set( 'counter', 0, 'concurrency', 300 );
	wp_cache_close();
	echo json_encode( [ 'ok' => $ok ] ), PHP_EOL;
	exit( $ok ? 0 : 1 );
}

function sp_oc_test_counter_read( string $root, string $salt, string $dropin ): void {
	sp_oc_test_boot( $root, $salt, $dropin );
	$found = null;
	$value = wp_cache_get( 'counter', 'concurrency', true, $found );
	wp_cache_close();
	echo json_encode( [ 'found' => $found === true, 'value' => $value ] ), PHP_EOL;
	exit( 0 );
}

function sp_oc_test_worker( string $root, string $salt, string $dropin, int $iterations, string $gate, string $worker_id ): void {
	sp_oc_test_boot( $root, $salt, $dropin );
	$persistent = method_exists( $GLOBALS['wp_object_cache'], 'is_persistent' ) && $GLOBALS['wp_object_cache']->is_persistent();
	$ready      = $gate . '.ready-' . preg_replace( '/[^a-z0-9_-]/i', '', $worker_id );
	file_put_contents( $ready, 'ready' );
	$deadline = microtime( true ) + 10;
	while ( ! is_file( $gate ) && microtime( true ) < $deadline ) {
		usleep( 10000 );
	}

	$failures = 0;
	if ( ! $persistent || ! is_file( $gate ) ) {
		$failures++;
	} else {
		for ( $index = 0; $index < $iterations; $index++ ) {
			if ( wp_cache_incr( 'counter', 1, 'concurrency' ) === false ) {
				$failures++;
			}
		}
	}
	wp_cache_close();
	echo json_encode( [ 'failures' => $failures ] ), PHP_EOL;
	exit( $failures === 0 ? 0 : 1 );
}

$sp_oc_test_mode = isset( $argv[1] ) ? (string) $argv[1] : 'run';
if ( $sp_oc_test_mode !== 'run' ) {
	if ( ! class_exists( 'SQLite3', false ) ) {
		fwrite( STDERR, "sqlite3 unavailable\n" );
		exit( 2 );
	}

	$root = isset( $argv[2] ) ? (string) $argv[2] : '';
	$salt = isset( $argv[3] ) ? (string) $argv[3] : '';
	if ( ! sp_oc_test_is_fixture( $root ) || $salt === '' ) {
		fwrite( STDERR, "unsafe or invalid fixture path\n" );
		exit( 2 );
	}

	switch ( $sp_oc_test_mode ) {
		case 'contract':
			sp_oc_test_contract( $root, $salt, $sp_oc_test_dropin );
			break;
		case 'ttl':
			sp_oc_test_ttl( $root, $salt, $sp_oc_test_dropin );
			break;
		case 'non-persistent-fallback':
			sp_oc_test_non_persistent_fallback( $root, $salt, $sp_oc_test_dropin );
			break;
		case 'write-value':
			sp_oc_test_write_value( $root, $salt, $sp_oc_test_dropin, isset( $argv[4] ) ? (string) $argv[4] : '' );
			break;
		case 'read-value':
			sp_oc_test_read_value( $root, $salt, $sp_oc_test_dropin );
			break;
		case 'flush':
			sp_oc_test_flush( $root, $salt, $sp_oc_test_dropin );
			break;
		case 'counter-init':
			sp_oc_test_counter_init( $root, $salt, $sp_oc_test_dropin );
			break;
		case 'counter-read':
			sp_oc_test_counter_read( $root, $salt, $sp_oc_test_dropin );
			break;
		case 'worker':
			$iterations = isset( $argv[4] ) ? max( 1, (int) $argv[4] ) : 1;
			$gate       = isset( $argv[5] ) ? (string) $argv[5] : '';
			$worker_id  = isset( $argv[6] ) ? (string) $argv[6] : '';
			if ( realpath( dirname( $gate ) ) !== realpath( $root ) ) {
				fwrite( STDERR, "unsafe gate path\n" );
				exit( 2 );
			}
			sp_oc_test_worker( $root, $salt, $sp_oc_test_dropin, $iterations, $gate, $worker_id );
			break;
		default:
			fwrite( STDERR, "unknown child mode\n" );
			exit( 2 );
	}
	exit( 2 );
}

if ( ! class_exists( 'SQLite3', false ) ) {
	echo "SP Accelerator object cache: skipped (PHP sqlite3 unavailable).\n";
	exit( 0 );
}

try {
	$sp_oc_test_suffix = bin2hex( random_bytes( 8 ) );
} catch ( Throwable $error ) {
	$sp_oc_test_suffix = str_replace( '.', '-', uniqid( '', true ) );
}
$sp_oc_test_root = sys_get_temp_dir() . '/sp-accelerator-object-cache-test-' . $sp_oc_test_suffix;
if ( ! @mkdir( $sp_oc_test_root, 0700, true ) || ! sp_oc_test_is_fixture( $sp_oc_test_root ) ) {
	fwrite( STDERR, "SP Accelerator object cache tests could not create a safe fixture.\n" );
	exit( 1 );
}
register_shutdown_function( 'sp_oc_test_remove_tree', $sp_oc_test_root );

$sp_oc_test_failures = [];
$sp_oc_test_checks   = 0;
$sp_oc_test_cases    = [
	'contract'    => $sp_oc_test_root . '/contract',
	'ttl'         => $sp_oc_test_root . '/ttl',
	'fallback'    => $sp_oc_test_root . '/fallback',
	'salt'        => $sp_oc_test_root . '/salt',
	'concurrency' => $sp_oc_test_root . '/concurrency',
];
foreach ( $sp_oc_test_cases as $case_root ) {
	@mkdir( $case_root . '/wp-content', 0700, true );
}

$result = sp_oc_test_run_process( [ PHP_BINARY, $sp_oc_test_script, 'contract', $sp_oc_test_cases['contract'], 'contract-salt' ] );
sp_oc_test_merge_child( 'contract checks', $result, $sp_oc_test_failures, $sp_oc_test_checks );

$result = sp_oc_test_run_process( [ PHP_BINARY, $sp_oc_test_script, 'ttl', $sp_oc_test_cases['ttl'], 'ttl-salt' ] );
sp_oc_test_merge_child( 'TTL checks', $result, $sp_oc_test_failures, $sp_oc_test_checks );

$result = sp_oc_test_run_process( [ PHP_BINARY, $sp_oc_test_script, 'non-persistent-fallback', $sp_oc_test_cases['fallback'], 'fallback-salt' ] );
sp_oc_test_merge_child( 'non-persistent fallback checks', $result, $sp_oc_test_failures, $sp_oc_test_checks );

$salt_root = $sp_oc_test_cases['salt'];
$result    = sp_oc_test_run_process( [ PHP_BINARY, $sp_oc_test_script, 'write-value', $salt_root, 'salt-a', 'A' ] );
$payload   = sp_oc_test_payload( $result );
sp_oc_test_assert( $result['status'] === 0 && is_array( $payload ) && ! empty( $payload['ok'] ), 'salt A write failed', $sp_oc_test_failures, $sp_oc_test_checks );

$result  = sp_oc_test_run_process( [ PHP_BINARY, $sp_oc_test_script, 'read-value', $salt_root, 'salt-b' ] );
$payload = sp_oc_test_payload( $result );
sp_oc_test_assert( $result['status'] === 0 && is_array( $payload ) && empty( $payload['found'] ), 'salt B could read salt A value', $sp_oc_test_failures, $sp_oc_test_checks );

$result  = sp_oc_test_run_process( [ PHP_BINARY, $sp_oc_test_script, 'write-value', $salt_root, 'salt-b', 'B' ] );
$payload = sp_oc_test_payload( $result );
sp_oc_test_assert( $result['status'] === 0 && is_array( $payload ) && ! empty( $payload['ok'] ), 'salt B write failed', $sp_oc_test_failures, $sp_oc_test_checks );

$result  = sp_oc_test_run_process( [ PHP_BINARY, $sp_oc_test_script, 'flush', $salt_root, 'salt-b' ] );
$payload = sp_oc_test_payload( $result );
sp_oc_test_assert( $result['status'] === 0 && is_array( $payload ) && ! empty( $payload['ok'] ), 'salt B namespace flush failed', $sp_oc_test_failures, $sp_oc_test_checks );

$result  = sp_oc_test_run_process( [ PHP_BINARY, $sp_oc_test_script, 'read-value', $salt_root, 'salt-a' ] );
$payload = sp_oc_test_payload( $result );
sp_oc_test_assert( $result['status'] === 0 && is_array( $payload ) && ! empty( $payload['found'] ) && isset( $payload['value'] ) && $payload['value'] === 'A', 'salt B flush removed salt A namespace', $sp_oc_test_failures, $sp_oc_test_checks );

$result  = sp_oc_test_run_process( [ PHP_BINARY, $sp_oc_test_script, 'read-value', $salt_root, 'salt-b' ] );
$payload = sp_oc_test_payload( $result );
sp_oc_test_assert( $result['status'] === 0 && is_array( $payload ) && empty( $payload['found'] ), 'salt B flush left its value behind', $sp_oc_test_failures, $sp_oc_test_checks );

$concurrency_root = $sp_oc_test_cases['concurrency'];
$concurrency_salt = 'concurrency-salt';
$workers          = 4;
$iterations       = 40;
$expected         = $workers * $iterations;
$result           = sp_oc_test_run_process( [ PHP_BINARY, $sp_oc_test_script, 'counter-init', $concurrency_root, $concurrency_salt ] );
$payload          = sp_oc_test_payload( $result );
sp_oc_test_assert( $result['status'] === 0 && is_array( $payload ) && ! empty( $payload['ok'] ), 'counter initialization failed', $sp_oc_test_failures, $sp_oc_test_checks );

$gate             = $concurrency_root . '/increment.start';
$running_workers  = [];
$worker_launch_ok = true;
for ( $worker = 0; $worker < $workers; $worker++ ) {
	$running = sp_oc_test_start_process( [ PHP_BINARY, $sp_oc_test_script, 'worker', $concurrency_root, $concurrency_salt, (string) $iterations, $gate, (string) $worker ] );
	if ( $running === null ) {
		$worker_launch_ok = false;
	} else {
		$running_workers[ $worker ] = $running;
	}
}
sp_oc_test_assert( $worker_launch_ok && count( $running_workers ) === $workers, 'not all increment workers started', $sp_oc_test_failures, $sp_oc_test_checks );

$ready          = 0;
$ready_deadline = microtime( true ) + 10;
do {
	$ready = 0;
	for ( $worker = 0; $worker < $workers; $worker++ ) {
		if ( is_file( $gate . '.ready-' . $worker ) ) {
			$ready++;
		}
	}
	if ( $ready === $workers ) {
		break;
	}
	usleep( 10000 );
} while ( microtime( true ) < $ready_deadline );
sp_oc_test_assert( $ready === $workers, 'increment workers did not reach start gate', $sp_oc_test_failures, $sp_oc_test_checks );
@touch( $gate );

$worker_failures = 0;
foreach ( $running_workers as $running ) {
	$result  = sp_oc_test_finish_process( $running );
	$payload = sp_oc_test_payload( $result );
	if ( $result['status'] !== 0 || ! is_array( $payload ) || ! isset( $payload['failures'] ) ) {
		$worker_failures++;
	} else {
		$worker_failures += max( 0, (int) $payload['failures'] );
	}
}
sp_oc_test_assert( $worker_failures === 0, 'one or more atomic increments failed', $sp_oc_test_failures, $sp_oc_test_checks );

$result  = sp_oc_test_run_process( [ PHP_BINARY, $sp_oc_test_script, 'counter-read', $concurrency_root, $concurrency_salt ] );
$payload = sp_oc_test_payload( $result );
sp_oc_test_assert(
	$result['status'] === 0 && is_array( $payload ) && ! empty( $payload['found'] ) && isset( $payload['value'] ) && (int) $payload['value'] === $expected,
	'atomic increment lost updates: expected ' . $expected . ', got ' . ( is_array( $payload ) && isset( $payload['value'] ) ? (string) $payload['value'] : 'unavailable' ),
	$sp_oc_test_failures,
	$sp_oc_test_checks
);

sp_oc_test_remove_tree( $sp_oc_test_root );
if ( $sp_oc_test_failures ) {
	fwrite( STDERR, "SP Accelerator object cache tests failed:\n- " . implode( "\n- ", $sp_oc_test_failures ) . "\n" );
	exit( 1 );
}

echo 'SP Accelerator object cache: ' . $sp_oc_test_checks . " checks passed.\n";
