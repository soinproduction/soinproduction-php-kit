<?php

/**
 * Focused regression checks for shared reusable content registrations.
 * Run directly with: php tests/content-library.php
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
	public array $location = [];

	public function __construct(string $name, array $settings = [])
	{
		$this->name = $name;
		$this->fields['settings'] = $settings;
	}

	public function addFields($field): self
	{
		$this->fields['nested'] = $field;
		return $this;
	}

	public function setLocation(string $parameter, string $operator, string $value): self
	{
		$this->location = compact('parameter', 'operator', 'value');
		return $this;
	}

	public function build(): array
	{
		return ['name' => $this->name, 'fields' => $this->fields, 'location' => $this->location];
	}
}
PHP);

$GLOBALS['sp_content_library_hooks'] = [];
$GLOBALS['sp_content_library_post_types'] = [];
$GLOBALS['sp_content_library_taxonomies'] = [];
$GLOBALS['sp_content_library_fields'] = [
	12 => [
		['acf_fc_layout' => 'block_author_quote'],
		['acf_fc_layout' => 'block_blockquote'],
		['acf_fc_layout' => 'block_author_quote'],
	],
];
$GLOBALS['sp_content_library_groups'] = [];
$GLOBALS['sp_content_library_builder_calls'] = [];
$GLOBALS['sp_content_library_blocks_calls'] = [];
$GLOBALS['sp_content_library_rendered_templates'] = [];
$GLOBALS['sp_content_library_have_rows_calls'] = 0;

function add_action(string $hook, $callback, int $priority = 10, int $acceptedArgs = 1): void
{
	$GLOBALS['sp_content_library_hooks'][] = compact('hook', 'callback', 'priority', 'acceptedArgs');
}

function __(string $value, string $domain = ''): string
{
	return $value;
}

function apply_filters(string $hook, $value)
{
	return $value;
}

function register_post_type(string $postType, array $args): void
{
	$GLOBALS['sp_content_library_post_types'][$postType] = $args;
}

function register_taxonomy(string $taxonomy, array $postTypes, array $args): void
{
	$GLOBALS['sp_content_library_taxonomies'][$taxonomy] = compact('postTypes', 'args');
}

function taxonomy_exists(string $taxonomy): bool
{
	return $taxonomy === 'widgets_category';
}

function term_exists(string $slug, string $taxonomy): bool
{
	return true;
}

function get_field(string $field, int $postId): array
{
	return $GLOBALS['sp_content_library_fields'][$postId] ?? [];
}

function get_sub_field(string $field): int
{
	return $field === 'widget_static_block' ? 44 : 0;
}

function absint($value): int
{
	return abs((int) $value);
}

function have_rows(string $field, int $postId): bool
{
	if ($field !== 'builder' || $postId !== 44) {
		return false;
	}

	return $GLOBALS['sp_content_library_have_rows_calls']++ === 0;
}

function the_row(): void
{
}

function get_row_layout(): string
{
	return 'section_hero';
}

function get_template_part(string $slug): void
{
	$GLOBALS['sp_content_library_rendered_templates'][] = $slug;
}

function acf_add_local_field_group(array $group): void
{
	$GLOBALS['sp_content_library_groups'][] = $group;
}

function sp_builder_add_flexible_field($builder, string $fieldName, array $args): array
{
	$GLOBALS['sp_content_library_builder_calls'][] = compact('builder', 'fieldName', 'args');
	return [];
}

function blocks(array $args = [], callable ...$layouts): \StoutLogic\AcfBuilder\FieldsBuilder
{
	$GLOBALS['sp_content_library_blocks_calls'][] = compact('args', 'layouts');
	return new \StoutLogic\AcfBuilder\FieldsBuilder('blocks');
}

function test_author_quote(array $args = []): callable
{
	return static function ($flex) use ($args): void {
		unset($flex, $args);
	};
}

require dirname(__DIR__) . '/src/ContentLibrary.php';

use SoinProduction\Kit\ContentLibrary;

$normalized = ContentLibrary::normalizeConfig([
	'menu_parent'    => '../unsafe',
	'editor_layouts' => 'invalid',
]);
$resolved = ContentLibrary::resolveEditorLayouts([
	'test_author_quote',
	['callback' => 'test_author_quote', 'args' => ['compact' => true]],
	'missing_layout',
]);

ContentLibrary::init([
	'editor_layouts' => ['test_author_quote'],
]);
ContentLibrary::registerContentTypes();
ContentLibrary::registerFieldGroups();
ContentLibrary::renderReusableSection();

$sections = $GLOBALS['sp_content_library_post_types']['widgets'] ?? [];
$editor = $GLOBALS['sp_content_library_post_types']['for-editor'] ?? [];
$taxonomy = $GLOBALS['sp_content_library_taxonomies']['widgets_category'] ?? [];
$hooks = array_column($GLOBALS['sp_content_library_hooks'], 'priority', 'hook');

$checks = [
	'unsafe menu parent falls back to Appearance'        => $normalized['menu_parent'] === 'themes.php',
	'invalid editor layout configuration becomes empty' => $normalized['editor_layouts'] === [],
	'configured layout factories resolve safely'        => count($resolved) === 2 && is_callable($resolved[0]),
	'historical Reusable Sections slug is preserved'    => isset($GLOBALS['sp_content_library_post_types']['widgets']),
	'historical Editor Blocks slug is preserved'        => isset($GLOBALS['sp_content_library_post_types']['for-editor']),
	'historical taxonomy slug is preserved'             => isset($GLOBALS['sp_content_library_taxonomies']['widgets_category']),
	'Reusable Sections page lives under Appearance'     => ($sections['show_in_menu'] ?? '') === 'themes.php',
	'Editor Blocks page lives under Appearance'         => ($editor['show_in_menu'] ?? '') === 'themes.php',
	'visible page labels use the new terminology'        => ($sections['labels']['menu_name'] ?? '') === 'Reusable Sections'
		&& ($editor['labels']['menu_name'] ?? '') === 'Editor Blocks',
	'editor REST identifier and title support remain'   => ! empty($editor['show_in_rest']) && ($editor['supports'] ?? []) === ['title'],
	'sections keep title and thumbnail support'          => ($sections['supports'] ?? []) === ['title', 'thumbnail'],
	'taxonomy remains attached to Reusable Sections'     => ($taxonomy['postTypes'] ?? []) === ['widgets'],
	'ACF registration runs after theme field callbacks' => ($hooks['acf/init'] ?? 0) === 20,
	'package renderer hooks the historical template slug' => isset($hooks['get_template_part_template_parts/section-widgets/index']),
	'package renders the selected section builder'         => $GLOBALS['sp_content_library_rendered_templates'] === ['template_parts/section-hero/index'],
	'legacy ACF group names remain stable'               => array_column($GLOBALS['sp_content_library_groups'], 'name') === ['widgets_builder', 'for_editor_widgets'],
	'sections still use the theme Builder callback'      => ($GLOBALS['sp_content_library_builder_calls'][0]['fieldName'] ?? '') === 'builder',
	'configured Editor layouts reach the blocks factory' => count($GLOBALS['sp_content_library_blocks_calls'][0]['layouts'] ?? []) === 1,
	'editor block rows are returned unchanged'           => count(ContentLibrary::editorBlocks(12)) === 3,
	'editor block labels are unique and readable'        => ContentLibrary::editorBlockLabels(12) === ['Author Quote', 'Blockquote'],
	'Editor Widgets runtime is bundled with the module'  => is_file(dirname(__DIR__) . '/plugins/sp-content-library/editor-widgets/index.php')
		&& is_file(dirname(__DIR__) . '/plugins/sp-content-library/editor-widgets/script.js'),
	'Builder selector runtime is bundled with the module' => is_file(dirname(__DIR__) . '/plugins/sp-content-library/section-widgets/fields.php'),
];

$failed = array_keys(array_filter($checks, static fn (bool $passed): bool => ! $passed));
if ($failed !== []) {
	fwrite(STDERR, 'Content library failures: ' . implode(', ', $failed) . PHP_EOL);
	exit(1);
}

echo 'Content library: ' . count($checks) . " checks passed.\n";
