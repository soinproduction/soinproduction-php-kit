<?php

declare(strict_types=1);

namespace SoinProduction\Kit;

/**
 * Composer-backed updater for a repository package installed in a WordPress project.
 *
 * Commands are always passed to proc_open() as arrays, so no shell interpolation
 * or request-controlled command fragments are involved.
 */
final class RepositoryUpdater
{
	/** @return array<string, mixed> */
	public static function normalizeConfig(array $config = []): array
	{
		$defaults = [
			'package'               => 'soinproduction/php-kit',
			'repository'            => 'soinproduction/soinproduction-php-kit',
			'branch'                => 'main',
			'project_root'          => '',
			'composer_command'      => [],
			'php_binary'            => '',
			'composer_home'         => '',
			'timeout'               => 300,
			'no_dev'                => false,
			'capability'            => 'update_plugins',
			'github_token_constant' => 'SP_DEPLOYMENT_GITHUB_TOKEN',
			'backup_limit'          => 5,
		];

		$config = array_replace($defaults, $config);

		$package = isset($config['package']) ? trim((string) $config['package']) : '';
		if (preg_match('#^[a-z0-9][a-z0-9_.-]*/[a-z0-9][a-z0-9_.-]*$#i', $package) !== 1) {
			$package = $defaults['package'];
		}

		$repository = isset($config['repository']) ? trim((string) $config['repository']) : '';
		if (preg_match('#^[a-z0-9][a-z0-9_.-]*/[a-z0-9][a-z0-9_.-]*$#i', $repository) !== 1) {
			$repository = $defaults['repository'];
		}

		$branch = isset($config['branch']) ? trim((string) $config['branch']) : '';
		if ($branch === '' || strlen($branch) > 200 || str_contains($branch, '..')) {
			$branch = $defaults['branch'];
		}

		$projectRoot = isset($config['project_root']) ? trim((string) $config['project_root']) : '';
		$composerCommand = isset($config['composer_command']) && is_array($config['composer_command'])
			? array_values(array_filter(array_map('strval', $config['composer_command']), static fn (string $part): bool => $part !== ''))
			: [];
		$phpBinary = isset($config['php_binary']) ? trim((string) $config['php_binary']) : '';
		$composerHome = isset($config['composer_home']) ? trim((string) $config['composer_home']) : '';

		$capability = isset($config['capability']) ? trim((string) $config['capability']) : '';
		if (preg_match('/^[a-z0-9_-]+$/i', $capability) !== 1) {
			$capability = $defaults['capability'];
		}

		$tokenConstant = isset($config['github_token_constant']) ? trim((string) $config['github_token_constant']) : '';
		if ($tokenConstant !== '' && preg_match('/^[A-Z][A-Z0-9_]*$/', $tokenConstant) !== 1) {
			$tokenConstant = $defaults['github_token_constant'];
		}

		$config['package']               = $package;
		$config['repository']            = $repository;
		$config['branch']                = $branch;
		$config['project_root']          = $projectRoot;
		$config['composer_command']      = $composerCommand;
		$config['php_binary']            = $phpBinary;
		$config['composer_home']         = $composerHome;
		$config['timeout']               = max(30, min(1800, (int) $config['timeout']));
		$config['no_dev']                = ! empty($config['no_dev']);
		$config['capability']            = $capability;
		$config['github_token_constant'] = $tokenConstant;
		$config['backup_limit']          = max(1, min(20, (int) $config['backup_limit']));

		return $config;
	}

	public static function findProjectRoot(string $package, string $configuredRoot = ''): string
	{
		if ($configuredRoot !== '') {
			$root = realpath($configuredRoot);
			if (is_string($root) && self::projectRequiresPackage($root, $package)) {
				return $root;
			}
		}

		$installPath = '';
		if (class_exists(\Composer\InstalledVersions::class)) {
			try {
				$path = \Composer\InstalledVersions::getInstallPath($package);
				$installPath = is_string($path) ? $path : '';
			} catch (\Throwable $error) {
				$installPath = '';
			}
		}

		if ($installPath === '') {
			$installPath = dirname(__DIR__);
		}

		$cursor = realpath($installPath);
		if (! is_string($cursor)) {
			return '';
		}

		for ($depth = 0; $depth < 8; $depth++) {
			if (self::projectRequiresPackage($cursor, $package)) {
				return $cursor;
			}

			$parent = dirname($cursor);
			if ($parent === $cursor) {
				break;
			}
			$cursor = $parent;
		}

		return '';
	}

