<?php
/**
 * Configurable Google Reviews summary widgets.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SP_Google_Reviews_Widget_Builder {

	private const OPTION_KEY = 'sp_google_reviews_widgets';
	private const SETTINGS_GROUP = 'sp_google_reviews_widgets';
	private const REVIEW_LANGUAGE_META = '_sp_review_language';
	private const IMPORTER_OPTION = 'sp_reviews_importer_options';
	private const STATS_BY_LANGUAGE_OPTION = 'sp_google_reviews_stats_by_language';
	private const STATS_RATING_OPTION = 'sp_google_reviews_rating';
	private const STATS_COUNT_OPTION = 'sp_google_reviews_count';

	public static function init(): void {
		add_action( 'init', [ __CLASS__, 'register_translation_strings' ], 30 );
	}

	public static function register_settings(): void {
		register_setting( self::SETTINGS_GROUP, self::OPTION_KEY, [
			'type'              => 'array',
			'sanitize_callback' => [ __CLASS__, 'sanitize_widgets' ],
			'default'           => [],
		] );
	}

	private static function defaults(): array {
		return [
			'id'             => 'default',
			'name'           => 'Default widget',
			'preset'         => 'banner',
			'components'     => [ 'avatars', 'stars', 'rating', 'rating_label', 'count_label' ],
			'avatar_count'    => 3,
			'avatar_size'     => 52,
			'avatar_overlap'  => 16,
			'star_size'       => 22,
			'font_size'       => 18,
			'padding_y'       => 18,
			'padding_x'       => 24,
			'gap'             => 22,
			'radius'          => 0,
			'text_color'      => '#ffffff',
			'muted_color'     => '#f2f2f5',
			'star_color'      => '#ff5a1f',
			'background_color'=> '#20232a',
			'background_image'=> '',
			'overlay_opacity' => 35,
			'rating_label'    => 'Rating',
			'count_label'     => 'Based on {count} reviews',
		];
	}

	private static function presets(): array {
		$base = self::defaults();
		return [
			'banner' => $base,
			'compact' => array_merge( $base, [
				'preset'          => 'compact',
				'components'      => [ 'stars', 'rating', 'rating_label', 'count_label' ],
				'avatar_count'     => 0,
				'star_size'        => 17,
				'font_size'        => 14,
				'padding_y'        => 12,
				'padding_x'        => 16,
				'gap'              => 10,
				'radius'           => 8,
				'background_color' => '#171717',
				'overlay_opacity'  => 0,
			] ),
			'minimal' => array_merge( $base, [
				'preset'          => 'minimal',
				'components'      => [ 'stars', 'count_label' ],
				'avatar_count'     => 0,
				'star_size'        => 15,
				'font_size'        => 12,
				'padding_y'        => 8,
				'padding_x'        => 10,
				'gap'              => 6,
				'radius'           => 4,
				'background_color' => '#171717',
				'overlay_opacity'  => 0,
			] ),
		];
	}

	public static function get_widgets(): array {
		$stored = get_option( self::OPTION_KEY, [] );
		if ( ! is_array( $stored ) || $stored === [] ) {
			return [ 'default' => self::defaults() ];
		}

		$widgets = [];
		foreach ( $stored as $key => $widget ) {
			if ( ! is_array( $widget ) ) {
				continue;
			}
			$widget = wp_parse_args( $widget, self::defaults() );
			$id = sanitize_key( (string) ( $widget['id'] ?? $key ) );
			if ( $id !== '' ) {
				$widget['id'] = $id;
				$widgets[ $id ] = $widget;
			}
		}

		return $widgets !== [] ? $widgets : [ 'default' => self::defaults() ];
	}

	public static function sanitize_widgets( $input ): array {
		if ( ! is_array( $input ) ) {
			return [];
		}

		$allowed_components = [ 'avatars', 'stars', 'rating', 'rating_label', 'count_label' ];
		$allowed_presets = array_keys( self::presets() );
		$widgets = [];

		foreach ( array_slice( array_values( $input ), 0, 30 ) as $index => $raw ) {
			if ( ! is_array( $raw ) ) {
				continue;
			}

			$name = sanitize_text_field( (string) ( $raw['name'] ?? '' ) );
			$id = sanitize_title( (string) ( $raw['id'] ?? $name ) );
			if ( $id === '' ) {
				$id = 'widget-' . ( $index + 1 );
			}
			$base_id = $id;
			$suffix = 2;
			while ( isset( $widgets[ $id ] ) ) {
				$id = $base_id . '-' . $suffix++;
			}

			$preset = sanitize_key( (string) ( $raw['preset'] ?? 'banner' ) );
			if ( ! in_array( $preset, $allowed_presets, true ) ) {
				$preset = 'banner';
			}

			$components_raw = $raw['components'] ?? [];
			if ( is_string( $components_raw ) ) {
				$components_raw = explode( ',', $components_raw );
			}
			$components = [];
			foreach ( (array) $components_raw as $component ) {
				$component = sanitize_key( (string) $component );
				if ( in_array( $component, $allowed_components, true ) && ! in_array( $component, $components, true ) ) {
					$components[] = $component;
				}
			}

			$widgets[ $id ] = [
				'id'               => $id,
				'name'             => $name !== '' ? $name : ucwords( str_replace( '-', ' ', $id ) ),
				'preset'           => $preset,
				'components'       => $components,
				'avatar_count'      => self::clamp_int( $raw['avatar_count'] ?? 3, 0, 6 ),
				'avatar_size'       => self::clamp_int( $raw['avatar_size'] ?? 52, 28, 96 ),
				'avatar_overlap'    => self::clamp_int( $raw['avatar_overlap'] ?? 16, 0, 40 ),
				'star_size'         => self::clamp_int( $raw['star_size'] ?? 22, 10, 48 ),
				'font_size'         => self::clamp_int( $raw['font_size'] ?? 18, 10, 36 ),
				'padding_y'         => self::clamp_int( $raw['padding_y'] ?? 18, 0, 80 ),
				'padding_x'         => self::clamp_int( $raw['padding_x'] ?? 24, 0, 100 ),
				'gap'               => self::clamp_int( $raw['gap'] ?? 22, 0, 64 ),
				'radius'            => self::clamp_int( $raw['radius'] ?? 0, 0, 80 ),
				'text_color'        => self::sanitize_color( $raw['text_color'] ?? '#ffffff', '#ffffff' ),
				'muted_color'       => self::sanitize_color( $raw['muted_color'] ?? '#f2f2f5', '#f2f2f5' ),
				'star_color'        => self::sanitize_color( $raw['star_color'] ?? '#ff5a1f', '#ff5a1f' ),
				'background_color'  => self::sanitize_color( $raw['background_color'] ?? '#20232a', '#20232a' ),
				'background_image'  => esc_url_raw( (string) ( $raw['background_image'] ?? '' ) ),
				'overlay_opacity'   => self::clamp_int( $raw['overlay_opacity'] ?? 35, 0, 100 ),
				'rating_label'      => sanitize_text_field( (string) ( $raw['rating_label'] ?? 'Rating' ) ),
				'count_label'       => sanitize_text_field( (string) ( $raw['count_label'] ?? 'Based on {count} reviews' ) ),
			];
		}

		return $widgets;
	}

	private static function clamp_int( $value, int $min, int $max ): int {
		return max( $min, min( $max, (int) $value ) );
	}

	private static function sanitize_color( $value, string $fallback ): string {
		$color = sanitize_hex_color( (string) $value );
		return is_string( $color ) ? $color : $fallback;
	}

	public static function register_translation_strings(): void {
		foreach ( self::get_widgets() as $widget ) {
			$id = (string) $widget['id'];
			foreach ( [ 'rating_label', 'count_label' ] as $field ) {
				$value = (string) $widget[ $field ];
				$name = 'google-reviews-' . $id . '-' . str_replace( '_', '-', $field );
				if ( function_exists( 'pll_register_string' ) ) {
					pll_register_string( $name, $value, 'SP Google Reviews', false );
				}
				do_action( 'wpml_register_single_string', 'SP Google Reviews', $name, $value );
			}
		}
	}

	private static function translate_label( string $widget_id, string $field, string $value ): string {
		$name = 'google-reviews-' . $widget_id . '-' . str_replace( '_', '-', $field );
		if ( function_exists( 'pll__' ) ) {
			$value = pll__( $value );
		}
		$value = (string) apply_filters( 'wpml_translate_single_string', $value, 'SP Google Reviews', $name );
		return (string) apply_filters( 'sp_google_reviews_widget_' . $field, $value, $widget_id );
	}

	public static function render_admin_page(): void {
		$widgets = array_values( self::get_widgets() );
		$presets = self::presets();
		?>
		<div class="wrap sp-gr-admin-wrap sp-gr-builder-page sp-admin-page">
			<header class="sp-admin-header">
				<div class="sp-admin-header__identity">
					<span class="sp-admin-header__icon dashicons dashicons-layout" aria-hidden="true"></span>
					<div class="sp-admin-header__copy">
						<h1>Google Reviews Widgets</h1>
						<p>Create reusable, responsive rating widgets without custom markup.</p>
					</div>
				</div>
				<div class="sp-admin-header__actions">
					<button type="button" class="button" data-sp-gr-add-widget>Add widget</button>
					<button type="submit" class="button button-primary" form="sp-gr-widget-form">Save widgets</button>
				</div>
			</header>

			<?php SP_Reviews_Importer::render_admin_tabs( 'widgets' ); ?>

			<form id="sp-gr-widget-form" method="post" action="options.php">
				<?php settings_fields( self::SETTINGS_GROUP ); ?>
				<div class="sp-gr-builder-intro sp-gr-card">
					<div>
						<h2>Widget library</h2>
						<p>Each widget has a stable ID and its own shortcode. Drag components to change their visual order.</p>
					</div>
					<span class="sp-gr-builder-count"><strong data-sp-gr-widget-count><?php echo esc_html( (string) count( $widgets ) ); ?></strong> widgets</span>
				</div>

				<div class="sp-gr-widget-list" data-sp-gr-widget-list>
					<?php foreach ( $widgets as $index => $widget ) : ?>
						<?php self::render_editor( $widget, (string) $index, $index === 0 ); ?>
					<?php endforeach; ?>
				</div>
			</form>

			<template id="sp-gr-widget-template">
				<?php self::render_editor( $presets['banner'], '__INDEX__', true ); ?>
			</template>
			<script type="application/json" id="sp-gr-widget-presets"><?php echo wp_json_encode( $presets ); ?></script>
		</div>
		<?php
	}

	private static function render_editor( array $widget, string $index, bool $expanded ): void {
		$widget = wp_parse_args( $widget, self::defaults() );
		$name = self::OPTION_KEY . '[' . $index . ']';
		$component_labels = [
			'avatars'     => 'Reviewer avatars',
			'stars'       => 'Stars',
			'rating'      => 'Numeric rating',
			'rating_label'=> 'Rating label',
			'count_label' => 'Review count',
		];
		$active = array_values( array_intersect( (array) $widget['components'], array_keys( $component_labels ) ) );
		?>
		<article class="sp-gr-widget-editor <?php echo $expanded ? 'is-expanded' : ''; ?>" data-sp-gr-widget>
			<header class="sp-gr-widget-editor__header">
				<button type="button" class="sp-gr-widget-editor__toggle" data-sp-gr-toggle aria-expanded="<?php echo $expanded ? 'true' : 'false'; ?>">
					<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
					<span><strong data-sp-gr-card-name><?php echo esc_html( (string) $widget['name'] ); ?></strong><small><code data-sp-gr-card-shortcode>[google_reviews_widget id="<?php echo esc_attr( (string) $widget['id'] ); ?>"]</code></small></span>
				</button>
				<div class="sp-gr-widget-editor__actions">
					<button type="button" class="button button-small" data-sp-gr-copy>Copy shortcode</button>
					<button type="button" class="button-link-delete" data-sp-gr-delete>Delete</button>
				</div>
			</header>

			<div class="sp-gr-widget-editor__body">
				<div class="sp-gr-widget-editor__controls">
					<section class="sp-gr-control-section">
						<h3>Identity & preset</h3>
						<div class="sp-gr-control-grid">
							<label><span>Name</span><input type="text" name="<?php echo esc_attr( $name ); ?>[name]" value="<?php echo esc_attr( (string) $widget['name'] ); ?>" data-sp-gr-name required></label>
							<label><span>Widget ID</span><input type="text" name="<?php echo esc_attr( $name ); ?>[id]" value="<?php echo esc_attr( (string) $widget['id'] ); ?>" data-sp-gr-id pattern="[a-z0-9-]+" required><small>Lowercase letters, numbers and hyphens.</small></label>
							<label class="sp-gr-control-grid__wide"><span>Preset</span><select name="<?php echo esc_attr( $name ); ?>[preset]" data-sp-gr-preset><option value="banner" <?php selected( $widget['preset'], 'banner' ); ?>>Banner</option><option value="compact" <?php selected( $widget['preset'], 'compact' ); ?>>Compact</option><option value="minimal" <?php selected( $widget['preset'], 'minimal' ); ?>>Minimal</option></select><small>Applying a preset replaces visual values in this editor.</small></label>
						</div>
					</section>

					<section class="sp-gr-control-section">
						<h3>Components</h3>
						<p class="description">Enable elements and drag them into the required order.</p>
						<input type="hidden" name="<?php echo esc_attr( $name ); ?>[components]" value="<?php echo esc_attr( implode( ',', $active ) ); ?>" data-sp-gr-components>
						<div class="sp-gr-component-list" data-sp-gr-component-list>
							<?php
							$order = array_merge( $active, array_diff( array_keys( $component_labels ), $active ) );
							foreach ( $order as $component ) :
								$enabled = in_array( $component, $active, true );
								?>
								<div class="sp-gr-component <?php echo $enabled ? 'is-enabled' : ''; ?>" draggable="true" data-component="<?php echo esc_attr( $component ); ?>">
									<span class="dashicons dashicons-menu" aria-hidden="true"></span>
									<label><input type="checkbox" <?php checked( $enabled ); ?> data-sp-gr-component-toggle> <?php echo esc_html( $component_labels[ $component ] ); ?></label>
								</div>
							<?php endforeach; ?>
						</div>
					</section>

					<section class="sp-gr-control-section">
						<h3>Content</h3>
						<div class="sp-gr-control-grid">
							<?php self::number_control( $name, $widget, 'avatar_count', 'Avatars', 0, 6 ); ?>
							<label><span>Rating label</span><input type="text" name="<?php echo esc_attr( $name ); ?>[rating_label]" value="<?php echo esc_attr( (string) $widget['rating_label'] ); ?>" data-setting="rating_label"></label>
							<label class="sp-gr-control-grid__wide"><span>Count label</span><input type="text" name="<?php echo esc_attr( $name ); ?>[count_label]" value="<?php echo esc_attr( (string) $widget['count_label'] ); ?>" data-setting="count_label"><small>Use <code>{count}</code> where the review count should appear.</small></label>
						</div>
					</section>

					<section class="sp-gr-control-section">
						<h3>Appearance</h3>
						<div class="sp-gr-control-grid sp-gr-control-grid--compact">
							<?php self::number_control( $name, $widget, 'avatar_size', 'Avatar size', 28, 96, 'px' ); ?>
							<?php self::number_control( $name, $widget, 'avatar_overlap', 'Avatar overlap', 0, 40, 'px' ); ?>
							<?php self::number_control( $name, $widget, 'star_size', 'Star size', 10, 48, 'px' ); ?>
							<?php self::number_control( $name, $widget, 'font_size', 'Font size', 10, 36, 'px' ); ?>
							<?php self::number_control( $name, $widget, 'gap', 'Gap', 0, 64, 'px' ); ?>
							<?php self::number_control( $name, $widget, 'radius', 'Radius', 0, 80, 'px' ); ?>
							<?php self::number_control( $name, $widget, 'padding_y', 'Vertical padding', 0, 80, 'px' ); ?>
							<?php self::number_control( $name, $widget, 'padding_x', 'Horizontal padding', 0, 100, 'px' ); ?>
							<?php self::color_control( $name, $widget, 'text_color', 'Text' ); ?>
							<?php self::color_control( $name, $widget, 'muted_color', 'Muted text' ); ?>
							<?php self::color_control( $name, $widget, 'star_color', 'Stars' ); ?>
							<?php self::color_control( $name, $widget, 'background_color', 'Background' ); ?>
							<?php self::number_control( $name, $widget, 'overlay_opacity', 'Image overlay', 0, 100, '%' ); ?>
							<label class="sp-gr-control-grid__wide"><span>Background image</span><span class="sp-gr-media-control"><input type="url" name="<?php echo esc_attr( $name ); ?>[background_image]" value="<?php echo esc_attr( (string) $widget['background_image'] ); ?>" data-setting="background_image"><button type="button" class="button" data-sp-gr-media>Select</button><button type="button" class="button-link" data-sp-gr-media-clear>Clear</button></span></label>
						</div>
					</section>
				</div>

				<aside class="sp-gr-widget-editor__preview">
					<div class="sp-gr-preview-toolbar"><strong>Live preview</strong><span>Responsive output</span></div>
					<div class="sp-gr-preview-stage" data-sp-gr-preview-stage>
						<?php echo self::render_widget( $widget, self::preview_context(), true ); ?>
					</div>
				</aside>
			</div>
		</article>
		<?php
	}

	private static function number_control( string $name, array $widget, string $key, string $label, int $min, int $max, string $unit = '' ): void {
		?>
		<label><span><?php echo esc_html( $label ); ?></span><span class="sp-gr-number-control"><input type="number" min="<?php echo esc_attr( (string) $min ); ?>" max="<?php echo esc_attr( (string) $max ); ?>" name="<?php echo esc_attr( $name ); ?>[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( (string) $widget[ $key ] ); ?>" data-setting="<?php echo esc_attr( $key ); ?>"><?php if ( $unit !== '' ) : ?><em><?php echo esc_html( $unit ); ?></em><?php endif; ?></span></label>
		<?php
	}

	private static function color_control( string $name, array $widget, string $key, string $label ): void {
		?>
		<label><span><?php echo esc_html( $label ); ?></span><span class="sp-gr-color-control"><input type="color" name="<?php echo esc_attr( $name ); ?>[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( (string) $widget[ $key ] ); ?>" data-setting="<?php echo esc_attr( $key ); ?>"><code><?php echo esc_html( (string) $widget[ $key ] ); ?></code></span></label>
		<?php
	}

	public static function render_shortcode( $atts ): string {
		$atts = shortcode_atts( [
			'id'         => 'default',
			'show_count' => '',
			'show_stars' => '',
		], is_array( $atts ) ? $atts : [], 'google_reviews_widget' );

		$widgets = self::get_widgets();
		$id = sanitize_key( (string) $atts['id'] );
		$widget = $widgets[ $id ] ?? ( $widgets['default'] ?? reset( $widgets ) );
		if ( ! is_array( $widget ) ) {
			return '';
		}

		// Backward-compatible visibility attributes for the original shortcode.
		foreach ( [ 'show_count' => 'count_label', 'show_stars' => 'stars' ] as $attribute => $component ) {
			if ( $atts[ $attribute ] === '' ) {
				continue;
			}
			$enabled = ! in_array( strtolower( (string) $atts[ $attribute ] ), [ '0', 'false', 'no', 'off' ], true );
			$components = (array) $widget['components'];
			if ( $enabled && ! in_array( $component, $components, true ) ) {
				$components[] = $component;
			} elseif ( ! $enabled ) {
				$components = array_values( array_diff( $components, [ $component ] ) );
			}
			$widget['components'] = $components;
		}

		return self::render_widget( $widget, self::frontend_context( $widget ), false );
	}

	private static function frontend_context( array $widget ): array {
		$options = get_option( self::IMPORTER_OPTION, [] );
		$options = is_array( $options ) ? $options : [];
		$language = self::current_language( (string) ( $options['language'] ?? 'en' ) );
		$stats_by_language = get_option( self::STATS_BY_LANGUAGE_OPTION, [] );
		$language_stats = is_array( $stats_by_language ) && isset( $stats_by_language[ $language ] )
			? (array) $stats_by_language[ $language ]
			: [];
		$fallback_rating = str_replace( ',', '.', trim( (string) ( $options['fallback_rating'] ?? '' ) ) );
		$fallback_count = trim( (string) ( $options['fallback_count'] ?? '' ) );
		$rating = is_numeric( $fallback_rating ) ? (float) $fallback_rating : (float) ( $language_stats['rating'] ?? get_option( self::STATS_RATING_OPTION, 5 ) );
		$count = is_numeric( $fallback_count ) && (int) $fallback_count > 0 ? (int) $fallback_count : (int) ( $language_stats['count'] ?? get_option( self::STATS_COUNT_OPTION, 0 ) );

		return [
			'rating' => $rating > 0 ? min( 5, $rating ) : 5,
			'count'   => $count > 0 ? $count : 1,
			'avatars' => self::get_avatars( (int) $widget['avatar_count'], $language ),
		];
	}

	private static function preview_context(): array {
		return [
			'rating' => 4.9,
			'count'   => 95,
			'avatars' => [
				[ 'url' => '', 'initial' => 'A' ],
				[ 'url' => '', 'initial' => 'M' ],
				[ 'url' => '', 'initial' => 'S' ],
				[ 'url' => '', 'initial' => 'J' ],
				[ 'url' => '', 'initial' => 'K' ],
				[ 'url' => '', 'initial' => 'L' ],
			],
		];
	}

	private static function current_language( string $fallback ): string {
		if ( function_exists( 'pll_current_language' ) ) {
			$language = pll_current_language( 'slug' );
			if ( is_string( $language ) && $language !== '' ) {
				return sanitize_key( $language );
			}
		}
		$language = apply_filters( 'wpml_current_language', null );
		if ( is_string( $language ) && $language !== '' ) {
			return sanitize_key( $language );
		}
		$fallback = strtolower( str_replace( '_', '-', $fallback ) );
		return sanitize_key( explode( '-', $fallback )[0] ?: 'en' );
	}

	private static function get_avatars( int $limit, string $language ): array {
		if ( $limit <= 0 ) {
			return [];
		}
		$query = new WP_Query( [
			'post_type'        => 'review',
			'post_status'      => 'publish',
			'posts_per_page'   => $limit,
			'no_found_rows'    => true,
			'suppress_filters' => true,
			'meta_query'       => [ [ 'key' => self::REVIEW_LANGUAGE_META, 'value' => $language ] ],
		] );
		$avatars = [];
		foreach ( $query->posts as $post ) {
			$title = get_the_title( $post );
			$url = get_the_post_thumbnail_url( $post, 'thumbnail' );
			$initial = function_exists( 'mb_substr' ) ? mb_substr( $title, 0, 1 ) : substr( $title, 0, 1 );
			$avatars[] = [ 'url' => is_string( $url ) ? $url : '', 'initial' => strtoupper( $initial ?: 'G' ) ];
		}
		return $avatars;
	}

	private static function render_widget( array $widget, array $context, bool $preview ): string {
		$widget = wp_parse_args( $widget, self::defaults() );
		$components = (array) $widget['components'];
		$rating = max( 0, min( 5, (float) $context['rating'] ) );
		$count = max( 0, (int) $context['count'] );
		$id = sanitize_key( (string) $widget['id'] );
		$rating_label = self::translate_label( $id, 'rating_label', (string) $widget['rating_label'] );
		$count_label = self::translate_label( $id, 'count_label', (string) $widget['count_label'] );
		$background = esc_url_raw( (string) $widget['background_image'] );
		$background = str_replace( [ '\\', '"', "\r", "\n" ], [ '\\\\', '\\"', '', '' ], $background );
		$style = sprintf(
			'--sp-gr-text:%1$s;--sp-gr-muted:%2$s;--sp-gr-star:%3$s;--sp-gr-bg:%4$s;--sp-gr-avatar-size:%5$dpx;--sp-gr-avatar-overlap:%6$dpx;--sp-gr-star-size:%7$dpx;--sp-gr-font-size:%8$dpx;--sp-gr-pad-y:%9$dpx;--sp-gr-pad-x:%10$dpx;--sp-gr-gap:%11$dpx;--sp-gr-radius:%12$dpx;--sp-gr-overlay:%13$s;--sp-gr-image:%14$s;',
			esc_attr( (string) $widget['text_color'] ), esc_attr( (string) $widget['muted_color'] ), esc_attr( (string) $widget['star_color'] ), esc_attr( (string) $widget['background_color'] ),
			(int) $widget['avatar_size'], (int) $widget['avatar_overlap'], (int) $widget['star_size'], (int) $widget['font_size'], (int) $widget['padding_y'], (int) $widget['padding_x'], (int) $widget['gap'], (int) $widget['radius'],
			$background !== '' ? number_format( (int) $widget['overlay_opacity'] / 100, 2, '.', '' ) : '0', $background !== '' ? 'url("' . $background . '")' : 'none'
		);

		ob_start();
		?>
		<div class="sp-gr-widget sp-gr-widget--<?php echo esc_attr( (string) $widget['preset'] ); ?>" style="<?php echo esc_attr( $style ); ?>" data-sp-gr-rendered-widget>
			<div class="sp-gr-widget__inner">
				<?php foreach ( $components as $component ) : ?>
					<?php if ( $component === 'avatars' && (int) $widget['avatar_count'] > 0 ) : ?>
						<div class="sp-gr-widget__avatars" data-preview-component="avatars">
							<?php foreach ( array_slice( (array) $context['avatars'], 0, (int) $widget['avatar_count'] ) as $avatar ) : ?>
								<span class="sp-gr-widget__avatar"><?php if ( ! empty( $avatar['url'] ) ) : ?><img src="<?php echo esc_url( (string) $avatar['url'] ); ?>" alt="" width="96" height="96" loading="lazy"><?php else : ?><span aria-hidden="true"><?php echo esc_html( (string) $avatar['initial'] ); ?></span><?php endif; ?></span>
							<?php endforeach; ?>
						</div>
					<?php elseif ( $component === 'stars' ) : ?>
						<div class="sp-gr-widget__stars" role="img" aria-label="<?php echo esc_attr( sprintf( '%.1f out of 5 stars', $rating ) ); ?>" data-preview-component="stars"><?php self::render_stars( $rating ); ?></div>
					<?php elseif ( $component === 'rating' ) : ?>
						<strong class="sp-gr-widget__rating" data-preview-component="rating"><?php echo esc_html( number_format_i18n( $rating, 1 ) ); ?></strong>
					<?php elseif ( $component === 'rating_label' ) : ?>
						<span class="sp-gr-widget__rating-label" data-preview-component="rating_label"><?php echo esc_html( $rating_label ); ?></span>
					<?php elseif ( $component === 'count_label' ) : ?>
						<span class="sp-gr-widget__count" data-preview-component="count_label"><?php echo wp_kses( str_replace( '{count}', '<strong>' . esc_html( number_format_i18n( $count ) ) . '</strong>', esc_html( $count_label ) ), [ 'strong' => [] ] ); ?></span>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	private static function render_stars( float $rating ): void {
		$filled = (int) round( $rating );
		for ( $index = 1; $index <= 5; $index++ ) {
			$class = $index <= $filled ? ' is-active' : '';
			echo '<svg class="sp-gr-widget__star' . esc_attr( $class ) . '" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.4l2.93 5.94 6.56.95-4.75 4.63 1.12 6.54L12 17.38l-5.86 3.08 1.12-6.54-4.75-4.63 6.56-.95L12 2.4z"/></svg>';
		}
	}

	public static function frontend_css(): string {
		return <<<'CSS'
.sp-gr-widget{position:relative;isolation:isolate;display:inline-flex;max-width:100%;overflow:hidden;border-radius:var(--sp-gr-radius);background-color:var(--sp-gr-bg);background-image:var(--sp-gr-image);background-position:center;background-size:cover;color:var(--sp-gr-text);font-family:inherit;font-size:var(--sp-gr-font-size);line-height:1.25}
.sp-gr-widget::before{position:absolute;z-index:-1;inset:0;background:rgb(0 0 0 / var(--sp-gr-overlay));content:"";pointer-events:none}
.sp-gr-widget__inner{display:flex;align-items:center;flex-wrap:wrap;gap:calc(var(--sp-gr-gap) * .45) var(--sp-gr-gap);padding:var(--sp-gr-pad-y) var(--sp-gr-pad-x)}
.sp-gr-widget__avatars{display:flex;align-items:center;margin-right:max(0px,calc(var(--sp-gr-avatar-overlap) * -.25))}
.sp-gr-widget__avatar{display:grid;place-items:center;width:var(--sp-gr-avatar-size);height:var(--sp-gr-avatar-size);overflow:hidden;border:2px solid #fff;border-radius:50%;background:#3858e9;color:#fff;font-size:calc(var(--sp-gr-avatar-size) * .42);font-weight:700;box-shadow:0 4px 12px rgb(0 0 0 / 15%)}
.sp-gr-widget__avatar:not(:first-child){margin-left:calc(var(--sp-gr-avatar-overlap) * -1)}
.sp-gr-widget__avatar img{display:block;width:100%;height:100%;object-fit:cover}
.sp-gr-widget__stars{display:inline-flex;gap:2px;color:color-mix(in srgb,var(--sp-gr-star) 20%,transparent)}
.sp-gr-widget__star{width:var(--sp-gr-star-size);height:var(--sp-gr-star-size);fill:currentColor}.sp-gr-widget__star.is-active{color:var(--sp-gr-star)}
.sp-gr-widget__rating{font-size:1.18em;line-height:1}.sp-gr-widget__rating-label,.sp-gr-widget__count{color:var(--sp-gr-muted)}.sp-gr-widget__count{flex-basis:100%}.sp-gr-widget__count strong{color:var(--sp-gr-text)}
.sp-gr-widget--compact .sp-gr-widget__inner{flex-wrap:nowrap}.sp-gr-widget--compact .sp-gr-widget__count{flex-basis:auto;white-space:nowrap}
.sp-gr-widget--minimal .sp-gr-widget__inner{align-items:flex-start;flex-direction:column}.sp-gr-widget--minimal .sp-gr-widget__count{flex-basis:auto}
@media(max-width:480px){.sp-gr-widget--banner{display:flex;width:100%}.sp-gr-widget--banner .sp-gr-widget__inner{gap:10px 14px}.sp-gr-widget--banner .sp-gr-widget__avatars{flex-basis:100%}}
CSS;
	}

	public static function admin_css(): string {
		return self::frontend_css() . <<<'CSS'
.sp-gr-tabs{margin:0 0 18px}.sp-gr-builder-intro{display:flex;align-items:center;justify-content:space-between;gap:20px}.sp-gr-builder-intro h2{margin:0 0 4px}.sp-gr-builder-intro p{margin:0;color:#646970}.sp-gr-builder-count{padding:8px 12px;border-radius:999px;background:#f0f3ff;color:#2746c7;white-space:nowrap}
.sp-gr-widget-list{display:grid;gap:14px}.sp-gr-widget-editor{overflow:hidden;border:1px solid #dcdcde;border-radius:14px;background:#fff;box-shadow:0 1px 2px rgb(0 0 0 / 4%)}.sp-gr-widget-editor__header{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 16px}.sp-gr-widget-editor__toggle{display:flex;flex:1;align-items:center;gap:10px;padding:0;border:0;background:none;text-align:left;cursor:pointer}.sp-gr-widget-editor__toggle>span:last-child{display:grid;gap:3px}.sp-gr-widget-editor__toggle small{color:#646970;font-weight:400}.sp-gr-widget-editor__toggle .dashicons{transition:transform .16s}.sp-gr-widget-editor.is-expanded .sp-gr-widget-editor__toggle .dashicons{transform:rotate(90deg)}.sp-gr-widget-editor__actions{display:flex;align-items:center;gap:12px}.sp-gr-widget-editor__body{display:none;grid-template-columns:minmax(0,1.05fr) minmax(360px,.95fr);border-top:1px solid #e7eaee}.sp-gr-widget-editor.is-expanded .sp-gr-widget-editor__body{display:grid}
.sp-gr-widget-editor__controls{padding:18px}.sp-gr-control-section+ .sp-gr-control-section{margin-top:24px;padding-top:20px;border-top:1px solid #edf0f2}.sp-gr-control-section h3{margin:0 0 12px;font-size:15px}.sp-gr-control-section>.description{margin:-6px 0 12px}.sp-gr-control-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.sp-gr-control-grid--compact{grid-template-columns:repeat(3,minmax(0,1fr))}.sp-gr-control-grid label{display:grid;align-content:start;gap:6px}.sp-gr-control-grid label>span:first-child{color:#3c434a;font-size:12px;font-weight:600}.sp-gr-control-grid input:not([type=color]),.sp-gr-control-grid select{width:100%;min-height:38px}.sp-gr-control-grid small{color:#646970}.sp-gr-control-grid__wide{grid-column:1/-1}.sp-gr-number-control,.sp-gr-color-control,.sp-gr-media-control{display:flex;align-items:center}.sp-gr-number-control input{min-width:0}.sp-gr-number-control em{margin-left:-34px;color:#646970;font-style:normal;pointer-events:none}.sp-gr-color-control{gap:8px}.sp-gr-color-control input{width:44px;height:38px;padding:2px;border:1px solid #8c8f94;border-radius:4px;background:#fff}.sp-gr-media-control{gap:7px}.sp-gr-media-control input{flex:1}
.sp-gr-component-list{display:flex;flex-wrap:wrap;gap:8px}.sp-gr-component{display:flex;align-items:center;gap:5px;padding:8px 10px;border:1px solid #dcdcde;border-radius:8px;background:#f6f7f7;color:#646970;cursor:grab}.sp-gr-component.is-enabled{border-color:#8fa2f1;background:#f0f3ff;color:#1d3fb8}.sp-gr-component.is-dragging{opacity:.45}.sp-gr-component .dashicons{font-size:16px;width:16px;height:16px}.sp-gr-component label{white-space:nowrap}
.sp-gr-widget-editor__preview{position:relative;min-width:0;padding:18px;border-left:1px solid #e7eaee;background:#f6f7f7}.sp-gr-preview-toolbar{display:flex;justify-content:space-between;margin-bottom:12px;color:#646970;font-size:12px}.sp-gr-preview-toolbar strong{color:#1d2327;font-size:13px}.sp-gr-preview-stage{position:sticky;top:46px;display:grid;min-height:300px;place-items:center;padding:28px;overflow:hidden;border:1px solid #dcdcde;border-radius:12px;background-color:#dfe3e8;background-image:linear-gradient(45deg,#eef0f2 25%,transparent 25%),linear-gradient(-45deg,#eef0f2 25%,transparent 25%),linear-gradient(45deg,transparent 75%,#eef0f2 75%),linear-gradient(-45deg,transparent 75%,#eef0f2 75%);background-position:0 0,0 10px,10px -10px,-10px 0;background-size:20px 20px}.sp-gr-preview-stage .sp-gr-widget{max-width:100%}
@media(max-width:1100px){.sp-gr-widget-editor__body{grid-template-columns:1fr}.sp-gr-widget-editor__preview{border-top:1px solid #e7eaee;border-left:0}.sp-gr-preview-stage{position:static;min-height:220px}}@media(max-width:782px){.sp-gr-control-grid,.sp-gr-control-grid--compact{grid-template-columns:1fr}.sp-gr-widget-editor__actions{align-items:flex-end;flex-direction:column}.sp-gr-builder-intro{align-items:flex-start;flex-direction:column}}
CSS;
	}

	public static function admin_js(): string {
		return <<<'JS'
(function($){
	'use strict';
	var presets={};try{presets=JSON.parse($('#sp-gr-widget-presets').text()||'{}');}catch(e){}
	var nextIndex=$('[data-sp-gr-widget]').length;
	function slug(value){return String(value||'').toLowerCase().trim().replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'');}
	function syncComponents($card){var values=[];$card.find('[data-sp-gr-component-list] .is-enabled').each(function(){values.push($(this).data('component'));});$card.find('[data-sp-gr-components]').val(values.join(','));return values;}
	function config($card){var out={};$card.find('[data-setting]').each(function(){out[$(this).data('setting')]=$(this).val();});out.id=$card.find('[data-sp-gr-id]').val();out.name=$card.find('[data-sp-gr-name]').val();out.preset=$card.find('[data-sp-gr-preset]').val();out.components=syncComponents($card);return out;}
	function render($card){var c=config($card),$w=$card.find('[data-sp-gr-rendered-widget]'),$inner=$w.find('.sp-gr-widget__inner'),components=c.components||[];$w.attr('class','sp-gr-widget sp-gr-widget--'+c.preset).css({'--sp-gr-text':c.text_color,'--sp-gr-muted':c.muted_color,'--sp-gr-star':c.star_color,'--sp-gr-bg':c.background_color,'--sp-gr-avatar-size':c.avatar_size+'px','--sp-gr-avatar-overlap':c.avatar_overlap+'px','--sp-gr-star-size':c.star_size+'px','--sp-gr-font-size':c.font_size+'px','--sp-gr-pad-y':c.padding_y+'px','--sp-gr-pad-x':c.padding_x+'px','--sp-gr-gap':c.gap+'px','--sp-gr-radius':c.radius+'px','--sp-gr-overlay':c.background_image?(Number(c.overlay_opacity||0)/100):0,'--sp-gr-image':c.background_image?'url("'+String(c.background_image).replace(/["\\]/g,'')+'")':'none'});components.forEach(function(component){var $component=$inner.find('[data-preview-component="'+component+'"]');if($component.length)$inner.append($component);});$w.find('[data-preview-component]').each(function(){$(this).toggle(components.indexOf($(this).data('preview-component'))!==-1);});$w.find('.sp-gr-widget__avatar').each(function(i){$(this).toggle(i<Number(c.avatar_count||0));});$w.find('[data-preview-component="rating_label"]').text(c.rating_label||'');var count=(c.count_label||'').replace('{count}','95');$w.find('[data-preview-component="count_label"]').text(count);$card.find('[data-sp-card-name]').text(c.name||'Untitled widget');var shortcode='[google_reviews_widget id="'+(slug(c.id)||'widget')+'"]';$card.find('[data-sp-card-shortcode]').text(shortcode);$card.find('.sp-gr-color-control').each(function(){$(this).find('code').text($(this).find('input').val());});}
	function bindCard($card){render($card);}
	$(document).on('click','[data-sp-gr-toggle]',function(){var $card=$(this).closest('[data-sp-gr-widget]'),open=!$card.hasClass('is-expanded');$card.toggleClass('is-expanded',open);$(this).attr('aria-expanded',open?'true':'false');});
	$(document).on('input change','[data-sp-gr-widget] input,[data-sp-gr-widget] select',function(){var $card=$(this).closest('[data-sp-gr-widget]');if($(this).is('[data-sp-gr-name]')&&!$card.data('idTouched')){$card.find('[data-sp-gr-id]').val(slug($(this).val()));}if($(this).is('[data-sp-gr-id]')){$card.data('idTouched',true).find('[data-sp-gr-id]').val(slug($(this).val()));}render($card);});
	$(document).on('change','[data-sp-gr-component-toggle]',function(){var $item=$(this).closest('[data-component]');$item.toggleClass('is-enabled',this.checked);render($(this).closest('[data-sp-gr-widget]'));});
	$(document).on('change','[data-sp-gr-preset]',function(){var p=presets[$(this).val()]||{},$card=$(this).closest('[data-sp-gr-widget]');Object.keys(p).forEach(function(key){if(['id','name','preset','components'].indexOf(key)!==-1)return;$card.find('[data-setting="'+key+'"]').val(p[key]);});$card.find('[data-sp-gr-component-toggle]').prop('checked',false).closest('[data-component]').removeClass('is-enabled');(p.components||[]).forEach(function(key){$card.find('[data-component="'+key+'"]').addClass('is-enabled').find('input').prop('checked',true);});render($card);});
	$(document).on('click','[data-sp-gr-add-widget]',function(){var html=$('#sp-gr-widget-template').html().replace(/__INDEX__/g,String(nextIndex++)),$card=$(html),number=$('[data-sp-gr-widget]').length+1;$card.find('[data-sp-gr-name]').val('Widget '+number);$card.find('[data-sp-gr-id]').val('widget-'+number);$('[data-sp-gr-widget-list]').append($card);$('[data-sp-gr-widget-count]').text($('[data-sp-gr-widget]').length);bindCard($card);$card[0].scrollIntoView({behavior:'smooth',block:'start'});});
	$(document).on('click','[data-sp-gr-delete]',function(){var $cards=$('[data-sp-gr-widget]');if($cards.length===1){window.alert('At least one widget must remain.');return;}if(window.confirm('Delete this widget? Save the page to confirm the change.')){$(this).closest('[data-sp-gr-widget]').remove();$('[data-sp-gr-widget-count]').text($('[data-sp-gr-widget]').length);}});
	$(document).on('click','[data-sp-gr-copy]',function(){var text=$(this).closest('[data-sp-gr-widget]').find('[data-sp-gr-card-shortcode]').text(),button=this;navigator.clipboard.writeText(text).then(function(){var old=button.textContent;button.textContent='Copied';setTimeout(function(){button.textContent=old;},1200);});});
	$(document).on('click','[data-sp-gr-media]',function(){var $input=$(this).siblings('input'),$card=$(this).closest('[data-sp-gr-widget]'),frame=wp.media({title:'Select background image',button:{text:'Use image'},multiple:false});frame.on('select',function(){var item=frame.state().get('selection').first().toJSON();$input.val(item.url).trigger('input');render($card);});frame.open();});
	$(document).on('click','[data-sp-gr-media-clear]',function(){$(this).siblings('input').val('').trigger('input');});
	var dragged=null;$(document).on('dragstart','[data-component]',function(e){dragged=this;$(this).addClass('is-dragging');e.originalEvent.dataTransfer.effectAllowed='move';}).on('dragend','[data-component]',function(){$(this).removeClass('is-dragging');dragged=null;}).on('dragover','[data-component]',function(e){e.preventDefault();if(!dragged||dragged===this)return;var rect=this.getBoundingClientRect(),before=e.originalEvent.clientX<rect.left+rect.width/2;this.parentNode.insertBefore(dragged,before?this:this.nextSibling);}).on('drop','[data-component-list]',function(e){e.preventDefault();render($(this).closest('[data-sp-gr-widget]'));});
	$('[data-sp-gr-widget]').each(function(){bindCard($(this));});
})(jQuery);
JS;
	}
}
