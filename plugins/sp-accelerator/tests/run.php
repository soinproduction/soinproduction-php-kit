<?php

/**
 * Dependency-free regression checks for the request policy and standalone
 * advanced-cache.php. Run with: php tests/run.php
 */

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

$root = dirname( __DIR__ );

function sp_test_remove_tree( string $directory ): void {
	$temporary = realpath( sys_get_temp_dir() );
	$path      = realpath( $directory );
	if ( $temporary === false || $path === false || strpos( $path, $temporary . DIRECTORY_SEPARATOR . 'sp-accelerator-test-' ) !== 0 ) {
		return;
	}
	$items = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ( $items as $item ) {
		$item->isDir() && ! $item->isLink() ? @rmdir( $item->getPathname() ) : @unlink( $item->getPathname() );
	}
	@rmdir( $path );
}

if ( isset( $argv[1] ) && $argv[1] === 'dropin-case' ) {
	$case = isset( $argv[2] ) ? (string) $argv[2] : '';
	$base = isset( $argv[3] ) ? (string) $argv[3] : '';
	@mkdir( $base . '/wp-content/cache/sp-accelerator', 0755, true );

	define( 'ABSPATH', $base . '/public/' );
	define( 'WP_CONTENT_DIR', $base . '/wp-content' );
	if ( $case === 'ancestor-root-protected' ) {
		define( 'SP_ACCELERATOR_CACHE_DIR', $base );
	}
	if ( $case !== 'unsafe-storage' ) {
		define( 'SP_ACCELERATOR_CACHE_WEB_PROTECTED', true );
	}
	$_GET = [];
	$_SERVER = [
		'REQUEST_METHOD' => $case === 'head' ? 'HEAD' : 'GET',
		'REQUEST_URI'    => '/hello/',
		'HTTP_HOST'      => 'example.test',
		'HTTPS'          => 'on',
	];
	if ( $case === 'unsafe-storage' ) {
		$_SERVER['DOCUMENT_ROOT'] = $base;
	}

	if ( $case === 'authorization' ) {
		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer secret';
	} elseif ( $case === 'unknown-cookie' ) {
		$_SERVER['HTTP_COOKIE'] = 'member_id=123';
	} elseif ( $case === 'allowed-cookie' ) {
		$_SERVER['HTTP_COOKIE'] = '_ga_ABC=123';
	} elseif ( $case === 'excluded-cookie' ) {
		$_SERVER['HTTP_COOKIE'] = 'woocommerce_items_in_cart=1';
	} elseif ( $case === 'utm' ) {
		$_SERVER['REQUEST_URI'] = '/hello/?utm_source=test&gclid=abc';
	} elseif ( $case === 'custom-query' ) {
		$_SERVER['REQUEST_URI'] = '/hello/?currency=EUR';
	} elseif ( $case === 'request-no-cache' ) {
		$_SERVER['HTTP_CACHE_CONTROL'] = 'max-age=0';
	} elseif ( $case === 'gzip-zero' ) {
		$_SERVER['HTTP_ACCEPT_ENCODING'] = 'gzip;q=0, br;q=1';
	} elseif ( $case === 'gzip' ) {
		$_SERVER['HTTP_ACCEPT_ENCODING'] = 'br;q=0.5, gzip;q=1';
	} elseif ( $case === 'untrusted-forwarded-proto' ) {
		$_SERVER['HTTPS']                  = 'off';
		$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
	} elseif ( $case === 'trusted-forwarded-proto' ) {
		$_SERVER['HTTPS']                  = 'off';
		$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
		define( 'SP_ACCELERATOR_TRUST_FORWARDED_PROTO', true );
	} elseif ( $case === 'https-port' ) {
		$_SERVER['HTTPS']      = 'off';
		$_SERVER['SERVER_PORT'] = 443;
	} elseif ( $case === 'tracking-stale' ) {
		$_SERVER['REQUEST_URI'] = '/hello/?utm_source=stale-test';
	} elseif ( $case === 'forged-warm' ) {
		$_SERVER['HTTP_X_SP_CACHE_WARM'] = '1';
	}

	$current  = in_array( $case, [ 'previous', 'expired-previous' ], true ) ? 'g2' : 'g1';
	$previous = in_array( $case, [ 'previous', 'expired-previous' ], true ) ? 'g1' : '';
	$cache_root = defined( 'SP_ACCELERATOR_CACHE_DIR' ) ? (string) SP_ACCELERATOR_CACHE_DIR : $base . '/wp-content/cache/sp-accelerator';
	$config = [
		'signature'                => 'SP Accelerator cache config',
		'enabled'                  => true,
		'cache_root'               => $cache_root,
		'generation'               => $current,
		'previous_generation'      => $previous,
		'generation_changed_at'    => time(),
		'generation_stale_ttl'     => 3600,
		'ttl'                      => 3600,
		'stale_ttl'                => 21600,
		'browser_ttl'              => 0,
		'gzip'                     => true,
		'hosts'                    => [ 'example.test' ],
		'exclude_paths'            => [ '/wp-admin/' ],
		'exclude_cookies'          => [ 'wordpress_logged_in_', 'woocommerce_items_in_cart' ],
		'allow_cookies'            => [ '_ga' ],
		'bypass_unknown_cookies'   => true,
		'warm_token'               => 'fixture-warm-token',
	];
	if ( $case === 'unsigned-config' ) {
		unset( $config['signature'] );
	}

	$url        = 'https://example.test/hello/';
	$warm_time  = $case === 'expired-warm' ? time() - 301 : time();
	if ( in_array( $case, [ 'signed-warm', 'expired-warm', 'wrong-url-warm', 'wrong-generation-warm' ], true ) ) {
		$warm_url        = $case === 'wrong-url-warm' ? 'https://example.test/other/' : $url;
		$warm_generation = $case === 'wrong-generation-warm' ? 'other-generation' : $current;
		$_SERVER['HTTP_X_SP_CACHE_WARM'] = $warm_time . ':' . hash_hmac(
			'sha256',
			$warm_time . '|' . $warm_generation . '|' . $warm_url,
			$config['warm_token']
		);
	}
	file_put_contents( $cache_root . '/config.json', json_encode( $config ) );

	$hash       = hash( 'sha256', $url );
	$generation = $previous !== '' ? $previous : $current;
	$directory  = $config['cache_root'] . '/pages/' . $generation . '/' . substr( $hash, 0, 2 ) . '/' . substr( $hash, 2, 2 );
	@mkdir( $directory, 0755, true );
	$base_file = $directory . '/' . $hash;
	$html      = '<!doctype html><html><body>' . str_repeat( 'cache-hit ', 40 ) . '</body></html>';
	$content_hash = hash( 'sha256', $html );
	if ( ! in_array( $case, [ 'lock-ownership', 'empty-lock-reuse' ], true ) ) {
		file_put_contents( $base_file . '.html', $html );
		file_put_contents( $base_file . '.html.gz', gzencode( $html, 6 ) );
		file_put_contents( $base_file . '.json', json_encode( [
			'ttl'          => 3600,
			'stale_ttl'    => 21600,
			'content_hash' => $content_hash,
			'content_type' => 'text/html; charset=UTF-8',
			'headers'      => [],
		] ) );
	}
	if ( $case === 'previous' ) {
		$current_directory = $config['cache_root'] . '/pages/' . $current . '/' . substr( $hash, 0, 2 ) . '/' . substr( $hash, 2, 2 );
		@mkdir( $current_directory, 0755, true );
		file_put_contents( $current_directory . '/' . $hash . '.lock', (string) time() );
	}
	if ( $case === 'expired-previous' ) {
		touch( $base_file . '.html', time() - 30000 );
	} elseif ( $case === 'hard-expired-current' ) {
		touch( $base_file . '.html', time() - 30000 );
	} elseif ( $case === 'tracking-stale' ) {
		touch( $base_file . '.html', time() - 4000 );
	}
	$etag = '"sp-' . substr( $hash, 0, 16 ) . '-' . substr( hash( 'sha256', $generation ), 0, 8 ) . '-' . substr( $content_hash, 0, 20 ) . '-id"';
	if ( $case === 'etag-match' ) {
		$_SERVER['HTTP_IF_NONE_MATCH'] = $etag;
	} elseif ( $case === 'etag-wrong-generation' ) {
		$_SERVER['HTTP_IF_NONE_MATCH'] = '"sp-' . substr( $hash, 0, 16 ) . '-' . substr( hash( 'sha256', 'different-generation' ), 0, 8 ) . '-' . substr( $content_hash, 0, 20 ) . '-id"';
	} elseif ( $case === 'etag-wrong-content' ) {
		$_SERVER['HTTP_IF_NONE_MATCH'] = '"sp-' . substr( $hash, 0, 16 ) . '-' . substr( hash( 'sha256', $generation ), 0, 8 ) . '-' . substr( hash( 'sha256', 'different-content' ), 0, 20 ) . '-id"';
	}
	if ( $case === 'etag-match' ) {
		register_shutdown_function( static function (): void {
			fwrite( STDERR, 'HTTP_STATUS:' . http_response_code() );
		} );
	} elseif ( $case === 'tracking-stale' ) {
		register_shutdown_function( static function () use ( $base_file ): void {
			fwrite( STDERR, is_file( $base_file . '.lock' ) ? 'REVALIDATION_LOCK:present' : 'REVALIDATION_LOCK:absent' );
		} );
	}
	register_shutdown_function( 'sp_test_remove_tree', $base );

	include $root . '/templates/advanced-cache.php';
	if ( $case === 'lock-ownership' ) {
		$owned_lock = $GLOBALS['sp_accelerator_revalidation_lock'] ?? null;
		$preserved  = false;
		if ( is_array( $owned_lock ) && ! empty( $owned_lock['path'] ) && isset( $sp_release_owned_lock ) && is_callable( $sp_release_owned_lock ) ) {
			$replacement = 'replacement-worker-token';
			file_put_contents( $owned_lock['path'], $replacement );
			$sp_release_owned_lock();
			$preserved = is_readable( $owned_lock['path'] ) && file_get_contents( $owned_lock['path'] ) === $replacement;
		}
		echo $preserved ? '__FOREIGN_LOCK_PRESERVED__' : '__FOREIGN_LOCK_REMOVED__';
		exit( $preserved ? 0 : 1 );
	}
	if ( $case === 'empty-lock-reuse' ) {
		$owned_lock = $GLOBALS['sp_accelerator_revalidation_lock'] ?? null;
		$reused = false;
		if ( is_array( $owned_lock ) && ! empty( $owned_lock['path'] ) && isset( $sp_release_owned_lock, $sp_acquire_lock ) && is_callable( $sp_release_owned_lock ) && is_callable( $sp_acquire_lock ) ) {
			$before = fileinode( $owned_lock['path'] );
			$sp_release_owned_lock();
			$empty = is_file( $owned_lock['path'] ) && file_get_contents( $owned_lock['path'] ) === '';
			$claimed = $sp_acquire_lock( $owned_lock['path'] ) === 1;
			$after = fileinode( $owned_lock['path'] );
			$reused = $empty && $claimed && $before !== false && $before === $after;
			$sp_release_owned_lock();
		}
		echo $reused ? '__LOCK_INODE_REUSED__' : '__LOCK_INODE_REPLACED__';
		exit( $reused ? 0 : 1 );
	}
	echo '__FALLTHROUGH__';
	exit( 0 );
}

