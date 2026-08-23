<?php

if (! defined('ABSPATH')) {
	exit;
}

$sp_editor_tools_modules = [
	'sp-aos-for-editor'      => 'SP_Aosanimate_Plugin',
	'sp-cf7-button'          => 'SP_CF7_Button_Plugin',
	'sp-custom-link-class'   => 'SP_Custom_Link_Class_Plugin',
	'sp-custom-lists'        => 'SP_Custom_Lists_Plugin',
	'sp-custom-text-class'   => 'SP_Tag_Style_Selector_Plugin',
	'sp-custom-underline'    => 'SP_Underline_Toggle_Elem_Plugin',
	'sp-custom-uppercase'    => 'SP_Textcase_Elem_Plugin',
	'sp-dark-mode'           => 'SP_Dark_Mode_Plugin',
	'sp-decor-span-tag'      => 'SP_Decor_Toggle_Plugin',
	'sp-editor-row'          => 'SP_Editor_Row_Plugin',
	'sp-font-family-select'  => 'SP_Font_Family_Select_Plugin',
	'sp-list-columns'        => 'SP_List_Columns_Plugin',
	'sp-readmore-modal'      => 'SP_Read_More_Modal_Img_Plugin',
	'sp-shortcode-button'    => 'SP_Shortcode_Button_Plugin',
	'sp-small-button-tag'    => 'SP_Small_Toggle_Plugin',
	'sp-social-list'         => 'SP_Social_List_Plugin',
	'sp-table-builder'       => 'SP_Table_Builder_Plugin',
	'sp-toc-item'            => 'SP_Toc_Item_Plugin',
	'sp-ul-align-redirect'   => 'SP_Ul_Align_Redirect_Plugin',
];

$sp_editor_tools_config = \SoinProduction\Kit\Bootstrapper::moduleConfig('plugins', 'sp-editor-tools');

if (is_array($sp_editor_tools_config) && isset($sp_editor_tools_config['modules'])) {
	$enabled_modules = is_array($sp_editor_tools_config['modules'])
		? array_values(array_filter(array_map('sanitize_key', $sp_editor_tools_config['modules'])))
		: [];
	$sp_editor_tools_modules = array_intersect_key(
		$sp_editor_tools_modules,
		array_fill_keys($enabled_modules, true)
	);
}

foreach ($sp_editor_tools_modules as $module => $class_name) {
	$file = __DIR__ . '/modules/' . $module . '/index.php';
	require_once $file;
}

unset($sp_editor_tools_config, $sp_editor_tools_modules, $enabled_modules, $module, $class_name, $file);
