# Social Share

Configurable frontend sharing component for selected public post types. It stores an ordered network collection separately from visual/global settings, supports SVG or Media Library icons, provides per-post visibility and renders through PHP or shortcode.

## Storage and Defaults

| Storage | Purpose |
| --- | --- |
| `sp_share_cfg` | Label, enabled post types, CSS output and visual dimensions. |
| `sp_share_networks` | Ordered network rows, enabled state, templates, colors and icons. |
| `sp_share_link_preset_added` | One-time migration marker for the copy-link preset. |
| `_sp_share_enabled` post meta | Per-post visibility; string `0` disables output. |

Default post types are `post` and `page`. The default enabled networks are Link, Facebook, LinkedIn and X; Instagram, WhatsApp, Telegram, Pinterest and Email are supplied disabled. Reddit is available as an add-network preset. Existing installations receive the Link row once through the migration marker.

## Global Settings Reference

| Key | Default | Sanitized range / effect |
| --- | ---: | --- |
| `label` | `Share to social media` | Plain text exposed as the component `data-title`. |
| `post_types` | `post,page` | Sanitized public post-type keys. |
| `output_styles` | `1` | Loads bundled CSS and generated dynamic CSS. |
| `btn_size` | `52` | Desktop button size, 20–200; converted to rem by dividing by 10. |
| `btn_size_min` | `40` | Mobile button size, 20–200. |
| `icon_size` | `22` | Desktop icon size, 8–120. |
| `icon_size_min` | `16` | Mobile icon size, 8–120. |
| `border_radius` | `12` | Radius, 0–100. |
| `border_width` | `1` | Width, 0–10. |
| `border_opacity` | `20` | Generated fallback border opacity, 0–100%. |
| `bg_opacity` | `12` | Generated fallback background opacity, 0–100%. |
| `gap` | `10` | Gap between buttons, 0–60. |

The mobile breakpoint in generated CSS is `767.98px`. Hover fallback increases background opacity by 10 percentage points and border opacity by 20, capped at 100.

Disable **Output frontend CSS** only when the theme supplies complete styles for `.sp-share`, `.sp-share__btns`, `.sp-share__btns-item`, `.sp-share__btn` and accessible hidden text. The copy-link JavaScript may still be registered when Link is enabled.

## Network Row Schema

Each ordered row contains:

| Field | Meaning |
| --- | --- |
| `key` | Sanitized slug; `link` activates copy-to-clipboard behavior. |
| `label` | Visible/accessibility title. |
| `enabled` | Requires a non-empty URL template as well. |
| `url` | Share endpoint containing supported placeholders. |
| `color` | Accent fallback. |
| `background_color` | Optional explicit background CSS color. |
| `icon_color` | Icon/currentColor value. |
| `border_color` | Optional explicit border color. |
| `icon_type` | `svg` or `img`. |
| `icon_svg` | Sanitized inline SVG markup. |
| `icon_img` / `icon_img_id` | Media URL and attachment ID. |

Accepted colors are hex, `transparent`, `currentColor`, or a restricted `rgb/rgba/hsl/hsla(...)` expression. Invalid values fall back safely. SVG markup is filtered to a small shape/attribute allowlist; scripts, event attributes and arbitrary elements are removed. Image URLs pass through `esc_url_raw()`.

## URL Templates and Encoding

Supported placeholders:

- `{url}` — `rawurlencode()` of the permalink;
- `{title}` — `rawurlencode()` of the post title;
- `{url_raw}` — unencoded permalink;
- `{title_raw}` — unencoded title.

Example:

```text
https://example.com/share?u={url}&text={title}
```

Use encoded placeholders inside query parameters. Raw placeholders exist for schemes/providers that require an unencoded value, but are easier to break with spaces, ampersands or non-ASCII characters.

Legacy LinkedIn `shareArticle` templates are normalized to `sharing/share-offsite`; legacy Twitter intent URLs are normalized to the X endpoint. The `link` key ignores the configured endpoint during output and points at the raw permalink for fallback navigation.

