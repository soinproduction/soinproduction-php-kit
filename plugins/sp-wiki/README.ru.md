# Wiki темы

Динамический browser документации внутри админки для кастомной темы и каждого подключённого модуля.

## Правила Обнаружения

- Главы темы читаются из `docs/en/*.md` или `docs/ru/*.md`.
- Документация модулей обнаруживается рядом с загруженными модулями в каталоге PHP Kit `plugins/*`.
- Модуль показывается только если его папка не начинается с `_`, содержит `index.php` и хотя бы один PHP-файл этой папки загружен в текущем admin request.
- Wiki самостоятельно не активирует и не подключает другие модули.

## Локализация

Язык определяется locale сайта. Локали, начинающиеся с `ru`, используют `README.ru.md` и `docs/ru`; все остальные — `README.en.md` и `docs/en`.

Если локализованного README нет, используется English, а затем legacy `README.md`.

## Добавление Документации Модулю

Положите рядом с `index.php`:

```text
README.en.md
README.ru.md
```

Первый `# Заголовок` станет названием в навигации. Поддерживаются headings, paragraphs, lists, tables, quotes, links и fenced code blocks.

## Модель Безопасности

Страница требует capability `manage_options`. Markdown читается только из директории активной темы, а итоговый HTML проходит WordPress allowlist для post content. В базе данных документация не хранится.

## Request Lifecycle

`SP_Theme_Wiki::init()` регистрирует submenu Settings и page-specific assets. CSS/JavaScript подключаются только для screen hook `settings_page_sp-wiki`. При каждом открытии discovery выполняется заново; persistent catalog cache отсутствует, поэтому enable/disable или новая документация видны на следующем request.

Порядок render:

1. Определить язык через `get_locale()`.
2. Просканировать соответствующую папку theme docs.
3. Просканировать modules и сравнить с `get_included_files()`.
4. Объединить catalogs и проверить requested `doc` ID.
5. Прочитать выбранный Markdown с диска.
6. Преобразовать поддерживаемые Markdown blocks в HTML.
7. Пропустить output через `wp_kses_post()` и вывести в shared admin UI.

## Discovery Документации Темы

Theme chapters — все readable `*.md` непосредственно в `docs/en` или `docs/ru`. Files сортируются naturally, `README.md` переносится первым. ID имеет вид `theme:<filename-without-extension>`. Первый level-one heading становится navigation label, fallback — filename.

Relative links на `.md` переписываются в `options-general.php?page=sp-wiki&doc=theme:<slug>`. Ссылки явно на другой язык выводятся как non-clickable labels, потому что locale сайта является источником истины. External `http`/`https` открываются в новой вкладке с `noopener noreferrer`; anchors остаются в статье.

## Discovery Модулей

Для каждого direct child в `core/plugins` требуются:

- folder name без начального `_`;
- реальный `index.php`;
- хотя бы один included PHP file, чей real path начинается с directory модуля.

Последнее условие делает каталог динамическим: присутствия папки в Git недостаточно. Поддерживаются и modules, где `index.php` подключает дополнительные files из `includes/`.

Fallback документов: `README.<current-language>.md`, затем `README.en.md`, затем legacy `README.md`. Если файлов нет, модуль всё равно показывается с placeholder — отсутствие coverage заметно сразу.

ID модуля: `plugin:<folder-slug>`. Для известных slugs используется semantic Dashicon; будущие неизвестные modules автоматически получают generic plugin icon.

## Поддерживаемый Markdown

Контролируемый renderer поддерживает:

- headings `#`–`######` с anchor IDs;
- paragraphs и horizontal rules;
- flat ordered/unordered lists;
- blockquotes;
- fenced code blocks с optional language class;
- inline code, bold, emphasis;
- Markdown links;
- pipe tables с delimiter row.

Raw HTML не считается доверенным Markdown syntax. Избегайте nested lists, task lists, footnotes и произвольных extensions. Документация должна оставаться portable и читаемой в source.

## Search и UI

Search фильтрует navigation entries по title/slug полностью client-side. Пустые groups скрываются, при нулевом результате появляется empty state. Body статей не индексируется, данные на server не отправляются.

На desktop sidebar sticky и имеет собственный scroll; на mobile layout становится одноколоночным. Article/header/cards/metrics/typography используют primitives `sp-admin-ui.css`.

## Maintenance Contract

При добавлении модуля:

1. В том же commit добавьте `README.en.md` и `README.ru.md` рядом с `index.php`.
2. Сохраняйте одинаковую структуру sections на двух языках.
3. Документируйте реальные options/meta/hooks, а не планируемое поведение.
4. Добавляйте icon в `PLUGIN_ICONS`, только если generic недостаточен.
5. Откройте оба файла через Wiki и визуально проверьте tables/code.
6. Обновляйте docs при изменении public helpers, storage schema, defaults и destructive workflows.

## Troubleshooting

- **Модуль отсутствует:** проверьте underscore, `index.php` и фактическую загрузку на admin request.
- **Неверный язык:** проверяйте site locale, не только admin language пользователя.
- **В RU показан English fallback:** добавьте/read-enable `README.ru.md`.
- **Неверный navigation title:** добавьте один первый `# Заголовок`.
- **Relative link ведёт не туда:** ссылайтесь на filename внутри текущей locale directory.
- **Formatting плоский:** проверьте supported syntax и readability Markdown file.
