<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
	exit(1);
}

$options   = getopt('', [ 'theme-root:', 'check' ]);
$themeRoot = isset($options['theme-root']) ? (string) $options['theme-root'] : getcwd();
$realRoot  = is_string($themeRoot) ? realpath($themeRoot) : false;

if ($realRoot === false || !is_dir($realRoot)) {
	fwrite(STDERR, "Invalid --theme-root.\n");
	exit(1);
}

if (!defined('ABSPATH')) {
	define('ABSPATH', rtrim($realRoot, '/\\') . '/');
}

require_once dirname(__DIR__, 3) . '/src/DocumentationInventory.php';

$configFile = $realRoot . '/config/php-kit.php';
$config     = is_readable($configFile) ? require $configFile : [];
$config     = is_array($config) ? $config : [];
$inventory  = \SoinProduction\Kit\DocumentationInventory::collect($realRoot, $config);
$targets    = [
	$realRoot . '/docs/en/00-current-configuration.md' => \SoinProduction\Kit\DocumentationInventory::renderMarkdown($inventory, 'en'),
	$realRoot . '/docs/ru/00-tekushchaya-konfiguraciya.md' => \SoinProduction\Kit\DocumentationInventory::renderMarkdown($inventory, 'ru'),
];
$check = array_key_exists('check', $options);
$stale = [];

foreach ($targets as $file => $content) {
	$current = is_readable($file) ? file_get_contents($file) : false;
	if ($check) {
		if (!is_string($current) || $current !== $content) {
			$stale[] = str_replace(rtrim($realRoot, '/\\') . '/', '', $file);
		}
		continue;
	}

	$directory = dirname($file);
	if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
		throw new RuntimeException('Unable to create documentation directory: ' . $directory);
	}
	if (file_put_contents($file, $content) === false) {
		throw new RuntimeException('Unable to write generated documentation: ' . $file);
	}
	echo 'Generated ' . str_replace(rtrim($realRoot, '/\\') . '/', '', $file) . "\n";
}

if ($stale !== []) {
	fwrite(STDERR, "Generated documentation is stale:\n- " . implode("\n- ", $stale) . "\nRun npm run build.\n");
	exit(1);
}

if ($check) {
	echo "Generated documentation is current.\n";
}
