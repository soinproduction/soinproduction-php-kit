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
