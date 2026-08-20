<?php
	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	const SP_REMOVE_SLUG_POST_TYPES_OPTION = 'sp_remove_slug_post_types';
	const SP_REMOVE_SLUG_TAXONOMIES_OPTION = 'sp_remove_slug_taxonomies';

	function sp_get_remove_slug_post_types(): array {
		$post_types = get_option( SP_REMOVE_SLUG_POST_TYPES_OPTION, [] );

		if ( ! is_array( $post_types ) ) {
			return [];
		}

		$post_types = array_map( 'sanitize_key', $post_types );

		return array_values( array_unique( array_filter( $post_types ) ) );
	}

	function sp_get_post_type_slug_bases( string $post_type ): array {
		$bases = [ $post_type ];

		$post_type_object = get_post_type_object( $post_type );
		if ( $post_type_object && ! empty( $post_type_object->rewrite['slug'] ) ) {
			$bases[] = (string) $post_type_object->rewrite['slug'];
		}

		if ( function_exists( 'fa_get_single_base_from_fake_archive_if_has_parent' ) ) {
			$bases[] = (string) fa_get_single_base_from_fake_archive_if_has_parent( $post_type );
		}

		$bases = array_map( static function ( $base ): string {
			return trim( (string) $base, '/' );
		}, $bases );
		$bases = array_values( array_unique( array_filter( $bases ) ) );

		usort( $bases, static function ( string $a, string $b ): int {
			return strlen( $b ) <=> strlen( $a );
		} );

		return $bases;
	}

	function sp_remove_post_type_slug_from_link( string $post_link, WP_Post $post ): string {
		$post_types = sp_get_remove_slug_post_types();

		if ( ! empty( $post_types ) && in_array( $post->post_type, $post_types, true ) ) {
			foreach ( sp_get_post_type_slug_bases( $post->post_type ) as $base ) {
				$search = '/' . $base . '/';

				if ( strpos( $post_link, $search ) !== false ) {
					$post_link = str_replace( $search, '/', $post_link );
					break;
				}
			}
		}

		return $post_link;
	}
	add_filter( 'post_type_link', 'sp_remove_post_type_slug_from_link', 999, 2 );

	function sp_fix_main_query_for_removed_post_type_slugs( WP_Query $query ): void {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		$requested_slug = '';
		$requested_language = '';

		if ( ! empty( $query->query['name'] ) ) {
			$requested_slug = (string) $query->query['name'];
		} elseif ( ! empty( $query->query['pagename'] ) ) {
			$requested_path = trim( (string) $query->query['pagename'], '/' );

			if ( $requested_path !== '' && get_page_by_path( $requested_path, OBJECT, 'page' ) ) {
				return;
			}

			if ( str_contains( $requested_path, '/' ) ) {
				$path_parts = array_values( array_filter( explode( '/', $requested_path ) ) );
				$languages  = function_exists( 'pll_languages_list' )
					? array_map( 'sanitize_key', (array) pll_languages_list( [ 'fields' => 'slug' ] ) )
					: [];

				if ( count( $path_parts ) !== 2 || ! in_array( sanitize_key( $path_parts[0] ), $languages, true ) ) {
					return;
				}

				$requested_language = sanitize_key( $path_parts[0] );
				$requested_path     = $path_parts[1];
			}

			$requested_slug = basename( $requested_path );
		}

		if ( $requested_slug === '' ) {
			return;
		}

		$current_post_type = $query->get( 'post_type' );
		if ( ! empty( $current_post_type ) ) {
			$pts = (array) $current_post_type;
			$diff = array_diff( $pts, [ 'post', 'page', 'any' ] );
			if ( ! empty( $diff ) ) {
				return;
			}
		}

		$post_types = sp_get_remove_slug_post_types();
		if ( empty( $post_types ) ) {
			return;
		}

		$post = get_page_by_path( $requested_slug, OBJECT, $post_types );
		if ( ! ( $post instanceof WP_Post ) ) {
			return;
		}

		if ( $requested_language !== '' && function_exists( 'pll_get_post_language' ) ) {
			if ( pll_get_post_language( $post->ID, 'slug' ) !== $requested_language ) {
				return;
			}

			$query->set( 'lang', $requested_language );
		}

		$query->set( 'post_type', $post->post_type );
		$query->set( 'name', $post->post_name );
		$query->set( 'pagename', '' );
		$query->set( 'page_id', '' );

		$query->is_page     = false;
		$query->is_single   = true;
		$query->is_singular = true;
	}
	add_action( 'pre_get_posts', 'sp_fix_main_query_for_removed_post_type_slugs' );


	function sp_remove_taxonomy_slug_from_link( string $termlink, WP_Term $term, string $taxonomy ): string {
		$taxonomies = get_option( SP_REMOVE_SLUG_TAXONOMIES_OPTION, [] );
		if ( empty( $taxonomies ) || ! in_array( $taxonomy, $taxonomies, true ) ) {
			return $termlink;
		}

		$tax_obj = get_taxonomy( $taxonomy );

		$rewrite_slug = $tax_obj && ! empty( $tax_obj->rewrite['slug'] )
			? trim( $tax_obj->rewrite['slug'], '/' )
			: $taxonomy;

		$search  = '/' . $rewrite_slug . '/';
		$replace = '/';

		if ( strpos( $termlink, $search ) !== false ) {
			$termlink = str_replace( $search, $replace, $termlink );
		}

		return $termlink;
	}
	add_filter( 'term_link', 'sp_remove_taxonomy_slug_from_link', 10, 3 );

	function sp_parse_request_for_removed_taxonomy_slugs( WP $wp ): void {
		if ( is_admin() ) {
			return;
		}

		$taxonomies = get_option( SP_REMOVE_SLUG_TAXONOMIES_OPTION, [] );
		if ( empty( $taxonomies ) ) {
			return;
		}

		$requested_slug = '';

		if ( ! empty( $wp->query_vars['pagename'] ) ) {
			$requested_slug = $wp->query_vars['pagename'];
		} elseif ( ! empty( $wp->query_vars['name'] ) ) {
			$requested_slug = $wp->query_vars['name'];
		}

		if ( $requested_slug === '' ) {
			return;
		}


		foreach ( $taxonomies as $tax ) {
			$tax_obj = get_taxonomy( $tax );
			if ( ! $tax_obj ) {
				continue;
			}

			$term = get_term_by( 'slug', $requested_slug, $tax );
			if ( $term && ! is_wp_error( $term ) ) {
				$wp->query_vars = [
					'taxonomy' => $tax,
					'term'     => $requested_slug,
				];
				return;
			}
		}
	}
	add_action( 'parse_request', 'sp_parse_request_for_removed_taxonomy_slugs', 9 );

	add_action( 'admin_init', function () {

		add_settings_section(
			'sp_remove_slug_section',
			__( 'Remove slugs from URLs', 'textdomain' ),
			null,
			'reading'
		);

		add_settings_field( SP_REMOVE_SLUG_POST_TYPES_OPTION, __( 'Post types without slug', 'textdomain' ),
			function () {
				$all_post_types = get_post_types(
					[
						'public'  => true,
						'show_ui' => true,
					],
					'objects'
				);
				$all_post_types = array_filter( $all_post_types, function ( $pt ) {
					return $pt->publicly_queryable && ! in_array( $pt->name, [ 'page', 'attachment' ], true );
				} );
				$selected = get_option( SP_REMOVE_SLUG_POST_TYPES_OPTION, [] );

				foreach ( $all_post_types as $pt ) {
					$name    = $pt->name;
					$checked = in_array( $name, $selected, true ) ? 'checked' : '';
					printf(
						'<label><input type="checkbox" name="%1$s[]" value="%2$s" %3$s> %4$s</label><br>',
						esc_attr( SP_REMOVE_SLUG_POST_TYPES_OPTION ),
						esc_attr( $name ),
						$checked,
						esc_html( $pt->labels->name )
					);
				}

				echo '<p class="description">' . esc_html__( 'Selected post types will have their slug removed from single URLs.', 'textdomain' ) . '</p>';
			},
			'reading',
			'sp_remove_slug_section'
		);

		register_setting(
			'reading',
			SP_REMOVE_SLUG_POST_TYPES_OPTION,
			[
				'type'              => 'array',
				'sanitize_callback' => function ( $input ) {
					return array_map( 'sanitize_text_field', (array) $input );
				},
				'default'           => [],
			]
		);

		add_settings_field( SP_REMOVE_SLUG_TAXONOMIES_OPTION, __( 'Taxonomies without slug', 'textdomain' ),
			function () {
				$all_taxes = get_taxonomies(
					[
						'public'  => true,
						'show_ui' => true,
					],
					'objects'
				);
				$all_taxes = array_filter( $all_taxes, function ( $tax ) {
					return ! empty( $tax->publicly_queryable ) && ! empty( $tax->rewrite );
				} );
				$selected = get_option( SP_REMOVE_SLUG_TAXONOMIES_OPTION, [] );

				foreach ( $all_taxes as $tax ) {
					$name    = $tax->name;
					$checked = in_array( $name, $selected, true ) ? 'checked' : '';
					printf(
						'<label><input type="checkbox" name="%1$s[]" value="%2$s" %3$s> %4$s</label><br>',
						esc_attr( SP_REMOVE_SLUG_TAXONOMIES_OPTION ),
						esc_attr( $name ),
						$checked,
						esc_html( $tax->labels->name )
					);
				}

				echo '<p class="description">' . esc_html__( 'Selected taxonomies will have their slug removed from term URLs (will use taxonomy rewrite slug).', 'textdomain' ) . '</p>';
			},
			'reading',
			'sp_remove_slug_section'
		);

		register_setting( 'reading', SP_REMOVE_SLUG_TAXONOMIES_OPTION,
			[
				'type'              => 'array',
				'sanitize_callback' => function ( $input ) {
					return array_map( 'sanitize_text_field', (array) $input );
				},
				'default'           => [],
			]
		);
	} );

	add_action( 'update_option_' . SP_REMOVE_SLUG_POST_TYPES_OPTION, function ( $old, $new ) {
		if ( $old !== $new ) {
			flush_rewrite_rules();
		}
	}, 10, 2 );

	add_action( 'update_option_' . SP_REMOVE_SLUG_TAXONOMIES_OPTION, function ( $old, $new ) {
		if ( $old !== $new ) {
			flush_rewrite_rules();
		}
	}, 10, 2 );
