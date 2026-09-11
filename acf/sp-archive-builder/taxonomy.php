<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Taxonomy Archive Builder
 *
 * A term-based companion bundled with PHP Kit's Archive Builder. It deliberately emits
 * the same frontend data contract, so the existing section-archive.js handles
 * sorting, per-page changes, numbered pagination, load more and infinite scroll.
 *
 * A card template receives:
 * - term_id (int)
 * - term (WP_Term)
 * - archive_loop_index (int)
 * - archive_term_ids (int[])
 */

if ( ! function_exists( 'sp_taxonomy_archive_builder_choice_label' ) ) {
	function sp_taxonomy_archive_builder_choice_label( string $taxonomy, object $object ): string {
		$taxonomy_label = (string) ( $object->labels->menu_name ?? $object->label ?? $taxonomy );
		$post_type_labels = [];

		foreach ( (array) ( $object->object_type ?? [] ) as $post_type ) {
			$post_type = sanitize_key( (string) $post_type );
			if ( $post_type === '' ) {
				continue;
			}

			$post_type_object = function_exists( 'get_post_type_object' ) ? get_post_type_object( $post_type ) : null;
			$post_type_labels[] = is_object( $post_type_object )
				? (string) ( $post_type_object->labels->name ?? $post_type_object->label ?? $post_type )
				: $post_type;
		}

		$post_type_labels = array_values( array_unique( array_filter( $post_type_labels ) ) );
		$context          = implode( ', ', $post_type_labels );

		return sprintf(
			'%s%s (%s)',
			$context !== '' ? $context . ' — ' : '',
			$taxonomy_label,
			$taxonomy
		);
	}
}

if ( ! function_exists( 'sp_taxonomy_archive_builder_choices' ) ) {
	function sp_taxonomy_archive_builder_choices(): array {
		$choices = [];

		foreach ( get_taxonomies( [], 'objects' ) as $taxonomy => $object ) {
			if ( in_array( $taxonomy, [ 'nav_menu', 'link_category', 'post_format' ], true ) ) {
				continue;
			}

			if ( empty( $object->show_ui ) && empty( $object->public ) && empty( $object->publicly_queryable ) ) {
				continue;
			}

			$choices[ $taxonomy ] = sp_taxonomy_archive_builder_choice_label( $taxonomy, $object );
		}

		natcasesort( $choices );

		return $choices;
	}
}

if ( ! function_exists( 'sp_taxonomy_archive_builder_defaults' ) ) {
	function sp_taxonomy_archive_builder_defaults(): array {
		$taxonomies = array_keys( sp_taxonomy_archive_builder_choices() );

		return [
			'taxonomy'        => $taxonomies[0] ?? 'category',
			'source_mode'     => 'all',
			'manual_terms'    => [ 'mode' => 'manual', 'ids' => [] ],
			'hide_empty'      => 1,
			'top_level_only'  => 0,
			'filters_enabled' => 1,
			'filter_ui'       => 'select',
			'filter_terms_mode' => 'selected',
			'all_label'       => 'All',
			'filter_arg'      => '',
			'per_page'        => 9,
			'load_more_label' => 'Show More',
			'pagination_type' => 'pagination',
			'order_mode'      => 'az',
			'confirm'         => 0,
			'reset'           => 0,
			'action'          => 'sp_taxonomy_archive_query',
			'page_arg'        => 'sp_term_page',
			'url_page_arg'    => 'page',
			'sort_arg'        => '',
			'per_page_arg'    => 'per_page',
		];
	}
}

if ( ! function_exists( 'sp_taxonomy_archive_builder_normalize' ) ) {
	function sp_taxonomy_archive_builder_normalize( $value ): array {
		$value = wp_parse_args( is_array( $value ) ? $value : [], sp_taxonomy_archive_builder_defaults() );

		$taxonomy = sanitize_key( (string) $value['taxonomy'] );
		$value['taxonomy']       = taxonomy_exists( $taxonomy ) ? $taxonomy : 'category';
		$value['source_mode']    = ( $value['source_mode'] ?? 'all' ) === 'manual' ? 'manual' : 'all';
		$manual_value            = is_array( $value['manual_terms'] ?? null ) ? $value['manual_terms'] : [];
		$manual_ids              = $manual_value['ids'] ?? $manual_value;
		$value['manual_terms']   = [
			'mode' => 'manual',
			'ids'  => array_values( array_unique( array_filter( array_map( 'absint', is_array( $manual_ids ) ? $manual_ids : [] ) ) ) ),
		];
		$value['hide_empty']     = ! empty( $value['hide_empty'] ) ? 1 : 0;
		$value['top_level_only'] = ! empty( $value['top_level_only'] ) ? 1 : 0;
		$value['filters_enabled'] = ! empty( $value['filters_enabled'] ) ? 1 : 0;
		$value['confirm']        = ! empty( $value['confirm'] ) ? 1 : 0;
		$value['reset']          = ! empty( $value['reset'] ) ? 1 : 0;
		$value['per_page']       = sp_archive_normalize_per_page( $value['per_page'] ?? 9 );
		$value['pagination_type'] = sp_archive_normalize_mode( $value['pagination_type'] ?? 'pagination' );
		$value['order_mode']      = sp_archive_normalize_sort( $value['order_mode'] ?? 'az', 'az' );

		$value['load_more_label'] = sanitize_text_field( (string) ( $value['load_more_label'] ?? '' ) );
		if ( $value['load_more_label'] === '' ) {
			$value['load_more_label'] = 'Show More';
		}
		$value['all_label'] = sanitize_text_field( (string) ( $value['all_label'] ?? '' ) );
		if ( $value['all_label'] === '' ) {
			$value['all_label'] = 'All';
		}

		$filter_ui = sanitize_key( (string) ( $value['filter_ui'] ?? 'select' ) );
		$value['filter_ui'] = in_array( $filter_ui, [ 'buttons', 'select', 'multiselect', 'radio', 'checkbox' ], true )
			? $filter_ui
			: 'select';
		$filter_terms_mode = sanitize_key( (string) ( $value['filter_terms_mode'] ?? 'selected' ) );
		$value['filter_terms_mode'] = in_array( $filter_terms_mode, [ 'selected', 'children', 'parent' ], true )
			? $filter_terms_mode
			: 'selected';

		$value['action']       = sanitize_key( (string) ( $value['action'] ?? '' ) ) ?: 'sp_taxonomy_archive_query';
		$value['page_arg']     = sanitize_key( (string) ( $value['page_arg'] ?? '' ) ) ?: 'sp_term_page';
		$value['url_page_arg'] = sanitize_key( (string) ( $value['url_page_arg'] ?? '' ) ) ?: 'page';
		$value['sort_arg']     = sanitize_key( (string) ( $value['sort_arg'] ?? '' ) );
		$value['per_page_arg'] = sanitize_key( (string) ( $value['per_page_arg'] ?? '' ) ) ?: 'per_page';
		$value['filter_arg']   = sanitize_key( (string) ( $value['filter_arg'] ?? '' ) ) ?: $value['taxonomy'];

		return $value;
	}
}

