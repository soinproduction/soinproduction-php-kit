<?php
	/**
	 * Plugin Name: SP Content Manager
	 * Description: Duplicate posts/pages/CPTs and reorder posts + terms + admin menu via drag and drop.
	 * Version: 1.2.0
	 */

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	if ( ! class_exists( 'SP_Content_Manager' ) ) {
		class SP_Content_Manager {
			private const OPT_KEY        = 'sp_content_manager_cfg';
			private const PAGE_SLUG      = 'sp-content-manager';
			private const NONCE_ACTION   = 'sp_content_manager_admin';
			private const VERSION        = '1.2.0';
			private const TERM_ORDER_KEY = '_sp_cm_order';

			private static ?self $instance = null;
			private $skip_term_order_filter = false;

			public static function get(): self {
				if ( ! self::$instance ) {
					self::$instance = new self();
				}

				return self::$instance;
			}

			private function __construct() {
				add_action( 'admin_menu', [ $this, 'menu' ] );
				add_action( 'admin_enqueue_scripts', [ $this, 'admin_assets' ] );
				add_action( 'admin_notices', [ $this, 'maybe_render_admin_notice' ] );

				add_filter( 'post_row_actions', [ $this, 'add_duplicate_link' ], 10, 2 );
				add_filter( 'page_row_actions', [ $this, 'add_duplicate_link' ], 10, 2 );
				add_action( 'admin_action_sp_cm_duplicate_post', [ $this, 'handle_duplicate_post' ] );

				add_action( 'wp_ajax_sp_cm_save_settings', [ $this, 'ajax_save_settings' ] );
				add_action( 'wp_ajax_sp_cm_save_post_order', [ $this, 'ajax_save_post_order' ] );
				add_action( 'wp_ajax_sp_cm_save_term_order', [ $this, 'ajax_save_term_order' ] );

				add_action( 'pre_get_posts', [ $this, 'apply_admin_post_order' ] );
				add_filter( 'get_terms_args', [ $this, 'apply_admin_term_order' ], 10, 2 );

				add_filter( 'custom_menu_order', [ $this, 'enable_custom_menu_order' ] );
				add_filter( 'menu_order', [ $this, 'apply_custom_menu_order' ] );
				add_action( 'admin_menu', [ $this, 'apply_custom_submenu_order_runtime' ], 999 );
				add_action( 'admin_menu', [ $this, 'apply_custom_menu_visibility_runtime' ], 1000 );
			}

			private function get_sortable_post_type_choices(): array {
				$objects = get_post_types( [ 'show_ui' => true ], 'objects' );
				$out     = [];

				foreach ( $objects as $slug => $obj ) {
					$slug = sanitize_key( (string) $slug );
					if ( $slug === '' || in_array( $slug, [ 'attachment', 'revision', 'nav_menu_item' ], true ) ) {
						continue;
					}
					if ( empty( $obj->show_ui ) ) {
						continue;
					}

					$label       = (string) ( $obj->labels->name ?? $obj->label ?? $slug );
					$out[ $slug ] = $label !== '' ? $label : $slug;
				}

				natcasesort( $out );

				return $out;
			}

			private function get_sortable_taxonomy_choices(): array {
				$objects = get_taxonomies( [ 'show_ui' => true ], 'objects' );
				$out     = [];

				foreach ( $objects as $slug => $obj ) {
					$slug = sanitize_key( (string) $slug );
					if ( $slug === '' || in_array( $slug, [ 'nav_menu' ], true ) ) {
						continue;
					}
					if ( empty( $obj->show_ui ) ) {
						continue;
					}

					$label       = (string) ( $obj->labels->name ?? $obj->label ?? $slug );
					$out[ $slug ] = $label !== '' ? $label : $slug;
				}

				natcasesort( $out );

				return $out;
			}

			private function get_admin_menu_items(): array {
				global $menu;

				$out = [];
				if ( ! is_array( $menu ) ) {
					return $out;
				}

				foreach ( $menu as $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}

					$slug = $this->normalize_menu_slug( (string) ( $row[2] ?? '' ) );
					if ( $slug === '' ) {
						continue;
					}

					$title = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) ( $row[0] ?? $slug ) ) ) );
					if ( $title === '' ) {
						$title = $slug;
					}

					$out[ $slug ] = [
						'slug'  => $slug,
						'title' => $title,
					];
				}

				return $out;
			}

			private function get_admin_submenu_items(): array {
				global $submenu;

				$out = [];
				if ( ! is_array( $submenu ) ) {
					return $out;
				}

				foreach ( $submenu as $parent_raw_slug => $rows ) {
					$parent_slug = $this->normalize_menu_slug( (string) $parent_raw_slug );
					if ( $parent_slug === '' || ! is_array( $rows ) ) {
						continue;
					}

					$items = [];
					foreach ( $rows as $row ) {
						if ( ! is_array( $row ) ) {
							continue;
						}

						$slug = $this->normalize_menu_slug( (string) ( $row[2] ?? '' ) );
						if ( $slug === '' ) {
							continue;
						}

						$title = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) ( $row[0] ?? $slug ) ) ) );
						if ( $title === '' ) {
							$title = $slug;
						}

						$items[] = [
							'slug'       => $slug,
							'title'      => $title,
							'parent_slug' => $parent_slug,
						];
					}

					if ( $items ) {
						$out[ $parent_slug ] = $items;
					}
				}

				return $out;
			}

			private function defaults(): array {
				return [
					'enable_duplicate' => 1,
					'enable_post_sort' => 1,
					'enable_term_sort' => 1,
					'enable_menu_sort' => 0,
					'enable_submenu_sort' => 0,
					'post_types'       => array_keys( $this->get_sortable_post_type_choices() ),
					'taxonomies'       => array_keys( $this->get_sortable_taxonomy_choices() ),
					'menu_order'       => [],
					'submenu_order'    => [],
					'hidden_menu'      => [],
					'hidden_submenu'   => [],
				];
			}

			private function cfg(): array {
				$raw = get_option( self::OPT_KEY, [] );
				if ( ! is_array( $raw ) ) {
					$raw = [];
				}

				$cfg                = array_merge( $this->defaults(), $raw );
				$cfg['post_types']  = array_values( array_unique( array_filter( array_map( 'sanitize_key', (array) $cfg['post_types'] ) ) ) );
				$cfg['taxonomies']  = array_values( array_unique( array_filter( array_map( 'sanitize_key', (array) $cfg['taxonomies'] ) ) ) );
				$cfg['menu_order']  = array_values( array_unique( array_filter( array_map( 'strval', (array) $cfg['menu_order'] ) ) ) );
				$cfg['submenu_order'] = $this->sanitize_submenu_order( $cfg['submenu_order'] ?? [] );
				$cfg['hidden_menu']  = $this->sanitize_slug_list( $cfg['hidden_menu'] ?? [] );
				$cfg['hidden_submenu'] = $this->sanitize_submenu_order( $cfg['hidden_submenu'] ?? [] );

				return $cfg;
			}

			private function sanitize_key_list( $value ): array {
				$list = is_array( $value ) ? $value : [];
				$list = array_map(
					static function ( $item ): string {
						return sanitize_key( (string) $item );
					},
					$list
				);

				return array_values( array_unique( array_filter( $list ) ) );
			}

			private function normalize_menu_slug( string $slug ): string {
				$slug = trim( $slug );
				if ( $slug === '' ) {
					return '';
				}

				// Guard against malformed or recursively expanded slugs
				// (for example customize.php?return=...return=...),
				// which may cause huge memory usage during normalization.
				if ( strlen( $slug ) > 3000 ) {
					$slug = substr( $slug, 0, 3000 );
				}

				$parts = wp_parse_url( $slug );
				if ( ! is_array( $parts ) ) {
					return $slug;
				}

				$base = trim( (string) ( $parts['path'] ?? $slug ) );
				if ( $base === '' ) {
					$base = $slug;
				}

				$query_args = [];
				if ( ! empty( $parts['query'] ) ) {
					$query_raw = (string) $parts['query'];

					// Do not parse very large query strings to avoid OOM.
					if ( strlen( $query_raw ) <= 1200 ) {
						parse_str( $query_raw, $query_args );
						if ( ! is_array( $query_args ) ) {
							$query_args = [];
						}
					}
				}

				unset(
					$query_args['return'],
					$query_args['_wpnonce'],
					$query_args['_wp_http_referer'],
					$query_args['_locale'],
					$query_args['nonce'],
					$query_args['message']
				);

				if ( count( $query_args ) > 40 ) {
					$query_args = array_slice( $query_args, 0, 40, true );
				}

				foreach ( $query_args as $k => $v ) {
					if ( is_array( $v ) ) {
						// Skip array values; they are not needed for menu matching
						// and may be extremely large.
						unset( $query_args[ $k ] );
						continue;
					}
					$query_args[ $k ] = (string) $v;
				}

				$query_args = array_filter(
					$query_args,
					static fn( string $v ): bool => trim( $v ) !== ''
				);
				ksort( $query_args );

				if ( ! $query_args ) {
					return $base;
				}

				return $base . '?' . http_build_query( $query_args, '', '&', PHP_QUERY_RFC3986 );
			}

			private function sanitize_slug_list( $value ): array {
				$list = is_array( $value ) ? $value : [];
				$out  = [];
				$max_items = 500;
				$count = 0;
				foreach ( $list as $item ) {
					$count++;
					if ( $count > $max_items ) {
						break;
					}

					$slug = $this->normalize_menu_slug( (string) $item );
					if ( $slug === '' || in_array( $slug, $out, true ) ) {
						continue;
					}
					$out[] = $slug;
				}

				return $out;
			}

			private function sanitize_submenu_order( $value ): array {
				if ( ! is_array( $value ) ) {
					return [];
				}

				$out = [];
				foreach ( $value as $parent_slug => $children ) {
					$parent_slug = trim( (string) $parent_slug );
					if ( $parent_slug === '' ) {
						continue;
					}

					$child_list = $this->sanitize_slug_list( $children );
					if ( ! $child_list ) {
						continue;
					}

					$out[ $parent_slug ] = $child_list;
				}

				return $out;
			}

			private function sanitize_cfg( array $raw ): array {
				$defaults       = $this->defaults();
				$allowed_pt     = array_keys( $this->get_sortable_post_type_choices() );
				$allowed_tax    = array_keys( $this->get_sortable_taxonomy_choices() );
				$menu_items     = $this->get_admin_menu_items();
				$submenu_items  = $this->get_admin_submenu_items();
				$allowed_menu   = array_keys( $menu_items );
				$allowed_submenu = [];
				foreach ( $submenu_items as $parent_slug => $items ) {
					$allowed_submenu[ $parent_slug ] = array_values(
						array_unique(
							array_filter(
								array_map(
									static function ( array $item ): string {
										return trim( (string) ( $item['slug'] ?? '' ) );
									},
									$items
								)
							)
						)
					);
				}

				$src_pt   = array_key_exists( 'post_types', $raw ) ? $this->sanitize_key_list( $raw['post_types'] ) : (array) $defaults['post_types'];
				$src_tax  = array_key_exists( 'taxonomies', $raw ) ? $this->sanitize_key_list( $raw['taxonomies'] ) : (array) $defaults['taxonomies'];
				$src_menu = array_key_exists( 'menu_order', $raw ) ? $this->sanitize_slug_list( $raw['menu_order'] ) : (array) $defaults['menu_order'];
				$src_submenu = array_key_exists( 'submenu_order', $raw ) ? $this->sanitize_submenu_order( $raw['submenu_order'] ) : (array) $defaults['submenu_order'];
				$src_hidden_menu = array_key_exists( 'hidden_menu', $raw ) ? $this->sanitize_slug_list( $raw['hidden_menu'] ) : (array) $defaults['hidden_menu'];
				$src_hidden_submenu = array_key_exists( 'hidden_submenu', $raw ) ? $this->sanitize_submenu_order( $raw['hidden_submenu'] ) : (array) $defaults['hidden_submenu'];

				$post_types = [];
				foreach ( $src_pt as $slug ) {
					if ( in_array( $slug, $allowed_pt, true ) ) {
						$post_types[] = $slug;
					}
				}

				$taxonomies = [];
				foreach ( $src_tax as $slug ) {
					if ( in_array( $slug, $allowed_tax, true ) ) {
						$taxonomies[] = $slug;
					}
				}

				$menu_order = [];
				if ( $allowed_menu ) {
					foreach ( $src_menu as $slug ) {
						if ( in_array( $slug, $allowed_menu, true ) ) {
							$menu_order[] = $slug;
						}
					}
				} else {
					// admin-ajax may not have populated global $menu; trust the submitted sanitized order
					$menu_order = $src_menu;
				}

				$submenu_order = [];
				if ( $allowed_submenu ) {
					foreach ( $src_submenu as $parent_slug => $children ) {
						if ( ! isset( $allowed_submenu[ $parent_slug ] ) ) {
							continue;
						}

						$allowed_children = $allowed_submenu[ $parent_slug ];
						$sorted_children  = [];

						foreach ( (array) $children as $child_slug ) {
							if ( in_array( $child_slug, $allowed_children, true ) && ! in_array( $child_slug, $sorted_children, true ) ) {
								$sorted_children[] = $child_slug;
							}
						}

						if ( $sorted_children ) {
							$submenu_order[ $parent_slug ] = $sorted_children;
						}
					}
				} else {
					// admin-ajax may not have populated global $submenu; trust the submitted sanitized order
					$submenu_order = $src_submenu;
				}

				$hidden_menu = [];
				if ( $allowed_menu ) {
					foreach ( $src_hidden_menu as $slug ) {
						if ( in_array( $slug, $allowed_menu, true ) && ! in_array( $slug, $hidden_menu, true ) ) {
							$hidden_menu[] = $slug;
						}
					}
				} else {
					$hidden_menu = $src_hidden_menu;
				}

				$hidden_submenu = [];
				if ( $allowed_submenu ) {
					foreach ( $src_hidden_submenu as $parent_slug => $children ) {
						if ( ! isset( $allowed_submenu[ $parent_slug ] ) ) {
							continue;
						}

						$allowed_children = $allowed_submenu[ $parent_slug ];
						$hidden_children  = [];
						foreach ( (array) $children as $child_slug ) {
							if ( in_array( $child_slug, $allowed_children, true ) && ! in_array( $child_slug, $hidden_children, true ) ) {
								$hidden_children[] = $child_slug;
							}
						}

						if ( $hidden_children ) {
							$hidden_submenu[ $parent_slug ] = $hidden_children;
						}
					}
				} else {
					$hidden_submenu = $src_hidden_submenu;
				}

				return [
					'enable_duplicate' => ! empty( $raw['enable_duplicate'] ) ? 1 : 0,
					'enable_post_sort' => ! empty( $raw['enable_post_sort'] ) ? 1 : 0,
					'enable_term_sort' => ! empty( $raw['enable_term_sort'] ) ? 1 : 0,
					'enable_menu_sort' => ! empty( $raw['enable_menu_sort'] ) ? 1 : 0,
					'enable_submenu_sort' => ! empty( $raw['enable_submenu_sort'] ) ? 1 : 0,
					'post_types'       => $post_types,
					'taxonomies'       => $taxonomies,
					'menu_order'       => $menu_order,
					'submenu_order'    => $submenu_order,
					'hidden_menu'      => $hidden_menu,
					'hidden_submenu'   => $hidden_submenu,
				];
			}

			private function is_duplicate_enabled(): bool {
				$cfg = $this->cfg();
				return ! empty( $cfg['enable_duplicate'] );
			}

			private function is_post_sort_enabled_for( string $post_type ): bool {
				$post_type = sanitize_key( $post_type );
				$cfg       = $this->cfg();

				if ( empty( $cfg['enable_post_sort'] ) ) {
					return false;
				}

				return in_array( $post_type, (array) $cfg['post_types'], true );
			}

			private function is_term_sort_enabled_for( string $taxonomy ): bool {
				$taxonomy = sanitize_key( $taxonomy );
				$cfg      = $this->cfg();

				if ( empty( $cfg['enable_term_sort'] ) ) {
					return false;
				}

				return in_array( $taxonomy, (array) $cfg['taxonomies'], true );
			}

			private function can_create_in_post_type( string $post_type ): bool {
				$obj = get_post_type_object( $post_type );
				if ( ! $obj || empty( $obj->show_ui ) ) {
					return false;
				}

				$cap = (string) ( $obj->cap->create_posts ?? $obj->cap->edit_posts ?? 'edit_posts' );

				return current_user_can( $cap );
			}

			private function is_sortable_post_type( string $post_type ): bool {
				$post_type = sanitize_key( $post_type );
				if ( $post_type === '' || in_array( $post_type, [ 'attachment', 'revision', 'nav_menu_item' ], true ) ) {
					return false;
				}

				if ( ! $this->is_post_sort_enabled_for( $post_type ) ) {
					return false;
				}

				$obj = get_post_type_object( $post_type );
				if ( ! $obj || empty( $obj->show_ui ) ) {
					return false;
				}

				$cap = (string) ( $obj->cap->edit_posts ?? 'edit_posts' );

				return current_user_can( $cap );
			}

			private function is_sortable_taxonomy( string $taxonomy ): bool {
				$taxonomy = sanitize_key( $taxonomy );
				if ( $taxonomy === '' || in_array( $taxonomy, [ 'nav_menu' ], true ) ) {
					return false;
				}

				if ( ! $this->is_term_sort_enabled_for( $taxonomy ) ) {
					return false;
				}

				$obj = get_taxonomy( $taxonomy );
				if ( ! $obj || empty( $obj->show_ui ) ) {
					return false;
				}

				$cap = (string) ( $obj->cap->manage_terms ?? 'manage_categories' );

				return current_user_can( $cap );
			}

			public function menu(): void {
				add_options_page(
					'Content Manager',
					'<span style="display: flex;align-items: center;gap: 5px;">
                        <svg width="19" height="19" xmlns="http://www.w3.org/2000/svg" xml:space="preserve" viewBox="0 0 218.6 218.6">
                          <path fill="currentColor" d="M160.8 64.5a13 13 0 0 0-4-4q0-3.6-.8-9.3c.4-4.6 1.7-29.4-13.5-40.6A51 51 0 0 0 112.3 0c-8.4 0-16.5 2.4-22.6 6.8a33 33 0 0 0-10.2 11.1c-4.8.2-14.7 2.2-19.1 14.8-4.2 12-.8 19.3 1.7 22.7l-.3 5.1q-2 1.3-4 4-4.1 6.5-1.4 16.9c3.4 13 11.3 15.9 15.6 16.5A86 86 0 0 0 86 119a28 28 0 0 0 9 5.4 39 39 0 0 0 28.7 0 28 28 0 0 0 8.8-5.4c5.8-5.4 11.5-16 14.1-21.2 4.2-.6 12.2-3.5 15.6-16.5q2.7-10.5-1.4-16.9M152.5 79c-2 7.9-5.8 9-7.8 9h-.3c-2.3-.5-4.5.7-5.5 2.9a88 88 0 0 1-13.2 21 18 18 0 0 1-5.7 3.4 29 29 0 0 1-21.4 0q-3.6-1.5-5.7-3.4a88 88 0 0 1-13.2-21q-1.5-2.8-4.4-3-.5 0-1.1.2h-.3c-2 0-5.8-1.2-7.8-9.1q-1.5-6 0-8.8c.6-1 1.5-1.3 1.7-1.4 2.7-.3 4.3-2.7 4.1-5.4 0 0-.2-2.4 0-6C75.5 56 81 53 85.6 48q3.5-4 5.3-8.6a63 63 0 0 0 14.4 8.8c10.8 4.7 32.2 7 41.3 7.8.4 4.3.2 7.2.2 7.3-.3 2.7 1.4 5 4 5.4.3 0 1.2.4 1.8 1.4q1.5 2.8 0 8.8m32 66.8L141 128.2a5 5 0 0 0-6.6 3l-11 30.3-2.2-6.3 3.5-8.4a5 5 0 0 0-4.6-7H98.6a5 5 0 0 0-4.6 7l3.4 8.4-2.1 6.3-11-30.4a5 5 0 0 0-6.6-3L34 145.8a35 35 0 0 0-22 32.6v35.3a5 5 0 0 0 5 5h184.7a5 5 0 0 0 5-5v-35.3a35 35 0 0 0-22.1-32.7m-1.4 47a5 5 0 0 1-5 5h-33.6a5 5 0 0 1-5-5v-15.6a5 5 0 0 1 5-5H178a5 5 0 0 1 5 5z"/>
                        </svg> Content Manager
                    </span>',
					'manage_options',
					self::PAGE_SLUG,
					[ $this, 'render_settings_page' ]
				);
			}

			public function render_settings_page(): void {
				if ( ! current_user_can( 'manage_options' ) ) {
					wp_die( esc_html__( 'Forbidden', 'frontre' ), 403 );
				}

				$cfg          = $this->cfg();
				$post_types   = $this->get_sortable_post_type_choices();
				$taxonomies   = $this->get_sortable_taxonomy_choices();
				$menu_items   = $this->get_admin_menu_items();
				$submenu_items = $this->get_admin_submenu_items();
				$sorted_items = [];
				$menu_titles  = [];

				foreach ( (array) $cfg['menu_order'] as $slug ) {
					if ( isset( $menu_items[ $slug ] ) ) {
						$sorted_items[ $slug ] = $menu_items[ $slug ];
						unset( $menu_items[ $slug ] );
					}
				}
				foreach ( $menu_items as $slug => $item ) {
					$sorted_items[ $slug ] = $item;
				}

				foreach ( $sorted_items as $slug => $item ) {
					$menu_titles[ $slug ] = (string) ( $item['title'] ?? $slug );
				}

				$hidden_menu_lookup = array_fill_keys( (array) $cfg['hidden_menu'], true );
				$hidden_submenu_lookup = [];
				if ( isset( $cfg['hidden_submenu'] ) && is_array( $cfg['hidden_submenu'] ) ) {
					foreach ( $cfg['hidden_submenu'] as $parent_slug => $children ) {
						$parent_slug = trim( (string) $parent_slug );
						if ( $parent_slug === '' || ! is_array( $children ) ) {
							continue;
						}

						$hidden_submenu_lookup[ $parent_slug ] = array_fill_keys( $children, true );
					}
				}

				$sorted_submenu = [];
				foreach ( $submenu_items as $parent_slug => $items ) {
					$item_map = [];
					foreach ( $items as $item ) {
						$item_slug = trim( (string) ( $item['slug'] ?? '' ) );
						if ( $item_slug === '' ) {
							continue;
						}
						$item_map[ $item_slug ] = $item;
					}

					$ordered_items = [];
					$custom_order  = isset( $cfg['submenu_order'][ $parent_slug ] ) && is_array( $cfg['submenu_order'][ $parent_slug ] )
						? $cfg['submenu_order'][ $parent_slug ]
						: [];

					foreach ( $custom_order as $item_slug ) {
						if ( isset( $item_map[ $item_slug ] ) ) {
							$ordered_items[] = $item_map[ $item_slug ];
							unset( $item_map[ $item_slug ] );
						}
					}
					foreach ( $item_map as $item ) {
						$ordered_items[] = $item;
					}

					if ( $ordered_items ) {
						$sorted_submenu[ $parent_slug ] = $ordered_items;
					}
				}
					?>
				<div class="wrap sp-cm-wrap">
					<h1>Content Manager</h1>
					<p class="description">Duplicate content and manage drag-and-drop ordering for content lists, taxonomies, and the admin sidebar menu.</p>

					<div id="sp-cm-settings-status" class="notice inline" style="display:none;"><p></p></div>

					<div class="sp-cm-toolbar">
						<button type="button" class="button button-primary" id="sp-cm-save-settings">Save settings</button>
					</div>

					<div class="sp-cm-grid">
                        <div class="sp-cm-grid__coll">
                            <section class="sp-cm-card sp-cm-card-wide">
                                <div class="sp-cm-card-box">
                                    <div class="sp-cm-card-box-inner">
                                        <h2>Admin Sidebar Menu Order</h2>
                                        <p class="description">Drag items to define your custom order for the left admin menu.</p>
                                    </div>
                                    <div class="sp-cm-actions">
                                        <button type="button" class="button" id="sp-cm-menu-reset">Reset to current WordPress order</button>
                                    </div>
                                </div>
								<ul id="sp-cm-menu-list" class="sp-cm-menu-list">
									<?php foreach ( $sorted_items as $item ) : ?>
										<?php
										$item_slug = (string) $item['slug'];
										$is_hidden = isset( $hidden_menu_lookup[ $item_slug ] );
										?>
		                                        <li class="sp-cm-menu-item <?php echo $is_hidden ? 'is-hidden' : ''; ?>" data-slug="<?php echo esc_attr( $item_slug ); ?>" data-visible="<?php echo $is_hidden ? '0' : '1'; ?>">
	                                            <span class="sp-cm-drag" aria-hidden="true">::</span>
												<button type="button" class="sp-cm-visibility-toggle <?php echo $is_hidden ? 'is-off' : 'is-on'; ?>" aria-pressed="<?php echo $is_hidden ? 'false' : 'true'; ?>">
													<?php echo esc_html( $is_hidden ? 'Hide' : 'Show' ); ?>
												</button>
	                                            <span class="sp-cm-menu-title"><?php echo esc_html( (string) $item['title'] ); ?></span>
	                                            <code><?php echo esc_html( $item_slug ); ?></code>
	                                        </li>
									<?php endforeach; ?>
	                                </ul>
                            </section>

							<section class="sp-cm-card sp-cm-card-wide">
								<div class="sp-cm-card-box">
									<div class="sp-cm-card-box-inner">
										<h2>Admin Submenu Order</h2>
										<p class="description">Set custom order for submenu items inside each parent menu.</p>
									</div>
									<div class="sp-cm-actions">
										<button type="button" class="button" id="sp-cm-submenu-reset">Reset all submenus to current WordPress order</button>
									</div>
								</div>

								<div id="sp-cm-submenu-groups" class="sp-cm-submenu-groups">
									<?php foreach ( $sorted_submenu as $parent_slug => $items ) : ?>
										<div class="sp-cm-submenu-group" data-parent="<?php echo esc_attr( $parent_slug ); ?>">
											<div class="sp-cm-submenu-group-head">
												<strong><?php echo esc_html( $menu_titles[ $parent_slug ] ?? $parent_slug ); ?></strong>
												<code><?php echo esc_html( $parent_slug ); ?></code>
											</div>
												<ul class="sp-cm-submenu-list" data-parent="<?php echo esc_attr( $parent_slug ); ?>">
													<?php foreach ( $items as $item ) : ?>
														<?php
														$item_slug = (string) $item['slug'];
														$is_hidden = isset( $hidden_submenu_lookup[ $parent_slug ][ $item_slug ] );
														?>
														<li class="sp-cm-submenu-item <?php echo $is_hidden ? 'is-hidden' : ''; ?>" data-slug="<?php echo esc_attr( $item_slug ); ?>" data-visible="<?php echo $is_hidden ? '0' : '1'; ?>">
															<span class="sp-cm-drag" aria-hidden="true">::</span>
															<button type="button" class="sp-cm-visibility-toggle <?php echo $is_hidden ? 'is-off' : 'is-on'; ?>" aria-pressed="<?php echo $is_hidden ? 'false' : 'true'; ?>">
																<?php echo esc_html( $is_hidden ? 'Hide' : 'Show' ); ?>
															</button>
															<span class="sp-cm-menu-title"><?php echo esc_html( (string) $item['title'] ); ?></span>
															<code><?php echo esc_html( $item_slug ); ?></code>
														</li>
													<?php endforeach; ?>
												</ul>
										</div>
									<?php endforeach; ?>
								</div>
							</section>
                        </div>
                        <div class="sp-cm-grid__coll">
                            <section class="sp-cm-card">
                                <div class="sp-cm-card-box">
                                    <h2>Features</h2>
                                </div>

                                <div class="sp-cm-list">
                                    <label class="sp-cm-check">
                                        <input type="checkbox" id="sp-cm-enable-duplicate" <?php checked( ! empty( $cfg['enable_duplicate'] ) ); ?>>
                                        <span>Enable duplicate link in post/page/CPT list</span>
                                    </label>
                                    <label class="sp-cm-check">
                                        <input type="checkbox" id="sp-cm-enable-post-sort" <?php checked( ! empty( $cfg['enable_post_sort'] ) ); ?>>
                                        <span>Enable drag-and-drop sorting for post lists</span>
                                    </label>
                                    <label class="sp-cm-check">
                                        <input type="checkbox" id="sp-cm-enable-term-sort" <?php checked( ! empty( $cfg['enable_term_sort'] ) ); ?>>
                                        <span>Enable drag-and-drop sorting for taxonomy terms</span>
                                    </label>
                                    <label class="sp-cm-check">
                                        <input type="checkbox" id="sp-cm-enable-menu-sort" <?php checked( ! empty( $cfg['enable_menu_sort'] ) ); ?>>
                                        <span>Enable custom order for admin sidebar menu</span>
                                    </label>
									<label class="sp-cm-check">
										<input type="checkbox" id="sp-cm-enable-submenu-sort" <?php checked( ! empty( $cfg['enable_submenu_sort'] ) ); ?>>
										<span>Enable custom order for admin submenu items</span>
									</label>
                                </div>
                            </section>
                            <section class="sp-cm-card">
                                <div class="sp-cm-card-box">
                                    <h2>Sortable Post Types</h2>
                                    <div class="sp-cm-actions">
                                        <button type="button" class="button" id="sp-cm-post-types-all">Select all</button>
                                        <button type="button" class="button" id="sp-cm-post-types-none">Clear all</button>
                                    </div>
                                </div>
                                <div class="sp-cm-list">
                                    <?php foreach ( $post_types as $slug => $label ) : ?>
                                        <label class="sp-cm-check">
                                            <input type="checkbox" class="sp-cm-post-type" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, (array) $cfg['post_types'], true ) ); ?>>
                                            <span><?php echo esc_html( $label ); ?> <code><?php echo esc_html( $slug ); ?></code></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                            <section class="sp-cm-card">
                                <div class="sp-cm-card-box">
                                    <h2>Sortable Taxonomies</h2>
                                    <div class="sp-cm-actions">
                                        <button type="button" class="button" id="sp-cm-taxonomies-all">Select all</button>
                                        <button type="button" class="button" id="sp-cm-taxonomies-none">Clear all</button>
                                    </div>
                                </div>
                                <div class="sp-cm-list">
                                    <?php foreach ( $taxonomies as $slug => $label ) : ?>
                                        <label class="sp-cm-check">
                                            <input type="checkbox" class="sp-cm-taxonomy" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, (array) $cfg['taxonomies'], true ) ); ?>>
                                            <span><?php echo esc_html( $label ); ?> <code><?php echo esc_html( $slug ); ?></code></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        </div>
					</div>
				</div>
				<?php
			}

			public function add_duplicate_link( array $actions, WP_Post $post ): array {
				if ( ! $this->is_duplicate_enabled() ) {
					return $actions;
				}

				if ( in_array( $post->post_type, [ 'attachment', 'revision', 'nav_menu_item' ], true ) ) {
					return $actions;
				}

				if ( ! current_user_can( 'edit_post', $post->ID ) ) {
					return $actions;
				}
				if ( ! $this->can_create_in_post_type( $post->post_type ) ) {
					return $actions;
				}

				$url = wp_nonce_url(
					admin_url( 'admin.php?action=sp_cm_duplicate_post&post=' . (int) $post->ID ),
					'sp_cm_duplicate_post_' . (int) $post->ID
				);

				$actions['sp_cm_duplicate'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Duplicate', 'frontre' ) . '</a>';

				return $actions;
			}

			private function duplicate_post_meta( int $source_id, int $target_id ): void {
				$skip_keys = [
					'_edit_lock',
					'_edit_last',
					'_wp_old_slug',
				];

				$meta = get_post_meta( $source_id );
				if ( ! is_array( $meta ) ) {
					return;
				}

				foreach ( $meta as $meta_key => $values ) {
					$meta_key = (string) $meta_key;
					if ( in_array( $meta_key, $skip_keys, true ) ) {
						continue;
					}

					delete_post_meta( $target_id, $meta_key );
					foreach ( (array) $values as $value ) {
						add_post_meta( $target_id, $meta_key, maybe_unserialize( $value ) );
					}
				}
			}

			private function duplicate_terms( WP_Post $source, int $target_id ): void {
				$taxonomies = get_object_taxonomies( $source->post_type, 'names' );
				if ( ! is_array( $taxonomies ) || ! $taxonomies ) {
					return;
				}

				foreach ( $taxonomies as $taxonomy ) {
					$term_ids = wp_get_object_terms( $source->ID, $taxonomy, [ 'fields' => 'ids' ] );
					if ( is_wp_error( $term_ids ) || ! is_array( $term_ids ) ) {
						continue;
					}

					$term_ids = array_values( array_filter( array_map( 'intval', $term_ids ), static fn( int $id ): bool => $id > 0 ) );
					wp_set_object_terms( $target_id, $term_ids, $taxonomy, false );
				}
			}

			public function handle_duplicate_post(): void {
				if ( ! $this->is_duplicate_enabled() ) {
					wp_die( esc_html__( 'Duplicate feature is disabled.', 'frontre' ), 403 );
				}

				$source_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
				if ( $source_id <= 0 ) {
					wp_die( esc_html__( 'Invalid content ID.', 'frontre' ) );
				}

				check_admin_referer( 'sp_cm_duplicate_post_' . $source_id );

				$source = get_post( $source_id );
				if ( ! ( $source instanceof WP_Post ) ) {
					wp_die( esc_html__( 'Source content was not found.', 'frontre' ) );
				}

				if ( in_array( $source->post_type, [ 'attachment', 'revision', 'nav_menu_item' ], true ) ) {
					wp_die( esc_html__( 'This content type cannot be duplicated.', 'frontre' ) );
				}

				if ( ! current_user_can( 'edit_post', $source_id ) || ! $this->can_create_in_post_type( $source->post_type ) ) {
					wp_die( esc_html__( 'You are not allowed to duplicate this content.', 'frontre' ), 403 );
				}

				$new_postarr = [
					'post_type'      => $source->post_type,
					'post_status'    => 'draft',
					'post_title'     => sprintf( '%s (Copy)', (string) $source->post_title ),
					'post_name'      => '',
					'post_content'   => (string) $source->post_content,
					'post_excerpt'   => (string) $source->post_excerpt,
					'post_parent'    => (int) $source->post_parent,
					'menu_order'     => (int) $source->menu_order,
					'comment_status' => (string) $source->comment_status,
					'ping_status'    => (string) $source->ping_status,
					'post_password'  => (string) $source->post_password,
					'post_author'    => get_current_user_id(),
					'post_date'      => current_time( 'mysql' ),
					'post_date_gmt'  => current_time( 'mysql', true ),
				];

				$new_id = wp_insert_post( $new_postarr, true );
				if ( is_wp_error( $new_id ) ) {
					wp_die( esc_html( $new_id->get_error_message() ) );
				}

				$new_id = (int) $new_id;
				$this->duplicate_post_meta( $source_id, $new_id );
				$this->duplicate_terms( $source, $new_id );

				do_action( 'sp_cm_after_duplicate', $source_id, $new_id );

				$redirect = add_query_arg(
					[
						'post'             => $new_id,
						'action'           => 'edit',
						'sp_cm_duplicated' => 1,
					],
					admin_url( 'post.php' )
				);

				wp_safe_redirect( $redirect );
				exit;
			}

			public function maybe_render_admin_notice(): void {
				if ( empty( $_GET['sp_cm_duplicated'] ) ) {
					return;
				}

				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Content duplicated as draft.', 'frontre' ) . '</p></div>';
			}

			public function apply_admin_post_order( WP_Query $query ): void {
				if ( ! is_admin() || ! $query->is_main_query() ) {
					return;
				}

				global $pagenow;
				if ( $pagenow !== 'edit.php' ) {
					return;
				}

				if ( ! empty( $_GET['orderby'] ) ) {
					return;
				}

				$post_type = $query->get( 'post_type' );
				if ( is_array( $post_type ) ) {
					return;
				}
				$post_type = sanitize_key( (string) ( $post_type ?: 'post' ) );

				if ( ! $this->is_sortable_post_type( $post_type ) ) {
					return;
				}

				$query->set( 'orderby', [
					'menu_order' => 'ASC',
					'date'       => 'DESC',
					'ID'         => 'DESC',
				] );
				$query->set( 'order', 'ASC' );
			}

			public function apply_admin_term_order( array $args, array $taxonomies ): array {
				if ( $this->skip_term_order_filter ) {
					return $args;
				}

				if ( ! is_admin() ) {
					return $args;
				}

				global $pagenow;
				if ( $pagenow !== 'edit-tags.php' ) {
					return $args;
				}

				if ( ! empty( $_GET['orderby'] ) ) {
					return $args;
				}

				$taxonomy = '';
				if ( ! empty( $args['taxonomy'] ) && is_string( $args['taxonomy'] ) ) {
					$taxonomy = $args['taxonomy'];
				} elseif ( ! empty( $taxonomies[0] ) ) {
					$taxonomy = (string) $taxonomies[0];
				}
				$taxonomy = sanitize_key( $taxonomy );

				if ( ! $this->is_sortable_taxonomy( $taxonomy ) ) {
					return $args;
				}

				$ordered_ids = $this->get_ordered_term_ids( $taxonomy );
				if ( ! $ordered_ids ) {
					return $args;
				}

				// Use explicit include order instead of meta_key sorting.
				// meta_key + orderby can hide terms that don't have meta yet.
				$args['include'] = $ordered_ids;
				$args['orderby'] = 'include';
				unset( $args['meta_key'], $args['meta_query'], $args['meta_type'] );

				return $args;
			}

			private function get_ordered_term_ids( string $taxonomy ): array {
				$taxonomy = sanitize_key( $taxonomy );
				if ( $taxonomy === '' ) {
					return [];
				}

				$this->skip_term_order_filter = true;
				try {
					$term_ids = get_terms(
						[
							'taxonomy'   => $taxonomy,
							'hide_empty' => false,
							'fields'     => 'ids',
							'orderby'    => 'term_id',
							'order'      => 'ASC',
						]
					);
				} finally {
					$this->skip_term_order_filter = false;
				}

				if ( is_wp_error( $term_ids ) || ! is_array( $term_ids ) || empty( $term_ids ) ) {
					return [];
				}

				$order_map = [];
				foreach ( $term_ids as $term_id ) {
					$term_id = (int) $term_id;
					if ( $term_id <= 0 ) {
						continue;
					}

					$raw = get_term_meta( $term_id, self::TERM_ORDER_KEY, true );
					if ( $raw === '' || ! is_numeric( $raw ) ) {
						$order_map[ $term_id ] = PHP_INT_MAX;
						continue;
					}

					$order_map[ $term_id ] = (int) $raw;
				}

				usort(
					$term_ids,
					static function ( $a, $b ) use ( $order_map ): int {
						$a = (int) $a;
						$b = (int) $b;

						$oa = $order_map[ $a ] ?? PHP_INT_MAX;
						$ob = $order_map[ $b ] ?? PHP_INT_MAX;

						if ( $oa === $ob ) {
							return $a <=> $b;
						}

						return $oa <=> $ob;
					}
				);

				return array_values( array_filter( array_map( 'intval', $term_ids ), static fn( int $id ): bool => $id > 0 ) );
			}

			public function enable_custom_menu_order( $enabled ) {
				if ( ! is_admin() ) {
					return $enabled;
				}

				$cfg = $this->cfg();
				if ( ! empty( $cfg['enable_menu_sort'] ) ) {
					return true;
				}

				return $enabled;
			}

			public function apply_custom_menu_order( $menu_order ) {
				if ( ! is_admin() ) {
					return $menu_order;
				}

				$order = is_array( $menu_order ) ? array_values( $menu_order ) : [];
				$cfg   = $this->cfg();

				if ( empty( $cfg['enable_menu_sort'] ) || ! $order ) {
					return $menu_order;
				}

				$custom = array_values( array_unique( array_filter( array_map( 'strval', (array) $cfg['menu_order'] ) ) ) );
				if ( ! $custom ) {
					return $menu_order;
				}

				$normalized_to_raw = [];
				foreach ( $order as $raw_slug ) {
					$normalized = $this->normalize_menu_slug( (string) $raw_slug );
					if ( $normalized === '' || isset( $normalized_to_raw[ $normalized ] ) ) {
						continue;
					}
					$normalized_to_raw[ $normalized ] = (string) $raw_slug;
				}

				$result = [];
				foreach ( $custom as $slug ) {
					$normalized = $this->normalize_menu_slug( (string) $slug );
					if ( $normalized !== '' && isset( $normalized_to_raw[ $normalized ] ) ) {
						$result[] = $normalized_to_raw[ $normalized ];
						unset( $normalized_to_raw[ $normalized ] );
					}
				}

				foreach ( $order as $slug ) {
					if ( ! in_array( $slug, $result, true ) ) {
						$result[] = $slug;
					}
				}

				return $result;
			}

			public function apply_custom_submenu_order_runtime(): void {
				if ( ! is_admin() ) {
					return;
				}

				$cfg = $this->cfg();
				if ( empty( $cfg['enable_submenu_sort'] ) ) {
					return;
				}

				$custom = isset( $cfg['submenu_order'] ) && is_array( $cfg['submenu_order'] )
					? $cfg['submenu_order']
					: [];
				if ( ! $custom ) {
					return;
				}

				global $submenu;
				if ( ! is_array( $submenu ) ) {
					return;
				}

				$parent_map = [];
				foreach ( array_keys( $submenu ) as $parent_raw_slug ) {
					$normalized = $this->normalize_menu_slug( (string) $parent_raw_slug );
					if ( $normalized !== '' && ! isset( $parent_map[ $normalized ] ) ) {
						$parent_map[ $normalized ] = (string) $parent_raw_slug;
					}
				}

				foreach ( $custom as $parent_slug => $child_order ) {
					$parent_slug = $this->normalize_menu_slug( (string) $parent_slug );
					$parent_raw  = $parent_map[ $parent_slug ] ?? '';

					if ( $parent_slug === '' || $parent_raw === '' || ! isset( $submenu[ $parent_raw ] ) || ! is_array( $submenu[ $parent_raw ] ) ) {
						continue;
					}

					$current_rows = array_values( $submenu[ $parent_raw ] );
					if ( ! $current_rows ) {
						continue;
					}

					$rows_by_slug = [];
					foreach ( $current_rows as $row ) {
						if ( ! is_array( $row ) ) {
							continue;
						}

						$child_slug = $this->normalize_menu_slug( (string) ( $row[2] ?? '' ) );
						if ( $child_slug === '' ) {
							continue;
						}

						$rows_by_slug[ $child_slug ] = $row;
					}

					if ( ! $rows_by_slug ) {
						continue;
					}

					$sorted_rows = [];
					foreach ( (array) $child_order as $child_slug ) {
						$child_slug = $this->normalize_menu_slug( (string) $child_slug );
						if ( $child_slug === '' || ! isset( $rows_by_slug[ $child_slug ] ) ) {
							continue;
						}

						$sorted_rows[] = $rows_by_slug[ $child_slug ];
						unset( $rows_by_slug[ $child_slug ] );
					}

					foreach ( $current_rows as $row ) {
						$child_slug = $this->normalize_menu_slug( (string) ( is_array( $row ) ? ( $row[2] ?? '' ) : '' ) );
						if ( $child_slug === '' || ! isset( $rows_by_slug[ $child_slug ] ) ) {
							continue;
						}

						$sorted_rows[] = $rows_by_slug[ $child_slug ];
						unset( $rows_by_slug[ $child_slug ] );
					}

					if ( $sorted_rows ) {
						$submenu[ $parent_raw ] = $sorted_rows;
					}
				}
			}

			public function apply_custom_menu_visibility_runtime(): void {
				if ( ! is_admin() ) {
					return;
				}

				$current_page = isset( $_GET['page'] ) ? sanitize_key( (string) wp_unslash( $_GET['page'] ) ) : '';
				if ( $current_page === self::PAGE_SLUG ) {
					// On plugin settings page we keep full menu/submenu lists visible in UI,
					// and use badges/toggles to mark hidden state instead of removing items.
					return;
				}

				$cfg = $this->cfg();

				$hidden_menu = array_values(
					array_unique(
						array_filter(
							array_map(
								function ( $slug ): string {
									return $this->normalize_menu_slug( (string) $slug );
								},
								(array) ( $cfg['hidden_menu'] ?? [] )
							),
							static fn( string $slug ): bool => $slug !== ''
						)
					)
				);
				$hidden_submenu = isset( $cfg['hidden_submenu'] ) && is_array( $cfg['hidden_submenu'] )
					? $cfg['hidden_submenu']
					: [];

				if ( ! $hidden_menu && ! $hidden_submenu ) {
					return;
				}

				global $menu, $submenu;

				if ( is_array( $menu ) && $hidden_menu ) {
					foreach ( $menu as $index => $row ) {
						if ( ! is_array( $row ) ) {
							continue;
						}

						$slug = $this->normalize_menu_slug( (string) ( $row[2] ?? '' ) );
						if ( $slug !== '' && in_array( $slug, $hidden_menu, true ) ) {
							unset( $menu[ $index ] );
						}
					}
				}

				if ( ! is_array( $submenu ) ) {
					return;
				}

				foreach ( $submenu as $parent_raw_slug => $rows ) {
					$parent_raw_slug = (string) $parent_raw_slug;
					$parent_slug     = $this->normalize_menu_slug( $parent_raw_slug );

					if ( $parent_slug === '' ) {
						continue;
					}

					if ( in_array( $parent_slug, $hidden_menu, true ) ) {
						unset( $submenu[ $parent_raw_slug ] );
						continue;
					}

					$hidden_children = isset( $hidden_submenu[ $parent_slug ] ) && is_array( $hidden_submenu[ $parent_slug ] )
						? array_values(
							array_unique(
								array_filter(
									array_map(
										function ( $slug ): string {
											return $this->normalize_menu_slug( (string) $slug );
										},
										$hidden_submenu[ $parent_slug ]
									),
									static fn( string $slug ): bool => $slug !== ''
								)
							)
						)
						: [];

					if ( ! $hidden_children || ! is_array( $rows ) ) {
						continue;
					}

					foreach ( $rows as $index => $row ) {
						if ( ! is_array( $row ) ) {
							continue;
						}

						$child_slug = $this->normalize_menu_slug( (string) ( $row[2] ?? '' ) );
						if ( $child_slug !== '' && in_array( $child_slug, $hidden_children, true ) ) {
							unset( $submenu[ $parent_raw_slug ][ $index ] );
						}
					}

					if ( isset( $submenu[ $parent_raw_slug ] ) && is_array( $submenu[ $parent_raw_slug ] ) ) {
						$submenu[ $parent_raw_slug ] = array_values( $submenu[ $parent_raw_slug ] );
					}
				}
			}

			private function ajax_guard_logged(): void {
				check_ajax_referer( self::NONCE_ACTION, 'nonce' );
				if ( ! is_user_logged_in() ) {
					wp_send_json_error( [ 'message' => 'Forbidden' ], 403 );
				}
			}

			private function ajax_guard_manage_options(): void {
				check_ajax_referer( self::NONCE_ACTION, 'nonce' );
				if ( ! current_user_can( 'manage_options' ) ) {
					wp_send_json_error( [ 'message' => 'Forbidden' ], 403 );
				}
			}

			public function ajax_save_settings(): void {
				$this->ajax_guard_manage_options();

				$raw = isset( $_POST['cfg'] ) && is_array( $_POST['cfg'] )
					? (array) wp_unslash( $_POST['cfg'] )
					: [];

				$cfg = $this->sanitize_cfg( $raw );
				update_option( self::OPT_KEY, $cfg, false );

				wp_send_json_success(
					[
						'message' => 'Settings saved',
						'cfg'     => $cfg,
					]
				);
			}

			public function ajax_save_post_order(): void {
				$this->ajax_guard_logged();

				$post_type = isset( $_POST['post_type'] ) ? sanitize_key( (string) wp_unslash( $_POST['post_type'] ) ) : '';
				if ( ! $this->is_sortable_post_type( $post_type ) ) {
					wp_send_json_error( [ 'message' => 'Invalid post type' ], 400 );
				}

				$order_raw = isset( $_POST['order'] ) ? (array) wp_unslash( $_POST['order'] ) : [];
				$order_ids = array_values( array_unique( array_filter( array_map( 'intval', $order_raw ), static fn( int $id ): bool => $id > 0 ) ) );

				if ( ! $order_ids ) {
					wp_send_json_success( [ 'updated' => 0 ] );
				}

				$updated  = 0;
				$position = 0;
				foreach ( $order_ids as $post_id ) {
					if ( get_post_type( $post_id ) !== $post_type ) {
						continue;
					}
					if ( ! current_user_can( 'edit_post', $post_id ) ) {
						continue;
					}

					$res = wp_update_post(
						[
							'ID'         => $post_id,
							'menu_order' => $position,
						],
						true
					);
					if ( is_wp_error( $res ) ) {
						continue;
					}

					$updated++;
					$position++;
				}

				wp_send_json_success( [ 'updated' => $updated ] );
			}

			public function ajax_save_term_order(): void {
				$this->ajax_guard_logged();

				$taxonomy = isset( $_POST['taxonomy'] ) ? sanitize_key( (string) wp_unslash( $_POST['taxonomy'] ) ) : '';
				if ( ! $this->is_sortable_taxonomy( $taxonomy ) ) {
					wp_send_json_error( [ 'message' => 'Invalid taxonomy' ], 400 );
				}

				$order_raw = isset( $_POST['order'] ) ? (array) wp_unslash( $_POST['order'] ) : [];
					$order_ids = array_values( array_unique( array_filter( array_map( 'intval', $order_raw ), static fn( int $id ): bool => $id > 0 ) ) );

				if ( ! $order_ids ) {
					wp_send_json_success( [ 'updated' => 0 ] );
				}

				$updated  = 0;
				$position = 0;
				foreach ( $order_ids as $term_id ) {
					$term = get_term( $term_id, $taxonomy );
					if ( ! $term || is_wp_error( $term ) ) {
						continue;
					}

					update_term_meta( $term_id, self::TERM_ORDER_KEY, $position );
					$updated++;
					$position++;
				}

				wp_send_json_success( [ 'updated' => $updated ] );
			}

			public function admin_assets( string $hook ): void {
				if ( $hook === 'settings_page_' . self::PAGE_SLUG ) {
					$this->enqueue_settings_assets();
					return;
				}

				if ( $hook === 'edit.php' || $hook === 'edit-tags.php' ) {
					$this->enqueue_list_assets( $hook );
				}
			}

			private function enqueue_settings_assets(): void {
				$cfg            = $this->cfg();
				$default_menu   = array_keys( $this->get_admin_menu_items() );
				$default_submenu_raw = $this->get_admin_submenu_items();
				$default_submenu = [];
				foreach ( $default_submenu_raw as $parent_slug => $items ) {
					$default_submenu[ $parent_slug ] = array_values(
						array_filter(
							array_map(
								static function ( array $item ): string {
									return trim( (string) ( $item['slug'] ?? '' ) );
								},
								$items
							),
							static fn( string $slug ): bool => $slug !== ''
						)
					);
				}

				wp_enqueue_script( 'jquery-ui-sortable' );

				wp_register_script( 'sp-content-manager-settings', '', [ 'jquery', 'jquery-ui-sortable' ], self::VERSION, true );
				wp_enqueue_script( 'sp-content-manager-settings' );
				wp_add_inline_script(
					'sp-content-manager-settings',
					'window.SPContentManagerSettings=' . wp_json_encode(
							[
								'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
								'nonce'            => wp_create_nonce( self::NONCE_ACTION ),
								'defaultMenuOrder' => array_values( $default_menu ),
								'defaultSubmenuOrder' => $default_submenu,
								'cfg'              => $cfg,
								'i18n'             => [
									'saving' => 'Saving settings...',
									'saved'  => 'Settings saved',
								'error'  => 'Failed to save settings',
							],
						]
					) . ';',
					'before'
				);
				wp_add_inline_script( 'sp-content-manager-settings', $this->settings_js() );

				wp_register_style( 'sp-content-manager-settings', '', [], self::VERSION );
				wp_enqueue_style( 'sp-content-manager-settings' );
				wp_add_inline_style( 'sp-content-manager-settings', $this->settings_css() );
			}

			private function enqueue_list_assets( string $hook ): void {
				$screen = get_current_screen();
				if ( ! $screen ) {
					return;
				}

				$mode      = '';
				$post_type = '';
				$taxonomy  = '';

				if ( $hook === 'edit.php' ) {
					$post_type = sanitize_key( (string) ( $screen->post_type ?: 'post' ) );
					if ( ! $this->is_sortable_post_type( $post_type ) ) {
						return;
					}
					$mode = 'post';
				}

				if ( $hook === 'edit-tags.php' ) {
					$taxonomy = sanitize_key( (string) ( $screen->taxonomy ?: '' ) );
					if ( ! $this->is_sortable_taxonomy( $taxonomy ) ) {
						return;
					}
					$mode = 'term';
				}

				if ( $mode === '' ) {
					return;
				}

				wp_enqueue_script( 'jquery-ui-sortable' );

				wp_register_script( 'sp-content-manager-list', '', [ 'jquery', 'jquery-ui-sortable' ], self::VERSION, true );
				wp_enqueue_script( 'sp-content-manager-list' );
				wp_add_inline_script(
					'sp-content-manager-list',
					'window.SPContentManagerList=' . wp_json_encode(
						[
							'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
							'nonce'      => wp_create_nonce( self::NONCE_ACTION ),
							'mode'       => $mode,
							'postType'   => $post_type,
							'taxonomy'   => $taxonomy,
							'savingText' => 'Saving order...',
							'savedText'  => 'Order saved',
							'errorText'  => 'Failed to save order',
						]
					) . ';',
					'before'
				);
				wp_add_inline_script( 'sp-content-manager-list', $this->list_js() );

				wp_register_style( 'sp-content-manager-list', '', [], self::VERSION );
				wp_enqueue_style( 'sp-content-manager-list' );
				wp_add_inline_style( 'sp-content-manager-list', $this->list_css() );
			}

			private function list_css(): string {
				return <<<'CSS'
#the-list tr.sp-cm-placeholder td {
	background: #f0f6fc !important;
	border-top: 2px dashed #2271b1;
	border-bottom: 2px dashed #2271b1;
}
.sp-cm-handle {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 16px;
	height: 16px;
	margin-right: 8px;
	cursor: move;
	color: #8c8f94;
	font-weight: 700;
	user-select: none;
}

strong:has(.sp-cm-handle) {
    display: flex !important;
    align-items: center;
    margin: 0 !important;
    gap: 5px;
}

.row-title:has(.sp-cm-handle) {
    display: flex;
    align-items: center;
}
.sp-cm-handle:hover {
	color: #2271b1;
}
#sp-cm-order-status {
	position: absolute;
    right: 0;
    top: 0;
    left: -20px;
    margin: 0;
    z-index: 20000 !important;
    transform: translateY(-100%);
    visibility: hidden;
    transition: all .300s;
    display: flex;
    
}
#sp-cm-order-status.visible {
	transform: translateY(0%) !important;
	visibility: visible !important;
	transition-delay: .25s;
}
CSS;
			}

			private function settings_css(): string {
				return <<<'CSS'
.sp-cm-wrap .description {
	max-width: 900px;
}
.sp-cm-toolbar {
	margin: 14px 0 18px;
}
.sp-cm-grid {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 16px;
	
	.sp-cm-grid__coll {
	    display: flex;
	    flex-direction: column;
	    gap: inherit;
	}
}
.sp-cm-card {
	background: #fff;
	border: 1px solid #dcdcde;
	border-radius: 8px;
	padding: 16px;
	display: flex;
	flex-direction: column;
	gap: 10px;
}
.sp-cm-card-box {
    display: flex;
    align-items: center;
    justify-content: space-between;
    
    * {
        margin: 0 !important;
    }
}

.sp-cm-card-box-inner {
    display: flex;
    flex-direction: column;
    gap: 5px;
    
    * {
        margin: 0 !important;
    }
} 
.sp-cm-card-wide {
	grid-column: 1 / -1;
}
.sp-cm-card h2 {
	margin-top: 0;
	margin-bottom: 12px;
}
.sp-cm-check {
	display: flex;
	align-items: flex-start;
	gap: 8px;
	margin-bottom: 10px;
	
	input {
	    margin: 0;
	}
}
.sp-cm-check code {
	margin-left: 6px;
}
.sp-cm-actions {
	display: flex;
	gap: 8px;
	margin-bottom: 10px;
}
.sp-cm-list {
	max-height: 280px;
	overflow: auto;
	padding-right: 4px;
	display: grid;
	grid-template-columns: repeat(2, 1fr);
}

.sp-cm-menu-list {
	margin: 0;
	padding: 0;
	list-style: none;
	border: 1px solid #dcdcde;
	border-radius: 8px;
	background: #f9f9f9;
	overflow: hidden;
}
.sp-cm-menu-item {
	display: flex;
	align-items: center;
	gap: 10px;
	padding: 10px 12px;
	border-top: 1px solid #e7e7e7;
	background: #fff;
	cursor: move;
}
.sp-cm-menu-item:first-child {
	border-top: 0;
}
.sp-cm-drag {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 16px;
	color: #8c8f94;
	font-weight: 700;
}
.sp-cm-menu-title {
	flex: 1;
	min-width: 0;
}
.sp-cm-menu-item code {
	opacity: .8;
}
.sp-cm-menu-item.is-hidden,
.sp-cm-submenu-item.is-hidden {
	opacity: .55;
}
.sp-cm-visibility-toggle {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	min-width: 52px;
	height: 24px;
	padding: 0 8px;
	border: 1px solid #bfc3c9;
	border-radius: 999px;
	background: #fff;
	color: #2c3338;
	font-size: 11px;
	line-height: 1;
	font-weight: 600;
	cursor: pointer;
}
.sp-cm-visibility-toggle.is-on {
	background: #eaf7f0;
	border-color: #7ebf95;
	color: #0d5f31;
}
.sp-cm-visibility-toggle.is-off {
	background: #fff1f1;
	border-color: #dba3a3;
	color: #922323;
}
.sp-cm-visibility-toggle:focus {
	outline: none;
	box-shadow: 0 0 0 2px rgba(34, 113, 177, .22);
}
.sp-cm-menu-list .sp-cm-placeholder {
	height: 42px;
	background: #f0f6fc;
	border-top: 2px dashed #2271b1;
	border-bottom: 2px dashed #2271b1;
}
.sp-cm-submenu-groups {
	display: grid;
	grid-template-columns: repeat(1, minmax(0, 1fr));
	gap: 12px;
}
.sp-cm-submenu-group {
	border: 1px solid #dcdcde;
	border-radius: 8px;
	background: #fff;
	overflow: hidden;
}
.sp-cm-submenu-group-head {
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 10px 12px;
	background: #f8f9fb;
	border-bottom: 1px solid #e7e7e7;
}
.sp-cm-submenu-group-head strong {
	flex: 1;
	min-width: 0;
}
.sp-cm-submenu-group-head code {
	opacity: .8;
}
.sp-cm-submenu-list {
	margin: 0;
	padding: 0;
	list-style: none;
	max-height: 240px;
	overflow: auto;
}
.sp-cm-submenu-item {
	display: flex;
	align-items: center;
	gap: 10px;
	padding: 9px 12px;
	border-top: 1px solid #f0f0f1;
	background: #fff;
	cursor: move;
}
.sp-cm-submenu-item:first-child {
	border-top: 0;
}
.sp-cm-submenu-list .sp-cm-placeholder {
	height: 40px;
	background: #f0f6fc;
	border-top: 2px dashed #2271b1;
	border-bottom: 2px dashed #2271b1;
}
@media (max-width: 960px) {
	.sp-cm-grid {
		grid-template-columns: 1fr;
	}
	.sp-cm-card-wide {
		grid-column: auto;
	}
	.sp-cm-submenu-groups {
		grid-template-columns: 1fr;
	}
}
CSS;
			}

			private function list_js(): string {
				return <<<'JS'
(function ($) {
	var cfg = window.SPContentManagerList || {};
	if (!cfg.mode || !cfg.ajaxUrl || !cfg.nonce) return;

	var state = {
		saving: false,
		statusTimer: null
	};

	function ensureStatus() {
		var $status = $('#sp-cm-order-status');
		if ($status.length) return $status;

		$status = $('<div style="transform:translateY(-100%);" id="sp-cm-order-status" class="notice inline"><p></p></div>');
		$('.wrap h1').first().after($status);
		return $status;
	}

	function setStatus(type, text) {
		var $status = ensureStatus();
		$status.removeClass('notice-success notice-error notice-info visible');
		$status.addClass(type).addClass('visible');
		$status.find('p').text(text || '');

		if (state.statusTimer) clearTimeout(state.statusTimer);
		if (type !== 'notice-info') {
			state.statusTimer = setTimeout(function () {
				$status.removeClass('visible');
			}, 1800);
		}
	}

	function parseRowId(rawId, prefix) {
		if (!rawId || rawId.indexOf(prefix) !== 0) return 0;
		var id = parseInt(rawId.replace(prefix, ''), 10);
		return Number.isFinite(id) && id > 0 ? id : 0;
	}

	function collectIds($list, selector, prefix) {
		var out = [];
		$list.children(selector).each(function () {
			var id = parseRowId(this.id || '', prefix);
			if (id > 0) out.push(id);
		});
		return out;
	}

	function rowHelper(_e, tr) {
		var $tr = $(tr);
		var $helper = $tr.clone();
		$helper.children().each(function (i) {
			$(this).width($tr.children().eq(i).outerWidth());
		});
		return $helper;
	}

	function addHandle($row, selector) {
		if ($row.find('.sp-cm-handle').length) return;
		var $target = $row.find(selector).first();
		if (!$target.length) return;

		var $anchor = $target.find('.row-title, strong a').first();
		if (!$anchor.length) {
			$anchor = $target.find('strong').first();
		}
		if (!$anchor.length) {
			$anchor = $target;
		}

		$anchor.prepend(`<span class="sp-cm-handle" title="Drag to reorder" aria-hidden="true">
		<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">
          <path fill="currentColor" d="M4 20h11v6.2l-2.6-2.6L11 25l5 5 5-5-1.4-1.4-2.6 2.6V20h11v-2H4zM11 7l1.4 1.4L15 5.8V12H4v2h24v-2H17V5.8l2.6 2.6L21 7l-5-5z"/>
      
        </svg>
		</span>`);
	}

	function saveOrder(payload) {
		if (state.saving) return;
		state.saving = true;
		setStatus('notice-info', cfg.savingText || 'Saving...');

		$.ajax({
			url: cfg.ajaxUrl,
			type: 'POST',
			dataType: 'json',
			data: payload
		}).done(function (resp) {
			if (resp && resp.success) {
				setStatus('notice-success', cfg.savedText || 'Saved');
			} else {
				setStatus('notice-error', cfg.errorText || 'Error');
			}
		}).fail(function () {
			setStatus('notice-error', cfg.errorText || 'Error');
		}).always(function () {
			state.saving = false;
		});
	}

	function initPostSort() {
		var $list = $('#the-list');
		if (!$list.length) return;

		var selector = 'tr[id^="post-"]:not(.inline-edit-row):not(.no-items)';
		var $rows = $list.children(selector);
		if ($rows.length < 2) return;

		$rows.each(function () {
			addHandle($(this), 'td.column-title');
		});

		if ($list.data('sp-cm-sortable-ready')) return;
		$list.data('sp-cm-sortable-ready', 1);

		$list.sortable({
			items: '> ' + selector,
			handle: '.sp-cm-handle',
			axis: 'y',
			helper: rowHelper,
			placeholder: 'sp-cm-placeholder',
			tolerance: 'pointer',
			update: function () {
				var ids = collectIds($list, selector, 'post-');
				if (!ids.length) return;
				saveOrder({
					action: 'sp_cm_save_post_order',
					nonce: cfg.nonce,
					post_type: cfg.postType || '',
					order: ids
				});
			}
		});
	}

	function initTermSort() {
		var $list = $('#the-list');
		if (!$list.length) return;

		var selector = 'tr[id^="tag-"]:not(.no-items)';
		var $rows = $list.children(selector);
		if ($rows.length < 2) return;

		$rows.each(function () {
			addHandle($(this), 'td.column-name');
		});

		if ($list.data('sp-cm-sortable-ready')) return;
		$list.data('sp-cm-sortable-ready', 1);

		$list.sortable({
			items: '> ' + selector,
			handle: '.sp-cm-handle',
			axis: 'y',
			helper: rowHelper,
			placeholder: 'sp-cm-placeholder',
			tolerance: 'pointer',
			update: function () {
				var ids = collectIds($list, selector, 'tag-');
				if (!ids.length) return;
				saveOrder({
					action: 'sp_cm_save_term_order',
					nonce: cfg.nonce,
					taxonomy: cfg.taxonomy || '',
					order: ids
				});
			}
		});
	}

	function boot() {
		if (cfg.mode === 'post') {
			initPostSort();
		} else if (cfg.mode === 'term') {
			initTermSort();
		}
	}

	$(boot);
	$(document).ajaxComplete(function () {
		boot();
	});
})(jQuery);
JS;
			}

			private function settings_js(): string {
				return <<<'JS'
(function ($) {
	var cfg = window.SPContentManagerSettings || {};
	if (!cfg.ajaxUrl || !cfg.nonce) return;

	function status(type, text) {
		var $box = $('#sp-cm-settings-status');
		if (!$box.length) return;
		$box.removeClass('notice-success notice-error notice-info').addClass(type).show();
		$box.find('p').text(text || '');
	}

	function collectChecked(selector) {
		var out = [];
		$(selector + ':checked').each(function () {
			var v = String($(this).val() || '').trim();
			if (v) out.push(v);
		});
		return out;
	}

	function collectMenuOrder() {
		var out = [];
		$('#sp-cm-menu-list .sp-cm-menu-item').each(function () {
			var slug = String($(this).data('slug') || '').trim();
			if (slug) out.push(slug);
		});
		return out;
	}

	function collectSubmenuOrder() {
		var out = {};
		$('#sp-cm-submenu-groups .sp-cm-submenu-list').each(function () {
			var $list = $(this);
			var parent = String($list.data('parent') || '').trim();
			if (!parent) return;

			var items = [];
			$list.children('.sp-cm-submenu-item').each(function () {
				var slug = String($(this).data('slug') || '').trim();
				if (slug) items.push(slug);
			});

			if (items.length) {
				out[parent] = items;
			}
		});

		return out;
	}

	function isVisibleItem($item) {
		return String($item.attr('data-visible') || '1') === '1';
	}

	function setItemVisibility($item, visible) {
		var isVisible = !!visible;
		$item.attr('data-visible', isVisible ? '1' : '0');
		$item.toggleClass('is-hidden', !isVisible);

		var $toggle = $item.find('.sp-cm-visibility-toggle').first();
		if ($toggle.length) {
			$toggle
				.toggleClass('is-on', isVisible)
				.toggleClass('is-off', !isVisible)
				.attr('aria-pressed', isVisible ? 'true' : 'false')
				.text(isVisible ? 'Show' : 'Hide');
		}
	}

	function collectHiddenMenu() {
		var out = [];
		$('#sp-cm-menu-list .sp-cm-menu-item').each(function () {
			var $item = $(this);
			var slug = String($item.data('slug') || '').trim();
			if (!slug) return;
			if (!isVisibleItem($item)) out.push(slug);
		});
		return out;
	}

	function collectHiddenSubmenu() {
		var out = {};
		$('#sp-cm-submenu-groups .sp-cm-submenu-list').each(function () {
			var $list = $(this);
			var parent = String($list.data('parent') || '').trim();
			if (!parent) return;

			var hidden = [];
			$list.children('.sp-cm-submenu-item').each(function () {
				var $item = $(this);
				var slug = String($item.data('slug') || '').trim();
				if (!slug) return;
				if (!isVisibleItem($item)) hidden.push(slug);
			});

			if (hidden.length) out[parent] = hidden;
		});
		return out;
	}

	function findMenuItemBySlug(slug) {
		return $('#sp-cm-menu-list .sp-cm-menu-item[data-slug="' + slug.replace(/"/g, '\\"') + '"]');
	}

	function findSubmenuItemBySlug(parentSlug, childSlug) {
		var escapedParent = parentSlug.replace(/"/g, '\\"');
		var escapedChild = childSlug.replace(/"/g, '\\"');
		return $('#sp-cm-submenu-groups .sp-cm-submenu-list[data-parent="' + escapedParent + '"] .sp-cm-submenu-item[data-slug="' + escapedChild + '"]');
	}

	function resetMenuOrder() {
		var defaults = Array.isArray(cfg.defaultMenuOrder) ? cfg.defaultMenuOrder : [];
		if (!defaults.length) return;
		var $list = $('#sp-cm-menu-list');
		defaults.forEach(function (slug) {
			var $item = findMenuItemBySlug(slug);
			if ($item.length) $list.append($item);
		});
	}

	function resetSubmenuOrder() {
		var defaults = cfg.defaultSubmenuOrder || {};
		if (!defaults || typeof defaults !== 'object') return;

		Object.keys(defaults).forEach(function (parentSlug) {
			var order = defaults[parentSlug];
			if (!Array.isArray(order) || !order.length) return;

			var escapedParent = parentSlug.replace(/"/g, '\\"');
			var $list = $('#sp-cm-submenu-groups .sp-cm-submenu-list[data-parent="' + escapedParent + '"]');
			if (!$list.length) return;

			order.forEach(function (childSlug) {
				var $item = findSubmenuItemBySlug(parentSlug, String(childSlug || ''));
				if ($item.length) $list.append($item);
			});
		});
	}

	function saveSettings() {
		var payload = {
			action: 'sp_cm_save_settings',
			nonce: cfg.nonce,
				cfg: {
					enable_duplicate: $('#sp-cm-enable-duplicate').is(':checked') ? 1 : 0,
					enable_post_sort: $('#sp-cm-enable-post-sort').is(':checked') ? 1 : 0,
					enable_term_sort: $('#sp-cm-enable-term-sort').is(':checked') ? 1 : 0,
					enable_menu_sort: $('#sp-cm-enable-menu-sort').is(':checked') ? 1 : 0,
					enable_submenu_sort: $('#sp-cm-enable-submenu-sort').is(':checked') ? 1 : 0,
					post_types: collectChecked('.sp-cm-post-type'),
					taxonomies: collectChecked('.sp-cm-taxonomy'),
					menu_order: collectMenuOrder(),
					submenu_order: collectSubmenuOrder(),
					hidden_menu: collectHiddenMenu(),
					hidden_submenu: collectHiddenSubmenu()
				}
			};

		status('notice-info', (cfg.i18n && cfg.i18n.saving) ? cfg.i18n.saving : 'Saving...');

		$.ajax({
			url: cfg.ajaxUrl,
			type: 'POST',
			dataType: 'json',
			data: payload
		}).done(function (resp) {
			if (resp && resp.success) {
				status('notice-success', (cfg.i18n && cfg.i18n.saved) ? cfg.i18n.saved : 'Saved');
			} else {
				status('notice-error', (cfg.i18n && cfg.i18n.error) ? cfg.i18n.error : 'Error');
			}
		}).fail(function () {
			status('notice-error', (cfg.i18n && cfg.i18n.error) ? cfg.i18n.error : 'Error');
		});
	}

	function bindBulkButtons() {
		$('#sp-cm-post-types-all').on('click', function () {
			$('.sp-cm-post-type').prop('checked', true);
		});
		$('#sp-cm-post-types-none').on('click', function () {
			$('.sp-cm-post-type').prop('checked', false);
		});

		$('#sp-cm-taxonomies-all').on('click', function () {
			$('.sp-cm-taxonomy').prop('checked', true);
		});
		$('#sp-cm-taxonomies-none').on('click', function () {
			$('.sp-cm-taxonomy').prop('checked', false);
		});
	}

	function bindMenuTools() {
		$('#sp-cm-menu-reset').on('click', function () {
			resetMenuOrder();
		});
		$('#sp-cm-submenu-reset').on('click', function () {
			resetSubmenuOrder();
		});
	}

	function bindSave() {
		$('#sp-cm-save-settings').on('click', function () {
			saveSettings();
		});
	}

	function bindVisibilityToggle() {
		$(document).on('click', '.sp-cm-visibility-toggle', function (e) {
			e.preventDefault();

			var $toggle = $(this);
			var $item = $toggle.closest('.sp-cm-menu-item, .sp-cm-submenu-item');
			if (!$item.length) return;

			setItemVisibility($item, !isVisibleItem($item));
		});
	}

	function initSortable() {
		var $list = $('#sp-cm-menu-list');
		if (!$list.length || $list.data('sp-cm-sortable-ready')) return;

		$list.data('sp-cm-sortable-ready', 1);
		$list.sortable({
			items: '> .sp-cm-menu-item',
			handle: '.sp-cm-drag',
			axis: 'y',
			tolerance: 'pointer',
				placeholder: 'sp-cm-placeholder'
			});

			$('#sp-cm-submenu-groups .sp-cm-submenu-list').each(function () {
				var $submenuList = $(this);
				if ($submenuList.data('sp-cm-sortable-ready')) return;

				$submenuList.data('sp-cm-sortable-ready', 1);
				$submenuList.sortable({
					items: '> .sp-cm-submenu-item',
					handle: '.sp-cm-drag',
					axis: 'y',
					tolerance: 'pointer',
					placeholder: 'sp-cm-placeholder'
				});
			});
		}

	function boot() {
		initSortable();
		bindBulkButtons();
		bindMenuTools();
		bindSave();
		bindVisibilityToggle();
	}

	$(boot);
})(jQuery);
JS;
			}
		}

		SP_Content_Manager::get();
	}
