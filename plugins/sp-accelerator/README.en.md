# SP Accelerator v2

SP Accelerator is the Targetized theme's native WordPress performance layer. Version 2 combines safe anonymous page caching, early cache delivery, bounded stale-while-revalidate, verified warming, optional SQLite object caching, and theme-aware frontend loading.

It is a clean-room implementation and does not contain Seraphinite code, telemetry, license checks, cloud optimization, or a remote API.

## Honest performance target

The acceptance target is **Lighthouse ≥90 on the median of repeated runs against agreed, warmed control pages**, plus passing field Core Web Vitals. It is not possible for a WordPress module to guarantee an always-green score for every URL and every test. Hosting TTFB, templates and content, third-party scripts, consent systems, traffic, network quality, and test variance still affect the result.

Use Lighthouse as a repeatable lab-regression signal. Judge the user outcome with field Core Web Vitals from Chrome UX Report, Search Console, or a comparable real-user monitoring system. Record cold and warm results separately.

## Components

| Component | Responsibility |
| --- | --- |
| `SP_Accelerator_Config` | Sanitizes settings, manages current/previous generations, writes the early drop-in JSON config, and protects the cache root. |
| `SP_Accelerator_Request` | Applies transport, host, path, query, cookie, and WordPress-state cache policy. |
| `SP_Accelerator_Cache` | Captures cacheable HTML, writes atomic entries, serves runtime `HIT`/`STALE` responses, and coordinates revalidation. |
| `SP_Accelerator_Dropin` | Installs and owns `wp-content/advanced-cache.php` for pre-WordPress cache hits. |
| `SP_Accelerator_Warmer` | Uses a generation-aware, locked WP-Cron queue, URL-bound short-lived HMACs, no redirects, and verifies HTTP 200 plus `X-SP-Cache: MISS`. |
| `SP_Accelerator_Object_Cache` | Installs and manages the protected SQLite-backed `wp-content/object-cache.php`. |
| `SP_Accelerator_Server` | Explicitly installs/removes the owned `SP Accelerator` marker in the root `.htaccess` for Apache/LiteSpeed static-asset caching and compression. |
| `SP_Accelerator_Assets` | Controls font/script preloads, asynchronous styles, resource hints, and delayed theme scripts. |
| `SP_Accelerator_Markup` | Uses the WordPress HTML tokenizer for LCP/media attributes, image dimensions, asynchronous image decoding, lazy iframes, and conservative video preload. |
| `SP_Accelerator_Admin` | Exposes settings and nonce-protected maintenance actions under **Settings → Accelerator**. |

The main option is `sp_accelerator_settings`. The early drop-in receives only its sanitized runtime subset through `config.json` in the resolved cache root (by default `wp-content/cache/sp-accelerator`).

## Safe page-cache policy

### Before WordPress output

Only anonymous `GET` and `HEAD` requests can use page cache. The runtime and early drop-in reject:

- an `Authorization` header or PHP-auth credentials;
- range requests;
- request `Cache-Control: no-cache`, `no-store`, or `max-age=0` and `Pragma: no-cache`;
- preview/customizer bypass arguments, Ajax, cron, REST, feeds, searches, 404s, password-protected content, and `DONOTCACHEPAGE`;
- a host other than the exact configured `home_url()` or `site_url()` host and port;
- apparent static-file paths and configured excluded path prefixes or regular expressions;
- configured private cookie-name markers, including WordPress login/security/password/comment, WooCommerce cart/session, Wordfence, and common multilingual cookies;
- by default, every other unknown cookie unless its name begins with a configured safe analytics prefix;
- query arguments other than the known marketing parameters `utm_*`, `gclid`, `fbclid`, `msclkid`, `_ga`, `mc_cid`, and `mc_eid`.

Marketing parameters are omitted from cache identity, but they cannot poison the canonical page: a request with any query string may reuse a previously built clean entry and is never allowed to seed it.

Strict unknown-cookie bypass is enabled by default. The initial safe-prefix list contains only common analytics identifiers such as `_ga`, `_gid`, `_gat`, `_gac_`, `_gcl_`, `_fbp`, `_clck`, and `_clsk`. Prefixes mean prefix matches, not exact names. Add a prefix only when that cookie is guaranteed not to change server-rendered HTML; never allow login, cart, currency, pricing, location, experiment, or language cookies.

