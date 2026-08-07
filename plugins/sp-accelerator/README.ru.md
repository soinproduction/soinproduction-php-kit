# SP Accelerator v2

SP Accelerator — встроенный в тему Targetized слой производительности WordPress. Версия 2 объединяет безопасный кеш анонимных страниц, раннюю выдачу кеша, ограниченный stale-while-revalidate, проверяемый прогрев, опциональный SQLite object cache и оптимизацию загрузки frontend-ресурсов с учётом архитектуры темы.

Это независимая clean-room реализация: в ней нет кода Seraphinite, телеметрии, проверки лицензии, облачной оптимизации и внешнего API.

## Честная цель производительности

Критерий приёмки — **Lighthouse ≥90 по медиане повторных запусков на согласованных прогретых контрольных страницах** и прохождение полевых Core Web Vitals. Модуль WordPress не может гарантировать, что любая страница при любом запуске всегда будет в зелёной зоне. На результат также влияют TTFB хостинга, шаблон и контент страницы, сторонние скрипты, consent-система, трафик, устройство, сеть и разброс лабораторных замеров.

Lighthouse используйте как воспроизводимый сигнал регрессии. Пользовательский результат оценивайте по полевым Core Web Vitals из Chrome UX Report, Search Console или сопоставимого RUM. Холодные и прогретые запросы фиксируйте отдельно.

## Компоненты

| Компонент | Ответственность |
| --- | --- |
| `SP_Accelerator_Config` | Очищает настройки, управляет текущим/предыдущим поколениями, пишет JSON раннего drop-in и защищает каталог кеша. |
| `SP_Accelerator_Request` | Проверяет транспорт, host, path, query, cookies и состояние WordPress. |
| `SP_Accelerator_Cache` | Перехватывает кешируемый HTML, атомарно пишет записи, отдаёт runtime `HIT`/`STALE` и координирует ревалидацию. |
| `SP_Accelerator_Dropin` | Устанавливает и контролирует `wp-content/advanced-cache.php` для выдачи до bootstrap WordPress. |
| `SP_Accelerator_Warmer` | Использует привязанную к поколению очередь WP-Cron, короткоживущие URL-bound HMAC, не следует redirects и проверяет HTTP 200 плюс `X-SP-Cache: MISS`. |
| `SP_Accelerator_Object_Cache` | Устанавливает и обслуживает защищённый SQLite-backed `wp-content/object-cache.php`. |
| `SP_Accelerator_Server` | Явно устанавливает/удаляет собственный marker `SP Accelerator` в корневом `.htaccess` для кеширования static assets и compression на Apache/LiteSpeed. |
| `SP_Accelerator_Assets` | Управляет preload шрифтов/скриптов, async CSS, resource hints и отложенными скриптами темы. |
| `SP_Accelerator_Markup` | Через HTML-токенизатор WordPress оптимизирует LCP/media-атрибуты, размеры изображений, асинхронное декодирование IMG, lazy iframe и preload видео. |
| `SP_Accelerator_Admin` | Показывает настройки и защищённые nonce служебные операции в **Настройки → Accelerator**. |

Основная опция — `sp_accelerator_settings`. Ранний drop-in получает только очищенную runtime-конфигурацию через `config.json` в вычисленном cache root (по умолчанию `wp-content/cache/sp-accelerator`).

## Безопасная политика page cache

### До формирования ответа WordPress

В page cache допускаются только анонимные `GET` и `HEAD`. Runtime и ранний drop-in исключают:

- заголовок `Authorization` и PHP-auth credentials;
- range-запросы;
- request-заголовки `Cache-Control: no-cache`, `no-store`, `max-age=0` и `Pragma: no-cache`;
- preview/customizer-параметры, Ajax, cron, REST, feeds, поиск, 404, password-protected контент и `DONOTCACHEPAGE`;
- host, который не совпадает точно с host и портом `home_url()` или `site_url()`;
- пути, похожие на статические файлы, а также настроенные path-prefix и regex-исключения;
- настроенные маркеры приватных cookie: WordPress login/security/password/comment, WooCommerce cart/session, Wordfence и типичные multilingual cookies;
- по умолчанию любую другую неизвестную cookie, если её имя не начинается с явно разрешённого безопасного analytics-prefix;
- query-параметры, кроме известных маркетинговых `utm_*`, `gclid`, `fbclid`, `msclkid`, `_ga`, `mc_cid`, `mc_eid`.

