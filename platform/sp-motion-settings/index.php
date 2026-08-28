<?php

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	define( 'SP_THEME_ANIMATIONS_OPTION', 'sp_enable_theme_animations' );
	define( 'SP_THEME_MOTION_SETTINGS_SECTION', 'sp_theme_motion_settings' );

	function sp_theme_animations_enabled(): bool {
		return get_option( SP_THEME_ANIMATIONS_OPTION, '1' ) === '1';
	}

	function sp_render_theme_switch_field( string $option, string $label, string $description, bool $enabled ): void {
		?>
		<label class="sp-theme-switch">
			<input type="hidden" name="<?php echo esc_attr( $option ); ?>" value="0">
			<input type="checkbox" name="<?php echo esc_attr( $option ); ?>" value="1" <?php checked( $enabled ); ?>>
			<span class="sp-theme-switch__track" aria-hidden="true">
				<span class="sp-theme-switch__thumb"></span>
			</span>
			<span><?php echo esc_html( $label ); ?></span>
		</label>
		<p class="description"><?php echo esc_html( $description ); ?></p>
		<?php
	}

	add_action( 'admin_init', function () {
		$setting_args = [
			'type'              => 'string',
			'sanitize_callback' => static function ( $value ): string {
				return ! empty( $value ) ? '1' : '0';
			},
			'default'           => '1',
		];

		register_setting( SP_THEME_MOTION_SETTINGS_SECTION, SP_THEME_ANIMATIONS_OPTION, $setting_args );
	} );

	add_action( 'admin_menu', function () {
		add_options_page(
			__( 'Theme Behavior', THEME_SLUG ),
			__( 'Theme Behavior', THEME_SLUG ),
			'manage_options',
			SP_THEME_MOTION_SETTINGS_SECTION,
			'sp_render_theme_behavior_settings_page'
		);
	} );

	function sp_render_theme_behavior_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap sp-theme-behavior-settings sp-admin-page">
			<header class="sp-admin-header">
				<div class="sp-admin-header__identity">
					<span class="sp-admin-header__icon dashicons dashicons-admin-customizer" aria-hidden="true"></span>
					<div class="sp-admin-header__copy">
						<h1><?php echo esc_html__( 'Theme Behavior', THEME_SLUG ); ?></h1>
						<p><?php echo esc_html__( 'Control global theme animations.', THEME_SLUG ); ?></p>
					</div>
				</div>
				<div class="sp-admin-header__actions">
					<button type="submit" class="button button-primary" form="sp-theme-behavior-form"><?php echo esc_html__( 'Save changes', THEME_SLUG ); ?></button>
				</div>
			</header>
			<form id="sp-theme-behavior-form" method="post" action="options.php" class="sp-admin-card">
				<?php settings_fields( SP_THEME_MOTION_SETTINGS_SECTION ); ?>
				<div class="sp-admin-card__header"><h2><?php echo esc_html__( 'Motion settings', THEME_SLUG ); ?></h2></div>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Animations', THEME_SLUG ); ?></th>
							<td>
								<?php
									sp_render_theme_switch_field(
										SP_THEME_ANIMATIONS_OPTION,
										__( 'Enable animations', THEME_SLUG ),
										__( 'Runs site motion effects only on screens from 1025px and up.', THEME_SLUG ),
										sp_theme_animations_enabled()
									);
								?>
							</td>
						</tr>
					</tbody>
				</table>
			</form>
		</div>
		<?php
	}

	add_action( 'admin_head-settings_page_' . SP_THEME_MOTION_SETTINGS_SECTION, function () {
		?>
		<style>
			.sp-theme-behavior-settings .form-table {
				max-width: 1040px;
				margin-top: 18px;
			}

			.sp-theme-switch {
				display: inline-flex;
				align-items: center;
				gap: 10px;
				min-height: 30px;
			}

			.sp-theme-switch input[type="checkbox"] {
				position: absolute;
				opacity: 0;
				pointer-events: none;
			}

			.sp-theme-switch__track {
				position: relative;
				width: 44px;
				height: 24px;
				border-radius: 999px;
				border: 1px solid var(--sp-admin-border-strong);
				background: var(--sp-admin-border-strong);
				box-shadow: inset 0 1px 2px rgba(26, 31, 36, .1);
				transition: background-color .2s ease;
				cursor: pointer;
			}

			.sp-theme-switch__thumb {
				position: absolute;
				top: 3px;
				left: 3px;
				width: 18px;
				height: 18px;
				border-radius: 50%;
				background: var(--sp-admin-surface);
				box-shadow: 0 2px 5px rgba(26, 31, 36, .24);
				transition: transform .2s ease;
			}

			.sp-theme-switch input[type="checkbox"]:checked + .sp-theme-switch__track {
				border-color: var(--sp-admin-accent);
				background: var(--sp-admin-accent);
			}

			.sp-theme-switch input[type="checkbox"]:checked + .sp-theme-switch__track .sp-theme-switch__thumb {
				transform: translateX(20px);
			}

			.sp-theme-switch input[type="checkbox"]:focus-visible + .sp-theme-switch__track {
				box-shadow: var(--sp-admin-focus);
			}
		</style>
		<?php
	} );
