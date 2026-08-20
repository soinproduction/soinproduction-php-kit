<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SP_Accelerator_Dropin {
	private const SIGNATURE = 'SP Accelerator Drop-in';
	private const WP_CACHE_BEGIN = '/* BEGIN SP Accelerator WP_CACHE */';
	private const WP_CACHE_END   = '/* END SP Accelerator WP_CACHE */';

	/** @var SP_Accelerator_Config */
	private $config;

	/** @var string */
	private $plugin_dir;

	public function __construct( SP_Accelerator_Config $config, string $plugin_dir ) {
		$this->config     = $config;
		$this->plugin_dir = rtrim( $plugin_dir, '/\\' );
	}

	public function path(): string {
		return trailingslashit( WP_CONTENT_DIR ) . 'advanced-cache.php';
	}

	public function backup_path(): string {
		return trailingslashit( WP_CONTENT_DIR ) . 'advanced-cache.sp-accelerator-backup.php';
	}

	/** @return array{code:string,label:string,detail:string} */
	public function status(): array {
		$path = $this->path();
		if ( ! is_file( $path ) ) {
			return [
				'code'   => 'missing',
				'label'  => 'Не установлен',
				'detail' => 'Работает fallback-кеш после загрузки ядра WordPress.',
			];
		}

		$contents = file_get_contents( $path, false, null, 0, 1024 );
		if ( is_string( $contents ) && strpos( $contents, self::SIGNATURE ) !== false ) {
			$source = $this->plugin_dir . '/templates/advanced-cache.php';
			if ( is_readable( $source ) && hash_file( 'sha256', $source ) !== hash_file( 'sha256', $path ) ) {
				return [
					'code'   => 'outdated',
					'label'  => 'Требуется обновление',
					'detail' => 'Установленный advanced-cache.php старее шаблона SP Accelerator и пока не использует новую cache policy.',
				];
			}
			if ( ! defined( 'WP_CACHE' ) || ! WP_CACHE ) {
				return [
					'code'   => 'inactive',
					'label'  => 'Установлен, но не активен',
					'detail' => 'Добавьте define(\'WP_CACHE\', true); в wp-config.php.',
				];
			}
			if ( ! $this->config->storage_is_safe_for_server() ) {
				return [
					'code'   => 'blocked',
					'label'  => 'Заблокирован безопасностью',
					'detail' => $this->config->storage_safety_message(),
				];
			}

			return [
				'code'   => 'active',
				'label'  => 'Активен',
				'detail' => 'Кеш отдаётся до загрузки WordPress.',
			];
		}

		if ( is_string( $contents ) && strpos( $contents, 'Disabled by seraphinite-accelerator' ) !== false ) {
			return [
				'code'   => 'replaceable',
				'label'  => 'SP Drop-in не установлен',
				'detail' => 'Найдена неактивная заглушка старого плагина; её можно безопасно заменить.',
			];
		}

		return [
			'code'   => 'foreign',
			'label'  => 'Конфликт',
			'detail' => 'advanced-cache.php принадлежит другому кеш-плагину и не будет перезаписан.',
		];
	}

	/** @return true|WP_Error */
	public function install() {
		if ( $this->config->has_legacy_accelerator_conflict() ) {
			return new WP_Error( 'legacy_accelerator_active', 'Сначала деактивируйте Seraphinite Accelerator, затем установите наш drop-in.' );
		}
		if ( is_multisite() && ( ! defined( 'SP_ACCELERATOR_MULTISITE_CACHE' ) || ! SP_ACCELERATOR_MULTISITE_CACHE ) ) {
			return new WP_Error( 'multisite_disabled', 'Page-cache drop-in для Multisite отключён по умолчанию. Включайте его только после настройки общей cache policy константой SP_ACCELERATOR_MULTISITE_CACHE.' );
		}
		if ( ! $this->config->storage_is_safe_for_server() ) {
			return new WP_Error( 'unsafe_page_cache_storage', $this->config->storage_safety_message() );
		}

		$status = $this->status();
		if ( $status['code'] === 'foreign' ) {
			return new WP_Error( 'foreign_dropin', $status['detail'] );
		}

		$wp_cache = $this->ensure_wp_cache_enabled();
		if ( is_wp_error( $wp_cache ) ) {
			return $wp_cache;
		}

		$source = $this->plugin_dir . '/templates/advanced-cache.php';
		if ( ! is_readable( $source ) ) {
			return new WP_Error( 'missing_source', 'Не найден шаблон advanced-cache.php.' );
		}

		$contents = file_get_contents( $source );
		if ( ! is_string( $contents ) || strpos( $contents, self::SIGNATURE ) === false ) {
			return new WP_Error( 'invalid_source', 'Шаблон drop-in повреждён.' );
		}

		if ( $status['code'] === 'replaceable' && ! is_file( $this->backup_path() ) ) {
			$old = file_get_contents( $this->path() );
			if ( is_string( $old ) ) {
				$this->config->atomic_write( $this->backup_path(), $old );
			}
		}

		if ( ! $this->config->sync_dropin_config() || ! $this->config->atomic_write( $this->path(), $contents ) ) {
			return new WP_Error( 'write_failed', 'WordPress не смог записать wp-content/advanced-cache.php или каталог кеша.' );
		}

		clearstatcache( true, $this->path() );
		$verified = $this->status();
		if ( ! in_array( $verified['code'], [ 'active', 'inactive' ], true ) ) {
			return new WP_Error( 'verify_failed', 'Drop-in записан, но проверка файла не прошла.' );
		}

		return true;
	}

	/** @return true|WP_Error */
	public function remove() {
		$status = $this->status();
		if ( ! in_array( $status['code'], [ 'active', 'inactive', 'outdated', 'blocked' ], true ) ) {
			return new WP_Error( 'not_owned', 'Удаление отменено: текущий advanced-cache.php не принадлежит SP Accelerator.' );
		}

		if ( is_file( $this->backup_path() ) ) {
			$backup = file_get_contents( $this->backup_path() );
			if ( ! is_string( $backup ) || ! $this->config->atomic_write( $this->path(), $backup ) ) {
				return new WP_Error( 'restore_failed', 'Не удалось восстановить резервную копию прежнего drop-in.' );
			}
			@unlink( $this->backup_path() );
		} elseif ( ! @unlink( $this->path() ) ) {
			return new WP_Error( 'remove_failed', 'Не удалось удалить advanced-cache.php.' );
		}

		return $this->remove_wp_cache_marker();
	}

	/** @return true|WP_Error */
	public function ensure_wp_cache_enabled() {
		if ( defined( 'WP_CACHE' ) ) {
			return WP_CACHE
				? true
				: new WP_Error( 'wp_cache_forced_off', 'WP_CACHE явно задан как false. Автоматическая настройка не будет менять чужую константу.' );
		}

		$path = $this->wp_config_path();
		if ( $path === '' || ! is_readable( $path ) || ! is_writable( $path ) ) {
			return new WP_Error( 'wp_config_readonly', 'Не удалось автоматически включить WP_CACHE: wp-config.php не найден или недоступен для записи.' );
		}

		$contents = file_get_contents( $path );
		if ( ! is_string( $contents ) ) {
			return new WP_Error( 'wp_config_read_failed', 'Не удалось прочитать wp-config.php для включения WP_CACHE.' );
		}
		if ( strpos( $contents, self::WP_CACHE_BEGIN ) !== false || strpos( $contents, self::WP_CACHE_END ) !== false ) {
			$complete = strpos( $contents, self::WP_CACHE_BEGIN ) !== false && strpos( $contents, self::WP_CACHE_END ) !== false;
			return $complete
				? true
				: new WP_Error( 'wp_cache_marker_broken', 'Marker SP Accelerator в wp-config.php повреждён; автоматическая запись остановлена.' );
		}

		$matched = preg_match( '~^[ \\t]*require_once\\s*(?:\\(\\s*)?ABSPATH\\s*\\.\\s*[\'\"]/?wp-settings\\.php[\'\"]\\s*\\)?\\s*;~m', $contents, $anchor, PREG_OFFSET_CAPTURE );
		if ( $matched !== 1 || ! isset( $anchor[0][1] ) ) {
			return new WP_Error( 'wp_config_anchor_missing', 'В wp-config.php не найдена строка подключения wp-settings.php; автоматическая запись остановлена.' );
		}

		$block = self::WP_CACHE_BEGIN . "\n"
			. "if ( ! defined( 'WP_CACHE' ) ) {\n"
			. "\tdefine( 'WP_CACHE', true );\n"
			. "}\n"
			. self::WP_CACHE_END . "\n\n";
		$updated = substr_replace( $contents, $block, (int) $anchor[0][1], 0 );
		if ( ! $this->write_wp_config( $path, $contents, $updated ) ) {
			return new WP_Error( 'wp_config_write_failed', 'Не удалось автоматически записать WP_CACHE в wp-config.php.' );
		}

		$verified = file_get_contents( $path );
		return is_string( $verified ) && strpos( $verified, self::WP_CACHE_BEGIN ) !== false
			? true
			: new WP_Error( 'wp_config_verify_failed', 'WP_CACHE был записан, но проверка wp-config.php не прошла.' );
	}

	/** @return true|WP_Error */
	private function remove_wp_cache_marker() {
		$path = $this->wp_config_path();
		if ( $path === '' || ! is_readable( $path ) ) {
			return true;
		}
		$contents = file_get_contents( $path );
		if ( ! is_string( $contents ) || strpos( $contents, self::WP_CACHE_BEGIN ) === false ) {
			return true;
		}
		if ( ! is_writable( $path ) || strpos( $contents, self::WP_CACHE_END ) === false ) {
			return new WP_Error( 'wp_cache_marker_remove_failed', 'Drop-in удалён, но его WP_CACHE marker не удалось безопасно удалить из wp-config.php.' );
		}

		$pattern = '~(?:\\r?\\n)?' . preg_quote( self::WP_CACHE_BEGIN, '~' ) . '.*?' . preg_quote( self::WP_CACHE_END, '~' ) . '(?:\\r?\\n){0,2}~s';
		$updated = preg_replace( $pattern, "\n", $contents, 1, $count );
		if ( ! is_string( $updated ) || $count !== 1 || ! $this->write_wp_config( $path, $contents, $updated ) ) {
			return new WP_Error( 'wp_cache_marker_remove_failed', 'Drop-in удалён, но его WP_CACHE marker не удалось безопасно удалить из wp-config.php.' );
		}

		return true;
	}

	private function write_wp_config( string $path, string $original, string $updated ): bool {
		if ( $this->config->atomic_write( $path, $updated ) ) {
			return true;
		}

		// Some hosts allow changing wp-config.php but not creating a temporary
		// sibling file. In that case an atomic rename is impossible, so use a
		// locked in-place write and restore the original bytes if verification
		// fails.
		if ( ! is_writable( $path ) || file_put_contents( $path, $updated, LOCK_EX ) !== strlen( $updated ) ) {
			@file_put_contents( $path, $original, LOCK_EX );
			return false;
		}

		clearstatcache( true, $path );
		$verified = file_get_contents( $path );
		if ( ! is_string( $verified ) || ! hash_equals( hash( 'sha256', $updated ), hash( 'sha256', $verified ) ) ) {
			@file_put_contents( $path, $original, LOCK_EX );
			return false;
		}

		return true;
	}

	private function wp_config_path(): string {
		$candidates = [
			rtrim( (string) ABSPATH, '/\\' ) . DIRECTORY_SEPARATOR . 'wp-config.php',
			dirname( rtrim( (string) ABSPATH, '/\\' ) ) . DIRECTORY_SEPARATOR . 'wp-config.php',
		];
		foreach ( array_unique( $candidates ) as $candidate ) {
			if ( is_file( $candidate ) ) {
				return $candidate;
			}
		}

		return '';
	}
}
