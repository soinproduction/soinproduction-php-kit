<?php
declare(strict_types=1);

namespace SoinProduction\Kit;

if (!defined('ABSPATH')) {
	exit;
}

class Bootstrapper {

	// Hardcoded rules for performance: things that shouldn't load on frontend
	private static array $frontend_skip_paths = [
		'plugins/sp-content-manager/', 
		'plugins/sp-video-preview/', 
		'plugins/sp-google-reviews/stars-column.php'
	];

	public static function run(array $config = []): void {
		$root = dirname(__DIR__);

		$is_cli_request = defined('WP_CLI') && WP_CLI;
		$is_admin_like  = is_admin() || wp_doing_ajax() || wp_doing_cron() || $is_cli_request;
		$is_frontend    = !$is_admin_like;

		$platform_modules = $config['platform'] ?? [];
		$plugins_modules  = $config['plugins'] ?? [];

		$normalize_relative = static function (string $path) use ($root): string {
			$root_norm = str_replace('\\', '/', rtrim($root, DIRECTORY_SEPARATOR));
			$path_norm = str_replace('\\', '/', $path);
			$relative  = ltrim(str_replace($root_norm, '', $path_norm), '/');
			return trim($relative);
		};

		$should_skip_on_frontend = static function (string $path) use ($is_frontend, $normalize_relative): bool {
			if (!$is_frontend) {
				return false;
			}

			$relative = $normalize_relative($path);
			if ($relative === '') {
				return false;
			}

			foreach (self::$frontend_skip_paths as $skip_path) {
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

		$autoload = static function (string $dir) use (&$autoload, $should_skip_on_frontend): void {
			if (!is_dir($dir) || !is_readable($dir) || $should_skip_on_frontend($dir)) {
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

				if ($should_skip_on_frontend($path)) {
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

		// 1. Load explicitly requested platform files
		foreach ($platform_modules as $module) {
			$path = $root . '/platform/' . $module . '.php';
			if (is_file($path) && !$should_skip_on_frontend($path)) {
				require_once $path;
			}
		}

		// 2. Load explicitly requested plugins (scan their directories recursively)
		foreach ($plugins_modules as $plugin) {
			$dir = $root . '/plugins/' . $plugin;
			if (is_dir($dir)) {
				$autoload($dir);
			} elseif (is_file($dir . '.php')) {
				if (!$should_skip_on_frontend($dir . '.php')) {
					require_once $dir . '.php';
				}
			}
		}
	}
}
