# Naming модулей

Каждый публичный module ID PHP Kit начинается с `sp-`, использует `kebab-case` и описывает назначение, а не деталь реализации. Вложенные модули включают namespace родителя, например `sp-cf7-mail-viewer`.

Для обратной совместимости Bootstrapper принимает legacy aliases из таблиц ниже. В новых конфигурациях следует использовать только канонические IDs.

## Platform и ACF

| Legacy ID | Канонический ID |
| --- | --- |
| `author-meta` | `sp-author-meta` |
| `branding` | `sp-login-branding` |
| `dev-user` | `sp-content-admin` |
| `duplicator-key` | `sp-duplicator-license` |
| `post-type-converter` | `sp-content-type-converter` |
| `reading-time` | `sp-reading-time` |
| `remove-post-slug` | `sp-permalink-manager` |
| `reset` | `sp-wordpress-baseline` |
| `archive-builder` | `sp-archive-builder` |
| `icon-link-list` | `sp-icon-links` |
| `related-posts` | `sp-related-content` |
| `smart-relationship` | `sp-post-selector` |
| `smart-taxonomy` | `sp-term-selector` |
| `table` | `sp-table` |
| `taxonomy-urls` | `sp-term-links` |
| `universal-media` | `sp-media` |

## Plugins

| Legacy ID | Канонический ID |
| --- | --- |
| `sp-allow-svg-upload` | `sp-svg-support` |
| `sp-cpt-archives` | `sp-archive-pages` |
| `sp-dev-mode` | `sp-debug-toolbar` |
| `sp-favorite-posts` | `sp-content-favorites` |
| `sp-redirects` | `sp-redirect-manager` |
| `sp-uploads-webp-convert` | `sp-webp-uploads` |
| `sp-video-preview` | `sp-video-posters` |
| `sp-wiki` | `sp-documentation` |

## Составные модули

Короткие Admin UI IDs сопоставляются с `sp-admin-ui-menu-heading`, `sp-admin-ui-text-column`, `sp-admin-ui-thumbnail-column`, `sp-admin-ui-taxonomy-checklist` и `sp-admin-ui-taxonomy-radio`.

Короткие CF7 IDs сопоставляются с `sp-cf7-core`, `sp-cf7-mail-viewer`, `sp-cf7-mailchimp-sync`, `sp-cf7-webhook`, `sp-cf7-redirects`, `sp-cf7-select-field` и `sp-cf7-icon-generator`.
