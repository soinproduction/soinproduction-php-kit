<?php

/**
 * Focused regression checks for the Composer-backed repository updater.
 * Run directly with: php tests/repository-updater.php
 */

if (PHP_SAPI !== 'cli') {
	exit(1);
}

define('ABSPATH', __DIR__ . '/');

require dirname(__DIR__) . '/src/RepositoryUpdater.php';
require dirname(__DIR__) . '/src/Bootstrapper.php';

use SoinProduction\Kit\RepositoryUpdater;
use SoinProduction\Kit\Bootstrapper;

$GLOBALS['sp_deployment_registered_hooks'] = [];
function add_action(string $hook, $callback, int $priority = 10, int $acceptedArgs = 1): void
{
	$GLOBALS['sp_deployment_registered_hooks'][] = compact('hook', 'callback', 'priority', 'acceptedArgs');
}

require dirname(__DIR__) . '/plugins/sp-deployment-manager/index.php';

$testRoot = sys_get_temp_dir() . '/sp-repository-updater-' . bin2hex(random_bytes(5));
$projectRoot = $testRoot . '/project';
$packagePath = $projectRoot . '/vendor/soinproduction/php-kit';
$composerDir = $projectRoot . '/vendor/composer';
$backupDir = $testRoot . '/backups';
$composerFixture = $testRoot . '/composer';
$gitFixture = $testRoot . '/git';

mkdir($packagePath, 0755, true);
mkdir($composerDir, 0755, true);
mkdir($packagePath . '/.git/refs/heads', 0755, true);
file_put_contents($packagePath . '/.git/HEAD', "ref: refs/heads/main\n");
file_put_contents($projectRoot . '/composer.json', json_encode([
	'require' => ['soinproduction/php-kit' => 'dev-main'],
], JSON_PRETTY_PRINT));
file_put_contents($projectRoot . '/composer.lock', "original-lock\n");
file_put_contents($composerFixture, "#!/usr/bin/env php\n<?php\n");
chmod($composerFixture, 0755);
file_put_contents($projectRoot . '/composer.phar', "#!/usr/bin/env php\n<?php\n");
chmod($projectRoot . '/composer.phar', 0755);
file_put_contents($gitFixture, "#!/usr/bin/env php\n<?php echo \"abcdef1234567890abcdef1234567890abcdef12\\trefs/heads/main\\n\";\n");
chmod($gitFixture, 0755);
file_put_contents($composerDir . '/installed.json', json_encode([
	'packages' => [[
		'name'   => 'soinproduction/php-kit',
		'source' => [
			'reference' => '1234567890abcdef1234567890abcdef12345678',
			'url'       => 'git@github.com:soinproduction/soinproduction-php-kit.git',
		],
	]],
], JSON_PRETTY_PRINT));

