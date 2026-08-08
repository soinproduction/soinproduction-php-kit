# SP CF7 Core

Shared baseline behavior for Contact Form 7.

- Disables automatic paragraph wrapping in CF7 forms.
- Replaces the literal `[theme-url]` token with the theme `BUILD` URL.
- Runs WordPress shortcodes in final CF7 form markup.
- Adds the default `main-form grid-cols` class when no `html_class` is supplied.
- Populates the ACF field `form_select` with available CF7 forms.

Contact Form 7 and ACF are optional external dependencies. Callbacks remain inactive when their APIs are unavailable.
