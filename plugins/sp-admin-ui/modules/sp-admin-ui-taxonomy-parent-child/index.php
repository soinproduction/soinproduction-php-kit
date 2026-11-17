<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

    if ( ! function_exists( 'sp_taxonomy_parent_radio_child_checkboxes_save' ) ) {
        function sp_taxonomy_parent_radio_child_checkboxes_save( $post_id ): void {
            $post = get_post( $post_id );
            if ( ! sp_taxonomy_is_valid_save_context( (int) $post_id, $post ) ) {
                return;
            }
            if ( empty( $_POST['sp_parent_child_tax'] ) || ! is_array( $_POST['sp_parent_child_tax'] ) ) {
                return;
            }

            $posted = wp_unslash( $_POST['sp_parent_child_tax'] );

            foreach ( $posted as $taxonomy => $value ) {
                $taxonomy = sanitize_key( (string) $taxonomy );

                if ( ! is_array( $value ) || ! taxonomy_exists( $taxonomy ) || ! is_object_in_taxonomy( $post->post_type, $taxonomy ) || ! is_taxonomy_hierarchical( $taxonomy ) ) {
                    continue;
                }

                $tax_obj = get_taxonomy( $taxonomy );
                if ( ! current_user_can( $tax_obj->cap->assign_terms ) ) {
                    continue;
                }

                $parent_id = isset( $value['parent'] ) ? (int) $value['parent'] : 0;

                if ( $parent_id <= 0 ) {
                    wp_set_object_terms( $post_id, [], $taxonomy );
                    continue;
                }

                $parent = get_term( $parent_id, $taxonomy );

                if ( ! $parent instanceof WP_Term || (int) $parent->parent !== 0 ) {
                    wp_set_object_terms( $post_id, [], $taxonomy );
                    continue;
                }

                $term_ids = [ $parent_id ];
                $children = isset( $value['children'] ) ? array_map( 'intval', (array) $value['children'] ) : [];

                foreach ( $children as $child_id ) {
                    if ( $child_id <= 0 ) {
                        continue;
                    }

                    $ancestors = array_map( 'intval', get_ancestors( $child_id, $taxonomy, 'taxonomy' ) );

                    if ( in_array( $parent_id, $ancestors, true ) ) {
                        $term_ids[] = $child_id;
                    }
                }

                $term_ids = array_values( array_unique( array_filter( $term_ids ) ) );

                wp_set_object_terms( $post_id, $term_ids, $taxonomy );
            }
        }

        add_action( 'save_post', 'sp_taxonomy_parent_radio_child_checkboxes_save', 10 );
    }

    if ( ! function_exists( 'sp_taxonomy_parent_radio_child_checkboxes' ) ) {
        function sp_taxonomy_parent_radio_child_checkboxes( $post, $box ): void {
            $taxonomy = sp_taxonomy_get_metabox_taxonomy( $box );
            $tax_obj  = get_taxonomy( $taxonomy );

            if ( ! $tax_obj ) {
                return;
            }

            $current_ids = wp_get_object_terms( $post->ID, $taxonomy, [ 'fields' => 'ids' ] );
            $current_ids = is_wp_error( $current_ids ) ? [] : array_map( 'intval', $current_ids );
            $current_parent_id = 0;

            foreach ( $current_ids as $term_id ) {
                $term = get_term( $term_id, $taxonomy );

                if ( $term instanceof WP_Term && (int) $term->parent === 0 ) {
                    $current_parent_id = (int) $term->term_id;
                    break;
                }
            }

            $parents = get_terms( [
                'taxonomy'   => $taxonomy,
                'hide_empty' => false,
                'parent'     => 0,
                'orderby'    => 'name',
                'order'      => 'ASC',
            ] );

            if ( is_wp_error( $parents ) ) {
                $parents = [];
            }

            $render_children = static function ( int $parent_id, int $depth = 0 ) use ( &$render_children, $taxonomy, $current_ids ): void {
                $children = get_terms( [
                    'taxonomy'   => $taxonomy,
                    'hide_empty' => false,
                    'parent'     => $parent_id,
                    'orderby'    => 'name',
                    'order'      => 'ASC',
                ] );

                if ( empty( $children ) || is_wp_error( $children ) ) {
                    return;
                }

                foreach ( $children as $child ) :
                    $child_id = (int) $child->term_id;
                    ?>
                    <label class="sp-parent-child-taxonomy__child" style="display:flex;align-items:center;gap:8px;padding:5px 0 5px <?= esc_attr( (string) ( 24 + $depth * 16 ) ) ?>px;">
                        <input
                            style="margin:0;"
                            type="checkbox"
                            name="sp_parent_child_tax[<?= esc_attr( $taxonomy ) ?>][children][]"
                            value="<?= esc_attr( (string) $child_id ) ?>"
                            <?= checked( in_array( $child_id, $current_ids, true ), true, false ) ?>
                        >
                        <?= esc_html( $child->name ) ?>
                    </label>
                    <?php
                    $render_children( $child_id, $depth + 1 );
                endforeach;
            };
            ?>

            <div class="sp-parent-child-taxonomy" data-taxonomy="<?= esc_attr( $taxonomy ) ?>">
                <label style="display:flex;align-items:center;gap:8px;padding:5px 0;color:#999;">
                    <input style="margin:0;" type="radio"
                           name="sp_parent_child_tax[<?= esc_attr( $taxonomy ) ?>][parent]"
                           value="0"
                           <?= checked( $current_parent_id, 0, false ) ?>>
                    — None —
                </label>

                <?php foreach ( $parents as $parent ) : ?>
                    <?php $parent_id = (int) $parent->term_id; ?>
                    <div class="sp-parent-child-taxonomy__group" data-parent-id="<?= esc_attr( (string) $parent_id ); ?>" style="padding:4px 0;">
                        <label style="display:flex;align-items:center;gap:8px;padding:5px 0;">
                            <input style="margin:0;" type="radio"
                                   name="sp_parent_child_tax[<?= esc_attr( $taxonomy ) ?>][parent]"
                                   value="<?= esc_attr( (string) $parent_id ) ?>"
                                   <?= checked( $current_parent_id, $parent_id, false ) ?>>
                            <strong><?= esc_html( $parent->name ) ?></strong>
                        </label>

                        <div class="sp-parent-child-taxonomy__children">
                            <?php $render_children( $parent_id ); ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if ( current_user_can( $tax_obj->cap->edit_terms ) ) : ?>
                    <a href="<?= esc_url( admin_url( 'edit-tags.php?taxonomy=' . $taxonomy . '&post_type=' . $post->post_type ) ) ?>"
                       style="display:block;margin-top:10px;font-size:12px;">
                        + Add New <?= esc_html( $tax_obj->labels->singular_name ) ?>
                    </a>
                <?php endif; ?>
            </div>

            <script>
                document.addEventListener('change', function (event) {
                    var input = event.target;
                    var wrapper = input.closest('.sp-parent-child-taxonomy[data-taxonomy="<?= esc_js( $taxonomy ) ?>"]');

                    if (!wrapper) {
                        return;
                    }

                    function syncChildren() {
                        var selected = wrapper.querySelector('input[type="radio"][name="sp_parent_child_tax[<?= esc_js( $taxonomy ) ?>][parent]"]:checked');
                        var selectedValue = selected ? selected.value : '0';

                        wrapper.querySelectorAll('.sp-parent-child-taxonomy__group').forEach(function (group) {
                            var active = group.getAttribute('data-parent-id') === selectedValue;

                            group.querySelectorAll('input[type="checkbox"]').forEach(function (checkbox) {
                                checkbox.disabled = !active;

                                if (!active) {
                                    checkbox.checked = false;
                                }
                            });
                        });
                    }

                    if (input.matches('input[type="radio"], input[type="checkbox"]')) {
                        syncChildren();
                    }
                });

                document.querySelectorAll('.sp-parent-child-taxonomy[data-taxonomy="<?= esc_js( $taxonomy ) ?>"]').forEach(function (wrapper) {
                    var event = new Event('change', { bubbles: true });
                    var selected = wrapper.querySelector('input[type="radio"]:checked') || wrapper.querySelector('input[type="radio"]');

                    if (selected) {
                        selected.dispatchEvent(event);
                    }
                });
            </script>

            <?php
        }
    }
