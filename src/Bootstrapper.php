<?php
declare(strict_types=1);

namespace SoinProduction\Kit;

if (!defined('ABSPATH')) {
	exit;
}

class Bootstrapper {

	private const DISABLED_MODULE_PREFIX = '_';
	private static array $moduleConfigs = [];
	private static array $activeModules = [
		'platform' => [],
		'acf'      => [],
		'plugins'  => [],
	];

	private const MODULE_ALIASES = [
		'platform' => [
			'author-meta'          => 'sp-author-meta',
			'branding'             => 'sp-login-branding',
			'dev-user'             => 'sp-content-admin',
			'duplicator-key'       => 'sp-duplicator-license',
			'page-loader-settings' => 'sp-motion-settings',
			'post-type-converter' => 'sp-content-type-converter',
			'reading-time'         => 'sp-reading-time',
			'remove-post-slug'     => 'sp-permalink-manager',
			'reset'                => 'sp-wordpress-baseline',
		],
		'acf' => [
			'archive-builder'    => 'sp-archive-builder',
			'icon-link-list'     => 'sp-icon-links',
			'related-posts'      => 'sp-related-content',
			'smart-relationship' => 'sp-post-selector',
			'smart-taxonomy'     => 'sp-term-selector',
			'table'              => 'sp-table',
			'taxonomy-urls'      => 'sp-term-links',
			'universal-media'    => 'sp-media',
		],
		'plugins' => [
			'sp-allow-svg-upload'     => 'sp-svg-support',
			'sp-cpt-archives'         => 'sp-archive-pages',
			'sp-dev-mode'             => 'sp-debug-toolbar',
			'sp-favorite-posts'       => 'sp-content-favorites',
			'sp-redirects'            => 'sp-redirect-manager',
			'sp-uploads-webp-convert' => 'sp-webp-uploads',
			'sp-video-preview'        => 'sp-video-posters',
			'sp-wiki'                 => 'sp-documentation',
		],
	];

	private const DEFAULT_FRONTEND_SKIP_PATHS = [
		'plugins/sp-content-manager/',
		'plugins/sp-deployment-manager/',
		'plugins/sp-video-posters/',
		'plugins/sp-google-reviews/includes/stars-column.php',
	];

	private const DEFAULT_AUTOLOAD_SKIP_PATHS = [
		'plugins/sp-accelerator/includes/',
		'plugins/sp-accelerator/tests/',
		'plugins/sp-admin-ui/modules/',
		'plugins/sp-admin-ui/includes/',
		'plugins/sp-cf7/modules/',
		'plugins/sp-editor-tools/modules/',
	];

	public static function pathToUrl(string $path): string {
		if (!defined('THEME_DIR') || !defined('THEME_URI')) {
			return '';
		}

		$path      = str_replace('\\', '/', $path);
		$theme_dir = rtrim(str_replace('\\', '/', (string) THEME_DIR), '/') . '/';

		if (!str_starts_with($path, $theme_dir)) {
			return '';
		}

		return rtrim((string) THEME_URI, '/') . '/' . ltrim(substr($path, strlen($theme_dir)), '/');
	}

