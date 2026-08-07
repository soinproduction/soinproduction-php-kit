# Google Reviews

Imports Google Maps reviews through SerpAPI into the theme’s `review` custom post type, keeps provider identity and reviewer media attached to each post, calculates aggregate statistics and exposes a compact frontend widget.

## Requirements and Data Flow

The module expects the theme to register the `review` post type. ACF is optional: when available, rating is also written with `update_field('stars', ...)`; otherwise the normal `stars` post meta remains the source of truth.

Synchronization follows this pipeline:

1. Validate an administrator request and read sanitized provider settings.
2. Request `google_maps_reviews` pages from SerpAPI, newest first.
3. Reject reviews below the configured rating and deduplicate the current response.
4. Find an existing WordPress review by provider plus generated external ID.
5. Insert, update or skip the `review` post according to **Overwrite existing reviews**.
6. Sideload a safe reviewer avatar and set it as the featured image.
7. Recalculate local statistics and store the last successful fetch time.

There is no built-in recurring cron schedule. **Sync now** is a deliberate admin operation; automate it only by invoking an approved integration around the same import behavior and respecting provider quotas.

## Settings Reference

All settings are stored in `sp_reviews_importer_options`.

| Key | Default | Validation / behavior |
| --- | ---: | --- |
| `api_key` | empty | Only letters and digits are retained. Required for import. |
| `place_id` | empty | Letters, digits, underscore and hyphen. Required for import. |
| `language` | site locale prefix | Lowercase letters; passed as SerpAPI `hl`. |
| `min_rating` | `1` | Integer clamped to 1–5. |
| `limit` | `30` | Imported review target, clamped to 1–200. |
| `overwrite` | `1` | Updates existing matching reviews and their avatars when enabled. |
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

HTTP timeout is 25 seconds. The maximum number of pages is `ceil(limit / 10)`, so 30 requested reviews may use three queries. Filtering low ratings does not reduce the requested page count in advance and may produce fewer than the requested limit.

The first available `place_info.rating` and `place_info.reviews` values update aggregate options. Non-2xx responses or WordPress HTTP errors fail the import when nothing has been collected; a later page failure preserves reviews already collected in that run.

## Identity and WordPress Storage

SerpAPI review rows do not use a stable source ID here. The module creates one with:

```text
md5(user link + "|" + ISO/date value + "|" + review snippet)
```

An existing post is found by the pair `_sp_review_provider=serpapi` and `_sp_review_external_id={hash}` across publish, future, draft, pending and private statuses.

| WordPress field | Imported value |
| --- | --- |
| `post_type` / status | `review` / `publish` |
| title | sanitized reviewer name, or `Anonymous` |
| content | review snippet passed through `wp_kses_post` |
| date | provider ISO timestamp when valid, otherwise current site time |
| `stars` | integer 1–5 in post meta and optionally ACF |
| `_sp_review_provider` | `serpapi` |
| `_sp_review_external_id` | generated hash |
| `_sp_review_place_id` | configured Place ID |
| `_sp_review_url` | reviewer/source URL when supplied |
| featured image | sideloaded reviewer thumbnail |

Changing the snippet or date can change the generated identity and produce a new post. If provider data has been edited upstream, review duplicates after a sync should be checked against these identity inputs.

## Avatar Handling and SSRF Protection

Avatar URLs must pass `wp_http_validate_url()`, use HTTP/HTTPS, have a non-local host and not point to private/reserved IP ranges. Accepted files are downloaded with WordPress `download_url()` and imported through `media_handle_sideload()` as `review-avatar-{post_id}.jpg`.

Avatar attachment IDs are cached for one hour in `sp_review_avatar_ids`. Those attachments are excluded from the normal Media Library grid/list so the library is not cluttered. Deleting a review permanently deletes its featured avatar; deleting an avatar clears the cached ID list.

When overwrite is disabled, an existing thumbnail is kept. When enabled, a new provider thumbnail may be sideloaded. Review deletion is therefore a destructive media operation if the avatar is used elsewhere manually.

## Aggregate Options

| Option | Purpose |
| --- | --- |
| `sp_google_reviews_rating` | Provider rating or recalculated local average, formatted to one decimal. |
| `sp_google_reviews_count` | Provider count or local published review count. |
| `sp_google_reviews_last_fetch` | Site-time MySQL timestamp after a completed upsert. |

After each import the module recalculates count and average from all published `review` posts. Consequently the local values may replace provider aggregate values and reflect the imported/local dataset rather than every live Google review. Manual fallback settings take precedence in the widget.

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
    'url'       => 'https://…',
    'provider'  => 'serpapi',
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
- **Fewer reviews than limit:** minimum rating filtering, missing pagination token, duplicate hashes or an interrupted later page can reduce output.
- **Duplicates:** compare reviewer URL, date and snippet; any change alters the generated hash.
- **Rating/count differs from Google:** local post recalculation and fallback overrides can supersede `place_info` totals.
- **Avatar absent:** source URL may fail safety validation/download, the remote server may reject the request, or WordPress cannot write uploads.
- **Avatar visible nowhere in Media Library:** this is intentional; review avatars are filtered from normal attachment screens.
- **Stars do not render:** confirm the theme `sprite()` helper and `Stars{n}` assets exist.
- **Import times out:** lower the limit and retry; each remote page can wait up to 25 seconds.