$failures = [];
$checks   = 0;

function sp_test_assert( bool $condition, string $message ): void {
	global $failures, $checks;
	$checks++;
	if ( ! $condition ) {
		$failures[] = $message;
	}
}

function sp_test_dropin_case( string $case ): array {
	$base = sys_get_temp_dir() . '/sp-accelerator-test-' . preg_replace( '/[^a-z0-9-]/i', '-', $case ) . '-' . bin2hex( random_bytes( 4 ) );
	$command = [ PHP_BINARY, __FILE__, 'dropin-case', $case, $base ];
	$pipes   = [];
	$process = proc_open( $command, [ 0 => [ 'pipe', 'r' ], 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ], $pipes );
	if ( ! is_resource( $process ) ) {
		return [ '', 'proc_open failed', 1 ];
	}
	fclose( $pipes[0] );
	$output = stream_get_contents( $pipes[1] );
	$error  = stream_get_contents( $pipes[2] );
	fclose( $pipes[1] );
	fclose( $pipes[2] );
	$status = proc_close( $process );
	sp_test_remove_tree( $base );
	return [ is_string( $output ) ? $output : '', is_string( $error ) ? $error : '', $status ];
}

function sp_test_external_suite( string $script ): array {
	$pipes   = [];
	$process = @proc_open( [ PHP_BINARY, $script ], [ 0 => [ 'pipe', 'r' ], 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ], $pipes );
	if ( ! is_resource( $process ) ) {
		return [ '', 'proc_open failed', 1 ];
	}
	fclose( $pipes[0] );
	$output = stream_get_contents( $pipes[1] );
	$error  = stream_get_contents( $pipes[2] );
	fclose( $pipes[1] );
	fclose( $pipes[2] );
	$status = proc_close( $process );
	return [ is_string( $output ) ? $output : '', is_string( $error ) ? $error : '', $status ];
}

