# Google Reviews

Imports Google Maps reviews through SerpAPI into the theme’s `review` custom post type, creates linked Polylang/WPML language versions, stores reviewer avatars and review photos, calculates per-language statistics and exposes a frontend widget.

## Requirements and Data Flow

The module expects the theme to register the `review` post type. ACF is optional: when available, rating is also written with `update_field('stars', ...)`; otherwise the normal `stars` post meta remains the source of truth.

Synchronization follows this pipeline:

1. Validate an administrator request and read sanitized provider settings.
2. Discover active Polylang/WPML languages, or use `language` when neither integration is active.
3. Request `google_maps_reviews` pages for every language with the matching `hl`, newest first.
4. Reject low-rated reviews and deduplicate by stable source identity.
5. Find, insert or update each language version and connect matching posts as translations.
6. Sideload the reviewer avatar as the featured image and review photos as child attachments.
7. Recalculate per-language statistics and store the last successful fetch time.

There is no built-in recurring cron schedule. **Sync now** is a deliberate admin operation; automate it only by invoking an approved integration around the same import behavior and respecting provider quotas.

## Settings Reference

All settings are stored in `sp_reviews_importer_options`.

| Key | Default | Validation / behavior |
| --- | ---: | --- |
| `api_key` | empty | Only letters and digits are retained. Required for import. |
| `place_id` | empty | Letters, digits, underscore and hyphen. Required for import. |
| `language` | site locale prefix | Fallback without Polylang/WPML. Every active site language is synchronized when either integration is active. |
| `min_rating` | `1` | Integer clamped to 1–5. |
| `limit` | `30` | Imported review target, clamped to 1–200. |
| `overwrite` | `1` | Updates matching content, avatar and review gallery when enabled. |
| `fallback_rating` | empty | Manual aggregate widget override when numeric. Comma decimal is accepted. |
| `fallback_count` | empty | Manual positive review-count override when numeric. |

The interface marks synchronization ready only when both API key and Place ID are present.

## SerpAPI Request Contract

Each request targets `https://serpapi.com/search.json` with:

- `engine=google_maps_reviews`;
- the configured `place_id` and `api_key`;
- `hl={language}`;
- `sort_by=newestFirst`;
- `next_page_token` for subsequent pages.

HTTP timeout is 25 seconds. The maximum number of pages is `ceil(limit / 10)` per language, so a limit of 30 across three languages can consume up to nine queries. Rating filtering may produce fewer than the requested limit.

Non-2xx responses or WordPress HTTP errors fail the import when nothing has been collected; a later page failure preserves reviews already collected in that language response.

## Identity and WordPress Storage

The primary source identity is SerpAPI `review_id`. If it is absent, the module uses the legacy fallback:

```text
md5(user link + "|" + ISO/date value + "|" + review snippet)
```

An existing post is found by provider, `_sp_review_source_id` and `_sp_review_language`. Legacy posts without the new metadata are matched by the old external hash and migrated during the next sync.

| WordPress field | Imported value |
| --- | --- |
| `post_type` / status | `review` / `publish` |
| title | sanitized reviewer name, or `Anonymous` |
| content | review snippet passed through `wp_kses_post` |
| date | provider ISO timestamp when valid, otherwise current site time |
| `stars` | integer 1–5 in post meta and optionally ACF |
| `_sp_review_provider` | `serpapi` |
| `_sp_review_external_id` | stable source ID; legacy imports may initially contain the old hash |
| `_sp_review_place_id` | configured Place ID |
| `_sp_review_source_id` | stable `review_id` or legacy hash |
| `_sp_review_language` | Polylang/WPML language code |
| `_sp_review_url` | reviewer/source URL when supplied |
| `_sp_review_images` | array of sideloaded photo attachment IDs |
| featured image | sideloaded reviewer thumbnail |

When `review_id` is unavailable, changing the snippet or date can still change the legacy fallback identity and produce a new post.

## Media Handling and SSRF Protection

Avatar URLs must pass `wp_http_validate_url()`, use HTTP/HTTPS, have a non-local host and not point to private/reserved IP ranges. Accepted files are downloaded with WordPress `download_url()` and imported through `media_handle_sideload()` as `review-avatar-{post_id}.jpg`.

