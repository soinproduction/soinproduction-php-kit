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

### Editing existing tags

Place the cursor inside a `custom_select` tag (or select the tag) before opening Select. The generator restores its settings and Insert Tag replaces the original. Required field names retain their case in both server validation and SWV rules.

### Theme arrow icon

Override the arrow without changing the shared markup or adding wrappers:

```php
add_filter('sp_cf7_select_arrow_markup', static function ($markup, $attributes) {
    return '<span class="icon icon-chevron-down" aria-hidden="true"></span>';
}, 10, 2);
```

The default `sprite(24, 24, 'chevron-down')` remains unchanged when the filter returns `null`. Override markup is sanitized with `wp_kses_post`. Remove-button appearance belongs to the theme's frontend Custom Select styles.
