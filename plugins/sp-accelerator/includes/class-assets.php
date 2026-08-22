<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SP_Accelerator_Assets {
	/** @var SP_Accelerator_Config */
	private $config;

	public function __construct( SP_Accelerator_Config $config ) {
		$this->config = $config;
	}

	public function register(): void {
		add_filter( 'theme_preload_fonts', [ $this, 'limit_font_preloads' ], 20 );
		add_filter( 'theme_script_preload_files', [ $this, 'preload_main_script' ], 20, 2 );
		add_filter( 'theme_async_style_handles', [ $this, 'async_section_styles' ], 20 );
		add_filter( 'script_loader_tag', [ $this, 'delay_noncritical_theme_scripts' ], 20, 2 );
		add_filter( 'wp_get_attachment_image_attributes', [ $this, 'image_attributes' ], 20 );
		add_filter( 'wp_resource_hints', [ $this, 'resource_hints' ], 20, 2 );
		add_action( 'wp_head', [ $this, 'print_script_loader' ], 6 );
	}

	public function asset_version( string $relative_path ): string {
		$relative_path = ltrim( str_replace( '\\', '/', $relative_path ), '/' );
		if ( $relative_path === '' || ! defined( 'THEME_DIR' ) ) {
			return defined( '_S_VERSION' ) ? (string) _S_VERSION : SP_Accelerator_Config::VERSION;
		}

		$path = rtrim( THEME_DIR, '/\\' ) . '/' . $relative_path;
		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			return defined( '_S_VERSION' ) ? (string) _S_VERSION : SP_Accelerator_Config::VERSION;
		}

		$mtime = filemtime( $path );
		$size  = filesize( $path );
		return (string) ( ( $mtime !== false ? $mtime : 0 ) . '-' . ( $size !== false ? $size : 0 ) );
	}

	/** @param string[] $fonts @return string[] */
	public function limit_font_preloads( array $fonts ): array {
		if ( ! $this->config->enabled( 'limit_font_preloads' ) ) {
			return $fonts;
		}

		$limit     = min( 6, max( 1, (int) $this->config->get( 'font_preload_limit', 2 ) ) );
		$available = [];
		foreach ( $fonts as $font ) {
			$font = ltrim( str_replace( '\\', '/', (string) $font ), '/' );
			if ( $font !== '' && preg_match( '/\.woff2$/i', $font ) ) {
				$available[ $font ] = $font;
			}
		}
		$available = array_values( $available );

		$automatic = $this->automatic_font_preloads( $available, $limit );
		$critical  = apply_filters( 'sp_accelerator_preload_fonts', $automatic, $available, $limit );
		$critical  = is_array( $critical ) ? $critical : [];
		$available = array_flip( $available );

		$selected = [];
		foreach ( $critical as $font ) {
			$font = ltrim( str_replace( '\\', '/', (string) $font ), '/' );
			if ( isset( $available[ $font ] ) ) {
				$selected[ $font ] = $font;
			}
		}

		return array_slice( array_values( $selected ), 0, $limit );
	}

	/**
	 * Pick regular, non-italic text faces from different families before using
	 * secondary weights from the same family. Icon fonts are never preloaded.
	 *
	 * @param string[] $fonts
	 * @return string[]
	 */
	private function automatic_font_preloads( array $fonts, int $limit ): array {
		$candidates = [];
		foreach ( $fonts as $font ) {
			$filename = pathinfo( $font, PATHINFO_FILENAME );
			if ( preg_match( '/(?:^|[-_.\s])(?:icon|icons|icomoon|symbol|glyph)(?:$|[-_.\s])/i', $filename ) ) {
				continue;
			}

			$italic   = (bool) preg_match( '/(?:italic|oblique)/i', $filename );
			$regular  = (bool) preg_match( '/(?:^|[-_.\s])(?:regular|book|roman|normal|400)(?:$|[-_.\s])/i', $filename );
			$variable = (bool) preg_match( '/(?:variable|\[[^\]]*wght)/i', $filename );
			$medium   = (bool) preg_match( '/(?:^|[-_.\s])(?:medium|500)(?:$|[-_.\s])/i', $filename );
			$bold     = (bool) preg_match( '/(?:^|[-_.\s])(?:semibold|demibold|bold|600|700)(?:$|[-_.\s])/i', $filename );
			$light    = (bool) preg_match( '/(?:^|[-_.\s])(?:thin|extralight|ultralight|light|100|200|300)(?:$|[-_.\s])/i', $filename );

			$rank = $regular ? 0 : ( $variable ? 10 : ( $medium ? 20 : ( $bold ? 30 : ( $light ? 40 : 25 ) ) ) );
			if ( $italic ) {
				$rank += 50;
			}

			$tokens = preg_split( '/[-_.\s]+/', strtolower( $filename ) ) ?: [];
			$style_tokens = [
				'thin', 'extralight', 'ultralight', 'light', 'regular', 'book', 'roman', 'normal',
				'medium', 'semibold', 'demibold', 'bold', 'extrabold', 'ultrabold', 'black', 'heavy',
				'italic', 'oblique', 'variable', '100', '200', '300', '400', '500', '600', '700', '800', '900',
			];
			$family_tokens = array_values( array_filter( $tokens, static function ( string $token ) use ( $style_tokens ): bool {
				return $token !== '' && ! in_array( $token, $style_tokens, true ) && strpos( $token, 'wght' ) === false;
			} ) );
			$family = implode( '-', $family_tokens );
			if ( $family === '' ) {
				$family = strtolower( $filename );
			}

			$candidates[] = [ 'font' => $font, 'family' => $family, 'rank' => $rank ];
		}

		usort( $candidates, static function ( array $left, array $right ): int {
			$rank = (int) $left['rank'] <=> (int) $right['rank'];
			return $rank !== 0 ? $rank : strnatcasecmp( (string) $left['font'], (string) $right['font'] );
		} );

		$selected = [];
		$families = [];
		foreach ( $candidates as $candidate ) {
			$family = (string) $candidate['family'];
			if ( isset( $families[ $family ] ) ) {
				continue;
			}
			$families[ $family ] = true;
			$selected[] = (string) $candidate['font'];
			if ( count( $selected ) >= $limit ) {
				return $selected;
			}
		}

		foreach ( $candidates as $candidate ) {
			$font = (string) $candidate['font'];
			if ( ! in_array( $font, $selected, true ) ) {
				$selected[] = $font;
			}
			if ( count( $selected ) >= $limit ) {
				break;
			}
		}

		return $selected;
	}

	/**
	 * @param string[] $files
	 * @param array<string,mixed> $manifest
	 * @return string[]
	 */
	public function preload_main_script( array $files, array $manifest ): array {
		if ( ! $this->config->enabled( 'preload_main_script' ) ) {
			return $files;
		}

		$main_files = $manifest['js']['main']['files'] ?? [];
		if ( ! is_array( $main_files ) ) {
			return $files;
		}

		foreach ( $main_files as $file ) {
			$file = is_string( $file ) ? trim( $file ) : '';
			if ( $file !== '' && preg_match( '~/main(?:\.[\w-]+)?\.js$~', $file ) ) {
				$files[] = $file;
			}
		}

		return array_values( array_unique( $files ) );
	}

	/** @param string[] $handles @return string[] */
	public function async_section_styles( array $handles ): array {
		if ( ! function_exists( 'wp_styles' ) ) {
			return $handles;
		}

		$critical_css_ready = function_exists( 'sp_get_inline_critical_css' )
			&& trim( (string) sp_get_inline_critical_css() ) !== '';
		if ( $this->config->enabled( 'async_main_style' ) && $critical_css_ready ) {
			$handles[] = 'main';
		}

		if ( ! $this->config->enabled( 'async_section_styles' ) ) {
			return array_values( array_unique( $handles ) );
		}

		$critical = apply_filters( 'sp_accelerator_critical_style_handles', [ 'section-hero' ] );
		$critical = is_array( $critical ) ? $critical : [];
		$styles   = wp_styles();
		$queue    = is_object( $styles ) && isset( $styles->queue ) && is_array( $styles->queue ) ? $styles->queue : [];

		foreach ( $queue as $handle ) {
			$handle = is_string( $handle ) ? $handle : '';
			if ( $handle !== '' && strpos( $handle, 'section-' ) === 0 && ! in_array( $handle, $critical, true ) ) {
				$handles[] = $handle;
			}
		}

		$handles = apply_filters( 'sp_accelerator_async_style_handles', $handles, $queue );
		return is_array( $handles ) ? array_values( array_unique( array_filter( $handles, 'is_string' ) ) ) : [];
	}

	public function delay_noncritical_theme_scripts( string $tag, string $handle ): string {
		if ( is_admin() || strpos( $handle, 'theme-' ) !== 0 || ! $this->config->enabled( 'delay_section_scripts' ) || ! function_exists( 'wp_scripts' ) ) {
			return $tag;
		}

		$scripts = wp_scripts();
		$src     = isset( $scripts->registered[ $handle ] ) ? (string) $scripts->registered[ $handle ]->src : '';
		$path    = (string) wp_parse_url( $src, PHP_URL_PATH );
		if ( $path === '' || strpos( $path, '/assets/js/' ) === false || strpos( $path, '/assets/js/modules/section-hero.js' ) !== false ) {
			return $tag;
		}

		$should_delay = strpos( $path, '/assets/js/modules/' ) !== false || strpos( $path, '/assets/js/npm.' ) !== false;
		$before       = $scripts->get_data( $handle, 'before' );
		$after        = $scripts->get_data( $handle, 'after' );
		if ( ! $should_delay || ! empty( $before ) || ! empty( $after ) || preg_match( '/\sasync(?:\s|=|>)/i', $tag ) ) {
			return $tag;
		}

		$should_delay = (bool) apply_filters( 'sp_accelerator_delay_script', true, $handle, $src, $tag );
		if ( ! $should_delay ) {
			return $tag;
		}

		return preg_replace_callback(
			'/\ssrc=(["\'])([^"\']+)\1/',
			static function ( array $match ): string {
				return ' data-sp-accelerator-src=' . $match[1] . $match[2] . $match[1];
			},
			$tag,
			1
		) ?: $tag;
	}

	public function print_script_loader(): void {
		if ( is_admin() || ! $this->config->enabled( 'delay_section_scripts' ) ) {
			return;
		}

		$timeout = min( 30000, max( 0, (int) $this->config->get( 'script_delay_ms', 12000 ) ) );
		$script  = <<<'JS'
(function(w,d,timeout){
	var ready=d.readyState!=='loading',requested=false,started=false,observer=null;
	function complete(){d.dispatchEvent(new CustomEvent('sp:accelerator:scripts-loaded'));}
	function start(){
		if(started||!ready){requested=true;return;}
		started=true;
		if(observer){observer.disconnect();observer=null;}
		var queue=Array.prototype.slice.call(d.querySelectorAll('script[data-sp-accelerator-src]'));
		function next(){
			var placeholder=queue.shift();
			if(!placeholder){complete();return;}
			var script=d.createElement('script');
			for(var i=0;i<placeholder.attributes.length;i++){
				var attr=placeholder.attributes[i];
				if(attr.name!=='data-sp-accelerator-src'&&attr.name!=='defer'){script.setAttribute(attr.name,attr.value);}
			}
			script.async=false;
			script.src=placeholder.getAttribute('data-sp-accelerator-src');
			script.onload=script.onerror=next;
			placeholder.parentNode.replaceChild(script,placeholder);
		}
		next();
	}
	function request(immediate){requested=true;if(!ready){return;}if(immediate||!w.requestIdleCallback){start();return;}w.requestIdleCallback(start,{timeout:1500});}
	function armProximity(){
		if(!('IntersectionObserver' in w)){return;}
		var targets=[];
		d.querySelectorAll('script[data-sp-accelerator-src]').forEach(function(node){
			var src=node.getAttribute('data-sp-accelerator-src')||'',match=src.match(/\/modules\/(section-[a-z0-9-]+)(?:\.[a-z0-9-]+)?\.js(?:[?#]|$)/i);
			if(!match){return;}var section=d.querySelector('.'+match[1]);if(section&&targets.indexOf(section)===-1){targets.push(section);}
		});
		if(!targets.length){return;}
		observer=new IntersectionObserver(function(entries){for(var i=0;i<entries.length;i++){if(entries[i].isIntersecting){request(false);return;}}},{rootMargin:'160px 0px'});
		targets.forEach(function(target){observer.observe(target);});
	}
	['pointerdown','touchstart','keydown','scroll'].forEach(function(name){w.addEventListener(name,function(){request(true);},{once:true,passive:true,capture:true});});
	if(!ready){d.addEventListener('DOMContentLoaded',function(){ready=true;if(requested){start();}else{armProximity();}},{once:true});}else{armProximity();}
	if(timeout>=0){w.setTimeout(function(){request(false);},timeout);}
})(window,document,%d);
JS;
		$script = sprintf( $script, $timeout );
		if ( function_exists( 'wp_get_inline_script_tag' ) ) {
			echo wp_get_inline_script_tag( $script, [ 'id' => 'sp-accelerator-loader' ] );
		} else {
			echo '<script id="sp-accelerator-loader">' . $script . '</script>';
		}
	}

	/**
	 * @param mixed[] $urls
	 * @return mixed[]
	 */
	public function resource_hints( array $urls, string $relation_type ): array {
		if ( ! $this->config->enabled( 'resource_hints' ) || ! in_array( $relation_type, [ 'preconnect', 'dns-prefetch' ], true ) ) {
			return $urls;
		}

		$home_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
		$origins   = [];
		foreach ( [ wp_scripts(), wp_styles() ] as $registry ) {
			$queue = is_object( $registry ) && isset( $registry->queue ) && is_array( $registry->queue ) ? $registry->queue : [];
			foreach ( $queue as $handle ) {
				$item = isset( $registry->registered[ $handle ] ) ? $registry->registered[ $handle ] : null;
				$src  = is_object( $item ) && isset( $item->src ) ? (string) $item->src : '';
				$host = strtolower( (string) wp_parse_url( $src, PHP_URL_HOST ) );
				if ( $host === '' || $host === $home_host ) {
					continue;
				}
				$scheme = strtolower( (string) wp_parse_url( $src, PHP_URL_SCHEME ) );
				$origins[ ( $scheme === 'http' ? 'http' : 'https' ) . '://' . $host ] = true;
			}
		}

		foreach ( array_slice( array_keys( $origins ), 0, 4 ) as $origin ) {
			$urls[] = $origin;
		}
		return array_values( array_unique( $urls, SORT_REGULAR ) );
	}

	/** @param array<string,mixed> $attributes @return array<string,mixed> */
	public function image_attributes( array $attributes ): array {
		if ( $this->config->enabled( 'async_image_decoding' ) && empty( $attributes['decoding'] ) ) {
			$attributes['decoding'] = 'async';
		}
		return $attributes;
	}
}
