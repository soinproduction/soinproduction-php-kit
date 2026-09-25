# SP CF7 Messages

Добавляет вариант **Custom message** в панель CF7 **Submit Action**. После сохранения этого варианта с CF7-формой один-к-одному связывается скрытая запись `sp-cf7-message` с ACF WYSIWYG-полями `success_message` и `error_message`.

Кнопка **Edit messages** на странице формы открывает связанную запись в модальном iframe.

Форма вместе со скрытыми success/error-блоками выводится одним helper-ом:

```php
<?php display_form($form_id); ?>
```

В режиме `message` frontend-обработчик темы показывает соответствующий блок после события CF7.

Связь хранится в `_sp_cf7_message_post_id` у CF7-формы и `_sp_cf7_form_id` у скрытой записи настроек.

Отдельное чтение сохранённых значений:

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

## Место вывода сообщения

```php
display_form($form_id, ['message_target' => '#contact-message']);
```

`message_target` — CSS-селектор блока для временного вывода успешного сообщения вместо его содержимого с последующим восстановлением. PHP выводит экранированный `data-cf7-message-target`. Перенос, таймер на 5 секунд и восстановление выполняет `sp-cf7/assets/form-validate.js`, подключаемый сборщиком через `sp-cf7/components.json`. ID должен быть уникальным для каждого экземпляра. Без аргумента сохраняется прежняя разметка и поведение формы. Для сборки нужны npm-зависимости, указанные в README модуля sp-cf7.
