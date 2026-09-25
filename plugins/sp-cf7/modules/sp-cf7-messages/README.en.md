# SP CF7 Messages

Adds a **Custom message** option to the CF7 **Submit Action** panel. When that option is saved, the CF7 form is linked one-to-one with a hidden `sp-cf7-message` post containing ACF WYSIWYG fields named `success_message` and `error_message`.

Use **Edit messages** on the CF7 form screen to open the linked settings post in an iframe modal.

Render the form together with hidden success/error blocks using one helper:

```php
<?php display_form($form_id); ?>
```

In `message` mode, the sp-cf7 frontend component reveals the corresponding block after a CF7 event.

Relationships are stored in `_sp_cf7_message_post_id` on the CF7 form and `_sp_cf7_form_id` on the hidden settings post.

Read the stored values separately:

```php
$settings_post_id = sp_cf7_messages_get_post_id($form_id);
$success_html = sp_cf7_messages_get_message($form_id, 'success_message');
$error_html = sp_cf7_messages_get_message($form_id, 'error_message');
```

## Content inside the form state

`display_form(int $form_id, array $args = [])` supports two WordPress actions:
`sp_cf7_before_form` and `sp_cf7_after_form`. Both receive `$form_id` and `$args`.
They run inside `[data-cf7-message-form]`, immediately before/after the CF7
shortcode, so inserted content hides together with the form when a custom
success/error message appears. Existing single-argument calls still work.

```php
add_action('sp_cf7_before_form', function ($form_id, $args) {
    if (($args['context'] ?? '') === 'footer' && !empty($args['title'])) {
        echo '<h2>' . esc_html($args['title']) . '</h2>';
    }
}, 10, 2);

display_form($form_id, ['context' => 'footer', 'title' => 'Subscribe']);
```

Callbacks render their own HTML and must escape dynamic values. Use `context`
to distinguish multiple instances of the same form. The helper does not add
wrappers around hook output. Invalid/zero IDs do not invoke these actions.

## Message target

```php
display_form($form_id, ['message_target' => '#contact-message']);
```

`message_target` is a CSS selector for temporarily replacing a target's contents with the success message, then restoring them. PHP emits an escaped `data-cf7-message-target` attribute. The controller in `sp-cf7/assets/form-validate.js` implements replacement, a 5-second timer and restoration. It is declared in `sp-cf7/components.json` for lazy bundling. Use unique IDs per form instance. Omitting the argument preserves the existing markup. See the sp-cf7 README for frontend build dependencies.

## Complete helper reference

See [display_form()](../../README.en.md#rendering-forms-display_form) for its signature, all arguments, ordinary and ACF-based rendering, external heading replacement, restore timing, before/after hooks, markup structure, frontend setup and troubleshooting.
