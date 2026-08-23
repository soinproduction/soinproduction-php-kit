<?php

/**
 * Focused checks for the package-provided section_widgets Builder layout.
 * Run directly with: php tests/content-library-builder-runtime.php
 */

if (PHP_SAPI !== 'cli') {
	exit(1);
}

define('ABSPATH', __DIR__ . '/');

eval(<<<'PHP'
namespace StoutLogic\AcfBuilder;
final class FieldsBuilder
{
	public string $name;
	public array $fields = [];

	public function __construct(string $name)
	{
		$this->name = $name;
	}

	public function addRadio(string $name, array $args = []): self
	{
		$this->fields[] = compact('name', 'args');
		return $this;
	}
}
PHP);

$GLOBALS['sp_content_library_filters'] = [];

function add_filter(string $hook, $callback, int $priority = 10, int $acceptedArgs = 1): void
{
	$GLOBALS['sp_content_library_filters'][$hook][$priority][] = compact('callback', 'acceptedArgs');
}

function add_action(string $hook, $callback, int $priority = 10, int $acceptedArgs = 1): void
{
	unset($hook, $callback, $priority, $acceptedArgs);
}

function __(string $value, string $domain = ''): string
{
	unset($domain);
	return $value;
}

function sanitize_key(string $value): string
{
	return strtolower((string) preg_replace('/[^a-z0-9_-]/i', '', $value));
}

require dirname(__DIR__) . '/plugins/sp-content-library/section-widgets/fields.php';

$registration = $GLOBALS['sp_content_library_filters']['sp_builder_layouts_config'][20][0]['callback'] ?? null;
$config = is_callable($registration)
	? $registration(['layouts' => [], 'layouts_only' => []])
	: [];
$layout = $config['layouts'][0] ?? [];
$fields = $layout['fields'] ?? null;

$checks = [
	'historical section_widgets callback is registered' => function_exists('section_widgets'),
	'Builder extension filter is registered'             => is_callable($registration),
	'package injects the historical layout name'         => ($layout['name'] ?? '') === 'section_widgets',
	'package keeps block display mode'                    => ($layout['display'] ?? '') === 'block',
	'layout exposes the reusable section radio field'     => $fields instanceof \StoutLogic\AcfBuilder\FieldsBuilder
		&& ($fields->fields[0]['name'] ?? '') === 'widget_static_block',
	'TinyMCE runtime keeps its public compatibility API'  => str_contains(
		(string) file_get_contents(dirname(__DIR__) . '/plugins/sp-content-library/editor-widgets/index.php'),
		'final class SP_Widgets_Plugin'
	),
	'TinyMCE script still registers sp_widgets'           => str_contains(
		(string) file_get_contents(dirname(__DIR__) . '/plugins/sp-content-library/editor-widgets/script.js'),
		"tinymce.PluginManager.add('sp_widgets'"
	),
];

$failed = array_keys(array_filter($checks, static fn (bool $passed): bool => ! $passed));
if ($failed !== []) {
	fwrite(STDERR, 'Content library Builder failures: ' . implode(', ', $failed) . PHP_EOL);
	exit(1);
}

echo 'Content library Builder: ' . count($checks) . " checks passed.\n";
