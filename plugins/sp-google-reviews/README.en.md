# Google Reviews

Imports Google Maps reviews through SerpAPI into the theme’s `review` custom post type, creates linked Polylang/WPML language versions, stores reviewer avatars and review photos, calculates per-language statistics and exposes a frontend widget.

## Requirements and Data Flow

The module expects the theme to register the `review` post type. It adds that CPT to Polylang and configures WPML translation mode as `translate`. ACF is optional: when available, rating is also written with `update_field('stars', ...)`; otherwise the normal `stars` post meta remains the source of truth.

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

The review editor exposes these attachments in a read-only **Review Photos** metabox. The files remain importer-owned; click a thumbnail to inspect the attachment, and use synchronization rather than the metabox to replace the gallery.

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

## Widget Builder

Open **Settings → Google Reviews → Widget Builder**. The builder stores multiple reusable widgets in the `sp_google_reviews_widgets` option. Every widget has a stable ID and uses a strictly sanitized schema; arbitrary HTML and CSS are never stored.

Available presets are Banner, Compact and Minimal. A widget can independently configure:

- reviewer avatars, stars, numeric rating, rating label and review-count label;
- component visibility and drag-and-drop order;
- avatar amount, size and overlap;
- star and typography sizes, spacing, padding and radius;
- text, muted text, star and background colors;
- Media Library background image and overlay opacity.

The editor provides a responsive live preview. Frontend output uses scoped CSS custom properties, semantic markup and standalone SVG stars, so it has no theme sprite dependency or remote fallback-avatar request. Reviews without a photo are represented by the reviewer initial.

Use the shortcode displayed on the widget card:

```text
[google_reviews_widget id="hero-rating"]
[google_reviews_widget id="footer-rating"]
```

`[google_reviews_widget]` remains compatible and renders the `default` widget (or the first saved widget). Legacy `show_count` and `show_stars` attributes remain supported as per-render visibility overrides.

Rating and count are selected for the current Polylang/WPML language. Widget labels are registered with Polylang/WPML String Translation under the `SP Google Reviews` group. They can also be adjusted through `sp_google_reviews_widget_rating_label` and `sp_google_reviews_widget_count_label`; both filters receive the widget ID as their second argument.

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

### Review Card Markup Example

The recommended approach uses `image_ids` with `wp_get_attachment_image()`, allowing WordPress to generate local URLs, dimensions and responsive `srcset` markup.

```php
<?php
$reviews = new WP_Query( [
    'post_type'      => 'review',
    'post_status'    => 'publish',
    'posts_per_page' => 6,
] );
?>

<?php while ( $reviews->have_posts() ) : $reviews->the_post(); ?>
    <?php $review = SP_Reviews_Importer::get_review_data( get_the_ID() ); ?>
    <?php if ( $review === null ) { continue; } ?>

    <article class="review-card">
        <header class="review-card__header">
            <?php if ( $review['thumb'] !== '' ) : ?>
                <img
                    class="review-card__avatar"
                    src="<?php echo esc_url( $review['thumb'] ); ?>"
                    alt=""
                    width="64"
                    height="64"
                    loading="lazy"
                >
            <?php endif; ?>

            <div>
                <h3><?php echo esc_html( $review['name'] ); ?></h3>
                <span><?php echo esc_html( number_format_i18n( $review['stars'], 1 ) ); ?>/5</span>
            </div>
        </header>

        <div class="review-card__body">
            <?php echo wp_kses_post( $review['content'] ); ?>
        </div>

        <?php if ( $review['image_ids'] !== [] ) : ?>
            <div class="review-card__gallery">
                <?php foreach ( $review['image_ids'] as $image_id ) : ?>
                    <figure class="review-card__photo">
                        <?php
                        echo wp_get_attachment_image(
                            $image_id,
                            'large',
                            false,
                            [ 'loading' => 'lazy' ]
                        );
                        ?>
                    </figure>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </article>
<?php endwhile; ?>

<?php wp_reset_postdata(); ?>
```

Polylang and WPML normally filter `WP_Query` to the current language. If a custom query sets `suppress_filters => true`, language filtering must be handled explicitly.

### Simplified Image URL Output

When responsive WordPress markup is unnecessary, use the ready-to-render `images` array:

```php
<?php foreach ( $review['images'] as $image_url ) : ?>
    <img
        src="<?php echo esc_url( $image_url ); ?>"
        alt=""
        loading="lazy"
    >
<?php endforeach; ?>
```

Prefer `image_ids` in production because it supports registered image sizes, `srcset` and WordPress image optimization.

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