$normalized = RepositoryUpdater::normalizeConfig([
	'package'          => 'invalid package',
	'repository'       => '../invalid',
	'branch'           => '../main',
	'timeout'          => 2,
	'backup_limit'     => 99,
	'composer_command' => ['/usr/local/bin/composer', ''],
	'composer_home'    => '  ' . $testRoot . '/composer-home  ',
]);
$defaultCommand = RepositoryUpdater::composerCommand([
	'package'          => 'vendor/package',
	'composer_command' => [ 'sp-composer-command' ],
], 'install', $projectRoot);
$automaticCommand = RepositoryUpdater::composerCommand([
	'package' => 'vendor/package',
], 'install', $projectRoot);
$command = RepositoryUpdater::composerCommand([
	'package'          => 'vendor/package',
	'composer_command' => ['sp-composer-command'],
	'no_dev'           => true,
], 'update', $projectRoot);
$cacheCommand = RepositoryUpdater::composerCommand([
	'composer_command' => ['sp-composer-command'],
], 'clear-cache', $projectRoot);
$reinstallCommand = RepositoryUpdater::composerCommand([
	'package'          => 'vendor/package',
	'composer_command' => ['sp-composer-command'],
], 'reinstall', $projectRoot);
$interpretedCommand = RepositoryUpdater::composerCommand([
	'package'          => 'vendor/package',
	'composer_command' => [$composerFixture],
	'php_binary'       => PHP_BINARY,
], 'update', $projectRoot);
$explicitInterpreterCommand = RepositoryUpdater::composerCommand([
	'package'          => 'vendor/package',
	'composer_command' => [PHP_BINARY, $composerFixture],
	'php_binary'       => PHP_BINARY,
], 'update', $projectRoot);
$processEnvironment = new ReflectionMethod(RepositoryUpdater::class, 'processEnvironment');
$processEnvironment->setAccessible(true);
$isolatedEnvironment = $processEnvironment->invoke(null, $projectRoot, $testRoot . '/composer-home', []);
file_put_contents($testRoot . '/composer-home/auth.json', json_encode([
	'github-oauth' => ['github.com' => 'test-read-only-token'],
]));
$composerToken = RepositoryUpdater::composerGithubToken([
	'composer_home' => $testRoot . '/composer-home',
], $projectRoot);
$publicRemote = RepositoryUpdater::remoteReference([
	'git_binary' => $gitFixture,
], $projectRoot);
$process = RepositoryUpdater::runProcess([
	PHP_BINARY,
	'-r',
	'fwrite(STDOUT, "ok"); fwrite(STDERR, "warn");',
], $projectRoot, 10);
$recoverableGitFailure = RepositoryUpdater::isRecoverableGitFailure([
	'exit_code' => 1,
	'stdout'    => 'In GitDownloader.php line 241:',
	'stderr'    => 'Failed to execute git show-ref --head -d: fatal: git show-ref: bad ref HEAD',
	'timed_out' => false,
]);
$ordinaryComposerFailure = RepositoryUpdater::isRecoverableGitFailure([
	'exit_code' => 1,
	'stdout'    => '',
	'stderr'    => 'Your requirements could not be resolved to an installable set of packages.',
	'timed_out' => false,
]);
$quarantinedGitMetadata = RepositoryUpdater::quarantinePackageGitMetadata(
	$projectRoot,
	'soinproduction/php-kit',
	$backupDir
);
$gitMetadataQuarantined = $quarantinedGitMetadata !== ''
	&& ! file_exists($packagePath . '/.git')
	&& is_file($quarantinedGitMetadata . '/HEAD');
$gitMetadataCleaned = RepositoryUpdater::cleanupQuarantinedGitMetadata($quarantinedGitMetadata, $backupDir);
$backup = RepositoryUpdater::backupLock(
	$projectRoot,
	$backupDir,
	'1234567890abcdef1234567890abcdef12345678'
);
file_put_contents($projectRoot . '/composer.lock', "changed-lock\n");
$restored = RepositoryUpdater::restoreLock($projectRoot, $backup, $backupDir);
$normalizeModules = new ReflectionMethod(Bootstrapper::class, 'normalize_modules');
$normalizeModules->setAccessible(true);
$enabledModules = $normalizeModules->invoke(null, [
	'sp-deployment-manager' => [
		'branch'  => 'stable',
		'timeout' => 420,
	],
], 'plugins');
$moduleConfig = Bootstrapper::moduleConfig('plugins', 'sp-deployment-manager');
$registeredHooks = array_column($GLOBALS['sp_deployment_registered_hooks'], 'hook');

