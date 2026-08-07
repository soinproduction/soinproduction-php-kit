<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SP_Accelerator_Config {
	public const OPTION_KEY = 'sp_accelerator_settings';
	public const VERSION    = '2.0.0';
	public const RUNTIME_DISABLED_KEY = 'sp_accelerator_runtime_disabled';
	private const CACHE_SIGNATURE = 'SP Accelerator cache config';
	private const WARM_TOKEN_TTL = 300;

	/** @var array<string,mixed>|null */
	private $settings = null;

	/** @return array<string,mixed> */
	public function defaults(): array {
		return [
			'enabled'                => 1,
			'page_cache'             => 1,
			'auto_warm'              => 1,
			'cache_ttl'              => 3600,
			'stale_ttl'              => 21600,
			'generation_stale_ttl'   => 3600,
			'browser_cache_ttl'      => 0,
			'gzip_cache'             => 1,
			'minify_html'            => 1,
			'preload_main_script'    => 0,
			'limit_font_preloads'    => 1,
			'async_main_style'       => 1,
			'async_section_styles'   => 1,
			'delay_section_scripts'  => 1,
			'script_delay_ms'        => 12000,
			'resource_hints'         => 1,
			'optimize_markup'        => 1,
			'preload_lcp_image'      => 1,
			'add_image_dimensions'   => 1,
			'lazy_embeds'            => 1,
			'async_image_decoding'   => 1,
			'bypass_unknown_cookies' => 1,
			'generation'             => '1',
			'previous_generation'    => '',
			'generation_changed_at'  => 0,
			'exclude_cookies'        => implode( "\n", [
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
			], ),
			'allow_cookies'          => implode( "\n", [
				'_ga',
				'_gid',
				'_gat',
				'_gac_',
				'_gcl_',
				'_fbp',
				'_clck',
				'_clsk',
			], ),
			'exclude_paths'          => implode( "\n", [
				'/wp-admin/',
				'/wp-login.php',
				'/wp-cron.php',
				'/wp-json/',
				'/xmlrpc.php',
				'/feed/',
				'/cart/',
				'/checkout/',
				'/my-account/',
				'~/(?:[^/]+/)?feed(?:/|$)~i',
				'~/sitemap(?:_index)?\\.xml$~i',
			] ),
		];
	}

	/** @return array<string,mixed> */
	public function all(): array {
		if ( is_array( $this->settings ) ) {
			return $this->settings;
		}

		$stored         = get_option( self::OPTION_KEY, [] );
		$stored         = is_array( $stored ) ? $stored : [];
		$this->settings = array_merge( $this->defaults(), $stored );

		return $this->settings;
	}

	/** @return mixed */
	public function get( string $key, $default = null ) {
		$settings = $this->all();
		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
	}

	public function enabled( string $key = 'enabled' ): bool {
		if ( $this->runtime_is_disabled() ) {
			return false;
		}
		if ( $key === 'page_cache' ) {
			return $this->page_cache_configuration_enabled() && $this->cache_root_is_runtime_ready();
		}

		return $this->feature_configuration_enabled( $key );
	}

	private function feature_configuration_enabled( string $key ): bool {
		return ! $this->has_legacy_accelerator_conflict()
			&& ! empty( $this->get( 'enabled' ) )
			&& ! empty( $this->get( $key ) );
	}

	private function page_cache_configuration_enabled(): bool {
		if ( $this->runtime_is_disabled() ) {
			return false;
		}
		if ( function_exists( 'is_multisite' ) && is_multisite()
			&& ( ! defined( 'SP_ACCELERATOR_MULTISITE_CACHE' ) || ! SP_ACCELERATOR_MULTISITE_CACHE ) ) {
			return false;
		}
		return $this->cache_root_is_dedicated()
			&& $this->storage_is_safe_for_server()
			&& $this->feature_configuration_enabled( 'page_cache' );
	}

	public function has_legacy_accelerator_conflict(): bool {
		return defined( 'SERAPH_ACCEL_PLUGIN_DIR' );
	}

	public function activate_runtime(): void {
		delete_option( self::RUNTIME_DISABLED_KEY );
	}

	public function mark_runtime_disabled(): void {
		$token = time() . ':' . wp_generate_password( 16, false, false );
		if ( get_option( self::RUNTIME_DISABLED_KEY, null ) === null ) {
			if ( add_option( self::RUNTIME_DISABLED_KEY, $token, '', false ) ) {
				return;
			}
		}
		update_option( self::RUNTIME_DISABLED_KEY, $token, false );
	}

	public function runtime_is_disabled(): bool {
		return (string) get_option( self::RUNTIME_DISABLED_KEY, '' ) !== '';
	}

	/**
	 * @param array<string,mixed> $input
	 * @return array<string,mixed>
	 */
	public function sanitize( array $input ): array {
		$current = $this->all();
		$clean   = [];

		$flags = [
			'enabled',
			'page_cache',
			'auto_warm',
			'gzip_cache',
			'minify_html',
			'preload_main_script',
			'limit_font_preloads',
			'async_main_style',
			'async_section_styles',
			'delay_section_scripts',
			'resource_hints',
			'optimize_markup',
			'preload_lcp_image',
			'add_image_dimensions',
			'lazy_embeds',
			'async_image_decoding',
			'bypass_unknown_cookies',
		];

		foreach ( $flags as $flag ) {
			$clean[ $flag ] = ! empty( $input[ $flag ] ) ? 1 : 0;
		}

		$clean['cache_ttl']             = min( WEEK_IN_SECONDS, max( 60, absint( $input['cache_ttl'] ?? 3600 ) ) );
		$clean['stale_ttl']             = min( DAY_IN_SECONDS, max( 0, absint( $input['stale_ttl'] ?? 21600 ) ) );
		$clean['generation_stale_ttl']  = min( DAY_IN_SECONDS, max( 0, absint( $input['generation_stale_ttl'] ?? 3600 ) ) );
		$clean['browser_cache_ttl']     = min( HOUR_IN_SECONDS, max( 0, absint( $input['browser_cache_ttl'] ?? 0 ) ) );
		$clean['script_delay_ms']       = min( 30000, max( 0, absint( $input['script_delay_ms'] ?? 12000 ) ) );
		$clean['generation']            = preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) ( $current['generation'] ?? '1' ) ) ?: '1';
		$clean['previous_generation']   = preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) ( $current['previous_generation'] ?? '' ) ) ?: '';
		$clean['generation_changed_at'] = max( 0, (int) ( $current['generation_changed_at'] ?? 0 ) );

		$paths = isset( $input['exclude_paths'] ) ? (string) $input['exclude_paths'] : '';
		$lines = preg_split( '/\R/', $paths ) ?: [];
		$lines = array_values( array_unique( array_filter( array_map( static function ( $line ): string {
			$line = trim( sanitize_text_field( (string) $line ) );
			return strlen( $line ) <= 240 ? $line : '';
		}, $lines ) ) ) );
		$clean['exclude_paths'] = implode( "\n", array_slice( $lines, 0, 100 ) );

		$cookies = isset( $input['exclude_cookies'] ) ? (string) $input['exclude_cookies'] : '';
		$cookies = preg_split( '/\R/', $cookies ) ?: [];
		$cookies = array_values( array_unique( array_filter( array_map( static function ( $cookie ): string {
			$cookie = strtolower( trim( sanitize_text_field( (string) $cookie ) ) );
			return preg_match( '/^[a-z0-9_.-]{2,120}$/', $cookie ) ? $cookie : '';
		}, $cookies ) ) ) );
		$clean['exclude_cookies'] = implode( "\n", array_slice( $cookies, 0, 100 ) );

		$allow_cookies = isset( $input['allow_cookies'] ) ? (string) $input['allow_cookies'] : '';
		$allow_cookies = preg_split( '/\R/', $allow_cookies ) ?: [];
		$allow_cookies = array_values( array_unique( array_filter( array_map( static function ( $cookie ): string {
			$cookie = strtolower( trim( sanitize_text_field( (string) $cookie ) ) );
			return preg_match( '/^[a-z0-9_.-]{2,120}$/', $cookie ) ? $cookie : '';
		}, $allow_cookies ) ) ) );
		$clean['allow_cookies'] = implode( "\n", array_slice( $allow_cookies, 0, 100 ) );

		return $clean;
	}

	/** @param array<string,mixed> $input */
	public function save( array $input ): bool {
		$previous       = $this->all();
		$this->settings = $this->sanitize( $input );
		if ( ! $this->sync_dropin_config() ) {
			$this->settings = $previous;
			return false;
		}

		update_option( self::OPTION_KEY, $this->settings, false );
		$stored = get_option( self::OPTION_KEY, [] );
		if ( ! is_array( $stored ) || $stored !== $this->settings ) {
			update_option( self::OPTION_KEY, $previous, false );
			$this->settings = $previous;
			$this->sync_dropin_config();
			return false;
		}

		return true;
	}

	public function bump_generation(): string {
		$previous = $this->all();
		if ( $this->runtime_is_disabled() ) {
			return (string) ( $previous['generation'] ?? '1' );
		}
		$settings = $previous;
		$settings['previous_generation']   = preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) ( $settings['generation'] ?? '1' ) ) ?: '1';
		$settings['generation'] = gmdate( 'YmdHis' ) . '-' . wp_generate_password( 6, false, false );
		$settings['generation_changed_at'] = time();
		$this->settings = $settings;
		if ( ! $this->sync_dropin_config() ) {
			$this->settings = $previous;
			return (string) ( $previous['generation'] ?? '1' );
		}

		update_option( self::OPTION_KEY, $settings, false );
		$stored = get_option( self::OPTION_KEY, [] );
		if ( ! is_array( $stored ) || (string) ( $stored['generation'] ?? '' ) !== (string) $settings['generation'] ) {
			update_option( self::OPTION_KEY, $previous, false );
			$this->settings = $previous;
			$this->sync_dropin_config();
			return (string) ( $previous['generation'] ?? '1' );
		}

		return (string) $settings['generation'];
	}

	public function cache_root(): string {
		if ( defined( 'SP_ACCELERATOR_CACHE_DIR' ) ) {
			$candidate = rtrim( trim( (string) SP_ACCELERATOR_CACHE_DIR ), '/\\' );
			$absolute  = strpos( $candidate, '/' ) === 0 || preg_match( '/^[a-zA-Z]:[\\\\\/]/', $candidate ) === 1;
			if ( $absolute && $candidate !== DIRECTORY_SEPARATOR && dirname( $candidate ) !== $candidate ) {
				return $candidate;
			}
		}
		return trailingslashit( WP_CONTENT_DIR ) . 'cache/sp-accelerator';
	}

	public function legacy_cache_root(): string {
		return trailingslashit( WP_CONTENT_DIR ) . 'cache/sp-accelerator';
	}

	public function storage_is_safe_for_server(): bool {
		if ( ! $this->cache_root_is_dedicated() ) {
			return false;
		}
		if ( defined( 'SP_ACCELERATOR_CACHE_WEB_PROTECTED' ) && SP_ACCELERATOR_CACHE_WEB_PROTECTED ) {
			return true;
		}

		$document_root = defined( 'SP_ACCELERATOR_DOCUMENT_ROOT' )
			? (string) SP_ACCELERATOR_DOCUMENT_ROOT
			: (string) ( $_SERVER['DOCUMENT_ROOT'] ?? '' );
		if ( ! $this->is_absolute_non_root_path( $document_root ) ) {
			return false;
		}

		$cache = $this->normalized_path( $this->cache_root() );
		foreach ( [ ABSPATH, WP_CONTENT_DIR, $document_root ] as $root ) {
			if ( $this->path_is_within( $cache, $this->normalized_path( (string) $root ) ) ) {
				return false;
			}
		}
		return true;
	}

	public function storage_safety_message(): string {
		return $this->storage_is_safe_for_server()
			? ''
			: 'Page cache отключён fail-closed: нужен отдельный каталог с именем sp-accelerator, который не является системным/WordPress root и подтверждён как недоступный из web. Вынесите SP_ACCELERATOR_CACHE_DIR за фактический document root (для CLI задайте SP_ACCELERATOR_DOCUMENT_ROOT) либо проверьте deny прямым HTTP-запросом и только затем задайте SP_ACCELERATOR_CACHE_WEB_PROTECTED=true.';
	}

	public function cache_root_is_dedicated(): bool {
		$raw_cache = $this->cache_root();
		if ( preg_match( '~(?:^|[\\/])\.\.?(?:[\\/]|$)~', $raw_cache ) ) {
			return false;
		}
		$cache = $this->normalized_path( $raw_cache );
		if ( ! $this->is_absolute_non_root_path( $cache ) || ! preg_match( '/(?:^|[-_.])sp-accelerator(?:[-_.]|$)/i', basename( $cache ) ) ) {
			return false;
		}

		$roots = [ ABSPATH, WP_CONTENT_DIR, sys_get_temp_dir() ];
		$document_root = defined( 'SP_ACCELERATOR_DOCUMENT_ROOT' )
			? (string) SP_ACCELERATOR_DOCUMENT_ROOT
			: (string) ( $_SERVER['DOCUMENT_ROOT'] ?? '' );
		if ( $document_root !== '' ) {
			$roots[] = $document_root;
		}
		foreach ( $roots as $root ) {
			$root = $this->normalized_path( (string) $root );
			if ( $root !== '' && ( $cache === $root || $this->path_is_within( $root, $cache ) ) ) {
				return false;
			}
		}
		return true;
	}

	public function cache_root_is_owned_for_mutation(): bool {
		return $this->cache_root_is_dedicated() && $this->cache_root_is_owned_at( $this->cache_root() );
	}

	private function cache_root_is_runtime_ready(): bool {
		$root = $this->cache_root();
		if ( ! $this->cache_root_is_owned_for_mutation() || ! is_dir( $root ) || is_link( $root ) ) {
			return false;
		}
		$config_path = rtrim( $root, '/\\' ) . DIRECTORY_SEPARATOR . 'config.json';
		if ( ! is_readable( $config_path ) ) {
			return false;
		}
		$json = file_get_contents( $config_path );
		$data = is_string( $json ) ? json_decode( $json, true ) : null;
		return is_array( $data )
			&& isset( $data['signature'], $data['cache_root'] )
			&& hash_equals( self::CACHE_SIGNATURE, (string) $data['signature'] )
			&& $this->normalized_path( (string) $data['cache_root'] ) === $this->normalized_path( $root );
	}

	public function config_file(): string {
		return trailingslashit( $this->cache_root() ) . 'config.json';
	}

	/** @return string[] */
	public function excluded_paths(): array {
		$lines = preg_split( '/\R/', (string) $this->get( 'exclude_paths', '' ) ) ?: [];
		return array_values( array_filter( array_map( 'trim', $lines ) ) );
	}

	/** @return string[] */
	public function excluded_cookies(): array {
		$lines = preg_split( '/\R/', (string) $this->get( 'exclude_cookies', '' ) ) ?: [];
		return array_values( array_filter( array_map( static function ( $line ): string {
			return strtolower( trim( (string) $line ) );
		}, $lines ) ) );
	}

	/** @return string[] */
	public function allowed_cookies(): array {
		$lines = preg_split( '/\R/', (string) $this->get( 'allow_cookies', '' ) ) ?: [];
		return array_values( array_filter( array_map( static function ( $line ): string {
			return strtolower( trim( (string) $line ) );
		}, $lines ) ) );
	}

	/** @return array<string,mixed> */
	public function dropin_config(): array {
		return [
			'signature'     => self::CACHE_SIGNATURE,
			'version'       => self::VERSION,
			'enabled'       => $this->page_cache_configuration_enabled(),
			'cache_root'    => $this->cache_root(),
			'generation'    => (string) $this->get( 'generation', '1' ),
			'previous_generation'   => (string) $this->get( 'previous_generation', '' ),
			'generation_changed_at' => (int) $this->get( 'generation_changed_at', 0 ),
			'generation_stale_ttl'  => (int) $this->get( 'generation_stale_ttl', 3600 ),
			'ttl'           => (int) $this->get( 'cache_ttl', 3600 ),
			'stale_ttl'     => (int) $this->get( 'stale_ttl', 21600 ),
			'browser_ttl'   => (int) $this->get( 'browser_cache_ttl', 0 ),
			'gzip'          => (bool) $this->get( 'gzip_cache', true ),
			'hosts'         => $this->allowed_hosts(),
			'exclude_paths' => $this->excluded_paths(),
			'exclude_cookies' => $this->excluded_cookies(),
			'allow_cookies' => $this->allowed_cookies(),
			'bypass_unknown_cookies' => (bool) $this->get( 'bypass_unknown_cookies', true ),
			'warm_token'    => $this->warm_token(),
		];
	}

	public function warm_token(): string {
		if ( function_exists( 'wp_salt' ) ) {
			$secret = (string) wp_salt( 'auth' );
		} elseif ( defined( 'AUTH_KEY' ) && (string) AUTH_KEY !== '' ) {
			$secret = (string) AUTH_KEY;
		} elseif ( defined( 'SECURE_AUTH_KEY' ) && (string) SECURE_AUTH_KEY !== '' ) {
			$secret = (string) SECURE_AUTH_KEY;
		} else {
			$secret = hash( 'sha256', (string) ABSPATH . '|' . site_url( '/' ) );
		}
		return hash_hmac( 'sha256', 'sp-accelerator-warm|' . home_url( '/' ), $secret );
	}

	public function warm_request_token( string $canonical_url ): string {
		$timestamp  = time();
		$generation = preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $this->get( 'generation', '1' ) ) ?: '1';
		$signature  = hash_hmac( 'sha256', $timestamp . '|' . $generation . '|' . $canonical_url, $this->warm_token() );
		return $timestamp . ':' . $signature;
	}

	public function is_authenticated_warm_request( string $canonical_url ): bool {
		$provided = trim( (string) ( $_SERVER['HTTP_X_SP_CACHE_WARM'] ?? '' ) );
		$parts    = explode( ':', $provided, 2 );
		if ( count( $parts ) !== 2 || ! ctype_digit( $parts[0] ) || ! preg_match( '/^[a-f0-9]{64}$/i', $parts[1] ) ) {
			return false;
		}
		$timestamp = (int) $parts[0];
		if ( $timestamp < 1 || abs( time() - $timestamp ) > self::WARM_TOKEN_TTL ) {
			return false;
		}
		$generation = preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $this->get( 'generation', '1' ) ) ?: '1';
		$expected   = hash_hmac( 'sha256', $timestamp . '|' . $generation . '|' . $canonical_url, $this->warm_token() );
		return hash_equals( $expected, strtolower( $parts[1] ) );
	}

	/** @return string[] */
	public function allowed_hosts(): array {
		$hosts = [];
		foreach ( [ home_url( '/' ), site_url( '/' ) ] as $url ) {
			$parts = wp_parse_url( $url );
			if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
				continue;
			}
			$host = strtolower( (string) $parts['host'] );
			if ( ! empty( $parts['port'] ) ) {
				$host .= ':' . absint( $parts['port'] );
			}
			$hosts[] = $host;
		}

		return array_values( array_unique( $hosts ) );
	}

	public function sync_dropin_config(): bool {
		$root = $this->cache_root();
		if ( ! $this->cache_root_is_dedicated() || ! $this->cache_root_is_owned_at( $root ) ) {
			return false;
		}
		if ( ! $this->disable_and_clean_legacy_root() ) {
			return false;
		}
		if ( ! is_dir( $root ) && ! wp_mkdir_p( $root ) ) {
			return false;
		}

		if ( ! $this->protect_cache_root() ) {
			return false;
		}

		$config = $this->dropin_config();
		if ( ! $this->storage_is_safe_for_server() ) {
			$config = [ 'signature' => self::CACHE_SIGNATURE, 'version' => self::VERSION, 'enabled' => false ];
			$disabled = $this->write_dropin_config( $config );
			$cleaned  = ! $this->storage_is_confirmed_web_exposed() || $this->remove_unsafe_web_cache_files();
			return $disabled && $cleaned;
		}
		return $this->write_dropin_config( $config );
	}

	public function disable_dropin_config(): bool {
		$root = $this->cache_root();
		if ( ! $this->cache_root_is_dedicated() || ! $this->cache_root_is_owned_at( $root ) ) {
			return false;
		}
		if ( ! $this->disable_and_clean_legacy_root() ) {
			return false;
		}
		if ( ! is_dir( $root ) && ! wp_mkdir_p( $root ) ) {
			return false;
		}
		if ( ! $this->protect_cache_root() ) {
			return false;
		}
		$config = $this->storage_is_safe_for_server()
			? array_merge( $this->dropin_config(), [ 'enabled' => false ] )
			: [ 'signature' => self::CACHE_SIGNATURE, 'version' => self::VERSION, 'enabled' => false ];
		if ( ! $this->storage_is_safe_for_server() ) {
			$disabled = $this->write_dropin_config( $config );
			$cleaned  = ! $this->storage_is_confirmed_web_exposed() || $this->remove_unsafe_web_cache_files();
			return $disabled && $cleaned;
		}
		return $this->write_dropin_config( $config );
	}

	private function remove_unsafe_web_cache_files(): bool {
		return $this->remove_page_cache_files_at_root( $this->cache_root() );
	}

	private function remove_page_cache_files_at_root( string $directory ): bool {
		$root = realpath( $directory );
		if ( $root === false || $root === DIRECTORY_SEPARATOR || ! is_dir( $root ) ) {
			return false;
		}

		$pages = realpath( $root . DIRECTORY_SEPARATOR . 'pages' );
		if ( $pages !== false && strpos( $pages, $root . DIRECTORY_SEPARATOR ) === 0 && is_dir( $pages ) ) {
			try {
				$iterator = new RecursiveIteratorIterator(
					new RecursiveDirectoryIterator( $pages, FilesystemIterator::SKIP_DOTS ),
					RecursiveIteratorIterator::CHILD_FIRST
				);
				foreach ( $iterator as $item ) {
					$item->isDir() && ! $item->isLink() ? @rmdir( $item->getPathname() ) : @unlink( $item->getPathname() );
				}
			} catch ( Throwable $error ) {
				return false;
			}
			@rmdir( $pages );
			if ( is_dir( $pages ) ) {
				return false;
			}
		}
		return true;
	}

	private function disable_and_clean_legacy_root(): bool {
		$legacy = rtrim( $this->legacy_cache_root(), '/\\' );
		$current = rtrim( $this->cache_root(), '/\\' );
		if ( $this->normalized_path( $legacy ) === $this->normalized_path( $current ) || ! is_dir( $legacy ) ) {
			return true;
		}

		if ( ! $this->directory_is_dedicated( $legacy ) || ! $this->cache_root_is_owned_at( $legacy ) ) {
			return false;
		}

		$config_path = $legacy . DIRECTORY_SEPARATOR . 'config.json';
		$data = null;
		if ( is_readable( $config_path ) ) {
			$json = file_get_contents( $config_path );
			$data = is_string( $json ) ? json_decode( $json, true ) : null;
		}
		if ( ! $this->cache_config_identifies_root( $data, $legacy ) && ! $this->known_protection_files_exist( $legacy ) ) {
			// Do not delete a directory that cannot be positively identified as ours.
			return ! is_dir( $legacy . DIRECTORY_SEPARATOR . 'pages' );
		}

		$disabled = $this->write_config_at_root( $legacy, [ 'signature' => self::CACHE_SIGNATURE, 'version' => self::VERSION, 'enabled' => false ] );
		$cleaned  = $this->remove_page_cache_files_at_root( $legacy );
		$sqlite_safe = true;
		if ( $this->storage_is_confirmed_web_exposed_at( $legacy )
			&& ( ! defined( 'SP_ACCELERATOR_OBJECT_CACHE_WEB_PROTECTED' ) || ! SP_ACCELERATOR_OBJECT_CACHE_WEB_PROTECTED ) ) {
			$sqlite_safe = $this->remove_sqlite_files_at_root( $legacy );
		}
		return $disabled && $cleaned && $sqlite_safe;
	}

	/** @param array<string,mixed> $config */
	private function write_dropin_config( array $config ): bool {
		return $this->write_config_at_root( $this->cache_root(), $config );
	}

	/** @param array<string,mixed> $config */
	private function write_config_at_root( string $root, array $config ): bool {
		$json = wp_json_encode( $config, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
		if ( ! is_string( $json ) ) {
			return false;
		}

		return $this->atomic_write( trailingslashit( $root ) . 'config.json', $json . "\n" );
	}

	private function is_absolute_non_root_path( string $path ): bool {
		$path = rtrim( trim( $path ), '/\\' );
		$absolute = strpos( $path, '/' ) === 0 || preg_match( '/^[a-zA-Z]:[\\\\\/]/', $path ) === 1;
		return $absolute && $path !== '' && $path !== DIRECTORY_SEPARATOR && dirname( $path ) !== $path;
	}

	private function normalized_path( string $path ): string {
		$resolved = realpath( $path );
		if ( $resolved === false ) {
			$parent = realpath( dirname( $path ) );
			$resolved = $parent !== false ? rtrim( $parent, '/\\' ) . DIRECTORY_SEPARATOR . basename( $path ) : $path;
		}
		$path = (string) $resolved;
		$path = rtrim( str_replace( '\\', '/', $path ), '/' );
		return DIRECTORY_SEPARATOR === '\\' ? strtolower( $path ) : $path;
	}

	private function path_is_within( string $path, string $root ): bool {
		return $path !== '' && $root !== '' && strpos( $path . '/', rtrim( $root, '/' ) . '/' ) === 0;
	}

	private function storage_is_confirmed_web_exposed(): bool {
		return $this->storage_is_confirmed_web_exposed_at( $this->cache_root() );
	}

	private function storage_is_confirmed_web_exposed_at( string $directory ): bool {
		if ( defined( 'SP_ACCELERATOR_CACHE_WEB_PROTECTED' ) && SP_ACCELERATOR_CACHE_WEB_PROTECTED ) {
			return false;
		}
		$cache = $this->normalized_path( $directory );
		$roots = [ ABSPATH, WP_CONTENT_DIR ];
		$document_root = defined( 'SP_ACCELERATOR_DOCUMENT_ROOT' )
			? (string) SP_ACCELERATOR_DOCUMENT_ROOT
			: (string) ( $_SERVER['DOCUMENT_ROOT'] ?? '' );
		if ( $document_root !== '' ) {
			$roots[] = $document_root;
		}
		foreach ( $roots as $root ) {
			if ( $this->path_is_within( $cache, $this->normalized_path( (string) $root ) ) ) {
				return true;
			}
		}
		return false;
	}

	private function directory_is_dedicated( string $directory ): bool {
		$directory = $this->normalized_path( $directory );
		return $this->is_absolute_non_root_path( $directory )
			&& preg_match( '/(?:^|[-_.])sp-accelerator(?:[-_.]|$)/i', basename( $directory ) ) === 1;
	}

	private function cache_root_is_owned_at( string $root ): bool {
		if ( is_link( $root ) ) {
			return false;
		}
		if ( ! file_exists( $root ) ) {
			return true;
		}
		if ( ! is_dir( $root ) || is_link( $root ) ) {
			return false;
		}
		$entries = @scandir( $root );
		if ( is_array( $entries ) && count( array_diff( $entries, [ '.', '..' ] ) ) === 0 ) {
			return true;
		}

		$data = null;
		$config_path = rtrim( $root, '/\\' ) . DIRECTORY_SEPARATOR . 'config.json';
		if ( is_readable( $config_path ) ) {
			$json = file_get_contents( $config_path );
			$data = is_string( $json ) ? json_decode( $json, true ) : null;
		}
		return $this->cache_config_identifies_root( $data, $root ) || $this->known_protection_files_exist( $root );
	}

	/** @param mixed $data */
	private function cache_config_identifies_root( $data, string $root ): bool {
		if ( ! is_array( $data ) ) {
			return false;
		}
		if ( isset( $data['signature'] ) && hash_equals( self::CACHE_SIGNATURE, (string) $data['signature'] ) ) {
			return true;
		}
		return ! empty( $data['version'] )
			&& isset( $data['generation'], $data['ttl'], $data['hosts'], $data['cache_root'] )
			&& is_array( $data['hosts'] )
			&& is_numeric( $data['ttl'] )
			&& $this->normalized_path( (string) $data['cache_root'] ) === $this->normalized_path( $root );
	}

	private function known_protection_files_exist( string $root ): bool {
		foreach ( [ '.htaccess', 'web.config', 'index.php' ] as $name ) {
			$path = rtrim( $root, '/\\' ) . DIRECTORY_SEPARATOR . $name;
			if ( ! is_readable( $path ) ) {
				continue;
			}
			$contents = file_get_contents( $path );
			if ( is_string( $contents )
				&& ( strpos( $contents, 'SP Accelerator cache protection' ) !== false || $this->legacy_protection_file_matches( $name, $contents ) ) ) {
				return true;
			}
		}
		return false;
	}

	private function remove_sqlite_files_at_root( string $root ): bool {
		$database = rtrim( $root, '/\\' ) . DIRECTORY_SEPARATOR . 'object-cache.sqlite';
		$ok = true;
		foreach ( [ $database . '-wal', $database . '-shm', $database . '-journal', $database ] as $path ) {
			if ( is_file( $path ) && ! @unlink( $path ) ) {
				$ok = false;
			}
		}
		return $ok;
	}

	private function protect_cache_root(): bool {
		$root = trailingslashit( $this->cache_root() );
		$files = [
			'.htaccess' => "# SP Accelerator cache protection\nOptions -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n",
			'index.php' => "<?php\n// SP Accelerator cache protection.\nhttp_response_code( 404 );\nexit;\n",
			'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<!-- SP Accelerator cache protection -->\n<configuration><system.webServer><security><authorization><remove users=\"*\" roles=\"\" verbs=\"\"/><add accessType=\"Deny\" users=\"*\"/></authorization></security></system.webServer></configuration>\n",
		];
		foreach ( $files as $name => $contents ) {
			$path = $root . $name;
			if ( is_file( $path ) ) {
				$current = file_get_contents( $path );
				if ( ! is_string( $current ) || ( strpos( $current, 'SP Accelerator cache protection' ) === false && ! $this->legacy_protection_file_matches( $name, $current ) ) ) {
					return false;
				}
			}
			if ( ! $this->atomic_write( $path, $contents ) ) {
				return false;
			}
		}
		return true;
	}

	private function legacy_protection_file_matches( string $name, string $contents ): bool {
		$legacy = [
			'.htaccess' => "Options -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n",
			'index.php' => "<?php\nhttp_response_code( 404 );\nexit;\n",
			'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><security><authorization><remove users=\"*\" roles=\"\" verbs=\"\"/><add accessType=\"Deny\" users=\"*\"/></authorization></security></system.webServer></configuration>\n",
		];
		return isset( $legacy[ $name ] ) && hash_equals( hash( 'sha256', $legacy[ $name ] ), hash( 'sha256', $contents ) );
	}

	public function atomic_write( string $path, string $contents ): bool {
		$directory = dirname( $path );
		if ( ! is_dir( $directory ) && ! wp_mkdir_p( $directory ) ) {
			return false;
		}

		$temp = tempnam( $directory, '.sp-accelerator-' );
		if ( $temp === false ) {
			return false;
		}

		$written = file_put_contents( $temp, $contents, LOCK_EX );
		if ( $written === false ) {
			@unlink( $temp );
			return false;
		}

		@chmod( $temp, 0644 );
		if ( ! @rename( $temp, $path ) ) {
			@unlink( $temp );
			return false;
		}

		return true;
	}
}
