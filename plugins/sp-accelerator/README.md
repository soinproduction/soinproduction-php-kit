# SP Accelerator v2

SP Accelerator is the theme-native performance layer for Targetized WordPress sites. It combines a conservative anonymous page cache, an optional early cache drop-in, a verified cache warmer, an optional SQLite object cache, and frontend optimizations that understand this theme's asset pipeline.

It is a clean-room implementation. It contains no Seraphinite code, license check, telemetry, cloud service, remote optimization API, or binary dependency.

Detailed documentation is also available in [English](README.en.md) and [Russian](README.ru.md).

## Performance objective

The engineering acceptance target is a Lighthouse score of **90 or higher on the median of repeated tests of agreed, warmed control pages**, together with passing field Core Web Vitals. This is a target, not a promise that every page will always be green: hosting latency, page content, third-party scripts, consent tools, traffic, device class, and network conditions remain outside the module's control.

Measure cold and warm requests separately. Use Lighthouse for regression detection and Chrome UX Report, Search Console, or equivalent real-user monitoring for field Core Web Vitals.

## Architecture

| Layer | What v2 does |
| --- | --- |
| Request policy | Admits only safe anonymous `GET`/`HEAD` HTML requests and rejects authorization, range, request no-cache, private cookies, personalized WordPress states, and unsafe response headers. |
| Runtime page cache | Captures cacheable HTML, writes HTML/GZIP/metadata atomically, preserves selected security headers, and serves conditional `HIT`/`STALE` responses. |
| Early page-cache drop-in | Optional `wp-content/advanced-cache.php` serves the same cache before WordPress bootstraps. |
| Generation SWR | A purge switches to a new generation in O(1); bounded stale data may serve concurrent visitors while one elected request synchronously rebuilds the entry. |
| Cache warmer | Manual and automatic warming use a locked WP-Cron queue, per-request short-lived HMAC tokens, no redirects, and count only HTTP 200 plus `X-SP-Cache: MISS` as success. |
| Persistent object cache | Optional `wp-content/object-cache.php` stores WordPress objects in a protected SQLite WAL database. |
| Server rules | `SP_Accelerator_Server` explicitly installs/removes an owned marker in the site's root `.htaccess` for static-asset browser caching and Brotli/GZIP compression on Apache/LiteSpeed. |
| Asset controller | Can load main and non-critical section CSS asynchronously, limit font preloads, add external-origin hints, and delay eligible section scripts while preserving their resolved order. |
| Markup optimizer | Uses the WordPress HTML tokenizer to prioritize an LCP image, add safe local image dimensions, set asynchronous image decoding, lazy-load unconfigured iframes, and reduce non-autoplay video preload. |
| Storage protection | Requires dedicated `sp-accelerator`-named roots, writes deny rules for Apache and IIS plus a defensive `index.php`, and hardens SQLite files separately. |

The admin UI is under **Settings → Accelerator**.

## Safe page caching

The page cache is deliberately conservative:

- only anonymous `GET` and `HEAD` requests are considered;
- `Authorization`, byte ranges, `Cache-Control: no-cache/no-store/max-age=0`, `Pragma: no-cache`, previews, REST/Ajax/cron, searches, feeds, 404s, password-protected content, and configured paths bypass it;
- logged-in, password, comment, WooCommerce, security, and multilingual cookie markers are always excluded and can be extended in the settings;
- safe cookie mode is enabled by default: any otherwise unknown cookie bypasses shared page cache, while only configurable analytics prefixes such as `_ga`, `_gcl_`, `_fbp`, and Microsoft Clarity cookies are allowed;
- unknown query parameters bypass cache lookup;
- known marketing parameters may reuse an already-built clean canonical entry, but a tracking URL is never allowed to seed that entry;
- responses with `Set-Cookie`, `private`, `no-cache`, `no-store`, `Pragma: no-cache`, a non-HTML content type, or an unsupported `Vary` value are not stored;
- cached HTML that appears to contain a WordPress nonce receives a bounded lifetime derived from `nonce_life`.

Entries contain HTML, optional pre-compressed GZIP, and metadata with per-entry TTLs, a SHA-256 content identity, content type, and an allowlist of response/security headers. ETags include generation and content identity, and identity/GZIP variants remain distinct. `If-None-Match`, `If-Modified-Since`, `HEAD`, `Age`, and `Vary: Accept-Encoding` are supported.

