<?php
	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	$sp_cf7_modules = [
		'sp-cf7-core',
		'sp-cf7-mail-viewer',
		'sp-cf7-mailchimp-sync',
		'sp-cf7-webhook',
		'sp-cf7-redirects',
		'sp-cf7-select-field',
		'sp-cf7-icon-generator',
	];
	$sp_cf7_aliases = [
		'base'           => 'sp-cf7-core',
		'mail-viewer'    => 'sp-cf7-mail-viewer',
		'flowchimp'      => 'sp-cf7-mailchimp-sync',
		'webhook'        => 'sp-cf7-webhook',
		'redirects'      => 'sp-cf7-redirects',
		'ui-select'      => 'sp-cf7-select-field',
		'icon-generator' => 'sp-cf7-icon-generator',
	];
	$sp_cf7_config = \SoinProduction\Kit\Bootstrapper::moduleConfig( 'plugins', 'sp-cf7' );
	if ( $sp_cf7_config !== null ) {
		$sp_cf7_modules = $sp_cf7_config;
	}

	$sp_cf7_modules = apply_filters( 'sp_cf7_modules', $sp_cf7_modules );
	$sp_cf7_modules = is_array( $sp_cf7_modules ) ? $sp_cf7_modules : [];

	foreach ( $sp_cf7_modules as $sp_cf7_module ) {
		if ( ! is_string( $sp_cf7_module ) || str_starts_with( $sp_cf7_module, '_' ) ) {
			continue;
		}

		$sp_cf7_module = sanitize_key( $sp_cf7_module );
		$sp_cf7_module = $sp_cf7_aliases[ $sp_cf7_module ] ?? $sp_cf7_module;
		if ( $sp_cf7_module === '' ) {
			continue;
		}

		$sp_cf7_module_file = __DIR__ . '/modules/' . $sp_cf7_module . '/index.php';
		if ( is_readable( $sp_cf7_module_file ) ) {
			require_once $sp_cf7_module_file;
		}
	}

	unset( $sp_cf7_modules, $sp_cf7_aliases, $sp_cf7_config, $sp_cf7_module, $sp_cf7_module_file );