### Before storage

The rendered response must be HTTP 200, valid-looking HTML of at least 256 bytes, and contain a closing `</html>`. SP Accelerator does not store a response that sends:

- `Set-Cookie`;
- `Cache-Control: private`, `no-cache`, or `no-store`;
- `Pragma: no-cache`;
- a content type other than HTML;
- `Vary` on anything except `Accept-Encoding`.

Selected security and representation headers are saved in metadata and replayed on a hit, including CSP, content language, cross-origin policies, `Link`, permissions/referrer policy, HSTS, frame/content-type protection, and robots instructions. HTML that appears to contain a WordPress nonce gets a reduced fresh-plus-stale lifetime derived from `nonce_life`.

## Cache files and response lifecycle

The canonical scheme, allowed host, and path are hashed with SHA-256 and sharded below the active generation:

```text
wp-content/cache/sp-accelerator/pages/{generation}/{hash[0:2]}/{hash[2:4]}/
  {hash}.html
  {hash}.html.gz
  {hash}.json
  {hash}.lock
  {hash}.write-lock
```

HTML, optional GZIP, and metadata are published atomically under a per-entry write lock. Metadata contains the canonical URL, creation time, byte size, SHA-256 content identity, effective TTLs, content type, and safe response headers. ETags include generation and content identity, while GZIP and identity variants remain distinct; `HEAD`, `If-None-Match`, `If-Modified-Since`, `Last-Modified`, `Age`, and `Vary: Accept-Encoding` are supported. `gzip;q=0` is respected.

Response status headers are:

- `X-SP-Cache: MISS` — WordPress rendered and stored the response;
- `X-SP-Cache: HIT` — a fresh entry was served;
- `X-SP-Cache: STALE` — a bounded stale entry was served during coordinated revalidation.

The v2 defaults are one hour fresh, six additional hours stale, and one hour of previous-generation grace. Browser cache lifetime is independently configurable and defaults to revalidation. Change these values only after checking nonce lifetime, editorial freshness, traffic, and upstream-cache behavior.

## Current/previous generation SWR

A full purge is O(1): it creates a new current generation, remembers the old current generation as previous, and records the switch time. It does not expose a half-deleted cache tree.

If the current generation has no entry, a previous-generation entry is eligible only while both generation grace and that entry's own hard limit remain valid. The hard limit is its stored fresh TTL plus stale TTL; generation grace never extends it.

An owner-token lock with a 120-second lease elects one request. The elected request always follows the normal synchronous WordPress render and store path. While it runs, concurrent requests may serve coherent stale HTML and its stored headers from the current or eligible previous generation, but only inside that entry's fresh-plus-stale hard limit.

On a cold miss, a non-owner waits up to five seconds for the elected request to publish the current entry. A hard-expired request uses the same collapse wait but never receives the expired entry as a busy fallback: if no valid replacement appears, WordPress renders normally. Only the matching owner token can release the lock.

Old generations are removed by the scheduled cleanup only after their useful grace window has passed.

## Early `advanced-cache.php`

Runtime caching works at `template_redirect`, but WordPress has already bootstrapped by then. The managed `advanced-cache.php` reads the small JSON configuration and static cache entry before WordPress loads, which is the preferred path for warm TTFB.

The drop-in mirrors the runtime request policy, current/previous-generation lookup, metadata/header replay, GZIP negotiation, conditional requests, and lock behavior. The installer verifies ownership and refuses to overwrite a foreign `advanced-cache.php`.

WordPress loads this drop-in only when `wp-config.php` contains:

```php
define( 'WP_CACHE', true );
```

Place it before the “stop editing” line. The module intentionally does not edit `wp-config.php`.

## Verified manual and automatic warming

Manual **Warm site** purges into a new generation, discovers same-host URLs, stores state in `sp_accelerator_warm_state`, and processes one URL immediately. If automatic warming is enabled, a purge schedules discovery and warming through WP-Cron. Work is protected by a short option lock so overlapping cron workers cannot consume the same queue.

