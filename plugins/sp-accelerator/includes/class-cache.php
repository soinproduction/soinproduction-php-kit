<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SP_Accelerator_Cache {
	private const REVALIDATION_LOCK_TTL = 120;
	private const COLLAPSE_WAIT_SECONDS = 5.0;

	/** @var SP_Accelerator_Config */
	private $config;

	/** @var SP_Accelerator_Request */
	private $request;

	/** @var bool */
	private $capturing = false;

	/** @var bool */
	private $purged = false;

	/** @var array{path:string,token:string}|null */
	private $owned_revalidation_lock = null;

	public function __construct( SP_Accelerator_Config $config, SP_Accelerator_Request $request ) {
		$this->config  = $config;
		$this->request = $request;
	}

	public function register(): void {
		add_action( 'template_redirect', [ $this, 'start' ], -9999 );
		add_action( 'save_post', [ $this, 'invalidate_post' ], 20, 3 );
		add_action( 'deleted_post', [ $this, 'invalidate_all' ] );
		add_action( 'wp_update_nav_menu', [ $this, 'invalidate_all' ] );
		add_action( 'customize_save_after', [ $this, 'invalidate_all' ] );
		add_action( 'set_object_terms', [ $this, 'invalidate_all' ], 20 );
		add_action( 'acf/save_post', [ $this, 'invalidate_acf_options' ], 30 );
		add_action( 'sp_accelerator_daily_cleanup', [ $this, 'cleanup_old_generations' ] );
		add_action( 'init', [ $this, 'schedule_cleanup' ], 20 );
	}

	public function start(): void {
		$this->adopt_early_lock_context();
		if ( ! $this->config->enabled( 'page_cache' ) || $this->has_foreign_dropin() || ! $this->request->is_cacheable_wordpress_request() ) {
			$this->release_owned_revalidation_lock();
			return;
		}

		// A correctly installed advanced-cache.php has already checked for a hit.
		// The runtime lookup keeps caching useful before the drop-in is installed.
		$canonical_url   = $this->request->canonical_request_url();
		$is_warm_request = $this->config->is_authenticated_warm_request( $canonical_url );
		$early_contention = ! empty( $GLOBALS['sp_accelerator_cache_contended'] );
		if ( ! $is_warm_request && ( ! $this->is_own_dropin_active() || $early_contention ) && $this->serve_url( $canonical_url ) ) {
			exit;
		}
		if ( ! empty( $GLOBALS['sp_accelerator_cache_contended'] ) ) {
			return;
		}

		$this->capturing = true;
		ob_start( [ $this, 'capture' ] );
	}

	public function capture( string $html ): string {
		if ( ! $this->capturing ) {
			return $html;
		}

		$this->capturing = false;
		$cacheable       = $this->can_store_response( $html );
		$output          = $cacheable && $this->config->enabled( 'minify_html' )
			? $this->minify_html( $html )
			: $html;

		if ( $cacheable ) {
			$stored = $this->store( $this->request->canonical_request_url(), $output );
			if ( $stored && ! headers_sent() ) {
				header( 'X-SP-Cache: MISS' );
			}
		} else {
			$this->release_owned_revalidation_lock();
		}

		return $output;
	}

	private function can_store_response( string $html ): bool {
		if ( ! $this->config->enabled( 'page_cache' ) ) {
			return false;
		}
		$status = (int) http_response_code();
		if ( $status !== 200 || ! $this->request->can_seed_cache() || strlen( $html ) < 256 || stripos( $html, '</html>' ) === false ) {
			return false;
		}

		foreach ( headers_list() as $header ) {
			$header = strtolower( trim( (string) $header ) );
			if ( strpos( $header, 'set-cookie:' ) === 0 ) {
				return false;
			}
			if ( strpos( $header, 'cache-control:' ) === 0 && preg_match( '/\b(?:private|no-cache|no-store)\b/', $header ) ) {
				return false;
			}
			if ( strpos( $header, 'pragma:' ) === 0 && strpos( $header, 'no-cache' ) !== false ) {
				return false;
			}
			if ( strpos( $header, 'vary:' ) === 0 ) {
				$value = trim( substr( $header, 5 ) );
				if ( $value !== '' && $value !== 'accept-encoding' ) {
					return false;
				}
			}
			if ( strpos( $header, 'content-type:' ) === 0 && strpos( $header, 'text/html' ) === false ) {
				return false;
			}
		}

		return $this->request->is_cacheable_wordpress_request();
	}

	public function serve_current_request(): bool {
		return $this->serve_url( $this->request->canonical_request_url() );
	}

	public function serve_url( string $canonical_url ): bool {
		$current_generation = preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $this->config->get( 'generation', '1' ) ) ?: '1';
		$current_paths      = $this->entry_paths( $canonical_url, $current_generation );
		$paths              = $current_paths;
		$served_generation = $current_generation;
		$generation_stale  = false;
		$can_revalidate    = $this->request->can_seed_cache();
		$status            = 'HIT';

		if ( ! is_readable( $paths['html'] ) ) {
			$previous   = preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $this->config->get( 'previous_generation', '' ) ) ?: '';
			$changed_at = max( 0, (int) $this->config->get( 'generation_changed_at', 0 ) );
			$grace      = max( 0, (int) $this->config->get( 'generation_stale_ttl', 3600 ) );

			if ( $previous !== '' && $previous !== $current_generation && $changed_at > 0 && $grace > 0 && time() - $changed_at <= $grace ) {
				$previous_paths = $this->entry_paths( $canonical_url, $previous );
				if ( $this->entry_is_within_hard_limit( $previous_paths ) ) {
					$paths             = $previous_paths;
					$served_generation = $previous;
					$generation_stale  = true;
					$status            = 'STALE';
				}
			}

			if ( ! $generation_stale ) {
				if ( ! $can_revalidate ) {
					return false;
				}
				$lock_result = $this->acquire_revalidation_lock( $current_paths['lock'] );
				if ( $lock_result !== 0 ) {
					if ( $lock_result < 0 ) {
						$GLOBALS['sp_accelerator_cache_contended'] = true;
					}
					return false;
				}
				if ( ! $this->wait_for_cache_file( $current_paths['html'] ) ) {
					$GLOBALS['sp_accelerator_cache_contended'] = true;
					return false;
				}
				$paths = $current_paths;
			}
		}

		$read_lock = @fopen( $paths['write_lock'], 'c' );
		if ( $read_lock === false || ! @flock( $read_lock, LOCK_SH ) ) {
			if ( is_resource( $read_lock ) ) {
				fclose( $read_lock );
			}
			return false;
		}

		try {
			if ( ! is_readable( $paths['html'] ) ) {
				return false;
			}
			$modified = filemtime( $paths['html'] );
			if ( $modified === false ) {
				return false;
			}

			$meta      = $this->read_meta( $paths['meta'] );
			$age       = max( 0, time() - $modified );
			$ttl       = max( 1, (int) ( $meta['ttl'] ?? $this->config->get( 'cache_ttl', 3600 ) ) );
			$stale_ttl = max( 0, (int) ( $meta['stale_ttl'] ?? $this->config->get( 'stale_ttl', 21600 ) ) );

			if ( $age > $ttl + $stale_ttl ) {
				if ( ! $can_revalidate ) {
					return false;
				}
				$lock_result = $this->acquire_revalidation_lock( $current_paths['lock'] );
				if ( $lock_result !== 0 ) {
					if ( $lock_result < 0 ) {
						$GLOBALS['sp_accelerator_cache_contended'] = true;
					}
					return false;
				}

				@flock( $read_lock, LOCK_UN );
				fclose( $read_lock );
				$read_lock = null;
				if ( $this->wait_for_cache_refresh( $current_paths['html'], $generation_stale ? false : $modified ) ) {
					return $this->serve_url( $canonical_url );
				}
				$GLOBALS['sp_accelerator_cache_contended'] = true;
				return false;
			}

			if ( $generation_stale ) {
				$status = 'STALE';
				if ( $can_revalidate ) {
					$lock_result = $this->acquire_revalidation_lock( $current_paths['lock'] );
					// The elected request rebuilds synchronously. Concurrent visitors may
					// keep using the coherent previous entry, but never past its hard TTL.
					if ( $lock_result === 1 ) {
						return false;
					}
				}
			} elseif ( $age > $ttl ) {
				$status = 'STALE';
				if ( $can_revalidate ) {
					$lock_result = $this->acquire_revalidation_lock( $paths['lock'] );
					if ( $lock_result === 1 ) {
						return false;
					}
				}
			}

			$gzip_modified = is_readable( $paths['gzip'] ) ? filemtime( $paths['gzip'] ) : false;
			$serve_path = $paths['html'];
			$gzip       = ! empty( $this->config->get( 'gzip_cache' ) )
				&& $this->accepts_gzip()
				&& $gzip_modified !== false
				&& $gzip_modified >= $modified;

			if ( $gzip ) {
				$serve_path = $paths['gzip'];
			}

			$size = filesize( $serve_path );
			if ( isset( $meta['content_hash'] ) && is_string( $meta['content_hash'] ) && preg_match( '/^[a-f0-9]{64}$/', $meta['content_hash'] ) ) {
				$content_hash = substr( $meta['content_hash'], 0, 20 );
			} else {
				$file_hash    = hash_file( 'sha256', $paths['html'] );
				$content_hash = is_string( $file_hash ) ? substr( $file_hash, 0, 20 ) : substr( hash( 'sha256', $served_generation . '|' . $modified . '|' . (string) $size ), 0, 20 );
			}
			$etag = '"sp-' . substr( $this->request->cache_hash( $canonical_url ), 0, 16 ) . '-' . substr( hash( 'sha256', $served_generation ), 0, 8 ) . '-' . $content_hash . ( $gzip ? '-gz' : '-id' ) . '"';

			if ( ! headers_sent() ) {
				$this->send_stored_headers( isset( $meta['headers'] ) && is_array( $meta['headers'] ) ? $meta['headers'] : [] );
				$content_type = isset( $meta['content_type'] ) && is_string( $meta['content_type'] ) ? trim( $meta['content_type'] ) : '';
				if ( ! preg_match( '~^text/html(?:\s*;\s*charset=[a-zA-Z0-9._-]+)?$~i', $content_type ) ) {
					$content_type = 'text/html; charset=UTF-8';
				}
				header( 'Content-Type: ' . $content_type );
				$browser_ttl = max( 0, min( HOUR_IN_SECONDS, (int) $this->config->get( 'browser_cache_ttl', 0 ) ) );
				header( 'Cache-Control: public, max-age=' . $browser_ttl . ', must-revalidate' );
				header( 'Vary: Accept-Encoding' );
				header( 'ETag: ' . $etag );
				header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', $modified ) . ' GMT' );
				header( 'Age: ' . $age );
				header( 'X-SP-Cache: ' . $status );
				if ( $gzip ) {
					header( 'Content-Encoding: gzip' );
				}
				if ( $size !== false ) {
					header( 'Content-Length: ' . $size );
				}
			}

			if ( $this->is_not_modified( $etag, $modified ) ) {
				http_response_code( 304 );
				header_remove( 'Content-Length' );
				return true;
			}

			if ( strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) !== 'HEAD' ) {
				readfile( $serve_path );
			}

			return true;
		} finally {
			if ( is_resource( $read_lock ) ) {
				@flock( $read_lock, LOCK_UN );
				fclose( $read_lock );
			}
		}
	}

	private function accepts_gzip(): bool {
		$header   = strtolower( trim( (string) ( $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '' ) ) );
		$explicit = null;
		$wildcard = null;
		if ( $header === '' ) {
			return false;
		}
		foreach ( explode( ',', $header ) as $part ) {
			$tokens = array_map( 'trim', explode( ';', $part ) );
			$name   = (string) array_shift( $tokens );
			if ( $name === '' ) {
				continue;
			}
			$q      = 1.0;
			foreach ( $tokens as $token ) {
				if ( preg_match( '/^q\s*=\s*(.*)$/i', $token, $match ) ) {
					$value = trim( (string) $match[1] );
					$q     = preg_match( '/^(?:0(?:\.\d{0,3})?|1(?:\.0{0,3})?)$/', $value ) ? (float) $value : 0.0;
				}
			}
			if ( $name === 'gzip' || $name === 'x-gzip' ) {
				$explicit = $explicit === null ? $q : max( $explicit, $q );
			}
			if ( $name === '*' ) {
				$wildcard = $wildcard === null ? $q : max( $wildcard, $q );
			}
		}
		return $explicit !== null ? $explicit > 0.0 : $wildcard !== null && $wildcard > 0.0;
	}

	/** @return array<string,mixed> */
	private function read_meta( string $path ): array {
		if ( ! is_readable( $path ) ) {
			return [];
		}
		$json = file_get_contents( $path );
		$data = is_string( $json ) ? json_decode( $json, true ) : null;
		return is_array( $data ) ? $data : [];
	}

	/** @param mixed[] $headers */
	private function send_stored_headers( array $headers ): void {
		$allowed = $this->cacheable_header_names();
		foreach ( $headers as $line ) {
			$line = is_string( $line ) ? trim( $line ) : '';
			if ( $line === '' || strpos( $line, "\r" ) !== false || strpos( $line, "\n" ) !== false || strpos( $line, ':' ) === false ) {
				continue;
			}
			$name              = strtolower( trim( (string) strtok( $line, ':' ) ) );
			if ( ! in_array( $name, $allowed, true ) ) {
				continue;
			}
			$preserve_multiple = in_array( $name, [ 'link', 'content-security-policy', 'content-security-policy-report-only' ], true );
			header( $line, ! $preserve_multiple );
		}
	}

	private function is_not_modified( string $etag, int $modified ): bool {
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
	}

	/**
	 * @return int 1 when this request owns the lock, 0 when another request owns
	 *             it, and -1 when the lock cannot be created safely.
	 */
	private function acquire_revalidation_lock( string $path ): int {
		$directory = dirname( $path );
		if ( ! is_dir( $directory ) && ! wp_mkdir_p( $directory ) ) {
			return -1;
		}

		if ( is_link( $path ) ) {
			return -1;
		}

		$token  = time() . ':' . wp_generate_password( 20, false, false );
		// The pathname is intentionally never unlinked. Every contender serializes
		// on the same inode; release turns the file into an immediately claimable
		// empty lock instead of creating an unlink/recreate race.
		$handle = @fopen( $path, 'c+' );
		if ( $handle === false || ! @flock( $handle, LOCK_EX ) ) {
			if ( is_resource( $handle ) ) {
				fclose( $handle );
			}
			return is_file( $path ) ? 0 : -1;
		}
		rewind( $handle );
		$current = stream_get_contents( $handle );
		$current = is_string( $current ) ? trim( $current ) : '';
		$parts   = explode( ':', $current, 2 );
		$started = isset( $parts[0] ) && ctype_digit( $parts[0] ) ? (int) $parts[0] : 0;
		$modified = filemtime( $path );
		$fresh = $current !== '' && ( $started > 0
			? time() - $started < self::REVALIDATION_LOCK_TTL
			: ( $modified !== false && time() - $modified < self::REVALIDATION_LOCK_TTL ) );
		if ( $fresh ) {
			@flock( $handle, LOCK_UN );
			fclose( $handle );
			return 0;
		}
		if ( ! ftruncate( $handle, 0 ) || rewind( $handle ) === false || fwrite( $handle, $token ) !== strlen( $token ) || ! fflush( $handle ) ) {
			@ftruncate( $handle, 0 );
			@fflush( $handle );
			@flock( $handle, LOCK_UN );
			fclose( $handle );
			return -1;
		}
		@flock( $handle, LOCK_UN );
		fclose( $handle );
		return $this->remember_owned_revalidation_lock( $path, $token );
	}

	private function remember_owned_revalidation_lock( string $path, string $token ): int {
		$this->owned_revalidation_lock = [ 'path' => $path, 'token' => $token ];
		$GLOBALS['sp_accelerator_revalidation_lock'] = $this->owned_revalidation_lock;
		register_shutdown_function( [ $this, 'release_owned_revalidation_lock' ] );
		return 1;
	}

	public function release_owned_revalidation_lock(): void {
		$lock = $this->owned_revalidation_lock;
		if ( ! is_array( $lock ) && isset( $GLOBALS['sp_accelerator_revalidation_lock'] ) && is_array( $GLOBALS['sp_accelerator_revalidation_lock'] ) ) {
			$lock = $GLOBALS['sp_accelerator_revalidation_lock'];
		}
		if ( ! is_array( $lock ) || empty( $lock['path'] ) || empty( $lock['token'] ) ) {
			return;
		}

		$handle = is_link( $lock['path'] ) ? false : @fopen( $lock['path'], 'r+' );
		if ( $handle !== false && @flock( $handle, LOCK_EX ) ) {
			rewind( $handle );
			$current = stream_get_contents( $handle );
			if ( is_string( $current ) && hash_equals( (string) $lock['token'], trim( $current ) ) ) {
				@ftruncate( $handle, 0 );
				@rewind( $handle );
				@fflush( $handle );
			}
			@flock( $handle, LOCK_UN );
			fclose( $handle );
		} elseif ( is_resource( $handle ) ) {
			fclose( $handle );
		}
		$this->owned_revalidation_lock = null;
		unset( $GLOBALS['sp_accelerator_revalidation_lock'] );
	}

	private function wait_for_cache_file( string $path ): bool {
		$deadline = microtime( true ) + self::COLLAPSE_WAIT_SECONDS;
		do {
			clearstatcache( true, $path );
			if ( is_readable( $path ) ) {
				return true;
			}
			usleep( 25000 );
		} while ( microtime( true ) < $deadline );

		return is_readable( $path );
	}

	private function wait_for_cache_refresh( string $path, $previous_modified ): bool {
		$deadline = microtime( true ) + self::COLLAPSE_WAIT_SECONDS;
		do {
			clearstatcache( true, $path );
			$modified = is_readable( $path ) ? filemtime( $path ) : false;
			if ( $modified !== false && ( $previous_modified === false || $modified > (int) $previous_modified ) ) {
				return true;
			}
			usleep( 25000 );
		} while ( microtime( true ) < $deadline );

		return false;
	}

	private function adopt_early_lock_context(): void {
		if ( isset( $GLOBALS['sp_accelerator_revalidation_lock'] ) && is_array( $GLOBALS['sp_accelerator_revalidation_lock'] ) ) {
			$this->owned_revalidation_lock = $GLOBALS['sp_accelerator_revalidation_lock'];
		}
	}

	/** @param array{html:string,gzip:string,meta:string,lock:string,write_lock:string} $paths */
	private function entry_is_within_hard_limit( array $paths ): bool {
		$modified = is_readable( $paths['html'] ) ? filemtime( $paths['html'] ) : false;
		if ( $modified === false ) {
			return false;
		}
		$meta      = $this->read_meta( $paths['meta'] );
		$ttl       = max( 1, (int) ( $meta['ttl'] ?? $this->config->get( 'cache_ttl', 3600 ) ) );
		$stale_ttl = max( 0, (int) ( $meta['stale_ttl'] ?? $this->config->get( 'stale_ttl', 21600 ) ) );
		return max( 0, time() - $modified ) <= $ttl + $stale_ttl;
	}

	public function store( string $canonical_url, string $html ): bool {
		if ( ! $this->config->enabled( 'page_cache' ) ) {
			$this->release_owned_revalidation_lock();
			return false;
		}
		$paths = $this->entry_paths( $canonical_url );
		$directory = dirname( $paths['html'] );
		if ( ! is_dir( $directory ) && ! wp_mkdir_p( $directory ) ) {
			$this->release_owned_revalidation_lock();
			return false;
		}
		$write_lock = @fopen( $paths['write_lock'], 'c' );
		if ( $write_lock === false || ! @flock( $write_lock, LOCK_EX ) ) {
			if ( is_resource( $write_lock ) ) {
				fclose( $write_lock );
			}
			$this->release_owned_revalidation_lock();
			return false;
		}
		if ( ! $this->config->enabled( 'page_cache' ) ) {
			@flock( $write_lock, LOCK_UN );
			fclose( $write_lock );
			$this->release_owned_revalidation_lock();
			return false;
		}
		if ( ! $this->owned_revalidation_lock_is_current() ) {
			@flock( $write_lock, LOCK_UN );
			fclose( $write_lock );
			$this->release_owned_revalidation_lock();
			return false;
		}

		$ttls  = $this->response_ttls( $html );
		$meta  = wp_json_encode( [
			'url'        => $canonical_url,
			'created_at' => time(),
			'bytes'      => strlen( $html ),
			'content_hash' => hash( 'sha256', $html ),
			'ttl'        => $ttls['ttl'],
			'stale_ttl'  => $ttls['stale_ttl'],
			'content_type' => $this->response_content_type(),
			'headers'      => $this->cacheable_response_headers(),
		], JSON_UNESCAPED_SLASHES );

		if ( is_file( $paths['gzip'] ) ) {
			@unlink( $paths['gzip'] );
		}
		$written = $this->config->atomic_write( $paths['html'], $html );
		$meta_written = $written && is_string( $meta ) && $this->config->atomic_write( $paths['meta'], $meta . "\n" );
		if ( $written && ! $meta_written ) {
			@unlink( $paths['html'] );
			@unlink( $paths['gzip'] );
			$written = false;
		}

		if ( $written && $this->config->enabled( 'gzip_cache' ) && function_exists( 'gzencode' ) ) {
			$compressed = gzencode( $html, 6 );
			if ( is_string( $compressed ) ) {
				$this->config->atomic_write( $paths['gzip'], $compressed );
			}
		} elseif ( is_file( $paths['gzip'] ) ) {
			@unlink( $paths['gzip'] );
		}

		@flock( $write_lock, LOCK_UN );
		fclose( $write_lock );
		$this->release_owned_revalidation_lock();

		return $written;
	}

	/** @return array{ttl:int,stale_ttl:int} */
	private function response_ttls( string $html ): array {
		$ttl       = max( 60, (int) $this->config->get( 'cache_ttl', 3600 ) );
		$stale_ttl = max( 0, (int) $this->config->get( 'stale_ttl', 21600 ) );
		$has_nonce = preg_match( '/(?:["\'](?:_wpnonce|nonce|security)["\']\s*[:=]|\b(?:data-)?(?:nonce|security)\s*=|\b_wpnonce\b)/i', $html ) === 1;

		if ( $has_nonce ) {
			$nonce_life = max( 1, (int) apply_filters( 'nonce_life', DAY_IN_SECONDS ) );
			$max_age    = max( 1, (int) floor( $nonce_life / 2 ) - 60 );
			$ttl        = min( $ttl, $max_age );
			$stale_ttl  = min( $stale_ttl, max( 0, $max_age - $ttl ) );
		}

		return [ 'ttl' => $ttl, 'stale_ttl' => $stale_ttl ];
	}

	private function response_content_type(): string {
		foreach ( headers_list() as $line ) {
			if ( stripos( (string) $line, 'content-type:' ) !== 0 ) {
				continue;
			}
			$value = trim( substr( (string) $line, strlen( 'content-type:' ) ) );
			if ( preg_match( '~^text/html(?:\s*;\s*charset=[a-zA-Z0-9._-]+)?$~i', $value ) ) {
				return $value;
			}
		}
		return 'text/html; charset=UTF-8';
	}

	/** @return string[] */
	private function cacheable_response_headers(): array {
		$allowed = $this->cacheable_header_names();
		$source  = headers_list();
		$out = [];
		foreach ( $source as $line ) {
			$line = trim( (string) $line );
			if ( $line === '' || strpos( $line, ':' ) === false || strpos( $line, "\r" ) !== false || strpos( $line, "\n" ) !== false ) {
				continue;
			}
			$name = strtolower( trim( (string) strtok( $line, ':' ) ) );
			if ( in_array( $name, $allowed, true ) ) {
				$out[] = $line;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/** @return string[] */
	private function cacheable_header_names(): array {
		return [
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
	}

	/** @return array{html:string,gzip:string,meta:string,lock:string,write_lock:string} */
	public function entry_paths( string $canonical_url, ?string $generation = null ): array {
		$hash       = $this->request->cache_hash( $canonical_url );
		$generation = preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) ( $generation ?? $this->config->get( 'generation', '1' ) ) ) ?: '1';
		$directory  = trailingslashit( $this->config->cache_root() ) . 'pages/' . $generation . '/' . substr( $hash, 0, 2 ) . '/' . substr( $hash, 2, 2 );
		$base       = trailingslashit( $directory ) . $hash;

		return [
			'html'       => $base . '.html',
			'gzip'       => $base . '.html.gz',
			'meta'       => $base . '.json',
			'lock'       => $base . '.lock',
			'write_lock' => $base . '.write-lock',
		];
	}

	public function purge_url( string $url ): bool {
		if ( ! $this->config->cache_root_is_owned_for_mutation() ) {
			return false;
		}
		$paths   = $this->entry_paths( $this->request->canonical_url( $url ) );
		$removed = false;
		$write_lock = @fopen( $paths['write_lock'], 'c' );
		if ( $write_lock === false || ! @flock( $write_lock, LOCK_EX ) ) {
			if ( is_resource( $write_lock ) ) {
				fclose( $write_lock );
			}
			return false;
		}

		foreach ( $paths as $name => $path ) {
			if ( in_array( $name, [ 'write_lock', 'lock' ], true ) ) {
				continue;
			}
			if ( is_file( $path ) && @unlink( $path ) ) {
				$removed = true;
			}
		}
		// Invalidate an in-flight renderer without unlinking the shared lock inode.
		// store() verifies its token under the write lock and will refuse to
		// repopulate an entry invalidated while it was rendering.
		if ( is_file( $paths['lock'] ) && ! is_link( $paths['lock'] ) ) {
			$revalidation_lock = @fopen( $paths['lock'], 'r+' );
			if ( $revalidation_lock !== false && @flock( $revalidation_lock, LOCK_EX ) ) {
				rewind( $revalidation_lock );
				$existing = stream_get_contents( $revalidation_lock );
				if ( is_string( $existing ) && $existing !== '' && @ftruncate( $revalidation_lock, 0 ) ) {
					@rewind( $revalidation_lock );
					@fflush( $revalidation_lock );
					$removed = true;
				}
				@flock( $revalidation_lock, LOCK_UN );
				fclose( $revalidation_lock );
			} elseif ( is_resource( $revalidation_lock ) ) {
				fclose( $revalidation_lock );
			}
		}
		@flock( $write_lock, LOCK_UN );
		fclose( $write_lock );

		return $removed;
	}

	private function owned_revalidation_lock_is_current(): bool {
		$this->adopt_early_lock_context();
		$lock = $this->owned_revalidation_lock;
		if ( ! is_array( $lock ) || empty( $lock['path'] ) || empty( $lock['token'] ) ) {
			return true;
		}
		if ( is_link( $lock['path'] ) ) {
			return false;
		}
		$handle = @fopen( $lock['path'], 'r' );
		if ( $handle === false || ! @flock( $handle, LOCK_SH ) ) {
			if ( is_resource( $handle ) ) {
				fclose( $handle );
			}
			return false;
		}
		$current = stream_get_contents( $handle );
		@flock( $handle, LOCK_UN );
		fclose( $handle );
		return is_string( $current ) && hash_equals( (string) $lock['token'], trim( $current ) );
	}

	public function purge_all(): bool {
		if ( $this->purged ) {
			return true;
		}
		$previous   = (string) $this->config->get( 'generation', '1' );
		$generation = $this->config->bump_generation();
		if ( $generation !== $previous ) {
			$this->purged = true;
			do_action( 'sp_accelerator_cache_purged', $generation );
			return true;
		}
		return false;
	}

	public function remove_all_page_entries(): bool {
		$pages = trailingslashit( $this->config->cache_root() ) . 'pages';
		if ( ! is_dir( $pages ) ) {
			return true;
		}
		if ( ! $this->config->cache_root_is_owned_for_mutation() ) {
			return false;
		}
		$this->delete_tree( $pages );
		clearstatcache( true, $pages );
		return ! is_dir( $pages );
	}

	/** @param int|WP_Post $post */
	public function invalidate_post( $post_id, $post = null, $update = null ): void {
		$post_id = absint( $post_id );
		if ( $post_id < 1 || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$post = $post instanceof WP_Post ? $post : get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return;
		}

		if ( apply_filters( 'sp_accelerator_global_post_invalidation', true, $post ) ) {
			$this->purge_all();
			return;
		}

		if ( in_array( $post->post_type, [ 'widgets', 'modals', 'nav_menu_item' ], true ) ) {
			$this->purge_all();
			return;
		}

		$this->purge_url( home_url( '/' ) );
		$permalink = get_permalink( $post );
		if ( is_string( $permalink ) && $permalink !== '' ) {
			$this->purge_url( $permalink );
		}

		$archive = get_post_type_archive_link( $post->post_type );
		if ( is_string( $archive ) && $archive !== '' ) {
			$this->purge_url( $archive );
		}

		$taxonomies = get_object_taxonomies( $post->post_type );
		foreach ( $taxonomies as $taxonomy ) {
			$terms = get_the_terms( $post, $taxonomy );
			if ( ! is_array( $terms ) ) {
				continue;
			}
			foreach ( $terms as $term ) {
				$link = get_term_link( $term );
				if ( is_string( $link ) ) {
					$this->purge_url( $link );
				}
			}
		}
	}

	/** @param mixed $object_id */
	public function invalidate_acf_options( $object_id ): void {
		if ( is_string( $object_id ) && ( $object_id === 'options' || strpos( $object_id, 'option' ) !== false ) ) {
			$this->purge_all();
		}
	}

	/** @param mixed ...$unused */
	public function invalidate_all( ...$unused ): void {
		$this->purge_all();
	}

	public function schedule_cleanup(): void {
		if ( ! wp_next_scheduled( 'sp_accelerator_daily_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'sp_accelerator_daily_cleanup' );
		}
	}

	public function cleanup_old_generations(): void {
		if ( ! $this->config->cache_root_is_owned_for_mutation() ) {
			return;
		}
		$pages   = trailingslashit( $this->config->cache_root() ) . 'pages';
		$current = (string) $this->config->get( 'generation', '1' );
		$previous = (string) $this->config->get( 'previous_generation', '' );
		$changed_at = max( 0, (int) $this->config->get( 'generation_changed_at', 0 ) );
		$grace = max( 0, (int) $this->config->get( 'generation_stale_ttl', 3600 ) );
		$keep_previous = $previous !== '' && $grace > 0 && $changed_at > 0 && time() - $changed_at <= $grace;
		if ( ! is_dir( $pages ) ) {
			return;
		}

		$entries = scandir( $pages );
		if ( ! is_array( $entries ) ) {
			return;
		}

		foreach ( $entries as $entry ) {
			if ( $entry === '.' || $entry === '..' || $entry === $current || ( $keep_previous && $entry === $previous ) || ! preg_match( '/^[a-zA-Z0-9_-]+$/', $entry ) ) {
				continue;
			}
			$this->delete_tree( $pages . '/' . $entry );
		}
	}

	private function delete_tree( string $directory ): void {
		if ( ! $this->config->cache_root_is_owned_for_mutation() ) {
			return;
		}
		$root = realpath( $this->config->cache_root() );
		$path = realpath( $directory );
		if ( $root === false || $path === false || strpos( $path, $root . DIRECTORY_SEPARATOR ) !== 0 || ! is_dir( $path ) ) {
			return;
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $iterator as $item ) {
			if ( $item->isDir() && ! $item->isLink() ) {
				@rmdir( $item->getPathname() );
			} else {
				@unlink( $item->getPathname() );
			}
		}
		@rmdir( $path );
	}

	/** @return array{files:int,bytes:int} */
	public function stats(): array {
		$generation = (string) $this->config->get( 'generation', '1' );
		$directory  = trailingslashit( $this->config->cache_root() ) . 'pages/' . $generation;
		$files      = 0;
		$bytes      = 0;

		if ( ! is_dir( $directory ) ) {
			return [ 'files' => 0, 'bytes' => 0 ];
		}

		$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $iterator as $item ) {
			if ( ! $item->isFile() ) {
				continue;
			}
			$bytes += $item->getSize();
			if ( substr( $item->getFilename(), -5 ) === '.html' ) {
				$files++;
			}
		}

		return [ 'files' => $files, 'bytes' => $bytes ];
	}

	private function is_own_dropin_active(): bool {
		if ( ! defined( 'WP_CACHE' ) || ! WP_CACHE ) {
			return false;
		}

		$file = trailingslashit( WP_CONTENT_DIR ) . 'advanced-cache.php';
		if ( ! is_readable( $file ) ) {
			return false;
		}

		$head = file_get_contents( $file, false, null, 0, 512 );
		return is_string( $head ) && strpos( $head, 'SP Accelerator Drop-in' ) !== false;
	}

	private function has_foreign_dropin(): bool {
		$file = trailingslashit( WP_CONTENT_DIR ) . 'advanced-cache.php';
		if ( ! is_readable( $file ) ) {
			return false;
		}

		$head = file_get_contents( $file, false, null, 0, 1024 );
		if ( ! is_string( $head ) ) {
			return false;
		}

		return strpos( $head, 'SP Accelerator Drop-in' ) === false
			&& strpos( $head, 'Disabled by seraphinite-accelerator' ) === false;
	}

	private function minify_html( string $html ): string {
		$protected = [];
		$html      = preg_replace_callback(
			'~<(pre|textarea|script|style|template)\b[^>]*>.*?</\1\s*>~is',
			static function ( array $match ) use ( &$protected ): string {
				$key               = '%%SP_ACCELERATOR_BLOCK_' . count( $protected ) . '%%';
				$protected[ $key ] = $match[0];
				return $key;
			},
			$html
		) ?: $html;

		$html = preg_replace( '/<!--(?!\[if|<!|\s*wp:|\s*\/wp:|\s*ko\b|\s*\/ko\b).*?-->/is', '', $html ) ?: $html;
		$html = preg_replace( '/^[\t ]+|[\t ]+$/m', '', $html ) ?: $html;
		$html = preg_replace( '/\R{3,}/', "\n\n", $html ) ?: $html;

		if ( $protected ) {
			$html = strtr( $html, $protected );
		}

		return trim( $html );
	}
}
