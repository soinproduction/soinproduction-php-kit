<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

define('ABSPATH', __DIR__ . '/');

$GLOBALS['srel_actions'] = [];
$GLOBALS['srel_post_types'] = [
    21 => 'testimonials',
    22 => 'testimonials',
    23 => 'testimonials',
    31 => 'projects',
];
$GLOBALS['srel_post_terms'] = [
    21 => ['testimonial_category' => [5]],
    22 => ['testimonial_category' => [8]],
    23 => ['testimonial_category' => [5, 8]],
    31 => ['testimonial_category' => [5]],
];
$GLOBALS['srel_fields'] = [
    10 => [
        'linked_testimonials' => [21, 22, 21, 31],
        'featured_quotes'     => [new WP_Post(23), 22],
    ],
];

class WP_Post
{
    public int $ID;

    public function __construct(int $id)
    {
        $this->ID = $id;
    }
}

class WP_Query
{
    public array $posts = [];

    public function __construct(array $args)
    {
        $ids = array_map('intval', $args['post__in'] ?? []);
        $taxQuery = $args['tax_query'] ?? [];
        $clauses = array_values(array_filter(
            $taxQuery,
            static fn($key): bool => is_int($key),
            ARRAY_FILTER_USE_KEY
        ));

        $this->posts = array_values(array_filter($ids, static function (int $id) use ($clauses): bool {
            foreach ($clauses as $clause) {
                $assigned = $GLOBALS['srel_post_terms'][$id][$clause['taxonomy']] ?? [];
                if (array_intersect($assigned, (array) $clause['terms']) === []) {
                    return false;
                }
            }
            return true;
        }));
    }
}

class acf_field
{
    public string $name = '';
    public string $label = '';
    public string $category = '';
    public array $defaults = [];

    public function __construct()
    {
        if (method_exists($this, 'initialize')) {
            $this->initialize();
        }
    }
}

function add_action(string $hook, $callback, int $priority = 10, int $acceptedArgs = 1): void
{
    $GLOBALS['srel_actions'][$hook][] = compact('callback', 'priority', 'acceptedArgs');
}

function sanitize_key($value): string
{
    return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value)) ?? '';
}

function absint($value): int
{
    return abs((int) $value);
}

function __(string $text, string $domain = ''): string
{
    return $text;
}

function get_post_type(int $postId): string|false
{
    return $GLOBALS['srel_post_types'][$postId] ?? false;
}

function get_field(string $name, int $postId, bool $formatValue = true)
{
    return $GLOBALS['srel_fields'][$postId][$name] ?? null;
}

function get_post_meta(int $postId, string $key, bool $single = false)
{
    return $GLOBALS['srel_fields'][$postId][$key] ?? null;
}

function get_posts(array $args): array
{
    return array_map(static fn(int $id): WP_Post => new WP_Post($id), $args['post__in'] ?? []);
}

function wp_reset_postdata(): void
{
}

function acf_register_field_type(string $className): void
{
}

require dirname(__DIR__) . '/acf/sp-post-selector/index.php';

foreach ($GLOBALS['srel_actions']['acf/include_field_types'] ?? [] as $registered) {
    ($registered['callback'])();
}

$fieldType = new acf_field_smart_relationship();

$baseField = [
    'post_type'     => ['testimonials'],
    'return_format' => 'id',
    'modes'         => ['manual', 'related', 'all'],
    'default_mode'  => 'manual',
    'taxonomy'      => ['testimonial_category'],
];
$restrictedField = array_merge($baseField, [
    'taxonomy_terms' => ['testimonial_category:5'],
]);

$autoRelated = $fieldType->format_value(['mode' => 'related', 'ids' => []], 10, $baseField);
$filteredRelated = $fieldType->format_value(['mode' => 'related', 'ids' => []], 10, $restrictedField);
$explicitRelated = $fieldType->format_value(
    ['mode' => 'related', 'ids' => []],
    10,
    array_merge($restrictedField, ['related_fields' => ['featured_quotes']])
);
$saved = $fieldType->update_value(['mode' => 'related', 'ids' => [21, 22]], 10, $restrictedField);
$objects = $fieldType->format_value(
    ['mode' => 'related', 'ids' => []],
    10,
    array_merge($restrictedField, ['return_format' => 'object'])
);
$termMap = acf_field_smart_relationship::normalize_taxonomy_terms(
    ['testimonial_category:5', 'testimonial_category:8', 'other:9', 'broken'],
    ['testimonial_category']
);
$taxQuery = acf_field_smart_relationship::build_tax_query($termMap);
$multiTaxQuery = acf_field_smart_relationship::build_tax_query([
    'testimonial_category' => [5],
    'language'             => [3],
]);
$fieldSource = file_get_contents(dirname(__DIR__) . '/acf/sp-post-selector/index.php');

$checks = [
    'automatic linked_{post_type} lookup retains source order' => $autoRelated === [21, 22],
    'allowed terms filter automatic related posts' => $filteredRelated === [21],
    'explicit relationship fields support objects, deduplicate IDs, and filter terms' => $explicitRelated === [23],
    'related mode survives value validation while disallowed manual IDs are removed' => ($saved['mode'] ?? '') === 'related' && ($saved['ids'] ?? []) === [21],
    'object return format keeps filtered related order' => array_map(static fn(WP_Post $post): int => $post->ID, $objects) === [21],
    'term normalization rejects taxonomies outside the field configuration' => $termMap === ['testimonial_category' => [5, 8]],
    'tax query groups terms from one taxonomy into one IN clause' => ($taxQuery[0]['terms'] ?? []) === [5, 8] && ($taxQuery[0]['operator'] ?? '') === 'IN',
    'tax query requires matches across configured taxonomies' => ($multiTaxQuery['relation'] ?? '') === 'AND' && count($multiTaxQuery) === 3,
    'clearing the taxonomy setting disables stale allowed terms' => acf_field_smart_relationship::normalize_taxonomy_terms(['testimonial_category:5'], []) === [],
    'term restrictions stay in field settings instead of the content picker' => is_string($fieldSource)
        && !str_contains($fieldSource, 'sp-srel__tax-filter')
        && str_contains($fieldSource, "'name'         => 'taxonomy_terms'"),
];

$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
if ($failed !== []) {
    fwrite(STDERR, 'Smart Relationship failures: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}

echo 'Smart Relationship: ' . count($checks) . " checks passed.\n";
