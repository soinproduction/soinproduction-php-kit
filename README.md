# SoinProduction PHP Kit

Библиотека общих PHP-утилит, платформенных функций и плагинов для проектов SoinProduction.
Используется для переиспользования общего кода между различными WordPress темами.

📖 **[Полная документация по структуре и модулям](doc/README.md)**

## Установка

1. Добавьте репозиторий в ваш `composer.json` и установите пакет:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "git@github.com:soinproduction/soinproduction-php-kit.git"
        }
    ],
    "require": {
        "soinproduction/php-kit": "dev-main"
    }
}
```

2. Выполните команду `composer update`.

## Использование (Подключение модулей)

Пакет использует систему **Opt-In** — по умолчанию ничего не загружается. Вы сами указываете, какие функции платформы, ACF-типы и модули вам нужны в конкретном проекте. Пакет сам знает, какие из его файлов не нужно грузить на фронтенде (для оптимизации производительности), поэтому вам об этом думать не нужно.

Чтобы сохранить модуль в конфигурации, но отключить его, добавьте `_` перед именем. Например, `sp-share` будет загружен, а `_sp-share` — нет. Правило одинаково работает в списках `platform`, `acf` и `plugins`.

### Интеграция в тему

Скопируйте файл `kit.example.php` из корня этого репозитория в корень вашей темы, переименуйте его в `kit.php` и отредактируйте массивы `$platform` и `$plugins`, оставив только нужное.

В файле `functions.php` вашей темы подключите `kit.php` **между** подключением автозагрузчика Composer и запуском ядра вашей темы (чтобы плагины кита имели доступ к хелперам темы, а CPT темы имели доступ к платформе кита):

```php
// 1. Подключаем автозагрузчик
require_once THEME_DIR . '/vendor/autoload.php';

// 2. Подключаем конфиг кита (платформа и ACF-типы -> тема -> модули)
require_once THEME_DIR . '/kit.php';

// ... далее идут настройки темы (theme_setup и прочее)
```

### Файл kit.php

Ваш файл `kit.php` должен выглядеть примерно так (оставляйте в списках только то, что используется на проекте):

```php
<?php
declare(strict_types=1);

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
	'smart-relationship',
	'_smart-taxonomy',
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

// 1. Загружаем платформу и ACF-типы из кита ДО регистрации групп полей темы.
if (class_exists(\SoinProduction\Kit\Bootstrapper::class)) {
	\SoinProduction\Kit\Bootstrapper::run([
		'platform' => $platform,
		'acf'      => $acf,
	]);
}

// 2. Грузим ACF-группы, CPT и хелперы конкретной темы.
// Замените эти пути на актуальные для вашей архитектуры:
require_once THEME_DIR . '/acf/index.php';
require_once THEME_DIR . '/core/bootstrap.php';

// 3. Загружаем плагины из кита (ПОСЛЕ загрузки ядра темы)
if (class_exists(\SoinProduction\Kit\Bootstrapper::class)) {
	\SoinProduction\Kit\Bootstrapper::run(['plugins' => $plugins]);
}
```
