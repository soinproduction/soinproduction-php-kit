<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SP_Accelerator_Request {
	/** @var SP_Accelerator_Config */
	private $config;

	public function __construct( SP_Accelerator_Config $config ) {
		$this->config = $config;
	}

	public function is_cacheable_transport(): bool {
		$method = strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) );
		if ( ! in_array( $method, [ 'GET', 'HEAD' ], true ) ) {
			return false;
		}

		if ( isset( $_GET['nocache'] ) || isset( $_GET['preview'] ) || isset( $_GET['customize_changeset_uuid'] ) ) {
			return false;
		}

		$cache_control = strtolower( (string) ( $_SERVER['HTTP_CACHE_CONTROL'] ?? '' ) );
		$pragma        = strtolower( (string) ( $_SERVER['HTTP_PRAGMA'] ?? '' ) );
		if ( ! empty( $_SERVER['HTTP_RANGE'] ) || preg_match( '/(?:^|,)\s*(?:no-cache|no-store|max-age\s*=\s*0)\b/', $cache_control ) || strpos( $pragma, 'no-cache' ) !== false ) {
			return false;
		}

		if ( ! $this->has_only_ignored_query_args() || $this->has_private_cookie() || $this->has_authorization() || ! $this->has_allowed_host() ) {
			return false;
		}

		$path = $this->request_path();
		if ( $path === '' || $this->is_excluded_path( $path ) || preg_match( '~\.[a-z0-9]{1,8}$~i', $path ) ) {
			return false;
		}

		return true;
	}

	public function is_cacheable_wordpress_request(): bool {
		if ( ! $this->is_cacheable_transport() || is_admin() || is_user_logged_in() ) {
			return false;
		}

		if ( wp_doing_ajax() || wp_doing_cron() || is_feed() || is_search() || is_preview() || is_404() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return false;
		}

		if ( function_exists( 'is_customize_preview' ) && is_customize_preview() ) {
			return false;
		}

		if ( is_singular() ) {
			$post = get_queried_object();
			if ( $post instanceof WP_Post && post_password_required( $post ) ) {
				return false;
			}
		}

		return ! ( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE );
	}

	public function can_seed_cache(): bool {
		return (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_QUERY ) === '';
	}

	public function request_path(): string {
		$uri  = (string) ( $_SERVER['REQUEST_URI'] ?? '/' );
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		return $path !== '' ? $path : '/';
	}

	public function canonical_request_url(): string {
		$scheme = $this->request_scheme();
		$host   = strtolower( (string) ( $_SERVER['HTTP_HOST'] ?? wp_parse_url( home_url( '/' ), PHP_URL_HOST ) ) );
		$host   = preg_replace( '/[^a-z0-9.\-:\[\]]/i', '', $host ) ?: 'localhost';

		return $scheme . '://' . $host . $this->request_path();
	}

	public function canonical_url( string $url ): string {
		$parts  = wp_parse_url( $url );
		$scheme = ! empty( $parts['scheme'] ) ? strtolower( (string) $parts['scheme'] ) : $this->request_scheme();
		$host   = ! empty( $parts['host'] ) ? strtolower( (string) $parts['host'] ) : strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
		$port   = ! empty( $parts['port'] ) ? ':' . absint( $parts['port'] ) : '';
		$path   = ! empty( $parts['path'] ) ? (string) $parts['path'] : '/';

		return $scheme . '://' . $host . $port . $path;
	}

	public function cache_hash( string $canonical_url ): string {
		return hash( 'sha256', $canonical_url );
	}

	private function request_scheme(): string {
		if ( is_ssl() ) {
			return 'https';
		}

		$forwarded = (string) ( $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '' );
		if ( defined( 'SP_ACCELERATOR_TRUST_FORWARDED_PROTO' ) && SP_ACCELERATOR_TRUST_FORWARDED_PROTO
			&& strtolower( trim( explode( ',', $forwarded )[0] ?? '' ) ) === 'https' ) {
			return 'https';
		}

		return 'http';
	}

	private function has_only_ignored_query_args(): bool {
		$query = (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_QUERY );
		if ( $query === '' ) {
			return true;
		}

		parse_str( $query, $args );
		if ( ! is_array( $args ) ) {
			return false;
		}

		foreach ( array_keys( $args ) as $key ) {
			$key = strtolower( (string) $key );
			if ( strpos( $key, 'utm_' ) === 0 || in_array( $key, [ 'gclid', 'fbclid', 'msclkid', '_ga', 'mc_cid', 'mc_eid' ], true ) ) {
				continue;
			}
			return false;
		}

		return true;
	}

	private function has_private_cookie(): bool {
		$cookie = (string) ( $_SERVER['HTTP_COOKIE'] ?? '' );
		if ( $cookie === '' ) {
			return false;
		}

		$names = [];
		foreach ( explode( ';', $cookie ) as $pair ) {
			$name = strtolower( trim( (string) strtok( $pair, '=' ) ) );
			if ( $name !== '' ) {
				$names[] = $name;
			}
		}

		foreach ( $this->config->excluded_cookies() as $marker ) {
			foreach ( $names as $name ) {
				if ( strpos( $name, $marker ) !== false ) {
					return true;
				}
			}
		}

		if ( ! $this->config->enabled( 'bypass_unknown_cookies' ) ) {
			return false;
		}

		$allowed = $this->config->allowed_cookies();
		foreach ( $names as $name ) {
			$known = false;
			foreach ( $allowed as $marker ) {
				if ( strpos( $name, $marker ) === 0 ) {
					$known = true;
					break;
				}
			}
			if ( ! $known ) {
				return true;
			}
		}

		return false;
	}

	private function has_authorization(): bool {
		return ! empty( $_SERVER['HTTP_AUTHORIZATION'] )
			|| ! empty( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] )
			|| ! empty( $_SERVER['PHP_AUTH_USER'] )
			|| ! empty( $_SERVER['PHP_AUTH_PW'] )
			|| ! empty( $_SERVER['PHP_AUTH_DIGEST'] );
	}

	private function has_allowed_host(): bool {
		$raw  = strtolower( trim( (string) ( $_SERVER['HTTP_HOST'] ?? '' ) ) );
		$host = preg_replace( '/[^a-z0-9.\-:\[\]]/i', '', $raw ) ?: '';
		return $host !== '' && $host === $raw && in_array( $host, $this->config->allowed_hosts(), true );
	}

	private function is_excluded_path( string $path ): bool {
		foreach ( $this->config->excluded_paths() as $rule ) {
			if ( $rule === '' ) {
				continue;
			}

			if ( $rule[0] === '~' ) {
				$matched = @preg_match( $rule, $path );
				if ( $matched === 1 ) {
					return true;
				}
				continue;
			}

			if ( strpos( $path, $rule ) === 0 ) {
				return true;
			}
		}

		return false;
	}
}