## Current/previous generation stale-while-revalidate

Invalidation immediately creates a new generation and remembers the previous one. If the new generation has no entry yet, the previous entry is eligible only while both generation grace and that entry's own hard limit are valid. The hard limit is the stored fresh TTL plus stale TTL; generation grace never extends it.

An owner-token lock with a 120-second lease elects one request. The elected request always continues through the normal synchronous WordPress render and store path. While it runs, concurrent requests may serve a coherent current- or previous-generation stale entry with its stored headers, but only inside that entry's fresh-plus-stale hard limit.

On a cold miss, a non-owner waits up to five seconds for the elected request to publish the current entry. Hard-expired requests use the same collapse wait, but never serve the expired entry as a busy fallback: if no valid replacement appears, WordPress renders the request normally. Only the matching lock owner token may release the lock.

## Verified warmer

Manual **Warm site** starts a new generation, discovers same-host public URLs, and builds one entry immediately. Automatic warming can queue after cache invalidation. Remaining work runs in small, lock-protected WP-Cron batches.

Discovery includes the home page, published singular content, public post-type archives, taxonomy archives, and their bounded pagination. Its 10,000-URL budget is enforced while querying and paginating, not after an unbounded intermediate list. The `sp_accelerator_warm_urls` filter can add project-specific landing pages. The queue is tied to its generation and rediscovers topology after another invalidation.

Each loopback sends `X-SP-Cache-Warm` as a timestamp plus HMAC derived from the protected `warm_token`. The HMAC is bound to the exact canonical URL and active generation, expires after five minutes, and is compared with `hash_equals` by both the early drop-in and runtime layer. An expired token, a token copied to another URL/generation, or a forged/static header follows the ordinary cache path and cannot force an expensive WordPress render.

The warmer sets redirect following to zero and accepts only an exact HTTP 200 response with `X-SP-Cache: MISS`. Redirects, `HIT`, `STALE`, other statuses, loopback errors, and pages that did not enter page cache are failures rather than false successes.

On a theme switch, a persistent runtime-disable flag is written before early config, warmer, or page-entry cleanup. Runtime feature and response-storage checks see that flag, so an output callback already in flight cannot repopulate entries after they are removed. Cancellation also advances the warmer epoch, preventing an old worker from publishing queue progress or scheduling another batch.

## Frontend optimizations

SP Accelerator integrates with the theme's registered handles instead of concatenating or blindly rewriting arbitrary plugin assets.

- Main CSS and non-critical section CSS can use the theme's asynchronous stylesheet pipeline; the critical hero stylesheet stays synchronous by default.
- Font preload is limited to the critical Regular and Bold WOFF2 files and remains filterable; Bold matches the 700-weight hero heading in critical CSS.
- Resource hints are derived from queued external scripts and styles and are capped to avoid speculative-connection overload.
- Main-script preload is opt-in so the browser is not forced to fetch a low-priority resource too early.
- Eligible theme section/module scripts are delayed until interaction, section proximity, idle time, or the configured safety timeout. WordPress resolves dependencies first, the loader preserves document order, the hero module is excluded, and handles with inline before/after code or `async` are not delayed.
- The markup pass identifies a credible first LCP image inside `<main>`, sets `fetchpriority="high"` and eager loading, and injects a responsive image preload when one is not already present.
- Missing dimensions are added only for readable same-site raster files inside the WordPress root. For other images, the markup pass may add asynchronous decoding but leaves `loading` to the theme/native policy; unconfigured iframes use `loading="lazy"`; non-autoplay videos default to `preload="none"`.

These switches still require staging verification of navigation, sliders, forms, consent, analytics, and third-party embeds.

## Page-cache storage safety

Page and object cache roots must first pass a non-bypassable dedicated-directory check: the normalized path is absolute, has no `.`/`..` traversal segment, and its basename contains the delimited name `sp-accelerator` (for example `sp-accelerator-cache`). A cache root cannot equal or broadly contain `ABSPATH`, `WP_CONTENT_DIR`, the actual document root, or the system temporary root. A `*_WEB_PROTECTED` assertion never makes such a broad path valid.

