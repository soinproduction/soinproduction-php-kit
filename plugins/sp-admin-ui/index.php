<?php
	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	require_once __DIR__ . '/includes/taxonomy.php';

	add_action( 'admin_enqueue_scripts', static function ( string $hook ): void {
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}

		$asset_path = __DIR__ . '/assets/builder-widget-selection.js';
		$asset_url  = \SoinProduction\Kit\Bootstrapper::pathToUrl( __DIR__ );
		if ( ! is_readable( $asset_path ) || $asset_url === '' ) {
			return;
		}

		wp_enqueue_script(
			'sp-admin-ui-builder-widget-selection',
			trailingslashit( $asset_url ) . 'assets/builder-widget-selection.js',
			[ 'jquery' ],
			(string) filemtime( $asset_path ),
			true
		);
	} );

	$sp_admin_ui_modules = [
		'sp-admin-ui-menu-heading',
		'sp-admin-ui-text-column',
		'sp-admin-ui-thumbnail-column',
		'sp-admin-ui-taxonomy-checklist',
		'sp-admin-ui-taxonomy-radio',
	];
	$sp_admin_ui_aliases = [
		'menu-title-item'   => 'sp-admin-ui-menu-heading',
		'text-column'       => 'sp-admin-ui-text-column',
		'preview-thumbnail' => 'sp-admin-ui-thumbnail-column',
		'taxonomy-checkbox' => 'sp-admin-ui-taxonomy-checklist',
		'taxonomy-radio'    => 'sp-admin-ui-taxonomy-radio',
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
		$sp_admin_ui_module = $sp_admin_ui_aliases[ $sp_admin_ui_module ] ?? $sp_admin_ui_module;
		if ( $sp_admin_ui_module === '' ) {
			continue;
		}

		$sp_admin_ui_module_file = __DIR__ . '/modules/' . $sp_admin_ui_module . '/index.php';
		if ( is_readable( $sp_admin_ui_module_file ) ) {
			require_once $sp_admin_ui_module_file;
		}
	}

	unset( $sp_admin_ui_modules, $sp_admin_ui_aliases, $sp_admin_ui_config, $sp_admin_ui_module, $sp_admin_ui_module_file );
