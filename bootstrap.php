<?php
declare(strict_types=1);

namespace SoinProduction\Kit;

if (!defined('ABSPATH')) {
	exit;
}

class Bootstrapper {
	public static function run(): void {
		$root = __DIR__;

		$is_cli_request = defined('WP_CLI') && WP_CLI;
		$is_admin_like  = is_admin() || wp_doing_ajax() || wp_doing_cron() || $is_cli_request;
		$is_frontend    = !$is_admin_like;

		$frontend_skip_paths = [
			'plugins/sp-content-manager/', 
			'plugins/sp-video-preview/', 
			'plugins/sp-google-reviews/stars-column.php'
		];

		// Allow themes to modify the skip paths
		$frontend_skip_paths = apply_filters('theme_core_frontend_skip_paths', $frontend_skip_paths);
		$frontend_skip_paths = is_array($frontend_skip_paths) ? $frontend_skip_paths : [];

		$normalize_relative = static function (string $path) use ($root): string {
			$root_norm = str_replace('\\', '/', rtrim($root, DIRECTORY_SEPARATOR));
			$path_norm = str_replace('\\', '/', $path);
			$relative  = ltrim(str_replace($root_norm, '', $path_norm), '/');
			return trim($relative);
		};

		$should_skip_on_frontend = static function (string $path) use ($is_frontend, $frontend_skip_paths, $normalize_relative): bool {
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

		$autoload = static function (string $dir) use (&$autoload, $should_skip_on_frontend): void {
			if (!is_dir($dir) || !is_readable($dir) || $should_skip_on_frontend($dir)) {
				return;
			}

			$items = scandir($dir);
			if ($items === false) {
				return;
			}

			usort($items, static function (string $a, string $b): int {
				$priority = static function (string $item): int {
					return match ($item) {
						'platform' => 1,
						default    => 2,
					};
				};

				$a_priority = $priority($a);
				$b_priority = $priority($b);

				if ($a_priority !== $b_priority) {
					return $a_priority <=> $b_priority;
				}

				return strcasecmp($a, $b);
			});

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

		// Load platform and plugins from the kit
		$autoload($root . '/platform');
		$autoload($root . '/plugins');
	}
}

// Run the bootstrapper when this file is required by Composer
Bootstrapper::run();
