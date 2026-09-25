# SP CF7 Messages

Подмодуль объявляет `display_form()` и добавляет **Custom message** в панель **Submit Action** редактора Contact Form 7. Для панели нужен `sp-cf7-redirects`, для WYSIWYG-редактора — ACF.

## Быстрый пример

```php
display_form(6);
```

Сообщение вместо содержимого внешнего контейнера:

```php
$message_id = wp_unique_id('form-message-');
?>
<div id="<?= esc_attr($message_id); ?>"><h2>Contact</h2></div>
<?php display_form(6, ['message_target' => '#' . $message_id]); ?>
```

Хелпер выводит HTML сразу, `echo` не нужен. Полная сигнатура, аргументы, структура вывода, hooks, настройка JS и примеры: [display_form() в документации SP CF7](../../README.ru.md#вывод-формы-display_form).

## Настройка сообщений

1. В редакторе формы выберите **Submit Action → Custom message** и сохраните форму.
2. Нажмите **Edit messages**.
3. Заполните и сохраните `success_message` и `error_message` в открывшемся редакторе.

С формой связывается скрытая запись `sp-cf7-message` с полями ACF. Связь хранится в `_sp_cf7_message_post_id` у формы и `_sp_cf7_form_id` у записи настроек. Кнопка Edit messages открывает запись в модальном iframe.

## Чтение сообщений отдельно

```php
$settings_post_id = sp_cf7_messages_get_post_id($form_id);
$success_html = sp_cf7_messages_get_message($form_id, 'success_message');
$error_html = sp_cf7_messages_get_message($form_id, 'error_message');
$raw_success = sp_cf7_messages_get_message($form_id, 'success_message', false);
```

`sp_cf7_messages_get_post_id(int $form_id): int` возвращает ID записи настроек или `0`. `sp_cf7_messages_get_message(int $form_id, string $message_type, bool $format_value = true): string` поддерживает только `success_message` и `error_message`; если данных нет, возвращает пустую строку. При самостоятельном выводе HTML используйте `wp_kses_post()`.

## Frontend

Переключением управляет `../../assets/form-validate.js`, объявленный в `../../components.json`. Без `message_target` режим message скрывает форму и показывает success/error, затем возвращает форму через 5 секунд. С корректным `message_target` успешное сообщение временно заменяет содержимое цели, а форма остаётся видимой. Ошибки заполнения остаются в самой форме. Разметка и стили сообщений настраиваются темой.
