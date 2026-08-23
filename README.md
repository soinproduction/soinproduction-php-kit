# SoinProduction PHP Kit

Библиотека общих PHP-утилит, платформенных функций и плагинов для проектов SoinProduction.
Используется для переиспользования общего кода между различными WordPress темами.

📖 **[Полная документация по структуре и модулям](doc/README.md)**

## Стандарт структуры

Модули в `platform/`, `acf/` и `plugins/` используют один формат: `<module-id>/index.php`, `README.en.md` и `README.ru.md`. Все публичные module IDs и каталоги используют namespace `sp-` и записываются в `kebab-case`. Вложенные модули используют полный namespace родителя, например `sp-cf7-mail-viewer`. Внутренние PHP-компоненты находятся в `includes/`, тесты — в `tests/`.

Классы в `src/` следуют PSR-4 и поэтому сохраняют имена файлов в `PascalCase`.

Старые module IDs поддерживаются Bootstrapper как compatibility aliases. Новые проекты должны использовать только канонические `sp-*` имена из `kit.example.php`.

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

### Единый JSON bootstrap для админки

`SoinProduction\Kit\AdminBootstrap` собирает небольшие данные нескольких admin-компонентов и выводит их один раз перед WordPress scripts. Регистрируйте данные на `admin_enqueue_scripts` только для нужного экрана; тогда bootstrap гарантированно доступен и head-, и footer-скриптам:

```php
use SoinProduction\Kit\AdminBootstrap;

AdminBootstrap::set( 'myFeature', [
	'ajaxUrl' => admin_url( 'admin-ajax.php' ),
	'nonce'   => wp_create_nonce( 'my_feature' ),
] );
```

В браузере данные доступны через `window.SPAdminData.get('myFeature', {})`. Для постепенной миграции старого JavaScript можно временно экспортировать feature или отдельный ключ в глобальную переменную через `AdminBootstrap::exposeLegacyGlobal()`.

Bootstrap предназначен для URL, nonce, capabilities, переводов и небольших screen-specific справочников. Полные каталоги записей, результаты поиска, HTML-превью и изменяемые данные должны оставаться ленивыми REST/AJAX запросами с пагинацией и проверкой прав.

### Единое дублирование данных записи

`SoinProduction\Kit\PostDuplicator::copyAssociatedData( $source_id, $target_id )` копирует meta и обычные taxonomy terms в уже созданную запись того же типа. Для переведённых типов он сохраняет язык через публичный API Polylang или WPML, но создаёт независимую translation group. Это позволяет использовать один контракт в Content Manager, AJAX-инструментах и кастомных редакторах без прямого копирования служебных языковых связей.

### Обновление php-kit из админки

Модуль `sp-deployment-manager` сравнивает установленный Composer reference с выбранной веткой GitHub и обновляет пакет целиком через `composer update soinproduction/php-kit --with-dependencies`. Операция запускается через WordPress Cron, защищена nonce/capabilities и filesystem lock; перед установкой сохраняется `composer.lock`, доступный для отката через интерфейс **Инструменты → Обновления репозитория**. Подробные требования к Composer, `proc_open`, приватным репозиториям и конфигурации описаны в `plugins/sp-deployment-manager/README.ru.md`.

### Библиотека переиспользуемого контента

Модуль `sp-content-library` переносит admin-регистрацию исторических post types `widgets` и `for-editor` в общий пакет и показывает их во **Внешний вид → Reusable Sections / Editor Blocks**. Внутренние slug и ACF field names не меняются. Разрешённые layout поля `blocks` задаются массивом `editor_layouts`; полная конфигурация и требования к callbacks описаны в `plugins/sp-content-library/README.ru.md`.

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
	'_sp-author-meta',
	'sp-login-branding',
	'sp-content-admin',
	'sp-duplicator-license',
	'sp-motion-settings',
	'sp-content-type-converter',
	'sp-reading-time',
	'sp-permalink-manager',
	'sp-wordpress-baseline'
];

$acf = [
	'sp-background-media',
	'sp-post-selector',
	'_sp-term-selector',
];

$plugins = [
	'sp-accelerator',
	'sp-svg-support',
	'sp-admin-ui' => [
		'sp-admin-ui-menu-heading',
		'sp-admin-ui-text-column',
		'sp-admin-ui-thumbnail-column',
		'sp-admin-ui-taxonomy-checklist',
		'sp-admin-ui-taxonomy-radio',
	],
	'sp-cf7' => [
		'sp-cf7-core',
		'sp-cf7-mail-viewer',
		'sp-cf7-mailchimp-sync',
		'sp-cf7-webhook',
		'sp-cf7-redirects',
		'sp-cf7-select-field',
		'sp-cf7-icon-generator',
	],
	'sp-content-manager',
	'sp-content-library' => [
		'editor_layouts' => [
			'author_quote',
			'blockquote',
		],
	],
	'sp-deployment-manager',
	'sp-archive-pages',
	'sp-debug-toolbar',
	'sp-content-favorites',
	'sp-google-reviews',
	'sp-redirect-manager',
	'_sp-share',
	'sp-tag-manager',
	'sp-webp-uploads',
	'sp-video-posters',
	'sp-documentation',
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