if ( ! function_exists( 'sp_taxonomy_archive_filter_config' ) ) {
	function sp_taxonomy_archive_filter_config( array $config ): array {
		$config = sp_taxonomy_archive_builder_normalize( $config );
		if ( empty( $config['filters_enabled'] ) ) {
			return [];
		}

		return [ [
			'name'       => $config['taxonomy'],
			'taxonomy'   => $config['taxonomy'],
			'query_arg'  => $config['filter_arg'],
			'ui'         => $config['filter_ui'],
			'terms'      => [],
			'terms_mode' => $config['filter_terms_mode'],
		] ];
	}
}

if ( ! function_exists( 'sp_taxonomy_archive_uses_client_filter' ) ) {
	function sp_taxonomy_archive_uses_client_filter( array $config, int $per_page, array $archive_filters ): bool {
		return $per_page === -1
			&& ! empty( $archive_filters )
			&& ( $config['filter_terms_mode'] ?? 'selected' ) === 'selected';
	}
}

if ( ! function_exists( 'sp_taxonomy_archive_filter_terms' ) ) {
	/** @param WP_Term[] $terms */
	function sp_taxonomy_archive_filter_terms( array $terms, string $taxonomy, $value, string $terms_mode = 'selected' ): array {
		$selected = sp_archive_normalize_filter_terms( $value );
		if ( ! $selected ) {
			return $terms;
		}

		$allowed = $terms_mode === 'selected'
			? $selected
			: sp_archive_filter_scope_slugs( $taxonomy, $selected, $terms_mode );
		$allowed = array_flip( array_map( 'sanitize_title', $allowed ) );

		return array_values( array_filter( $terms, static function ( $term ) use ( $allowed ): bool {
			return $term instanceof WP_Term && isset( $allowed[ sanitize_title( (string) $term->slug ) ] );
		} ) );
	}
}

if ( ! function_exists( 'sp_taxonomy_archive_sort_terms' ) ) {
	/** @param WP_Term[] $terms */
	function sp_taxonomy_archive_sort_terms( array $terms, string $sort ): array {
		$sort = sp_archive_normalize_sort( $sort, 'az' );
		if ( $sort === 'manual' ) {
			return $terms;
		}

		usort( $terms, static function ( $left, $right ) use ( $sort ): int {
			if ( ! $left instanceof WP_Term || ! $right instanceof WP_Term ) {
				return 0;
			}

			switch ( $sort ) {
				case 'newest':
					return (int) $right->term_id <=> (int) $left->term_id;
				case 'oldest':
					return (int) $left->term_id <=> (int) $right->term_id;
				case 'za':
					$by_name = strcasecmp( (string) $right->name, (string) $left->name );
					break;
				case 'menu_order':
					$left_order  = get_term_meta( $left->term_id, '_sp_cm_order', true );
					$right_order = get_term_meta( $right->term_id, '_sp_cm_order', true );
					$left_order  = $left_order === '' || ! is_numeric( $left_order ) ? PHP_INT_MAX : (int) $left_order;
					$right_order = $right_order === '' || ! is_numeric( $right_order ) ? PHP_INT_MAX : (int) $right_order;

					if ( $left_order !== $right_order ) {
						return $left_order <=> $right_order;
					}
					$by_name = strcasecmp( (string) $left->name, (string) $right->name );
					break;
				case 'az':
				default:
					$by_name = strcasecmp( (string) $left->name, (string) $right->name );
					break;
			}

			return $by_name !== 0 ? $by_name : ( (int) $left->term_id <=> (int) $right->term_id );
		} );

		return $terms;
	}
}

