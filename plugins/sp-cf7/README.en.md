# SP CF7

A unified collection of reusable Contact Form 7 integrations. Enable it with `sp-cf7` in the PHP Kit `plugins` configuration.

Each submodule follows the same structure: `modules/<name>/index.php`, `README.en.md` and `README.ru.md`.

## Rendering forms: `display_form()`

```php
display_form(int $form_id, array $args = []): void
```

The helper **outputs HTML immediately**; do not use `echo`. It is defined by `sp-cf7-messages`, which must be enabled even for the basic call. Contact Form 7 must be active; the rich-message editor also requires ACF.

### Basic form and ACF selection

```php
<?php display_form(6); ?>
```

```php
<?php
$form_id = (int) get_sub_field('form_select');
if ($form_id) {
    display_form($form_id);
}
?>
```

`$form_id` is an existing CF7 form ID. It is normalized with `absint()`. A resulting `0` produces no output and does not invoke hooks. CF7 handles positive IDs that do not resolve to a form when the shortcode runs.

### Arguments

| Key | Value | Behavior |
| --- | --- | --- |
| `message_target` | CSS selector, e.g. `#contact-message` | Temporarily replaces the selected container's contents with the success message. |
| Any other key | Per-instance context | Passed unchanged to `sp_cf7_before_form` and `sp_cf7_after_form`; not interpreted by the helper. |

`title`, `context`, and `class` are not built-in options. Redirect/modal/message actions are selected in the form editor, not in `$args`.

### Replace a heading with the success message

In the CF7 editor, select **Submit Action → Custom message**, save the form, click **Edit messages**, and fill and save Success message / Error message.

```php
<?php $message_id = wp_unique_id('contact-message-'); ?>
<div id="<?= esc_attr($message_id); ?>">
    <h2>Contact</h2>
</div>
<?php display_form(6, ['message_target' => '#' . $message_id]); ?>
```

On `wpcf7mailsent`, the frontend component moves the rendered success block into the target and preserves its original DOM nodes. After **5 seconds**, it moves the message back to its hidden form container and restores the original nodes and their event handlers. The form stays visible and is reset. Repeated successful submissions restore the previous state before restarting the timer.

The selector is resolved with `document.querySelector()`: use a unique ID per form instance. Select a container such as `div`, not the heading element itself. The target must neither contain `.wpcf7` nor be inside it. Invalid selectors and missing targets fall back to the selected Submit Action. Only success messages use the target; errors follow the form's configured action.

Currently `message_target` is checked before redirect/modal/message and takes precedence if resolved. Omit it when a redirect or modal is intended. Configure nonempty success text: its container exists even when no text is saved.

### Replace the form with a message

```php
<?php display_form(6); ?>
```

With **Custom message** selected and no target, success displays the success block; mail failures/spam display the error block. The form content is hidden, then restored after **5 seconds**. Success resets the form. Invalid field input (`wpcf7invalid`) leaves it visible for corrections.

### Render content before and after the form

Register callbacks once in theme code:

```php
add_action('sp_cf7_before_form', function ($form_id, $args) {
    if (($args['context'] ?? '') === 'footer' && !empty($args['title'])) {
        echo '<h2>' . esc_html($args['title']) . '</h2>';
    }
}, 10, 2);

add_action('sp_cf7_after_form', function ($form_id, $args) {
    if (($args['context'] ?? '') === 'footer') {
        echo '<p>We will get back to you shortly.</p>';
    }
}, 10, 2);
```

Then render the instance:

```php
display_form(6, ['context' => 'footer', 'title' => 'Subscribe']);
```

Both hooks receive the form ID and all arguments. They run inside `[data-cf7-message-form]`, immediately before/after the CF7 shortcode, so their content hides with the form during message display. No extra wrappers are inserted. Callbacks must escape dynamic output.

### Rendered structure

Simplified HTML, omitting styling classes:

```html
<div data-cf7-message-wrapper data-cf7-form-id="6">
  <div data-cf7-message-form>
    <!-- sp_cf7_before_form -->
    <!-- output of [contact-form-7 id="6"] -->
    <!-- sp_cf7_after_form -->
  </div>
  <div data-cf7-message="success" role="status" aria-live="polite" style="display:none"></div>
  <div data-cf7-message="error" role="alert" aria-live="assertive" style="display:none"></div>
</div>
```

A configured target adds `data-cf7-message-target` to the outer wrapper. Message HTML is sanitized with `wp_kses_post()`. CF7 owns field markup; the theme styles containers, loaders and messages. Wrap the helper call in your own element to add layout classes.

See [SP CF7 Messages](modules/sp-cf7-messages/README.en.md) for message storage and retrieval.

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

`components.json` declares `assets/form-validate.js` with the `.wpcf7` selector. A manifest-aware theme build imports it lazily; there is no separate PHP enqueue and no theme adapter is needed. Composer alone does not load browser assets: rebuild the frontend. Remove the old theme component to avoid duplicate registration.

The runtime preserves loader states, CF7 success/error handling, redirect/modal/message actions and `message_target` replacement/restoration (5 seconds). WordPress Contact Form 7 still performs form submission and validation. Modal actions use the optional `window.modalManager`; styling stays in the theme.

Build dependency: `@soinproduction/kit` (tested with 1.1.14), providing `fadeIn`, `fadeOut` and `loaderInstanse`. Configure the bundler to resolve imports from the theme's node_modules even for entries under Composer vendor, for example:

```js
resolve: {
  modules: [path.resolve(themeRoot, 'node_modules'), 'node_modules'],
}
```

Here `themeRoot` is the frontend source directory containing package.json. Rebuild frontend assets after Composer updates. No WordPress REST calls or form markup changes are required for this migration.

## Troubleshooting

- Undefined `display_form()`: enable `sp-cf7-messages`.
- Missing Custom message action: enable `sp-cf7-redirects` and `sp-cf7-messages`, and allow `message` in `submit_actions`.
- Submission works but custom messages do not switch: verify the lazy chunk loads and inspect JS errors; PHP alone does not switch states.
- Empty replacement: fill Success message using Edit messages.
- Target does not change: check its unique ID, selector and placement outside `.wpcf7`.
- Old behavior after an update: rebuild frontend assets and verify caches.
