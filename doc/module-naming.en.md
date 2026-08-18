# Module Naming

Every public PHP Kit module ID starts with `sp-`, uses `kebab-case`, and describes the feature rather than its implementation. Nested modules include their parent namespace, for example `sp-cf7-mail-viewer`.

Bootstrapper accepts the following legacy aliases for backward compatibility. New configuration must use the canonical IDs.

## Platform and ACF

| Legacy ID | Canonical ID |
| --- | --- |
| `author-meta` | `sp-author-meta` |
| `branding` | `sp-login-branding` |
| `dev-user` | `sp-content-admin` |
| `duplicator-key` | `sp-duplicator-license` |
| `page-loader-settings` | `sp-motion-settings` |
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

| Legacy ID | Canonical ID |
| --- | --- |
| `sp-allow-svg-upload` | `sp-svg-support` |
| `sp-cpt-archives` | `sp-archive-pages` |
| `sp-dev-mode` | `sp-debug-toolbar` |
| `sp-favorite-posts` | `sp-content-favorites` |
| `sp-redirects` | `sp-redirect-manager` |
| `sp-uploads-webp-convert` | `sp-webp-uploads` |
| `sp-video-preview` | `sp-video-posters` |
| `sp-wiki` | `sp-documentation` |

## Composite Modules

The short Admin UI IDs map to `sp-admin-ui-menu-heading`, `sp-admin-ui-text-column`, `sp-admin-ui-thumbnail-column`, `sp-admin-ui-taxonomy-checklist`, and `sp-admin-ui-taxonomy-radio`.

The short CF7 IDs map to `sp-cf7-core`, `sp-cf7-mail-viewer`, `sp-cf7-mailchimp-sync`, `sp-cf7-webhook`, `sp-cf7-redirects`, `sp-cf7-select-field`, and `sp-cf7-icon-generator`.
