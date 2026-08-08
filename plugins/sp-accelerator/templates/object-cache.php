<?php
/**
 * SP Accelerator Object Cache Drop-in
 * SQLite-backed persistent WordPress object cache.
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

if ( ! defined( 'SP_ACCELERATOR_OBJECT_CACHE' ) ) {
	define( 'SP_ACCELERATOR_OBJECT_CACHE', true );
}

if ( ! class_exists( 'WP_Object_Cache', false ) ) {
	class WP_Object_Cache {
		/** @var array<string,array{value:mixed,expires:int,group:string,scope:string}> */
		public $cache = array();

		/** @var int */
		public $cache_hits = 0;

		/** @var int */
		public $cache_misses = 0;

		/** @var string[] */
		private $global_groups = array();

		/** @var string[] */
		private $non_persistent_groups = array();

		/** @var int */
		private $blog_id = 1;

		/** @var SQLite3|null */
		private $db = null;

		/** @var bool */
		private $persistent = false;

		/** @var string */
		private $database_path = '';

		/** @var string */
		private $namespace = '';

		public function __construct() {
			$this->blog_id   = isset( $GLOBALS['blog_id'] ) ? max( 1, (int) $GLOBALS['blog_id'] ) : 1;
			$this->namespace = $this->build_namespace();
			$this->connect();
		}

		private function connect(): void {
			if ( ! defined( 'WP_CONTENT_DIR' ) ) {
				return;
			}

			$directory = $this->storage_directory();
			if ( ! $this->storage_directory_is_dedicated( $directory ) || ! $this->storage_is_safe_for_server( $directory ) ) {
				if ( $this->storage_cleanup_target_is_specific( $directory ) && $this->storage_is_confirmed_web_exposed( $directory ) ) {
					$this->remove_unsafe_database_files( $directory );
				}
				return;
			}
			if ( ! class_exists( 'SQLite3', false ) ) {
				return;
			}
			if ( ! $this->storage_directory_is_owned( $directory ) ) {
				return;
			}
			if ( ! is_dir( $directory ) && ! @mkdir( $directory, 0700, true ) && ! is_dir( $directory ) ) {
				return;
			}
			if ( ! $this->storage_directory_is_owned( $directory ) ) {
				return;
			}
			if ( ! $this->sync_storage_protection( $directory ) ) {
				return;
			}

			$this->database_path = $directory . '/object-cache.sqlite';
			try {
				$this->db = new SQLite3( $this->database_path, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE );
				if ( ! @chmod( $this->database_path, 0600 ) ) {
					$this->db->close();
					$this->db = null;
					return;
				}
				$this->db->busyTimeout( 1000 );
				@$this->db->exec( 'PRAGMA journal_mode=WAL' );
				@$this->db->exec( 'PRAGMA synchronous=NORMAL' );
				@$this->db->exec( 'PRAGMA temp_store=MEMORY' );
				$created = @$this->db->exec(
					'CREATE TABLE IF NOT EXISTS sp_cache (' .
					'scope TEXT NOT NULL,' .
					'group_name TEXT NOT NULL,' .
					'item_key TEXT NOT NULL,' .
					'cache_value BLOB NOT NULL,' .
					'expires INTEGER NOT NULL DEFAULT 0,' .
					'PRIMARY KEY (scope, group_name, item_key)' .
					') WITHOUT ROWID'
				);
				if ( ! $created ) {
					$this->db->close();
					$this->db = null;
					return;
				}
				@$this->db->exec( 'CREATE INDEX IF NOT EXISTS sp_cache_expiry ON sp_cache(expires)' );
				if ( ! $this->secure_database_files() ) {
					$this->db->close();
					$this->db = null;
					return;
				}
				$this->persistent = true;
				if ( ! defined( 'SP_ACCELERATOR_OBJECT_CACHE_PERSISTENT' ) ) {
					define( 'SP_ACCELERATOR_OBJECT_CACHE_PERSISTENT', true );
				}
				if ( mt_rand( 1, 100 ) === 1 ) {
					$stmt = @$this->db->prepare( 'DELETE FROM sp_cache WHERE scope LIKE :scope_prefix AND expires > 0 AND expires <= :now' );
					if ( $stmt ) {
						$stmt->bindValue( ':scope_prefix', $this->namespace . '%', SQLITE3_TEXT );
						$stmt->bindValue( ':now', time(), SQLITE3_INTEGER );
						$result = @$stmt->execute();
						if ( $result instanceof SQLite3Result ) {
							$result->finalize();
						}
						$stmt->close();
					}
				}
			} catch ( Throwable $error ) {
				$this->db         = null;
				$this->persistent = false;
			}
		}

		/** @param int|string $key @param mixed $data */
		public function add( $key, $data, $group = 'default', $expire = 0 ): bool {
			if ( function_exists( 'wp_suspend_cache_addition' ) && wp_suspend_cache_addition() ) {
				return false;
			}
			if ( ! $this->is_valid_key( $key ) ) {
				return false;
			}

			$group = $this->normalize_group( $group );
			if ( $this->is_non_persistent( $group ) || ! $this->persistent || ! $this->db ) {
				$found = false;
				$this->get( $key, $group, false, $found );
				return $found ? false : $this->set( $key, $data, $group, $expire );
			}

			$parts   = $this->parts( $key, $group );
			$expires = $this->expires_at( $expire );
			$value   = serialize( $data );
			$stmt    = @$this->db->prepare(
				'INSERT INTO sp_cache(scope,group_name,item_key,cache_value,expires) VALUES(:scope,:group_name,:item_key,:cache_value,:expires) ' .
				'ON CONFLICT(scope,group_name,item_key) DO UPDATE SET cache_value=excluded.cache_value, expires=excluded.expires ' .
				'WHERE sp_cache.expires > 0 AND sp_cache.expires <= :now'
			);
			if ( ! $stmt ) {
				return false;
			}
			$this->bind_parts( $stmt, $parts );
			$stmt->bindValue( ':cache_value', $value, SQLITE3_BLOB );
			$stmt->bindValue( ':expires', $expires, SQLITE3_INTEGER );
			$stmt->bindValue( ':now', time(), SQLITE3_INTEGER );
			$result = @$stmt->execute();
			$ok     = $result !== false;
			if ( $result instanceof SQLite3Result ) {
				$result->finalize();
			}
			$added = $ok && $this->db->changes() > 0;
			$stmt->close();
			if ( $added ) {
				$this->runtime_set( $parts, $group, $data, $expires );
			}
			return $added;
		}

		/** @param array<int|string,mixed> $data @return array<int|string,bool> */
		public function add_multiple( array $data, $group = '', $expire = 0 ): array {
			$results = array();
			foreach ( $data as $key => $value ) {
				$results[ $key ] = $this->add( $key, $value, $group, $expire );
			}
			return $results;
		}

		/** @param int|string $key @param mixed $data */
		public function replace( $key, $data, $group = 'default', $expire = 0 ): bool {
			if ( ! $this->is_valid_key( $key ) ) {
				return false;
			}

			$group = $this->normalize_group( $group );
			if ( $this->is_non_persistent( $group ) || ! $this->persistent || ! $this->db ) {
				$found = false;
				$this->get( $key, $group, false, $found );
				return $found ? $this->set( $key, $data, $group, $expire ) : false;
			}

			$parts   = $this->parts( $key, $group );
			$expires = $this->expires_at( $expire );
			$stmt    = @$this->db->prepare(
				'UPDATE sp_cache SET cache_value=:cache_value,expires=:expires ' .
				'WHERE scope=:scope AND group_name=:group_name AND item_key=:item_key AND (expires=0 OR expires>:now)'
			);
			if ( ! $stmt ) {
				return false;
			}
			$this->bind_parts( $stmt, $parts );
			$stmt->bindValue( ':cache_value', serialize( $data ), SQLITE3_BLOB );
			$stmt->bindValue( ':expires', $expires, SQLITE3_INTEGER );
			$stmt->bindValue( ':now', time(), SQLITE3_INTEGER );
			$result  = @$stmt->execute();
			$ok      = $result !== false;
			$changed = $ok && $this->db->changes() > 0;
			if ( $result instanceof SQLite3Result ) {
				$result->finalize();
			}
			$stmt->close();
			if ( $changed ) {
				$this->runtime_set( $parts, $group, $data, $expires );
			}
			return $changed;
		}

		/** @param int|string $key @param mixed $data */
		public function set( $key, $data, $group = 'default', $expire = 0 ): bool {
			if ( ! $this->is_valid_key( $key ) ) {
				return false;
			}

			$group   = $this->normalize_group( $group );
			$parts   = $this->parts( $key, $group );
			$expires = $this->expires_at( $expire );

			if ( $this->is_non_persistent( $group ) || ! $this->persistent || ! $this->db ) {
				$this->runtime_set( $parts, $group, $data, $expires );
				return true;
			}

			$stmt = @$this->db->prepare(
				'INSERT INTO sp_cache(scope,group_name,item_key,cache_value,expires) VALUES(:scope,:group_name,:item_key,:cache_value,:expires) ' .
				'ON CONFLICT(scope,group_name,item_key) DO UPDATE SET cache_value=excluded.cache_value, expires=excluded.expires'
			);
			if ( ! $stmt ) {
				return false;
			}
			$this->bind_parts( $stmt, $parts );
			$stmt->bindValue( ':cache_value', serialize( $data ), SQLITE3_BLOB );
			$stmt->bindValue( ':expires', $expires, SQLITE3_INTEGER );
			$result = @$stmt->execute();
			$ok     = $result !== false;
			if ( $result instanceof SQLite3Result ) {
				$result->finalize();
			}
			$stmt->close();
			if ( $ok ) {
				$this->runtime_set( $parts, $group, $data, $expires );
			}
			return $ok;
		}

		/** @param array<int|string,mixed> $data @return array<int|string,bool> */
		public function set_multiple( array $data, $group = '', $expire = 0 ): array {
			$results = array();
			foreach ( $data as $key => $value ) {
				$results[ $key ] = $this->set( $key, $value, $group, $expire );
			}
			return $results;
		}

		/** @param int|string $key @param bool|null $found @return mixed|false */
		public function get( $key, $group = 'default', $force = false, &$found = null ) {
			if ( ! $this->is_valid_key( $key ) ) {
				$found = false;
				return false;
			}

			$group = $this->normalize_group( $group );
			$parts = $this->parts( $key, $group );
			$id    = $this->runtime_id( $parts );

			if ( ! $force && array_key_exists( $id, $this->cache ) ) {
				$entry = $this->cache[ $id ];
				if ( $entry['expires'] === 0 || $entry['expires'] > time() ) {
					$found = true;
					$this->cache_hits++;
					return $this->copy_value( $entry['value'] );
				}
				unset( $this->cache[ $id ] );
			}

			if ( $this->is_non_persistent( $group ) || ! $this->persistent || ! $this->db ) {
				$found = false;
				$this->cache_misses++;
				return false;
			}

			$stmt = @$this->db->prepare( 'SELECT cache_value,expires FROM sp_cache WHERE scope=:scope AND group_name=:group_name AND item_key=:item_key LIMIT 1' );
			if ( ! $stmt ) {
				$found = false;
				$this->cache_misses++;
				return false;
			}
			$this->bind_parts( $stmt, $parts );
			$result = @$stmt->execute();
			$row    = $result instanceof SQLite3Result ? $result->fetchArray( SQLITE3_ASSOC ) : false;
			if ( $result instanceof SQLite3Result ) {
				$result->finalize();
			}
			$stmt->close();

			if ( ! is_array( $row ) || (int) $row['expires'] > 0 && (int) $row['expires'] <= time() ) {
				if ( is_array( $row ) ) {
					$this->delete_expired( $parts );
				}
				unset( $this->cache[ $id ] );
				$found = false;
				$this->cache_misses++;
				return false;
			}

			$value = @unserialize( (string) $row['cache_value'], array( 'allowed_classes' => true ) );
			if ( $value === false && (string) $row['cache_value'] !== 'b:0;' ) {
				$this->delete( $key, $group );
				$found = false;
				$this->cache_misses++;
				return false;
			}

			$this->runtime_set( $parts, $group, $value, (int) $row['expires'] );
			$found = true;
			$this->cache_hits++;
			return $this->copy_value( $value );
		}

		/** @param array<int,int|string> $keys @return array<int|string,mixed> */
		public function get_multiple( $keys, $group = 'default', $force = false ): array {
			$results = array();
			foreach ( (array) $keys as $key ) {
				$results[ $key ] = $this->get( $key, $group, $force );
			}
			return $results;
		}

		/** @param int|string $key */
		public function delete( $key, $group = 'default', $deprecated = false ): bool {
			if ( ! $this->is_valid_key( $key ) ) {
				return false;
			}

			$group   = $this->normalize_group( $group );
			$parts   = $this->parts( $key, $group );
			$id      = $this->runtime_id( $parts );
			$runtime = array_key_exists( $id, $this->cache );

			if ( $this->is_non_persistent( $group ) || ! $this->persistent || ! $this->db ) {
				unset( $this->cache[ $id ] );
				return $runtime;
			}

			$stmt = @$this->db->prepare( 'DELETE FROM sp_cache WHERE scope=:scope AND group_name=:group_name AND item_key=:item_key' );
			if ( ! $stmt ) {
				return false;
			}
			$this->bind_parts( $stmt, $parts );
			$result = @$stmt->execute();
			$ok      = $result !== false;
			$deleted = $ok && $this->db->changes() > 0;
			if ( $result instanceof SQLite3Result ) {
				$result->finalize();
			}
			$stmt->close();
			if ( ! $ok ) {
				return false;
			}
			unset( $this->cache[ $id ] );
			return $runtime || $deleted;
		}

		/** @param array<int,int|string> $keys @return array<int|string,bool> */
		public function delete_multiple( array $keys, $group = '' ): array {
			$results = array();
			foreach ( $keys as $key ) {
				$results[ $key ] = $this->delete( $key, $group );
			}
			return $results;
		}

		/** @param int|string $key @return int|false */
		public function incr( $key, $offset = 1, $group = 'default' ) {
			return $this->change_numeric( $key, abs( (int) $offset ), $group );
		}

		/** @param int|string $key @return int|false */
		public function decr( $key, $offset = 1, $group = 'default' ) {
			return $this->change_numeric( $key, -abs( (int) $offset ), $group );
		}

		/** @param int|string $key @return int|false */
		private function change_numeric( $key, int $delta, string $group ) {
			if ( ! $this->is_valid_key( $key ) ) {
				return false;
			}

			$group = $this->normalize_group( $group );
			$parts = $this->parts( $key, $group );
			$id    = $this->runtime_id( $parts );

			if ( $this->is_non_persistent( $group ) || ! $this->persistent || ! $this->db ) {
				$found = false;
				$value = $this->get( $key, $group, false, $found );
				if ( ! $found || ! is_numeric( $value ) ) {
					return false;
				}
				$expires = isset( $this->cache[ $id ] ) ? (int) $this->cache[ $id ]['expires'] : 0;
				$value   = max( 0, (int) $value + $delta );
				$this->runtime_set( $parts, $group, $value, $expires );
				return $value;
			}

			if ( ! @$this->db->exec( 'BEGIN IMMEDIATE' ) ) {
				return false;
			}

			try {
				$stmt = @$this->db->prepare( 'SELECT cache_value,expires FROM sp_cache WHERE scope=:scope AND group_name=:group_name AND item_key=:item_key LIMIT 1' );
				if ( ! $stmt ) {
					@$this->db->exec( 'ROLLBACK' );
					return false;
				}
				$this->bind_parts( $stmt, $parts );
				$result = @$stmt->execute();
				$row    = $result instanceof SQLite3Result ? $result->fetchArray( SQLITE3_ASSOC ) : false;
				if ( $result instanceof SQLite3Result ) {
					$result->finalize();
				}
				$stmt->close();

				if ( ! is_array( $row ) || (int) $row['expires'] > 0 && (int) $row['expires'] <= time() ) {
					@$this->db->exec( 'ROLLBACK' );
					return false;
				}

				$current = @unserialize( (string) $row['cache_value'], array( 'allowed_classes' => true ) );
				if ( ! is_numeric( $current ) ) {
					@$this->db->exec( 'ROLLBACK' );
					return false;
				}

				$value = max( 0, (int) $current + $delta );
				$stmt  = @$this->db->prepare( 'UPDATE sp_cache SET cache_value=:cache_value WHERE scope=:scope AND group_name=:group_name AND item_key=:item_key' );
				if ( ! $stmt ) {
					@$this->db->exec( 'ROLLBACK' );
					return false;
				}
				$this->bind_parts( $stmt, $parts );
				$stmt->bindValue( ':cache_value', serialize( $value ), SQLITE3_BLOB );
				$result  = @$stmt->execute();
				$updated = $result !== false && $this->db->changes() === 1;
				if ( $result instanceof SQLite3Result ) {
					$result->finalize();
				}
				$stmt->close();

				if ( ! $updated || ! @$this->db->exec( 'COMMIT' ) ) {
					@$this->db->exec( 'ROLLBACK' );
					return false;
				}

				$this->runtime_set( $parts, $group, $value, (int) $row['expires'] );
				return $value;
			} catch ( Throwable $error ) {
				@$this->db->exec( 'ROLLBACK' );
				return false;
			}
		}

		public function flush(): bool {
			$this->cache = array();
			if ( ! $this->persistent || ! $this->db ) {
				return true;
			}
			$stmt = @$this->db->prepare( 'DELETE FROM sp_cache WHERE scope LIKE :scope_prefix' );
			if ( ! $stmt ) {
				return false;
			}
			$stmt->bindValue( ':scope_prefix', $this->namespace . '%', SQLITE3_TEXT );
			$result = @$stmt->execute();
			$ok     = $result !== false;
			if ( $result instanceof SQLite3Result ) {
				$result->finalize();
			}
			$stmt->close();
			@$this->db->exec( 'PRAGMA wal_checkpoint(TRUNCATE)' );
			return $ok;
		}

		public function flush_runtime(): bool {
			$this->cache = array();
			return true;
		}

		public function flush_group( $group ): bool {
			$group         = $this->normalize_group( $group );
			$scope         = $this->scope_for_group( $group );
			$storage_group = $this->storage_group( $group );
			foreach ( $this->cache as $id => $entry ) {
				if ( $entry['group'] === $group && $entry['scope'] === $scope ) {
					unset( $this->cache[ $id ] );
				}
			}

			if ( $this->is_non_persistent( $group ) || ! $this->persistent || ! $this->db ) {
				return true;
			}
			$stmt = @$this->db->prepare( 'DELETE FROM sp_cache WHERE scope=:scope AND group_name=:group_name' );
			if ( ! $stmt ) {
				return false;
			}
			$stmt->bindValue( ':scope', $scope, SQLITE3_TEXT );
			$stmt->bindValue( ':group_name', $storage_group, SQLITE3_TEXT );
			$result = @$stmt->execute();
			$ok     = $result !== false;
			if ( $result instanceof SQLite3Result ) {
				$result->finalize();
			}
			$stmt->close();
			return $ok;
		}

		/** @param string|string[] $groups */
		public function add_global_groups( $groups ): void {
			$this->global_groups = array_values( array_unique( array_merge( $this->global_groups, array_map( 'strval', (array) $groups ) ) ) );
		}

		/** @param string|string[] $groups */
		public function add_non_persistent_groups( $groups ): void {
			$this->non_persistent_groups = array_values( array_unique( array_merge( $this->non_persistent_groups, array_map( 'strval', (array) $groups ) ) ) );
		}

		public function switch_to_blog( $blog_id ): void {
			$this->blog_id = max( 1, (int) $blog_id );
		}

		public function reset(): void {
			$this->cache = array();
		}

		public function close(): bool {
			if ( $this->db ) {
				$this->secure_database_files();
				$this->db->close();
				$this->db = null;
			}
			$this->persistent = false;
			return true;
		}

		public function is_persistent(): bool {
			return $this->persistent && $this->db !== null;
		}

		public function sp_accelerator_supports_namespace_flush(): bool {
			return true;
		}

		public function stats(): void {
			echo '<p>SP Object Cache: ' . (int) $this->cache_hits . ' hits, ' . (int) $this->cache_misses . ' misses.</p>';
		}

		/** @param mixed $key */
		private function is_valid_key( $key ): bool {
			if ( is_int( $key ) || is_string( $key ) && trim( $key ) !== '' ) {
				return true;
			}

			if ( function_exists( '_doing_it_wrong' ) ) {
				$type    = gettype( $key );
				$message = is_string( $key )
					? 'Cache key must not be an empty string.'
					: sprintf( 'Cache key must be an integer or a non-empty string, %s given.', $type );
				$trace   = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 2 );
				$method  = isset( $trace[1]['function'] ) ? (string) $trace[1]['function'] : 'unknown';
				_doing_it_wrong( 'WP_Object_Cache::' . $method, $message, '6.1.0' );
			}

			return false;
		}

		private function normalize_group( $group ): string {
			$group = (string) $group;
			return $group !== '' ? $group : 'default';
		}

		private function is_non_persistent( string $group ): bool {
			return in_array( $group, $this->non_persistent_groups, true );
		}

		private function build_namespace(): string {
			if ( defined( 'WP_CACHE_KEY_SALT' ) && (string) WP_CACHE_KEY_SALT !== '' ) {
				$seed = 'salt:' . (string) WP_CACHE_KEY_SALT;
			} elseif ( defined( 'AUTH_KEY' ) && (string) AUTH_KEY !== '' ) {
				$seed = 'auth:' . (string) AUTH_KEY;
			} else {
				$seed = 'root:' . ( defined( 'ABSPATH' ) ? (string) ABSPATH : 'wordpress' );
			}

			return 'sp:' . hash( 'sha256', $seed ) . ':';
		}

		private function storage_directory(): string {
			if ( defined( 'SP_ACCELERATOR_OBJECT_CACHE_DIR' ) ) {
				return trim( (string) SP_ACCELERATOR_OBJECT_CACHE_DIR );
			}
			if ( defined( 'SP_ACCELERATOR_CACHE_DIR' ) ) {
				return trim( (string) SP_ACCELERATOR_CACHE_DIR );
			}

			return rtrim( WP_CONTENT_DIR, '/\\' ) . '/cache/sp-accelerator';
		}

		private function storage_is_safe_for_server( string $directory ): bool {
			if ( ! $this->storage_directory_is_dedicated( $directory ) ) {
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

			$path = $this->normalized_path( $directory );
			foreach ( array( ABSPATH, WP_CONTENT_DIR, $document_root ) as $root ) {
				if ( $this->path_is_within( $path, $this->normalized_path( (string) $root ) ) ) {
					return false;
				}
			}
			return true;
		}

		private function storage_directory_is_dedicated( string $directory ): bool {
			if ( ! $this->is_absolute_non_root_path( $directory ) || preg_match( '~(?:^|[\\/])\.\.?(?:[\\/]|$)~', $directory ) ) {
				return false;
			}

			$directory = $this->normalized_path( $directory );
			if ( ! $this->is_absolute_non_root_path( $directory ) || preg_match( '/(?:^|[-_.])sp-accelerator(?:[-_.]|$)/i', basename( $directory ) ) !== 1 ) {
				return false;
			}

			foreach ( $this->protected_roots() as $root ) {
				$root = $this->normalized_path( $root );
				if ( $root !== '' && ( $directory === $root || $this->path_is_within( $root, $directory ) ) ) {
					return false;
				}
			}

			return true;
		}

		private function storage_cleanup_target_is_specific( string $directory ): bool {
			if ( ! $this->is_absolute_non_root_path( $directory ) || preg_match( '~(?:^|[\\/])\.\.?(?:[\\/]|$)~', $directory ) ) {
				return false;
			}

			$directory = $this->normalized_path( $directory );
			if ( ! $this->is_absolute_non_root_path( $directory ) ) {
				return false;
			}
			foreach ( $this->protected_roots() as $root ) {
				$root = $this->normalized_path( $root );
				if ( $root !== '' && ( $directory === $root || $this->path_is_within( $root, $directory ) ) ) {
					return false;
				}
			}

			return true;
		}

		/** @return string[] */
		private function protected_roots(): array {
			$roots = array( ABSPATH, WP_CONTENT_DIR, sys_get_temp_dir() );
			$document_root = defined( 'SP_ACCELERATOR_DOCUMENT_ROOT' )
				? (string) SP_ACCELERATOR_DOCUMENT_ROOT
				: (string) ( $_SERVER['DOCUMENT_ROOT'] ?? '' );
			if ( $document_root !== '' ) {
				$roots[] = $document_root;
			}

			return array_map( 'strval', $roots );
		}

		private function is_absolute_non_root_path( string $path ): bool {
			$path = rtrim( trim( $path ), '/\\' );
			$absolute = strpos( $path, '/' ) === 0 || preg_match( '/^[a-zA-Z]:[\\\\\/]/', $path ) === 1;
			return $absolute && $path !== '' && $path !== DIRECTORY_SEPARATOR && dirname( $path ) !== $path;
		}

		private function normalized_path( string $path ): string {
			$resolved = realpath( $path );
			if ( $resolved === false ) {
				$tail  = array();
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
			$path = rtrim( str_replace( '\\', '/', (string) $resolved ), '/' );
			return DIRECTORY_SEPARATOR === '\\' ? strtolower( $path ) : $path;
		}

		private function path_is_within( string $path, string $root ): bool {
			return $path !== '' && $root !== '' && strpos( $path . '/', rtrim( $root, '/' ) . '/' ) === 0;
		}

		private function storage_is_confirmed_web_exposed( string $directory ): bool {
			if ( defined( 'SP_ACCELERATOR_OBJECT_CACHE_WEB_PROTECTED' ) && SP_ACCELERATOR_OBJECT_CACHE_WEB_PROTECTED ) {
				return false;
			}
			$path = $this->normalized_path( $directory );
			$roots = array( ABSPATH, WP_CONTENT_DIR );
			$document_root = defined( 'SP_ACCELERATOR_DOCUMENT_ROOT' )
				? (string) SP_ACCELERATOR_DOCUMENT_ROOT
				: (string) ( $_SERVER['DOCUMENT_ROOT'] ?? '' );
			if ( $document_root !== '' ) {
				$roots[] = $document_root;
			}
			foreach ( $roots as $root ) {
				if ( $this->path_is_within( $path, $this->normalized_path( (string) $root ) ) ) {
					return true;
				}
			}
			return false;
		}

		private function sync_storage_protection( string $directory ): bool {
			if ( ! $this->storage_directory_is_dedicated( $directory ) || ! $this->storage_is_safe_for_server( $directory ) ) {
				return false;
			}
			if ( ! $this->storage_directory_is_owned( $directory ) ) {
				return false;
			}
			if ( ! @chmod( $directory, 0700 ) ) {
				return false;
			}

			$files  = $this->protection_files();
			$legacy = $this->legacy_protection_files();

			foreach ( $files as $name => $contents ) {
				$path = $directory . '/' . $name;
				if ( is_link( $path ) ) {
					return false;
				}
				if ( is_file( $path ) ) {
					$current = @file_get_contents( $path );
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

				if ( @file_put_contents( $path, $contents, LOCK_EX ) !== strlen( $contents ) || ! @chmod( $path, 0644 ) ) {
					return false;
				}
			}

			return true;
		}

		private function storage_directory_is_owned( string $directory ): bool {
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
			if ( count( array_diff( $entries, array( '.', '..' ) ) ) === 0 ) {
				return true;
			}

			$current = $this->protection_files();
			$legacy  = $this->legacy_protection_files();
			foreach ( $current as $name => $contents ) {
				$path = rtrim( $directory, '/\\' ) . '/' . $name;
				if ( is_link( $path ) || ! is_readable( $path ) ) {
					continue;
				}
				$installed = @file_get_contents( $path );
				if ( is_string( $installed )
					&& ( hash_equals( hash( 'sha256', $contents ), hash( 'sha256', $installed ) )
						|| isset( $legacy[ $name ] ) && hash_equals( hash( 'sha256', $legacy[ $name ] ), hash( 'sha256', $installed ) ) ) ) {
					return true;
				}
			}

			return false;
		}

		/** @return array<string,string> */
		private function protection_files(): array {
			return array(
				'.htaccess' => "# SP Accelerator cache protection\nOptions -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n",
				'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<!-- SP Accelerator cache protection -->\n<configuration><system.webServer><security><authorization><clear/><add accessType=\"Deny\" users=\"*\"/></authorization></security></system.webServer></configuration>\n",
				'index.php' => "<?php\n// SP Accelerator cache protection.\nhttp_response_code( 404 );\nexit;\n",
			);
		}

		/** @return array<string,string> */
		private function legacy_protection_files(): array {
			return array(
				'.htaccess' => "Options -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n",
				'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><security><authorization><remove users=\"*\" roles=\"\" verbs=\"\"/><add accessType=\"Deny\" users=\"*\"/></authorization></security></system.webServer></configuration>\n",
				'index.php' => "<?php\nhttp_response_code( 404 );\nexit;\n",
			);
		}

		private function secure_database_files(): bool {
			if ( $this->database_path === '' ) {
				return false;
			}

			foreach ( array( $this->database_path, $this->database_path . '-wal', $this->database_path . '-shm', $this->database_path . '-journal' ) as $path ) {
				if ( is_file( $path ) && ! @chmod( $path, 0600 ) ) {
					return false;
				}
			}

			return is_file( $this->database_path );
		}

		private function remove_unsafe_database_files( string $directory ): void {
			if ( ! $this->storage_cleanup_target_is_specific( $directory ) || ! $this->storage_is_confirmed_web_exposed( $directory ) ) {
				return;
			}

			$base = rtrim( $directory, '/\\' ) . '/object-cache.sqlite';
			foreach ( array( $base . '-wal', $base . '-shm', $base . '-journal', $base ) as $path ) {
				if ( is_file( $path ) ) {
					@unlink( $path );
				}
			}
		}

		private function scope_for_group( string $group ): string {
			return $this->namespace . ( in_array( $group, $this->global_groups, true ) ? 'global' : 'blog:' . $this->blog_id );
		}

		private function storage_group( string $group ): string {
			return strlen( $group ) > 180 ? substr( $group, 0, 120 ) . ':' . hash( 'sha256', $group ) : $group;
		}

		/** @param int|string $key @return array{scope:string,group:string,key:string} */
		private function parts( $key, string $group ): array {
			return [
				'scope' => $this->scope_for_group( $group ),
				'group' => $this->storage_group( $group ),
				'key'   => hash( 'sha256', (string) $key ),
			];
		}

		/** @param array{scope:string,group:string,key:string} $parts */
		private function runtime_id( array $parts ): string {
			return $parts['scope'] . "\0" . $parts['group'] . "\0" . $parts['key'];
		}

		/** @param array{scope:string,group:string,key:string} $parts @param mixed $data */
		private function runtime_set( array $parts, string $group, $data, int $expires ): void {
			$this->cache[ $this->runtime_id( $parts ) ] = [
				'value'   => $this->copy_value( $data ),
				'expires' => $expires,
				'group'   => $group,
				'scope'   => $parts['scope'],
			];
		}

		/** @param mixed $value @return mixed */
		private function copy_value( $value ) {
			return is_object( $value ) ? clone $value : $value;
		}

		private function expires_at( $expire ): int {
			$expire = max( 0, (int) $expire );
			return $expire > 0 ? time() + $expire : 0;
		}

		/** @param array{scope:string,group:string,key:string} $parts */
		private function bind_parts( $stmt, array $parts ): void {
			$stmt->bindValue( ':scope', $parts['scope'], SQLITE3_TEXT );
			$stmt->bindValue( ':group_name', $parts['group'], SQLITE3_TEXT );
			$stmt->bindValue( ':item_key', $parts['key'], SQLITE3_TEXT );
		}

		/** @param array{scope:string,group:string,key:string} $parts */
		private function delete_expired( array $parts ): void {
			if ( ! $this->db ) {
				return;
			}
			$stmt = @$this->db->prepare( 'DELETE FROM sp_cache WHERE scope=:scope AND group_name=:group_name AND item_key=:item_key AND expires > 0 AND expires <= :now' );
			if ( ! $stmt ) {
				return;
			}
			$this->bind_parts( $stmt, $parts );
			$stmt->bindValue( ':now', time(), SQLITE3_INTEGER );
			$result = @$stmt->execute();
			if ( $result instanceof SQLite3Result ) {
				$result->finalize();
			}
			$stmt->close();
		}

	}
}

if ( ! function_exists( 'wp_cache_init' ) ) {
function wp_cache_init() {
	$GLOBALS['wp_object_cache'] = new WP_Object_Cache();
}

function wp_cache_add( $key, $data, $group = '', $expire = 0 ) {
	return $GLOBALS['wp_object_cache']->add( $key, $data, $group, $expire );
}

function wp_cache_add_multiple( array $data, $group = '', $expire = 0 ) {
	return $GLOBALS['wp_object_cache']->add_multiple( $data, $group, $expire );
}

function wp_cache_replace( $key, $data, $group = '', $expire = 0 ) {
	return $GLOBALS['wp_object_cache']->replace( $key, $data, $group, $expire );
}

function wp_cache_set( $key, $data, $group = '', $expire = 0 ) {
	return $GLOBALS['wp_object_cache']->set( $key, $data, $group, $expire );
}

function wp_cache_set_multiple( array $data, $group = '', $expire = 0 ) {
	return $GLOBALS['wp_object_cache']->set_multiple( $data, $group, $expire );
}

function wp_cache_get( $key, $group = '', $force = false, &$found = null ) {
	return $GLOBALS['wp_object_cache']->get( $key, $group, $force, $found );
}

function wp_cache_get_multiple( $keys, $group = '', $force = false ) {
	return $GLOBALS['wp_object_cache']->get_multiple( $keys, $group, $force );
}

function wp_cache_delete( $key, $group = '' ) {
	return $GLOBALS['wp_object_cache']->delete( $key, $group );
}

function wp_cache_delete_multiple( array $keys, $group = '' ) {
	return $GLOBALS['wp_object_cache']->delete_multiple( $keys, $group );
}

function wp_cache_incr( $key, $offset = 1, $group = '' ) {
	return $GLOBALS['wp_object_cache']->incr( $key, $offset, $group );
}

function wp_cache_decr( $key, $offset = 1, $group = '' ) {
	return $GLOBALS['wp_object_cache']->decr( $key, $offset, $group );
}

function wp_cache_flush() {
	return $GLOBALS['wp_object_cache']->flush();
}

function wp_cache_flush_runtime() {
	return $GLOBALS['wp_object_cache']->flush_runtime();
}

function wp_cache_flush_group( $group ) {
	return $GLOBALS['wp_object_cache']->flush_group( $group );
}

function wp_cache_supports( $feature ) {
	return in_array( $feature, array( 'add_multiple', 'set_multiple', 'get_multiple', 'delete_multiple', 'flush_runtime', 'flush_group' ), true );
}

function wp_cache_close() {
	return isset( $GLOBALS['wp_object_cache'] ) ? $GLOBALS['wp_object_cache']->close() : true;
}

function wp_cache_add_global_groups( $groups ) {
	$GLOBALS['wp_object_cache']->add_global_groups( $groups );
}

function wp_cache_add_non_persistent_groups( $groups ) {
	$GLOBALS['wp_object_cache']->add_non_persistent_groups( $groups );
}

function wp_cache_switch_to_blog( $blog_id ) {
	$GLOBALS['wp_object_cache']->switch_to_blog( $blog_id );
}

function wp_cache_reset() {
	$GLOBALS['wp_object_cache']->reset();
}
}