	public static function projectRequiresPackage(string $projectRoot, string $package): bool
	{
		$composer = self::readJsonFile(rtrim($projectRoot, '/\\') . '/composer.json');
		if ($composer === []) {
			return false;
		}

		return isset($composer['require'][$package]) || isset($composer['require-dev'][$package]);
	}

	public static function installedReference(string $projectRoot, string $package): string
	{
		$installedJson = self::readJsonFile(rtrim($projectRoot, '/\\') . '/vendor/composer/installed.json');
		$packages = isset($installedJson['packages']) && is_array($installedJson['packages'])
			? $installedJson['packages']
			: $installedJson;

		foreach ($packages as $item) {
			if (! is_array($item) || ($item['name'] ?? '') !== $package) {
				continue;
			}

			$reference = (string) ($item['source']['reference'] ?? $item['dist']['reference'] ?? '');
			return self::normalizeReference($reference);
		}

		$installedPhp = rtrim($projectRoot, '/\\') . '/vendor/composer/installed.php';
		if (is_readable($installedPhp)) {
			$data = require $installedPhp;
			$reference = is_array($data)
				? (string) ($data['versions'][$package]['reference'] ?? '')
				: '';

			return self::normalizeReference($reference);
		}

		return '';
	}

	/** @param array<string, mixed> $config */
	public static function composerCommand(array $config, string $operation, string $projectRoot): array
	{
		$config = self::normalizeConfig($config);
		$command = $config['composer_command'];

		if ($command === []) {
			$localPhar = rtrim($projectRoot, '/\\') . '/composer.phar';
			if (is_file($localPhar)) {
				$command = [$localPhar];
			} elseif (defined('SP_DEPLOYMENT_COMPOSER_BINARY') && is_string(SP_DEPLOYMENT_COMPOSER_BINARY) && SP_DEPLOYMENT_COMPOSER_BINARY !== '') {
				$command = [SP_DEPLOYMENT_COMPOSER_BINARY];
			} else {
				$command = ['composer'];
			}
		}

		$command = self::withPhpInterpreter($command, (string) $config['php_binary']);

		if ($operation === 'install') {
			$command[] = 'install';
		} else {
			$command[] = 'update';
			$command[] = $config['package'];
			$command[] = '--with-dependencies';
		}

		$command[] = '--no-interaction';
		$command[] = '--no-progress';
		$command[] = '--prefer-dist';
		$command[] = '--optimize-autoloader';

		if ($config['no_dev']) {
			$command[] = '--no-dev';
		}

		return $command;
	}

	/**
	 * @return array{available: bool, message: string, command: array<int, string>}
	 */
	public static function environment(array $config, string $projectRoot): array
	{
		$config = self::normalizeConfig($config);

		if ($projectRoot === '' || ! self::projectRequiresPackage($projectRoot, (string) $config['package'])) {
			return ['available' => false, 'message' => 'Composer project was not found.', 'command' => []];
		}

		$lockPath = rtrim($projectRoot, '/\\') . '/composer.lock';
		if (
			! is_writable($projectRoot)
			|| ! is_writable(rtrim($projectRoot, '/\\') . '/vendor')
			|| (is_file($lockPath) && ! is_writable($lockPath))
		) {
			return ['available' => false, 'message' => 'Composer project or vendor directory is not writable.', 'command' => []];
		}

		if (! self::functionAvailable('proc_open')) {
			return ['available' => false, 'message' => 'proc_open() is disabled on this server.', 'command' => []];
		}

		$command = self::composerCommand($config, 'update', $projectRoot);
		$probe = self::runProcess(
			array_merge(array_slice($command, 0, self::binaryPrefixLength($command)), ['--version', '--no-ansi']),
			$projectRoot,
			15,
			(string) $config['composer_home']
		);

		if ($probe['exit_code'] !== 0) {
			return [
				'available' => false,
				'message'   => trim($probe['stderr'] !== '' ? $probe['stderr'] : $probe['stdout']) ?: 'Composer is not executable.',
				'command'   => $command,
			];
		}

		return ['available' => true, 'message' => trim($probe['stdout']), 'command' => $command];
	}

