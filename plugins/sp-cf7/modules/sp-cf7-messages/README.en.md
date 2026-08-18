# SP CF7 Messages

Adds a **Custom message** option to the CF7 **Submit Action** panel. When that option is saved, the CF7 form is linked one-to-one with a hidden `sp-cf7-message` post containing ACF WYSIWYG fields named `success_message` and `error_message`.

Use **Edit messages** on the CF7 form screen to open the linked settings post in an iframe modal. The module owns only the admin data model and editor. It intentionally does not replace CF7 messages on the front end.

Relationships are stored in `_sp_cf7_message_post_id` on the CF7 form and `_sp_cf7_form_id` on the hidden settings post.

Read the stored values without triggering front-end behavior:

```php
$settings_post_id = sp_cf7_messages_get_post_id($form_id);
$success_html = sp_cf7_messages_get_message($form_id, 'success_message');
$error_html = sp_cf7_messages_get_message($form_id, 'error_message');
```
