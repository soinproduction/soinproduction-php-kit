<?php
/**
 * Admin helper for moving posts between constructor-enabled post types.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'sp_post_type_converter_post_type_has_constructor' ) ) {
	function sp_post_type_converter_post_type_has_constructor( string $post_type ): bool {
		$post_type = sanitize_key( $post_type );

		if ( $post_type === '' || ! post_type_exists( $post_type ) || ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_get_fields' ) ) {
			return false;
		}

		$field_groups = acf_get_field_groups( [ 'post_type' => $post_type ] );

		if ( ! is_array( $field_groups ) ) {
			return false;
		}

		foreach ( $field_groups as $group ) {
			$group_key = (string) ( $group['key'] ?? '' );

			if ( $group_key === '' ) {
				continue;
			}

			$fields = acf_get_fields( $group_key );

			if ( ! is_array( $fields ) ) {
				continue;
			}

			foreach ( $fields as $field ) {
				if ( ( $field['type'] ?? '' ) === 'flexible_content' && ( $field['name'] ?? '' ) === 'builder' ) {
					return true;
				}
			}
		}

		return false;
	}
}

if ( ! function_exists( 'sp_post_type_converter_targets' ) ) {
	function sp_post_type_converter_targets( string $source_type ): array {
		$source_type = sanitize_key( $source_type );

		if ( ! sp_post_type_converter_post_type_has_constructor( $source_type ) ) {
			return [];
		}

		$out = [];

		foreach ( get_post_types( [ 'show_ui' => true ], 'objects' ) as $post_type => $object ) {
			$post_type = sanitize_key( $post_type );

			if ( $post_type === '' || $post_type === $source_type || in_array( $post_type, [ 'attachment', 'revision', 'nav_menu_item', 'acf-field-group', 'static' ], true ) ) {
				continue;
			}

			if ( ! $object || empty( $object->show_ui ) ) {
				continue;
			}

			if ( ! sp_post_type_converter_post_type_has_constructor( $post_type ) ) {
				continue;
			}

			$out[ $post_type ] = (string) ( $object->labels->singular_name ?? $object->label ?? $post_type );
		}

		natcasesort( $out );

		$out = apply_filters( 'sp_post_type_converter_targets', $out, $source_type );

		if ( ! is_array( $out ) ) {
			return [];
		}

		return $out;
	}
}

if ( ! function_exists( 'sp_post_type_converter_notice_url' ) ) {
	function sp_post_type_converter_notice_url( string $status, int $count = 1 ): string {
		return add_query_arg(
			[
				'sp_ptc_status' => rawurlencode( $status ),
				'sp_ptc_count'  => max( 1, $count ),
			],
			admin_url( 'edit.php?post_type=page' )
		);
	}
}

if ( ! function_exists( 'sp_post_type_converter_action_url' ) ) {
	function sp_post_type_converter_action_url( int $post_id, string $target_type, string $redirect = '' ): string {
		$args = [
			'action'           => 'sp_convert_post_type',
			'post_id'          => $post_id,
			'target_post_type' => sanitize_key( $target_type ),
		];

		if ( $redirect !== '' ) {
			$args['redirect'] = $redirect;
		}

		return wp_nonce_url(
			add_query_arg( $args, admin_url( 'admin-post.php' ) ),
			'sp_convert_post_type_' . $post_id
		);
	}
}

if ( ! function_exists( 'sp_post_type_converter_convert' ) ) {
	function sp_post_type_converter_convert( int $post_id, string $target_type ) {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return new WP_Error( 'missing_post', __( 'Post not found.', 'ACF Fields' ) );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'forbidden', __( 'You are not allowed to edit this post.', 'ACF Fields' ) );
		}

		$source_type = (string) $post->post_type;
		$targets     = sp_post_type_converter_targets( $source_type );
		$target_type = sanitize_key( $target_type );

		if ( ! isset( $targets[ $target_type ] ) ) {
			return new WP_Error( 'invalid_target', __( 'This post type conversion is not allowed.', 'ACF Fields' ) );
		}

		return wp_update_post(
			[
				'ID'          => $post_id,
				'post_type'   => $target_type,
				'post_parent' => 0,
			],
			true
		);
	}
}

add_action( 'add_meta_boxes', function ( string $post_type ): void {
	if ( sp_post_type_converter_targets( $post_type ) === [] ) {
		return;
	}

	add_meta_box(
		'sp-post-type-converter',
		__( 'Move to post type', 'ACF Fields' ),
		'sp_post_type_converter_metabox',
		$post_type,
		'side',
		'high'
	);
} );

if ( ! function_exists( 'sp_post_type_converter_metabox' ) ) {
	function sp_post_type_converter_metabox( WP_Post $post ): void {
		$targets = sp_post_type_converter_targets( (string) $post->post_type );

		if ( $targets === [] ) {
			return;
		}
		?>
		<p><?php esc_html_e( 'Change this item into another post type while keeping its content, media, custom fields, and ID.', 'ACF Fields' ); ?></p>
		<p><?php esc_html_e( 'Only post types with the builder field are shown here.', 'ACF Fields' ); ?></p>
		<?php foreach ( $targets as $target_type => $label ) : ?>
			<p>
				<a class="button button-secondary" href="<?php echo esc_url( sp_post_type_converter_action_url( $post->ID, $target_type, (string) get_edit_post_link( $post->ID, 'raw' ) ) ); ?>">
					<?php echo esc_html( sprintf( __( 'Move to %s', 'ACF Fields' ), $label ) ); ?>
				</a>
			</p>
		<?php endforeach; ?>
		<?php
	}
}

add_filter( 'page_row_actions', function ( array $actions, WP_Post $post ): array {
	$targets = sp_post_type_converter_targets( (string) $post->post_type );

	if ( $targets === [] || ! current_user_can( 'edit_post', $post->ID ) ) {
		return $actions;
	}

	foreach ( $targets as $target_type => $label ) {
		$url = sp_post_type_converter_action_url( $post->ID, $target_type, admin_url( 'edit.php?post_type=' . $target_type ) );

		$actions[ 'sp_convert_to_' . $target_type ] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $url ),
			esc_html( sprintf( __( 'Move to %s', 'ACF Fields' ), $label ) )
		);
	}

	return $actions;
}, 10, 2 );

add_filter( 'post_row_actions', function ( array $actions, WP_Post $post ): array {
	$targets = sp_post_type_converter_targets( (string) $post->post_type );

	if ( $targets === [] || ! current_user_can( 'edit_post', $post->ID ) ) {
		return $actions;
	}

	foreach ( $targets as $target_type => $label ) {
		$url = sp_post_type_converter_action_url( $post->ID, $target_type, admin_url( 'edit.php?post_type=' . $target_type ) );

		$actions[ 'sp_convert_to_' . $target_type ] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $url ),
			esc_html( sprintf( __( 'Move to %s', 'ACF Fields' ), $label ) )
		);
	}

	return $actions;
}, 10, 2 );

add_action( 'admin_init', function (): void {
	foreach ( get_post_types( [ 'show_ui' => true ], 'names' ) as $source_type ) {
		$source_type = sanitize_key( (string) $source_type );

		if ( $source_type === '' ) {
			continue;
		}

		add_filter( 'bulk_actions-edit-' . $source_type, 'sp_post_type_converter_register_bulk_actions' );
		add_filter( 'handle_bulk_actions-edit-' . $source_type, 'sp_post_type_converter_handle_bulk_action', 10, 3 );
	}
} );

if ( ! function_exists( 'sp_post_type_converter_register_bulk_actions' ) ) {
	function sp_post_type_converter_register_bulk_actions( array $actions ): array {
		$screen      = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$source_type = $screen && ! empty( $screen->post_type ) ? sanitize_key( (string) $screen->post_type ) : '';
		$targets     = $source_type !== '' ? sp_post_type_converter_targets( $source_type ) : [];

		foreach ( $targets as $target_type => $label ) {
			$actions[ 'sp_convert_to_' . $target_type ] = sprintf( __( 'Move to %s', 'ACF Fields' ), $label );
		}

		return $actions;
	}
}

if ( ! function_exists( 'sp_post_type_converter_handle_bulk_action' ) ) {
	function sp_post_type_converter_handle_bulk_action( string $redirect_to, string $doaction, array $post_ids ): string {
		if ( ! str_starts_with( $doaction, 'sp_convert_to_' ) ) {
			return $redirect_to;
		}

		$target_type = sanitize_key( substr( $doaction, strlen( 'sp_convert_to_' ) ) );

		if ( $target_type === '' ) {
			return $redirect_to;
		}

		$count = 0;

		foreach ( $post_ids as $post_id ) {
			$result = sp_post_type_converter_convert( (int) $post_id, $target_type );

			if ( ! is_wp_error( $result ) ) {
				$count++;
			}
		}

		return add_query_arg(
			[
				'sp_ptc_status' => $count > 0 ? 'converted' : 'failed',
				'sp_ptc_count'  => max( 1, $count ),
				'sp_ptc_target' => rawurlencode( $target_type ),
			],
			$redirect_to
		);
	}
}

if ( ! function_exists( 'sp_post_type_converter_handle_request' ) ) {
	function sp_post_type_converter_handle_request(): void {
		$post_id     = isset( $_REQUEST['post_id'] ) ? absint( $_REQUEST['post_id'] ) : 0;
		$target_type = isset( $_REQUEST['target_post_type'] ) ? sanitize_key( (string) wp_unslash( $_REQUEST['target_post_type'] ) ) : '';
		$nonce       = isset( $_REQUEST['_wpnonce'] ) ? (string) wp_unslash( $_REQUEST['_wpnonce'] ) : '';

		if ( $post_id <= 0 || ! wp_verify_nonce( $nonce, 'sp_convert_post_type_' . $post_id ) ) {
			wp_safe_redirect( sp_post_type_converter_notice_url( 'failed' ) );
			exit;
		}

		$result = sp_post_type_converter_convert( $post_id, $target_type );

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( sp_post_type_converter_notice_url( 'failed' ) );
			exit;
		}

		$redirect = isset( $_REQUEST['redirect'] ) ? esc_url_raw( (string) wp_unslash( $_REQUEST['redirect'] ) ) : '';

		if ( $redirect === '' ) {
			$redirect = get_edit_post_link( $post_id, 'raw' );
		}

		wp_safe_redirect(
			add_query_arg(
				[
					'sp_ptc_status' => 'converted',
					'sp_ptc_target' => rawurlencode( $target_type ),
				],
				$redirect ?: admin_url( 'edit.php?post_type=' . $target_type )
			)
		);
		exit;
	}
}

add_action( 'admin_post_sp_convert_post_type', 'sp_post_type_converter_handle_request' );
add_action( 'admin_action_sp_convert_post_type', 'sp_post_type_converter_handle_request' );

add_action( 'admin_notices', function (): void {
	$status = isset( $_GET['sp_ptc_status'] ) ? sanitize_key( (string) wp_unslash( $_GET['sp_ptc_status'] ) ) : '';

	if ( $status === '' ) {
		return;
	}

	$count       = isset( $_GET['sp_ptc_count'] ) ? max( 1, absint( $_GET['sp_ptc_count'] ) ) : 1;
	$target_type = isset( $_GET['sp_ptc_target'] ) ? sanitize_key( (string) wp_unslash( $_GET['sp_ptc_target'] ) ) : '';
	$target      = $target_type !== '' ? get_post_type_object( $target_type ) : null;
	$target_name = $target ? (string) ( $target->labels->name ?? $target->label ?? $target_type ) : __( 'selected post type', 'ACF Fields' );

	if ( $status === 'converted' ) {
		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html( sprintf( _n( '%1$d item moved to %2$s.', '%1$d items moved to %2$s.', $count, 'ACF Fields' ), $count, $target_name ) )
		);
		return;
	}

	printf(
		'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
		esc_html__( 'Could not move the selected item.', 'ACF Fields' )
	);
} );
