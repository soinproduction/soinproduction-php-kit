<?php
declare(strict_types=1);

/**
 * Configuration for SoinProduction PHP Kit
 * Prefix a module name with "_" to keep it listed but disabled.
 */
$platform = [
	'_author-meta',
	'branding',
	'dev-user',
	'duplicator-key',
	'page-loader-settings',
	'post-type-converter',
	'reading-time',
	'remove-post-slug',
	'reset'
];

$acf = [
	'_icon-link-list',
	'_related-posts',
	'_smart-relationship',
	'_smart-taxonomy',
	'_table',
	'_taxonomy-urls',
	'_universal-media',
	'_archive-builder',
];

$plugins = [
	'sp-accelerator',
	'sp-allow-svg-upload',
	'sp-admin-ui',
	'sp-cf7',
	'sp-content-manager',
	'sp-cpt-archives',
	'sp-dev-mode',
	'sp-favorite-posts',
	'sp-google-reviews',
	'sp-redirects',
	'_sp-share',
	'sp-tag-manager',
	'sp-uploads-webp-convert',
	'sp-video-preview',
	'sp-wiki',
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
