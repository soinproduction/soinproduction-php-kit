<?php

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	if ( ! function_exists( 'register_acf_text_column' ) ) {
		/**
		 * Register an optionally editable text column for a post type or taxonomy.
		 *
		 * A non-empty ACF field reads and updates a scalar ACF value. When the field
		 * is empty, posts use post_excerpt and terms use their native description.
		 */
		function register_acf_text_column(
			string $type,
			string $object,
			string $column_label = 'Text',
			string $after = '',
			string $acf_field = '',
			string $column_key = '',
			int $width = 0,
			int $max_words = 0,
			string $prefix = '',
			string $suffix = '',
			bool $editable = true,
			string $input_type = '',
		): void {
			$type   = sanitize_key( $type );
			$object = sanitize_key( $object );

			if ( ! in_array( $type, [ 'post', 'term' ], true ) || $object === '' ) {
				return;
			}

			$acf_field  = sanitize_key( $acf_field );
			$source_key = $acf_field !== '' ? $acf_field : ( $type === 'post' ? 'excerpt' : 'description' );
			$column_key = sanitize_key( $column_key !== '' ? $column_key : "sp_text_{$object}_{$source_key}" );
			$after      = sanitize_key( $after !== '' ? $after : ( $type === 'post' ? 'title' : 'name' ) );
			$width      = max( 0, $width );
			$max_words  = max( 0, $max_words );
			$input_type = sanitize_key( $input_type );

			if ( $column_key === '' ) {
				return;
			}

			$ajax_action = "sp_save_text_{$object}_{$column_key}";
			$nonce_key   = $ajax_action;
			$get_acf_key = static fn( int $id ): int|string => $type === 'post' ? $id : "{$object}_{$id}";

			$normalize_text = static function ( mixed $value ): string {
				if ( is_scalar( $value ) || $value instanceof Stringable ) {
					return trim( wp_strip_all_tags( (string) $value ) );
				}

				return '';
			};

			$get_value = static function ( int $id ) use ( $type, $object, $acf_field, $get_acf_key, $normalize_text ): string {
				if ( $acf_field !== '' ) {
					$value = function_exists( 'get_field' ) ? get_field( $acf_field, $get_acf_key( $id ) ) : '';
				} elseif ( $type === 'post' ) {
					$value = get_post_field( 'post_excerpt', $id, 'raw' );
				} else {
					$value = get_term_field( 'description', $id, $object, 'raw' );
					$value = is_wp_error( $value ) ? '' : $value;
				}

				return $normalize_text( $value );
			};

			$format_value = static function ( string $value ) use ( $max_words, $prefix, $suffix ): string {
				if ( $value === '' ) {
					return '';
				}

				if ( $max_words > 0 ) {
					$value = wp_trim_words( $value, $max_words, '…' );
				}

				return $prefix . $value . $suffix;
			};

			$get_input_type = static function ( int $id ) use ( $input_type, $type, $acf_field, $get_acf_key ): string {
				$allowed_types = [ 'text', 'textarea', 'number', 'email', 'url' ];
				if ( in_array( $input_type, $allowed_types, true ) ) {
					return $input_type;
				}

				if ( $acf_field === '' ) {
					return 'textarea';
				}

				if ( function_exists( 'get_field_object' ) ) {
					$field = get_field_object( $acf_field, $get_acf_key( $id ), false, false );
					$field_type = is_array( $field ) ? sanitize_key( (string) ( $field['type'] ?? '' ) ) : '';
					if ( in_array( $field_type, $allowed_types, true ) ) {
						return $field_type;
					}
				}

				return $type === 'term' && $acf_field === '' ? 'textarea' : 'text';
			};

			$inject_column = static function ( array $columns ) use ( $column_key, $column_label, $after ): array {
				$result = [];

				foreach ( $columns as $key => $label ) {
					$result[ $key ] = $label;
					if ( $key === $after ) {
						$result[ $column_key ] = __( $column_label, 'ACF Fields' );
					}
				}

				if ( ! isset( $result[ $column_key ] ) ) {
					$result[ $column_key ] = __( $column_label, 'ACF Fields' );
				}

				return $result;
			};

			$render_cell = static function ( int $id ) use ( $object, $type, $editable, $ajax_action, $nonce_key, $get_value, $format_value, $get_input_type ): string {
				$value         = $get_value( $id );
				$display_value = $format_value( $value );
				$editor_type   = $get_input_type( $id );

				ob_start();
				?>
				<div class="sp-admin-text-column<?php echo $editable ? ' is-editable' : ''; ?>"
					data-id="<?php echo esc_attr( $id ); ?>"
					data-object="<?php echo esc_attr( $object ); ?>"
					data-type="<?php echo esc_attr( $type ); ?>"
					data-action="<?php echo esc_attr( $ajax_action ); ?>"
					data-nonce="<?php echo esc_attr( wp_create_nonce( $nonce_key ) ); ?>">
					<?php if ( $editable ) : ?>
						<button type="button" class="sp-admin-text-column__trigger" aria-label="<?php esc_attr_e( 'Edit value', 'ACF Fields' ); ?>">
					<?php endif; ?>

					<span class="sp-admin-text-column__value<?php echo $display_value === '' ? ' is-empty' : ''; ?>"><?php echo $display_value !== '' ? esc_html( $display_value ) : '&mdash;'; ?></span>

					<?php if ( $editable ) : ?>
						<span class="dashicons dashicons-edit sp-admin-text-column__edit-icon" aria-hidden="true"></span>
						</button>

						<div class="sp-admin-text-column__editor">
							<?php if ( $editor_type === 'textarea' ) : ?>
								<textarea class="sp-admin-text-column__input" rows="4"><?php echo esc_textarea( $value ); ?></textarea>
							<?php else : ?>
								<input class="sp-admin-text-column__input" type="<?php echo esc_attr( $editor_type ); ?>" value="<?php echo esc_attr( $value ); ?>"<?php echo $editor_type === 'number' ? ' step="any"' : ''; ?>>
							<?php endif; ?>

							<div class="sp-admin-text-column__actions">
								<button type="button" class="button button-primary button-small sp-admin-text-column__save"><?php esc_html_e( 'Save', 'ACF Fields' ); ?></button>
								<button type="button" class="button button-small sp-admin-text-column__cancel"><?php esc_html_e( 'Cancel', 'ACF Fields' ); ?></button>
							</div>
						</div>
					<?php endif; ?>

					<span class="sp-admin-text-column__status" aria-live="polite"></span>
				</div>
				<?php

				return (string) ob_get_clean();
			};

			if ( $width > 0 ) {
				add_action( 'admin_head', static function () use ( $type, $object, $column_key, $width ): void {
					$screen = get_current_screen();
					if ( ! $screen ) {
						return;
					}

					$is_target_screen = $type === 'post'
						? $screen->base === 'edit' && $screen->post_type === $object
						: $screen->base === 'edit-tags' && $screen->taxonomy === $object;

					if ( $is_target_screen ) {
						echo '<style>.column-' . esc_attr( $column_key ) . '{width:' . (int) $width . 'px}</style>';
					}
				} );
			}

			if ( $type === 'post' ) {
				add_filter( "manage_{$object}_posts_columns", static fn( array $columns ): array => $inject_column( $columns ) );

				add_action( "manage_{$object}_posts_custom_column", static function ( string $column, int $post_id ) use ( $column_key, $render_cell ): void {
					if ( $column === $column_key ) {
						echo $render_cell( $post_id );
					}
				}, 10, 2 );
			} else {
				add_filter( "manage_edit-{$object}_columns", static fn( array $columns ): array => $inject_column( $columns ) );

				add_filter( "manage_{$object}_custom_column", static function ( string $output, string $column, int $term_id ) use ( $column_key, $render_cell ): string {
					return $column === $column_key ? $render_cell( $term_id ) : $output;
				}, 10, 3 );
			}

			if ( ! $editable ) {
				return;
			}

			add_action( "wp_ajax_{$ajax_action}", static function () use ( $type, $object, $acf_field, $nonce_key, $get_acf_key, $normalize_text, $format_value ): void {
				check_ajax_referer( $nonce_key, 'nonce' );

				$id = (int) ( $_POST['id'] ?? 0 );
				if ( $id <= 0 ) {
					wp_send_json_error( [ 'message' => __( 'Invalid ID', 'ACF Fields' ) ] );
				}

				if ( $type === 'post' ) {
					if ( get_post_type( $id ) !== $object || ! current_user_can( 'edit_post', $id ) ) {
						wp_send_json_error( [ 'message' => __( 'Insufficient permissions', 'ACF Fields' ) ] );
					}
				} else {
					$term = get_term( $id, $object );
					if ( ! $term || is_wp_error( $term ) || ! current_user_can( 'edit_term', $id ) ) {
						wp_send_json_error( [ 'message' => __( 'Insufficient permissions', 'ACF Fields' ) ] );
					}
				}

				$value = $normalize_text( sanitize_textarea_field( wp_unslash( $_POST['value'] ?? '' ) ) );

				if ( $acf_field !== '' ) {
					if ( ! function_exists( 'update_field' ) ) {
						wp_send_json_error( [ 'message' => __( 'ACF is unavailable', 'ACF Fields' ) ] );
					}

					update_field( $acf_field, $value, $get_acf_key( $id ) );
				} elseif ( $type === 'post' ) {
					$result = wp_update_post( [
						'ID'           => $id,
						'post_excerpt' => $value,
					], true );

					if ( is_wp_error( $result ) ) {
						wp_send_json_error( [ 'message' => $result->get_error_message() ] );
					}
				} else {
					$result = wp_update_term( $id, $object, [ 'description' => $value ] );
					if ( is_wp_error( $result ) ) {
						wp_send_json_error( [ 'message' => $result->get_error_message() ] );
					}
				}

				wp_send_json_success( [
					'message' => __( 'Saved', 'ACF Fields' ),
					'value'   => $value,
					'display' => $format_value( $value ),
				] );
			} );
		}
	}

	add_action( 'admin_head', static function (): void {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->base, [ 'edit', 'edit-tags' ], true ) ) {
			return;
		}
		?>
		<style>
			.sp-admin-text-column {
				position: relative;
			}

			.sp-admin-text-column__trigger {
				display: flex;
				align-items: flex-start;
				gap: 6px;
				width: 100%;
				margin: -4px;
				padding: 4px;
				border: 1px solid transparent;
				border-radius: 4px;
				background: transparent;
				color: inherit;
				font: inherit;
				text-align: left;
				cursor: text;
			}

			.sp-admin-text-column__trigger:hover,
			.sp-admin-text-column__trigger:focus-visible {
				border-color: #8c8f94;
				outline: 0;
			}

			.sp-admin-text-column__value {
				max-width: 100%;
				overflow-wrap: anywhere;
				white-space: pre-line;
			}

			.sp-admin-text-column__value.is-empty {
				color: #8c8f94;
			}

			.sp-admin-text-column__edit-icon {
				width: 16px;
				height: 16px;
				margin-left: auto;
				font-size: 16px;
				line-height: 16px;
				opacity: 0;
			}

			.sp-admin-text-column__trigger:hover .sp-admin-text-column__edit-icon,
			.sp-admin-text-column__trigger:focus-visible .sp-admin-text-column__edit-icon {
				opacity: 1;
			}

			.sp-admin-text-column__editor {
				display: none;
			}

			.sp-admin-text-column.is-editing .sp-admin-text-column__trigger {
				display: none;
			}

			.sp-admin-text-column.is-editing .sp-admin-text-column__editor {
				display: block;
			}

			.sp-admin-text-column__input {
				box-sizing: border-box;
				width: 100%;
				min-width: 100px;
			}

			textarea.sp-admin-text-column__input {
				min-height: 84px;
				resize: vertical;
			}

			.sp-admin-text-column__actions {
				display: flex;
				gap: 5px;
				margin-top: 6px;
			}

			.sp-admin-text-column__status {
				display: block;
				min-height: 16px;
				margin-top: 3px;
				color: #008a20;
				font-size: 11px;
			}

			.sp-admin-text-column__status.is-error {
				color: #d63638;
			}
		</style>
		<?php
	} );

	add_action( 'admin_footer', static function (): void {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->base, [ 'edit', 'edit-tags' ], true ) ) {
			return;
		}
		?>
		<script>
			(function ($) {
				function setStatus($cell, message, isError) {
					var $status = $cell.find('.sp-admin-text-column__status');
					$status.text(message || '').toggleClass('is-error', !!isError);

					if (message && !isError) {
						setTimeout(function () {
							$status.text('');
						}, 1500);
					}
				}

				function closeEditor($cell, restoreValue) {
					var $input = $cell.find('.sp-admin-text-column__input');
					if (restoreValue) {
						$input.val($input.attr('data-original-value') || '');
					}
					$cell.removeClass('is-editing');
				}

				function saveCell($cell) {
					var $input = $cell.find('.sp-admin-text-column__input');
					var $buttons = $cell.find('.sp-admin-text-column__save, .sp-admin-text-column__cancel').prop('disabled', true);

					setStatus($cell, <?php echo wp_json_encode( __( 'Saving…', 'ACF Fields' ) ); ?>, false);

					$.post(ajaxurl, {
						action: $cell.data('action'),
						nonce: $cell.data('nonce'),
						id: $cell.data('id'),
						value: $input.val(),
					})
						.done(function (response) {
							if (!response || !response.success) {
								setStatus($cell, response?.data?.message || <?php echo wp_json_encode( __( 'Error', 'ACF Fields' ) ); ?>, true);
								return;
							}

							var value = response.data?.value ?? '';
							var display = response.data?.display ?? '';
							var $display = $cell.find('.sp-admin-text-column__value');

							$input.val(value).attr('data-original-value', value);
							$display.text(display || '—').toggleClass('is-empty', !display);
							closeEditor($cell, false);
							setStatus($cell, response.data?.message || <?php echo wp_json_encode( __( 'Saved', 'ACF Fields' ) ); ?>, false);
						})
						.fail(function () {
							setStatus($cell, <?php echo wp_json_encode( __( 'Error', 'ACF Fields' ) ); ?>, true);
						})
						.always(function () {
							$buttons.prop('disabled', false);
						});
				}

				$(document).on('click', '.sp-admin-text-column__trigger', function () {
					var $cell = $(this).closest('.sp-admin-text-column');
					var $input = $cell.find('.sp-admin-text-column__input');

					$('.sp-admin-text-column.is-editing').not($cell).each(function () {
						closeEditor($(this), true);
					});

					$input.attr('data-original-value', $input.val());
					$cell.addClass('is-editing');
					$input.trigger('focus').trigger('select');
				});

				$(document).on('click', '.sp-admin-text-column__cancel', function () {
					closeEditor($(this).closest('.sp-admin-text-column'), true);
				});

				$(document).on('click', '.sp-admin-text-column__save', function () {
					saveCell($(this).closest('.sp-admin-text-column'));
				});

				$(document).on('keydown', '.sp-admin-text-column__input', function (event) {
					var $cell = $(this).closest('.sp-admin-text-column');

					if (event.key === 'Escape') {
						event.preventDefault();
						closeEditor($cell, true);
						return;
					}

					if (event.key === 'Enter' && (!this.matches('textarea') || event.ctrlKey || event.metaKey)) {
						event.preventDefault();
						saveCell($cell);
					}
				});
			})(jQuery);
		</script>
		<?php
	} );