Маркетинговые параметры не участвуют в идентификаторе кеша, но не могут отравить каноническую запись: URL с query string может использовать уже созданный чистый кеш, однако никогда не создаёт его.

Строгий bypass неизвестных cookies включён по умолчанию. Начальный список safe prefixes содержит только распространённые analytics identifiers: `_ga`, `_gid`, `_gat`, `_gac_`, `_gcl_`, `_fbp`, `_clck`, `_clsk`. Это prefix match, а не точное совпадение имени. Добавляйте prefix, только если cookie гарантированно не меняет server-rendered HTML; никогда не разрешайте login, cart, currency, pricing, location, experiment или language cookies.

### Перед сохранением

Ответ должен иметь HTTP 200, содержать минимум 256 байт правдоподобного HTML и закрывающий `</html>`. Не сохраняются ответы, отправляющие:

- `Set-Cookie`;
- `Cache-Control: private`, `no-cache` или `no-store`;
- `Pragma: no-cache`;
- content type, отличный от HTML;
- `Vary` по чему-либо, кроме `Accept-Encoding`.

Выбранные security/representation headers сохраняются в metadata и воспроизводятся на cache hit: CSP, content language, cross-origin policies, `Link`, permissions/referrer policy, HSTS, защита frame/content-type и robots. Если HTML похож на содержащий WordPress nonce, его суммарное fresh+stale время автоматически ограничивается с учётом `nonce_life`.

## Файлы кеша и жизненный цикл ответа

Канонические scheme, допустимый host и path хешируются SHA-256 и шардируются внутри активного поколения:

```text
wp-content/cache/sp-accelerator/pages/{generation}/{hash[0:2]}/{hash[2:4]}/
  {hash}.html
  {hash}.html.gz
  {hash}.json
  {hash}.lock
  {hash}.write-lock
```

HTML, опциональный GZIP и metadata публикуются атомарно под per-entry write lock. Metadata содержит канонический URL, время создания, размер, SHA-256 identity содержимого, эффективные TTL, content type и безопасные response headers. ETag включает поколение и identity содержимого, а GZIP/identity варианты остаются разными; поддерживаются `HEAD`, `If-None-Match`, `If-Modified-Since`, `Last-Modified`, `Age` и `Vary: Accept-Encoding`. Значение `gzip;q=0` соблюдается.

Состояние видно по заголовку:

- `X-SP-Cache: MISS` — WordPress сформировал и сохранил ответ;
- `X-SP-Cache: HIT` — отдана свежая запись;
- `X-SP-Cache: STALE` — во время координированной ревалидации отдана допустимая устаревшая запись.

По умолчанию v2 использует один час свежести, ещё шесть часов stale и один час grace для предыдущего поколения. Browser cache настраивается отдельно и по умолчанию требует ревалидации. Меняйте эти значения только после проверки nonce lifetime, требований к свежести, трафика и поведения внешнего кеша.

## SWR текущего и предыдущего поколений

Полная очистка выполняется за O(1): создаётся новое текущее поколение, старое текущее становится предыдущим, фиксируется время переключения. Посетитель не видит наполовину удалённое дерево.

Если в текущем поколении записи ещё нет, запись предыдущего поколения допустима только пока одновременно действуют generation grace и собственный hard limit этой записи. Hard limit равен сохранённым fresh TTL плюс stale TTL; generation grace его никогда не продлевает.

Owner-token lock со сроком 120 секунд выбирает один запрос. Этот запрос всегда проходит обычный безопасный синхронный render и сохранение WordPress. Пока он выполняется, параллельные запросы могут получить согласованную stale-запись текущего или допустимого предыдущего поколения вместе с сохранёнными headers, но только внутри её fresh+stale hard limit.

