<?php
	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/*
	 * Project configuration:
	 * post_relationships([
	 *     [
	 *         'post_types'     => ['services', 'projects', 'markets'],
	 *         'field_prefix'   => 'linked_',
	 *         'width'          => 50,
	 *         'featured_image' => true, // Set to false to hide thumbnails.
	 *         'group_title'    => 'Relations',
	 *     ],
	 * ]);
	 *
	 * Short equivalent:
	 * post_relationships(['services', 'projects', 'markets']);
	 *
	 * This creates a separate bidirectional relationship field for every pair:
	 * - Services: linked_projects, linked_markets
	 * - Projects: linked_services, linked_markets
	 * - Markets:  linked_services, linked_projects
	 *
	 * Each field is 50% wide, so two fields appear in the same row.
	 *
	 * Generated fields and retrieval examples (return format is an array of IDs):
	 *
	 * // On a Service post:
	 * $projects = get_field('linked_projects', $service_id);
	 * $markets  = get_field('linked_markets', $service_id);
	 *
	 * // On a Project post:
	 * $services = get_field('linked_services', $project_id);
	 * $markets  = get_field('linked_markets', $project_id);
	 *
	 * // On a Market post:
	 * $services = get_field('linked_services', $market_id);
	 * $projects = get_field('linked_projects', $market_id);
	 *
	 * Taxonomy options (post_types/types syntax; keys identify the TARGET type):
	 * post_relationships([[
	 *     'post_types'         => ['leadership', 'news_insights'],
	 *     'field_prefix'       => 'linked_',
	 *     'width'              => 100,
	 *     'featured_image'     => true,
	 *     'group_title'        => 'Related News & Insights',
	 *     'split_by_taxonomy'  => ['news_insights' => 'news_insights_category'],
	 *     'show_taxonomies'    => ['news_insights' => false],
	 *     'show_uncategorized' => ['news_insights' => false],
	 * ]]);
	 *
	 * On Leadership this creates one selection field per enabled category.
	 * The reverse Leadership field on News & Insights remains a regular relationship.
	 * Each category's add/edit screen gets a "Show Relationship field" toggle.
	 * It defaults to on. Turning it off hides the field, preserving existing links.
	 * The toggle is shared by all split configurations using that taxonomy.
	 *
	 * To show category labels in an ordinary, unsplit relationship:
	 * post_relationships([[
	 *     'post_types'      => ['leadership', 'news_insights'],
	 *     'show_taxonomies' => ['news_insights' => ['news_insights_category']],
	 * ]]);
	 * These are alternative configurations; do not register the same pair twice.
	 *
	 * Defaults and behavior:
	 * - split_by_taxonomy: off; one taxonomy slug per target type when enabled.
	 * - show_taxonomies: no labels unless configured or splitting is enabled.
	 *   false for a target hides labels even when split; top-level false hides all.
	 * - show_uncategorized: true per target; false hides "Without category".
	 * - Categories include empty terms, match exactly, and exclude descendants.
	 * - A multi-category post appears in each matching field. Submitted selections
	 *   are merged, so deselect it in all displayed categories to remove the link.
	 * - These options control the editor UI, not the frontend display/query.
	 * - Existing links are projected into the fields without a data migration.
	 *   The canonical linked_* field remains the only relationship storage.
	 *
	 * Reading and updating the canonical relationship still works when split:
	 * $news_ids = get_field('linked_news_insights', $leader_id) ?: [];
	 * update_field('linked_news_insights', $news_ids, $leader_id);
	 * For a first programmatic write, use the canonical ACF field key so ACF can
	 * resolve it before reference metadata exists (same rule as ordinary fields).
	 * See README.en.md / README.ru.md for configuration and verification examples.
	 *
	 * Example rendering:
	 * foreach ((array)get_field('linked_projects', get_the_ID()) as $project_id) {
	 *     echo esc_html(get_the_title($project_id));
	 * }
	 */

	if ( ! function_exists( 'post_relationships' ) ) {
		function post_relationships( array $defs ): void {
			$pairs  = [];
			$groups = [];

			// Short syntax: post_relationships(['services', 'projects', 'markets']).
			if (
				count( $defs ) >= 2
				&& array_keys( $defs ) === range( 0, count( $defs ) - 1 )
				&& count( array_filter( $defs, 'is_string' ) ) === count( $defs )
			) {
				$groups[] = [ 'post_types' => $defs ];
			} else {
				foreach ( $defs as $k => $v ) {
					if ( is_string( $k ) && is_string( $v ) ) {
						$pairs[] = [ 'from' => $k, 'to' => $v ];
					} elseif ( is_array( $v ) && isset( $v['from'], $v['to'] ) ) {
						$pairs[] = $v;
					} elseif ( is_array( $v ) && ( isset( $v['post_types'] ) || isset( $v['types'] ) ) ) {
						$groups[] = $v;
					}
				}
			}

			if ( ! $pairs && ! $groups ) {
				return;
			}

			add_action( 'acf/init', function () use ( $pairs, $groups ) {
				foreach ( $pairs as $cfg ) {
					$from        = sanitize_key( $cfg['from'] );
					$to          = sanitize_key( $cfg['to'] );
					$to_existing = $cfg['to_existing'] ?? null; // ['flex','layout','field','label'?]

					$pt_from_obj = get_post_type_object( $from );
					$pt_to_obj   = get_post_type_object( $to );

					$label_from_default = $pt_to_obj?->labels->name ?: ucfirst( $to );
					$label_to_default   = $pt_from_obj?->labels->name ?: ucfirst( $from );

					$field_from = isset( $cfg['field_from'] ) ? sanitize_key( $cfg['field_from'] ) : 'linked_' . $to;
					$field_to   = isset( $cfg['field_to'] ) ? sanitize_key( $cfg['field_to'] ) : 'linked_' . $from;

					$label_from = isset( $cfg['label_from'] ) ? (string) $cfg['label_from'] : $label_from_default;
					$label_to   = $to_existing['label'] ?? ( isset( $cfg['label_to'] ) ? (string) $cfg['label_to'] : $label_to_default );

					$group_title = isset( $cfg['group_title'] ) ? (string) $cfg['group_title'] : __( 'Relations', 'ACF Fields' );
					$menu_order  = isset( $cfg['menu_order'] ) ? (int) $cfg['menu_order'] : 20;

					// Create the field on the FROM side.
					acf_add_local_field_group( [
						'key'        => 'grp_rel_' . $from . '_to_' . $to,
						'title'      => $group_title,
						'position'   => 'normal',
						'style'      => 'default',
						'menu_order' => $menu_order,
						'fields'     => [
							[
								'key'           => 'fld_' . $from . '_' . $field_from,
								'name'          => $field_from,
								'label'         => $label_from,
								'type'          => 'relationship',
								'post_type'     => [ $to ],
								'filters'       => [ '', '', '' ],
								'elements'      => [ 'post_type' ],
								'return_format' => 'id',
							]
						],
						'location'   => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => $from ] ] ],
						'active'     => true,
					] );

					// Create the field on the TO side unless an existing field was provided.
					if ( ! $to_existing ) {
						acf_add_local_field_group( [
							'key'        => 'grp_rel_' . $to . '_to_' . $from,
							'title'      => $group_title,
							'position'   => 'normal',
							'style'      => 'default',
							'menu_order' => $menu_order,
							'fields'     => [
								[
									'key'           => 'fld_' . $to . '_' . $field_to,
									'name'          => $field_to,
									'label'         => $label_to,
									'type'          => 'relationship',
									'post_type'     => [ $from ],
									'filters'       => [ '', '', '' ],
									'elements'      => [ 'post_type' ],
									'return_format' => 'id',
								]
							],
							'location'   => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => $to ] ] ],
							'active'     => true,
						] );
					}

					// Synchronize FROM to TO.
					add_filter( 'acf/update_value/name=' . $field_from, function ( $value, $post_id ) use ( $field_from, $field_to, $to_existing ) {
						if ( $to_existing ) {
							return _pr_sync_from_to_existing( $value, $post_id, $field_from, $to_existing );
						}

						return _pr_sync_bidirectional( $value, $post_id, $field_from, $field_to );
					}, 10, 2 );

					// Synchronize TO to FROM.
					if ( $to_existing ) {
						// Read the flexible content field after saving a TO post.
						add_action( 'acf/save_post', function ( $post_id ) use ( $from, $to, $field_from, $to_existing ) {
							if ( get_post_type( $post_id ) !== $to ) {
								return;
							}
							_pr_sync_existing_to_from( (int) $post_id, $field_from, $to_existing );
						}, 20 );
					} else {
						add_filter( 'acf/update_value/name=' . $field_to, function ( $value, $post_id ) use ( $field_from, $field_to ) {
							return _pr_sync_bidirectional( $value, $post_id, $field_to, $field_from );
						}, 10, 2 );
					}

					// Insert an admin column after the title column.
					$add_after_title = function ( array $cols, string $key, string $label ): array {
						$out = [];
						foreach ( $cols as $k => $v ) {
							$out[ $k ] = $v;
							if ( $k === 'title' ) {
								$out[ $key ] = $label;
							}
						}
						if ( ! isset( $out[ $key ] ) ) {
							$out[ $key ] = $label;
						}

						return $out;
					};

					// Admin column for the FROM side.
					add_filter( "manage_{$from}_posts_columns", function ( $cols ) use ( $field_from, $label_from, $add_after_title ) {
						return $add_after_title( $cols, $field_from, $label_from );
					} );
					add_action( "manage_{$from}_posts_custom_column", function ( $column, $post_id ) use ( $field_from ) {
						if ( $column !== $field_from ) {
							return;
						}
						$ids = array_values( array_filter( array_map( 'intval', (array) get_field( $field_from, $post_id, false ) ) ) );
						echo _pr_admin_rel_links( $ids );
					}, 10, 2 );

					// Admin column for the TO side.
					if ( $to_existing ) {
						$col_key = 'pr_col_' . $to_existing['flex'] . '_' . $to_existing['field'];
						add_filter( "manage_{$to}_posts_columns", function ( $cols ) use ( $col_key, $label_to, $add_after_title ) {
							return $add_after_title( $cols, $col_key, $label_to );
						} );
						add_action( "manage_{$to}_posts_custom_column", function ( $column, $post_id ) use ( $col_key, $to_existing ) {
							if ( $column !== $col_key ) {
								return;
							}
							$ids = _pr_get_flex_ids( (int) $post_id, $to_existing['flex'], $to_existing['layout'], $to_existing['field'] );
							echo _pr_admin_rel_links( $ids );
						}, 10, 2 );
					} else {
						add_filter( "manage_{$to}_posts_columns", function ( $cols ) use ( $field_to, $label_to, $add_after_title ) {
							return $add_after_title( $cols, $field_to, $label_to );
						} );
						add_action( "manage_{$to}_posts_custom_column", function ( $column, $post_id ) use ( $field_to ) {
							if ( $column !== $field_to ) {
								return;
							}
							$ids = array_values( array_filter( array_map( 'intval', (array) get_field( $field_to, $post_id, false ) ) ) );
							echo _pr_admin_rel_links( $ids );
						}, 10, 2 );
					}
				}

				foreach ( $groups as $cfg ) {
					_pr_register_relationship_group( $cfg );
				}

				if ( $groups ) {
					_pr_register_relationship_admin_styles();
				}

				add_action( 'admin_head-edit.php', function () {
					echo '<style>
                    .wp-list-table [class^="column-linked_"],
                    .wp-list-table [class^="column-pr_col_"] {
                        width: 40%;
                        max-width: 640px;
                        white-space: normal;
                        word-break: break-word;
                        line-height: 1.35;
                    }
                </style>';
				} );
			} );
		}
	}

