<?php
	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	add_action( 'wpcf7_admin_init', function () {
		if ( ! function_exists( 'wpcf7_add_tag_generator' ) ) {
			return;
		}

		wpcf7_add_tag_generator(
			'sp_icon',
			__( 'UI Icon', 'contact-form-7' ),
			'wpcf7-tg-pane-sp_icon',
			'sp_cf7_icons_taggen_callback'
		);
	} );

	function sp_cf7_icons_taggen_callback( $form, $args = '' ) {
		// Read the manifest to get the icons list
		$icons = [];
		if ( defined( 'SP_UI_ICONS_UPLOAD_DIR' ) && defined( 'SP_UI_ICONS_MANIFEST_FILE' ) ) {
			$up  = wp_get_upload_dir();
			$dir = trailingslashit( $up['basedir'] ) . SP_UI_ICONS_UPLOAD_DIR;
			$manifest_file = trailingslashit( $dir ) . SP_UI_ICONS_MANIFEST_FILE;
			if ( file_exists( $manifest_file ) ) {
				$icons = json_decode( file_get_contents( $manifest_file ), true ) ?: [];
			}
		}
		?>
		<div class="sp-cf7-icons-generator sp-admin-component" data-sp-admin-component>
			<div class="sp-cf7-icons-settings">
				<div class="sp-cf7-icons-row">
					<div class="sp-cf7-icons-col">
						<label class="sp-cf7-icons-label" for="sp-cf7-icon-width">Width</label>
						<input type="text" id="sp-cf7-icon-width" class="sp-cf7-icons-input" placeholder="2.4rem" value="2.4rem">
					</div>
					<div class="sp-cf7-icons-col">
						<label class="sp-cf7-icons-label" for="sp-cf7-icon-height">Height</label>
						<input type="text" id="sp-cf7-icon-height" class="sp-cf7-icons-input" placeholder="2.4rem" value="2.4rem">
					</div>
					<div class="sp-cf7-icons-col">
						<label class="sp-cf7-icons-label" for="sp-cf7-icon-class">CSS Class</label>
						<input type="text" id="sp-cf7-icon-class" class="sp-cf7-icons-input" placeholder="e.g. icon-left">
					</div>
				</div>
				<div class="sp-cf7-icons-row" style="margin-top: 15px;">
					<div class="sp-cf7-icons-col-full">
						<label class="sp-cf7-icons-label" for="sp-cf7-icons-search">Search Icons</label>
						<div class="sp-cf7-icons-search-wrap">
							<span class="dashicons dashicons-search"></span>
							<input type="text" id="sp-cf7-icons-search" class="sp-cf7-icons-input" placeholder="Type to filter icons...">
						</div>
					</div>
				</div>
			</div>

			<div class="sp-cf7-icons-header">Select an Icon to Insert:</div>
			
			<?php if ( empty( $icons ) ) : ?>
				<div class="sp-cf7-icons-empty">
					No icons found. Please upload SVG icons to Media &rarr; <a href="<?php echo admin_url('upload.php?page=ui_assets'); ?>" target="_blank">UI Assets</a> first.
				</div>
			<?php else : ?>
				<div class="sp-cf7-icons-grid">
					<?php foreach ( $icons as $slug => $icon ) : ?>
						<div class="sp-cf7-icon-card" data-slug="<?php echo esc_attr( $slug ); ?>" title="<?php echo esc_attr( $icon['title'] ); ?>">
							<div class="sp-cf7-icon-preview">
								<svg viewBox="<?php echo esc_attr( $icon['viewBox'] ); ?>">
									<use href="<?php echo esc_url( $icon['spriteUrl'] ); ?>"></use>
								</svg>
							</div>
							<div class="sp-cf7-icon-name"><?php echo esc_html( $slug ); ?></div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	// Add styles to admin head
	add_action( 'admin_head', function() {
		$screen = get_current_screen();
		if ( ! $screen || strpos( $screen->id, 'wpcf7' ) === false ) {
			return;
		}
		?>
		<style>
			#tag-generator-list button[data-target*="-sp_icon"]::before {
				content: "\f115"; /* dashicons-art */
			}

			.sp-cf7-icons-generator {
				padding: 15px 0;
				color: var(--sp-admin-text);
				font-family: var(--sp-admin-font);
			}
			.sp-cf7-icons-settings {
				background: var(--sp-admin-surface-subtle);
				border: 1px solid var(--sp-admin-border);
				border-radius: var(--sp-admin-radius);
				padding: 15px;
				margin-bottom: 20px;
				box-shadow: var(--sp-admin-shadow-xs);
			}
			.sp-cf7-icons-row {
				display: flex;
				gap: 15px;
			}
			.sp-cf7-icons-col {
				flex: 1;
				min-width: 0;
			}
			.sp-cf7-icons-col-full {
				width: 100%;
			}
			.sp-cf7-icons-label {
				display: block;
				font-size: 11px;
				font-weight: 600;
				text-transform: uppercase;
				color: var(--sp-admin-text-2);
				margin-bottom: 6px;
				letter-spacing: 0.5px;
			}
			.sp-cf7-icons-input {
				width: 100%;
				padding: 8px 12px;
				font-size: 13px;
				border: 1px solid var(--sp-admin-border-strong);
				border-radius: var(--sp-admin-radius-sm);
				background: var(--sp-admin-input-bg);
				color: var(--sp-admin-text);
				box-sizing: border-box;
				transition: border-color var(--sp-admin-transition), box-shadow var(--sp-admin-transition);
			}
			.sp-cf7-icons-input:focus {
				outline: none;
				border-color: var(--sp-admin-accent);
				box-shadow: var(--sp-admin-focus);
			}
			.sp-cf7-icons-search-wrap {
				position: relative;
			}
			.sp-cf7-icons-search-wrap .dashicons {
				position: absolute;
				left: 10px;
				top: 50%;
				transform: translateY(-50%);
				color: var(--sp-admin-subtle);
			}
			.sp-cf7-icons-search-wrap input {
				padding-left: 36px;
			}
			.sp-cf7-icons-header {
				font-size: 12px;
				font-weight: 700;
				text-transform: uppercase;
				color: var(--sp-admin-text);
				margin-bottom: 12px;
				letter-spacing: 0.5px;
			}
			.sp-cf7-icons-grid {
				display: grid;
				grid-template-columns: repeat(auto-fill, minmax(85px, 1fr));
				gap: 10px;
				max-height: 250px;
				overflow-y: auto;
				padding: 8px;
				border: 1px solid var(--sp-admin-border);
				border-radius: var(--sp-admin-radius);
				background: var(--sp-admin-surface);
			}
			.sp-cf7-icon-card {
				display: flex;
				flex-direction: column;
				align-items: center;
				justify-content: center;
				padding: 10px;
				border: 1px solid var(--sp-admin-border);
				border-radius: var(--sp-admin-radius-sm);
				cursor: pointer;
				transition: border-color var(--sp-admin-transition), background var(--sp-admin-transition), box-shadow var(--sp-admin-transition), transform var(--sp-admin-transition);
				background: var(--sp-admin-surface-subtle);
				user-select: none;
			}
			.sp-cf7-icon-card:hover,
			.sp-cf7-icon-card:focus-within {
				border-color: var(--sp-admin-accent);
				background: var(--sp-admin-accent-softer);
				transform: translateY(-1px);
				box-shadow: var(--sp-admin-shadow-hover);
			}
			.sp-cf7-icon-preview {
				width: 32px;
				height: 32px;
				display: flex;
				align-items: center;
				justify-content: center;
				margin-bottom: 6px;
				color: var(--sp-admin-text);
			}
			.sp-cf7-icon-preview svg {
				width: 100%;
				height: 100%;
				fill: currentColor;
			}
			.sp-cf7-icon-name {
				font-size: 9px;
				color: var(--sp-admin-muted);
				text-align: center;
				word-break: break-all;
				line-height: 1.2;
				font-weight: 500;
			}
			.sp-cf7-icons-empty {
				padding: 30px;
				text-align: center;
				background: var(--sp-admin-surface-subtle);
				border: 1px dashed var(--sp-admin-border-strong);
				border-radius: var(--sp-admin-radius);
				color: var(--sp-admin-muted);
				font-size: 13px;
			}
			.sp-cf7-icons-empty a {
				color: var(--sp-admin-accent);
			}
		</style>
		<?php
	} );

	// Add JS to admin footer
	add_action( 'admin_footer', function() {
		$screen = get_current_screen();
		if ( ! $screen || strpos( $screen->id, 'wpcf7' ) === false ) {
			return;
		}
		?>
		<script>
			jQuery(function($) {
				// Search icons filtering
				$(document).on('input', '#sp-cf7-icons-search', function() {
					var query = $(this).val().toLowerCase().trim();
					$('.sp-cf7-icon-card').each(function() {
						var slug = $(this).data('slug').toLowerCase();
						$(this).toggle(slug.indexOf(query) !== -1);
					});
				});

				// Icon selection click handler
				$(document).on('click', '.sp-cf7-icon-card', function() {
					var slug = $(this).data('slug');
					var w = $('#sp-cf7-icon-width').val().trim();
					var h = $('#sp-cf7-icon-height').val().trim();
					var cls = $('#sp-cf7-icon-class').val().trim();

					// Construct the [sp_icon] shortcode
					var tag = '[sp_icon name="' + slug + '"';
					if (cls) tag += ' class="' + cls + '"';
					if (w) tag += ' width="' + w + '"';
					if (h) tag += ' height="' + h + '"';
					tag += ']';

					// Insert tag to editor
					var textarea = document.getElementById('wpcf7-form');
					if (textarea) {
						var start = textarea.selectionStart;
						var end = textarea.selectionEnd;
						var val = textarea.value;
						textarea.value = val.substring(0, start) + tag + val.substring(end);
						textarea.selectionStart = textarea.selectionEnd = start + tag.length;
						textarea.focus();
					}

					// Close thickbox dialog
					if (typeof tb_remove === 'function') {
						tb_remove();
					}
				});
			});
		</script>
		<?php
	}, 9999 );