Discovery includes:

- home and published singular content, excluding attachments;
- public post-type archives and bounded pagination;
- the posts page or home posts archive;
- non-empty public taxonomy terms and bounded pagination;
- extra URLs returned by `sp_accelerator_warm_urls`.

The deduplicated same-host queue is capped at 10,000 URLs, and this budget is enforced during post/term queries and pagination so discovery cannot first build an unbounded intermediate array. Small batches are scheduled roughly every five seconds. The queue is tied to the cache generation; if another invalidation changes that generation, progress resets and URL topology is rediscovered.

Each loopback sends `X-SP-Cache-Warm` as a timestamp plus HMAC derived from the protected `warm_token`. The HMAC covers the exact canonical URL and active generation, expires after five minutes, and is checked with `hash_equals` in both the early drop-in and runtime layer. An expired token, a token copied to another URL/generation, or a forged/static header follows the ordinary cache path and cannot force an expensive WordPress render.

Redirect following is disabled (`redirection: 0`). A URL succeeds only on an exact HTTP 200 response with `X-SP-Cache: MISS`; redirects, `HIT`, `STALE`, other statuses, loopback errors, and responses that did not enter page cache remain failures rather than false warmed results.

On a theme switch, SP Accelerator persists a runtime-disable flag before early config, warmer, or page-entry cleanup. Runtime feature and response-storage checks see that flag, so an output callback already in flight cannot repopulate entries after removal. Cancellation also advances the warmer epoch, preventing an old worker from publishing queue progress or scheduling another batch.

Project-specific URLs can be added safely:

```php
add_filter( 'sp_accelerator_warm_urls', function ( array $urls ): array {
    $urls[] = home_url( '/landing-page/' );
    return $urls;
} );
```

WP-Cron and loopback HTTP must work for automatic batches to finish.

## Page-cache storage safety

Page and object cache roots must first pass a non-bypassable dedicated-directory check: the normalized path is absolute, contains no `.`/`..` traversal segment, and its basename includes the delimited name `sp-accelerator` (for example `sp-accelerator-cache`). A cache root cannot equal or broadly contain `ABSPATH`, `WP_CONTENT_DIR`, the actual document root, or the system temporary root. A `*_WEB_PROTECTED` assertion never makes such a broad path valid.

Page-cache persistence requires positive proof on every server. The normalized storage path must be outside the actual document root and the WordPress roots checked by the module, or `SP_ACCELERATOR_CACHE_WEB_PROTECTED` must explicitly assert a web-server deny that was verified independently. Without that assertion, a missing, invalid, or ambiguous actual document root makes page cache fail closed.

The preferred configuration is one writable private directory outside every web-accessible root. It also becomes the default object-cache directory unless a more specific constant is set:

```php
define( 'SP_ACCELERATOR_CACHE_DIR', '/absolute/private/path/sp-accelerator-cache' );
```

WP-CLI, reverse proxies, chroots, and unusual hosting layouts may not provide the real public root in `$_SERVER['DOCUMENT_ROOT']`. Define it explicitly in that case:

```php
define( 'SP_ACCELERATOR_DOCUMENT_ROOT', '/absolute/path/to/public' );
```

If storage must remain at `/wp-content/cache/sp-accelerator/`, first add and reload an exact web-server deny, then verify direct requests to `config.json` and a known cache URL return 403/404. Only after that may `wp-config.php` acknowledge the protection:

```php
define( 'SP_ACCELERATOR_CACHE_WEB_PROTECTED', true );
```

This is a security assertion, not a deny rule. Object-cache persistence in that shared path additionally requires its own independently verified `SP_ACCELERATOR_OBJECT_CACHE_WEB_PROTECTED` assertion.

`X-Forwarded-Proto` is not trusted by default. Prefer WordPress proxy configuration that sets `HTTPS`; define `SP_ACCELERATOR_TRUST_FORWARDED_PROTO` only when a trusted reverse proxy strips the client header and sets its own value.

## SQLite persistent object cache