При cold miss конкурент ждёт до пяти секунд, пока выбранный запрос опубликует текущую запись. Для hard-expired запроса действует такое же collapse wait, однако просроченная запись никогда не выдаётся как резерв при занятости: если допустимая замена не появилась, WordPress формирует ответ обычным способом. Снять lock может только владелец совпадающего token.

Плановая очистка удаляет старые поколения после завершения полезного grace-окна.

## Ранний `advanced-cache.php`

Runtime cache работает на `template_redirect`, но к этому моменту WordPress уже загружен. Управляемый `advanced-cache.php` читает небольшой JSON и статическую запись до bootstrap WordPress — это предпочтительный путь для низкого TTFB на прогретой странице.

Drop-in повторяет transport policy, поиск в текущем/предыдущем поколениях, metadata/header replay, GZIP negotiation, conditional requests и блокировки runtime-слоя. Установщик проверяет владельца и не перезаписывает чужой `advanced-cache.php`.

WordPress загружает drop-in, только если в `wp-config.php` до строки «stop editing» указано:

```php
define( 'WP_CACHE', true );
```

Модуль намеренно не редактирует `wp-config.php` автоматически.

## Проверяемый ручной и автоматический прогрев

Ручная команда **Warm site** переключает поколение, находит same-host URL, сохраняет состояние в `sp_accelerator_warm_state` и сразу обрабатывает один URL. При включённом автоматическом прогреве очистка планирует discovery и дальнейшие запросы через WP-Cron. Короткая option lock не позволяет нескольким cron workers одновременно разобрать одну очередь.

В discovery входят:

- главная и опубликованный singular-контент, кроме attachments;
- публичные архивы post types и ограниченная пагинация;
- страница записей либо posts archive на главной;
- непустые термины публичных taxonomy и ограниченная пагинация;
- дополнительные URL фильтра `sp_accelerator_warm_urls`.

Дедуплицированная same-host очередь ограничена 10 000 URL, причём budget применяется уже во время запросов posts/terms и пагинации, а не после построения потенциально огромного промежуточного массива. Небольшие пакеты планируются примерно каждые пять секунд. Очередь привязана к поколению: при новой инвалидации topology URL открывается заново.

Каждый loopback-запрос отправляет в `X-SP-Cache-Warm` timestamp и HMAC, производный от защищённого `warm_token`. HMAC связан с точным каноническим URL и активным поколением, действует пять минут и проверяется через `hash_equals` ранним drop-in и runtime-слоем. Истёкший token, token от другого URL/поколения и поддельный или статический header идут по обычному cache path и не могут принудительно запустить дорогой render WordPress.

Следование redirects отключено (`redirection: 0`). URL засчитывается только при точном HTTP 200 и `X-SP-Cache: MISS`; redirects, `HIT`, `STALE`, другие статусы, loopback errors и ответы, не попавшие в page cache, остаются failures, а не превращаются в ложный успех.

При переключении темы SP Accelerator сначала сохраняет persistent runtime-disable, а уже затем отключает раннюю config и очищает warmer/page entries. Runtime feature и response-storage checks видят этот флаг, поэтому уже выполняющийся output callback не может заново записать entry после удаления. Cancellation также меняет epoch прогревателя, и worker со старым epoch не может сохранить progress или запланировать следующий batch.

Дополнительные проектные URL:

```php
add_filter( 'sp_accelerator_warm_urls', function ( array $urls ): array {
    $urls[] = home_url( '/landing-page/' );
    return $urls;
} );
```

Для завершения фоновой очереди должны работать WP-Cron и loopback HTTP.

## Безопасность page-cache storage

Page и object cache roots сначала проходят обязательную проверку отдельного каталога: нормализованный путь должен быть абсолютным, не содержать сегментов `.`/`..`, а basename — включать отделённое имя `sp-accelerator`, например `sp-accelerator-cache`. Cache root не может совпадать с `ABSPATH`, `WP_CONTENT_DIR`, фактическим document root или системным temporary root и не может быть широким родительским каталогом для них. Assertion `*_WEB_PROTECTED` не делает такой широкий путь допустимым.

