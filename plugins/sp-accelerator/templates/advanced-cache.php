<?php
/**
 * SP Accelerator Drop-in
 * Standalone full-page cache reader. No WordPress functions are used here.
 */

if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CONTENT_DIR' ) ) {
	return;
}

$sp_config_root = defined( 'SP_ACCELERATOR_CACHE_DIR' ) ? rtrim( trim( (string) SP_ACCELERATOR_CACHE_DIR ), '/\\' ) : '';
$sp_config_root_is_absolute = strpos( $sp_config_root, '/' ) === 0 || preg_match( '/^[a-zA-Z]:[\\\\\/]/', $sp_config_root ) === 1;
if ( ! $sp_config_root_is_absolute || $sp_config_root === DIRECTORY_SEPARATOR || dirname( $sp_config_root ) === $sp_config_root ) {
	$sp_automatic_document_root = defined( 'SP_ACCELERATOR_DOCUMENT_ROOT' )
		? trim( (string) SP_ACCELERATOR_DOCUMENT_ROOT )
		: trim( (string) ( $_SERVER['DOCUMENT_ROOT'] ?? '' ) );
	$sp_automatic_root_is_absolute = strpos( $sp_automatic_document_root, '/' ) === 0 || preg_match( '/^[a-zA-Z]:[\\\\\/]/', $sp_automatic_document_root ) === 1;
	$sp_automatic_anchor = $sp_automatic_root_is_absolute
		&& $sp_automatic_document_root !== DIRECTORY_SEPARATOR
		&& dirname( rtrim( $sp_automatic_document_root, '/\\' ) ) !== rtrim( $sp_automatic_document_root, '/\\' )
			? $sp_automatic_document_root
			: (string) ABSPATH;
	$sp_site_path = rtrim( str_replace( '\\', '/', (string) ABSPATH ), '/' );
	$sp_config_root = rtrim( dirname( rtrim( $sp_automatic_anchor, '/\\' ) ), '/\\' )
		. DIRECTORY_SEPARATOR . 'sp-accelerator-' . substr( hash( 'sha256', $sp_site_path ), 0, 12 );
}
$sp_config_file = $sp_config_root . '/config.json';
if ( ! is_readable( $sp_config_file ) ) {
	return;
}

$sp_config_json = file_get_contents( $sp_config_file );
$sp_config      = is_string( $sp_config_json ) ? json_decode( $sp_config_json, true ) : null;
if ( ! is_array( $sp_config )
	|| empty( $sp_config['enabled'] )
	|| ! isset( $sp_config['signature'] )
	|| ! hash_equals( 'SP Accelerator cache config', (string) $sp_config['signature'] )
) {
	return;
}

// Enforce the same positive-proof storage policy before trusting even an old
// enabled config. This closes the update window before admin_init can migrate
// or rewrite a legacy JSON file.
$sp_root = isset( $sp_config['cache_root'] ) ? rtrim( (string) $sp_config['cache_root'], '/\\' ) : $sp_config_root;
$sp_root_is_absolute = strpos( $sp_root, '/' ) === 0 || preg_match( '/^[a-zA-Z]:[\\\\\/]/', $sp_root ) === 1;
if ( ! $sp_root_is_absolute || $sp_root === '' || $sp_root === DIRECTORY_SEPARATOR || dirname( $sp_root ) === $sp_root ) {
	return;
}
$sp_storage_path = rtrim( str_replace( '\\', '/', (string) ( realpath( $sp_root ) ?: $sp_root ) ), '/' );
if ( DIRECTORY_SEPARATOR === '\\' ) {
	$sp_storage_path = strtolower( $sp_storage_path );
}
if ( ! preg_match( '/(?:^|[-_.])sp-accelerator(?:[-_.]|$)/i', basename( $sp_storage_path ) ) ) {
	return;
}
$sp_reserved_roots = [ ABSPATH, WP_CONTENT_DIR, sys_get_temp_dir() ];
$sp_candidate_document_root = defined( 'SP_ACCELERATOR_DOCUMENT_ROOT' )
	? (string) SP_ACCELERATOR_DOCUMENT_ROOT
	: (string) ( $_SERVER['DOCUMENT_ROOT'] ?? '' );