// Create a separate bidirectional field for every pair in a 2+ post type group.
	if ( ! function_exists( '_pr_register_relationship_group' ) ) {
		function _pr_register_relationship_group( array $cfg ): void {
			$post_types = $cfg['post_types'] ?? $cfg['types'] ?? [];
			$post_types = array_values( array_unique( array_filter( array_map( 'sanitize_key', (array) $post_types ) ) ) );

			if ( count( $post_types ) < 2 ) {
				return;
			}

			$field_prefix = isset( $cfg['field_prefix'] ) ? sanitize_key( $cfg['field_prefix'] ) : 'linked_';
			$group_title  = isset( $cfg['group_title'] ) ? (string) $cfg['group_title'] : __( 'Relations', 'ACF Fields' );
			$menu_order   = isset( $cfg['menu_order'] ) ? (int) $cfg['menu_order'] : 20;
			$width        = isset( $cfg['width'] ) ? max( 1, min( 100, (int) $cfg['width'] ) ) : 50;
			$show_image   = (bool) ( $cfg['featured_image'] ?? $cfg['show_featured_image'] ?? false );
			$elements     = [ 'post_type' ];

			if ( $show_image ) {
				$elements[] = 'featured_image';
			}

			$group_id   = substr( md5( implode( '|', $post_types ) . '|' . $field_prefix ), 0, 12 );
			$field_keys = [];

			foreach ( $post_types as $post_type ) {
				foreach ( array_diff( $post_types, [ $post_type ] ) as $other_type ) {
					$field_keys[ $post_type ][ $other_type ] = 'field_rel_group_' . $group_id . '_' . $post_type . '_' . $other_type;
				}
			}

			foreach ( $post_types as $post_type ) {
				$other_types = array_values( array_diff( $post_types, [ $post_type ] ) );
				$fields      = [];
				$columns     = [];

				foreach ( $other_types as $other_type ) {
					$object        = get_post_type_object( $other_type );
					$label         = $object?->labels->name ?: ucfirst( $other_type );
					$field         = $field_prefix . $other_type;
					$reverse_field = $field_prefix . $post_type;
					$field_key     = $field_keys[ $post_type ][ $other_type ];
					$reverse_key   = $field_keys[ $other_type ][ $post_type ];

					$relationship_field = [
						'key'           => $field_key,
						'name'          => $field,
						'label'         => $label,
						'type'          => 'relationship',
						'post_type'     => [ $other_type ],
						'filters'       => [ 'search' ],
						'elements'      => $elements,
						'return_format' => 'id',
						'wrapper'       => [
							'width' => (string) $width,
							'class' => 'pr-relationship-field',
						],
					];

					require_once __DIR__ . '/taxonomy-fields.php';
					$fields = array_merge( $fields, _pr_taxonomy_fields(
						$relationship_field,
						$post_type,
						(string) ( $cfg['split_by_taxonomy'][ $other_type ] ?? '' ),
						( $cfg['show_taxonomies'] ?? null ) === false ? false : ( $cfg['show_taxonomies'][ $other_type ] ?? [] ),
						(bool) ( $cfg['show_uncategorized'][ $other_type ] ?? true )
					) );

					$columns[ $field ] = $label;

					add_filter( 'acf/update_value/key=' . $field_key, function ( $value, $post_id ) use (
						$field,
						$reverse_field,
						$post_type,
						$other_type,
						$reverse_key
					) {
						return _pr_sync_relationship_pair(
							$value,
							$post_id,
							$field,
							$reverse_field,
							$post_type,
							$other_type,
							$reverse_key
						);
					}, 10, 2 );
				}

				acf_add_local_field_group( [
					'key'        => 'group_rel_group_' . $group_id . '_' . $post_type,
					'title'      => $group_title,
					'position'   => 'normal',
					'style'      => 'default',
					'menu_order' => $menu_order,
					'fields'     => $fields,
					'location'   => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => $post_type ] ] ],
					'active'     => true,
				] );

				add_filter( "manage_{$post_type}_posts_columns", function ( $cols ) use ( $columns ) {
					$out = [];
					foreach ( $cols as $key => $value ) {
						$out[ $key ] = $value;
						if ( $key === 'title' ) {
							foreach ( $columns as $field => $label ) {
								$out[ $field ] = $label;
							}
						}
					}
					foreach ( $columns as $field => $label ) {
						if ( ! isset( $out[ $field ] ) ) {
							$out[ $field ] = $label;
						}
					}

					return $out;
				} );

				add_action( "manage_{$post_type}_posts_custom_column", function ( $column, $post_id ) use ( $columns ) {
					if ( ! isset( $columns[ $column ] ) ) {
						return;
					}
					$ids = _pr_normalize_ids( get_post_meta( $post_id, $column, true ) );
					echo _pr_admin_rel_links( $ids );
				}, 10, 2 );
			}
		}
	}

	if ( ! function_exists( '_pr_register_relationship_admin_styles' ) ) {
		function _pr_register_relationship_admin_styles(): void {
			static $registered = false;

			if ( $registered ) {
				return;
			}

			$registered = true;

			add_action( 'acf/input/admin_head', function () {
				?>
				<style id="pr-relationship-admin-styles">
					/* Use the Relations metabox width instead of the browser viewport width. */
					[id^="acf-group_rel_group_"] > .inside.acf-fields {
						container-name: pr-relations;
						container-type: inline-size;
					}

					[id^="acf-group_rel_group_"] .pr-relationship-field {
						box-sizing: border-box;
						min-width: 0;
						container-name: pr-relationship-field;
						container-type: inline-size;
					}

					/* Two columns when the metabox no longer has room for three usable fields. */
					@container pr-relations (max-width: 1080px) {
						.pr-relationship-field {
							width: 50% !important;
						}
					}

					/* One column in a narrow metabox or sidebar. */
					@container pr-relations (max-width: 680px) {
						.pr-relationship-field {
							width: 100% !important;
							float: none !important;
							clear: both;
						}
					}

					/* WordPress sidebar fallback for browsers without container queries. */
					#side-sortables [id^="acf-group_rel_group_"] .pr-relationship-field,
					.metabox-location-side [id^="acf-group_rel_group_"] .pr-relationship-field {
						width: 100% !important;
						float: none !important;
						clear: both;
					}

					/* Stack the available and selected lists when a single field is very narrow. */
					@container pr-relationship-field (max-width: 480px) {
						.acf-relationship .selection .choices,
						.acf-relationship .selection .values {
							width: 100%;
							float: none;
						}

						.acf-relationship .selection .choices .list {
							border-right: 0;
							border-bottom: 1px solid #dcdcde;
						}
					}
				</style>
				<?php
			} );
		}
	}

	if ( ! function_exists( '_pr_normalize_ids' ) ) {
		function _pr_normalize_ids( $value ): array {
			return array_values( array_unique( array_filter( array_map( 'intval', is_array( $value ) ? $value : [] ) ) ) );
		}
	}

	if ( ! function_exists( '_pr_sync_relationship_pair' ) ) {
		function _pr_sync_relationship_pair(
			$value,
			$post_id,
			string $field,
			string $reverse_field,
			string $post_type,
			string $other_type,
			string $reverse_key
		): array {
			static $guard = false;
			if ( $guard ) {
				return _pr_normalize_ids( $value );
			}

			$post_id = (int) $post_id;
			if ( ! $post_id || get_post_type( $post_id ) !== $post_type ) {
				return _pr_normalize_ids( $value );
			}

			$new_ids  = array_values( array_filter(
				_pr_normalize_ids( $value ),
				static fn( int $id ): bool => get_post_type( $id ) === $other_type
			) );
			$prev_ids = _pr_normalize_ids( get_post_meta( $post_id, $field, true ) );

			$guard = true;

			// Check all current values so a regular re-save also repairs a missing back-link.
			foreach ( $new_ids as $other_id ) {
				$list = _pr_normalize_ids( get_post_meta( $other_id, $reverse_field, true ) );
				if ( ! in_array( $post_id, $list, true ) ) {
					$list[] = $post_id;
					_pr_update_relationship_meta( $other_id, $reverse_field, $list, $reverse_key );
				}
			}

			foreach ( array_diff( $prev_ids, $new_ids ) as $other_id ) {
				$list = _pr_normalize_ids( get_post_meta( $other_id, $reverse_field, true ) );
				_pr_update_relationship_meta( $other_id, $reverse_field, array_diff( $list, [ $post_id ] ), $reverse_key );
			}

			$guard = false;

			return $new_ids;
		}
	}

	if ( ! function_exists( '_pr_update_relationship_meta' ) ) {
		function _pr_update_relationship_meta( int $post_id, string $field, array $ids, string $field_key ): void {
			update_post_meta( $post_id, $field, _pr_normalize_ids( $ids ) );
			// ACF reference meta lets get_field() find the local field before a manual post save.
			update_post_meta( $post_id, '_' . $field, $field_key );
		}
	}

