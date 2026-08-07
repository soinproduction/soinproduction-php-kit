# Tag Manager

Centralized output pipeline for Google Tag Manager, Google Consent Mode v2, a lightweight first-party consent prompt and trusted custom snippets. It controls when markup is printed and when the remote GTM script is requested; it does not configure containers inside Google Tag Manager.

## Output Lifecycle

| WordPress hook | Priority | Output |
| --- | ---: | --- |
| `wp_head` | `1` | Consent bootstrap, GTM preconnect/loader and custom head snippet. |
| `wp_body_open` | `1` | Conditional GTM noscript iframe and custom body-open snippet. |
| `wp_footer` | `100` | Cookie prompt/script and custom footer snippet. |

The theme must call `wp_body_open()` directly after `<body>` for body-start output. A missing hook does not prevent the main head loader, but removes noscript and body-open custom code.

## Global Eligibility

Nothing is printed when the master `enabled` flag is off. The module also skips feeds, robots responses, trackbacks and embeds. By default it suppresses output for logged-in users and on admin/login requests.

Final eligibility can be changed without editing the module:

```php
add_filter('sp_tag_manager_should_output', function (bool $output, array $cfg): bool {
    if (is_page('privacy-preview')) {
        return false;
    }
    return $output;
}, 10, 2);
```

Test as a logged-out visitor: the default “disable for logged-in users” is the most common reason administrators do not see tags.

## Storage and Complete Defaults

Configuration is stored in non-autoloaded option `sp_tag_manager_cfg`.

| Key | Default | Validation / effect |
| --- | ---: | --- |
| `enabled` | `1` | Master output switch. |
| `disable_for_logged` | `1` | Suppresses all module output for authenticated visitors. |
| `disable_on_admin` | `1` | Suppresses admin and `wp-login.php`. |
| `gtm_enabled` | `1` | Enables the GTM loader when a valid ID exists. |
| `gtm_id` | empty | Must match `GTM-[A-Z0-9]+`; otherwise saved empty. |
| `gtm_data_layer` | `dataLayer` | Valid JavaScript identifier; invalid input resets to `dataLayer`. |
| `gtm_strategy` | `after_interaction` | `immediate`, `after_delay` or `after_interaction`. |
| `gtm_delay_ms` | `2500` | Delay strategy timeout, 0–15000 ms. |
| `consent_mode_enabled` | `1` | Prints Consent Mode bootstrap before GTM. |
| `consent_default` | `denied` | Only `granted` or `denied`. |
| `consent_wait_ms` | `500` | `wait_for_update`, 0–5000 ms. |
| `consent_cookie_sync` | `1` | Applies a previously stored matching cookie value. |
| `consent_cookie_key` | `sp_cookie_consent` | 2–80 letters, digits, `_` or `-`. |
| `consent_cookie_grant` | `granted` | 1–80 safe key characters. |
| `consent_cookie_deny` | `denied` | 1–80 safe key characters. |
| `cookie_modal_enabled` | `1` | Prints the built-in consent prompt. |
| `cookie_modal_text` | default English copy | Sanitized post-style HTML. |
| accept/reject labels | `Accept` / `Reject` | Plain text. |
| accept/reject classes | empty | Sanitized space-separated HTML classes. |
| `custom_head/body_open/footer` | empty | Trusted HTML filtered by a tag/attribute allowlist. |

## GTM Loading Strategies

### Immediate

Initializes the configured data layer, pushes `{gtm.start, event:'gtm.js'}` and asynchronously inserts `gtm.js` during the head hook. Choose this when measurement must begin as early as possible and the privacy/performance consequences are accepted.

### After Delay

Exposes `window.spLoadGtm`, then loads after `gtm_delay_ms`. A zero delay loads immediately through the deferred loader. Calling `window.spLoadGtm()` earlier is safe; an internal flag prevents duplicate insertion.

### After Interaction

Exposes the same `window.spLoadGtm` function and attaches one-time passive listeners for `pointerdown`, `touchstart`, `mousemove`, `keydown` and `scroll`. The first event loads GTM. If no interaction occurs, a ten-second timer starts after `window.load`. This ten-second safety fallback is independent of `gtm_delay_ms`.

All strategies add a preconnect to `https://www.googletagmanager.com`. A custom data layer appends the encoded `l` parameter to loader and noscript URLs.

## Consent Mode v2

The head bootstrap creates the configured array and a `window.gtag` queue function when absent, then sends `gtag('consent','default', values)` before the GTM loader. The default covers:

- `ad_storage`;
- `analytics_storage`;
- `ad_user_data`;
- `ad_personalization`;
- `wait_for_update`.

