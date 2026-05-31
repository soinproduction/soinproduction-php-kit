<?php

	if ( ! function_exists( 'wp_debug_panel_render' ) ) {

		function wp_dbg_human_bytes( $bytes ) {
			$bytes = (float) $bytes;
			if ( $bytes <= 0 ) return '0 KB';
			$kb = $bytes / 1024;
			if ( $kb < 1024 ) return round( $kb, 1 ) . ' KB';
			return round( $kb / 1024, 2 ) . ' MB';
		}

		function wp_dbg_normalize_url( $src ) {
			$src = (string) $src;
			if ( $src === '' ) return '';

			if ( strpos( $src, '//' ) === 0 ) {
				$scheme = is_ssl() ? 'https:' : 'http:';
				return $scheme . $src;
			}
			if ( strpos( $src, 'http://' ) === 0 || strpos( $src, 'https://' ) === 0 ) {
				return $src;
			}
			if ( strpos( $src, '/' ) === 0 ) {
				return home_url( $src );
			}
			return home_url( '/' . ltrim( $src, '/' ) );
		}

		function wp_dbg_url_to_path( $url ) {
			$url = wp_dbg_normalize_url( $url );
			if ( $url === '' ) return '';

			$home = home_url();
			$u    = wp_parse_url( $url );
			$h    = wp_parse_url( $home );

			if ( ! is_array( $u ) || ! is_array( $h ) ) return '';

			$url_host  = isset( $u['host'] ) ? (string) $u['host'] : '';
			$home_host = isset( $h['host'] ) ? (string) $h['host'] : '';

			if ( $url_host && $home_host && strtolower( $url_host ) !== strtolower( $home_host ) ) {
				return '';
			}

			$url_path  = isset( $u['path'] ) ? (string) $u['path'] : '';
			$home_path = isset( $h['path'] ) ? rtrim( (string) $h['path'], '/' ) : '';

			if ( $home_path && strpos( $url_path, $home_path ) === 0 ) {
				$url_path = substr( $url_path, strlen( $home_path ) );
			}

			$url_path = '/' . ltrim( $url_path, '/' );
			$abs      = rtrim( ABSPATH, '/\\' ) . $url_path;

			if ( function_exists( 'wp_normalize_path' ) ) {
				$abs = wp_normalize_path( $abs );
			}

			$abs = urldecode( $abs );

			if ( file_exists( $abs ) && is_file( $abs ) ) {
				return $abs;
			}

			return '';
		}

		function wp_dbg_extract_images_from_html( $html ) {
			$items = [];
			$html  = (string) $html;
			if ( $html === '' ) return $items;

			if ( ! preg_match_all( '#<img\b[^>]*>#i', $html, $m ) ) {
				return $items;
			}

			foreach ( (array) $m[0] as $img_tag ) {
				$src = '';
				$w   = '';
				$h   = '';
				$alt = '';

				if ( preg_match( '#\bsrc\s*=\s*([\'"])(.*?)\1#i', $img_tag, $mm ) ) {
					$src = (string) $mm[2];
				} elseif ( preg_match( '#\bsrc\s*=\s*([^\s>]+)#i', $img_tag, $mm2 ) ) {
					$src = trim( (string) $mm2[1], '"\'' );
				}

				if ( preg_match( '#\bwidth\s*=\s*([\'"])(.*?)\1#i', $img_tag, $mw ) ) {
					$w = (string) $mw[2];
				}
				if ( preg_match( '#\bheight\s*=\s*([\'"])(.*?)\1#i', $img_tag, $mh ) ) {
					$h = (string) $mh[2];
				}
				if ( preg_match( '#\balt\s*=\s*([\'"])(.*?)\1#i', $img_tag, $ma ) ) {
					$alt = (string) $ma[2];
				}

				if ( $src !== '' ) {
					$items[] = [
						'src'    => $src,
						'width'  => $w,
						'height' => $h,
						'alt'    => $alt,
					];
				}
			}

			return $items;
		}

		function wp_dbg_safe_strlen_bytes( $str ) {
			$str = (string) $str;
			return strlen( $str ); // bytes
		}

		function wp_dbg_inline_data_to_string( $value ): string {
			if ( is_array( $value ) ) {
				$parts = array_map(
					static function ( $item ): string {
						if ( is_scalar( $item ) || $item === null ) {
							return (string) $item;
						}

						return '';
					},
					$value
				);

				return implode( "\n", array_filter( $parts, static fn ( string $item ): bool => $item !== '' ) );
			}

			if ( is_scalar( $value ) || $value === null ) {
				return (string) $value;
			}

			return '';
		}

		function wp_dbg_host( $url ) {
			$url = wp_dbg_normalize_url( $url );
			if ( $url === '' ) return '';
			$u = wp_parse_url( $url );
			if ( ! is_array( $u ) ) return '';
			return isset( $u['host'] ) ? (string) $u['host'] : '';
		}

		function wp_dbg_is_external( $url, $home_host ) {
			$host = wp_dbg_host( $url );
			if ( $host === '' || $home_host === '' ) return false;
			return strtolower( $host ) !== strtolower( $home_host );
		}

		function wp_dbg_css_extract_font_urls( $css ) {
			$css = (string) $css;
			if ( $css === '' ) return [];

			// url(...) with woff2/woff/ttf/otf/eot
			$fonts = [];
			if ( preg_match_all( '#url\(\s*([\'"]?)([^\'")]+)\1\s*\)#i', $css, $m ) ) {
				foreach ( (array) $m[2] as $u ) {
					$u = trim( (string) $u );
					if ( $u === '' ) continue;

					// strip query/hash
					$u_clean = preg_replace( '~[?#].*$~', '', $u );

					if ( preg_match( '#\.(woff2|woff|ttf|otf|eot)$#i', $u_clean ) ) {
						$fonts[] = $u;
					}
				}
			}

			$fonts = array_values( array_unique( $fonts ) );
			return $fonts;
		}

		function wp_dbg_css_extract_bg_images( $css ) {
			$css = (string) $css;
			if ( $css === '' ) return [];

			$imgs = [];
			if ( preg_match_all( '#url\(\s*([\'"]?)([^\'")]+)\1\s*\)#i', $css, $m ) ) {
				foreach ( (array) $m[2] as $u ) {
					$u = trim( (string) $u );
					if ( $u === '' ) continue;

					$u_clean = preg_replace( '~[?#].*$~', '', $u );
					if ( preg_match( '#\.(png|jpe?g|gif|webp|avif|svg)$#i', $u_clean ) ) {
						$imgs[] = $u;
					}
				}
			}

			$imgs = array_values( array_unique( $imgs ) );
			return $imgs;
		}

		function wp_dbg_is_closed_cookie(): bool {
			return isset( $_COOKIE['sp_dbg_closed'] ) && (string) $_COOKIE['sp_dbg_closed'] === '1';
		}

		function wp_dbg_render_closed_launcher( string $template_name ): void {
			$template_name = trim( $template_name ) !== '' ? $template_name : 'debug panel';
			?>
            <style>
                #wp-dbg-launcher {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    position: fixed;
                    z-index: 2147483646;
                    left: 20px;
                    bottom: 20px;
                    height: 40px;
                    padding: 0 14px;
                    border-radius: 10px;
                    border: 1px solid rgba(124,58,237,0.34);
                    background: rgba(15,18,32,0.96);
                    color: #ddd6fe;
                    font-size: 12px;
                    font-weight: 900;
                    letter-spacing: 0.4px;
                    box-shadow: 0 10px 32px rgba(0,0,0,0.55);
                    cursor: pointer;
                }
                #wp-dbg-launcher:hover {
                    background: rgba(23,27,46,0.98);
                    border-color: rgba(124,58,237,0.5);
                }
                @media (max-width: 520px) {
                    #wp-dbg-launcher {
                        left: 12px;
                        bottom: 12px;
                        max-width: calc(100vw - 24px);
                        white-space: nowrap;
                        overflow: hidden;
                        text-overflow: ellipsis;
                    }
                }
            </style>
            <button type="button" id="wp-dbg-launcher" aria-label="Open debug panel"><?php echo esc_html( $template_name ); ?></button>
            <script>
                (function () {
                    var btn = document.getElementById('wp-dbg-launcher');
                    if (!btn) return;

                    function setClosedCookie(closed) {
                        var maxAge = 60 * 60 * 24 * 365;
                        document.cookie = 'sp_dbg_closed=' + (closed ? '1' : '0') + '; path=/; max-age=' + maxAge + '; samesite=lax';
                    }

                    function openPanel() {
                        setClosedCookie(false);
                        var url = new URL(window.location.href);
                        url.searchParams.set('sp_dbg_open', '1');
                        window.location.href = url.toString();
                    }

                    btn.addEventListener('click', function (e) {
                        e.preventDefault();
                        openPanel();
                    });

                    document.addEventListener('keydown', function (e) {
                        if (e.altKey && (e.key === 'd' || e.key === 'D')) {
                            e.preventDefault();
                            openPanel();
                        }
                    });
                })();
            </script>
			<?php
		}

		function wp_debug_panel_render() {

			if ( ! defined( 'DEV_MODE' ) || ! DEV_MODE ) {
				return;
			}

			if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
				return;
			}

			$debug_panel_force_open = isset( $_GET['sp_dbg_open'] ) && (string) $_GET['sp_dbg_open'] === '1';

			global $template, $wp_query, $wp_scripts, $wp_styles, $wpdb;
			$template_name = basename( (string) $template );
			if ( $template_name === '' ) {
				$template_name = 'unknown-template.php';
			}

			if ( ! $debug_panel_force_open && wp_dbg_is_closed_cookie() ) {
				wp_dbg_render_closed_launcher( $template_name );
				return;
			}

			$debug_collect_started_at = microtime( true );
			$debug_collect_start_mem  = memory_get_usage();

			$current_user     = wp_get_current_user();
			$object           = get_queried_object();
			$post             = get_post();

			$scripts_enqueued = ( isset( $wp_scripts->queue ) && is_array( $wp_scripts->queue ) ) ? $wp_scripts->queue : [];
			$styles_enqueued  = ( isset( $wp_styles->queue ) && is_array( $wp_styles->queue ) ) ? $wp_styles->queue : [];

			$num_queries    = function_exists( 'get_num_queries' ) ? (int) get_num_queries() : 0;
			$query_time     = function_exists( 'timer_stop' ) ? (string) timer_stop( 0, 3 ) : '—';
			$memory_peak    = round( memory_get_peak_usage() / 1024 / 1024, 2 );
			$memory_current = round( memory_get_usage() / 1024 / 1024, 2 );
			$memory_limit   = (string) ini_get( 'memory_limit' );
			$active_plugins = (array) get_option( 'active_plugins', [] );

			$user_login = ( $current_user && ! empty( $current_user->user_login ) ) ? $current_user->user_login : 'guest';
			$user_roles = ( $current_user && ! empty( $current_user->roles ) ) ? implode( ', ', (array) $current_user->roles ) : 'no role';

			$hooks_count = isset( $GLOBALS['wp_filter'] ) && is_array( $GLOBALS['wp_filter'] ) ? count( $GLOBALS['wp_filter'] ) : 0;
			$image_sizes = function_exists( 'get_intermediate_image_sizes' ) ? (array) get_intermediate_image_sizes() : [];

			$ob_level = function_exists( 'ob_get_level' ) ? (int) ob_get_level() : 0;

			$theme = function_exists( 'wp_get_theme' ) ? wp_get_theme() : null;

			$home      = function_exists( 'home_url' ) ? home_url() : '';
			$home_host = '';
			$home_u    = wp_parse_url( $home );
			if ( is_array( $home_u ) && isset( $home_u['host'] ) ) {
				$home_host = (string) $home_u['host'];
			}

			// Conditionals
			$conditionals = [];
			$cond_map     = [
				'is_front_page',
				'is_home',
				'is_single',
				'is_page',
				'is_archive',
				'is_category',
				'is_tag',
				'is_tax',
				'is_search',
				'is_404',
				'is_singular',
				'is_post_type_archive',
				'is_attachment',
				'is_author',
			];
			foreach ( $cond_map as $fn ) {
				if ( function_exists( $fn ) && call_user_func( $fn ) ) {
					$conditionals[] = $fn . '()';
				}
			}

			// Slow queries (requires SAVEQUERIES)
			$slow_queries = [];
			if ( defined( 'SAVEQUERIES' ) && SAVEQUERIES && isset( $wpdb->queries ) && is_array( $wpdb->queries ) ) {
				foreach ( $wpdb->queries as $q ) {
					if ( isset( $q[1] ) && (float) $q[1] > 0.05 ) {
						$slow_queries[] = [
							'sql'    => isset( $q[0] ) ? trim( (string) $q[0] ) : '',
							'time'   => round( (float) $q[1] * 1000, 2 ) . 'ms',
							'caller' => isset( $q[2] ) ? (string) $q[2] : '',
						];
					}
				}
			}

			// Meta keys (public/private)
			$meta_keys_public  = [];
			$meta_keys_private = [];
			if ( $post ) {
				$meta = get_post_meta( $post->ID );
				foreach ( array_keys( (array) $meta ) as $k ) {
					if ( strpos( (string) $k, '_' ) === 0 ) {
						$meta_keys_private[] = $k;
					} else {
						$meta_keys_public[] = $k;
					}
				}
			}

			// ACF fields
			$acf_fields = [];
			if ( function_exists( 'get_fields' ) && $post ) {
				$fields = get_fields( $post->ID );
				if ( is_array( $fields ) ) {
					$acf_fields = $fields;
				}
			}

			// Rewrite rules & cron
			$rewrite_rules = get_option( 'rewrite_rules' );
			$rules_count   = is_array( $rewrite_rules ) ? count( $rewrite_rules ) : 0;

			$crons      = function_exists( '_get_cron_array' ) ? (array) _get_cron_array() : [];
			$cron_count = 0;
			foreach ( $crons as $jobs ) {
				$cron_count += is_array( $jobs ) ? count( $jobs ) : 0;
			}

			$permalink = function_exists( 'get_permalink' ) ? get_permalink() : '';

			// =========================
			// Assets (JS/CSS) + Inline
			// =========================

			$assets_js  = [];
			$assets_css = [];

			$total_js_bytes  = 0;
			$total_css_bytes = 0;

			$total_ext_js  = 0;
			$total_ext_css = 0;

			$head_js_count   = 0;
			$nover_js        = 0;
			$nover_css       = 0;

			// Inline bytes totals + per-handle
			$total_inline_js_bytes  = 0;
			$total_inline_css_bytes = 0;

			$inline_js_items  = [];
			$inline_css_items = [];

			// Script strategy (async/defer) if available via WP "strategy"
			$head_js_without_strategy = 0;

			// Third-party domains
			$third_party_domains = []; // host => count
			$third_party_assets  = []; // list items

			// Duplicate src detection
			$src_seen = []; // src => [type=>handles]
			$dup_src  = []; // src => meta

			foreach ( (array) $scripts_enqueued as $h ) {

				$reg = isset( $wp_scripts->registered[ $h ] ) ? $wp_scripts->registered[ $h ] : null;

				$src = '';
				$ver = '';
				$deps = [];

				if ( $reg ) {
					$src  = isset( $reg->src ) ? (string) $reg->src : '';
					$ver  = isset( $reg->ver ) ? (string) $reg->ver : '';
					$deps = isset( $reg->deps ) && is_array( $reg->deps ) ? $reg->deps : [];
				}

				$src_abs     = wp_dbg_normalize_url( $src );
				$is_external = $src_abs ? wp_dbg_is_external( $src_abs, $home_host ) : false;

				// group: 1 => footer
				$in_footer = false;
				if ( isset( $wp_scripts->groups[ $h ] ) ) {
					$in_footer = ( (int) $wp_scripts->groups[ $h ] === 1 );
				}

				if ( ! $in_footer ) $head_js_count++;

				// strategy: 'defer'/'async' if set by WP Script API
				$strategy = '';
				if ( is_object( $wp_scripts ) && method_exists( $wp_scripts, 'get_data' ) ) {
					$strategy = (string) $wp_scripts->get_data( $h, 'strategy' ); // WP 6.3+ supports this
				}
				if ( ! $in_footer && $strategy === '' ) {
					$head_js_without_strategy++;
				}

				if ( $ver === '' ) $nover_js++;

				$bytes = 0;
				$path  = '';
				if ( ! $is_external && $src_abs ) {
					$path = wp_dbg_url_to_path( $src_abs );
					if ( $path ) $bytes = (int) @filesize( $path );
				}

				$total_js_bytes += $bytes;

				if ( $is_external ) {
					$total_ext_js++;
					$host = wp_dbg_host( $src_abs );
					if ( $host ) {
						$third_party_domains[ $host ] = isset( $third_party_domains[ $host ] ) ? $third_party_domains[ $host ] + 1 : 1;
						$third_party_assets[] = [ 'type' => 'js', 'handle' => $h, 'host' => $host, 'src' => $src_abs ];
					}
				}

				// Inline script sizes: before/after
				$inline_before = '';
				$inline_after  = '';
				if ( is_object( $wp_scripts ) && method_exists( $wp_scripts, 'get_data' ) ) {
						$inline_before = wp_dbg_inline_data_to_string( $wp_scripts->get_data( $h, 'before' ) );
						$inline_after  = wp_dbg_inline_data_to_string( $wp_scripts->get_data( $h, 'after' ) );
				}

				$inline_bytes = wp_dbg_safe_strlen_bytes( $inline_before ) + wp_dbg_safe_strlen_bytes( $inline_after );
				$total_inline_js_bytes += $inline_bytes;

				if ( $inline_bytes > 0 ) {
					$inline_js_items[] = [
						'handle' => (string) $h,
						'bytes'  => (int) $inline_bytes,
						'where'  => ( $inline_before ? 'before' : '' ) . ( $inline_before && $inline_after ? '+' : '' ) . ( $inline_after ? 'after' : '' ),
					];
				}

				$assets_js[] = [
					'handle'    => (string) $h,
					'src'       => (string) $src_abs,
					'ver'       => (string) $ver,
					'external'  => (bool) $is_external,
					'bytes'     => (int) $bytes,
					'path'      => (string) $path,
					'location'  => $in_footer ? 'footer' : 'head',
					'deps'      => $deps,
					'strategy'  => $strategy,
					'inline'    => (int) $inline_bytes,
				];

				if ( $src_abs ) {
					if ( ! isset( $src_seen[ $src_abs ] ) ) $src_seen[ $src_abs ] = [];
					$src_seen[ $src_abs ][] = 'js:' . $h;
				}
			}

			foreach ( (array) $styles_enqueued as $h ) {

				$reg = isset( $wp_styles->registered[ $h ] ) ? $wp_styles->registered[ $h ] : null;

				$src  = '';
				$ver  = '';
				$deps = '';

				if ( $reg ) {
					$src  = isset( $reg->src ) ? (string) $reg->src : '';
					$ver  = isset( $reg->ver ) ? (string) $reg->ver : '';
					$deps = isset( $reg->deps ) && is_array( $reg->deps ) ? $reg->deps : [];
				}

				$src_abs     = wp_dbg_normalize_url( $src );
				$is_external = $src_abs ? wp_dbg_is_external( $src_abs, $home_host ) : false;

				if ( $ver === '' ) $nover_css++;

				$bytes = 0;
				$path  = '';
				if ( ! $is_external && $src_abs ) {
					$path = wp_dbg_url_to_path( $src_abs );
					if ( $path ) $bytes = (int) @filesize( $path );
				}

				$total_css_bytes += $bytes;

				if ( $is_external ) {
					$total_ext_css++;
					$host = wp_dbg_host( $src_abs );
					if ( $host ) {
						$third_party_domains[ $host ] = isset( $third_party_domains[ $host ] ) ? $third_party_domains[ $host ] + 1 : 1;
						$third_party_assets[] = [ 'type' => 'css', 'handle' => $h, 'host' => $host, 'src' => $src_abs ];
					}
				}

				// Inline CSS sizes: before/after for style handle
				$inline_before = '';
				$inline_after  = '';
				if ( is_object( $wp_styles ) && method_exists( $wp_styles, 'get_data' ) ) {
						$inline_before = wp_dbg_inline_data_to_string( $wp_styles->get_data( $h, 'before' ) );
						$inline_after  = wp_dbg_inline_data_to_string( $wp_styles->get_data( $h, 'after' ) );
				}
				$inline_bytes = wp_dbg_safe_strlen_bytes( $inline_before ) + wp_dbg_safe_strlen_bytes( $inline_after );
				$total_inline_css_bytes += $inline_bytes;

				if ( $inline_bytes > 0 ) {
					$inline_css_items[] = [
						'handle' => (string) $h,
						'bytes'  => (int) $inline_bytes,
						'where'  => ( $inline_before ? 'before' : '' ) . ( $inline_before && $inline_after ? '+' : '' ) . ( $inline_after ? 'after' : '' ),
					];
				}

				$assets_css[] = [
					'handle'   => (string) $h,
					'src'      => (string) $src_abs,
					'ver'      => (string) $ver,
					'external' => (bool) $is_external,
					'bytes'    => (int) $bytes,
					'path'     => (string) $path,
					'deps'     => $deps,
					'inline'   => (int) $inline_bytes,
				];

				if ( $src_abs ) {
					if ( ! isset( $src_seen[ $src_abs ] ) ) $src_seen[ $src_abs ] = [];
					$src_seen[ $src_abs ][] = 'css:' . $h;
				}
			}

			foreach ( $src_seen as $src => $arr ) {
				if ( is_array( $arr ) && count( $arr ) > 1 ) {
					$dup_src[ $src ] = $arr;
				}
			}

			usort( $inline_js_items, function ( $a, $b ) { return (int) $b['bytes'] <=> (int) $a['bytes']; } );
			usort( $inline_css_items, function ( $a, $b ) { return (int) $b['bytes'] <=> (int) $a['bytes']; } );

			// =========================
			// Media (images from content + featured)
			// =========================

			$media_images = [];
			$total_img_bytes = 0;
			$img_missing_dims = 0;
			$img_big_count = 0;

			$content_html = '';
			if ( $post ) $content_html = (string) $post->post_content;

			$imgs = wp_dbg_extract_images_from_html( $content_html );

			foreach ( $imgs as $img ) {
				$src = isset( $img['src'] ) ? (string) $img['src'] : '';
				if ( $src === '' ) continue;

				$src_abs     = wp_dbg_normalize_url( $src );
				$is_external = $src_abs ? wp_dbg_is_external( $src_abs, $home_host ) : false;

				$bytes = 0;
				$path  = '';
				if ( ! $is_external ) {
					$path = wp_dbg_url_to_path( $src_abs );
					if ( $path ) $bytes = (int) @filesize( $path );
				} else {
					$host = wp_dbg_host( $src_abs );
					if ( $host ) {
						$third_party_domains[ $host ] = isset( $third_party_domains[ $host ] ) ? $third_party_domains[ $host ] + 1 : 1;
						$third_party_assets[] = [ 'type' => 'img', 'handle' => '', 'host' => $host, 'src' => $src_abs ];
					}
				}

				$total_img_bytes += $bytes;

				$w = isset( $img['width'] ) ? trim( (string) $img['width'] ) : '';
				$h = isset( $img['height'] ) ? trim( (string) $img['height'] ) : '';
				$has_dims = ( $w !== '' && $h !== '' );

				if ( ! $has_dims ) $img_missing_dims++;
				if ( $bytes >= 1024 * 1024 ) $img_big_count++;

				$media_images[] = [
					'src'      => $src_abs,
					'external' => (bool) $is_external,
					'bytes'    => (int) $bytes,
					'path'     => (string) $path,
					'width'    => $w,
					'height'   => $h,
					'featured' => false,
				];
			}

			if ( $post ) {
				$thumb_id = (int) get_post_thumbnail_id( $post->ID );
				if ( $thumb_id > 0 ) {
					$thumb_src = wp_get_attachment_image_url( $thumb_id, 'full' );
					if ( $thumb_src ) {
						$thumb_abs = wp_dbg_normalize_url( $thumb_src );
						$path      = wp_dbg_url_to_path( $thumb_abs );
						$bytes     = $path ? (int) @filesize( $path ) : 0;

						$total_img_bytes += $bytes;
						if ( $bytes >= 1024 * 1024 ) $img_big_count++;

						$media_images[] = [
							'src'      => $thumb_abs,
							'external' => false,
							'bytes'    => (int) $bytes,
							'path'     => (string) $path,
							'width'    => '',
							'height'   => '',
							'featured' => true,
						];
					}
				}
			}

			// =========================
			// Fonts & CSS background images (best-effort)
			// =========================

			$font_refs = [];     // url => meta
			$font_total_bytes = 0;

			$css_bg_imgs = [];   // url => meta
			$css_bg_total_bytes = 0;

			// We read only "reasonable" local CSS files to avoid heavy IO.
			// Conditions: local path exists and size <= 700 KB
			$css_files_to_scan = [];
			foreach ( $assets_css as $it ) {
				if ( ! empty( $it['external'] ) ) continue;
				if ( empty( $it['path'] ) ) continue;
				if ( empty( $it['bytes'] ) ) continue;

				if ( (int) $it['bytes'] <= 700 * 1024 ) {
					$css_files_to_scan[] = $it;
				}
			}

			$css_scan_count = 0;
			foreach ( $css_files_to_scan as $it ) {
				$css_scan_count++;
				if ( $css_scan_count > 6 ) break; // limit

				$css = @file_get_contents( $it['path'] );
				if ( ! is_string( $css ) || $css === '' ) continue;

				$fonts = wp_dbg_css_extract_font_urls( $css );
				foreach ( $fonts as $u ) {
					$u_abs = wp_dbg_normalize_url( $u );

					$is_ext = $u_abs ? wp_dbg_is_external( $u_abs, $home_host ) : false;
					$bytes  = 0;
					$path   = '';

					if ( ! $is_ext ) {
						$path = wp_dbg_url_to_path( $u_abs );
						if ( $path ) $bytes = (int) @filesize( $path );
					} else {
						$host = wp_dbg_host( $u_abs );
						if ( $host ) {
							$third_party_domains[ $host ] = isset( $third_party_domains[ $host ] ) ? $third_party_domains[ $host ] + 1 : 1;
							$third_party_assets[] = [ 'type' => 'font', 'handle' => $it['handle'], 'host' => $host, 'src' => $u_abs ];
						}
					}

					if ( ! isset( $font_refs[ $u_abs ] ) ) {
						$font_refs[ $u_abs ] = [
							'url'   => $u_abs,
							'bytes' => (int) $bytes,
							'ext'   => (bool) $is_ext,
							'from'  => [],
						];
					}
					$font_refs[ $u_abs ]['from'][] = $it['handle'];

					$font_total_bytes += $bytes;
				}

				$bg_imgs = wp_dbg_css_extract_bg_images( $css );
				foreach ( $bg_imgs as $u ) {
					$u_abs = wp_dbg_normalize_url( $u );

					$is_ext = $u_abs ? wp_dbg_is_external( $u_abs, $home_host ) : false;
					$bytes  = 0;
					$path   = '';

					if ( ! $is_ext ) {
						$path = wp_dbg_url_to_path( $u_abs );
						if ( $path ) $bytes = (int) @filesize( $path );
					} else {
						$host = wp_dbg_host( $u_abs );
						if ( $host ) {
							$third_party_domains[ $host ] = isset( $third_party_domains[ $host ] ) ? $third_party_domains[ $host ] + 1 : 1;
							$third_party_assets[] = [ 'type' => 'bg', 'handle' => $it['handle'], 'host' => $host, 'src' => $u_abs ];
						}
					}

					if ( ! isset( $css_bg_imgs[ $u_abs ] ) ) {
						$css_bg_imgs[ $u_abs ] = [
							'url'   => $u_abs,
							'bytes' => (int) $bytes,
							'ext'   => (bool) $is_ext,
							'from'  => [],
						];
					}
					$css_bg_imgs[ $u_abs ]['from'][] = $it['handle'];

					$css_bg_total_bytes += $bytes;
				}
			}

			$font_refs = array_values( $font_refs );
			usort( $font_refs, function ( $a, $b ) { return (int) $b['bytes'] <=> (int) $a['bytes']; } );

			$css_bg_imgs = array_values( $css_bg_imgs );
			usort( $css_bg_imgs, function ( $a, $b ) { return (int) $b['bytes'] <=> (int) $a['bytes']; } );

			// Third-party domains sorted
			$third_party_domains_sorted = [];
			foreach ( $third_party_domains as $host => $cnt ) {
				$third_party_domains_sorted[] = [ 'host' => $host, 'count' => (int) $cnt ];
			}
			usort( $third_party_domains_sorted, function ( $a, $b ) { return (int) $b['count'] <=> (int) $a['count']; } );

			// =========================
			// PSI hints heuristics (MAX)
			// =========================

			$psi_warnings = [];

			$total_requests_est = count( $assets_js ) + count( $assets_css ) + count( $media_images );
			$total_js_all = $total_js_bytes + $total_inline_js_bytes;
			$total_css_all = $total_css_bytes + $total_inline_css_bytes;

			if ( $total_requests_est > 70 ) {
				$psi_warnings[] = [ 'high', 'Очень много запросов (est.): ' . $total_requests_est . ' — часто бьёт по mobile PSI.' ];
			} elseif ( $total_requests_est > 50 ) {
				$psi_warnings[] = [ 'med', 'Много запросов (est.): ' . $total_requests_est . ' — проверь third-party и дробление ассетов.' ];
			}

			if ( $total_js_all > 1100 * 1024 ) {
				$psi_warnings[] = [ 'high', 'JS общий (local+inline): ' . wp_dbg_human_bytes( $total_js_all ) . ' — риск TBT/Long Tasks.' ];
			} elseif ( $total_js_all > 800 * 1024 ) {
				$psi_warnings[] = [ 'med', 'JS общий (local+inline): ' . wp_dbg_human_bytes( $total_js_all ) . ' — возможная просадка TBT.' ];
			}

			if ( $total_css_all > 320 * 1024 ) {
				$psi_warnings[] = [ 'med', 'CSS общий (local+inline): ' . wp_dbg_human_bytes( $total_css_all ) . ' — часто “Remove unused CSS / Reduce CSS”.' ];
			}

			if ( $head_js_count > 0 ) {
				$psi_warnings[] = [ 'med', 'Есть JS в <head>: ' . $head_js_count . ' — проверь defer/strategy или перенос в footer.' ];
			}
			if ( $head_js_without_strategy > 0 ) {
				$psi_warnings[] = [ 'med', 'Head JS без strategy (defer/async): ' . $head_js_without_strategy . ' — вероятный render-block / TBT.' ];
			}

			if ( $total_inline_js_bytes > 40 * 1024 ) {
				$psi_warnings[] = [ 'med', 'Inline JS большой: ' . wp_dbg_human_bytes( $total_inline_js_bytes ) . ' — не кэшируется, усложняет оптимизацию.' ];
			}
			if ( $total_inline_css_bytes > 25 * 1024 ) {
				$psi_warnings[] = [ 'med', 'Inline CSS большой: ' . wp_dbg_human_bytes( $total_inline_css_bytes ) . ' — может дуть HTML и ломать кеш.' ];
			}

			if ( $img_big_count > 0 ) {
				$psi_warnings[] = [ 'med', 'Крупные <img> (≥1MB): ' . $img_big_count . ' — WebP/AVIF + resize.' ];
			}
			if ( $img_missing_dims > 0 ) {
				$psi_warnings[] = [ 'med', 'Есть <img> без width/height: ' . $img_missing_dims . ' — риск CLS.' ];
			}

			if ( count( $third_party_domains_sorted ) > 0 ) {
				$psi_warnings[] = [ 'low', 'Есть third-party домены: ' . count( $third_party_domains_sorted ) . ' — часто сажают PSI.' ];
			}

			if ( ! empty( $dup_src ) ) {
				$psi_warnings[] = [ 'med', 'Найдены дубли по src: ' . count( $dup_src ) . ' — проверь двойные регистрации/подключения.' ];
			}

			if ( $font_total_bytes > 250 * 1024 ) {
				$psi_warnings[] = [ 'med', 'Fonts (local, найдено в CSS): ' . wp_dbg_human_bytes( $font_total_bytes ) . ' — проверь subset, woff2, preload, font-display.' ];
			}

			$debug_collect_elapsed_ms = round( ( microtime( true ) - $debug_collect_started_at ) * 1000, 2 );
			$debug_collect_mem_delta_kb = round( ( memory_get_usage() - $debug_collect_start_mem ) / 1024, 2 );

			$debug_snapshot = [
				'generated_at' => gmdate( 'c' ),
				'url' => isset( $_SERVER['REQUEST_URI'] ) ? home_url( (string) $_SERVER['REQUEST_URI'] ) : home_url( '/' ),
				'template' => $template_name,
				'collect' => [
					'ms' => (float) $debug_collect_elapsed_ms,
					'memory_delta_kb' => (float) $debug_collect_mem_delta_kb,
				],
				'requests_est' => (int) $total_requests_est,
				'assets' => [
					'js_count' => (int) count( $assets_js ),
					'css_count' => (int) count( $assets_css ),
					'head_js' => (int) $head_js_count,
					'head_js_without_strategy' => (int) $head_js_without_strategy,
					'js_local_bytes' => (int) $total_js_bytes,
					'css_local_bytes' => (int) $total_css_bytes,
					'inline_js_bytes' => (int) $total_inline_js_bytes,
					'inline_css_bytes' => (int) $total_inline_css_bytes,
				],
				'images' => [
					'total_bytes' => (int) $total_img_bytes,
					'missing_dims' => (int) $img_missing_dims,
					'big_images' => (int) $img_big_count,
				],
				'db' => [
					'num_queries' => (int) $num_queries,
					'query_time_sec' => (float) $query_time,
					'slow_queries' => (int) count( $slow_queries ),
				],
				'third_party_domains' => $third_party_domains_sorted,
				'psi_hints' => $psi_warnings,
			];

			$debug_snapshot_json = wp_json_encode(
				$debug_snapshot,
				JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
			);

			// =========================
			// Tabs
			// =========================

			$tabs = [
				'page'    => 'Page',
				'assets'  => 'Assets',
				'weights' => 'Weights',
				'inline'  => 'Inline',
				'fonts'   => 'Fonts & BG',
				'third'   => '3rd-party',
				'psi'     => 'PSI Hints',
				'db'      => 'SQL',
				'server'  => 'Server',
				'plugins' => 'Plugins',
				'acf'     => 'ACF / Meta',
				'theme'   => 'Theme',
			];

			?>
            <style>
                #wp-dbg-panel {
                    position: fixed;
                    z-index: 2147483647;
                    left: 20px;
                    bottom: 20px;
                    width: 720px;
                    max-width: calc(100vw - 40px);
                    max-height: 78vh;

                    background: linear-gradient(180deg, #0f1220 0%, #0b0d16 100%);
                    border: 1px solid rgba(124, 58, 237, 0.22);
                    border-radius: 14px;

                    box-shadow:
                            0 0 0 1px rgba(124, 58, 237, 0.12),
                            0 24px 80px rgba(0,0,0,0.70),
                            inset 0 1px 0 rgba(255,255,255,0.06);

                    color: #e5e7eb;
                    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
                    overflow: hidden;

                    display: flex;
                    flex-direction: column;
                }

                #wp-dbg-panel * { box-sizing: border-box; }

                #wp-dbg-header {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;

                    padding: 12px 14px;
                    background: rgba(255,255,255,0.02);
                    border-bottom: 1px solid rgba(255,255,255,0.06);

                    cursor: move;
                    user-select: none;
                }

                #wp-dbg-title {
                    display: flex;
                    align-items: center;
                    gap: 10px;

                    font-size: 12px;
                    font-weight: 900;
                    letter-spacing: 2.6px;
                    text-transform: uppercase;

                    color: #a78bfa;
                }

                #wp-dbg-title .dbg-title-template {
                    display: none;
                    max-width: 360px;
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;

                    font-size: 12px;
                    font-weight: 900;
                    letter-spacing: 0;
                    text-transform: none;
                    color: #e5e7eb;
                }

                #wp-dbg-title .dbg-dot {
                    width: 8px;
                    height: 8px;
                    border-radius: 50%;
                    background: #a78bfa;
                    box-shadow: 0 0 12px rgba(167,139,250,0.9);
                    animation: dbg-blink 2.4s ease-in-out infinite;
                    flex-shrink: 0;
                }

                @keyframes dbg-blink {
                    0%, 100% { opacity: 1; }
                    50% { opacity: 0.25; }
                }

                #wp-dbg-controls { display: flex; gap: 8px; }

                #wp-dbg-controls button {
                    all: unset;
                    width: 28px;
                    height: 28px;
                    border-radius: 9px;

                    display: flex;
                    align-items: center;
                    justify-content: center;

                    cursor: pointer;
                    font-size: 12px;
                    font-weight: 900;

                    transition: transform 0.12s ease, background 0.12s ease, border-color 0.12s ease, color 0.12s ease;
                    border: 1px solid rgba(255,255,255,0.08);
                    background: rgba(255,255,255,0.04);
                    color: #e5e7eb;
                }

                #wp-dbg-controls button:hover {
                    transform: translateY(-1px);
                    background: rgba(255,255,255,0.07);
                    border-color: rgba(255,255,255,0.14);
                }

                #wp-dbg-controls .dbg-btn-min {
                    background: rgba(245,158,11,0.14);
                    border-color: rgba(245,158,11,0.22);
                    color: #fbbf24;
                }

                #wp-dbg-controls .dbg-btn-min:hover {
                    background: rgba(245,158,11,0.26);
                    border-color: rgba(245,158,11,0.34);
                }

                #wp-dbg-controls .dbg-btn-close {
                    background: rgba(239,68,68,0.14);
                    border-color: rgba(239,68,68,0.22);
                    color: #f87171;
                }

                #wp-dbg-controls .dbg-btn-close:hover {
                    background: rgba(239,68,68,0.26);
                    border-color: rgba(239,68,68,0.34);
                }

                #wp-dbg-controls .dbg-btn-json {
                    width: auto;
                    min-width: 44px;
                    padding: 0 10px;
                    background: rgba(99,102,241,0.14);
                    border-color: rgba(99,102,241,0.24);
                    color: #c7d2fe;
                    font-size: 11px;
                    letter-spacing: 0.3px;
                }

                #wp-dbg-controls .dbg-btn-json:hover {
                    background: rgba(99,102,241,0.28);
                    border-color: rgba(99,102,241,0.36);
                }

                #wp-dbg-tabs {
                    display: flex;
                    align-items: center;
                    gap: 2px;

                    padding: 6px;
                    background: rgba(0,0,0,0.25);
                    border-bottom: 1px solid rgba(255,255,255,0.06);

                    overflow-x: auto;
                    scrollbar-width: none;
                }
                #wp-dbg-tabs::-webkit-scrollbar { display: none; }

                .wp-dbg-tab {
                    display: flex;
                    align-items: center;

                    height: 34px;
                    padding: 0 12px;

                    border-radius: 10px;
                    cursor: pointer;
                    white-space: nowrap;

                    font-size: 12px;
                    font-weight: 800;

                    color: rgba(229,231,235,0.60);
                    background: transparent;
                    border: 1px solid transparent;

                    transition: background 0.12s ease, color 0.12s ease, border-color 0.12s ease;
                    user-select: none;
                }

                .wp-dbg-tab:hover {
                    color: rgba(229,231,235,0.85);
                    background: rgba(255,255,255,0.04);
                    border-color: rgba(255,255,255,0.08);
                }

                .wp-dbg-tab.active {
                    color: #e5e7eb;
                    background: rgba(124,58,237,0.16);
                    border-color: rgba(124,58,237,0.28);
                    box-shadow: inset 0 1px 0 rgba(255,255,255,0.04);
                }

                #wp-dbg-body {
                    display: block;
                    overflow-y: auto;
                    padding: 12px 14px;
                    flex: 1;

                    scrollbar-width: thin;
                    scrollbar-color: rgba(124,58,237,0.35) transparent;
                }
                #wp-dbg-body::-webkit-scrollbar { width: 8px; }
                #wp-dbg-body::-webkit-scrollbar-thumb {
                    background: rgba(124,58,237,0.28);
                    border-radius: 10px;
                    border: 2px solid transparent;
                    background-clip: content-box;
                }

                .wp-dbg-panel { display: none; }
                .wp-dbg-panel.active { display: block; }

                .dbg-row {
                    display: flex;
                    align-items: flex-start;
                    gap: 12px;

                    padding: 8px 0;
                    border-bottom: 1px solid rgba(255,255,255,0.06);
                }
                .dbg-row:last-child { border-bottom: none; }

                .dbg-k {
                    min-width: 150px;
                    flex-shrink: 0;

                    font-size: 12px;
                    font-weight: 800;
                    color: rgba(229,231,235,0.55);
                    line-height: 18px;
                }

                .dbg-v {
                    flex: 1;

                    font-size: 12px;
                    font-weight: 700;
                    color: rgba(229,231,235,0.92);
                    line-height: 18px;

                    word-break: break-word;
                    overflow-wrap: anywhere;

                    a {
                        font-size: 12px;
                    }
                }

                .dbg-badge {
                    display: inline-flex;
                    align-items: center;

                    padding: 4px 10px;
                    border-radius: 999px;

                    font-size: 11px;
                    font-weight: 900;
                    letter-spacing: 0.6px;
                    text-transform: uppercase;

                    border: 1px solid rgba(255,255,255,0.10);
                    background: rgba(255,255,255,0.05);
                    color: #e5e7eb;
                }

                .dbg-bg { background: rgba(124,58,237,0.16); border-color: rgba(124,58,237,0.28); color: #c4b5fd; }
                .dbg-gg { background: rgba(34,197,94,0.14);  border-color: rgba(34,197,94,0.24);  color: #86efac; }
                .dbg-rr { background: rgba(239,68,68,0.14);   border-color: rgba(239,68,68,0.24);   color: #fca5a5; }
                .dbg-yy { background: rgba(245,158,11,0.14);  border-color: rgba(245,158,11,0.24);  color: #fcd34d; }

                .dbg-tags {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 6px;
                }

                .dbg-tag {
                    display: inline-flex;
                    align-items: center;

                    padding: 4px 10px;
                    border-radius: 999px;

                    font-size: 11px;
                    font-weight: 900;

                    background: rgba(124,58,237,0.12);
                    color: #c4b5fd;
                    border: 1px solid rgba(124,58,237,0.22);
                }
                .dbg-tag-gray {
                    background: rgba(107,114,128,0.12);
                    color: rgba(229,231,235,0.70);
                    border-color: rgba(107,114,128,0.20);
                }

                .dbg-stats {
                    display: flex;
                    gap: 10px;
                    flex-wrap: wrap;
                    margin-bottom: 12px;
                }

                .dbg-stat {
                    flex: 1;
                    min-width: 170px;

                    background: rgba(255,255,255,0.04);
                    border: 1px solid rgba(255,255,255,0.08);
                    border-radius: 12px;

                    padding: 10px 12px;
                }

                .dbg-stat-l {
                    font-size: 11px;
                    font-weight: 900;
                    letter-spacing: 1.2px;
                    text-transform: uppercase;

                    color: rgba(229,231,235,0.50);
                    margin-bottom: 8px;
                }

                .dbg-stat-v {
                    font-size: 22px;
                    font-weight: 1000;
                    line-height: 22px;
                    color: #e5e7eb;
                }

                .dbg-section {
                    margin-top: 14px;
                    margin-bottom: 8px;

                    font-size: 11px;
                    font-weight: 1000;
                    letter-spacing: 2px;
                    text-transform: uppercase;

                    color: rgba(229,231,235,0.45);
                }

                .dbg-item {
                    padding: 10px 0;
                    border-bottom: 1px solid rgba(255,255,255,0.06);
                }
                .dbg-item:last-child { border-bottom: none; }

                .dbg-item-top {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 10px;

                    margin-bottom: 6px;
                }

                .dbg-item-h {
                    font-size: 12px;
                    font-weight: 900;
                    line-height: 18px;
                    color: rgba(229,231,235,0.92);
                    word-break: break-word;
                    overflow-wrap: anywhere;
                    flex: 1;
                }

                .dbg-item-meta {
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    flex-shrink: 0;
                }

                .dbg-item-s {
                    font-size: 11px;
                    font-weight: 700;
                    color: rgba(229,231,235,0.58);
                    line-height: 16px;
                    word-break: break-all;
                }

                .dbg-pill {
                    display: inline-flex;
                    align-items: center;

                    padding: 3px 8px;
                    border-radius: 999px;

                    font-size: 10px;
                    font-weight: 1000;
                    letter-spacing: 0.6px;
                    text-transform: uppercase;

                    border: 1px solid rgba(255,255,255,0.12);
                    background: rgba(255,255,255,0.05);
                    color: rgba(229,231,235,0.86);
                }

                .dbg-pill-ext { background: rgba(245,158,11,0.12); border-color: rgba(245,158,11,0.22); color: #fcd34d; }
                .dbg-pill-big { background: rgba(239,68,68,0.12); border-color: rgba(239,68,68,0.22); color: #fca5a5; }
                .dbg-pill-ok  { background: rgba(34,197,94,0.12); border-color: rgba(34,197,94,0.22); color: #86efac; }
                .dbg-pill-head{ background: rgba(124,58,237,0.12); border-color: rgba(124,58,237,0.22); color: #c4b5fd; }
                .dbg-pill-warn{ background: rgba(245,158,11,0.12); border-color: rgba(245,158,11,0.22); color: #fcd34d; }

                .dbg-hint {
                    padding: 18px 12px;
                    text-align: left;

                    font-size: 12px;
                    font-weight: 800;
                    line-height: 18px;

                    color: rgba(229,231,235,0.70);

                    background: rgba(255,255,255,0.03);
                    border: 1px solid rgba(255,255,255,0.08);
                    border-radius: 12px;
                }

                .dbg-hint + .dbg-hint { margin-top: 10px; }

                #wp-dbg-panel.is-minimized #wp-dbg-tabs,
                #wp-dbg-panel.is-minimized #wp-dbg-body {
                    display: none;
                }

                #wp-dbg-panel.is-minimized #wp-dbg-title {
                    gap: 0;
                    letter-spacing: 0;
                    text-transform: none;
                }

                #wp-dbg-panel.is-minimized #wp-dbg-title .dbg-dot,
                #wp-dbg-panel.is-minimized #wp-dbg-title .dbg-title-debug {
                    display: none;
                }

                #wp-dbg-panel.is-minimized #wp-dbg-title .dbg-title-template {
                    display: inline-block;
                }

                #wp-dbg-open {
                    position: fixed;
                    z-index: 2147483646;
                    left: 20px;
                    bottom: 20px;

                    display: none;
                    align-items: center;
                    gap: 8px;

                    max-width: calc(100vw - 40px);
                    height: 40px;
                    padding: 0 14px;

                    border-radius: 10px;
                    border: 1px solid rgba(124,58,237,0.32);
                    background: rgba(15,18,32,0.96);
                    color: #ddd6fe;

                    font-size: 12px;
                    font-weight: 900;
                    letter-spacing: 0.4px;

                    cursor: pointer;
                    box-shadow: 0 10px 32px rgba(0,0,0,0.55);
                }

                #wp-dbg-open:hover {
                    background: rgba(23,27,46,0.98);
                    border-color: rgba(124,58,237,0.50);
                }

                @media (max-width: 520px) {
                    #wp-dbg-panel {
                        width: calc(100vw - 24px);
                        left: 12px;
                        bottom: 12px;
                        border-radius: 12px;
                    }
                    #wp-dbg-open {
                        left: 12px;
                        bottom: 12px;
                        max-width: calc(100vw - 24px);
                    }
                    .dbg-k { min-width: 120px; }
                    .dbg-stat { min-width: 150px; }
                }
            </style>

            <div id="wp-dbg-panel" role="dialog" aria-label="WordPress debug panel">
                <div id="wp-dbg-header" aria-label="Drag handle">
                    <div id="wp-dbg-title">
                        <span class="dbg-dot"></span>
                        <span class="dbg-title-debug">DEBUG</span>
                        <span class="dbg-title-template"><?php echo esc_html( $template_name ); ?></span>
                    </div>
                    <div id="wp-dbg-controls">
                        <button type="button" class="dbg-btn-json" id="wp-dbg-btn-json" aria-label="Export JSON">JSON</button>
                        <button type="button" class="dbg-btn-min" id="wp-dbg-btn-min" aria-label="Minimize">—</button>
                        <button type="button" class="dbg-btn-close" id="wp-dbg-btn-close" aria-label="Close">✕</button>
                    </div>
                </div>

                <div id="wp-dbg-tabs" role="tablist" aria-label="Debug tabs">
					<?php foreach ( $tabs as $key => $label ) : ?>
                        <div class="wp-dbg-tab" role="tab" tabindex="0" data-tab="<?php echo esc_attr( $key ); ?>">
							<?php echo esc_html( $label ); ?>
                        </div>
					<?php endforeach; ?>
                </div>

                <div id="wp-dbg-body">

                    <!-- PAGE -->
                    <div class="wp-dbg-panel" id="wp-dbg-panel-page">
						<?php
							$page_rows = [
								'Template'    => '<span class="dbg-badge dbg-bg">' . esc_html( basename( (string) $template ) ) . '</span>',
								'Conditions'  => $conditionals
									? '<div class="dbg-tags">' . implode( '', array_map( function ( $c ) {
										return '<span class="dbg-tag">' . esc_html( $c ) . '</span>';
									}, $conditionals ) ) . '</div>'
									: '<span class="dbg-badge dbg-rr">none</span>',
								'Post type'   => esc_html( get_post_type() ?: '—' ),
								'Post ID'     => esc_html( get_the_ID() ?: '—' ),
								'Status'      => $post ? '<span class="dbg-badge ' . ( (string) $post->post_status === 'publish' ? 'dbg-gg' : 'dbg-yy' ) . '">' . esc_html( (string) $post->post_status ) . '</span>' : '—',
								'Modified'    => esc_html( $post ? get_the_modified_date( 'd.m.Y H:i', $post ) : '—' ),
								'Author'      => esc_html( $post ? get_the_author_meta( 'display_name', (int) $post->post_author ) : '—' ),
								'User'        => esc_html( $user_login . ' · ' . $user_roles ),
								'Found posts' => esc_html( isset( $wp_query->found_posts ) ? (string) $wp_query->found_posts : '—' ),
								'Max pages'   => esc_html( isset( $wp_query->max_num_pages ) ? (string) $wp_query->max_num_pages : '—' ),
								'OB level'    => '<span class="dbg-badge ' . ( $ob_level > 1 ? 'dbg-yy' : 'dbg-gg' ) . '">' . (int) $ob_level . '</span>',
								'Hooks'       => esc_html( $hooks_count . ' registered' ),
							];

							if ( $object instanceof WP_Term ) {
								$page_rows['Term']     = esc_html( $object->name ) . ' <span style="opacity:.55">#' . (int) $object->term_id . '</span>';
								$page_rows['Taxonomy'] = esc_html( $object->taxonomy );
								$page_rows['Posts']    = esc_html( (string) $object->count );
							}

							if ( $post ) {
								$tpl = get_page_template_slug( $post );
								if ( $tpl ) {
									$page_rows['Page tpl'] = esc_html( $tpl );
								}
							}

							foreach ( $page_rows as $k => $v ) :
								?>
                                <div class="dbg-row">
                                    <div class="dbg-k"><?php echo esc_html( $k ); ?></div>
                                    <div class="dbg-v"><?php echo $v; ?></div>
                                </div>
							<?php endforeach; ?>

						<?php if ( $permalink ) : ?>
                            <div class="dbg-row">
                                <div class="dbg-k">Permalink</div>
                                <div class="dbg-v">
                                    <a href="<?php echo esc_url( $permalink ); ?>" target="_blank" rel="noopener" style="color:#c4b5fd;font-weight:900;text-decoration:none;">
										<?php echo esc_html( $home ? str_replace( $home, '', $permalink ) : $permalink ); ?>
                                    </a>
                                </div>
                            </div>
						<?php endif; ?>

						<?php if ( $post && function_exists( 'get_edit_post_link' ) ) : ?>
                            <div class="dbg-row">
                                <div class="dbg-k">Edit URL</div>
                                <div class="dbg-v">
                                    <a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>" target="_blank" rel="noopener" style="color:#c4b5fd;font-weight:900;text-decoration:none;">
                                        wp-admin
                                    </a>
                                </div>
                            </div>
						<?php endif; ?>
                    </div>

                    <!-- ASSETS -->
                    <div class="wp-dbg-panel" id="wp-dbg-panel-assets">

                        <div class="dbg-stats">
                            <div class="dbg-stat">
                                <div class="dbg-stat-l">JS files</div>
                                <div class="dbg-stat-v" style="color:#c4b5fd"><?php echo (int) count( $assets_js ); ?></div>
                            </div>
                            <div class="dbg-stat">
                                <div class="dbg-stat-l">CSS files</div>
                                <div class="dbg-stat-v" style="color:#ddd6fe"><?php echo (int) count( $assets_css ); ?></div>
                            </div>
                            <div class="dbg-stat">
                                <div class="dbg-stat-l">Head JS</div>
                                <div class="dbg-stat-v" style="color:<?php echo $head_js_count ? '#fcd34d' : '#86efac'; ?>">
									<?php echo (int) $head_js_count; ?>
                                </div>
                            </div>
                        </div>

                        <div class="dbg-section">JavaScript</div>
						<?php if ( empty( $assets_js ) ) : ?>
                            <div class="dbg-hint">Нет enqueued JS</div>
						<?php else : ?>
							<?php foreach ( $assets_js as $it ) :
								$size_label = $it['bytes'] > 0 ? wp_dbg_human_bytes( $it['bytes'] ) : '—';
								$is_big     = ( $it['bytes'] >= 220 * 1024 );
								$inline_big = ( $it['inline'] >= 20 * 1024 );
								?>
                                <div class="dbg-item">
                                    <div class="dbg-item-top">
                                        <div class="dbg-item-h"><?php echo esc_html( $it['handle'] ); ?></div>
                                        <div class="dbg-item-meta">
										<span class="dbg-pill <?php echo $it['external'] ? 'dbg-pill-ext' : 'dbg-pill-ok'; ?>">
											<?php echo $it['external'] ? 'ext' : 'local'; ?>
										</span>
											<?php if ( $it['location'] === 'head' ) : ?>
                                                <span class="dbg-pill dbg-pill-head">head</span>
											<?php endif; ?>
											<?php if ( $it['strategy'] !== '' ) : ?>
                                                <span class="dbg-pill dbg-pill-ok"><?php echo esc_html( $it['strategy'] ); ?></span>
											<?php elseif ( $it['location'] === 'head' ) : ?>
                                                <span class="dbg-pill dbg-pill-warn">no strategy</span>
											<?php endif; ?>
											<?php if ( $is_big ) : ?>
                                                <span class="dbg-pill dbg-pill-big"><?php echo esc_html( $size_label ); ?></span>
											<?php else : ?>
                                                <span class="dbg-pill"><?php echo esc_html( $size_label ); ?></span>
											<?php endif; ?>
											<?php if ( $it['inline'] > 0 ) : ?>
                                                <span class="dbg-pill <?php echo $inline_big ? 'dbg-pill-big' : 'dbg-pill-warn'; ?>">
												inline <?php echo esc_html( wp_dbg_human_bytes( $it['inline'] ) ); ?>
											</span>
											<?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="dbg-item-s">
										<?php echo esc_html( $it['src'] ? str_replace( $home, '', $it['src'] ) : '— inline/registered' ); ?>
										<?php if ( $it['ver'] !== '' ) : ?>
                                            <span style="opacity:.55"> v<?php echo esc_html( $it['ver'] ); ?></span>
										<?php else : ?>
                                            <span style="opacity:.55"> (no ver)</span>
										<?php endif; ?>
                                    </div>
									<?php if ( ! empty( $it['deps'] ) ) : ?>
                                        <div class="dbg-item-s" style="opacity:.75">
                                            deps: <?php echo esc_html( implode( ', ', (array) $it['deps'] ) ); ?>
                                        </div>
									<?php endif; ?>
                                </div>
							<?php endforeach; ?>
						<?php endif; ?>

                        <div class="dbg-section">CSS</div>
						<?php if ( empty( $assets_css ) ) : ?>
                            <div class="dbg-hint">Нет enqueued CSS</div>
						<?php else : ?>
							<?php foreach ( $assets_css as $it ) :
								$size_label = $it['bytes'] > 0 ? wp_dbg_human_bytes( $it['bytes'] ) : '—';
								$is_big     = ( $it['bytes'] >= 140 * 1024 );
								$inline_big = ( $it['inline'] >= 16 * 1024 );
								?>
                                <div class="dbg-item">
                                    <div class="dbg-item-top">
                                        <div class="dbg-item-h"><?php echo esc_html( $it['handle'] ); ?></div>
                                        <div class="dbg-item-meta">
										<span class="dbg-pill <?php echo $it['external'] ? 'dbg-pill-ext' : 'dbg-pill-ok'; ?>">
											<?php echo $it['external'] ? 'ext' : 'local'; ?>
										</span>
											<?php if ( $is_big ) : ?>
                                                <span class="dbg-pill dbg-pill-big"><?php echo esc_html( $size_label ); ?></span>
											<?php else : ?>
                                                <span class="dbg-pill"><?php echo esc_html( $size_label ); ?></span>
											<?php endif; ?>
											<?php if ( $it['inline'] > 0 ) : ?>
                                                <span class="dbg-pill <?php echo $inline_big ? 'dbg-pill-big' : 'dbg-pill-warn'; ?>">
												inline <?php echo esc_html( wp_dbg_human_bytes( $it['inline'] ) ); ?>
											</span>
											<?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="dbg-item-s">
										<?php echo esc_html( $it['src'] ? str_replace( $home, '', $it['src'] ) : '— inline/registered' ); ?>
										<?php if ( $it['ver'] !== '' ) : ?>
                                            <span style="opacity:.55"> v<?php echo esc_html( $it['ver'] ); ?></span>
										<?php else : ?>
                                            <span style="opacity:.55"> (no ver)</span>
										<?php endif; ?>
                                    </div>
									<?php if ( ! empty( $it['deps'] ) ) : ?>
                                        <div class="dbg-item-s" style="opacity:.75">
                                            deps: <?php echo esc_html( implode( ', ', (array) $it['deps'] ) ); ?>
                                        </div>
									<?php endif; ?>
                                </div>
							<?php endforeach; ?>
						<?php endif; ?>

                    </div>

                    <!-- WEIGHTS -->
                    <div class="wp-dbg-panel" id="wp-dbg-panel-weights">

                        <div class="dbg-stats">
                            <div class="dbg-stat">
                                <div class="dbg-stat-l">JS local</div>
                                <div class="dbg-stat-v" style="color:#c4b5fd;font-size:18px;line-height:18px;">
									<?php echo esc_html( wp_dbg_human_bytes( $total_js_bytes ) ); ?>
                                </div>
                            </div>
                            <div class="dbg-stat">
                                <div class="dbg-stat-l">CSS local</div>
                                <div class="dbg-stat-v" style="color:#ddd6fe;font-size:18px;line-height:18px;">
									<?php echo esc_html( wp_dbg_human_bytes( $total_css_bytes ) ); ?>
                                </div>
                            </div>
                            <div class="dbg-stat">
                                <div class="dbg-stat-l">Images*</div>
                                <div class="dbg-stat-v" style="color:#86efac;font-size:18px;line-height:18px;">
									<?php echo esc_html( wp_dbg_human_bytes( $total_img_bytes ) ); ?>
                                </div>
                            </div>
                        </div>

                        <div class="dbg-hint">
                            <div style="font-weight:1000;margin-bottom:8px;">Что тут считается</div>
                            <div style="opacity:.85;font-weight:800;line-height:1.5;font-size:14px;">
                                • JS/CSS — только локальные файлы, которые удалось сопоставить с путём в файловой системе.<br>
                                • Inline учитывается отдельно во вкладке <b>Inline</b> (и в PSI hints).<br>
                                • Images* — только <b>&lt;img&gt;</b> из контента + featured image. Background images из CSS — см. <b>Fonts & BG</b>.
                            </div>
                        </div>

                        <div class="dbg-section">Top local JS</div>
						<?php
							$js_local = array_filter( $assets_js, function ( $it ) { return empty( $it['external'] ) && ! empty( $it['bytes'] ); } );
							usort( $js_local, function ( $a, $b ) { return (int) $b['bytes'] <=> (int) $a['bytes']; } );
							$js_top = array_slice( $js_local, 0, 10 );
						?>
						<?php if ( empty( $js_top ) ) : ?>
                            <div class="dbg-hint">Нет локальных JS для подсчёта размера</div>
						<?php else : ?>
							<?php foreach ( $js_top as $it ) : ?>
                                <div class="dbg-row">
                                    <div class="dbg-k"><?php echo esc_html( $it['handle'] ); ?></div>
                                    <div class="dbg-v">
									<span class="dbg-badge <?php echo ( $it['bytes'] >= 220 * 1024 ) ? 'dbg-rr' : 'dbg-gg'; ?>">
										<?php echo esc_html( wp_dbg_human_bytes( $it['bytes'] ) ); ?>
									</span>
                                        <span style="opacity:.6;font-weight:800;margin-left:8px;">
										<?php echo esc_html( $it['location'] ); ?>
									</span>
                                    </div>
                                </div>
							<?php endforeach; ?>
						<?php endif; ?>

                        <div class="dbg-section">Top local CSS</div>
						<?php
							$css_local = array_filter( $assets_css, function ( $it ) { return empty( $it['external'] ) && ! empty( $it['bytes'] ); } );
							usort( $css_local, function ( $a, $b ) { return (int) $b['bytes'] <=> (int) $a['bytes']; } );
							$css_top = array_slice( $css_local, 0, 10 );
						?>
						<?php if ( empty( $css_top ) ) : ?>
                            <div class="dbg-hint">Нет локальных CSS для подсчёта размера</div>
						<?php else : ?>
							<?php foreach ( $css_top as $it ) : ?>
                                <div class="dbg-row">
                                    <div class="dbg-k"><?php echo esc_html( $it['handle'] ); ?></div>
                                    <div class="dbg-v">
									<span class="dbg-badge <?php echo ( $it['bytes'] >= 140 * 1024 ) ? 'dbg-rr' : 'dbg-gg'; ?>">
										<?php echo esc_html( wp_dbg_human_bytes( $it['bytes'] ) ); ?>
									</span>
                                    </div>
                                </div>
							<?php endforeach; ?>
						<?php endif; ?>

                    </div>

                    <!-- INLINE -->
                    <div class="wp-dbg-panel" id="wp-dbg-panel-inline">

                        <div class="dbg-stats">
                            <div class="dbg-stat">
                                <div class="dbg-stat-l">Inline JS</div>
                                <div class="dbg-stat-v" style="color:<?php echo ( $total_inline_js_bytes > 40 * 1024 ) ? '#fca5a5' : '#fcd34d'; ?>;font-size:18px;line-height:18px;">
									<?php echo esc_html( wp_dbg_human_bytes( $total_inline_js_bytes ) ); ?>
                                </div>
                            </div>
                            <div class="dbg-stat">
                                <div class="dbg-stat-l">Inline CSS</div>
                                <div class="dbg-stat-v" style="color:<?php echo ( $total_inline_css_bytes > 25 * 1024 ) ? '#fca5a5' : '#fcd34d'; ?>;font-size:18px;line-height:18px;">
									<?php echo esc_html( wp_dbg_human_bytes( $total_inline_css_bytes ) ); ?>
                                </div>
                            </div>
                            <div class="dbg-stat">
                                <div class="dbg-stat-l">Duplicates src</div>
                                <div class="dbg-stat-v" style="color:<?php echo ! empty( $dup_src ) ? '#fcd34d' : '#86efac'; ?>;">
									<?php echo (int) count( $dup_src ); ?>
                                </div>
                            </div>
                        </div>

                        <div class="dbg-hint">
                            <div style="font-weight:1000;margin-bottom:8px;">Почему это важно для PSI</div>
                            <div style="opacity:.85;font-weight:800;line-height:1.5;font-size:14px;">
                                Inline не кэшируется как отдельный файл, увеличивает HTML и усложняет оптимизацию. Часто “Minimize/Reduce unused JS/CSS” начинает болеть именно из-за inline.
                            </div>
                        </div>

                        <div class="dbg-section">Top inline JS (by handle)</div>
						<?php if ( empty( $inline_js_items ) ) : ?>
                            <div class="dbg-hint">Inline JS не найден (через wp_add_inline_script)</div>
						<?php else : ?>
							<?php foreach ( array_slice( $inline_js_items, 0, 12 ) as $it ) : ?>
                                <div class="dbg-row">
                                    <div class="dbg-k"><?php echo esc_html( $it['handle'] ); ?></div>
                                    <div class="dbg-v">
									<span class="dbg-badge <?php echo ( $it['bytes'] >= 20 * 1024 ) ? 'dbg-rr' : 'dbg-yy'; ?>">
										<?php echo esc_html( wp_dbg_human_bytes( $it['bytes'] ) ); ?>
									</span>
                                        <span style="opacity:.6;font-weight:800;margin-left:8px;">
										<?php echo esc_html( $it['where'] ); ?>
									</span>
                                    </div>
                                </div>
							<?php endforeach; ?>
						<?php endif; ?>

                        <div class="dbg-section">Top inline CSS (by handle)</div>
						<?php if ( empty( $inline_css_items ) ) : ?>
                            <div class="dbg-hint">Inline CSS не найден (через wp_add_inline_style)</div>
						<?php else : ?>
							<?php foreach ( array_slice( $inline_css_items, 0, 12 ) as $it ) : ?>
                                <div class="dbg-row">
                                    <div class="dbg-k"><?php echo esc_html( $it['handle'] ); ?></div>
                                    <div class="dbg-v">
									<span class="dbg-badge <?php echo ( $it['bytes'] >= 16 * 1024 ) ? 'dbg-rr' : 'dbg-yy'; ?>">
										<?php echo esc_html( wp_dbg_human_bytes( $it['bytes'] ) ); ?>
									</span>
                                        <span style="opacity:.6;font-weight:800;margin-left:8px;">
										<?php echo esc_html( $it['where'] ); ?>
									</span>
                                    </div>
                                </div>
							<?php endforeach; ?>
						<?php endif; ?>

                        <div class="dbg-section">Duplicate src</div>
						<?php if ( empty( $dup_src ) ) : ?>
                            <div class="dbg-hint" style="border-color:rgba(34,197,94,0.22);color:#86efac;">✓ Дубликатов по src не найдено</div>
						<?php else : ?>
							<?php foreach ( $dup_src as $src => $handles ) : ?>
                                <div class="dbg-hint" style="border-color:rgba(245,158,11,0.22);">
                                    <div style="font-weight:1000;margin-bottom:6px;">src: <span style="opacity:.9"><?php echo esc_html( str_replace( $home, '', (string) $src ) ); ?></span></div>
                                    <div style="opacity:.85;font-weight:800;line-height:18px;">
										<?php echo esc_html( implode( ', ', (array) $handles ) ); ?>
                                    </div>
                                </div>
							<?php endforeach; ?>
						<?php endif; ?>

                    </div>

                    <!-- FONTS & BG -->
                    <div class="wp-dbg-panel" id="wp-dbg-panel-fonts">

                        <div class="dbg-stats">
                            <div class="dbg-stat">
                                <div class="dbg-stat-l">Fonts found*</div>
                                <div class="dbg-stat-v" style="color:#c4b5fd">
									<?php echo (int) count( $font_refs ); ?>
                                </div>
                            </div>
                            <div class="dbg-stat">
                                <div class="dbg-stat-l">Fonts local bytes*</div>
                                <div class="dbg-stat-v" style="color:<?php echo ( $font_total_bytes > 250 * 1024 ) ? '#fcd34d' : '#86efac'; ?>;font-size:18px;line-height:18px;">
									<?php echo esc_html( wp_dbg_human_bytes( $font_total_bytes ) ); ?>
                                </div>
                            </div>
                            <div class="dbg-stat">
                                <div class="dbg-stat-l">BG images found*</div>
                                <div class="dbg-stat-v" style="color:#ddd6fe">
									<?php echo (int) count( $css_bg_imgs ); ?>
                                </div>
                            </div>
                        </div>

                        <div class="dbg-hint">
                            <div style="font-weight:1000;margin-bottom:8px;">Ограничения</div>
                            <div style="opacity:.85;font-weight:800;line-height:1.5;font-size:14px;">
                                Это best-effort скан локальных CSS файлов (до 6 файлов, каждый ≤700KB).<br>
                                Если CSS огромный или генерится динамически — часть ссылок может не попасть.
                            </div>
                        </div>

                        <div class="dbg-section">Fonts (from CSS url())</div>
						<?php if ( empty( $font_refs ) ) : ?>
                            <div class="dbg-hint">Шрифты в CSS не найдены (или CSS не попал под скан)</div>
						<?php else : ?>
							<?php foreach ( array_slice( $font_refs, 0, 16 ) as $f ) :
								$is_big = ( (int) $f['bytes'] >= 80 * 1024 );
								?>
                                <div class="dbg-item">
                                    <div class="dbg-item-top">
                                        <div class="dbg-item-h">Font</div>
                                        <div class="dbg-item-meta">
										<span class="dbg-pill <?php echo ! empty( $f['ext'] ) ? 'dbg-pill-ext' : 'dbg-pill-ok'; ?>">
											<?php echo ! empty( $f['ext'] ) ? 'ext' : 'local'; ?>
										</span>
											<?php if ( ! empty( $f['bytes'] ) ) : ?>
                                                <span class="dbg-pill <?php echo $is_big ? 'dbg-pill-big' : ''; ?>">
												<?php echo esc_html( wp_dbg_human_bytes( (int) $f['bytes'] ) ); ?>
											</span>
											<?php else : ?>
                                                <span class="dbg-pill">—</span>
											<?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="dbg-item-s"><?php echo esc_html( $home ? str_replace( $home, '', (string) $f['url'] ) : (string) $f['url'] ); ?></div>
									<?php if ( ! empty( $f['from'] ) ) : ?>
                                        <div class="dbg-item-s" style="opacity:.75">from: <?php echo esc_html( implode( ', ', array_slice( array_unique( (array) $f['from'] ), 0, 6 ) ) ); ?></div>
									<?php endif; ?>
                                </div>
							<?php endforeach; ?>
						<?php endif; ?>

                        <div class="dbg-section">Background images (from CSS url())</div>
						<?php if ( empty( $css_bg_imgs ) ) : ?>
                            <div class="dbg-hint">Background images в CSS не найдены (или CSS не попал под скан)</div>
						<?php else : ?>
							<?php foreach ( array_slice( $css_bg_imgs, 0, 16 ) as $bg ) :
								$is_big = ( (int) $bg['bytes'] >= 180 * 1024 );
								?>
                                <div class="dbg-item">
                                    <div class="dbg-item-top">
                                        <div class="dbg-item-h">BG image</div>
                                        <div class="dbg-item-meta">
										<span class="dbg-pill <?php echo ! empty( $bg['ext'] ) ? 'dbg-pill-ext' : 'dbg-pill-ok'; ?>">
											<?php echo ! empty( $bg['ext'] ) ? 'ext' : 'local'; ?>
										</span>
											<?php if ( ! empty( $bg['bytes'] ) ) : ?>
                                                <span class="dbg-pill <?php echo $is_big ? 'dbg-pill-big' : ''; ?>">
												<?php echo esc_html( wp_dbg_human_bytes( (int) $bg['bytes'] ) ); ?>
											</span>
											<?php else : ?>
                                                <span class="dbg-pill">—</span>
											<?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="dbg-item-s"><?php echo esc_html( $home ? str_replace( $home, '', (string) $bg['url'] ) : (string) $bg['url'] ); ?></div>
									<?php if ( ! empty( $bg['from'] ) ) : ?>
                                        <div class="dbg-item-s" style="opacity:.75">from: <?php echo esc_html( implode( ', ', array_slice( array_unique( (array) $bg['from'] ), 0, 6 ) ) ); ?></div>
									<?php endif; ?>
                                </div>
							<?php endforeach; ?>
						<?php endif; ?>

                    </div>

                    <!-- 3RD-PARTY -->
                    <div class="wp-dbg-panel" id="wp-dbg-panel-third">

                        <div class="dbg-stats">
                            <div class="dbg-stat">
                                <div class="dbg-stat-l">3rd-party domains</div>
                                <div class="dbg-stat-v" style="color:<?php echo count( $third_party_domains_sorted ) ? '#fcd34d' : '#86efac'; ?>">
									<?php echo (int) count( $third_party_domains_sorted ); ?>
                                </div>
                            </div>
                            <div class="dbg-stat">
                                <div class="dbg-stat-l">3rd-party JS</div>
                                <div class="dbg-stat-v" style="color:#fcd34d"><?php echo (int) $total_ext_js; ?></div>
                            </div>
                            <div class="dbg-stat">
                                <div class="dbg-stat-l">3rd-party CSS</div>
                                <div class="dbg-stat-v" style="color:#fcd34d"><?php echo (int) $total_ext_css; ?></div>
                            </div>
                        </div>

						<?php if ( empty( $third_party_domains_sorted ) ) : ?>
                            <div class="dbg-hint" style="border-color:rgba(34,197,94,0.22);color:#86efac;">✓ Внешних доменов не найдено (по текущим ассетам)</div>
						<?php else : ?>
                            <div class="dbg-section">Domains</div>
							<?php foreach ( array_slice( $third_party_domains_sorted, 0, 18 ) as $d ) : ?>
                                <div class="dbg-row">
                                    <div class="dbg-k"><?php echo esc_html( $d['host'] ); ?></div>
                                    <div class="dbg-v">
                                        <span class="dbg-badge dbg-yy"><?php echo (int) $d['count']; ?> req</span>
                                    </div>
                                </div>
							<?php endforeach; ?>

                            <div class="dbg-section">Examples</div>
							<?php foreach ( array_slice( $third_party_assets, 0, 20 ) as $a ) : ?>
                                <div class="dbg-item">
                                    <div class="dbg-item-top">
                                        <div class="dbg-item-h"><?php echo esc_html( strtoupper( (string) $a['type'] ) ); ?> <?php echo $a['handle'] ? '· ' . esc_html( (string) $a['handle'] ) : ''; ?></div>
                                        <div class="dbg-item-meta">
                                            <span class="dbg-pill dbg-pill-ext"><?php echo esc_html( (string) $a['host'] ); ?></span>
                                        </div>
                                    </div>
                                    <div class="dbg-item-s"><?php echo esc_html( (string) $a['src'] ); ?></div>
                                </div>
							<?php endforeach; ?>
						<?php endif; ?>

                    </div>

                    <!-- PSI HINTS -->
                    <div class="wp-dbg-panel" id="wp-dbg-panel-psi">

                        <div class="dbg-stats">
                            <div class="dbg-stat">
                                <div class="dbg-stat-l">Requests (est.)</div>
                                <div class="dbg-stat-v" style="color:<?php echo ( $total_requests_est > 70 ) ? '#fca5a5' : ( ( $total_requests_est > 50 ) ? '#fcd34d' : '#86efac' ); ?>">
									<?php echo (int) $total_requests_est; ?>
                                </div>
                            </div>
                            <div class="dbg-stat">
                                <div class="dbg-stat-l">JS total*</div>
                                <div class="dbg-stat-v" style="color:<?php echo ( $total_js_all > 1100 * 1024 ) ? '#fca5a5' : ( ( $total_js_all > 800 * 1024 ) ? '#fcd34d' : '#c4b5fd' ); ?>;font-size:18px;line-height:18px;">
									<?php echo esc_html( wp_dbg_human_bytes( $total_js_all ) ); ?>
                                </div>
                            </div>
                            <div class="dbg-stat">
                                <div class="dbg-stat-l">CLS risk</div>
                                <div class="dbg-stat-v" style="color:<?php echo $img_missing_dims ? '#fcd34d' : '#86efac'; ?>;">
									<?php echo $img_missing_dims ? 'check' : 'ok'; ?>
                                </div>
                            </div>
                        </div>

                        <div class="dbg-hint">
                            <div style="font-weight:1000;margin-bottom:8px;">Важно</div>
                            <div style="opacity:.85;font-weight:800;line-height:1.5;font-size:14px;">
                                Это не Lighthouse. Это быстрые эвристики по текущему запросу: local sizes + inline bytes + структура ассетов.
                            </div>
                        </div>

						<?php if ( empty( $psi_warnings ) ) : ?>
                            <div class="dbg-hint" style="border-color:rgba(34,197,94,0.22);">
                                <div style="font-weight:1000;margin-bottom:6px;color:#86efac;">✓ По эвристикам — без явных красных флагов</div>
                            </div>
						<?php else : ?>
							<?php foreach ( $psi_warnings as $w ) :
								$level = isset( $w[0] ) ? (string) $w[0] : 'low';
								$text  = isset( $w[1] ) ? (string) $w[1] : '';
								$cls = 'dbg-bg';
								if ( $level === 'high' ) $cls = 'dbg-rr';
								if ( $level === 'med' )  $cls = 'dbg-yy';
								?>
                                <div class="dbg-hint">
                                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;">
									<span class="dbg-badge <?php echo esc_attr( $cls ); ?>">
										<?php echo esc_html( strtoupper( $level ) ); ?>
									</span>
                                        <div style="font-weight:1000;">Potential PSI issue</div>
                                    </div>
                                    <div style="opacity:.9;font-weight:800;line-height:1.5;font-size:14px;"><?php echo esc_html( $text ); ?></div>
                                </div>
							<?php endforeach; ?>
						<?php endif; ?>

                        <div class="dbg-section">Быстрые цели для улучшения</div>
                        <div class="dbg-hint">
                            <div style="opacity:.9;font-weight:900;line-height:1.5;font-size:14px;">
                                • Head JS без defer/strategy — в первую очередь.<br>
                                • Уменьшить total JS (vendor split / tree-shaking / убрать плагины).<br>
                                • Inline JS/CSS — вынести в файлы или сократить генерацию.<br>
                                • Картинки: размеры, форматы, dims (CLS), lazy/priority только где нужно.<br>
                                • Third-party: грузить позже или только по взаимодействию.
                            </div>
                        </div>

                    </div>

                    <!-- SQL -->
                    <div class="wp-dbg-panel" id="wp-dbg-panel-db">

                        <div class="dbg-stats">
                            <div class="dbg-stat">
                                <div class="dbg-stat-l">Queries</div>
                                <div class="dbg-stat-v" style="color:#c4b5fd"><?php echo (int) $num_queries; ?></div>
                            </div>
                            <div class="dbg-stat">
                                <div class="dbg-stat-l">Time</div>
                                <div class="dbg-stat-v" style="color:#ddd6fe;font-size:18px;line-height:18px;">
									<?php echo esc_html( $query_time ); ?>s
                                </div>
                            </div>
                            <div class="dbg-stat">
                                <div class="dbg-stat-l">Slow &gt;50ms</div>
                                <div class="dbg-stat-v" style="color:<?php echo count( $slow_queries ) ? '#fca5a5' : '#86efac'; ?>">
									<?php echo (int) count( $slow_queries ); ?>
                                </div>
                            </div>
                        </div>

						<?php if ( ! defined( 'SAVEQUERIES' ) || ! SAVEQUERIES ) : ?>
                            <div class="dbg-hint">
                                Для детального SQL-лога добавь в <span class="dbg-badge dbg-bg" style="text-transform:none;letter-spacing:0;">wp-config.php</span><br><br>
                                <span class="dbg-badge dbg-yy" style="text-transform:none;letter-spacing:0;">define('SAVEQUERIES', true);</span>
                            </div>
						<?php elseif ( empty( $slow_queries ) ) : ?>
                            <div class="dbg-hint" style="border-color:rgba(34,197,94,0.22);color:#86efac;">✓ Медленных запросов нет</div>
						<?php else : ?>
                            <div class="dbg-section"><?php echo (int) count( $slow_queries ); ?> slow queries</div>
							<?php foreach ( $slow_queries as $q ) : ?>
                                <div class="dbg-hint" style="border-color:rgba(239,68,68,0.22);">
                                    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:6px;">
                                        <span class="dbg-badge dbg-rr"><?php echo esc_html( $q['time'] ); ?></span>
                                        <span style="opacity:.7;font-weight:900;">caller</span>
                                    </div>
                                    <div style="font-size:12px;font-weight:900;opacity:.85;line-height:18px;word-break:break-word;overflow-wrap:anywhere;">
										<?php echo esc_html( mb_substr( (string) $q['sql'], 0, 700 ) ); ?>
                                    </div>
                                    <div style="margin-top:8px;font-size:11px;font-weight:800;opacity:.6;line-height:16px;word-break:break-word;overflow-wrap:anywhere;">
										<?php echo esc_html( (string) $q['caller'] ); ?>
                                    </div>
                                </div>
							<?php endforeach; ?>
						<?php endif; ?>

                    </div>

                    <!-- SERVER -->
                    <div class="wp-dbg-panel" id="wp-dbg-panel-server">

                        <div class="dbg-stats">
                            <div class="dbg-stat">
                                <div class="dbg-stat-l">Peak RAM</div>
                                <div class="dbg-stat-v" style="color:#c4b5fd;font-size:18px;line-height:18px;">
									<?php echo esc_html( (string) $memory_peak ); ?><span style="font-size:12px;opacity:.55;"> MB</span>
                                </div>
                            </div>
                            <div class="dbg-stat">
                                <div class="dbg-stat-l">Limit</div>
                                <div class="dbg-stat-v" style="color:#ddd6fe;font-size:18px;line-height:18px;">
									<?php echo esc_html( (string) $memory_limit ); ?>
                                </div>
                            </div>
                            <div class="dbg-stat">
                                <div class="dbg-stat-l">Current</div>
                                <div class="dbg-stat-v" style="color:rgba(229,231,235,0.75);font-size:18px;line-height:18px;">
									<?php echo esc_html( (string) $memory_current ); ?><span style="font-size:12px;opacity:.55;"> MB</span>
                                </div>
                            </div>
                        </div>

						<?php
							$server_rows = [
								'PHP'           => phpversion(),
								'WordPress'     => get_bloginfo( 'version' ),
								'MySQL'         => $wpdb->db_version(),
								'Server'        => isset( $_SERVER['SERVER_SOFTWARE'] ) ? (string) $_SERVER['SERVER_SOFTWARE'] : '—',
								'Max exec'      => ini_get( 'max_execution_time' ) . 's',
								'Upload max'    => ini_get( 'upload_max_filesize' ),
								'Post max'      => ini_get( 'post_max_size' ),
								'HTTPS'         => is_ssl() ? '<span class="dbg-badge dbg-gg">yes</span>' : '<span class="dbg-badge dbg-rr">no</span>',
								'DEV_MODE'      => ( defined( 'DEV_MODE' ) && DEV_MODE ) ? '<span class="dbg-badge dbg-gg">ON</span>' : '<span class="dbg-badge dbg-rr">off</span>',
								'WP_DEBUG'      => ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? '<span class="dbg-badge dbg-yy">ON</span>' : '<span class="dbg-badge dbg-rr">off</span>',
								'SAVEQUERIES'   => ( defined( 'SAVEQUERIES' ) && SAVEQUERIES ) ? '<span class="dbg-badge dbg-yy">ON</span>' : '<span class="dbg-badge dbg-rr">off</span>',
								'Multisite'     => is_multisite() ? '<span class="dbg-badge dbg-bg">yes</span>' : 'no',
								'Rewrite rules' => (string) $rules_count,
								'Cron jobs'     => (string) $cron_count,
								'OB level'      => (string) $ob_level,
								'Hooks reg.'    => (string) $hooks_count,
								'Panel collect' => (string) $debug_collect_elapsed_ms . ' ms',
								'Panel mem Δ'   => (string) $debug_collect_mem_delta_kb . ' KB',
							];

							foreach ( $server_rows as $k => $v ) :
								?>
                                <div class="dbg-row">
                                    <div class="dbg-k"><?php echo esc_html( $k ); ?></div>
                                    <div class="dbg-v"><?php echo ( is_string( $v ) && strpos( $v, '<' ) !== false ) ? $v : esc_html( (string) $v ); ?></div>
                                </div>
							<?php endforeach; ?>

						<?php if ( ! empty( $image_sizes ) ) : ?>
                            <div class="dbg-section">Image sizes</div>
                            <div class="dbg-tags">
								<?php foreach ( $image_sizes as $size ) : ?>
                                    <span class="dbg-tag"><?php echo esc_html( (string) $size ); ?></span>
								<?php endforeach; ?>
                            </div>
						<?php endif; ?>

                    </div>

                    <!-- PLUGINS -->
                    <div class="wp-dbg-panel" id="wp-dbg-panel-plugins">

                        <div class="dbg-stats">
                            <div class="dbg-stat">
                                <div class="dbg-stat-l">Active plugins</div>
                                <div class="dbg-stat-v" style="color:#c4b5fd"><?php echo (int) count( $active_plugins ); ?></div>
                            </div>
                        </div>

						<?php
							if ( empty( $active_plugins ) ) :
								?>
                                <div class="dbg-hint">No active plugins</div>
							<?php
							else :
								foreach ( $active_plugins as $plugin ) :
									$path = WP_PLUGIN_DIR . '/' . $plugin;
									$name = $plugin;
									$ver  = '';

									if ( function_exists( 'get_plugin_data' ) && file_exists( $path ) ) {
										$data = get_plugin_data( $path, false, false );
										$name = isset( $data['Name'] ) && $data['Name'] ? $data['Name'] : $plugin;
										$ver  = isset( $data['Version'] ) ? (string) $data['Version'] : '';
									}
									?>
                                    <div class="dbg-row">
                                        <div class="dbg-k"><?php echo esc_html( (string) $name ); ?></div>
                                        <div class="dbg-v" style="opacity:.8">
											<?php echo esc_html( $ver ? 'v' . $ver : '' ); ?>
                                        </div>
                                    </div>
								<?php
								endforeach;
							endif;
						?>

                    </div>

                    <!-- ACF / META -->
                    <div class="wp-dbg-panel" id="wp-dbg-panel-acf">

						<?php if ( ! empty( $acf_fields ) ) : ?>
                            <div class="dbg-section">ACF Fields (<?php echo (int) count( $acf_fields ); ?>)</div>
							<?php foreach ( $acf_fields as $field_key => $field_val ) :
								if ( is_array( $field_val ) ) {
									$display = '[array: ' . count( $field_val ) . ' items]';
								} elseif ( is_object( $field_val ) ) {
									$display = '[object]';
								} else {
									$val = (string) $field_val;
									$display = ( mb_strlen( $val ) > 140 ) ? ( mb_substr( $val, 0, 140 ) . '…' ) : $val;
								}
								?>
                                <div class="dbg-row">
                                    <div class="dbg-k"><?php echo esc_html( (string) $field_key ); ?></div>
                                    <div class="dbg-v" style="font-size:11px;font-weight:800;opacity:.75;">
										<?php echo esc_html( $display !== '' ? $display : '(empty)' ); ?>
                                    </div>
                                </div>
							<?php endforeach; ?>
						<?php else : ?>
                            <div class="dbg-hint">Нет ACF полей для этого поста</div>
						<?php endif; ?>

						<?php if ( ! empty( $meta_keys_public ) ) : ?>
                            <div class="dbg-section">Public Meta (<?php echo (int) count( $meta_keys_public ); ?>)</div>
                            <div class="dbg-tags">
								<?php foreach ( $meta_keys_public as $k ) : ?>
                                    <span class="dbg-tag"><?php echo esc_html( (string) $k ); ?></span>
								<?php endforeach; ?>
                            </div>
						<?php endif; ?>

						<?php if ( ! empty( $meta_keys_private ) ) : ?>
                            <div class="dbg-section">Private Meta (<?php echo (int) count( $meta_keys_private ); ?>)</div>
                            <div class="dbg-tags">
								<?php foreach ( $meta_keys_private as $k ) : ?>
                                    <span class="dbg-tag dbg-tag-gray"><?php echo esc_html( (string) $k ); ?></span>
								<?php endforeach; ?>
                            </div>
						<?php endif; ?>

                    </div>

                    <!-- THEME -->
                    <div class="wp-dbg-panel" id="wp-dbg-panel-theme">

						<?php
							$theme_rows = [];

							if ( $theme ) {
								$theme_rows = [
									'Name'        => (string) $theme->get( 'Name' ),
									'Version'     => (string) $theme->get( 'Version' ),
									'Author'      => (string) $theme->get( 'Author' ),
									'Text domain' => (string) $theme->get( 'TextDomain' ),
									'Status'      => is_child_theme() ? '<span class="dbg-badge dbg-bg">child</span>' : '<span class="dbg-badge dbg-bg">parent</span>',
								];

								if ( is_child_theme() ) {
									$parent = wp_get_theme( $theme->get( 'Template' ) );
									if ( $parent ) {
										$theme_rows['Parent'] = (string) $parent->get( 'Name' );
									}
								}
							}

							if ( empty( $theme_rows ) ) :
								?>
                                <div class="dbg-hint">Theme info unavailable</div>
							<?php else : ?>
								<?php foreach ( $theme_rows as $k => $v ) : ?>
                                    <div class="dbg-row">
                                        <div class="dbg-k"><?php echo esc_html( $k ); ?></div>
                                        <div class="dbg-v"><?php echo ( is_string( $v ) && strpos( $v, '<' ) !== false ) ? $v : esc_html( (string) $v ); ?></div>
                                    </div>
								<?php endforeach; ?>
							<?php endif; ?>

                    </div>

                </div>
            </div>
            <button type="button" id="wp-dbg-open" aria-label="Open debug panel"><?php echo esc_html( $template_name ); ?></button>
            <script>
                window.WP_DBG_SNAPSHOT = <?php echo $debug_snapshot_json ? $debug_snapshot_json : '{}'; ?>;
            </script>

            <script>
                (function () {
                    var panel = document.getElementById('wp-dbg-panel');
                    if (!panel) return;

                    var header = document.getElementById('wp-dbg-header');
                    var tabsWrap = document.getElementById('wp-dbg-tabs');

                    var btnMin = document.getElementById('wp-dbg-btn-min');
                    var btnClose = document.getElementById('wp-dbg-btn-close');
                    var btnOpen = document.getElementById('wp-dbg-open');
                    var btnJson = document.getElementById('wp-dbg-btn-json');

                    var LS_KEY = 'wp_dbg_panel_state_v5';
                    var COOKIE_KEY = 'sp_dbg_closed';

                    function qsAll(sel, root) {
                        return Array.prototype.slice.call((root || document).querySelectorAll(sel));
                    }

                    function setActiveTab(tab) {
                        qsAll('.wp-dbg-tab', tabsWrap).forEach(function (t) {
                            t.classList.toggle('active', t.getAttribute('data-tab') === tab);
                        });
                        qsAll('.wp-dbg-panel', panel).forEach(function (p) {
                            p.classList.toggle('active', p.id === 'wp-dbg-panel-' + tab);
                        });
                    }

                    function readState() {
                        try { return JSON.parse(localStorage.getItem(LS_KEY) || '{}') || {}; }
                        catch (e) { return {}; }
                    }

                    function writeState(next) {
                        try { localStorage.setItem(LS_KEY, JSON.stringify(next)); }
                        catch (e) {}
                    }

                    function setClosedCookie(closed) {
                        var maxAge = 60 * 60 * 24 * 365;
                        document.cookie = COOKIE_KEY + '=' + (closed ? '1' : '0') + '; path=/; max-age=' + maxAge + '; samesite=lax';
                    }

                    function readClosedCookie() {
                        var needle = COOKIE_KEY + '=';
                        var parts = String(document.cookie || '').split(';');
                        for (var i = 0; i < parts.length; i++) {
                            var c = parts[i].trim();
                            if (c.indexOf(needle) === 0) {
                                return c.substring(needle.length) === '1';
                            }
                        }
                        return null;
                    }

                    function applyState() {
                        var st = readState();

                        if (st && typeof st.x === 'number' && typeof st.y === 'number') {
                            panel.style.left = Math.max(0, st.x) + 'px';
                            panel.style.top = Math.max(0, st.y) + 'px';
                            panel.style.bottom = 'auto';
                        }

                        panel.classList.toggle('is-minimized', !!(st && st.minimized));

                        var tab = (st && st.tab) ? st.tab : 'page';
                        setActiveTab(tab);
                    }

                    function setPanelClosed(closed) {
                        var isClosed = !!closed;
                        panel.style.display = isClosed ? 'none' : 'flex';

                        if (btnOpen) {
                            btnOpen.style.display = isClosed ? 'inline-flex' : 'none';
                        }

                        var st = readState();
                        st.closed = isClosed;
                        writeState(st);
                        setClosedCookie(isClosed);
                    }

                    function toggleMinimize() {
                        panel.classList.toggle('is-minimized');
                        var st = readState();
                        st.minimized = panel.classList.contains('is-minimized');
                        writeState(st);
                    }

                    function closePanel() {
                        setPanelClosed(true);
                    }

                    function openPanel() {
                        setPanelClosed(false);
                        applyState();
                    }

                    function downloadJsonSnapshot() {
                        var snapshot = window.WP_DBG_SNAPSHOT || {};
                        var pretty = JSON.stringify(snapshot, null, 2);
                        var blob = new Blob([pretty], {type: 'application/json;charset=utf-8'});
                        var url = URL.createObjectURL(blob);
                        var ts = new Date().toISOString().replace(/[:.]/g, '-');
                        var link = document.createElement('a');
                        link.href = url;
                        link.download = 'wp-debug-' + ts + '.json';
                        document.body.appendChild(link);
                        link.click();
                        link.remove();
                        URL.revokeObjectURL(url);
                    }

                    qsAll('.wp-dbg-tab', tabsWrap).forEach(function (t) {
                        t.addEventListener('click', function () {
                            var tab = t.getAttribute('data-tab');
                            setActiveTab(tab);
                            var st = readState();
                            st.tab = tab;
                            writeState(st);
                        });

                        t.addEventListener('keydown', function (e) {
                            if (e.key === 'Enter' || e.key === ' ') {
                                e.preventDefault();
                                t.click();
                            }
                        });
                    });

                    if (btnMin) btnMin.addEventListener('click', function (e) {
                        e.preventDefault();
                        toggleMinimize();
                    });

                    if (btnClose) btnClose.addEventListener('click', function (e) {
                        e.preventDefault();
                        closePanel();
                    });

                    if (btnOpen) btnOpen.addEventListener('click', function (e) {
                        e.preventDefault();
                        openPanel();
                    });

                    if (btnJson) btnJson.addEventListener('click', function (e) {
                        e.preventDefault();
                        downloadJsonSnapshot();
                    });

                    // Drag
                    var drag = false;
                    var ox = 0, oy = 0;

                    if (header) {
                        header.addEventListener('mousedown', function (e) {
                            if (e.target && e.target.closest && e.target.closest('#wp-dbg-controls')) return;
                            drag = true;
                            var r = panel.getBoundingClientRect();
                            ox = e.clientX - r.left;
                            oy = e.clientY - r.top;
                        });
                    }

                    document.addEventListener('mousemove', function (e) {
                        if (!drag) return;

                        var x = Math.max(0, e.clientX - ox);
                        var y = Math.max(0, e.clientY - oy);

                        panel.style.left = x + 'px';
                        panel.style.top = y + 'px';
                        panel.style.bottom = 'auto';

                        var st = readState();
                        st.x = x;
                        st.y = y;
                        writeState(st);
                    });

                    document.addEventListener('mouseup', function () { drag = false; });

                    applyState();
                    var st = readState();
                    var closedByCookie = readClosedCookie();
                    setPanelClosed(closedByCookie !== null ? closedByCookie : !!(st && st.closed));

                    var currentUrl = new URL(window.location.href);
                    if (currentUrl.searchParams.has('sp_dbg_open')) {
                        currentUrl.searchParams.delete('sp_dbg_open');
                        window.history.replaceState(window.history.state || {}, '', currentUrl.toString());
                    }

                    // Hotkey: Alt + D
                    document.addEventListener('keydown', function (e) {
                        if (e.altKey && (e.key === 'd' || e.key === 'D')) {
                            e.preventDefault();
                            var st2 = readState();
                            if (st2 && st2.closed) {
                                openPanel();
                            } else {
                                closePanel();
                            }
                        }
                    });
                })();
            </script>
			<?php
		}
	}


	if ( defined( 'PRINT_TEMPLATE_NAME' ) && PRINT_TEMPLATE_NAME ) {
		add_action( 'wp_footer', 'wp_debug_panel_render', 9999 );
	}
