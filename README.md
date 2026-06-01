# SoinProduction PHP Kit

Библиотека общих PHP-утилит, платформенных функций и плагинов для проектов SoinProduction.
Используется для переиспользования общего кода между различными WordPress темами.

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

Пакет использует систему **Opt-In** — по умолчанию ничего не загружается. Вы сами указываете, какие функции платформы и какие плагины вам нужны в конкретном проекте. Пакет сам знает, какие из его файлов не нужно грузить на фронтенде (для оптимизации производительности), поэтому вам об этом думать не нужно.

### Интеграция в тему

Скопируйте файл `kit.example.php` из корня этого репозитория в корень вашей темы, переименуйте его в `kit.php` и отредактируйте массивы `$platform` и `$plugins`, оставив только нужное.

В файле `functions.php` вашей темы подключите `kit.php` **между** подключением автозагрузчика Composer и запуском ядра вашей темы (чтобы плагины кита имели доступ к хелперам темы, а CPT темы имели доступ к платформе кита):

```php
// 1. Подключаем автозагрузчик
require_once THEME_DIR . '/vendor/autoload.php';

// 2. Подключаем кит (он сам загрузит платформу -> затем вашу тему -> затем плагины)
require_once THEME_DIR . '/kit.php';

// ... далее идут настройки темы (theme_setup и прочее)
```

### Файл kit.php

Ваш файл `kit.php` должен выглядеть примерно так (оставляйте в списках только то, что используется на проекте):

```php
<?php
declare(strict_types=1);

$platform = [
	'author-meta',
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

// 1. Загружаем платформу из кита (ДО загрузки ядра темы)
if (class_exists(\SoinProduction\Kit\Bootstrapper::class)) {
	\SoinProduction\Kit\Bootstrapper::run(['platform' => $platform]);
}

// 2. Грузим ядро темы (здесь загрузятся CPT и хелперы темы)
// Замените эти пути на актуальные для вашей архитектуры:
require_once THEME_DIR . '/acf/index.php';
require_once THEME_DIR . '/core/bootstrap.php';

// 3. Загружаем плагины из кита (ПОСЛЕ загрузки ядра темы)
if (class_exists(\SoinProduction\Kit\Bootstrapper::class)) {
	\SoinProduction\Kit\Bootstrapper::run(['plugins' => $plugins]);
}
```

## Добавление новых модулей

Если вы хотите добавить новый плагин или функцию платформы, просто создайте соответствующий файл/папку в `platform/` или `plugins/` в этом репозитории и запушьте изменения. После этого в любом проекте достаточно сделать `composer update` и добавить название модуля в массив в `kit.php`.
