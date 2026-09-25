# Interactive Map

Enable `sp-interactive-map` in the Bootstrapper `acf` list. It registers the `sp_interactive_map` field with Group-style custom Sub Fields, single/multiple points, image selection, point dragging/locking and a per-map zoom toggle.

```php
display_interactive_map(get_sub_field('map'), [
    'class' => 'w-full',
    'marker_class' => 'w-[3rem] aspect-square rounded-full overflow-hidden transition-[filter] duration-300 data-[muted=true]:grayscale',
    'zoom_controls_class' => 'position-[absolute] right-[1.6rem] bottom-[1.6rem] z-30 d-[flex] flex-col gap-[.8rem]',
    'tooltip_template' => 'php/templates/map-tooltips/default',
    'loading' => 'lazy',
]);
```

The tooltip template belongs to the theme. Read configured fields with `get_sub_field('title')`, `get_sub_field('image')`, etc. `$args` contains `point`, `index`, and `map`; the outer ACF loop is restored after rendering. Markers use optional `title` and `image` fields. Marker images require the theme's `display_image()` helper.

Values contain `map_id`, `zoom_enabled` and `points`, with percentage `x/y` coordinates and stable `_id` values. Custom values are stored through native ACF group fields. Pass a formatted ACF value to the renderer so it includes its subfield context.

`assets/map-module.js` is enqueued once in the footer when a map is rendered. Define `THEME_DIR` and `THEME_URI`, and call `wp_footer()`. Tooltips remain unscaled in a body portal and automatically fit the viewport. Other markers receive `data-muted`. Enabled zoom supports buttons, drag panning, Ctrl+wheel trackpad pinch and Safari gestures; normal page scrolling is preserved.

Frontend styling uses Tailwind, with `d-[...]` / `position-[...]` utilities and `--bg-a` supplied by the theme. Include this module directory in Tailwind sources, relative to your CSS:

```css
@source "../../vendor/soinproduction/php-kit/acf/sp-interactive-map";
```

Admin assets are embedded and need no theme build. When migrating from a theme, remove its duplicate field PHP and map runtime, keep tooltip templates and ACF JSON, then rebuild Tailwind. Existing field names and storage stay unchanged.

## Webpack lazy components

`components.json` declares the module's frontend entry and root selectors. The theme build can discover `{acf,plugins,platform}/*/components.json` automatically and generate literal dynamic imports, with one named chunk per component. No theme adapter file is needed. Only load a chunk when one of its selectors exists on the page.

Disable the standalone script when using this integration:

```php
'acf' => ['sp-interactive-map' => ['enqueue_script' => false]],
```

Rebuild theme JS after updating Composer dependencies. The runtime source stays in the kit; do not copy it into the theme.
