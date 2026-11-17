<?php
	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/*
	|------------------------------------------------------------------------------
	| Generic attachment media renderer
	|------------------------------------------------------------------------------
	|
	| Main function:
	| - display_media($media, $width, $height, $class = '', $loading = 'lazy')
	|
	| Supported $media formats:
	| - ACF array: ['ID' => 123, 'url' => '...', 'alt' => '...']
	| - Attachment ID: 123
	| - URL: 'https://site.com/wp-content/uploads/.../file.mp4'
	| - WP_Post attachment object
	|
	| Type detection:
	| - by MIME (video/*, image/*)
	| - by array 'type' (video/image)
	| - by URL extension fallback (mp4/webm/... or jpg/png/...)
	|
	| Examples:
	|
	| 1) Echo directly (recommended):
	|    <?php display_media($item, 1920, 1080, 'hero-media', 'eager'); ?>
	|
	| 2) Get HTML string:
	|    $html = get_media_html($item, 800, 450, 'card-media');
	|    echo $html;
	|
	| 3) Backward compatibility (old API):
	|    <?php display_image($item, 438, 267, 'blog-card__image'); ?>
	|
	| 4) Video options via array:
	|    $item['video_attrs'] = [
	|      'autoplay' => false,
	|      'controls' => true,
	|      'preload'  => 'metadata',
	|    ];
	|    display_media($item, 1280, 720, 'gallery__item');
	|
	| 5) Image attrs override:
	|    $item['img_attrs'] = ['class' => 'my-img', 'data-id' => '42'];
	|    display_media($item, 600, 400);
	|
	| 6) SVG render mode:
	|    $item['svg_mode'] = 'img'; // auto|sprite|inline|img
	|    display_media($item, 600, 400);
	|
	*/

	if ( ! function_exists( 'display_media_bool' ) ) {
		function display_media_bool( $value, bool $default ): bool {
			if ( is_bool( $value ) ) {
				return $value;
			}

			if ( is_numeric( $value ) ) {
				return ( (int) $value ) !== 0;
			}

			if ( is_string( $value ) ) {
				$norm = strtolower( trim( $value ) );
				if ( in_array( $norm, [ '1', 'true', 'yes', 'on' ], true ) ) {
					return true;
				}
				if ( in_array( $norm, [ '0', 'false', 'no', 'off', '' ], true ) ) {
					return false;
				}
			}

			return $default;
		}
	}

	if ( ! function_exists( 'display_media_preload' ) ) {
		function display_media_preload( $value, string $default = 'auto' ): string {
			$norm = strtolower( trim( (string) $value ) );
			return in_array( $norm, [ 'auto', 'metadata', 'none' ], true ) ? $norm : $default;
		}
	}

	if ( ! function_exists( 'display_media_format_attrs' ) ) {
		function display_media_format_attrs( array $attrs ): string {
			$out = '';

			foreach ( $attrs as $name => $value ) {
				$attr_name = trim( (string) $name );
				if ( $attr_name === '' ) {
					continue;
				}

				if ( $value === null || $value === false ) {
					continue;
				}

				if ( $value === true ) {
					$out .= ' ' . esc_attr( $attr_name );
					continue;
				}

				$str = (string) $value;
				if ( $str === '' && $attr_name !== 'alt' ) {
					continue;
				}

				$escaped = in_array( $attr_name, [ 'src', 'poster' ], true ) ? esc_url( $str ) : esc_attr( $str );
				$out .= ' ' . esc_attr( $attr_name ) . '="' . $escaped . '"';
			}

			return $out;
		}
	}

	if ( ! function_exists( 'resolve_sizes_attr' ) ) {
		function resolve_sizes_attr( array $image_array ): string {
			$default_sizes = (string) apply_filters( 'display_media_default_sizes', '100vw', $image_array );

			if ( isset( $image_array['sizes_attr'] ) && is_string( $image_array['sizes_attr'] ) ) {
				$sizes_attr = trim( $image_array['sizes_attr'] );
				if ( $sizes_attr !== '' ) {
					return $sizes_attr;
				}
			}

			// If "sizes" comes from ACF image array, it is usually an array of image URLs.
			if ( isset( $image_array['sizes'] ) && is_string( $image_array['sizes'] ) ) {
				$sizes_attr = trim( $image_array['sizes'] );
				if ( $sizes_attr !== '' ) {
					return $sizes_attr;
				}
			}

			return $default_sizes;
		}
	}

	if ( ! function_exists( 'normalize_media_input' ) ) {
		function normalize_media_input( $media ): array {
			if ( is_array( $media ) ) {
				return $media;
			}

			if ( $media instanceof WP_Post ) {
				$id = (int) $media->ID;
				return [
					'ID'  => $id,
					'url' => (string) wp_get_attachment_url( $id ),
				];
			}

			if ( is_numeric( $media ) ) {
				$id = (int) $media;
				return [
					'ID'  => $id,
					'url' => (string) wp_get_attachment_url( $id ),
				];
			}

			if ( is_string( $media ) && trim( $media ) !== '' ) {
				$url = trim( $media );
				$id  = (int) attachment_url_to_postid( $url );
				return [
					'ID'  => $id,
					'url' => $url,
				];
			}

			return [];
		}
	}

	if ( ! function_exists( 'resolve_display_media_data' ) ) {
		function resolve_display_media_data( $media, int $target_width, int $target_height, string $loading = 'lazy' ): ?array {
			$image_array = normalize_media_input( $media );
			if ( empty( $image_array ) || ! is_array( $image_array ) ) {
				return null;
			}

			$image_id   = isset( $image_array['ID'] ) ? (int) $image_array['ID'] : 0;
			$passed_url = isset( $image_array['url'] ) ? (string) $image_array['url'] : '';

			$attachment_url = $image_id > 0 ? (string) wp_get_attachment_url( $image_id ) : '';
			$media_url      = $passed_url !== '' ? $passed_url : $attachment_url;
			if ( $media_url === '' ) {
				return null;
			}

			$alt_from_array = isset( $image_array['alt'] ) ? (string) $image_array['alt'] : '';
			$alt_meta       = $image_id > 0 ? (string) get_post_meta( $image_id, '_wp_attachment_image_alt', true ) : '';
			$alt_title      = $image_id > 0 ? (string) get_the_title( $image_id ) : '';
			$image_alt      = $alt_from_array !== '' ? $alt_from_array : ( $alt_meta !== '' ? $alt_meta : $alt_title );

			$mime = $image_id > 0 ? (string) ( get_post_mime_type( $image_id ) ?: '' ) : '';
			if ( $mime === '' && ! empty( $image_array['mime_type'] ) ) {
				$mime = (string) $image_array['mime_type'];
			}
			if ( $mime === '' && ! empty( $image_array['mime'] ) ) {
				$mime = (string) $image_array['mime'];
			}

			$main_type = '';
			if ( $mime !== '' ) {
				$main_type = (string) strtok( $mime, '/' );
			}
			if ( $main_type === '' && ! empty( $image_array['type'] ) ) {
				$type = strtolower( trim( (string) $image_array['type'] ) );
				if ( in_array( $type, [ 'image', 'video' ], true ) ) {
					$main_type = $type;
				}
			}
			$path_for_ext = (string) wp_parse_url( $media_url, PHP_URL_PATH );
			$ext          = strtolower( pathinfo( $path_for_ext, PATHINFO_EXTENSION ) );

			if ( $main_type === '' ) {
				if ( in_array( $ext, [ 'mp4', 'webm', 'ogg', 'mov', 'm4v' ], true ) ) {
					$main_type = 'video';
				} elseif ( in_array( $ext, [ 'jpg', 'jpeg', 'png', 'webp', 'gif', 'avif', 'svg' ], true ) ) {
					$main_type = 'image';
				}
			}

			$is_svg = false;
			if ( $main_type === 'image' ) {
				$mime_lc = strtolower( $mime );
				$is_svg  = $mime_lc === 'image/svg+xml' || $ext === 'svg';
			}

			$loading_norm  = $loading === 'eager' ? 'eager' : 'lazy';
			$fetchpriority = $loading_norm === 'eager' ? 'high' : 'auto';

			$data = [
				'id'            => $image_id,
				'url'           => $media_url,
				'mime'          => $mime,
				'main_type'     => $main_type,
				'alt'           => $image_alt,
				'target_width'  => $target_width,
				'target_height' => $target_height,
				'loading'       => $loading_norm,
				'fetchpriority' => $fetchpriority,
				'is_svg'        => $is_svg,
			];

			return apply_filters( 'display_media_data', $data, $image_array );
		}
	}

	if ( ! function_exists( 'resolve_video_poster_url' ) ) {
		function resolve_video_poster_url( int $image_id, array $image_array, string $size = 'large' ): string {
			if ( ! empty( $image_array['poster'] ) ) {
				return esc_url( (string) $image_array['poster'] );
			}

			$poster = '';
			if ( $image_id > 0 && function_exists( 'sp_video_poster_url' ) ) {
				$poster = (string) sp_video_poster_url( $image_id, $size );
			}

			if ( $poster === '' && $image_id > 0 ) {
				$poster_id = (int) get_post_meta( $image_id, '_sp_video_poster_id', true );
				if ( $poster_id <= 0 ) {
					$poster_id = (int) get_post_meta( $image_id, '_thumbnail_id', true );
				}

				if ( $poster_id > 0 ) {
					$poster = (string) wp_get_attachment_image_url( $poster_id, $size );
				}
			}

			$poster = apply_filters( 'display_media_poster', $poster, $image_id, $image_array, $size );
			return esc_url( (string) $poster );
		}
	}

	if ( ! function_exists( 'resolve_image_source' ) ) {
		function resolve_image_source( int $image_id, int $target_width, int $target_height, string $fallback_url = '' ): array {
			static $cache = [];

			$cache_key = $image_id . '|' . $target_width . 'x' . $target_height . '|' . $fallback_url;
			if ( isset( $cache[ $cache_key ] ) && is_array( $cache[ $cache_key ] ) ) {
				return $cache[ $cache_key ];
			}

			$best_match   = 'full';
			$image_url    = '';
			$image_width  = 0;
			$image_height = 0;
			$img_data     = false;

			$meta = $image_id > 0 ? wp_get_attachment_metadata( $image_id ) : false;
			if ( is_array( $meta ) && ! empty( $meta['sizes'] ) && $target_width > 0 && $target_height > 0 ) {
				$min_diff = PHP_INT_MAX;
				foreach ( $meta['sizes'] as $name => $size_data ) {
					if ( ! isset( $size_data['width'], $size_data['height'] ) ) {
						continue;
					}

					$w    = (int) $size_data['width'];
					$h    = (int) $size_data['height'];
					$diff = abs( $w - $target_width ) + abs( $h - $target_height );

					if ( $diff < $min_diff ) {
						$min_diff   = $diff;
						$best_match = (string) $name;
					}
				}

				$img_data = wp_get_attachment_image_src( $image_id, $best_match );
			}

			if ( ! $img_data ) {
				$best_match = 'full';
				$img_data   = $image_id > 0 ? wp_get_attachment_image_src( $image_id, 'full' ) : false;
			}

			if ( $img_data ) {
				$image_url    = (string) $img_data[0];
				$image_width  = isset( $img_data[1] ) ? (int) $img_data[1] : 0;
				$image_height = isset( $img_data[2] ) ? (int) $img_data[2] : 0;
			} else {
				$image_url    = $fallback_url;
				$image_width  = $target_width > 0 ? $target_width : 0;
				$image_height = $target_height > 0 ? $target_height : 0;
			}

			$srcset = $image_id > 0 ? (string) wp_get_attachment_image_srcset( $image_id, $best_match ) : '';

			$cache[ $cache_key ] = [
				'url'    => $image_url,
				'width'  => $image_width,
				'height' => $image_height,
				'size'   => $best_match,
				'srcset' => $srcset,
			];

			return $cache[ $cache_key ];
		}
	}

	if ( ! function_exists( 'render_video_media_html' ) ) {
		function render_video_media_html( array $data, array $image_array ): string {
			$video_attrs = isset( $image_array['video_attrs'] ) && is_array( $image_array['video_attrs'] ) ? $image_array['video_attrs'] : [];

			$video_id = isset( $data['id'] ) ? (int) $data['id'] : 0;

			if ( $video_id > 0 ) {
				$meta_controls = get_post_meta( $video_id, '_sp_video_controls', true );
				$meta_muted    = get_post_meta( $video_id, '_sp_video_muted', true );
				$meta_autoplay = get_post_meta( $video_id, '_sp_video_autoplay', true );
				$meta_loop     = get_post_meta( $video_id, '_sp_video_loop', true );

				if ( $meta_controls !== '' ) {
					$image_array['video_controls'] = (bool) (int) $meta_controls;
				}

				if ( $meta_muted !== '' ) {
					$image_array['video_muted'] = (bool) (int) $meta_muted;
				}

				if ( $meta_autoplay !== '' ) {
					$image_array['video_autoplay'] = (bool) (int) $meta_autoplay;
				}

				if ( $meta_loop !== '' ) {
					$image_array['video_loop'] = (bool) (int) $meta_loop;
				}
			}

			$attrs = [
				'autoplay'      => display_media_bool( $video_attrs['autoplay'] ?? ( $image_array['video_autoplay'] ?? true ), true ),
				'loop'          => display_media_bool( $video_attrs['loop'] ?? ( $image_array['video_loop'] ?? true ), true ),
				'muted'         => display_media_bool( $video_attrs['muted'] ?? ( $image_array['video_muted'] ?? true ), true ),
				'playsinline'   => display_media_bool( $video_attrs['playsinline'] ?? ( $image_array['video_playsinline'] ?? true ), true ),
				'controls'      => display_media_bool( $video_attrs['controls'] ?? ( $image_array['video_controls'] ?? false ), false ),
				'preload'       => display_media_preload( $video_attrs['preload'] ?? ( $image_array['video_preload'] ?? 'auto' ), 'auto' ),
				'fetchpriority' => (string) ( $data['fetchpriority'] ?? 'auto' ), // fix: pass fetchpriority=high for eager LCP videos
				'style'         => isset( $video_attrs['style'] ) ? (string) $video_attrs['style'] : 'object-fit:cover;display:block;width:100%;',
			];

			if ( ! empty( $video_attrs['class'] ) ) {
				$attrs['class'] = (string) $video_attrs['class'];
			}

			$poster = resolve_video_poster_url( (int) $data['id'], $image_array, 'large' );
			if ( $poster !== '' ) {
				$attrs['poster'] = $poster;
			}

			$attrs = apply_filters( 'display_media_attrs', $attrs, $data, 'video', $image_array );
			$html  = '<video' . display_media_format_attrs( $attrs ) . '>'
			         . '<source src="' . esc_url( (string) $data['url'] ) . '"' . ( $data['mime'] !== '' ? ' type="' . esc_attr( (string) $data['mime'] ) . '"' : '' ) . '>'
			         . 'Your browser does not support video.'
			         . '</video>';

			return (string) apply_filters( 'display_media_html', $html, $data, 'video', $image_array );
		}
	}

	if ( ! function_exists( 'render_image_media_html' ) ) {
		function render_image_media_html( array $data, array $image_array ): string {
			$src = resolve_image_source(
				(int) $data['id'],
				(int) $data['target_width'],
				(int) $data['target_height'],
				(string) $data['url']
			);

			$attrs = [
				'src'           => (string) $src['url'],
				'alt'           => (string) $data['alt'],
				'sizes'         => resolve_sizes_attr( $image_array ),
				'loading'       => (string) $data['loading'],
				'decoding'      => 'async',
				'fetchpriority' => (string) $data['fetchpriority'],
			];

			if ( ! empty( $data['is_svg'] ) ) {
				$svg_mode_raw = strtolower( trim( (string) ( $image_array['svg_mode'] ?? ( $image_array['svg_render'] ?? 'auto' ) ) ) );
				$svg_mode     = in_array( $svg_mode_raw, [ 'auto', 'sprite', 'inline', 'img' ], true ) ? $svg_mode_raw : 'auto';
				$attrs['data-sp-svg-mode'] = $svg_mode;
			}

			if ( ! empty( $src['srcset'] ) ) {
				$attrs['srcset'] = (string) $src['srcset'];
			}
			if ( ! empty( $src['width'] ) ) {
				$attrs['width'] = (int) $src['width'];
			}
			if ( ! empty( $src['height'] ) ) {
				$attrs['height'] = (int) $src['height'];
			}

			if ( isset( $image_array['img_attrs'] ) && is_array( $image_array['img_attrs'] ) ) {
				foreach ( $image_array['img_attrs'] as $name => $value ) {
					$attrs[ (string) $name ] = $value;
				}
			}

			$attrs = apply_filters( 'display_media_attrs', $attrs, $data, 'image', $image_array );
			$html  = '<img' . display_media_format_attrs( $attrs ) . ' />';

			return (string) apply_filters( 'display_media_html', $html, $data, 'image', $image_array );
		}
	}

	if ( ! function_exists( 'get_media_html' ) ) {
		// Returns ready-to-use HTML string for image/video based on attachment data.
		function get_media_html( $media, int $target_width, int $target_height, string $class = '', string $loading = 'lazy' ): string {
			$image_array = normalize_media_input( $media );
			$data        = resolve_display_media_data( $image_array, $target_width, $target_height, $loading );
			if ( ! is_array( $data ) || empty( $data['url'] ) || esc_url( (string) $data['url'] ) === '' ) {
				return '';
			}

			$html = ( (string) ( $data['main_type'] ?? '' ) === 'video' )
				? render_video_media_html( $data, is_array( $image_array ) ? $image_array : [] )
				: render_image_media_html( $data, is_array( $image_array ) ? $image_array : [] );

			$class = trim( $class );
			if ( $class !== '' ) {
				$html = '<div class="' . esc_attr( $class ) . '">' . $html . '</div>';
			}

			return (string) apply_filters( 'display_media_output', $html, $data, $image_array );
		}
	}

	if ( ! function_exists( 'display_media' ) ) {
		// Echo helper for templates.
		function display_media( $media, int $target_width, int $target_height, string $class = '', string $loading = 'lazy' ): void {
			echo get_media_html( $media, $target_width, $target_height, $class, $loading );
		}
	}

	if ( ! function_exists( 'display_image' ) ) {
		// Legacy alias: kept for existing template calls.
		function display_image( $image_array, int $target_width, int $target_height, string $class = '', string $loading = 'lazy' ): void {
			display_media( $image_array, $target_width, $target_height, $class, $loading );
		}
	}

	if ( function_exists( 'display_media' ) && ! has_action( 'display_media', 'display_media' ) ) {
		add_action( 'display_media', 'display_media', 5, 10 );
	}

	if ( function_exists( 'display_image' ) && ! has_action( 'display_image', 'display_image' ) ) {
		add_action( 'display_image', 'display_image', 5, 10 );
	}

	if ( ! function_exists( 'sp_get_post_thumbnail_data' ) ) {
		/**
		 * Returns featured image data in ACF-like array format.
		 */
		function sp_get_post_thumbnail_data( int $post_id = 0, $size = 'large' ): ?array {
			$post_id = $post_id ?: (int) get_the_ID();
			if ( $post_id <= 0 ) {
				return null;
			}

			$attachment_id = (int) get_post_thumbnail_id( $post_id );
			if ( $attachment_id <= 0 ) {
				return null;
			}

			$src = wp_get_attachment_image_src( $attachment_id, $size );
			if ( ! is_array( $src ) || empty( $src[0] ) ) {
				return null;
			}

			return [
				'ID'     => $attachment_id,
				'url'    => (string) $src[0],
				'width'  => (int) ( $src[1] ?? 0 ),
				'height' => (int) ( $src[2] ?? 0 ),
				'alt'    => (string) ( get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ?: '' ),
			];
		}
	}

	if ( ! function_exists( 'get_thumb' ) ) {
		/**
		 * Backward-compatible alias.
		 */
		function get_thumb( int $post_id = 0, $size = 'large' ): ?array {
			return sp_get_post_thumbnail_data( $post_id, $size );
		}
	}
