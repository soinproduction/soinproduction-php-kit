# SP CF7

A unified collection of reusable Contact Form 7 integrations. Enable it with `sp-cf7` in the PHP Kit `plugins` configuration.

Each submodule follows the same structure: `modules/<name>/index.php`, `README.en.md` and `README.ru.md`.

| Module | Purpose |
| --- | --- |
| `base` | Shared CF7 rendering and ACF form choices. |
| `mail-viewer` | Private log of prepared CF7 emails. |
| `flowchimp` | Mailchimp audience synchronization. |
| `webhook` | Per-form outgoing HTTP webhook. |
| `redirects` | Redirect/modal metadata on rendered forms. |
| `ui-select` | Custom Select shortcode, form tag and mail tags. |
| `icon-generator` | UI Icon generator in the CF7 editor. |

All submodules load by default. Configure them directly in the PHP Kit `plugins` array; prefix a name with `_` to keep it listed but disabled:

```php
'plugins' => [
	'sp-cf7' => [
		'base',
		'mail-viewer',
		'_flowchimp',
		'_webhook',
		'redirects',
		'ui-select',
		'icon-generator',
	],
],
```

An empty `sp-cf7` array loads no submodules. The `sp_cf7_modules` filter remains available for runtime customization.
