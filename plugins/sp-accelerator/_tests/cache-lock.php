<?php

/**
 * Focused regression checks for runtime cache-lock invalidation.
 * Run directly with: php _tests/cache-lock.php
 */

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

$fixture = sys_get_temp_dir() . '/sp-accelerator-test-runtime-lock-' . bin2hex( random_bytes( 5 ) );
if ( ! @mkdir( $fixture, 0700, true ) ) {
	exit( 1 );
}

define( 'ABSPATH', $fixture . '/' );

function wp_mkdir_p( $path ) {
	return is_dir( $path ) || ( ! file_exists( $path ) && @mkdir( $path, 0755, true ) );
}

function wp_generate_password( $length = 12, $special_chars = true, $extra_special_chars = false ) {
	return substr( str_repeat( 'runtime-lock-token', $length ), 0, $length );
}

function trailingslashit( $path ) {
	return rtrim( (string) $path, '/\\' ) . '/';
}

final class SP_Accelerator_Config {
	/** @var string */
	private $root;

	public function __construct( string $root ) {
		$this->root = $root;
	}

	public function enabled( string $key = 'enabled' ): bool {
		return true;
	}

	public function cache_root(): string {
		return $this->root;
	}

	public function cache_root_is_owned_for_mutation(): bool {
		return true;
	}

	public function get( string $key, $default = null ) {
		return $key === 'generation' ? 'g1' : $default;
	}
}

final class SP_Accelerator_Request {
	public function canonical_url( string $url ): string {
		return $url;
	}

	public function cache_hash( string $url ): string {
		return hash( 'sha256', $url );
	}
}

require dirname( __DIR__ ) . '/includes/class-cache.php';

$config  = new SP_Accelerator_Config( $fixture . '/sp-accelerator' );
$request = new SP_Accelerator_Request();
$cache   = new SP_Accelerator_Cache( $config, $request );
$url     = 'https://example.test/page/';
$paths   = $cache->entry_paths( $url );
@mkdir( dirname( $paths['lock'] ), 0755, true );
$token = time() . ':render-owner';
file_put_contents( $paths['lock'], $token );
$inode_before = fileinode( $paths['lock'] );

$property = new ReflectionProperty( SP_Accelerator_Cache::class, 'owned_revalidation_lock' );
$property->setAccessible( true );
$property->setValue( $cache, [ 'path' => $paths['lock'], 'token' => $token ] );
$GLOBALS['sp_accelerator_revalidation_lock'] = [ 'path' => $paths['lock'], 'token' => $token ];

$checks = [];
$checks['purge invalidates an in-flight owner'] = $cache->purge_url( $url ) === true;
$checks['purge preserves the shared lock inode'] = is_file( $paths['lock'] ) && fileinode( $paths['lock'] ) === $inode_before;
$checks['purge leaves an immediately claimable empty lock'] = file_get_contents( $paths['lock'] ) === '';
$checks['invalidated renderer cannot repopulate the entry'] = $cache->store( $url, '<!doctype html><html><body>stale</body></html>' ) === false && ! is_file( $paths['html'] );

$acquire = new ReflectionMethod( SP_Accelerator_Cache::class, 'acquire_revalidation_lock' );
$acquire->setAccessible( true );
$checks['next renderer claims the same inode'] = $acquire->invoke( $cache, $paths['lock'] ) === 1 && fileinode( $paths['lock'] ) === $inode_before;
$cache->release_owned_revalidation_lock();
$checks['release keeps the reusable inode empty'] = is_file( $paths['lock'] ) && fileinode( $paths['lock'] ) === $inode_before && file_get_contents( $paths['lock'] ) === '';

$items = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $fixture, FilesystemIterator::SKIP_DOTS ),
	RecursiveIteratorIterator::CHILD_FIRST
);
foreach ( $items as $item ) {
	$item->isDir() && ! $item->isLink() ? @rmdir( $item->getPathname() ) : @unlink( $item->getPathname() );
}
@rmdir( $fixture );

$failed = array_keys( array_filter( $checks, static function ( bool $passed ): bool {
	return ! $passed;
} ) );
if ( $failed ) {
	fwrite( STDERR, 'SP Accelerator cache-lock failures: ' . implode( ', ', $failed ) . PHP_EOL );
	exit( 1 );
}

echo 'SP Accelerator cache lock: ' . count( $checks ) . " checks passed.\n";
