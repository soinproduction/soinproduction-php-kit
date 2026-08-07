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
- **[author-meta.php](../platform/author-meta.php)** — универсальный метабокс для авторов. Позволяет прикреплять автора с фото и именем к любому кастомному типу записи (CPT).
- **[branding.php](../platform/branding.php)** — общие настройки брендинга WordPress и административного интерфейса.
- **[dev-user.php](../platform/dev-user.php)** — создание роли "Content Admin" (ограниченный администратор) с запретом на установку и удаление плагинов, тем и т.д.
- **[duplicator-key.php](../platform/duplicator-key.php)** — поддержка лицензионного ключа Duplicator в конфигурации проекта.
- **[page-loader-settings.php](../platform/page-loader-settings.php)** — настройки page loader и frontend-анимаций.
- **[post-type-converter.php](../platform/post-type-converter.php)** — административное преобразование записей между поддерживаемыми типами.
- **[reading-time.php](../platform/reading-time.php)** — функция `sp_reading_time()` для подсчета примерного времени чтения текста.
- **[remove-post-slug.php](../platform/remove-post-slug.php)** — удаление слага кастомного типа записи из URL (настраивается через опции).
- **[reset.php](../platform/reset.php)** — базовая оптимизация и очистка WordPress (отключение XML-RPC, разрешение безопасных протоколов и т.д.).

## ACF (`acf/`)
ACF-типы и общие ACF-хелперы загружаются до регистрации групп полей конкретной темы.

- **[archive-builder](../acf/archive-builder/index.php)** — общий runtime и ACF-поле для архивов с фильтрами, сортировкой и AJAX.
- **[icon-link-list](../acf/icon-link-list/index.php)** — сортируемый список ссылок с иконками.
- **[related-posts](../acf/related-posts/index.php)** — поле выбора связанных записей и хелперы для вывода.
- **[smart-relationship](../acf/smart-relationship/index.php)** — расширенное relationship-поле ACF.
- **[smart-taxonomy](../acf/smart-taxonomy/index.php)** — расширенное taxonomy-поле ACF.
- **[table](../acf/table/index.php)** — редактируемая таблица и хелперы рендера.
- **[taxonomy-urls](../acf/taxonomy-urls/index.php)** — поле для управления URL таксономий.
- **[universal-media](../acf/universal-media/index.php)** — единое поле медиафайлов и внешнего видео.

## Модули (`plugins/`)
Плагины загружаются после инициализации темы, чтобы иметь доступ к ее функциям.

- **[sp-allow-svg-upload](../plugins/sp-allow-svg-upload/index.php)** — разрешает загрузку SVG файлов администраторам.
- **[sp-accelerator](../plugins/sp-accelerator/index.php)** — page cache, оптимизация assets, markup и object-cache интеграция.
- **[sp-cf7](../plugins/sp-cf7/README.ru.md)** — единая структурированная коллекция CF7-интеграций с отдельной документацией каждого подмодуля.
- **[sp-content-manager](../plugins/sp-content-manager/index.php)** — (SP Content Manager) дублирование записей/страниц/CPT и изменение порядка (drag-and-drop) для записей, таксономий и пунктов меню админки.
- **[sp-cpt-archives](../plugins/sp-cpt-archives/index.php)** — управление страницами архивов CPT (создание фейковых страниц для вывода архивов).
- **[sp-dev-mode](../plugins/sp-dev-mode/index.php)** — панель отладки на фронтенде, отображающая потребление памяти, количество запросов к БД и время генерации страницы.
- **[sp-favorite-posts](../plugins/sp-favorite-posts/index.php)** — (SP Favorite Posts) добавляет колонку "Избранное" в список записей и хелперы для вывода на фронтенде.
- **[sp-google-reviews](../plugins/sp-google-reviews/index.php)** — импорт и администрирование отзывов Google.
- **[sp-redirects](../plugins/sp-redirects/index.php)** — система управления 301-редиректами с панелью управления в админке.
- **[sp-share](../plugins/sp-share/index.php)** — (SP Social Share) настраиваемые кнопки "Поделиться" в соцсетях.
- **[sp-tag-manager](../plugins/sp-tag-manager/index.php)** — интерфейс для добавления кодов Google Tag Manager (GTM) в `head` и `body`.
- **[sp-uploads-webp-convert](../plugins/sp-uploads-webp-convert/index.php)** — автоматическая конвертация загружаемых изображений в формат WebP для оптимизации скорости.
- **[sp-video-preview](../plugins/sp-video-preview/index.php)** — генерация превью-изображений для загружаемых видеофайлов.
- **[sp-wiki](../plugins/sp-wiki/index.php)** — встроенная Wiki для документации темы и подключённых модулей кита.