Page-cache persistence требует положительного доказательства на любом сервере. Нормализованный storage path должен находиться за пределами фактического document root и проверяемых модулем WordPress roots, либо `SP_ACCELERATOR_CACHE_WEB_PROTECTED` должен явно подтверждать независимо проверенный server deny. Без этого assertion отсутствующий, некорректный или неоднозначный фактический document root переводит page cache в fail-closed.

Предпочтительно использовать один writable private-каталог за пределами всех доступных из web roots. По умолчанию он станет storage и для page cache, и для object cache:

```php
define( 'SP_ACCELERATOR_CACHE_DIR', '/absolute/private/path/sp-accelerator-cache' );
```

В WP-CLI, за reverse proxy, в chroot и необычных hosting layouts значение `$_SERVER['DOCUMENT_ROOT']` может отсутствовать или не совпадать с реальным публичным root. Тогда задайте его явно:

```php
define( 'SP_ACCELERATOR_DOCUMENT_ROOT', '/absolute/path/to/public' );
```

Если storage должен остаться в `/wp-content/cache/sp-accelerator/`, сначала добавьте точный server deny и проверьте прямые запросы к `config.json` и известному cache URL — они должны вернуть 403/404. Только затем можно подтвердить защиту:

```php
define( 'SP_ACCELERATOR_CACHE_WEB_PROTECTED', true );
```

Это security assertion, а не deny rule. Для SQLite в том же каталоге дополнительно требуется собственный независимо проверенный `SP_ACCELERATOR_OBJECT_CACHE_WEB_PROTECTED`.

`X-Forwarded-Proto` по умолчанию не считается доверенным. Предпочтительно настроить proxy в WordPress так, чтобы он выставлял `HTTPS`; задавайте `SP_ACCELERATOR_TRUST_FORWARDED_PROTO` только когда доверенный reverse proxy удаляет клиентский header и устанавливает собственное значение.

## Persistent object cache на SQLite

По умолчанию опциональный управляемый `object-cache.php` хранит постоянные группы WordPress в `wp-content/cache/sp-accelerator/object-cache.sqlite` в WAL mode; описанные выше storage-константы могут перенести database. Поддерживаются expiration, multiple operations, атомарные numeric updates, global/non-persistent groups, runtime/group flush, multisite blog scopes и namespace на основе `WP_CACHE_KEY_SALT` либо идентичности установки.

Менеджер сравнивает hash встроенного шаблона и различает отсутствующий, актуальный, устаревший, недоступный и чужой drop-in. Для установки нужен PHP `sqlite3`; чужой object-cache drop-in не перезаписывается и не удаляется. Права файлов database/WAL/SHM ужесточаются, каталог получает server deny rules.

Object-cache persistence применяет те же проверки dedicated-name, запрет широкого root и positive-proof policy на любом сервере. Его нормализованный каталог должен находиться за пределами фактического document root и проверяемых WordPress roots, либо `SP_ACCELERATOR_OBJECT_CACHE_WEB_PROTECTED` должен подтверждать отдельно проверенный deny. Иначе новая установка отклоняется, а установленный управляемый drop-in оставляет SQLite persistence выключенным.

Предпочтительный вариант — writable private-каталог за пределами всех доступных из web roots. До установки/обновления drop-in задайте абсолютный путь в `wp-config.php`:

```php
define( 'SP_ACCELERATOR_OBJECT_CACHE_DIR', '/absolute/path/outside/web-root/sp-accelerator-cache' );
```

Если storage должен остаться в `/wp-content/cache/sp-accelerator/`, сначала добавьте явный deny для этого URL и проверьте прямым запросом ответ 403/404. Только после подтверждения защиты её можно явно декларировать в `wp-config.php`. Например, для Nginx:

```nginx
location ^~ /wp-content/cache/sp-accelerator/ {
    deny all;
}
```

```php
define( 'SP_ACCELERATOR_OBJECT_CACHE_WEB_PROTECTED', true );
```

`SP_ACCELERATOR_OBJECT_CACHE_WEB_PROTECTED` — security assertion, а не механизм защиты. Если задать константу без проверенного deny, fail-closed guard будет отключён, а SQLite/WAL, metadata и cached page files могут остаться доступны из web.

