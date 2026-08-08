<?php
	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	if ( ! function_exists( 'sp_svg_user_can_upload' ) ) {
		function sp_svg_user_can_upload( $user = null ): bool {
			if ( $user instanceof WP_User ) {
				return user_can( $user, 'manage_options' );
			}

			return current_user_can( 'manage_options' );
		}
	}

	function allow_svg_upload( array $mimes, $user = null ): array {
		if ( ! sp_svg_user_can_upload( $user ) ) {
			unset( $mimes['svg'], $mimes['svgz'] );
			return $mimes;
		}

		$mimes['svg'] = 'image/svg+xml';
		return $mimes;
	}

	add_filter( 'upload_mimes', 'allow_svg_upload', 10, 2 );

	function fix_svg_display(): void {
		echo '<style>
			#set-post-thumbnail img {
				width: 100% !important;
				height: auto !important;
				background: #fff;
			}
			#set-post-thumbnail {
				width: 100%;
			}
		</style>';
	}

	add_action( 'admin_head', 'fix_svg_display' );

	if ( ! function_exists( 'sp_svg_is_svg_extension' ) ) {
		function sp_svg_is_svg_extension( string $filename ): bool {
			return (bool) preg_match( '~\.svgz?$~i', $filename );
		}
	}

	if ( ! function_exists( 'sp_svg_allowed_real_mime' ) ) {
		function sp_svg_allowed_real_mime( string $mime ): bool {
			$mime = strtolower( trim( $mime ) );
			if ( $mime === '' ) {
				return true;
			}

			$allowed = [
				'image/svg+xml',
				'text/plain',
				'text/xml',
				'application/xml',
				'application/octet-stream',
			];

			return in_array( $mime, $allowed, true );
		}
	}

	if ( ! function_exists( 'sp_svg_sanitize_style_attr' ) ) {
		function sp_svg_sanitize_style_attr( string $style ): string {
			$style = trim( $style );
			if ( $style === '' ) {
				return '';
			}

			if ( preg_match( '~expression\s*\(|javascript\s*:|@import~i', $style ) ) {
				return '';
			}

			if ( preg_match_all( '~url\s*\(\s*["\']?([^\)"\']+)["\']?\s*\)~i', $style, $matches ) ) {
				foreach ( $matches[1] as $target ) {
					$target = trim( $target );
					if ( $target !== '' && $target[0] !== '#' ) {
						return '';
					}
				}
			}

			return $style;
		}
	}

	if ( ! function_exists( 'sp_svg_attr_value_is_safe' ) ) {
		function sp_svg_attr_value_is_safe( string $name, string $value ): bool {
			$name  = strtolower( $name );
			$value = trim( $value );

			if ( $value === '' ) {
				return true;
			}

			if ( str_contains( strtolower( $value ), 'javascript:' ) ) {
				return false;
			}

			if ( in_array( $name, [ 'href', 'xlink:href' ], true ) ) {
				if ( $value[0] === '#' ) {
					return true;
				}

				if ( str_starts_with( $value, 'data:image/' ) ) {
					return true;
				}

				return false;
			}

			if ( in_array( $name, [ 'style', 'src' ], true ) ) {
				return false;
			}

			if ( preg_match_all( '~url\s*\(\s*["\']?([^\)"\']+)["\']?\s*\)~i', $value, $matches ) ) {
				foreach ( $matches[1] as $target ) {
					$target = trim( $target );
					if ( $target !== '' && $target[0] !== '#' ) {
						return false;
					}
				}
			}

			return true;
		}
	}

	if ( ! function_exists( 'sp_svg_sanitize_markup' ) ) {
		function sp_svg_sanitize_markup( string $markup ): string {
			$markup = trim( $markup );
			if ( $markup === '' ) {
				return '';
			}

			$markup = preg_replace( '/^\xEF\xBB\xBF/', '', $markup );
			$markup = preg_replace( '~<\?xml[^>]*\?>~i', '', $markup );
			$markup = preg_replace( '~<!DOCTYPE[^>]*>~i', '', $markup );
			$markup = preg_replace( '~<!ENTITY[^>]*>~i', '', $markup );
			$markup = trim( $markup );

			if ( $markup === '' ) {
				return '';
			}

			$libxml_prev = libxml_use_internal_errors( true );
			$dom         = new DOMDocument( '1.0', 'UTF-8' );
			$loaded      = $dom->loadXML( $markup, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT );
			libxml_clear_errors();
			libxml_use_internal_errors( $libxml_prev );

			if ( ! $loaded || ! $dom->documentElement ) {
				return '';
			}

			$root = $dom->documentElement;
			if ( strtolower( $root->tagName ) !== 'svg' ) {
				return '';
			}

			if ( ! $root->hasAttribute( 'xmlns' ) ) {
				$root->setAttribute( 'xmlns', 'http://www.w3.org/2000/svg' );
			}

			$dangerous_tags = [
				'script',
				'foreignobject',
				'iframe',
				'object',
				'embed',
				'audio',
				'video',
				'canvas',
				'link',
				'meta',
				'base',
			];

			$all_nodes = iterator_to_array( $dom->getElementsByTagName( '*' ) );
			foreach ( $all_nodes as $node ) {
				if ( ! ( $node instanceof DOMElement ) ) {
					continue;
				}

				$tag = strtolower( $node->tagName );
				if ( in_array( $tag, $dangerous_tags, true ) ) {
					if ( $node->parentNode ) {
						$node->parentNode->removeChild( $node );
					}
					continue;
				}

				if ( ! $node->hasAttributes() ) {
					continue;
				}

				$remove_attrs = [];
				foreach ( iterator_to_array( $node->attributes ) as $attr ) {
					$name  = strtolower( $attr->nodeName );
					$value = (string) $attr->nodeValue;

					if ( str_starts_with( $name, 'on' ) ) {
						$remove_attrs[] = $attr->nodeName;
						continue;
					}

					if ( $name === 'style' ) {
						$safe_style = sp_svg_sanitize_style_attr( $value );
						if ( $safe_style === '' ) {
							$remove_attrs[] = $attr->nodeName;
						} else {
							$node->setAttribute( $attr->nodeName, $safe_style );
						}
						continue;
					}

					if ( ! sp_svg_attr_value_is_safe( $name, $value ) ) {
						$remove_attrs[] = $attr->nodeName;
					}
				}

				foreach ( $remove_attrs as $remove_attr ) {
					$node->removeAttribute( $remove_attr );
				}
			}

			$sanitized = (string) $dom->saveXML( $root );
			return trim( $sanitized );
		}
	}

	if ( ! function_exists( 'sp_svg_sanitize_file' ) ) {
		function sp_svg_sanitize_file( string $file ): bool {
			if ( ! is_file( $file ) || ! is_readable( $file ) ) {
				return false;
			}

			$raw = file_get_contents( $file );
			if ( ! is_string( $raw ) || $raw === '' ) {
				return false;
			}

			$sanitized = sp_svg_sanitize_markup( $raw );
			if ( $sanitized === '' ) {
				return false;
			}

			return file_put_contents( $file, $sanitized, LOCK_EX ) !== false;
		}
	}

	if ( ! function_exists( 'sp_svg_deny_filetype' ) ) {
		function sp_svg_deny_filetype( array $data ): array {
			$data['ext']             = false;
			$data['type']            = false;
			$data['proper_filename'] = false;
			return $data;
		}
	}

	function sanitize_svg( array $data, string $file, string $filename, $mimes = null, string $real_mime = '' ): array {
		if ( ! is_array( $mimes ) ) {
			$mimes = [];
		}

		if ( ! sp_svg_is_svg_extension( $filename ) ) {
			return $data;
		}

		if ( ! sp_svg_user_can_upload() ) {
			return sp_svg_deny_filetype( $data );
		}

		if ( ! sp_svg_allowed_real_mime( $real_mime ) ) {
			return sp_svg_deny_filetype( $data );
		}

		if ( function_exists( 'finfo_open' ) && is_file( $file ) ) {
			$finfo = finfo_open( FILEINFO_MIME_TYPE );
			if ( $finfo ) {
				$mime_by_file = (string) finfo_file( $finfo, $file );
				finfo_close( $finfo );
				if ( ! sp_svg_allowed_real_mime( $mime_by_file ) ) {
					return sp_svg_deny_filetype( $data );
				}
			}
		}

		if ( ! sp_svg_sanitize_file( $file ) ) {
			return sp_svg_deny_filetype( $data );
		}

		$data['ext']  = 'svg';
		$data['type'] = 'image/svg+xml';
		return $data;
	}

	add_filter( 'wp_check_filetype_and_ext', 'sanitize_svg', 10, 5 );

	if ( ! function_exists( 'sp_svg_extract_attr' ) ) {
		function sp_svg_extract_attr( string $tag, string $name ): string {
			if ( preg_match( '~\s' . preg_quote( $name, '~' ) . '\s*=\s*(["\'])(.*?)\1~i', $tag, $match ) ) {
				return html_entity_decode( trim( (string) $match[2] ), ENT_QUOTES );
			}

			return '';
		}
	}

	if ( ! function_exists( 'sp_svg_normalize_url_key' ) ) {
		function sp_svg_normalize_url_key( string $url ): string {
			$url = trim( html_entity_decode( $url, ENT_QUOTES ) );
			if ( $url === '' ) {
				return '';
			}

			if ( str_starts_with( $url, '//' ) ) {
				$url = ( is_ssl() ? 'https:' : 'http:' ) . $url;
			}

			$parts = wp_parse_url( $url );
			if ( ! is_array( $parts ) || empty( $parts['path'] ) ) {
				return '';
			}

			$host = isset( $parts['host'] ) ? strtolower( (string) $parts['host'] ) : '';
			$path = '/' . ltrim( (string) $parts['path'], '/' );

			return $host . $path;
		}
	}

	if ( ! function_exists( 'sp_svg_ui_icons_manifest' ) ) {
		function sp_svg_ui_icons_manifest(): array {
			static $manifest = null;

			if ( $manifest !== null ) {
				return $manifest;
			}

			$manifest = [];
			if ( ! defined( 'SP_UI_ICONS_UPLOAD_DIR' ) || ! defined( 'SP_UI_ICONS_MANIFEST_FILE' ) ) {
				return $manifest;
			}

			$up       = wp_get_upload_dir();
			$base_dir = isset( $up['basedir'] ) ? (string) $up['basedir'] : '';
			if ( $base_dir === '' ) {
				return $manifest;
			}

			$file = trailingslashit( $base_dir ) . SP_UI_ICONS_UPLOAD_DIR . '/' . SP_UI_ICONS_MANIFEST_FILE;
			if ( ! is_file( $file ) || ! is_readable( $file ) ) {
				return $manifest;
			}

			$data = json_decode( (string) file_get_contents( $file ), true );
			if ( is_array( $data ) ) {
				$manifest = $data;
			}

			return $manifest;
		}
	}

	if ( ! function_exists( 'sp_svg_ui_icon_slug_from_url' ) ) {
		function sp_svg_ui_icon_slug_from_url( string $url ): string {
			static $index_by_url = null;
			static $index_by_path = null;

			if ( $index_by_url === null || $index_by_path === null ) {
				$index_by_url  = [];
				$index_by_path = [];

				$manifest = sp_svg_ui_icons_manifest();
				foreach ( $manifest as $key => $row ) {
					$slug = sanitize_title( is_string( $key ) ? $key : (string) ( $row['slug'] ?? '' ) );
					if ( $slug === '' ) {
						continue;
					}

					$candidates = [];
					if ( ! empty( $row['url'] ) ) {
						$candidates[] = (string) $row['url'];
					}

					$att_id = isset( $row['attId'] ) ? (int) $row['attId'] : 0;
					if ( $att_id > 0 ) {
						$att_url = (string) wp_get_attachment_url( $att_id );
						if ( $att_url !== '' ) {
							$candidates[] = $att_url;
						}
					}

					foreach ( $candidates as $candidate ) {
						$url_key = sp_svg_normalize_url_key( $candidate );
						if ( $url_key !== '' ) {
							$index_by_url[ $url_key ] = $slug;
						}

						$path = (string) wp_parse_url( $candidate, PHP_URL_PATH );
						if ( $path !== '' ) {
							$index_by_path[ '/' . ltrim( $path, '/' ) ] = $slug;
						}
					}
				}
			}

			$url_key = sp_svg_normalize_url_key( $url );
			if ( $url_key !== '' && isset( $index_by_url[ $url_key ] ) ) {
				return $index_by_url[ $url_key ];
			}

			$path = (string) wp_parse_url( $url, PHP_URL_PATH );
			$path = $path !== '' ? '/' . ltrim( $path, '/' ) : '';
			if ( $path !== '' && isset( $index_by_path[ $path ] ) ) {
				return $index_by_path[ $path ];
			}

			if ( ! defined( 'SP_UI_ICONS_UPLOAD_DIR' ) || ! defined( 'SP_UI_ICONS_ITEMS_DIR' ) ) {
				return '';
			}

			if ( preg_match( '~/' . preg_quote( (string) SP_UI_ICONS_UPLOAD_DIR, '~' ) . '/(?:' . preg_quote( (string) SP_UI_ICONS_ITEMS_DIR, '~' ) . '/)?([a-z0-9\-_]+)\.svg$~i', $path, $match ) ) {
				$slug = sanitize_title( (string) $match[1] );
				$manifest = sp_svg_ui_icons_manifest();
				if ( $slug !== '' && isset( $manifest[ $slug ] ) ) {
					return $slug;
				}
			}

			return '';
		}
	}

	if ( ! function_exists( 'sp_svg_sprite_href_for_ui_icon' ) ) {
		function sp_svg_sprite_href_for_ui_icon( string $svg_url ): string {
			$svg_url = trim( $svg_url );
			if ( $svg_url === '' ) {
				return '';
			}

			if ( str_contains( $svg_url, '#icon-' ) ) {
				return $svg_url;
			}

			$slug = sp_svg_ui_icon_slug_from_url( $svg_url );
			if ( $slug === '' || ! function_exists( 'sp_icons_sprite_url' ) ) {
				return '';
			}

			$sprite_url = trim( (string) sp_icons_sprite_url() );
			if ( $sprite_url === '' ) {
				return '';
			}

			return $sprite_url . '#icon-' . $slug;
		}
	}

	if ( ! function_exists( 'sp_svg_resolve_local_file' ) ) {
		function sp_svg_resolve_local_file( string $svg_url ): string {
			$svg_url = trim( html_entity_decode( $svg_url, ENT_QUOTES ) );
			if ( $svg_url === '' ) {
				return '';
			}

			$svg_url = preg_replace( '~#.*$~', '', $svg_url );
			$attachment_id = attachment_url_to_postid( $svg_url );
			if ( $attachment_id > 0 ) {
				$path = (string) get_attached_file( $attachment_id );
				if ( $path !== '' ) {
					$real = realpath( $path );
					if ( is_string( $real ) ) {
						$path = $real;
					}
					if ( is_file( $path ) && is_readable( $path ) && sp_svg_is_svg_extension( $path ) ) {
						return $path;
					}
				}
			}

			$parts = wp_parse_url( $svg_url );
			if ( ! is_array( $parts ) || empty( $parts['path'] ) ) {
				return '';
			}

			if ( ! empty( $parts['host'] ) ) {
				$home_host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
				if ( strtolower( (string) $parts['host'] ) !== strtolower( $home_host ) ) {
					return '';
				}
			}

			$path     = '/' . ltrim( (string) $parts['path'], '/' );
			$abspath  = realpath( ABSPATH );
			$candidate = $abspath ? $abspath . $path : ABSPATH . ltrim( $path, '/' );
			$real      = realpath( $candidate );

			if ( ! is_string( $real ) || ! is_file( $real ) || ! is_readable( $real ) || ! sp_svg_is_svg_extension( $real ) ) {
				return '';
			}

			if ( $abspath && ! str_starts_with( $real, $abspath ) ) {
				return '';
			}

			return $real;
		}
	}

	if ( ! function_exists( 'sp_svg_apply_inline_attrs' ) ) {
		function sp_svg_apply_inline_attrs( string $svg_markup, array $args = [] ): string {
			$svg_markup = trim( $svg_markup );
			if ( $svg_markup === '' ) {
				return '';
			}

			$libxml_prev = libxml_use_internal_errors( true );
			$dom         = new DOMDocument( '1.0', 'UTF-8' );
			$loaded      = $dom->loadXML( $svg_markup, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT );
			libxml_clear_errors();
			libxml_use_internal_errors( $libxml_prev );

			if ( ! $loaded || ! $dom->documentElement ) {
				return $svg_markup;
			}

			$svg = $dom->documentElement;
			if ( strtolower( $svg->tagName ) !== 'svg' ) {
				return $svg_markup;
			}

			$class = trim( (string) ( $args['class'] ?? '' ) );
			if ( $class !== '' ) {
				$current_class = trim( (string) $svg->getAttribute( 'class' ) );
				$svg->setAttribute( 'class', trim( $current_class . ' ' . $class ) );
			}

			$width = trim( (string) ( $args['width'] ?? '' ) );
			if ( $width !== '' ) {
				$svg->setAttribute( 'width', $width );
			}

			$height = trim( (string) ( $args['height'] ?? '' ) );
			if ( $height !== '' ) {
				$svg->setAttribute( 'height', $height );
			}

			$aria_hidden = array_key_exists( 'aria_hidden', $args ) ? (bool) $args['aria_hidden'] : true;
			if ( $aria_hidden ) {
				$svg->setAttribute( 'aria-hidden', 'true' );
				$svg->removeAttribute( 'role' );
			} else {
				$svg->setAttribute( 'role', 'img' );
			}

			$title = trim( (string) ( $args['title'] ?? '' ) );
			if ( $title !== '' ) {
				$existing_titles = $svg->getElementsByTagName( 'title' );
				if ( $existing_titles->length === 0 ) {
					$title_node = $dom->createElement( 'title', $title );
					$svg->insertBefore( $title_node, $svg->firstChild );
				}
			}

			return (string) $dom->saveXML( $svg );
		}
	}

	if ( ! function_exists( 'sp_svg_inline_markup_from_url' ) ) {
		function sp_svg_inline_markup_from_url( string $svg_url, array $args = [] ): string {
			$file = sp_svg_resolve_local_file( $svg_url );
			if ( $file === '' ) {
				return '';
			}

			$raw = file_get_contents( $file );
			if ( ! is_string( $raw ) || $raw === '' ) {
				return '';
			}

			$sanitized = sp_svg_sanitize_markup( $raw );
			if ( $sanitized === '' ) {
				return '';
			}

			return sp_svg_apply_inline_attrs( $sanitized, $args );
		}
	}

	if ( ! function_exists( 'sp_svg_build_sprite_markup' ) ) {
		function sp_svg_build_sprite_markup( string $href, array $args = [] ): string {
			$href = trim( $href );
			if ( $href === '' ) {
				return '';
			}

			$class = trim( (string) ( $args['class'] ?? 'sprite' ) );
			if ( $class === '' ) {
				$class = 'sprite';
			}

			$attrs = ' class="' . esc_attr( $class ) . '"';

			$width = trim( (string) ( $args['width'] ?? '' ) );
			if ( $width !== '' ) {
				$attrs .= ' width="' . esc_attr( $width ) . '"';
			}

			$height = trim( (string) ( $args['height'] ?? '' ) );
			if ( $height !== '' ) {
				$attrs .= ' height="' . esc_attr( $height ) . '"';
			}

			$attrs .= ' aria-hidden="true" focusable="false"';

			return '<svg' . $attrs . '><use href="' . esc_url( $href ) . '"></use></svg>';
		}
	}

	function inline_svg_processing( $buffer ) {
		if ( empty( $buffer ) || ! is_string( $buffer ) ) {
			return $buffer;
		}

		return (string) preg_replace_callback(
			'~<img\b[^>]*\bsrc=(["\'])([^"\']+\.svg(?:\?[^"\']*)?(?:#[^"\']*)?)\1[^>]*>~i',
			function ( array $match ): string {
				$img_tag = (string) $match[0];
				$svg_url = html_entity_decode( (string) $match[2], ENT_QUOTES );

				if ( trim( $svg_url ) === '' ) {
					return $img_tag;
				}

				$img_class  = sp_svg_extract_attr( $img_tag, 'class' );
				$img_width  = sp_svg_extract_attr( $img_tag, 'width' );
				$img_height = sp_svg_extract_attr( $img_tag, 'height' );
				$img_alt    = sp_svg_extract_attr( $img_tag, 'alt' );
				$svg_mode   = strtolower( trim( sp_svg_extract_attr( $img_tag, 'data-sp-svg-mode' ) ) );
				if ( ! in_array( $svg_mode, [ 'auto', 'sprite', 'inline', 'img' ], true ) ) {
					$svg_mode = 'auto';
				}

				if ( $svg_mode === 'img' ) {
					return $img_tag;
				}

				$common_class = trim( 'sprite ' . $img_class );
				$sprite_href  = sp_svg_sprite_href_for_ui_icon( $svg_url );

				if ( $svg_mode === 'sprite' ) {
					if ( $sprite_href === '' ) {
						return $img_tag;
					}

					$sprite_markup = sp_svg_build_sprite_markup( $sprite_href, [
						'class'  => $common_class,
						'width'  => $img_width,
						'height' => $img_height,
					] );

					return $sprite_markup !== '' ? $sprite_markup : $img_tag;
				}

				if ( $svg_mode === 'inline' ) {
					$inline_markup = sp_svg_inline_markup_from_url( $svg_url, [
						'class'       => $common_class,
						'width'       => $img_width,
						'height'      => $img_height,
						'aria_hidden' => $img_alt === '',
						'title'       => $img_alt,
					] );

					return $inline_markup !== '' ? $inline_markup : $img_tag;
				}

				if ( $sprite_href !== '' ) {
					$sprite_markup = sp_svg_build_sprite_markup( $sprite_href, [
						'class'  => $common_class,
						'width'  => $img_width,
						'height' => $img_height,
					] );

					return $sprite_markup !== '' ? $sprite_markup : $img_tag;
				}

				$inline_markup = sp_svg_inline_markup_from_url( $svg_url, [
					'class'       => $common_class,
					'width'       => $img_width,
					'height'      => $img_height,
					'aria_hidden' => $img_alt === '',
					'title'       => $img_alt,
				] );

				return $inline_markup !== '' ? $inline_markup : $img_tag;
			},
			$buffer
		);
	}

	function start_output_buffering(): void {
		ob_start( 'inline_svg_processing' );
	}

	add_action( 'template_redirect', 'start_output_buffering' );
