# SP CF7 Select Field

Добавляет `custom_select` как WordPress shortcode и form tag Contact Form 7. В модуль входят генератор тега, интеграция с mail tags и нативная required-валидация CF7.

## Подключение

```php
'plugins' => [
    'sp-cf7' => [
        'sp-cf7-select-field',
    ],
],
```

## Использование в Contact Form 7

```text
[custom_select service type "single" placeholder "Выберите услугу" options "Консультация|Аудит|Сопровождение"]
```

Обязательное поле:

```text
[custom_select* service type "single" placeholder "Выберите услугу" options "Консультация|Аудит|Сопровождение"]
```

Доступные параметры: `type`, `id`, `placeholder`, `options`, `active`, `disabled` и `class:`. Значения `options`, `active` и `disabled` разделяются символом `|`.

В письме CF7 используйте обычный mail tag:

```text
[service]
```

Required-поле регистрируется в Schema Validation (SWV) CF7 и дополнительно проверяется на сервере. Ошибка выводится внутри стандартного `.wpcf7-form-control-wrap`, а контрол получает `aria-required`, `aria-invalid` и стандартные классы CF7.

## Редактор формы

В textarea формы `Tab` добавляет отступ для текущей или всех выделенных строк, `Shift+Tab` удаляет один tab или четыре пробела. `Enter` сохраняет отступ текущей строки.

## Frontend

Отрендерованному контролу нужен frontend JavaScript Custom Select проекта, который создаёт отправляемый hidden input с именем из `data-name`. Required-валидация проверяет непустое значение; добавляйте server-side allow-list, если значение влияет на цену, права или другую доверенную логику.