Page-cache persistence requires positive proof on every server. The normalized cache path must be outside the actual document root and the WordPress roots checked by the module, or an administrator must explicitly assert a separately verified web-server deny with `SP_ACCELERATOR_CACHE_WEB_PROTECTED`. Without that assertion, an unresolved actual document root makes page cache fail closed. Auto-generated deny files are defense in depth and do not prove HTTP protection by themselves.

Prefer one private directory outside every web-accessible root; it becomes the default for both page and object cache:

```php
define( 'SP_ACCELERATOR_CACHE_DIR', '/absolute/private/path/sp-accelerator-cache' );
```

For WP-CLI, reverse-proxy, chroot, or unusual hosting contexts where `$_SERVER['DOCUMENT_ROOT']` is absent or does not name the real public root, define that real root explicitly:

```php
define( 'SP_ACCELERATOR_DOCUMENT_ROOT', '/absolute/path/to/public' );
```

If storage must remain under `wp-content`, first configure and directly verify a 403/404 deny for the exact cache URL, then acknowledge that verified protection with `SP_ACCELERATOR_CACHE_WEB_PROTECTED`. The constant is an assertion, not a firewall rule. Object-cache persistence in the same directory additionally requires its own verified `SP_ACCELERATOR_OBJECT_CACHE_WEB_PROTECTED` assertion.

For a reverse proxy, SP Accelerator does not trust client-supplied `X-Forwarded-Proto` by default. Prefer normal WordPress proxy configuration that sets `HTTPS`; use `SP_ACCELERATOR_TRUST_FORWARDED_PROTO` only when the header is stripped and set exclusively by a trusted proxy.

## SQLite object-cache storage safety

Object-cache persistence uses the same dedicated-name, broad-root rejection, and positive-proof policy. Its normalized storage path must be outside the actual document root and checked WordPress roots, or `SP_ACCELERATOR_OBJECT_CACHE_WEB_PROTECTED` must assert a deny that was verified independently. Otherwise installation is refused and an installed managed drop-in keeps SQLite persistence disabled.

The preferred fix is a writable private directory outside the web root, configured in `wp-config.php`:

```php
define( 'SP_ACCELERATOR_OBJECT_CACHE_DIR', '/absolute/path/outside/web-root/sp-accelerator-cache' );
```

Alternatively, explicitly deny HTTP access to `/wp-content/cache/sp-accelerator/`, verify that a direct request is blocked, and only then acknowledge that protection. For Nginx, for example:

```nginx
location ^~ /wp-content/cache/sp-accelerator/ {
    deny all;
}
```

```php
define( 'SP_ACCELERATOR_OBJECT_CACHE_WEB_PROTECTED', true );
```

This constant is an assertion, not a firewall rule. Never set it before the corresponding server deny is active and independently verified.

## Cache-root migration and theme switch

When `SP_ACCELERATOR_CACHE_DIR` moves page cache away from the legacy `wp-content/cache/sp-accelerator` root, configuration synchronization handles the old root before enabling the new one. A positively identified legacy config is first rewritten with `enabled: false`; only then are its `pages` entries removed. If the old root cannot be identified safely or disabled and cleaned, synchronization stops instead of publishing a second active root.

Legacy SQLite is handled separately from page entries. `object-cache.sqlite` and its WAL/SHM/journal sidecars are preserved when the legacy root is outside the confirmed web-exposed roots or has explicit verified protection. If that legacy root is confirmed web-exposed and object-cache protection is not asserted, the database and sidecars are removed; any failed removal makes migration fail closed.

A theme switch first persists runtime disable, then disables the early page-cache config, advances the warmer cancellation epoch, and removes all page entries. Because response storage rechecks runtime enablement, an in-flight response cannot recreate an entry after cleanup. The switch then removes only owned server rules and managed drop-ins; cleanup failures are logged.

## Static-asset cache and compression rules

The **Static assets / compression** card is separate from page-cache settings. On Apache or LiteSpeed, its explicit **Install .htaccess rules** action writes only a WordPress marker named `SP Accelerator` to the site's root `.htaccess`; it does not replace WordPress or foreign directives. Removal deletes only that owned marker.

The marker sets one-year immutable browser caching for CSS, JavaScript, WASM, and fonts; three-month browser caching for common raster/vector images; and text compression with Brotli when `mod_brotli` is available, falling back to GZIP/Deflate when it is not. The rules cannot install missing web-server modules.

