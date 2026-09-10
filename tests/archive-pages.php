<?php
declare(strict_types=1);

/**
 * Focused multilingual and individual archive-page regression checks.
 * Run directly with: php tests/archive-pages.php
 */

if (PHP_SAPI !== 'cli') {
	exit(1);
}

define('ABSPATH', __DIR__ . '/');
define('ARCHIVE_POSTS', ['book']);

final class WP_Post
{
	public function __construct(
		public int $ID,
		public string $post_type,
		public string $post_status = 'publish',
		public string $post_name = '',
	) {
	}
}

final class WP
{
	public array $query_vars = [];
}

$GLOBALS['fa_test_filters'] = [];
$GLOBALS['fa_test_options'] = [
	'custom_fake_archives_fr'            => ['book' => 10],
	'custom_fake_archives_default'       => ['book' => 11],
	'custom_fake_archives_individual_fr' => ['book' => 1],
];
$GLOBALS['fa_test_posts'] = [
	10 => new WP_Post(10, 'page', 'publish', 'livres'),
	11 => new WP_Post(11, 'page', 'publish', 'books'),
	20 => new WP_Post(20, 'page', 'publish', 'selection'),
	21 => new WP_Post(21, 'page', 'publish', 'english-selection'),
	30 => new WP_Post(30, 'book', 'publish', 'example'),
];
$GLOBALS['fa_test_meta'] = [30 => ['_fa_archive_page_id' => 20]];
$GLOBALS['fa_test_languages'] = [10 => 'fr', 11 => 'en', 20 => 'fr', 21 => 'en', 30 => 'fr'];
$GLOBALS['fa_test_permalinks'] = [
	10 => 'https://example.test/fr/livres/',
	11 => 'https://example.test/books/',
	20 => 'https://example.test/fr/livres/selection/',
	21 => 'https://example.test/books/english-selection/',
];

function add_action(...$args): void
{
}

function add_filter(string $hook, $callback, int $priority = 10, int $acceptedArgs = 1): void
{
	$GLOBALS['fa_test_filters'][$hook][] = compact('callback', 'priority', 'acceptedArgs');
}

function apply_filters(string $hook, $value, ...$args)
{
	if ($hook === 'wpml_current_language') {
		$value = 'fr';
	} elseif ($hook === 'wpml_default_language') {
		$value = 'en';
	} elseif ($hook === 'wpml_post_language_details') {
		$value = ['language_code' => $GLOBALS['fa_test_languages'][(int) ($args[0] ?? 0)] ?? ''];
	}

	$callbacks = $GLOBALS['fa_test_filters'][$hook] ?? [];
	usort($callbacks, static fn(array $a, array $b): int => $a['priority'] <=> $b['priority']);
	foreach ($callbacks as $registered) {
		$callArgs = array_slice(array_merge([$value], $args), 0, $registered['acceptedArgs']);
		$value = ($registered['callback'])(...$callArgs);
	}

	return $value;
}

function sanitize_key(string $value): string
{
	return preg_replace('/[^a-z0-9_\-]/', '', strtolower($value)) ?? '';
}

function get_option(string $name, $default = false)
{
	return $GLOBALS['fa_test_options'][$name] ?? $default;
}

function get_post($post): ?WP_Post
{
	if ($post instanceof WP_Post) {
		return $post;
	}

	return $GLOBALS['fa_test_posts'][(int) $post] ?? null;
}

function get_post_meta(int $postId, string $key, bool $single = false)
{
	return $GLOBALS['fa_test_meta'][$postId][$key] ?? ($single ? '' : []);
}

function get_page_uri(int $postId): string
{
	$path = parse_url($GLOBALS['fa_test_permalinks'][$postId] ?? '', PHP_URL_PATH);
	return trim((string) $path, '/');
}

function get_permalink(int $postId): string
{
	return $GLOBALS['fa_test_permalinks'][$postId] ?? '';
}

function home_url(string $path = ''): string
{
	return 'https://example.test' . $path;
}

function wp_parse_url(string $url, int $component = -1)
{
	return parse_url($url, $component);
}

function untrailingslashit(string $value): string
{
	return rtrim($value, '/\\');
}

require dirname(__DIR__) . '/plugins/sp-archive-pages/index.php';

$checks = [
	'WPML current language is used'                     => fa_current_lang() === 'fr',
	'WPML default language is used'                     => fa_default_lang() === 'en',
	'supported post types remain theme-configurable'    => get_supported_fake_archive_post_types() === ['book'],
	'language archive assignment resolves correctly'    => fa_get_fake_archive_page_for_language('book', 'fr')?->ID === 10,
	'individual assignment overrides the type archive'  => fa_get_archive_page_for_post(30)?->ID === 20,
	'individual route keeps its language and hierarchy' => fa_get_archive_base_for_post(30) === 'fr/livres/selection',
];

$GLOBALS['fa_test_meta'][30]['_fa_archive_page_id'] = 21;
$checks['cross-language individual page is rejected'] = fa_get_archive_page_for_post(30)?->ID === 10;

$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => ! $passed));
if ($failed !== []) {
	fwrite(STDERR, 'Archive page failures: ' . implode(', ', $failed) . PHP_EOL);
	exit(1);
}

echo 'Archive pages: ' . count($checks) . " checks passed.\n";