WordPress загружает `object-cache.php` очень рано, поэтому сначала устанавливайте его на staging и сразу проверяйте frontend и wp-admin.

## Загрузка ресурсов темы

SP Accelerator работает с зарегистрированными handles темы: он не объединяет сгенерированные CSS/JS и не запускает универсальный Critical CSS scraper.

- Main CSS и некритичные `section-*` styles могут передаваться в async CSS pipeline темы. Критические handles, включая hero, остаются синхронными.
- Critical font preload ограничивается `DMSans-Regular.woff2` и `DMSans-Bold.woff2`; Bold соответствует hero heading с весом 700 в critical CSS. Список меняется фильтром `sp_accelerator_preload_fonts`.
- `preconnect`/`dns-prefetch` формируются по внешним origins поставленных в очередь scripts/styles и ограничиваются четырьмя.
- Preload главного script является явной опцией, а не безусловным поведением.
- Подходящие theme module/npm scripts можно отложить. WordPress печатает их в dependency-resolved порядке, loader выполняет placeholders последовательно. Hero module, `async` scripts и handles с inline before/after не откладываются. Дополнительный handle исключается фильтром `sp_accelerator_delay_script`.
- Delayed scripts запускаются при pointer/touch/keyboard/scroll interaction, приближении связанной секции к viewport, в idle или по safety timeout (по умолчанию 12 секунд). После завершения отправляется событие `sp:accelerator:scripts-loaded`.

После изменения этих переключателей обязательно перепроверьте меню, слайдеры, формы, аналитику, consent, навигацию и сторонние widgets.

## Markup, LCP и media

Финальный проход использует `WP_HTML_Tag_Processor` и не меняет regex-ами scripts, styles или текст документа. При включении он:

- находит первое правдоподобное не-icon изображение внутри `<main>`, задаёт `loading="eager"` и `fetchpriority="high"`;
- добавляет image preload с `imagesrcset`/`imagesizes`, если они есть и эквивалентного preload ещё нет;
- добавляет intrinsic dimensions, только когда same-site raster безопасно разрешается внутри WordPress root и достаточно мал для проверки;
- может добавить другим IMG `decoding="async"`, но оставляет их `loading` поведению темы/native browser, задаёт `loading="lazy"` iframe без собственного значения и `preload="none"` non-autoplay video;
- не трогает encoded responses, data URLs, SVG/ICO, содержимое `<noscript>` и неподходящие logo/icon candidates.

Так улучшается обнаружение LCP и уменьшается лишний layout shift без выдуманных размеров и переписывания URL изображений.

## Корневые `.htaccess` rules для static assets

`SP_Accelerator_Server` — отдельная явно управляемая оптимизация для Apache и LiteSpeed. Карточка **Static assets / compression** в **Настройки → Accelerator** не меняет конфигурацию сервера скрытно. Кнопка **Установить правила .htaccess** через WordPress markers добавляет в корневой `.htaccess` сайта только блок `# BEGIN SP Accelerator` / `# END SP Accelerator`; существующие WordPress и чужие directives сохраняются. Удаление убирает только этот собственный marker.

При наличии соответствующих server modules marker задаёт:

- один год `public, immutable` browser cache для CSS, JavaScript/MJS, WASM и font files;
- три месяца public browser cache для AVIF, WebP, JPEG, PNG, GIF, SVG и ICO;
- Brotli compression текстов/HTML/CSS/JavaScript/JSON/XML/SVG через `mod_brotli`;
- GZIP/Deflate через `mod_deflate`, если Brotli недоступен.

Marker не может включить отсутствующий Apache module. Корневой `.htaccess` без прав записи получает статус `readonly` и не изменяется.

**Nginx не читает `.htaccess`.** На Nginx карточка показывает `manual`, установка недоступна, а эквивалентные cache TTL и Brotli/GZIP нужно добавить в конфигурацию Nginx/сервера/CDN. Не путайте автоматически создаваемые deny-файлы cache root с этими опциональными корневыми performance rules.

## Защита хранилища

При синхронизации конфигурации в cache root записываются:

