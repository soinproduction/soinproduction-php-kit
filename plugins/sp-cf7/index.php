<?php
	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	$sp_cf7_modules = [
		'base',
		'mail-viewer',
		'flowchimp',
		'webhook',
		'redirects',
		'ui-select',
		'icon-generator',
	];

	$sp_cf7_modules = apply_filters( 'sp_cf7_modules', $sp_cf7_modules );
	$sp_cf7_modules = is_array( $sp_cf7_modules ) ? $sp_cf7_modules : [];

	foreach ( $sp_cf7_modules as $sp_cf7_module ) {
		$sp_cf7_module = is_string( $sp_cf7_module ) ? sanitize_key( $sp_cf7_module ) : '';
		if ( $sp_cf7_module === '' ) {
			continue;
		}

		$sp_cf7_module_file = __DIR__ . '/modules/' . $sp_cf7_module . '/index.php';
		if ( is_readable( $sp_cf7_module_file ) ) {
			require_once $sp_cf7_module_file;
		}
	}

	unset( $sp_cf7_modules, $sp_cf7_module, $sp_cf7_module_file );
