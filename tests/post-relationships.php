<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

define('ABSPATH', __DIR__ . '/');

$GLOBALS['pr_actions'] = [];
$GLOBALS['pr_filters'] = [];
$GLOBALS['pr_groups'] = [];

function add_action(string $hook, $callback, int $priority = 10, int $acceptedArgs = 1): void
{
    $GLOBALS['pr_actions'][$hook][] = compact('callback', 'priority', 'acceptedArgs');
}

function add_filter(string $hook, $callback, int $priority = 10, int $acceptedArgs = 1): void
{
    $GLOBALS['pr_filters'][$hook][] = compact('callback', 'priority', 'acceptedArgs');
}

function sanitize_key(string $value): string
{
    return preg_replace('/[^a-z0-9_\-]/', '', strtolower($value)) ?? '';
}

function __(string $text, string $domain = ''): string { return $text; }
function get_post_type_object(string $postType): object { return (object) ['labels' => (object) ['name' => ucfirst($postType)]]; }
function acf_add_local_field_group(array $group): void { $GLOBALS['pr_groups'][] = $group; }

require dirname(__DIR__) . '/acf/sp-post-relationships/index.php';

post_relationships([
    [
        'post_types'     => ['services', 'projects', 'markets'],
        'field_prefix'   => 'linked_',
        'width'          => 33,
        'featured_image' => false,
        'group_title'    => 'Relations',
    ],
]);

foreach ($GLOBALS['pr_actions']['acf/init'] ?? [] as $registered) {
    ($registered['callback'])();
}

$groups = $GLOBALS['pr_groups'];
$firstFields = $groups[0]['fields'] ?? [];
$checks = [
    'one field group is created per post type'       => count($groups) === 3,
    'each post type links to every other post type'  => count($firstFields) === 2,
    'configured width is retained'                   => ($firstFields[0]['wrapper']['width'] ?? '') === '33',
    'featured images can be disabled'                => ($firstFields[0]['elements'] ?? []) === ['post_type'],
    'bidirectional update filters are registered'    => count(array_filter(array_keys($GLOBALS['pr_filters']), static fn(string $hook): bool => str_starts_with($hook, 'acf/update_value/key='))) === 6,
];

$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => ! $passed));
if ($failed !== []) {
    fwrite(STDERR, 'Post Relationships failures: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}

echo 'Post Relationships: ' . count($checks) . " checks passed.\n";