// Read IDs from a flexible content sub-field.
	if ( ! function_exists( '_pr_get_flex_ids' ) ) {
		function _pr_get_flex_ids( int $post_id, string $flex, string $layout, string $sub_field ): array {
			$rows = get_post_meta( $post_id, $flex, true );
			if ( ! is_array( $rows ) ) {
				return [];
			}
			foreach ( $rows as $i => $row_layout ) {
				if ( $row_layout === $layout ) {
					$val = get_post_meta( $post_id, "{$flex}_{$i}_{$sub_field}", true );

					return is_array( $val ) ? array_values( array_filter( array_map( 'intval', $val ) ) ) : [];
				}
			}

			return [];
		}
	}

// Write IDs to a flexible content sub-field.
	if ( ! function_exists( '_pr_set_flex_ids' ) ) {
		function _pr_set_flex_ids( int $post_id, string $flex, string $layout, string $sub_field, array $ids ): void {
			$rows = get_post_meta( $post_id, $flex, true );
			if ( ! is_array( $rows ) ) {
				return;
			}
			foreach ( $rows as $i => $row_layout ) {
				if ( $row_layout === $layout ) {
					update_post_meta( $post_id, "{$flex}_{$i}_{$sub_field}", array_values( $ids ) );

					return;
				}
			}
		}
	}

