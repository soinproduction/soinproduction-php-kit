<?php

	use StoutLogic\AcfBuilder\FieldsBuilder;

	$function_name = str_replace( '-', '_', basename( __DIR__ ) );

	$GLOBALS[ '_builder_' . $function_name ] = function ( $layout_name ) {

		$layout = new FieldsBuilder( $layout_name );
		$layout
			->addRadio( 'widget_static_block', [
				'label'      => false,
				'choices'    => [],
				'layout'     => 'vertical',
				'allow_null' => 1,
				'wrapper'    => [ 'class' => 'wsb-radio-field' ],
			] );

		return [
			'layout'  => $layout,
			'display' => 'block',
		];
	};

	eval( "function {$function_name}( \$layout_name ) {
	return \$GLOBALS['_builder_{$function_name}']( \$layout_name );
}" );

	add_filter( 'sp_builder_layouts_config', function ( array $config ) use ( $function_name ): array {
		$config['layouts'] = isset( $config['layouts'] ) && is_array( $config['layouts'] ) ? $config['layouts'] : [];

		foreach ( $config['layouts'] as $registered_layout ) {
			if ( is_array( $registered_layout ) && ( $registered_layout['name'] ?? '' ) === $function_name ) {
				return $config;
			}
		}

		$fields = call_user_func( $function_name, $function_name . '_fields' );
		if ( ! is_array( $fields ) || empty( $fields['layout'] ) ) {
			return $config;
		}

		$config['layouts'][] = [
			'name'    => $function_name,
			'label'   => $fields['label'] ?? __( 'Reusable Section', 'sp-content-library' ),
			'display' => $fields['display'] ?? 'block',
			'fields'  => $fields['layout'],
		];

		if ( isset( $fields['only'] ) ) {
			$only = array_values( array_filter( array_map( 'sanitize_key', (array) $fields['only'] ) ) );
			if ( $only ) {
				$config['layouts_only'][ $function_name ] = $only;
			}
		}

		return $config;
	}, 20 );

	if ( ! function_exists( 'sp_wsb_index_acf_fields' ) ) {
		function sp_wsb_index_acf_fields( array $fields ): array {
			$indexed = [
				'key'  => [],
				'name' => [],
			];

			foreach ( $fields as $field ) {
				if ( ! is_array( $field ) ) {
					continue;
				}

				if ( ! empty( $field['key'] ) ) {
					$indexed['key'][ (string) $field['key'] ] = $field;
				}

				if ( ! empty( $field['name'] ) ) {
					$indexed['name'][ (string) $field['name'] ] = $field;
				}
			}

			return $indexed;
		}
	}

	if ( ! function_exists( 'sp_wsb_index_acf_layouts' ) ) {
		function sp_wsb_index_acf_layouts( array $layouts ): array {
			$indexed = [];

			foreach ( $layouts as $layout ) {
				if ( is_array( $layout ) && ! empty( $layout['name'] ) ) {
					$indexed[ (string) $layout['name'] ] = $layout;
				}
			}

			return $indexed;
		}
	}

	if ( ! function_exists( 'sp_wsb_normalize_acf_value_keys' ) ) {
		function sp_wsb_normalize_acf_value_keys( $value, array $source_field, array $target_field ) {
			if ( ! is_array( $value ) ) {
				return $value;
			}

			$type = (string) ( $source_field['type'] ?? '' );

			if ( $type === 'group' || $type === 'clone' ) {
				return sp_wsb_normalize_acf_row_keys(
					$value,
					is_array( $source_field['sub_fields'] ?? null ) ? $source_field['sub_fields'] : [],
					is_array( $target_field['sub_fields'] ?? null ) ? $target_field['sub_fields'] : []
				);
			}

			if ( $type === 'repeater' ) {
				$rows = [];

				foreach ( $value as $row_index => $row ) {
					$rows[ $row_index ] = sp_wsb_normalize_acf_row_keys(
						$row,
						is_array( $source_field['sub_fields'] ?? null ) ? $source_field['sub_fields'] : [],
						is_array( $target_field['sub_fields'] ?? null ) ? $target_field['sub_fields'] : []
					);
				}

				return $rows;
			}

			if ( $type === 'flexible_content' ) {
				$source_layouts = sp_wsb_index_acf_layouts( is_array( $source_field['layouts'] ?? null ) ? $source_field['layouts'] : [] );
				$target_layouts = sp_wsb_index_acf_layouts( is_array( $target_field['layouts'] ?? null ) ? $target_field['layouts'] : [] );
				$rows           = [];

				foreach ( $value as $row_index => $row ) {
					if ( ! is_array( $row ) ) {
						$rows[ $row_index ] = $row;
						continue;
					}

					$layout_name = (string) ( $row['acf_fc_layout'] ?? '' );
					if ( $layout_name === '' || empty( $source_layouts[ $layout_name ] ) || empty( $target_layouts[ $layout_name ] ) ) {
						$rows[ $row_index ] = $row;
						continue;
					}

					$rows[ $row_index ] = sp_wsb_normalize_acf_row_keys(
						$row,
						is_array( $source_layouts[ $layout_name ]['sub_fields'] ?? null ) ? $source_layouts[ $layout_name ]['sub_fields'] : [],
						is_array( $target_layouts[ $layout_name ]['sub_fields'] ?? null ) ? $target_layouts[ $layout_name ]['sub_fields'] : []
					);
				}

				return $rows;
			}

			return $value;
		}
	}

	if ( ! function_exists( 'sp_wsb_normalize_acf_row_keys' ) ) {
		function sp_wsb_normalize_acf_row_keys( $row, array $source_fields, array $target_fields ) {
			if ( ! is_array( $row ) ) {
				return $row;
			}

			$source_index = sp_wsb_index_acf_fields( $source_fields );
			$target_index = sp_wsb_index_acf_fields( $target_fields );
			$normalized   = [];

			foreach ( $row as $key => $value ) {
				if ( $key === 'acf_fc_layout' ) {
					$normalized[ $key ] = $value;
					continue;
				}

				$source_field = is_string( $key )
					? ( $source_index['key'][ $key ] ?? $source_index['name'][ $key ] ?? null )
					: null;

				if ( ! is_array( $source_field ) || empty( $source_field['name'] ) ) {
					$normalized[ $key ] = $value;
					continue;
				}

				$target_field = $target_index['name'][ (string) $source_field['name'] ] ?? null;
				if ( ! is_array( $target_field ) || empty( $target_field['key'] ) ) {
					$normalized[ $key ] = $value;
					continue;
				}

				$normalized[ (string) $target_field['key'] ] = sp_wsb_normalize_acf_value_keys( $value, $source_field, $target_field );
			}

			return $normalized;
		}
	}

	add_filter( 'acf/load_field/type=radio', function ( $field ) {
		if ( empty( $field['wrapper']['class'] ) || ! str_contains( $field['wrapper']['class'], 'wsb-radio-field' ) ) {
			return $field;
		}

		$posts = get_posts( [
			'post_type'      => 'widgets',
			'post_status'    => [ 'publish', 'draft', 'private' ],
			'posts_per_page' => - 1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		] );

		$field['choices'] = [];

		foreach ( $posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			$title       = get_the_title( $post->ID ) ?: sprintf( __( 'Untitled #%d', 'ACF' ), $post->ID );
			$thumb       = get_the_post_thumbnail_url( $post->ID, 'full' );
			$thumb_large = get_the_post_thumbnail_url( $post->ID, 'full' );
			$post_status = get_post_status( $post );
			$status_obj  = get_post_status_object( $post_status );
			$status      = $status_obj ? $status_obj->label : '';
			$edit_url    = get_edit_post_link( $post->ID, 'raw' );

			$terms = wp_get_post_terms( $post->ID, 'widgets_category' );
			$cat_slugs = [];
			$cat_names = [];
			if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				foreach ( $terms as $term ) {
					$cat_slugs[] = $term->slug;
					$cat_names[] = $term->name;
				}
			}
			$categories_str       = implode( ' ', $cat_slugs );
			$categories_names_str = implode( ';', $cat_names );

			$thumb_html = $thumb
				? '<img src="' . esc_url( $thumb ) . '" class="wsb-item-thumb" alt="">'
				: '<div class="wsb-item-thumb wsb-item-thumb--empty"><svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="3" width="12" height="10" rx="2"/><path d="M2 10l3-3 3 3 2-2 4 4"/></svg></div>';

			$field['choices'][ (string) $post->ID ] = sprintf(
				'<span class="wsb-item-inner" data-title="%s" data-edit="%s" data-status="%s" data-status-slug="%s" data-thumb-large="%s" data-categories="%s" data-categories-names="%s">%s<span class="wsb-item-info"><span class="wsb-item-name">%s</span><span class="wsb-item-status">%s</span></span><span class="wsb-radio"><span class="wsb-radio-dot"></span></span></span>',
				esc_attr( $title ),
				esc_attr( $edit_url ),
				esc_attr( $status ),
				esc_attr( $post_status ),
				esc_attr( $thumb_large ),
				esc_attr( $categories_str ),
				esc_attr( $categories_names_str ),
				$thumb_html,
				esc_html( $title ),
				esc_html( $status )
			);
		}

		return $field;
	} );

	add_action( 'wp_ajax_sp_import_widget_builder_row', function () {
		if ( ! function_exists( 'get_field' ) || ! function_exists( 'update_field' ) ) {
			wp_send_json_error( [ 'message' => __( 'ACF is not available.', 'ACF' ) ], 500 );
		}

		$get_builder_field = static function ( int $post_id ): array {
			if ( ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_get_fields' ) ) {
				return [];
			}

			$field_groups = acf_get_field_groups( [ 'post_id' => $post_id ] );
			if ( ! is_array( $field_groups ) ) {
				return [];
			}

			foreach ( $field_groups as $field_group ) {
				$fields = acf_get_fields( $field_group );
				if ( ! is_array( $fields ) ) {
					continue;
				}

				foreach ( $fields as $field ) {
					if ( is_array( $field ) && ( $field['name'] ?? '' ) === 'builder' && ( $field['type'] ?? '' ) === 'flexible_content' ) {
						return $field;
					}
				}
			}

			return [];
		};

		$canonicalize_meta_suffix = static function ( string $suffix ): string {
			$parts = explode( '_', $suffix );
			foreach ( $parts as &$part ) {
				if ( ctype_digit( $part ) ) {
					$part = '{row}';
				}
			}
			unset( $part );

			return implode( '_', $parts );
		};

		$collect_field_keys = null;
		$collect_field_keys = static function ( array $fields, string $prefix = '' ) use ( &$collect_field_keys ): array {
			$keys = [];

			foreach ( $fields as $field ) {
				if ( ! is_array( $field ) || empty( $field['name'] ) || empty( $field['key'] ) ) {
					continue;
				}

				$name = $prefix === '' ? (string) $field['name'] : $prefix . '_' . $field['name'];
				$type = (string) ( $field['type'] ?? '' );

				$keys[ $name ] = (string) $field['key'];

				if ( $type === 'group' && ! empty( $field['sub_fields'] ) && is_array( $field['sub_fields'] ) ) {
					$keys += $collect_field_keys( $field['sub_fields'], $name );
					continue;
				}

				if ( $type === 'repeater' && ! empty( $field['sub_fields'] ) && is_array( $field['sub_fields'] ) ) {
					$keys += $collect_field_keys( $field['sub_fields'], $name . '_{row}' );
					continue;
				}

				if ( $type === 'flexible_content' && ! empty( $field['layouts'] ) && is_array( $field['layouts'] ) ) {
					$keys[ $name . '_{row}_acf_fc_layout' ] = (string) $field['key'];

					foreach ( $field['layouts'] as $layout ) {
						if ( ! empty( $layout['sub_fields'] ) && is_array( $layout['sub_fields'] ) ) {
							$keys += $collect_field_keys( $layout['sub_fields'], $name . '_{row}' );
						}
					}
				}
			}

			return $keys;
		};

		$get_builder_key_map = static function ( array $builder_field ) use ( $collect_field_keys ): array {
			if ( empty( $builder_field['layouts'] ) || ! is_array( $builder_field['layouts'] ) ) {
				return [];
			}

			$map = [];

			foreach ( $builder_field['layouts'] as $layout ) {
				if ( ! is_array( $layout ) || empty( $layout['name'] ) ) {
					continue;
				}

				$layout_name         = (string) $layout['name'];
				$map[ $layout_name ] = [
					'acf_fc_layout' => (string) ( $builder_field['key'] ?? '' ),
				];

				if ( ! empty( $layout['sub_fields'] ) && is_array( $layout['sub_fields'] ) ) {
					$map[ $layout_name ] += $collect_field_keys( $layout['sub_fields'] );
				}
			}

			return $map;
		};

		$get_builder_meta_keys = static function ( array $meta ): array {
			$keys = [];

			foreach ( array_keys( $meta ) as $meta_key ) {
				if ( $meta_key === 'builder' || $meta_key === '_builder' || str_starts_with( $meta_key, 'builder_' ) || str_starts_with( $meta_key, '_builder_' ) ) {
					$keys[] = $meta_key;
				}
			}

			sort( $keys, SORT_NATURAL );

			return $keys;
		};

		check_ajax_referer( 'sp_import_widget_builder_row', 'nonce' );

		$post_id   = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
		$widget_id = isset( $_POST['widget_id'] ) ? (int) $_POST['widget_id'] : 0;
		$row_index = isset( $_POST['row_index'] ) ? (int) $_POST['row_index'] : -1;

		if ( $post_id <= 0 || $widget_id <= 0 || $row_index < 0 ) {
			wp_send_json_error( [ 'message' => __( 'Missing import data.', 'ACF' ) ], 400 );
		}

		if ( get_post_type( $widget_id ) !== 'widgets' ) {
			wp_send_json_error( [ 'message' => __( 'Selected item is not a widget.', 'ACF' ) ], 400 );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) || ! current_user_can( 'edit_post', $widget_id ) ) {
			wp_send_json_error( [ 'message' => __( 'You are not allowed to import this widget.', 'ACF' ) ], 403 );
		}

		$current_rows = get_field( 'builder', $post_id, false );
		$source_rows  = get_field( 'builder', $widget_id, false );
		$current_rows_formatted = get_field( 'builder', $post_id );
		$source_rows_formatted  = get_field( 'builder', $widget_id );

		if ( ! is_array( $current_rows ) || ! isset( $current_rows[ $row_index ] ) ) {
			wp_send_json_error( [ 'message' => __( 'Current builder row was not found. Save/reload the page and try again.', 'ACF' ) ], 404 );
		}

		$current_layout = $current_rows[ $row_index ]['acf_fc_layout'] ?? '';
		if ( $current_layout !== 'section_widgets' ) {
			wp_send_json_error( [ 'message' => __( 'Current row is not a widget section anymore.', 'ACF' ) ], 409 );
		}

		if ( empty( $source_rows ) || ! is_array( $source_rows ) ) {
			wp_send_json_error( [ 'message' => __( 'Selected widget has no builder sections to import.', 'ACF' ) ], 400 );
		}

		$import_row_indexes = [];
		foreach ( $source_rows as $source_index => $source_row ) {
			if ( ! is_array( $source_row ) || empty( $source_row['acf_fc_layout'] ) ) {
				continue;
			}

			if ( $source_row['acf_fc_layout'] === 'section_widgets' ) {
				continue;
			}

			$import_row_indexes[] = (int) $source_index;
		}

		if ( ! $import_row_indexes ) {
			wp_send_json_error( [ 'message' => __( 'Selected widget has no importable sections.', 'ACF' ) ], 400 );
		}

		$current_meta = get_post_meta( $post_id );
		$source_meta  = get_post_meta( $widget_id );
		$builder_field        = $get_builder_field( $post_id );
		$source_builder_field = $get_builder_field( $widget_id );
		$field_key_map        = $get_builder_key_map( $builder_field );
		$source_field_key_map = $get_builder_key_map( $source_builder_field );

		if ( empty( $builder_field['key'] ) || ! $field_key_map ) {
			wp_send_json_error( [ 'message' => __( 'Could not read the target builder field map.', 'ACF' ) ], 500 );
		}

		if ( empty( $source_builder_field['key'] ) || ! $source_field_key_map ) {
			wp_send_json_error( [ 'message' => __( 'Could not read the widget builder field map.', 'ACF' ) ], 500 );
		}

		$row_sources = [];
		foreach ( $current_rows as $current_index => $current_row ) {
			if ( (int) $current_index === $row_index ) {
				foreach ( $import_row_indexes as $source_index ) {
					$row_sources[] = [
						'index'  => $source_index,
						'layout' => (string) ( $source_rows[ $source_index ]['acf_fc_layout'] ?? '' ),
						'meta'   => $source_meta,
					];
				}
				continue;
			}

			$row_sources[] = [
				'index'  => (int) $current_index,
				'layout' => (string) ( $current_row['acf_fc_layout'] ?? '' ),
				'meta'   => $current_meta,
			];
		}

		$new_meta = [
			'builder'  => [ (string) count( $row_sources ) ],
			'_builder' => [ (string) $builder_field['key'] ],
		];

		foreach ( $row_sources as $new_index => $row_source ) {
			$old_index   = (int) $row_source['index'];
			$layout_name = (string) $row_source['layout'];
			$row_meta    = is_array( $row_source['meta'] ) ? $row_source['meta'] : [];
			$old_prefix  = 'builder_' . $old_index . '_';
			$new_prefix  = 'builder_' . $new_index . '_';
			$old_rprefix = '_builder_' . $old_index . '_';
			$new_rprefix = '_builder_' . $new_index . '_';
			$layout_keys = $field_key_map[ $layout_name ] ?? [];

			if ( $layout_name === '' || ! $layout_keys ) {
				wp_send_json_error( [ 'message' => __( 'Could not map one of the imported layouts.', 'ACF' ) ], 500 );
			}

			$new_meta[ $new_prefix . 'acf_fc_layout' ]  = [ $layout_name ];
			$new_meta[ $new_rprefix . 'acf_fc_layout' ] = [ (string) $builder_field['key'] ];

			foreach ( $row_meta as $meta_key => $values ) {
				if ( ! is_array( $values ) || $values === [] ) {
					continue;
				}

				$new_key = null;
				if ( str_starts_with( $meta_key, $old_prefix ) ) {
					$new_key = $new_prefix . substr( $meta_key, strlen( $old_prefix ) );
					$new_meta[ $new_key ] = array_map( 'maybe_unserialize', $values );
					continue;
				}

				if ( str_starts_with( $meta_key, $old_rprefix ) ) {
					$suffix = substr( $meta_key, strlen( $old_rprefix ) );
					$path   = $canonicalize_meta_suffix( $suffix );

					if ( empty( $layout_keys[ $path ] ) ) {
						continue;
					}

					$new_key = $new_rprefix . $suffix;
					$new_meta[ $new_key ] = [ $layout_keys[ $path ] ];
				}
			}
		}

		for ( $i = 0, $count = count( $row_sources ); $i < $count; $i++ ) {
			if ( empty( $new_meta[ 'builder_' . $i . '_acf_fc_layout' ][0] ) ) {
				wp_send_json_error( [ 'message' => __( 'Prepared import is missing a layout marker. Nothing was changed.', 'ACF' ) ], 500 );
			}
		}

		$summarize_rows = static function ( $rows ): array {
			if ( ! is_array( $rows ) ) {
				return [
					'type' => gettype( $rows ),
				];
			}

			return array_map( static function ( $row ): array {
				if ( ! is_array( $row ) ) {
					return [
						'type' => gettype( $row ),
					];
				}

				$summary = [
					'acf_fc_layout' => $row['acf_fc_layout'] ?? null,
					'keys'          => array_keys( $row ),
				];

				foreach ( $row as $key => $value ) {
					if ( $key === 'acf_fc_layout' ) {
						continue;
					}

					if ( is_array( $value ) ) {
						$summary['value_shapes'][ $key ] = [
							'type'  => 'array',
							'count' => count( $value ),
							'keys'  => array_slice( array_keys( $value ), 0, 12 ),
						];
						continue;
					}

					$summary['value_shapes'][ $key ] = [
						'type'  => gettype( $value ),
						'value' => is_scalar( $value ) ? mb_substr( (string) $value, 0, 160 ) : null,
					];
				}

				return $summary;
			}, $rows );
		};

		$sample_meta_values = static function ( array $meta, array $keys ): array {
			$samples = [];

			foreach ( $keys as $key ) {
				if ( ! array_key_exists( $key, $meta ) ) {
					continue;
				}

				$value = maybe_unserialize( $meta[ $key ][0] ?? null );
				$samples[ $key ] = [
					'type'  => gettype( $value ),
					'value' => is_scalar( $value ) ? mb_substr( (string) $value, 0, 300 ) : $value,
				];
			}

			return $samples;
		};

		$prepared_layout_markers = [];
		foreach ( range( 0, max( 0, count( $row_sources ) - 1 ) ) as $marker_index ) {
			$marker = $new_meta[ 'builder_' . $marker_index . '_acf_fc_layout' ][0] ?? null;
			if ( $marker !== null ) {
				$prepared_layout_markers[] = $marker;
			}
		}

		$debug = [
			'time'                    => current_time( 'mysql' ),
			'post_id'                 => $post_id,
			'widget_id'               => $widget_id,
			'row_index'               => $row_index,
			'dry_run'                 => ! empty( $_POST['dry_run'] ),
			'current_rows_count'      => count( $current_rows ),
			'source_rows_count'       => count( $source_rows ),
			'import_row_indexes'      => $import_row_indexes,
			'row_sources'             => array_map( static function ( array $row_source ): array {
				return [
					'index'  => $row_source['index'],
					'layout' => $row_source['layout'],
					'source' => $row_source['meta'] === [] ? 'empty' : 'meta',
				];
			}, $row_sources ),
			'target_builder_key'      => (string) ( $builder_field['key'] ?? '' ),
			'available_target_layouts' => array_keys( $field_key_map ),
			'current_rows_raw'       => $summarize_rows( $current_rows ),
			'source_rows_raw'        => $summarize_rows( $source_rows ),
			'current_rows_formatted' => $summarize_rows( $current_rows_formatted ),
			'source_rows_formatted'  => $summarize_rows( $source_rows_formatted ),
			'current_meta_values'    => $sample_meta_values( $current_meta, [ 'builder', '_builder', 'builder_0_acf_fc_layout', 'builder_0_blocks', '_builder_0_blocks' ] ),
			'source_meta_values'     => $sample_meta_values( $source_meta, [ 'builder', '_builder', 'builder_0_acf_fc_layout', 'builder_0_blocks', '_builder_0_blocks', 'builder_0_gallery', '_builder_0_gallery' ] ),
			'current_builder_keys'    => $get_builder_meta_keys( $current_meta ),
			'source_builder_keys'     => $get_builder_meta_keys( $source_meta ),
			'prepared_meta_keys'      => array_keys( $new_meta ),
			'prepared_layout_markers' => $prepared_layout_markers,
		];

		if ( ! empty( $_POST['dry_run'] ) ) {
			error_log( 'SP_WIDGET_IMPORT_DEBUG ' . wp_json_encode( $debug, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );

			wp_send_json_success( [
				'message' => sprintf(
					__( 'Debug import logged. Nothing was changed. Rows: %1$d, prepared meta keys: %2$d.', 'ACF' ),
					count( $row_sources ),
					count( $new_meta )
				),
				'debug'   => $debug,
			] );
		}

		$backup = [];
		foreach ( $current_meta as $meta_key => $values ) {
			if ( $meta_key === 'builder' || $meta_key === '_builder' || str_starts_with( $meta_key, 'builder_' ) || str_starts_with( $meta_key, '_builder_' ) ) {
				$backup[ $meta_key ] = array_map( 'maybe_unserialize', (array) $values );
			}
		}
		update_post_meta( $post_id, '_sp_builder_import_backup_' . time(), $backup );

		if ( ! is_array( $current_rows ) || ! isset( $current_rows[ $row_index ] ) ) {
			wp_send_json_error( [ 'message' => __( 'Could not read raw current builder rows.', 'ACF' ) ], 500 );
		}

		if ( ! is_array( $source_rows ) ) {
			wp_send_json_error( [ 'message' => __( 'Could not read raw widget builder rows.', 'ACF' ) ], 500 );
		}

		$raw_import_rows = [];
		$source_builder_layouts = sp_wsb_index_acf_layouts( is_array( $source_builder_field['layouts'] ?? null ) ? $source_builder_field['layouts'] : [] );
		$target_builder_layouts = sp_wsb_index_acf_layouts( is_array( $builder_field['layouts'] ?? null ) ? $builder_field['layouts'] : [] );

		foreach ( $import_row_indexes as $source_index ) {
			if ( empty( $source_rows[ $source_index ] ) || ! is_array( $source_rows[ $source_index ] ) ) {
				continue;
			}

			$source_layout = (string) ( $source_rows[ $source_index ]['acf_fc_layout'] ?? '' );
			if ( $source_layout === '' || empty( $source_builder_layouts[ $source_layout ] ) || empty( $target_builder_layouts[ $source_layout ] ) ) {
				wp_send_json_error( [ 'message' => __( 'Could not map raw widget row field keys.', 'ACF' ) ], 500 );
			}

			$raw_import_rows[] = sp_wsb_normalize_acf_row_keys(
				$source_rows[ $source_index ],
				is_array( $source_builder_layouts[ $source_layout ]['sub_fields'] ?? null ) ? $source_builder_layouts[ $source_layout ]['sub_fields'] : [],
				is_array( $target_builder_layouts[ $source_layout ]['sub_fields'] ?? null ) ? $target_builder_layouts[ $source_layout ]['sub_fields'] : []
			);
		}

		if ( ! $raw_import_rows ) {
			wp_send_json_error( [ 'message' => __( 'Could not prepare raw widget rows.', 'ACF' ) ], 500 );
		}

		array_splice( $current_rows, $row_index, 1, $raw_import_rows );

		foreach ( $current_rows as $raw_row ) {
			if ( ! is_array( $raw_row ) || empty( $raw_row['acf_fc_layout'] ) ) {
				wp_send_json_error( [ 'message' => __( 'Prepared raw builder is missing a layout marker. Nothing was changed.', 'ACF' ) ], 500 );
			}
		}

		if ( update_field( $builder_field['key'], $current_rows, $post_id ) === false ) {
			wp_send_json_error( [ 'message' => __( 'Could not update the builder with raw rows.', 'ACF' ) ], 500 );
		}

		wp_send_json_success( [
			'message' => __( 'Widget imported.', 'ACF' ),
			'count'   => count( $import_row_indexes ),
		] );
	} );

	add_action( 'wp_ajax_sp_save_builder_row_as_widget', function () {
		if ( ! function_exists( 'get_field' ) || ! function_exists( 'update_field' ) ) {
			wp_send_json_error( [ 'message' => __( 'ACF is not available.', 'ACF' ) ], 500 );
		}

		$get_builder_field = static function ( $context ): array {
			if ( ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_get_fields' ) ) {
				return [];
			}

			$args = is_numeric( $context ) ? [ 'post_id' => (int) $context ] : [ 'post_type' => (string) $context ];
			$field_groups = acf_get_field_groups( $args );
			if ( ! is_array( $field_groups ) ) {
				return [];
			}

			foreach ( $field_groups as $field_group ) {
				$fields = acf_get_fields( $field_group );
				if ( ! is_array( $fields ) ) {
					continue;
				}

				foreach ( $fields as $field ) {
					if ( is_array( $field ) && ( $field['name'] ?? '' ) === 'builder' && ( $field['type'] ?? '' ) === 'flexible_content' ) {
						return $field;
					}
				}
			}

			return [];
		};

		$collect_field_keys = null;
		$collect_field_keys = static function ( array $fields, string $prefix = '' ) use ( &$collect_field_keys ): array {
			$keys = [];

			foreach ( $fields as $field ) {
				if ( ! is_array( $field ) || empty( $field['name'] ) || empty( $field['key'] ) ) {
					continue;
				}

				$name = $prefix === '' ? (string) $field['name'] : $prefix . '_' . $field['name'];
				$type = (string) ( $field['type'] ?? '' );

				$keys[ $name ] = (string) $field['key'];

				if ( $type === 'group' && ! empty( $field['sub_fields'] ) && is_array( $field['sub_fields'] ) ) {
					$keys += $collect_field_keys( $field['sub_fields'], $name );
					continue;
				}

				if ( $type === 'repeater' && ! empty( $field['sub_fields'] ) && is_array( $field['sub_fields'] ) ) {
					$keys += $collect_field_keys( $field['sub_fields'], $name . '_{row}' );
					continue;
				}

				if ( $type === 'flexible_content' && ! empty( $field['layouts'] ) && is_array( $field['layouts'] ) ) {
					$keys[ $name . '_{row}_acf_fc_layout' ] = (string) $field['key'];

					foreach ( $field['layouts'] as $layout ) {
						if ( ! empty( $layout['sub_fields'] ) && is_array( $layout['sub_fields'] ) ) {
							$keys += $collect_field_keys( $layout['sub_fields'], $name . '_{row}' );
						}
					}
				}
			}

			return $keys;
		};

		$get_builder_key_map = static function ( array $builder_field ) use ( $collect_field_keys ): array {
			if ( empty( $builder_field['layouts'] ) || ! is_array( $builder_field['layouts'] ) ) {
				return [];
			}

			$map = [];

			foreach ( $builder_field['layouts'] as $layout ) {
				if ( ! is_array( $layout ) || empty( $layout['name'] ) ) {
					continue;
				}

				$layout_name         = (string) $layout['name'];
				$map[ $layout_name ] = [
					'acf_fc_layout' => (string) ( $builder_field['key'] ?? '' ),
				];

				if ( ! empty( $layout['sub_fields'] ) && is_array( $layout['sub_fields'] ) ) {
					$map[ $layout_name ] += $collect_field_keys( $layout['sub_fields'] );
				}
			}

			return $map;
		};

		check_ajax_referer( 'sp_import_widget_builder_row', 'nonce' );

		$post_id   = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
		$row_index = isset( $_POST['row_index'] ) ? (int) $_POST['row_index'] : -1;
		$title     = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$thumbnail_id = isset( $_POST['thumbnail_id'] ) ? (int) $_POST['thumbnail_id'] : 0;

		if ( $post_id <= 0 || $row_index < 0 ) {
			wp_send_json_error( [ 'message' => __( 'Missing section data.', 'ACF' ) ], 400 );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) || ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( [ 'message' => __( 'You are not allowed to create widgets.', 'ACF' ) ], 403 );
		}

		$current_rows = get_field( 'builder', $post_id, false );
		if ( ! is_array( $current_rows ) || empty( $current_rows[ $row_index ] ) || ! is_array( $current_rows[ $row_index ] ) ) {
			wp_send_json_error( [ 'message' => __( 'Current builder row was not found. Save/reload the page and try again.', 'ACF' ) ], 404 );
		}

		$row = $current_rows[ $row_index ];
		$layout_name = (string) ( $row['acf_fc_layout'] ?? '' );
		if ( $layout_name === '' || $layout_name === 'section_widgets' ) {
			wp_send_json_error( [ 'message' => __( 'This row cannot be saved as a widget.', 'ACF' ) ], 400 );
		}

		if ( $title === '' ) {
			$title = ucwords( str_replace( [ 'section_', '_' ], [ '', ' ' ], $layout_name ) );
		}

		$page_builder_field = $get_builder_field( $post_id );
		$widget_builder_field = $get_builder_field( 'widgets' );
		$page_key_map = $get_builder_key_map( $page_builder_field );
		$widget_key_map = $get_builder_key_map( $widget_builder_field );

		if ( empty( $page_builder_field['key'] ) || empty( $widget_builder_field['key'] ) || empty( $page_key_map[ $layout_name ] ) || empty( $widget_key_map[ $layout_name ] ) ) {
			wp_send_json_error( [ 'message' => __( 'Could not map this section to the widget builder.', 'ACF' ) ], 500 );
		}

		$page_layouts   = sp_wsb_index_acf_layouts( is_array( $page_builder_field['layouts'] ?? null ) ? $page_builder_field['layouts'] : [] );
		$widget_layouts = sp_wsb_index_acf_layouts( is_array( $widget_builder_field['layouts'] ?? null ) ? $widget_builder_field['layouts'] : [] );

		if ( empty( $page_layouts[ $layout_name ] ) || empty( $widget_layouts[ $layout_name ] ) ) {
			wp_send_json_error( [ 'message' => __( 'Could not map this section layout to the widget builder.', 'ACF' ) ], 500 );
		}

		$widget_row = sp_wsb_normalize_acf_row_keys(
			$row,
			is_array( $page_layouts[ $layout_name ]['sub_fields'] ?? null ) ? $page_layouts[ $layout_name ]['sub_fields'] : [],
			is_array( $widget_layouts[ $layout_name ]['sub_fields'] ?? null ) ? $widget_layouts[ $layout_name ]['sub_fields'] : []
		);

		$widget_id = wp_insert_post( [
			'post_type'   => 'widgets',
			'post_status' => 'publish',
			'post_title'  => $title,
		], true );

		if ( is_wp_error( $widget_id ) || ! $widget_id ) {
			wp_send_json_error( [ 'message' => __( 'Could not create widget post.', 'ACF' ) ], 500 );
		}

		if ( update_field( $widget_builder_field['key'], [ $widget_row ], (int) $widget_id ) === false ) {
			wp_delete_post( (int) $widget_id, true );
			wp_send_json_error( [ 'message' => __( 'Could not save section fields into the widget.', 'ACF' ) ], 500 );
		}

		if ( $thumbnail_id > 0 && wp_attachment_is_image( $thumbnail_id ) && current_user_can( 'upload_files' ) ) {
			set_post_thumbnail( (int) $widget_id, $thumbnail_id );
		}

		if ( taxonomy_exists( 'widgets_category' ) ) {
			wp_set_object_terms( (int) $widget_id, [ 'section' ], 'widgets_category', false );
		}

		wp_send_json_success( [
			'message'  => __( 'Widget created.', 'ACF' ),
			'widgetId' => (int) $widget_id,
			'thumbnailId' => $thumbnail_id,
			'title'    => get_the_title( (int) $widget_id ),
			'editUrl'  => get_edit_post_link( (int) $widget_id, 'raw' ),
		] );
	} );

	add_action( 'acf/input/admin_enqueue_scripts', function () {
		if ( function_exists( 'wp_enqueue_media' ) ) {
			wp_enqueue_media();
		}
	} );

	add_action( 'acf/input/admin_head', function () use ( $function_name ) {
		$post_id = 0;
		$post_type = '';

		if ( isset( $GLOBALS['post'] ) && $GLOBALS['post'] instanceof WP_Post ) {
			$post_id = (int) $GLOBALS['post']->ID;
			$post_type = (string) $GLOBALS['post']->post_type;
		}
		?>
        <style>
            [data-layout="<?php echo $function_name; ?>"] > .acf-fields > .acf-field {
                padding: 0;
            }

            [data-layout="<?php echo $function_name; ?>"] .wsb-radio-field > .acf-label {
                display: none;
            }

            [data-layout="<?php echo $function_name; ?>"] .wsb-radio-field > .acf-input {
                padding: 0;
            }

            [data-layout="<?php echo $function_name; ?>"] .wsb-radio-field ul.acf-radio-list {
                display: none !important;
            }

            [data-layout="<?php echo $function_name; ?>"] > .acf-fields {
                border: none !important
            }


            [data-layout="<?php echo $function_name; ?>"].active-layout {
                border-color: #ccd0d4 !important;
            }

            .wsb-ui {
                display: grid;
                grid-template-columns: minmax(380px, 44%) minmax(0, 1fr);
                min-height: 680px;
                overflow: hidden;
                background: #fff;
            }

            .wsb-left {
                display: flex;
                flex-direction: column;
                min-width: 0;
                background: #f6f7f7;
                border-right: 1px solid #dcdcde;
            }

            .wsb-search {
                text-indent: 24px;
            }

            .wsb-search-wrap {
                position: sticky;
                top: 0;
                z-index: 5;
                padding: 12px;
                border-bottom: 1px solid #dcdcde;
                background: #fff;
            }

            .wsb-cats-filter {
                display: flex;
                flex-wrap: wrap;
                gap: 6px;
                margin-top: 10px;
            }

            .wsb-cat-tab {
                display: inline-flex;
                align-items: center;
                padding: 5px 10px;
                font-size: 11px;
                font-weight: 600;
                color: #50575e;
                background: #f0f0f1;
                border: none;
                border-radius: 0 !important;
                cursor: pointer;
                transition: background .12s, color .12s;
            }

            .wsb-cat-tab:hover {
                background: #dcdcde;
                color: #1d2327;
            }

            .wsb-cat-tab.is-active {
                background: var(--wp-admin-theme-color);
                color: #fff;
            }

            .wsb-search-inner {
                position: relative;
                width: 100%;
            }

            .wsb-search-ico {
                position: absolute;
                left: 12px;
                top: 50%;
                transform: translateY(-50%);
                width: 14px;
                height: 14px;
                color: #787c82;
                pointer-events: none;
                z-index: 2;
            }

            .wsb-search {
                width: 100%;
                height: 40px;
                padding: 0 14px 0 36px;
                font-size: 13px;
                border: 1px solid #c3c4c7;
                background: #fff;
                color: #1d2327;
                outline: none;
                box-shadow: none;
                transition: border-color .15s, box-shadow .15s;
            }

            .wsb-search:focus {
                border-color: var(--wp-admin-theme-color);
                box-shadow: 0 0 0 1px var(--wp-admin-theme-color);
            }

            .wsb-list {
                padding: 12px;
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 12px;
                overflow-y: auto;
                grid-auto-rows: max-content;
                max-height: 550px;
                margin-bottom: -50px;
                padding-bottom: 50px;
                align-content: start;
                flex: 1;
            }

            .wsb-list:has(.wsb-item) .wsb-empty-results {
                display: none;
            }

            .wsb-list::-webkit-scrollbar {
                width: 10px;
            }

            .wsb-list::-webkit-scrollbar-track {
                background: transparent;
            }

            .wsb-list::-webkit-scrollbar-thumb {
                background: #c3c4c7;
                border: 2px solid transparent;
                background-clip: padding-box;
            }

            .wsb-list::-webkit-scrollbar-thumb:hover {
                background: #8c8f94;
            }

            .wsb-item {
                position: relative;
                display: flex;
                flex-direction: column;
                min-width: 0;
                border: 1px solid #dcdcde;
                overflow: hidden;
                background: #fff;
                cursor: pointer;
                transition: transform .16s ease, box-shadow .16s ease, border-color .16s ease;
            }

            .wsb-item:hover {
                transform: translateY(-1px);
                border-color: #b6bcc2;
                box-shadow: 0 6px 18px rgba(0, 0, 0, .08);
            }

            .wsb-item.is-active {
                border-color: var(--wp-admin-theme-color);
                box-shadow: 0 0 0 1px var(--wp-admin-theme-color), 0 8px 22px rgba(34, 113, 177, .14);
            }

            .wsb-item-media {
                position: relative;
                aspect-ratio: 16 / 10;
                overflow: hidden;
                background: #f0f0f1;
            }

            .wsb-item-thumb {
                width: 100%;
                height: 100%;
                object-fit: cover;
                display: block;
            }

            .wsb-item-thumb--empty {
                width: 100%;
                height: 100%;
                display: flex;
                align-items: center;
                justify-content: center;
                background: linear-gradient(135deg, #f6f7f7 0%, #eceef0 100%);
                color: #8c8f94;
            }

            .wsb-item-thumb--empty svg {
                width: 24px;
                height: 24px;
            }

            .wsb-item-topbar {
                position: absolute;
                top: 10px;
                right: 10px;
                left: 10px;
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                gap: 8px;
                z-index: 2;
                pointer-events: none;
            }

            .wsb-edit-link {
                pointer-events: auto;
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 6px 10px;
                font-size: 11px;
                font-weight: 600;
                text-decoration: none;
                color: #fff;
                background: rgba(29, 35, 39, .72);
                backdrop-filter: blur(6px);
                opacity: 0;
                transform: translateY(-4px);
                transition: opacity .18s, transform .18s, background .15s;
            }

            .wsb-item:hover .wsb-edit-link {
                opacity: 1;
                transform: translateY(0);
            }

            .wsb-edit-link:hover {
                background: rgba(29, 35, 39, .92);
                color: #fff;
            }

            .wsb-radio {
                width: 20px;
                height: 20px;
                margin-left: auto;
                /*border-radius: 50%;*/
                border: 2px solid rgba(255, 255, 255, .95);
                background: rgba(0, 0, 0, .18);
                backdrop-filter: blur(4px);
                display: flex;
                align-items: center;
                justify-content: center;
                transition: background .15s, border-color .15s, transform .15s;
            }

            .wsb-item.is-active .wsb-radio {
                background: var(--wp-admin-theme-color);
                border-color: var(--wp-admin-theme-color);
                transform: scale(1.04);
            }

            .wsb-radio-dot {
                width: 10px;
                height: 10px;
                /*border-radius: 50%;*/
                background: #fff;
                opacity: 0;
                transition: opacity .15s;
            }

            .wsb-item.is-active .wsb-radio-dot {
                opacity: 1;
            }

            .wsb-item-body {
                padding: 12px 14px 14px;
            }

            .wsb-item-name {
                display: block;
                margin-bottom: 8px;
                font-size: 13px;
                line-height: 1.4;
                font-weight: 600;
                color: #1d2327;
                word-break: break-word;
            }

            .wsb-item-status {
                display: inline-flex;
                align-items: center;
                min-height: 24px;
                padding: 0 9px;
                font-size: 11px;
                font-weight: 600;
                background: #f0f0f1;
                color: #50575e;
            }

            .wsb-item-status.is-publish {
                background: #edf7ed;
                color: #137333;
            }

            .wsb-item-status.is-draft {
                background: #fff4e5;
                color: #b06000;
            }

            .wsb-item-status.is-private {
                background: #f1f3f4;
                color: #5f6368;
            }

            .wsb-empty-results {
                grid-column: 1 / -1;
                padding: 22px 18px;
                border: 1px dashed #c3c4c7;
                background: #fff;
                text-align: center;
                color: #646970;
                font-size: 13px;
            }

            /* RIGHT */
            .wsb-right {
                position: relative;
                min-width: 0;
                background: linear-gradient(180deg, #f8f9fa 0%, #f1f3f4 100%);
            }

            .wsb-preview-wrap {
                position: absolute;
                inset: 0;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 28px;
                overflow: hidden;
            }

            .wsb-preview-panel {
                position: relative;
                width: 100%;
                height: 100%;
                border: 1px solid #dcdcde;
                background: #fff;
                overflow: hidden;
                box-shadow: 0 12px 30px rgba(0, 0, 0, .08);
            }

            .wsb-preview-img {
                width: 100%;
                height: 100%;
                object-fit: contain;
                display: block;
                background: #fff;
            }

            .wsb-preview-overlay {
                position: absolute;
                inset: auto 18px 18px 18px;
                display: flex;
                justify-content: flex-end;
                gap: 10px;
                pointer-events: none;
            }

            .wsb-preview-import {
                pointer-events: auto;
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 10px 14px;
                border: 0;
                text-decoration: none;
                font-size: 12px;
                line-height: 1;
                font-weight: 600;
                color: #fff;
                background: var(--wp-admin-theme-color);
                cursor: pointer;
                transition: background .15s, transform .15s, opacity .15s;
            }

            .wsb-preview-import:hover {
                background: var(--wp-admin-theme-color-darker-10);
                border-color: transparent;
                color: #fff;
                transform: translateY(-1px);
            }

            .wsb-preview-import:disabled {
                opacity: .65;
                cursor: wait;
                transform: none;
            }

            .wsb-save-widget {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 128px;
                height: 30px;
                margin: 0 8px 0 12px;
                padding: 0 12px;
                border: 1px solid var(--wp-admin-theme-color);
                background: #fff;
                color: var(--wp-admin-theme-color);
                font-size: 12px;
                line-height: 1;
                font-weight: 600;
                cursor: pointer;
                vertical-align: middle;
            }

            .wsb-save-widget:hover {
                background: #f0f6fc;
                color: #135e96;
                border-color: #135e96;
            }

            .wsb-save-widget:disabled {
                opacity: .65;
                cursor: wait;
            }

            .wsb-modal-backdrop {
                position: fixed;
                inset: 0;
                z-index: 100000;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 24px;
                background: rgba(13, 18, 24, .55);
                backdrop-filter: blur(2px);
            }

            .wsb-modal {
                width: min(460px, 100%);
                background: #fff;
                border: 1px solid #dcdcde;
                box-shadow: 0 22px 70px rgba(0, 0, 0, .24);
            }

            .wsb-modal-head {
                padding: 18px 20px 0;
            }

            .wsb-modal-title {
                margin: 0;
                color: #1d2327;
                font-size: 18px;
                line-height: 1.35;
                font-weight: 700;
            }

            .wsb-modal-body {
                padding: 12px 20px 20px;
                color: #50575e;
                font-size: 13px;
                line-height: 1.55;
            }

            .wsb-modal-input {
                width: 100%;
                min-height: 38px;
                margin-top: 14px;
                padding: 0 10px;
                border: 1px solid #8c8f94;
                background: #fff;
                color: #1d2327;
                font-size: 14px;
                box-shadow: none;
            }

            .wsb-modal-input:focus {
                border-color: var(--wp-admin-theme-color);
                box-shadow: 0 0 0 1px var(--wp-admin-theme-color);
                outline: 2px solid transparent;
            }

            .wsb-modal-media {
                margin-top: 14px;
            }

            .wsb-modal-media-label {
                display: block;
                margin-bottom: 8px;
                color: #1d2327;
                font-size: 12px;
                line-height: 1.4;
                font-weight: 600;
            }

            .wsb-modal-media-row {
                display: flex;
                align-items: flex-start;
                flex-direction: column;
                gap: 12px;
            }

            .wsb-modal-media-preview {
                position: relative;
                width: 100%;
                aspect-ratio: 1 / .5;
                display: flex;
                align-items: center;
                justify-content: center;
                overflow: hidden;
                border: 1px solid #dcdcde;
                background: #f6f7f7;
                color: #646970;
                font-size: 14px;
                font-weight: 600;
                text-align: center;
                cursor: pointer;
                transition: border-color .15s, background .15s, color .15s;
            }

            .wsb-modal-media-preview img {
                width: 100%;
                height: 100%;
                object-fit: contain;
                display: block;
            }

            .wsb-modal-media-preview:hover,
            .wsb-modal-media-preview:focus {
                border-color: var(--wp-admin-theme-color);
                background: #f0f6fc;
                color: #135e96;
                outline: none;
            }

            .wsb-modal-media-remove {
                position: absolute;
                top: 0;
                right: 0;
                width: 24px;
                height: 24px;
                display: none;
                align-items: center;
                justify-content: center;
                padding: 0;
                transform: translate(50%, -50%);
                border: 1px solid rgba(0, 0, 0, .2);
                border-radius: 50%;
                background: rgba(255, 255, 255, .94);
                color: #1d2327;
                font-size: 16px;
                line-height: 1;
                font-weight: 700;
                cursor: pointer;
                box-shadow: 0 1px 3px rgba(0, 0, 0, .16);
            }

            .wsb-modal-media-remove svg {
                width: 80%;
                height: auto;
            }

            .wsb-modal-media-preview.has-image .wsb-modal-media-remove {
                display: inline-flex;
            }

            .wsb-modal-media-remove:hover {
                background: #fff;
                color: #b32d2e;
            }

            .wsb-modal-actions {
                display: flex;
                justify-content: flex-end;
                gap: 10px;
                padding: 14px 20px;
                border-top: 1px solid #dcdcde;
                background: #f6f7f7;
            }

            .wsb-modal-button {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-height: 36px;
                padding: 0 14px;
                border: 1px solid #c3c4c7;
                background: #fff;
                color: #1d2327;
                font-size: 13px;
                font-weight: 600;
                cursor: pointer;
            }

            .wsb-modal-button:hover {
                border-color: #8c8f94;
            }

            .wsb-modal-button-primary {
                border-color: var(--wp-admin-theme-color);
                background: var(--wp-admin-theme-color);
                color: #fff;
            }

            .wsb-modal-button-primary:hover {
                border-color: #135e96;
                background: #135e96;
                color: #fff;
            }

            .wsb-preview-edit-hover {
                pointer-events: auto;
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 10px 14px;
                text-decoration: none;
                font-size: 12px;
                font-weight: 600;
                color: #fff;
                background: rgba(29, 35, 39, .8);
                transition: background .15s, transform .15s;
            }

            .wsb-preview-edit-hover:hover {
                background: rgba(29, 35, 39, .95);
                color: #fff;
                transform: translateY(-1px);
            }

            .wsb-preview-empty,
            .wsb-no-selection {
                width: 100%;
                height: 100%;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: 10px;
                color: #8c8f94;
                text-align: center;
                padding: 24px;
            }

            .wsb-preview-empty svg,
            .wsb-no-selection svg {
                width: 34px;
                height: 34px;
            }

            .wsb-preview-empty span,
            .wsb-no-selection span {
                font-size: 13px;
                line-height: 1.5;
                color: #646970;
                max-width: 280px;
            }

            @media (max-width: 1200px) {
                .wsb-ui {
                    grid-template-columns: 1fr;
                }

                .wsb-left {
                    border-right: 0;
                    border-bottom: 1px solid #dcdcde;
                }

                .wsb-right {
                    min-height: 420px;
                }
            }

            @media (max-width: 782px) {
                .wsb-list {
                    grid-template-columns: 1fr;
                }
            }
        </style>

        <script>
            jQuery(function ($) {
                const WSB_IMPORT = <?php echo wp_json_encode( [
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'sp_import_widget_builder_row' ),
					'postId'  => $post_id,
					'postType' => $post_type,
				] ); ?> || {};

                const WSB_SAVE_DEBUG = true;

                function wsbSaveLog(message, data) {
                    if (!WSB_SAVE_DEBUG || !window.console || typeof window.console.log !== 'function') {
                        return;
                    }
                }

                function getBuilderRowIndex($node) {
                    const $layout = $node.closest('.layout[data-layout]').first();
                    const $rows = $layout.closest('.values').children('.layout:not(.acf-clone)');
                    const domIndex = $rows.index($layout);

                    if (domIndex >= 0) {
                        return domIndex;
                    }

                    const rowId = String($layout.attr('data-id') || '');
                    const match = rowId.match(/^row-(\d+)$/);

                    return match ? parseInt(match[1], 10) : -1;
                }

                function wsbModal(options) {
                    const settings = $.extend({
                        title: '',
                        message: '',
                        confirmText: 'OK',
                        cancelText: '',
                        confirmClass: 'wsb-modal-button-primary',
                        input: false,
                        inputValue: '',
                        inputPlaceholder: '',
                        media: false,
                        mediaId: 0,
                        mediaUrl: ''
                    }, options || {});

                    return new Promise(function (resolve) {
                        const $backdrop = $('<div class="wsb-modal-backdrop" role="presentation"></div>');
                        const $modal = $('<div class="wsb-modal" role="dialog" aria-modal="true"></div>');
                        const $actions = $('<div class="wsb-modal-actions"></div>');
                        const $confirm = $('<button type="button" class="wsb-modal-button"></button>')
                            .addClass(settings.confirmClass)
                            .text(settings.confirmText);
                        const $input = settings.input
                            ? $('<input type="text" class="wsb-modal-input">')
                                .val(settings.inputValue || '')
                                .attr('placeholder', settings.inputPlaceholder || '')
                            : null;
                        let mediaFrame = null;
                        const selectedMedia = {
                            id: parseInt(settings.mediaId, 10) || 0,
                            url: settings.mediaUrl || ''
                        };
                        const $media = settings.media
                            ? $('<div class="wsb-modal-media"></div>')
                            : null;
                        const $mediaPreview = settings.media
                            ? $('<div class="wsb-modal-media-preview"></div>')
                            : null;
                        const $removeMedia = settings.media
                            ? $('<button type="button" class="wsb-modal-media-remove" aria-label="Remove preview"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"> <path fill="currentColor" d="M17.7 5.2a.8.8 0 1 1 1 1.1L6.4 18.8a.8.8 0 0 1-1-1.1z"/> <path fill="currentColor" d="M5.2 5.2q.6-.5 1.1 0l12.5 12.5a.8.8 0 0 1-1.1 1L5.2 6.4a1 1 0 0 1 0-1"/> </svg></button>')
                            : null;

                        function renderMediaPreview() {
                            if (!$mediaPreview) {
                                return;
                            }

                            $mediaPreview
                                .empty()
                                .toggleClass('has-image', !!selectedMedia.url);

                            if (selectedMedia.url) {
                                $mediaPreview
                                    .append($('<img alt="">').attr('src', selectedMedia.url))
                                    .append($removeMedia);
                                return;
                            }

                            $mediaPreview
                                .text('Add preview')
                                .append($removeMedia);
                        }

                        function close(value) {
                            $(document).off('keydown.wsbModal');
                            $backdrop.remove();
                            resolve(value);
                        }

                        if ($media) {
                            renderMediaPreview();

                            const openMediaFrame = function () {
                                if (!window.wp || !window.wp.media) {
                                    return;
                                }

                                if (!mediaFrame) {
                                    mediaFrame = window.wp.media({
                                        title: 'Select widget preview',
                                        button: {
                                            text: 'Use this image'
                                        },
                                        multiple: false,
                                        library: {
                                            type: 'image'
                                        }
                                    });

                                    mediaFrame.on('select', function () {
                                        const attachment = mediaFrame.state().get('selection').first();
                                        const data = attachment ? attachment.toJSON() : {};
                                        const sizes = data.sizes || {};
                                        const image = sizes.medium || sizes.thumbnail || sizes.full || {};

                                        selectedMedia.id = parseInt(data.id, 10) || 0;
                                        selectedMedia.url = image.url || data.url || '';
                                        renderMediaPreview();
                                    });
                                }

                                mediaFrame.open();
                            };

                            $mediaPreview
                                .attr({
                                    role: 'button',
                                    tabindex: '0'
                                })
                                .on('click', openMediaFrame)
                                .on('keydown', function (event) {
                                    if (event.key === 'Enter' || event.key === ' ') {
                                        event.preventDefault();
                                        openMediaFrame();
                                    }
                                });

                            $removeMedia.on('click', function (event) {
                                event.preventDefault();
                                event.stopPropagation();
                                selectedMedia.id = 0;
                                selectedMedia.url = '';
                                renderMediaPreview();
                            });

                            $media
                                .append('<span class="wsb-modal-media-label">Preview image</span>')
                                .append(
                                    $('<div class="wsb-modal-media-row"></div>')
                                        .append($mediaPreview)
                                );
                        }

                        $modal
                            .append(
                                $('<div class="wsb-modal-head"></div>').append(
                                    $('<h3 class="wsb-modal-title"></h3>').text(settings.title)
                                )
                            )
                            .append(
                                $('<div class="wsb-modal-body"></div>')
                                    .text(settings.message)
                                    .append($input || $())
                                    .append($media || $())
                            );

                        if (settings.cancelText) {
                            const $cancel = $('<button type="button" class="wsb-modal-button"></button>')
                                .text(settings.cancelText)
                                .on('click', function () {
                                    close(false);
                                });

                            $actions.append($cancel);
                        }

                        $confirm.on('click', function () {
                            if ($input || $media) {
                                close({
                                    confirmed: true,
                                    value: $input ? $input.val() : '',
                                    attachmentId: selectedMedia.id
                                });
                                return;
                            }

                            close(true);
                        });

                        $actions.append($confirm);
                        $modal.append($actions);
                        $backdrop.append($modal);
                        $('body').append($backdrop);

                        $(document).on('keydown.wsbModal', function (event) {
                            if (event.key === 'Escape') {
                                close(false);
                            }
                        });

                        ($input || $confirm).trigger('focus');
                    });
                }

                function defaultWidgetTitle($layout) {
                    const label = ($layout.find('> .acf-fc-layout-handle .acf-fc-layout-title, > .acf-fc-layout-handle').first().text() || '').trim();
                    const clean = label.replace(/^\d+\s*/, '').trim();

                    if (clean) return clean;

                    const layoutName = $layout.attr('data-layout') || 'Widget';
                    return layoutName.replace(/^section_/, '').replace(/_/g, ' ').replace(/\b\w/g, function (letter) {
                        return letter.toUpperCase();
                    });
                }

                function bindSaveAsWidgetButtons($root) {
                    if (WSB_IMPORT.postType === 'widgets') {
                        return;
                    }

                    const $scope = $root || $(document);
                    let $layouts = $scope.filter('.layout[data-layout]')
                        .add($scope.find('.acf-flexible-content .layout[data-layout]'));

                    $layouts = $layouts.filter(function () {
                        const $layout = $(this);
                        const $flexibleField = $layout.closest('.acf-field-flexible-content');
                        return $flexibleField.attr('data-name') === 'builder' && 
                               $flexibleField.parents('.acf-field-flexible-content').length === 0;
                    });

                    wsbSaveLog('scan', {
                        root: $scope && $scope[0] ? ($scope[0].nodeName || 'node') : 'unknown',
                        layouts: $layouts.length
                    });

                    $layouts.each(function () {
                        const $layout = $(this);
                        const layoutName = $layout.attr('data-layout') || '';
                        const isWidgetsLayout = layoutName === '<?php echo esc_js( $function_name ); ?>';
                        const isClone = $layout.hasClass('acf-clone');
                        const isSectionLayout = layoutName.indexOf('section_') === 0;
                        const hasButton = $layout.find('> .acf-fc-layout-actions-wrap .wsb-save-widget').length > 0;

                        if (isClone || hasButton || isWidgetsLayout) {
                            return;
                        }

                        const $actionsWrap = $layout.children('.acf-fc-layout-actions-wrap').first();
                        const $controls = $actionsWrap.children('.acf-fc-layout-controls').first();

                        const $button = $('<button type="button" class="wsb-save-widget">Save as widget</button>');

                        if (!$actionsWrap.length) {
                            wsbSaveLog('missing actions wrap', {
                                layout: layoutName,
                                rowIndex: getBuilderRowIndex($layout)
                            });
                            return;
                        }

                        if ($controls.length) {
                            $controls.before($button);
                        } else {
                            $actionsWrap.append($button);
                        }

                        $button.on('click', function (event) {
                            event.preventDefault();
                            event.stopPropagation();

                            const rowIndex = getBuilderRowIndex($layout);
                            wsbSaveLog('click', {
                                layout: layoutName,
                                rowIndex: rowIndex,
                                postId: WSB_IMPORT.postId || 0
                            });

                            if (!WSB_IMPORT.postId || rowIndex < 0) {
                                wsbModal({
                                    title: 'Save unavailable',
                                    message: 'Could not detect current builder row. Save/reload the page and try again.',
                                    confirmText: 'OK'
                                });
                                return;
                            }

                            wsbModal({
                                title: 'Save section as widget',
                                message: 'Create a reusable widget from the saved version of this section. Save the page first if you just changed fields.',
                                confirmText: 'Create widget',
                                cancelText: 'Cancel',
                                input: true,
                                inputValue: defaultWidgetTitle($layout),
                                inputPlaceholder: 'Widget title',
                                media: true
                            }).then(function (result) {
                                if (!result || !result.confirmed) {
                                    return;
                                }

                                const title = (result.value || '').trim();
                                if (!title) {
                                    wsbModal({
                                        title: 'Title required',
                                        message: 'Add a widget title before creating it.',
                                        confirmText: 'OK'
                                    });
                                    return;
                                }

                                $button.prop('disabled', true).text('Saving...');

                                $.post(WSB_IMPORT.ajaxUrl, {
                                    action: 'sp_save_builder_row_as_widget',
                                    nonce: WSB_IMPORT.nonce,
                                    post_id: WSB_IMPORT.postId,
                                    row_index: rowIndex,
                                    title: title,
                                    thumbnail_id: parseInt(result.attachmentId, 10) || 0
                                }).done(function (response) {
                                    wsbSaveLog('ajax done', response || {});

                                    if (response && response.success) {
                                        const editUrl = response.data && response.data.editUrl ? response.data.editUrl : '';
                                        wsbModal({
                                            title: 'Widget created',
                                            message: editUrl ? 'The section was saved as a widget. Open it from the Widgets menu to add preview/category details.' : 'The section was saved as a widget.',
                                            confirmText: 'OK'
                                        });
                                        $button.prop('disabled', false).text('Save as widget');
                                        return;
                                    }

                                    const message = response && response.data && response.data.message ? response.data.message : 'Could not create widget.';
                                    wsbModal({
                                        title: 'Save failed',
                                        message: message,
                                        confirmText: 'OK'
                                    });
                                    $button.prop('disabled', false).text('Save as widget');
                                }).fail(function (xhr) {
                                    wsbSaveLog('ajax fail', {
                                        status: xhr.status,
                                        response: xhr.responseJSON || xhr.responseText || ''
                                    });

                                    const response = xhr.responseJSON || {};
                                    const message = response.data && response.data.message ? response.data.message : 'Could not create widget.';
                                    wsbModal({
                                        title: 'Save failed',
                                        message: message,
                                        confirmText: 'OK'
                                    });
                                    $button.prop('disabled', false).text('Save as widget');
                                });
                            });
                        });
                    });
                }

                function updatePreview($right, thumbLarge, editUrl, widgetId, $field) {
                    $right.empty();

                    const $wrap = $('<div class="wsb-preview-wrap"></div>');
                    const $panel = $('<div class="wsb-preview-panel"></div>');

                    if (thumbLarge) {
                        $panel.append(
                            $('<img class="wsb-preview-img" alt="">').attr('src', thumbLarge)
                        );
                    } else {
                        $panel.append(
                            '<div class="wsb-preview-empty">' +
                            '<svg viewBox="0 0 36 36" fill="none" stroke="currentColor" stroke-width="1.2">' +
                            '<rect x="4" y="6" width="28" height="24" rx="2"/>' +
                            '<path d="M4 22l8-8 8 8 5-5 7 7"/>' +
                            '</svg>' +
                            '<span>No preview — save the widget to generate one</span>' +
                            '</div>'
                        );
                    }

                    if (editUrl) {
                        const $overlay = $('<div class="wsb-preview-overlay"></div>');
                        const $import = $('<button type="button" class="wsb-preview-import">Insert Block</button>');
                        const $edit = $('<a target="_blank" class="wsb-preview-edit-hover">Edit widget</a>').attr('href', editUrl);

                        $import.on('click', function (event) {
                            event.preventDefault();
                            event.stopPropagation();

                            const rowIndex = getBuilderRowIndex($field);

                            if (!WSB_IMPORT.postId || !widgetId || rowIndex < 0) {
                                wsbModal({
                                    title: 'Import unavailable',
                                    message: 'Could not detect current builder row. Save/reload the page and try again.',
                                    confirmText: 'OK'
                                });
                                return;
                            }

                            wsbModal({
                                title: 'Import editable section?',
                                message: 'This will replace the current Widgets row with editable sections copied from the selected widget.',
                                confirmText: 'Import',
                                cancelText: 'Cancel'
                            }).then(function (confirmed) {
                                if (!confirmed) {
                                    return;
                                }

                                $import.prop('disabled', true).text('Importing...');

                                $.post(WSB_IMPORT.ajaxUrl, {
                                    action: 'sp_import_widget_builder_row',
                                    nonce: WSB_IMPORT.nonce,
                                    post_id: WSB_IMPORT.postId,
                                    widget_id: widgetId,
                                    row_index: rowIndex
                                }).done(function (response) {
                                    if (response && response.success) {
                                        window.location.reload();
                                        return;
                                    }

                                    const message = response && response.data && response.data.message ? response.data.message : 'Import failed.';
                                    wsbModal({
                                        title: 'Import failed',
                                        message: message,
                                        confirmText: 'OK'
                                    });
                                    $import.prop('disabled', false).text('Import widget');
                                }).fail(function (xhr) {
                                    const response = xhr.responseJSON || {};
                                    const message = response.data && response.data.message ? response.data.message : 'Import failed.';
                                    wsbModal({
                                        title: 'Import failed',
                                        message: message,
                                        confirmText: 'OK'
                                    });
                                    $import.prop('disabled', false).text('Import widget');
                                });
                            });
                        });

                        $edit.on('click', function (event) {
                            event.stopPropagation();
                        });

                        $overlay.append($import).append($edit);
                        $panel.append($overlay);
                    }

                    $wrap.append($panel);
                    $right.append($wrap);
                }

                function updateEmptyState($wsbList) {
                    const $visibleItems = $wsbList.find('.wsb-item:visible');
                    const $empty = $wsbList.find('.wsb-empty-results');

                    if (!$visibleItems.length) {
                        if (!$empty.length) {
                            $wsbList.append('<div class="wsb-empty-results">No widgets found</div>');
                        }
                    } else {
                        $empty.remove();
                    }
                }

                function wsbBind($field) {
                    const $list = $field.find('ul.acf-radio-list');
                    const $ui = $field.find('.wsb-ui');
                    const $right = $ui.find('.wsb-right');
                    const $wsbList = $ui.find('.wsb-list');

                    if (!$list.length || !$ui.length) return;

                    $wsbList.empty();

                    const categoriesMap = {};

                    $list.find('li').each(function () {
                        const $li = $(this);
                        const $input = $li.find('input[type="radio"]');
                        const $inner = $li.find('.wsb-item-inner');
                        const widgetId = parseInt($input.val(), 10) || 0;
                        const title = $inner.attr('data-title') || '';
                        const editUrl = $inner.attr('data-edit') || '';
                        const status = $inner.attr('data-status') || '';
                        const statusSlug = $inner.attr('data-status-slug') || '';
                        const thumbLarge = $inner.attr('data-thumb-large') || '';
                        const thumbSrc = $inner.find('img.wsb-item-thumb').attr('src') || '';
                        const categories = $inner.attr('data-categories') || '';
                        const categoriesNames = $inner.attr('data-categories-names') || '';

                        if (categories) {
                            const slugs = categories.split(' ');
                            const names = categoriesNames.split(';');
                            for (var i = 0; i < slugs.length; i++) {
                                if (slugs[i]) {
                                    categoriesMap[slugs[i]] = names[i] || slugs[i];
                                }
                            }
                        }

                        const $media = $('<div class="wsb-item-media"></div>');

                        if (thumbSrc) {
                            $media.append(
                                $('<img class="wsb-item-thumb" alt="">').attr('src', thumbSrc)
                            );
                        } else {
                            $media.append(
                                '<div class="wsb-item-thumb wsb-item-thumb--empty">' +
                                '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">' +
                                '<rect x="2" y="3" width="12" height="10" rx="2"/>' +
                                '<path d="M2 10l3-3 3 3 2-2 4 4"/>' +
                                '</svg>' +
                                '</div>'
                            );
                        }

                        const $topbar = $('<div class="wsb-item-topbar"></div>');

                        if (editUrl) {
                            $topbar.append(
                                $('<a class="wsb-edit-link" target="_blank">Edit</a>')
                                    .attr('href', editUrl)
                                    .on('click', function (e) {
                                        e.stopPropagation();
                                    })
                            );
                        } else {
                            $topbar.append('<span></span>');
                        }

                        $topbar.append('<div class="wsb-radio"><div class="wsb-radio-dot"></div></div>');

                        const $item = $('<div class="wsb-item"></div>')
                            .attr('data-cats', categories)
                            .attr('data-cats-names', categoriesNames)
                            .append($topbar)
                            .append($media)
                            .append(
                                $('<div class="wsb-item-body"></div>')
                                    .append($('<span class="wsb-item-name"></span>').text(title))
                                    .append(
                                        $('<span class="wsb-item-status"></span>')
                                            .addClass('is-' + statusSlug)
                                            .text(status)
                                    )
                            );

                        if ($input.is(':checked')) {
                            $item.addClass('is-active');
                            updatePreview($right, thumbLarge, editUrl, widgetId, $field);
                        }

                        $item.on('click', function () {
                            $wsbList.find('.wsb-item').removeClass('is-active');
                            $(this).addClass('is-active');
                            $input.prop('checked', true).trigger('change');
                            updatePreview($right, thumbLarge, editUrl, widgetId, $field);
                        });

                        $wsbList.append($item);
                    });

                    if (!$wsbList.find('.wsb-item.is-active').length) {
                        $right.html(
                            '<div class="wsb-no-selection">' +
                            '<svg viewBox="0 0 36 36" fill="none" stroke="currentColor" stroke-width="1.2">' +
                            '<rect x="4" y="6" width="28" height="24" rx="2"/>' +
                            '<path d="M4 22l8-8 8 8 5-5 7 7"/>' +
                            '</svg>' +
                            '<span>Select a widget to preview it here</span>' +
                            '</div>'
                        );
                    }

                    // Remove any old category filter wrapper
                    $ui.find('.wsb-cats-filter').remove();

                    // Dynamically build category filter buttons
                    var $catsWrap = $('<div class="wsb-cats-filter"></div>');
                    var $allTab = $('<button type="button" class="wsb-cat-tab is-active" data-cat="all">All</button>');
                    $catsWrap.append($allTab);

                    // Sort categories: Section first, Page Template second, then alphabetical
                    var sortedKeys = Object.keys(categoriesMap).sort(function (a, b) {
                        var order = ['section', 'page-template', 'page_template'];
                        var idxA = order.indexOf(a);
                        var idxB = order.indexOf(b);

                        if (idxA !== -1 && idxB !== -1) {
                            return idxA - idxB;
                        }
                        if (idxA !== -1) return -1;
                        if (idxB !== -1) return 1;

                        return categoriesMap[a].localeCompare(categoriesMap[b]);
                    });

                    $.each(sortedKeys, function (index, slug) {
                        var name = categoriesMap[slug];
                        var $tab = $('<button type="button" class="wsb-cat-tab" data-cat="' + slug + '"></button>').text(name);
                        $catsWrap.append($tab);
                    });

                    if (Object.keys(categoriesMap).length > 0) {
                        $ui.find('.wsb-search-wrap').append($catsWrap);
                    }

                    // Unified filtering state
                    var currentSearchQuery = '';
                    var currentCategoryFilter = 'all';

                    function applyWsbFilters() {
                        $wsbList.find('.wsb-item').each(function () {
                            var $item = $(this);

                            // 1. Search Query (Matches title, category slug, or category name)
                            var text = $item.find('.wsb-item-name').text().toLowerCase();
                            var itemCats = ($item.attr('data-cats') || '').toLowerCase();
                            var itemCatsNames = ($item.attr('data-cats-names') || '').toLowerCase().replace(/;/g, ' ');
                            var matchesSearch = text.includes(currentSearchQuery) ||
                                                itemCats.includes(currentSearchQuery) ||
                                                itemCatsNames.includes(currentSearchQuery);

                            // 2. Category
                            var matchesCategory = false;
                            if (currentCategoryFilter === 'all') {
                                matchesCategory = true;
                            } else {
                                var itemCatsArray = ($item.attr('data-cats') || '').split(' ');
                                matchesCategory = itemCatsArray.includes(currentCategoryFilter);
                            }

                            $item.toggle(matchesSearch && matchesCategory);
                        });

                        updateEmptyState($wsbList);
                    }

                    // Search input event
                    $field.find('.wsb-search').off('input.wsb').on('input.wsb', function () {
                        currentSearchQuery = ($(this).val() || '').toLowerCase().trim();
                        applyWsbFilters();
                    });

                    // Category tab click event
                    $catsWrap.on('click', '.wsb-cat-tab', function (e) {
                        e.preventDefault(); // Prevent default button behavior
                        $catsWrap.find('.wsb-cat-tab').removeClass('is-active');
                        $(this).addClass('is-active');

                        currentCategoryFilter = $(this).attr('data-cat') || 'all';
                        applyWsbFilters();
                    });

                    updateEmptyState($wsbList);
                }

                function wsbInit($field) {
                    const $flexibleField = $field.closest('.acf-field-flexible-content');
                    const isFirstLevelBuilder = $flexibleField.attr('data-name') === 'builder' && 
                                               $flexibleField.parents('.acf-field-flexible-content').length === 0;
                    if (!isFirstLevelBuilder) {
                        return;
                    }

                    if ($field.find('.wsb-ui').length) {
                        wsbBind($field);
                        return;
                    }

                    if (!$field.find('ul.acf-radio-list').length) return;

                    const $ui = $('<div class="wsb-ui"></div>');
                    const $left = $(
                        '<div class="wsb-left">' +
                        '<div class="wsb-search-wrap">' +
                        '<div class="wsb-search-inner">' +
                        '<svg class="wsb-search-ico" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">' +
                        '<circle cx="6.5" cy="6.5" r="4"/>' +
                        '<line x1="10" y1="10" x2="14" y2="14"/>' +
                        '</svg>' +
                        '<input type="text" class="wsb-search" placeholder="Search widgets…" />' +
                        '</div>' +
                        '</div>' +
                        '<div class="wsb-list"></div>' +
                        '</div>'
                    );

                    const $right = $(
                        '<div class="wsb-right">' +
                        '<div class="wsb-no-selection">' +
                        '<svg viewBox="0 0 36 36" fill="none" stroke="currentColor" stroke-width="1.2">' +
                        '<rect x="4" y="6" width="28" height="24" rx="2"/>' +
                        '<path d="M4 22l8-8 8 8 5-5 7 7"/>' +
                        '</svg>' +
                        '<span>Select a widget to preview it here</span>' +
                        '</div>' +
                        '</div>'
                    );

                    $ui.append($left).append($right);
                    $field.find('.acf-input').append($ui);

                    wsbBind($field);
                }

                function initAll() {
                    $('.wsb-radio-field').each(function () {
                        wsbInit($(this));
                    });
                    bindSaveAsWidgetButtons($(document));
                }

                initAll();

                $(document).on('acf/setup_fields', function (e, $el) {
                    ($el || $(document)).find('.wsb-radio-field').each(function () {
                        wsbInit($(this));
                    });
                    bindSaveAsWidgetButtons($el || $(document));
                });

                if (typeof acf !== 'undefined') {
                    acf.addAction('ready', initAll);
                    acf.addAction('append', function ($el) {
                        $el.find('.wsb-radio-field').each(function () {
                            wsbInit($(this));
                        });
                        bindSaveAsWidgetButtons($el);
                        window.setTimeout(function () {
                            bindSaveAsWidgetButtons($el);
                        }, 50);
                    });
                }

            });
        </script>
		<?php
	} );
