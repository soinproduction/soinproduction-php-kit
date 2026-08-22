# SP Deployment Manager

Composer-backed repository updater for sites that consume `soinproduction/php-kit`. It compares the installed package reference with a configured GitHub branch, schedules the update outside the Ajax request, records Composer output and keeps the previous `composer.lock` as a recovery point.

## What Gets Updated

The php-kit is one Composer package, so a successful update installs one consistent revision of all its `src`, `platform`, `acf` and `plugins` code. Only modules enabled by the consuming theme are loaded, but the package itself is updated as a unit.

The manager deliberately does not run `git pull` and does not replace individual files. Its update command is equivalent to:

```bash
composer update soinproduction/php-kit --with-dependencies --no-interaction --no-progress --prefer-dist --optimize-autoloader
```

Composer updates `composer.lock`, the package checkout and generated installed metadata together. A failed job restores the previous lock and attempts `composer install` recovery.

## Enabling

Add the module to the theme's php-kit configuration:

```php
'plugins' => [
	'sp-deployment-manager',
],
```

The page appears under **Tools → Repository Updates** for administrators with both `manage_options` and `update_plugins`.

## Configuration

Defaults target the `main` branch of `soinproduction/soinproduction-php-kit`:

```php
'sp-deployment-manager' => [
	'package'               => 'soinproduction/php-kit',
	'repository'            => 'soinproduction/soinproduction-php-kit',
	'branch'                => 'main',
	'composer_command'      => [ '/usr/local/bin/composer' ],
	'php_binary'            => '',
	'timeout'               => 300,
	'no_dev'                => true,
	'capability'            => 'update_plugins',
	'github_token_constant' => 'SP_DEPLOYMENT_GITHUB_TOKEN',
	'backup_limit'          => 5,
],
```

`project_root` is normally discovered through Composer's installed package metadata. Set it only when the package lives outside the project that owns `composer.json`.

`composer_command` is an argument array, never a shell string. Leave it empty to use a project `composer.phar`, `SP_DEPLOYMENT_COMPOSER_BINARY`, or finally `composer` from `PATH`. When the configured Composer executable is a PHP script with a shebang, the manager automatically invokes it through the CLI binary from `PHP_BINDIR` instead of relying on the web server's `PATH`. Use `php_binary` or `SP_DEPLOYMENT_PHP_BINARY` for non-standard installations.

## Private Repositories

Define a read-only GitHub token outside the database for GitHub API status checks:

```php
define( 'SP_DEPLOYMENT_GITHUB_TOKEN', 'github-token-here' );
```

Composer must also already be able to read the repository through its normal `auth.json`, `COMPOSER_AUTH`, SSH agent or deploy-key configuration. The manager never places tokens in process arguments or logs.

## Job and Rollback Flow

1. Ajax validates a nonce and capabilities.
2. A single WordPress Cron event receives a random job ID.
3. The worker acquires a filesystem lock shared by all requests.
4. The current `composer.lock` is copied into a protected directory under `wp-content/sp-deployment-backups`.
5. Composer updates only the configured package and its dependencies.
6. The installed commit is read from `vendor/composer/installed.json` and compared with GitHub.
7. On failure, the previous lock is restored and Composer recovery is attempted.

The successful update leaves one rollback point in the UI. Rollback restores that lock and runs `composer install`, so network access to the previous package revision must remain available.

## Server Requirements

- Writable project root and `vendor` directory.
- Composer available as an executable or local PHAR.
- `proc_open()` enabled for PHP-FPM/Apache PHP.
- Working WordPress Cron loopback and outbound HTTPS to GitHub/Composer repositories.
- Enough process time for the configured timeout.

The UI disables installation and reports the exact diagnostic when these requirements are not met.