// Sync a field_from change on FROM to the flexible content field on TO.
	if ( ! function_exists( '_pr_sync_from_to_existing' ) ) {
		function _pr_sync_from_to_existing( $value, $post_id, string $field_from, array $tex ): array {
			static $guard = false;
			if ( $guard ) {
				return $value;
			}
			$guard = true;

			$new_ids  = array_values( array_filter( array_map( 'intval', is_array( $value ) ? $value : [] ) ) );
			$prev_ids = get_post_meta( $post_id, $field_from, true );
			$prev_ids = is_array( $prev_ids ) ? array_values( array_filter( array_map( 'intval', $prev_ids ) ) ) : [];

			$to_add    = array_diff( $new_ids, $prev_ids );
			$to_remove = array_diff( $prev_ids, $new_ids );

			foreach ( $to_add as $to_id ) {
				$list = _pr_get_flex_ids( (int) $to_id, $tex['flex'], $tex['layout'], $tex['field'] );
				if ( ! in_array( (int) $post_id, $list, true ) ) {
					$list[] = (int) $post_id;
					_pr_set_flex_ids( (int) $to_id, $tex['flex'], $tex['layout'], $tex['field'], array_values( array_unique( $list ) ) );
				}
			}

			foreach ( $to_remove as $to_id ) {
				$list = _pr_get_flex_ids( (int) $to_id, $tex['flex'], $tex['layout'], $tex['field'] );
				_pr_set_flex_ids( (int) $to_id, $tex['flex'], $tex['layout'], $tex['field'],
					array_values( array_diff( $list, [ (int) $post_id ] ) )
				);
			}

			$guard = false;

			return $new_ids;
		}
	}

