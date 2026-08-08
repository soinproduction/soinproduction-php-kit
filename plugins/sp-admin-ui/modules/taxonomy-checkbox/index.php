<?php
	// =========================================================================
	// Taxonomy — Checklist (multi-select checkboxes)
	// =========================================================================

	if ( ! function_exists( 'sp_taxonomy_checklist_handle_save' ) ) {
		function sp_taxonomy_checklist_handle_save( $post_id, $post ): void {
			if ( ! sp_taxonomy_is_valid_save_context( (int) $post_id, $post ) ) {
				return;
			}

			$nonce = isset( $_POST['sp_taxonomy_checklist_nonce'] )
				? sanitize_text_field( wp_unslash( (string) $_POST['sp_taxonomy_checklist_nonce'] ) )
				: '';
			if ( $nonce === '' || ! wp_verify_nonce( $nonce, 'sp_taxonomy_checklist_all_terms' ) ) {
				return;
			}

			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return;
			}

			$post_type = get_post_type( $post_id ) ?: '';
			$present   = isset( $_POST['sp_tax_present'] )
				? (array) wp_unslash( $_POST['sp_tax_present'] )
				: [];
			$tax_input = isset( $_POST['sp_tax'] ) && is_array( $_POST['sp_tax'] )
				? (array) wp_unslash( $_POST['sp_tax'] )
				: [];

			foreach ( $present as $tax ) {
				$tax = sanitize_key( $tax );

				if ( ! taxonomy_exists( $tax ) ) {
					continue;
				}
				if ( ! is_object_in_taxonomy( $post_type, $tax ) ) {
					continue;
				}

				$ids = isset( $tax_input[ $tax ] ) ? (array) $tax_input[ $tax ] : [];
				$ids = array_values( array_filter( array_map( 'intval', $ids ), static fn( $v ) => $v > 0 ) );

				$result = wp_set_object_terms( $post_id, $ids, $tax, false );
				if ( is_wp_error( $result ) ) {
					continue;
				}
			}
		}

		add_action( 'save_post', 'sp_taxonomy_checklist_handle_save', 10, 2 );
	}

	if ( ! function_exists( 'sp_taxonomy_checklist_all_terms' ) ) {
		function sp_taxonomy_checklist_all_terms( WP_Post $post, array $box ): void {
			$tax = sp_taxonomy_get_metabox_taxonomy( $box );
			if ( $tax === '' ) return;

			$is_hierarchical = is_taxonomy_hierarchical( $tax );
			$selected_ids    = array_map( 'intval', (array) wp_get_object_terms( $post->ID, $tax, [ 'fields' => 'ids' ] ) );
			$terms           = get_terms( [ 'taxonomy' => $tax, 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC' ] );
			$uid             = 'taxonomy-' . sanitize_html_class( $tax );

			echo '<div class="categorydiv sp-taxonomy-checklist sp-admin-component" id="' . esc_attr( $uid ) . '">';
			wp_nonce_field( 'sp_taxonomy_checklist_all_terms', 'sp_taxonomy_checklist_nonce' );
			echo '<input type="hidden" name="sp_tax_present[]" value="' . esc_attr( $tax ) . '">';

			if ( is_wp_error( $terms ) ) {
				echo '<p>' . esc_html__( 'Failed to load terms.', 'ACF Fields' ) . '</p></div>';
				return;
			}

			if ( empty( $terms ) ) {
				echo '<p>' . esc_html__( 'No terms yet.', 'ACF Fields' ) . '</p></div>';
				return;
			}

			$count = count( $selected_ids );
			?>

            <div class="sp-taxonomy-checklist__header">
                <label class="sp-taxonomy-checklist__select-all">
                    <input type="checkbox" class="sp-select-all">
                    Select All
                </label>
                <span class="sp-selected-count"><?= $count ?> selected</span>
            </div>

            <ul id="<?= esc_attr( $tax ) ?>checklist" class="sp-taxonomy-checklist__list">
				<?php if ( $is_hierarchical ):

					$parents  = [];
					$children = [];
					foreach ( $terms as $t ) {
						if ( (int) $t->parent === 0 ) {
							$parents[] = $t;
						} else {
							$children[ (int) $t->parent ][] = $t;
						}
					}

					foreach ( $parents as $parent ):
						$term_id = (int) $parent->term_id;
						$checked = in_array( $term_id, $selected_ids, true );
						?>
                        <li class="sp-taxonomy-checklist__item is-parent">
                            <label class="sp-taxonomy-checklist__label">
                                <input
                                       type="checkbox"
                                       class="sp-tax-item"
                                       name="sp_tax[<?= esc_attr( $tax ) ?>][]"
                                       value="<?= $term_id ?>"
									<?= checked( $checked, true, false ) ?>>
								<?= esc_html( $parent->name ) ?>
                            </label>

							<?php if ( ! empty( $children[ $term_id ] ) ): ?>
                                <ul class="sp-taxonomy-checklist__children">
									<?php foreach ( $children[ $term_id ] as $child ):
										$child_id      = (int) $child->term_id;
										$child_checked = in_array( $child_id, $selected_ids, true );
										?>
                                        <li class="sp-taxonomy-checklist__item is-child">
                                            <label class="sp-taxonomy-checklist__label">
                                                <input type="checkbox"
                                                       class="sp-tax-item sp-tax-child"
                                                       name="sp_tax[<?= esc_attr( $tax ) ?>][]"
                                                       value="<?= $child_id ?>"
                                                       data-parent="<?= $term_id ?>"
													<?= checked( $child_checked, true, false ) ?>>
												<?= esc_html( $child->name ) ?>
                                            </label>
                                        </li>
									<?php endforeach; ?>
                                </ul>
							<?php endif; ?>
                        </li>
					<?php endforeach;

				else:
					foreach ( $terms as $t ):
						$term_id = (int) $t->term_id;
						$checked = in_array( $term_id, $selected_ids, true );
						?>
                        <li class="sp-taxonomy-checklist__item">
                            <label class="sp-taxonomy-checklist__label">
                                <input type="checkbox"
                                       class="sp-tax-item"
                                       name="sp_tax[<?= esc_attr( $tax ) ?>][]"
                                       value="<?= $term_id ?>"
									<?= checked( $checked, true, false ) ?>>
								<?= esc_html( $t->name ) ?>
                            </label>
                        </li>
					<?php endforeach;
				endif; ?>
            </ul>

            <script>
                (function () {
                    var wrap      = document.getElementById('<?= esc_js( $uid ) ?>');
                    if (!wrap) return;
                    var selectAll = wrap.querySelector('.sp-select-all');
                    var items     = wrap.querySelectorAll('.sp-tax-item');
                    var counter   = wrap.querySelector('.sp-selected-count');

                    function updateCounter() {
                        counter.textContent = wrap.querySelectorAll('.sp-tax-item:checked').length + ' selected';
                    }

                    function updateSelectAll() {
                        var checked = wrap.querySelectorAll('.sp-tax-item:checked').length;
                        selectAll.checked       = checked === items.length;
                        selectAll.indeterminate = checked > 0 && checked < items.length;
                        updateCounter();
                    }

                    updateSelectAll();

                    selectAll.addEventListener('change', function () {
                        items.forEach(function (cb) { cb.checked = selectAll.checked; });
                        updateCounter();
                    });

                    wrap.querySelectorAll('.sp-tax-item:not(.sp-tax-child)').forEach(function (parentCb) {
                        parentCb.addEventListener('change', function () {
                            var parentId = parentCb.value;
                            wrap.querySelectorAll('.sp-tax-child[data-parent="' + parentId + '"]').forEach(function (childCb) {
                                childCb.checked = parentCb.checked;
                            });
                            updateSelectAll();
                        });
                    });

                    items.forEach(function (cb) {
                        cb.addEventListener('change', updateSelectAll);
                    });
                })();
            </script>

			<?php
			echo '</div>';
		}
	}
