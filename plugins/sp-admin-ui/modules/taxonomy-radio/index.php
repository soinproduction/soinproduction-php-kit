<?php
	// =========================================================================
	// Taxonomy — Radio (single-select, with save fix)
	// =========================================================================

	if ( ! function_exists( 'sp_taxonomy_radio_save' ) ) {
		function sp_taxonomy_radio_save( $post_id, $post ): void {
			if ( ! sp_taxonomy_is_valid_save_context( (int) $post_id, $post ) ) {
				return;
			}

			$nonce = isset( $_POST['sp_taxonomy_radio_nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['sp_taxonomy_radio_nonce'] ) ) : '';
			if ( $nonce === '' || ! wp_verify_nonce( $nonce, 'sp_taxonomy_radio_save' ) ) {
				return;
			}

			if ( ! isset( $_POST['sp_radio'] ) || ! is_array( $_POST['sp_radio'] ) ) {
				return;
			}

			$post_type = get_post_type( $post_id ) ?: '';
			$payload   = (array) wp_unslash( $_POST['sp_radio'] );

			foreach ( $payload as $taxonomy => $value ) {
				$taxonomy = sanitize_key( $taxonomy );
				if ( ! taxonomy_exists( $taxonomy ) ) {
					continue;
				}
				if ( ! is_object_in_taxonomy( $post_type, $taxonomy ) ) {
					continue;
				}

				$value = sanitize_text_field( (string) $value );

				if ( $value === '0' || $value === '' ) {
					wp_set_object_terms( $post_id, [], $taxonomy );
				} else {
					$term_id = (int) $value;
					if ( $term_id <= 0 ) {
						$term = get_term_by( 'slug', $value, $taxonomy );
						$term_id = ( $term && ! is_wp_error( $term ) ) ? (int) $term->term_id : 0;
					}

					if ( $term_id > 0 ) {
						wp_set_object_terms( $post_id, [ $term_id ], $taxonomy );
					}
				}
			}
		}

		add_action( 'save_post', 'sp_taxonomy_radio_save', 10, 2 );
	}

	if ( ! function_exists( 'sp_taxonomy_radio_all_terms' ) ) {
		function sp_taxonomy_radio_all_terms( WP_Post $post, array $box ): void {
			$taxonomy = sp_taxonomy_get_metabox_taxonomy( $box );

			if ( $taxonomy === '' ) {
				return;
			}

			$tax_obj    = get_taxonomy( $taxonomy );
			if ( ! $tax_obj ) {
				return;
			}

			$terms      = get_terms( [ 'taxonomy' => $taxonomy, 'hide_empty' => false ] );
			$current    = wp_get_object_terms( $post->ID, $taxonomy, [ 'fields' => 'ids' ] );
			$current_id = ( is_array( $current ) && ! empty( $current ) ) ? (int) reset( $current ) : 0;
			?>

			<div class="sp-radio-taxonomy sp-admin-component">
				<?php wp_nonce_field( 'sp_taxonomy_radio_save', 'sp_taxonomy_radio_nonce' ); ?>
				<label class="sp-radio-taxonomy__option is-empty">
					<input type="radio" name="sp_radio[<?= esc_attr( $taxonomy ) ?>]" value="0"<?= checked( $current_id, 0, false ) ?>>
					— None —
				</label>

				<?php foreach ( $terms as $term ) : ?>
					<label class="sp-radio-taxonomy__option">
						<input type="radio" name="sp_radio[<?= esc_attr( $taxonomy ) ?>]" value="<?= esc_attr( (string) $term->term_id ) ?>"<?= checked( $current_id, $term->term_id, false ) ?>>
						<?= esc_html( $term->name ) ?>
					</label>
				<?php endforeach; ?>

				<?php if ( current_user_can( $tax_obj->cap->edit_terms ) ) : ?>
					<a class="sp-radio-taxonomy__add" href="<?= esc_url( admin_url( 'edit-tags.php?taxonomy=' . $taxonomy . '&post_type=' . $post->post_type ) ) ?>">
						+ Add New <?= esc_html( $tax_obj->labels->singular_name ) ?>
					</a>
				<?php endif; ?>
			</div>

			<?php
		}
	}
