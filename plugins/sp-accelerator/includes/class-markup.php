<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Small, HTML-aware finishing pass for media hints the theme cannot add at the
 * enqueue layer. It intentionally uses the WordPress HTML tokenizer instead of
 * regular expressions and does not rewrite scripts, styles or document text.
 */
final class SP_Accelerator_Markup {
	/** @var SP_Accelerator_Config */
	private $config;

	/** @var bool */
	private $capturing = false;

	public function __construct( SP_Accelerator_Config $config ) {
		$this->config = $config;
	}

	public function register(): void {
		add_action( 'template_redirect', [ $this, 'start' ], -9000 );
	}

	public function start(): void {
		$method = strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) );
		if ( ! $this->config->enabled( 'optimize_markup' ) || ! in_array( $method, [ 'GET', 'HEAD' ], true ) || is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}
		if ( is_feed() || is_robots() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		$this->capturing = true;
		ob_start( [ $this, 'optimize' ] );
	}

	public function optimize( string $html ): string {
		if ( ! $this->capturing ) {
			return $html;
		}
		$this->capturing = false;

		if ( strlen( $html ) < 256 || stripos( $html, '</html>' ) === false || ! class_exists( 'WP_HTML_Tag_Processor' ) || $this->has_encoded_response() ) {
			return $html;
		}

		$lcp_contexts   = $this->lcp_image_contexts( $html );
		$processor      = new WP_HTML_Tag_Processor( $html );
		$main_depth     = 0;
		$noscript_depth = 0;
		$template_depth = 0;
		$picture_depth  = 0;
		$image_index    = 0;
		$contexts_valid = is_array( $lcp_contexts );
		$lcp            = null;
		$preloaded      = [];

		while ( $processor->next_tag( [ 'tag_closers' => 'visit' ] ) ) {
			$tag    = (string) $processor->get_tag();
			$closer = $processor->is_tag_closer();

			if ( $tag === 'NOSCRIPT' ) {
				$noscript_depth = max( 0, $noscript_depth + ( $closer ? -1 : 1 ) );
				continue;
			}
			if ( $tag === 'TEMPLATE' ) {
				$template_depth = max( 0, $template_depth + ( $closer ? -1 : 1 ) );
				continue;
			}
			if ( $tag === 'MAIN' ) {
				$main_depth = max( 0, $main_depth + ( $closer ? -1 : 1 ) );
				continue;
			}
			if ( $tag === 'PICTURE' ) {
				$picture_depth = max( 0, $picture_depth + ( $closer ? -1 : 1 ) );
				continue;
			}
			if ( $closer || $noscript_depth > 0 || $template_depth > 0 ) {
				continue;
			}

			if ( $tag === 'LINK' ) {
				$rel  = strtolower( (string) $processor->get_attribute( 'rel' ) );
				$as   = strtolower( (string) $processor->get_attribute( 'as' ) );
				$href = (string) $processor->get_attribute( 'href' );
				if ( strpos( $rel, 'preload' ) !== false && $as === 'image' && $href !== '' ) {
					$preloaded[ $href ] = true;
				}
				continue;
			}

			if ( $tag === 'IFRAME' && $this->config->enabled( 'lazy_embeds' ) && $processor->get_attribute( 'loading' ) === null ) {
				$processor->set_attribute( 'loading', 'lazy' );
				continue;
			}

			if ( $tag === 'VIDEO' && $this->config->enabled( 'lazy_embeds' ) && $processor->get_attribute( 'autoplay' ) === null && $processor->get_attribute( 'preload' ) === null ) {
				$processor->set_attribute( 'preload', 'none' );
				continue;
			}

			if ( $tag !== 'IMG' && $tag !== 'IMAGE' ) {
				continue;
			}

			$src = trim( (string) $processor->get_attribute( 'src' ) );
			$context = null;
			if ( $contexts_valid ) {
				// The full parser may expose images in TEMPLATE that the safe mutation
				// pass skips. Advance only to an exact source match; ambiguity therefore
				// disables optimization instead of borrowing another image's ancestry.
				while ( isset( $lcp_contexts[ $image_index ] ) && (string) ( $lcp_contexts[ $image_index ]['src'] ?? '' ) !== $src ) {
					$image_index++;
				}
				if ( isset( $lcp_contexts[ $image_index ] ) ) {
					$context = $lcp_contexts[ $image_index ];
					$image_index++;
				}
			}
			if ( ! is_array( $context ) || ! isset( $context['src'], $context['eligible'] ) || (string) $context['src'] !== $src ) {
				$contexts_valid = false;
				$context = null;
			}
			// The full parser normalizes the obsolete <image> alias to IMG. Its
			// context is consumed above solely to keep following tokens aligned.
			if ( $tag === 'IMAGE' ) {
				continue;
			}
			if ( $src === '' || strpos( $src, 'data:' ) === 0 ) {
				continue;
			}

			$width  = absint( $processor->get_attribute( 'width' ) );
			$height = absint( $processor->get_attribute( 'height' ) );
			if ( $this->config->enabled( 'add_image_dimensions' ) && ( $width < 1 || $height < 1 ) ) {
				$dimensions = $this->local_image_dimensions( $src );
				if ( $dimensions !== null ) {
					$width  = $dimensions[0];
					$height = $dimensions[1];
					$processor->set_attribute( 'width', (string) $width );
					$processor->set_attribute( 'height', (string) $height );
				}
			}

			if ( $this->config->enabled( 'async_image_decoding' ) && $processor->get_attribute( 'decoding' ) === null ) {
				$processor->set_attribute( 'decoding', 'async' );
			}

			$inside_main = $main_depth > 0;
			$class       = strtolower( (string) $processor->get_attribute( 'class' ) );
			$priority    = strtolower( (string) $processor->get_attribute( 'fetchpriority' ) );
			$loading     = strtolower( (string) $processor->get_attribute( 'loading' ) );
			$style       = strtolower( (string) $processor->get_attribute( 'style' ) );
			$aria_hidden = strtolower( (string) $processor->get_attribute( 'aria-hidden' ) );
			if ( $processor->get_attribute( 'hidden' ) !== null || $aria_hidden === 'true' || preg_match( '/(?:display\s*:\s*none|visibility\s*:\s*hidden)/', $style ) ) {
				continue;
			}

			$context_allows_lcp = $contexts_valid && is_array( $context ) && ! empty( $context['eligible'] );
			if ( $lcp === null && $inside_main && $picture_depth === 0 && $context_allows_lcp && $this->is_lcp_candidate( $src, $class, $width, $height, $priority, $loading ) ) {
				$processor->set_attribute( 'loading', 'eager' );
				$processor->set_attribute( 'fetchpriority', 'high' );
				$lcp = [
					'src'    => $src,
					'srcset' => (string) $processor->get_attribute( 'srcset' ),
					'sizes'  => (string) $processor->get_attribute( 'sizes' ),
				];
			}
		}

		$optimized = $processor->get_updated_html();
		if ( ! is_array( $lcp ) || ! $this->config->enabled( 'preload_lcp_image' ) || isset( $preloaded[ $lcp['src'] ] ) ) {
			return $optimized;
		}

		$hint = '<link rel="preload" as="image" href="' . esc_url( $lcp['src'] ) . '" fetchpriority="high"';
		if ( $lcp['srcset'] !== '' ) {
			$hint .= ' imagesrcset="' . esc_attr( $lcp['srcset'] ) . '"';
		}
		if ( $lcp['sizes'] !== '' ) {
			$hint .= ' imagesizes="' . esc_attr( $lcp['sizes'] ) . '"';
		}
		$hint .= '>' . "\n";

		$head_end = stripos( $optimized, '</head>' );
		if ( $head_end === false ) {
			return $optimized;
		}
		return substr( $optimized, 0, $head_end ) . $hint . substr( $optimized, $head_end );
	}

	/**
	 * Builds DOM-aware eligibility for each image without using the full parser
	 * for output mutation. A missing/aborted parser deliberately returns null so
	 * the tag-processor fallback keeps safe media rewrites but selects no LCP.
	 *
	 * @return array<int,array{src:string,eligible:bool}>|null
	 */
	private function lcp_image_contexts( string $html ): ?array {
		if ( ! class_exists( 'WP_HTML_Processor' ) || ! method_exists( 'WP_HTML_Processor', 'create_full_parser' ) ) {
			return null;
		}

		try {
			$processor = WP_HTML_Processor::create_full_parser( $html );
			if ( ! $processor instanceof WP_HTML_Processor ) {
				return null;
			}

			$contexts       = [];
			$unsafe_at_depth = [];
			while ( $processor->next_tag() ) {
				$breadcrumbs = $processor->get_breadcrumbs();
				$depth       = count( $breadcrumbs );
				if ( $depth < 1 ) {
					return null;
				}

				foreach ( array_keys( $unsafe_at_depth ) as $known_depth ) {
					if ( $known_depth >= $depth ) {
						unset( $unsafe_at_depth[ $known_depth ] );
					}
				}

				$parent_unsafe = ! empty( $unsafe_at_depth[ $depth - 1 ] );
				$current_unsafe = $parent_unsafe || $this->processor_element_is_hidden_or_responsive( $processor );
				$unsafe_at_depth[ $depth ] = $current_unsafe;

				if ( (string) $processor->get_tag() !== 'IMG' ) {
					continue;
				}

				$contexts[] = [
					'src'      => trim( (string) $processor->get_attribute( 'src' ) ),
					'eligible' => ! $current_unsafe
						&& in_array( 'MAIN', $breadcrumbs, true )
						&& ! in_array( 'PICTURE', $breadcrumbs, true ),
				];
			}

			if ( ( method_exists( $processor, 'get_last_error' ) && $processor->get_last_error() !== null )
				|| ( method_exists( $processor, 'paused_at_incomplete_token' ) && $processor->paused_at_incomplete_token() ) ) {
				return null;
			}

			return $contexts;
		} catch ( Throwable $error ) {
			return null;
		}
	}

	/** @param WP_HTML_Processor $processor */
	private function processor_element_is_hidden_or_responsive( $processor ): bool {
		$tag         = (string) $processor->get_tag();
		$class       = strtolower( (string) $processor->get_attribute( 'class' ) );
		$style       = strtolower( (string) $processor->get_attribute( 'style' ) );
		$aria_hidden = strtolower( trim( (string) $processor->get_attribute( 'aria-hidden' ) ) );

		if ( $processor->get_attribute( 'hidden' ) !== null || $aria_hidden === 'true' ) {
			return true;
		}
		if ( $tag === 'TEMPLATE' || $tag === 'NOSCRIPT' ) {
			return true;
		}
		if ( ( $tag === 'DETAILS' || $tag === 'DIALOG' ) && $processor->get_attribute( 'open' ) === null ) {
			return true;
		}
		if ( preg_match( '/(?:display\s*:\s*none|visibility\s*:\s*(?:hidden|collapse)|content-visibility\s*:\s*hidden)/', $style ) ) {
			return true;
		}

		return $this->class_is_hidden_or_responsive( $class );
	}

	private function class_is_hidden_or_responsive( string $class ): bool {
		$tokens = preg_split( '/\s+/', trim( strtolower( $class ) ) ) ?: [];
		foreach ( $tokens as $token ) {
			if ( $token === '' ) {
				continue;
			}
			if ( in_array( $token, [ 'hidden', 'hide', 'invisible', 'is-hidden', 'uk-hidden', 'u-hidden', 'd-none', 'visually-hidden', 'sr-only', 'screen-reader-text', 'desktop', 'tablet', 'mobile', 'mob', 'desktop-only', 'tablet-only', 'mobile-only', 'is-desktop', 'is-tablet', 'is-mobile' ], true ) ) {
				return true;
			}
			if ( preg_match( '/^(?:elementor-hidden-(?:desktop|tablet|mobile)|(?:show|hide)-(?:for|on)-(?:small|medium|large|desktop|tablet|mobile).*)$/', $token ) ) {
				return true;
			}
			$has_viewport = preg_match( '/(?:^|[-_:])(?:xs|sm|md|lg|xl|xxl|2xl|3xl|small|medium|large|phone|mobile|tablet|desktop)(?:$|[-_:])/', $token );
			$conditional  = preg_match( '/(?:^|[-_:])(?:hidden|hide|show|visible|only|none|block|flex|grid|inline|table)(?:$|[-_:])/', $token );
			if ( $has_viewport && $conditional ) {
				return true;
			}
		}

		return false;
	}

	private function is_lcp_candidate( string $src, string $class, int $width, int $height, string $priority, string $loading ): bool {
		$path = strtolower( (string) wp_parse_url( $src, PHP_URL_PATH ) );
		if ( $priority === 'low' || $loading === 'lazy' || $this->class_is_hidden_or_responsive( $class ) ) {
			return false;
		}
		if ( preg_match( '/\.(?:svg|ico)(?:$|[?#])/', $path ) || preg_match( '/(?:^|[-_\s])(logo|icon|sprite|avatar|emoji|badge)(?:$|[-_\s])/', $class ) ) {
			return false;
		}
		if ( $priority === 'high' || $loading === 'eager' ) {
			return true;
		}
		if ( preg_match( '/(?:hero|banner|masthead|featured|cover|intro)/', $class . ' ' . $path ) ) {
			return true;
		}
		return $width >= 480 && $height >= 240 && $width * $height >= 150000;
	}

	/** @return array{0:int,1:int}|null */
	private function local_image_dimensions( string $url ): ?array {
		static $cache = [];
		if ( array_key_exists( $url, $cache ) ) {
			return $cache[ $url ];
		}

		$parts     = wp_parse_url( $url );
		$home      = wp_parse_url( home_url( '/' ) );
		$url_host  = is_array( $parts ) ? strtolower( (string) ( $parts['host'] ?? '' ) ) : '';
		$home_host = is_array( $home ) ? strtolower( (string) ( $home['host'] ?? '' ) ) : '';
		if ( $url_host !== '' && $home_host !== '' && $url_host !== $home_host ) {
			$cache[ $url ] = null;
			return null;
		}

		$path      = is_array( $parts ) ? rawurldecode( (string) ( $parts['path'] ?? '' ) ) : '';
		$home_path = is_array( $home ) ? rtrim( (string) ( $home['path'] ?? '' ), '/' ) : '';
		if ( $home_path !== '' && strpos( $path, $home_path . '/' ) === 0 ) {
			$path = substr( $path, strlen( $home_path ) );
		}
		if ( $path === '' || preg_match( '/\.(?:svg|ico)$/i', $path ) ) {
			$cache[ $url ] = null;
			return null;
		}

		$root = realpath( ABSPATH );
		$file = $root !== false ? realpath( rtrim( $root, '/\\' ) . '/' . ltrim( $path, '/' ) ) : false;
		if ( $root === false || $file === false || strpos( $file, rtrim( $root, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR ) !== 0 || ! is_file( $file ) ) {
			$cache[ $url ] = null;
			return null;
		}

		$size = filesize( $file );
		if ( $size === false || $size > 20 * MB_IN_BYTES ) {
			$cache[ $url ] = null;
			return null;
		}
		$image = @getimagesize( $file );
		if ( ! is_array( $image ) || empty( $image[0] ) || empty( $image[1] ) ) {
			$cache[ $url ] = null;
			return null;
		}

		$cache[ $url ] = [ (int) $image[0], (int) $image[1] ];
		return $cache[ $url ];
	}

	private function has_encoded_response(): bool {
		foreach ( headers_list() as $line ) {
			if ( stripos( (string) $line, 'content-encoding:' ) === 0 && stripos( (string) $line, 'identity' ) === false ) {
				return true;
			}
		}
		return false;
	}
}
