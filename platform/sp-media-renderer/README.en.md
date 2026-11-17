# SP Media Renderer

Enable `sp-media-renderer` in `platform` to render ordinary images and local videos without ACF. This module is independent of `acf/sp-media`: `display_universal_media()` renders the structured `sp_universal_media` field and keeps its own markup and options.

## API

- `get_media_html($media, int $width, int $height, string $class = '', string $loading = 'lazy'): string`
- `display_media(...)`: echoes the same HTML.
- `display_image(...)`: compatibility alias; the `display_media` and `display_image` actions are also available.
- `sp_get_post_thumbnail_data(int $post_id = 0, $size = 'large'): ?array` and its alias `get_thumb(...)` return featured-image data.

Media accepts an attachment ID, numeric string, attachment `WP_Post`, URL, or ordinary ACF image array (`ID`, `url`, `alt`). URL-only media does not require a Media Library attachment. Missing input returns an empty string. Dimensions select the closest registered image size; SVGs carry `data-sp-svg-mode` (`auto`, `sprite`, `inline`, `img`). Actual inline/sprite processing remains the consumer's responsibility.

Use `sizes_attr` (or a string `sizes`) for responsive image sizes. Otherwise the `display_media_default_sizes` filter receives `100vw` and the normalized media array; themes can retain their own layout defaults. `img_attrs` overrides image attributes. `video_attrs` supports autoplay, loop, muted, playsinline, controls, preload, style and class. Existing `_sp_video_*` metadata and optional `sp_video_poster_url()` integration are preserved. Video defaults are autoplay, loop, muted and playsinline enabled, controls disabled, preload `auto`.

The `display_media_data`, `display_media_poster`, `display_media_attrs`, `display_media_html` and `display_media_output` filters retain their existing argument order. Public functions are guarded with `function_exists` for legacy consumers. The module is opt-in; enabling it before a theme's guarded legacy helpers makes Kit the implementation. Remove those duplicate helpers after migrating theme-specific defaults to filters.
