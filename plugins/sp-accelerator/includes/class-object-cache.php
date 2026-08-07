<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SP_Accelerator_Object_Cache {
	private const SIGNATURE = 'SP Accelerator Object Cache Drop-in';

	/** @var SP_Accelerator_Config */
	private $config;

	/** @var string */
	private $plugin_dir;

	public function __construct( SP_Accelerator_Config $config, string $plugin_dir ) {
		$this->config     = $config;
		$this->plugin_dir = rtrim( $plugin_dir, '/\\' );
	}

	public function path(): string {
		return trailingslashit( WP_CONTENT_DIR ) . 'object-cache.php';
	}

	public function database_path(): string {
		$directory = $this->cache_directory();
		return $this->cache_directory_is_dedicated()
			? trailingslashit( $directory ) . 'object-cache.sqlite'
			: '';
	}

	/** @return array{code:string,label:string,detail:string} */
	public function status(): array {
		if ( ! is_file( $this->path() ) ) {
			if ( ! class_exists( 'SQLite3', false ) ) {
				return [
					'code'   => 'unavailable',
					'label'  => 'SQLite недоступен',
					'detail' => 'Для persistent object cache требуется PHP extension sqlite3.',
				];
			}
			if ( ! $this->storage_is_safe_for_server() ) {
				return [
					'code'   => 'blocked',
					'label'  => 'Заблокирован безопасностью',
					'detail' => 'Сначала вынесите object-cache storage за фактический document root либо проверьте deny и задайте SP_ACCELERATOR_OBJECT_CACHE_WEB_PROTECTED=true.',
				];
			}
			return [
				'code'   => 'missing',
				'label'  => 'Не установлен',
				'detail' => 'WordPress использует только request-local object cache.',
			];
		}

		if ( $this->owns_dropin() ) {
			$loaded = defined( 'SP_ACCELERATOR_OBJECT_CACHE' );
			$source = $this->plugin_dir . '/dropin/object-cache.php.txt';
			$current_hash = is_readable( $source ) ? hash_file( 'sha256', $source ) : false;
			$installed_hash = hash_file( 'sha256', $this->path() );
			if ( is_string( $current_hash ) && is_string( $installed_hash ) && ! hash_equals( $current_hash, $installed_hash ) ) {
				return [
					'code'   => 'outdated',
					'label'  => 'Нужно обновить',
					'detail' => 'Установлена старая версия object-cache.php. Обновите drop-in перед включением кеша.',
				];
			}
			if ( ! class_exists( 'SQLite3', false ) ) {
				return [
					'code'   => 'installed',
					'label'  => 'Установлен, SQLite недоступен',
					'detail' => 'Drop-in принадлежит SP Accelerator и может быть удалён, но persistence отключён: extension sqlite3 недоступен.',
				];
			}
			if ( ! $this->storage_is_safe_for_server() ) {
				return [
					'code'   => 'installed',
					'label'  => 'Нужна защита Nginx',
					'detail' => 'Persistence отключён fail-closed: вынесите SP_ACCELERATOR_OBJECT_CACHE_DIR за фактический document root либо проверьте deny и задайте SP_ACCELERATOR_OBJECT_CACHE_WEB_PROTECTED=true.',
				];
			}
			$persistent = defined( 'SP_ACCELERATOR_OBJECT_CACHE_PERSISTENT' );
			if ( $loaded && isset( $GLOBALS['wp_object_cache'] ) && is_object( $GLOBALS['wp_object_cache'] ) && method_exists( $GLOBALS['wp_object_cache'], 'is_persistent' ) ) {
				$persistent = (bool) $GLOBALS['wp_object_cache']->is_persistent();
			}
			return [
				'code'   => $loaded && $persistent ? 'active' : 'installed',
				'label'  => $loaded && $persistent ? 'Активен' : 'Установлен',
				'detail' => $loaded && $persistent
					? 'Объекты WordPress сохраняются в SQLite между запросами.'
					: ( $loaded
						? 'Drop-in загружен, но SQLite persistence не инициализирован. Проверьте права каталога и защитные файлы.'
						: 'Файл установлен; он загрузится со следующего запроса WordPress.' ),
			];
		}

		return [
			'code'   => 'foreign',
			'label'  => 'Конфликт',
			'detail' => 'object-cache.php принадлежит другому плагину и не будет перезаписан.',
		];
	}

	/** @return true|WP_Error */
	public function install() {
		if ( $this->config->has_legacy_accelerator_conflict() ) {
			return new WP_Error( 'legacy_accelerator_active', 'Сначала деактивируйте Seraphinite Accelerator.' );
		}

		$status = $this->status();
		if ( $status['code'] === 'foreign' ) {
			return new WP_Error( 'foreign_object_cache', $status['detail'] );
		}

		if ( ! class_exists( 'SQLite3', false ) ) {
			return new WP_Error( 'sqlite_missing', 'PHP extension sqlite3 недоступен.' );
		}

		$source = $this->plugin_dir . '/dropin/object-cache.php.txt';
		if ( ! is_readable( $source ) ) {
			return new WP_Error( 'missing_source', 'Не найден шаблон object-cache.php.' );
		}

		$contents = file_get_contents( $source );
		if ( ! is_string( $contents ) || strpos( $contents, self::SIGNATURE ) === false ) {
			return new WP_Error( 'invalid_source', 'Шаблон object-cache.php повреждён.' );
		}

		if ( ! $this->cache_directory_is_dedicated() ) {
			return new WP_Error( 'invalid_cache_directory', 'SP_ACCELERATOR_OBJECT_CACHE_DIR должен указывать на отдельный абсолютный каталог SP Accelerator и не может быть системным, WordPress или document root.' );
		}
		if ( ! $this->storage_is_safe_for_server() ) {
			return new WP_Error( 'cache_protection_required', 'Каталог object cache не подтверждён как недоступный из web. Вынесите SP_ACCELERATOR_OBJECT_CACHE_DIR за фактический document root или проверьте deny и задайте SP_ACCELERATOR_OBJECT_CACHE_WEB_PROTECTED=true.' );
		}
		if ( ! $this->cache_directory_is_owned( $this->cache_directory() ) ) {
			return new WP_Error( 'cache_directory_unowned', 'Каталог object cache уже содержит чужие или неподтверждённые файлы. Используйте новый пустой каталог либо каталог с точными protection-файлами или подписанным config.json SP Accelerator.' );
		}
		if ( ! $this->sync_storage_protection() ) {
			return new WP_Error( 'cache_protection_failed', 'Не удалось создать или проверить защитные конфиги каталога object cache.' );
		}

		if ( ! $this->harden_database_files() ) {
			return new WP_Error( 'cache_permissions_failed', 'Не удалось установить права 0600 на файлы SQLite object cache.' );
		}

		if ( ! $this->config->atomic_write( $this->path(), $contents ) ) {
			return new WP_Error( 'write_failed', 'WordPress не смог записать wp-content/object-cache.php.' );
		}

		clearstatcache( true, $this->path() );
		$status = $this->status();
		return in_array( $status['code'], [ 'active', 'installed' ], true )
			? true
			: new WP_Error( 'verify_failed', 'Object-cache drop-in записан, но проверка не прошла.' );
	}

	/** @return true|WP_Error */
	public function remove() {
		if ( ! $this->owns_dropin() ) {
			return new WP_Error( 'not_owned', 'Удаление отменено: object-cache.php не принадлежит SP Accelerator.' );
		}

		if ( defined( 'SP_ACCELERATOR_OBJECT_CACHE' ) ) {
			if ( function_exists( 'wp_cache_flush_runtime' ) ) {
				wp_cache_flush_runtime();
			}
			if ( function_exists( 'wp_cache_close' ) ) {
				wp_cache_close();
			}
		}
		// Never delegate removal to an older drop-in whose flush may be global.
		$flushed = $this->flush_database_namespace();

		$delete_database = $flushed ? $this->database_can_be_deleted_after_flush() : null;
		if ( $delete_database === true && ! $this->delete_database_files() ) {
			return new WP_Error( 'database_remove_failed', 'Не удалось удалить SQLite object-cache database, WAL или SHM.' );
		}
		if ( $delete_database !== true ) {
			// A shared or uninspectable database is deliberately preserved.
			$this->harden_database_files();
		}
		if ( ! @unlink( $this->path() ) ) {
			return new WP_Error( 'remove_failed', 'Не удалось удалить wp-content/object-cache.php.' );
		}

		return true;
	}

	public function flush(): bool {
		if ( defined( 'SP_ACCELERATOR_OBJECT_CACHE' ) && function_exists( 'wp_cache_flush' ) && $this->active_cache_supports_namespace_flush() ) {
			return (bool) wp_cache_flush();
		}
		if ( defined( 'SP_ACCELERATOR_OBJECT_CACHE' ) && function_exists( 'wp_cache_flush_runtime' ) ) {
			wp_cache_flush_runtime();
		}

		return $this->flush_database_namespace();
	}

	private function flush_database_namespace(): bool {
		$db_path = $this->database_path();
		if ( $db_path === '' ) {
			return false;
		}
		if ( ! is_file( $db_path ) ) {
			return ! $this->database_artifacts_exist();
		}
		if ( ! class_exists( 'SQLite3', false ) ) {
			return false;
		}

		try {
			$db = new SQLite3( $db_path, SQLITE3_OPEN_READWRITE );
			$db->busyTimeout( 1000 );
			$stmt = @$db->prepare( 'DELETE FROM sp_cache WHERE scope LIKE :scope_prefix' );
			if ( ! $stmt ) {
				$db->close();
				return false;
			}
			$stmt->bindValue( ':scope_prefix', $this->namespace_prefix() . '%', SQLITE3_TEXT );
			$query  = @$stmt->execute();
			$result = $query !== false;
			if ( $query instanceof SQLite3Result ) {
				$query->finalize();
			}
			$stmt->close();
			@$db->exec( 'PRAGMA wal_checkpoint(TRUNCATE)' );
			$db->close();
			return $result && $this->harden_database_files();
		} catch ( Throwable $error ) {
			return false;
		}
	}

	private function active_cache_supports_namespace_flush(): bool {
		if ( ! isset( $GLOBALS['wp_object_cache'] ) || ! is_object( $GLOBALS['wp_object_cache'] ) || ! method_exists( $GLOBALS['wp_object_cache'], 'sp_accelerator_supports_namespace_flush' ) ) {
			return false;
		}

		try {
			return (bool) $GLOBALS['wp_object_cache']->sp_accelerator_supports_namespace_flush();
		} catch ( Throwable $error ) {
			return false;
		}
	}

	/** @return array{items:int,bytes:int} */
	public function stats(): array {
		$items = 0;
		$bytes = 0;
		$path  = $this->database_path();
		if ( $path === '' ) {
			return [ 'items' => 0, 'bytes' => 0 ];
		}

		foreach ( [ $path, $path . '-wal', $path . '-shm' ] as $file ) {
			if ( is_file( $file ) ) {
				$size = filesize( $file );
				$bytes += $size !== false ? $size : 0;
			}
		}

		if ( ! class_exists( 'SQLite3', false ) || ! is_file( $path ) ) {
			return [ 'items' => 0, 'bytes' => $bytes ];
		}

		try {
			$db = new SQLite3( $path, SQLITE3_OPEN_READONLY );
			$db->busyTimeout( 100 );
			$stmt = @$db->prepare( 'SELECT COUNT(*) FROM sp_cache WHERE scope LIKE :scope_prefix AND (expires = 0 OR expires > :now)' );
			$value = false;
			if ( $stmt ) {
				$stmt->bindValue( ':scope_prefix', $this->namespace_prefix() . '%', SQLITE3_TEXT );
				$stmt->bindValue( ':now', time(), SQLITE3_INTEGER );
				$result = @$stmt->execute();
				$row    = $result instanceof SQLite3Result ? $result->fetchArray( SQLITE3_NUM ) : false;
				$value  = is_array( $row ) ? $row[0] : false;
				if ( $result instanceof SQLite3Result ) {
					$result->finalize();
				}
				$stmt->close();
			}
			$items = is_numeric( $value ) ? (int) $value : 0;
			$db->close();
		} catch ( Throwable $error ) {
			$items = 0;
		}

		return [ 'items' => $items, 'bytes' => $bytes ];
	}

	private function cache_directory(): string {
		if ( defined( 'SP_ACCELERATOR_OBJECT_CACHE_DIR' ) ) {
			return trim( (string) SP_ACCELERATOR_OBJECT_CACHE_DIR );
		}

		return trim( $this->config->cache_root() );
	}

	private function storage_is_safe_for_server(): bool {
		if ( ! $this->cache_directory_is_dedicated() ) {
			return false;
		}
		if ( defined( 'SP_ACCELERATOR_OBJECT_CACHE_WEB_PROTECTED' ) && SP_ACCELERATOR_OBJECT_CACHE_WEB_PROTECTED ) {
			return true;
		}
		$document_root = defined( 'SP_ACCELERATOR_DOCUMENT_ROOT' )
			? (string) SP_ACCELERATOR_DOCUMENT_ROOT
			: (string) ( $_SERVER['DOCUMENT_ROOT'] ?? '' );
		if ( ! $this->is_absolute_non_root_path( $document_root ) ) {
			return false;
		}

		$directory = $this->normalized_path( $this->cache_directory() );
		foreach ( [ ABSPATH, WP_CONTENT_DIR, $document_root ] as $root ) {
			$root = $this->normalized_path( (string) $root );
			if ( $this->path_is_within( $directory, $root ) ) {
				return false;
			}
		}
		return true;
	}

	private function cache_directory_is_dedicated(): bool {
		$raw_directory = $this->cache_directory();
		if ( ! $this->is_absolute_non_root_path( $raw_directory ) || preg_match( '~(?:^|[\\/])\.\.?(?:[\\/]|$)~', $raw_directory ) ) {
			return false;
		}

		$directory = $this->normalized_path( $raw_directory );
		if ( ! $this->is_absolute_non_root_path( $directory ) || preg_match( '/(?:^|[-_.])sp-accelerator(?:[-_.]|$)/i', basename( $directory ) ) !== 1 ) {
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
			if ( $root !== '' && ( $directory === $root || $this->path_is_within( $root, $directory ) ) ) {
				return false;
			}
		}

		return true;
	}

	private function is_absolute_non_root_path( string $path ): bool {
		$path = rtrim( trim( $path ), '/\\' );
		$absolute = strpos( $path, '/' ) === 0 || preg_match( '/^[a-zA-Z]:[\\\\\/]/', $path ) === 1;
		return $absolute && $path !== '' && $path !== DIRECTORY_SEPARATOR && dirname( $path ) !== $path;
	}

	private function normalized_path( string $path ): string {
		$resolved = realpath( $path );
		if ( $resolved === false ) {
			$tail  = [];
			$probe = $path;
			while ( $probe !== '' && realpath( $probe ) === false ) {
				$parent = dirname( $probe );
				if ( $parent === $probe ) {
					break;
				}
				array_unshift( $tail, basename( $probe ) );
				$probe = $parent;
			}
			$parent = realpath( $probe );
			if ( $parent !== false ) {
				$resolved = rtrim( $parent, '/\\' );
				if ( $tail ) {
					$resolved .= DIRECTORY_SEPARATOR . implode( DIRECTORY_SEPARATOR, $tail );
				}
			} else {
				$resolved = $path;
			}
		}
		$path = (string) $resolved;
		$path = rtrim( str_replace( '\\', '/', $path ), '/' );
		return DIRECTORY_SEPARATOR === '\\' ? strtolower( $path ) : $path;
	}

	private function path_is_within( string $path, string $root ): bool {
		return $path !== '' && $root !== '' && strpos( $path . '/', rtrim( $root, '/' ) . '/' ) === 0;
	}

	private function namespace_prefix(): string {
		if ( defined( 'WP_CACHE_KEY_SALT' ) && (string) WP_CACHE_KEY_SALT !== '' ) {
			$seed = 'salt:' . (string) WP_CACHE_KEY_SALT;
		} elseif ( defined( 'AUTH_KEY' ) && (string) AUTH_KEY !== '' ) {
			$seed = 'auth:' . (string) AUTH_KEY;
		} else {
			$seed = 'root:' . ( defined( 'ABSPATH' ) ? (string) ABSPATH : 'wordpress' );
		}

		return 'sp:' . hash( 'sha256', $seed ) . ':';
	}

	private function owns_dropin(): bool {
		if ( ! is_file( $this->path() ) || ! is_readable( $this->path() ) ) {
			return false;
		}

		$head = file_get_contents( $this->path(), false, null, 0, 1024 );
		return is_string( $head ) && strpos( $head, self::SIGNATURE ) !== false;
	}

	private function sync_storage_protection(): bool {
		if ( ! $this->cache_directory_is_dedicated() || ! $this->storage_is_safe_for_server() ) {
			return false;
		}

		$directory = $this->cache_directory();
		if ( ! $this->cache_directory_is_owned( $directory ) ) {
			return false;
		}
		if ( ! is_dir( $directory ) && ! wp_mkdir_p( $directory ) ) {
			return false;
		}
		if ( ! $this->cache_directory_is_owned( $directory ) ) {
			return false;
		}
		if ( ! @chmod( $directory, 0700 ) ) {
			return false;
		}

		$files  = $this->protection_files();
		$legacy = $this->legacy_protection_files();

		foreach ( $files as $name => $contents ) {
			$path = trailingslashit( $directory ) . $name;
			if ( is_link( $path ) ) {
				return false;
			}
			if ( is_file( $path ) ) {
				$current = file_get_contents( $path );
				if ( ! is_string( $current ) ) {
					return false;
				}
				$matches_current = hash_equals( hash( 'sha256', $contents ), hash( 'sha256', $current ) );
				$matches_legacy  = isset( $legacy[ $name ] ) && hash_equals( hash( 'sha256', $legacy[ $name ] ), hash( 'sha256', $current ) );
				if ( $matches_current || $matches_legacy ) {
					if ( ! @chmod( $path, 0644 ) ) {
						return false;
					}
					continue;
				}
				return false;
			}

			if ( ! $this->config->atomic_write( $path, $contents ) ) {
				return false;
			}
		}

		return true;
	}

	private function cache_directory_is_owned( string $directory ): bool {
		if ( is_link( $directory ) ) {
			return false;
		}
		if ( ! file_exists( $directory ) ) {
			return true;
		}
		if ( ! is_dir( $directory ) ) {
			return false;
		}

		$entries = @scandir( $directory );
		if ( ! is_array( $entries ) ) {
			return false;
		}
		if ( count( array_diff( $entries, [ '.', '..' ] ) ) === 0 ) {
			return true;
		}

		$known = $this->protection_files();
		foreach ( $this->legacy_protection_files() as $name => $contents ) {
			$known[ $name ] = isset( $known[ $name ] ) ? [ $known[ $name ], $contents ] : [ $contents ];
		}
		foreach ( $known as $name => $contents ) {
			$path = trailingslashit( $directory ) . $name;
			if ( is_link( $path ) || ! is_readable( $path ) ) {
				continue;
			}
			$current = file_get_contents( $path );
			foreach ( (array) $contents as $expected ) {
				if ( is_string( $current ) && hash_equals( hash( 'sha256', $expected ), hash( 'sha256', $current ) ) ) {
					return true;
				}
			}
		}

		$config_path = trailingslashit( $directory ) . 'config.json';
		if ( is_link( $config_path ) || ! is_readable( $config_path ) ) {
			return false;
		}
		$json = file_get_contents( $config_path );
		$data = is_string( $json ) ? json_decode( $json, true ) : null;
		return is_array( $data )
			&& isset( $data['signature'], $data['cache_root'] )
			&& hash_equals( 'SP Accelerator cache config', (string) $data['signature'] )
			&& $this->normalized_path( (string) $data['cache_root'] ) === $this->normalized_path( $directory );
	}

	/** @return array<string,string> */
	private function protection_files(): array {
		return [
			'.htaccess' => "# SP Accelerator cache protection\nOptions -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n",
			'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<!-- SP Accelerator cache protection -->\n<configuration><system.webServer><security><authorization><clear/><add accessType=\"Deny\" users=\"*\"/></authorization></security></system.webServer></configuration>\n",
			'index.php' => "<?php\n// SP Accelerator cache protection.\nhttp_response_code( 404 );\nexit;\n",
		];
	}

	/** @return array<string,string> */
	private function legacy_protection_files(): array {
		return [
			'.htaccess' => "Options -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n",
			'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><security><authorization><remove users=\"*\" roles=\"\" verbs=\"\"/><add accessType=\"Deny\" users=\"*\"/></authorization></security></system.webServer></configuration>\n",
			'index.php' => "<?php\nhttp_response_code( 404 );\nexit;\n",
		];
	}

	private function harden_database_files(): bool {
		$database = $this->database_path();
		if ( $database === '' ) {
			return false;
		}
		foreach ( [ $database, $database . '-wal', $database . '-shm', $database . '-journal' ] as $path ) {
			if ( is_file( $path ) && ! @chmod( $path, 0600 ) ) {
				return false;
			}
		}

		return true;
	}

	private function delete_database_files(): bool {
		$database = $this->database_path();
		if ( $database === '' ) {
			return false;
		}
		$ok       = true;
		foreach ( [ $database . '-wal', $database . '-shm', $database . '-journal', $database ] as $path ) {
			clearstatcache( true, $path );
			if ( is_file( $path ) && ! @unlink( $path ) ) {
				$ok = false;
			}
		}

		return $ok;
	}

	private function database_artifacts_exist(): bool {
		$database = $this->database_path();
		if ( $database === '' ) {
			return false;
		}

		foreach ( [ $database, $database . '-wal', $database . '-shm', $database . '-journal' ] as $path ) {
			if ( is_file( $path ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Return true only when the SQLite file is proven to contain no foreign scope.
	 * Null means the database could not be inspected safely and must be preserved.
	 */
	private function database_can_be_deleted_after_flush(): ?bool {
		$database = $this->database_path();
		if ( $database === '' ) {
			return null;
		}
		if ( ! is_file( $database ) ) {
			return $this->database_artifacts_exist() ? null : true;
		}
		if ( ! class_exists( 'SQLite3', false ) ) {
			return null;
		}

		$db = null;
		try {
			$db = new SQLite3( $database, SQLITE3_OPEN_READONLY );
			$db->busyTimeout( 1000 );
			if ( @$db->querySingle( 'PRAGMA quick_check' ) !== 'ok' ) {
				$db->close();
				return null;
			}

			$table_result = @$db->query( "SELECT 1 FROM sqlite_master WHERE type='table' AND name='sp_cache' LIMIT 1" );
			if ( ! ( $table_result instanceof SQLite3Result ) ) {
				$db->close();
				return null;
			}
			$has_cache_table = $table_result->fetchArray( SQLITE3_NUM ) !== false;
			$table_result->finalize();
			if ( ! $has_cache_table ) {
				$db->close();
				return null;
			}

			$other_table_result = @$db->query( "SELECT 1 FROM sqlite_master WHERE type='table' AND name NOT IN ('sp_cache','sqlite_sequence') LIMIT 1" );
			if ( ! ( $other_table_result instanceof SQLite3Result ) ) {
				$db->close();
				return null;
			}
			$has_other_table = $other_table_result->fetchArray( SQLITE3_NUM ) !== false;
			$other_table_result->finalize();
			if ( $has_other_table ) {
				$db->close();
				return false;
			}

			$stmt = @$db->prepare( 'SELECT 1 FROM sp_cache WHERE substr(scope,1,:prefix_length) <> :scope_prefix LIMIT 1' );
			if ( ! $stmt ) {
				$db->close();
				return null;
			}
			$prefix = $this->namespace_prefix();
			$stmt->bindValue( ':prefix_length', strlen( $prefix ), SQLITE3_INTEGER );
			$stmt->bindValue( ':scope_prefix', $prefix, SQLITE3_TEXT );
			$result = @$stmt->execute();
			if ( ! ( $result instanceof SQLite3Result ) ) {
				$stmt->close();
				$db->close();
				return null;
			}
			$has_foreign_scope = $result->fetchArray( SQLITE3_NUM ) !== false;
			$result->finalize();
			$stmt->close();
			$db->close();

			return ! $has_foreign_scope;
		} catch ( Throwable $error ) {
			if ( $db instanceof SQLite3 ) {
				@$db->close();
			}
			return null;
		}
	}
}
