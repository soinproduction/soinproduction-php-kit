<?php

/**
 * Focused regression checks for the package-owned Classic Editor tools.
 * Run directly with: php tests/editor-tools.php
 */

if (PHP_SAPI !== 'cli') {
	exit(1);
}

define('ABSPATH', __DIR__ . '/');

eval(<<<'PHP'
namespace SoinProduction\Kit;
final class Bootstrapper
{
	public static function moduleConfig(string $category, string $module): ?array
	{
		unset($category, $module);
		return null;
	}

	public static function pathToUrl(string $path): string
	{
		return 'https://example.test/vendor/' . basename($path);
	}
}
PHP);

$GLOBALS['sp_editor_tools_actions'] = [];
$GLOBALS['sp_editor_tools_filters'] = [];
$GLOBALS['sp_editor_tools_shortcodes'] = [];

function add_action(string $hook, $callback, int $priority = 10, int $acceptedArgs = 1): void
{
	$GLOBALS['sp_editor_tools_actions'][] = compact('hook', 'callback', 'priority', 'acceptedArgs');
}

function add_filter(string $hook, $callback, int $priority = 10, int $acceptedArgs = 1): void
{
	$GLOBALS['sp_editor_tools_filters'][] = compact('hook', 'callback', 'priority', 'acceptedArgs');
}

function add_shortcode(string $tag, $callback): void
{
	$GLOBALS['sp_editor_tools_shortcodes'][$tag] = $callback;
}

function sanitize_key(string $value): string
{
	return strtolower((string) preg_replace('/[^a-z0-9_-]/i', '', $value));
}

function add_query_arg(...$args): string
{
	return (string) end($args);
}

require dirname(__DIR__) . '/plugins/sp-editor-tools/index.php';

$classes = [
	'SP_Aosanimate_Plugin',
	'SP_CF7_Button_Plugin',
	'SP_Custom_Link_Class_Plugin',
	'SP_Custom_Lists_Plugin',
	'SP_Tag_Style_Selector_Plugin',
	'SP_Underline_Toggle_Elem_Plugin',
	'SP_Textcase_Elem_Plugin',
	'SP_Dark_Mode_Plugin',
	'SP_Decor_Toggle_Plugin',
	'SP_Editor_Row_Plugin',
	'SP_Font_Family_Select_Plugin',
	'SP_List_Columns_Plugin',
	'SP_Read_More_Modal_Img_Plugin',
	'SP_Shortcode_Button_Plugin',
	'SP_Small_Toggle_Plugin',
	'SP_Social_List_Plugin',
	'SP_Table_Builder_Plugin',
	'SP_Toc_Item_Plugin',
	'SP_Ul_Align_Redirect_Plugin',
];

$missing = array_values(array_filter($classes, static fn (string $class): bool => ! class_exists($class, false)));
$moduleRoot = dirname(__DIR__) . '/plugins/sp-editor-tools/modules';
$scripts = glob($moduleRoot . '/*/script.js') ?: [];
$phpModules = glob($moduleRoot . '/*/index.php') ?: [];
$customLinkSource = (string) file_get_contents($moduleRoot . '/sp-custom-link-class/index.php');
$listColumnsSource = (string) file_get_contents($moduleRoot . '/sp-list-columns/index.php');

$checks = [
	'all editor tool classes load from php-kit'         => $missing === [],
	'all nineteen PHP modules are bundled'              => count($phpModules) === 19,
	'all nineteen JavaScript modules are bundled'       => count($scripts) === 19,
	'TOC shortcode registration remains active'         => isset($GLOBALS['sp_editor_tools_shortcodes']['toc']),
	'editor scripts register their init endpoints'      => count(array_filter(
		$GLOBALS['sp_editor_tools_actions'],
		static fn (array $hook): bool => $hook['hook'] === 'init'
	)) >= 18,
	'custom link script resolves from package path'      => str_contains($customLinkSource, 'Bootstrapper::pathToUrl($script_path)'),
	'list columns CSS resolves from package path'        => str_contains($listColumnsSource, "Bootstrapper::pathToUrl(__DIR__ . '/style.css')"),
];

$failed = array_keys(array_filter($checks, static fn (bool $passed): bool => ! $passed));
if ($failed !== []) {
	fwrite(STDERR, 'Editor tools failures: ' . implode(', ', $failed) . PHP_EOL);
	exit(1);
}

echo 'Editor tools: ' . count($checks) . " checks passed.\n";