if ( ! function_exists( 'sp_taxonomy_archive_prepare_query' ) ) {
	function sp_taxonomy_archive_prepare_query( array $config, int $paged = 1, string $sort = '', bool $ajax = false ): array {
		$config   = sp_taxonomy_archive_builder_normalize( $config );
		$per_page = (int) $config['per_page'];
		$paged    = $per_page === -1 ? 1 : max( 1, $paged );
		$is_manual = $config['source_mode'] === 'manual';
		$sort      = $is_manual ? 'manual' : sp_archive_normalize_sort( $sort, $config['order_mode'] );

		$args = [
			'taxonomy'   => $config['taxonomy'],
			'hide_empty' => ! empty( $config['hide_empty'] ),
		];
		if ( $config['source_mode'] === 'manual' ) {
			$manual_ids = $config['manual_terms']['ids'];
			if ( ! $manual_ids ) {
				return [ 'terms' => [], 'total_found' => 0, 'total_pages' => 1, 'current_page' => 1 ];
			}
			$args['include'] = $manual_ids;
		}
		if ( ! empty( $config['lang'] ) ) {
			$args['lang'] = sanitize_key( (string) $config['lang'] );
		}

		if ( ! empty( $config['top_level_only'] ) ) {
			$args['parent'] = 0;
		}

		$terms = get_terms( $args );
		$terms = is_wp_error( $terms ) ? [] : array_values( array_filter(
			(array) $terms,
			static fn( $term ): bool => $term instanceof WP_Term
		) );
		if ( $is_manual ) {
			$terms_by_id = [];
			foreach ( $terms as $term ) {
				$terms_by_id[ (int) $term->term_id ] = $term;
			}
			$terms = array_values( array_filter( array_map(
				static fn( int $term_id ) => $terms_by_id[ $term_id ] ?? null,
				$config['manual_terms']['ids']
			) ) );
		}
		if ( ! empty( $config['filters_enabled'] ) ) {
			$terms = sp_taxonomy_archive_filter_terms(
				$terms,
				$config['taxonomy'],
				$config['filter_values'] ?? [],
				$config['filter_terms_mode']
			);
		}
		if ( $sort === 'menu_order' && $terms && function_exists( 'update_termmeta_cache' ) ) {
			update_termmeta_cache( array_map( static fn( WP_Term $term ): int => (int) $term->term_id, $terms ) );
		}
		$terms = sp_taxonomy_archive_sort_terms( $terms, $sort );

		$total_found = count( $terms );
		$total_pages = $per_page === -1 ? 1 : max( 1, (int) ceil( $total_found / $per_page ) );
		$current_page = max( 1, min( $paged, $total_pages ) );
		$offset = $per_page === -1 ? 0 : ( $current_page - 1 ) * $per_page;
		$limit  = $per_page === -1 ? null : $per_page;

		if ( ! $ajax && in_array( $config['pagination_type'], [ 'load_more', 'infinity_scroll' ], true ) && $current_page > 1 ) {
			$offset = 0;
			$limit  = $per_page * $current_page;
		}

		$page_terms = $limit === null ? array_slice( $terms, $offset ) : array_slice( $terms, $offset, $limit );

		return [
			'terms'        => $page_terms,
			'total_found'  => $total_found,
			'total_pages'  => $total_pages,
			'current_page' => $current_page,
		];
	}
}

if ( ! function_exists( 'sp_taxonomy_archive_render_cards' ) ) {
	/** @param WP_Term[] $terms */
	function sp_taxonomy_archive_render_cards( array $terms, string $template, array $args = [] ): string {
		$template       = sp_archive_sanitize_template( $template );
		$empty_template = sp_archive_sanitize_template( $args['empty_template'] ?? '' );
		$template_args  = is_array( $args['template_args'] ?? null ) ? $args['template_args'] : [];
		$loop_index     = max( 0, (int) ( $args['start_index'] ?? 0 ) );
		$term_ids       = array_map( static fn( WP_Term $term ): int => (int) $term->term_id, $terms );

		ob_start();
		if ( $template !== '' && $terms ) {
			foreach ( $terms as $term ) {
				$item_args = sp_archive_template_args_for_index( $template_args, $loop_index );
				echo sp_archive_render_template( $template, array_merge( $item_args, [
					'term_id'            => (int) $term->term_id,
					'term'               => $term,
					'archive_loop_index' => $loop_index,
					'archive_term_ids'   => $term_ids,
				] ) );
				$loop_index++;
			}
		} elseif ( $empty_template !== '' ) {
			echo sp_archive_render_template( $empty_template );
		}

		return (string) ob_get_clean();
	}
}

if ( ! function_exists( '_sp_taxonomy_archive_ctx' ) ) {
	function _sp_taxonomy_archive_ctx( ?array $set = null ): ?array {
		static $context;
		if ( $set !== null ) {
			$context = $set;
		}

		return $context ?? null;
	}
}

if ( ! function_exists( 'sp_taxonomy_archive_setup' ) ) {
	function sp_taxonomy_archive_setup( array $config, string $card_template, array $options = [] ): void {
		$config           = sp_taxonomy_archive_builder_normalize( $config );
		$is_manual        = $config['source_mode'] === 'manual';
		$default_per_page = (int) $config['per_page'];
		$action           = sanitize_key( (string) ( $options['action'] ?? $config['action'] ) ) ?: 'sp_taxonomy_archive_query';
		$page_arg         = sanitize_key( (string) ( $options['page_arg'] ?? $config['page_arg'] ) ) ?: 'sp_term_page';
		$url_page_arg     = sanitize_key( (string) ( $options['url_page_arg'] ?? $config['url_page_arg'] ) ) ?: 'page';
		$sort_arg         = $is_manual ? '' : sanitize_key( (string) ( $options['sort_arg'] ?? $config['sort_arg'] ) );
		$per_page_arg     = sanitize_key( (string) ( $options['per_page_arg'] ?? $config['per_page_arg'] ) ) ?: 'per_page';
		$template_args    = is_array( $options['template_args'] ?? null ) ? $options['template_args'] : [];
		$empty_template   = sp_archive_sanitize_template( $options['empty_template'] ?? dirname( $card_template ) . '/empty' );
		$language         = sp_archive_current_language();
		$archive_filters  = sp_taxonomy_archive_filter_config( $config );
		$current_filters  = $archive_filters ? sp_archive_filter_values( $archive_filters, $_GET ) : [];
		$default_sort     = $is_manual ? 'manual' : $config['order_mode'];
		$current_sort     = ! $is_manual && $sort_arg !== '' && isset( $_GET[ $sort_arg ] )
			? sp_archive_normalize_sort( wp_unslash( $_GET[ $sort_arg ] ), $default_sort )
			: $default_sort;
		$current_per_page = isset( $_GET[ $per_page_arg ] )
			? sp_archive_normalize_per_page( wp_unslash( $_GET[ $per_page_arg ] ), $config['per_page'] )
			: (int) $config['per_page'];

		if ( $current_per_page === -1 && (int) $config['per_page'] !== -1 ) {
			$current_per_page = (int) $config['per_page'];
		}
		$client_filter_mode = sp_taxonomy_archive_uses_client_filter( $config, $current_per_page, $archive_filters );

		$query_config = array_merge( $config, [
			'per_page'      => $current_per_page,
			'filter_values' => $client_filter_mode ? [] : ( $current_filters[ $config['taxonomy'] ] ?? [] ),
		] );
		$paged = sp_archive_current_page( $page_arg, $url_page_arg );
		$query = sp_taxonomy_archive_prepare_query( $query_config, $paged, $current_sort, false );

		$token = sp_archive_register_config( [
			'entity_type'      => 'taxonomy',
			'taxonomy'         => $config['taxonomy'],
			'source_mode'      => $config['source_mode'],
			'manual_terms'     => $config['manual_terms'],
			'hide_empty'       => $config['hide_empty'],
			'top_level_only'   => $config['top_level_only'],
			'filters_enabled'  => $config['filters_enabled'],
			'filter_ui'        => $config['filter_ui'],
			'filter_terms_mode' => $config['filter_terms_mode'],
			'all_label'        => $config['all_label'],
			'filter_arg'       => $config['filter_arg'],
			'per_page'         => $default_per_page,
			'load_more_label'  => $config['load_more_label'],
			'pagination_type'  => $config['pagination_type'],
			'order_mode'       => $default_sort,
			'confirm'          => $config['confirm'],
			'reset'            => $config['reset'],
			'action'           => $action,
			'card_template'    => $card_template,
			'template_args'    => $template_args,
			'empty_template'   => $empty_template,
			'page_arg'         => $page_arg,
			'url_page_arg'     => $url_page_arg,
			'sort_arg'         => $sort_arg,
			'per_page_arg'     => $per_page_arg,
			'lang'             => $language,
		] );

		_sp_taxonomy_archive_ctx( [
			'config'           => $config,
			'action'           => $action,
			'page_arg'         => $page_arg,
			'url_page_arg'     => $url_page_arg,
			'sort_arg'         => $sort_arg,
			'per_page_arg'     => $per_page_arg,
			'default_sort'     => $default_sort,
			'current_sort'     => $current_sort,
			'current_per_page' => $current_per_page,
			'archive_filters'  => $archive_filters,
			'current_filters'  => $current_filters,
			'client_filter_mode' => $client_filter_mode,
			'card_template'    => $card_template,
			'template_args'    => $template_args,
			'empty_template'   => $empty_template,
			'archive_token'    => $token,
			'query'            => $query,
		] );
	}
}

