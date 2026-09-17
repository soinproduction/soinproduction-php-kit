<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
	exit(1);
}

require dirname(__DIR__) . '/src/Html.php';

use SoinProduction\Kit\Html;

$checks = [
	'arbitrary decimal value is preserved' => Html::classNames('max-w-[23.5rem]') === 'max-w-[23.5rem]',
	'variants and arbitrary selectors are preserved' => Html::classNames('hover:bg-[rgba(0,0,0,.35)] [&>*]:w-1/2') === 'hover:bg-[rgba(0,0,0,.35)] [&>*]:w-1/2',
	'whitespace is normalized' => Html::classNames("  flex\n\titems-center  ") === 'flex items-center',
	'duplicate classes are removed' => Html::classNames('flex flex items-center flex') === 'flex items-center',
	'arrays are supported' => Html::classNames(['grid', 'grid-cols-[minmax(0,1fr)_auto]']) === 'grid grid-cols-[minmax(0,1fr)_auto]',
	'control characters are removed' => Html::classNames("flex\0 items-center") === 'flex items-center',
];

$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => ! $passed));
if ($failed !== []) {
	fwrite(STDERR, 'HTML helper failures: ' . implode(', ', $failed) . PHP_EOL);
	exit(1);
}

echo 'HTML helper: ' . count($checks) . " checks passed.\n";
