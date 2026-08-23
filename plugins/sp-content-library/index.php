<?php

if (! defined('ABSPATH')) {
	exit;
}

$sp_content_library_config = \SoinProduction\Kit\Bootstrapper::moduleConfig('plugins', 'sp-content-library');
\SoinProduction\Kit\ContentLibrary::init(is_array($sp_content_library_config) ? $sp_content_library_config : []);

if (! function_exists('for_editor_get_blocks')) {
	function for_editor_get_blocks(int $post_id): array
	{
		return \SoinProduction\Kit\ContentLibrary::editorBlocks($post_id);
	}
}

if (! function_exists('for_editor_get_block_labels')) {
	function for_editor_get_block_labels(int $post_id): array
	{
		return \SoinProduction\Kit\ContentLibrary::editorBlockLabels($post_id);
	}
}

unset($sp_content_library_config);
