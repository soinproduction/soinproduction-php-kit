<?php
	/**
	 * Plugin Name: SP Social Share
	 * Description: Customizable social sharing buttons for any post type
	 * Version: 3.0.0
	 */

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	define( 'SP_SHARE_VER', '3.1.0' );

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
			add_action( 'admin_init', [ $this, 'maybe_migrate_networks' ], 5 );
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
					'key'              => 'link',
					'label'            => 'Link',
					'enabled'          => 1,
					'color'            => '#487DE4',
					'background_color' => '#FFFFFF',
					'icon_color'       => '#487DE4',
					'border_color'     => '#FFFFFF',
					'url'              => '{url}',
					'icon_type'        => 'svg',
					'icon_svg'         => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M7.00106 17.1487C5.5734 17.1487 4.35823 16.6474 3.35556 15.6447C2.3529 14.6422 1.85156 13.4272 1.85156 11.9997C1.85156 10.5722 2.3529 9.35608 3.35556 8.35125C4.35823 7.34625 5.5734 6.84375 7.00106 6.84375H10.3336C10.5741 6.84375 10.7761 6.927 10.9398 7.0935C11.1035 7.26 11.1853 7.46408 11.1853 7.70575C11.1853 7.94742 11.1035 8.14908 10.9398 8.31075C10.7761 8.47225 10.5741 8.553 10.3336 8.553H7.00106C6.04073 8.553 5.22631 8.88675 4.55781 9.55425C3.88915 10.2217 3.55481 11.0347 3.55481 11.9932C3.55481 12.9519 3.88915 13.767 4.55781 14.4385C5.22631 15.1098 6.04073 15.4455 7.00106 15.4455H10.3336C10.5741 15.4455 10.7761 15.5277 10.9398 15.6922C11.1035 15.8567 11.1853 16.0598 11.1853 16.3015C11.1853 16.5432 11.1035 16.7447 10.9398 16.9062C10.7761 17.0679 10.5741 17.1487 10.3336 17.1487H7.00106ZM8.81031 12.785C8.58831 12.785 8.40181 12.7102 8.25081 12.5605C8.09981 12.4107 8.02431 12.2221 8.02431 11.9947C8.02431 11.7676 8.09881 11.5806 8.24781 11.4337C8.39681 11.2869 8.58431 11.2135 8.81031 11.2135H15.1918C15.4138 11.2135 15.6003 11.2883 15.7513 11.438C15.9023 11.5878 15.9778 11.7764 15.9778 12.0037C15.9778 12.2309 15.9033 12.4179 15.7543 12.5647C15.6053 12.7116 15.4178 12.785 15.1918 12.785H8.81031ZM13.6743 17.1487C13.43 17.1487 13.226 17.0665 13.0623 16.902C12.8986 16.7375 12.8168 16.5344 12.8168 16.2927C12.8168 16.0511 12.8986 15.8494 13.0623 15.6877C13.226 15.5262 13.43 15.4455 13.6743 15.4455H17.0011C17.9614 15.4455 18.7758 15.1117 19.4443 14.4442C20.113 13.7767 20.4473 12.9637 20.4473 12.0052C20.4473 11.0466 20.113 10.2315 19.4443 9.56C18.7758 8.88867 17.9614 8.553 17.0011 8.553H13.6743C13.43 8.553 13.226 8.47075 13.0623 8.30625C12.8986 8.14175 12.8168 7.93867 12.8168 7.697C12.8168 7.45533 12.8986 7.25275 13.0623 7.08925C13.226 6.92558 13.43 6.84375 13.6743 6.84375H17.0011C18.4287 6.84375 19.6449 7.34608 20.6496 8.35075C21.6542 9.35525 22.1566 10.5712 22.1566 11.9987C22.1566 13.4262 21.6542 14.6414 20.6496 15.6442C19.6449 16.6472 18.4287 17.1487 17.0011 17.1487H13.6743Z" fill="currentColor"/></svg>',
					'icon_img'         => '',
					'icon_img_id'      => 0
				],
				[
					'key'       => 'facebook',
					'label'     => 'Facebook',
					'enabled'   => 1,
					'color'     => '#1877F2',
					'background_color' => '#FFFFFF',
					'icon_color'       => '#487DE4',
					'border_color'     => '#FFFFFF',
					'url'       => 'https://www.facebook.com/sharer/sharer.php?u={url}',
					'icon_type' => 'svg',
					'icon_svg'  => '<svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M20 10.0611C20 4.50451 15.5229 0 10 0C4.47715 0 0 4.50451 0 10.0611C0 15.0828 3.65684 19.2452 8.4375 20V12.9694H5.89844V10.0611H8.4375V7.84452C8.4375 5.32296 9.9305 3.93012 12.2146 3.93012C13.3088 3.93012 14.4531 4.12663 14.4531 4.12663V6.60261H13.1922C11.95 6.60261 11.5625 7.37822 11.5625 8.1739V10.0611H14.3359L13.8926 12.9694H11.5625V20C16.3432 19.2452 20 15.083 20 10.0611Z" fill="currentColor"/></svg>',
					'icon_img'  => '',
					'icon_img_id' => 0
				],
				[
					'key'       => 'instagram',
					'label'     => 'Instagram',
					'enabled'   => 0,
					'color'     => '#E1306C',
					'url'       => 'https://www.instagram.com/',
					'icon_type' => 'svg',
					'icon_svg'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>',
					'icon_img'  => '',
					'icon_img_id' => 0
				],
				[
					'key'       => 'linkedin',
					'label'     => 'LinkedIn',
					'enabled'   => 1,
					'color'     => '#0A66C2',
					'background_color' => '#FFFFFF',
					'icon_color'       => '#487DE4',
					'border_color'     => '#FFFFFF',
					'url'       => 'https://www.linkedin.com/sharing/share-offsite/?url={url}',
					'icon_type' => 'svg',
					'icon_svg'  => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M4.5 3.24219C3.67157 3.24219 3 3.91376 3 4.74219V19.7422C3 20.5706 3.67157 21.2422 4.5 21.2422H19.5C20.3284 21.2422 21 20.5706 21 19.7422V4.74219C21 3.91376 20.3284 3.24219 19.5 3.24219H4.5ZM8.52076 7.24491C8.52639 8.20116 7.81061 8.79038 6.96123 8.78616C6.16107 8.78194 5.46357 8.14491 5.46779 7.24632C5.47201 6.40116 6.13998 5.72194 7.00764 5.74163C7.88795 5.76132 8.52639 6.40679 8.52076 7.24491ZM12.2797 10.0039H9.75971H9.7583V18.5638H12.4217V18.3641C12.4217 17.9842 12.4214 17.6042 12.4211 17.2241C12.4203 16.2103 12.4194 15.1954 12.4246 14.1819C12.426 13.9358 12.4372 13.6799 12.5005 13.445C12.7381 12.5675 13.5271 12.0008 14.4074 12.1401C14.9727 12.2286 15.3467 12.5563 15.5042 13.0893C15.6013 13.4225 15.6449 13.7811 15.6491 14.1285C15.6605 15.1761 15.6589 16.2237 15.6573 17.2714C15.6567 17.6412 15.6561 18.0112 15.6561 18.381V18.5624H18.328V18.3571C18.328 17.9051 18.3278 17.4532 18.3275 17.0013C18.327 15.8718 18.3264 14.7423 18.3294 13.6124C18.3308 13.1019 18.276 12.5985 18.1508 12.1049C17.9638 11.3708 17.5771 10.7633 16.9485 10.3246C16.5027 10.0124 16.0133 9.81129 15.4663 9.78879C15.404 9.7862 15.3412 9.78281 15.2781 9.7794C14.9984 9.76428 14.7141 9.74892 14.4467 9.80285C13.6817 9.95613 13.0096 10.3063 12.5019 10.9236C12.4429 10.9944 12.3852 11.0663 12.2991 11.1736L12.2797 11.1979V10.0039ZM5.68164 18.5666H8.33242V10.0095H5.68164V18.5666Z" fill="currentColor"/></svg>',
					'icon_img'  => '',
					'icon_img_id' => 0
				],
				[
					'key'       => 'twitter',
					'label'     => 'X (Twitter)',
					'enabled'   => 1,
					'color'     => '#000000',
					'background_color' => '#FFFFFF',
					'icon_color'       => '#487DE4',
					'border_color'     => '#FFFFFF',
					'url'       => 'https://x.com/intent/post?url={url}&text={title}',
					'icon_type' => 'svg',
					'icon_svg'  => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M17.1761 4.24219H19.9362L13.9061 11.0196L21 20.2422H15.4456L11.0951 14.6488L6.11723 20.2422H3.35544L9.80517 12.993L3 4.24219H8.69545L12.6279 9.35481L17.1761 4.24219ZM16.2073 18.6176H17.7368L7.86441 5.78147H6.2232L16.2073 18.6176Z" fill="currentColor"/></svg>',
					'icon_img'  => '',
					'icon_img_id' => 0
				],
				[
					'key'       => 'whatsapp',
					'label'     => 'WhatsApp',
					'enabled'   => 0,
					'color'     => '#25D366',
					'url'       => 'https://wa.me/?text={title}%20{url}',
					'icon_type' => 'svg',
					'icon_svg'  => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z"/></svg>',
					'icon_img'  => '',
					'icon_img_id' => 0
				],
				[
					'key'       => 'telegram',
					'label'     => 'Telegram',
					'enabled'   => 0,
					'color'     => '#2AABEE',
					'url'       => 'https://t.me/share/url?url={url}&text={title}',
					'icon_type' => 'svg',
					'icon_svg'  => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>',
					'icon_img'  => '',
					'icon_img_id' => 0
				],
				[
					'key'       => 'pinterest',
					'label'     => 'Pinterest',
					'enabled'   => 0,
					'color'     => '#E60023',
					'url'       => 'https://pinterest.com/pin/create/button/?url={url}&description={title}',
					'icon_type' => 'svg',
					'icon_svg'  => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.373 0 0 5.373 0 12c0 5.084 3.163 9.426 7.627 11.174-.105-.949-.2-2.405.042-3.441.218-.937 1.407-5.965 1.407-5.965s-.359-.719-.359-1.782c0-1.668.967-2.914 2.171-2.914 1.023 0 1.518.769 1.518 1.69 0 1.029-.655 2.568-.994 3.995-.283 1.194.599 2.169 1.777 2.169 2.133 0 3.772-2.249 3.772-5.495 0-2.873-2.064-4.882-5.012-4.882-3.414 0-5.418 2.561-5.418 5.207 0 1.031.397 2.138.893 2.738a.36.36 0 0 1 .083.345l-.333 1.36c-.053.22-.174.267-.402.161-1.499-.698-2.436-2.889-2.436-4.649 0-3.785 2.75-7.262 7.929-7.262 4.163 0 7.398 2.967 7.398 6.931 0 4.136-2.607 7.464-6.227 7.464-1.216 0-2.359-.632-2.75-1.378l-.748 2.853c-.271 1.043-1.002 2.35-1.492 3.146C9.57 23.812 10.763 24 12 24c6.627 0 12-5.373 12-12S18.627 0 12 0z"/></svg>',
					'icon_img'  => '',
					'icon_img_id' => 0
				],
				[
					'key'       => 'email',
					'label'     => 'Email',
					'enabled'   => 0,
					'color'     => '#6B7280',
					'url'       => 'mailto:?subject={title}&body={url}',
					'icon_type' => 'svg',
					'icon_svg'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>',
					'icon_img'  => '',
					'icon_img_id' => 0
				],
			];
		}

		private function network_presets(): array {
			$presets = [];
			foreach ( $this->default_networks() as $network ) {
				$key = sanitize_key( $network['key'] ?? '' );
				if ( $key === '' ) {
					continue;
				}
				$network['background_color'] = $network['background_color'] ?? '#FFFFFF';
				$network['icon_color']       = $network['icon_color'] ?? ( $network['color'] ?? '#000000' );
				$network['border_color']     = $network['border_color'] ?? '#FFFFFF';
				$presets[ $key ]             = $network;
			}

			$presets['reddit'] = [
				'key'              => 'reddit',
				'label'            => 'Reddit',
				'enabled'          => 0,
				'color'            => '#FF4500',
				'background_color' => '#FFFFFF',
				'icon_color'       => '#FF4500',
				'border_color'     => '#FFFFFF',
				'url'              => 'https://www.reddit.com/submit?url={url}&title={title}',
				'icon_type'        => 'svg',
				'icon_svg'         => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0Zm5.01 8.75c.83 0 1.5.67 1.5 1.5 0 .42-.17.79-.45 1.06.03.18.05.36.05.55 0 2.3-2.72 4.16-6.08 4.16s-6.08-1.86-6.08-4.16c0-.2.02-.39.06-.58a1.49 1.49 0 0 1-.41-1.03c0-.83.67-1.5 1.5-1.5.42 0 .8.17 1.07.45a7.3 7.3 0 0 1 3.52-1.06l.67-3.16c.03-.15.18-.25.33-.22l2.22.47a1.1 1.1 0 1 1-.13.62l-1.92-.41-.58 2.73c1.29.05 2.47.42 3.38 1a1.5 1.5 0 0 1 1.05-.43ZM9.4 11.12a.9.9 0 1 0 0 1.8.9.9 0 0 0 0-1.8Zm5.2 0a.9.9 0 1 0 0 1.8.9.9 0 0 0 0-1.8Zm-5.04 3.18a.35.35 0 0 0-.5.5c.7.7 1.72 1.04 2.94 1.04s2.24-.34 2.94-1.04a.35.35 0 0 0-.5-.5c-.55.55-1.39.84-2.44.84s-1.89-.29-2.44-.84Z"/></svg>',
				'icon_img'         => '',
				'icon_img_id'      => 0
			];

			return $presets;
		}

		private function network_with_defaults( array $network ): array {
			$color = $network['color'] ?? '#000000';
			$network['background_color'] = $network['background_color'] ?? '';
			$network['icon_color']       = $network['icon_color'] ?? $color;
			$network['border_color']     = $network['border_color'] ?? '';
			$network['icon_img_id']      = isset( $network['icon_img_id'] ) ? (int) $network['icon_img_id'] : 0;

			return $network;
		}

		public function maybe_migrate_networks(): void {
			$stored = get_option( 'sp_share_networks', null );
			if ( $stored === null || get_option( 'sp_share_link_preset_added', false ) ) {
				return;
			}

			$networks = is_array( $stored ) ? $stored : [];
			$has_link = false;
			foreach ( $networks as $network ) {
				if ( sanitize_key( $network['key'] ?? '' ) === 'link' ) {
					$has_link = true;
					break;
				}
			}

			if ( ! $has_link ) {
				$presets    = $this->network_presets();
				$networks[] = $presets['link'];
				update_option( 'sp_share_networks', $networks );
			}

			update_option( 'sp_share_link_preset_added', 1 );
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
			$networks = $s !== null ? $s : $this->default_networks();

			return array_values( array_map( [ $this, 'network_with_defaults' ], (array) $networks ) );
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
				'svg'      => [ 'viewBox' => [], 'fill' => [], 'stroke' => [], 'stroke-width' => [], 'stroke-linecap' => [], 'stroke-linejoin' => [], 'xmlns' => [], 'width' => [], 'height' => [] ],
				'path'     => [
					'd'            => [],
					'fill'         => [],
					'stroke'       => [],
					'stroke-width' => [],
					'stroke-linecap' => [],
					'stroke-linejoin' => [],
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
					'key'              => sanitize_key( $n['key'] ?? '' ),
					'label'            => sanitize_text_field( $n['label'] ?? '' ),
					'enabled'          => (int) ( $n['enabled'] ?? 0 ),
					'color'            => $this->sanitize_css_color( $n['color'] ?? '#000000', '#000000' ),
					'background_color' => $this->sanitize_css_color( $n['background_color'] ?? '', '' ),
					'icon_color'       => $this->sanitize_css_color( $n['icon_color'] ?? ( $n['color'] ?? '#000000' ), '#000000' ),
					'border_color'     => $this->sanitize_css_color( $n['border_color'] ?? '', '' ),
					'url'              => sanitize_text_field( $n['url'] ?? '' ),
					'icon_type'        => in_array( $n['icon_type'] ?? '', [ 'svg', 'img' ], true ) ? $n['icon_type'] : 'svg',
					'icon_svg'         => wp_kses( $n['icon_svg'] ?? '', $allowed ),
					'icon_img'         => esc_url_raw( $n['icon_img'] ?? '' ),
					'icon_img_id'      => max( 0, (int) ( $n['icon_img_id'] ?? 0 ) ),
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
            <div class="sp-admin sp-admin-page">

                <header class="sp-admin__header sp-admin-header">
                    <div class="sp-admin__logo sp-admin-header__identity">
                        <span class="sp-admin__logo-icon sp-admin-header__icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" fill="none" viewBox="0 0 24 24">
                              <path stroke="currentColor" stroke-width="1.5" d="M9 11.5a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0Z"/>
                              <path stroke="currentColor" stroke-linecap="round" stroke-width="1.5" d="M14.32 16.802 9 13.29M14.42 6.84 9.1 10.352" opacity="1"/>
                              <path stroke="currentColor" stroke-width="1.5" d="M19 18.5a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0ZM19 5.5a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0Z"/>
                            </svg>
                        </span>
						<div class="sp-admin-header__copy">
							<h1>Social Share</h1>
							<p>Configure social networks, sharing URLs and frontend button styles.</p>
						</div>
                    </div>
                    <div class="sp-admin__actions sp-admin-header__actions">
                        <button type="button" class="sp-btn sp-btn--ghost" id="sp-save-cfg">Save settings</button>
                        <button type="button" class="sp-btn sp-btn--primary" id="sp-save-nets">Save networks</button>
                        <span class="sp-saved" id="sp-saved">✓ Saved</span>
                    </div>
                </header>

                <div class="sp-admin__body">

                    <aside class="sp-sidebar">

                        <div class="sp-panel sp-admin-card">
                            <div class="sp-admin-card__header"><h2>General</h2></div>

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

                        <div class="sp-panel sp-panel--usage sp-admin-card">
                            <div class="sp-admin-card__header"><h2>Usage</h2></div>
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
                            <div class="sp-admin-card__header" style="grid-column: 1/-1;"><h2>Button style</h2></div>

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
                            <div class="sp-nets-header sp-admin-card__header">
                                <h2>Networks</h2>
                                <div class="sp-admin-card__actions">
									<button type="button" class="sp-btn sp-btn--sm" id="sp-add-net">+ Add network</button>
								</div>
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
					'key'              => '',
					'label'            => 'New network',
					'enabled'          => 1,
					'color'            => '#1877F2',
					'background_color' => '',
					'icon_color'       => '#1877F2',
					'border_color'     => '',
					'url'              => '',
					'icon_type'        => 'svg',
					'icon_svg'         => '',
					'icon_img'         => '',
					'icon_img_id'      => 0
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
                    color: var(--sp-admin-text);
                    background: var(--sp-admin-canvas);
                    min-height: 100vh;
                    /*margin: 0 0 -50px 0;*/
                    /*width: calc(100% + 20px);*/
                    display: flex;
                    flex-direction: column;
                }

                .sp-admin__header {
                    background: var(--sp-admin-surface);
                    border-bottom: 1px solid var(--sp-admin-border);
                    padding: 0 24px;
                    height: 56px;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    position: sticky;
                    top: 32px;
                    z-index: 100;
                    box-shadow: var(--sp-admin-shadow)
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
                    color: var(--sp-admin-text)
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
                    background: var(--sp-admin-surface);
                    border-right: 1px solid var(--sp-admin-border);
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
                    background: var(--sp-admin-surface);
                    border: 1px solid var(--sp-admin-border);
                    border-radius: var(--sp-admin-radius);
                    padding: 18px 20px
                }

                .sp-panel__title {
                    font-size: 12px;
                    font-weight: 700;
                    text-transform: uppercase;
                    letter-spacing: .6px;
                    color: var(--sp-admin-muted);
                    margin-bottom: 14px;
                    padding-bottom: 10px;
                    border-bottom: 1px solid var(--sp-admin-border)
                }

                .sp-panel--usage {
                    background: var(--sp-admin-surface-subtle)
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
                    color: var(--sp-admin-text-2);
                    margin-bottom: 5px
                }

                .sp-input,
                .sp-nf input[type=text],
                .sp-nf textarea,
                .sp-nf select {
                    width: 100%;
                    min-height: 40px;
                    padding: 8px 12px;
                    border: 1px solid var(--sp-admin-border-strong);
                    border-radius: var(--sp-admin-radius-sm);
                    font-size: 13px;
                    line-height: 1.4;
                    color: var(--sp-admin-text);
                    background: var(--sp-admin-input-bg);
                    transition: border-color .15s, box-shadow .15s
                }

                .sp-input:focus,
                .sp-nf input:focus,
                .sp-nf textarea:focus,
                .sp-nf select:focus {
                    outline: none;
                    border-color: var(--sp-admin-accent);
                    box-shadow: var(--sp-admin-focus)
                }

                select.sp-input,
                .sp-nf select {
                    appearance: none;
                    padding-right: 38px;
                    background-image: linear-gradient(45deg, transparent 50%, var(--sp-admin-text-2) 50%), linear-gradient(135deg, var(--sp-admin-text-2) 50%, transparent 50%);
                    background-position: calc(100% - 18px) 17px, calc(100% - 12px) 17px;
                    background-size: 6px 6px, 6px 6px;
                    background-repeat: no-repeat
                }

                .sp-hint {
                    font-size: 11px;
                    color: var(--sp-admin-muted);
                    margin-top: 4px;
                    line-height: 1.4
                }

                .sp-hint--top {
                    margin-bottom: 14px;
                    margin-top: -4px
                }

                .sp-code {
                    display: block;
                    background: var(--sp-admin-surface-subtle);
                    padding: 7px 10px;
                    border-radius: var(--sp-admin-radius-sm);
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
                    color: var(--sp-admin-text);
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
                    border: 2px solid var(--sp-admin-border-strong);
                    border-radius: var(--sp-admin-radius-xs);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    flex-shrink: 0;
                    background: var(--sp-admin-input-bg);
                    transition: all .15s
                }

                .sp-check input:checked ~ .sp-check__box {
                    background: var(--sp-admin-accent);
                    border-color: var(--sp-admin-accent)
                }

                .sp-check input:focus-visible ~ .sp-check__box {
                    box-shadow: var(--sp-admin-focus)
                }

                .sp-check input:checked ~ .sp-check__box::after {
                    content: "";
                    width: 4px;
                    height: 7px;
                    border: 2px solid var(--color-on-accent);
                    border-top: none;
                    border-left: none;
                    transform: rotate(45deg) translate(-1px, -1px);
                    display: block
                }

                .sp-check__label {
                    flex: 1
                }

                .sp-check code {
                    background: var(--sp-admin-surface-subtle);
                    padding: 1px 5px;
                    border-radius: var(--sp-admin-radius-xs);
                    font-size: 10px;
                    color: var(--sp-admin-text-2)
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
                    color: var(--sp-admin-accent);
                    min-width: 36px;
                    text-align: right
                }

                .sp-range {
                    width: 100%;
                    height: 4px;
                    -webkit-appearance: none;
                    appearance: none;
                    background: var(--sp-admin-border-strong);
                    border-radius: var(--sp-admin-radius-pill, 999px);
                    outline: none
                }

                .sp-range::-webkit-slider-thumb {
                    -webkit-appearance: none;
                    width: 16px;
                    height: 16px;
                    border-radius: 50%;
                    background: var(--sp-admin-accent);
                    cursor: pointer;
                    box-shadow: var(--sp-admin-shadow)
                }

                .sp-range::-moz-range-thumb {
                    width: 16px;
                    height: 16px;
                    border-radius: 50%;
                    background: var(--sp-admin-accent);
                    cursor: pointer;
                    border: none
                }

                .sp-range:focus-visible {
                    box-shadow: var(--sp-admin-focus)
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
                    background: var(--sp-admin-border-strong);
                    border-radius: var(--sp-admin-radius-pill, 999px);
                    transition: background .25s;
                    position: relative
                }

                .sp-ios-thumb {
                    position: absolute;
                    top: 2px;
                    left: 2px;
                    width: 18px;
                    height: 18px;
                    background: var(--sp-admin-surface);
                    border-radius: 50%;
                    transition: left .25s;
                    box-shadow: var(--sp-admin-shadow)
                }

                .sp-ios-toggle input:checked ~ .sp-ios-track {
                    background: var(--sp-admin-accent)
                }

                .sp-ios-toggle input:focus-visible ~ .sp-ios-track {
                    box-shadow: var(--sp-admin-focus)
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
                    border-radius: var(--sp-admin-radius-sm);
                    font-size: 13px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all .15s;
                    border: 1px solid transparent;
                    line-height: 1
                }

                .sp-btn--primary {
                    background: var(--sp-admin-accent);
                    color: var(--color-on-accent);
                    border-color: var(--sp-admin-accent)
                }

                .sp-btn--primary:hover,
                .sp-btn--primary:focus {
                    background: var(--sp-admin-accent-hover);
                    border-color: var(--sp-admin-accent-hover);
                    color: var(--color-on-accent)
                }

                .sp-btn--ghost {
                    background: var(--sp-admin-surface);
                    color: var(--sp-admin-accent);
                    border-color: var(--sp-admin-accent)
                }

                .sp-btn--ghost:hover,
                .sp-btn--ghost:focus {
                    background: var(--sp-admin-accent-soft)
                }

                .sp-btn--sm {
                    background: var(--sp-admin-surface-subtle);
                    color: var(--sp-admin-text-2);
                    border-color: var(--sp-admin-border-strong);
                    padding: 6px 12px;
                    font-size: 12px
                }

                .sp-btn--sm:hover,
                .sp-btn--sm:focus {
                    border-color: var(--sp-admin-accent);
                    background: var(--sp-admin-accent-soft);
                    color: var(--sp-admin-accent)
                }

                .sp-btn:focus-visible {
                    box-shadow: var(--sp-admin-focus);
                    outline: 0
                }

                .sp-btn:disabled {
                    opacity: .5;
                    cursor: not-allowed
                }

                .sp-saved {
                    font-size: 12px;
                    font-weight: 600;
                    color: var(--sp-admin-success);
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
                    border: 1px solid var(--sp-admin-border);
                    border-radius: var(--sp-admin-radius-sm);
                    background: var(--sp-admin-surface);
                    overflow: hidden;
                    transition: box-shadow .15s
                }

                .sp-net.disabled {
                    opacity: .45
                }

                .sp-net.open {
                    border-color: var(--sp-admin-border-strong);
                    box-shadow: var(--sp-admin-shadow)
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
                    background: var(--sp-admin-surface-subtle)
                }

                .sp-handle {
                    color: var(--sp-admin-muted);
                    cursor: grab;
                    font-size: 16px;
                    line-height: 1;
                    text-align: center
                }

                .sp-net-icon {
                    width: 38px;
                    height: 38px;
                    border-radius: var(--sp-admin-radius-sm);
                    border: 1px solid transparent;
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
                    color: var(--sp-admin-text);
                    line-height: 1.2
                }

                .sp-net-url {
                    font-size: 11px;
                    color: var(--sp-admin-muted);
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
                    border: 1px solid var(--sp-admin-border);
                    flex-shrink: 0
                }

                .sp-net-color-badge {
                    display: flex;
                    align-items: center;
                    gap: 6px;
                    font-size: 11px;
                    color: var(--sp-admin-muted);
                    font-family: monospace;
                    white-space: nowrap
                }

                .sp-net-chevron {
                    color: var(--sp-admin-muted);
                    font-size: 9px;
                    text-align: center;
                    transition: transform .2s;
                    line-height: 1
                }

                .sp-net.open .sp-net-chevron {
                    transform: rotate(180deg)
                }

                .sp-net-del {
                    background: transparent;
                    border: none;
                    color: var(--sp-admin-danger);
                    cursor: pointer;
                    padding: 4px;
                    border-radius: var(--sp-admin-radius-sm);
                    font-size: 16px;
                    line-height: 1;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    transition: background .15s
                }

                .sp-net-del:hover {
                    background: color-mix(in srgb, var(--sp-admin-danger) 9%, var(--sp-admin-surface))
                }

                .sp-net-del:focus-visible {
                    background: color-mix(in srgb, var(--sp-admin-danger) 9%, var(--sp-admin-surface));
                    box-shadow: var(--sp-admin-focus);
                    outline: 0
                }

                /* ── Network body ── */
                .sp-net-body {
                    display: none;
                    padding: 16px;
                    border-top: 1px solid var(--sp-admin-border);
                    background: var(--sp-admin-surface-subtle)
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
                    color: var(--sp-admin-muted);
                    text-transform: uppercase;
                    letter-spacing: .5px
                }

                .sp-nf textarea {
                    height: 68px;
                    font-family: monospace;
                    resize: vertical
                }

                .sp-preset-row {
                    display: block
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
                    width: 40px;
                    height: 40px;
                    padding: 4px;
                    border: 1px solid var(--sp-admin-border-strong);
                    border-radius: var(--sp-admin-radius-sm);
                    background: var(--sp-admin-input-bg);
                    cursor: pointer;
                    flex-shrink: 0
                }

                .sp-color-picker:focus-visible {
                    border-color: var(--sp-admin-accent);
                    box-shadow: var(--sp-admin-focus);
                    outline: 0
                }

                /* ── Icon tabs ── */
                .sp-icon-tabs {
                    display: flex;
                    border-bottom: 1px solid var(--sp-admin-border);
                    margin-bottom: 10px
                }

                .sp-icon-tab {
                    padding: 6px 12px;
                    font-size: 12px;
                    border: none;
                    background: none;
                    color: var(--sp-admin-muted);
                    cursor: pointer;
                    border-bottom: 2px solid transparent;
                    margin-bottom: -1px;
                    font-weight: 500
                }

                .sp-icon-tab.active {
                    color: var(--sp-admin-accent);
                    border-bottom-color: var(--sp-admin-accent)
                }

                .sp-icon-tab:hover,
                .sp-icon-tab:focus-visible {
                    color: var(--sp-admin-accent)
                }

                .sp-icon-tab:focus-visible {
                    box-shadow: var(--sp-admin-focus);
                    outline: 0
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
                    border-radius: var(--sp-admin-radius-sm);
                    border: 1px solid var(--sp-admin-border);
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
                    const presets = <?php echo wp_json_encode( $this->network_presets() ); ?>;
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
                        const accentColor = row.find('.net-color-text').val() || '#000';
                        const iconColor = row.find('.net-icon-color-text').val() || accentColor;
                        const fallbackBg = accentColor.indexOf('#') === 0 ? (accentColor + '20') : ('color-mix(in srgb,' + accentColor + ' 12%,transparent)');
                        const bgColor = row.find('.net-bg-color-text').val() || fallbackBg;
                        const borderColor = row.find('.net-border-color-text').val() || 'transparent';
                        const icon = row.find('.sp-net-icon');

                        icon.css({background: bgColor, color: iconColor, borderColor: borderColor});

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
                                color: iconColor
                            });
                        }

                        // Update badge
                        row.find('.sp-net-color-dot').css('background', iconColor);
                        row.find('.sp-net-color-text-badge').text(iconColor);
                    }

                    $(document).on('input change', '.net-svg,.sp-color-text,.net-img-url', function () {
                        updateIcon($(this).closest('.sp-net'));
                    });

                    $(document).on('input', '.net-label', function () {
                        $(this).closest('.sp-net').find('.sp-net-name').text($(this).val() || 'Untitled');
                    });

                    $(document).on('input', '.net-url', function () {
                        $(this).closest('.sp-net').find('.sp-net-url').text($(this).val());
                    });

                    // Color picker sync
                    $(document).on('input', '.sp-color-picker', function () {
                        $(this).siblings('.sp-color-text').val(this.value).trigger('input');
                    });

                    function colorPickerValue(value, fallback) {
                        return /^#[0-9a-f]{6}$/i.test(value || '') ? value : fallback;
                    }

                    function setColorField(row, selector, value, fallback) {
                        const input = row.find(selector);
                        input.val(value || '');
                        input.siblings('.sp-color-picker').val(colorPickerValue(value || '', fallback));
                    }

                    function applyPreset(row, key) {
                        const preset = presets[key];
                        if (!preset) return;

                        row.find('.net-key').val(preset.key || '');
                        row.find('.net-label').val(preset.label || '').trigger('input');
                        row.find('.net-url').val(preset.url || '').trigger('input');
                        setColorField(row, '.net-bg-color-text', preset.background_color || '', '#ffffff');
                        setColorField(row, '.net-icon-color-text', preset.icon_color || preset.color || '#000000', '#1877f2');
                        setColorField(row, '.net-border-color-text', preset.border_color || '', '#ffffff');
                        row.find('.net-icon-type').val(preset.icon_type || 'svg');
                        row.find('.net-svg').val(preset.icon_svg || '');
                        row.find('.net-img-url').val(preset.icon_img || '');
                        row.find('.net-img-id').val(preset.icon_img_id || 0);
                        row.find('.sp-img-preview').attr('src', preset.icon_img || '').toggleClass('show', !!preset.icon_img);
                        row.find('.sp-icon-tab').removeClass('active');
                        row.find('.sp-icon-pane').removeClass('active');
                        row.find('.sp-icon-tab[data-tab="' + (preset.icon_type || 'svg') + '"]').addClass('active');
                        row.find('.sp-icon-pane[data-pane="' + (preset.icon_type || 'svg') + '"]').addClass('active');
                        updateIcon(row);
                    }

                    // Apply preset immediately on select change
                    $(document).on('change', '.net-preset', function () {
                        applyPreset($(this).closest('.sp-net'), $(this).val());
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
                            row.find('.net-img-id').val(att.id || 0);
                            row.find('.net-img-url').val(att.url).trigger('input');
                            row.find('.sp-img-preview').attr('src', att.url).addClass('show');
                        });
                        frame.open();
                    });

                    $(document).on('click', '.sp-img-clear', function () {
                        const row = $(this).closest('.sp-net');
                        row.find('.net-img-id').val(0);
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
                                color: r.find('.net-icon-color-text').val() || r.find('.net-color-text').val(),
                                background_color: r.find('.net-bg-color-text').val(),
                                icon_color: r.find('.net-icon-color-text').val(),
                                border_color: r.find('.net-border-color-text').val(),
                                url: r.find('.net-url').val(),
                                icon_type: r.find('.net-icon-type').val(),
                                icon_svg: r.find('.net-svg').val(),
                                icon_img: r.find('.net-img-url').val(),
                                icon_img_id: r.find('.net-img-id').val(),
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
			$color        = $n['color'] ?? '#1877F2';
			$bg_color     = $n['background_color'] ?? '';
			$icon_color   = $n['icon_color'] ?? $color;
			$border_color = $n['border_color'] ?? '';
			$key          = sanitize_key( $n['key'] ?? '' );
			$label        = $n['label'] ?? '';
			$url          = $n['url'] ?? '';
			$enabled      = ! empty( $n['enabled'] );
			$itype        = $n['icon_type'] ?? 'svg';
			$isvg         = $n['icon_svg'] ?? '';
			$iimg         = $n['icon_img'] ?? '';
			$iimg_id      = isset( $n['icon_img_id'] ) ? (int) $n['icon_img_id'] : 0;
			if ( $iimg_id <= 0 && $iimg !== '' ) {
				$iimg_id = (int) attachment_url_to_postid( $iimg );
			}
			$presets      = $this->network_presets();

			$icon_html = $itype === 'img' && $iimg
				? '<img src="' . esc_url( $iimg ) . '" style="width:20px;height:20px;object-fit:contain">'
				: $isvg;
			$preview_bg     = $bg_color !== '' ? $bg_color : ( str_starts_with( (string) $color, '#' ) ? $color . '20' : 'color-mix(in srgb,' . $color . ' 12%,transparent)' );
			$preview_border = $border_color !== '' ? $border_color : 'transparent';

			ob_start(); ?>
            <div class="sp-net<?php echo $enabled ? '' : ' disabled'; ?>">

                <div class="sp-net-head">
                    <span class="sp-handle">⠿</span>

                    <div class="sp-net-icon"
                         style="background:<?php echo esc_attr( $preview_bg ); ?>;color:<?php echo esc_attr( $icon_color ); ?>;border-color:<?php echo esc_attr( $preview_border ); ?>;">
						<?php echo $icon_html; ?>
                    </div>

                    <div class="sp-net-info">
                        <div class="sp-net-name"><?php echo esc_html( $label ?: 'Untitled' ); ?></div>
                        <div class="sp-net-url"><?php echo esc_html( $url ); ?></div>
                    </div>

                    <div class="sp-net-color-badge">
                        <span class="sp-net-color-dot" style="background:<?php echo esc_attr( $icon_color ); ?>"></span>
                        <span class="sp-net-color-text-badge"><?php echo esc_html( $icon_color ); ?></span>
                    </div>

                    <label class="sp-ios-toggle" onclick="event.stopPropagation()">
                        <input type="checkbox" class="sp-net-enabled" <?php checked( $enabled ); ?>>
                        <span class="sp-ios-track"><span class="sp-ios-thumb"></span></span>
                    </label>

                    <span class="sp-net-chevron">▼</span>
                </div>

                <div class="sp-net-body">
                    <div class="sp-net-grid">
                        <div class="sp-nf full">
                            <label>Preset</label>
                            <div class="sp-preset-row">
                                <select class="sp-input net-preset">
                                    <option value="">Select preset</option>
									<?php foreach ( $presets as $preset_key => $preset ) : ?>
                                        <option value="<?php echo esc_attr( $preset_key ); ?>" <?php selected( $preset_key, $key ); ?>><?php echo esc_html( $preset['label'] ?? $preset_key ); ?></option>
									<?php endforeach; ?>
                                </select>
                            </div>
                        </div>
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
                        <div class="sp-nf">
                            <label>Button background</label>
                            <div class="sp-color-row">
                                <input type="text" class="sp-color-text net-bg-color-text" value="<?php echo esc_attr( $bg_color ); ?>"
                                       placeholder="#FFFFFF">
                                <input type="color" class="sp-color-picker" value="<?php echo esc_attr( $this->color_picker_value( $bg_color, '#ffffff' ) ); ?>">
                            </div>
                        </div>
                        <div class="sp-nf">
                            <label>Icon color</label>
                            <div class="sp-color-row">
                                <input type="text" class="sp-color-text net-color-text net-icon-color-text" value="<?php echo esc_attr( $icon_color ); ?>"
                                       placeholder="#1877F2">
                                <input type="color" class="sp-color-picker" value="<?php echo esc_attr( $this->color_picker_value( $icon_color, '#1877f2' ) ); ?>">
                            </div>
                        </div>
                        <div class="sp-nf full">
                            <label>Border color</label>
                            <div class="sp-color-row">
                                <input type="text" class="sp-color-text net-border-color-text" value="<?php echo esc_attr( $border_color ); ?>"
                                       placeholder="#FFFFFF">
                                <input type="color" class="sp-color-picker" value="<?php echo esc_attr( $this->color_picker_value( $border_color, '#ffffff' ) ); ?>">
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
                                <input type="hidden" class="net-img-id" value="<?php echo esc_attr( (string) $iimg_id ); ?>">
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
                    color: var(--sp-admin-text)
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
                    background: var(--sp-admin-border-strong);
                    border-radius: var(--sp-admin-radius-pill, 999px);
                    transition: background .25s;
                    position: relative
                }

                .sp-ios-thumb {
                    position: absolute;
                    top: 2px;
                    left: 2px;
                    width: 18px;
                    height: 18px;
                    background: var(--sp-admin-surface);
                    border-radius: 50%;
                    transition: left .25s;
                    box-shadow: var(--sp-admin-shadow)
                }

                .sp-ios-toggle input:checked ~ .sp-ios-track {
                    background: var(--sp-admin-accent)
                }

                .sp-ios-toggle input:focus-visible ~ .sp-ios-track {
                    box-shadow: var(--sp-admin-focus)
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

		private function sanitize_css_color( $color, string $fallback = '' ): string {
			$color = trim( sanitize_text_field( (string) $color ) );
			if ( $color === '' ) {
				return $fallback;
			}

			$hex = sanitize_hex_color( $color );
			if ( is_string( $hex ) && $hex !== '' ) {
				return $hex;
			}

			if ( in_array( strtolower( $color ), [ 'transparent', 'currentcolor' ], true ) ) {
				return strtolower( $color ) === 'currentcolor' ? 'currentColor' : 'transparent';
			}

			if ( preg_match( '/^(rgba?|hsla?)\([0-9a-z.%\s,\/+-]+\)$/i', $color ) ) {
				return $color;
			}

			return $fallback;
		}

		private function color_picker_value( $color, string $fallback ): string {
			$hex = sanitize_hex_color( trim( (string) $color ) );

			return is_string( $hex ) && $hex !== '' ? $hex : $fallback;
		}

		private function rem( $value ) {
			$rem = ( (int) $value ) / 10;
			$out = rtrim( rtrim( number_format( $rem, 2, '.', '' ), '0' ), '.' );

			return ( $out === '' ? '0' : $out ) . 'rem';
		}

		private function frontend_style_handle(): string {
			return 'sp-share-frontend';
		}

		private function frontend_styles_url(): string {
			return trailingslashit( THEME_URI ) . 'core/plugins/sp-share/assets/sp-share-frontend.min.css';
		}

		private function frontend_script_handle(): string {
			return 'sp-share-frontend-js';
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
					'.sp-share__btn{width:' . $btn_size . ';height:' . $btn_size . ';border-radius:' . $border_radius . ';border-style:solid;border-width:' . $border_width . ';color:var(--sp-share-icon-color,var(--sp-share-color,currentColor));background:var(--sp-share-bg-color,color-mix(in srgb,var(--sp-share-color,currentColor) ' . $bg_opacity . '%,transparent));border-color:var(--sp-share-border-color,color-mix(in srgb,var(--sp-share-color,currentColor) ' . $border_opacity . '%,transparent));}',
					'.sp-share__btn:hover{background:var(--sp-share-bg-color,color-mix(in srgb,var(--sp-share-color,currentColor) ' . $bg_hover . '%,transparent));border-color:var(--sp-share-border-color,color-mix(in srgb,var(--sp-share-color,currentColor) ' . $border_hover . '%,transparent));}',
					'.sp-share__btn svg,.sp-share__btn img{width:' . $icon_size . ';height:' . $icon_size . ';}',
					'@media (max-width: 767.98px){.sp-share__btn{width:' . $btn_size_min . ';height:' . $btn_size_min . ';}.sp-share__btn svg,.sp-share__btn img{width:' . $icon_size_min . ';height:' . $icon_size_min . ';}}',
				]
			);
		}

		private function has_copy_link_network( array $networks ): bool {
			foreach ( $networks as $network ) {
				if ( sanitize_key( $network['key'] ?? '' ) === 'link' ) {
					return true;
				}
			}

			return false;
		}

		private function frontend_copy_js(): string {
			return "(function(){document.addEventListener('click',function(event){var button=event.target.closest('[data-sp-share-copy]');if(!button)return;var text=button.getAttribute('data-sp-share-copy')||button.href;if(!text)return;event.preventDefault();function done(){button.classList.add('is-copied');button.setAttribute('data-copied','1');window.setTimeout(function(){button.classList.remove('is-copied');button.removeAttribute('data-copied');},1400)}function fallback(){var input=document.createElement('textarea');input.value=text;input.setAttribute('readonly','');input.style.position='fixed';input.style.top='-1000px';document.body.appendChild(input);input.select();try{document.execCommand('copy');done()}catch(e){window.location.href=button.href}document.body.removeChild(input)}if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(text).then(done).catch(fallback)}else{fallback()}});})();";
		}

		public function enqueue_frontend_assets(): void {
			$cfg = $this->cfg();
			if ( is_admin() ) {
				return;
			}

			$post_id = (int) get_queried_object_id();
			if ( ! $this->post_can_render_share( $post_id ) ) {
				return;
			}
			$networks = $this->enabled_networks();
			if ( ! $networks ) {
				return;
			}

			if ( ! empty( $cfg['output_styles'] ) ) {
				$handle       = $this->frontend_style_handle();
				$css_file     = __DIR__ . '/assets/sp-share-frontend.min.css';
				$css_file_url = $this->frontend_styles_url();
				$version      = file_exists( $css_file ) ? (string) filemtime( $css_file ) : SP_SHARE_VER;

				wp_enqueue_style( $handle, $css_file_url, [], $version, 'all' );
				wp_add_inline_style( $handle, $this->frontend_dynamic_css( $cfg ) );
			}

			if ( $this->has_copy_link_network( $networks ) ) {
				$script_handle = $this->frontend_script_handle();
				wp_register_script( $script_handle, false, [], SP_SHARE_VER, true );
				wp_enqueue_script( $script_handle );
				wp_add_inline_script( $script_handle, $this->frontend_copy_js() );
			}
		}

		private function normalize_color( $color ): string {
			return $this->sanitize_css_color( $color, '#000000' );
		}

		private function render_network_style( array $network ): string {
			$accent = $this->sanitize_css_color( $network['color'] ?? '#000000', '#000000' );
			$icon   = $this->sanitize_css_color( $network['icon_color'] ?? $accent, $accent );
			$bg     = $this->sanitize_css_color( $network['background_color'] ?? '', '' );
			$border = $this->sanitize_css_color( $network['border_color'] ?? '', '' );
			$styles = [
				'--sp-share-color:' . $accent,
				'--sp-share-icon-color:' . $icon,
			];

			if ( $bg !== '' ) {
				$styles[] = '--sp-share-bg-color:' . $bg;
			}
			if ( $border !== '' ) {
				$styles[] = '--sp-share-border-color:' . $border;
			}

			return implode( ';', $styles ) . ';';
		}

		private function render_network_icon( array $network, string $label = '' ): string {
			$icon_type = (string) ( $network['icon_type'] ?? 'svg' );
			$icon_img  = trim( (string) ( $network['icon_img'] ?? '' ) );
			$icon_id   = max( 0, (int) ( $network['icon_img_id'] ?? 0 ) );
			$icon_svg  = (string) ( $network['icon_svg'] ?? '' );

			if ( $icon_type === 'img' && $icon_img !== '' ) {
				if ( function_exists( 'display_image' ) ) {
					if ( $icon_id <= 0 ) {
						$icon_id = (int) attachment_url_to_postid( $icon_img );
					}

					if ( $icon_id > 0 ) {
						$image = [
							'ID'       => $icon_id,
							'url'      => $icon_img,
							'alt'      => $label,
							'svg_mode' => 'img',
							'img_attrs' => [
								'class' => 'sp-share__icon',
							],
						];

						ob_start();

						display_image( $image, 24, 24, '', 'lazy' );
						$html = trim( (string) ob_get_clean() );
						if ( $html !== '' ) {
							return $html;
						}
					}
				}

				return '<img class="sp-share__icon" src="' . esc_url( $icon_img ) . '" alt="' . esc_attr( $label ) . '">';
			}

			return $icon_svg;
		}

		private function share_url_template( array $network ): string {
			$key      = sanitize_key( $network['key'] ?? '' );
			$template = trim( (string) ( $network['url'] ?? '' ) );

			if ( $key === 'linkedin' && str_contains( $template, 'linkedin.com/shareArticle' ) ) {
				return 'https://www.linkedin.com/sharing/share-offsite/?url={url}';
			}

			if ( in_array( $key, [ 'twitter', 'x' ], true ) && str_contains( $template, 'twitter.com/intent/tweet' ) ) {
				return 'https://x.com/intent/post?url={url}&text={title}';
			}

			return $template;
		}

		private function build_share_href( array $network, string $raw_url, string $raw_title ): string {
			$template = $this->share_url_template( $network );
			if ( $template === '' ) {
				return '';
			}

			return str_replace(
				[ '{url}', '{title}', '{url_raw}', '{title_raw}' ],
				[
					rawurlencode( $raw_url ),
					rawurlencode( $raw_title ),
					$raw_url,
					$raw_title,
				],
				$template
			);
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
			$raw_url   = (string) get_permalink( $post_id );
			$raw_title = (string) get_the_title( $post_id );
			$lbl   = (string) ( $cfg['label'] ?? '' );

			ob_start(); ?>
            <div class="sp-share" <?php if ( $lbl !== '' ) : ?>data-title="<?php echo esc_attr( $lbl ); ?>"<?php endif; ?>>
                <ul class="sp-share__btns">
					<?php foreach ( $nets as $n ) :
						$key       = sanitize_key( $n['key'] ?? '' );
						$href      = $this->build_share_href( (array) $n, $raw_url, $raw_title );
						$is_copy   = $key === 'link';
						$href      = $is_copy ? $raw_url : $href;
						$label     = (string) ( $n['label'] ?? '' );
						if ( $href === '' ) {
							continue;
						}
						$mail      = str_starts_with( $href, 'mailto:' );
						$target    = ( $mail || $is_copy ) ? '' : 'target="_blank" rel="noopener noreferrer"';
						$icon      = $this->render_network_icon( (array) $n, $label );
						$style     = $this->render_network_style( (array) $n );
						$copy_attr = $is_copy ? 'data-sp-share-copy="' . esc_attr( $raw_url ) . '"' : '';
						?>
                       <li class="sp-share__btns-item">
                           <a href="<?php echo esc_url( $href ); ?>" class="sp-share__btn"
                              style="<?php echo esc_attr( $style ); ?>"
								<?php echo $target; ?>
								<?php echo $copy_attr; ?>
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
