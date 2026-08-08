<?php
	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	require_once __DIR__ . '/support/taxonomy.php';

	$sp_admin_ui_modules = [
		'menu-title-item',
		'preview-thumbnail',
		'taxonomy-checkbox',
		'taxonomy-radio',
	];
	$sp_admin_ui_config = \SoinProduction\Kit\Bootstrapper::moduleConfig( 'plugins', 'sp-admin-ui' );
	if ( $sp_admin_ui_config !== null ) {
		$sp_admin_ui_modules = $sp_admin_ui_config;
	}

	$sp_admin_ui_modules = apply_filters( 'sp_admin_ui_modules', $sp_admin_ui_modules );
	$sp_admin_ui_modules = is_array( $sp_admin_ui_modules ) ? $sp_admin_ui_modules : [];

	foreach ( $sp_admin_ui_modules as $sp_admin_ui_module ) {
		if ( ! is_string( $sp_admin_ui_module ) || str_starts_with( $sp_admin_ui_module, '_' ) ) {
			continue;
		}

		$sp_admin_ui_module = sanitize_key( $sp_admin_ui_module );
		if ( $sp_admin_ui_module === '' ) {
			continue;
		}

		$sp_admin_ui_module_file = __DIR__ . '/modules/' . $sp_admin_ui_module . '/index.php';
		if ( is_readable( $sp_admin_ui_module_file ) ) {
			require_once $sp_admin_ui_module_file;
		}
	}

	unset( $sp_admin_ui_modules, $sp_admin_ui_config, $sp_admin_ui_module, $sp_admin_ui_module_file );
