<?php
	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	if ( ! class_exists( 'SP_Tag_Manager' ) ) {
		class SP_Tag_Manager {
			private const OPT_KEY      = 'sp_tag_manager_cfg';
			private const NONCE_ACTION = 'sp_tag_manager_admin';
			private const PAGE_SLUG    = 'sp-tag-manager';
			private const VERSION      = '1.0.1';

			private static ?self $instance = null;

			public static function get(): self {
				if ( ! self::$instance ) {
					self::$instance = new self();
				}

				return self::$instance;
			}

			private function __construct() {
				add_action( 'admin_menu', [ $this, 'menu' ] );
				add_action( 'admin_enqueue_scripts', [ $this, 'admin_assets' ] );
				add_action( 'wp_ajax_sp_tag_manager_save', [ $this, 'ajax_save' ] );

				add_action( 'wp_head', [ $this, 'output_head' ], 1 );
				add_action( 'wp_body_open', [ $this, 'output_body_open' ], 1 );
				add_action( 'wp_footer', [ $this, 'output_footer' ], 100 );
			}

			private function defaults(): array {
				return [
					'enabled'              => 1,
					'disable_for_logged'   => 1,
					'disable_on_admin'     => 1,
					'gtm_enabled'          => 1,
					'gtm_id'               => '',
					'gtm_data_layer'       => 'dataLayer',
					'gtm_strategy'         => 'after_interaction',
					'gtm_delay_ms'         => 2500,
					'consent_mode_enabled' => 1,
					'consent_default'      => 'denied',
					'consent_wait_ms'      => 500,
					'consent_cookie_sync'  => 1,
					'consent_cookie_key'   => 'sp_cookie_consent',
					'consent_cookie_grant' => 'granted',
					'consent_cookie_deny'  => 'denied',
					'custom_head'          => '',
					'custom_body_open'     => '',
					'custom_footer'        => '',
				];
			}

			private function cfg(): array {
				$raw = get_option( self::OPT_KEY, [] );
				if ( ! is_array( $raw ) ) {
					$raw = [];
				}

				return array_merge( $this->defaults(), $raw );
			}

			private function sanitize_gtm_id( string $value ): string {
				$value = strtoupper( trim( $value ) );
				if ( preg_match( '/^GTM-[A-Z0-9]+$/', $value ) ) {
					return $value;
				}

				return '';
			}

			private function sanitize_data_layer( string $value ): string {
				$value = trim( $value );
				if ( preg_match( '/^[A-Za-z_$][A-Za-z0-9_$]*$/', $value ) ) {
					return $value;
				}

				return 'dataLayer';
			}

			private function sanitize_strategy( string $value ): string {
				$allowed = [ 'immediate', 'after_delay', 'after_interaction' ];
				if ( in_array( $value, $allowed, true ) ) {
					return $value;
				}

				return 'after_interaction';
			}

			private function sanitize_consent_default( string $value ): string {
				return $value === 'granted' ? 'granted' : 'denied';
			}

			private function sanitize_cookie_key( string $value ): string {
				$value = trim( $value );
				if ( preg_match( '/^[A-Za-z0-9_-]{2,80}$/', $value ) ) {
					return $value;
				}

				return 'sp_cookie_consent';
			}

			private function sanitize_cookie_value( string $value, string $fallback ): string {
				$value = trim( $value );
				if ( preg_match( '/^[A-Za-z0-9_-]{1,80}$/', $value ) ) {
					return $value;
				}

				return $fallback;
			}

			private function snippet_allowed_html(): array {
				return [
					'script'   => [
						'src'             => true,
						'id'              => true,
						'class'           => true,
						'type'            => true,
						'async'           => true,
						'defer'           => true,
						'nomodule'        => true,
						'blocking'        => true,
						'crossorigin'     => true,
						'integrity'       => true,
						'referrerpolicy'  => true,
						'nonce'           => true,
						'fetchpriority'   => true,
						'data-*'          => true,
						'data-cookieconsent' => true,
						'data-category'   => true,
					],
					'noscript' => [
						'id'    => true,
						'class' => true,
					],
					'iframe'   => [
						'src'            => true,
						'height'         => true,
						'width'          => true,
						'frameborder'    => true,
						'style'          => true,
						'title'          => true,
						'loading'        => true,
						'referrerpolicy' => true,
						'allow'          => true,
						'allowfullscreen'=> true,
						'sandbox'        => true,
						'data-*'         => true,
						'aria-hidden'    => true,
						'tabindex'       => true,
					],
					'img'      => [
						'src'            => true,
						'alt'            => true,
						'height'         => true,
						'width'          => true,
						'style'          => true,
						'loading'        => true,
						'decoding'       => true,
						'referrerpolicy' => true,
						'data-*'         => true,
					],
					'link'     => [
						'rel'           => true,
						'href'          => true,
						'as'            => true,
						'type'          => true,
						'crossorigin'   => true,
						'media'         => true,
						'fetchpriority' => true,
						'data-*'        => true,
					],
					'meta'     => [
						'name'       => true,
						'content'    => true,
						'property'   => true,
						'http-equiv' => true,
						'charset'    => true,
					],
					'style'    => [
						'media' => true,
						'nonce' => true,
						'type'  => true,
					],
					'a'        => [
						'href'           => true,
						'id'             => true,
						'class'          => true,
						'style'          => true,
						'target'         => true,
						'rel'            => true,
						'referrerpolicy' => true,
						'data-*'         => true,
					],
					'div'      => [
						'id'    => true,
						'class' => true,
						'style' => true,
						'data-*'=> true,
					],
					'span'     => [
						'id'    => true,
						'class' => true,
						'style' => true,
						'data-*'=> true,
					],
				];
			}

			private function sanitize_snippet( string $value ): string {
				$value = trim( (string) $value );
				if ( $value === '' ) {
					return '';
				}

				return trim( wp_kses( $value, $this->snippet_allowed_html() ) );
			}

			private function sanitize_cfg( array $raw ): array {
				$d = $this->defaults();

				return [
					'enabled'            => ! empty( $raw['enabled'] ) ? 1 : 0,
					'disable_for_logged' => ! empty( $raw['disable_for_logged'] ) ? 1 : 0,
					'disable_on_admin'   => ! empty( $raw['disable_on_admin'] ) ? 1 : 0,
					'gtm_enabled'        => ! empty( $raw['gtm_enabled'] ) ? 1 : 0,
					'gtm_id'             => $this->sanitize_gtm_id( (string) ( $raw['gtm_id'] ?? '' ) ),
					'gtm_data_layer'     => $this->sanitize_data_layer( (string) ( $raw['gtm_data_layer'] ?? $d['gtm_data_layer'] ) ),
					'gtm_strategy'       => $this->sanitize_strategy( (string) ( $raw['gtm_strategy'] ?? $d['gtm_strategy'] ) ),
					'gtm_delay_ms'       => min( 15000, max( 0, (int) ( $raw['gtm_delay_ms'] ?? $d['gtm_delay_ms'] ) ) ),
					'consent_mode_enabled' => ! empty( $raw['consent_mode_enabled'] ) ? 1 : 0,
					'consent_default'      => $this->sanitize_consent_default( (string) ( $raw['consent_default'] ?? $d['consent_default'] ) ),
					'consent_wait_ms'      => min( 5000, max( 0, (int) ( $raw['consent_wait_ms'] ?? $d['consent_wait_ms'] ) ) ),
					'consent_cookie_sync'  => ! empty( $raw['consent_cookie_sync'] ) ? 1 : 0,
					'consent_cookie_key'   => $this->sanitize_cookie_key( (string) ( $raw['consent_cookie_key'] ?? $d['consent_cookie_key'] ) ),
					'consent_cookie_grant' => $this->sanitize_cookie_value( (string) ( $raw['consent_cookie_grant'] ?? $d['consent_cookie_grant'] ), 'granted' ),
					'consent_cookie_deny'  => $this->sanitize_cookie_value( (string) ( $raw['consent_cookie_deny'] ?? $d['consent_cookie_deny'] ), 'denied' ),
					'custom_head'        => $this->sanitize_snippet( (string) ( $raw['custom_head'] ?? '' ) ),
					'custom_body_open'   => $this->sanitize_snippet( (string) ( $raw['custom_body_open'] ?? '' ) ),
					'custom_footer'      => $this->sanitize_snippet( (string) ( $raw['custom_footer'] ?? '' ) ),
				];
			}

			private function ajax_guard(): void {
				check_ajax_referer( self::NONCE_ACTION, 'nonce' );

				if ( ! current_user_can( 'manage_options' ) ) {
					wp_send_json_error( [ 'message' => 'Forbidden' ], 403 );
				}
			}

			public function ajax_save(): void {
				$this->ajax_guard();

				$raw = isset( $_POST['cfg'] ) && is_array( $_POST['cfg'] )
					? (array) wp_unslash( $_POST['cfg'] )
					: [];

				$cfg = $this->sanitize_cfg( $raw );
				update_option( self::OPT_KEY, $cfg, false );

				wp_send_json_success( [
					'message' => 'Settings saved',
					'cfg'     => $cfg,
				] );
			}

			public function menu(): void {
				add_options_page(
					'Tag Manager',
					'<span style="display:inline-flex;align-items:center;gap:6px;"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M5 4h14a2 2 0 0 1 2 2v3H3V6a2 2 0 0 1 2-2Zm-2 8h18v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-6Z" stroke="currentColor" stroke-width="1.5"/><path d="M7 15h5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>Tag Manager</span>',
					'manage_options',
					self::PAGE_SLUG,
					[ $this, 'render_page' ]
				);
			}

			public function admin_assets( string $hook ): void {
				if ( $hook !== 'settings_page_' . self::PAGE_SLUG ) {
					return;
				}
				wp_enqueue_script( 'jquery' );
			}

			private function is_login_screen(): bool {
				if ( is_admin() ) {
					return false;
				}

				$pagenow = isset( $GLOBALS['pagenow'] ) ? (string) $GLOBALS['pagenow'] : '';

				return $pagenow === 'wp-login.php';
			}

			private function should_output_front(): bool {
				$cfg = $this->cfg();
				if ( empty( $cfg['enabled'] ) ) {
					return false;
				}

				if ( is_feed() || is_robots() || is_trackback() || is_embed() ) {
					return false;
				}

				if ( ! empty( $cfg['disable_on_admin'] ) && ( is_admin() || $this->is_login_screen() ) ) {
					return false;
				}

				if ( is_user_logged_in() && ! empty( $cfg['disable_for_logged'] ) ) {
					return false;
				}

				return (bool) apply_filters( 'sp_tag_manager_should_output', true, $cfg );
			}

			private function gtm_src( string $gtm_id, string $layer ): string {
				$src = 'https://www.googletagmanager.com/gtm.js?id=' . rawurlencode( $gtm_id );
				if ( $layer !== 'dataLayer' ) {
					$src .= '&l=' . rawurlencode( $layer );
				}

				return $src;
			}

			private function gtm_noscript_src( string $gtm_id, string $layer ): string {
				$src = 'https://www.googletagmanager.com/ns.html?id=' . rawurlencode( $gtm_id );
				if ( $layer !== 'dataLayer' ) {
					$src .= '&l=' . rawurlencode( $layer );
				}

				return $src;
			}

			private function should_output_gtm_noscript( array $cfg ): bool {
				if ( empty( $cfg['gtm_enabled'] ) || empty( $cfg['gtm_id'] ) ) {
					return false;
				}

				// In strict mode, avoid noscript iframe before consent state can be updated.
				if ( ! empty( $cfg['consent_mode_enabled'] ) && (string) ( $cfg['consent_default'] ?? 'denied' ) === 'denied' ) {
					return false;
				}

				return true;
			}

			private function render_consent_bootstrap( array $cfg ): void {
				if ( empty( $cfg['consent_mode_enabled'] ) ) {
					return;
				}

				$layer = (string) ( $cfg['gtm_data_layer'] ?? 'dataLayer' );
				$wait  = min( 5000, max( 0, (int) ( $cfg['consent_wait_ms'] ?? 500 ) ) );
				$default_state = (string) ( $cfg['consent_default'] ?? 'denied' ) === 'granted' ? 'granted' : 'denied';

				$denied_values  = [
					'ad_storage'         => 'denied',
					'analytics_storage'  => 'denied',
					'ad_user_data'       => 'denied',
					'ad_personalization' => 'denied',
				];
				$granted_values = [
					'ad_storage'         => 'granted',
					'analytics_storage'  => 'granted',
					'ad_user_data'       => 'granted',
					'ad_personalization' => 'granted',
				];

				$default_values                    = $default_state === 'granted' ? $granted_values : $denied_values;
				$default_values['wait_for_update'] = $wait;

				$cookie_sync  = ! empty( $cfg['consent_cookie_sync'] );
				$cookie_key   = (string) ( $cfg['consent_cookie_key'] ?? 'sp_cookie_consent' );
				$cookie_grant = (string) ( $cfg['consent_cookie_grant'] ?? 'granted' );
				$cookie_deny  = (string) ( $cfg['consent_cookie_deny'] ?? 'denied' );

				printf(
					"<script id=\"sp-consent-mode\">(function(w,d){var layerName=%s;w[layerName]=w[layerName]||[];if(typeof w.gtag!=='function'){w.gtag=function(){w[layerName].push(arguments);};}var denied=%s;var granted=%s;var consentDefault=%s;function applyState(state){if(state==='granted'){w.gtag('consent','update',granted);return 'granted';}if(state==='denied'){w.gtag('consent','update',denied);return 'denied';}return '';}\nfunction readCookie(name){var parts=('; '+d.cookie).split('; '+name+'=');if(parts.length<2){return '';}return decodeURIComponent(parts.pop().split(';').shift()||'');}\nw.gtag('consent','default',consentDefault);w.spTagConsentGrantAll=function(){return applyState('granted');};w.spTagConsentDenyAll=function(){return applyState('denied');};w.spTagConsentUpdate=function(values){if(!values||typeof values!=='object'){return '';}w.gtag('consent','update',values);return 'custom';};d.addEventListener('sp:consent:update',function(ev){var detail=ev&&ev.detail?ev.detail:null;if(!detail){return;}if(typeof detail==='string'){applyState(detail);return;}if(detail.state){applyState(detail.state);return;}if(detail.values&&typeof detail.values==='object'){w.spTagConsentUpdate(detail.values);}});\nif(%s){var cookieValue=readCookie(%s);if(cookieValue===%s){applyState('granted');}else if(cookieValue===%s){applyState('denied');}}})(window,document);</script>\n",
					wp_json_encode( $layer ),
					wp_json_encode( $denied_values ),
					wp_json_encode( $granted_values ),
					wp_json_encode( $default_values ),
					$cookie_sync ? 'true' : 'false',
					wp_json_encode( $cookie_key ),
					wp_json_encode( $cookie_grant ),
					wp_json_encode( $cookie_deny )
				);
			}

			private function render_gtm_loader( array $cfg ): void {
				if ( empty( $cfg['gtm_enabled'] ) ) {
					return;
				}

				$gtm_id = (string) ( $cfg['gtm_id'] ?? '' );
				if ( $gtm_id === '' ) {
					return;
				}

				$layer    = (string) ( $cfg['gtm_data_layer'] ?? 'dataLayer' );
				$strategy = (string) ( $cfg['gtm_strategy'] ?? 'after_interaction' );
				$delay_ms = min( 15000, max( 0, (int) ( $cfg['gtm_delay_ms'] ?? 0 ) ) );
				$src      = $this->gtm_src( $gtm_id, $layer );

				printf( '<link rel="preconnect" href="%s" crossorigin>' . "\n", esc_url( 'https://www.googletagmanager.com' ) );

				if ( $strategy === 'immediate' ) {
					printf(
						"<script id=\"sp-gtm-loader\">(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':Date.now(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!=='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','%s','%s');</script>\n",
						esc_js( $layer ),
						esc_js( $gtm_id )
					);
					return;
				}

				if ( $strategy === 'after_delay' ) {
					printf(
						"<script id=\"sp-gtm-loader\">(function(w,d,s,l,src,delay){var loaded=false;function load(){if(loaded){return;}loaded=true;w[l]=w[l]||[];w[l].push({'gtm.start':Date.now(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s);j.async=true;j.src=src;f.parentNode.insertBefore(j,f);}w.spLoadGtm=load;if(delay<=0){load();return;}setTimeout(load,delay);})(window,document,'script','%s','%s',%d);</script>\n",
						esc_js( $layer ),
						esc_url_raw( $src ),
						$delay_ms
					);
					return;
				}

				printf(
					"<script id=\"sp-gtm-loader\">(function(w,d,s,l,src){var loaded=false;function load(){if(loaded){return;}loaded=true;w[l]=w[l]||[];w[l].push({'gtm.start':Date.now(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s);j.async=true;j.src=src;f.parentNode.insertBefore(j,f);}w.spLoadGtm=load;var events=['pointerdown','touchstart','mousemove','keydown','scroll'];for(var i=0;i<events.length;i++){d.addEventListener(events[i],load,{once:true,passive:true});}w.addEventListener('load',function(){setTimeout(load,10000);},{once:true});})(window,document,'script','%s','%s');</script>\n",
					esc_js( $layer ),
					esc_url_raw( $src )
				);
			}

			public function output_head(): void {
				if ( ! $this->should_output_front() ) {
					return;
				}

				$cfg = $this->cfg();
				$this->render_consent_bootstrap( $cfg );
				$this->render_gtm_loader( $cfg );

				if ( ! empty( $cfg['custom_head'] ) ) {
					echo "\n<!-- SP Tag Manager: custom head -->\n";
					echo (string) $cfg['custom_head'] . "\n";
				}
			}

			public function output_body_open(): void {
				if ( ! $this->should_output_front() ) {
					return;
				}

				$cfg = $this->cfg();

				if ( $this->should_output_gtm_noscript( $cfg ) ) {
					$noscript_src = $this->gtm_noscript_src( (string) $cfg['gtm_id'], (string) $cfg['gtm_data_layer'] );
					echo "\n<!-- Google Tag Manager (noscript) -->\n";
					printf(
						'<noscript><iframe src="%1$s" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>' . "\n",
						esc_url( $noscript_src )
					);
				}

				if ( ! empty( $cfg['custom_body_open'] ) ) {
					echo "\n<!-- SP Tag Manager: custom body_open -->\n";
					echo (string) $cfg['custom_body_open'] . "\n";
				}
			}

			public function output_footer(): void {
				if ( ! $this->should_output_front() ) {
					return;
				}

				$cfg = $this->cfg();
				if ( ! empty( $cfg['custom_footer'] ) ) {
					echo "\n<!-- SP Tag Manager: custom footer -->\n";
					echo (string) $cfg['custom_footer'] . "\n";
				}
			}

			public function render_page(): void {
				$cfg   = $this->cfg();
				$nonce = wp_create_nonce( self::NONCE_ACTION );
				?>
				<div class="sp-tag-admin">
					<div class="sp-tag-admin__header">
						<div class="sp-tag-admin__title-wrap">
							<div class="sp-tag-admin__icon">TM</div>
							<div>
								<h1>Tag Manager</h1>
								<p>Configure GTM and custom tag snippets with predictable loading behavior.</p>
							</div>
						</div>
						<div>
							<button type="button" class="button button-primary" id="sp-tag-save">Save settings</button>
						</div>
					</div>

					<div class="sp-tag-grid">
						<section class="sp-tag-card">
							<h2>Global</h2>
							<label class="sp-tag-row">
								<span>Enable output</span>
								<input type="checkbox" id="sp-tag-enabled" <?php checked( ! empty( $cfg['enabled'] ) ); ?>>
							</label>
							<label class="sp-tag-row">
								<span>Disable for logged-in users</span>
								<input type="checkbox" id="sp-tag-disable-logged" <?php checked( ! empty( $cfg['disable_for_logged'] ) ); ?>>
							</label>
							<label class="sp-tag-row">
								<span>Disable on wp-admin/login</span>
								<input type="checkbox" id="sp-tag-disable-admin" <?php checked( ! empty( $cfg['disable_on_admin'] ) ); ?>>
							</label>
							<p class="sp-tag-help">Recommended: keep output disabled for logged-in users to avoid analytics noise.</p>
						</section>

						<section class="sp-tag-card">
							<h2>Google Tag Manager</h2>
							<label class="sp-tag-row">
								<span>Enable GTM</span>
								<input type="checkbox" id="sp-tag-gtm-enabled" <?php checked( ! empty( $cfg['gtm_enabled'] ) ); ?>>
							</label>
							<label class="sp-tag-field">
								<span>Container ID</span>
								<input type="text" id="sp-tag-gtm-id" value="<?php echo esc_attr( (string) $cfg['gtm_id'] ); ?>" placeholder="GTM-XXXXXXX">
							</label>
							<label class="sp-tag-field">
								<span>dataLayer name</span>
								<input type="text" id="sp-tag-layer" value="<?php echo esc_attr( (string) $cfg['gtm_data_layer'] ); ?>" placeholder="dataLayer">
							</label>
							<label class="sp-tag-field">
								<span>Load strategy</span>
								<select id="sp-tag-strategy">
									<option value="after_interaction" <?php selected( (string) $cfg['gtm_strategy'], 'after_interaction' ); ?>>After interaction (best PSI)</option>
									<option value="after_delay" <?php selected( (string) $cfg['gtm_strategy'], 'after_delay' ); ?>>After delay</option>
									<option value="immediate" <?php selected( (string) $cfg['gtm_strategy'], 'immediate' ); ?>>Immediate</option>
								</select>
							</label>
							<label class="sp-tag-field" id="sp-tag-delay-wrap">
								<span>Delay (ms)</span>
								<input type="number" min="0" max="15000" step="100" id="sp-tag-delay" value="<?php echo esc_attr( (string) (int) $cfg['gtm_delay_ms'] ); ?>">
							</label>
							<p class="sp-tag-help">For Core Web Vitals, use <b>After interaction</b> or <b>After delay</b> in most cases.</p>
						</section>

						<section class="sp-tag-card">
							<h2>Consent Mode v2</h2>
							<label class="sp-tag-row">
								<span>Enable Consent Mode</span>
								<input type="checkbox" id="sp-tag-consent-enabled" <?php checked( ! empty( $cfg['consent_mode_enabled'] ) ); ?>>
							</label>
							<div id="sp-tag-consent-fields">
								<label class="sp-tag-field">
									<span>Default consent state</span>
									<select id="sp-tag-consent-default">
										<option value="denied" <?php selected( (string) $cfg['consent_default'], 'denied' ); ?>>Denied (recommended)</option>
										<option value="granted" <?php selected( (string) $cfg['consent_default'], 'granted' ); ?>>Granted</option>
									</select>
								</label>
								<label class="sp-tag-field">
									<span>wait_for_update (ms)</span>
									<input type="number" min="0" max="5000" step="50" id="sp-tag-consent-wait" value="<?php echo esc_attr( (string) (int) $cfg['consent_wait_ms'] ); ?>">
								</label>
								<label class="sp-tag-row">
									<span>Sync from cookie</span>
									<input type="checkbox" id="sp-tag-consent-cookie-sync" <?php checked( ! empty( $cfg['consent_cookie_sync'] ) ); ?>>
								</label>
								<div id="sp-tag-consent-cookie-fields">
									<label class="sp-tag-field">
										<span>Cookie key</span>
										<input type="text" id="sp-tag-consent-cookie-key" value="<?php echo esc_attr( (string) $cfg['consent_cookie_key'] ); ?>" placeholder="sp_cookie_consent">
									</label>
									<label class="sp-tag-field">
										<span>Granted value</span>
										<input type="text" id="sp-tag-consent-cookie-grant" value="<?php echo esc_attr( (string) $cfg['consent_cookie_grant'] ); ?>" placeholder="granted">
									</label>
									<label class="sp-tag-field">
										<span>Denied value</span>
										<input type="text" id="sp-tag-consent-cookie-deny" value="<?php echo esc_attr( (string) $cfg['consent_cookie_deny'] ); ?>" placeholder="denied">
									</label>
								</div>
								<div class="sp-tag-note">
									<div><b>Integration examples:</b></div>
									<code>window.spTagConsentGrantAll();</code>
									<code>window.spTagConsentDenyAll();</code>
									<code>window.dispatchEvent(new CustomEvent('sp:consent:update',{detail:{state:'granted'}}));</code>
								</div>
							</div>
							<p class="sp-tag-help">When default is denied, the GTM <code>noscript</code> iframe is intentionally skipped.</p>
						</section>

						<section class="sp-tag-card sp-tag-card--full">
							<h2>Custom snippets</h2>
							<p class="sp-tag-help">Paste trusted snippets only. HTML is sanitized on save.</p>
							<label class="sp-tag-field">
								<span>Head snippet (`wp_head`)</span>
								<textarea id="sp-tag-custom-head" rows="7" placeholder="&lt;script&gt;/* ... */&lt;/script&gt;"><?php echo esc_textarea( (string) $cfg['custom_head'] ); ?></textarea>
							</label>
							<label class="sp-tag-field">
								<span>Body open snippet (`wp_body_open`)</span>
								<textarea id="sp-tag-custom-body" rows="7" placeholder="&lt;noscript&gt;...&lt;/noscript&gt;"><?php echo esc_textarea( (string) $cfg['custom_body_open'] ); ?></textarea>
							</label>
							<label class="sp-tag-field">
								<span>Footer snippet (`wp_footer`)</span>
								<textarea id="sp-tag-custom-footer" rows="7" placeholder="&lt;script src=&quot;https://example.com/widget.js&quot; defer&gt;&lt;/script&gt;"><?php echo esc_textarea( (string) $cfg['custom_footer'] ); ?></textarea>
							</label>
							<div class="sp-tag-note">
								<div><b>Where to paste snippets:</b></div>
								<code>Head: verification tags, early init, preconnect/meta tags.</code>
								<code>Body open: GTM noscript fallback or critical body-start snippets.</code>
								<code>Footer: chat widgets, delayed pixels, non-critical trackers.</code>
							</div>
						</section>
					</div>

					<div id="sp-tag-status" class="sp-tag-status" aria-live="polite"></div>
				</div>

				<style>
					.sp-tag-admin { margin: 24px 20px 0 0; font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Inter,Arial,sans-serif; color: #1f2937; }
					.sp-tag-admin__header { display: flex; justify-content: space-between; align-items: center; gap: 16px; padding: 18px 20px; background: #fff; border: 1px solid #e5e7eb; border-radius: 14px; }
					.sp-tag-admin__title-wrap { display: flex; align-items: center; gap: 12px; }
					.sp-tag-admin__title-wrap h1 { margin: 0 0 2px; font-size: 22px; line-height: 1.25; }
					.sp-tag-admin__title-wrap p { margin: 0; color: #6b7280; }
					.sp-tag-admin__icon { width: 40px; height: 40px; border-radius: 10px; background: linear-gradient(135deg, #1d4ed8, #3b82f6); color: #fff; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; }
					.sp-tag-grid { margin-top: 16px; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
					.sp-tag-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 14px; padding: 16px; box-shadow: 0 8px 24px rgba(0,0,0,.03); }
					.sp-tag-card--full { grid-column: 1 / -1; }
					.sp-tag-card h2 { margin: 0 0 12px; font-size: 16px; letter-spacing: .03em; text-transform: uppercase; color: #374151; }
					.sp-tag-row { display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 10px 0; border-top: 1px solid #f3f4f6; }
					.sp-tag-row:first-of-type { border-top: 0; }
					.sp-tag-field { display: grid; gap: 6px; margin-top: 12px; }
					.sp-tag-field > span { font-weight: 600; color: #374151; }
					.sp-tag-field input,
					.sp-tag-field select,
					.sp-tag-field textarea { width: 100%; border: 1px solid #d1d5db; border-radius: 8px; padding: 9px 10px; font-size: 14px; }
					.sp-tag-field textarea { font-family: ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono",monospace; resize: vertical; }
					.sp-tag-help { margin: 10px 0 0; color: #6b7280; }
					.sp-tag-note { margin-top: 12px; padding: 10px; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 10px; display: grid; gap: 6px; }
					.sp-tag-note code { display: block; white-space: pre-wrap; word-break: break-word; padding: 6px 8px; border-radius: 6px; background: #0f172a; color: #e2e8f0; font-size: 12px; }
					.sp-tag-status { margin-top: 14px; min-height: 22px; color: #1d4ed8; font-weight: 600; }
					.sp-tag-status.is-error { color: #b91c1c; }
					@media (max-width: 1100px) {
						.sp-tag-grid { grid-template-columns: 1fr; }
					}
				</style>

				<script>
					(function ($) {
						const nonce = <?php echo wp_json_encode( $nonce ); ?>;
						const ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
						const $status = $('#sp-tag-status');
						const $strategy = $('#sp-tag-strategy');
						const $delayWrap = $('#sp-tag-delay-wrap');
						const $consentEnabled = $('#sp-tag-consent-enabled');
						const $consentFields = $('#sp-tag-consent-fields');
						const $cookieSync = $('#sp-tag-consent-cookie-sync');
						const $cookieFields = $('#sp-tag-consent-cookie-fields');

						function setStatus(text, isError) {
							$status.text(text || '');
							$status.toggleClass('is-error', !!isError);
						}

						function toggleDelay() {
							const v = $strategy.val();
							$delayWrap.toggle(v === 'after_delay');
						}

						function toggleConsentFields() {
							$consentFields.toggle($consentEnabled.is(':checked'));
						}

						function toggleCookieFields() {
							$cookieFields.toggle($cookieSync.is(':checked'));
						}

						function payload() {
							return {
								enabled: $('#sp-tag-enabled').is(':checked') ? 1 : 0,
								disable_for_logged: $('#sp-tag-disable-logged').is(':checked') ? 1 : 0,
								disable_on_admin: $('#sp-tag-disable-admin').is(':checked') ? 1 : 0,
								gtm_enabled: $('#sp-tag-gtm-enabled').is(':checked') ? 1 : 0,
								gtm_id: $('#sp-tag-gtm-id').val() || '',
								gtm_data_layer: $('#sp-tag-layer').val() || '',
								gtm_strategy: $('#sp-tag-strategy').val() || 'after_interaction',
								gtm_delay_ms: parseInt($('#sp-tag-delay').val() || '0', 10) || 0,
								consent_mode_enabled: $('#sp-tag-consent-enabled').is(':checked') ? 1 : 0,
								consent_default: $('#sp-tag-consent-default').val() || 'denied',
								consent_wait_ms: parseInt($('#sp-tag-consent-wait').val() || '0', 10) || 0,
								consent_cookie_sync: $('#sp-tag-consent-cookie-sync').is(':checked') ? 1 : 0,
								consent_cookie_key: $('#sp-tag-consent-cookie-key').val() || '',
								consent_cookie_grant: $('#sp-tag-consent-cookie-grant').val() || '',
								consent_cookie_deny: $('#sp-tag-consent-cookie-deny').val() || '',
								custom_head: $('#sp-tag-custom-head').val() || '',
								custom_body_open: $('#sp-tag-custom-body').val() || '',
								custom_footer: $('#sp-tag-custom-footer').val() || ''
							};
						}

						function save() {
							setStatus('Saving...', false);
							$.post(ajaxUrl, {
								action: 'sp_tag_manager_save',
								nonce: nonce,
								cfg: payload()
							}).done(function (resp) {
								if (!resp || !resp.success) {
									const message = (resp && resp.data && resp.data.message) ? resp.data.message : 'Save failed';
									setStatus(message, true);
									return;
								}
								setStatus('Settings saved', false);
							}).fail(function () {
								setStatus('Network error while saving settings', true);
							});
						}

						$strategy.on('change', toggleDelay);
						$consentEnabled.on('change', toggleConsentFields);
						$cookieSync.on('change', toggleCookieFields);
						$('#sp-tag-save').on('click', save);
						toggleDelay();
						toggleConsentFields();
						toggleCookieFields();
					})(jQuery);
				</script>
				<?php
			}
		}

		SP_Tag_Manager::get();
	}