if ( $sp_candidate_document_root !== '' ) {
	$sp_reserved_roots[] = $sp_candidate_document_root;
}
foreach ( $sp_reserved_roots as $sp_reserved_root ) {
	$sp_reserved_root = rtrim( str_replace( '\\', '/', (string) ( realpath( $sp_reserved_root ) ?: $sp_reserved_root ) ), '/' );
	if ( DIRECTORY_SEPARATOR === '\\' ) {
		$sp_reserved_root = strtolower( $sp_reserved_root );
	}
	if ( $sp_reserved_root !== ''
		&& ( $sp_storage_path === $sp_reserved_root || strpos( $sp_reserved_root . '/', $sp_storage_path . '/' ) === 0 ) ) {
		return;
	}
}
if ( ! defined( 'SP_ACCELERATOR_CACHE_WEB_PROTECTED' ) || ! SP_ACCELERATOR_CACHE_WEB_PROTECTED ) {
	$sp_document_root = defined( 'SP_ACCELERATOR_DOCUMENT_ROOT' )
		? rtrim( trim( (string) SP_ACCELERATOR_DOCUMENT_ROOT ), '/\\' )
		: rtrim( trim( (string) ( $_SERVER['DOCUMENT_ROOT'] ?? '' ) ), '/\\' );
	$sp_document_root_is_absolute = strpos( $sp_document_root, '/' ) === 0 || preg_match( '/^[a-zA-Z]:[\\\\\/]/', $sp_document_root ) === 1;
	if ( ! $sp_document_root_is_absolute || $sp_document_root === '' || $sp_document_root === DIRECTORY_SEPARATOR || dirname( $sp_document_root ) === $sp_document_root ) {
		return;
	}

	foreach ( [ ABSPATH, WP_CONTENT_DIR, $sp_document_root ] as $sp_web_root ) {
		$sp_web_root = str_replace( '\\', '/', (string) ( realpath( $sp_web_root ) ?: $sp_web_root ) );
		if ( DIRECTORY_SEPARATOR === '\\' ) {
			$sp_web_root = strtolower( $sp_web_root );
		}
		if ( $sp_web_root !== '' && strpos( rtrim( $sp_storage_path, '/' ) . '/', rtrim( $sp_web_root, '/' ) . '/' ) === 0 ) {
			return;
		}
	}
}

$sp_method = strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) );
if ( $sp_method !== 'GET' && $sp_method !== 'HEAD' ) {
	return;
}

if ( ! empty( $_SERVER['HTTP_AUTHORIZATION'] )
	|| ! empty( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] )
	|| ! empty( $_SERVER['PHP_AUTH_USER'] )
	|| ! empty( $_SERVER['PHP_AUTH_PW'] )
	|| ! empty( $_SERVER['PHP_AUTH_DIGEST'] )
) {
	return;
}

$sp_request_cache_control = strtolower( (string) ( $_SERVER['HTTP_CACHE_CONTROL'] ?? '' ) );
$sp_request_pragma        = strtolower( (string) ( $_SERVER['HTTP_PRAGMA'] ?? '' ) );
if ( ! empty( $_SERVER['HTTP_RANGE'] )
	|| preg_match( '/(?:^|,)\s*(?:no-cache|no-store|max-age\s*=\s*0)\b/', $sp_request_cache_control )
	|| strpos( $sp_request_pragma, 'no-cache' ) !== false
) {
	return;
}

$sp_uri   = (string) ( $_SERVER['REQUEST_URI'] ?? '/' );
$sp_parts = parse_url( $sp_uri );
if ( $sp_parts === false ) {
	return;
}

