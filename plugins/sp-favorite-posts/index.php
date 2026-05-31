<?php
	/**
	 * Plugin Name: SP Favorite Posts
	 * Description: Adds a "Favorite" star column in post lists and helpers for frontend output.
	 * Version: 1.3.0
	 */

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	if ( ! class_exists( 'SP_Favorite_Posts' ) ) {
		class SP_Favorite_Posts {
			private const META_KEY        = '_sp_favorite_post';
			private const COLUMN_KEY      = 'sp_favorite_post';
			private const FILTER_QUERYVAR = 'sp_favorite_filter';
			private const NONCE_KEY       = 'sp_favorite_post_toggle';
			private const SETTINGS_NONCE  = 'sp_favorite_posts_settings';
			private const EDITOR_NONCE    = 'sp_favorite_post_editor';
			private const ROW_NONCE       = 'sp_favorite_post_row_action';
			private const OPT_KEY         = 'sp_favorite_posts_cfg';
			private const PAGE_SLUG       = 'sp-favorite-posts';
			private const VERSION         = '1.3.0';

			private const BULK_MARK_ACTION   = 'sp_favorite_mark';
			private const BULK_UNMARK_ACTION = 'sp_favorite_unmark';

			private static ?self $instance = null;

			public static function get(): self {
				if ( ! self::$instance ) {
					self::$instance = new self();
				}

				return self::$instance;
			}

			private function __construct() {
				add_action( 'admin_menu', [ $this, 'menu' ] );
				add_action( 'admin_post_sp_favorite_posts_save_settings', [ $this, 'save_settings' ] );
				add_action( 'admin_notices', [ $this, 'admin_notices' ] );
				add_action( 'admin_action_sp_favorite_toggle', [ $this, 'handle_row_action_toggle' ] );

				add_action( 'init', [ $this, 'register_post_type_hooks' ], 20 );
				add_action( 'admin_enqueue_scripts', [ $this, 'admin_assets' ] );
				add_action( 'wp_ajax_sp_favorite_post_toggle', [ $this, 'ajax_toggle_favorite' ] );
				add_action( 'add_meta_boxes', [ $this, 'register_editor_metaboxes' ], 10, 2 );
				add_action( 'rest_api_init', [ $this, 'register_rest_fields' ] );

				add_action( 'pre_get_posts', [ $this, 'handle_admin_query' ] );
				add_action( 'restrict_manage_posts', [ $this, 'render_admin_filter' ] );
				add_action( 'quick_edit_custom_box', [ $this, 'render_quick_edit_field' ], 10, 2 );
				add_action( 'save_post', [ $this, 'save_quick_edit' ], 20, 2 );
				add_action( 'save_post', [ $this, 'save_editor_metabox' ], 30, 2 );
				add_filter( 'post_row_actions', [ $this, 'add_row_action_link' ], 10, 2 );
				add_filter( 'page_row_actions', [ $this, 'add_row_action_link' ], 10, 2 );

				add_shortcode( 'sp_favorite_posts', [ $this, 'shortcode' ] );
			}

			private function get_supported_post_type_labels(): array {
				$objects = get_post_types( [ 'show_ui' => true ], 'objects' );
				$out     = [];

				foreach ( $objects as $slug => $obj ) {
					$slug = sanitize_key( (string) $slug );
					if ( $slug === '' || in_array( $slug, [ 'attachment', 'revision', 'nav_menu_item' ], true ) ) {
						continue;
					}

					$label = (string) ( $obj->labels->name ?? $obj->label ?? $slug );
					if ( $label === '' ) {
						$label = $slug;
					}

					$out[ $slug ] = $label;
				}

				natcasesort( $out );

				return $out;
			}

			private function get_supported_post_types(): array {
				return array_keys( $this->get_supported_post_type_labels() );
			}

			private function defaults(): array {
				return [
					'enabled_post_types' => $this->get_supported_post_types(),
					'enable_admin_filter' => 1,
					'enable_bulk_actions' => 1,
					'enable_quick_edit'   => 1,
					'enable_views_tab'    => 1,
					'enable_editor_metabox' => 1,
					'enable_row_action'   => 1,
					'enable_rest_api'     => 1,
				];
			}

			private function sanitize_cfg( array $raw ): array {
				$supported = $this->get_supported_post_types();
				$selected  = isset( $raw['enabled_post_types'] ) ? (array) $raw['enabled_post_types'] : [];

				$selected = array_map(
					static function ( $item ): string {
						return sanitize_key( (string) $item );
					},
					$selected
				);
				$selected = array_values( array_unique( array_filter( $selected ) ) );

				$enabled = [];
				foreach ( $selected as $post_type ) {
					if ( in_array( $post_type, $supported, true ) ) {
						$enabled[] = $post_type;
					}
				}

				return [
					'enabled_post_types' => $enabled,
					'enable_admin_filter' => ! empty( $raw['enable_admin_filter'] ) ? 1 : 0,
					'enable_bulk_actions' => ! empty( $raw['enable_bulk_actions'] ) ? 1 : 0,
					'enable_quick_edit'   => ! empty( $raw['enable_quick_edit'] ) ? 1 : 0,
					'enable_views_tab'    => ! empty( $raw['enable_views_tab'] ) ? 1 : 0,
					'enable_editor_metabox' => ! empty( $raw['enable_editor_metabox'] ) ? 1 : 0,
					'enable_row_action'   => ! empty( $raw['enable_row_action'] ) ? 1 : 0,
					'enable_rest_api'     => ! empty( $raw['enable_rest_api'] ) ? 1 : 0,
				];
			}

			private function cfg(): array {
				$raw = get_option( self::OPT_KEY, [] );
				if ( ! is_array( $raw ) ) {
					$raw = [];
				}

				$cfg = array_merge( $this->defaults(), $raw );
				return $this->sanitize_cfg( $cfg );
			}

			private function enabled_post_types(): array {
				return (array) ( $this->cfg()['enabled_post_types'] ?? [] );
			}

			private function is_enabled_post_type( string $post_type ): bool {
				$post_type = sanitize_key( $post_type );
				if ( $post_type === '' ) {
					return false;
				}

				return in_array( $post_type, $this->enabled_post_types(), true );
			}

			private function is_feature_enabled( string $key ): bool {
				$cfg = $this->cfg();
				return ! empty( $cfg[ $key ] );
			}

			private function current_admin_post_type(): string {
				if ( ! empty( $_GET['post_type'] ) ) {
					return sanitize_key( (string) wp_unslash( $_GET['post_type'] ) );
				}

				global $typenow;
				if ( ! empty( $typenow ) ) {
					return sanitize_key( (string) $typenow );
				}

				return 'post';
			}

			public function menu(): void {
				add_options_page(
					'Favorite Posts',
					'<span style="display: flex;align-items: center;gap: 5px;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 1000 1000">
                          <path fill="currentColor" d="M47 43h553q10 0 16.5 7t6.5 16v29q0 9-6.5 16t-16.5 7H47q-10 0-17-7t-7-16V66q0-9 7-16t17-7m0 139h553q10 0 16.5 7t6.5 17v28q0 10-6.5 16.5T600 257H47q-10 0-17-6.5T23 234v-28q0-10 7-17t17-7m0 140h359q10 0 17 6.5t7 16.5v28q0 10-7 17t-17 7H47q-10 0-17-7t-7-17v-28q0-10 7-16.5t17-6.5m0 139h153q10 0 16.5 7t6.5 16v29q0 9-6.5 16t-16.5 7H47q-10 0-17-7t-7-16v-29q0-9 7-16t17-7m578 386-151 79q-9 5-19.5 4t-19-7-12.5-16-3-20l29-168q4-19-10-32L317 568q-8-7-10.5-17.5t1-20.5 11.5-17 18-8l169-25q19-2 27-20l76-152q4-10 13-15.5t19.5-5.5 19.5 5.5 14 15.5l75 152q9 18 28 20l168 25q11 1 19 8t11 17 .5 20.5T967 568L845 687q-14 13-10 32l28 168q2 10-2 20t-12.5 16-19 7-20.5-4l-150-79q-17-9-34 0"/>
                        </svg> Favorite Posts
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

				$cfg       = $this->cfg();
				$posttypes = $this->get_supported_post_type_labels();
				?>
				<div class="wrap">
					<h1>Favorite Posts</h1>
					<p class="description">Configure where Favorite functionality should be enabled.</p>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sp-favorite-settings-form">
						<input type="hidden" name="action" value="sp_favorite_posts_save_settings">
						<?php wp_nonce_field( self::SETTINGS_NONCE, 'sp_favorite_posts_nonce' ); ?>

						<div class="sp-favorite-settings-box">
							<h2>Features</h2>
							<label class="sp-favorite-settings-row">
								<input type="checkbox" name="enable_admin_filter" value="1" <?php checked( ! empty( $cfg['enable_admin_filter'] ) ); ?>>
								<span>Show dropdown filter in admin list (All/Favorites/Not favorites)</span>
							</label>
							<label class="sp-favorite-settings-row">
								<input type="checkbox" name="enable_bulk_actions" value="1" <?php checked( ! empty( $cfg['enable_bulk_actions'] ) ); ?>>
								<span>Enable bulk actions: mark/unmark favorites</span>
							</label>
							<label class="sp-favorite-settings-row">
								<input type="checkbox" name="enable_quick_edit" value="1" <?php checked( ! empty( $cfg['enable_quick_edit'] ) ); ?>>
								<span>Enable Quick Edit checkbox</span>
							</label>
							<label class="sp-favorite-settings-row">
								<input type="checkbox" name="enable_views_tab" value="1" <?php checked( ! empty( $cfg['enable_views_tab'] ) ); ?>>
								<span>Enable Favorites tab in list views</span>
							</label>
							<label class="sp-favorite-settings-row">
								<input type="checkbox" name="enable_editor_metabox" value="1" <?php checked( ! empty( $cfg['enable_editor_metabox'] ) ); ?>>
								<span>Enable "Favorite" checkbox in post editor sidebar</span>
							</label>
							<label class="sp-favorite-settings-row">
								<input type="checkbox" name="enable_row_action" value="1" <?php checked( ! empty( $cfg['enable_row_action'] ) ); ?>>
								<span>Enable row action link under post title (Favorite/Unfavorite)</span>
							</label>
							<label class="sp-favorite-settings-row">
								<input type="checkbox" name="enable_rest_api" value="1" <?php checked( ! empty( $cfg['enable_rest_api'] ) ); ?>>
								<span>Enable REST field <code>is_favorite</code> and request filter <code>?sp_favorite=1|0</code></span>
							</label>
						</div>

						<div class="sp-favorite-settings-box">
							<h2>Enabled Post Types</h2>
							<div class="sp-favorite-settings-actions">
								<button type="button" class="button" id="sp-favorite-select-all">Select all</button>
								<button type="button" class="button" id="sp-favorite-clear-all">Clear all</button>
							</div>
							<?php foreach ( $posttypes as $slug => $label ) : ?>
								<label class="sp-favorite-settings-row">
									<input
										type="checkbox"
										class="sp-favorite-posttype-checkbox"
										name="enabled_post_types[]"
										value="<?php echo esc_attr( $slug ); ?>"
										<?php checked( in_array( $slug, (array) $cfg['enabled_post_types'], true ) ); ?>
									>
									<span><?php echo esc_html( $label ); ?> <code><?php echo esc_html( $slug ); ?></code></span>
								</label>
							<?php endforeach; ?>
						</div>

						<div class="sp-favorite-settings-box">
							<h2>Shortcode</h2>
							<p><code>[sp_favorite_posts post_type="post" card="card-favorite.php" posts_per_page="6"]</code></p>
							<p><small>Template file: <code>wp-content/themes/<?= THEME_SLUG;?>/templates/card-favorite.php</code></small></p>
							<p><small>In template you can use <code>$post_id</code> (current favorite post ID).</small></p>
						</div>

						<div class="sp-favorite-settings-box">
							<h2>REST API</h2>
							<p><code>GET /wp-json/wp/v2/{post_type}?sp_favorite=1</code> or <code>?sp_favorite=0</code></p>
							<p>Response field: <code>is_favorite</code></p>
						</div>

						<p>
							<button type="submit" class="button button-primary">Save settings</button>
						</p>
					</form>
				</div>
				<?php
			}

			public function save_settings(): void {
				if ( ! current_user_can( 'manage_options' ) ) {
					wp_die( esc_html__( 'Forbidden', 'frontre' ), 403 );
				}

				check_admin_referer( self::SETTINGS_NONCE, 'sp_favorite_posts_nonce' );

				$raw = [
					'enabled_post_types' => isset( $_POST['enabled_post_types'] ) ? (array) wp_unslash( $_POST['enabled_post_types'] ) : [],
					'enable_admin_filter' => ! empty( $_POST['enable_admin_filter'] ) ? 1 : 0,
					'enable_bulk_actions' => ! empty( $_POST['enable_bulk_actions'] ) ? 1 : 0,
					'enable_quick_edit'   => ! empty( $_POST['enable_quick_edit'] ) ? 1 : 0,
					'enable_views_tab'    => ! empty( $_POST['enable_views_tab'] ) ? 1 : 0,
					'enable_editor_metabox' => ! empty( $_POST['enable_editor_metabox'] ) ? 1 : 0,
					'enable_row_action'   => ! empty( $_POST['enable_row_action'] ) ? 1 : 0,
					'enable_rest_api'     => ! empty( $_POST['enable_rest_api'] ) ? 1 : 0,
				];

				$cfg = $this->sanitize_cfg( $raw );
				update_option( self::OPT_KEY, $cfg, false );

				$redirect = add_query_arg(
					[
						'page'              => self::PAGE_SLUG,
						'sp_favorite_saved' => 1,
					],
					admin_url( 'options-general.php' )
				);

				wp_safe_redirect( $redirect );
				exit;
			}

			public function admin_notices(): void {
				if ( ! is_admin() ) {
					return;
				}

				if ( current_user_can( 'manage_options' ) && ! empty( $_GET['page'] ) && sanitize_key( (string) $_GET['page'] ) === self::PAGE_SLUG && ! empty( $_GET['sp_favorite_saved'] ) ) {
					echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Favorite settings saved.', 'frontre' ) . '</p></div>';
				}

				if ( ! empty( $_GET['sp_favorite_bulk_updated'] ) && ! empty( $_GET['sp_favorite_bulk_action'] ) ) {
					$count  = (int) $_GET['sp_favorite_bulk_updated'];
					$action = sanitize_key( (string) $_GET['sp_favorite_bulk_action'] );
					if ( $count > 0 ) {
						if ( $action === self::BULK_MARK_ACTION ) {
							echo '<div class="notice notice-success is-dismissible"><p>' . sprintf( esc_html__( '%d posts marked as favorite.', 'frontre' ), $count ) . '</p></div>';
						}
						if ( $action === self::BULK_UNMARK_ACTION ) {
							echo '<div class="notice notice-success is-dismissible"><p>' . sprintf( esc_html__( '%d posts removed from favorites.', 'frontre' ), $count ) . '</p></div>';
						}
					}
				}

				if ( ! empty( $_GET['sp_favorite_row_toggled'] ) ) {
					echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Favorite status updated.', 'frontre' ) . '</p></div>';
				}
			}

			public function register_post_type_hooks(): void {
				$post_types = $this->enabled_post_types();

				foreach ( $post_types as $post_type ) {
					add_filter( 'manage_' . $post_type . '_posts_columns', [ $this, 'add_favorite_column' ] );
					add_action( 'manage_' . $post_type . '_posts_custom_column', [ $this, 'render_favorite_column' ], 10, 2 );
					add_filter( 'manage_edit-' . $post_type . '_sortable_columns', [ $this, 'register_sortable_column' ] );

					if ( $this->is_feature_enabled( 'enable_views_tab' ) ) {
						add_filter( 'views_edit-' . $post_type, [ $this, 'add_favorites_view_link' ] );
					}
					if ( $this->is_feature_enabled( 'enable_bulk_actions' ) ) {
						add_filter( 'bulk_actions-edit-' . $post_type, [ $this, 'register_bulk_actions' ] );
						add_filter( 'handle_bulk_actions-edit-' . $post_type, [ $this, 'handle_bulk_actions' ], 10, 3 );
					}

					if ( $this->is_feature_enabled( 'enable_rest_api' ) ) {
						add_filter( 'rest_' . $post_type . '_query', [ $this, 'filter_rest_query' ], 10, 2 );
					}
				}
			}

			private function has_favorite_flag( int $post_id ): bool {
				return (int) get_post_meta( $post_id, self::META_KEY, true ) === 1;
			}

			private function set_favorite_flag( int $post_id, bool $is_favorite ): void {
				if ( $is_favorite ) {
					update_post_meta( $post_id, self::META_KEY, '1' );
					return;
				}

				delete_post_meta( $post_id, self::META_KEY );
			}

			private function count_favorites( string $post_type ): int {
				$query = new WP_Query(
					[
						'post_type'      => $post_type,
						'post_status'    => 'any',
						'fields'         => 'ids',
						'posts_per_page' => 1,
						'meta_key'       => self::META_KEY,
						'meta_value'     => '1',
					]
				);

				return (int) $query->found_posts;
			}

			public function add_favorites_view_link( array $views ): array {
				$post_type = $this->current_admin_post_type();
				if ( ! $this->is_enabled_post_type( $post_type ) || ! $this->is_feature_enabled( 'enable_views_tab' ) ) {
					return $views;
				}

				$current = isset( $_GET[ self::FILTER_QUERYVAR ] )
					? sanitize_key( (string) wp_unslash( $_GET[ self::FILTER_QUERYVAR ] ) )
					: '';

				$count = $this->count_favorites( $post_type );
				$url   = add_query_arg(
					[
						'post_type'          => $post_type,
						self::FILTER_QUERYVAR => 'favorite',
					],
					admin_url( 'edit.php' )
				);

				$views['sp_favorites'] = '<a href="' . esc_url( $url ) . '"' . ( $current === 'favorite' ? ' class="current" aria-current="page"' : '' ) . '>'
					. esc_html__( 'Favorites', 'frontre' )
					. ' <span class="count">(' . number_format_i18n( $count ) . ')</span></a>';

				return $views;
			}

			public function register_bulk_actions( array $actions ): array {
				$actions[ self::BULK_MARK_ACTION ]   = esc_html__( 'Mark as favorite', 'frontre' );
				$actions[ self::BULK_UNMARK_ACTION ] = esc_html__( 'Remove from favorites', 'frontre' );
				return $actions;
			}

			public function handle_bulk_actions( string $redirect_to, string $doaction, array $post_ids ): string {
				if ( ! in_array( $doaction, [ self::BULK_MARK_ACTION, self::BULK_UNMARK_ACTION ], true ) ) {
					return $redirect_to;
				}

				$post_type = $this->current_admin_post_type();
				if ( ! $this->is_enabled_post_type( $post_type ) ) {
					return $redirect_to;
				}

				$count = 0;
				foreach ( $post_ids as $post_id ) {
					$post_id = (int) $post_id;
					if ( $post_id <= 0 ) {
						continue;
					}
					if ( get_post_type( $post_id ) !== $post_type ) {
						continue;
					}
					if ( ! current_user_can( 'edit_post', $post_id ) ) {
						continue;
					}

					$this->set_favorite_flag( $post_id, $doaction === self::BULK_MARK_ACTION );
					$count++;
				}

				return add_query_arg(
					[
						'sp_favorite_bulk_updated' => $count,
						'sp_favorite_bulk_action'  => $doaction,
					],
					$redirect_to
				);
			}

			public function add_row_action_link( array $actions, WP_Post $post ): array {
				if ( ! is_admin() || ! $this->is_feature_enabled( 'enable_row_action' ) ) {
					return $actions;
				}
				if ( ! $this->is_enabled_post_type( (string) $post->post_type ) ) {
					return $actions;
				}
				if ( ! current_user_can( 'edit_post', $post->ID ) ) {
					return $actions;
				}

				$is_favorite = $this->has_favorite_flag( (int) $post->ID );
				$url         = add_query_arg(
					[
						'action'  => 'sp_favorite_toggle',
						'post_id' => (int) $post->ID,
						'value'   => $is_favorite ? 0 : 1,
					],
					admin_url( 'admin.php' )
				);
				$url         = wp_nonce_url( $url, self::ROW_NONCE . '_' . (int) $post->ID, 'sp_nonce' );
				$label       = $is_favorite ? __( 'Unfavorite', 'frontre' ) : __( 'Favorite', 'frontre' );

				$actions['sp_favorite_toggle'] = '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';

				return $actions;
			}

			public function handle_row_action_toggle(): void {
				$post_id = isset( $_GET['post_id'] ) ? (int) $_GET['post_id'] : 0;
				$value   = isset( $_GET['value'] ) ? (int) $_GET['value'] : 0;
				$nonce   = isset( $_GET['sp_nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['sp_nonce'] ) ) : '';

				if ( $post_id <= 0 ) {
					wp_die( esc_html__( 'Invalid post ID.', 'frontre' ), 400 );
				}
				if ( ! wp_verify_nonce( $nonce, self::ROW_NONCE . '_' . $post_id ) ) {
					wp_die( esc_html__( 'Invalid nonce.', 'frontre' ), 403 );
				}
				if ( ! current_user_can( 'edit_post', $post_id ) ) {
					wp_die( esc_html__( 'Forbidden', 'frontre' ), 403 );
				}

				$post = get_post( $post_id );
				if ( ! ( $post instanceof WP_Post ) ) {
					wp_die( esc_html__( 'Post not found.', 'frontre' ), 404 );
				}
				if ( ! $this->is_enabled_post_type( (string) $post->post_type ) ) {
					wp_die( esc_html__( 'Feature disabled for this post type.', 'frontre' ), 400 );
				}

				$this->set_favorite_flag( $post_id, $value === 1 );

				$redirect = wp_get_referer();
				if ( ! is_string( $redirect ) || $redirect === '' ) {
					$redirect = admin_url( 'edit.php?post_type=' . sanitize_key( (string) $post->post_type ) );
				}
				$redirect = add_query_arg( 'sp_favorite_row_toggled', 1, $redirect );

				wp_safe_redirect( $redirect );
				exit;
			}

			public function add_favorite_column( array $columns ): array {
				$new = [];

				foreach ( $columns as $key => $label ) {
					$new[ $key ] = $label;
					if ( $key === 'title' ) {
						$new[ self::COLUMN_KEY ] = esc_html__( 'Favorite', 'frontre' );
					}
				}

				if ( ! isset( $new[ self::COLUMN_KEY ] ) ) {
					$new[ self::COLUMN_KEY ] = esc_html__( 'Favorite', 'frontre' );
				}

				return $new;
			}

			public function render_favorite_column( string $column, int $post_id ): void {
				if ( $column !== self::COLUMN_KEY ) {
					return;
				}

				$post = get_post( $post_id );
				if ( ! ( $post instanceof WP_Post ) ) {
					return;
				}

				$is_favorite = $this->has_favorite_flag( $post_id );
				if ( ! current_user_can( 'edit_post', $post_id ) ) {
					echo $is_favorite
						? '<span class="dashicons dashicons-star-filled" aria-hidden="true"></span>'
						: '<span class="dashicons dashicons-star-empty" aria-hidden="true"></span>';
					echo '<span class="sp-fav-row-data" data-fav="' . ( $is_favorite ? '1' : '0' ) . '" style="display:none;"></span>';
					return;
				}

				$nonce = wp_create_nonce( self::NONCE_KEY . '_' . $post_id );

				echo '<button type="button" class="sp-fav-post-toggle ' . ( $is_favorite ? 'is-on' : 'is-off' ) . '"'
					 . ' data-post-id="' . esc_attr( (string) $post_id ) . '"'
					 . ' data-nonce="' . esc_attr( $nonce ) . '"'
					 . ' aria-label="' . esc_attr__( 'Toggle favorite', 'frontre' ) . '"'
					 . ' aria-pressed="' . ( $is_favorite ? 'true' : 'false' ) . '">'
					 . '<span class="dashicons ' . ( $is_favorite ? 'dashicons-star-filled' : 'dashicons-star-empty' ) . '" aria-hidden="true"></span>'
					 . '</button>'
					 . '<span class="sp-fav-row-data" data-fav="' . ( $is_favorite ? '1' : '0' ) . '" style="display:none;"></span>';
			}

			public function register_sortable_column( array $columns ): array {
				$columns[ self::COLUMN_KEY ] = self::COLUMN_KEY;
				return $columns;
			}

			public function render_admin_filter( string $post_type ): void {
				global $pagenow;
				if ( $pagenow !== 'edit.php' || ! $this->is_feature_enabled( 'enable_admin_filter' ) ) {
					return;
				}

				$post_type = sanitize_key( (string) $post_type );
				if ( ! $this->is_enabled_post_type( $post_type ) ) {
					return;
				}

				$current = isset( $_GET[ self::FILTER_QUERYVAR ] )
					? sanitize_key( (string) wp_unslash( $_GET[ self::FILTER_QUERYVAR ] ) )
					: '';
				?>
				<select name="<?php echo esc_attr( self::FILTER_QUERYVAR ); ?>">
					<option value="" <?php selected( $current, '' ); ?>><?php echo esc_html__( 'All posts', 'frontre' ); ?></option>
					<option value="favorite" <?php selected( $current, 'favorite' ); ?>><?php echo esc_html__( 'Favorites only', 'frontre' ); ?></option>
					<option value="not_favorite" <?php selected( $current, 'not_favorite' ); ?>><?php echo esc_html__( 'Not favorites', 'frontre' ); ?></option>
				</select>
				<?php
			}

			private function append_meta_query( WP_Query $query, array $clause ): void {
				$existing = $query->get( 'meta_query' );
				if ( ! is_array( $existing ) || ! $existing ) {
					$query->set( 'meta_query', [ $clause ] );
					return;
				}

				$merged = [ 'relation' => 'AND' ];
				foreach ( $existing as $key => $item ) {
					$merged[ $key ] = $item;
				}
				$merged[] = $clause;
				$query->set( 'meta_query', $merged );
			}

			public function handle_admin_query( WP_Query $query ): void {
				if ( ! is_admin() || ! $query->is_main_query() ) {
					return;
				}

				global $pagenow;
				if ( $pagenow !== 'edit.php' ) {
					return;
				}

				$post_type = sanitize_key( (string) ( $query->get( 'post_type' ) ?: 'post' ) );
				if ( ! $this->is_enabled_post_type( $post_type ) ) {
					return;
				}

				$favorite_filter = '';
				if ( $this->is_feature_enabled( 'enable_admin_filter' ) ) {
					$favorite_filter = sanitize_key( (string) $query->get( self::FILTER_QUERYVAR ) );
					if ( $favorite_filter === '' && isset( $_GET[ self::FILTER_QUERYVAR ] ) ) {
						$favorite_filter = sanitize_key( (string) wp_unslash( $_GET[ self::FILTER_QUERYVAR ] ) );
					}
				}

				if ( $favorite_filter === 'favorite' ) {
					$this->append_meta_query(
						$query,
						[
							'key'     => self::META_KEY,
							'value'   => '1',
							'compare' => '=',
						]
					);
				} elseif ( $favorite_filter === 'not_favorite' ) {
					$this->append_meta_query(
						$query,
						[
							'relation' => 'OR',
							[
								'key'     => self::META_KEY,
								'compare' => 'NOT EXISTS',
							],
							[
								'key'     => self::META_KEY,
								'value'   => '1',
								'compare' => '!=',
							],
						]
					);
				}

				if ( $query->get( 'orderby' ) !== self::COLUMN_KEY ) {
					return;
				}

				if ( $favorite_filter === '' ) {
					$this->append_meta_query(
						$query,
						[
							'relation' => 'OR',
							[
								'key'     => self::META_KEY,
								'compare' => 'EXISTS',
								'type'    => 'NUMERIC',
							],
							[
								'key'     => self::META_KEY,
								'compare' => 'NOT EXISTS',
							],
						]
					);
				}

				$query->set( 'meta_key', self::META_KEY );
				$query->set(
					'orderby',
					[
						'meta_value_num' => 'DESC',
						'date'           => 'DESC',
						'ID'             => 'DESC',
					]
				);
				$query->set( 'order', 'DESC' );
			}

			public function render_quick_edit_field( string $column_name, string $post_type ): void {
				if ( $column_name !== self::COLUMN_KEY ) {
					return;
				}
				if ( ! $this->is_feature_enabled( 'enable_quick_edit' ) ) {
					return;
				}

				$post_type = sanitize_key( $post_type );
				if ( ! $this->is_enabled_post_type( $post_type ) ) {
					return;
				}
				?>
				<fieldset class="inline-edit-col-right sp-fav-quick-edit">
					<div class="inline-edit-col">
						<label class="alignleft">
							<input type="checkbox" name="sp_favorite_quickedit" value="1">
							<span class="checkbox-title"><?php echo esc_html__( 'Favorite', 'frontre' ); ?></span>
						</label>
						<input type="hidden" name="sp_favorite_quickedit_present" value="1">
					</div>
				</fieldset>
				<?php
			}

			public function save_quick_edit( int $post_id, WP_Post $post ): void {
				if ( ! $this->is_feature_enabled( 'enable_quick_edit' ) ) {
					return;
				}
				if ( ! $this->is_enabled_post_type( (string) $post->post_type ) ) {
					return;
				}
				if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
					return;
				}
				if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
					return;
				}

				$inline_nonce = isset( $_POST['_inline_edit'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['_inline_edit'] ) ) : '';
				if ( ! $inline_nonce || ! wp_verify_nonce( $inline_nonce, 'inlineeditnonce' ) ) {
					return;
				}

				if ( ! isset( $_POST['sp_favorite_quickedit_present'] ) ) {
					return;
				}
				if ( ! current_user_can( 'edit_post', $post_id ) ) {
					return;
				}

				$is_favorite = ! empty( $_POST['sp_favorite_quickedit'] );
				$this->set_favorite_flag( $post_id, $is_favorite );
			}

			public function register_editor_metaboxes( string $post_type, WP_Post $post ): void {
				if ( ! is_admin() || ! $this->is_feature_enabled( 'enable_editor_metabox' ) ) {
					return;
				}
				if ( ! $this->is_enabled_post_type( $post_type ) ) {
					return;
				}
				if ( ! current_user_can( 'edit_post', (int) $post->ID ) ) {
					return;
				}

				add_meta_box(
					'sp-favorite-post-metabox',
                    '<span style="display: flex; align-items: center;gap: 5px;">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" width="22" height="22">
                          <path fill="currentColor" d="M12 2.5a1 1 0 0 1 .9.6l2.6 5.1L21 9a1 1 0 0 1 .6 1.7l-4.1 4 .9 5.7a1 1 0 0 1-1.5 1l-5-2.6-5 2.7a1 1 0 0 1-1.5-1l1-5.7-4.2-4A1 1 0 0 1 2.9 9l5.6-.8 2.6-5.1a1 1 0 0 1 .9-.6m0 3.2-2 3.9-.7.5-4.2.6 3.1 3a1 1 0 0 1 .3.9l-.7 4.2 3.7-2a1 1 0 0 1 1 0l3.7 2-.7-4.2a1 1 0 0 1 .3-.9l3-3-4.1-.6a1 1 0 0 1-.8-.5z"/>
                        </svg>'.__( 'Favorite', 'frontre' ).
                    '</span>',
					[ $this, 'render_editor_metabox' ],
					$post_type,
					'side',
					'high'
				);
			}

			public function render_editor_metabox( WP_Post $post ): void {
				$is_favorite = $this->has_favorite_flag( (int) $post->ID );
				wp_nonce_field( self::EDITOR_NONCE, 'sp_favorite_editor_nonce' );
				$uid = 'sp_favorite_editor_' . (int) $post->ID;
				?>
				<style>
                    label[for="sp-favorite-post-metabox-hide"] {
                        display: inline-flex;
                        align-items: center;
                        gap: .5em;

                        input {
                            margin: 0 0 2px 0 !important;
                        }
                    }
					.sp-favorite-meta-row {
						display: flex;
						align-items: center;
						justify-content: space-between;
						padding: 4px 0;
						font-size: 13px;
						color: #1d2327;
					}

					.sp-favorite-ios-toggle {
						position: relative;
						display: inline-block;
						cursor: pointer;
					}

					.sp-favorite-ios-toggle input {
						position: absolute;
						opacity: 0;
						width: 0;
						height: 0;
					}

					.sp-favorite-ios-track {
						display: block;
						width: 40px;
						height: 22px;
						background: #c3c4c7;
						border-radius: 22px;
						transition: background .25s;
						position: relative;
					}

					.sp-favorite-ios-thumb {
						position: absolute;
						top: 2px;
						left: 2px;
						width: 18px;
						height: 18px;
						background: #fff;
						border-radius: 50%;
						transition: left .25s;
						box-shadow: 0 1px 3px rgba(0, 0, 0, .25);
					}

					.sp-favorite-ios-toggle input:checked ~ .sp-favorite-ios-track {
						background: #2271b1;
					}

					.sp-favorite-ios-toggle input:checked ~ .sp-favorite-ios-track .sp-favorite-ios-thumb {
						left: 20px;
					}
				</style>
				<div class="sp-favorite-meta-row">
					<span><?php echo esc_html__( 'Mark as favorite', 'frontre' ); ?></span>
					<label class="sp-favorite-ios-toggle" for="<?php echo esc_attr( $uid ); ?>">
						<input type="checkbox" id="<?php echo esc_attr( $uid ); ?>" name="sp_favorite_editor" value="1" <?php checked( $is_favorite ); ?>>
						<span class="sp-favorite-ios-track"><span class="sp-favorite-ios-thumb"></span></span>
					</label>
				</div>
				<?php
			}

			public function save_editor_metabox( int $post_id, WP_Post $post ): void {
				if ( ! $this->is_feature_enabled( 'enable_editor_metabox' ) ) {
					return;
				}
				if ( ! $this->is_enabled_post_type( (string) $post->post_type ) ) {
					return;
				}
				if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
					return;
				}
				if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
					return;
				}

				$nonce = isset( $_POST['sp_favorite_editor_nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['sp_favorite_editor_nonce'] ) ) : '';
				if ( $nonce === '' || ! wp_verify_nonce( $nonce, self::EDITOR_NONCE ) ) {
					return;
				}
				if ( ! current_user_can( 'edit_post', $post_id ) ) {
					return;
				}

				$is_favorite = ! empty( $_POST['sp_favorite_editor'] );
				$this->set_favorite_flag( $post_id, $is_favorite );
			}

			public function register_rest_fields(): void {
				if ( ! $this->is_feature_enabled( 'enable_rest_api' ) ) {
					return;
				}

				foreach ( $this->enabled_post_types() as $post_type ) {
					register_rest_field(
						$post_type,
						'is_favorite',
						[
							'get_callback' => function ( array $object ): int {
								$post_id = isset( $object['id'] ) ? (int) $object['id'] : 0;
								return $post_id > 0 && $this->has_favorite_flag( $post_id ) ? 1 : 0;
							},
							'update_callback' => function ( $value, $post_object ) {
								$post_id = 0;
								if ( $post_object instanceof WP_Post ) {
									$post_id = (int) $post_object->ID;
								} elseif ( is_array( $post_object ) && isset( $post_object['id'] ) ) {
									$post_id = (int) $post_object['id'];
								}

								if ( $post_id <= 0 ) {
									return new WP_Error( 'rest_invalid_post', __( 'Invalid post object.', 'frontre' ), [ 'status' => 400 ] );
								}
								if ( ! current_user_can( 'edit_post', $post_id ) ) {
									return new WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to edit this post.', 'frontre' ), [ 'status' => 403 ] );
								}
								$is_favorite = filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
								if ( $is_favorite === null ) {
									return new WP_Error( 'rest_invalid_param', __( 'Invalid is_favorite value.', 'frontre' ), [ 'status' => 400 ] );
								}
								$this->set_favorite_flag( $post_id, $is_favorite );
								return true;
							},
							'schema' => [
								'description' => __( 'Favorite flag for post list and frontend queries.', 'frontre' ),
								'type'        => 'integer',
								'context'     => [ 'view', 'edit' ],
							],
						]
					);
				}
			}

			public function filter_rest_query( array $args, WP_REST_Request $request ): array {
				if ( ! $this->is_feature_enabled( 'enable_rest_api' ) ) {
					return $args;
				}

				$raw = $request->get_param( 'sp_favorite' );
				if ( $raw === null || $raw === '' ) {
					return $args;
				}

				$flag = filter_var( $raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
				if ( $flag === null ) {
					return $args;
				}

				$meta_query = [];
				if ( isset( $args['meta_query'] ) && is_array( $args['meta_query'] ) ) {
					$meta_query = $args['meta_query'];
				}

				if ( $flag ) {
					$meta_query[] = [
						'key'     => self::META_KEY,
						'value'   => '1',
						'compare' => '=',
					];
				} else {
					$meta_query[] = [
						'relation' => 'OR',
						[
							'key'     => self::META_KEY,
							'compare' => 'NOT EXISTS',
						],
						[
							'key'     => self::META_KEY,
							'value'   => '1',
							'compare' => '!=',
						],
					];
				}

				$args['meta_query'] = $meta_query;

				return $args;
			}

			public function admin_assets( string $hook ): void {
				if ( $hook === 'settings_page_' . self::PAGE_SLUG ) {
					wp_register_style( 'sp-favorite-posts-settings-inline', '', [], self::VERSION );
					wp_enqueue_style( 'sp-favorite-posts-settings-inline' );
					wp_add_inline_style( 'sp-favorite-posts-settings-inline', $this->settings_css() );

					wp_register_script( 'sp-favorite-posts-settings-inline', '', [ 'jquery' ], self::VERSION, true );
					wp_enqueue_script( 'sp-favorite-posts-settings-inline' );
					wp_add_inline_script( 'sp-favorite-posts-settings-inline', $this->settings_js() );
					return;
				}

				if ( $hook !== 'edit.php' ) {
					return;
				}

				$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
				if ( ! $screen || empty( $screen->post_type ) ) {
					return;
				}

				$post_type = sanitize_key( (string) $screen->post_type );
				if ( ! $this->is_enabled_post_type( $post_type ) ) {
					return;
				}

				wp_register_style( 'sp-favorite-posts-inline', '', [], self::VERSION );
				wp_enqueue_style( 'sp-favorite-posts-inline' );
				wp_add_inline_style( 'sp-favorite-posts-inline', $this->admin_css() );

				wp_register_script( 'sp-favorite-posts-inline', '', [ 'jquery' ], self::VERSION, true );
				wp_enqueue_script( 'sp-favorite-posts-inline' );
				wp_add_inline_script(
					'sp-favorite-posts-inline',
					'window.SPFavoritePostsCfg=' . wp_json_encode(
						[
							'quickEditEnabled' => $this->is_feature_enabled( 'enable_quick_edit' ) ? 1 : 0,
						]
					) . ';',
					'before'
				);
				wp_add_inline_script( 'sp-favorite-posts-inline', $this->admin_js() );
			}

			private function settings_css(): string {
				return <<<'CSS'
                    .sp-favorite-settings-form {
                        margin-top: 16px;
                        display: grid;
                        grid-template-columns: repeat(2, 1fr);
                        gap: 16px;
                    }
                    .sp-favorite-settings-box {
                        max-width: 100%;
                        background: #fff;
                        border: 1px solid #dcdcde;
                        border-radius: 8px;
                        padding: 14px;
                        
                        input {
                            margin: 0;
                        }
                    }
                    .sp-favorite-settings-row {
                        display: flex;
                        align-items: flex-start;
                        gap: 8px;
                        padding: 6px 0;
                    }
                    .sp-favorite-settings-row code {
                        margin-left: 6px;
                    }
                    .sp-favorite-settings-actions {
                        display: flex;
                        gap: 8px;
                        margin-bottom: 8px;
                    }
                CSS;
			}

			private function settings_js(): string {
				return <<<'JS'
                jQuery(function ($) {
                    $('#sp-favorite-select-all').on('click', function () {
                        $('.sp-favorite-posttype-checkbox').prop('checked', true);
                    });
                    $('#sp-favorite-clear-all').on('click', function () {
                        $('.sp-favorite-posttype-checkbox').prop('checked', false);
                    });
                });
                JS;
			}

			private function admin_css(): string {
				return <<<'CSS'
                    .fixed .column-sp_favorite_post {
                        width: 90px;
                        text-align: center;
                    }
                    .column-sp_favorite_post .sp-fav-post-toggle {
                        display: inline-flex;
                        align-items: center;
                        justify-content: center;
                        width: 30px;
                        height: 30px;
                        background: transparent;
                        border: 0;
                        border-radius: 4px;
                        padding: 0;
                        cursor: pointer;
                    }
                    .column-sp_favorite_post .sp-fav-post-toggle:focus {
                        outline: none;
                        box-shadow: 0 0 0 3px rgba(34, 113, 177, .18);
                    }
                    .column-sp_favorite_post .sp-fav-post-toggle .dashicons {
                        width: 20px;
                        height: 20px;
                        font-size: 20px;
                        line-height: 20px;
                    }
                    .column-sp_favorite_post .sp-fav-post-toggle.is-on .dashicons {
                        color: #f6b300;
                    }
                    .column-sp_favorite_post .sp-fav-post-toggle.is-off .dashicons {
                        color: #f6b300;
                        opacity: .35;
                    }
                    .column-sp_favorite_post .sp-fav-post-toggle.is-saving {
                        opacity: .6;
                        pointer-events: none;
                    }
                    .inline-edit-row fieldset.sp-fav-quick-edit .inline-edit-col {
                        padding-top: 0;
                    }
                    CSS;
			}

			private function admin_js(): string {
				return <<<'JS'
                    jQuery(function ($) {
                        var cfg = window.SPFavoritePostsCfg || {};
                    
                        function render($btn, isOn) {
                            $btn.toggleClass('is-on', !!isOn).toggleClass('is-off', !isOn).attr('aria-pressed', isOn ? 'true' : 'false');
                            const $icon = $btn.find('.dashicons');
                            $icon.removeClass('dashicons-star-filled dashicons-star-empty').addClass(isOn ? 'dashicons-star-filled' : 'dashicons-star-empty');
                        }
                    
                        function updateRowData(postId, isOn) {
                            $('#post-' + postId).find('.sp-fav-row-data').attr('data-fav', isOn ? '1' : '0');
                        }
                    
                        $(document).on('click', '.sp-fav-post-toggle', function (e) {
                            e.preventDefault();
                    
                            const $btn = $(this);
                            if ($btn.hasClass('is-saving')) return;
                    
                            const postId = parseInt($btn.data('post-id'), 10);
                            const nonce = String($btn.data('nonce') || '');
                            const current = $btn.attr('aria-pressed') === 'true';
                            const next = current ? 0 : 1;
                    
                            if (!postId || !nonce) return;
                    
                            $btn.addClass('is-saving');
                    
                            $.ajax({
                                url: (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php'),
                                type: 'POST',
                                dataType: 'json',
                                data: {
                                    action: 'sp_favorite_post_toggle',
                                    post_id: postId,
                                    value: next,
                                    nonce: nonce
                                }
                            }).done(function (resp) {
                                if (resp && resp.success && resp.data) {
                                    const on = !!resp.data.is_favorite;
                                    render($btn, on);
                                    updateRowData(postId, on);
                                } else {
                                    render($btn, current);
                                }
                            }).fail(function () {
                                render($btn, current);
                            }).always(function () {
                                $btn.removeClass('is-saving');
                            });
                        });
                    
                        if (parseInt(cfg.quickEditEnabled || 0, 10) === 1 && typeof inlineEditPost !== 'undefined') {
                            const wpInlineEdit = inlineEditPost.edit;
                    
                            inlineEditPost.edit = function (id) {
                                wpInlineEdit.apply(this, arguments);
                    
                                let postId = 0;
                                if (typeof id === 'object') {
                                    postId = parseInt(this.getId(id), 10);
                                } else {
                                    postId = parseInt(id, 10);
                                }
                                if (!postId) return;
                    
                                const fav = parseInt($('#post-' + postId).find('.sp-fav-row-data').data('fav'), 10) === 1;
                                $('#edit-' + postId).find('input[name="sp_favorite_quickedit"]').prop('checked', fav);
                            };
                        }
                    });
                    JS;
			}

			public function ajax_toggle_favorite(): void {
				if ( ! is_admin() ) {
					wp_send_json_error( [ 'message' => 'Invalid context' ], 400 );
				}

				$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
				$value   = isset( $_POST['value'] ) ? (int) $_POST['value'] : 0;
				$nonce   = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['nonce'] ) ) : '';

				if ( $post_id <= 0 ) {
					wp_send_json_error( [ 'message' => 'Invalid post ID' ], 400 );
				}

				if ( ! wp_verify_nonce( $nonce, self::NONCE_KEY . '_' . $post_id ) ) {
					wp_send_json_error( [ 'message' => 'Invalid nonce' ], 403 );
				}

				if ( ! current_user_can( 'edit_post', $post_id ) ) {
					wp_send_json_error( [ 'message' => 'Permission denied' ], 403 );
				}

				$post = get_post( $post_id );
				if ( ! ( $post instanceof WP_Post ) ) {
					wp_send_json_error( [ 'message' => 'Post not found' ], 404 );
				}

				$post_type = sanitize_key( (string) $post->post_type );
				if ( ! $this->is_enabled_post_type( $post_type ) ) {
					wp_send_json_error( [ 'message' => 'Favorite feature disabled for this post type' ], 400 );
				}

				$is_favorite = $value === 1;
				$this->set_favorite_flag( $post_id, $is_favorite );

				wp_send_json_success(
					[
						'post_id'     => $post_id,
						'is_favorite' => $is_favorite ? 1 : 0,
					]
				);
			}

			public function shortcode( array $atts = [] ): string {
				$atts = shortcode_atts(
					[
						'post_type'      => 'any',
						'card'           => '',
						'posts_per_page' => '-1',
					],
					$atts,
					'sp_favorite_posts'
				);

				$post_type      = sanitize_key( (string) $atts['post_type'] );
				$card           = sanitize_file_name( basename( (string) $atts['card'] ) );
				$posts_per_page = (int) $atts['posts_per_page'];
				if ( $posts_per_page === 0 || $posts_per_page < -1 ) {
					$posts_per_page = -1;
				}

				if ( $card === '' ) {
					return '';
				}

				if ( strtolower( (string) pathinfo( $card, PATHINFO_EXTENSION ) ) !== 'php' ) {
					$card .= '.php';
				}

				$templates_dir = realpath( trailingslashit( get_template_directory() ) . 'templates' );
				$template_file = trailingslashit( get_template_directory() ) . 'templates/' . $card;
				$template_path = realpath( $template_file );

				if ( ! is_string( $templates_dir ) || $templates_dir === '' ) {
					return '';
				}
				if ( ! is_string( $template_path ) || $template_path === '' ) {
					return '';
				}

				$templates_prefix = trailingslashit( wp_normalize_path( $templates_dir ) );
				$template_norm    = wp_normalize_path( $template_path );

				if ( strpos( $template_norm, $templates_prefix ) !== 0 ) {
					return '';
				}

				if ( ! is_file( $template_path ) || ! is_readable( $template_path ) ) {
					return '';
				}

				$query = new WP_Query(
					[
						'post_type'      => $post_type !== '' ? $post_type : 'any',
						'post_status'    => 'publish',
						'posts_per_page' => $posts_per_page,
						'meta_key'       => self::META_KEY,
						'meta_value'     => '1',
						'orderby'        => [
							'menu_order' => 'ASC',
							'date'       => 'DESC',
						],
					]
				);

				if ( ! $query->have_posts() ) {
					return '';
				}

				ob_start();

				while ( $query->have_posts() ) {
					$query->the_post();
					$post_id = (int) get_the_ID();
					if ( $post_id <= 0 ) {
						continue;
					}

					include $template_path;
				}

				wp_reset_postdata();
				return (string) ob_get_clean();
			}
		}

		SP_Favorite_Posts::get();
	}

	if ( ! function_exists( 'sp_is_favorite_post' ) ) {
		/**
		 * Checks if post is marked as favorite in admin.
		 */
		function sp_is_favorite_post( int $post_id ): bool {
			return (int) get_post_meta( $post_id, '_sp_favorite_post', true ) === 1;
		}
	}

	if ( ! function_exists( 'sp_set_favorite_post' ) ) {
		/**
		 * Sets favorite status for a post.
		 *
		 * Example:
		 * sp_set_favorite_post( 123, true );
		 */
		function sp_set_favorite_post( int $post_id, bool $is_favorite = true ): bool {
			$post_id = (int) $post_id;
			if ( $post_id <= 0 || ! get_post( $post_id ) ) {
				return false;
			}

			if ( $is_favorite ) {
				update_post_meta( $post_id, '_sp_favorite_post', '1' );
				return true;
			}

			delete_post_meta( $post_id, '_sp_favorite_post' );
			return false;
		}
	}

	if ( ! function_exists( 'sp_toggle_favorite_post' ) ) {
		/**
		 * Toggles favorite status and returns current state.
		 *
		 * Example:
		 * $is_favorite_now = sp_toggle_favorite_post( 123 );
		 */
		function sp_toggle_favorite_post( int $post_id ): bool {
			$current = sp_is_favorite_post( $post_id );
			return sp_set_favorite_post( $post_id, ! $current );
		}
	}

	if ( ! function_exists( 'sp_get_favorite_post_ids' ) ) {
		/**
		 * Returns favorite post IDs.
		 *
		 * Example:
		 * $ids = sp_get_favorite_post_ids(['post_type' => 'blog', 'posts_per_page' => 10]);
		 */
		function sp_get_favorite_post_ids( array $args = [] ): array {
			$q = sp_get_favorite_posts(
				array_merge(
					$args,
					[
						'fields'        => 'ids',
						'no_found_rows' => true,
					]
				)
			);

			return array_values( array_filter( array_map( 'intval', (array) $q->posts ), static fn( int $id ): bool => $id > 0 ) );
		}
	}

	if ( ! function_exists( 'sp_get_favorite_posts' ) ) {
		/**
		 * Frontend helper to get favorite posts.
		 *
		 * Example:
		 * $favorites = sp_get_favorite_posts([
		 *   'post_type' => 'blog',
		 *   'posts_per_page' => 6,
		 * ]);
		 */
		function sp_get_favorite_posts( array $args = [] ): WP_Query {
			$defaults = [
				'post_type'      => 'any',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'meta_key'       => '_sp_favorite_post',
				'meta_value'     => '1',
				'orderby'        => [
					'menu_order' => 'ASC',
					'date'       => 'DESC',
				],
				'order'          => 'DESC',
			];

			return new WP_Query( wp_parse_args( $args, $defaults ) );
		}
	}
