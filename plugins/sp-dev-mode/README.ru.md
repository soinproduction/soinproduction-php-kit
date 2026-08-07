# Development Mode Panel

Фронтенд-панель диагностики для администраторов, работающих с темой. Она собирает сведения о запросе, шаблоне, WordPress Query, ACF/meta, ресурсах, медиа и окружении, которые иначе пришлось бы искать в нескольких инструментах.

## Точные условия запуска

Панель подключена к `wp_footer` с приоритетом `9999`, но выводится только когда одновременно выполнено следующее:

1. PHP-константа `DEV_MODE` определена и равна true;
2. посетитель авторизован;
3. у пользователя есть capability `manage_options`.

Одного `WP_DEBUG` недостаточно, а анонимный посетитель не получает markup панели. Активный шаблон обязан вызвать `wp_footer()`.

```php
define('DEV_MODE', true);
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Не держите `DEV_MODE` включённым на публичном production. Capability защищает от гостей, но администратор всё ещё может раскрыть чувствительные пути и параметры через screenshot или JSON export.

## Жизненный цикл сбора данных

Код работает поздно, когда main query уже определён, а большинство ресурсов зарегистрировано или выведено. Он читает `$template`, `$wp_query`, `$wp_scripts`, `$wp_styles`, `$wpdb`, текущую запись и пользователя, request/server data, asset queues и metadata локальных файлов.

Внешние ресурсы не скачиваются. URL локального файла преобразуется в путь только для host текущего сайта и относительно `ABSPATH`. Поэтому байтовые размеры — полезная оценка, но не замена браузерному Network waterfall.

## Разделы панели

### Request и Template

- имя выбранного PHP template;
- URL, HTTP method и условные функции WordPress, вернувшие true;
- queried object, post ID/type/status, parent и author;
- found posts и max pages main query;
- login и роли текущего пользователя;
- page-template slug и контекст запроса.

Это самый быстрый способ проверить, действительно ли WordPress выбрал ожидаемый singular/archive template.

### WordPress, PHP и Database

- версии WordPress/PHP, environment constants и memory limits;
- request timer и оценка query time;
- доступная информация базы и запросов текущего request;
- состояния `DEV_MODE` и `WP_DEBUG`;
- server/runtime факты для сравнения local и staging.

Значения относятся только к текущему запросу. Это не исторический мониторинг; сравнивая cached и uncached страницу, обязательно учитывайте состояние кеша.

### Scripts и Styles

Для зарегистрированных/enqueued JS и CSS панель показывает handle, URL, зависимости, version/media, local/external и размер локального файла. Также собираются inline before/after/data блоки; большие inline payload сортируются по размеру.

Сводка third-party группирует внешние host и число ресурсов. Большое значение указывает, куда смотреть при проблемах соединения, privacy или Core Web Vitals, но не доказывает фактическую загрузку каждого ресурса браузером.

### Images, Fonts и CSS Backgrounds

- `<img>` из content текущей записи и featured image;
- dimensions, alt, URL, размер файла и внешний host;
- font URL и background-image URL, извлечённые best-effort из доступных локальных CSS;
- общий размер локальных шрифтов и предупреждения о тяжёлых изображениях.

Это намеренно не полный DOM audit: динамически вставленные элементы и произвольный template HTML могут отсутствовать. Финальное состояние проверяется в DevTools.

### ACF и Meta

Если ACF доступен, панель читает поля текущего объекта и связанный post/meta context. Это помогает отличить пустое поле от просмотра неправильной записи или шаблона. Перед экспортом проверяйте крупные и приватные значения.

### Performance Hints

Предупреждения строятся эвристически по числу requests, third-party domains, размерам assets, изображениям, шрифтам и inline code. Это направления для расследования, а не Lighthouse score: панель не измеряет layout, main-thread time или реальные пользовательские метрики.

## JSON Snapshot

Кнопка **JSON** формирует в браузере `wp-debug-{timestamp}.json`. В файл входят идентичность страницы/request, template, диагностический контекст, counts и сводные данные ресурсов, медиа и performance на момент рендера.

Перед отправкой файла проверьте:

- локальные filesystem paths и hostnames;
- request URL и query parameters;
- login/roles пользователя;
- ACF/meta и environment values;
- адреса сторонних сервисов.

Snapshot — материал для поддержки, не backup и не автоматический bug report.

## Состояние интерфейса

Выбранная вкладка, minimized/closed state и перетащенная позиция сохраняются в `localStorage`. Cookie `sp_dbg_closed` с SameSite=Lax и сроком год позволяет PHP на следующих запросах вывести только компактный launcher. Закрытие интерфейса не отключает модуль глобально.

Launcher перезагружает текущий URL с `sp_dbg_open=1`, временно игнорируя closed cookie. Tabs и кнопки поддерживают keyboard interaction, окно можно перетаскивать мышью.

Чтобы сбросить UI, удалите local storage сайта и cookie `sp_dbg_closed`, затем перезагрузите страницу.

## Влияние на производительность

Модуль делает filesystem lookup, сканирует asset queues, парсит content и доступные CSS, генерирует большой HTML/CSS/JS overlay. Следовательно, он изменяет измеряемый запрос. Используйте его для диагностики и относительного сравнения, не для production benchmark.

Правильная последовательность:

1. найти конфигурационные проблемы с панелью;
2. выключить `DEV_MODE`;
3. открыть чистую logged-out сессию;
4. измерить страницу с реальными cache/CDN settings.

## Диагностика

- **Панели нет:** проверьте `DEV_MODE === true`, авторизацию, `manage_options` и вызов `wp_footer()`.
- **Виден только launcher:** активен `sp_dbg_closed=1`; нажмите кнопку либо один раз добавьте `sp_dbg_open=1`.
- **Показан неверный template:** очистите кеши и убедитесь, что ответ не отдан до WordPress.
- **Нет размера asset:** URL внешний, находится вне `ABSPATH`, преобразован CDN либо файл отсутствует.
- **Не все images:** dynamic/template-level изображения не входят в content + featured scan.
- **Не все fonts/backgrounds:** анализируются только читаемые CSS и распознаваемые расширения в `url(...)`.
- **Панель мешает layout:** это fixed overlay с высоким z-index; сверните её или выключите `DEV_MODE` перед visual QA.
- **JSON выглядит устаревшим:** перезагрузите именно нужное состояние страницы; snapshot строится из данных конкретного response.
