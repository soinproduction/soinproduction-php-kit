<?php

	add_filter( 'fake_archive_supported_post_types', function () {
		return ARCHIVE_POSTS;
	} );

	add_action( 'admin_menu', function () {
		add_options_page(
			'Post Type Archives',
			'<span style="display: flex;align-items: center;gap: 5px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 32 32">
                  <path fill="currentColor" d="M13 5.2 7.4 3.6a1 1 0 0 0-1.2.8L0 26.6q-.1.8.7 1.2l5.8 1.6q.8 0 1.2-.8l6-22.2a1 1 0 0 0-.7-1.2M7.9 24.9A3 3 0 1 1 2 23.4a3 3 0 0 1 5.8 1.5m1.1-4.2L3.1 19 6.2 7.5 12 9.1zM19 23a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3M11 9.8l-4-1-.2.9 3.9 1zm-.9 2.9.3-1-3.9-1-.2 1zm-4.7 10a1.5 1.5 0 1 0-.8 3 1.5 1.5 0 0 0 .8-3M29.9 9h-4v1h4zm0 4h-4v1h4zm0-2h-4v1h4zm-2 12a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3m3-19h-6a1 1 0 0 0-1 1v23q.1 1 1 1h6q1 0 1-1V5q0-1-1-1m-3 23.5a3 3 0 1 1 0-6 3 3 0 0 1 0 6m3-7.5h-6V8h6zM21 9h-4v1h4zm0 4h-4v1h4zm1-9h-6a1 1 0 0 0-1 1v23q.1 1 1 1h6q1 0 1-1V5q0-1-1-1m-3 23.4a3 3 0 1 1 0-6 3 3 0 0 1 0 6m3-7.4h-6V8h6zm-1-9h-4v1h4zM5.7 13.6l3.8 1 .3-1-3.9-1z"/>
                </svg> CPT Archives
            </span>',
			'manage_options',
			'fake_archives',
			'render_fake_archives_settings_page'
		);
	} );

	function fa_current_lang(): string {
		if ( function_exists( 'icl_object_id' ) ) {
			$cur = apply_filters( 'wpml_current_language', null );
			if ( is_string( $cur ) && $cur !== '' ) {
				return $cur;
			}
		}

		return 'default';
	}

	function get_supported_fake_archive_post_types(): array {
		$list = apply_filters( 'fake_archive_supported_post_types', [] );

		return array_values( array_filter( is_array( $list ) ? $list : [] ) );
	}


	function render_fake_archives_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$lang = fa_current_lang();

		if ( isset( $_POST['fake_archives_nonce'] ) && wp_verify_nonce( $_POST['fake_archives_nonce'], 'save_fake_archives' ) ) {
			$raw = $_POST['fake_archives'] ?? [];
			$san = [];

			if ( is_array( $raw ) ) {
				foreach ( $raw as $pt => $page_id ) {
					$pt      = sanitize_key( (string) $pt );
					$page_id = absint( $page_id );
					if ( $pt !== '' && $page_id > 0 ) {
						$san[ $pt ] = $page_id;
					}
				}
			}

			update_option( 'custom_fake_archives_' . $lang, $san );
			echo '<div class="updated"><p>Saved successfully.</p></div>';
		}

		$fake_archives = get_option( 'custom_fake_archives_' . $lang, [] );
		$fake_archives = is_array( $fake_archives ) ? $fake_archives : [];

		$supported_types = get_supported_fake_archive_post_types();
		$post_types      = get_post_types( [], 'objects' );
		$pages           = get_pages();
		?>
        <div class="wrap sp-cpt-archives sp-admin-page">
			<header class="sp-admin-header">
				<div class="sp-admin-header__identity">
					<span class="sp-admin-header__icon dashicons dashicons-archive" aria-hidden="true"></span>
					<div class="sp-admin-header__copy">
						<h1>CPT Archive Pages<?= $lang !== 'default' ? ' [' . esc_html( $lang ) . ']' : ''; ?></h1>
						<p>Assign a WordPress page to each custom post type archive.</p>
					</div>
				</div>
				<div class="sp-admin-header__actions">
					<button type="submit" class="button button-primary" form="sp-cpt-archives-settings">Save changes</button>
				</div>
			</header>
            <form id="sp-cpt-archives-settings" method="post" class="sp-admin-card">
				<?php wp_nonce_field( 'save_fake_archives', 'fake_archives_nonce' ); ?>
				<div class="sp-admin-card__header"><h2>Archive assignments</h2></div>
                <table class="form-table">
					<?php foreach ( $supported_types as $pt_name ) :
						if ( ! isset( $post_types[ $pt_name ] ) ) {
							continue;
						}
						$pt = $post_types[ $pt_name ]; ?>
                        <tr>
                            <th scope="row"><?= esc_html( $pt->label ); ?></th>
                            <td>
                                <select name="fake_archives[<?= esc_attr( $pt_name ); ?>]">
                                    <option value="">— Not selected —</option>
									<?php foreach ( $pages as $page ) : ?>
                                        <option value="<?= esc_attr( $page->ID ); ?>"
											<?= selected( (int) ( $fake_archives[ $pt_name ] ?? 0 ), (int) $page->ID, false ); ?>>
											<?= esc_html( $page->post_title ); ?>
                                        </option>
									<?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
					<?php endforeach; ?>
                </table>
            </form>
        </div>
		<?php
	}


	function get_fake_archive_page( $post_type ): ?WP_Post {
		$post_type = sanitize_key( (string) $post_type );
		if ( $post_type === '' ) {
			return null;
		}

		$lang     = fa_current_lang();
		$map_lang = get_option( 'custom_fake_archives_' . $lang, [] );
		$map_lang = is_array( $map_lang ) ? $map_lang : [];

		$page_id = (int) ( $map_lang[ $post_type ] ?? 0 );


		if ( $page_id <= 0 ) {
			$map_def = get_option( 'custom_fake_archives_default', [] );
			$map_def = is_array( $map_def ) ? $map_def : [];
			$page_id = (int) ( $map_def[ $post_type ] ?? 0 );
		}

		if ( $page_id <= 0 ) {
			return null;
		}

		$post = get_post( $page_id );

		return ( $post instanceof WP_Post && $post->post_status !== 'trash' ) ? $post : null;
	}


	function prevent_deletion_of_fake_archive_pages( $post_id ) {
		if ( get_post_type( $post_id ) !== 'page' ) {
			return;
		}

		$lang = 'default';
		if ( function_exists( 'icl_object_id' ) ) {
			$det = apply_filters( 'wpml_post_language_details', null, $post_id );
			if ( is_array( $det ) && ! empty( $det['language_code'] ) ) {
				$lang = (string) $det['language_code'];
			}
		}

		$archives = get_option( 'custom_fake_archives_' . $lang, [] );
		$archives = is_array( $archives ) ? array_map( 'intval', $archives ) : [];

		if ( in_array( (int) $post_id, $archives, true ) ) {
			wp_die(
				'This page is assigned as an archive for one of the post types. Please remove the assignment in the "CPT Archives" settings first.',
				'Deletion Forbidden',
				[ 'response' => 403, 'back_link' => true ]
			);
		}
	}

	add_action( 'before_delete_post', 'prevent_deletion_of_fake_archive_pages' );
	add_action( 'wp_trash_post', 'prevent_deletion_of_fake_archive_pages' );


	add_filter( 'display_post_states', function ( $post_states, $post ) {
		if ( $post->post_type !== 'page' ) {
			return $post_states;
		}

		$lang = 'default';
		if ( function_exists( 'icl_object_id' ) ) {
			$det = apply_filters( 'wpml_post_language_details', null, $post->ID );
			if ( is_array( $det ) && ! empty( $det['language_code'] ) ) {
				$lang = (string) $det['language_code'];
			}
		}

		$fake_archives = get_option( 'custom_fake_archives_' . $lang, [] );
		if ( ! is_array( $fake_archives ) ) {
			return $post_states;
		}

		$post_types = get_post_types( [], 'objects' );

		$labels = [];
		foreach ( $fake_archives as $cpt => $page_id ) {
			if ( (int) $page_id === (int) $post->ID && isset( $post_types[ $cpt ] ) ) {
				$labels[] = (string) $post_types[ $cpt ]->labels->name;
			}
		}

		if ( $labels ) {
			$post_states['fake_archive_pages'] = 'Archive Page, ' . implode( ', ', $labels );
		}

		return $post_states;
	}, 10, 2 );


	function fa_get_archive_map_for_current_lang(): array {
		$lang = fa_current_lang();
		$map  = get_option( 'custom_fake_archives_' . $lang, [] );
		if ( ! is_array( $map ) ) {
			$map = [];
		}

		if ( $lang !== 'default' ) {
			$def = get_option( 'custom_fake_archives_default', [] );
			if ( is_array( $def ) ) {
				foreach ( $def as $pt => $pid ) {
					if ( ! isset( $map[ $pt ] ) ) {
						$map[ $pt ] = $pid;
					}
				}
			}
		}

		$out = [];
		foreach ( $map as $pt => $pid ) {
			$pt  = sanitize_key( (string) $pt );
			$pid = (int) $pid;
			if ( $pt && $pid > 0 ) {
				$out[ $pt ] = $pid;
			}
		}

		return $out;
	}

	add_filter( 'body_class', function ( array $classes ): array {
		if ( ! is_page() ) {
			return $classes;
		}

		$page_id = (int) get_queried_object_id();
		if ( $page_id <= 0 ) {
			return $classes;
		}

		$archive_post_types = [];

		foreach ( fa_get_archive_map_for_current_lang() as $post_type => $archive_page_id ) {
			if ( (int) $archive_page_id !== $page_id ) {
				continue;
			}

			$post_type = sanitize_key( (string) $post_type );
			if ( $post_type !== '' ) {
				$archive_post_types[] = $post_type;
			}
		}

		$archive_post_types = array_values( array_unique( $archive_post_types ) );
		if ( ! $archive_post_types ) {
			return $classes;
		}

		$classes[] = 'archive';
		$classes[] = 'post-type-archive';
		$classes[] = 'fake-archive';

		foreach ( $archive_post_types as $post_type ) {
			$classes[] = 'post-type-archive-' . $post_type;
			$classes[] = 'fake-archive-' . $post_type;
		}

		return array_values( array_unique( array_filter( $classes ) ) );
	}, 20 );


	add_filter( 'wp_link_query', function ( array $results, array $q ) {
		$archive_map = fa_get_archive_map_for_current_lang();
		if ( ! $archive_map ) {
			return $results;
		}

		$archive_page_ids = array_map( 'intval', array_values( $archive_map ) );

		foreach ( $results as &$r ) {
			$page_id = isset( $r['ID'] ) ? (int) $r['ID'] : 0;
			if ( $page_id <= 0 ) {
				continue;
			}

			$post = get_post( $page_id );
			if ( ! $post || $post->post_type !== 'page' ) {
				continue;
			}

			if ( in_array( $page_id, $archive_page_ids, true ) ) {
				if ( empty( $r['title'] ) ) {
					$r['title'] = get_the_title( $page_id );
				}
				if ( ! empty( $r['info'] ) ) {
					if ( strpos( $r['info'], 'ARCHIVE' ) === false ) {
						$r['info'] = 'ARCHIVE, ' . $r['info'];
					}
				} else {
					$r['info'] = 'ARCHIVE';
				}
			}
		}

		return $results;
	}, 10, 2 );


	function fa_get_single_base_from_fake_archive_if_has_parent( string $post_type ): string {
		$post_type = sanitize_key( $post_type );
		if ( $post_type === '' ) {
			return '';
		}

		$archive_page = get_fake_archive_page( $post_type );
		if ( ! ( $archive_page instanceof WP_Post ) ) {
			return '';
		}

		$archive_id = (int) $archive_page->ID;

		if ( wp_get_post_parent_id( $archive_id ) <= 0 ) {
			return '';
		}

		$uri = get_page_uri( $archive_id );
		$uri = trim( (string) $uri, '/' );

		return $uri;
	}

	function fa_get_archive_base_for_post_type( string $post_type ): string {
		$post_type = sanitize_key( $post_type );
		if ( $post_type === '' ) {
			return '';
		}

		$archive_page = get_fake_archive_page( $post_type );
		if ( ! ( $archive_page instanceof WP_Post ) ) {
			return '';
		}

		return trim( (string) get_page_uri( (int) $archive_page->ID ), '/' );
	}

	add_filter( 'post_type_link', function ( string $permalink, WP_Post $post, bool $leavename ) {

		$pt = (string) $post->post_type;
		if ( $pt === '' ) {
			return $permalink;
		}

		$base = fa_get_single_base_from_fake_archive_if_has_parent( $pt );
		if ( $base === '' ) {
			return $permalink;
		}

		$slug = $leavename ? '%postname%' : $post->post_name;
		$slug = trim( (string) $slug, '/' );
		if ( $slug === '' ) {
			return $permalink;
		}

		return home_url( '/' . user_trailingslashit( $base . '/' . $slug ) );

	}, 20, 3 );

	add_action( 'parse_request', function ( WP $wp ): void {
		if ( is_admin() ) {
			return;
		}

		$post_type = $wp->query_vars['post_type'] ?? '';
		if ( is_array( $post_type ) ) {
			return;
		}

		$post_type = sanitize_key( (string) $post_type );
		if ( $post_type === '' || empty( $wp->query_vars['name'] ) ) {
			return;
		}

		$archive_map = fa_get_archive_map_for_current_lang();
		if ( ! isset( $archive_map[ $post_type ] ) ) {
			return;
		}

		$request_path = trim( (string) $wp->request, '/' );
		if ( $request_path === '' ) {
			return;
		}

		$archive_base = fa_get_archive_base_for_post_type( $post_type );
		if ( $archive_base === '' || $request_path === $archive_base ) {
			return;
		}

		if ( ! str_starts_with( $request_path . '/', $archive_base . '/' ) ) {
			return;
		}

		$page = get_page_by_path( $request_path, OBJECT, 'page' );
		if ( ! ( $page instanceof WP_Post ) ) {
			return;
		}

		$wp->query_vars = [
			'page_id'  => (string) $page->ID,
			'pagename' => $request_path,
		];
	}, 8 );

	add_action( 'init', function () {

		$map = fa_get_archive_map_for_current_lang();
		if ( ! is_array( $map ) || ! $map ) {
			return;
		}

		foreach ( $map as $pt => $page_id ) {
			$pt      = sanitize_key( (string) $pt );
			$page_id = (int) $page_id;

			if ( $pt === '' || $page_id <= 0 ) {
				continue;
			}

			if ( wp_get_post_parent_id( $page_id ) <= 0 ) {
				continue;
			}

			$base = trim( (string) get_page_uri( $page_id ), '/' );
			if ( $base === '' ) {
				continue;
			}

			add_rewrite_rule(
				'^' . preg_quote( $base, '#' ) . '/([^/]+)/?$',
				'index.php?post_type=' . $pt . '&name=$matches[1]',
				'top'
			);
		}

	}, 30 );

	add_action( 'admin_init', function () {
		if ( ! is_admin() ) {
			return;
		}

		if ( empty( $_POST['fake_archives_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_POST['fake_archives_nonce'], 'save_fake_archives' ) ) {
			return;
		}

		flush_rewrite_rules( false );
	}, 30 );