// ── Sync: save TO → update field_from on FROM ─────────────────────────────────
	if ( ! function_exists( '_pr_sync_existing_to_from' ) ) {
		function _pr_sync_existing_to_from( int $post_id, string $field_from, array $tex ): void {
			static $guard = false;
			if ( $guard ) {
				return;
			}
			$guard = true;

			$new_ids = _pr_get_flex_ids( $post_id, $tex['flex'], $tex['layout'], $tex['field'] );

			// Shadow meta-key stores previous state to compute diff
			$shadow_key = '_pr_shadow_' . $tex['flex'] . '_' . $tex['layout'] . '_' . $tex['field'];
			$prev_ids   = get_post_meta( $post_id, $shadow_key, true );
			$prev_ids   = is_array( $prev_ids ) ? array_values( array_filter( array_map( 'intval', $prev_ids ) ) ) : [];

			$to_add    = array_diff( $new_ids, $prev_ids );
			$to_remove = array_diff( $prev_ids, $new_ids );

			foreach ( $to_add as $from_id ) {
				$list = get_post_meta( $from_id, $field_from, true );
				$list = is_array( $list ) ? array_map( 'intval', $list ) : [];
				if ( ! in_array( $post_id, $list, true ) ) {
					$list[] = $post_id;
					update_post_meta( $from_id, $field_from, array_values( array_unique( $list ) ) );
				}
			}

			foreach ( $to_remove as $from_id ) {
				$list = get_post_meta( $from_id, $field_from, true );
				$list = is_array( $list ) ? array_map( 'intval', $list ) : [];
				update_post_meta( $from_id, $field_from, array_values( array_diff( $list, [ $post_id ] ) ) );
			}

			// Update shadow
			update_post_meta( $post_id, $shadow_key, $new_ids );

			$guard = false;
		}
	}