$sp_path  = is_array( $sp_parts ) && isset( $sp_parts['path'] ) ? (string) $sp_parts['path'] : '/';
$sp_query = is_array( $sp_parts ) && isset( $sp_parts['query'] ) ? (string) $sp_parts['query'] : '';
$sp_can_seed_cache = $sp_query === '';
if ( $sp_path === '' || preg_match( '~\.[a-z0-9]{1,8}$~i', $sp_path ) ) {
	return;
}

if ( $sp_query !== '' ) {
	parse_str( $sp_query, $sp_query_args );
	foreach ( array_keys( (array) $sp_query_args ) as $sp_query_key ) {
		$sp_query_key = strtolower( (string) $sp_query_key );
		$sp_ignored   = strpos( $sp_query_key, 'utm_' ) === 0
			|| in_array( $sp_query_key, [ 'gclid', 'fbclid', 'msclkid', '_ga', 'mc_cid', 'mc_eid' ], true );
		if ( ! $sp_ignored ) {
			return;
		}
	}
}

$sp_cookie = (string) ( $_SERVER['HTTP_COOKIE'] ?? '' );
if ( $sp_cookie !== '' ) {
	$sp_cookie_names = [];
	foreach ( explode( ';', $sp_cookie ) as $sp_cookie_pair ) {
		$sp_cookie_name = strtolower( trim( (string) strtok( $sp_cookie_pair, '=' ) ) );
		if ( $sp_cookie_name !== '' ) {
			$sp_cookie_names[] = $sp_cookie_name;
		}
	}

	$sp_default_excluded_cookies = [
		'wordpress_logged_in_',
		'wordpress_sec_',
		'wp-postpass_',
		'comment_author_',
		'woocommerce_cart_hash',
		'woocommerce_items_in_cart',
		'wp_woocommerce_session_',
		'wfwaf-authcookie-',
		'wp-wpml_current_language',
		'wpml_browser_redirect_test',
		'icl_current_language',
		'pll_language',
	];
	$sp_excluded_cookies = array_key_exists( 'exclude_cookies', $sp_config ) && is_array( $sp_config['exclude_cookies'] )
		? $sp_config['exclude_cookies']
		: $sp_default_excluded_cookies;

	foreach ( $sp_excluded_cookies as $sp_cookie_marker ) {
		$sp_cookie_marker = strtolower( trim( (string) $sp_cookie_marker ) );
		if ( $sp_cookie_marker === '' ) {
			continue;
		}
		foreach ( $sp_cookie_names as $sp_cookie_name ) {
			if ( strpos( $sp_cookie_name, $sp_cookie_marker ) !== false ) {
				return;
			}
		}
	}

	if ( ! array_key_exists( 'bypass_unknown_cookies', $sp_config ) || ! empty( $sp_config['bypass_unknown_cookies'] ) ) {
		$sp_default_allowed_cookies = [ '_ga', '_gid', '_gat', '_gac_', '_gcl_', '_fbp', '_clck', '_clsk' ];
		$sp_allowed_cookies = array_key_exists( 'allow_cookies', $sp_config ) && is_array( $sp_config['allow_cookies'] )
			? $sp_config['allow_cookies']
			: $sp_default_allowed_cookies;

		foreach ( $sp_cookie_names as $sp_cookie_name ) {
			$sp_cookie_is_allowed = false;
			foreach ( $sp_allowed_cookies as $sp_cookie_marker ) {
				$sp_cookie_marker = strtolower( trim( (string) $sp_cookie_marker ) );
				if ( $sp_cookie_marker !== '' && strpos( $sp_cookie_name, $sp_cookie_marker ) === 0 ) {
					$sp_cookie_is_allowed = true;
					break;
				}
			}
			if ( ! $sp_cookie_is_allowed ) {
				return;
			}
		}
	}
}

$sp_host_raw = strtolower( trim( (string) ( $_SERVER['HTTP_HOST'] ?? '' ) ) );
$sp_host     = preg_replace( '/[^a-z0-9.\-:\[\]]/i', '', $sp_host_raw ) ?: '';
if ( $sp_host === '' || $sp_host !== $sp_host_raw ) {
	return;
}

