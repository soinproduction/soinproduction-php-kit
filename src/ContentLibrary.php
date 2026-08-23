<?php

declare(strict_types=1);

namespace SoinProduction\Kit;

/**
 * Shared registrations for reusable Builder sections and TinyMCE editor blocks.
 *
 * The historical post type, taxonomy and field names are intentionally stable so
 * existing content and multilingual relationships continue to work unchanged.
 */
final class ContentLibrary
{
	public const SECTIONS_POST_TYPE = 'widgets';
	public const EDITOR_POST_TYPE = 'for-editor';
	public const SECTIONS_TAXONOMY = 'widgets_category';

	/** @var array<string, mixed> */
	private static array $config = [];
	private static bool $initialized = false;

	/** @return array<string, mixed> */
	public static function normalizeConfig(array $config = []): array
	{
		$defaults = [
			'menu_parent'           => 'themes.php',
			'editor_layouts'        => [],
			'builder_field_callback' => 'sp_builder_add_flexible_field',
			'editor_field_factory'  => 'blocks',
		];
		$config = array_replace($defaults, $config);

		$menuParent = trim((string) $config['menu_parent']);
		if ($menuParent === '' || preg_match('/^[a-z0-9_.?=&-]+$/i', $menuParent) !== 1) {
			$menuParent = $defaults['menu_parent'];
		}

		$builderCallback = trim((string) $config['builder_field_callback']);
		$editorFactory = trim((string) $config['editor_field_factory']);

		$config['menu_parent'] = $menuParent;
		$config['editor_layouts'] = is_array($config['editor_layouts']) ? array_values($config['editor_layouts']) : [];
		$config['builder_field_callback'] = $builderCallback !== '' ? $builderCallback : $defaults['builder_field_callback'];
		$config['editor_field_factory'] = $editorFactory !== '' ? $editorFactory : $defaults['editor_field_factory'];

		return $config;
	}

	public static function init(array $config = []): void
	{
		if (self::$initialized) {
			return;
		}

		self::$initialized = true;
		self::$config = self::normalizeConfig($config);

		add_action('init', [self::class, 'registerContentTypes']);
		add_action('init', [self::class, 'ensureDefaultTerms'], 20);
		add_action('acf/init', [self::class, 'registerFieldGroups'], 20);
		add_action('after_setup_theme', [self::class, 'registerPreviewColumns'], 20);
	}

	public static function registerContentTypes(): void
	{
		$menuParent = (string) self::$config['menu_parent'];

		$sectionLabels = [
			'name'               => __('Reusable Sections', 'sp-content-library'),
			'singular_name'      => __('Reusable Section', 'sp-content-library'),
			'menu_name'          => __('Reusable Sections', 'sp-content-library'),
			'name_admin_bar'     => __('Reusable Section', 'sp-content-library'),
			'add_new'            => __('Add New', 'sp-content-library'),
			'add_new_item'       => __('Add New Reusable Section', 'sp-content-library'),
			'new_item'           => __('New Reusable Section', 'sp-content-library'),
			'edit_item'          => __('Edit Reusable Section', 'sp-content-library'),
			'view_item'          => __('View Reusable Section', 'sp-content-library'),
			'all_items'          => __('Reusable Sections', 'sp-content-library'),
			'search_items'       => __('Search Reusable Sections', 'sp-content-library'),
			'not_found'          => __('No reusable sections found.', 'sp-content-library'),
			'not_found_in_trash' => __('No reusable sections found in Trash.', 'sp-content-library'),
		];

		register_post_type(self::SECTIONS_POST_TYPE, apply_filters('sp_content_library_sections_post_type_args', [
			'labels'              => $sectionLabels,
			'public'              => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'show_ui'             => true,
			'show_in_menu'        => $menuParent,
			'show_in_rest'        => true,
			'show_in_admin_bar'   => false,
			'has_archive'         => false,
			'rewrite'             => false,
			'supports'            => ['title', 'thumbnail'],
			'menu_icon'           => 'dashicons-screenoptions',
		]));

		$editorLabels = [
			'name'               => __('Editor Blocks', 'sp-content-library'),
			'singular_name'      => __('Editor Block', 'sp-content-library'),
			'menu_name'          => __('Editor Blocks', 'sp-content-library'),
			'name_admin_bar'     => __('Editor Block', 'sp-content-library'),
			'add_new'            => __('Add New', 'sp-content-library'),
			'add_new_item'       => __('Add New Editor Block', 'sp-content-library'),
			'new_item'           => __('New Editor Block', 'sp-content-library'),
			'edit_item'          => __('Edit Editor Block', 'sp-content-library'),
			'view_item'          => __('View Editor Block', 'sp-content-library'),
			'all_items'          => __('Editor Blocks', 'sp-content-library'),
			'search_items'       => __('Search Editor Blocks', 'sp-content-library'),
			'not_found'          => __('No editor blocks found.', 'sp-content-library'),
			'not_found_in_trash' => __('No editor blocks found in Trash.', 'sp-content-library'),
		];

		register_post_type(self::EDITOR_POST_TYPE, apply_filters('sp_content_library_editor_post_type_args', [
			'labels'              => $editorLabels,
			'public'              => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'show_ui'             => true,
			'show_in_menu'        => $menuParent,
			'show_in_rest'        => true,
			'show_in_admin_bar'   => false,
			'has_archive'         => false,
			'rewrite'             => false,
			'supports'            => ['title'],
			'menu_icon'           => 'dashicons-welcome-widgets-menus',
		]));

		$taxonomyArgs = [
			'hierarchical'       => false,
			'labels'             => [
				'name'          => __('Section Categories', 'sp-content-library'),
				'singular_name' => __('Section Category', 'sp-content-library'),
				'search_items'  => __('Search Categories', 'sp-content-library'),
				'all_items'     => __('All Categories', 'sp-content-library'),
				'edit_item'     => __('Edit Category', 'sp-content-library'),
				'update_item'   => __('Update Category', 'sp-content-library'),
				'add_new_item'  => __('Add New Category', 'sp-content-library'),
				'new_item_name' => __('New Category Name', 'sp-content-library'),
				'menu_name'     => __('Section Categories', 'sp-content-library'),
			],
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_admin_column'  => true,
			'show_in_menu'       => $menuParent,
			'query_var'          => false,
			'rewrite'            => false,
			'show_in_rest'       => true,
		];
		if (function_exists('sp_taxonomy_checklist_all_terms')) {
			$taxonomyArgs['meta_box_cb'] = 'sp_taxonomy_checklist_all_terms';
		}

		register_taxonomy(
			self::SECTIONS_TAXONOMY,
			[self::SECTIONS_POST_TYPE],
			apply_filters('sp_content_library_sections_taxonomy_args', $taxonomyArgs)
		);
	}

