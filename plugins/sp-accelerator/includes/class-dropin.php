<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SP_Accelerator_Dropin {
	private const SIGNATURE = 'SP Accelerator Drop-in';

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
			return true;
		}

		if ( ! @unlink( $this->path() ) ) {
			return new WP_Error( 'remove_failed', 'Не удалось удалить advanced-cache.php.' );
		}

		return true;
	}
}
