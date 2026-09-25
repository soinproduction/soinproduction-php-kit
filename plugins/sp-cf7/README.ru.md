# SP CF7

Единый набор переиспользуемых интеграций Contact Form 7. Подключается именем `sp-cf7` в списке `plugins` PHP Kit.

Каждый подмодуль имеет одинаковую структуру: `modules/<name>/index.php`, `README.en.md` и `README.ru.md`.

## Вывод формы: `display_form()`

```php
display_form(int $form_id, array $args = []): void
```

Хелпер **сразу выводит HTML**, не возвращает строку — `echo` не нужен. Он объявлен в `sp-cf7-messages`, поэтому этот подмодуль должен быть включён даже для обычного вызова. Нужны активный Contact Form 7, а для редактора rich-сообщений — ACF.

### Обычная форма

```php
<?php display_form(6); ?>
```

Из поля конструктора ACF:

```php
<?php
$form_id = (int) get_sub_field('form_select');
if ($form_id) {
    display_form($form_id);
}
?>
```

`$form_id` — ID существующей CF7-формы. Хелпер нормализует его через `absint()`. При результате `0` ничего не выводится и хуки не вызываются. Наличие формы по положительному ID проверяет сам CF7 при выполнении shortcode.

### Аргументы `$args`

| Ключ | Значение | Поведение |
| --- | --- | --- |
| `message_target` | CSS-селектор, например `#contact-message` | После успешной отправки временно заменяет содержимое найденного блока сообщением. |
| Любые другие ключи | Данные конкретного вызова | Передаются в `sp_cf7_before_form` и `sp_cf7_after_form`; сам хелпер их не интерпретирует. |

`title`, `context`, `class` не являются встроенными настройками хелпера. `context` и `title` ниже — пример данных для вашего hook. Настройки redirect/modal/message выбираются в админке формы, а не в `$args`.

### Сообщение вместо заголовка

В админке CF7 выберите **Submit Action → Custom message**, сохраните форму, нажмите **Edit messages**, заполните и сохраните Success message / Error message. Затем:

```php
<?php $message_id = wp_unique_id('contact-message-'); ?>
<div id="<?= esc_attr($message_id); ?>">
    <h2>Contact</h2>
</div>

<?php
// Заголовок находится снаружи формы и её внутренних контейнеров.
display_form(6, ['message_target' => '#' . $message_id]);
?>
```

При `wpcf7mailsent` компонент переносит подготовленный success-блок в указанный контейнер, сохраняя его исходные DOM-узлы. Через **5 секунд** сообщение возвращается в скрытый блок формы, а исходный заголовок восстанавливается вместе с его узлами и обработчиками. Форма остаётся видимой и сбрасывается. Повторная успешная отправка сначала восстанавливает предыдущий вывод и перезапускает таймер.

Выбирается первый элемент через `document.querySelector()`, поэтому используйте уникальный ID для каждого экземпляра формы. Передавайте селектор контейнера, содержимое которого можно заменить, например `div`, а не сам `h2`. Контейнер не должен содержать `.wpcf7` или находиться внутри него. Неверный селектор / отсутствующая цель возвращают обработку к обычному Submit Action. Цель применяется **только к успеху**; ошибки выводятся по выбранному действию формы.

Текущий JS проверяет `message_target` перед redirect/modal/message: если цель найдена, она имеет приоритет над этими действиями. Удалите аргумент, если нужен редирект или модальное окно. Не оставляйте success-сообщение пустым: хелпер создаёт его контейнер даже при пустом тексте.

### Сообщение вместо формы

```php
<?php display_form(6); ?>
```

При выбранном **Custom message** и без `message_target` после успеха показывается success-сообщение, после ошибки отправки / spam — error-сообщение. Внутренний блок формы скрывается. Через **5 секунд** форма возвращается; после успеха её поля сбрасываются. Ошибки заполнения (`wpcf7invalid`) оставляют форму видимой для исправления.

### Контент до и после формы

Зарегистрируйте hook один раз в коде темы:

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

В шаблоне:

```php
display_form(6, ['context' => 'footer', 'title' => 'Subscribe']);
```

Оба hook получают `$form_id` и весь `$args`. Они выводятся внутри `[data-cf7-message-form]`, до/после shortcode CF7, поэтому скрываются вместе с формой в режиме сообщения. Хелпер не добавляет обёртки вокруг вывода hook; экранирование динамических данных выполняет callback.

### Что выводит хелпер

Упрощённая структура (служебные классы опущены):

```html
<div data-cf7-message-wrapper data-cf7-form-id="6">
  <div data-cf7-message-form>
    <!-- sp_cf7_before_form -->
    <!-- результат [contact-form-7 id="6"] -->
    <!-- sp_cf7_after_form -->
  </div>
  <div data-cf7-message="success" role="status" aria-live="polite" style="display:none"></div>
  <div data-cf7-message="error" role="alert" aria-live="assertive" style="display:none"></div>
</div>
```