By default, the optional managed `object-cache.php` stores persistent WordPress cache groups in `wp-content/cache/sp-accelerator/object-cache.sqlite` using WAL mode; the storage constants above may move it. It supports expiry, multiple operations, atomic numeric updates, global and non-persistent groups, runtime/group flushing, multisite blog scopes, and a namespace derived from `WP_CACHE_KEY_SALT` or the installation identity.

The manager checks the bundled template hash and reports missing, current, outdated, unavailable, or foreign status. Installation requires PHP `sqlite3`; a foreign object-cache drop-in is never overwritten or removed. Database, WAL, and SHM files are hardened, and storage receives server deny rules.

Object-cache persistence follows the same dedicated-name, broad-root rejection, and positive-proof rule on every server. Its normalized directory must be outside the actual document root and checked WordPress roots, or `SP_ACCELERATOR_OBJECT_CACHE_WEB_PROTECTED` must assert a separately verified deny. Otherwise a new installation is refused and an installed managed drop-in leaves SQLite persistence disabled.

The preferred configuration places storage in a writable private directory outside every web-accessible root. Define an absolute path in `wp-config.php` before installing/updating the drop-in:

```php
define( 'SP_ACCELERATOR_OBJECT_CACHE_DIR', '/absolute/path/outside/web-root/sp-accelerator-cache' );
```

If storage must remain at `/wp-content/cache/sp-accelerator/`, add an explicit deny for that URL first and verify a direct request returns 403/404. Only after that protection is proven may `wp-config.php` acknowledge it. For Nginx, for example:

```nginx
location ^~ /wp-content/cache/sp-accelerator/ {
    deny all;
}
```

```php
define( 'SP_ACCELERATOR_OBJECT_CACHE_WEB_PROTECTED', true );
```

`SP_ACCELERATOR_OBJECT_CACHE_WEB_PROTECTED` is a security assertion, not a protection mechanism. Setting it without a verified deny disables the fail-closed guard and can expose SQLite, WAL, metadata, or cached page files.

Because WordPress loads `object-cache.php` very early, install it on staging first and verify both frontend and wp-admin immediately.

## Theme asset loading

SP Accelerator works with registered theme handles and does not combine generated CSS/JS or run a generic Critical CSS scraper.

- Main CSS and non-critical `section-*` styles can be sent through the theme's asynchronous CSS pipeline. Critical handles such as the hero remain synchronous.
- Critical font preload is limited to `DMSans-Regular.woff2` and `DMSans-Bold.woff2`; Bold matches the 700-weight hero heading in critical CSS. The list can be changed with `sp_accelerator_preload_fonts`.
- `preconnect`/`dns-prefetch` hints are derived from queued external script/style origins and capped at four.
- Main script preload is an explicit option rather than an unconditional default.
- Eligible theme module/npm scripts can be delayed. WordPress emits them in dependency-resolved order and the loader executes placeholders sequentially. The hero module, `async` scripts, and handles with inline before/after code are not delayed. `sp_accelerator_delay_script` can exclude another handle.
- Delayed scripts start on pointer/touch/keyboard/scroll interaction, when their section approaches within the observer margin, during idle time, or at the configured fallback (12 seconds by default). The loader emits `sp:accelerator:scripts-loaded` after completion.

Always retest menus, sliders, forms, analytics, consent code, navigation, and third-party widgets after changing these controls.

## Markup, LCP, and media

The finishing pass uses `WP_HTML_Tag_Processor`; it does not regex-rewrite script, style, or document text. When enabled it:

- finds the first credible non-icon image inside `<main>` and marks it `loading="eager"` and `fetchpriority="high"`;
- adds an image preload with `imagesrcset` and `imagesizes` when available and when no equivalent preload exists;
- adds intrinsic dimensions only when a same-site raster file resolves safely inside the WordPress root and is small enough to inspect;
- may add `decoding="async"` to non-LCP images but leaves their `loading` behavior to the theme/native policy, sets `loading="lazy"` on unconfigured iframes, and sets `preload="none"` on non-autoplay videos;
- leaves encoded responses, data URLs, SVG/ICO files, `<noscript>` content, and unsuitable logo/icon candidates alone.

This improves LCP discovery and reduces avoidable layout shift without inventing dimensions or rewriting image URLs.