- Apache `.htaccess`, запрещающий прямой доступ и directory listing;
- deny rule для IIS в `web.config`;
- защитный `index.php`, возвращающий 404.

Эти файлы дают defense in depth, но не являются положительным доказательством для PHP. Проверка dedicated-name и запрет широкого root действуют всегда; после неё page cache и object cache остаются выключенными, пока их нормализованные пути не вынесены за пределы фактического document root и проверяемых WordPress roots либо администратор не подтвердит независимо проверенный deny соответствующей page/object `*_WEB_PROTECTED` константой. Небезопасные page payloads в web path удаляются при fail-closed синхронизации; установка object cache отдельно проверяет защиту и ужесточает права SQLite-файлов.

Защита cache root создаётся автоматически и относится к security. Она не зависит от отдельно устанавливаемого performance-marker в корневом `.htaccess`.

## Миграция legacy root и очистка при переключении темы

Когда `SP_ACCELERATOR_CACHE_DIR` переносит page cache из legacy root `wp-content/cache/sp-accelerator`, синхронизация сначала обрабатывает старый root и только потом публикует новую активную config. Положительно идентифицированный старый `config.json` сначала переписывается с `enabled: false`; лишь после этого удаляются его `pages` entries. Если старый root нельзя безопасно идентифицировать, отключить или очистить, синхронизация останавливается, не оставляя два активных root.

Legacy SQLite обрабатывается отдельно от page entries. `object-cache.sqlite` и WAL/SHM/journal sidecars сохраняются, когда старый root находится за пределами подтверждённо доступных из web roots либо имеет явно проверенную защиту. Если legacy root подтверждённо web-exposed, а защита object cache не заявлена, database и sidecars удаляются; неудача удаления переводит миграцию в fail-closed.

При переключении темы SP Accelerator сначала сохраняет runtime-disable, затем отключает раннюю page-cache config, меняет cancellation epoch прогревателя и удаляет все page entries. Response storage повторно проверяет runtime enablement, поэтому in-flight response не может создать entry после cleanup. Затем удаляются только собственный server marker и управляемые drop-ins; ошибки cleanup записываются в log. Проверка epoch не позволяет уже работающему warmer сохранить старый state очереди или запланировать новый batch после отмены.

## Ввод в эксплуатацию

1. Разворачивайте PHP Kit одной версией, зафиксированной Composer lock. Не смешивайте разные релизы пофайловой FTP-загрузкой.
2. Откройте **Настройки → Accelerator**, сохраните настройки и проверьте анонимную страницу без drop-ins.
3. Добавьте `WP_CACHE` в `wp-config.php`. Используйте отдельный абсолютный `SP_ACCELERATOR_CACHE_DIR`, basename которого содержит `sp-accelerator`; никогда не указывайте широкий WordPress, document или system-temp root. Вынесите каталог за фактический document root либо проверьте точный deny и задайте соответствующий assertion. Если server/CLI не сообщает реальный публичный root, определите `SP_ACCELERATOR_DOCUMENT_ROOT`; затем установите/обновите управляемый `advanced-cache.php`.
4. Проверьте последовательность `MISS` → `HIT`, включая security headers и GZIP.
5. На Apache/LiteSpeed через отдельную карточку **Static assets / compression** установите корневой `.htaccess` marker после просмотра существующего файла. На Nginx задайте эквивалентные rules вручную на сервере или CDN.
6. Установите/обновите SQLite object cache только после проверки extension и прав записи. Его отдельный абсолютный путь также должен содержать `sp-accelerator` и не быть широким reserved root. Вынесите каталог за фактический document root либо установите и проверьте явный deny для `/wp-content/cache/sp-accelerator/`, а уже затем определяйте `SP_ACCELERATOR_OBJECT_CACHE_WEB_PROTECTED`.
7. Запустите **Warm site**, изучите failed URLs, затем проведите повторные Lighthouse-тесты согласованных прогретых страниц.
8. После изменения слоёв очистите CDN/reverse-proxy cache.

Установленные drop-ins находятся в `wp-content`, вне темы. Загрузка темы их не обновляет. После deployment модуля снова откройте **Настройки → Accelerator** и обновите карточки со статусом outdated. Никогда не переносите development-каталог `wp-content/cache` на production.

