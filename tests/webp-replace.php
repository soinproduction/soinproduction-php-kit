<?php
declare(strict_types=1);

define('ABSPATH', __DIR__ . '/wordpress/');

function add_filter(...$args): void
{
}

function add_action(...$args): void
{
}

require_once __DIR__ . '/../plugins/sp-webp-uploads/index.php';

$plugin = SP_Uploads_WebP_Convert::get();
$convertible = new ReflectionMethod($plugin, 'replacement_can_convert_to_webp');
$matches = new ReflectionMethod($plugin, 'replacement_mime_matches_attachment');
$checks = 0;

$assert = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$assert($convertible->invoke($plugin, 'image/webp', 'image/png'), 'PNG must be convertible for a WebP target');
$assert($convertible->invoke($plugin, 'image/webp', 'image/jpeg'), 'JPEG must be convertible for a WebP target');
$assert(! $convertible->invoke($plugin, 'image/webp', 'image/gif'), 'GIF must not be flattened into WebP implicitly');
$assert(! $convertible->invoke($plugin, 'image/png', 'image/jpeg'), 'non-WebP targets must not accept mismatched MIME');
$assert($matches->invoke($plugin, 'image/jpeg', 'image/jpeg'), 'identical MIME must match');
$assert($matches->invoke($plugin, 'image/jpg', 'image/jpeg'), 'legacy image/jpg must match JPEG');
$assert(! $matches->invoke($plugin, 'image/webp', 'image/png'), 'conversion must remain separate from direct MIME matching');

echo "WebP replace PHP: {$checks} checks passed.\n";