## Root `.htaccess` rules for static assets

`SP_Accelerator_Server` is an explicit, separately controlled optimization for Apache and LiteSpeed. The **Static assets / compression** card in **Settings → Accelerator** does not silently modify the server configuration. Clicking **Install .htaccess rules** uses WordPress markers to add only `# BEGIN SP Accelerator` / `# END SP Accelerator` to the site's root `.htaccess`; existing WordPress and foreign directives are preserved. Removal deletes only that owned marker.

When the relevant server modules exist, the marker applies:

- one-year `public, immutable` browser caching to CSS, JavaScript/MJS, WASM, and font files;
- three-month public browser caching to AVIF, WebP, JPEG, PNG, GIF, SVG, and ICO images;
- Brotli compression for text/HTML/CSS/JavaScript/JSON/XML/SVG through `mod_brotli`;
- GZIP/Deflate through `mod_deflate` when Brotli is unavailable.

The marker cannot enable a missing Apache module. A read-only root `.htaccess` receives a `readonly` status and is not modified.

**Nginx does not read `.htaccess`.** On Nginx the card reports `manual`, installation is unavailable, and the equivalent cache TTL and Brotli/GZIP policy must be added to the Nginx/server/CDN configuration. Do not mistake the automatic cache-root deny files described below for these optional root performance rules.

## Storage protection

Configuration synchronization creates the cache root and publishes:

- Apache `.htaccess` rules that deny direct access and disable directory listing;
- an IIS `web.config` deny rule;
- a defensive `index.php` returning 404.

These generated files are defense in depth, not positive proof for PHP. The dedicated-name/broad-root gate always applies; after that, page and object persistence remain disabled unless their normalized paths are outside the actual document root and checked WordPress roots, or the administrator explicitly asserts an independently verified deny with the corresponding page/object `*_WEB_PROTECTED` constant. Unsafe web-path page payloads are removed during fail-closed synchronization; object-cache installation separately verifies protection and tightens SQLite file permissions.

Cache-root protection is automatic and security-related. It is independent of the separately installed root `.htaccess` performance marker.

## Legacy-root migration and theme-switch cleanup

When `SP_ACCELERATOR_CACHE_DIR` moves page cache away from the legacy `wp-content/cache/sp-accelerator` root, synchronization handles that old root before publishing the new active config. A positively identified legacy `config.json` is first rewritten with `enabled: false`; only then are its `pages` entries removed. If the old root cannot be identified safely or disabled and cleaned, synchronization stops instead of leaving two active roots.

Legacy SQLite is handled separately from page entries. `object-cache.sqlite` and its WAL/SHM/journal sidecars are preserved when the legacy root is outside the confirmed web-exposed roots or has explicit verified protection. When the legacy root is confirmed web-exposed and object-cache protection is not asserted, the database and sidecars are removed; failure to remove any of them makes migration fail closed.

On a theme switch, SP Accelerator first persists runtime disable, then disables early page-cache config, advances the warmer cancellation epoch, and removes all page entries. Response storage rechecks runtime enablement, so an in-flight response cannot recreate an entry after cleanup. It then removes only its owned root server marker and managed drop-ins; cleanup failures are logged. The epoch check prevents an already-running warmer from saving old queue state or scheduling a new batch after cancellation.

## Deployment

1. Deploy PHP Kit through one Composer-locked version. Do not merge different releases file by file.
2. Open **Settings → Accelerator**, save settings, and test an anonymous page without drop-ins.
3. Add `WP_CACHE` to `wp-config.php`. Use a dedicated absolute `SP_ACCELERATOR_CACHE_DIR` whose basename contains `sp-accelerator`; never point it at a broad WordPress, document, or system-temp root. Place it outside the actual document root or verify the exact cache-directory deny and set the matching assertion. Define `SP_ACCELERATOR_DOCUMENT_ROOT` when the server/CLI value is not the real public root; then install/update the managed `advanced-cache.php`.
4. Verify a `MISS` followed by a `HIT`, including security headers and GZIP behavior.
5. On Apache/LiteSpeed, use the separate **Static assets / compression** card to install the root `.htaccess` marker after reviewing the existing file. On Nginx, apply equivalent rules manually at the server or CDN.
6. Install/update the SQLite object cache only after checking extension and write permissions. Its dedicated absolute path must also contain `sp-accelerator` and must not be a broad reserved root. Configure it outside the actual document root, or install and verify an explicit deny for `/wp-content/cache/sp-accelerator/` before defining `SP_ACCELERATOR_OBJECT_CACHE_WEB_PROTECTED`.
7. Run **Warm site**, inspect its failed URLs, then run repeated Lighthouse tests against agreed warm control pages.
8. Purge any CDN or reverse-proxy cache after changing these layers.