$sp_allowed_hosts = [];
foreach ( (array) ( $sp_config['hosts'] ?? [] ) as $sp_allowed_host ) {
	$sp_allowed_host = strtolower( trim( (string) $sp_allowed_host ) );
	if ( $sp_allowed_host !== '' ) {
		$sp_allowed_hosts[] = $sp_allowed_host;
	}
}
if ( ! in_array( $sp_host, $sp_allowed_hosts, true ) ) {
	return;
}

foreach ( (array) ( $sp_config['exclude_paths'] ?? [] ) as $sp_rule ) {
	$sp_rule = trim( (string) $sp_rule );
	if ( $sp_rule === '' ) {
		continue;
	}
	if ( $sp_rule[0] === '~' ) {
		if ( @preg_match( $sp_rule, $sp_path ) === 1 ) {
			return;
		}
	} elseif ( strpos( $sp_path, $sp_rule ) === 0 ) {
		return;
	}
}

$sp_https = ( ! empty( $_SERVER['HTTPS'] ) && strtolower( (string) $_SERVER['HTTPS'] ) !== 'off' )
	|| (int) ( $_SERVER['SERVER_PORT'] ?? 0 ) === 443;
if ( ! $sp_https && defined( 'SP_ACCELERATOR_TRUST_FORWARDED_PROTO' ) && SP_ACCELERATOR_TRUST_FORWARDED_PROTO && isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) ) {
	$sp_forwarded_proto = explode( ',', (string) $_SERVER['HTTP_X_FORWARDED_PROTO'] );
	$sp_https           = strtolower( trim( (string) ( $sp_forwarded_proto[0] ?? '' ) ) ) === 'https';
}
$sp_scheme = $sp_https ? 'https' : 'http';
$sp_url    = $sp_scheme . '://' . $sp_host . $sp_path;
$sp_hash   = hash( 'sha256', $sp_url );
$sp_root   = $sp_root !== '' ? $sp_root : rtrim( WP_CONTENT_DIR, '/\\' ) . '/cache/sp-accelerator';

// Only a short-lived, URL- and generation-bound HMAC from the internal warmer
// may bypass early delivery. Static/forged headers are treated normally.
$sp_warm_token          = trim( (string) ( $_SERVER['HTTP_X_SP_CACHE_WARM'] ?? '' ) );
$sp_expected_warm_token = trim( (string) ( $sp_config['warm_token'] ?? '' ) );
$sp_warm_parts          = explode( ':', $sp_warm_token, 2 );
if ( count( $sp_warm_parts ) === 2
	&& ctype_digit( $sp_warm_parts[0] )
	&& preg_match( '/^[a-f0-9]{64}$/i', $sp_warm_parts[1] )
	&& $sp_expected_warm_token !== ''
) {
	$sp_warm_timestamp  = (int) $sp_warm_parts[0];
	$sp_warm_generation = preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) ( $sp_config['generation'] ?? '1' ) ) ?: '1';
	$sp_warm_expected   = hash_hmac( 'sha256', $sp_warm_timestamp . '|' . $sp_warm_generation . '|' . $sp_url, $sp_expected_warm_token );
	if ( $sp_warm_timestamp > 0 && abs( time() - $sp_warm_timestamp ) <= 300 && hash_equals( $sp_warm_expected, strtolower( $sp_warm_parts[1] ) ) ) {
		return;
	}
}

$sp_entry_paths = static function ( string $generation ) use ( $sp_root, $sp_hash ): array {
	$generation = preg_replace( '/[^a-zA-Z0-9_-]/', '', $generation ) ?: '1';
	$directory  = rtrim( $sp_root, '/\\' ) . '/pages/' . $generation . '/' . substr( $sp_hash, 0, 2 ) . '/' . substr( $sp_hash, 2, 2 );
	$base       = $directory . '/' . $sp_hash;
	return [
		'html'       => $base . '.html',
		'gzip'       => $base . '.html.gz',
		'meta'       => $base . '.json',
		'lock'       => $base . '.lock',
		'write_lock' => $base . '.write-lock',
	];
};

