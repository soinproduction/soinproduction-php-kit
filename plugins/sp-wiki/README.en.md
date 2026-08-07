# Theme Wiki

Dynamic in-admin documentation browser for the custom theme and every connected module.

## Discovery Rules

- Theme chapters are read from `docs/en/*.md` or `docs/ru/*.md`.
- Module documentation is discovered under `core/plugins/*`.
- A module is listed only when its folder is not prefixed with `_`, contains `index.php`, and at least one PHP file from that folder is loaded in the current admin request.
- The Wiki itself does not activate or include other modules.

## Localization

The site locale controls documentation language. Locales beginning with `ru` use `README.ru.md` and `docs/ru`; every other locale uses `README.en.md` and `docs/en`.

If a localized module file is missing, English and then legacy `README.md` are used as fallbacks.

## Adding Documentation to a Module

Place these files beside the module's `index.php`:

```text
README.en.md
README.ru.md
```

The first `# Heading` becomes the navigation title. Standard headings, paragraphs, lists, tables, quotes, links and fenced code blocks are supported.

## Security Model

The page requires `manage_options`. Markdown is read only from the active theme directory and the rendered HTML is passed through the WordPress post HTML allowlist. No documentation is stored in the database.

## Request Lifecycle

`SP_Theme_Wiki::init()` registers the Settings submenu and page-specific assets. CSS/JavaScript load only for the `settings_page_sp-wiki` screen hook. Opening the page performs discovery again; there is no persistent catalog cache, so enabling, disabling or documenting a module is reflected on the next request.

The render lifecycle is:

1. Determine language from `get_locale()`.
2. Scan the matching theme documentation directory.
3. Scan module directories and compare them with `get_included_files()`.
4. Merge both catalogs and validate the requested `doc` ID.
5. Read the selected Markdown file from disk.
6. Convert supported Markdown blocks to HTML.
7. Pass output through `wp_kses_post()` and render it in the shared admin design system.

## Theme Document Discovery

Theme chapters are every readable `*.md` file directly inside `docs/en` or `docs/ru`. Files are naturally sorted; `README.md` is moved to the first position. Document IDs use `theme:<filename-without-extension>`. The first level-one heading becomes the navigation label; the filename is the fallback.

Relative Markdown links to another `.md` file are rewritten to `options-general.php?page=sp-wiki&doc=theme:<slug>`. Links that explicitly target the other language are rendered as non-clickable labels because site locale is authoritative. External `http`/`https` links open in a new tab with `noopener noreferrer`; anchors remain in the current article.

## Module Discovery in Detail

For each direct child of `core/plugins` the Wiki requires:

- a folder name that does not begin with `_`;
- a real `index.php` file;
- at least one included PHP file whose real path starts with that module directory.

The last condition is what makes the catalog dynamic: mere presence in Git is insufficient. It also supports modules whose `index.php` loads additional files in `includes/`.

Document fallback order is `README.<current-language>.md`, `README.en.md`, then legacy `README.md`. If none exists, the module still appears with an instructional placeholder so missing coverage is visible.

Module document IDs use `plugin:<folder-slug>`. A known-slug icon registry supplies semantic Dashicons; unknown future modules receive the generic plugin icon automatically.

## Supported Markdown

The bundled renderer intentionally supports a controlled subset:

- headings `#` through `######` with generated anchor IDs;
- paragraphs and horizontal rules;
- ordered and unordered flat lists;
- blockquotes;
- fenced code blocks with an optional language class;
- inline code, bold and emphasis;
- Markdown links;
- pipe tables with a delimiter row.

Raw HTML is not treated as trusted Markdown syntax. Complex nested lists, task lists, footnotes and arbitrary extensions should be avoided. Keep docs portable and source-readable.

## Search and UI Behavior

The search box filters navigation entries client-side by title and slug. Empty groups are hidden and an empty-state message appears when nothing matches. Search does not index article body text and does not send data to the server.

The sidebar is sticky on desktop with its own scroll area; mobile layout becomes a single column. The article, header, cards, metrics, buttons and typography inherit `sp-admin-ui.css` primitives.

## Maintenance Contract

When adding a new module:

1. Add `README.en.md` and `README.ru.md` beside `index.php` in the same commit.
2. Use matching section structure in both languages.
3. Document exact options/meta/hooks rather than planned behavior.
4. Add a semantic icon to `PLUGIN_ICONS` only when the generic icon is insufficient.
5. Open both files through Wiki and verify tables/code blocks visually.
6. Update documentation whenever public helpers, storage schema, defaults or destructive workflows change.

## Troubleshooting

- **Module is missing:** confirm the folder is not underscored, has `index.php`, and is actually loaded on the admin request.
- **Wrong language:** check the site locale, not only the current user's admin language.
- **English fallback appears in Russian:** create/read-enable `README.ru.md` in the module.
- **Navigation title is wrong:** add a single first `# Heading`.
- **Relative link opens the wrong chapter:** link to a filename inside the current locale directory.
- **Formatting is plain:** verify syntax belongs to the supported subset and the Markdown file is readable.
