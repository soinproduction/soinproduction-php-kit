<?php

/**
 * Focused regression checks for generated theme documentation.
 * Run directly with: php tests/documentation.php
 */

if (PHP_SAPI !== 'cli') {
	exit(1);
}

define('ABSPATH', __DIR__ . '/');

require dirname(__DIR__) . '/src/Bootstrapper.php';
require dirname(__DIR__) . '/src/DocumentationInventory.php';

$fixture = sys_get_temp_dir() . '/sp-documentation-' . bin2hex(random_bytes(6));
$make = static function (string $path, string $content = '') use ($fixture): void {
	$file = $fixture . '/' . ltrim($path, '/');
	if (!is_dir(dirname($file))) {
		mkdir(dirname($file), 0775, true);
	}
	file_put_contents($file, $content);
};
$remove = static function (string $path) use (&$remove): void {
	if (is_dir($path)) {
		foreach (scandir($path) ?: [] as $item) {
			if ($item !== '.' && $item !== '..') {
				$remove($path . '/' . $item);
			}
		}
		rmdir($path);
	} elseif (file_exists($path)) {
		unlink($path);
	}
};

$make('style.css', "/*\nTheme Name: Fixture\nVersion: 2.1.0\nRequires at least: 6.4\nRequires PHP: 8.0\nText Domain: fixture\n*/\n");
$make('composer.json', json_encode([ 'require' => [ 'php' => '>=8.1' ] ], JSON_PRETTY_PRINT));
$make('package.json', json_encode([ 'engines' => [ 'node' => '>=20.19 <23' ] ], JSON_PRETTY_PRINT));
$make('template_parts/section-hero/fields.php');
$make('template_parts/section-hero/index.php');
$make('template_parts/section-hero/index.js');
$make('template_parts/section-incomplete/index.php');
$make('acf/blocks-layout/block-editor/fields.php');
$make('acf/blocks-layout/block-editor/index.php');
$make('acf/options/options.php');
$make('js/components/modal.js');
$make('core/cpt/journal/index.php', "<?php\n\$post_type = 'blog';\nregister_post_type(\$post_type, []);\n");
$make('core/cpt/journal/fields.php');
$make('single-blog.php');
$make('vendor/soinproduction/php-kit/plugins/sp-documentation/index.php');

$config = [
	'platform' => [ 'sp-author-meta', '_sp-disabled' ],
	'acf'      => [],
	'plugins'  => [ 'sp-documentation', 'sp-content-library' => [ 'editor_layouts' => [ 'author_quote', 'blockquote' ] ] ],
];

try {
	$inventory = \SoinProduction\Kit\DocumentationInventory::collect($fixture, $config);
	$english   = \SoinProduction\Kit\DocumentationInventory::renderMarkdown($inventory, 'en');
	$russian   = \SoinProduction\Kit\DocumentationInventory::renderMarkdown($inventory, 'ru');
	$wiki      = file_get_contents(dirname(__DIR__) . '/plugins/sp-documentation/index.php');
	$generator = dirname(__DIR__) . '/plugins/sp-documentation/bin/generate-theme-docs.php';
	$probe     = $fixture . '/include-generator.php';
	file_put_contents(
		$probe,
		'<?php chdir(' . var_export($fixture, true) . '); require ' . var_export($generator, true) . '; echo "continued";'
	);
	$probeOutput = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($probe));
	$bootstrapperSource = file_get_contents(dirname(__DIR__) . '/src/Bootstrapper.php');
	$sections  = array_column($inventory['sections'], 'name');
	$blocks    = array_column($inventory['blocks'], 'name');
	$modules   = array_column($inventory['modules'], 'name');

	$active = new ReflectionProperty(\SoinProduction\Kit\Bootstrapper::class, 'activeModules');
	$active->setAccessible(true);
	$active->setValue(null, [ 'platform' => [ 'sp-author-meta' ], 'acf' => [], 'plugins' => [ 'sp-documentation' ] ]);

	$checks = [
		'composer PHP requirement is canonical'        => ($inventory['metadata']['php'] ?? '') === '>=8.1',
		'Node engine is read from package.json'         => ($inventory['metadata']['node'] ?? '') === '>=20.19 <23',
		'complete section is discovered'                => $sections === [ 'section-hero' ],
		'incomplete section is excluded'                => !in_array('section-incomplete', $sections, true),
		'complete block is discovered'                  => $blocks === [ 'block-editor' ],
		'frontend component is discovered'              => ($inventory['frontend'] ?? []) === [ 'modal' ],
		'disabled module is excluded'                   => !in_array('_sp-disabled', $modules, true),
		'missing configured module stays visible'       => str_contains($english, '`sp-content-library`') && str_contains($english, '**missing**'),
		'public nested config is documented'            => str_contains($english, 'editor_layouts=author_quote, blockquote'),
		'Russian inventory is localized'                => str_contains($russian, '# Текущая конфигурация темы'),
		'Bootstrapper exposes runtime active modules'    => \SoinProduction\Kit\Bootstrapper::activeModules('plugins') === [ 'sp-documentation' ],
		'build generator is inert when included by runtime' => trim((string) $probeOutput) === 'continued',
		'runtime autoloader skips executable bin directories' => is_string($bootstrapperSource)
			&& str_contains($bootstrapperSource, "'bin'")
			&& str_contains($bootstrapperSource, 'AUTOLOAD_SKIP_DIRECTORIES'),
		'Wiki discovers platform and ACF module docs'     => is_string($wiki) && str_contains($wiki, 'php-kit/platform') && str_contains($wiki, 'php-kit/acf'),
		'Wiki builds a runtime theme inventory'           => is_string($wiki) && str_contains($wiki, 'DocumentationInventory::collect'),
	];
} finally {
	$remove($fixture);
}

$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
if ($failed) {
	fwrite(STDERR, 'Documentation failures: ' . implode(', ', $failed) . PHP_EOL);
	exit(1);
}

echo 'Documentation: ' . count($checks) . " checks passed.\n";
