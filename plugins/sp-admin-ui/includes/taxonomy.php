<?php
	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	if ( ! function_exists( 'sp_taxonomy_get_metabox_taxonomy' ) ) {
		function sp_taxonomy_get_metabox_taxonomy( array $box ): string {
			$taxonomy = '';

			if ( ! empty( $box['args']['taxonomy'] ) ) {
				$taxonomy = sanitize_key( (string) $box['args']['taxonomy'] );
			} elseif ( ! empty( $box['id'] ) && str_starts_with( (string) $box['id'], 'taxonomy-' ) ) {
				$taxonomy = sanitize_key( substr( (string) $box['id'], strlen( 'taxonomy-' ) ) );
			}

			return $taxonomy !== '' && taxonomy_exists( $taxonomy ) ? $taxonomy : '';
		}
	}

	if ( ! function_exists( 'sp_taxonomy_is_valid_save_context' ) ) {
		function sp_taxonomy_is_valid_save_context( int $post_id, $post = null ): bool {
			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
				return false;
			}
			if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
				return false;
			}

			return $post instanceof WP_Post && current_user_can( 'edit_post', $post_id );
		}
	}

	/** Keep hierarchy expansion opt-in for projects that store ancestor terms. */
	function sp_taxonomy_include_ancestors(string $taxonomy, int $post_id): bool {
		return is_taxonomy_hierarchical($taxonomy)
			&& (bool) apply_filters('sp_taxonomy_include_ancestors', false, $taxonomy, $post_id);
	}

	function sp_taxonomy_expand_ancestors(array $ids, string $taxonomy): array {
		$ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn($id) => $id > 0)));
		$expanded = $ids;
		foreach ($ids as $id) {
			$expanded = array_merge($expanded, get_ancestors($id, $taxonomy, 'taxonomy'));
		}
		return array_values(array_unique(array_filter(array_map('intval', $expanded), static fn($id) => $id > 0)));
	}

	if (!function_exists('sp_taxonomy_add_selected_parent_terms')) {
	function sp_taxonomy_add_selected_parent_terms($post_id, $post = null): void {
		$post = $post instanceof WP_Post ? $post : get_post($post_id);
		if (!sp_taxonomy_is_valid_save_context((int) $post_id, $post)) return;
		foreach (get_object_taxonomies($post->post_type, 'objects') as $taxonomy) {
			if (!sp_taxonomy_include_ancestors($taxonomy->name, (int) $post_id)) continue;
			$ids = wp_get_object_terms($post_id, $taxonomy->name, ['fields' => 'ids']);
			if (is_wp_error($ids) || !$ids) continue;
			$expanded = sp_taxonomy_expand_ancestors($ids, $taxonomy->name);
			if (array_diff($expanded, array_map('intval', $ids))) {
				wp_set_object_terms($post_id, $expanded, $taxonomy->name);
			}
		}
	}
	}
	add_action('save_post', 'sp_taxonomy_add_selected_parent_terms', 20, 2);