$sp_read_meta = static function ( string $path ): array {
	if ( ! is_readable( $path ) ) {
		return [];
	}
	$json = file_get_contents( $path );
	$data = is_string( $json ) ? json_decode( $json, true ) : null;
	return is_array( $data ) ? $data : [];
};

$sp_owned_lock = null;
$sp_release_owned_lock = static function () use ( &$sp_owned_lock ): void {
	if ( ! is_array( $sp_owned_lock ) || empty( $sp_owned_lock['path'] ) || empty( $sp_owned_lock['token'] ) ) {
		return;
	}
	$handle = is_link( $sp_owned_lock['path'] ) ? false : @fopen( $sp_owned_lock['path'], 'r+' );
	if ( $handle !== false && @flock( $handle, LOCK_EX ) ) {
		@rewind( $handle );
		$current = stream_get_contents( $handle );
		if ( is_string( $current ) && hash_equals( (string) $sp_owned_lock['token'], trim( $current ) ) ) {
			@ftruncate( $handle, 0 );
			@rewind( $handle );
			@fflush( $handle );
		}
		@flock( $handle, LOCK_UN );
		@fclose( $handle );
	} elseif ( is_resource( $handle ) ) {
		@fclose( $handle );
	}
	$sp_owned_lock = null;
};
register_shutdown_function( $sp_release_owned_lock );

// Return 1 when this request acquired the lock, 0 when another request owns it,
// and -1 when the lock cannot be created safely.
$sp_acquire_lock = static function ( string $path ) use ( &$sp_owned_lock ): int {
	$directory = dirname( $path );
	if ( ! is_dir( $directory ) && ! @mkdir( $directory, 0755, true ) && ! is_dir( $directory ) ) {
		return -1;
	}

	try {
		$random = bin2hex( random_bytes( 12 ) );
	} catch ( Throwable $error ) {
		$random = str_replace( '.', '', uniqid( '', true ) );
	}
	$token = time() . ':' . $random;
	if ( is_link( $path ) ) {
		return -1;
	}
	$handle = @fopen( $path, 'c+' );
	if ( $handle === false || ! @flock( $handle, LOCK_EX ) ) {
		if ( is_resource( $handle ) ) {
			@fclose( $handle );
		}
		return is_file( $path ) ? 0 : -1;
	}
	@rewind( $handle );
	$current = stream_get_contents( $handle );
	$current = is_string( $current ) ? trim( $current ) : '';
	$parts   = explode( ':', $current, 2 );
	$started = isset( $parts[0] ) && ctype_digit( $parts[0] ) ? (int) $parts[0] : 0;
	$modified = filemtime( $path );
	$fresh = $current !== '' && ( $started > 0
		? time() - $started < 120
		: ( $modified !== false && time() - $modified < 120 ) );
	if ( $fresh ) {
		@flock( $handle, LOCK_UN );
		@fclose( $handle );
		return 0;
	}
	if ( ! @ftruncate( $handle, 0 ) || ! @rewind( $handle ) || @fwrite( $handle, $token ) !== strlen( $token ) || ! @fflush( $handle ) ) {
		@ftruncate( $handle, 0 );
		@fflush( $handle );
		@flock( $handle, LOCK_UN );
		@fclose( $handle );
		return -1;
	}
	@flock( $handle, LOCK_UN );
	@fclose( $handle );
	$sp_owned_lock = [ 'path' => $path, 'token' => $token ];
	$GLOBALS['sp_accelerator_revalidation_lock'] = $sp_owned_lock;
	return 1;
};

$sp_wait_for_file = static function ( string $path ): bool {
	$deadline = microtime( true ) + 5.0;
	do {
		clearstatcache( true, $path );
		if ( is_readable( $path ) ) {
			return true;
		}
		usleep( 25000 );
	} while ( microtime( true ) < $deadline );
	return is_readable( $path );
};