They are **not installed automatically**. A read-only `.htaccess` is reported without attempting a write. Nginx does not read `.htaccess`, so the UI reports manual setup and the equivalent TTL/compression policy must be configured in Nginx or at the CDN.

## Installation

1. Deploy PHP Kit through one Composer-locked version.
2. Open **Settings → Accelerator**, save the required settings, and test logged-out requests before installing drop-ins.
3. Add the following to `wp-config.php` before WordPress' “stop editing” line:

   ```php
   define( 'WP_CACHE', true );
   ```

4. Use a dedicated absolute `SP_ACCELERATOR_CACHE_DIR` whose basename contains `sp-accelerator`; never point it at a broad WordPress, document, or system-temp root. Put it outside the actual document root, or install and verify the exact cache-directory deny before setting the page-cache assertion. Define `SP_ACCELERATOR_DOCUMENT_ROOT` when the server/CLI value is not the real public root. Then install or update the managed `advanced-cache.php`; the installer refuses unproven storage and foreign drop-ins.
5. On Apache/LiteSpeed, use the separate **Static assets / compression** card to install the root `.htaccess` marker after reviewing the existing file. On Nginx, configure equivalent rules manually; the button cannot make `.htaccess` effective there.
6. If PHP has the `sqlite3` extension and the cache directory is writable, install or update the managed `object-cache.php`. Its dedicated absolute path must also contain `sp-accelerator` and must not be a broad reserved root. Move it outside the actual document root, or install and verify an explicit deny for `/wp-content/cache/sp-accelerator/` before setting `SP_ACCELERATOR_OBJECT_CACHE_WEB_PROTECTED`. A foreign object-cache drop-in is never overwritten.
7. Clear/warm the page cache, inspect failed warmer URLs, and clear any CDN or reverse-proxy cache.

Without `WP_CACHE`, the runtime page cache still works after WordPress boots, but the early drop-in cannot run. Uploading only the theme does not update the two installed files in `wp-content`; use the UI after every module update when either card reports an outdated drop-in.

Do not deploy a local `wp-content/cache/sp-accelerator` directory. The module creates and protects storage on the destination host.

## Verification

Request the same logged-out URL twice and inspect its headers:

```bash
curl -I https://example.com/control-page/
curl -I https://example.com/control-page/
```

Expected lifecycle:

- `X-SP-Cache: MISS` — WordPress rendered and stored the response;
- `X-SP-Cache: HIT` — a fresh cached response was served;
- `X-SP-Cache: STALE` — a bounded stale response was served while revalidation was coordinated.

Also verify HTML identity, CSP/security headers, logged-in behavior, cart/account routes, forms and nonces, GZIP negotiation including `gzip;q=0`, and the warmer's failure list. Run performance tests only after the control pages show a valid cache lifecycle.

## Troubleshooting

- **Page/object storage is not proven safe:** first verify a dedicated absolute basename containing `sp-accelerator` and that the path is not a broad reserved root. Then verify `SP_ACCELERATOR_DOCUMENT_ROOT`; prefer storage outside that actual public root, or verify the exact deny before setting the matching `*_WEB_PROTECTED` assertion.
- **Warm URL fails with a redirect, HIT, or STALE:** expected; the warmer does not follow redirects and accepts only HTTP 200 plus `X-SP-Cache: MISS`. Queue the final canonical URL and inspect why the authenticated request did not rebuild it.
- **Object cache is installed but not persistent:** also check PHP `sqlite3`, write permissions, the current drop-in hash, and the resolved storage path.
- **Static rules show manual on Nginx:** expected; configure asset TTL and Brotli/GZIP in Nginx or the CDN because `.htaccess` has no effect.

## Emergency recovery

If WordPress fails immediately after deployment:

1. Change `sp-accelerator` to `_sp-accelerator` in the project's PHP Kit configuration.
2. If the error remains, temporarily rename only the managed `wp-content/object-cache.php` and `wp-content/advanced-cache.php` files.
3. Read the exact fatal error from `wp-content/debug.log` or the hosting PHP log.
4. Upload one complete module version, restore the directory name, then reinstall/update both managed drop-ins from **Settings → Accelerator**.

Never overwrite or remove a foreign drop-in through SP Accelerator.
