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
