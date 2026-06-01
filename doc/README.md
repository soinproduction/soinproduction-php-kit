# Документация SoinProduction PHP Kit

В этом разделе описана структура и назначение всех файлов и модулей, входящих в состав пакета.

## Корневые файлы
- **[kit.example.php](../kit.example.php)** — шаблон конфигурационного файла. Вы должны скопировать его в корень вашей темы (с именем `kit.php`), чтобы управлять тем, какие модули загружать.
- **[composer.json](../composer.json)** — файл конфигурации Composer для установки кита как зависимости.
- **[README.md](../README.md)** — основная информация по установке и подключению пакета.

## Ядро (`src/`)
- **[Bootstrapper.php](../src/Bootstrapper.php)** — класс для загрузки выбранных модулей платформы и плагинов в правильном порядке. Следит за тем, чтобы некоторые тяжелые модули не грузились на фронтенде без необходимости.
- **[ExampleHelper.php](../src/ExampleHelper.php)** — пример хелпера, демонстрирующего автозагрузку классов.

## Платформа (`platform/`)
Платформенные модули загружаются до инициализации ядра темы (то есть до загрузки CPT и прочего).
- **[author-meta.php](../platform/author-meta.php)** — универсальный метабокс для авторов. Позволяет прикреплять автора с фото и именем к любому кастомному типу записи (CPT).
- **[dev-user.php](../platform/dev-user.php)** — создание роли "Content Admin" (ограниченный администратор) с запретом на установку и удаление плагинов, тем и т.д.
- **[reading-time.php](../platform/reading-time.php)** — функция `sp_reading_time()` для подсчета примерного времени чтения текста.
- **[remove-post-slug.php](../platform/remove-post-slug.php)** — удаление слага кастомного типа записи из URL (настраивается через опции).
- **[reset.php](../platform/reset.php)** — базовая оптимизация и очистка WordPress (отключение XML-RPC, разрешение безопасных протоколов и т.д.).

## Плагины (`plugins/`)
Плагины загружаются после инициализации темы, чтобы иметь доступ к ее функциям.

- **[sp-allow-svg-upload](../plugins/sp-allow-svg-upload/index.php)** — разрешает загрузку SVG файлов администраторам.
- **[sp-cf7-mail-viewer](../plugins/sp-cf7-mail-viewer/index.php)** — логгер отправленных писем из Contact Form 7. Сохраняет до 300 последних писем и позволяет просматривать их в админке.
- **[sp-content-manager](../plugins/sp-content-manager/index.php)** — (SP Content Manager) дублирование записей/страниц/CPT и изменение порядка (drag-and-drop) для записей, таксономий и пунктов меню админки.
- **[sp-cpt-archives](../plugins/sp-cpt-archives/index.php)** — управление страницами архивов CPT (создание фейковых страниц для вывода архивов).
- **[sp-dev-mode](../plugins/sp-dev-mode/index.php)** — панель отладки на фронтенде, отображающая потребление памяти, количество запросов к БД и время генерации страницы.
- **[sp-favorite-posts](../plugins/sp-favorite-posts/index.php)** — (SP Favorite Posts) добавляет колонку "Избранное" в список записей и хелперы для вывода на фронтенде.
- **[sp-flowchimp](../plugins/sp-flowchimp/index.php)** — базовая интеграция с Mailchimp.
- **[sp-google-reviews](../plugins/sp-google-reviews/index.php)** — (SP Google Reviews) автоматический импорт отзывов из Google Maps через SerpAPI в специальный CPT "Отзывы".
- **[sp-redirects](../plugins/sp-redirects/index.php)** — система управления 301 редиректами с удобной панелью управления в админке.
- **[sp-share](../plugins/sp-share/index.php)** — (SP Social Share) настраиваемые кнопки "Поделиться" в соцсетях.
- **[sp-tag-manager](../plugins/sp-tag-manager/index.php)** — интерфейс для добавления кодов Google Tag Manager (GTM) в `head` и `body`.
- **[sp-uploads-webp-convert](../plugins/sp-uploads-webp-convert/index.php)** — автоматическая конвертация загружаемых изображений в формат WebP для оптимизации скорости.
- **[sp-video-preview](../plugins/sp-video-preview/index.php)** — генерация превью-изображений для загружаемых видеофайлов.
