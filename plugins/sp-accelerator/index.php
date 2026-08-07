<?php
/**
 * SP Accelerator
 *
 * Clean-room, theme-bundled performance layer for Targetized.
 * No license checks, telemetry, external services or vendor runtime.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'sp_accelerator_boot_failure' ) ) {
	/** @param Throwable|string $error */
	function sp_accelerator_boot_failure( $error ): void {
		$message = $error instanceof Throwable ? $error->getMessage() : (string) $error;
		if ( function_exists( 'error_log' ) ) {
			error_log( '[SP Accelerator] ' . $message );
		}

		if ( function_exists( 'add_action' ) && is_admin() ) {
			add_action(
				'admin_notices',
				static function (): void {
					echo '<div class="notice notice-error"><p><strong>SP Accelerator отключён:</strong> набор файлов загружен не полностью или несовместим с PHP. Переустановите модуль <code>sp-accelerator</code> из SoinProduction PHP Kit.</p></div>';
				}
			);
		}
	}
}

$sp_accelerator_files = [
	'/includes/class-config.php',
	'/includes/class-request.php',
	'/includes/class-cache.php',
	'/includes/class-assets.php',
	'/includes/class-markup.php',
	'/includes/class-server.php',
	'/includes/class-dropin.php',
	'/includes/class-object-cache.php',
	'/includes/class-warmer.php',
	'/includes/class-admin.php',
];

foreach ( $sp_accelerator_files as $sp_accelerator_file ) {
	$sp_accelerator_path = __DIR__ . $sp_accelerator_file;
	if ( ! is_readable( $sp_accelerator_path ) ) {
		sp_accelerator_boot_failure( 'Missing required file: ' . $sp_accelerator_file );
		return;
	}

	try {
		require_once $sp_accelerator_path;
	} catch ( Throwable $sp_accelerator_error ) {
		sp_accelerator_boot_failure( $sp_accelerator_error );
		return;
	}
}