## Render Eligibility

`render()` returns an empty string when:

- no valid post ID is available;
- the post type is not selected;
- `_sp_share_enabled` is exactly `0`;
- no enabled network has a non-empty URL;
- all enabled rows produce an empty href.

The editor meta box appears only for globally selected post types. Its default is enabled when no meta exists. Save requests verify the post nonce, skip autosaves and require `edit_post`.

## Frontend Rendering

PHP usage echoes markup:

```php
<?php sp_social_share(); ?>
<?php sp_social_share(123); ?>
```

Shortcode usage returns markup:

```text
[sp_social_share]
[sp_social_share id="123"]
```

Each row renders as a list item and link with CSS custom properties for accent/icon/background/border. External share links open in a new tab with `noopener noreferrer`; `mailto:` and Link do not. Each control includes a visually hidden label.

When an image icon has a valid attachment ID and the theme `display_image()` helper exists, rendering goes through that responsive-media helper. Otherwise a normal escaped `<img>` is printed. Inline SVG is output from the already sanitized stored value.

## Copy Link Behavior

The `link` network gets `data-sp-share-copy={permalink}`. A delegated click listener first uses `navigator.clipboard.writeText()`. If unavailable or rejected, it selects a temporary readonly textarea and calls `document.execCommand('copy')`; if that also fails, normal navigation to the permalink is used.

Successful copy adds `.is-copied` and `data-copied=1` for 1.4 seconds so the theme can display feedback. Clipboard API normally requires HTTPS or localhost.

## Asset Loading

On `wp_enqueue_scripts`, assets are loaded only for a valid queried post that can render and has enabled networks. Bundled CSS is versioned by its file modification time, then dynamic sizing rules are appended inline. Copy JavaScript is a small inline script registered only when the Link network is active.

Calling the shortcode for an arbitrary ID on a page whose queried post was not eligible can render HTML after the normal enqueue decision. When using cross-post shortcodes in unusual templates, explicitly enqueue/provide the component CSS or keep the containing page type enabled.

## Admin Ajax API

| Action | Writes | Protection |
| --- | --- | --- |
| `sp_share_save_settings` | `sp_share_cfg` | `sp_share_admin` nonce + `manage_options`. |
| `sp_share_save_networks` | `sp_share_networks` | Same nonce + capability. |

Network order is the submitted array order. Both endpoints sanitize every field server-side; do not rely on the JavaScript controls as validation.

## Adding a Custom Network

1. Click **Add network** and choose a preset or blank row.
2. Assign a unique lowercase key and descriptive label.
3. Paste the provider’s current web-share endpoint using encoded placeholders.
4. Select inline SVG or a Media Library icon.
5. Set accent/icon/background/border colors.
6. Enable the row, drag it into order and **Save networks**.
7. Test a title containing spaces, `&`, quotes and non-Latin characters on desktop and mobile.

Services such as Instagram that have no general web-share composer should remain disabled or be treated as normal profile links, not assumed to receive the current page.

## Troubleshooting

- **Nothing renders:** verify post type, per-post toggle, enabled network and non-empty URL template.
- **Buttons are unstyled:** enable output styles or implement all component selectors and CSS variables in the theme.
- **Clipboard fails:** test HTTPS/localhost and browser permission; the module should fall back to legacy copy or navigation.
- **Share text is malformed:** use `{url}`/`{title}` in query parameters rather than raw placeholders.
- **Custom SVG disappears:** unsupported elements/attributes were removed by the allowlist; use simple paths/shapes.
- **Image icon is broken:** reselect a valid attachment and confirm its file/URL still exists.
- **Meta box absent:** the current post type is not selected globally.
- **Old LinkedIn/X URL persists in admin:** output is normalized at render time; save the modern preset to update the stored row.
- **Shortcode HTML exists but CSS did not load:** the containing queried post failed the enqueue eligibility check; enqueue styles explicitly for this special case.