	public static function run(array $config = []): void {
		$root = dirname(__DIR__);

		$is_cli_request = defined('WP_CLI') && WP_CLI;
		$is_admin_like  = is_admin() || wp_doing_ajax() || wp_doing_cron() || $is_cli_request;
		$is_frontend    = !$is_admin_like;

		$platform_modules = self::normalize_modules($config['platform'] ?? [], 'platform');
		$acf_modules      = self::normalize_modules($config['acf'] ?? [], 'acf');
		$plugins_modules  = self::normalize_modules($config['plugins'] ?? [], 'plugins');
		$frontend_skip_paths = self::normalize_skip_paths(
			$config['frontend_skip_paths'] ?? self::DEFAULT_FRONTEND_SKIP_PATHS
		);
		$autoload_skip_paths = self::normalize_skip_paths(
			$config['autoload_skip_paths'] ?? self::DEFAULT_AUTOLOAD_SKIP_PATHS
		);

		$normalize_relative = static function (string $path) use ($root): string {
			$root_norm = str_replace('\\', '/', rtrim($root, DIRECTORY_SEPARATOR));
			$path_norm = str_replace('\\', '/', $path);
			$relative  = ltrim(str_replace($root_norm, '', $path_norm), '/');
			return trim($relative);
		};

		$should_skip_on_frontend = static function (string $path) use ($is_frontend, $normalize_relative, $frontend_skip_paths): bool {
			if (!$is_frontend) {
				return false;
			}

			$relative = $normalize_relative($path);
			if ($relative === '') {
				return false;
			}

			foreach ($frontend_skip_paths as $skip_path) {
				$skip = trim(str_replace('\\', '/', (string) $skip_path), '/');
				if ($skip === '') {
					continue;
				}

				$is_dir_rule = str_ends_with((string) $skip_path, '/') || str_ends_with((string) $skip_path, '\\');
				if ($is_dir_rule) {
					if (str_starts_with($relative . '/', $skip . '/')) {
						return true;
					}
					continue;
				}

				if ($relative === $skip) {
					return true;
				}
			}

			return false;
		};

		$should_skip_autoload = static function (string $path) use ($normalize_relative, $autoload_skip_paths): bool {
			$relative = $normalize_relative($path);

			foreach ($autoload_skip_paths as $skip_path) {
				$skip = trim(str_replace('\\', '/', (string) $skip_path), '/');
				if ($skip !== '' && str_starts_with($relative . '/', $skip . '/')) {
					return true;
				}
			}

			return false;
		};

		$autoload = static function (string $dir) use (&$autoload, $should_skip_on_frontend, $should_skip_autoload): void {
			if (!is_dir($dir) || !is_readable($dir) || $should_skip_on_frontend($dir) || $should_skip_autoload($dir)) {
				return;
			}

			$items = scandir($dir);
			if ($items === false) {
				return;
			}

			foreach ($items as $item) {
				if ($item === '.' || $item === '..') {
					continue;
				}

				if ($item[0] === '_') {
					continue;
				}

				if (strtolower($item) === 'templates' || strtolower($item) === 'blocks') {
					continue;
				}

				$path = $dir . DIRECTORY_SEPARATOR . $item;

				if ($should_skip_on_frontend($path) || $should_skip_autoload($path)) {
					continue;
				}

				if (is_dir($path)) {
					$autoload($path);
					continue;
				}

				if (!is_file($path)) {
					continue;
				}

				if (pathinfo($path, PATHINFO_EXTENSION) !== 'php') {
					continue;
				}

				require_once $path;
			}
		};

		$load_directories = static function (string $category, array $modules) use ($root, $autoload, $should_skip_on_frontend): void {
			foreach ($modules as $module) {
				$dir = $root . '/' . $category . '/' . $module;

				if (is_dir($dir)) {
					$autoload($dir);
				} elseif (is_file($dir . '.php')) {
					if (!$should_skip_on_frontend($dir . '.php')) {
						require_once $dir . '.php';
					}
				}
			}
		};

		// Platform modules load before theme runtime.
		$load_directories('platform', $platform_modules);

		// ACF field types/helpers should load before the theme registers field groups.
		$load_directories('acf', $acf_modules);

		// Feature modules load after the theme's own runtime is available.
		$load_directories('plugins', $plugins_modules);
	}

	public static function moduleConfig(string $category, string $module): ?array {
		return self::$moduleConfigs[$category][$module] ?? null;
	}

	/**
	 * Return the normalized modules selected by the current application.
	 *
	 * @return array<int, string>|array<string, array<int, string>>
	 */
	public static function activeModules(?string $category = null): array {
		if ($category !== null) {
			return self::$activeModules[$category] ?? [];
		}

		return self::$activeModules;
	}

	private static function normalize_modules($modules, string $category): array {
		if (!is_array($modules)) {
			self::$moduleConfigs[$category] = [];
			self::$activeModules[$category] = [];
			return [];
		}

		self::$moduleConfigs[$category] = [];
		$enabled = [];

		foreach ($modules as $key => $value) {
			$module        = is_string($key) ? $key : $value;
			$module_config = is_string($key) && is_array($value) ? $value : null;

			if (!is_string($module)) {
				continue;
			}

			$module = trim($module);

			if ($module === '' || str_starts_with($module, self::DISABLED_MODULE_PREFIX)) {
				continue;
			}

			if (preg_match('/^[a-z0-9][a-z0-9_-]*$/i', $module) !== 1) {
				continue;
			}

			$module = self::MODULE_ALIASES[$category][$module] ?? $module;

			$enabled[$module] = true;

			if ($module_config !== null) {
				self::$moduleConfigs[$category][$module] = $module_config;
			}
		}

		self::$activeModules[$category] = array_keys($enabled);

		return self::$activeModules[$category];
	}

	private static function normalize_skip_paths($paths): array {
		if (!is_array($paths)) {
			return self::DEFAULT_FRONTEND_SKIP_PATHS;
		}

		return array_values(array_filter($paths, static function ($path): bool {
			return is_string($path) && $path !== '' && !str_contains($path, '..');
		}));
	}
}