if ( ! class_exists( 'SP_Accelerator_Plugin' ) ) {
	final class SP_Accelerator_Plugin {
		/** @var self|null */
		private static $instance = null;

		/** @var SP_Accelerator_Config */
		private $config;

		/** @var SP_Accelerator_Assets */
		private $assets;

		/** @var SP_Accelerator_Cache */
		private $cache;

		/** @var SP_Accelerator_Markup */
		private $markup;

		/** @var SP_Accelerator_Dropin */
		private $dropin;

		/** @var SP_Accelerator_Server */
		private $server;

		/** @var SP_Accelerator_Object_Cache */
		private $object_cache;

		/** @var SP_Accelerator_Warmer */
		private $warmer;

		public static function get(): self {
			if ( self::$instance === null ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		private function __construct() {
			$this->config = new SP_Accelerator_Config();
			$request      = new SP_Accelerator_Request( $this->config );
			$this->cache  = new SP_Accelerator_Cache( $this->config, $request );
			$this->assets = new SP_Accelerator_Assets( $this->config );
			$this->markup = new SP_Accelerator_Markup( $this->config );
			$this->server = new SP_Accelerator_Server();
			$this->dropin = new SP_Accelerator_Dropin( $this->config, __DIR__ );
			$this->object_cache = new SP_Accelerator_Object_Cache( $this->config, __DIR__ );
			$warmer = new SP_Accelerator_Warmer( $this->cache, $this->config );
			$this->warmer = $warmer;

			$this->cache->register();
			$this->assets->register();
			$this->markup->register();
			$warmer->register();
			add_action( 'switch_theme', [ $this, 'on_theme_switch' ] );
			add_action( 'after_switch_theme', [ $this, 'on_theme_activated' ], 1 );

			if ( is_admin() ) {
				$plugin_url = trailingslashit( \SoinProduction\Kit\Bootstrapper::pathToUrl( __DIR__ ) );
				$admin      = new SP_Accelerator_Admin( $this->config, $this->cache, $this->dropin, $this->object_cache, $warmer, $plugin_url, $this->server );
				$admin->register();
				add_action( 'admin_init', [ $this, 'ensure_config' ], 2 );
			}
		}

		public function ensure_config(): void {
			$data = null;
			if ( is_readable( $this->config->config_file() ) ) {
				$json = file_get_contents( $this->config->config_file() );
				$data = is_string( $json ) ? json_decode( $json, true ) : null;
			}
			$expected_enabled = $this->config->enabled( 'page_cache' );
			$needs_sync = ! is_array( $data )
				|| (string) ( $data['signature'] ?? '' ) !== 'SP Accelerator cache config'
				|| (string) ( $data['version'] ?? '' ) !== SP_Accelerator_Config::VERSION
				|| ! array_key_exists( 'enabled', $data )
				|| (bool) $data['enabled'] !== $expected_enabled
				|| ( $expected_enabled && ! hash_equals( $this->config->warm_token(), (string) ( $data['warm_token'] ?? '' ) ) )
				|| ( $expected_enabled && (string) ( $data['cache_root'] ?? '' ) !== $this->config->cache_root() );
			if ( $needs_sync ) {
				$this->config->sync_dropin_config();
			}
		}

		/** @param mixed ...$unused */
		public function on_theme_switch( ...$unused ): void {
			$this->config->mark_runtime_disabled();
			if ( ! $this->config->disable_dropin_config() ) {
				sp_accelerator_boot_failure( 'Theme switch: failed to disable early page-cache config.' );
			}
			$this->warmer->cancel();
			wp_clear_scheduled_hook( 'sp_accelerator_daily_cleanup' );
			if ( ! $this->cache->remove_all_page_entries() ) {
				sp_accelerator_boot_failure( 'Theme switch: failed to remove cached page entries.' );
			}

			if ( in_array( $this->server->status()['code'], [ 'active', 'outdated', 'ignored' ], true ) ) {
				$this->log_cleanup_result( 'server rules', $this->server->remove() );
			}
			$status = $this->dropin->status();
			if ( in_array( $status['code'], [ 'active', 'inactive', 'outdated', 'blocked' ], true ) ) {
				$this->log_cleanup_result( 'page-cache drop-in', $this->dropin->remove() );
			}
			$object_status = $this->object_cache->status();
			if ( in_array( $object_status['code'], [ 'active', 'installed', 'outdated' ], true ) ) {
				$this->log_cleanup_result( 'object-cache drop-in', $this->object_cache->remove() );
			}
		}

		/** @param mixed ...$unused */
		public function on_theme_activated( ...$unused ): void {
			$this->config->activate_runtime();
			if ( ! $this->config->sync_dropin_config() ) {
				sp_accelerator_boot_failure( 'Theme activation: failed to synchronize page-cache config.' );
			}
		}

		/** @param true|WP_Error $result */
		private function log_cleanup_result( string $component, $result ): void {
			if ( is_wp_error( $result ) ) {
				sp_accelerator_boot_failure( 'Theme switch cleanup failed for ' . $component . ': ' . $result->get_error_message() );
			}
		}

		public function asset_version( string $relative_path ): string {
			return $this->assets->asset_version( $relative_path );
		}
	}
}

if ( ! function_exists( 'sp_theme_accelerator_asset_version' ) ) {
	function sp_theme_accelerator_asset_version( string $relative_path ): string {
		try {
			return SP_Accelerator_Plugin::get()->asset_version( $relative_path );
		} catch ( Throwable $error ) {
			sp_accelerator_boot_failure( $error );
			return defined( '_S_VERSION' ) ? (string) _S_VERSION : '1.0.0';
		}
	}
}

try {
	SP_Accelerator_Plugin::get();
} catch ( Throwable $sp_accelerator_error ) {
	sp_accelerator_boot_failure( $sp_accelerator_error );
}