All four storage/signaling fields are granted or denied together by the built-in all-or-nothing controls. For a granular CMP use `spTagConsentUpdate()` with a custom object.

Public runtime API:

```js
window.spTagConsentGrantAll();
window.spTagConsentDenyAll();
window.spTagConsentUpdate({
  analytics_storage: 'granted',
  ad_storage: 'denied',
  ad_user_data: 'denied',
  ad_personalization: 'denied'
});

document.dispatchEvent(new CustomEvent('sp:consent:update', {
  detail: { state: 'granted' }
}));

document.dispatchEvent(new CustomEvent('sp:consent:update', {
  detail: { values: { analytics_storage: 'granted' } }
}));
```

The listener accepts a string state, `detail.state` or `detail.values`. Note that it is attached to `document`, not `window`.

When cookie sync is enabled, the bootstrap reads the configured cookie and applies only exact configured grant/deny values. An unknown value leaves the default unchanged.

## GTM Noscript Rule

The iframe is printed only when GTM is enabled, the ID is valid, and strict default-denied Consent Mode is not active. With consent enabled and default denied, the iframe is intentionally omitted because a noscript request cannot wait for JavaScript consent updates. This is expected, not a missing-hook error.

## Built-in Cookie Prompt

The prompt is a standalone `role=dialog` block in the footer, not the theme modal overlay. It appears only when enabled, text is non-empty and the configured cookie does not already exist. Accept/reject:

1. writes the configured value for one year with `path=/; SameSite=Lax`;
2. calls the corresponding all-consent helper when available;
3. dispatches `sp:consent:update` on `document`;
4. animates and hides the dialog.

The cookie is not marked `Secure` or `HttpOnly` because frontend JavaScript must read it. Changing the key or grant/deny values makes existing cookies no longer match. There is no built-in “reopen preferences” button; a privacy-settings UI should delete/change the cookie and dispatch the appropriate update.

This component is technical infrastructure, not legal advice. Consent requirements, copy, categories and proof of consent must be reviewed for the site’s jurisdictions and vendors.

## Custom Snippets

Snippets may contain a restricted set of `script`, `noscript`, `iframe`, `img`, `link`, `meta`, `style`, `a`, `div` and `span` elements with explicitly allowed attributes. Inline event handlers and unlisted elements/attributes are removed by `wp_kses()`.

Use locations as follows:

- **Head:** verification meta, preconnect/link resources, essential vendor bootstrap.
- **Body open:** noscript fallbacks or vendor markup required immediately after body.
- **Footer:** chat widgets, delayed pixels and non-critical scripts.

Only trusted administrators should edit snippets. Sanitization reduces accidental markup risk but does not make arbitrary analytics JavaScript safe, private or compliant. If a vendor requires syntax removed by the allowlist, implement a reviewed code integration rather than weakening the global sanitizer.

## Admin Security

Settings save through Ajax action `sp_tag_manager_save`, protected by nonce `sp_tag_manager_admin` and `manage_options`. IDs, JavaScript identifiers, timing values, cookies, class lists, text and snippets are sanitized server-side before `update_option(..., false)`.

## Verification Procedure

1. Save a valid `GTM-...` ID and keep logged-in suppression enabled.
2. Open a clean logged-out private window and inspect page source.
3. Confirm `sp-consent-mode` appears before `sp-gtm-loader`.
4. For delayed strategies, verify no `gtm.js` request before the configured trigger.
5. Accept and reject separately; inspect the cookie and dataLayer consent commands.
6. Reload and confirm cookie synchronization hides the prompt and applies the stored choice.
7. Test the site without JavaScript and understand whether the noscript rule meets the project’s policy.
8. Validate events in GTM Preview/Tag Assistant and the browser network panel.

## Troubleshooting

- **No output:** check master switch, logged-in suppression, request type and `sp_tag_manager_should_output` filters.
- **Invalid GTM ID disappears after save:** only the `GTM-...` container format is accepted; GA measurement IDs are not GTM IDs.
- **GTM never loads:** interact with the page, call `spLoadGtm()`, inspect CSP/ad blockers and check the ten-second post-load fallback.
- **Delay value has no effect:** it applies only to `after_delay`; interaction mode uses interaction plus fixed fallback.
- **Noscript missing:** expected when Consent Mode is enabled with default denied; otherwise verify `wp_body_open()`.
- **Consent event ignored:** dispatch on `document`, and use `detail.state` or `detail.values`.
- **Modal never returns:** delete the configured consent cookie or change its key.
- **Snippet is altered:** markup or attributes are outside the allowlist.
- **Duplicate tracking:** remove equivalent hard-coded/theme/vendor snippets and ensure only one GTM installation remains.
- **Cached old config:** purge page/CDN caches after every output change.