	public static function ensureDefaultTerms(): void
	{
		if (! taxonomy_exists(self::SECTIONS_TAXONOMY)) {
			return;
		}

		foreach ([
			'section'       => __('Section', 'sp-content-library'),
			'page-template' => __('Page Template', 'sp-content-library'),
		] as $slug => $name) {
			if (! term_exists($slug, self::SECTIONS_TAXONOMY)) {
				wp_insert_term($name, self::SECTIONS_TAXONOMY, ['slug' => $slug]);
			}
		}
	}

	public static function registerPreviewColumns(): void
	{
		if (! function_exists('register_acf_thumb_column')) {
			return;
		}

		register_acf_thumb_column(
			type: 'post',
			object: self::SECTIONS_POST_TYPE,
			column_label: __('Preview', 'sp-content-library'),
			after: 'cb',
			acf_field: '',
			size: '250x200'
		);
		register_acf_thumb_column(
			type: 'post',
			object: self::EDITOR_POST_TYPE,
			column_label: __('Preview', 'sp-content-library'),
			after: 'cb',
			acf_field: '',
			size: '380x100'
		);
	}

	public static function registerFieldGroups(): void
	{
		if (
			! function_exists('acf_add_local_field_group')
			|| ! class_exists(\StoutLogic\AcfBuilder\FieldsBuilder::class)
		) {
			return;
		}

		self::registerSectionsFieldGroup();
		self::registerEditorFieldGroup();
	}

	/** @return array<int, callable> */
	public static function resolveEditorLayouts(array $entries): array
	{
		$layouts = [];

		foreach ($entries as $entry) {
			$callback = $entry;
			$args = [];

			if (is_array($entry)) {
				$callback = $entry['callback'] ?? '';
				$args = isset($entry['args']) && is_array($entry['args']) ? $entry['args'] : [];
			}

			if (! is_callable($callback)) {
				continue;
			}

			try {
				$layout = $args === [] ? call_user_func($callback) : call_user_func($callback, $args);
			} catch (\Throwable $error) {
				continue;
			}

			if (is_callable($layout)) {
				$layouts[] = $layout;
			}
		}

		return $layouts;
	}

	public static function editorBlocks(int $postId): array
	{
		if ($postId <= 0 || ! function_exists('get_field')) {
			return [];
		}

		$blocks = get_field('blocks', $postId);
		return is_array($blocks) ? $blocks : [];
	}

	public static function editorBlockLabels(int $postId): array
	{
		$labels = [];
		foreach (self::editorBlocks($postId) as $block) {
			$layout = is_array($block) ? (string) ($block['acf_fc_layout'] ?? '') : '';
			if ($layout === '') {
				continue;
			}

			$layout = (string) preg_replace('/^block_/', '', $layout);
			$labels[] = ucwords(str_replace(['_', '-'], ' ', $layout));
		}

		return array_values(array_unique($labels));
	}

	private static function registerSectionsFieldGroup(): void
	{
		$callback = (string) self::$config['builder_field_callback'];
		if (! is_callable($callback)) {
			return;
		}

		$builder = new \StoutLogic\AcfBuilder\FieldsBuilder('widgets_builder', [
			'menu_order'     => 1,
			'style'          => 'seamless',
			'hide_on_screen' => ['the_content', 'excerpt', 'revisions', 'editor'],
		]);

		call_user_func($callback, $builder, 'builder', [
			'label'        => false,
			'button_label' => __('Add section', 'sp-content-library'),
		]);
		$builder->setLocation('post_type', '==', self::SECTIONS_POST_TYPE);
		acf_add_local_field_group($builder->build());
	}

	private static function registerEditorFieldGroup(): void
	{
		$factory = (string) self::$config['editor_field_factory'];
		$layouts = self::resolveEditorLayouts((array) self::$config['editor_layouts']);
		if (! is_callable($factory) || $layouts === []) {
			return;
		}

		$field = call_user_func_array($factory, array_merge([[
			'label'   => false,
			'max'     => 1,
			'wrapper' => ['class' => 'flexible-1'],
		]], $layouts));
		if (! $field instanceof \StoutLogic\AcfBuilder\FieldsBuilder) {
			return;
		}

		$widgets = new \StoutLogic\AcfBuilder\FieldsBuilder('for_editor_widgets', [
			'title'          => __('Editor Block Settings', 'sp-content-library'),
			'menu_order'     => 1,
			'style'          => 'seamless',
			'hide_on_screen' => ['the_content', 'editor', 'revisions'],
		]);
		$widgets
			->addFields($field)
			->setLocation('post_type', '==', self::EDITOR_POST_TYPE);
		acf_add_local_field_group($widgets->build());
	}
}