$sp_wait_for_refresh = static function ( string $path, $previous_modified ): bool {
	$deadline = microtime( true ) + 5.0;
	do {
		clearstatcache( true, $path );
		$modified = is_readable( $path ) ? filemtime( $path ) : false;
		if ( $modified !== false && ( $previous_modified === false || $modified > (int) $previous_modified ) ) {
			return true;
		}
		usleep( 25000 );
	} while ( microtime( true ) < $deadline );
	return false;
};

$sp_current_generation  = preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) ( $sp_config['generation'] ?? '1' ) ) ?: '1';
$sp_previous_generation = preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) ( $sp_config['previous_generation'] ?? '' ) ) ?: '';
$sp_current_paths       = $sp_entry_paths( $sp_current_generation );
$sp_paths               = $sp_current_paths;
$sp_served_generation   = $sp_current_generation;
$sp_generation_stale    = false;

if ( ! is_readable( $sp_paths['html'] ) ) {
	$sp_changed_at = max( 0, (int) ( $sp_config['generation_changed_at'] ?? 0 ) );
	$sp_grace      = max( 0, (int) ( $sp_config['generation_stale_ttl'] ?? 3600 ) );
	if ( $sp_previous_generation !== ''
		&& $sp_previous_generation !== $sp_current_generation
		&& $sp_changed_at > 0
		&& $sp_grace > 0
		&& time() - $sp_changed_at <= $sp_grace
	) {
		$sp_previous_paths = $sp_entry_paths( $sp_previous_generation );
		$sp_previous_modified = is_readable( $sp_previous_paths['html'] ) ? filemtime( $sp_previous_paths['html'] ) : false;
		$sp_previous_meta = $sp_read_meta( $sp_previous_paths['meta'] );
		$sp_previous_ttl = max( 1, (int) ( $sp_previous_meta['ttl'] ?? ( $sp_config['ttl'] ?? 3600 ) ) );
		$sp_previous_stale_ttl = max( 0, (int) ( $sp_previous_meta['stale_ttl'] ?? ( $sp_config['stale_ttl'] ?? 21600 ) ) );
		if ( $sp_previous_modified !== false && time() - $sp_previous_modified <= $sp_previous_ttl + $sp_previous_stale_ttl ) {
			$sp_paths             = $sp_previous_paths;
			$sp_served_generation = $sp_previous_generation;
			$sp_generation_stale  = true;
		}
	}

	if ( ! $sp_generation_stale ) {
		if ( ! $sp_can_seed_cache ) {
			return;
		}
		$sp_lock_result = $sp_acquire_lock( $sp_current_paths['lock'] );
		if ( $sp_lock_result !== 0 ) {
			if ( $sp_lock_result < 0 ) {
				$GLOBALS['sp_accelerator_cache_contended'] = true;
			}
			return;
		}
		if ( ! $sp_wait_for_file( $sp_current_paths['html'] ) ) {
			$GLOBALS['sp_accelerator_cache_contended'] = true;
			return;
		}
		$sp_paths = $sp_current_paths;
	}
}

$sp_read_lock = @fopen( $sp_paths['write_lock'], 'c' );
if ( $sp_read_lock === false || ! @flock( $sp_read_lock, LOCK_SH ) ) {
	if ( is_resource( $sp_read_lock ) ) {
		@fclose( $sp_read_lock );
	}
	return;
}