При заданном `message_target` внешний контейнер дополнительно получает `data-cf7-message-target`. HTML сообщений обрабатывается `wp_kses_post()`. Разметка полей задаётся в CF7, а стили контейнера, loader и сообщений — в теме. Для позиционирования/классов оберните вызов собственным элементом.

Подробности хранения и получения сообщений: [SP CF7 Messages](modules/sp-cf7-messages/README.ru.md).

| Модуль | Назначение |
| --- | --- |
| `sp-cf7-core` | Общее поведение CF7 и ACF-список форм. |
| `sp-cf7-mail-viewer` | Приватный журнал подготовленных писем CF7. |
| `sp-cf7-mailchimp-sync` | Синхронизация с Mailchimp audience. |
| `sp-cf7-webhook` | Исходящий HTTP webhook отдельной формы. |
| `sp-cf7-redirects` | Redirect/modal metadata в разметке форм. |
| `sp-cf7-messages` | Rich success/error сообщения отдельной формы в модальном редакторе. |
| `sp-cf7-select-field` | Custom Select shortcode, form tag и mail tags. |
| `sp-cf7-icon-generator` | Генератор UI Icon в редакторе CF7. |

По умолчанию загружаются все подмодули. Управлять ими можно прямо в массиве `plugins`; префикс `_` оставляет подмодуль в списке, но отключает его:

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

Пустой массив `sp-cf7` не загружает ни одного подмодуля. Для динамической настройки остаётся фильтр `sp_cf7_modules`.

## Настройка Submit Action

```php
'sp-cf7' => [
    'submit_actions' => ['none', 'redirect', 'modal', 'message'],
    // Optional: 'modules' => ['sp-cf7-core', 'sp-cf7-redirects', 'sp-cf7-messages'],
],
```

Удалите ненужные действия из `submit_actions`. Вариант `none` (Default) остаётся доступным всегда. Если `submit_actions` не указан, доступны все действия загруженных подмодулей; `message` требует `sp-cf7-messages`. Ограничение применяется к полям админки, сохранению и frontend-метаданным. Ранее сохранённое отключённое действие работает как Default, но его настройки назначения сохраняются.

В именованной конфигурации отсутствие `modules` включает все подмодули по умолчанию; `modules => []` отключает их все. Числовые списки подмодулей, включая пустой список, сохраняют прежнее поведение.

| Действие | После отправки |
| --- | --- |
| `none` | Стандартный ответ CF7. |
| `redirect` | При успехе переход по URL из настроек формы. |
| `modal` | Открытие заданного success/error modal через `window.modalManager`; автоматическое закрытие через 3 секунды после открытия. |
| `message` | Показ rich success/error-блока и возврат формы через 5 секунд. |

## Подключение JS-компонента

`components.json` объявляет `assets/form-validate.js` с селектором `.wpcf7`. Сборщик темы, поддерживающий эти манифесты, автоматически создаёт lazy chunk и загружает его только при наличии формы. Отдельного PHP enqueue нет; адаптер в теме не нужен. Сам Composer не подключает JS в браузер: необходима сборка frontend.

Компонент управляет loader, обрабатывает события CF7, redirect/modal/message и `message_target`. Отправку формы и валидацию выполняет сам Contact Form 7. Название `form-validate.js` не означает, что компонент заменяет CF7. Для modal-действий используется необязательный `window.modalManager`, доступный при инициализации компонента; стили остаются в теме.

Зависимость сборки — npm-пакет `@soinproduction/kit` (проверено с 1.1.14), предоставляющий `fadeIn`, `fadeOut` и `loaderInstanse`. Для JS внутри Composer vendor настройте поиск импортов в node_modules темы:

```js
resolve: {
  modules: [path.resolve(themeRoot, 'node_modules'), 'node_modules'],
}
```

Здесь `themeRoot` — каталог frontend-исходников с `package.json`. После обновления Composer пересоберите frontend. При переносе удалите прежний `form-validate.js` из компонентов темы, чтобы не получить дублирующее имя компонента и обработчики.

## Если сообщение не показывается

- `display_form()` не найден: проверьте подключение `sp-cf7-messages`.
- Режима Custom message нет: включите `sp-cf7-redirects`, `sp-cf7-messages` и разрешите `message` в `submit_actions`.
- Форма отправляется, но custom-сообщение не переключается: проверьте загрузку chunk и ошибки JS; один PHP-хелпер не выполняет переключение.
- Вместо заголовка пусто: заполните Success message через Edit messages.
- Цель не меняется: проверьте уникальность ID, селектор и расположение контейнера вне `.wpcf7`.
- Работает старый сценарий: обновите Composer, пересоберите JS и проверьте актуальность assets/cache.