$checks = [
	'invalid package falls back to the safe default'   => $normalized['package'] === 'soinproduction/php-kit',
	'invalid repository falls back to the safe default' => $normalized['repository'] === 'soinproduction/soinproduction-php-kit',
	'unsafe branch falls back to main'                 => $normalized['branch'] === 'main',
	'timeout is clamped to a safe minimum'             => $normalized['timeout'] === 30,
	'backup limit is clamped to a safe maximum'        => $normalized['backup_limit'] === 20,
	'empty command fragments are discarded'           => $normalized['composer_command'] === ['/usr/local/bin/composer'],
	'configured Composer home is normalized'           => $normalized['composer_home'] === $testRoot . '/composer-home',
	'configured Composer project is detected'          => RepositoryUpdater::findProjectRoot('soinproduction/php-kit', $projectRoot) === realpath($projectRoot),
	'project package requirement is validated'         => RepositoryUpdater::projectRequiresPackage($projectRoot, 'soinproduction/php-kit'),
	'unrelated package requirement is rejected'        => ! RepositoryUpdater::projectRequiresPackage($projectRoot, 'vendor/missing'),
	'installed Composer reference is read'             => RepositoryUpdater::installedReference($projectRoot, 'soinproduction/php-kit') === '1234567890abcdef1234567890abcdef12345678',
	'installed Composer source URL is read'             => RepositoryUpdater::installedSourceUrl($projectRoot, 'soinproduction/php-kit') === 'git@github.com:soinproduction/soinproduction-php-kit.git',
	'Composer GitHub OAuth token is reused'             => $composerToken === 'test-read-only-token',
	'project Composer PHAR is discovered automatically' => isset($automaticCommand[0], $automaticCommand[1])
		&& is_executable($automaticCommand[0])
		&& $automaticCommand[1] === $projectRoot . '/composer.phar',
	'public Git remote resolves without an API token'    => $publicRemote['sha'] === 'abcdef1234567890abcdef1234567890abcdef12',
	'update command targets only the configured package' => array_slice($command, 0, 4) === ['sp-composer-command', 'update', 'vendor/package', '--with-dependencies'],
	'cache repair command clears only Composer cache state' => $cacheCommand === ['sp-composer-command', 'clear-cache', '--no-interaction'],
	'package repair command forces a dist reinstall'       => array_slice($reinstallCommand, 0, 4) === ['sp-composer-command', 'reinstall', 'vendor/package', '--no-interaction']
		&& in_array('--prefer-dist', $reinstallCommand, true)
		&& ! in_array('--no-dev', $reinstallCommand, true),
	'broken Git metadata is eligible for one repair attempt' => $recoverableGitFailure,
	'ordinary dependency failures are not retried'          => ! $ordinaryComposerFailure,
	'only managed package Git metadata is quarantined'       => $gitMetadataQuarantined,
	'quarantined Git metadata is removed safely'             => $gitMetadataCleaned && ! file_exists($quarantinedGitMetadata),
	'PHP Composer scripts receive a CLI interpreter'    => array_slice($interpretedCommand, 0, 2) === [PHP_BINARY, $composerFixture],
	'explicit PHP interpreter is not duplicated'        => array_slice($explicitInterpreterCommand, 0, 2) === [PHP_BINARY, $composerFixture],
	'missing HOME receives a private Composer home'     => ($isolatedEnvironment['HOME'] ?? '') === $testRoot
		&& ($isolatedEnvironment['COMPOSER_HOME'] ?? '') === $testRoot . '/composer-home'
		&& ($isolatedEnvironment['GIT_TERMINAL_PROMPT'] ?? '') === '0'
		&& is_dir($testRoot . '/composer-home'),
	'non-interactive Composer flags are present'        => in_array('--no-interaction', $command, true) && in_array('--prefer-dist', $command, true),
	'production no-dev mode is supported'              => in_array('--no-dev', $command, true),
	'production no-dev mode is the safe default'        => in_array('--no-dev', $defaultCommand, true),
	'process output and exit code are captured'        => $process['exit_code'] === 0 && $process['stdout'] === 'ok' && $process['stderr'] === 'warn',
	'composer lock backup is created'                  => $backup !== '' && is_readable($backup),
	'composer lock backup is restored atomically'      => $restored && file_get_contents($projectRoot . '/composer.lock') === "original-lock\n",
	'commit references are shortened consistently'     => RepositoryUpdater::shortReference('1234567890abcdef1234567890abcdef12345678') === '1234567890ab',
	'invalid commit references are rejected'           => RepositoryUpdater::shortReference('../main') === '',
	'associative module configuration enables module'  => $enabledModules === ['sp-deployment-manager'],
	'associative module configuration keeps its keys'  => $moduleConfig === ['branch' => 'stable', 'timeout' => 420],
	'deployment module registers its admin page'        => in_array('admin_menu', $registeredHooks, true),
	'deployment module registers an update worker'      => in_array('sp_deployment_manager_run_job', $registeredHooks, true),
	'deployment module exposes authenticated Ajax only' => in_array('wp_ajax_sp_deployment_update', $registeredHooks, true) && ! in_array('wp_ajax_nopriv_sp_deployment_update', $registeredHooks, true),
];

$failed = array_keys(array_filter($checks, static fn (bool $passed): bool => ! $passed));

$removeTree = static function (string $path) use (&$removeTree): void {
	if (! is_dir($path)) {
		@unlink($path);
		return;
	}

	foreach (scandir($path) ?: [] as $item) {
		if ($item === '.' || $item === '..') {
			continue;
		}
		$removeTree($path . '/' . $item);
	}
	@rmdir($path);
};
$removeTree($testRoot);

if ($failed !== []) {
	fwrite(STDERR, 'Repository updater failures: ' . implode(', ', $failed) . PHP_EOL);
	exit(1);
}

echo 'Repository updater: ' . count($checks) . " checks passed.\n";