// ── Standard bidirectional sync (regular fields) ──────────────────────────────
	if ( ! function_exists( '_pr_sync_bidirectional' ) ) {
		function _pr_sync_bidirectional( $value, $post_id, string $this_field, string $other_field ) {
			static $guard = false;
			if ( $guard ) {
				return $value;
			}
			$guard = true;

			$new_ids  = array_filter( array_map( 'intval', is_array( $value ) ? $value : [] ) );
			$prev_ids = get_post_meta( $post_id, $this_field, true );
			$prev_ids = is_array( $prev_ids ) ? array_filter( array_map( 'intval', $prev_ids ) ) : [];

			foreach ( array_diff( $new_ids, $prev_ids ) as $other_id ) {
				$list = get_post_meta( $other_id, $other_field, true );
				$list = is_array( $list ) ? array_map( 'intval', $list ) : [];
				if ( ! in_array( (int) $post_id, $list, true ) ) {
					$list[] = (int) $post_id;
					update_post_meta( $other_id, $other_field, array_values( array_unique( $list ) ) );
				}
			}

			foreach ( array_diff( $prev_ids, $new_ids ) as $other_id ) {
				$list = get_post_meta( $other_id, $other_field, true );
				$list = is_array( $list ) ? array_map( 'intval', $list ) : [];
				update_post_meta( $other_id, $other_field, array_values( array_diff( $list, [ (int) $post_id ] ) ) );
			}

			$guard = false;

			return $new_ids;
		}
	}

	if ( ! function_exists( '_pr_admin_rel_links' ) ) {
		function _pr_admin_rel_links( array $ids ): string {
			if ( ! $ids ) {
				return '—';
			}
			$links = [];
			foreach ( $ids as $id ) {
				$t       = get_the_title( $id ) ?: 'ID ' . $id;
				$links[] = '<a href="' . esc_url( get_edit_post_link( $id ) ) . '">' . esc_html( $t ) . '</a>';
			}

			return implode( ', ', $links );
		}
	}
