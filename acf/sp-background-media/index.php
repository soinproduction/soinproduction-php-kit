<?php
	declare(strict_types=1);

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/**
	 * Clamp a numeric background option to a safe range.
	 */
	function sp_background_media_number( mixed $value, float $minimum, float $maximum, float $fallback ): float {
		if ( ! is_numeric( $value ) ) {
			return $fallback;
		}

		return max( $minimum, min( $maximum, (float) $value ) );
	}

	/**
	 * Return a safe six-digit hex color.
	 */
	function sp_background_media_color( mixed $value, string $fallback ): string {
		$color = sanitize_hex_color( is_string( $value ) ? $value : '' );

		return is_string( $color ) ? $color : $fallback;
	}

	/**
	 * Responsive variants are fixed semantically. Theme breakpoint helpers take
	 * precedence; the filter and defaults keep the kit module portable.
	 */
	function sp_background_media_breakpoint_config(): array {
		$breakpoints = apply_filters( 'sp_background_media_breakpoints', [
			'mobile' => 576.0,
			'tablet' => 1024.0,
		] );
		$breakpoints = is_array( $breakpoints ) ? $breakpoints : [];
		$mobile = function_exists( 'sp_theme_breakpoint' )
			? sp_theme_breakpoint( 'mobile' )
			: sp_background_media_number( $breakpoints['mobile'] ?? 576, 1, 9998, 576 );
		$tablet = function_exists( 'sp_theme_breakpoint' )
			? sp_theme_breakpoint( 'tablet' )
			: sp_background_media_number( $breakpoints['tablet'] ?? 1024, $mobile + 0.02, 9999, 1024 );

		return [
			'desktop' => [
				'min' => $tablet,
				'max' => null,
			],
			'tablet' => [
				'min' => $mobile,
				'max' => $tablet - 0.02,
			],
			'mobile' => [
				'min' => null,
				'max' => $mobile - 0.02,
			],
		];
	}

	/**
	 * Sanitize repeatable gradient stops and migrate the former two-stop shape.
	 */
	function sp_background_media_gradient_stops( array $overlay ): array {
		$raw_stops = is_array( $overlay['stops'] ?? null ) ? $overlay['stops'] : [];

		if ( [] === $raw_stops ) {
			$raw_stops = [
				[
					'color'    => $overlay['start_color'] ?? '#000000',
					'opacity'  => $overlay['start_opacity'] ?? 70,
					'position' => $overlay['start_position'] ?? 0,
				],
				[
					'color'    => $overlay['end_color'] ?? '#000000',
					'opacity'  => $overlay['end_opacity'] ?? 0,
					'position' => $overlay['end_position'] ?? 100,
				],
			];
		}

		$stops = [];
		foreach ( array_slice( $raw_stops, 0, 8 ) as $stop ) {
			if ( ! is_array( $stop ) ) {
				continue;
			}

			$stops[] = [
				'color'    => sp_background_media_color( $stop['color'] ?? '', '#000000' ),
				'opacity'  => sp_background_media_number( $stop['opacity'] ?? 100, 0, 100, 100 ),
				'position' => sp_background_media_number( $stop['position'] ?? 50, 0, 100, 50 ),
			];
		}

		while ( count( $stops ) < 2 ) {
			$stops[] = 0 === count( $stops )
				? [ 'color' => '#000000', 'opacity' => 70.0, 'position' => 0.0 ]
				: [ 'color' => '#000000', 'opacity' => 0.0, 'position' => 100.0 ];
		}

		usort( $stops, static fn( array $left, array $right ): int => $left['position'] <=> $right['position'] );

		return array_values( $stops );
	}

	/**
	 * Normalize one responsive background variant, inheriting missing media.
	 */
	function sp_background_media_normalize_variant( mixed $value, array $fallback = [] ): array {
		$value         = is_array( $value ) ? $value : [];
		$attachment_id = absint( $value['attachment_id'] ?? 0 );
		$inherited     = false;

		if ( ! $attachment_id && ! empty( $fallback['attachment_id'] ) ) {
			$attachment_id = absint( $fallback['attachment_id'] );
			$inherited     = true;
		}

		if ( ! $attachment_id ) {
			return [];
		}

		$url  = wp_get_attachment_url( $attachment_id );
		$mime = (string) get_post_mime_type( $attachment_id );
		$type = str_starts_with( $mime, 'video/' ) ? 'video' : ( str_starts_with( $mime, 'image/' ) ? 'image' : '' );

		if ( ! $url || '' === $type ) {
			return [];
		}

		$fit = sanitize_key( (string) ( $value['fit'] ?? ( $fallback['fit'] ?? 'cover' ) ) );
		$fit = in_array( $fit, [ 'cover', 'contain' ], true ) ? $fit : 'cover';

		$position_x = sp_background_media_number(
			$value['position_x'] ?? ( $fallback['position_x'] ?? 50 ),
			0,
			100,
			50
		);
		$position_y = sp_background_media_number(
			$value['position_y'] ?? ( $fallback['position_y'] ?? 50 ),
			0,
			100,
			50
		);

		$poster_id = $inherited
			? absint( $fallback['poster_id'] ?? 0 )
			: absint( $value['poster_id'] ?? 0 );
		$poster_url = $poster_id && str_starts_with( (string) get_post_mime_type( $poster_id ), 'image/' )
			? wp_get_attachment_image_url( $poster_id, 'full' )
			: '';

		return [
			'attachment_id' => $attachment_id,
			'poster_id'     => $poster_id,
			'poster_url'    => is_string( $poster_url ) ? $poster_url : '',
			'url'           => $url,
			'mime_type'     => $mime,
			'media_type'    => $type,
			'fit'           => $fit,
			'position_x'    => $position_x,
			'position_y'    => $position_y,
			'inherited'     => $inherited,
		];
	}

	/**
	 * Normalize the custom ACF background value for templates and APIs.
	 */
	function sp_get_background_media( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return [];
		}

		$overlay_value = is_array( $value['overlay'] ?? null ) ? $value['overlay'] : [];
		$overlay_type  = sanitize_key( (string) ( $overlay_value['type'] ?? 'solid' ) );
		$overlay_type  = in_array( $overlay_type, [ 'solid', 'gradient' ], true ) ? $overlay_type : 'solid';
		$gradient_stops = sp_background_media_gradient_stops( $overlay_value );
		$overlay = [
			'enabled' => ! empty( $overlay_value['enabled'] ),
			'type'    => $overlay_type,
			'color'   => sp_background_media_color( $overlay_value['color'] ?? '', '#000000' ),
			'opacity' => sp_background_media_number( $overlay_value['opacity'] ?? 40, 0, 100, 40 ),
			'angle'   => sp_background_media_number( $overlay_value['angle'] ?? 180, 0, 360, 180 ),
			'stops'   => $gradient_stops,
		];

		$desktop = sp_background_media_normalize_variant( $value['desktop'] ?? [] );
		$tablet  = [];
		$mobile  = [];

		if ( [] !== $desktop ) {
			$tablet = sp_background_media_normalize_variant( $value['tablet'] ?? [], $desktop );
			$mobile = sp_background_media_normalize_variant( $value['mobile'] ?? [], $tablet ?: $desktop );
		}

		if ( [] === $desktop && empty( $overlay['enabled'] ) ) {
			return [];
		}

		return [
			'desktop' => $desktop,
			'tablet'  => $tablet ?: $desktop,
			'mobile'  => $mobile ?: ( $tablet ?: $desktop ),
			'overlay' => $overlay,
		];
	}

	/**
	 * Convert a hex color and percent opacity to rgba().
	 */
	function sp_background_media_rgba( string $color, float $opacity ): string {
		$hex = ltrim( $color, '#' );
		$red = hexdec( substr( $hex, 0, 2 ) );
		$green = hexdec( substr( $hex, 2, 2 ) );
		$blue = hexdec( substr( $hex, 4, 2 ) );

		return sprintf( 'rgba(%d, %d, %d, %.3F)', $red, $green, $blue, $opacity / 100 );
	}

	/**
	 * Build the sanitized CSS background for an overlay.
	 */
	function sp_background_media_overlay_css( array $overlay ): string {
		if ( empty( $overlay['enabled'] ) ) {
			return '';
		}

		if ( 'gradient' === ( $overlay['type'] ?? 'solid' ) ) {
			$stops = sp_background_media_gradient_stops( $overlay );
			$css_stops = array_map(
				static function ( array $stop ): string {
					$position = rtrim( rtrim( number_format( (float) $stop['position'], 2, '.', '' ), '0' ), '.' );

					return sp_background_media_rgba( (string) $stop['color'], (float) $stop['opacity'] ) . ' ' . $position . '%';
				},
				$stops
			);

			return sprintf(
				'linear-gradient(%sdeg, %s)',
				rtrim( rtrim( number_format( (float) $overlay['angle'], 2, '.', '' ), '0' ), '.' ),
				implode( ', ', $css_stops )
			);
		}

		return sp_background_media_rgba( (string) $overlay['color'], (float) $overlay['opacity'] );
	}

	/**
	 * Return a representative overlay HEX color for text-theme decisions.
	 *
	 * Solid overlays return their configured color. Gradient overlays return the
	 * interpolated color at the requested 0–100% position (50% by default).
	 * Opacity and underlying media are intentionally not included.
	 */
	function sp_background_media_overlay_color( mixed $value, float $position = 50 ): string {
		$value   = is_array( $value ) ? $value : [];
		$overlay = is_array( $value['overlay'] ?? null ) ? $value['overlay'] : $value;

		if ( empty( $overlay['enabled'] ) ) {
			return '';
		}

		$type = sanitize_key( (string) ( $overlay['type'] ?? 'solid' ) );
		if ( 'gradient' !== $type ) {
			return sp_background_media_color( $overlay['color'] ?? '', '#000000' );
		}

		$position = sp_background_media_number( $position, 0, 100, 50 );
		$stops    = sp_background_media_gradient_stops( $overlay );
		$left     = $stops[0];
		$right    = $stops[ count( $stops ) - 1 ];

		foreach ( $stops as $index => $stop ) {
			if ( (float) $stop['position'] >= $position ) {
				$right = $stop;
				$left  = 0 === $index ? $stop : $stops[ $index - 1 ];
				break;
			}
		}

		$left_position  = (float) $left['position'];
		$right_position = (float) $right['position'];
		$ratio = $right_position > $left_position
			? ( $position - $left_position ) / ( $right_position - $left_position )
			: 0;
		$left_hex  = ltrim( (string) $left['color'], '#' );
		$right_hex = ltrim( (string) $right['color'], '#' );
		$channels  = [];

		for ( $offset = 0; $offset <= 4; $offset += 2 ) {
			$left_channel  = hexdec( substr( $left_hex, $offset, 2 ) );
			$right_channel = hexdec( substr( $right_hex, $offset, 2 ) );
			$channels[] = (int) round( $left_channel + ( ( $right_channel - $left_channel ) * $ratio ) );
		}

		return sprintf( '#%02X%02X%02X', $channels[0], $channels[1], $channels[2] );
	}

	/**
	 * Render the responsive background as a decorative absolute layer.
	 *
	 * Place it as the first child of a positioned container. Content above it
	 * should have a positive stacking context when the default z-index is used.
	 */
	function display_background_media( mixed $value, array $args = [] ): void {
		$background = sp_get_background_media( $value );

		if ( [] === $background ) {
			return;
		}

		$args = wp_parse_args( $args, [
			'class'                  => '',
			'loading'                => 'lazy',
			'z_index'                => 0,
			'respect_reduced_motion' => true,
		] );
		$classes = preg_split( '/\s+/', trim( (string) $args['class'] ) ) ?: [];
		$classes = array_filter( array_map( 'sanitize_html_class', $classes ) );
		$class   = trim( 'sp-background-media ' . implode( ' ', $classes ) );
		$loading = 'eager' === $args['loading'] ? 'eager' : 'lazy';
		$breakpoint_config = sp_background_media_breakpoint_config();
		$mobile_breakpoint = (float) $breakpoint_config['tablet']['min'];
		$tablet_breakpoint = (float) $breakpoint_config['desktop']['min'];
		$style = '--sp-background-z-index:' . (int) $args['z_index'] . ';';

		echo '<div class="' . esc_attr( $class ) . '" data-sp-background-media'
			. ' data-mobile-breakpoint="' . esc_attr( (string) $mobile_breakpoint ) . '"'
			. ' data-tablet-breakpoint="' . esc_attr( (string) $tablet_breakpoint ) . '"'
			. ' data-respect-reduced-motion="' . ( $args['respect_reduced_motion'] ? 'true' : 'false' ) . '"'
			. ' style="' . esc_attr( $style ) . '" aria-hidden="true">';

		foreach ( [ 'desktop', 'tablet', 'mobile' ] as $breakpoint ) {
			$variant = $background[ $breakpoint ];

			if ( [] === $variant ) {
				continue;
			}

			$variant_style = sprintf(
				'--sp-background-fit:%s;--sp-background-x:%s%%;--sp-background-y:%s%%;',
				$variant['fit'],
				rtrim( rtrim( number_format( (float) $variant['position_x'], 2, '.', '' ), '0' ), '.' ),
				rtrim( rtrim( number_format( (float) $variant['position_y'], 2, '.', '' ), '0' ), '.' )
			);

			echo '<div class="sp-background-media__variant sp-background-media__variant--' . esc_attr( $breakpoint ) . '"'
				. ' data-sp-background-variant="' . esc_attr( $breakpoint ) . '" style="' . esc_attr( $variant_style ) . '">';

			if ( 'image' === $variant['media_type'] ) {
				$image_url = wp_get_attachment_image_url( $variant['attachment_id'], 'full' );
				$srcset    = wp_get_attachment_image_srcset( $variant['attachment_id'], 'full' );
				$sizes     = wp_get_attachment_image_sizes( $variant['attachment_id'], 'full' );
				$placeholder = 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=';

				if ( $image_url ) {
					echo '<img class="sp-background-media__asset sp-background-media__image"'
						. ' src="' . esc_attr( $placeholder ) . '" data-src="' . esc_url( $image_url ) . '"'
						. ( $srcset ? ' data-srcset="' . esc_attr( $srcset ) . '"' : '' )
						. ( $sizes ? ' data-sizes="' . esc_attr( $sizes ) . '"' : '' )
						. ' data-sp-background-image alt="" aria-hidden="true" loading="' . esc_attr( $loading ) . '">';
					echo '<noscript>' . wp_get_attachment_image( $variant['attachment_id'], 'full', false, [
						'alt'         => '',
						'aria-hidden' => 'true',
						'class'       => 'sp-background-media__asset sp-background-media__image',
						'loading'     => $loading,
					] ) . '</noscript>';
				}
			} else {
				echo '<video class="sp-background-media__asset sp-background-media__video" data-sp-background-video'
					. ( $variant['poster_url'] ? ' poster="' . esc_url( $variant['poster_url'] ) . '"' : '' )
					. ' muted loop playsinline preload="none">'
					. '<source data-src="' . esc_url( $variant['url'] ) . '" type="' . esc_attr( $variant['mime_type'] ) . '">'
					. '</video>';
			}

			echo '</div>';
		}

		$overlay_css = sp_background_media_overlay_css( $background['overlay'] );
		if ( '' !== $overlay_css ) {
			echo '<span class="sp-background-media__overlay" style="background:' . esc_attr( $overlay_css ) . '"></span>';
		}

		echo '</div>';
	}

	/**
	 * Load the small standalone frontend assets and keep media queries in sync
	 * with the theme breakpoint configuration.
	 */
	function sp_background_media_enqueue_frontend_assets(): void {
		$base_path = __DIR__ . '/assets/';
		$base_uri  = class_exists( \SoinProduction\Kit\Bootstrapper::class )
			? \SoinProduction\Kit\Bootstrapper::pathToUrl( $base_path )
			: '';

		if ( '' === $base_uri ) {
			return;
		}

		$css_path  = $base_path . 'background-media.css';
		$js_path   = $base_path . 'background-media.js';

		wp_enqueue_style(
			'sp-background-media',
			$base_uri . 'background-media.css',
			[],
			is_readable( $css_path ) ? (string) filemtime( $css_path ) : null
		);

		$breakpoint_config = sp_background_media_breakpoint_config();
		$mobile = (float) $breakpoint_config['tablet']['min'];
		$tablet = (float) $breakpoint_config['desktop']['min'];
		wp_add_inline_style(
			'sp-background-media',
			'@media (max-width:' . number_format( $tablet - 0.02, 2, '.', '' ) . 'px){'
			. '.sp-background-media__variant--desktop{display:none}.sp-background-media__variant--tablet{display:block}'
			. '}@media (max-width:' . number_format( $mobile - 0.02, 2, '.', '' ) . 'px){'
			. '.sp-background-media__variant--tablet{display:none}.sp-background-media__variant--mobile{display:block}'
			. '}'
		);

		wp_enqueue_script(
			'sp-background-media',
			$base_uri . 'background-media.js',
			[],
			is_readable( $js_path ) ? (string) filemtime( $js_path ) : null,
			true
		);
	}
	add_action( 'wp_enqueue_scripts', 'sp_background_media_enqueue_frontend_assets', 20 );

	/**
	 * Register the ACF field through the current acf_field API.
	 */
	function sp_register_background_media_field_type(): void {
		static $registered = false;

		if ( $registered || ! class_exists( 'acf_field' ) || ! function_exists( 'acf_register_field_type' ) ) {
			return;
		}

		require_once __DIR__ . '/includes/class-sp-acf-field-background-media.php';
		acf_register_field_type( 'SP_ACF_Field_Background_Media' );
		$registered = true;
	}

	add_action( 'acf/include_field_types', 'sp_register_background_media_field_type', 20 );

	if ( class_exists( 'acf_field' ) ) {
		sp_register_background_media_field_type();
	}
