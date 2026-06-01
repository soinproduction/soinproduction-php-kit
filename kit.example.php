<?php
declare(strict_types=1);

/**
 * Configuration for SoinProduction PHP Kit
 * Comment out or remove items you don't want to load in this theme.
 */
$platform = [
//	'author-meta',
	'dev-user',
	'reading-time',
	'remove-post-slug',
	'reset'
];

$plugins = [
	'sp-allow-svg-upload',
	'sp-cf7-mail-viewer',
	'sp-content-manager',
	'sp-cpt-archives',
	'sp-dev-mode',
	'sp-favorite-posts',
	'sp-flowchimp',
	'sp-google-reviews',
	'sp-redirects',
	'sp-uploads-webp-convert',
	'sp-video-preview'
];

if (class_exists(\SoinProduction\Kit\Bootstrapper::class)) {
	\SoinProduction\Kit\Bootstrapper::run(['platform' => $platform]);
}

require_once THEME_DIR . '/acf/index.php';
require_once THEME_DIR . '/core/bootstrap.php';

if (class_exists(\SoinProduction\Kit\Bootstrapper::class)) {
	\SoinProduction\Kit\Bootstrapper::run(['plugins' => $plugins]);
}
