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
| `sp-cf7-messages` | Per-form rich success/error message editor in an admin modal. |
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
		'sp-cf7-messages',
		'sp-cf7-select-field',
		'sp-cf7-icon-generator',
	],
],
```

An empty `sp-cf7` array loads no submodules. The `sp_cf7_modules` filter remains available for runtime customization.

## Submit Action configuration

```php
'sp-cf7' => [
    'submit_actions' => ['none', 'redirect', 'modal', 'message'],
    // Optional: 'modules' => ['sp-cf7-core', 'sp-cf7-redirects', 'sp-cf7-messages'],
],
```

Remove any unneeded action from `submit_actions`. `none` (Default) always remains
as the safe fallback. Omitting `submit_actions` preserves all actions provided by
loaded modules; `message` requires `sp-cf7-messages`. The restriction applies to
the editor choices and fields, saving, and frontend behavior. Previously saved
disabled actions behave as Default; their target metadata is retained.

With this named configuration, omitting `modules` loads all default modules.
`modules => []` disables all modules. Legacy numeric module lists, including an
empty list, keep their original behavior.

## Frontend component

`components.json` declares `assets/form-validate.js` with the `.wpcf7` selector. A manifest-aware theme build imports it lazily; there is no separate PHP enqueue and no theme adapter is needed. Remove the old theme component to avoid duplicate registration.

The runtime preserves loader states, CF7 success/error handling, redirect/modal/message actions and `message_target` replacement/restoration (5 seconds). WordPress Contact Form 7 still performs form submission and validation. Modal actions use the optional `window.modalManager`; styling stays in the theme.

Build dependency: `@soinproduction/kit` (tested with 1.1.14), providing `fadeIn`, `fadeOut` and `loaderInstanse`. Configure the bundler to resolve imports from the theme's node_modules even for entries under Composer vendor, for example:

```js
resolve: {
  modules: [path.resolve(themeRoot, 'node_modules'), 'node_modules'],
}
```

Here `themeRoot` is the frontend source directory containing package.json. Rebuild frontend assets after Composer updates. No WordPress REST calls or form markup changes are required for this migration.