Для быстрой проверки дважды запросите один URL в logged-out режиме:

```bash
curl -I https://example.com/control-page/
curl -I https://example.com/control-page/
```

До Lighthouse проверьте HTML identity, CSP/security headers, logged-in поведение, cart/account routes, формы и nonce, GZIP negotiation с `gzip;q=0`, а также failure list прогрева.

## Диагностика

- **Всегда MISS:** проверьте logged-out режим, authorization, range/no-cache headers, query, private cookies, исключения, HTTP status, `Set-Cookie`, response cache policy, content type и `Vary`.
- **Нет `X-SP-Cache`:** выключен master/page cache, запрос не прошёл policy, путь занят чужим drop-in либо upstream удаляет заголовок.
- **Очередь прогрева не завершается:** проверьте WP-Cron, loopback DNS/TLS и записанные ошибки.
- **Warm URL сообщает «не попала в page cache»:** ищите персонализацию, cookies, non-200, запрещённые response headers или path exclusion.
- **Warm URL вернул redirect, HIT или STALE:** это ожидаемый failure; redirects не переходятся, принимается только HTTP 200 плюс `X-SP-Cache: MISS`. Добавьте в очередь конечный канонический URL и выясните, почему authenticated request не перестроил страницу.
- **Устаревший контент:** очистите кеш, проверьте invalidation hook и внешний CDN/reverse proxy.
- **Drop-in не устанавливается:** проверьте `WP_CACHE`, права `wp-content`, владельца существующего файла и актуальность встроенного шаблона.
- **Безопасность page-cache storage не доказана:** сначала проверьте отдельный абсолютный basename с `sp-accelerator` и убедитесь, что путь не является широким reserved root. Затем проверьте `SP_ACCELERATOR_DOCUMENT_ROOT`; предпочтительно вынесите storage за фактический public root либо проверьте точный deny до определения `SP_ACCELERATOR_CACHE_WEB_PROTECTED`.
- **Static rules показывают `manual`:** сервер работает на Nginx; настройте asset TTL и Brotli/GZIP вне WordPress, потому что `.htaccess` там не действует.
- **Static rules показывают `readonly`:** временно дайте WordPress безопасные права записи либо попросите хостинг установить эту policy; не перезаписывайте корневой файл вслепую.
- **Безопасность object-cache storage не доказана:** persistence намеренно fail-closed. Проверьте те же dedicated-name/broad-root условия и `SP_ACCELERATOR_DOCUMENT_ROOT`; предпочтительно вынесите storage за фактический public root либо проверьте точный deny до определения `SP_ACCELERATOR_OBJECT_CACHE_WEB_PROTECTED`.
- **Object cache влияет на wp-admin:** временно переименуйте только управляемый `wp-content/object-cache.php`, прочитайте PHP log, проверьте SQLite/права и переустановите совпадающий шаблон.
- **Видна вспышка layout либо JS стартует поздно:** верните нужный style/script в critical path или исключите handle доступным фильтром, затем повторите матрицу контрольных страниц.
- **Seraphinite ещё активен:** отключите его перед SP Accelerator; при наличии legacy-константы v2 подавляет собственные feature checks, чтобы не возникла двойная оптимизация.

## Аварийное восстановление

Если сразу после deployment возник critical error:

1. Замените `sp-accelerator` на `_sp-accelerator` в конфигурации PHP Kit проекта.
2. Если нужно, временно переименуйте только управляемые `wp-content/object-cache.php` и `wp-content/advanced-cache.php`.
3. Прочитайте точный fatal в `wp-content/debug.log` или PHP error log хостинга.
4. Загрузите один полный совпадающий релиз, верните имя каталога, затем переустановите/обновите оба drop-in через **Настройки → Accelerator**.

SP Accelerator отказывается от destructive operations над drop-ins, которыми он не владеет.

## Регрессионные тесты

Если PHP Kit находится вне установки WordPress, передайте её корень явно:

```bash
SP_ACCELERATOR_WP_ROOT=/path/to/wordpress php _tests/run.php
```
