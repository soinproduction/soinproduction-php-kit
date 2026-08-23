# SP Deployment Manager

Composer-based менеджер обновлений для сайтов, подключающих `soinproduction/php-kit`. Он сравнивает установленный commit пакета с выбранной веткой GitHub, запускает установку вне Ajax-запроса, сохраняет вывод Composer и оставляет предыдущий `composer.lock` как точку восстановления.

## Что обновляется

PHP Kit является одним Composer-пакетом. Поэтому успешное обновление устанавливает одну согласованную ревизию всего кода из `src`, `platform`, `acf` и `plugins`. Тема продолжает загружать только включённые в её конфигурации модули, но сам пакет обновляется целиком.

Менеджер намеренно не выполняет `git pull` и не заменяет отдельные файлы. Команда обновления эквивалентна:

```bash
composer update soinproduction/php-kit --with-dependencies --no-interaction --no-progress --prefer-dist --optimize-autoloader
```

Composer согласованно обновляет `composer.lock`, checkout пакета и generated metadata. Если Composer обнаруживает повреждённый VCS cache (`bad ref HEAD`, `git show-ref`), менеджер один раз очищает его disposable cache, переустанавливает только управляемый пакет через dist и повторяет update, если это ещё необходимо. При окончательной ошибке менеджер восстанавливает предыдущий lock и пытается выполнить recovery через `composer install`.

## Подключение

Добавьте модуль в конфигурацию PHP Kit темы:

```php
'plugins' => [
	'sp-deployment-manager',
],
```

Страница появится в **Инструменты → Обновления репозитория** у администраторов с правами `manage_options` и `update_plugins`.

## Конфигурация

Для публичного репозитория модуль работает без обязательных constants: PHP CLI определяется из текущего PHP runtime, Composer ищется в `composer.phar`, стандартных hosting paths и `PATH`, приватный `COMPOSER_HOME` создаётся автоматически, а commit ветки сначала проверяется через публичный `git ls-remote` без GitHub API rate limit.

По умолчанию используется ветка `main` репозитория `soinproduction/soinproduction-php-kit`. Конфигурация ниже нужна только для переопределения автодетекта:

```php
'sp-deployment-manager' => [
	'package'               => 'soinproduction/php-kit',
	'repository'            => 'soinproduction/soinproduction-php-kit',
	'branch'                => 'main',
	'composer_command'      => [ '/usr/local/bin/composer' ],
	'php_binary'            => '',
	'composer_home'         => '',
	'git_binary'            => 'git',
	'timeout'               => 300,
	'no_dev'                => true,
	'capability'            => 'update_plugins',
	'github_token_constant' => 'SP_DEPLOYMENT_GITHUB_TOKEN',
	'backup_limit'          => 5,
],
```

`project_root` обычно определяется автоматически через Composer metadata установленного пакета. Указывайте его только при нестандартной структуре проекта.

`composer_command` — массив аргументов, а не shell-строка. Пустое значение последовательно ищет локальный `composer.phar`, константу `SP_DEPLOYMENT_COMPOSER_BINARY` и команду `composer` в `PATH`. Если указанный Composer является PHP-скриптом с shebang, менеджер автоматически запускает его через CLI из `PHP_BINDIR`, не полагаясь на `PATH` веб-сервера. Для нестандартной установки задайте `php_binary` или константу `SP_DEPLOYMENT_PHP_BINARY`.

Если PHP-FPM не передаёт `HOME` или `COMPOSER_HOME`, менеджер создаёт приватный каталог Composer в системной временной директории. Для постоянного cache/auth-каталога укажите `composer_home` или константу `SP_DEPLOYMENT_COMPOSER_HOME`; каталог должен находиться вне публичного web root.

## Приватные репозитории

Для публичного репозитория token не нужен. Для приватного репозитория read-only GitHub token для проверки commit храните вне базы данных:

```php
define( 'SP_DEPLOYMENT_GITHUB_TOKEN', 'github-token-here' );
```

Если отдельный token не задан, менеджер автоматически использует `github-oauth.github.com` из `COMPOSER_AUTH` или Composer `auth.json`. При недоступности GitHub API (включая исчерпанный rate limit) commit ветки определяется через `git ls-remote`; сначала используется source URL установленного Composer-пакета. Для нестандартного пути Git задайте `git_binary`.

Composer также должен иметь настроенный доступ через `auth.json`, `COMPOSER_AUTH`, SSH agent или deploy key. Менеджер никогда не добавляет token в аргументы процесса, HTML или журнал.

## Обновление и откат

1. Ajax проверяет nonce и права пользователя.
2. В WordPress Cron создаётся одиночная задача со случайным job ID.
3. Worker получает filesystem lock, общий для всех запросов сайта.
4. Текущий `composer.lock` копируется в защищённую директорию `wp-content/sp-deployment-backups`.
5. Composer обновляет только настроенный пакет и его зависимости.
6. Установленный commit читается из `vendor/composer/installed.json` и сравнивается с GitHub.
7. Повреждённый Git cache автоматически очищается, а управляемый пакет принудительно переустанавливается из dist.
8. При окончательной ошибке восстанавливается предыдущий lock и запускается recovery.

После успешной установки в интерфейсе остаётся одна точка отката. Откат восстанавливает lock и выполняет `composer install`, поэтому серверу нужен сетевой доступ к предыдущей ревизии пакета.

## Требования к серверу

- Права записи в project root и `vendor`.
- Composer как executable или локальный PHAR.
- Разрешённый `proc_open()` в PHP веб-сервера.
- Работающий WordPress Cron loopback и outbound HTTPS к GitHub/Composer repositories.
- Достаточный timeout процесса.

Если требование не выполнено, UI отключает установку и показывает точную причину.
