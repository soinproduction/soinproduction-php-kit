# Документация SoinProduction PHP Kit

В этом разделе описана структура и назначение всех файлов и модулей, входящих в состав пакета.

## Корневые файлы
- **[kit.example.php](../kit.example.php)** — шаблон конфигурационного файла. Вы должны скопировать его в корень вашей темы (с именем `kit.php`), чтобы управлять тем, какие модули загружать.
- **[composer.json](../composer.json)** — файл конфигурации Composer для установки кита как зависимости.
- **[README.md](../README.md)** — основная информация по установке и подключению пакета.
- **[module-naming.ru.md](module-naming.ru.md)** — канонические module IDs и compatibility aliases.

Во всех конфигурационных списках имя с префиксом `_` считается отключённым: `_sp-share` остаётся видимым в конфигурации, но Bootstrapper не загружает этот модуль.

## Ядро (`src/`)
- **[Bootstrapper.php](../src/Bootstrapper.php)** — класс для загрузки выбранных модулей платформы и плагинов в правильном порядке. Следит за тем, чтобы некоторые тяжелые модули не грузились на фронтенде без необходимости.
- **[ExampleHelper.php](../src/ExampleHelper.php)** — пример хелпера, демонстрирующего автозагрузку классов.

## Платформа (`platform/`)
Платформенные модули загружаются до инициализации ядра темы (то есть до загрузки CPT и прочего).
- **[sp-author-meta](../platform/sp-author-meta/README.ru.md)** — универсальный метабокс автора для выбранных CPT.
- **[sp-login-branding](../platform/sp-login-branding/README.ru.md)** — branding экрана входа WordPress.
- **[sp-content-admin](../platform/sp-content-admin/README.ru.md)** — ограниченная роль Content Admin.
- **[sp-duplicator-license](../platform/sp-duplicator-license/README.ru.md)** — приватный compatibility layer Duplicator.
- **[sp-motion-settings](../platform/sp-motion-settings/README.ru.md)** — настройки frontend-анимаций.
- **[sp-content-type-converter](../platform/sp-content-type-converter/README.ru.md)** — административное преобразование записей между совместимыми post types.
- **[sp-reading-time](../platform/sp-reading-time/README.ru.md)** — расчёт примерного времени чтения.
- **[sp-permalink-manager](../platform/sp-permalink-manager/README.ru.md)** — управление удалением CPT/taxonomy bases из URL.
- **[sp-wordpress-baseline](../platform/sp-wordpress-baseline/README.ru.md)** — baseline-policy оптимизации и очистки WordPress.

## ACF (`acf/`)
ACF-типы и общие ACF-хелперы загружаются до регистрации групп полей конкретной темы.

- **[sp-archive-builder](../acf/sp-archive-builder/README.ru.md)** — общий runtime и ACF-поле для архивов с фильтрами, сортировкой и AJAX.
- **[sp-background-media](../acf/sp-background-media/README.ru.md)** — адаптивный фон из изображения/видео с позиционированием и overlay.
- **[sp-icon-links](../acf/sp-icon-links/README.ru.md)** — сортируемый список ссылок с иконками.
- **[sp-related-content](../acf/sp-related-content/README.ru.md)** — поле выбора связанных записей и хелперы для вывода.
- **[sp-post-selector](../acf/sp-post-selector/README.ru.md)** — расширенное relationship-поле ACF.
- **[sp-term-selector](../acf/sp-term-selector/README.ru.md)** — расширенное taxonomy-поле ACF.
- **[sp-table](../acf/sp-table/README.ru.md)** — редактируемая таблица и хелперы рендера.
- **[sp-term-links](../acf/sp-term-links/README.ru.md)** — поле для управления URL таксономий.
- **[sp-media](../acf/sp-media/README.ru.md)** — единое поле медиафайлов и внешнего видео.

## Модули (`plugins/`)
Плагины загружаются после инициализации темы, чтобы иметь доступ к ее функциям.

- **[sp-accelerator](../plugins/sp-accelerator/README.ru.md)** — page cache, оптимизация assets, markup и object-cache интеграция.
- **[sp-admin-ui](../plugins/sp-admin-ui/README.ru.md)** — структурированный набор admin UI: menu heading, thumbnail column и taxonomy metaboxes.
- **[sp-archive-pages](../plugins/sp-archive-pages/README.ru.md)** — управление страницами архивов CPT.
- **[sp-cf7](../plugins/sp-cf7/README.ru.md)** — единая структурированная коллекция CF7-интеграций с отдельной документацией каждого подмодуля.
- **[sp-content-favorites](../plugins/sp-content-favorites/README.ru.md)** — favorites settings, metadata/admin controls и REST/filter behavior.
- **[sp-content-manager](../plugins/sp-content-manager/README.ru.md)** — (SP Content Manager) дублирование записей/страниц/CPT и изменение порядка (drag-and-drop) для записей, таксономий и пунктов меню админки.
- **[sp-deployment-manager](../plugins/sp-deployment-manager/README.ru.md)** — безопасная проверка, Composer-обновление и откат php-kit из GitHub прямо в админке WordPress.
- **[sp-debug-toolbar](../plugins/sp-debug-toolbar/README.ru.md)** — панель отладки на фронтенде, отображающая потребление памяти, количество запросов к БД и время генерации страницы.
- **[sp-documentation](../plugins/sp-documentation/README.ru.md)** — встроенная Wiki для документации темы и подключённых модулей кита.
- **[sp-google-reviews](../plugins/sp-google-reviews/README.ru.md)** — импорт и администрирование отзывов Google.
- **[sp-redirect-manager](../plugins/sp-redirect-manager/README.ru.md)** — система управления 301-редиректами с панелью управления в админке.
- **[sp-share](../plugins/sp-share/README.ru.md)** — (SP Social Share) настраиваемые кнопки "Поделиться" в соцсетях.
- **[sp-svg-support](../plugins/sp-svg-support/README.ru.md)** — безопасная загрузка и обработка SVG.
- **[sp-tag-manager](../plugins/sp-tag-manager/README.ru.md)** — интерфейс для добавления кодов Google Tag Manager (GTM) в `head` и `body`.
- **[sp-video-posters](../plugins/sp-video-posters/README.ru.md)** — генерация превью-изображений для загружаемых видеофайлов.
- **[sp-webp-uploads](../plugins/sp-webp-uploads/README.ru.md)** — автоматическая конвертация загружаемых изображений в WebP.
