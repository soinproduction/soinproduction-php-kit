<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

define('ABSPATH', __DIR__ . '/');
define('THEME_DIR', dirname(__DIR__));
define('THEME_SLUG', 'php-kit-test');

function add_action(...$args): void {}
function add_filter(...$args): void {}
function sanitize_key(string $value): string { return preg_replace('/[^a-z0-9_\-]/', '', strtolower($value)) ?? ''; }
function sanitize_title(string $value): string { return sanitize_key($value); }
function absint($value): int { return abs((int) $value); }
function wp_unslash($value) { return $value; }
function post_type_exists(string $postType): bool { return in_array($postType, ['post', 'case_study'], true); }
function wp_parse_args($args, array $defaults = []): array { return array_merge($defaults, is_array($args) ? $args : []); }
function apply_filters(string $hook, $value, ...$args) { return $hook === 'wpml_current_language' ? 'uk' : $value; }

require dirname(__DIR__) . '/acf/sp-archive-builder/index.php';

$query = sp_archive_query_args([
    'post_type'     => 'case_study',
    'per_page'      => 'all',
    'favorite_first' => true,
    'lang'          => 'uk',
]);

$manualQuery = sp_archive_query_args([
    'post_type' => 'case_study',
    'per_page'  => 3,
    'post__in'  => [12, '34', 12, 0],
    'sort'      => 'manual',
]);

$emptyManualQuery = sp_archive_query_args([
    'post_type' => 'case_study',
    'post__in'  => [],
]);

$checks = [
    'all normalizes to unlimited'                => sp_archive_normalize_per_page('all') === -1,
    'minus one stays unlimited'                  => sp_archive_normalize_per_page(-1) === -1,
    'invalid per-page value uses fallback'       => sp_archive_normalize_per_page(0, 12) === 12,
    'WPML language is detected'                  => sp_archive_current_language() === 'uk',
    'query preserves unlimited posts'            => $query['posts_per_page'] === -1,
    'query forwards multilingual language'       => $query['lang'] === 'uk',
    'favorite ordering uses named meta clause'   => isset($query['orderby']['sp_favorite_first']),
    'favorite metadata query remains optional'   => isset($query['meta_query']['sp_favorite_first']),
    'manual selection limits the WordPress query' => $manualQuery['post__in'] === [12, 34],
    'manual selection keeps configured page size' => $manualQuery['posts_per_page'] === 3,
    'manual selection preserves editor order'      => $manualQuery['orderby'] === 'post__in',
    'empty manual selection returns no posts'      => $emptyManualQuery['post__in'] === [0],
];

$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => ! $passed));
if ($failed !== []) {
    fwrite(STDERR, 'Archive Builder failures: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}

echo 'Archive Builder: ' . count($checks) . " checks passed.\n";
