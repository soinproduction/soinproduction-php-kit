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
			'avatar_size_mobile' => 42,
			'avatar_overlap'  => 16,
			'avatar_overlap_mobile' => 12,
			'star_size'       => 22,
			'star_size_mobile' => 18,
			'font_size'       => 18,
			'font_size_mobile' => 16,
			'padding_y'       => 18,
			'padding_y_mobile' => 14,
			'padding_x'       => 24,
			'padding_x_mobile' => 16,
			'gap'             => 22,
			'gap_mobile'      => 14,
			'radius'          => 0,
			'radius_mobile'   => 0,
			'text_color'      => '#ffffff',
			'muted_color'     => '#f2f2f5',
			'star_color'      => '#ff5a1f',
			'background_color'=> '#20232a',
			'background_image'=> '',
			'overlay_opacity' => 35,
			'rating_label'    => 'Rating',
			'count_label'     => 'Based on {count} reviews',
			'link_url'        => '',
			'link_scope'      => 'none',
			'link_target'     => 'same',
			'link_nofollow'   => 0,
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
				'avatar_size_mobile'=> 28,
				'star_size'        => 17,
				'star_size_mobile' => 15,
				'font_size'        => 14,
				'font_size_mobile' => 13,
				'padding_y'        => 12,
				'padding_y_mobile' => 10,
				'padding_x'        => 16,
				'padding_x_mobile' => 12,
				'gap'              => 10,
				'gap_mobile'       => 8,
				'radius'           => 8,
				'radius_mobile'    => 6,
				'background_color' => '#171717',
				'overlay_opacity'  => 0,
			] ),
			'minimal' => array_merge( $base, [
				'preset'          => 'minimal',
				'components'      => [ 'stars', 'count_label' ],
				'avatar_count'     => 0,
				'avatar_size_mobile'=> 28,
				'star_size'        => 15,
				'star_size_mobile' => 13,
				'font_size'        => 12,
				'font_size_mobile' => 11,
				'padding_y'        => 8,
				'padding_y_mobile' => 6,
				'padding_x'        => 10,
				'padding_x_mobile' => 8,
				'gap'              => 6,
				'gap_mobile'       => 5,
				'radius'           => 4,
				'radius_mobile'    => 4,
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
			$enabled_components = (array) $widget['components'];
			$widget['components'] = array_values( array_filter(
				[ 'avatars', 'stars', 'rating', 'rating_label', 'count_label' ],
				static fn( string $component ): bool => in_array( $component, $enabled_components, true )
			) );
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
			if ( in_array( 'avatars', $components, true ) ) {
				$components = array_merge( [ 'avatars' ], array_values( array_diff( $components, [ 'avatars' ] ) ) );
			}
			$enabled_components = $components;
			$components = array_values( array_filter(
				$allowed_components,
				static fn( string $component ): bool => in_array( $component, $enabled_components, true )
			) );

			$widgets[ $id ] = [
				'id'               => $id,
				'name'             => $name !== '' ? $name : ucwords( str_replace( '-', ' ', $id ) ),
				'preset'           => $preset,
				'components'       => $components,
				'avatar_count'      => self::clamp_int( $raw['avatar_count'] ?? 3, 0, 6 ),
				'avatar_size'       => self::clamp_int( $raw['avatar_size'] ?? 52, 28, 96 ),
				'avatar_size_mobile'=> self::clamp_int( $raw['avatar_size_mobile'] ?? 42, 20, 96 ),
				'avatar_overlap'    => self::clamp_int( $raw['avatar_overlap'] ?? 16, 0, 40 ),
				'avatar_overlap_mobile' => self::clamp_int( $raw['avatar_overlap_mobile'] ?? 12, 0, 40 ),
				'star_size'         => self::clamp_int( $raw['star_size'] ?? 22, 10, 48 ),
				'star_size_mobile'  => self::clamp_int( $raw['star_size_mobile'] ?? 18, 8, 48 ),
				'font_size'         => self::clamp_int( $raw['font_size'] ?? 18, 10, 36 ),
				'font_size_mobile'  => self::clamp_int( $raw['font_size_mobile'] ?? 16, 8, 36 ),
				'padding_y'         => self::clamp_int( $raw['padding_y'] ?? 18, 0, 80 ),
				'padding_y_mobile'  => self::clamp_int( $raw['padding_y_mobile'] ?? 14, 0, 80 ),
				'padding_x'         => self::clamp_int( $raw['padding_x'] ?? 24, 0, 100 ),
				'padding_x_mobile'  => self::clamp_int( $raw['padding_x_mobile'] ?? 16, 0, 100 ),
				'gap'               => self::clamp_int( $raw['gap'] ?? 22, 0, 64 ),
				'gap_mobile'        => self::clamp_int( $raw['gap_mobile'] ?? 14, 0, 64 ),
				'radius'            => self::clamp_int( $raw['radius'] ?? 0, 0, 80 ),
				'radius_mobile'     => self::clamp_int( $raw['radius_mobile'] ?? 0, 0, 80 ),
				'text_color'        => self::sanitize_color( $raw['text_color'] ?? '#ffffff', '#ffffff' ),
				'muted_color'       => self::sanitize_color( $raw['muted_color'] ?? '#f2f2f5', '#f2f2f5' ),
				'star_color'        => self::sanitize_color( $raw['star_color'] ?? '#ff5a1f', '#ff5a1f' ),
				'background_color'  => self::sanitize_color( $raw['background_color'] ?? '#20232a', '#20232a' ),
				'background_image'  => esc_url_raw( (string) ( $raw['background_image'] ?? '' ) ),
				'overlay_opacity'   => self::clamp_int( $raw['overlay_opacity'] ?? 35, 0, 100 ),
				'rating_label'      => sanitize_text_field( (string) ( $raw['rating_label'] ?? 'Rating' ) ),
				'count_label'       => sanitize_text_field( (string) ( $raw['count_label'] ?? 'Based on {count} reviews' ) ),
				'link_url'          => esc_url_raw( (string) ( $raw['link_url'] ?? '' ) ),
				'link_scope'        => in_array( (string) ( $raw['link_scope'] ?? 'none' ), [ 'none', 'widget', 'count' ], true ) ? (string) $raw['link_scope'] : 'none',
				'link_target'       => ( $raw['link_target'] ?? 'same' ) === 'blank' ? 'blank' : 'same',
				'link_nofollow'     => empty( $raw['link_nofollow'] ) ? 0 : 1,
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

	private static function brand_palette(): array {
		$palette = function_exists( 'color_palette_config' ) ? color_palette_config() : [];
		if ( ! is_array( $palette ) ) {
			return [];
		}
		$result = [];
		foreach ( $palette as $color => $label ) {
			$color = sanitize_hex_color( (string) $color );
			if ( is_string( $color ) ) {
				$result[ strtolower( $color ) ] = sanitize_text_field( (string) $label );
			}
		}
		return $result;
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
						<p>Each widget has a stable ID, independent design settings and its own shortcode.</p>
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
			<script type="application/json" id="sp-gr-brand-colors"><?php echo wp_json_encode( self::brand_palette() ); ?></script>
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
		<article class="sp-gr-widget-editor sp-admin-card <?php echo $expanded ? 'is-expanded' : ''; ?>" data-sp-gr-widget>
			<header class="sp-gr-widget-editor__header">
				<button type="button" class="sp-gr-widget-editor__toggle" data-sp-gr-toggle aria-expanded="<?php echo $expanded ? 'true' : 'false'; ?>">
					<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
					<span><strong data-sp-gr-card-name><?php echo esc_html( (string) $widget['name'] ); ?></strong><small><code data-sp-gr-card-shortcode>[google_reviews_widget id="<?php echo esc_attr( (string) $widget['id'] ); ?>"]</code></small></span>
				</button>
				<div class="sp-gr-widget-editor__actions">
					<button type="button" class="button button-small" data-sp-gr-copy>Copy shortcode</button>
					<button type="button" class="button button-small" data-sp-gr-duplicate>Duplicate</button>
					<button type="button" class="button button-small sp-gr-delete-button" data-sp-gr-delete>
						<span class="dashicons dashicons-trash" aria-hidden="true"></span>
						Delete
					</button>
				</div>
			</header>

			<div class="sp-gr-widget-editor__body">
				<div class="sp-gr-widget-editor__controls">
					<section class="sp-gr-control-section">
						<h3>Identity & preset</h3>
						<div class="sp-gr-control-grid sp-gr-control-grid--identity">
							<label><span>Name</span><input type="text" name="<?php echo esc_attr( $name ); ?>[name]" value="<?php echo esc_attr( (string) $widget['name'] ); ?>" data-sp-gr-name required></label>
							<label><span>Widget ID</span><input type="text" name="<?php echo esc_attr( $name ); ?>[id]" value="<?php echo esc_attr( (string) $widget['id'] ); ?>" data-sp-gr-id pattern="[a-z0-9-]+" required><small>Lowercase letters, numbers and hyphens.</small></label>
							<label><span>Preset</span><select name="<?php echo esc_attr( $name ); ?>[preset]" data-sp-gr-preset><option value="banner" <?php selected( $widget['preset'], 'banner' ); ?>>Banner</option><option value="compact" <?php selected( $widget['preset'], 'compact' ); ?>>Compact</option><option value="minimal" <?php selected( $widget['preset'], 'minimal' ); ?>>Minimal</option></select><small>Applying a preset replaces visual values in this editor.</small></label>
						</div>
					</section>

					<section class="sp-gr-control-section">
						<h3>Components</h3>
						<p class="description">Choose what the widget displays. The selected preset controls the layout.</p>
						<input type="hidden" name="<?php echo esc_attr( $name ); ?>[components]" value="<?php echo esc_attr( implode( ',', $active ) ); ?>" data-sp-gr-components>
						<div class="sp-gr-component-list" data-sp-gr-component-list>
							<?php
							$order = array_merge( $active, array_diff( array_keys( $component_labels ), $active ) );
							foreach ( $order as $component ) :
								$enabled = in_array( $component, $active, true );
								?>
								<div class="sp-gr-component <?php echo $enabled ? 'is-enabled' : ''; ?>" data-component="<?php echo esc_attr( $component ); ?>">
									<button type="button" role="switch" aria-checked="<?php echo $enabled ? 'true' : 'false'; ?>" data-sp-gr-component-toggle><span><?php echo esc_html( $component_labels[ $component ] ); ?></span><i aria-hidden="true"></i></button>
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
						<h3>Interaction</h3>
						<p class="description">Add a structured, safe link without placing HTML inside the labels.</p>
						<div class="sp-gr-control-grid sp-gr-control-grid--interaction">
							<label class="sp-gr-control-grid__wide"><span>Destination URL</span><input type="url" name="<?php echo esc_attr( $name ); ?>[link_url]" value="<?php echo esc_attr( (string) $widget['link_url'] ); ?>" placeholder="https://example.com/reviews" data-setting="link_url"></label>
							<label><span>Clickable area</span><select name="<?php echo esc_attr( $name ); ?>[link_scope]" data-setting="link_scope"><option value="none" <?php selected( $widget['link_scope'], 'none' ); ?>>No link</option><option value="widget" <?php selected( $widget['link_scope'], 'widget' ); ?>>Entire widget</option><option value="count" <?php selected( $widget['link_scope'], 'count' ); ?>>Review count only</option></select></label>
							<div class="sp-gr-link-options">
								<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[link_target]" value="blank" data-setting="link_target" <?php checked( $widget['link_target'], 'blank' ); ?>><span>Open in a new tab</span></label>
								<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[link_nofollow]" value="1" data-setting="link_nofollow" <?php checked( ! empty( $widget['link_nofollow'] ) ); ?>><span>Add nofollow</span></label>
							</div>
						</div>
					</section>

					<section class="sp-gr-control-section">
						<h3>Appearance</h3>
						<div class="sp-gr-control-grid sp-gr-control-grid--compact">
							<?php self::responsive_number_control( $name, $widget, 'avatar_size', 'Avatar size', 20, 96 ); ?>
							<?php self::responsive_number_control( $name, $widget, 'avatar_overlap', 'Avatar overlap', 0, 40 ); ?>
							<?php self::responsive_number_control( $name, $widget, 'star_size', 'Star size', 8, 48 ); ?>
							<?php self::responsive_number_control( $name, $widget, 'font_size', 'Font size', 8, 36 ); ?>
							<?php self::responsive_number_control( $name, $widget, 'gap', 'Gap', 0, 64 ); ?>
							<?php self::responsive_number_control( $name, $widget, 'radius', 'Radius', 0, 80 ); ?>
							<?php self::responsive_number_control( $name, $widget, 'padding_y', 'Vertical padding', 0, 80 ); ?>
							<?php self::responsive_number_control( $name, $widget, 'padding_x', 'Horizontal padding', 0, 100 ); ?>
						</div>
						<div class="sp-gr-color-grid">
							<?php self::color_control( $name, $widget, 'text_color', 'Text' ); ?>
							<?php self::color_control( $name, $widget, 'muted_color', 'Muted text' ); ?>
							<?php self::color_control( $name, $widget, 'star_color', 'Stars' ); ?>
							<?php self::color_control( $name, $widget, 'background_color', 'Background' ); ?>
						</div>
						<div class="sp-gr-asset-grid">
							<?php self::number_control( $name, $widget, 'overlay_opacity', 'Image overlay', 0, 100, '%' ); ?>
							<label><span>Background image</span><span class="sp-gr-media-control"><input type="url" name="<?php echo esc_attr( $name ); ?>[background_image]" value="<?php echo esc_attr( (string) $widget['background_image'] ); ?>" data-setting="background_image"><button type="button" class="button" data-sp-gr-media>Select</button><button type="button" class="button-link" data-sp-gr-media-clear>Clear</button></span></label>
						</div>
					</section>
				</div>

				<aside class="sp-gr-widget-editor__preview">
					<div class="sp-gr-preview-toolbar"><strong>Live preview</strong><span class="sp-gr-preview-modes"><button type="button" class="button button-small button-primary is-active" aria-pressed="true" data-sp-gr-preview-mode="desktop">Desktop</button><button type="button" class="button button-small" aria-pressed="false" data-sp-gr-preview-mode="mobile">Mobile</button></span></div>
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

	private static function responsive_number_control( string $name, array $widget, string $key, string $label, int $min, int $max ): void {
		$mobile_key = $key . '_mobile';
		?>
		<label class="sp-gr-responsive-control">
			<span><?php echo esc_html( $label ); ?></span>
			<span class="sp-gr-responsive-values">
				<span><small>Desktop</small><span class="sp-gr-number-control"><input type="number" min="<?php echo esc_attr( (string) $min ); ?>" max="<?php echo esc_attr( (string) $max ); ?>" name="<?php echo esc_attr( $name ); ?>[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( (string) $widget[ $key ] ); ?>" data-setting="<?php echo esc_attr( $key ); ?>"><em>px</em></span></span>
				<span><small>Mobile</small><span class="sp-gr-number-control"><input type="number" min="<?php echo esc_attr( (string) $min ); ?>" max="<?php echo esc_attr( (string) $max ); ?>" name="<?php echo esc_attr( $name ); ?>[<?php echo esc_attr( $mobile_key ); ?>]" value="<?php echo esc_attr( (string) $widget[ $mobile_key ] ); ?>" data-setting="<?php echo esc_attr( $mobile_key ); ?>"><em>px</em></span></span>
			</span>
		</label>
		<?php
	}

	private static function color_control( string $name, array $widget, string $key, string $label ): void {
		?>
		<label><span><?php echo esc_html( $label ); ?></span><span class="sp-gr-color-control"><input type="text" class="sp-gr-color-picker" name="<?php echo esc_attr( $name ); ?>[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( (string) $widget[ $key ] ); ?>" data-setting="<?php echo esc_attr( $key ); ?>"></span></label>
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

	private static function fluid_rem( int $mobile, int $desktop ): string {
		if ( $mobile === $desktop ) {
			return number_format( $mobile / 10, 3, '.', '' ) . 'rem';
		}
		$vw = ( $desktop - $mobile ) * 100 / ( 1440 - 375 );
		$intercept_rem = ( $mobile - ( $vw * 3.75 ) ) / 10;
		$minimum = min( $mobile, $desktop ) / 10;
		$maximum = max( $mobile, $desktop ) / 10;
		return sprintf( 'clamp(%.3frem, calc(%.3frem + %.3fvw), %.3frem)', $minimum, $intercept_rem, $vw, $maximum );
	}

	private static function render_widget( array $widget, array $context, bool $preview ): string {
		$widget = wp_parse_args( $widget, self::defaults() );
		$components = (array) $widget['components'];
		$render_components = $preview ? [ 'stars', 'rating', 'rating_label', 'count_label' ] : array_values( array_diff( $components, [ 'avatars' ] ) );
		$rating = max( 0, min( 5, (float) $context['rating'] ) );
		$count = max( 0, (int) $context['count'] );
		$id = sanitize_key( (string) $widget['id'] );
		$rating_label = self::translate_label( $id, 'rating_label', (string) $widget['rating_label'] );
		$count_label = self::translate_label( $id, 'count_label', (string) $widget['count_label'] );
		$background = esc_url_raw( (string) $widget['background_image'] );
		$background = str_replace( [ '\\', '"', "\r", "\n" ], [ '\\\\', '\\"', '', '' ], $background );
		$link_url = esc_url( (string) $widget['link_url'] );
		$link_scope = in_array( (string) $widget['link_scope'], [ 'widget', 'count' ], true ) && $link_url !== '' ? (string) $widget['link_scope'] : 'none';
		$link_target = (string) $widget['link_target'] === 'blank' ? '_blank' : '';
		$link_rel = array_filter( [ $link_target === '_blank' ? 'noopener' : '', ! empty( $widget['link_nofollow'] ) ? 'nofollow' : '' ] );
		$link_attributes = $link_url !== '' ? ' href="' . esc_url( $link_url ) . '"' : '';
		$link_attributes .= $link_target !== '' ? ' target="_blank"' : '';
		$link_attributes .= $link_rel !== [] ? ' rel="' . esc_attr( implode( ' ', $link_rel ) ) . '"' : '';
		$wrapper_tag = ! $preview && $link_scope === 'widget' ? 'a' : 'div';
		$formatted_count = wp_kses( str_replace( '{count}', '<strong>' . esc_html( number_format_i18n( $count ) ) . '</strong>', esc_html( $count_label ) ), [ 'strong' => [] ] );
		$style = sprintf(
			'--sp-gr-text:%1$s;--sp-gr-muted:%2$s;--sp-gr-star:%3$s;--sp-gr-bg:%4$s;--sp-gr-avatar-size:%5$s;--sp-gr-avatar-overlap:%6$s;--sp-gr-star-size:%7$s;--sp-gr-font-size:%8$s;--sp-gr-pad-y:%9$s;--sp-gr-pad-x:%10$s;--sp-gr-gap:%11$s;--sp-gr-radius:%12$s;--sp-gr-overlay:%13$s;--sp-gr-image:%14$s;',
			esc_attr( (string) $widget['text_color'] ), esc_attr( (string) $widget['muted_color'] ), esc_attr( (string) $widget['star_color'] ), esc_attr( (string) $widget['background_color'] ),
			self::fluid_rem( (int) $widget['avatar_size_mobile'], (int) $widget['avatar_size'] ), self::fluid_rem( (int) $widget['avatar_overlap_mobile'], (int) $widget['avatar_overlap'] ), self::fluid_rem( (int) $widget['star_size_mobile'], (int) $widget['star_size'] ), self::fluid_rem( (int) $widget['font_size_mobile'], (int) $widget['font_size'] ), self::fluid_rem( (int) $widget['padding_y_mobile'], (int) $widget['padding_y'] ), self::fluid_rem( (int) $widget['padding_x_mobile'], (int) $widget['padding_x'] ), self::fluid_rem( (int) $widget['gap_mobile'], (int) $widget['gap'] ), self::fluid_rem( (int) $widget['radius_mobile'], (int) $widget['radius'] ),
			$background !== '' ? number_format( (int) $widget['overlay_opacity'] / 100, 2, '.', '' ) : '0', $background !== '' ? 'url("' . $background . '")' : 'none'
		);

		ob_start();
		?>
		<<?php echo esc_html( $wrapper_tag ); ?> class="sp-gr-widget sp-gr-widget--<?php echo esc_attr( (string) $widget['preset'] ); ?><?php echo $link_scope !== 'none' ? ' is-linked' : ''; ?>" style="<?php echo esc_attr( $style ); ?>" data-sp-gr-rendered-widget<?php echo $wrapper_tag === 'a' ? $link_attributes : ''; ?>>
			<div class="sp-gr-widget__inner">
				<?php if ( ( $preview || in_array( 'avatars', $components, true ) ) && (int) $widget['avatar_count'] > 0 ) : ?>
						<div class="sp-gr-widget__avatars" data-preview-component="avatars">
							<?php foreach ( array_slice( (array) $context['avatars'], 0, (int) $widget['avatar_count'] ) as $avatar ) : ?>
								<span class="sp-gr-widget__avatar"><?php if ( ! empty( $avatar['url'] ) ) : ?><img src="<?php echo esc_url( (string) $avatar['url'] ); ?>" alt="" width="96" height="96" loading="lazy"><?php else : ?><span aria-hidden="true"><?php echo esc_html( (string) $avatar['initial'] ); ?></span><?php endif; ?></span>
							<?php endforeach; ?>
						</div>
				<?php endif; ?>
				<div class="sp-gr-widget__content">
				<?php foreach ( $render_components as $component ) : ?>
					<?php if ( $component === 'stars' ) : ?>
						<div class="sp-gr-widget__stars" role="img" aria-label="<?php echo esc_attr( sprintf( '%.1f out of 5 stars', $rating ) ); ?>" data-preview-component="stars"><?php self::render_stars( $rating ); ?></div>
					<?php elseif ( $component === 'rating' ) : ?>
						<strong class="sp-gr-widget__rating" data-preview-component="rating"><?php echo esc_html( number_format_i18n( $rating, 1 ) ); ?></strong>
					<?php elseif ( $component === 'rating_label' ) : ?>
						<span class="sp-gr-widget__rating-label" data-preview-component="rating_label"><?php echo esc_html( $rating_label ); ?></span>
					<?php elseif ( $component === 'count_label' ) : ?>
						<span class="sp-gr-widget__count" data-preview-component="count_label"><?php if ( ! $preview && $link_scope === 'count' ) : ?><a class="sp-gr-widget__count-link"<?php echo $link_attributes; ?>><?php echo $formatted_count; ?></a><?php else : ?><?php echo $formatted_count; ?><?php endif; ?></span>
					<?php endif; ?>
				<?php endforeach; ?>
				</div>
			</div>
		</<?php echo esc_html( $wrapper_tag ); ?>>
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
.sp-gr-widget{position:relative;isolation:isolate;display:inline-flex;max-width:100%;height:max-content;overflow:hidden;border-radius:var(--sp-gr-radius);background-color:var(--sp-gr-bg);background-image:var(--sp-gr-image);background-position:center;background-size:cover;color:var(--sp-gr-text);font-family:inherit;font-size:var(--sp-gr-font-size);line-height:1.25}
.sp-gr-widget.is-linked{text-decoration:none;cursor:pointer}.sp-gr-widget.is-linked:focus-visible{outline:3px solid currentColor;outline-offset:3px}.sp-gr-widget__count-link{color:inherit;text-decoration:underline;text-decoration-thickness:.08em;text-underline-offset:.14em}.sp-gr-widget__count-link:hover{text-decoration-thickness:.14em}
.sp-gr-widget::before{position:absolute;z-index:-1;inset:0;background:rgb(0 0 0 / var(--sp-gr-overlay));content:"";pointer-events:none}
.sp-gr-widget__inner{display:flex;align-items:center;gap:var(--sp-gr-gap);padding:var(--sp-gr-pad-y) var(--sp-gr-pad-x)}
.sp-gr-widget__content{display:flex;align-items:center;flex:1;flex-wrap:wrap;gap:calc(var(--sp-gr-gap) * .3) calc(var(--sp-gr-gap) * .45);min-width:0}
.sp-gr-widget__avatars{display:flex;align-items:center;margin-right:max(0px,calc(var(--sp-gr-avatar-overlap) * -.25))}
.sp-gr-widget__avatar{display:grid;place-items:center;width:var(--sp-gr-avatar-size);height:var(--sp-gr-avatar-size);overflow:hidden;border:2px solid #fff;border-radius:50%;background:#3858e9;color:#fff;font-size:calc(var(--sp-gr-avatar-size) * .42);font-weight:700;box-shadow:0 4px 12px rgb(0 0 0 / 15%)}
.sp-gr-widget__avatar:not(:first-child){margin-left:calc(var(--sp-gr-avatar-overlap) * -1)}
.sp-gr-widget__avatar img{display:block;width:100%;height:100%;object-fit:cover}
.sp-gr-widget__stars{display:inline-flex;gap:2px;color:color-mix(in srgb,var(--sp-gr-star) 20%,transparent)}
.sp-gr-widget__star{width:var(--sp-gr-star-size);height:var(--sp-gr-star-size);fill:currentColor}.sp-gr-widget__star.is-active{color:var(--sp-gr-star)}
.sp-gr-widget__rating{font-size:1.18em;line-height:1}.sp-gr-widget__rating-label,.sp-gr-widget__count{color:var(--sp-gr-muted)}.sp-gr-widget__count{flex-basis:100%}.sp-gr-widget__count strong{color:var(--sp-gr-text)}
.sp-gr-widget--compact .sp-gr-widget__content{flex-wrap:nowrap}.sp-gr-widget--compact .sp-gr-widget__count{flex-basis:auto;white-space:nowrap}
.sp-gr-widget--minimal .sp-gr-widget__inner{align-items:flex-start}.sp-gr-widget--minimal .sp-gr-widget__content{align-items:flex-start;flex-direction:column}.sp-gr-widget--minimal .sp-gr-widget__count{flex-basis:auto}
@media(max-width:480px){.sp-gr-widget--banner{display:flex;width:100%}.sp-gr-widget--banner .sp-gr-widget__inner{align-items:flex-start;flex-direction:column;gap:12px}}
CSS;
	}

	public static function admin_css(): string {
		return self::frontend_css() . <<<'CSS'
.sp-gr-admin-wrap.sp-gr-builder-page{width:auto;max-width:none}.sp-gr-tabs{margin-bottom:20px}.sp-gr-builder-intro{display:flex;align-items:center;justify-content:space-between;gap:20px;padding:18px 20px}.sp-gr-builder-intro h2{margin:0 0 4px;font-size:18px}.sp-gr-builder-intro p{margin:0;color:var(--sp-admin-muted,#646970)}.sp-gr-builder-count{padding:7px 11px;border-radius:var(--sp-admin-radius-pill,0);background:var(--sp-admin-accent-soft,#eef2ff);color:var(--sp-admin-accent,#2746c7);white-space:nowrap}
.sp-gr-widget-list{display:grid;gap:16px}.sp-admin-page .sp-gr-widget-editor{padding:0;overflow:hidden;border-color:var(--sp-admin-border,#dfe3e8);border-radius:var(--sp-admin-radius,0);background:var(--sp-admin-surface,#fff);box-shadow:var(--sp-admin-shadow-xs,0 1px 2px rgb(16 24 40 / 4%))}.sp-gr-widget-editor.is-expanded{border-color:var(--sp-admin-border-strong,#c9d1dc);box-shadow:var(--sp-admin-shadow,0 8px 24px rgb(16 24 40 / 7%))}.sp-gr-widget-editor__header{display:flex;align-items:center;justify-content:space-between;gap:16px;min-height:58px;padding:10px 16px}.sp-gr-widget-editor__toggle{display:flex;flex:1;align-items:center;gap:10px;min-width:0;padding:0;border:0;background:none;text-align:left;cursor:pointer}.sp-gr-widget-editor__toggle>span:last-child{display:grid;gap:3px;min-width:0}.sp-gr-widget-editor__toggle strong{font-size:14px}.sp-gr-widget-editor__toggle small{overflow:hidden;color:var(--sp-admin-muted,#646970);font-weight:400;text-overflow:ellipsis;white-space:nowrap}.sp-gr-widget-editor__toggle code{padding:0;background:none}.sp-gr-widget-editor__toggle .dashicons{color:var(--sp-admin-muted,#667085);transition:transform var(--sp-admin-transition,.16s)}.sp-gr-widget-editor.is-expanded .sp-gr-widget-editor__toggle .dashicons{transform:rotate(90deg)}.sp-gr-widget-editor__actions{display:flex;align-items:center;gap:8px}.sp-gr-widget-editor__body{display:none;grid-template-columns:minmax(520px,1fr) minmax(420px,.9fr);border-top:1px solid var(--sp-admin-border,#e7eaee)}.sp-gr-widget-editor.is-expanded .sp-gr-widget-editor__body{display:grid}.sp-gr-widget-list>.sp-gr-widget-editor:only-child [data-sp-gr-delete]{display:none}
.sp-gr-delete-button{display:inline-flex!important;align-items:center;gap:4px!important;border-color:#f1b8b3!important;background:#fff7f6!important;color:#b42318!important}.sp-gr-delete-button .dashicons{width:15px;height:15px;font-size:15px;line-height:15px}.sp-gr-delete-button:hover,.sp-gr-delete-button:focus{border-color:#d92d20!important;background:#d92d20!important;color:#fff!important}.sp-gr-delete-button:focus{box-shadow:0 0 0 1px #fff,0 0 0 3px #d92d20!important}
.sp-gr-widget-editor__controls{padding:18px}.sp-gr-control-section+ .sp-gr-control-section{margin-top:24px;padding-top:20px;border-top:1px solid var(--sp-admin-border,#edf0f2)}.sp-gr-control-section h3{margin:0 0 12px;font-size:15px}.sp-gr-control-section>.description{margin:-6px 0 12px}.sp-gr-control-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.sp-gr-control-grid--identity,.sp-gr-control-grid--compact{grid-template-columns:repeat(3,minmax(0,1fr))}.sp-gr-control-grid label{display:grid;align-content:start;gap:6px}.sp-gr-control-grid label>span:first-child{color:var(--sp-admin-text-2,#3c434a);font-size:12px;font-weight:600}.sp-gr-control-grid input,.sp-gr-control-grid select{width:100%;min-height:38px}.sp-gr-control-grid small{color:var(--sp-admin-muted,#646970)}.sp-gr-control-grid__wide{grid-column:1/-1}.sp-gr-number-control,.sp-gr-color-control,.sp-gr-media-control{display:flex;align-items:center}.sp-gr-number-control{position:relative}.sp-gr-number-control input{min-width:0;padding-right:32px!important;-moz-appearance:textfield}.sp-gr-number-control input::-webkit-inner-spin-button,.sp-gr-number-control input::-webkit-outer-spin-button{margin:0;-webkit-appearance:none}.sp-gr-number-control em{position:absolute;right:10px;color:var(--sp-admin-muted,#646970);font-style:normal;pointer-events:none}.sp-gr-responsive-values{display:grid!important;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.sp-gr-responsive-values>span{display:grid;gap:4px}.sp-gr-responsive-values small{font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.04em}.sp-gr-color-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-top:18px;padding-top:18px;border-top:1px solid var(--sp-admin-border,#edf0f2)}.sp-gr-color-grid>label,.sp-gr-asset-grid>label{display:grid;align-content:start;gap:6px}.sp-gr-color-grid>label>span:first-child,.sp-gr-asset-grid>label>span:first-child{color:var(--sp-admin-text-2,#3c434a);font-size:12px;font-weight:600}.sp-gr-color-control{display:block}.sp-gr-color-control .wp-picker-container{display:block}.sp-gr-color-control .wp-color-result{margin:0}.sp-gr-asset-grid{display:grid;grid-template-columns:minmax(160px,1fr) minmax(0,3fr);gap:14px;margin-top:14px;align-items:end}.sp-gr-asset-grid input{width:100%;min-height:38px}.sp-gr-media-control{gap:7px}.sp-gr-media-control input{flex:1}.sp-gr-link-options{display:flex;align-items:center;gap:18px;padding-top:24px}.sp-gr-link-options label{display:flex;align-items:center;gap:7px}.sp-gr-link-options input{width:auto;min-height:0}.sp-gr-admin-wrap input[type="checkbox"]{border-radius:var(--sp-admin-radius-xs,0)}
.sp-gr-component-list{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px}.sp-gr-component{min-width:0}.sp-gr-component button{display:flex;align-items:center;justify-content:space-between;gap:12px;width:100%;min-height:42px;padding:8px 10px;border:1px solid var(--sp-admin-border,#dfe3e8);border-radius:var(--sp-admin-radius-sm,0);background:var(--sp-admin-surface-alt,#f8fafc);color:var(--sp-admin-text-2,#475467);font:inherit;font-weight:600;text-align:left;cursor:pointer;transition:border-color var(--sp-admin-transition,.15s),background var(--sp-admin-transition,.15s),color var(--sp-admin-transition,.15s)}.sp-gr-component i{position:relative;flex:0 0 34px;width:34px;height:20px;border-radius:var(--sp-admin-radius-pill,0);background:var(--sp-admin-border-strong,#c9d1dc);box-shadow:none;transition:background var(--sp-admin-transition,.15s)}.sp-gr-component i::after{position:absolute;top:3px;left:3px;width:14px;height:14px;border-radius:var(--sp-admin-radius-pill,0);background:var(--sp-admin-surface,#fff);box-shadow:none;content:"";transition:transform var(--sp-admin-transition,.15s)}.sp-gr-component.is-enabled button{border-color:var(--sp-admin-accent,#3858e9);background:var(--sp-admin-accent-soft,#f5f7ff);color:var(--sp-admin-accent-hover,#2442b5)}.sp-gr-component.is-enabled i{background:var(--sp-admin-accent,#3858e9)}.sp-gr-component.is-enabled i::after{transform:translateX(14px)}.sp-gr-component button:focus-visible{outline:2px solid var(--sp-admin-accent,#3858e9);outline-offset:2px}
.sp-gr-widget-editor__preview{position:relative;min-width:0;padding:18px;border-left:1px solid var(--sp-admin-border,#e7eaee);background:var(--sp-admin-surface-alt,#f6f7f7)}.sp-gr-preview-toolbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;color:var(--sp-admin-muted,#646970);font-size:12px}.sp-gr-preview-toolbar strong{color:var(--sp-admin-text,#1d2327);font-size:13px}.sp-gr-preview-modes{display:inline-flex;gap:6px}.sp-gr-preview-modes .button{margin:0}.sp-gr-preview-stage{position:sticky;top:46px;display:grid;min-height:300px;place-items:center;padding:28px;overflow:hidden;border:1px solid var(--sp-admin-border,#dcdcde);border-radius:var(--sp-admin-radius,0);background-color:#dfe3e8;background-image:linear-gradient(45deg,#eef0f2 25%,transparent 25%),linear-gradient(-45deg,#eef0f2 25%,transparent 25%),linear-gradient(45deg,transparent 75%,#eef0f2 75%),linear-gradient(-45deg,transparent 75%,#eef0f2 75%);background-position:0 0,0 10px,10px -10px,-10px 0;background-size:20px 20px}.sp-gr-preview-stage .sp-gr-widget{max-width:100%}.sp-gr-preview-stage.is-mobile{width:375px;max-width:100%;margin-inline:auto;padding:18px}.sp-gr-preview-stage.is-mobile .sp-gr-widget--banner{display:flex;width:100%}.sp-gr-preview-stage.is-mobile .sp-gr-widget--banner .sp-gr-widget__inner{align-items:flex-start;flex-direction:column;gap:12px}
@media(max-width:1180px){.sp-gr-widget-editor__body{grid-template-columns:1fr}.sp-gr-widget-editor__preview{border-top:1px solid #e7eaee;border-left:0}.sp-gr-preview-stage{position:static;min-height:220px}}@media(max-width:782px){.sp-gr-control-grid,.sp-gr-control-grid--compact,.sp-gr-component-list,.sp-gr-color-grid,.sp-gr-asset-grid{grid-template-columns:1fr}.sp-gr-link-options{align-items:flex-start;flex-direction:column;padding-top:0}.sp-gr-widget-editor__header{align-items:flex-start;flex-direction:column}.sp-gr-widget-editor__actions{width:100%}.sp-gr-builder-intro{align-items:flex-start;flex-direction:column}.sp-gr-tabs{display:flex}.sp-gr-tabs .nav-tab{flex:1;text-align:center}}
CSS;
	}

	public static function admin_js(): string {
		return <<<'JS'
(function($){
	'use strict';
	var presets={};
	var brandColors={};
	var nextIndex=$('[data-sp-gr-widget]').length;
	try{presets=JSON.parse($('#sp-gr-widget-presets').text()||'{}');}catch(error){presets={};}
	try{brandColors=JSON.parse($('#sp-gr-brand-colors').text()||'{}');}catch(error){brandColors={};}

	function initColorPickers($scope){
		if(typeof $.fn.wpColorPicker!=='function'){return;}
		$scope.find('.sp-gr-color-picker').each(function(){
			var $input=$(this);
			if($input.hasClass('wp-color-picker')){return;}
			$input.wpColorPicker({palettes:Object.keys(brandColors),change:function(event,ui){$input.val(ui.color.toString()).trigger('input');},clear:function(){$input.val('').trigger('input');}});
		});
	}

	function slug(value){
		return String(value||'').toLowerCase().trim().replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'');
	}

	function uniqueId(base,$ignore){
		base=slug(base)||'widget';
		var candidate=base,suffix=2;
		while($('[data-sp-gr-id]').not($ignore).filter(function(){return $(this).val()===candidate;}).length){candidate=base+'-'+suffix++;}
		return candidate;
	}

	function updateCount(){
		var count=$('[data-sp-gr-widget]').length;
		$('[data-sp-gr-widget-count]').text(count);
		$('.sp-gr-builder-count').contents().last()[0].textContent=count===1?' widget':' widgets';
	}

	function syncComponents($card){
		var values=[];
		$card.find('[data-sp-gr-component-list] .is-enabled').each(function(){values.push($(this).data('component'));});
		$card.find('[data-sp-gr-components]').val(values.join(','));
		return values;
	}

	function config($card){
		var output={};
		$card.find('[data-setting]').each(function(){var $field=$(this),key=$field.data('setting');if($field.is(':checkbox')){output[key]=$field.prop('checked')?$field.val():key==='link_target'?'same':'0';}else{output[key]=$field.val();}});
		output.id=$card.find('[data-sp-gr-id]').val();
		output.name=$card.find('[data-sp-gr-name]').val();
		output.preset=$card.find('[data-sp-gr-preset]').val();
		output.components=syncComponents($card);
		return output;
	}

	function render($card){
		var settings=config($card);
		var $widget=$card.find('[data-sp-gr-rendered-widget]');
		var components=settings.components||[];
		var mobile=$card.data('previewMode')==='mobile';
		function size(key){return Number(settings[mobile?key+'_mobile':key]||0)+'px';}
		var linked=Boolean(settings.link_url&&settings.link_scope&&settings.link_scope!=='none');
		$widget.attr('class','sp-gr-widget sp-gr-widget--'+settings.preset+(linked?' is-linked':'')).css({
			'--sp-gr-text':settings.text_color,'--sp-gr-muted':settings.muted_color,'--sp-gr-star':settings.star_color,
			'--sp-gr-bg':settings.background_color,'--sp-gr-avatar-size':size('avatar_size'),'--sp-gr-avatar-overlap':size('avatar_overlap'),
			'--sp-gr-star-size':size('star_size'),'--sp-gr-font-size':size('font_size'),'--sp-gr-pad-y':size('padding_y'),
			'--sp-gr-pad-x':size('padding_x'),'--sp-gr-gap':size('gap'),'--sp-gr-radius':size('radius'),
			'--sp-gr-overlay':settings.background_image?(Number(settings.overlay_opacity||0)/100):0,
			'--sp-gr-image':settings.background_image?'url("'+String(settings.background_image).replace(/["\\]/g,'')+'")':'none'
		});
		$widget.find('[data-preview-component]').each(function(){$(this).toggle(components.indexOf($(this).data('preview-component'))!==-1);});
		$widget.find('.sp-gr-widget__avatar').each(function(index){$(this).toggle(index<Number(settings.avatar_count||0));});
		$widget.find('[data-preview-component="rating_label"]').text(settings.rating_label||'');
		$widget.find('[data-preview-component="count_label"]').text((settings.count_label||'').replace('{count}','95'));
		$widget.find('[data-preview-component="count_label"]').toggleClass('is-preview-link',linked&&settings.link_scope==='count');
		$card.find('[data-sp-gr-card-name]').text(settings.name||'Untitled widget');
		$card.find('[data-sp-gr-card-shortcode]').text('[google_reviews_widget id="'+(slug(settings.id)||'widget')+'"]');
	}

	function appendCard($card){
		$('[data-sp-gr-widget]').removeClass('is-expanded').find('[data-sp-gr-toggle]').attr('aria-expanded','false');
		$card.addClass('is-expanded').find('[data-sp-gr-toggle]').attr('aria-expanded','true');
		$('[data-sp-gr-widget-list]').append($card);
		initColorPickers($card);
		render($card);
		updateCount();
		var element=$card.get(0);
		if(element&&typeof element.scrollIntoView==='function'){element.scrollIntoView({behavior:'smooth',block:'start'});}
	}

	function newCard(){
		var template=document.getElementById('sp-gr-widget-template');
		if(!template){return;}
		var holder=document.createElement('div');
		holder.innerHTML=template.innerHTML.replace(/__INDEX__/g,String(nextIndex++));
		var $card=$(holder).children('[data-sp-gr-widget]').first();
		var number=$('[data-sp-gr-widget]').length+1;
		$card.find('[data-sp-gr-name]').val('Widget '+number);
		$card.find('[data-sp-gr-id]').val(uniqueId('widget-'+number,$card.find('[data-sp-gr-id]')));
		appendCard($card);
	}

	$(document).on('click','[data-sp-gr-toggle]',function(){var $card=$(this).closest('[data-sp-gr-widget]'),open=!$card.hasClass('is-expanded');$card.toggleClass('is-expanded',open);$(this).attr('aria-expanded',open?'true':'false');});
	$(document).on('input change','[data-sp-gr-widget] input,[data-sp-gr-widget] select',function(){var $card=$(this).closest('[data-sp-gr-widget]');if($(this).is('[data-sp-gr-name]')&&!$card.data('idTouched')){$card.find('[data-sp-gr-id]').val(uniqueId($(this).val(),$card.find('[data-sp-gr-id]')));}if($(this).is('[data-sp-gr-id]')){$card.data('idTouched',true);$(this).val(uniqueId($(this).val(),$(this)));}render($card);});
	$(document).on('click','[data-sp-gr-component-toggle]',function(){var $button=$(this),$item=$button.closest('[data-component]'),enabled=!$item.hasClass('is-enabled');$item.toggleClass('is-enabled',enabled);$button.attr('aria-checked',enabled?'true':'false');render($button.closest('[data-sp-gr-widget]'));});
	$(document).on('change','[data-sp-gr-preset]',function(){var p=presets[$(this).val()]||{},$card=$(this).closest('[data-sp-gr-widget]');Object.keys(p).forEach(function(key){if(['id','name','preset','components'].indexOf(key)!==-1)return;var $field=$card.find('[data-setting="'+key+'"]');if($field.hasClass('wp-color-picker')){$field.wpColorPicker('color',p[key]);}else{$field.val(p[key]);}});$card.find('[data-sp-gr-component-toggle]').attr('aria-checked','false').closest('[data-component]').removeClass('is-enabled');(p.components||[]).forEach(function(key){$card.find('[data-component="'+key+'"]').addClass('is-enabled').find('[data-sp-gr-component-toggle]').attr('aria-checked','true');});render($card);});
	$(document).on('click','[data-sp-gr-add-widget]',newCard);
	$(document).on('click','[data-sp-gr-duplicate]',function(){var $source=$(this).closest('[data-sp-gr-widget]'),$card=$source.clone(false,false),index=String(nextIndex++),sourceName=$source.find('[data-sp-gr-name]').val()||'Widget';$card.find('.wp-picker-container').each(function(){var $input=$(this).find('.sp-gr-color-picker').removeClass('wp-color-picker').removeAttr('style');$(this).before($input);$(this).remove();});$card.find('[name]').each(function(){this.name=this.name.replace(/sp_google_reviews_widgets\[[^\]]+\]/,'sp_google_reviews_widgets['+index+']');});$card.find('[data-sp-gr-name]').val(sourceName+' copy');$card.find('[data-sp-gr-id]').val(uniqueId($source.find('[data-sp-gr-id]').val()+'-copy',$card.find('[data-sp-gr-id]')));appendCard($card);});
	$(document).on('click','[data-sp-gr-delete]',function(){if($('[data-sp-gr-widget]').length<2){return;}$(this).closest('[data-sp-gr-widget]').remove();updateCount();});
	$(document).on('click','[data-sp-gr-copy]',function(){var text=$(this).closest('[data-sp-gr-widget]').find('[data-sp-gr-card-shortcode]').text(),button=this;navigator.clipboard.writeText(text).then(function(){var old=button.textContent;button.textContent='Copied';setTimeout(function(){button.textContent=old;},1200);});});
	$(document).on('click','[data-sp-gr-media]',function(){var $input=$(this).siblings('input'),$card=$(this).closest('[data-sp-gr-widget]'),frame=wp.media({title:'Select background image',button:{text:'Use image'},multiple:false});frame.on('select',function(){var item=frame.state().get('selection').first().toJSON();$input.val(item.url).trigger('input');render($card);});frame.open();});
	$(document).on('click','[data-sp-gr-media-clear]',function(){$(this).siblings('input').val('').trigger('input');});
	$(document).on('click','[data-sp-gr-preview-mode]',function(){var $button=$(this),$card=$button.closest('[data-sp-gr-widget]'),mode=$button.data('sp-gr-preview-mode');$card.data('previewMode',mode).find('[data-sp-gr-preview-mode]').removeClass('is-active button-primary').attr('aria-pressed','false');$button.addClass('is-active button-primary').attr('aria-pressed','true');$card.find('[data-sp-gr-preview-stage]').toggleClass('is-mobile',mode==='mobile');render($card);});
	$('[data-sp-gr-widget]').each(function(){initColorPickers($(this));render($(this));});
	updateCount();
})(jQuery);
JS;
	}
}