$expected_html = '<!doctype html><html><body>' . str_repeat( 'cache-hit ', 40 ) . '</body></html>';
foreach ( [ 'hit', 'allowed-cookie', 'utm', 'trusted-forwarded-proto', 'https-port', 'forged-warm', 'expired-warm', 'wrong-url-warm', 'wrong-generation-warm' ] as $case ) {
	list( $output, $error, $status ) = sp_test_dropin_case( $case );
	sp_test_assert( $status === 0 && $error === '' && $output === $expected_html, 'drop-in should HIT: ' . $case );
}
foreach ( [ 'authorization', 'unknown-cookie', 'excluded-cookie', 'custom-query', 'request-no-cache', 'untrusted-forwarded-proto', 'signed-warm', 'unsafe-storage', 'ancestor-root-protected', 'unsigned-config' ] as $case ) {
	list( $output, $error, $status ) = sp_test_dropin_case( $case );
	sp_test_assert( $status === 0 && $error === '' && $output === '__FALLTHROUGH__', 'drop-in should bypass: ' . $case );
}
list( $output ) = sp_test_dropin_case( 'previous' );
sp_test_assert( $output === $expected_html, 'drop-in should serve the previous generation during grace' );
list( $output ) = sp_test_dropin_case( 'gzip-zero' );
sp_test_assert( $output === $expected_html, 'gzip;q=0 must serve identity' );
list( $output ) = sp_test_dropin_case( 'gzip' );
sp_test_assert( function_exists( 'gzdecode' ) && gzdecode( $output ) === $expected_html, 'gzip client must receive the gzip variant' );
list( $output ) = sp_test_dropin_case( 'head' );
sp_test_assert( $output === '', 'HEAD HIT must not emit a body' );
list( $output, $error, $status ) = sp_test_dropin_case( 'etag-match' );
sp_test_assert( $status === 0 && $error === 'HTTP_STATUS:304' && $output === '', 'matching content-hash/generation ETag should return 304 with no body' );
foreach ( [ 'etag-wrong-generation', 'etag-wrong-content' ] as $case ) {
	list( $output, $error, $status ) = sp_test_dropin_case( $case );
	sp_test_assert( $status === 0 && $error === '' && $output === $expected_html, 'mismatched ETag identity should return the body: ' . $case );
}
list( $output, $error, $status ) = sp_test_dropin_case( 'lock-ownership' );
sp_test_assert( $status === 0 && $error === '' && $output === '__FOREIGN_LOCK_PRESERVED__', 'old revalidation worker must not remove a replacement lock token' );
list( $output, $error, $status ) = sp_test_dropin_case( 'empty-lock-reuse' );
sp_test_assert( $status === 0 && $error === '' && $output === '__LOCK_INODE_REUSED__', 'released lock must keep and reuse the same inode' );
list( $output, $error, $status ) = sp_test_dropin_case( 'hard-expired-current' );
sp_test_assert( $status === 0 && $error === '' && $output === '__FALLTHROUGH__', 'current entry past ttl + stale_ttl must never be served' );
list( $output, $error, $status ) = sp_test_dropin_case( 'expired-previous' );
sp_test_assert( $status === 0 && $error === '' && $output === '__FALLTHROUGH__', 'previous-generation entry past ttl + stale_ttl must never be served' );
list( $output, $error, $status ) = sp_test_dropin_case( 'tracking-stale' );
sp_test_assert( $status === 0 && $error === 'REVALIDATION_LOCK:absent' && $output === $expected_html, 'tracking query may read permissible stale without creating a revalidation lock' );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
if ( ! defined( 'WEEK_IN_SECONDS' ) ) {
	define( 'WEEK_IN_SECONDS', 604800 );
	define( 'DAY_IN_SECONDS', 86400 );
	define( 'HOUR_IN_SECONDS', 3600 );
}
function get_option( $key, $default = false ) { return $default; }
function home_url( $path = '' ) { return 'https://example.test' . $path; }
function site_url( $path = '' ) { return 'https://example.test' . $path; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function absint( $value ) { return abs( (int) $value ); }
function is_ssl() {
	return ( ! empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' )
		|| (int) ( $_SERVER['SERVER_PORT'] ?? 0 ) === 443;
}
function apply_filters( $hook, $value ) {
	if ( $hook === 'nonce_life' && isset( $GLOBALS['sp_test_nonce_life'] ) ) {
		return $GLOBALS['sp_test_nonce_life'];
	}
	return $value;
}

require_once $root . '/includes/class-config.php';
require_once $root . '/includes/class-request.php';
require_once $root . '/includes/class-cache.php';
$request = new SP_Accelerator_Request( new SP_Accelerator_Config() );

function sp_test_request( SP_Accelerator_Request $request, string $uri = '/hello/', array $server = [] ): bool {
	$_GET = [];
	$_SERVER = array_merge( [
		'REQUEST_METHOD' => 'GET',
		'REQUEST_URI'    => $uri,
		'HTTP_HOST'      => 'example.test',
		'HTTPS'          => 'on',
	], $server );
	return $request->is_cacheable_transport();
}

sp_test_assert( sp_test_request( $request ), 'runtime policy should allow a clean public request' );
sp_test_assert( sp_test_request( $request, '/hello/?utm_source=test' ) && ! $request->can_seed_cache(), 'tracking URL may read but must not seed the shared entry' );
sp_test_assert( ! sp_test_request( $request, '/hello/?currency=EUR' ), 'custom query must bypass runtime cache' );
sp_test_assert( ! sp_test_request( $request, '/hello/', [ 'HTTP_COOKIE' => 'member_id=1' ] ), 'unknown cookie must bypass runtime cache' );
sp_test_assert( sp_test_request( $request, '/hello/', [ 'HTTP_COOKIE' => '_ga_ABC=1' ] ), 'allowed analytics cookie may use runtime cache' );
sp_test_assert( ! sp_test_request( $request, '/hello/', [ 'HTTP_CACHE_CONTROL' => 'max-age=0' ] ), 'request max-age=0 must bypass runtime cache' );
sp_test_assert( ! sp_test_request( $request, '/hello/', [ 'PHP_AUTH_DIGEST' => 'secret' ] ), 'digest authorization must bypass runtime cache' );
$untrusted_forwarded_transport = sp_test_request( $request, '/hello/', [ 'HTTPS' => 'off', 'HTTP_X_FORWARDED_PROTO' => 'https' ] );
sp_test_assert( $untrusted_forwarded_transport && $request->canonical_request_url() === 'http://example.test/hello/', 'runtime cache key must ignore untrusted X-Forwarded-Proto' );
$https_port_transport = sp_test_request( $request, '/hello/', [ 'HTTPS' => 'off', 'SERVER_PORT' => 443 ] );
sp_test_assert( $https_port_transport && $request->canonical_request_url() === 'https://example.test/hello/', 'SERVER_PORT=443 must use the HTTPS runtime cache key' );

$warm_config = new SP_Accelerator_Config();
$warm_url    = 'https://example.test/hello/';
$_SERVER['HTTP_X_SP_CACHE_WARM'] = $warm_config->warm_request_token( $warm_url );
sp_test_assert( $warm_config->is_authenticated_warm_request( $warm_url ), 'runtime accepts its current URL/generation-bound warm signature' );
sp_test_assert( ! $warm_config->is_authenticated_warm_request( 'https://example.test/other/' ), 'runtime rejects a warm signature replayed to another URL' );
$expired_time = time() - 301;
$_SERVER['HTTP_X_SP_CACHE_WARM'] = $expired_time . ':' . hash_hmac( 'sha256', $expired_time . '|1|' . $warm_url, $warm_config->warm_token() );
sp_test_assert( ! $warm_config->is_authenticated_warm_request( $warm_url ), 'runtime rejects an expired warm signature' );
unset( $_SERVER['HTTP_X_SP_CACHE_WARM'] );

$cache       = new SP_Accelerator_Cache( new SP_Accelerator_Config(), $request );
$gzip_method = new ReflectionMethod( SP_Accelerator_Cache::class, 'accepts_gzip' );
$gzip_method->setAccessible( true );
foreach ( [
	'gzip;q=0, br;q=1'       => false,
	'*;q=1, gzip;q=0'        => false,
	'x-gzip;q=0.8'           => true,
	'gzip;q=broken'          => false,
	'br;q=1, *;q=0.5'        => true,
	'gzip;q=0, gzip;q=0.8'   => true,
] as $encoding => $expected ) {
	$_SERVER['HTTP_ACCEPT_ENCODING'] = $encoding;
	sp_test_assert( $gzip_method->invoke( $cache ) === $expected, 'runtime gzip negotiation: ' . $encoding );
}

$ttl_method = new ReflectionMethod( SP_Accelerator_Cache::class, 'response_ttls' );
$ttl_method->setAccessible( true );
$plain_ttls = $ttl_method->invoke( $cache, '<!doctype html><html><body>Public page</body></html>' );
sp_test_assert( is_array( $plain_ttls ) && $plain_ttls['ttl'] === 3600 && $plain_ttls['stale_ttl'] === 21600, 'plain HTML should retain configured cache TTLs' );
$GLOBALS['sp_test_nonce_life'] = 120;
$nonce_ttls = $ttl_method->invoke( $cache, '<!doctype html><html><body><input name="_wpnonce" value="abc"></body></html>' );
unset( $GLOBALS['sp_test_nonce_life'] );
sp_test_assert( is_array( $nonce_ttls ) && $nonce_ttls['ttl'] === 1 && $nonce_ttls['stale_ttl'] === 0, 'short nonce_life must clamp page TTL and disable stale serving' );

foreach ( [ 'object-cache.php', 'control-plane.php', 'markup.php', 'cache-lock.php', 'server.php' ] as $external_suite ) {
	list( $external_output, $external_error, $external_status ) = sp_test_external_suite( __DIR__ . '/' . $external_suite );
	$external_detail = trim( $external_error . ' ' . $external_output );
	sp_test_assert( $external_status === 0, $external_suite . ' regression failed: ' . $external_detail );
	if ( strpos( $external_output, 'skipped' ) !== false ) {
		echo trim( $external_output ) . "\n";
	}
}

if ( $failures ) {
	fwrite( STDERR, "SP Accelerator tests failed:\n- " . implode( "\n- ", $failures ) . "\n" );
	exit( 1 );
}

echo 'SP Accelerator: ' . $checks . " checks passed.\n";
