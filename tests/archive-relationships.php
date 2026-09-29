<?php
declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');
function add_action(...$args): void {}
function add_filter(...$args): void {}
function sanitize_key(string $value): string { return preg_replace('/[^a-z0-9_\-]/', '', strtolower($value)) ?? ''; }
function sanitize_title(string $value): string { return sanitize_key($value); }
function sanitize_text_field(string $value): string { return strip_tags($value); }
function absint($value): int { return abs((int) $value); }
function wp_parse_args($args, array $defaults = []): array { return array_merge($defaults, $args); }
function post_type_exists(string $type): bool { return in_array($type, ['post', 'testimonials', 'markets', 'services'], true); }
function taxonomy_exists(string $taxonomy): bool { return $taxonomy === 'category'; }
function get_posts(array $args): array {
    if ($args['post_status'] !== 'publish' || $args['posts_per_page'] !== -1 || $args['suppress_filters'] !== false) {
        throw new RuntimeException('Unexpected source query');
    }
    $ids = $args['post_type'] === 'markets' ? [10] : [20, 21, 22];
    return array_map(static fn(int $id) => (object) ['ID' => $id, 'post_title' => 'Source ' . $id], $ids);
}
function get_post_meta(int $id, string $key, bool $single) {
    if ($key !== 'linked_testimonials') { return []; }
    return [10 => [1, 2, 3], 20 => ['2', '3', '4'], 21 => [1], 22 => []][$id] ?? [];
}

require dirname(__DIR__) . '/acf/sp-archive-builder/index.php';
$filters = sp_archive_normalize_relationship_filters([
    'industry' => ['post_type' => 'markets', 'reverse_field' => 'linked_testimonials', 'query_arg' => 'industry-id'],
    'service' => ['post_type' => 'services', 'reverse_field' => 'linked_testimonials', 'ui' => 'multiselect'],
]);
$args = static fn(array $values, array $extra = []) => sp_archive_query_args(array_merge([
    'post_type' => 'testimonials', 'filters' => $filters, 'filter_values' => $values,
], $extra));
$both = $args(['industry' => '10', 'service' => '20']);
$manual = $args(['service' => '20'], ['post__in' => [4, 1, 3, 2], 'sort' => 'manual']);
$checks = [
    'no selection leaves archive unrestricted' => ! isset($args([])['post__in']),
    'one filter restricts IDs' => $args(['industry' => '10'])['post__in'] === [1, 2, 3],
    'separate filters intersect' => $both['post__in'] === [2, 3],
    'multiple values union' => $args(['service' => '20|21'])['post__in'] === [2, 3, 4, 1],
    'empty relationship restricts to zero' => $args(['service' => '22'])['post__in'] === [0],
    'unknown source restricts to zero' => $args(['service' => '99'])['post__in'] === [0],
    'wrong source type restricts to zero' => $args(['service' => '10'])['post__in'] === [0],
    'malformed source is not coerced' => $args(['service' => '20!'])['post__in'] === [0],
    'nested source is not unrestricted' => $args(['service' => [['20']]])['post__in'] === [0],
    'manual IDs keep their original order' => $manual['post__in'] === [4, 3, 2] && $manual['orderby'] === 'post__in',
    'empty manual remains empty' => $args(['service' => '20'], ['post__in' => []])['post__in'] === [0],
    'relationship does not create a taxonomy query' => ! isset($both['tax_query']),
    'taxonomy scope remains applied' => isset($args(['service' => '20'], ['term_scope' => ['category' => ['news']]])['tax_query']),
    'paging remains in SQL' => $args(['service' => '20'], ['per_page' => 2, 'paged' => 3])['paged'] === 3,
    'custom URL argument is parsed' => sp_archive_filter_values($filters, ['industry-id' => '10'])['industry'] === '10',
    'AJAX control name is parsed' => sp_archive_filter_values($filters, ['industry' => '10'])['industry'] === '10',
    'invalid request remains restrictive' => sp_archive_filter_values($filters, ['service' => '!!!'])['service'] === '__invalid__',
    'all removes restriction' => ! isset($args(['service' => 'all'])['post__in']),
    'invalid definition is rejected' => sp_archive_normalize_relationship_filters(['x' => ['post_type' => 'missing', 'reverse_field' => 'x']]) === [],
    'disabled definition is omitted' => sp_archive_normalize_relationship_filters(['x' => ['enabled' => false, 'post_type' => 'services', 'reverse_field' => 'x']]) === [],
    'normalization retains trusted definition' => sp_archive_normalize_filters($filters) === $filters,
];
$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => ! $passed));
if ($failed) {
    fwrite(STDERR, 'Archive relationships failed: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}
echo 'Archive relationships: ' . count($checks) . " checks passed.\n";