	/**
	 * @param array<int, string> $command
	 * @return array{exit_code: int, stdout: string, stderr: string, timed_out: bool}
	 */
	public static function runProcess(array $command, string $cwd, int $timeout, string $composerHome = ''): array
	{
		$result = ['exit_code' => 1, 'stdout' => '', 'stderr' => '', 'timed_out' => false];
		if ($command === [] || ! self::functionAvailable('proc_open')) {
			$result['stderr'] = 'Process execution is unavailable.';
			return $result;
		}

		$descriptors = [
			0 => ['pipe', 'r'],
			1 => ['pipe', 'w'],
			2 => ['pipe', 'w'],
		];
		$pipes = [];
		$environment = self::processEnvironment($cwd, $composerHome);
		$process = @proc_open($command, $descriptors, $pipes, $cwd, $environment, ['bypass_shell' => true]);
		if (! is_resource($process)) {
			$result['stderr'] = 'Unable to start Composer.';
			return $result;
		}

		fclose($pipes[0]);
		stream_set_blocking($pipes[1], false);
		stream_set_blocking($pipes[2], false);
		$started = microtime(true);
		$lastExitCode = null;

		do {
			$result['stdout'] .= (string) stream_get_contents($pipes[1]);
			$result['stderr'] .= (string) stream_get_contents($pipes[2]);
			$status = proc_get_status($process);

			if (! $status['running']) {
				$lastExitCode = (int) $status['exitcode'];
				break;
			}

			if ((microtime(true) - $started) >= $timeout) {
				$result['timed_out'] = true;
				proc_terminate($process);
				break;
			}

			usleep(100000);
		} while (true);

		$result['stdout'] .= (string) stream_get_contents($pipes[1]);
		$result['stderr'] .= (string) stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);
		$closeCode = proc_close($process);
		$result['exit_code'] = $result['timed_out']
			? 124
			: ($lastExitCode !== null && $lastExitCode >= 0 ? $lastExitCode : (int) $closeCode);

