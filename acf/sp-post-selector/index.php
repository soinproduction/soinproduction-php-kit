<?php
	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/* ================================================================
	 * ACF Field Type: Smart Relationship
	 * ================================================================
	 *
	 * Usage (ACF Builder):
	 *   ->addFields( smart_relationship( 'team_members', [
	 *       'label'         => false,
	 *       'post_type'     => ['team'],
	 *       'return_format' => 'id',          // 'id' | 'object'
	 *       'modes'         => ['favorites', 'manual', 'all'],
	 *       'default_mode'  => 'manual',
	 *       'taxonomy'      => [],            // optional taxonomy filter
	 *       'thumb_field'   => '',            // ACF image field name or ordered names, empty = featured image
	 *       'min'           => 0,
	 *       'max'           => 0,
	 *   ]) )
	 *
	 * Returned value:
	 *   get_field('team_members')  →  [12, 34, 56]  or  [WP_Post, ...]
	 *
	 * ================================================================ */

	add_action( 'acf/include_field_types', function (): void {
		if ( ! class_exists( 'acf_field' ) || class_exists( 'acf_field_smart_relationship', false ) ) {
			return;
		}

		class acf_field_smart_relationship extends acf_field {

			public function initialize(): void {
				$this->name     = 'smart_relationship';
				$this->label    = __( 'Smart Relationship', 'acf' );
				$this->category = 'relational';
				$this->defaults = [
					'post_type'     => [],
					'taxonomy'      => [],
					'return_format' => 'id',
					'modes'         => [ 'manual', 'favorites', 'all' ],
					'default_mode'  => 'manual',
					'thumb_field'   => 'none',
					'min'           => 0,
					'max'           => 0,
				];
			}

			/* ── Settings ──────────────────────────────────── */

			public function render_field_settings( array $field ): void {

				acf_render_field_setting( $field, [
					'label'        => __( 'Post Type', 'acf' ),
					'type'         => 'select',
					'name'         => 'post_type',
					'choices'      => function_exists( 'acf_get_pretty_post_types' ) ? acf_get_pretty_post_types() : self::get_post_type_choices(),
					'multiple'     => 1,
					'ui'           => 1,
					'allow_null'   => 1,
					'placeholder'  => __( 'All post types', 'acf' ),
				] );

				acf_render_field_setting( $field, [
					'label'        => __( 'Taxonomy Filter', 'acf' ),
					'type'         => 'select',
					'name'         => 'taxonomy',
					'choices'      => function_exists( 'acf_get_taxonomy_labels' ) ? acf_get_taxonomy_labels() : self::get_taxonomy_choices(),
					'multiple'     => 1,
					'ui'           => 1,
					'allow_null'   => 1,
					'placeholder'  => __( 'No filter', 'acf' ),
				] );

				acf_render_field_setting( $field, [
					'label'   => __( 'Modes', 'acf' ),
					'type'    => 'checkbox',
					'name'    => 'modes',
					'choices' => [
						'manual'    => __( 'Manual', 'acf' ),
						'favorites' => __( 'Favorites', 'acf' ),
						'all'       => __( 'All', 'acf' ),
					],
				] );

				acf_render_field_setting( $field, [
					'label'   => __( 'Default Mode', 'acf' ),
					'type'    => 'select',
					'name'    => 'default_mode',
					'choices' => [
						'manual'    => 'Manual',
						'favorites' => 'Favorites',
						'all'       => 'All',
					],
				] );

				acf_render_field_setting( $field, [
					'label' => __( 'Return Format', 'acf' ),
					'type'  => 'radio',
					'name'  => 'return_format',
					'choices' => [
						'id'     => 'Post ID',
						'object' => 'Post Object',
					],
					'layout' => 'horizontal',
				] );

				acf_render_field_setting( $field, [
					'label' => __( 'Minimum', 'acf' ),
					'type'  => 'number',
					'name'  => 'min',
				] );

				acf_render_field_setting( $field, [
					'label' => __( 'Maximum', 'acf' ),
					'type'  => 'number',
					'name'  => 'max',
				] );

				acf_render_field_setting( $field, [
					'label'        => __( 'Thumbnail Field', 'acf' ),
					'instructions' => __( 'ACF image field name. Leave empty to use Featured Image.', 'acf' ),
					'type'         => 'text',
					'name'         => 'thumb_field',
					'placeholder'  => __( 'Featured Image', 'acf' ),
				] );
			}

			private static function get_post_type_choices(): array {
				$choices = [];

				foreach ( get_post_types( [ 'show_ui' => true ], 'objects' ) as $post_type => $object ) {
					$choices[ $post_type ] = $object->labels->singular_name ?? $object->label ?? $post_type;
				}

				return $choices;
			}

			private static function get_taxonomy_choices(): array {
				$choices = [];

				foreach ( get_taxonomies( [ 'show_ui' => true ], 'objects' ) as $taxonomy => $object ) {
					$choices[ $taxonomy ] = $object->labels->singular_name ?? $object->label ?? $taxonomy;
				}

				return $choices;
			}

			/* ── Render ────────────────────────────────────── */

			public function render_field( array $field ): void {
				$modes        = ! empty( $field['modes'] ) ? (array) $field['modes'] : [ 'manual' ];
				$value        = is_array( $field['value'] ) ? $field['value'] : [];
				$current_mode = $value['mode'] ?? ( $field['default_mode'] ?: $modes[0] );
				$selected_ids = ! empty( $value['ids'] ) ? array_map( 'intval', (array) $value['ids'] ) : [];
				$post_types   = ! empty( $field['post_type'] ) ? (array) $field['post_type'] : get_post_types( [ 'public' => true ] );
				$taxonomies   = ! empty( $field['taxonomy'] ) ? (array) $field['taxonomy'] : [];
				$max          = (int) ( $field['max'] ?? 0 );
				$fname        = esc_attr( $field['name'] );

				$mode_labels = [
					'favorites' => __( 'Favorites', 'acf' ),
					'manual'    => __( 'Manual', 'acf' ),
					'all'       => __( 'All', 'acf' ),
				];

				$thumb_field = $field['thumb_field'] ?? '';

				$config = wp_json_encode( [
					'post_type'   => array_values( $post_types ),
					'taxonomy'    => array_values( $taxonomies ),
					'max'         => $max,
					'thumb_field' => $thumb_field,
					'nonce'       => wp_create_nonce( 'sp_srel' ),
					'i18n'        => [
						'selected'    => __( 'selected', 'acf' ),
						'loading'     => __( 'Loading posts…', 'acf' ),
						'load_error'  => __( 'Could not load posts. Please try again.', 'acf' ),
						'updated'     => __( 'Results updated.', 'acf' ),
						'empty'       => __( 'No posts found', 'acf' ),
						'selected_empty' => __( 'Select posts from the available list.', 'acf' ),
						'add'         => __( 'Add post', 'acf' ),
						'remove'      => __( 'Remove post', 'acf' ),
						'drag'        => __( 'Drag to reorder', 'acf' ),
						'max_reached' => __( 'Maximum number of posts selected.', 'acf' ),
					],
				] );

				echo '<div class="sp-srel sp-admin-component sp-acf-component" data-sp-admin-component data-config=\'' . esc_attr( $config ) . '\' aria-busy="false">';

				/* ── Mode input ── */
				echo '<input type="hidden" name="' . $fname . '[mode]" value="' . esc_attr( $current_mode ) . '" class="sp-srel__mode-input">';

				/* ── Tabs ── */
				if ( count( $modes ) > 1 ) {
					echo '<div class="sp-srel__tabs" role="group" aria-label="' . esc_attr__( 'Selection mode', 'acf' ) . '">';
					foreach ( $modes as $mode ) {
						$active = ( $mode === $current_mode ) ? ' is-active' : '';
						$label  = $mode_labels[ $mode ] ?? ucfirst( $mode );
						echo '<button type="button" class="sp-srel__tab' . $active . '" data-mode="' . esc_attr( $mode ) . '" aria-pressed="' . ( $mode === $current_mode ? 'true' : 'false' ) . '">'
						     . esc_html( $label )
						     . '</button>';
					}
					echo '</div>';
				}

				/* ── Manual Panel ── */
				if ( in_array( 'manual', $modes, true ) ) {
					$hidden = ( $current_mode !== 'manual' ) ? ' style="display:none"' : '';
					echo '<div class="sp-srel__panel" data-panel="manual"' . $hidden . '>';
					echo '<div class="sp-srel__picker">';

					/* Available column */
					echo '<div class="sp-srel__col sp-srel__col--available">';
					echo '<div class="sp-srel__col-header">';
					echo '<input type="search" class="sp-srel__search" placeholder="' . esc_attr__( 'Search…', 'acf' ) . '" autocomplete="off">';

					/* Taxonomy filter dropdown */
					if ( ! empty( $taxonomies ) ) {
						echo '<select class="sp-srel__tax-filter">';
						echo '<option value="">' . esc_html__( 'All terms', 'acf' ) . '</option>';
						foreach ( $taxonomies as $tax ) {
							$terms = get_terms( [ 'taxonomy' => $tax, 'hide_empty' => false ] );
							if ( is_wp_error( $terms ) ) {
								continue;
							}
							$tax_obj = get_taxonomy( $tax );
							$tax_label = $tax_obj ? $tax_obj->labels->singular_name : $tax;
							foreach ( $terms as $t ) {
								echo '<option value="' . esc_attr( $tax . ':' . $t->term_id ) . '">'
								     . esc_html( $tax_label . ': ' . $t->name )
								     . '</option>';
							}
						}
						echo '</select>';
					}
					echo '</div>';
					echo '<div class="sp-srel__list sp-srel__list--avail" data-empty="' . esc_attr__( 'No posts found', 'acf' ) . '" role="list" aria-busy="false"></div>';
					echo '<button type="button" class="sp-srel__load-more" style="display:none">' . esc_html__( 'Load more', 'acf' ) . '</button>';
					echo '<p class="sp-srel__status" role="status" aria-live="polite"></p>';
					echo '</div>';

					/* Selected column */
					echo '<div class="sp-srel__col sp-srel__col--selected">';
					echo '<div class="sp-srel__col-header">';
					echo '<span class="sp-srel__count">' . count( $selected_ids ) . ' ' . esc_html__( 'selected', 'acf' ) . '</span>';
					echo '</div>';
					echo '<div class="sp-srel__list sp-srel__list--sel" data-empty="' . esc_attr__( 'Select posts from the available list.', 'acf' ) . '" role="list" aria-live="polite">';

					/* Pre-render selected posts */
					if ( $selected_ids ) {
						$posts_map = [];
						$posts_q   = get_posts( [
							'post_type'      => $post_types,
							'post__in'       => $selected_ids,
							'posts_per_page' => count( $selected_ids ),
							'post_status'    => 'any',
							'orderby'        => 'post__in',
						] );
						foreach ( $posts_q as $p ) {
							$posts_map[ $p->ID ] = $p;
						}

						foreach ( $selected_ids as $pid ) {
							if ( ! isset( $posts_map[ $pid ] ) ) {
								continue;
							}
							$p = $posts_map[ $pid ];
							echo self::render_post_item( $p, $fname, true, $thumb_field );
						}
					}

					echo '</div>';
					echo '</div>';

					echo '</div>'; /* picker */
					echo '</div>'; /* panel */
				}

				/* ── Favorites Panel ── */
				if ( in_array( 'favorites', $modes, true ) ) {
					$hidden = ( $current_mode !== 'favorites' ) ? ' style="display:none"' : '';
					echo '<div class="sp-srel__panel" data-panel="favorites"' . $hidden . '>';
					echo '<div class="sp-srel__info">';
					echo '<svg class="sp-srel__info-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>';
					echo '<div>';
					echo '<strong>' . esc_html__( 'Favorites Mode', 'acf' ) . '</strong><br>';
					echo '<span>' . esc_html__( 'Posts marked as Favorites will be displayed automatically.', 'acf' ) . '</span>';
					echo '</div>';
					echo '</div>';
					echo '</div>';
				}

				/* ── All Panel ── */
				if ( in_array( 'all', $modes, true ) ) {
					$hidden = ( $current_mode !== 'all' ) ? ' style="display:none"' : '';
					echo '<div class="sp-srel__panel" data-panel="all"' . $hidden . '>';
					echo '<div class="sp-srel__info">';
					echo '<svg class="sp-srel__info-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>';
					echo '<div>';
					echo '<strong>' . esc_html__( 'All Posts Mode', 'acf' ) . '</strong><br>';
					echo '<span>' . esc_html__( 'All published posts will be displayed automatically.', 'acf' ) . '</span>';
					echo '</div>';
					echo '</div>';
					echo '</div>';
				}

				echo '</div>'; /* sp-srel */
			}

			/* ── Render single post item ───────────────────── */

			private static function normalize_thumb_fields( $thumb_field ): array {
				$values = is_array( $thumb_field ) ? $thumb_field : [ $thumb_field ];
				$fields = [];

				foreach ( $values as $value ) {
					if ( ! is_scalar( $value ) ) {
						continue;
					}

					$raw   = trim( (string) $value );
					$field = sanitize_key( $raw );

					if ( $raw !== '' && $field === '' ) {
						continue;
					}

					if ( ! in_array( $field, $fields, true ) ) {
						$fields[] = $field;
					}
				}

				return $fields ?: [ '' ];
			}

			public static function render_post_item( WP_Post $post, string $field_name, bool $is_selected, $thumb_field = 'none' ): string {
				$pid          = (int) $post->ID;
				$thumb_fields = self::normalize_thumb_fields( $thumb_field );
				$hide_thumb   = count( $thumb_fields ) === 1 && $thumb_fields[0] === 'none';
				$thumb        = '';

				if ( ! $hide_thumb ) {
					foreach ( $thumb_fields as $candidate ) {
						if ( $candidate === 'none' ) {
							continue;
						}

						if ( $candidate === '' || $candidate === 'featured_image' ) {
							$thumb = get_the_post_thumbnail_url( $pid, 'thumbnail' ) ?: '';
						} elseif ( function_exists( 'get_field' ) ) {
							$acf_img = get_field( $candidate, $pid );
							if ( is_array( $acf_img ) && ! empty( $acf_img['sizes']['thumbnail'] ) ) {
								$thumb = $acf_img['sizes']['thumbnail'];
							} elseif ( is_array( $acf_img ) && ! empty( $acf_img['url'] ) ) {
								$thumb = $acf_img['url'];
							} elseif ( is_numeric( $acf_img ) ) {
								$thumb = wp_get_attachment_image_url( absint( $acf_img ), 'thumbnail' ) ?: '';
							} elseif ( is_string( $acf_img ) && $acf_img !== '' ) {
								$thumb = $acf_img;
							}
						}

						if ( $thumb !== '' ) {
							break;
						}
					}

					if ( $thumb === '' ) {
						$thumb = get_the_post_thumbnail_url( $pid, 'thumbnail' ) ?: '';
					}
				}

				$title = get_the_title( $pid );
				$type  = get_post_type_object( $post->post_type );
				$label = $type ? $type->labels->singular_name : $post->post_type;

				$html = '<div class="sp-srel__item' . ( $is_selected ? ' is-selected' : '' ) . '" data-id="' . $pid . '" role="listitem">';

				/* Drag handle (selected only) */
				if ( $is_selected ) {
					$html .= '<span class="sp-srel__drag" draggable="true" aria-label="' . esc_attr__( 'Drag to reorder', 'acf' ) . '" title="' . esc_attr__( 'Drag to reorder', 'acf' ) . '">'
					         . '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="16" height="16">'
					         . '<circle cx="9" cy="6" r="1.5"/><circle cx="15" cy="6" r="1.5"/>'
					         . '<circle cx="9" cy="12" r="1.5"/><circle cx="15" cy="12" r="1.5"/>'
					         . '<circle cx="9" cy="18" r="1.5"/><circle cx="15" cy="18" r="1.5"/>'
					         . '</svg></span>';
				}

				/* Thumbnail (skip if 'none') */
				if ( ! $hide_thumb ) {
					$html .= '<span class="sp-srel__thumb">';
					if ( $thumb ) {
						$html .= '<img src="' . esc_url( $thumb ) . '" alt="">';
					} else {
						$html .= '<span class="sp-srel__thumb-empty dashicons dashicons-format-image"></span>';
					}
					$html .= '</span>';
				}

				/* Title + type */
				$html .= '<span class="sp-srel__title">'
				         . '<span class="sp-srel__title-text">' . esc_html( $title ) . '</span>'
				         . '<span class="sp-srel__title-type">' . esc_html( $label ) . '</span>'
				         . '</span>';

				/* Action button */
				if ( $is_selected ) {
					$html .= '<input type="hidden" name="' . esc_attr( $field_name ) . '[ids][]" value="' . $pid . '">';
					$html .= '<button type="button" class="sp-srel__remove" aria-label="' . esc_attr__( 'Remove post', 'acf' ) . '" title="' . esc_attr__( 'Remove post', 'acf' ) . '">&times;</button>';
				} else {
					$html .= '<button type="button" class="sp-srel__add" aria-label="' . esc_attr__( 'Add post', 'acf' ) . '" title="' . esc_attr__( 'Add post', 'acf' ) . '">+</button>';
				}

				$html .= '</div>';
				return $html;
			}

			/* ── Save ──────────────────────────────────────── */

			public function update_value( $value, $post_id, array $field ) {
				if ( ! is_array( $value ) ) {
					return [ 'mode' => $field['default_mode'] ?? 'manual', 'ids' => [] ];
				}

				$mode = sanitize_key( $value['mode'] ?? 'manual' );
				$ids  = [];

				if ( ! empty( $value['ids'] ) && is_array( $value['ids'] ) ) {
					$ids = array_values( array_unique( array_filter( array_map( 'absint', $value['ids'] ) ) ) );
				}

				return [ 'mode' => $mode, 'ids' => $ids ];
			}

			/* ── Format for front-end ──────────────────────── */

			public function format_value( $value, $post_id, array $field ) {
				if ( ! is_array( $value ) ) {
					return [];
				}

				$mode       = $value['mode'] ?? 'manual';
				$stored_ids = ! empty( $value['ids'] ) ? array_map( 'intval', (array) $value['ids'] ) : [];
				$post_types = ! empty( $field['post_type'] ) ? (array) $field['post_type'] : [ 'any' ];
				$format     = $field['return_format'] ?? 'id';

				$result_ids = [];

				switch ( $mode ) {
					case 'manual':
						$result_ids = $stored_ids;
						break;

					case 'favorites':
						if ( function_exists( 'sp_get_favorite_post_ids' ) ) {
							$result_ids = sp_get_favorite_post_ids( [
								'post_type'      => $post_types,
								'posts_per_page' => -1,
							] );
						} else {
							$q = new WP_Query( [
								'post_type'      => $post_types,
								'post_status'    => 'publish',
								'posts_per_page' => -1,
								'fields'         => 'ids',
								'meta_key'       => '_sp_favorite_post',
								'meta_value'     => '1',
								'orderby'        => [ 'menu_order' => 'ASC', 'date' => 'DESC' ],
							] );
							$result_ids = is_array( $q->posts ) ? array_map( 'intval', $q->posts ) : [];
							wp_reset_postdata();
						}
						break;

					case 'all':
						$q = new WP_Query( [
							'post_type'      => $post_types,
							'post_status'    => 'publish',
							'posts_per_page' => -1,
							'fields'         => 'ids',
							'orderby'        => [ 'menu_order' => 'ASC', 'date' => 'DESC' ],
						] );
						$result_ids = is_array( $q->posts ) ? array_map( 'intval', $q->posts ) : [];
						wp_reset_postdata();
						break;
				}

				$result_ids = array_values( array_unique( array_filter( $result_ids ) ) );

				if ( $format === 'object' && ! empty( $result_ids ) ) {
					$posts = get_posts( [
						'post_type'      => $post_types,
						'post__in'       => $result_ids,
						'posts_per_page' => count( $result_ids ),
						'orderby'        => 'post__in',
						'post_status'    => 'publish',
					] );
					return $posts ?: [];
				}

				return $result_ids;
			}

			/* ── Admin assets ──────────────────────────────── */

			public function input_admin_enqueue_scripts(): void {
				$h = 'sp-smart-rel';

				if ( ! wp_style_is( $h, 'registered' ) ) {
					wp_register_style( $h, false );
					wp_add_inline_style( $h, self::get_css() );
				}
				wp_enqueue_style( $h );

				if ( ! wp_script_is( $h, 'registered' ) ) {
					wp_register_script( $h, false, [ 'jquery' ], null, true );
					wp_add_inline_script( $h, self::get_js() );
				}
				wp_enqueue_script( $h );
			}

			/* ── CSS ───────────────────────────────────────── */

			private static function get_css(): string {
				return <<<'CSS'

/* ─────────────────────────────────────────────
   Smart Relationship — Sharp Modern UI
───────────────────────────────────────────── */

.sp-srel{
    --bg:var(--sp-acf-surface, #fff);
    --bg-soft:var(--sp-acf-surface-soft, #f7f8fc);
    --bg-blue:var(--sp-acf-accent-soft, #eef3ff);
    --border:var(--sp-acf-border, #d0d5dd);
    --border-soft:var(--sp-acf-border, #d0d5dd);
    --text:var(--sp-acf-text, #344054);
    --muted:var(--sp-acf-text-muted, #667085);
    --blue:var(--sp-acf-accent, #3858e9);
    --blue-soft:var(--sp-acf-accent-soft, #eef3ff);
    --red:var(--sp-acf-danger, #cc1818);
    --shadow:var(--sp-acf-shadow, 0 1px 2px rgba(16,24,40,.04));

    font-size:13px;
    color:var(--text);
    container-type:inline-size;
}

/* tabs */

.sp-srel__tabs{
    display:flex;
    gap:2px;
    margin:0 0 14px;
    padding:3px;
    border:1px solid var(--border);
    background:var(--sp-acf-segment-bg, #e9edf5);
    width:max-content;
    max-width:100%;
}

.sp-srel__tab{
    min-height:34px;
    border:0;
    background:transparent;
    color:var(--muted);
    padding:0 14px;
    cursor:pointer;
    transition:background var(--sp-admin-transition), border-color var(--sp-admin-transition), color var(--sp-admin-transition), box-shadow var(--sp-admin-transition);
    font-size:13px;
    font-weight:500;
}

.sp-srel__tab:hover{
    color:var(--sp-acf-accent-hover, #2145e6);
    background:var(--bg-soft);
}

.sp-srel__tab.is-active{
    color:var(--blue);
    background:var(--bg);
    box-shadow:var(--shadow);
}
.sp-srel__tab:focus-visible{
    outline:0;
    box-shadow:var(--sp-acf-focus, 0 0 0 2px rgba(56,88,233,.18));
    position:relative;
    z-index:1;
}

/* picker */

.sp-srel__picker{
    display:grid;
    grid-template-columns:minmax(0,1fr) minmax(0,1fr);
    border:1px solid var(--border);
    background:var(--bg);
    border-radius:0;
    overflow:hidden;
    box-shadow:var(--shadow);

    height:460px;
    min-height:460px;
    max-height:460px;
}

/* columns */

.sp-srel__col{
    display:flex;
    flex-direction:column;
    min-width:0;
    min-height:0;
    overflow:hidden;
}

.sp-srel__col--available{
    border-right:1px solid var(--border);
}

.sp-srel__col--selected{
    background:var(--bg-blue);
}

/* headers */

.sp-srel__col-header{
    display:flex;
    align-items:center;
    min-height:52px;
    flex-shrink:0;
    border-bottom:1px solid var(--border);
    background:var(--bg);

    position:sticky;
    top:0;
    z-index:10;
}

.sp-srel__search{
    flex:1;
    height:52px;
    border:none!important;
    outline:none!important;
    box-shadow:none!important;
    background:transparent;
    padding:0 16px 0 42px;
    font-size:13px;
    color:var(--text);
	text-indent: 30px;
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%238a919b'%3E%3Ccircle cx='11' cy='11' r='7' stroke-width='2'/%3E%3Cpath stroke-width='2' d='m20 20-3.5-3.5'/%3E%3C/svg%3E");
    background-repeat:no-repeat;
    background-position:14px center;
    background-size:16px;
}

.sp-srel__search::placeholder{
    color:var(--sp-acf-text-subtle, #98a2b3);
}

.sp-srel__search:focus{
    background-color:var(--bg);
    box-shadow:inset 0 0 0 1px var(--blue)!important;
}

.sp-srel__tax-filter{
    height:52px!important;
    border:none!important;
    border-left:1px solid var(--border)!important;
    background:transparent!important;
    box-shadow:none!important;
    padding:0 14px!important;
    color:var(--muted)!important;
    max-width:180px;
}
.sp-srel__tax-filter:focus{
    box-shadow:inset 0 0 0 1px var(--blue)!important;
    color:var(--text)!important;
}

.sp-srel__count{
    padding:0 16px;
    font-size:13px;
    font-weight:600;
    color:var(--muted);
}

/* list */

.sp-srel__list{
    flex:1;
    min-height:0;
    overflow:auto;
    overscroll-behavior:contain;
    background:transparent;
}

.sp-srel__list::-webkit-scrollbar{
    width:10px;
}

.sp-srel__list::-webkit-scrollbar-thumb{
    background:var(--sp-acf-border-strong, #b8c1d1);
    border:2px solid transparent;
    background-clip:padding-box;
}

.sp-srel__list::-webkit-scrollbar-thumb:hover{
    background:var(--sp-acf-text-subtle, #98a2b3);
    background-clip:padding-box;
}

/* empty */

.sp-srel__list:empty::after{
    content:attr(data-empty);
    display:flex;
    justify-content:center;
    align-items:center;
    min-height:120px;
    color:var(--sp-acf-text-subtle, #98a2b3);
    font-size:13px;
}

.sp-srel__list--sel:empty::after{
    content:attr(data-empty);
}

/* item */

.sp-srel__item{
    display:flex;
	    align-items:center;
	    min-height:62px;
	    border-bottom:1px solid var(--border-soft);
    transition:background var(--sp-admin-transition), box-shadow var(--sp-admin-transition);
    background:transparent;
    position:relative;
}

.sp-srel__item.is-selected {
    background:var(--sp-admin-accent-soft);
}

.sp-srel__item:last-child{
    border-bottom:none;
}

.sp-srel__item:hover{
    background:var(--bg-soft);
}

.sp-srel__item:focus-within{
    background:var(--bg-soft);
    box-shadow:inset 3px 0 0 var(--blue);
}

.sp-srel__list--sel .sp-srel__item:hover{
    background:var(--bg);
}

.sp-srel__item.is-disabled{
    opacity:.45;
    cursor:not-allowed;
}

.sp-srel__item.is-disabled .sp-srel__add{
    pointer-events:none;
}

.sp-srel__item.is-dragging{
    opacity:.42;
    box-shadow:inset 3px 0 0 var(--blue);
}

.sp-srel__item.is-drag-over{
    background:var(--blue-soft)!important;
    box-shadow:inset 0 2px 0 var(--blue);
}

/* drag */

.sp-srel__drag{
    width:34px;
    display:flex;
    justify-content:center;
    align-items:center;
    flex-shrink:0;
    color:var(--sp-acf-text-subtle, #98a2b3);
    cursor:grab;
}

.sp-srel__drag:hover{
    color:var(--muted);
}

/* thumb */

.sp-srel__thumb{
    width:42px;
    height:42px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow:hidden;
    flex-shrink:0;
    background:var(--bg-soft);
    border:1px solid var(--border);
    border-radius:0;
    margin-left:10px;
}

.sp-srel__thumb img{
    width:100%;
    height:100%;
    object-fit:cover;
    display:block;
}

/* title */

.sp-srel__title{
    flex:1;
    min-width:0;
    padding:0 14px;
    display:flex;
    flex-direction:column;
    justify-content:center;
}

.sp-srel__title-text{
    font-size:13px;
    font-weight:600;
    color:var(--text);
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
}

.sp-srel__title-type{
    margin-top:2px;
    font-size:11px;
    color:var(--muted);
}

/* buttons */

.sp-srel__add,
.sp-srel__remove{
    width:34px;
    height:34px;
    border:1px solid transparent;
    background:transparent;
    cursor:pointer;
    flex-shrink:0;
    margin-right:10px;

    opacity:0;
    transform:translateX(4px);
    border-radius:0;
    transition:background var(--sp-acf-transition, .15s ease), border-color var(--sp-acf-transition, .15s ease), color var(--sp-acf-transition, .15s ease), box-shadow var(--sp-acf-transition, .15s ease), transform var(--sp-acf-transition, .15s ease), opacity var(--sp-acf-transition, .15s ease);

    font-size:18px;
}

.sp-srel__item:hover .sp-srel__add,
.sp-srel__item:hover .sp-srel__remove,
.sp-srel__item:focus-within .sp-srel__add,
.sp-srel__item:focus-within .sp-srel__remove{
    opacity:1;
    transform:none;
}

.sp-srel__add{
    color:var(--blue);
}

.sp-srel__add:hover{
    background:var(--blue);
    border-color:var(--blue);
    color:var(--color-on-accent);
}

.sp-srel__remove{
    color:var(--sp-acf-text-subtle, #98a2b3);
}

.sp-srel__remove:hover{
    background:color-mix(in srgb, var(--red) 10%, var(--bg));
    border-color:var(--red);
    color:var(--red);
}
.sp-srel__add:focus-visible,
.sp-srel__remove:focus-visible{
    opacity:1;
    transform:none;
    outline:0;
    border-color:currentColor;
    box-shadow:var(--sp-acf-focus, 0 0 0 2px rgba(56,88,233,.18));
}

.sp-srel__remove:focus-visible{
    box-shadow:var(--sp-acf-danger-focus, 0 0 0 2px rgba(204,24,24,.18));
}

.sp-srel__add:active,
.sp-srel__remove:active{
    transform:translateY(1px);
}

/* load more */

.sp-srel__load-more{
    width:100%;
    border:none;
    border-top:1px solid var(--border);
    background:var(--bg);
    padding:12px;
    cursor:pointer;
    color:var(--blue);
    font-size:12px;
    font-weight:600;
    transition:background var(--sp-admin-transition), color var(--sp-admin-transition), box-shadow var(--sp-admin-transition);
}

.sp-srel__load-more:hover{
    background:var(--sp-admin-accent-softer);
    color:var(--sp-admin-accent-hover);
}
.sp-srel__load-more:focus-visible{
    outline:0;
    box-shadow:inset 0 0 0 1px var(--blue), var(--sp-acf-focus, 0 0 0 2px rgba(56,88,233,.18));
}

.sp-srel__load-more:disabled{
    background:var(--bg-soft);
    color:var(--sp-acf-text-subtle, #98a2b3);
    cursor:not-allowed;
}

.sp-srel__status{
    display:none;
    min-height:34px;
    margin:0;
    padding:8px 12px;
    border-top:1px solid var(--border);
    background:var(--bg-soft);
    color:var(--muted);
    font-size:12px;
    line-height:1.4;
}

.sp-srel__status:not(:empty){ display:block; }
.sp-srel__status.is-loading{ color:var(--blue); }
.sp-srel__status.is-success{ color:var(--sp-acf-success, #27ae60); }
.sp-srel__status.is-error{
    background:#fff3f2;
    color:var(--sp-acf-error, #e74c3c);
}

/* loading */

.sp-srel__list.is-loading{
    position:relative;
}

.sp-srel__list.is-loading::before{
    content:'';
    position:absolute;
    inset:0;
    background:color-mix(in srgb, var(--bg) 72%, transparent);
    z-index:2;
}

.sp-srel__list.is-loading::after{
    content:'';
    position:absolute;
    top:50%;
    left:50%;
    width:18px;
    height:18px;
    margin:-9px 0 0 -9px;
    border:2px solid var(--sp-acf-border-strong, #b8c1d1);
    border-top-color:var(--blue);
    border-radius:50%;
    animation:spspin .55s linear infinite;
    z-index:3;
}

@keyframes spspin{
    to{transform:rotate(360deg);}
}

/* info panels */

.sp-srel__info{
    display:flex;
    align-items:center;
    gap:18px;
    min-height:140px;
    padding:28px;
    border:1px solid var(--border);
    background:var(--bg);
    border-radius:0;
    box-shadow:var(--shadow);
}

.sp-srel__info-icon{
    width:62px;
    height:62px;
    flex-shrink:0;
    color:var(--blue);
    opacity:.9;
}

.sp-srel__info strong{
    display:block;
    margin-bottom:4px;
    font-size:14px;
    color:var(--text);
}

.sp-srel__info span{
    font-size:13px;
    line-height:1.5;
    color:var(--muted);
}

@container (max-width: 640px){
    .sp-srel__picker{
        grid-template-columns:1fr;
        height:auto;
        min-height:0;
        max-height:none;
    }
    .sp-srel__col{ min-height:280px; }
    .sp-srel__col--available{
        border-right:0;
        border-bottom:1px solid var(--border);
    }
}

@container (max-width: 430px){
    .sp-srel__tabs{
        display:grid;
        grid-template-columns:1fr;
        width:100%;
    }
    .sp-srel__tab{ width:100%; }
    .sp-srel__col-header{ align-items:stretch; flex-direction:column; }
    .sp-srel__search,
    .sp-srel__tax-filter{
        width:100%;
        max-width:none;
        height:42px!important;
        border-left:0!important;
    }
    .sp-srel__tax-filter{ border-top:1px solid var(--border)!important; }
}

@media (prefers-reduced-motion: reduce){
    .sp-srel *,
    .sp-srel *::before,
    .sp-srel *::after{
        animation-duration:.01ms!important;
        animation-iteration-count:1!important;
        transition-duration:.01ms!important;
    }
}

CSS;
			}


			/* ── JS ────────────────────────────────────────── */

			private static function get_js(): string {
				return <<<'JS'
jQuery(function($){

/* ── Tab switching ──────────────────────────────────── */
$(document).on('click', '.sp-srel__tab', function(){
	var $btn=$(this), $root=$btn.closest('.sp-srel'), mode=$btn.data('mode');
	$root.find('.sp-srel__tab').removeClass('is-active').attr('aria-pressed','false');
	$btn.addClass('is-active').attr('aria-pressed','true');
	$root.find('.sp-srel__panel').hide();
	$root.find('[data-panel="'+mode+'"]').show();
	$root.find('.sp-srel__mode-input').val(mode);
});

/* ── Init each widget ──────────────────────────────── */
function initSmartRelationship(root){
	var $root  = $(root);
	if($root.data('spSrelReady')) return;
	$root.data('spSrelReady', true);

	var config = $root.data('config') || {};
	var $avail = $root.find('.sp-srel__list--avail');
	var $sel   = $root.find('.sp-srel__list--sel');
	var $search= $root.find('.sp-srel__search');
	var $tax   = $root.find('.sp-srel__tax-filter');
	var $more  = $root.find('.sp-srel__load-more');
	var $status= $root.find('.sp-srel__status');
	var i18n   = config.i18n || {};
	var page   = 1;
	var hasMore= true;
	var loading= false;
	var timer  = null;
	var xhr    = null;
	var requestId = 0;
	var fieldName = $root.find('.sp-srel__mode-input').attr('name').replace('[mode]','');

	function escapeAttr(value){
		return String(value || '')
			.replace(/&/g,'&amp;').replace(/"/g,'&quot;')
			.replace(/</g,'&lt;').replace(/>/g,'&gt;');
	}

	function setStatus(type, message){
		$status.removeClass('is-loading is-success is-error is-empty');
		if(type) $status.addClass('is-'+type);
		$status.text(message || '');
	}

	function setBusy(isBusy){
		loading=isBusy;
		$root.attr('aria-busy', isBusy ? 'true' : 'false');
		$avail.attr('aria-busy', isBusy ? 'true' : 'false').toggleClass('is-loading', isBusy);
		$more.prop('disabled', isBusy || !hasMore);
	}

	function getSelectedIds(){
		var ids=[];
		$sel.find('.sp-srel__item').each(function(){ ids.push(parseInt($(this).data('id'),10)); });
		return ids;
	}

	function updateCount(){
		$root.find('.sp-srel__count').text(getSelectedIds().length+' '+(i18n.selected || 'selected'));
	}

	function markDisabled(){
		var ids=getSelectedIds();
		$avail.find('.sp-srel__item').each(function(){
			var $i=$(this), id=parseInt($i.data('id'),10);
			var disabled=ids.indexOf(id)!==-1;
			$i.toggleClass('is-disabled', disabled).attr('aria-disabled', disabled ? 'true' : 'false');
			$i.find('.sp-srel__add').prop('disabled', disabled)
				.attr('title', i18n.add || 'Add post').attr('aria-label', i18n.add || 'Add post');
		});
		/* Max check */
		var max=config.max||0;
		if(max>0 && ids.length>=max){
			$avail.find('.sp-srel__item:not(.is-disabled)')
				.addClass('is-disabled').attr('aria-disabled','true')
				.find('.sp-srel__add').prop('disabled',true).attr('title', i18n.max_reached || 'Maximum reached');
		}
	}

	function loadPosts(append){
		var currentRequest=++requestId;
		if(xhr && xhr.readyState!==4) xhr.abort();
		if(!append){ $avail.empty(); page=1; hasMore=true; }
		setBusy(true);
		setStatus('loading', i18n.loading || 'Loading posts…');

		var taxVal=$tax.length?$tax.val():'';
		var taxParts=taxVal?taxVal.split(':'):[];

		xhr=$.post(ajaxurl,{
			action:'sp_srel_search',
			s: $search.val()||'',
			post_type: config.post_type||[],
			taxonomy: taxParts[0]||'',
			term_id: taxParts[1]||'',
			thumb_field: config.thumb_field||'',
			page: page,
			_wpnonce: config.nonce
		}, null, 'json').done(function(r){
			if(currentRequest!==requestId) return;
			if(!r||!r.success){
				setStatus('error', i18n.load_error || 'Could not load posts. Please try again.');
				return;
			}
			var posts=r.data.posts||[];
			hasMore=r.data.has_more||false;
			$more.toggle(hasMore);
			for(var i=0;i<posts.length;i++){
				$avail.append(posts[i].html);
			}
			markDisabled();
			setStatus(posts.length || append ? 'success' : 'empty', posts.length || append ? (i18n.updated || 'Results updated.') : (i18n.empty || 'No posts found'));
		}).fail(function(_request, status){
			if(currentRequest!==requestId || status==='abort') return;
			setStatus('error', i18n.load_error || 'Could not load posts. Please try again.');
		}).always(function(){
			if(currentRequest!==requestId) return;
			setBusy(false);
		});
	}

	/* Initial load */
	if($avail.length) loadPosts(false);

	/* Search */
	$search.on('input', function(){
		clearTimeout(timer);
		timer=setTimeout(function(){ page=1; loadPosts(false); }, 250);
	});

	/* Tax filter */
	$tax.on('change', function(){ page=1; loadPosts(false); });

	/* Load more */
	$more.on('click', function(){ page++; loadPosts(true); });

	/* Add post */
	$(document).on('click', '.sp-srel__add', function(e){
		e.stopPropagation();
		var $item=$(this).closest('.sp-srel__item');
		var $r=$item.closest('.sp-srel');
		if(!$r.is($root)) return;
		var id=$item.data('id');
		var max=config.max||0;
		if(max>0 && getSelectedIds().length>=max) return;

		var $clone=$item.clone();
		/* Transform to selected item */
		$clone.find('.sp-srel__add').remove();
		$clone.prepend('<span class="sp-srel__drag" draggable="true" aria-label="'+escapeAttr(i18n.drag || 'Drag to reorder')+'" title="'+escapeAttr(i18n.drag || 'Drag to reorder')+'"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><circle cx="9" cy="6" r="1.5"/><circle cx="15" cy="6" r="1.5"/><circle cx="9" cy="12" r="1.5"/><circle cx="15" cy="12" r="1.5"/><circle cx="9" cy="18" r="1.5"/><circle cx="15" cy="18" r="1.5"/></svg></span>');
		$clone.append('<input type="hidden" name="'+fieldName+'[ids][]" value="'+id+'">');
		$clone.append('<button type="button" class="sp-srel__remove" aria-label="'+escapeAttr(i18n.remove || 'Remove post')+'" title="'+escapeAttr(i18n.remove || 'Remove post')+'">&times;</button>');
		$clone.addClass('is-selected');
		$sel.append($clone);

		$item.addClass('is-disabled');
		updateCount();
		markDisabled();
	});

	/* Remove post */
	$(document).on('click', '.sp-srel__remove', function(e){
		e.stopPropagation();
		var $item=$(this).closest('.sp-srel__item');
		var $r=$item.closest('.sp-srel');
		if(!$r.is($root)) return;
		var id=$item.data('id');
		$item.remove();
		$avail.find('[data-id="'+id+'"]').removeClass('is-disabled');
		updateCount();
		markDisabled();
	});

	/* ── Drag & Drop sorting ───────────────────────── */
	var $dragItem=null;
	$sel.on('dragstart', '.sp-srel__drag', function(e){
		$dragItem=$(this).closest('.sp-srel__item');
		$dragItem.addClass('is-dragging').attr('aria-grabbed','true');
		e.originalEvent.dataTransfer.effectAllowed='move';
		e.originalEvent.dataTransfer.setData('text/plain', $dragItem.data('id'));
	});
	$sel.on('dragend', '.sp-srel__item', function(){
		$(this).removeClass('is-dragging').attr('aria-grabbed','false');
		$sel.find('.is-drag-over').removeClass('is-drag-over');
		$dragItem=null;
	});
	$sel.on('dragover', '.sp-srel__item', function(e){
		e.preventDefault();
		e.originalEvent.dataTransfer.dropEffect='move';
		$sel.find('.is-drag-over').removeClass('is-drag-over');
		$(this).addClass('is-drag-over');
	});
	$sel.on('drop', '.sp-srel__item', function(e){
		e.preventDefault();
		if(!$dragItem) return;
		var $target=$(this);
		$target.removeClass('is-drag-over');
		if($dragItem.data('id')!==$target.data('id')){
			$dragItem.detach();
			$target.before($dragItem);
		}
		$dragItem.removeClass('is-dragging').attr('aria-grabbed','false');
		$dragItem=null;
	});
	/* Drop on list container */
	$sel.on('dragover', function(e){ e.preventDefault(); });
	$sel.on('drop', function(e){
		if(!$dragItem) return;
		e.preventDefault();
		/* If dropped on container (not on item), append to end */
		if($(e.target).hasClass('sp-srel__list--sel')){
			$dragItem.detach();
			$sel.append($dragItem);
			$dragItem.removeClass('is-dragging').attr('aria-grabbed','false');
			$dragItem=null;
		}
	});
}

function initSmartRelationships(context){
	var $context = $(context || document);
	$context.find('.sp-srel').addBack('.sp-srel').each(function(){
		initSmartRelationship(this);
	});
}

initSmartRelationships(document);

if(window.acf && typeof acf.addAction === 'function'){
	acf.addAction('append', function($el){
		initSmartRelationships($el);
	});
}

});
JS;
			}
		}

		acf_register_field_type( 'acf_field_smart_relationship' );
	} );


	/* ── AJAX: search posts ────────────────────────────── */

	add_action( 'wp_ajax_sp_srel_search', function (): void {
		if ( ! check_ajax_referer( 'sp_srel', '_wpnonce', false ) ) {
			wp_send_json_error( 'Nonce' );
		}

		$search      = sanitize_text_field( $_POST['s'] ?? '' );
		$post_types  = ! empty( $_POST['post_type'] ) && is_array( $_POST['post_type'] )
			? array_map( 'sanitize_key', $_POST['post_type'] )
			: [ 'any' ];
		$taxonomy    = sanitize_key( $_POST['taxonomy'] ?? '' );
		$term_id     = absint( $_POST['term_id'] ?? 0 );
		$page        = max( 1, absint( $_POST['page'] ?? 1 ) );
		$raw_thumb_field = wp_unslash( $_POST['thumb_field'] ?? '' );
		$thumb_field     = is_array( $raw_thumb_field )
			? array_values( array_map( 'sanitize_key', array_filter( $raw_thumb_field, 'is_scalar' ) ) )
			: sanitize_key( (string) $raw_thumb_field );
		$per_page    = 20;

		$args = [
			'post_type'      => $post_types,
			'post_status'    => 'publish',
			'posts_per_page' => $per_page + 1,
			'paged'          => $page,
			'orderby'        => 'title',
			'order'          => 'ASC',
		];

		if ( $search !== '' ) {
			$args['s'] = $search;
		}

		if ( $taxonomy !== '' && $term_id > 0 ) {
			$args['tax_query'] = [ [
				'taxonomy' => $taxonomy,
				'field'    => 'term_id',
				'terms'    => $term_id,
			] ];
		}

		$q     = new WP_Query( $args );
		$posts = [];

		if ( $q->have_posts() ) {
			$count = 0;
			while ( $q->have_posts() && $count < $per_page ) {
				$q->the_post();
				$p = get_post();
				$posts[] = [
					'id'   => $p->ID,
					'html' => acf_field_smart_relationship::render_post_item( $p, '', false, $thumb_field ),
				];
				$count++;
			}
		}
		wp_reset_postdata();

		$has_more = count( $q->posts ) > $per_page;

		wp_send_json_success( [
			'posts'    => $posts,
			'has_more' => $has_more,
		] );
	} );


	/* ── Helper function for ACF Builder ───────────────── */

	if ( ! function_exists( 'smart_relationship' ) ) {
		/**
		 * Helper: creates a FieldsBuilder with a smart_relationship field.
		 *
		 * Usage:
		 *   ->addFields( smart_relationship( 'team_members', [
		 *       'post_type'     => ['team'],
		 *       'return_format' => 'id',
		 *       'modes'         => ['favorites', 'manual', 'all'],
		 *       'thumb_field'   => 'image', // string or ordered array of fallback fields
		 *   ]) )
		 */
		function smart_relationship( string $name, array $args = [] ): StoutLogic\AcfBuilder\FieldsBuilder {
			$builder = new StoutLogic\AcfBuilder\FieldsBuilder( 'sp_srel_' . $name );
			$builder->addField( $name, 'smart_relationship', $args );

			return $builder;
		}
	}
