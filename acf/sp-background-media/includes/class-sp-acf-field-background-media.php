<?php
	declare(strict_types=1);

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	class SP_ACF_Field_Background_Media extends acf_field {
		private array $palette = [ '#FFFFFF', '#111111' ];

		public function initialize(): void {
			$this->name     = 'sp_background_media';
			$this->label    = __( 'Responsive Background', 'acf' );
			$this->category = 'content';
			$this->defaults = [
				'responsive'    => 1,
				'allow_video'   => 1,
				'allow_overlay' => 1,
			];
		}

		public function render_field_settings( $field ): void {
			acf_render_field_setting( $field, [
				'label'        => __( 'Responsive variants', 'acf' ),
				'instructions' => __( 'Allow separate media and positioning for tablet and mobile.', 'acf' ),
				'name'         => 'responsive',
				'type'         => 'true_false',
				'ui'           => 1,
				'default_value' => 1,
			] );

			acf_render_field_setting( $field, [
				'label'        => __( 'Video backgrounds', 'acf' ),
				'instructions' => __( 'Allow images and uploaded videos. Videos are muted and loop automatically.', 'acf' ),
				'name'         => 'allow_video',
				'type'         => 'true_false',
				'ui'           => 1,
				'default_value' => 1,
			] );

			acf_render_field_setting( $field, [
				'label'        => __( 'Overlay controls', 'acf' ),
				'instructions' => __( 'Allow solid and gradient overlays to be configured in the field value.', 'acf' ),
				'name'         => 'allow_overlay',
				'type'         => 'true_false',
				'ui'           => 1,
				'default_value' => 1,
			] );
		}

		public function render_field( $field ): void {
			$value         = is_array( $field['value'] ?? null ) ? $field['value'] : [];
			$responsive    = ! array_key_exists( 'responsive', $field ) || ! empty( $field['responsive'] );
			$allow_video   = ! array_key_exists( 'allow_video', $field ) || ! empty( $field['allow_video'] );
			$allow_overlay = ! array_key_exists( 'allow_overlay', $field ) || ! empty( $field['allow_overlay'] );
			$breakpoints   = $responsive ? [ 'desktop', 'tablet', 'mobile' ] : [ 'desktop' ];
			$config        = sp_background_media_breakpoint_config();
			$format_px     = static fn( float $number ): string => rtrim( rtrim( number_format( $number, 2, '.', '' ), '0' ), '.' );
			$labels        = [
				'desktop' => __( 'Desktop', 'acf' ),
				'tablet'  => __( 'Tablet', 'acf' ),
				'mobile'  => __( 'Mobile', 'acf' ),
			];
			$hints         = [
				'desktop' => sprintf(
					/* translators: %s: minimum viewport width. */
					__( 'Optional media from %spx and wider.', 'acf' ),
					$format_px( (float) $config['desktop']['min'] )
				),
				'tablet'  => sprintf(
					/* translators: 1: minimum viewport width, 2: maximum viewport width. */
					__( '%1$s–%2$spx. Optional media inherits Desktop.', 'acf' ),
					$format_px( (float) $config['tablet']['min'] ),
					$format_px( (float) $config['tablet']['max'] )
				),
				'mobile'  => sprintf(
					/* translators: %s: maximum viewport width. */
					__( 'Up to %spx. Optional media inherits Tablet/Desktop.', 'acf' ),
					$format_px( (float) $config['mobile']['max'] )
				),
			];
			$this->palette = function_exists( 'color_palette_config' )
				? array_keys( color_palette_config() )
				: [ '#FFFFFF', '#111111' ];
			$instance_id = wp_unique_id( 'sp-background-media-' );
			?>
			<div class="sp-background-field sp-admin-component sp-acf-component"
				id="<?php echo esc_attr( $instance_id ); ?>"
				data-sp-background-field
				data-color-palette="<?php echo esc_attr( wp_json_encode( array_values( $this->palette ) ) ); ?>"
				data-allow-video="<?php echo $allow_video ? 'true' : 'false'; ?>">
				<span class="screen-reader-text" data-sp-background-status aria-live="polite" aria-atomic="true"></span>
				<input type="hidden"
					name="<?php echo esc_attr( (string) $field['name'] . '[state]' ); ?>"
					value="<?php echo esc_attr( wp_json_encode( $value ) ); ?>"
					data-sp-background-state>

				<?php if ( $responsive ) : ?>
					<div class="sp-background-field__tabs" role="tablist" aria-label="<?php esc_attr_e( 'Background breakpoint', 'acf' ); ?>">
						<?php foreach ( $breakpoints as $index => $breakpoint ) : ?>
							<button type="button"
								class="sp-background-field__tab<?php echo 0 === $index ? ' is-active' : ''; ?>"
								role="tab"
								aria-selected="<?php echo 0 === $index ? 'true' : 'false'; ?>"
								aria-controls="<?php echo esc_attr( $instance_id . '-' . $breakpoint ); ?>"
								data-sp-background-tab="<?php echo esc_attr( $breakpoint ); ?>">
								<?php echo esc_html( $labels[ $breakpoint ] ); ?>
							</button>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<div class="sp-background-field__variants">
					<?php foreach ( $breakpoints as $index => $breakpoint ) : ?>
						<?php
							$variant = is_array( $value[ $breakpoint ] ?? null ) ? $value[ $breakpoint ] : [];
							$this->render_variant(
								(string) $field['name'],
								$breakpoint,
								$variant,
								$labels[ $breakpoint ],
								$hints[ $breakpoint ],
								0 === $index,
								$allow_video,
								$instance_id
							);
						?>
					<?php endforeach; ?>
				</div>

				<?php if ( $allow_overlay ) : ?>
					<?php $this->render_overlay( (string) $field['name'], is_array( $value['overlay'] ?? null ) ? $value['overlay'] : [] ); ?>
				<?php endif; ?>
			</div>
			<?php
		}

		private function render_variant(
			string $field_name,
			string $breakpoint,
			array $variant,
			string $label,
			string $hint,
			bool $active,
			bool $allow_video,
			string $instance_id
		): void {
			$attachment_id = absint( $variant['attachment_id'] ?? 0 );
			$poster_id     = absint( $variant['poster_id'] ?? 0 );
			$mime          = $attachment_id ? (string) get_post_mime_type( $attachment_id ) : '';
			$is_video      = str_starts_with( $mime, 'video/' );
			$preview_url   = $attachment_id && ! $is_video ? wp_get_attachment_image_url( $attachment_id, 'medium' ) : '';
			$poster_url    = $poster_id ? wp_get_attachment_image_url( $poster_id, 'medium' ) : '';
			$file_name     = $attachment_id ? basename( (string) get_attached_file( $attachment_id ) ) : '';
			$fit           = sanitize_key( (string) ( $variant['fit'] ?? 'cover' ) );
			$fit           = in_array( $fit, [ 'cover', 'contain' ], true ) ? $fit : 'cover';
			$position_x    = sp_background_media_number( $variant['position_x'] ?? 50, 0, 100, 50 );
			$position_y    = sp_background_media_number( $variant['position_y'] ?? 50, 0, 100, 50 );
			$name          = $field_name . '[' . $breakpoint . ']';
			?>
			<section class="sp-background-field__variant<?php echo $active ? ' is-active' : ''; ?>"
				id="<?php echo esc_attr( $instance_id . '-' . $breakpoint ); ?>"
				role="tabpanel"
				<?php echo $active ? '' : 'hidden'; ?>
				data-sp-background-panel="<?php echo esc_attr( $breakpoint ); ?>">
				<div class="sp-background-field__variant-heading">
					<strong><?php echo esc_html( $label ); ?></strong>
					<span><?php echo esc_html( $hint ); ?></span>
				</div>

				<input type="hidden" name="<?php echo esc_attr( $name . '[attachment_id]' ); ?>" value="<?php echo esc_attr( (string) $attachment_id ); ?>" data-sp-background-media-id>
				<input type="hidden" value="<?php echo $is_video ? 'video' : 'image'; ?>" data-sp-background-media-type>

				<div class="sp-background-field__stage">
					<div class="sp-background-field__preview<?php echo $attachment_id ? ' is-filled' : ''; ?>"
						role="button"
						tabindex="0"
						aria-label="<?php esc_attr_e( 'Set focal point on the preview', 'acf' ); ?>"
						data-sp-background-preview
						data-sp-background-focal-surface
						style="--sp-preview-fit:<?php echo esc_attr( $fit ); ?>;--sp-preview-x:<?php echo esc_attr( (string) $position_x ); ?>%;--sp-preview-y:<?php echo esc_attr( (string) $position_y ); ?>%;--sp-focal-x:<?php echo esc_attr( (string) $position_x ); ?>%;--sp-focal-y:<?php echo esc_attr( (string) $position_y ); ?>%;">
						<span class="sp-background-field__preview-content" data-sp-background-preview-content>
							<?php if ( $preview_url ) : ?>
								<img src="<?php echo esc_url( $preview_url ); ?>" alt="">
							<?php elseif ( $attachment_id && $is_video ) : ?>
								<span class="sp-background-field__file"><span class="sp-background-field__empty-icon" aria-hidden="true">▶</span><span><?php echo esc_html( $file_name ); ?></span></span>
							<?php else : ?>
								<span class="sp-background-field__empty"><span class="sp-background-field__empty-icon" aria-hidden="true">+</span><span><?php esc_html_e( 'Select an image or video', 'acf' ); ?></span></span>
							<?php endif; ?>
						</span>
						<span class="sp-background-field__focal-grid" aria-hidden="true"></span>
						<span class="sp-background-field__focal-dot" aria-hidden="true"></span>
						<output class="sp-background-field__position-badge" data-sp-background-position-output><?php echo esc_html( (string) $position_x . '% / ' . (string) $position_y . '%' ); ?></output>
					</div>

					<div class="sp-background-field__toolbar">
						<div class="sp-background-field__fit" role="group" aria-label="<?php esc_attr_e( 'Background sizing', 'acf' ); ?>">
							<label><input type="radio" name="<?php echo esc_attr( $name . '[fit]' ); ?>" value="cover" <?php checked( $fit, 'cover' ); ?> data-sp-background-fit><span><?php esc_html_e( 'Cover', 'acf' ); ?></span></label>
							<label><input type="radio" name="<?php echo esc_attr( $name . '[fit]' ); ?>" value="contain" <?php checked( $fit, 'contain' ); ?> data-sp-background-fit><span><?php esc_html_e( 'Contain', 'acf' ); ?></span></label>
						</div>

						<div class="sp-background-field__position-values">
							<label><span>X</span><input type="number" min="0" max="100" step="1" name="<?php echo esc_attr( $name . '[position_x]' ); ?>" value="<?php echo esc_attr( (string) $position_x ); ?>" data-sp-background-position="x"><em>%</em></label>
							<label><span>Y</span><input type="number" min="0" max="100" step="1" name="<?php echo esc_attr( $name . '[position_y]' ); ?>" value="<?php echo esc_attr( (string) $position_y ); ?>" data-sp-background-position="y"><em>%</em></label>
						</div>

						<div class="sp-background-field__actions">
							<button type="button" class="sp-background-field__button sp-background-field__button--primary" data-sp-background-select><span data-sp-background-select-label><?php echo $attachment_id ? esc_html__( 'Replace media', 'acf' ) : esc_html__( 'Select media', 'acf' ); ?></span></button>
							<button type="button" class="sp-background-field__button sp-background-field__button--danger<?php echo $attachment_id ? '' : ' is-hidden'; ?>" data-sp-background-remove><?php esc_html_e( 'Remove', 'acf' ); ?></button>
						</div>
					</div>
				</div>

				<?php if ( $allow_video ) : ?>
					<div class="sp-background-field__poster" data-sp-background-poster-panel <?php echo $is_video ? '' : 'hidden'; ?>>
						<div>
							<strong><?php esc_html_e( 'Video poster', 'acf' ); ?></strong>
							<span><?php esc_html_e( 'Shown before playback and when reduced motion is enabled.', 'acf' ); ?></span>
						</div>
						<input type="hidden" name="<?php echo esc_attr( $name . '[poster_id]' ); ?>" value="<?php echo esc_attr( (string) $poster_id ); ?>" data-sp-background-poster-id>
						<div class="sp-background-field__poster-preview<?php echo $poster_url ? ' is-filled' : ''; ?>" data-sp-background-poster-preview>
							<?php if ( $poster_url ) : ?>
								<img src="<?php echo esc_url( $poster_url ); ?>" alt="">
							<?php else : ?>
								<span><?php esc_html_e( 'Optional poster image', 'acf' ); ?></span>
							<?php endif; ?>
						</div>
						<div class="sp-background-field__actions">
							<button type="button" class="sp-background-field__button" data-sp-background-poster-select><span data-sp-background-poster-select-label><?php echo $poster_id ? esc_html__( 'Replace poster', 'acf' ) : esc_html__( 'Select poster', 'acf' ); ?></span></button>
							<button type="button" class="sp-background-field__button sp-background-field__button--danger<?php echo $poster_id ? '' : ' is-hidden'; ?>" data-sp-background-poster-remove><?php esc_html_e( 'Remove', 'acf' ); ?></button>
						</div>
					</div>
				<?php endif; ?>
			</section>
			<?php
		}

		private function render_overlay( string $field_name, array $overlay ): void {
			$enabled = ! empty( $overlay['enabled'] );
			$type    = sanitize_key( (string) ( $overlay['type'] ?? 'solid' ) );
			$type    = in_array( $type, [ 'solid', 'gradient' ], true ) ? $type : 'solid';
			$color   = sp_background_media_color( $overlay['color'] ?? '', '#000000' );
			$opacity = sp_background_media_number( $overlay['opacity'] ?? 40, 0, 100, 40 );
			$angle   = sp_background_media_number( $overlay['angle'] ?? 180, 0, 360, 180 );
			$stops   = sp_background_media_gradient_stops( $overlay );
			$name    = $field_name . '[overlay]';
			?>
			<section class="sp-background-field__overlay" data-sp-background-overlay>
				<div class="sp-background-field__overlay-heading">
					<div>
						<span class="sp-background-field__eyebrow"><?php esc_html_e( 'Layer', 'acf' ); ?></span>
						<strong><?php esc_html_e( 'Background overlay', 'acf' ); ?></strong>
						<span><?php esc_html_e( 'Add a palette color or build a multi-stop gradient.', 'acf' ); ?></span>
					</div>
					<label class="sp-background-field__switch">
						<input type="hidden" name="<?php echo esc_attr( $name . '[enabled]' ); ?>" value="0">
						<input type="checkbox" name="<?php echo esc_attr( $name . '[enabled]' ); ?>" value="1" <?php checked( $enabled ); ?> data-sp-background-overlay-enabled>
						<span class="sp-background-field__switch-track" aria-hidden="true"><i></i></span>
						<span class="sp-background-field__switch-label"><?php esc_html_e( 'Enabled', 'acf' ); ?></span>
					</label>
				</div>

				<div class="sp-background-field__overlay-body" data-sp-background-overlay-body <?php echo $enabled ? '' : 'hidden'; ?>>
					<div class="sp-background-field__overlay-types" role="group" aria-label="<?php esc_attr_e( 'Overlay type', 'acf' ); ?>" data-sp-background-overlay-types>
						<label><input type="radio" name="<?php echo esc_attr( $name . '[type]' ); ?>" value="solid" <?php checked( $type, 'solid' ); ?>><span><?php esc_html_e( 'Solid color', 'acf' ); ?></span></label>
						<label><input type="radio" name="<?php echo esc_attr( $name . '[type]' ); ?>" value="gradient" <?php checked( $type, 'gradient' ); ?>><span><?php esc_html_e( 'Gradient', 'acf' ); ?></span></label>
					</div>

					<div class="sp-background-field__overlay-preview" data-sp-background-overlay-preview>
						<span><?php esc_html_e( 'Live preview', 'acf' ); ?></span>
					</div>

					<div class="sp-background-field__overlay-controls sp-background-field__overlay-controls--solid" data-sp-background-overlay-controls="solid" <?php echo 'solid' === $type ? '' : 'hidden'; ?>>
						<div class="sp-background-field__controls-title"><strong><?php esc_html_e( 'Color layer', 'acf' ); ?></strong><span><?php esc_html_e( 'Theme palette or a custom HEX value.', 'acf' ); ?></span></div>
						<div class="sp-background-field__solid-row">
							<?php $this->render_color_control( $name . '[color]', __( 'Color', 'acf' ), $color, 'solid-color' ); ?>
							<?php $this->render_number_control( $name . '[opacity]', __( 'Opacity', 'acf' ), $opacity, 0, 100, 'solid-opacity', '%' ); ?>
						</div>
					</div>

					<div class="sp-background-field__overlay-controls sp-background-field__overlay-controls--gradient" data-sp-background-overlay-controls="gradient" <?php echo 'gradient' === $type ? '' : 'hidden'; ?>>
						<div class="sp-background-field__gradient-toolbar">
							<div class="sp-background-field__controls-title"><strong><?php esc_html_e( 'Gradient stops', 'acf' ); ?></strong><span><?php esc_html_e( 'Two to eight colors.', 'acf' ); ?></span></div>
							<div class="sp-background-field__angle-control">
								<?php $this->render_number_control( $name . '[angle]', __( 'Angle', 'acf' ), $angle, 0, 360, 'angle', '°' ); ?>
								<div class="sp-background-field__angle-presets">
									<?php foreach ( [ 0, 45, 90, 135, 180, 225, 270, 315 ] as $preset_angle ) : ?>
										<button type="button" data-sp-gradient-angle="<?php echo esc_attr( (string) $preset_angle ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Set angle to %s degrees', 'acf' ), $preset_angle ) ); ?>"><span style="--sp-angle:<?php echo esc_attr( (string) $preset_angle ); ?>deg"></span></button>
									<?php endforeach; ?>
								</div>
							</div>
						</div>

						<div class="sp-background-field__gradient-labels" aria-hidden="true"><span><?php esc_html_e( 'Color', 'acf' ); ?></span><span><?php esc_html_e( 'Opacity', 'acf' ); ?></span><span><?php esc_html_e( 'Position', 'acf' ); ?></span><span></span></div>
						<div class="sp-background-field__gradient-stops" data-sp-gradient-stops data-max-stops="8">
							<?php foreach ( $stops as $index => $stop ) : ?>
								<?php $this->render_gradient_stop( $name, (string) $index, $stop, count( $stops ) ); ?>
							<?php endforeach; ?>
						</div>

						<button type="button" class="sp-background-field__add-stop" data-sp-gradient-stop-add>+ <?php esc_html_e( 'Add color stop', 'acf' ); ?></button>
						<template data-sp-gradient-stop-template>
							<?php $this->render_gradient_stop( $name, '__INDEX__', [ 'color' => '#FFFFFF', 'opacity' => 100, 'position' => 50 ], 3 ); ?>
						</template>
					</div>
				</div>
			</section>
			<?php
		}

		private function render_color_control( string $name, string $label, string $value, string $key ): void {
			?>
			<div class="sp-background-field__control sp-background-field__control--color" data-sp-color-control>
				<span><?php echo esc_html( $label ); ?></span>
				<div class="sp-background-field__color-line">
					<label class="sp-background-field__native-color" title="<?php esc_attr_e( 'Choose custom color', 'acf' ); ?>">
						<input type="color" value="<?php echo esc_attr( $value ); ?>" data-sp-color-native>
						<span style="--sp-current-color:<?php echo esc_attr( $value ); ?>" data-sp-color-chip></span>
					</label>
					<input type="text" class="sp-background-field__color-input" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" maxlength="7" spellcheck="false" data-sp-color-value data-sp-overlay-value="<?php echo esc_attr( $key ); ?>">
				</div>
				<div class="sp-background-field__palette" aria-label="<?php esc_attr_e( 'Theme color palette', 'acf' ); ?>">
					<?php foreach ( $this->palette as $palette_color ) : ?>
						<?php $palette_color = sp_background_media_color( $palette_color, '#000000' ); ?>
						<button type="button" data-sp-palette-color="<?php echo esc_attr( $palette_color ); ?>" title="<?php echo esc_attr( $palette_color ); ?>" style="--sp-palette-color:<?php echo esc_attr( $palette_color ); ?>"><span class="screen-reader-text"><?php echo esc_html( $palette_color ); ?></span></button>
					<?php endforeach; ?>
				</div>
			</div>
			<?php
		}

		private function render_number_control( string $name, string $label, float $value, int $min, int $max, string $key, string $suffix ): void {
			?>
			<label class="sp-background-field__control">
				<span><?php echo esc_html( $label ); ?></span>
				<span class="sp-background-field__number"><input type="number" min="<?php echo esc_attr( (string) $min ); ?>" max="<?php echo esc_attr( (string) $max ); ?>" step="1" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( (string) $value ); ?>" data-sp-overlay-value="<?php echo esc_attr( $key ); ?>"><em><?php echo esc_html( $suffix ); ?></em></span>
			</label>
			<?php
		}

		private function render_gradient_stop( string $overlay_name, string $index, array $stop, int $count ): void {
			$color    = sp_background_media_color( $stop['color'] ?? '', '#000000' );
			$opacity  = sp_background_media_number( $stop['opacity'] ?? 100, 0, 100, 100 );
			$position = sp_background_media_number( $stop['position'] ?? 50, 0, 100, 50 );
			$name     = $overlay_name . '[stops][' . $index . ']';
			?>
			<div class="sp-background-field__gradient-stop" data-sp-gradient-stop>
				<div class="sp-background-field__stop-color" data-sp-color-control>
					<span class="sp-background-field__stop-index" data-sp-gradient-stop-label><?php echo esc_html( (string) ( is_numeric( $index ) ? (int) $index + 1 : '' ) ); ?></span>
					<label class="sp-background-field__native-color" title="<?php esc_attr_e( 'Choose custom color', 'acf' ); ?>">
						<input type="color" value="<?php echo esc_attr( $color ); ?>" data-sp-color-native>
						<span style="--sp-current-color:<?php echo esc_attr( $color ); ?>" data-sp-color-chip data-sp-stop-swatch></span>
					</label>
					<input type="text" class="sp-background-field__color-input" name="<?php echo esc_attr( $name . '[color]' ); ?>" value="<?php echo esc_attr( $color ); ?>" maxlength="7" spellcheck="false" data-sp-color-value data-sp-stop-color>
					<div class="sp-background-field__palette sp-background-field__palette--popover">
						<?php foreach ( $this->palette as $palette_color ) : ?>
							<?php $palette_color = sp_background_media_color( $palette_color, '#000000' ); ?>
							<button type="button" data-sp-palette-color="<?php echo esc_attr( $palette_color ); ?>" title="<?php echo esc_attr( $palette_color ); ?>" style="--sp-palette-color:<?php echo esc_attr( $palette_color ); ?>"><span class="screen-reader-text"><?php echo esc_html( $palette_color ); ?></span></button>
						<?php endforeach; ?>
					</div>
				</div>
				<label class="sp-background-field__number"><input type="number" min="0" max="100" step="1" name="<?php echo esc_attr( $name . '[opacity]' ); ?>" value="<?php echo esc_attr( (string) $opacity ); ?>" aria-label="<?php esc_attr_e( 'Opacity', 'acf' ); ?>" data-sp-stop-opacity><em>%</em></label>
				<label class="sp-background-field__number"><input type="number" min="0" max="100" step="1" name="<?php echo esc_attr( $name . '[position]' ); ?>" value="<?php echo esc_attr( (string) $position ); ?>" aria-label="<?php esc_attr_e( 'Position', 'acf' ); ?>" data-sp-stop-position><em>%</em></label>
				<button type="button" class="sp-background-field__stop-remove" data-sp-gradient-stop-remove <?php echo $count <= 2 ? 'hidden' : ''; ?> aria-label="<?php esc_attr_e( 'Remove color stop', 'acf' ); ?>">×</button>
			</div>
			<?php
		}

		private function submitted_value( $value ): array {
			$value = is_array( $value ) ? $value : [];
			$state = isset( $value['state'] ) && is_string( $value['state'] ) ? $value['state'] : '';

			if ( '' !== $state ) {
				$state = function_exists( 'wp_unslash' ) ? wp_unslash( $state ) : $state;
				$decoded = json_decode( $state, true );
				if ( is_array( $decoded ) ) {
					return $decoded;
				}
			}

			unset( $value['state'] );
			return $value;
		}

		public function update_value( $value, $post_id, $field ) {
			$value       = $this->submitted_value( $value );
			$responsive  = ! array_key_exists( 'responsive', $field ) || ! empty( $field['responsive'] );
			$breakpoints = $responsive ? [ 'desktop', 'tablet', 'mobile' ] : [ 'desktop' ];
			$clean       = [];

			foreach ( $breakpoints as $breakpoint ) {
				$variant = is_array( $value[ $breakpoint ] ?? null ) ? $value[ $breakpoint ] : [];
				$fit = sanitize_key( (string) ( $variant['fit'] ?? 'cover' ) );
				$clean[ $breakpoint ] = [
					'attachment_id' => absint( $variant['attachment_id'] ?? 0 ),
					'poster_id'     => absint( $variant['poster_id'] ?? 0 ),
					'fit'           => in_array( $fit, [ 'cover', 'contain' ], true ) ? $fit : 'cover',
					'position_x'    => sp_background_media_number( $variant['position_x'] ?? 50, 0, 100, 50 ),
					'position_y'    => sp_background_media_number( $variant['position_y'] ?? 50, 0, 100, 50 ),
				];
			}

			if ( ! empty( $field['allow_overlay'] ) || ! array_key_exists( 'allow_overlay', $field ) ) {
				$overlay = is_array( $value['overlay'] ?? null ) ? $value['overlay'] : [];
				$type  = sanitize_key( (string) ( $overlay['type'] ?? 'solid' ) );
				$clean['overlay'] = [
					'enabled' => ! empty( $overlay['enabled'] ) ? 1 : 0,
					'type'    => in_array( $type, [ 'solid', 'gradient' ], true ) ? $type : 'solid',
					'color'   => sp_background_media_color( $overlay['color'] ?? '', '#000000' ),
					'opacity' => sp_background_media_number( $overlay['opacity'] ?? 40, 0, 100, 40 ),
					'angle'   => sp_background_media_number( $overlay['angle'] ?? 180, 0, 360, 180 ),
					'stops'   => sp_background_media_gradient_stops( $overlay ),
				];
			}

			$has_media   = ! empty( $clean['desktop']['attachment_id'] );
			$has_overlay = ! empty( $clean['overlay']['enabled'] );

			if ( ! $has_media && ! $has_overlay ) {
				return '';
			}

			return $clean;
		}

		public function validate_value( $valid, $value, $field, $input ) {
			if ( true !== $valid ) {
				return $valid;
			}

			$value = $this->submitted_value( $value );
			$desktop_id = absint( $value['desktop']['attachment_id'] ?? 0 );
			$responsive = ! array_key_exists( 'responsive', $field ) || ! empty( $field['responsive'] );
			$breakpoints = $responsive ? [ 'desktop', 'tablet', 'mobile' ] : [ 'desktop' ];

			if ( ! $desktop_id ) {
				$has_responsive_media = false;
				foreach ( [ 'tablet', 'mobile' ] as $breakpoint ) {
					$has_responsive_media = $has_responsive_media || ! empty( $value[ $breakpoint ]['attachment_id'] );
				}

				if ( $has_responsive_media ) {
					return __( 'Please select the Desktop background first.', 'acf' );
				}

				$overlay_enabled = ! empty( $value['overlay']['enabled'] );
				if ( ! empty( $field['required'] ) && ! $overlay_enabled ) {
					return __( 'Please select background media or enable the overlay.', 'acf' );
				}

				return $valid;
			}

			foreach ( $breakpoints as $breakpoint ) {
				$attachment_id = absint( $value[ $breakpoint ]['attachment_id'] ?? 0 );
				$poster_id = absint( $value[ $breakpoint ]['poster_id'] ?? 0 );

				if ( $attachment_id ) {
					$mime = (string) get_post_mime_type( $attachment_id );
					$is_image = str_starts_with( $mime, 'image/' );
					$is_video = str_starts_with( $mime, 'video/' );

					if ( ! $is_image && ! $is_video ) {
						return __( 'Background media must be an image or video.', 'acf' );
					}

					if ( $is_video && empty( $field['allow_video'] ) && array_key_exists( 'allow_video', $field ) ) {
						return __( 'Video is not allowed for this background field.', 'acf' );
					}
				}

				if ( $poster_id && ! str_starts_with( (string) get_post_mime_type( $poster_id ), 'image/' ) ) {
					return __( 'Video poster must be an image.', 'acf' );
				}
			}

			return $valid;
		}

		public function input_admin_enqueue_scripts(): void {
			wp_enqueue_media();

			$base_path = dirname( __DIR__ ) . '/assets/';
			$base_uri  = class_exists( \SoinProduction\Kit\Bootstrapper::class )
				? \SoinProduction\Kit\Bootstrapper::pathToUrl( $base_path )
				: '';

			if ( '' === $base_uri ) {
				return;
			}

			$css_path  = $base_path . 'background-media-admin.css';
			$js_path   = $base_path . 'background-media-admin.js';

			wp_enqueue_style(
				'sp-background-media-admin',
				$base_uri . 'background-media-admin.css',
				[],
				is_readable( $css_path ) ? (string) filemtime( $css_path ) : null
			);
			wp_enqueue_script(
				'sp-background-media-admin',
				$base_uri . 'background-media-admin.js',
				[ 'jquery', 'acf-input' ],
				is_readable( $js_path ) ? (string) filemtime( $js_path ) : null,
				true
			);
		}
	}
