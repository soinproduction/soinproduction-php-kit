# SP Redirect Manager

Управляет exact-path redirects внутри WordPress и импортирует migration maps из CSV, TSV или TXT.

## Как Работает Сопоставление

Модуль выполняется рано на `template_redirect`. Нормализованный request path сравнивается с активными source paths, после чего посетитель перенаправляется на destination с выбранным HTTP status.

Правила хранятся в option `sp_redirects_rules` и содержат source, destination, status и active state. Точное сопоставление исключает неожиданное wildcard-поведение.

## Использование

Откройте **Settings → SP Redirects**:

1. Добавьте правило.
2. Укажите source path, например `/old-url/`.
3. Укажите destination path или безопасный URL.
4. Выберите redirect type и active state.
5. Сохраните redirects.

## Формат Импорта

Загрузите `.csv`, `.tsv` или `.txt` с колонками `OLD`, `NEW`, `STATUS`. Header row необязателен. **Replace existing rules** полностью перезаписывает карту; без него импорт объединяется с текущими правилами.

## Безопасность

Не перенаправляйте URL на самого себя и не создавайте chains/loops. Используйте `301` только для постоянной миграции; во время теста выбирайте временный код. После изменений очистите upstream/CDN cache.

## Runtime Lifecycle

`SP_Redirects::init()` регистрирует runtime на каждом request. `maybe_redirect()` выполняется на `template_redirect` с priority `1`, до обычного template rendering. Он читает `sp_redirects_rules`, пропускает выключенные/неполные строки, нормализует текущий path и сравнивает его с каждым source. Срабатывает первое точное совпадение.

Destination нормализуется при sanitization settings. Внутренние paths остаются site-relative; абсолютные `http`/`https` URL сохраняются только после WordPress URL sanitization. Redirect выполняется через `wp_safe_redirect()` с сохранённым status.

## Schema Правила и Validation

Option содержит массив строк:

| Key | Type | Назначение |
| --- | --- | --- |
| `old` | string | Exact source path с нормализованным leading slash. |
| `new` | string | Внутренний path или безопасный absolute destination. |
| `status` | integer | Допустимый code: `301`, `302`, `307` или `308`. |
| `enabled` | boolean/integer | Участвует ли строка в runtime matching. |

Пустые строки и неподдерживаемые codes удаляются либо заменяются default. Не создавайте duplicate sources: реально работает только первое совпадение.

## Admin и Import Flow

Страница требует `manage_options`. Сохранение защищено стандартным nonce Settings API. Import идёт через `admin-post.php?action=sp_redirects_import`: проверяет nonce/capability, extension файла, delimiter, sanitizе каждую строку и возвращает notice.

Поведение import:

- CSV разбирается с учётом запятых, TSV — tabs, TXT допускает определённый tab/comma separator.
- Порядок колонок: `OLD`, `NEW`, `STATUS`.
- Распознанный header пропускается; иначе первая строка считается data.
- Без status используется `301`.
- **Replace existing** оставляет только import; merge mode добавляет строки к текущему option и sanitizе весь результат.

## Deployment Checklist

1. Сохраните текущую redirect map.
2. Тестируйте новые правила с `302`/`307` и отключённым browser cache.
3. Проверьте query strings: matching выполняется по path, а destination определяет их сохранение.
4. Найдите возможный competing redirect в canonical/SEO plugins.
5. Меняйте code на `301`/`308` только после проверки.
6. Очистите page cache/CDN и протестируйте logged-out.

## Troubleshooting

- **Правило не срабатывает:** проверьте active state, leading `/` и точное совпадение с учётом trailing slash.
- **Redirect loop:** сравните нормализованный source с финальным destination после WordPress canonical redirects.
- **Срабатывает не та строка:** удалите duplicate `old` или измените порядок.
- **Import пустой:** проверьте delimiter, UTF-8, upload limits и первые три колонки.
- **Старый status кешируется:** очистите browser, WordPress cache, reverse proxy и CDN.
