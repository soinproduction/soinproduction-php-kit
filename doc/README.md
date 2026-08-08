# Документация SoinProduction PHP Kit

В этом разделе описана структура и назначение всех файлов и модулей, входящих в состав пакета.

## Корневые файлы
- **[kit.example.php](../kit.example.php)** — шаблон конфигурационного файла. Вы должны скопировать его в корень вашей темы (с именем `kit.php`), чтобы управлять тем, какие модули загружать.
- **[composer.json](../composer.json)** — файл конфигурации Composer для установки кита как зависимости.
- **[README.md](../README.md)** — основная информация по установке и подключению пакета.

Во всех конфигурационных списках имя с префиксом `_` считается отключённым: `_sp-share` остаётся видимым в конфигурации, но Bootstrapper не загружает этот модуль.

## Ядро (`src/`)
- **[Bootstrapper.php](../src/Bootstrapper.php)** — класс для загрузки выбранных модулей платформы и плагинов в правильном порядке. Следит за тем, чтобы некоторые тяжелые модули не грузились на фронтенде без необходимости.
- **[ExampleHelper.php](../src/ExampleHelper.php)** — пример хелпера, демонстрирующего автозагрузку классов.

## Платформа (`platform/`)
Платформенные модули загружаются до инициализации ядра темы (то есть до загрузки CPT и прочего).
- **[author-meta](../platform/author-meta/README.ru.md)** — универсальный метабокс автора для выбранных CPT.
- **[branding](../platform/branding/README.ru.md)** — branding экрана входа WordPress.
- **[dev-user](../platform/dev-user/README.ru.md)** — ограниченная роль Content Admin.
- **[duplicator-key](../platform/duplicator-key/README.ru.md)** — приватный compatibility layer Duplicator.
- **[page-loader-settings](../platform/page-loader-settings/README.ru.md)** — настройки page loader и frontend-анимаций.
- **[post-type-converter](../platform/post-type-converter/README.ru.md)** — административное преобразование записей между совместимыми post types.
- **[reading-time](../platform/reading-time/README.ru.md)** — расчёт примерного времени чтения.
- **[remove-post-slug](../platform/remove-post-slug/README.ru.md)** — управление удалением CPT/taxonomy bases из URL.
- **[reset](../platform/reset/README.ru.md)** — baseline-policy оптимизации и очистки WordPress.

## ACF (`acf/`)
ACF-типы и общие ACF-хелперы загружаются до регистрации групп полей конкретной темы.

- **[archive-builder](../acf/archive-builder/README.ru.md)** — общий runtime и ACF-поле для архивов с фильтрами, сортировкой и AJAX.
- **[icon-link-list](../acf/icon-link-list/README.ru.md)** — сортируемый список ссылок с иконками.
- **[related-posts](../acf/related-posts/README.ru.md)** — поле выбора связанных записей и хелперы для вывода.
- **[smart-relationship](../acf/smart-relationship/README.ru.md)** — расширенное relationship-поле ACF.
- **[smart-taxonomy](../acf/smart-taxonomy/README.ru.md)** — расширенное taxonomy-поле ACF.
- **[table](../acf/table/README.ru.md)** — редактируемая таблица и хелперы рендера.
- **[taxonomy-urls](../acf/taxonomy-urls/README.ru.md)** — поле для управления URL таксономий.
- **[universal-media](../acf/universal-media/README.ru.md)** — единое поле медиафайлов и внешнего видео.

## Модули (`plugins/`)
Плагины загружаются после инициализации темы, чтобы иметь доступ к ее функциям.

- **[sp-allow-svg-upload](../plugins/sp-allow-svg-upload/README.ru.md)** — разрешает загрузку SVG файлов администраторам.
- **[sp-accelerator](../plugins/sp-accelerator/README.ru.md)** — page cache, оптимизация assets, markup и object-cache интеграция.
- **[sp-cf7](../plugins/sp-cf7/README.ru.md)** — единая структурированная коллекция CF7-интеграций с отдельной документацией каждого подмодуля.
- **[sp-content-manager](../plugins/sp-content-manager/README.ru.md)** — (SP Content Manager) дублирование записей/страниц/CPT и изменение порядка (drag-and-drop) для записей, таксономий и пунктов меню админки.
- **[sp-cpt-archives](../plugins/sp-cpt-archives/README.ru.md)** — управление страницами архивов CPT (создание фейковых страниц для вывода архивов).
- **[sp-dev-mode](../plugins/sp-dev-mode/README.ru.md)** — панель отладки на фронтенде, отображающая потребление памяти, количество запросов к БД и время генерации страницы.
- **[sp-favorite-posts](../plugins/sp-favorite-posts/README.ru.md)** — (SP Favorite Posts) добавляет колонку "Избранное" в список записей и хелперы для вывода на фронтенде.
- **[sp-google-reviews](../plugins/sp-google-reviews/README.ru.md)** — импорт и администрирование отзывов Google.
- **[sp-redirects](../plugins/sp-redirects/README.ru.md)** — система управления 301-редиректами с панелью управления в админке.
- **[sp-share](../plugins/sp-share/README.ru.md)** — (SP Social Share) настраиваемые кнопки "Поделиться" в соцсетях.
- **[sp-tag-manager](../plugins/sp-tag-manager/README.ru.md)** — интерфейс для добавления кодов Google Tag Manager (GTM) в `head` и `body`.
- **[sp-uploads-webp-convert](../plugins/sp-uploads-webp-convert/README.ru.md)** — автоматическая конвертация загружаемых изображений в формат WebP для оптимизации скорости.
- **[sp-video-preview](../plugins/sp-video-preview/README.ru.md)** — генерация превью-изображений для загружаемых видеофайлов.
- **[sp-wiki](../plugins/sp-wiki/README.ru.md)** — встроенная Wiki для документации темы и подключённых модулей кита.
- **[sp-admin-ui](../plugins/sp-admin-ui/README.ru.md)** — структурированный набор admin UI: menu title, preview thumbnail и taxonomy metaboxes.
