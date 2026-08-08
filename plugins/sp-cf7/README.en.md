# SP CF7

A unified collection of reusable Contact Form 7 integrations. Enable it with `sp-cf7` in the PHP Kit `plugins` configuration.

Each submodule follows the same structure: `modules/<name>/index.php`, `README.en.md` and `README.ru.md`.

| Module | Purpose |
| --- | --- |
| `sp-cf7-core` | Shared CF7 rendering and ACF form choices. |
| `sp-cf7-mail-viewer` | Private log of prepared CF7 emails. |
| `sp-cf7-mailchimp-sync` | Mailchimp audience synchronization. |
| `sp-cf7-webhook` | Per-form outgoing HTTP webhook. |
| `sp-cf7-redirects` | Redirect/modal metadata on rendered forms. |
| `sp-cf7-select-field` | Custom Select shortcode, form tag and mail tags. |
| `sp-cf7-icon-generator` | UI Icon generator in the CF7 editor. |

All submodules load by default. Configure them directly in the PHP Kit `plugins` array; prefix a name with `_` to keep it listed but disabled:

```php
'plugins' => [
	'sp-cf7' => [
		'sp-cf7-core',
		'sp-cf7-mail-viewer',
		'_sp-cf7-mailchimp-sync',
		'_sp-cf7-webhook',
		'sp-cf7-redirects',
		'sp-cf7-select-field',
		'sp-cf7-icon-generator',
	],
],
```

An empty `sp-cf7` array loads no submodules. The `sp_cf7_modules` filter remains available for runtime customization.