if ( ! function_exists( 'sp_taxonomy_archive_attr' ) ) {
	function sp_taxonomy_archive_attr(): string {
		return 'data-sp-archive';
	}
}

if ( ! function_exists( 'sp_taxonomy_archive_config' ) ) {
	function sp_taxonomy_archive_config(): void {
		$context = _sp_taxonomy_archive_ctx();
		if ( ! $context ) {
			return;
		}

		$config = [
			'action'           => $context['action'],
			'archive_token'    => $context['archive_token'],
			'post_type'        => '',
			'template'         => $context['card_template'],
			'filters'          => $context['archive_filters'],
			'current_filters'  => $context['current_filters'],
			'per_page'         => $context['current_per_page'],
			'default_per_page' => $context['config']['per_page'],
			'page_arg'         => $context['page_arg'],
			'url_page_arg'     => $context['url_page_arg'],
			'sort_arg'         => $context['sort_arg'],
			'default_sort'     => $context['default_sort'],
			'sort_mode'        => $context['current_sort'],
			'per_page_arg'     => $context['per_page_arg'],
			'pagination_mode'  => $context['config']['pagination_type'],
			'confirm'          => $context['config']['confirm'],
			'reset'            => $context['config']['reset'],
			'disable_empty'    => false,
			'all_label'        => $context['config']['all_label'],
			'client_filter_mode' => $context['client_filter_mode'],
			'current_page'     => $context['query']['current_page'],
			'total_pages'      => $context['query']['total_pages'],
		];

		echo '<script type="application/json" data-sp-archive-config>'
			. wp_json_encode( $config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
			. "</script>\n";
	}
}

if ( ! function_exists( 'sp_taxonomy_archive_filters' ) ) {
	function sp_taxonomy_archive_filters( string $all_label = '', string $class = '' ): void {
		$context = _sp_taxonomy_archive_ctx();
		if ( ! $context || empty( $context['archive_filters'] ) ) {
			return;
		}

		$all_label = $all_label !== '' ? $all_label : $context['config']['all_label'];
		foreach ( $context['archive_filters'] as $filter ) {
			sp_archive_render_filter( $filter, $context['current_filters'], $all_label, $class );
		}
	}
}

if ( ! function_exists( 'sp_taxonomy_archive_sort' ) ) {
	function sp_taxonomy_archive_sort( array $options = [], string $title = '', string $class = '' ): void {
		$context = _sp_taxonomy_archive_ctx();
		if ( ! $context || $context['sort_arg'] === '' ) {
			return;
		}

		if ( ! $options ) {
			$options = [
				'newest'    => __( 'Newest first', THEME_SLUG ),
				'oldest'    => __( 'Oldest first', THEME_SLUG ),
				'az'        => __( 'A → Z', THEME_SLUG ),
				'za'        => __( 'Z → A', THEME_SLUG ),
				'menu_order' => __( 'Menu order', THEME_SLUG ),
			];
		} else {
			$normalized = [];
			foreach ( $options as $key => $option ) {
				if ( is_array( $option ) ) {
					$normalized[ $option['value'] ?? '' ] = $option['label'] ?? '';
				} else {
					$normalized[ $key ] = $option;
				}
			}
			$options = $normalized;
		}

		$template = sp_archive_component_template( 'select' );
		if ( $template !== '' ) {
			get_template_part( $template, null, [
				'name'    => $context['sort_arg'],
				'value'   => $context['current_sort'],
				'options' => $options,
				'title'   => $title ?: __( 'Sort:', THEME_SLUG ),
				'mode'    => 'single',
				'class'   => $class,
			] );
		}
	}
}

if ( ! function_exists( 'sp_taxonomy_archive_per_page' ) ) {
	function sp_taxonomy_archive_per_page( array $options = [], string $title = '', string $class = '' ): void {
		$context = _sp_taxonomy_archive_ctx();
		if ( ! $context ) {
			return;
		}

		if ( ! $options ) {
			$options = array_combine( range( 1, 24 ), range( 1, 24 ) );
			if ( (int) $context['config']['per_page'] === -1 ) {
				$options['all'] = __( 'Show all', THEME_SLUG );
			}
		}
		$template = sp_archive_component_template( 'select' );
		if ( $template !== '' ) {
			get_template_part( $template, null, [
				'name'    => $context['per_page_arg'],
				'value'   => $context['current_per_page'] === -1 ? 'all' : (string) $context['current_per_page'],
				'options' => $options,
				'title'   => $title ?: __( 'Categories per page:', THEME_SLUG ),
				'mode'    => 'single',
				'class'   => $class,
			] );
		}
	}
}

if ( ! function_exists( 'sp_taxonomy_archive_confirm' ) ) {
	function sp_taxonomy_archive_confirm( string $label = '', string $class = '' ): void {
		$context = _sp_taxonomy_archive_ctx();
		if ( ! $context || empty( $context['config']['confirm'] ) ) {
			return;
		}

		echo '<button class="' . esc_attr( sp_archive_sanitize_class_string( $class ) ) . '" type="button" data-sp-archive-confirm disabled>'
			. '<span>' . esc_html( $label ?: __( 'Apply', THEME_SLUG ) ) . '</span></button>';
	}
}

if ( ! function_exists( 'sp_taxonomy_archive_reset' ) ) {
	function sp_taxonomy_archive_reset( string $label = '', string $class = '' ): void {
		$context = _sp_taxonomy_archive_ctx();
		if ( ! $context || empty( $context['config']['reset'] ) ) {
			return;
		}

		$active = array_filter( $context['current_filters'] )
			|| $context['current_sort'] !== $context['default_sort']
			|| $context['current_per_page'] !== (int) $context['config']['per_page'];
		echo '<button class="' . esc_attr( sp_archive_sanitize_class_string( $class ) ) . '" type="button" data-sp-archive-reset' . ( $active ? '' : ' disabled' ) . '>'
			. '<span>' . esc_html( $label ?: __( 'Reset', THEME_SLUG ) ) . '</span></button>';
	}
}

if ( ! function_exists( 'sp_taxonomy_archive_cards' ) ) {
	function sp_taxonomy_archive_cards( string $class = '' ): void {
		$context = _sp_taxonomy_archive_ctx();
		if ( ! $context ) {
			return;
		}

		$query = $context['query'];
		$client_filter_values = $context['client_filter_mode']
			? sp_archive_normalize_filter_terms( $context['current_filters'][ $context['config']['taxonomy'] ] ?? [] )
			: [];
		$start = $context['config']['pagination_type'] === 'pagination'
			? ( $query['current_page'] - 1 ) * $context['current_per_page']
			: 0;
		?>
		<div class="<?php echo esc_attr( $class ); ?>" data-sp-archive-list data-loader="false" data-total="<?php echo esc_attr( (string) $query['total_found'] ); ?>">
			<?php echo sp_taxonomy_archive_render_cards( $query['terms'], $context['card_template'], [
				'empty_template' => $context['empty_template'],
				'template_args'  => array_merge( $context['template_args'], [
					'archive_client_filter' => $context['client_filter_mode'],
					'archive_filter_values' => $client_filter_values,
				] ),
				'start_index'    => $start,
			] ); ?>
		</div>
		<?php
	}
}

if ( ! function_exists( 'sp_taxonomy_archive_pagination' ) ) {
	function sp_taxonomy_archive_pagination( string $class = '' ): void {
		$context = _sp_taxonomy_archive_ctx();
		if ( ! $context ) {
			return;
		}

		$query = $context['query'];
		ob_start();
		sp_archive_render_pagination( [
			'current'          => $query['current_page'],
			'total'            => $query['total_pages'],
			'mode'             => $context['config']['pagination_type'],
			'load_more_label'  => $context['config']['load_more_label'],
			'action'           => $context['action'],
			'page_arg'         => $context['page_arg'],
			'url_page_arg'     => $context['url_page_arg'],
			'pagination_data'  => [
				'archive_token'  => $context['archive_token'],
				'per_page'       => $context['current_per_page'],
				'query_arg'      => $context['page_arg'],
				'url_query_arg'  => $context['url_page_arg'],
				'sort'           => $context['current_sort'],
				'pagination_mode' => $context['config']['pagination_type'],
			],
		] );
		$pagination = trim( (string) ob_get_clean() );

		echo '<div class="' . esc_attr( $class ) . '" data-sp-archive-pagination>' . $pagination . '</div>';
	}
}

if ( ! function_exists( 'sp_taxonomy_archive_render' ) ) {
	function sp_taxonomy_archive_render( array $config, string $card_template, array $options = [], string $section_class = 'sp-taxonomy-archive' ): void {
		sp_taxonomy_archive_setup( $config, $card_template, $options );
		?>
		<section class="<?php echo esc_attr( $section_class ); ?>" <?php echo sp_taxonomy_archive_attr(); ?>>
			<?php sp_taxonomy_archive_filters(); ?>
			<?php sp_taxonomy_archive_sort(); ?>
			<?php sp_taxonomy_archive_cards( $options['list_class'] ?? '' ); ?>
			<?php sp_taxonomy_archive_pagination(); ?>
			<?php sp_taxonomy_archive_config(); ?>
		</section>
		<?php
	}
}

if ( ! function_exists( 'sp_taxonomy_archive_ajax_query' ) ) {
	function sp_taxonomy_archive_ajax_query(): void {
		nocache_headers();
		$source = wp_unslash( $_POST );
		$nonce_action = sanitize_key( (string) apply_filters( 'sp_archive_nonce_action', 'ajax_global' ) ) ?: 'ajax_global';

		if ( ! wp_verify_nonce( (string) ( $source['nonce'] ?? '' ), $nonce_action ) ) {
			wp_send_json_error( [ 'code' => 'invalid_nonce' ] );
		}

		$token  = sanitize_key( (string) ( $source['archive_token'] ?? '' ) );
		$config = sp_archive_get_config( $token );
		if ( ! $config ) {
			wp_send_json_error( [ 'code' => 'config_expired', 'reload' => true ] );
		}
		if ( ( $config['entity_type'] ?? '' ) !== 'taxonomy' || ! taxonomy_exists( $config['taxonomy'] ?? '' ) ) {
			wp_send_json_error( [ 'code' => 'invalid_config' ] );
		}

		$language = sanitize_key( (string) ( $config['lang'] ?? '' ) );
		if ( $language !== '' && function_exists( 'PLL' ) ) {
			$language_object = PLL()->model->get_language( $language );
			if ( $language_object ) {
				PLL()->curlang = $language_object;
				if ( ! empty( $language_object->locale ) ) {
					switch_to_locale( (string) $language_object->locale );
				}
			}
		} elseif ( $language !== '' && has_action( 'wpml_switch_language' ) ) {
			do_action( 'wpml_switch_language', $language );
		}

		$default_per_page = sp_archive_normalize_per_page( $config['per_page'] ?? 9 );
		$requested        = sp_archive_normalize_per_page( $source['per_page'] ?? $default_per_page, $default_per_page );
		$max_per_page     = max( 1, min( 100, (int) apply_filters( 'sp_taxonomy_archive_ajax_max_per_page', 48, $config ) ) );
		$config['per_page'] = $requested === -1 && $default_per_page === -1
			? -1
			: min( $max_per_page, $requested > 0 ? $requested : max( 1, $default_per_page ) );

		$sort = ! empty( $config['sort_arg'] ) && isset( $source['sort'] )
			? sp_archive_normalize_sort( $source['sort'], $config['order_mode'] )
			: $config['order_mode'];
		$archive_filters = sp_taxonomy_archive_filter_config( $config );
		$current_filters = $archive_filters ? sp_archive_filter_values( $archive_filters, $source ) : [];
		$client_filter_mode = sp_taxonomy_archive_uses_client_filter( $config, (int) $config['per_page'], $archive_filters );
		$config['filter_values'] = $client_filter_mode ? [] : ( $current_filters[ $config['taxonomy'] ] ?? [] );
		$query = sp_taxonomy_archive_prepare_query( $config, max( 1, (int) ( $source['paged'] ?? 1 ) ), $sort, true );
		$start = ( $query['current_page'] - 1 ) * $config['per_page'];
		$html  = sp_taxonomy_archive_render_cards( $query['terms'], $config['card_template'], [
			'empty_template' => $config['empty_template'] ?? '',
			'template_args'  => array_merge( $config['template_args'] ?? [], [
				'archive_client_filter' => $client_filter_mode,
				'archive_filter_values' => sp_archive_normalize_filter_terms( $current_filters[ $config['taxonomy'] ] ?? [] ),
			] ),
			'start_index'    => max( 0, $start ),
		] );

		$pagination = '';
		if ( $config['pagination_type'] === 'pagination' && $query['total_pages'] > 1 ) {
			ob_start();
			sp_archive_render_pagination( [
				'current'          => $query['current_page'],
				'total'            => $query['total_pages'],
				'mode'             => $config['pagination_type'],
				'load_more_label'  => $config['load_more_label'],
				'action'           => $config['action'],
				'page_arg'         => $config['page_arg'],
				'url_page_arg'     => $config['url_page_arg'],
				'pagination_data'  => [
					'archive_token'   => $token,
					'per_page'        => $config['per_page'],
					'query_arg'       => $config['page_arg'],
					'url_query_arg'   => $config['url_page_arg'],
					'sort'            => $sort,
					'pagination_mode' => $config['pagination_type'],
				],
			] );
			$pagination = trim( (string) ob_get_clean() );
		}

		wp_send_json_success( [
			'html'         => $html,
			'pagination'   => $pagination,
			'found'        => $query['total_found'],
			'max_pages'    => $query['total_pages'],
			'current_page' => $query['current_page'],
			'has_next'     => $query['current_page'] < $query['total_pages'],
		] );
	}
}

add_action( 'wp_ajax_sp_taxonomy_archive_query', 'sp_taxonomy_archive_ajax_query' );
add_action( 'wp_ajax_nopriv_sp_taxonomy_archive_query', 'sp_taxonomy_archive_ajax_query' );

add_action( 'acf/include_field_types', static function (): void {
	if ( ! class_exists( 'acf_field' ) || class_exists( 'SP_ACF_Field_Taxonomy_Archive_Builder', false ) ) {
		return;
	}

	class SP_ACF_Field_Taxonomy_Archive_Builder extends acf_field {
		public function initialize(): void {
			$this->name     = 'taxonomy_archive_builder';
			$this->label    = __( 'Taxonomy Archive Builder', 'acf' );
			$this->category = 'layout';
			$this->defaults = sp_taxonomy_archive_builder_defaults();
		}

		public function render_field_settings( array $field ): void {
			$settings = [
				[ 'Target Taxonomy', 'Select the taxonomy whose terms will be rendered.', 'select', 'taxonomy', [ 'choices' => sp_taxonomy_archive_builder_choices(), 'ui' => 0 ] ],
				[ 'Hide Empty Terms', 'Exclude terms that have no assigned posts.', 'true_false', 'hide_empty', [ 'ui' => 1 ] ],
				[ 'Top-level Terms Only', 'Exclude child terms from the archive.', 'true_false', 'top_level_only', [ 'ui' => 1 ] ],
				[ 'Enable Filters', 'Show a category filter for this archive field.', 'true_false', 'filters_enabled', [ 'ui' => 1 ] ],
				[ 'Filter Type', 'Choose how the category filter is displayed.', 'select', 'filter_ui', [ 'choices' => [ 'buttons' => 'Buttons', 'select' => 'Select', 'multiselect' => 'Multi-select', 'radio' => 'Radio', 'checkbox' => 'Checkbox' ], 'ui' => 0 ] ],
				[ 'Filter Scope', 'Choose whether filtering shows selected terms, their children, or their parents.', 'select', 'filter_terms_mode', [ 'choices' => [ 'selected' => 'Selected', 'children' => 'Children', 'parent' => 'Parent' ], 'ui' => 0 ] ],
				[ 'All Categories Label', 'Label used for the empty filter option.', 'text', 'all_label', [ 'placeholder' => 'All' ] ],
				[ 'Filter argument', 'URL/query key used by the category filter. Leave empty to use the taxonomy slug.', 'text', 'filter_arg', [ 'placeholder' => 'category_filter' ] ],
				[ 'Confirm Button', 'Allow templates to render an apply button for sorting and per-page controls.', 'true_false', 'confirm', [ 'ui' => 1 ] ],
				[ 'Reset Button', 'Allow templates to render a reset button.', 'true_false', 'reset', [ 'ui' => 1 ] ],
				[ 'Load more button text', 'Text rendered inside the load-more button.', 'text', 'load_more_label', [ 'placeholder' => 'Show More' ] ],
				[ 'AJAX action', 'WordPress AJAX action used by this archive.', 'text', 'action', [ 'placeholder' => 'sp_taxonomy_archive_query' ] ],
				[ 'Internal page argument', 'Request key sent to AJAX pagination.', 'text', 'page_arg', [ 'placeholder' => 'sp_term_page' ] ],
				[ 'URL page argument', 'URL query key used for pagination links.', 'text', 'url_page_arg', [ 'placeholder' => 'page' ] ],
				[ 'Sort argument', 'URL/query key used by the sort control. Leave empty to hide it.', 'text', 'sort_arg', [ 'placeholder' => 'category_sort' ] ],
				[ 'Per-page argument', 'URL/query key used by the terms-per-page control.', 'text', 'per_page_arg', [ 'placeholder' => 'per_page' ] ],
			];

			foreach ( $settings as [ $label, $instructions, $type, $name, $extra ] ) {
				acf_render_field_setting( $field, array_merge( [
					'label'        => __( $label, 'acf' ),
					'instructions' => __( $instructions, 'acf' ),
					'type'         => $type,
					'name'         => $name,
				], $extra ) );
			}
		}

		public function render_field( array $field ): void {
			$value    = sp_taxonomy_archive_builder_normalize( array_merge( $field, is_array( $field['value'] ?? null ) ? $field['value'] : [] ) );
			$name     = esc_attr( $field['name'] );
			$choices  = sp_taxonomy_archive_builder_choices();
			$tax_name = $choices[ $value['taxonomy'] ] ?? $value['taxonomy'];
			$per_page_choices = self::per_page_choices( $field );
			if ( ! array_key_exists( (int) $value['per_page'], $per_page_choices ) ) {
				$value['per_page'] = (int) array_key_first( $per_page_choices );
			}
			?>
			<div class="sp-archive-builder-card">
				<div class="sp-archive-builder-card__header"><span class="dashicons dashicons-category"></span><strong><?php printf( esc_html__( 'Taxonomy Archive Settings (%s)', 'acf' ), esc_html( $tax_name ) ); ?></strong></div>
				<div class="sp-archive-builder-card__grid">
					<div class="sp-archive-builder-card__field sp-archive-builder-card__field--source">
						<label><?php esc_html_e( 'Content source', 'acf' ); ?></label>
						<div class="sp-archive-builder-card__segmented sp-archive-builder-card__segmented--source">
							<?php foreach ( [ 'all' => 'All categories', 'manual' => 'Manual' ] as $mode => $label ) : ?>
								<label class="sp-archive-builder-card__segment"><input type="radio" name="<?php echo $name; ?>[source_mode]" value="<?php echo esc_attr( $mode ); ?>" <?php checked( $value['source_mode'], $mode ); ?>><span><?php echo esc_html__( $label, 'acf' ); ?></span></label>
							<?php endforeach; ?>
						</div>
					</div>
					<div class="sp-archive-builder-card__field"><label><?php esc_html_e( 'Number of categories', 'acf' ); ?></label><select name="<?php echo $name; ?>[per_page]">
						<?php foreach ( $per_page_choices as $number => $label ) : ?>
							<option value="<?php echo esc_attr( $number === -1 ? 'all' : (string) $number ); ?>" <?php selected( $value['per_page'], $number ); ?>><?php echo esc_html( (string) $label ); ?></option>
						<?php endforeach; ?>
					</select></div>
					<div class="sp-archive-builder-card__field"><label><?php esc_html_e( 'Pagination type', 'acf' ); ?></label><div class="sp-archive-builder-card__segmented">
						<?php foreach ( [ 'pagination' => 'Pagination', 'load_more' => 'Load more', 'infinity_scroll' => 'Infinite scroll' ] as $mode => $label ) : ?>
							<label class="sp-archive-builder-card__segment"><input type="radio" name="<?php echo $name; ?>[pagination_type]" value="<?php echo esc_attr( $mode ); ?>" <?php checked( $value['pagination_type'], $mode ); ?>><span><?php echo esc_html__( $label, 'acf' ); ?></span></label>
						<?php endforeach; ?>
					</div></div>
					<div class="sp-archive-builder-card__field sp-archive-builder-card__field--sorting" <?php echo $value['source_mode'] === 'manual' ? 'hidden' : ''; ?>><label><?php esc_html_e( 'Sorting order', 'acf' ); ?></label><select name="<?php echo $name; ?>[order_mode]">
						<?php foreach ( [ 'newest' => 'Newest first', 'oldest' => 'Oldest first', 'az' => 'Alphabetical (A–Z)', 'za' => 'Alphabetical (Z–A)', 'menu_order' => 'Menu order' ] as $sort => $label ) : ?>
							<option value="<?php echo esc_attr( $sort ); ?>" <?php selected( $value['order_mode'], $sort ); ?>><?php echo esc_html__( $label, 'acf' ); ?></option>
						<?php endforeach; ?>
					</select></div>
				</div>
				<div class="sp-archive-builder-card__manual" <?php echo $value['source_mode'] === 'manual' ? '' : 'hidden'; ?>>
					<div class="sp-archive-builder-card__manual-header"><strong><?php esc_html_e( 'Selected categories', 'acf' ); ?></strong><span><?php esc_html_e( 'Pagination and filters are applied only to this selection. Drag categories to set their display order.', 'acf' ); ?></span></div>
					<?php
					$smart_taxonomy = function_exists( 'acf_get_field_type' ) ? acf_get_field_type( 'smart_taxonomy' ) : null;
					if ( $smart_taxonomy && method_exists( $smart_taxonomy, 'render_field' ) ) {
						$smart_taxonomy->render_field( [
							'key'           => ( $field['key'] ?? 'taxonomy_archive_builder' ) . '_manual_terms',
							'name'          => $field['name'] . '[manual_terms]',
							'type'          => 'smart_taxonomy',
							'value'         => $value['manual_terms'],
							'taxonomy'      => [ $value['taxonomy'] ],
							'return_format' => 'id',
							'modes'         => [ 'manual' ],
							'default_mode'  => 'manual',
							'thumb_field'   => 'image',
							'min'           => 0,
							'max'           => 0,
						] );
					} else {
						echo '<p class="description">' . esc_html__( 'Enable the Smart Taxonomy component to use Manual mode.', 'acf' ) . '</p>';
					}
					?>
				</div>
			</div>
			<?php
		}

		public function update_value( $value, $post_id, array $field ) {
			$value    = is_array( $value ) ? $value : [];
			$choices  = array_keys( self::per_page_choices( $field ) );
			$per_page = sp_archive_normalize_per_page( $value['per_page'] ?? $field['per_page'] ?? 9 );
			if ( ! in_array( $per_page, $choices, true ) ) {
				$per_page = in_array( 9, $choices, true ) ? 9 : (int) reset( $choices );
			}

			$source_mode = ( $value['source_mode'] ?? 'all' ) === 'manual' ? 'manual' : 'all';
			$manual_value = is_array( $value['manual_terms'] ?? null ) ? $value['manual_terms'] : [];
			$manual_ids = array_values( array_unique( array_filter( array_map(
				'absint',
				is_array( $manual_value['ids'] ?? null ) ? $manual_value['ids'] : []
			) ) ) );
			$taxonomy = sanitize_key( (string) ( $field['taxonomy'] ?? 'category' ) );
			$manual_ids = array_values( array_filter( $manual_ids, static function ( int $term_id ) use ( $taxonomy ): bool {
				$term = get_term( $term_id, $taxonomy );
				return $term instanceof WP_Term;
			} ) );

			return [
				'source_mode'     => $source_mode,
				'manual_terms'    => [ 'mode' => 'manual', 'ids' => $manual_ids ],
				'per_page'        => $per_page,
				'pagination_type' => sp_archive_normalize_mode( $value['pagination_type'] ?? 'pagination' ),
				'order_mode'      => sp_archive_normalize_sort( $value['order_mode'] ?? 'az', 'az' ),
			];
		}

		public function format_value( $value, $post_id, array $field ) {
			$value = is_array( $value ) ? $value : [];
			$defaults = sp_taxonomy_archive_builder_defaults();
			foreach ( array_keys( $defaults ) as $key ) {
				if ( in_array( $key, [ 'source_mode', 'manual_terms', 'per_page', 'pagination_type', 'order_mode' ], true ) ) {
					if ( ! array_key_exists( $key, $value ) ) {
						$value[ $key ] = $field[ $key ] ?? $defaults[ $key ];
					}
					continue;
				}
				$value[ $key ] = $field[ $key ] ?? $defaults[ $key ];
			}

			$value   = sp_taxonomy_archive_builder_normalize( $value );
			$choices = array_keys( self::per_page_choices( $field ) );
			if ( ! in_array( (int) $value['per_page'], $choices, true ) ) {
				$value['per_page'] = in_array( (int) ( $field['per_page'] ?? 9 ), $choices, true )
					? (int) $field['per_page']
					: (int) reset( $choices );
			}

			return $value;
		}

		public function input_admin_enqueue_scripts(): void {
			$this->enqueue_styles();
		}

		public function field_group_admin_enqueue_scripts(): void {
			$this->enqueue_styles();
		}

		private function enqueue_styles(): void {
			$archive_builder = function_exists( 'acf_get_field_type' ) ? acf_get_field_type( 'archive_builder' ) : null;
			if ( $archive_builder && method_exists( $archive_builder, 'input_admin_enqueue_scripts' ) ) {
				$archive_builder->input_admin_enqueue_scripts();
			}
		}

		private static function per_page_choices( array $field ): array {
			$configured = is_array( $field['per_page_choices'] ?? null ) ? $field['per_page_choices'] : [];
			$choices    = [];
			foreach ( $configured ?: range( 1, 24 ) as $number ) {
				if ( $number === 'all' || (int) $number === -1 ) {
					$choices[-1] = __( 'Show all', 'acf' );
				} elseif ( (int) $number > 0 ) {
					$choices[ (int) $number ] = sprintf( _n( '%d category', '%d categories', (int) $number, 'acf' ), (int) $number );
				}
			}
			if ( ! $configured ) {
				$choices[-1] = __( 'Show all', 'acf' );
			}

			return $choices;
		}
	}

	acf_register_field_type( 'SP_ACF_Field_Taxonomy_Archive_Builder' );
} );

if ( ! function_exists( 'taxonomy_archive_builder' ) && class_exists( 'StoutLogic\\AcfBuilder\\FieldsBuilder' ) ) {
	function taxonomy_archive_builder( string $name, array $args = [] ): StoutLogic\AcfBuilder\FieldsBuilder {
		$builder = new StoutLogic\AcfBuilder\FieldsBuilder( $name . '_taxonomy_archive_builder' );
		$builder->addField( $name, 'taxonomy_archive_builder', $args );

		return $builder;
	}
}
