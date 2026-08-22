<?php

/**
 * Plugin Name: SP Deployment Manager
 * Description: Composer-backed repository updates, status checks and rollback from WordPress admin.
 * Version: 1.0.3
 */

if (! defined('ABSPATH')) {
	exit;
}

if (! class_exists('SP_Deployment_Manager', false)) {
	final class SP_Deployment_Manager
	{
		private const VERSION = '1.0.3';
		private const PAGE_SLUG = 'sp-deployment-manager';
		private const NONCE_ACTION = 'sp_deployment_manager';
		private const CRON_HOOK = 'sp_deployment_manager_run_job';
		private const STATE_OPTION = 'sp_deployment_manager_state';
		private const REMOTE_CACHE_TTL = 300;
		private const LOG_LIMIT = 30000;

		/** @var array<string, mixed>|null */
		private static ?array $config = null;

		public static function init(): void
		{
			add_action('admin_menu', [self::class, 'registerPage']);
			add_action('admin_enqueue_scripts', [self::class, 'enqueueAssets']);
			add_action('wp_ajax_sp_deployment_snapshot', [self::class, 'ajaxSnapshot']);
			add_action('wp_ajax_sp_deployment_update', [self::class, 'ajaxStartUpdate']);
			add_action('wp_ajax_sp_deployment_rollback', [self::class, 'ajaxStartRollback']);
			add_action(self::CRON_HOOK, [self::class, 'runJob'], 10, 1);
		}

		public static function registerPage(): void
		{
			$config = self::config();
			add_management_page(
				__('Repository Updates', 'sp-deployment-manager'),
				__('Repository Updates', 'sp-deployment-manager'),
				(string) $config['capability'],
				self::PAGE_SLUG,
				[self::class, 'renderPage']
			);
		}

		public static function enqueueAssets(string $hook): void
		{
			if ($hook !== 'tools_page_' . self::PAGE_SLUG || ! self::canManage()) {
				return;
			}

			$assetDir = __DIR__ . '/assets/';
			$assetUrl = \SoinProduction\Kit\Bootstrapper::pathToUrl(__DIR__);
			if ($assetUrl === '' && defined('WP_CONTENT_DIR')) {
				$contentDir = rtrim(wp_normalize_path(WP_CONTENT_DIR), '/') . '/';
				$moduleDir = wp_normalize_path(__DIR__);
				if (str_starts_with($moduleDir . '/', $contentDir)) {
					$assetUrl = content_url(ltrim(substr($moduleDir, strlen($contentDir)), '/'));
				}
			}
			$assetUrl = trailingslashit($assetUrl) . 'assets/';
			$styleDependencies = wp_style_is('sp-admin-ui', 'registered') ? ['sp-admin-ui'] : [];
			wp_enqueue_style(
				'sp-deployment-manager',
				$assetUrl . 'admin.css',
				$styleDependencies,
				is_readable($assetDir . 'admin.css') ? (string) filemtime($assetDir . 'admin.css') : self::VERSION
			);
			wp_enqueue_script(
				'sp-deployment-manager',
				$assetUrl . 'admin.js',
				[],
				is_readable($assetDir . 'admin.js') ? (string) filemtime($assetDir . 'admin.js') : self::VERSION,
				true
			);

			\SoinProduction\Kit\AdminBootstrap::set('deploymentManager', [
				'ajaxUrl'      => admin_url('admin-ajax.php'),
				'nonce'        => wp_create_nonce(self::NONCE_ACTION),
				'pollInterval' => 3000,
				'copy'         => self::copy(),
			]);
		}

		public static function renderPage(): void
		{
			if (! self::canManage()) {
				wp_die(
					esc_html__('You are not allowed to update repository packages.', 'sp-deployment-manager'),
					'',
					['response' => 403]
				);
			}

			$config = self::config();
			$copy = self::copy();
			?>
			<div class="wrap sp-admin-page sp-deployment" data-sp-deployment-manager>
				<header class="sp-admin-header">
					<div class="sp-admin-header__identity">
						<span class="sp-admin-header__icon dashicons dashicons-update" aria-hidden="true"></span>
						<div class="sp-admin-header__copy">
							<h1><?php echo esc_html($copy['title']); ?></h1>
							<p><?php echo esc_html($copy['description']); ?></p>
						</div>
					</div>
					<div class="sp-admin-header__actions">
						<button type="button" class="button" data-sp-deployment-check><?php echo esc_html($copy['check']); ?></button>
						<button type="button" class="button button-primary" data-sp-deployment-update disabled><?php echo esc_html($copy['update']); ?></button>
					</div>
				</header>

				<div class="notice inline" data-sp-deployment-notice hidden><p></p></div>

				<section class="sp-admin-card sp-deployment__package">
					<div class="sp-admin-card__header">
						<div class="sp-admin-card__copy">
							<h2><?php echo esc_html((string) $config['package']); ?></h2>
							<p><code><?php echo esc_html((string) $config['repository']); ?></code> · <code><?php echo esc_html((string) $config['branch']); ?></code></p>
						</div>
						<span class="sp-deployment__badge" data-sp-deployment-badge><?php echo esc_html($copy['loading']); ?></span>
					</div>
					<div class="sp-deployment__versions">
						<div><span><?php echo esc_html($copy['installed']); ?></span><strong data-sp-deployment-installed>—</strong></div>
						<span class="dashicons dashicons-arrow-right-alt" aria-hidden="true"></span>
						<div><span><?php echo esc_html($copy['available']); ?></span><strong data-sp-deployment-remote>—</strong></div>
					</div>
					<div class="sp-deployment__commit" data-sp-deployment-commit hidden>
						<strong data-sp-deployment-message></strong>
						<span data-sp-deployment-date></span>
					</div>
				</section>

				<div class="sp-deployment__grid">
					<section class="sp-admin-card">
						<div class="sp-admin-card__header"><h2><?php echo esc_html($copy['environment']); ?></h2></div>
						<dl class="sp-deployment__details">
							<div><dt>Project</dt><dd><code data-sp-deployment-root>—</code></dd></div>
							<div><dt>Composer</dt><dd data-sp-deployment-composer>—</dd></div>
							<div><dt><?php echo esc_html($copy['job']); ?></dt><dd data-sp-deployment-state>—</dd></div>
						</dl>
					</section>

					<section class="sp-admin-card">
						<div class="sp-admin-card__header">
							<h2><?php echo esc_html($copy['rollback_title']); ?></h2>
							<button type="button" class="button" data-sp-deployment-rollback disabled><?php echo esc_html($copy['rollback']); ?></button>
						</div>
						<p class="sp-deployment__rollback-copy" data-sp-deployment-rollback-copy><?php echo esc_html($copy['rollback_empty']); ?></p>
					</section>
				</div>

				<section class="sp-admin-card sp-deployment__log" data-sp-deployment-log-card hidden>
					<div class="sp-admin-card__header"><h2><?php echo esc_html($copy['log']); ?></h2></div>
					<pre data-sp-deployment-log></pre>
				</section>
			</div>
			<?php
		}

		public static function ajaxSnapshot(): void
		{
			self::verifyAjax();
			$force = ! empty($_POST['force']);
			wp_send_json_success(self::snapshot($force));
		}

		public static function ajaxStartUpdate(): void
		{
			self::verifyAjax();
			$snapshot = self::snapshot(true);
			if (empty($snapshot['environment']['available'])) {
				wp_send_json_error(['message' => (string) $snapshot['environment']['message']], 409);
			}

			if (empty($snapshot['remote']['sha'])) {
				wp_send_json_error(['message' => (string) ($snapshot['remote']['error'] ?? 'Remote version is unavailable.')], 502);
			}

			if (empty($snapshot['update_available'])) {
				wp_send_json_error(['message' => 'The package is already up to date.'], 409);
			}

			self::startJob('update', [
				'target'           => (string) $snapshot['remote']['sha'],
				'installed_before' => (string) $snapshot['installed'],
			]);
		}

		public static function ajaxStartRollback(): void
		{
			self::verifyAjax();
			$state = self::state();
			$rollback = isset($state['rollback']) && is_array($state['rollback']) ? $state['rollback'] : [];
			if (empty($rollback['path']) || empty($rollback['target']) || ! is_readable((string) $rollback['path'])) {
				wp_send_json_error(['message' => 'A rollback snapshot is not available.'], 409);
			}

			$projectRoot = self::projectRoot();
			$environment = \SoinProduction\Kit\RepositoryUpdater::environment(self::config(), $projectRoot);
			if (empty($environment['available'])) {
				wp_send_json_error(['message' => (string) $environment['message']], 409);
			}

			self::startJob('rollback', [
				'target'           => (string) $rollback['target'],
				'backup_path'      => (string) $rollback['path'],
				'installed_before' => \SoinProduction\Kit\RepositoryUpdater::installedReference($projectRoot, (string) self::config()['package']),
			]);
		}

		/** @param array<string, string> $payload */
		private static function startJob(string $operation, array $payload): void
		{
			$current = self::state();
			if (in_array((string) ($current['status'] ?? ''), ['pending', 'running'], true)) {
				wp_send_json_error(['message' => 'Another repository update is already running.'], 409);
			}

			$jobId = wp_generate_uuid4();
			$state = array_merge($payload, [
				'job_id'       => $jobId,
				'operation'    => $operation,
				'status'       => 'pending',
				'message'      => 'Update job is waiting for WordPress Cron.',
				'log'          => '',
				'created_at'   => gmdate('c'),
				'started_at'   => '',
				'finished_at'  => '',
				'rollback'     => $current['rollback'] ?? null,
			]);
			self::saveState($state);

			$scheduled = wp_schedule_single_event(time() + 1, self::CRON_HOOK, [$jobId]);
			if (is_wp_error($scheduled) || $scheduled === false) {
				$state['status'] = 'error';
				$state['message'] = is_wp_error($scheduled) ? $scheduled->get_error_message() : 'Unable to schedule the update job.';
				self::saveState($state);
				wp_send_json_error(['message' => $state['message']], 500);
			}

			if (function_exists('spawn_cron')) {
				spawn_cron(time());
			}

			wp_send_json_success(['job_id' => $jobId, 'state' => $state]);
		}

		public static function runJob(string $jobId): void
		{
			$state = self::state();
			if (($state['job_id'] ?? '') !== $jobId || ($state['status'] ?? '') !== 'pending') {
				return;
			}

			$config = self::config();
			$projectRoot = self::projectRoot();
			$backupDir = self::backupDir();
			self::protectBackupDir($backupDir);
			$lockHandle = @fopen($backupDir . '/update.lock', 'c+');

			if (! is_resource($lockHandle) || ! flock($lockHandle, LOCK_EX | LOCK_NB)) {
				self::finishWithError($state, 'Another filesystem update is already running.', '');
				if (is_resource($lockHandle)) {
					fclose($lockHandle);
				}
				return;
			}

			$state['status'] = 'running';
			$state['message'] = 'Composer is updating the repository package.';
			$state['started_at'] = gmdate('c');
			self::saveState($state);

			if (function_exists('set_time_limit')) {
				@set_time_limit((int) $config['timeout'] + 30);
			}
			ignore_user_abort(true);

			$operation = ($state['operation'] ?? '') === 'rollback' ? 'rollback' : 'update';
			$currentReference = \SoinProduction\Kit\RepositoryUpdater::installedReference($projectRoot, (string) $config['package']);
			$currentBackup = \SoinProduction\Kit\RepositoryUpdater::backupLock($projectRoot, $backupDir, $currentReference);
			$commandOperation = 'update';

			if ($operation === 'rollback') {
				$restored = \SoinProduction\Kit\RepositoryUpdater::restoreLock(
					$projectRoot,
					(string) ($state['backup_path'] ?? ''),
					$backupDir
				);
				if (! $restored) {
					self::finishWithError($state, 'Unable to restore the Composer lock snapshot.', '');
					flock($lockHandle, LOCK_UN);
					fclose($lockHandle);
					return;
				}
				$commandOperation = 'install';
			}

			$command = \SoinProduction\Kit\RepositoryUpdater::composerCommand($config, $commandOperation, $projectRoot);
			$result = \SoinProduction\Kit\RepositoryUpdater::runProcess($command, $projectRoot, (int) $config['timeout']);
			$installedAfter = \SoinProduction\Kit\RepositoryUpdater::installedReference($projectRoot, (string) $config['package']);
			$target = (string) ($state['target'] ?? '');
			$matchesTarget = $target === '' || hash_equals($target, $installedAfter);
			$success = $result['exit_code'] === 0 && $installedAfter !== '' && $matchesTarget;
			$log = self::processLog($command, $result, $projectRoot);

			if (! $success && $currentBackup !== '') {
				$recovered = \SoinProduction\Kit\RepositoryUpdater::restoreLock($projectRoot, $currentBackup, $backupDir);
				if ($recovered) {
					$recoveryCommand = \SoinProduction\Kit\RepositoryUpdater::composerCommand($config, 'install', $projectRoot);
					$recovery = \SoinProduction\Kit\RepositoryUpdater::runProcess($recoveryCommand, $projectRoot, (int) $config['timeout']);
					$log .= "\n\nRecovery:\n" . self::processLog($recoveryCommand, $recovery, $projectRoot);
				}
			}

			if ($success) {
				$state['status'] = 'success';
				$state['message'] = $operation === 'rollback' ? 'Rollback completed.' : 'Repository package updated successfully.';
				$state['installed_after'] = $installedAfter;
				$state['rollback'] = $operation === 'update' && $currentBackup !== '' && $currentReference !== ''
					? ['path' => $currentBackup, 'target' => $currentReference]
					: null;
			} else {
				$state['status'] = 'error';
				$state['message'] = $result['timed_out']
					? 'Composer timed out; the previous lock file was restored.'
					: ($matchesTarget ? 'Composer update failed; recovery was attempted.' : 'Composer finished, but the installed reference does not match the requested commit.');
			}

			$state['log'] = self::truncate($log);
			$state['finished_at'] = gmdate('c');
			self::saveState($state);
			\SoinProduction\Kit\RepositoryUpdater::cleanupBackups($backupDir, (int) $config['backup_limit']);
			do_action('sp_deployment_manager_job_complete', $state, $config);

			flock($lockHandle, LOCK_UN);
			fclose($lockHandle);
		}

		/** @return array<string, mixed> */
		private static function snapshot(bool $forceRemote): array
		{
			$config = self::config();
			$projectRoot = self::projectRoot();
			$installed = $projectRoot !== ''
				? \SoinProduction\Kit\RepositoryUpdater::installedReference($projectRoot, (string) $config['package'])
				: '';
			$remote = self::remoteInfo($forceRemote);
			$environment = self::environment($forceRemote, $projectRoot);
			$state = self::state();

			return [
				'package'          => (string) $config['package'],
				'repository'       => (string) $config['repository'],
				'branch'           => (string) $config['branch'],
				'project_root'     => $projectRoot,
				'installed'        => $installed,
				'installed_short'  => \SoinProduction\Kit\RepositoryUpdater::shortReference($installed),
				'remote'           => $remote,
				'update_available' => ! empty($remote['sha']) && $installed !== '' && ! hash_equals((string) $remote['sha'], $installed),
				'environment'      => $environment,
				'state'            => $state,
			];
		}

		/** @return array<string, mixed> */
		private static function remoteInfo(bool $force): array
		{
			$config = self::config();
			$cacheKey = 'sp_deployment_remote_' . md5((string) $config['repository'] . '|' . (string) $config['branch']);
			if (! $force) {
				$cached = get_transient($cacheKey);
				if (is_array($cached)) {
					return $cached;
				}
			}

			$url = 'https://api.github.com/repos/' . (string) $config['repository'] . '/commits/' . rawurlencode((string) $config['branch']);
			$headers = [
				'Accept'               => 'application/vnd.github+json',
				'X-GitHub-Api-Version' => '2022-11-28',
				'User-Agent'           => 'SoinProduction-Deployment-Manager/' . self::VERSION,
			];
			$token = self::githubToken();
			if ($token !== '') {
				$headers['Authorization'] = 'Bearer ' . $token;
			}

			$response = wp_remote_get($url, ['timeout' => 15, 'redirection' => 2, 'headers' => $headers]);
			if (is_wp_error($response)) {
				return ['sha' => '', 'short' => '', 'error' => $response->get_error_message()];
			}

			$code = (int) wp_remote_retrieve_response_code($response);
			$body = json_decode((string) wp_remote_retrieve_body($response), true);
			if ($code !== 200 || ! is_array($body)) {
				$message = is_array($body) ? (string) ($body['message'] ?? '') : '';
				return ['sha' => '', 'short' => '', 'error' => $message !== '' ? $message : 'GitHub returned HTTP ' . $code . '.'];
			}

			$sha = isset($body['sha']) ? strtolower((string) $body['sha']) : '';
			if (preg_match('/^[a-f0-9]{40}$/', $sha) !== 1) {
				return ['sha' => '', 'short' => '', 'error' => 'GitHub returned an invalid commit reference.'];
			}

			$message = trim((string) ($body['commit']['message'] ?? ''));
			$message = strtok($message, "\r\n") ?: '';
			$info = [
				'sha'     => $sha,
				'short'   => \SoinProduction\Kit\RepositoryUpdater::shortReference($sha),
				'message' => $message,
				'date'    => (string) ($body['commit']['committer']['date'] ?? ''),
				'url'     => (string) ($body['html_url'] ?? ''),
				'error'   => '',
			];
			set_transient($cacheKey, $info, self::REMOTE_CACHE_TTL);

			return $info;
		}

		/** @return array<string, mixed> */
		private static function environment(bool $force, string $projectRoot): array
		{
			$config = self::config();
			$cacheKey = 'sp_deployment_environment_' . md5(self::VERSION . '|' . $projectRoot . '|' . wp_json_encode([
				$config['composer_command'],
				$config['php_binary'],
			]));
			if (! $force) {
				$cached = get_transient($cacheKey);
				if (is_array($cached)) {
					return $cached;
				}
			}

			$environment = \SoinProduction\Kit\RepositoryUpdater::environment($config, $projectRoot);
			set_transient($cacheKey, $environment, 300);

			return $environment;
		}

		/** @return array<string, mixed> */
		private static function config(): array
		{
			if (self::$config !== null) {
				return self::$config;
			}

			$config = \SoinProduction\Kit\Bootstrapper::moduleConfig('plugins', 'sp-deployment-manager');
			self::$config = \SoinProduction\Kit\RepositoryUpdater::normalizeConfig(is_array($config) ? $config : []);

			return self::$config;
		}

		private static function projectRoot(): string
		{
			$config = self::config();
			return \SoinProduction\Kit\RepositoryUpdater::findProjectRoot(
				(string) $config['package'],
				(string) $config['project_root']
			);
		}

		private static function backupDir(): string
		{
			$base = defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : dirname(ABSPATH) . '/wp-content';
			return rtrim($base, '/\\') . '/sp-deployment-backups/' . substr(md5((string) self::config()['package']), 0, 12);
		}

		private static function protectBackupDir(string $backupDir): void
		{
			if (! is_dir($backupDir)) {
				wp_mkdir_p($backupDir);
			}
			if (! is_file($backupDir . '/index.php')) {
				@file_put_contents($backupDir . '/index.php', "<?php\n// Silence is golden.\n");
			}
			if (! is_file($backupDir . '/.htaccess')) {
				@file_put_contents($backupDir . '/.htaccess', "Require all denied\nDeny from all\n");
			}
			if (! is_file($backupDir . '/web.config')) {
				@file_put_contents(
					$backupDir . '/web.config',
					"<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>\n"
				);
			}
		}

		/** @return array<string, mixed> */
		private static function state(): array
		{
			$state = get_option(self::STATE_OPTION, []);
			$state = is_array($state) ? $state : [];
			$status = (string) ($state['status'] ?? '');
			$started = ! empty($state['started_at'])
				? (string) $state['started_at']
				: (string) ($state['created_at'] ?? '');
			$startedTimestamp = $started !== '' ? strtotime($started) : false;
			$staleAfter = (int) self::config()['timeout'] + 120;

			if (
				in_array($status, ['pending', 'running'], true)
				&& is_int($startedTimestamp)
				&& $startedTimestamp < (time() - $staleAfter)
			) {
				$state['status'] = 'error';
				$state['message'] = 'The update job became stale. Check WordPress Cron and the Composer log before retrying.';
				$state['finished_at'] = gmdate('c');
				self::saveState($state);
			}

			return $state;
		}

		/** @param array<string, mixed> $state */
		private static function saveState(array $state): void
		{
			update_option(self::STATE_OPTION, $state, false);
		}

		/** @param array<string, mixed> $state */
		private static function finishWithError(array $state, string $message, string $log): void
		{
			$state['status'] = 'error';
			$state['message'] = $message;
			$state['log'] = self::truncate($log);
			$state['finished_at'] = gmdate('c');
			self::saveState($state);
		}

		/**
		 * @param array<int, string> $command
		 * @param array{exit_code: int, stdout: string, stderr: string, timed_out: bool} $result
		 */
		private static function processLog(array $command, array $result, string $projectRoot): string
		{
			$displayCommand = implode(' ', array_map(static fn (string $part): string => escapeshellarg($part), $command));
			$log = '$ ' . $displayCommand . "\n";
			$log .= trim($result['stdout'] . ($result['stderr'] !== '' ? "\n" . $result['stderr'] : ''));
			$log .= "\nExit code: " . $result['exit_code'];
			$log = str_replace($projectRoot, '[project]', $log);
			$log = (string) preg_replace('#(https?://)[^/\s:@]+:[^@\s/]+@#i', '$1[redacted]@', $log);
			$log = (string) preg_replace('/\bgh[pousr]_[A-Za-z0-9_]{20,}\b/', '[redacted-github-token]', $log);
			$token = self::githubToken();
			if ($token !== '') {
				$log = str_replace($token, '[redacted]', $log);
			}

			return self::truncate($log);
		}

		private static function githubToken(): string
		{
			$constant = (string) self::config()['github_token_constant'];
			if ($constant !== '' && defined($constant)) {
				$value = constant($constant);
				return is_string($value) ? trim($value) : '';
			}

			$value = getenv('SP_DEPLOYMENT_GITHUB_TOKEN');
			return is_string($value) ? trim($value) : '';
		}

		private static function truncate(string $value): string
		{
			if (strlen($value) <= self::LOG_LIMIT) {
				return $value;
			}

			return "… output truncated …\n" . substr($value, -self::LOG_LIMIT);
		}

		private static function canManage(): bool
		{
			return current_user_can('manage_options') && current_user_can((string) self::config()['capability']);
		}

		private static function verifyAjax(): void
		{
			check_ajax_referer(self::NONCE_ACTION, 'nonce');
			if (! self::canManage()) {
				wp_send_json_error(['message' => 'Permission denied.'], 403);
			}
		}

		/** @return array<string, string> */
		private static function copy(): array
		{
			$locale = function_exists('get_user_locale') ? strtolower((string) get_user_locale()) : 'en_us';
			if (str_starts_with($locale, 'ru')) {
				return [
					'title'          => 'Обновления репозитория',
					'description'    => 'Проверка и установка последней согласованной версии SoinProduction PHP Kit через Composer.',
					'check'          => 'Проверить обновления',
					'update'         => 'Обновить сейчас',
					'rollback'       => 'Откатить',
					'installed'      => 'Установлено',
					'available'      => 'В репозитории',
					'environment'    => 'Готовность сервера',
					'job'            => 'Последняя операция',
					'rollback_title' => 'Точка восстановления',
					'rollback_empty' => 'Появится после успешного обновления.',
					'log'            => 'Журнал Composer',
					'loading'        => 'Проверка…',
					'up_to_date'     => 'Актуально',
					'update_ready'   => 'Доступно обновление',
					'unavailable'    => 'Недоступно',
					'confirm_update' => 'Установить последнюю версию php-kit? Перед обновлением будет сохранён composer.lock.',
					'confirm_rollback' => 'Восстановить предыдущий composer.lock и переустановить зависимости?',
				];
			}

			return [
				'title'          => 'Repository Updates',
				'description'    => 'Check and install the latest consistent SoinProduction PHP Kit version through Composer.',
				'check'          => 'Check for updates',
				'update'         => 'Update now',
				'rollback'       => 'Rollback',
				'installed'      => 'Installed',
				'available'      => 'Repository',
				'environment'    => 'Server readiness',
				'job'            => 'Last operation',
				'rollback_title' => 'Recovery point',
				'rollback_empty' => 'Created after a successful update.',
				'log'            => 'Composer log',
				'loading'        => 'Checking…',
				'up_to_date'     => 'Up to date',
				'update_ready'   => 'Update available',
				'unavailable'    => 'Unavailable',
				'confirm_update' => 'Install the latest php-kit version? composer.lock will be backed up first.',
				'confirm_rollback' => 'Restore the previous composer.lock and reinstall dependencies?',
			];
		}
	}

	SP_Deployment_Manager::init();
}