The installed drop-ins live in `wp-content`, outside the theme. A theme upload cannot update them. Revisit **Settings → Accelerator** after module deployment and update any card marked outdated. Never copy a development `wp-content/cache` directory to production.

## Troubleshooting

- **Always MISS:** test logged out; inspect authorization, range/no-cache headers, query parameters, private cookies, exclusions, status, `Set-Cookie`, response cache policy, content type, and `Vary`.
- **No `X-SP-Cache`:** the master/page-cache switch may be off, the request may be ineligible, a foreign drop-in may own the path, or an upstream proxy may remove the header.
- **Warm queue does not finish:** verify WP-Cron and loopback DNS/TLS; inspect the recorded failure list.
- **Warm URL reports “not cached”:** inspect that page for personalization, cookies, non-200 status, unsupported response headers, or a configured path exclusion.
- **Warm URL returns a redirect, HIT, or STALE:** expected failure; redirects are not followed and only HTTP 200 plus `X-SP-Cache: MISS` is accepted. Queue the final canonical URL and inspect why the authenticated request did not rebuild it.
- **Stale content:** clear cache, confirm the invalidating hook fired, and inspect CDN/reverse-proxy caching.
- **Drop-in will not install:** check `WP_CACHE`, `wp-content` permissions, existing-file ownership, and whether the bundled template is current.
- **Page-cache storage is not proven safe:** first verify a dedicated absolute basename containing `sp-accelerator` and that the path is not a broad reserved root. Then verify `SP_ACCELERATOR_DOCUMENT_ROOT`; prefer storage outside the actual public root, or verify the exact deny before setting `SP_ACCELERATOR_CACHE_WEB_PROTECTED`.
- **Static rules show `manual`:** the server is Nginx; configure asset TTL and Brotli/GZIP outside WordPress because `.htaccess` has no effect.
- **Static rules show `readonly`:** grant WordPress safe write access temporarily or ask the hosting provider to install the displayed policy; do not overwrite the root file blindly.
- **Object-cache storage is not proven safe:** persistence is intentionally fail-closed. Verify the same dedicated-name/broad-root gate and `SP_ACCELERATOR_DOCUMENT_ROOT`; prefer storage outside the actual public root, or verify the exact deny before setting `SP_ACCELERATOR_OBJECT_CACHE_WEB_PROTECTED`.
- **Object cache affects wp-admin:** temporarily rename only the managed `wp-content/object-cache.php`, inspect the PHP log, verify SQLite and storage permissions, then reinstall the matching template.
- **Layout flashes or JS starts too late:** restore the affected style/script to the critical path or exclude its handle with the provided filters, then retest the control-page matrix.
- **Seraphinite is still active:** deactivate it before enabling SP Accelerator; v2 suppresses its own feature checks when the legacy constant is present to avoid double optimization.

## Emergency recovery

If a deployment causes an immediate critical error:

1. Change `sp-accelerator` to `_sp-accelerator` in the project's PHP Kit configuration.
2. If needed, temporarily rename only the managed `wp-content/object-cache.php` and `wp-content/advanced-cache.php` files.
3. Read the exact fatal error from `wp-content/debug.log` or the hosting PHP error log.
4. Upload one complete matching module version, restore the directory name, then reinstall/update both managed drop-ins from **Settings → Accelerator**.

SP Accelerator refuses destructive operations on drop-ins it does not own.

## Regression tests

If PHP Kit is outside a WordPress installation, pass its root explicitly:

```bash
SP_ACCELERATOR_WP_ROOT=/path/to/wordpress php _tests/run.php
```