		return $result;
	}

	public static function backupLock(string $projectRoot, string $backupDir, string $reference): string
	{
		$source = rtrim($projectRoot, '/\\') . '/composer.lock';
		if (! is_readable($source)) {
			return '';
		}

		if (! is_dir($backupDir) && ! mkdir($backupDir, 0750, true) && ! is_dir($backupDir)) {
			return '';
		}

		$reference = self::shortReference($reference) ?: 'unknown';
		$target = rtrim($backupDir, '/\\') . '/' . gmdate('Ymd-His') . '-' . $reference . '-' . bin2hex(random_bytes(6)) . '.lock';

		return copy($source, $target) ? $target : '';
	}

	public static function restoreLock(string $projectRoot, string $backupPath, string $backupDir): bool
	{
		$backupReal = realpath($backupPath);
		$dirReal = realpath($backupDir);
		if (! is_string($backupReal) || ! is_string($dirReal) || ! str_starts_with($backupReal, rtrim($dirReal, '/\\') . DIRECTORY_SEPARATOR)) {
			return false;
		}

		$lockPath = rtrim($projectRoot, '/\\') . '/composer.lock';
		$tempPath = $lockPath . '.sp-deployment-' . bin2hex(random_bytes(4));
		if (! copy($backupReal, $tempPath)) {
			return false;
		}

		if (! rename($tempPath, $lockPath)) {
			@unlink($tempPath);
			return false;
		}

		return true;
	}

	public static function cleanupBackups(string $backupDir, int $limit): void
	{
		$files = glob(rtrim($backupDir, '/\\') . '/*.lock');
		if (! is_array($files) || count($files) <= $limit) {
			return;
		}

		usort($files, static fn (string $left, string $right): int => (int) filemtime($right) <=> (int) filemtime($left));
		foreach (array_slice($files, $limit) as $file) {
			@unlink($file);
		}
	}

	public static function shortReference(string $reference): string
	{
		$reference = self::normalizeReference($reference);
		return $reference === '' ? '' : substr($reference, 0, 12);
	}

	private static function normalizeReference(string $reference): string
	{
		$reference = strtolower(trim($reference));
		return preg_match('/^[a-f0-9]{7,64}$/', $reference) === 1 ? $reference : '';
	}

	/** @return array<string, mixed> */
	private static function readJsonFile(string $path): array
	{
		if (! is_readable($path)) {
			return [];
		}

		$decoded = json_decode((string) file_get_contents($path), true);
		return is_array($decoded) ? $decoded : [];
	}

	private static function functionAvailable(string $function): bool
	{
		if (! function_exists($function)) {
			return false;
		}

		$disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
		return ! in_array($function, $disabled, true);
	}

	/** @param array<string, string>|null $inherited */
	private static function processEnvironment(string $cwd, string $configuredHome = '', ?array $inherited = null): array
	{
		if ($inherited === null) {
			$current = getenv();
			$inherited = is_array($current) ? array_map('strval', $current) : [];
		}

		if (empty($inherited['PATH'])) {
			$path = getenv('PATH');
			$inherited['PATH'] = is_string($path) && $path !== ''
				? $path
				: '/usr/local/bin:/usr/bin:/bin:/usr/sbin:/sbin';
		}

		$home = $configuredHome;
		if ($home === '' && defined('SP_DEPLOYMENT_COMPOSER_HOME') && is_string(SP_DEPLOYMENT_COMPOSER_HOME)) {
			$home = trim(SP_DEPLOYMENT_COMPOSER_HOME);
		}
		if ($home !== '') {
			if ((! is_dir($home) && ! @mkdir($home, 0700, true)) || ! is_writable($home)) {
				return $inherited;
			}
			@chmod($home, 0700);
			$inherited['COMPOSER_HOME'] = $home;
			if (empty($inherited['HOME'])) {
				$inherited['HOME'] = dirname($home);
			}
			return $inherited;
		}

		if (! empty($inherited['HOME']) || ! empty($inherited['COMPOSER_HOME'])) {
			return $inherited;
		}

		if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
			$account = posix_getpwuid(posix_geteuid());
			$accountHome = is_array($account) ? trim((string) ($account['dir'] ?? '')) : '';
			if ($accountHome !== '' && is_dir($accountHome) && is_readable($accountHome) && is_writable($accountHome)) {
				$inherited['HOME'] = $accountHome;
				return $inherited;
			}
		}

		$home = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'sp-deployment-composer-' . substr(hash('sha256', $cwd), 0, 16);
		if ((! is_dir($home) && ! @mkdir($home, 0700, true)) || ! is_writable($home)) {
			return $inherited;
		}
		@chmod($home, 0700);

		$inherited['HOME'] = dirname($home);
		$inherited['COMPOSER_HOME'] = $home;
		return $inherited;
	}

	/** @param array<int, string> $command */
	private static function withPhpInterpreter(array $command, string $configuredBinary): array
	{
		if ($command === [] || ! self::isPhpScript($command[0])) {
			return $command;
		}

		$candidates = [];
		if ($configuredBinary !== '') {
			$candidates[] = $configuredBinary;
		}
		if (defined('SP_DEPLOYMENT_PHP_BINARY') && is_string(SP_DEPLOYMENT_PHP_BINARY) && SP_DEPLOYMENT_PHP_BINARY !== '') {
			$candidates[] = SP_DEPLOYMENT_PHP_BINARY;
		}
		if (defined('PHP_BINDIR') && PHP_BINDIR !== '') {
			$candidates[] = rtrim(PHP_BINDIR, '/\\') . DIRECTORY_SEPARATOR . (PHP_OS_FAMILY === 'Windows' ? 'php.exe' : 'php');
		}
		if (defined('PHP_BINARY') && PHP_BINARY !== '') {
			$candidates[] = PHP_BINARY;
		}

		foreach (array_unique($candidates) as $candidate) {
			if (is_file($candidate) && is_executable($candidate)) {
				array_unshift($command, $candidate);
				break;
			}
		}

		return $command;
	}

	private static function isPhpScript(string $path): bool
	{
		if (! is_file($path) || ! is_readable($path)) {
			return false;
		}

		$head = @file_get_contents($path, false, null, 0, 256);
		if (! is_string($head)) {
			return false;
		}

		$head = ltrim($head, "\xEF\xBB\xBF \t\r\n");
		return str_starts_with($head, '<?php')
			|| (str_starts_with($head, '#!') && preg_match('/^#![^\r\n]*\bphp(?:\s|$)/i', $head) === 1);
	}

	/** @param array<int, string> $command */
	private static function binaryPrefixLength(array $command): int
	{
		return isset($command[1]) && self::isPhpScript($command[1]) ? 2 : 1;
	}
}
