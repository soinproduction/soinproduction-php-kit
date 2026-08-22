<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ================================================================
 * ACF Field Type: Smart Taxonomy
 * ================================================================
 *
 * Usage (ACF Builder):
 *   ->addFields( smart_taxonomy( 'service_areas', [
 *       'label'         => false,
 *       'taxonomy'      => ['city', 'region'], // list of taxonomies
 *       'return_format' => 'id',              // 'id' | 'object'
 *       'modes'         => ['manual', 'all'], // available modes
 *       'default_mode'  => 'manual',
 *       'thumb_field'   => '',                // ACF image field name on term, empty = none
 *       'min'           => 0,
 *       'max'           => 0,
 *   ]) )
 *
 * Returned value:
 *   get_field('service_areas')  →  [12, 34, 56]  or  [WP_Term, ...]
 *
 * ================================================================ */

add_action( 'acf/include_field_types', function (): void {
	if ( ! class_exists( 'acf_field' ) || class_exists( 'acf_field_smart_taxonomy', false ) ) {
		return;
	}

	class acf_field_smart_taxonomy extends acf_field {

		public function initialize(): void {
			$this->name     = 'smart_taxonomy';
			$this->label    = __( 'Smart Taxonomy', 'acf' );
			$this->category = 'relational';
			$this->defaults = [
				'taxonomy'      => [],
				'return_format' => 'id',
				'modes'         => [ 'manual', 'all' ],
				'default_mode'  => 'manual',
				'thumb_field'   => 'none',
				'min'           => 0,
				'max'           => 0,
			];
		}

		/* ── Settings ──────────────────────────────────── */

		public function render_field_settings( array $field ): void {

			acf_render_field_setting( $field, [
				'label'        => __( 'Taxonomy', 'acf' ),
				'type'         => 'select',
				'name'         => 'taxonomy',
				'choices'      => acf_get_taxonomy_labels(),
				'multiple'     => 1,
				'ui'           => 1,
				'allow_null'   => 1,
				'placeholder'  => __( 'All taxonomies', 'acf' ),
			] );

			acf_render_field_setting( $field, [
				'label'   => __( 'Modes', 'acf' ),
				'type'    => 'checkbox',
				'name'    => 'modes',
				'choices' => [
					'manual'    => __( 'Manual', 'acf' ),
					'all'       => __( 'All', 'acf' ),
				],
			] );

			acf_render_field_setting( $field, [
				'label'   => __( 'Default Mode', 'acf' ),
				'type'    => 'select',
				'name'    => 'default_mode',
				'choices' => [
					'manual'    => 'Manual',
					'all'       => 'All',
				],
			] );

			acf_render_field_setting( $field, [
				'label' => __( 'Return Format', 'acf' ),
				'type'  => 'radio',
				'name'  => 'return_format',
				'choices' => [
					'id'     => 'Term ID',
					'object' => 'Term Object',
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
				'instructions' => __( 'ACF image field name on the taxonomy term. Set to "none" or leave empty to disable icons.', 'acf' ),
				'type'         => 'text',
				'name'         => 'thumb_field',
				'placeholder'  => __( 'e.g. icon', 'acf' ),
			] );
		}

		/* ── Render ────────────────────────────────────── */

		public function render_field( array $field ): void {
			$modes        = ! empty( $field['modes'] ) ? (array) $field['modes'] : [ 'manual' ];
			$value        = is_array( $field['value'] ) ? $field['value'] : [];
			$current_mode = $value['mode'] ?? ( $field['default_mode'] ?: $modes[0] );
			$selected_ids = ! empty( $value['ids'] ) ? array_map( 'intval', (array) $value['ids'] ) : [];
			$taxonomies   = ! empty( $field['taxonomy'] ) ? (array) $field['taxonomy'] : get_taxonomies( [ 'public' => true ] );
			$max          = (int) ( $field['max'] ?? 0 );
			$fname        = esc_attr( $field['name'] );

			$mode_labels = [
				'manual'    => __( 'Manual', 'acf' ),
				'all'       => __( 'All', 'acf' ),
			];

			$thumb_field = $field['thumb_field'] ?? '';

			$config = wp_json_encode( [
				'taxonomy'    => array_values( $taxonomies ),
				'max'         => $max,
				'thumb_field' => $thumb_field,
				'nonce'       => wp_create_nonce( 'sp_stax' ),
				'i18n'        => [
					'selected'       => __( 'selected', 'acf' ),
					'loading'        => __( 'Loading terms…', 'acf' ),
					'load_error'     => __( 'Could not load terms. Please try again.', 'acf' ),
					'updated'        => __( 'Results updated.', 'acf' ),
					'empty'          => __( 'No terms found', 'acf' ),
					'selected_empty' => __( 'Select terms from the available list.', 'acf' ),
					'add'            => __( 'Add term', 'acf' ),
					'remove'         => __( 'Remove term', 'acf' ),
					'drag'           => __( 'Drag to reorder', 'acf' ),
					'max_reached'    => __( 'Maximum number of terms selected.', 'acf' ),
				],
			] );

			echo '<div class="sp-stax sp-admin-component sp-acf-component" data-sp-admin-component data-config=\'' . esc_attr( $config ) . '\' aria-busy="false">';

			/* ── Mode input ── */
			echo '<input type="hidden" name="' . $fname . '[mode]" value="' . esc_attr( $current_mode ) . '" class="sp-stax__mode-input">';

			/* ── Tabs ── */
			if ( count( $modes ) > 1 ) {
				echo '<div class="sp-stax__tabs" role="group" aria-label="' . esc_attr__( 'Selection mode', 'acf' ) . '">';
				foreach ( $modes as $mode ) {
					$active = ( $mode === $current_mode ) ? ' is-active' : '';
					$label  = $mode_labels[ $mode ] ?? ucfirst( $mode );
					echo '<button type="button" class="sp-stax__tab' . $active . '" data-mode="' . esc_attr( $mode ) . '" aria-pressed="' . ( $mode === $current_mode ? 'true' : 'false' ) . '">'
						 . esc_html( $label )
						 . '</button>';
				}
				echo '</div>';
			}

			/* ── Manual Panel ── */
			if ( in_array( 'manual', $modes, true ) ) {
				$hidden = ( $current_mode !== 'manual' ) ? ' style="display:none"' : '';
				echo '<div class="sp-stax__panel" data-panel="manual"' . $hidden . '>';
				echo '<div class="sp-stax__picker">';

				/* Available column */
				echo '<div class="sp-stax__col sp-stax__col--available">';
				echo '<div class="sp-stax__col-header">';
				echo '<input type="search" class="sp-stax__search" placeholder="' . esc_attr__( 'Search…', 'acf' ) . '" autocomplete="off">';

				/* Taxonomy filter dropdown (only show if more than 1 taxonomy allowed) */
				if ( count( $taxonomies ) > 1 ) {
					echo '<select class="sp-stax__tax-filter">';
					echo '<option value="">' . esc_html__( 'All taxonomies', 'acf' ) . '</option>';
					foreach ( $taxonomies as $tax ) {
						$tax_obj = get_taxonomy( $tax );
						if ( ! $tax_obj ) {
							continue;
						}
						echo '<option value="' . esc_attr( $tax ) . '">'
							 . esc_html( $tax_obj->labels->singular_name )
							 . '</option>';
					}
					echo '</select>';
				}
				echo '</div>';
				echo '<div class="sp-stax__list sp-stax__list--avail" data-empty="' . esc_attr__( 'No terms found', 'acf' ) . '" role="list" aria-busy="false"></div>';
				echo '<button type="button" class="sp-stax__load-more" style="display:none">' . esc_html__( 'Load more', 'acf' ) . '</button>';
				echo '<p class="sp-stax__status" role="status" aria-live="polite"></p>';
				echo '</div>';

				/* Selected column */
				echo '<div class="sp-stax__col sp-stax__col--selected">';
				echo '<div class="sp-stax__col-header">';
				echo '<span class="sp-stax__count">' . count( $selected_ids ) . ' ' . esc_html__( 'selected', 'acf' ) . '</span>';
				echo '</div>';
				echo '<div class="sp-stax__list sp-stax__list--sel" data-empty="' . esc_attr__( 'Select terms from the available list.', 'acf' ) . '" role="list" aria-live="polite">';

				/* Pre-render selected terms */
				if ( $selected_ids ) {
					$terms_map = [];
					$terms_q   = get_terms( [
						'taxonomy'   => $taxonomies,
						'include'    => $selected_ids,
						'hide_empty' => false,
					] );
					if ( ! is_wp_error( $terms_q ) && ! empty( $terms_q ) ) {
						foreach ( $terms_q as $t ) {
							$terms_map[ $t->term_id ] = $t;
						}
					}

					foreach ( $selected_ids as $tid ) {
						if ( ! isset( $terms_map[ $tid ] ) ) {
							continue;
						}
						$t = $terms_map[ $tid ];
						echo self::render_term_item( $t, $fname, true, $thumb_field );
					}
				}

				echo '</div>';
				echo '</div>';

				echo '</div>'; /* picker */
				echo '</div>'; /* panel */
			}

			/* ── All Panel ── */
			if ( in_array( 'all', $modes, true ) ) {
				$hidden = ( $current_mode !== 'all' ) ? ' style="display:none"' : '';
				echo '<div class="sp-stax__panel" data-panel="all"' . $hidden . '>';
				echo '<div class="sp-stax__info">';
				echo '<svg class="sp-stax__info-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>';
				echo '<div>';
				echo '<strong>' . esc_html__( 'All Terms Mode', 'acf' ) . '</strong><br>';
				echo '<span>' . esc_html__( 'All terms of selected taxonomies will be displayed automatically.', 'acf' ) . '</span>';
				echo '</div>';
				echo '</div>';
				echo '</div>';
			}

			echo '</div>'; /* sp-stax */
		}

		/* ── Render single term item ───────────────────── */

		public static function render_term_item( WP_Term $term, string $field_name, bool $is_selected, string $thumb_field = 'none' ): string {
			$tid        = (int) $term->term_id;
			$hide_thumb = ( $thumb_field === 'none' || $thumb_field === '' );
			$thumb      = '';

			if ( ! $hide_thumb ) {
				if ( function_exists( 'get_field' ) ) {
					$acf_img = get_field( $thumb_field, $term );
					if ( is_array( $acf_img ) && ! empty( $acf_img['sizes']['thumbnail'] ) ) {
						$thumb = $acf_img['sizes']['thumbnail'];
					} elseif ( is_array( $acf_img ) && ! empty( $acf_img['url'] ) ) {
						$thumb = $acf_img['url'];
					} elseif ( is_string( $acf_img ) && $acf_img !== '' ) {
						$thumb = $acf_img;
					}
				}
			}

			$title = $term->name;
			$tax_obj = get_taxonomy( $term->taxonomy );
			$label = $tax_obj ? $tax_obj->labels->singular_name : $term->taxonomy;

			$html = '<div class="sp-stax__item' . ( $is_selected ? ' is-selected' : '' ) . '" data-id="' . $tid . '" role="listitem">';

			/* Drag handle (selected only) */
			if ( $is_selected ) {
				$html .= '<span class="sp-stax__drag" draggable="true" aria-label="' . esc_attr__( 'Drag to reorder', 'acf' ) . '" title="' . esc_attr__( 'Drag to reorder', 'acf' ) . '">'
						  . '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="16" height="16">'
						  . '<circle cx="9" cy="6" r="1.5"/><circle cx="15" cy="6" r="1.5"/>'
						  . '<circle cx="9" cy="12" r="1.5"/><circle cx="15" cy="12" r="1.5"/>'
						  . '<circle cx="9" cy="18" r="1.5"/><circle cx="15" cy="18" r="1.5"/>'
						  . '</svg></span>';
			}

			/* Thumbnail (skip if 'none') */
			if ( ! $hide_thumb ) {
				$html .= '<span class="sp-stax__thumb">';
				if ( $thumb ) {
					$html .= '<img src="' . esc_url( $thumb ) . '" alt="">';
				} else {
					$html .= '<span class="sp-stax__thumb-empty dashicons dashicons-format-image"></span>';
				}
				$html .= '</span>';
			}

			/* Title + taxonomy label */
			$html .= '<span class="sp-stax__title">'
					  . '<span class="sp-stax__title-text">' . esc_html( $title ) . '</span>'
					  . '<span class="sp-stax__title-type">' . esc_html( $label ) . '</span>'
					  . '</span>';

			/* Action button */
			if ( $is_selected ) {
				$html .= '<input type="hidden" name="' . esc_attr( $field_name ) . '[ids][]" value="' . $tid . '">';
				$html .= '<button type="button" class="sp-stax__remove" aria-label="' . esc_attr__( 'Remove term', 'acf' ) . '" title="' . esc_attr__( 'Remove term', 'acf' ) . '">&times;</button>';
			} else {
				$html .= '<button type="button" class="sp-stax__add" aria-label="' . esc_attr__( 'Add term', 'acf' ) . '" title="' . esc_attr__( 'Add term', 'acf' ) . '">+</button>';
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
			$taxonomies = ! empty( $field['taxonomy'] ) ? (array) $field['taxonomy'] : get_taxonomies( [ 'public' => true ] );
			$format     = $field['return_format'] ?? 'id';

			$result_ids = [];

			switch ( $mode ) {
				case 'manual':
					$result_ids = $stored_ids;
					break;

				case 'all':
					$result_ids = get_terms( [
						'taxonomy'   => $taxonomies,
						'hide_empty' => false,
						'fields'     => 'ids',
						'orderby'    => 'name',
						'order'      => 'ASC',
					] );
					if ( is_wp_error( $result_ids ) ) {
						$result_ids = [];
					}
					break;
			}

			$result_ids = array_values( array_unique( array_filter( array_map( 'intval', $result_ids ) ) ) );

			if ( $format === 'object' && ! empty( $result_ids ) ) {
				$terms = [];
				$fetched_terms = get_terms( [
					'taxonomy'   => $taxonomies,
					'include'    => $result_ids,
					'hide_empty' => false,
				] );

				if ( ! is_wp_error( $fetched_terms ) ) {
					$terms_map = [];
					foreach ( $fetched_terms as $t ) {
						$terms_map[ $t->term_id ] = $t;
					}
					foreach ( $result_ids as $tid ) {
						if ( isset( $terms_map[ $tid ] ) ) {
							$terms[] = $terms_map[ $tid ];
						}
					}
				}
				return $terms;
			}

			return $result_ids;
		}

		/* ── Admin assets ──────────────────────────────── */

		public function input_admin_enqueue_scripts(): void {
			$h = 'sp-smart-tax';

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
   Smart Taxonomy — Sharp Modern UI
───────────────────────────────────────────── */

.sp-stax{
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

.sp-stax__tabs{
    display:flex;
    gap:2px;
    margin:0 0 14px;
    padding:3px;
    border:1px solid var(--border);
    background:var(--sp-acf-segment-bg, #e9edf5);
    width:max-content;
    max-width:100%;
}

.sp-stax__tab{
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

.sp-stax__tab:hover{
    color:var(--sp-acf-accent-hover, #2145e6);
    background:var(--bg-soft);
}

.sp-stax__tab.is-active{
    color:var(--blue);
    background:var(--bg);
    box-shadow:var(--shadow);
}
.sp-stax__tab:focus-visible{
    outline:0;
    box-shadow:var(--sp-acf-focus, 0 0 0 2px rgba(56,88,233,.18));
    position:relative;
    z-index:1;
}

/* picker */

.sp-stax__picker{
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

.sp-stax__col{
    display:flex;
    flex-direction:column;
    min-width:0;
    min-height:0;
    overflow:hidden;
}

.sp-stax__col--available{
    border-right:1px solid var(--border);
}

.sp-stax__col--selected{
    background:var(--bg-blue);
}

/* headers */

.sp-stax__col-header{
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

.sp-stax__search{
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

.sp-stax__search::placeholder{
    color:var(--sp-acf-text-subtle, #98a2b3);
}

.sp-stax__search:focus{
    background-color:var(--bg);
    box-shadow:inset 0 0 0 1px var(--blue)!important;
}

.sp-stax__tax-filter{
    height:52px!important;
    border:none!important;
    border-left:1px solid var(--border)!important;
    background:transparent!important;
    box-shadow:none!important;
    padding:0 14px!important;
    color:var(--muted)!important;
    max-width:180px;
}
.sp-stax__tax-filter:focus{
    box-shadow:inset 0 0 0 1px var(--blue)!important;
    color:var(--text)!important;
}

.sp-stax__count{
    padding:0 16px;
    font-size:13px;
    font-weight:600;
    color:var(--muted);
}

/* list */

.sp-stax__list{
    flex:1;
    min-height:0;
    overflow:auto;
    overscroll-behavior:contain;
    background:transparent;
}

.sp-stax__list::-webkit-scrollbar{
    width:10px;
}

.sp-stax__list::-webkit-scrollbar-thumb{
    background:var(--sp-acf-border-strong, #b8c1d1);
    border:2px solid transparent;
    background-clip:padding-box;
}

.sp-stax__list::-webkit-scrollbar-thumb:hover{
    background:var(--sp-acf-text-subtle, #98a2b3);
    background-clip:padding-box;
}

/* empty */

.sp-stax__list:empty::after{
    content:attr(data-empty);
    display:flex;
    justify-content:center;
    align-items:center;
    min-height:120px;
    color:var(--sp-acf-text-subtle, #98a2b3);
    font-size:13px;
}

.sp-stax__list--sel:empty::after{
    content:attr(data-empty);
}

/* item */

.sp-stax__item{
    display:flex;
    align-items:center;
    min-height:62px;
    border-bottom:1px solid var(--border-soft);
    transition:background var(--sp-admin-transition), box-shadow var(--sp-admin-transition);
    background:transparent;
    position:relative;
}

.sp-stax__item.is-selected {
    background:var(--sp-admin-accent-soft);
}

.sp-stax__item:last-child{
    border-bottom:none;
}

.sp-stax__item:hover{
    background:var(--bg-soft);
}

.sp-stax__item:focus-within{
    background:var(--bg-soft);
    box-shadow:inset 3px 0 0 var(--blue);
}

.sp-stax__list--sel .sp-stax__item:hover{
    background:var(--bg);
}

.sp-stax__item.is-disabled{
    opacity:.45;
    cursor:not-allowed;
}

.sp-stax__item.is-disabled .sp-stax__add{ pointer-events:none; }

.sp-stax__item.is-dragging{
    opacity:.42;
    box-shadow:inset 3px 0 0 var(--blue);
}

.sp-stax__item.is-drag-over{
    background:var(--blue-soft)!important;
    box-shadow:inset 0 2px 0 var(--blue);
}

/* drag */

.sp-stax__drag{
    width:34px;
    display:flex;
    justify-content:center;
    align-items:center;
    flex-shrink:0;
    color:var(--sp-acf-text-subtle, #98a2b3);
    cursor:grab;
}

.sp-stax__drag:hover{
    color:var(--muted);
}

/* thumb */

.sp-stax__thumb{
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

.sp-stax__thumb img{
    width:100%;
    height:100%;
    object-fit:cover;
    display:block;
}

/* title */

.sp-stax__title{
    flex:1;
    min-width:0;
    padding:0 14px;
    display:flex;
    flex-direction:column;
    justify-content:center;
}

.sp-stax__title-text{
    font-size:13px;
    font-weight:600;
    color:var(--text);
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
}

.sp-stax__title-type{
    margin-top:2px;
    font-size:11px;
    color:var(--muted);
}

/* buttons */

.sp-stax__add,
.sp-stax__remove{
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

.sp-stax__item:hover .sp-stax__add,
.sp-stax__item:hover .sp-stax__remove,
.sp-stax__item:focus-within .sp-stax__add,
.sp-stax__item:focus-within .sp-stax__remove{
    opacity:1;
    transform:none;
}

.sp-stax__add{
    color:var(--blue);
}

.sp-stax__add:hover{
    background:var(--blue);
    border-color:var(--blue);
    color:var(--color-on-accent);
}

.sp-stax__remove{
    color:var(--sp-acf-text-subtle, #98a2b3);
}

.sp-stax__remove:hover{
    background:color-mix(in srgb, var(--red) 10%, var(--bg));
    border-color:var(--red);
    color:var(--red);
}
.sp-stax__add:focus-visible,
.sp-stax__remove:focus-visible{
    opacity:1;
    transform:none;
    outline:0;
    border-color:currentColor;
    box-shadow:var(--sp-acf-focus, 0 0 0 2px rgba(56,88,233,.18));
}

.sp-stax__remove:focus-visible{
    box-shadow:var(--sp-acf-danger-focus, 0 0 0 2px rgba(204,24,24,.18));
}

.sp-stax__add:active,
.sp-stax__remove:active{ transform:translateY(1px); }

/* load more */

.sp-stax__load-more{
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

.sp-stax__load-more:hover{
    background:var(--sp-admin-accent-softer);
    color:var(--sp-admin-accent-hover);
}
.sp-stax__load-more:focus-visible{
    outline:0;
    box-shadow:inset 0 0 0 1px var(--blue), var(--sp-acf-focus, 0 0 0 2px rgba(56,88,233,.18));
}

.sp-stax__load-more:disabled{
    background:var(--bg-soft);
    color:var(--sp-acf-text-subtle, #98a2b3);
    cursor:not-allowed;
}

.sp-stax__status{
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

.sp-stax__status:not(:empty){ display:block; }
.sp-stax__status.is-loading{ color:var(--blue); }
.sp-stax__status.is-success{ color:var(--sp-acf-success, #27ae60); }
.sp-stax__status.is-error{
    background:#fff3f2;
    color:var(--sp-acf-error, #e74c3c);
}

/* loading */

.sp-stax__list.is-loading{
    position:relative;
}

.sp-stax__list.is-loading::before{
    content:'';
    position:absolute;
    inset:0;
    background:color-mix(in srgb, var(--bg) 72%, transparent);
    z-index:2;
}

.sp-stax__list.is-loading::after{
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
    animation:spstaxspin .55s linear infinite;
    z-index:3;
}

@keyframes spstaxspin{
    to{transform:rotate(360deg);}
}

/* info panels */

.sp-stax__info{
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

.sp-stax__info-icon{
    width:62px;
    height:62px;
    flex-shrink:0;
    color:var(--blue);
    opacity:.9;
}

.sp-stax__info strong{
    display:block;
    margin-bottom:4px;
    font-size:14px;
    color:var(--text);
}

.sp-stax__info span{
    font-size:13px;
    line-height:1.5;
    color:var(--muted);
}

@container (max-width: 640px){
    .sp-stax__picker{
        grid-template-columns:1fr;
        height:auto;
        min-height:0;
        max-height:none;
    }
    .sp-stax__col{ min-height:280px; }
    .sp-stax__col--available{
        border-right:0;
        border-bottom:1px solid var(--border);
    }
}

@container (max-width: 430px){
    .sp-stax__tabs{
        display:grid;
        grid-template-columns:1fr;
        width:100%;
    }
    .sp-stax__tab{ width:100%; }
    .sp-stax__col-header{ align-items:stretch; flex-direction:column; }
    .sp-stax__search,
    .sp-stax__tax-filter{
        width:100%;
        max-width:none;
        height:42px!important;
        border-left:0!important;
    }
    .sp-stax__tax-filter{ border-top:1px solid var(--border)!important; }
}

@media (prefers-reduced-motion: reduce){
    .sp-stax *,
    .sp-stax *::before,
    .sp-stax *::after{
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

var sharedRequests = window.SPAdminRequestCache = window.SPAdminRequestCache || {};
function sharedTermRequest(data){
	var key='stax:'+JSON.stringify(data);
	var now=Date.now();
	var cached=sharedRequests[key];
	if(cached&&cached.data&&cached.expires>now){
		return $.Deferred().resolve(cached.data).promise();
	}
	if(cached&&cached.request){
		return cached.request;
	}
	var request=$.post(ajaxurl,data,null,'json');
	sharedRequests[key]={request:request,expires:0};
	request.done(function(response){
		if(response&&response.success){
			sharedRequests[key]={data:response,expires:Date.now()+60000};
		}else{
			delete sharedRequests[key];
		}
	}).fail(function(){ delete sharedRequests[key]; });
	return request;
}

/* ── Tab switching ──────────────────────────────────── */
$(document).on('click', '.sp-stax__tab', function(){
	var $btn=$(this), $root=$btn.closest('.sp-stax'), mode=$btn.data('mode');
	$root.find('.sp-stax__tab').removeClass('is-active').attr('aria-pressed','false');
	$btn.addClass('is-active').attr('aria-pressed','true');
	$root.find('.sp-stax__panel').hide();
	$root.find('[data-panel="'+mode+'"]').show();
	$root.find('.sp-stax__mode-input').val(mode);
});

/* ── Init each widget ──────────────────────────────── */
function initWidget($root) {
	if(!$root.length||$root.closest('.acf-clone').length) return;
	if ($root.data('sp-stax-initialized')) return;
	$root.data('sp-stax-initialized', true);

	var config = $root.data('config') || {};
	var $avail = $root.find('.sp-stax__list--avail');
	var $sel   = $root.find('.sp-stax__list--sel');
	var $search= $root.find('.sp-stax__search');
	var $tax   = $root.find('.sp-stax__tax-filter');
	var $more  = $root.find('.sp-stax__load-more');
	var $status= $root.find('.sp-stax__status');
	var i18n   = config.i18n || {};
	var page   = 1;
	var hasMore= true;
	var loading= false;
	var timer  = null;
	var xhr    = null;
	var requestId = 0;
	var initialLoaded = false;
	var fieldName = $root.find('.sp-stax__mode-input').attr('name').replace('[mode]','');

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
		$sel.find('.sp-stax__item').each(function(){ ids.push(parseInt($(this).data('id'),10)); });
		return ids;
	}

	function updateCount(){
		$root.find('.sp-stax__count').text(getSelectedIds().length+' '+(i18n.selected || 'selected'));
	}

	function markDisabled(){
		var ids=getSelectedIds();
		$avail.find('.sp-stax__item').each(function(){
			var $i=$(this), id=parseInt($i.data('id'),10);
			var disabled=ids.indexOf(id)!==-1;
			$i.toggleClass('is-disabled', disabled).attr('aria-disabled', disabled ? 'true' : 'false');
			$i.find('.sp-stax__add').prop('disabled', disabled)
				.attr('title', i18n.add || 'Add term').attr('aria-label', i18n.add || 'Add term');
		});
		/* Max check */
		var max=config.max||0;
		if(max>0 && ids.length>=max){
			$avail.find('.sp-stax__item:not(.is-disabled)')
				.addClass('is-disabled').attr('aria-disabled','true')
				.find('.sp-stax__add').prop('disabled',true).attr('title', i18n.max_reached || 'Maximum reached');
		}
	}

	function loadTerms(append){
		var currentRequest=++requestId;
		initialLoaded=true;
		if(!append){ $avail.empty(); page=1; hasMore=true; }
		setBusy(true);
		setStatus('loading', i18n.loading || 'Loading terms…');

		xhr=sharedTermRequest({
			action:'sp_stax_search',
			s: $search.val()||'',
			taxonomy: config.taxonomy||[],
			filter_taxonomy: $tax.length?$tax.val():'',
			thumb_field: config.thumb_field||'',
			page: page,
			_wpnonce: config.nonce
		}).done(function(r){
			if(currentRequest!==requestId) return;
			if(!r||!r.success){
				setStatus('error', i18n.load_error || 'Could not load terms. Please try again.');
				return;
			}
			var terms=r.data.terms||[];
			hasMore=r.data.has_more||false;
			$more.toggle(hasMore);
			for(var i=0;i<terms.length;i++){
				$avail.append(terms[i].html);
			}
			markDisabled();
			setStatus(terms.length || append ? 'success' : 'empty', terms.length || append ? (i18n.updated || 'Results updated.') : (i18n.empty || 'No terms found'));
		}).fail(function(_request, status){
			if(currentRequest!==requestId || status==='abort') return;
			setStatus('error', i18n.load_error || 'Could not load terms. Please try again.');
		}).always(function(){
			if(currentRequest!==requestId) return;
			setBusy(false);
		});
	}

	/* Load only when the real field becomes visible or receives interaction. */
	function ensureInitialLoad(){
		if(initialLoaded||!$avail.length||$root.closest('.acf-clone').length||!$avail.is(':visible')) return;
		loadTerms(false);
	}
	$root.on('focusin.spStaxLazy click.spStaxLazy', function(){
		window.setTimeout(ensureInitialLoad,0);
	});
	if('IntersectionObserver' in window&&!$root.closest('.acf-clone').length){
		var observer=new IntersectionObserver(function(entries){
			if(entries.some(function(entry){return entry.isIntersecting;})){
				ensureInitialLoad();
				if(initialLoaded) observer.disconnect();
			}
		},{rootMargin:'240px'});
		observer.observe($root.get(0));
	}else{
		window.setTimeout(ensureInitialLoad,0);
	}

	/* Search */
	$search.on('input', function(){
		clearTimeout(timer);
		timer=setTimeout(function(){ page=1; loadTerms(false); }, 250);
	});

	/* Tax filter */
	$tax.on('change', function(){ page=1; loadTerms(false); });

	/* Load more */
	$more.on('click', function(){ page++; loadTerms(true); });

	/* Add term */
	$root.on('click', '.sp-stax__add', function(e){
		e.stopPropagation();
		var $item=$(this).closest('.sp-stax__item');
		var id=$item.data('id');
		var max=config.max||0;
		if(max>0 && getSelectedIds().length>=max) return;

		var $clone=$item.clone();
		/* Transform to selected item */
		$clone.find('.sp-stax__add').remove();
		$clone.prepend('<span class="sp-stax__drag" draggable="true" aria-label="'+escapeAttr(i18n.drag || 'Drag to reorder')+'" title="'+escapeAttr(i18n.drag || 'Drag to reorder')+'"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><circle cx="9" cy="6" r="1.5"/><circle cx="15" cy="6" r="1.5"/><circle cx="9" cy="12" r="1.5"/><circle cx="15" cy="12" r="1.5"/><circle cx="9" cy="18" r="1.5"/><circle cx="15" cy="18" r="1.5"/></svg></span>');
		$clone.append('<input type="hidden" name="'+fieldName+'[ids][]" value="'+id+'">');
		$clone.append('<button type="button" class="sp-stax__remove" aria-label="'+escapeAttr(i18n.remove || 'Remove term')+'" title="'+escapeAttr(i18n.remove || 'Remove term')+'">&times;</button>');
		$clone.addClass('is-selected');
		$sel.append($clone);

		$item.addClass('is-disabled');
		updateCount();
		markDisabled();
	});

	/* Remove term */
	$root.on('click', '.sp-stax__remove', function(e){
		e.stopPropagation();
		var $item=$(this).closest('.sp-stax__item');
		var id=$item.data('id');
		$item.remove();
		$avail.find('[data-id="'+id+'"]').removeClass('is-disabled');
		updateCount();
		markDisabled();
	});

	/* ── Drag & Drop sorting ───────────────────────── */
	var $dragItem=null;
	$sel.on('dragstart', '.sp-stax__drag', function(e){
		$dragItem=$(this).closest('.sp-stax__item');
		$dragItem.addClass('is-dragging').attr('aria-grabbed','true');
		e.originalEvent.dataTransfer.effectAllowed='move';
		e.originalEvent.dataTransfer.setData('text/plain', $dragItem.data('id'));
	});
	$sel.on('dragend', '.sp-stax__item', function(){
		$(this).removeClass('is-dragging').attr('aria-grabbed','false');
		$sel.find('.is-drag-over').removeClass('is-drag-over');
		$dragItem=null;
	});
	$sel.on('dragover', '.sp-stax__item', function(e){
		e.preventDefault();
		e.originalEvent.dataTransfer.dropEffect='move';
		$sel.find('.is-drag-over').removeClass('is-drag-over');
		$(this).addClass('is-drag-over');
	});
	$sel.on('drop', '.sp-stax__item', function(e){
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
		if($(e.target).hasClass('sp-stax__list--sel')){
			$dragItem.detach();
			$sel.append($dragItem);
			$dragItem.removeClass('is-dragging').attr('aria-grabbed','false');
			$dragItem=null;
		}
	});
}

/* Run on existing widgets and dynamic ones (e.g. ACF block/repeater) */
$('.sp-stax').not('.acf-clone .sp-stax').each(function(){ initWidget($(this)); });
if (typeof acf !== 'undefined') {
	acf.add_action('ready append', function($el){
		acf.get_fields({type: 'smart_taxonomy'}, $el).each(function(){
			var $widget=$(this).find('.sp-stax');
			if(!$widget.closest('.acf-clone').length) initWidget($widget);
		});
	});
}

});
JS;
		}
	}

	acf_register_field_type( 'acf_field_smart_taxonomy' );
} );


/* ── AJAX: search terms ────────────────────────────── */

add_action( 'wp_ajax_sp_stax_search', function (): void {
	if ( ! check_ajax_referer( 'sp_stax', '_wpnonce', false ) ) {
		wp_send_json_error( 'Nonce' );
	}
	if ( ! is_user_logged_in() ) {
		wp_send_json_error( 'Permission denied', 403 );
	}

	$search      = sanitize_text_field( $_POST['s'] ?? '' );
	$taxonomies  = ! empty( $_POST['taxonomy'] ) && is_array( $_POST['taxonomy'] )
		? array_map( 'sanitize_key', $_POST['taxonomy'] )
		: [];

	if ( empty( $taxonomies ) ) {
		$taxonomies = get_taxonomies( [ 'public' => true ] );
	}
	$taxonomies = array_values( array_filter( $taxonomies, static function ( string $taxonomy ): bool {
		$object = get_taxonomy( $taxonomy );
		return $object && ! empty( $object->cap->assign_terms ) && current_user_can( (string) $object->cap->assign_terms );
	} ) );
	if ( empty( $taxonomies ) ) {
		wp_send_json_error( 'Permission denied', 403 );
	}

	$filter_taxonomy = sanitize_key( $_POST['filter_taxonomy'] ?? '' );
	if ( $filter_taxonomy !== '' && in_array( $filter_taxonomy, $taxonomies, true ) ) {
		$taxonomies = [ $filter_taxonomy ];
	}

	$page        = max( 1, absint( $_POST['page'] ?? 1 ) );
	$thumb_field = sanitize_key( $_POST['thumb_field'] ?? '' );
	$per_page    = 20;
	$offset      = ( $page - 1 ) * $per_page;

	$args = [
		'taxonomy'   => $taxonomies,
		'hide_empty' => false,
		'number'     => $per_page + 1,
		'offset'     => $offset,
		'orderby'    => 'name',
		'order'      => 'ASC',
	];

	if ( $search !== '' ) {
		$args['search'] = $search;
	}

	$terms   = get_terms( $args );
	$results = [];

	if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
		$count = 0;
		foreach ( $terms as $term ) {
			if ( $count >= $per_page ) {
				break;
			}
			$results[] = [
				'id'   => $term->term_id,
				'html' => acf_field_smart_taxonomy::render_term_item( $term, '', false, $thumb_field ),
			];
			$count++;
		}
	}

	$has_more = ! is_wp_error( $terms ) && count( $terms ) > $per_page;

	wp_send_json_success( [
		'terms'    => $results,
		'has_more' => $has_more,
	] );
} );


/* ── Helper function for ACF Builder ───────────────── */

if ( ! function_exists( 'smart_taxonomy' ) ) {
	/**
	 * Helper: creates a FieldsBuilder with a smart_taxonomy field.
	 *
	 * Usage:
	 *   ->addFields( smart_taxonomy( 'service_areas', [
	 *       'taxonomy'      => ['city', 'region'],
	 *       'return_format' => 'id',
	 *       'modes'         => ['manual', 'all'],
	 *       'thumb_field'   => 'icon',
	 *   ]) )
	 */
	function smart_taxonomy( string $name, array $args = [] ): StoutLogic\AcfBuilder\FieldsBuilder {
		$builder = new StoutLogic\AcfBuilder\FieldsBuilder( 'sp_stax_' . $name );
		$builder->addField( $name, 'smart_taxonomy', $args );

		return $builder;
	}
}
