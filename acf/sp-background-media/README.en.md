# Responsive Background ACF field

`sp_background_media` is a reusable PHP Kit ACF field for image/video backgrounds with
responsive variants, per-breakpoint positioning, and solid or gradient overlays.

## Field declaration

```php
->addField( 'background', 'sp_background_media', [
	'label'         => __( 'Background', 'acf' ),
	'required'      => 0,
	'responsive'    => 1,
	'allow_video'   => 1,
	'allow_overlay' => 1,
] )
```

The field may contain media, an overlay, or both. When media is used, the
Desktop attachment is its base; Tablet and Mobile attachments are optional and
inherit from the preceding larger breakpoint. Sizing and X/Y position remain
independently configurable for every breakpoint. An enabled solid/gradient
overlay is saved and rendered even when every media attachment is empty.

When the theme exposes `sp_theme_breakpoint()`, breakpoints come from its configuration. Other projects can use the `sp_background_media_breakpoints` filter; portable defaults are `576px` and `1024px`.

- Desktop: `>= tablet`
- Tablet: `>= mobile` and `< tablet`
- Mobile: `< mobile`

## Saved value

```php
[
	'desktop' => [
		'attachment_id' => 123,
		'poster_id'     => 456,
		'fit'           => 'cover',
		'position_x'    => 50,
		'position_y'    => 50,
	],
	'tablet' => [
		'attachment_id' => 0,
		'poster_id'     => 0,
		'fit'           => 'cover',
		'position_x'    => 40,
		'position_y'    => 50,
	],
	'mobile' => [
		'attachment_id' => 789,
		'poster_id'     => 0,
		'fit'           => 'cover',
		'position_x'    => 65,
		'position_y'    => 50,
	],
	'overlay' => [
		'enabled' => 1,
		'type'    => 'gradient',
		'color'   => '#000000',
		'opacity' => 40,
		'angle'   => 180,
		'stops'   => [
			[ 'color' => '#111111', 'opacity' => 80, 'position' => 0 ],
			[ 'color' => '#6F3547', 'opacity' => 55, 'position' => 45 ],
			[ 'color' => '#FFFFFF', 'opacity' => 0, 'position' => 100 ],
		],
	],
]
```

Gradient stops are repeatable (2–8 rows). Existing values saved with the former
`start_*` / `end_*` keys are migrated transparently when the field is loaded and
saved. Color controls use compact swatches from `color_palette_config()`, a
native color input and a custom HEX input. The editor synchronizes the complete
composite value into one hidden JSON state before ACF validation/submission, so
dynamic gradient rows remain stable inside repeaters and flexible content.

## Frontend

Place the rendered background as the first child of a positioned container:

```php
<section class="hero">
	<?php
		display_background_media( get_sub_field( 'background' ), [
			'class'   => 'hero__background',
			'loading' => 'eager',
			'z_index' => 0,
		] );
	?>
	<div class="hero__content">...</div>
</section>
```

```scss
.hero {
	isolation: isolate;
	position: relative;
}

.hero__content {
	position: relative;
	z-index: 1;
}
```

Use `sp_get_background_media( $value )` when normalized structured data is
needed without HTML. Background images and videos are loaded only for the
active breakpoint; images retain WordPress responsive `srcset` data. Videos are
muted and looped. By default they stay on their poster when the visitor
requests reduced motion; pass `respect_reduced_motion => false` to override.

For a text-theme class, `sp_background_media_overlay_color()` returns the solid
overlay color or the interpolated gradient color at a requested position:

```php
$background  = get_sub_field( 'background' );
$section_bg  = sp_background_media_overlay_color( $background, 50 );
$color_theme = $section_bg && is_color_dark( $section_bg )
	? 'light-text'
	: 'dark-text';
```

The position is `0–100%` along the configured gradient and defaults to `50`.
The helper intentionally evaluates the overlay color itself; opacity and the
brightness of an underlying image cannot be represented by one deterministic
HEX value.