String and object values from SerpAPI `images` are normalized, sideloaded as `review-image-{post_id}-{n}.jpg`, and stored in `_sp_review_images`. All review-owned attachments are excluded from normal Media Library grid/list views. Deleting a review permanently deletes its owned avatar and gallery attachments.

With overwrite disabled, existing media is kept. With overwrite enabled, old media is replaced only after a successful new download, so a temporary network failure does not erase the existing gallery. Do not reuse these owned attachments manually without accounting for this lifecycle.

## Aggregate Options

| Option | Purpose |
| --- | --- |
| `sp_google_reviews_rating` | Provider rating or recalculated local average, formatted to one decimal. |
| `sp_google_reviews_count` | Provider count or local published review count. |
| `sp_google_reviews_last_fetch` | Site-time MySQL timestamp after a completed upsert. |
| `sp_google_reviews_stats_by_language` | Local count and average keyed by language code. |

After each import the module recalculates count and average separately for every imported language. The widget selects the current Polylang/WPML language stats. Manual fallback settings still take precedence.

## Admin Endpoints and Security

The classic `admin-post.php?action=sp_reviews_import` flow and Ajax action `sp_reviews_import` both require `manage_options` and a nonce. Settings are registered through the WordPress Settings API with a sanitizer. API credentials are stored in the WordPress options table; protect database exports and restrict administrator accounts accordingly.

Import results report inserted, updated and skipped totals. “Skipped” includes malformed rows, missing external identity, overwrite-disabled matches and post write failures.

## Frontend Widget

Basic usage:

```text
[google_reviews_widget]
[google_reviews_widget show_count="false" show_stars="true"]
```

`show_count` and `show_stars` treat `0`, `false`, `no` and `off` as false; other values are true. The widget:

- chooses manual fallback aggregate values when valid, otherwise stored statistics;
- falls back to rating `5.0` and count `1` when stored values are zero;
- rounds the visual star sprite to an integer from 1 to 5 while printing the numeric rating with one decimal;
- loads up to three latest published review thumbnails;
- uses three remote Unsplash fallback avatars when fewer are available;
- adds its inline stylesheet under handle `sp-google-reviews-widget`.

The star graphic depends on the theme `sprite()` helper and sprites named `Stars1` through `Stars5`. If the module is moved outside this theme architecture, provide an equivalent helper or change the renderer.

## Public PHP API

`SP_Google_Reviews_Importer::get_review_data($post_id)` returns `null` for a non-review and otherwise returns:

```php
[
    'id'        => 123,
    'name'      => 'Reviewer name',
    'content'   => 'Filtered review HTML',
    'raw'       => 'Raw stored content',
    'stars'     => 5.0,
    'date'      => '2026-08-01 12:00:00',
    'timestamp' => 1785585600,
    'thumb'     => 'https://…',
    'images'    => ['https://…'],
    'image_ids' => [456],
    'url'       => 'https://…',
    'provider'  => 'serpapi',
    'language'  => 'en',
]
```

Use this helper for custom cards instead of depending on private meta implementation details.

## Operational Checklist

1. Confirm the `review` CPT and `stars` field/meta are available.
2. Save API key, Place ID, language, rating threshold and a small test limit.
3. Estimate SerpAPI query cost before raising the limit.
4. Run a sync and verify insert/update/skip totals.
5. Open several review posts and inspect dates, text, rating and thumbnails.
6. Verify aggregate numbers and the shortcode as a logged-out visitor.
7. Back up the database/uploads before bulk deletion or identity changes.

## Troubleshooting

- **Sync button disabled:** API key or Place ID is empty after sanitization.
- **HTTP/provider error:** verify key, quota, Place ID, outbound HTTPS and the SerpAPI error message.
- **Fewer reviews than limit:** minimum rating filtering, missing pagination token, duplicate source IDs or an interrupted later page can reduce output.
- **Duplicates:** inspect `_sp_review_source_id`; only rows without provider `review_id` depend on the URL/date/snippet fallback hash.
- **Rating/count differs from Google:** local post recalculation and fallback overrides can supersede `place_info` totals.
- **Avatar absent:** source URL may fail safety validation/download, the remote server may reject the request, or WordPress cannot write uploads.
- **Review media absent from Media Library:** intentional; owned avatars and photos are filtered from normal attachment screens.
- **Stars do not render:** confirm the theme `sprite()` helper and `Stars{n}` assets exist.
- **Import times out:** lower the limit and retry; each remote page can wait up to 25 seconds.
