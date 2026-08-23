<?php
declare(strict_types=1);

/**
 * Configuration for SoinProduction PHP Kit
 * Prefix a module name with "_" to keep it listed but disabled.
 */
$platform = [
	'_sp-author-meta',
	'sp-login-branding',
	'sp-content-admin',
	'sp-duplicator-license',
	'sp-motion-settings',
	'sp-content-type-converter',
	'sp-reading-time',
	'sp-permalink-manager',
	'sp-wordpress-baseline'
];

$acf = [
	'_sp-background-media',
	'_sp-icon-links',
	'_sp-related-content',
	'_sp-post-selector',
	'_sp-term-selector',
	'_sp-table',
	'_sp-term-links',
	'_sp-media',
	'_sp-archive-builder',
];

$plugins = [
	'sp-accelerator',
	'sp-svg-support',
	'sp-admin-ui' => [
		'sp-admin-ui-menu-heading',
		'sp-admin-ui-text-column',
		'sp-admin-ui-thumbnail-column',
		'sp-admin-ui-taxonomy-checklist',
		'sp-admin-ui-taxonomy-radio',
	],
	'sp-cf7' => [
		'sp-cf7-core',
		'sp-cf7-mail-viewer',
		'sp-cf7-mailchimp-sync',
		'sp-cf7-webhook',
		'sp-cf7-redirects',
		'sp-cf7-select-field',
		'sp-cf7-icon-generator',
	],
	'sp-content-manager',
	'sp-content-library' => [
		'editor_layouts' => [
			'author_quote',
			'blockquote',
		],
	],
	'sp-editor-tools',
	'sp-deployment-manager',
	'sp-archive-pages',
	'sp-debug-toolbar',
	'sp-content-favorites',
	'sp-google-reviews',
	'sp-redirect-manager',
	'_sp-share',
	'sp-tag-manager',
	'sp-webp-uploads',
	'sp-video-posters',
	'sp-documentation',
];

if (class_exists(\SoinProduction\Kit\Bootstrapper::class)) {
	\SoinProduction\Kit\Bootstrapper::run([
		'platform' => $platform,
		'acf'      => $acf,
	]);
}

// Theme-specific ACF field groups and theme runtime.
require_once THEME_DIR . '/acf/index.php';
require_once THEME_DIR . '/core/bootstrap.php';

if (class_exists(\SoinProduction\Kit\Bootstrapper::class)) {
	\SoinProduction\Kit\Bootstrapper::run(['plugins' => $plugins]);
}
