# Development Mode Panel

Frontend diagnostic panel for administrators working on the theme. It collects the request, template, WordPress query, ACF/meta, enqueued resources, media and environment data that would otherwise require several separate developer tools.

## Exact Activation Rules

The panel is attached to `wp_footer` at priority `9999`, but renders only when all conditions are true:

1. the PHP constant `DEV_MODE` is defined and truthy;
2. the visitor is logged in;
3. the current user has the `manage_options` capability.

Therefore enabling `WP_DEBUG` alone does not display it, and anonymous visitors never receive the panel markup. The active frontend template must call `wp_footer()` for the hook to run.

Example development configuration:

```php
define('DEV_MODE', true);
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Do not set `DEV_MODE` on a public production environment longer than required. Capability gating protects anonymous traffic, but administrators can still expose sensitive paths and request details in screenshots or JSON exports.

## Data Collection Lifecycle

The panel runs late, when WordPress has resolved the main query and most assets have been registered or printed. It reads global `$template`, `$wp_query`, `$wp_scripts`, `$wp_styles` and `$wpdb`; the queried post and user; request/server data; theme/plugin asset queues; and selected filesystem metadata for same-host files.

Remote assets are not downloaded. Local URL-to-file resolution is restricted to the current site host and maps paths under `ABSPATH`; missing or external files remain URL-only entries. This makes byte counts useful estimates, not a replacement for the browser network waterfall.

## Panel Sections

### Request and Template

- resolved PHP template filename;
- request URL, HTTP method and WordPress conditional functions that returned true;
- queried object, post ID/type/status, parent and author context;
- main query counts such as found posts and maximum pages;
- current user login and roles;
- page-template slug and relevant body/request context.

This section is the fastest way to confirm whether WordPress selected the expected template and archive/singular condition.

### WordPress, PHP and Database

- WordPress and PHP versions, environment constants and memory limits;
- timer value and estimated query duration;
- database/query information available through the current request globals;
- `DEV_MODE` and `WP_DEBUG` states;
- server/runtime facts useful when behavior differs between local and staging.

The values describe the current request only. They are not historical monitoring data and should not be compared across cached and uncached requests without noting the cache state.

### Scripts and Styles

For registered/enqueued JavaScript and CSS the panel records handle, resolved URL, dependencies, version/media metadata, local/external status and local file size when resolvable. It also gathers inline before/after/data blocks and sorts large inline payloads by byte size.

The third-party summary groups external hosts and asset counts. A high count points to likely connection, privacy or Core Web Vitals overhead, but does not prove that every resource completed loading in the browser.

### Images, Fonts and CSS Backgrounds

- `<img>` elements found in the current post content plus the featured image;
- declared dimensions, alt text, URL, local file size and external-host status;
- font URLs and background-image URLs extracted on a best-effort basis from readable local CSS;
- aggregate local font bytes and large-image warnings.

The image list is intentionally not a complete DOM audit: dynamically inserted elements and arbitrary template HTML outside inspected content may be absent. Browser DevTools remains authoritative for final rendered resources.

### ACF and Meta

When ACF is available, the panel reads fields associated with the current object and exposes relevant post/meta context. This helps distinguish “field exists but is empty” from “wrong post/template is being inspected.” Large or private field values should be reviewed before exporting.

### Performance Hints

The module derives heuristic warnings from request counts, third-party domains, local asset sizes, large images, fonts and inline payloads. These hints identify investigation targets; they are not Lighthouse scores and do not measure layout, main-thread execution or real-user performance.

## JSON Snapshot

The **JSON** button serializes a compact snapshot in the browser and downloads `wp-debug-{timestamp}.json`. It includes page/request identity, template, user-facing diagnostics, counts and summarized asset/media/performance data available at render time.

Before sharing the file, inspect it for:

- local filesystem paths and hostnames;
- request URLs or query parameters;
- user login/role information;
- ACF/meta and environment values;
- third-party service URLs.

The snapshot is a support artifact, not a restore file and not an automated bug report.

## Interface State and Keyboard Behavior

The UI remembers its selected tab, minimized/closed state and dragged position in browser `localStorage`. A year-long SameSite=Lax cookie named `sp_dbg_closed` lets PHP render only the compact launcher on future requests. Closing the panel does not disable diagnostics globally.

Opening the launcher reloads the current URL with `sp_dbg_open=1`, which overrides the closed-cookie state for that request. The panel supports keyboard interaction for tab selection and its open/close controls; its position is draggable with a pointing device.

To reset local UI state, clear the site’s local storage and the `sp_dbg_closed` cookie, then reload.

## Performance Characteristics

The panel performs filesystem lookups, scans asset queues, parses content and readable CSS, and builds a substantial block of HTML/CSS/JS. It therefore changes the very request it is measuring. Use it for diagnosis and relative comparisons, not for production benchmarks.

For trustworthy performance testing:

1. diagnose configuration and resource composition with the panel;
2. turn `DEV_MODE` off;
3. test logged out in a clean browser profile;
4. measure with the real cache/CDN configuration.

## Troubleshooting

- **Panel is absent:** confirm `DEV_MODE === true`, the user is logged in with `manage_options`, and the template calls `wp_footer()`.
- **Only a small launcher appears:** the `sp_dbg_closed=1` cookie is active; click it or add `sp_dbg_open=1` once.
- **Wrong template shown:** clear page/cache layers and confirm the request is not being served before WordPress.
- **Asset size is empty:** the URL may be external, map outside `ABSPATH`, contain a transformed CDN path, or reference a missing file.
- **Images are missing from the list:** dynamically rendered/template-level images are outside the content/featured-image scan.
- **Fonts/backgrounds are incomplete:** only readable CSS and recognizable `url(...)` extensions are inspected.
- **Panel affects page layout:** it is a fixed, high-z-index developer overlay; close/minimize it or disable `DEV_MODE` for visual QA.
- **JSON download contains stale data:** reload the exact page/state before exporting; the snapshot is generated from server data embedded in that response.
