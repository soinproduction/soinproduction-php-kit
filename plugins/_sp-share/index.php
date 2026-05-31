<?php
	/**
	 * Plugin Name: SP Social Share
	 * Description: Customizable social sharing buttons for any post type
	 * Version: 3.0.0
	 */

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	define( 'SP_SHARE_VER', '3.0.0' );

	class SP_Social_Share {

		private static $i = null;

		public static function get() {
			if ( ! self::$i ) {
				self::$i = new self();
			}

			return self::$i;
		}

		private function __construct() {
			add_action( 'admin_menu', [ $this, 'menu' ] );
			add_action( 'admin_init', [ $this, 'settings' ] );
			add_action( 'admin_enqueue_scripts', [ $this, 'admin_assets' ] );
			add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );
			add_action( 'add_meta_boxes', [ $this, 'meta_box' ] );
			add_action( 'save_post', [ $this, 'save_meta' ] );
			add_shortcode( 'sp_social_share', [ $this, 'shortcode' ] );
			add_action( 'wp_ajax_sp_share_save_networks', [ $this, 'ajax_save_networks' ] );
			add_action( 'wp_ajax_sp_share_save_settings', [ $this, 'ajax_save_settings' ] );
		}

		// ── Data ──────────────────────────────────────────────────────────────────

		private function default_networks() {
			return [
				[
					'key'       => 'facebook',
					'label'     => 'Facebook',
					'enabled'   => 1,
					'color'     => '#1877F2',
					'url'       => 'https://www.facebook.com/sharer/sharer.php?u={url}',
					'icon_type' => 'svg',
					'icon_svg'  => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>',
					'icon_img'  => ''
				],
				[
					'key'       => 'instagram',
					'label'     => 'Instagram',
					'enabled'   => 1,
					'color'     => '#E1306C',
					'url'       => 'https://www.instagram.com/',
					'icon_type' => 'svg',
					'icon_svg'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>',
					'icon_img'  => ''
				],
				[
					'key'       => 'linkedin',
					'label'     => 'LinkedIn',
					'enabled'   => 1,
					'color'     => '#0A66C2',
					'url'       => 'https://www.linkedin.com/shareArticle?mini=true&url={url}&title={title}',
					'icon_type' => 'svg',
					'icon_svg'  => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-4 0v7h-4v-7a6 6 0 0 1 6-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/></svg>',
					'icon_img'  => ''
				],
				[
					'key'       => 'twitter',
					'label'     => 'X (Twitter)',
					'enabled'   => 1,
					'color'     => '#000000',
					'url'       => 'https://twitter.com/intent/tweet?url={url}&text={title}',
					'icon_type' => 'svg',
					'icon_svg'  => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>',
					'icon_img'  => ''
				],
				[
					'key'       => 'whatsapp',
					'label'     => 'WhatsApp',
					'enabled'   => 0,
					'color'     => '#25D366',
					'url'       => 'https://wa.me/?text={title}%20{url}',
					'icon_type' => 'svg',
					'icon_svg'  => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z"/></svg>',
					'icon_img'  => ''
				],
				[
					'key'       => 'telegram',
					'label'     => 'Telegram',
					'enabled'   => 0,
					'color'     => '#2AABEE',
					'url'       => 'https://t.me/share/url?url={url}&text={title}',
					'icon_type' => 'svg',
					'icon_svg'  => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>',
					'icon_img'  => ''
				],
				[
					'key'       => 'pinterest',
					'label'     => 'Pinterest',
					'enabled'   => 0,
					'color'     => '#E60023',
					'url'       => 'https://pinterest.com/pin/create/button/?url={url}&description={title}',
					'icon_type' => 'svg',
					'icon_svg'  => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.373 0 0 5.373 0 12c0 5.084 3.163 9.426 7.627 11.174-.105-.949-.2-2.405.042-3.441.218-.937 1.407-5.965 1.407-5.965s-.359-.719-.359-1.782c0-1.668.967-2.914 2.171-2.914 1.023 0 1.518.769 1.518 1.69 0 1.029-.655 2.568-.994 3.995-.283 1.194.599 2.169 1.777 2.169 2.133 0 3.772-2.249 3.772-5.495 0-2.873-2.064-4.882-5.012-4.882-3.414 0-5.418 2.561-5.418 5.207 0 1.031.397 2.138.893 2.738a.36.36 0 0 1 .083.345l-.333 1.36c-.053.22-.174.267-.402.161-1.499-.698-2.436-2.889-2.436-4.649 0-3.785 2.75-7.262 7.929-7.262 4.163 0 7.398 2.967 7.398 6.931 0 4.136-2.607 7.464-6.227 7.464-1.216 0-2.359-.632-2.75-1.378l-.748 2.853c-.271 1.043-1.002 2.35-1.492 3.146C9.57 23.812 10.763 24 12 24c6.627 0 12-5.373 12-12S18.627 0 12 0z"/></svg>',
					'icon_img'  => ''
				],
				[
					'key'       => 'email',
					'label'     => 'Email',
					'enabled'   => 0,
					'color'     => '#6B7280',
					'url'       => 'mailto:?subject={title}&body={url}',
					'icon_type' => 'svg',
					'icon_svg'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>',
					'icon_img'  => ''
				],
			];
		}

		private function default_settings() {
			return [
				'label'          => 'Share to social media',
				'post_types'     => [ 'post', 'page' ],
				'output_styles'  => 1,
				'btn_size'       => 52,
				'btn_size_min'   => 40,
				'icon_size'      => 22,
				'icon_size_min'  => 16,
				'border_radius'  => 12,
				'border_width'   => 1,
				'border_opacity' => 20,
				'bg_opacity'     => 12,
				'gap'            => 10,
			];
		}

		public function networks() {
			$s = get_option( 'sp_share_networks', null );

			return $s !== null ? $s : $this->default_networks();
		}

		public function cfg() {
			return array_merge( $this->default_settings(), (array) get_option( 'sp_share_cfg', [] ) );
		}

		private function post_types() {
			return $this->cfg()['post_types'] ?? [ 'post', 'page' ];
		}

		private function enabled_networks(): array {
			$nets = array_filter( $this->networks(), static function ( $n ) {
				return ! empty( $n['enabled'] ) && ! empty( $n['url'] );
			} );

			return array_values( is_array( $nets ) ? $nets : [] );
		}

		private function post_can_render_share( int $post_id ): bool {
			if ( $post_id <= 0 ) {
				return false;
			}

			if ( ! in_array( get_post_type( $post_id ), $this->post_types(), true ) ) {
				return false;
			}

			$meta_enabled = get_post_meta( $post_id, '_sp_share_enabled', true );
			if ( $meta_enabled === '0' ) {
				return false;
			}

			return true;
		}

		// ── Admin ─────────────────────────────────────────────────────────────────

		public function menu() {
			add_options_page( 'Social Share', '<span style="display: flex;align-items: center;gap: 5px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24">
                  <path stroke="currentColor" stroke-width="1.5" d="M9 11.5a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0Z"/>
                  <path stroke="currentColor" stroke-linecap="round" stroke-width="1.5" d="M14.32 16.802 9 13.29M14.42 6.84 9.1 10.352" opacity="1"/>
                  <path stroke="currentColor" stroke-width="1.5" d="M19 18.5a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0ZM19 5.5a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0Z"/>
                </svg> Social Share
            </span>', 'manage_options', 'sp-social-share', [
				$this,
				'page'
			] );
		}

		public function settings() {
		}

		public function admin_assets( $hook ) {
			if ( $hook !== 'settings_page_sp-social-share' ) {
				return;
			}
			wp_enqueue_script( 'jquery-ui-sortable' );
			wp_enqueue_media();
		}

		public function ajax_save_networks() {
			check_ajax_referer( 'sp_share_admin', 'nonce' );
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error();
			}

			$allowed = [
				'svg'      => [ 'viewBox' => [], 'fill' => [], 'xmlns' => [], 'width' => [], 'height' => [] ],
				'path'     => [
					'd'            => [],
					'fill'         => [],
					'stroke'       => [],
					'stroke-width' => [],
					'fill-rule'    => [],
					'clip-rule'    => []
				],
				'rect'     => [ 'x' => [], 'y' => [], 'width' => [], 'height' => [], 'rx' => [], 'ry' => [] ],
				'circle'   => [ 'cx' => [], 'cy' => [], 'r' => [], 'fill' => [] ],
				'line'     => [ 'x1' => [], 'y1' => [], 'x2' => [], 'y2' => [] ],
				'polyline' => [ 'points' => [] ],
				'polygon'  => [ 'points' => [] ]
			];

			$nets = [];
			foreach ( (array) ( $_POST['networks'] ?? [] ) as $n ) {
				$nets[] = [
					'key'       => sanitize_key( $n['key'] ?? '' ),
					'label'     => sanitize_text_field( $n['label'] ?? '' ),
					'enabled'   => (int) ( $n['enabled'] ?? 0 ),
					'color'     => sanitize_text_field( $n['color'] ?? '#000000' ),
					'url'       => sanitize_text_field( $n['url'] ?? '' ),
					'icon_type' => in_array( $n['icon_type'] ?? '', [ 'svg', 'img' ] ) ? $n['icon_type'] : 'svg',
					'icon_svg'  => wp_kses( $n['icon_svg'] ?? '', $allowed ),
					'icon_img'  => esc_url_raw( $n['icon_img'] ?? '' ),
				];
			}
			update_option( 'sp_share_networks', $nets );
			wp_send_json_success();
		}

		public function ajax_save_settings() {
			check_ajax_referer( 'sp_share_admin', 'nonce' );
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error();
			}

			$d = $this->default_settings();
			$s = (array) ( $_POST['cfg'] ?? [] );

			$clean = [
				'label'          => sanitize_text_field( $s['label'] ?? $d['label'] ),
				'post_types'     => is_array( $s['post_types'] ?? null ) ? array_map( 'sanitize_key', $s['post_types'] ) : [],
				'output_styles'  => (int) ( $s['output_styles'] ?? 1 ),
				'btn_size'       => min( 200, max( 20, (int) ( $s['btn_size'] ?? $d['btn_size'] ) ) ),
				'btn_size_min'   => min( 200, max( 20, (int) ( $s['btn_size_min'] ?? $d['btn_size_min'] ) ) ),
				'icon_size'      => min( 120, max( 8, (int) ( $s['icon_size'] ?? $d['icon_size'] ) ) ),
				'icon_size_min'  => min( 120, max( 8, (int) ( $s['icon_size_min'] ?? $d['icon_size_min'] ) ) ),
				'border_radius'  => min( 100, max( 0, (int) ( $s['border_radius'] ?? $d['border_radius'] ) ) ),
				'border_width'   => min( 10, max( 0, (int) ( $s['border_width'] ?? $d['border_width'] ) ) ),
				'border_opacity' => min( 100, max( 0, (int) ( $s['border_opacity'] ?? $d['border_opacity'] ) ) ),
				'bg_opacity'     => min( 100, max( 0, (int) ( $s['bg_opacity'] ?? $d['bg_opacity'] ) ) ),
				'gap'            => min( 60, max( 0, (int) ( $s['gap'] ?? $d['gap'] ) ) ),
			];

			update_option( 'sp_share_cfg', $clean );
			wp_send_json_success();
		}

		// ── Page ──────────────────────────────────────────────────────────────────

		public function page() {
			$nets  = $this->networks();
			$cfg   = $this->cfg();
			$all   = get_post_types( [ 'public' => true ], 'objects' );
			$nonce = wp_create_nonce( 'sp_share_admin' );
			$pts   = $cfg['post_types'];
			?>
            <div class="sp-admin">

                <div class="sp-admin__header">
                    <div class="sp-admin__logo">
                        <span class="sp-admin__logo-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" fill="none" viewBox="0 0 24 24">
                              <path stroke="currentColor" stroke-width="1.5" d="M9 11.5a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0Z"/>
                              <path stroke="currentColor" stroke-linecap="round" stroke-width="1.5" d="M14.32 16.802 9 13.29M14.42 6.84 9.1 10.352" opacity="1"/>
                              <path stroke="currentColor" stroke-width="1.5" d="M19 18.5a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0ZM19 5.5a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0Z"/>
                            </svg>
                        </span>
                        <span class="sp-admin__logo-text">Social Share</span>
                    </div>
                    <div class="sp-admin__actions">
                        <button type="button" class="sp-btn sp-btn--ghost" id="sp-save-cfg">Save settings</button>
                        <button type="button" class="sp-btn sp-btn--primary" id="sp-save-nets">Save networks</button>
                        <span class="sp-saved" id="sp-saved">✓ Saved</span>
                    </div>
                </div>

                <div class="sp-admin__body">

                    <aside class="sp-sidebar">

                        <div class="sp-panel">
                            <div class="sp-panel__title">General</div>

                            <div class="sp-field">
                                <label class="sp-label">Label above buttons</label>
                                <input type="text" id="cfg-label" class="sp-input"
                                       value="<?php echo esc_attr( $cfg['label'] ); ?>">
                            </div>

                            <div class="sp-field">
                                <label class="sp-label">Post types</label>
                                <div class="sp-checks">
									<?php foreach ( $all as $pt ) : ?>
                                        <label class="sp-check">
                                            <input type="checkbox" class="cfg-pt"
                                                   value="<?php echo esc_attr( $pt->name ); ?>" <?php checked( in_array( $pt->name, $pts ) ); ?>>
                                            <span class="sp-check__box"></span>
                                            <span class="sp-check__label"><?php echo esc_html( $pt->label ); ?></span>
                                            <code><?php echo esc_html( $pt->name ); ?></code>
                                        </label>
									<?php endforeach; ?>
                                </div>
                            </div>

                            <div class="sp-field">
                                <div class="sp-toggle-row">
                                    <span class="sp-label" style="margin:0">Output frontend CSS</span>
									<?php $c = ! empty( $cfg['output_styles'] );
										$uid = 'cfg-styles'; ?>
                                    <label class="sp-ios-toggle">
                                        <input type="checkbox" id="<?php echo $uid; ?>" <?php checked( $c ); ?>>
                                        <span class="sp-ios-track"><span class="sp-ios-thumb"></span></span>
                                    </label>
                                </div>
                                <p class="sp-hint">Disable to write your own CSS.</p>
                            </div>
                        </div>

                        <div class="sp-panel sp-panel--usage">
                            <div class="sp-panel__title">Usage</div>
                            <p class="sp-hint">PHP template:</p>
                            <code class="sp-code">&lt;?php sp_social_share(); ?&gt;</code>
                            <p class="sp-hint">Shortcode:</p>
                            <code class="sp-code">[sp_social_share]</code>
                            <p class="sp-hint" style="margin-top:10px"><strong>{url}</strong> — page
                                permalink<br><strong>{title}</strong> — page title</p>
                        </div>

                    </aside>

                    <main class="sp-main">
                        <div class="sp-panel" style="display: grid;grid-template-columns: repeat(2, 1fr);gap:10px 20px;">
                            <div class="sp-panel__title" style="grid-column: 1/-1;margin:0;">Button style</div>

							<?php
								$ranges = [
									[
										'id'    => 'cfg-btn-size',
										'label' => 'Button size',
										'key'   => 'btn_size',
										'min'   => 20,
										'max'   => 120,
										'unit'  => 'px'
									],
									[
										'id'    => 'cfg-btn-min',
										'label' => 'Button size (mobile)',
										'key'   => 'btn_size_min',
										'min'   => 20,
										'max'   => 120,
										'unit'  => 'px'
									],
									[
										'id'    => 'cfg-icon-size',
										'label' => 'Icon size',
										'key'   => 'icon_size',
										'min'   => 8,
										'max'   => 80,
										'unit'  => 'px'
									],
									[
										'id'    => 'cfg-icon-min',
										'label' => 'Icon size (mobile)',
										'key'   => 'icon_size_min',
										'min'   => 8,
										'max'   => 80,
										'unit'  => 'px'
									],
									[
										'id'    => 'cfg-radius',
										'label' => 'Border radius',
										'key'   => 'border_radius',
										'min'   => 0,
										'max'   => 100,
										'unit'  => 'px'
									],
									[
										'id'    => 'cfg-border-w',
										'label' => 'Border width',
										'key'   => 'border_width',
										'min'   => 0,
										'max'   => 10,
										'unit'  => 'px'
									],
									[
										'id'    => 'cfg-border-op',
										'label' => 'Border opacity',
										'key'   => 'border_opacity',
										'min'   => 0,
										'max'   => 100,
										'unit'  => '%'
									],
									[
										'id'    => 'cfg-bg-op',
										'label' => 'Background opacity',
										'key'   => 'bg_opacity',
										'min'   => 0,
										'max'   => 100,
										'unit'  => '%'
									],
									[
										'id'    => 'cfg-gap',
										'label' => 'Gap between buttons',
										'key'   => 'gap',
										'min'   => 0,
										'max'   => 60,
										'unit'  => 'px'
									],
								];
								foreach ( $ranges as $r ) : ?>
                                    <div class="sp-field sp-field--range">
                                        <div class="sp-range-header">
                                            <label class="sp-label"><?php echo $r['label']; ?></label>
                                            <span class="sp-range-val"
                                                  id="<?php echo $r['id']; ?>-v"><?php echo (int) $cfg[ $r['key'] ]; ?><?php echo $r['unit']; ?></span>
                                        </div>
                                        <input type="range"
                                               id="<?php echo $r['id']; ?>"
                                               class="sp-range"
                                               min="<?php echo $r['min']; ?>"
                                               max="<?php echo $r['max']; ?>"
                                               value="<?php echo (int) $cfg[ $r['key'] ]; ?>"
                                               data-unit="<?php echo $r['unit']; ?>"
                                               oninput="document.getElementById('<?php echo $r['id']; ?>-v').textContent=this.value+'<?php echo $r['unit']; ?>'">
                                    </div>
								<?php endforeach; ?>
                        </div>
                        <div class="sp-panel">
                            <div class="sp-nets-header">
                                <div class="sp-panel__title">Networks</div>
                                <button type="button" class="sp-btn sp-btn--sm" id="sp-add-net">+ Add network</button>
                            </div>
                            <p class="sp-hint sp-hint--top">Drag to reorder · Toggle to enable/disable · Click row to
                                expand</p>

                            <div id="sp-nets-list" class="sp-nets-list">
								<?php foreach ( $nets as $i => $n ) {
									echo $this->net_row( $i, $n );
								} ?>
                            </div>
                        </div>
                    </main>

                </div>
            </div>

            <script type="text/template" id="sp-net-tpl">
				<?php echo $this->net_row( '__N__', [
					'key'       => '',
					'label'     => 'New network',
					'enabled'   => 1,
					'color'     => '#1877F2',
					'url'       => '',
					'icon_type' => 'svg',
					'icon_svg'  => '',
					'icon_img'  => ''
				] ); ?>
            </script>

            <style>
                /* ── Reset ── */
                .sp-admin * {
                    box-sizing: border-box;
                    margin: 0;
                    padding: 0
                }

                #wpcontent:has(.sp-admin) {
                    padding: 0;
                }

                /* ── Layout ── */
                .sp-admin {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                    font-size: 13px;
                    color: #1d2327;
                    background: #f0f0f1;
                    min-height: 100vh;
                    /*margin: 0 0 -50px 0;*/
                    /*width: calc(100% + 20px);*/
                    display: flex;
                    flex-direction: column;
                }

                .sp-admin__header {
                    background: #fff;
                    border-bottom: 1px solid #dcdcde;
                    padding: 0 24px;
                    height: 56px;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    position: sticky;
                    top: 32px;
                    z-index: 100;
                    box-shadow: 0 1px 3px rgba(0, 0, 0, .06)
                }

                .sp-admin__logo {
                    display: flex;
                    align-items: center;
                    gap: 8px
                }

                .sp-admin__logo-icon {
                    font-size: 18px
                }

                .sp-admin__logo-text {
                    font-size: 16px;
                    font-weight: 700;
                    color: #1d2327
                }

                .sp-admin__actions {
                    display: flex;
                    align-items: center;
                    gap: 8px
                }

                .sp-admin__body {
                    display: grid;
                    grid-template-columns:300px 1fr;
                    gap: 0;
                    min-height: calc(100vh - 88px)
                }

                /* ── Sidebar ── */
                .sp-sidebar {
                    background: #fff;
                    border-right: 1px solid #dcdcde;
                    padding: 20px;
                    display: flex;
                    flex-direction: column;
                    gap: 16px;
                    overflow-y: auto
                }

                .sp-main {
                    padding: 20px;
                    display: flex;
                    flex-direction: column;
                    gap: 16px
                }

                /* ── Panel ── */
                .sp-panel {
                    background: #fff;
                    border: 1px solid #dcdcde;
                    border-radius: 8px;
                    padding: 18px 20px
                }

                .sp-panel__title {
                    font-size: 12px;
                    font-weight: 700;
                    text-transform: uppercase;
                    letter-spacing: .6px;
                    color: #787c82;
                    margin-bottom: 14px;
                    padding-bottom: 10px;
                    border-bottom: 1px solid #f0f0f1
                }

                .sp-panel--usage {
                    background: #f8f8f8
                }

                /* ── Fields ── */
                .sp-field {
                    margin-bottom: 14px
                }

                .sp-field:last-child {
                    margin-bottom: 0
                }

                .sp-field--range {
                    margin-bottom: 12px
                }

                .sp-label {
                    display: block;
                    font-size: 12px;
                    font-weight: 600;
                    color: #444;
                    margin-bottom: 5px
                }

                .sp-input {
                    width: 100%;
                    padding: 7px 10px;
                    border: 1px solid #dcdcde;
                    border-radius: 5px;
                    font-size: 13px;
                    color: #1d2327;
                    background: #fff;
                    transition: border-color .15s
                }

                .sp-input:focus {
                    outline: none;
                    border-color: #2271b1;
                    box-shadow: 0 0 0 1px #2271b1
                }

                .sp-hint {
                    font-size: 11px;
                    color: #999;
                    margin-top: 4px;
                    line-height: 1.4
                }

                .sp-hint--top {
                    margin-bottom: 14px;
                    margin-top: -4px
                }

                .sp-code {
                    display: block;
                    background: #f0f0f1;
                    padding: 7px 10px;
                    border-radius: 4px;
                    font-size: 11px;
                    font-family: monospace;
                    margin-top: 4px;
                    margin-bottom: 8px
                }

                /* ── Checkboxes ── */
                .sp-checks {
                    display: flex;
                    flex-direction: column;
                    gap: 6px
                }

                .sp-check {
                    display: flex;
                    align-items: center;
                    gap: 6px;
                    cursor: pointer;
                    font-size: 12px;
                    color: #1d2327;
                    user-select: none
                }

                .sp-check input {
                    position: absolute;
                    opacity: 0;
                    width: 0;
                    height: 0
                }

                .sp-check__box {
                    width: 16px;
                    height: 16px;
                    border: 2px solid #bbb;
                    border-radius: 3px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    flex-shrink: 0;
                    background: #fff;
                    transition: all .15s
                }

                .sp-check input:checked ~ .sp-check__box {
                    background: #2271b1;
                    border-color: #2271b1
                }

                .sp-check input:checked ~ .sp-check__box::after {
                    content: "";
                    width: 4px;
                    height: 7px;
                    border: 2px solid #fff;
                    border-top: none;
                    border-left: none;
                    transform: rotate(45deg) translate(-1px, -1px);
                    display: block
                }

                .sp-check__label {
                    flex: 1
                }

                .sp-check code {
                    background: #f0f0f1;
                    padding: 1px 5px;
                    border-radius: 3px;
                    font-size: 10px;
                    color: #666
                }

                /* ── Range ── */
                .sp-range-header {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    margin-bottom: 5px
                }

                .sp-range-val {
                    font-size: 12px;
                    font-weight: 700;
                    color: #2271b1;
                    min-width: 36px;
                    text-align: right
                }

                .sp-range {
                    width: 100%;
                    height: 4px;
                    -webkit-appearance: none;
                    appearance: none;
                    background: #e0e0e0;
                    border-radius: 2px;
                    outline: none
                }

                .sp-range::-webkit-slider-thumb {
                    -webkit-appearance: none;
                    width: 16px;
                    height: 16px;
                    border-radius: 50%;
                    background: #2271b1;
                    cursor: pointer;
                    box-shadow: 0 1px 3px rgba(0, 0, 0, .2)
                }

                .sp-range::-moz-range-thumb {
                    width: 16px;
                    height: 16px;
                    border-radius: 50%;
                    background: #2271b1;
                    cursor: pointer;
                    border: none
                }

                /* ── iOS Toggle ── */
                .sp-toggle-row {
                    display: flex;
                    align-items: center;
                    justify-content: space-between
                }

                .sp-ios-toggle {
                    position: relative;
                    display: inline-block;
                    cursor: pointer
                }

                .sp-ios-toggle input {
                    position: absolute;
                    opacity: 0;
                    width: 0;
                    height: 0
                }

                .sp-ios-track {
                    display: block;
                    width: 40px;
                    height: 22px;
                    background: #c3c4c7;
                    border-radius: 22px;
                    transition: background .25s;
                    position: relative
                }

                .sp-ios-thumb {
                    position: absolute;
                    top: 2px;
                    left: 2px;
                    width: 18px;
                    height: 18px;
                    background: #fff;
                    border-radius: 50%;
                    transition: left .25s;
                    box-shadow: 0 1px 3px rgba(0, 0, 0, .25)
                }

                .sp-ios-toggle input:checked ~ .sp-ios-track {
                    background: #2271b1
                }

                .sp-ios-toggle input:checked ~ .sp-ios-track .sp-ios-thumb {
                    left: 20px
                }

                /* ── Buttons ── */
                .sp-btn {
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    padding: 7px 14px;
                    border-radius: 5px;
                    font-size: 13px;
                    font-weight: 500;
                    cursor: pointer;
                    transition: all .15s;
                    border: 1px solid transparent;
                    line-height: 1
                }

                .sp-btn--primary {
                    background: #2271b1;
                    color: #fff;
                    border-color: #2271b1
                }

                .sp-btn--primary:hover {
                    background: #135e96;
                    border-color: #135e96
                }

                .sp-btn--ghost {
                    background: transparent;
                    color: #2271b1;
                    border-color: #2271b1
                }

                .sp-btn--ghost:hover {
                    background: #f0f6fc
                }

                .sp-btn--sm {
                    background: #f0f0f1;
                    color: #444;
                    border-color: #dcdcde;
                    padding: 6px 12px;
                    font-size: 12px
                }

                .sp-btn--sm:hover {
                    background: #e0e0e0
                }

                .sp-btn:disabled {
                    opacity: .5;
                    cursor: not-allowed
                }

                .sp-saved {
                    font-size: 12px;
                    font-weight: 600;
                    color: #00a32a;
                    opacity: 0;
                    transition: opacity .3s;
                    pointer-events: none
                }

                .sp-saved.show {
                    opacity: 1
                }

                /* ── Networks header ── */
                .sp-nets-header {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    margin-bottom: 4px
                }

                .sp-nets-header .sp-panel__title {
                    margin-bottom: 0;
                    padding-bottom: 0;
                    border-bottom: none
                }

                .sp-nets-list {
                    display: flex;
                    flex-direction: column;
                    gap: 6px
                }

                /* ── Network row ── */
                .sp-net {
                    border: 1px solid #dcdcde;
                    border-radius: 7px;
                    background: #fff;
                    overflow: hidden;
                    transition: box-shadow .15s
                }

                .sp-net.disabled {
                    opacity: .45
                }

                .sp-net.open {
                    box-shadow: 0 2px 8px rgba(0, 0, 0, .08)
                }

                .sp-net-head {
                    display: grid;
                    grid-template-columns:28px 42px 1fr 120px 40px 30px;
                    gap: 12px;
                    align-items: center;
                    padding: 10px 14px;
                    cursor: pointer;
                    user-select: none
                }

                .sp-net-head:hover {
                    background: #fafafa
                }

                .sp-handle {
                    color: #c3c4c7;
                    cursor: grab;
                    font-size: 16px;
                    line-height: 1;
                    text-align: center
                }

                .sp-net-icon {
                    width: 38px;
                    height: 38px;
                    border-radius: 9px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    flex-shrink: 0
                }

                .sp-net-icon svg, .sp-net-icon img {
                    width: 20px;
                    height: 20px;
                    display: block;
                    flex-shrink: 0;
                    object-fit: contain
                }

                .sp-net-info {
                }

                .sp-net-name {
                    font-size: 13px;
                    font-weight: 600;
                    color: #1d2327;
                    line-height: 1.2
                }

                .sp-net-url {
                    font-size: 11px;
                    color: #bbb;
                    margin-top: 2px;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    white-space: nowrap;
                    max-width: 400px
                }

                .sp-net-color-dot {
                    width: 12px;
                    height: 12px;
                    border-radius: 50%;
                    display: inline-block;
                    border: 1px solid rgba(0, 0, 0, .1);
                    flex-shrink: 0
                }

                .sp-net-color-badge {
                    display: flex;
                    align-items: center;
                    gap: 6px;
                    font-size: 11px;
                    color: #787c82;
                    font-family: monospace;
                    white-space: nowrap
                }

                .sp-net-chevron {
                    color: #c3c4c7;
                    font-size: 9px;
                    text-align: center;
                    transition: transform .2s;
                    line-height: 1
                }

                .sp-net.open .sp-net-chevron {
                    transform: rotate(180deg)
                }

                .sp-net-del {
                    background: none;
                    border: none;
                    color: #d63638;
                    cursor: pointer;
                    padding: 4px;
                    border-radius: 4px;
                    font-size: 16px;
                    line-height: 1;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    transition: background .15s
                }

                .sp-net-del:hover {
                    background: #fef2f2
                }

                /* ── Network body ── */
                .sp-net-body {
                    display: none;
                    padding: 16px;
                    border-top: 1px solid #f0f0f1;
                    background: #fafafa
                }

                .sp-net.open .sp-net-body {
                    display: block
                }

                .sp-net-grid {
                    display: grid;
                    grid-template-columns:1fr 1fr;
                    gap: 12px
                }

                .sp-net-grid .full {
                    grid-column: 1/-1
                }

                .sp-nf {
                    display: flex;
                    flex-direction: column;
                    gap: 4px
                }

                .sp-nf label {
                    font-size: 11px;
                    font-weight: 600;
                    color: #787c82;
                    text-transform: uppercase;
                    letter-spacing: .5px
                }

                .sp-nf input[type=text], .sp-nf textarea {
                    width: 100%;
                    padding: 7px 10px;
                    border: 1px solid #dcdcde;
                    border-radius: 5px;
                    font-size: 12px;
                    color: #1d2327;
                    background: #fff;
                    transition: border-color .15s
                }

                .sp-nf input:focus, .sp-nf textarea:focus {
                    outline: none;
                    border-color: #2271b1;
                    box-shadow: 0 0 0 1px #2271b1
                }

                .sp-nf textarea {
                    height: 68px;
                    font-family: monospace;
                    resize: vertical
                }

                /* ── Color row ── */
                .sp-color-row {
                    display: flex;
                    align-items: center;
                    gap: 8px
                }

                .sp-color-row input[type=text] {
                    flex: 1
                }

                .sp-color-picker {
                    width: 34px;
                    height: 32px;
                    padding: 3px;
                    border: 1px solid #dcdcde;
                    border-radius: 5px;
                    cursor: pointer;
                    flex-shrink: 0
                }

                /* ── Icon tabs ── */
                .sp-icon-tabs {
                    display: flex;
                    border-bottom: 1px solid #dcdcde;
                    margin-bottom: 10px
                }

                .sp-icon-tab {
                    padding: 6px 12px;
                    font-size: 12px;
                    border: none;
                    background: none;
                    color: #787c82;
                    cursor: pointer;
                    border-bottom: 2px solid transparent;
                    margin-bottom: -1px;
                    font-weight: 500
                }

                .sp-icon-tab.active {
                    color: #2271b1;
                    border-bottom-color: #2271b1
                }

                .sp-icon-pane {
                    display: none
                }

                .sp-icon-pane.active {
                    display: block
                }

                .sp-img-preview {
                    width: 48px;
                    height: 48px;
                    border-radius: 8px;
                    border: 1px solid #dcdcde;
                    object-fit: contain;
                    display: none;
                    margin-top: 8px
                }

                .sp-img-preview.show {
                    display: block
                }

                .sp-img-btns {
                    display: flex;
                    gap: 8px;
                    margin-top: 6px
                }
            </style>

            <script>
                (function ($) {
                    const nonce = '<?php echo $nonce; ?>';
                    let N = <?php echo count( $nets ); ?>;

                    // Sortable
                    $(function () {
                        if ($.fn.sortable) {
                            $('#sp-nets-list').sortable({handle: '.sp-handle', axis: 'y', tolerance: 'pointer'});
                        }
                    });

                    // Toggle row
                    $(document).on('click', '.sp-net-head', function (e) {
                        if ($(e.target).closest('.sp-ios-toggle,.sp-net-del,input[type=color]').length) return;
                        $(this).closest('.sp-net').toggleClass('open');
                    });

                    // Toggle enabled
                    $(document).on('change', '.sp-net-enabled', function () {
                        $(this).closest('.sp-net').toggleClass('disabled', !this.checked);
                    });

                    // Update icon preview
                    function updateIcon(row) {
                        const type = row.find('.net-icon-type').val();
                        const color = row.find('.net-color-text').val() || '#000';
                        const icon = row.find('.sp-net-icon');

                        icon.css({background: color + '20', color: color});

                        if (type === 'img') {
                            const src = row.find('.net-img-url').val();
                            icon.html(src ? '<img src="' + src + '" style="width:20px;height:20px;object-fit:contain">' : '');
                        } else {
                            const svg = row.find('.net-svg').val();
                            icon.html(svg);
                            icon.find('svg').css({
                                width: '20px',
                                height: '20px',
                                display: 'block',
                                flexShrink: 0,
                                color: color
                            });
                        }

                        // Update badge
                        row.find('.sp-net-color-dot').css('background', color);
                        row.find('.sp-net-color-text-badge').text(color);
                    }

                    $(document).on('input change', '.net-svg,.net-color-text,.net-img-url', function () {
                        updateIcon($(this).closest('.sp-net'));
                    });

                    $(document).on('input', '.net-label', function () {
                        $(this).closest('.sp-net').find('.sp-net-name').text($(this).val() || 'Untitled');
                    });

                    $(document).on('input', '.net-url', function () {
                        $(this).closest('.sp-net').find('.sp-net-url').text($(this).val());
                    });

                    // Color picker sync
                    $(document).on('input', '.net-color-picker', function () {
                        $(this).siblings('.net-color-text').val(this.value).trigger('input');
                    });

                    // Icon tabs
                    $(document).on('click', '.sp-icon-tab', function () {
                        const row = $(this).closest('.sp-net');
                        const t = $(this).data('tab');
                        row.find('.sp-icon-tab').removeClass('active');
                        row.find('.sp-icon-pane').removeClass('active');
                        $(this).addClass('active');
                        row.find('.sp-icon-pane[data-pane="' + t + '"]').addClass('active');
                        row.find('.net-icon-type').val(t);
                    });

                    // Media upload
                    $(document).on('click', '.sp-upload-btn', function (e) {
                        e.preventDefault();
                        const row = $(this).closest('.sp-net');
                        const frame = wp.media({title: 'Select icon', multiple: false});
                        frame.on('select', function () {
                            const att = frame.state().get('selection').first().toJSON();
                            row.find('.net-img-url').val(att.url).trigger('input');
                            row.find('.sp-img-preview').attr('src', att.url).addClass('show');
                        });
                        frame.open();
                    });

                    $(document).on('click', '.sp-img-clear', function () {
                        const row = $(this).closest('.sp-net');
                        row.find('.net-img-url').val('').trigger('input');
                        row.find('.sp-img-preview').attr('src', '').removeClass('show');
                    });

                    // Add network
                    $('#sp-add-net').on('click', function () {
                        const tpl = $('#sp-net-tpl').html().replace(/__N__/g, N++);
                        $('#sp-nets-list').append(tpl);
                        if ($.fn.sortable) $('#sp-nets-list').sortable('refresh');
                        $('#sp-nets-list .sp-net').last().addClass('open');
                    });

                    // Delete
                    $(document).on('click', '.sp-net-del', function (e) {
                        e.stopPropagation();
                        if (confirm('Delete this network?')) $(this).closest('.sp-net').remove();
                    });

                    function flash() {
                        const s = $('#sp-saved').addClass('show');
                        setTimeout(() => s.removeClass('show'), 2500);
                    }

                    // Save networks
                    $('#sp-save-nets').on('click', function () {
                        const btn = $(this).prop('disabled', true).text('Saving...');
                        const nets = [];
                        $('#sp-nets-list .sp-net').each(function () {
                            const r = $(this);
                            nets.push({
                                key: r.find('.net-key').val(),
                                label: r.find('.net-label').val(),
                                enabled: r.find('.sp-net-enabled').is(':checked') ? 1 : 0,
                                color: r.find('.net-color-text').val(),
                                url: r.find('.net-url').val(),
                                icon_type: r.find('.net-icon-type').val(),
                                icon_svg: r.find('.net-svg').val(),
                                icon_img: r.find('.net-img-url').val(),
                            });
                        });
                        $.post(ajaxurl, {action: 'sp_share_save_networks', nonce, networks: nets}, function (res) {
                            btn.prop('disabled', false).text('Save networks');
                            if (res.success) flash();
                        });
                    });

                    // Save settings
                    $('#sp-save-cfg').on('click', function () {
                        const btn = $(this).prop('disabled', true).text('Saving...');
                        const pts = [];
                        $('.cfg-pt:checked').each(function () {
                            pts.push($(this).val());
                        });

                        $.post(ajaxurl, {
                            action: 'sp_share_save_settings',
                            nonce,
                            cfg: {
                                label: $('#cfg-label').val(),
                                post_types: pts,
                                output_styles: $('#cfg-styles').is(':checked') ? 1 : 0,
                                btn_size: $('#cfg-btn-size').val(),
                                btn_size_min: $('#cfg-btn-min').val(),
                                icon_size: $('#cfg-icon-size').val(),
                                icon_size_min: $('#cfg-icon-min').val(),
                                border_radius: $('#cfg-radius').val(),
                                border_width: $('#cfg-border-w').val(),
                                border_opacity: $('#cfg-border-op').val(),
                                bg_opacity: $('#cfg-bg-op').val(),
                                gap: $('#cfg-gap').val(),
                            }
                        }, function (res) {
                            btn.prop('disabled', false).text('Save settings');
                            if (res.success) flash();
                        });
                    });

                })(jQuery);
            </script>
			<?php
		}

		private function net_row( $i, $n ) {
			$color   = $n['color'] ?? '#1877F2';
			$label   = $n['label'] ?? '';
			$url     = $n['url'] ?? '';
			$enabled = ! empty( $n['enabled'] );
			$itype   = $n['icon_type'] ?? 'svg';
			$isvg    = $n['icon_svg'] ?? '';
			$iimg    = $n['icon_img'] ?? '';

			$icon_html = $itype === 'img' && $iimg
				? '<img src="' . esc_url( $iimg ) . '" style="width:20px;height:20px;object-fit:contain">'
				: $isvg;

			ob_start(); ?>
            <div class="sp-net<?php echo $enabled ? '' : ' disabled'; ?>">

                <div class="sp-net-head">
                    <span class="sp-handle">⠿</span>

                    <div class="sp-net-icon"
                         style="background:<?php echo esc_attr( $color ); ?>20;color:<?php echo esc_attr( $color ); ?>;">
						<?php echo $icon_html; ?>
                    </div>

                    <div class="sp-net-info">
                        <div class="sp-net-name"><?php echo esc_html( $label ?: 'Untitled' ); ?></div>
                        <div class="sp-net-url"><?php echo esc_html( $url ); ?></div>
                    </div>

                    <div class="sp-net-color-badge">
                        <span class="sp-net-color-dot" style="background:<?php echo esc_attr( $color ); ?>"></span>
                        <span class="sp-net-color-text-badge"><?php echo esc_html( $color ); ?></span>
                    </div>

                    <label class="sp-ios-toggle" onclick="event.stopPropagation()">
                        <input type="checkbox" class="sp-net-enabled" <?php checked( $enabled ); ?>>
                        <span class="sp-ios-track"><span class="sp-ios-thumb"></span></span>
                    </label>

                    <span class="sp-net-chevron">▼</span>
                </div>

                <div class="sp-net-body">
                    <div class="sp-net-grid">
                        <div class="sp-nf">
                            <label>Key</label>
                            <input type="text" class="net-key" value="<?php echo esc_attr( $n['key'] ?? '' ); ?>"
                                   placeholder="facebook">
                        </div>
                        <div class="sp-nf">
                            <label>Label</label>
                            <input type="text" class="net-label" value="<?php echo esc_attr( $label ); ?>"
                                   placeholder="Facebook">
                        </div>
                        <div class="sp-nf full">
                            <label>URL template — use {url} and {title}</label>
                            <input type="text" class="net-url" value="<?php echo esc_attr( $url ); ?>"
                                   placeholder="https://...?url={url}">
                        </div>
                        <div class="sp-nf full">
                            <label>Color — any CSS: #hex, rgba(), hsl()</label>
                            <div class="sp-color-row">
                                <input type="text" class="net-color-text" value="<?php echo esc_attr( $color ); ?>"
                                       placeholder="#1877F2 or rgba(24,119,242,0.15)">
                                <input type="color" class="sp-color-picker net-color-picker" value="#1877f2"
                                       style="width:34px;height:32px;padding:3px;border:1px solid #dcdcde;border-radius:5px;cursor:pointer;flex-shrink:0">
                            </div>
                        </div>
                        <div class="sp-nf full">
                            <label>Icon</label>
                            <input type="hidden" class="net-icon-type" value="<?php echo esc_attr( $itype ); ?>">
                            <div class="sp-icon-tabs">
                                <button type="button"
                                        class="sp-icon-tab<?php echo $itype === 'svg' ? ' active' : ''; ?>"
                                        data-tab="svg">SVG code
                                </button>
                                <button type="button"
                                        class="sp-icon-tab<?php echo $itype === 'img' ? ' active' : ''; ?>"
                                        data-tab="img">Image
                                </button>
                            </div>
                            <div class="sp-icon-pane<?php echo $itype === 'svg' ? ' active' : ''; ?>" data-pane="svg">
                                <textarea class="net-svg"
                                          placeholder="<svg viewBox=&quot;0 0 24 24&quot;>...</svg>"><?php echo esc_textarea( $isvg ); ?></textarea>
                            </div>
                            <div class="sp-icon-pane<?php echo $itype === 'img' ? ' active' : ''; ?>" data-pane="img">
                                <div class="sp-img-btns">
                                    <button type="button" class="sp-btn sp-btn--sm sp-upload-btn">Choose image</button>
                                    <button type="button" class="sp-btn sp-btn--sm sp-img-clear">✕ Clear</button>
                                </div>
                                <input type="hidden" class="net-img-url" value="<?php echo esc_url( $iimg ); ?>">
                                <img class="sp-img-preview<?php echo $iimg ? ' show' : ''; ?>"
                                     src="<?php echo esc_url( $iimg ); ?>" alt="">
                            </div>
                        </div>
                        <div class="full" style="display:flex;justify-content:flex-end;padding-top:4px">
                            <button type="button" class="sp-net-del" title="Delete network">🗑 Delete</button>
                        </div>
                    </div>
                </div>
            </div>
			<?php return ob_get_clean();
		}

		// ── Meta box ──────────────────────────────────────────────────────────────

		public function meta_box() {
			foreach ( $this->post_types() as $pt ) {
				add_meta_box( 'sp_share', '<span style="display: flex; align-items: center;gap: 5px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24">
                  <path stroke="#1c274c" stroke-width="1.5" d="M9 11.5a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0Z"/>
                  <path stroke="#1c274c" stroke-linecap="round" stroke-width="1.5" d="M14.32 16.802 9 13.29M14.42 6.84 9.1 10.352" opacity=".5"/>
                  <path stroke="#1c274c" stroke-width="1.5" d="M19 18.5a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0ZM19 5.5a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0Z"/>
                </svg> Social Share
                </span>', [ $this, 'meta_box_html' ], $pt, 'side' );
			}
		}

		public function meta_box_html( $post ) {
			wp_nonce_field( 'sp_share_meta', 'sp_share_meta_nonce' );
			$v   = get_post_meta( $post->ID, '_sp_share_enabled', true );
			$c   = $v === '' ? true : (bool) $v;
			$uid = 'sp_share_' . $post->ID;
			?>
            <style>
                .sp-meta-row {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    padding: 4px 0;
                    font-size: 13px;
                    color: #1d2327
                }

                .sp-ios-toggle {
                    position: relative;
                    display: inline-block;
                    cursor: pointer
                }

                .sp-ios-toggle input {
                    position: absolute;
                    opacity: 0;
                    width: 0;
                    height: 0
                }

                .sp-ios-track {
                    display: block;
                    width: 40px;
                    height: 22px;
                    background: #c3c4c7;
                    border-radius: 22px;
                    transition: background .25s;
                    position: relative
                }

                .sp-ios-thumb {
                    position: absolute;
                    top: 2px;
                    left: 2px;
                    width: 18px;
                    height: 18px;
                    background: #fff;
                    border-radius: 50%;
                    transition: left .25s;
                    box-shadow: 0 1px 3px rgba(0, 0, 0, .25)
                }

                .sp-ios-toggle input:checked ~ .sp-ios-track {
                    background: #2271b1
                }

                .sp-ios-toggle input:checked ~ .sp-ios-track .sp-ios-thumb {
                    left: 20px
                }
            </style>
            <div class="sp-meta-row">
                <span>Show sharing buttons</span>
                <label class="sp-ios-toggle">
                    <input type="checkbox" id="<?php echo $uid; ?>" name="sp_share_enabled"
                           value="1" <?php checked( $c ); ?>>
                    <span class="sp-ios-track"><span class="sp-ios-thumb"></span></span>
                </label>
            </div>
			<?php
		}

		public function save_meta( $id ) {
			if ( ! isset( $_POST['sp_share_meta_nonce'] ) ) {
				return;
			}
			if ( ! wp_verify_nonce( $_POST['sp_share_meta_nonce'], 'sp_share_meta' ) ) {
				return;
			}
			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
				return;
			}
			if ( ! current_user_can( 'edit_post', $id ) ) {
				return;
			}
			update_post_meta( $id, '_sp_share_enabled', isset( $_POST['sp_share_enabled'] ) ? 1 : 0 );
		}

		// ── Frontend ──────────────────────────────────────────────────────────────

		private function rem( $value ) {
			$rem = ( (int) $value ) / 10;
			$out = rtrim( rtrim( number_format( $rem, 2, '.', '' ), '0' ), '.' );

			return ( $out === '' ? '0' : $out ) . 'rem';
		}

		private function frontend_style_handle(): string {
			return 'sp-share-frontend';
		}

		private function frontend_styles_url(): string {
			return trailingslashit( THEME_URI ) . 'core/plugins/_sp-share/assets/sp-share-frontend.min.css';
		}

		private function frontend_dynamic_css( array $cfg ): string {
			$btn_size       = $this->rem( (int) ( $cfg['btn_size'] ?? 52 ) );
			$btn_size_min   = $this->rem( (int) ( $cfg['btn_size_min'] ?? 40 ) );
			$icon_size      = $this->rem( (int) ( $cfg['icon_size'] ?? 22 ) );
			$icon_size_min  = $this->rem( (int) ( $cfg['icon_size_min'] ?? 16 ) );
			$border_radius  = $this->rem( (int) ( $cfg['border_radius'] ?? 12 ) );
			$border_width   = $this->rem( (int) ( $cfg['border_width'] ?? 1 ) );
			$gap            = $this->rem( (int) ( $cfg['gap'] ?? 10 ) );
			$bg_opacity     = min( 100, max( 0, (int) ( $cfg['bg_opacity'] ?? 12 ) ) );
			$border_opacity = min( 100, max( 0, (int) ( $cfg['border_opacity'] ?? 20 ) ) );
			$bg_hover       = min( 100, $bg_opacity + 10 );
			$border_hover   = min( 100, $border_opacity + 20 );

			return implode(
				"\n",
				[
					'.sp-share__btns{gap:' . $gap . ';}',
					'.sp-share__btn{width:' . $btn_size . ';height:' . $btn_size . ';border-radius:' . $border_radius . ';border-style:solid;border-width:' . $border_width . ';background:color-mix(in srgb,currentColor ' . $bg_opacity . '%,transparent);border-color:color-mix(in srgb,currentColor ' . $border_opacity . '%,transparent);}',
					'.sp-share__btn:hover{background:color-mix(in srgb,currentColor ' . $bg_hover . '%,transparent);border-color:color-mix(in srgb,currentColor ' . $border_hover . '%,transparent);}',
					'.sp-share__btn svg,.sp-share__btn img{width:' . $icon_size . ';height:' . $icon_size . ';}',
					'@media (max-width: 767.98px){.sp-share__btn{width:' . $btn_size_min . ';height:' . $btn_size_min . ';}.sp-share__btn svg,.sp-share__btn img{width:' . $icon_size_min . ';height:' . $icon_size_min . ';}}',
				]
			);
		}

		public function enqueue_frontend_assets(): void {
			$cfg = $this->cfg();
			if ( empty( $cfg['output_styles'] ) || is_admin() ) {
				return;
			}

			$post_id = (int) get_queried_object_id();
			if ( ! $this->post_can_render_share( $post_id ) ) {
				return;
			}
			if ( ! $this->enabled_networks() ) {
				return;
			}

			$handle       = $this->frontend_style_handle();
			$css_file     = __DIR__ . '/assets/sp-share-frontend.min.css';
			$css_file_url = $this->frontend_styles_url();
			$version      = file_exists( $css_file ) ? (string) filemtime( $css_file ) : SP_SHARE_VER;

			wp_enqueue_style( $handle, $css_file_url, [], $version, 'all' );
			wp_add_inline_style( $handle, $this->frontend_dynamic_css( $cfg ) );
		}

		private function normalize_color( $color ): string {
			$color = trim( (string) $color );
			$hex   = sanitize_hex_color( $color );
			if ( is_string( $hex ) && $hex !== '' ) {
				return $hex;
			}

			return '#000000';
		}

		private function render_network_icon( array $network, string $label = '' ): string {
			$icon_type = (string) ( $network['icon_type'] ?? 'svg' );
			$icon_img  = trim( (string) ( $network['icon_img'] ?? '' ) );
			$icon_svg  = (string) ( $network['icon_svg'] ?? '' );

			if ( $icon_type === 'img' && $icon_img !== '' ) {
				$sprite_href = function_exists( 'sp_svg_sprite_href_for_ui_icon' )
					? trim( (string) sp_svg_sprite_href_for_ui_icon( $icon_img ) )
					: '';

				if ( $sprite_href !== '' ) {
					if ( function_exists( 'sp_svg_build_sprite_markup' ) ) {
						$sprite_markup = (string) sp_svg_build_sprite_markup( $sprite_href, [
							'class' => 'sp-share__icon',
						] );
						if ( $sprite_markup !== '' ) {
							return $sprite_markup;
						}
					}

					return '<svg class="sp-share__icon" aria-hidden="true" focusable="false"><use href="' . esc_url( $sprite_href ) . '"></use></svg>';
				}

				return '<img class="sp-share__icon" src="' . esc_url( $icon_img ) . '" alt="' . esc_attr( $label ) . '">';
			}

			return $icon_svg;
		}

		public function render( $post_id = null ) {
			if ( ! $post_id ) {
				$post_id = get_the_ID();
			}
			if ( ! $post_id ) {
				return '';
			}
			if ( ! $this->post_can_render_share( $post_id ) ) {
				return '';
			}

			$nets = $this->enabled_networks();
			if ( ! $nets ) {
				return '';
			}

			$cfg   = $this->cfg();
			$url   = urlencode( get_permalink( $post_id ) );
			$title = urlencode( get_the_title( $post_id ) );
			$lbl   = (string) ( $cfg['label'] ?? '' );

			ob_start(); ?>
            <div class="sp-share" <?php if ( $lbl !== '' ) : ?>data-title="<?php echo esc_attr( $lbl ); ?>"<?php endif; ?>>
                <ul class="sp-share__btns">
					<?php foreach ( $nets as $n ) :
						$href   = str_replace( [ '{url}', '{title}' ], [ $url, $title ], $n['url'] );
						$color  = $this->normalize_color( $n['color'] ?? '#000000' );
						$label  = (string) ( $n['label'] ?? '' );
						$mail   = str_starts_with( $href, 'mailto:' );
						$target = $mail ? '' : 'target="_blank" rel="noopener noreferrer"';
						$icon   = $this->render_network_icon( (array) $n, $label );
						?>
                       <li class="sp-share__btns-item">
                           <a href="<?php echo esc_url( $href ); ?>" class="sp-share__btn"
                              style="color:<?php echo esc_attr( $color ); ?>;"
								<?php echo $target; ?>
                              title="<?php echo esc_attr( $label ); ?>">
							   <?php echo $icon; ?>
                               <span class="visually-hidden"><?php echo esc_html( $label ); ?></span>
                           </a>
                       </li>
					<?php endforeach; ?>
                </ul>
            </div>
			<?php return ob_get_clean();
		}

		public function shortcode( $a ) {
			$a = shortcode_atts( [ 'id' => get_the_ID() ], $a );

			return $this->render( (int) $a['id'] );
		}
	}

	SP_Social_Share::get();

	function sp_social_share( $id = null ) {
		echo SP_Social_Share::get()->render( $id );
	}
