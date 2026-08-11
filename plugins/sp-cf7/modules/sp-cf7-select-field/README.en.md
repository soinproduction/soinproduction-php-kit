# SP CF7 Select Field

Adds `custom_select` as both a WordPress shortcode and a Contact Form 7 form tag. The module includes a tag generator, mail-tag integration, and native CF7 required validation.

## Registration

```php
'plugins' => [
    'sp-cf7' => [
        'sp-cf7-select-field',
    ],
],
```

## Contact Form 7 usage

```text
[custom_select service type "single" placeholder "Choose a service" options "Consulting|Audit|Support"]
```

Required field:

```text
[custom_select* service type "single" placeholder "Choose a service" options "Consulting|Audit|Support"]
```

Supported parameters are `type`, `id`, `placeholder`, `options`, `active`, `disabled`, and `class:`. Separate `options`, `active`, and `disabled` values with `|`.

Use the standard mail tag in the CF7 email template:

```text
[service]
```

Required fields are registered with CF7 Schema Validation (SWV) and also validated server-side. Validation messages are rendered inside the standard `.wpcf7-form-control-wrap`; the control receives `aria-required`, `aria-invalid`, and the standard CF7 classes.

## Form editor

Inside the form textarea, `Tab` indents the current line or every selected line, while `Shift+Tab` removes one tab or four spaces. `Enter` preserves the current line indentation.

## Frontend

The rendered control requires the project's frontend Custom Select JavaScript, which creates the submitted hidden input using `data-name`. Required validation checks for a nonempty value; add a server-side allow-list when the value affects pricing, permissions, or other trusted logic.