try {
if ( ! is_readable( $sp_paths['html'] ) ) {
	return;
}
$sp_modified = filemtime( $sp_paths['html'] );
if ( $sp_modified === false ) {
	return;
}

$sp_meta      = $sp_read_meta( $sp_paths['meta'] );
$sp_age       = max( 0, time() - $sp_modified );
$sp_ttl       = max( 1, (int) ( $sp_meta['ttl'] ?? ( $sp_config['ttl'] ?? 3600 ) ) );
$sp_stale_ttl = max( 0, (int) ( $sp_meta['stale_ttl'] ?? ( $sp_config['stale_ttl'] ?? 21600 ) ) );
$sp_status    = $sp_generation_stale || $sp_age > $sp_ttl ? 'STALE' : 'HIT';

if ( $sp_age > $sp_ttl + $sp_stale_ttl ) {
	if ( ! $sp_can_seed_cache ) {
		return;
	}
	$sp_lock_result = $sp_acquire_lock( $sp_current_paths['lock'] );
	if ( $sp_lock_result !== 0 ) {
		if ( $sp_lock_result < 0 ) {
			$GLOBALS['sp_accelerator_cache_contended'] = true;
		}
		return;
	}
	@flock( $sp_read_lock, LOCK_UN );
	@fclose( $sp_read_lock );
	$sp_read_lock = null;
	if ( $sp_wait_for_refresh( $sp_current_paths['html'], $sp_generation_stale ? false : $sp_modified ) ) {
		// Let WordPress continue; its runtime cache reader performs a fresh,
		// header-aware lookup before the template is rendered.
		$GLOBALS['sp_accelerator_cache_contended'] = true;
		return;
	}
	$GLOBALS['sp_accelerator_cache_contended'] = true;
	return;
}

if ( $sp_generation_stale ) {
	if ( $sp_can_seed_cache ) {
		$sp_lock_result = $sp_acquire_lock( $sp_current_paths['lock'] );
		if ( $sp_lock_result === 1 ) {
			// A generation switch may change headers/assets. The elected request
			// rebuilds synchronously; concurrent visitors receive coherent old data.
			return;
		}
	}
} elseif ( $sp_age > $sp_ttl ) {
	if ( $sp_can_seed_cache ) {
		$sp_lock_result = $sp_acquire_lock( $sp_paths['lock'] );
		if ( $sp_lock_result === 1 ) {
			return;
		}
	}
}

$sp_accepts_gzip = static function (): bool {
	$header = strtolower( trim( (string) ( $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '' ) ) );
	if ( $header === '' ) {
		return false;
	}

	$explicit = null;
	$wildcard = null;
	foreach ( explode( ',', $header ) as $part ) {
		$tokens = array_map( 'trim', explode( ';', $part ) );
		$name   = (string) array_shift( $tokens );
		if ( $name === '' ) {
			continue;
		}

		$q = 1.0;
		foreach ( $tokens as $token ) {
			if ( ! preg_match( '/^q\s*=\s*(.*)$/i', $token, $quality ) ) {
				continue;
			}
			$value = trim( (string) $quality[1] );
			$q     = preg_match( '/^(?:0(?:\.\d{0,3})?|1(?:\.0{0,3})?)$/', $value ) ? (float) $value : 0.0;
		}

		if ( $name === 'gzip' || $name === 'x-gzip' ) {
			$explicit = $explicit === null ? $q : max( $explicit, $q );
		} elseif ( $name === '*' ) {
			$wildcard = $wildcard === null ? $q : max( $wildcard, $q );
		}
	}

	return $explicit !== null ? $explicit > 0.0 : $wildcard !== null && $wildcard > 0.0;
};

$sp_gzip_modified = is_readable( $sp_paths['gzip'] ) ? filemtime( $sp_paths['gzip'] ) : false;
$sp_use_gzip      = ! empty( $sp_config['gzip'] )
	&& $sp_accepts_gzip()
	&& $sp_gzip_modified !== false
	&& $sp_gzip_modified >= $sp_modified;
$sp_file = $sp_use_gzip ? $sp_paths['gzip'] : $sp_paths['html'];
$sp_size = filesize( $sp_file );
if ( isset( $sp_meta['content_hash'] ) && is_string( $sp_meta['content_hash'] ) && preg_match( '/^[a-f0-9]{64}$/', $sp_meta['content_hash'] ) ) {
	$sp_content_hash = substr( $sp_meta['content_hash'], 0, 20 );
} else {
	$sp_file_hash    = hash_file( 'sha256', $sp_paths['html'] );
	$sp_content_hash = is_string( $sp_file_hash ) ? substr( $sp_file_hash, 0, 20 ) : substr( hash( 'sha256', $sp_served_generation . '|' . $sp_modified . '|' . (string) $sp_size ), 0, 20 );
}
$sp_etag = '"sp-' . substr( $sp_hash, 0, 16 ) . '-' . substr( hash( 'sha256', $sp_served_generation ), 0, 8 ) . '-' . $sp_content_hash . ( $sp_use_gzip ? '-gz' : '-id' ) . '"';

$sp_content_type = isset( $sp_meta['content_type'] ) && is_string( $sp_meta['content_type'] )
	? trim( $sp_meta['content_type'] )
	: '';
if ( ! preg_match( '~^text/html(?:\s*;\s*charset=[a-zA-Z0-9._-]+)?$~i', $sp_content_type ) ) {
	$sp_content_type = 'text/html; charset=UTF-8';
}

$sp_safe_header_names = [
	'content-language',
	'content-security-policy',
	'content-security-policy-report-only',
	'cross-origin-embedder-policy',
	'cross-origin-opener-policy',
	'cross-origin-resource-policy',
	'link',
	'permissions-policy',
	'referrer-policy',
	'strict-transport-security',
	'x-content-type-options',
	'x-frame-options',
	'x-robots-tag',
];
foreach ( (array) ( $sp_meta['headers'] ?? [] ) as $sp_header_line ) {
	$sp_header_line = is_string( $sp_header_line ) ? trim( $sp_header_line ) : '';
	if ( $sp_header_line === ''
		|| strpos( $sp_header_line, "\r" ) !== false
		|| strpos( $sp_header_line, "\n" ) !== false
		|| strpos( $sp_header_line, ':' ) === false
	) {
		continue;
	}
	$sp_header_name = strtolower( trim( substr( $sp_header_line, 0, strpos( $sp_header_line, ':' ) ) ) );
	if ( ! in_array( $sp_header_name, $sp_safe_header_names, true ) ) {
		continue;
	}
	$sp_preserve_multiple = in_array( $sp_header_name, [ 'link', 'content-security-policy', 'content-security-policy-report-only' ], true );
	header( $sp_header_line, ! $sp_preserve_multiple );
}

$sp_browser_ttl = max( 0, min( 3600, (int) ( $sp_config['browser_ttl'] ?? 0 ) ) );
header( 'Content-Type: ' . $sp_content_type );
header( 'Cache-Control: public, max-age=' . $sp_browser_ttl . ', must-revalidate' );
header( 'Vary: Accept-Encoding' );
header( 'ETag: ' . $sp_etag );
header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', $sp_modified ) . ' GMT' );
header( 'Age: ' . $sp_age );
header( 'X-SP-Cache: ' . $sp_status );
if ( $sp_use_gzip ) {
	header( 'Content-Encoding: gzip' );
}
if ( $sp_size !== false ) {
	header( 'Content-Length: ' . $sp_size );
}

$sp_is_not_modified = static function ( string $etag, int $modified ): bool {
	$if_none_match = trim( (string) ( $_SERVER['HTTP_IF_NONE_MATCH'] ?? '' ) );
	if ( $if_none_match !== '' ) {
		$expected = preg_replace( '/^W\//i', '', $etag );
		foreach ( explode( ',', $if_none_match ) as $candidate ) {
			$candidate = trim( $candidate );
			if ( $candidate === '*' || preg_replace( '/^W\//i', '', $candidate ) === $expected ) {
				return true;
			}
		}
		return false;
	}

	$if_modified_since = trim( (string) ( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '' ) );
	$since             = $if_modified_since !== '' ? strtotime( $if_modified_since ) : false;
	return $since !== false && $since >= $modified;
};

if ( $sp_is_not_modified( $sp_etag, $sp_modified ) ) {
	http_response_code( 304 );
	header_remove( 'Content-Length' );
	exit;
}

if ( $sp_method !== 'HEAD' ) {
	readfile( $sp_file );
}

exit;
} finally {
	if ( is_resource( $sp_read_lock ) ) {
		@flock( $sp_read_lock, LOCK_UN );
		@fclose( $sp_read_lock );
	}
}
