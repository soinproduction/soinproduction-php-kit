<?php
	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	if ( ! class_exists( 'SP_Uploads_WebP_Convert' ) ) {
		class SP_Uploads_WebP_Convert {
			private const OPT_KEY      = 'sp_webp_convert_cfg';
			private const NONCE_ACTION = 'sp_webp_convert_admin';
			private const PAGE_SLUG    = 'sp-uploads-webp-convert';
			private const VERSION      = '2.1.0';
			private const URL_MAP_TRANSIENT = 'sp_webp_url_replace_map_cache';
			private const UNUSED_SCHEMA_TRANSIENT = 'sp_webp_unused_schema_cache';

			private static ?self $instance = null;

			public static function get(): self {
				if ( ! self::$instance ) {
					self::$instance = new self();
				}

				return self::$instance;
			}

			private function __construct() {
				add_filter( 'wp_handle_upload', [ $this, 'convert_on_upload' ], 20 );
				add_filter( 'upload_mimes', [ $this, 'allow_webp_mime' ] );
				add_filter( 'wp_check_filetype_and_ext', [ $this, 'fix_webp_filetype' ], 10, 5 );

				add_action( 'admin_menu', [ $this, 'menu' ] );
				add_action( 'admin_enqueue_scripts', [ $this, 'admin_assets' ] );

				add_action( 'wp_ajax_sp_webp_save_settings', [ $this, 'ajax_save_settings' ] );
				add_action( 'wp_ajax_sp_webp_scan_media', [ $this, 'ajax_scan_media' ] );
				add_action( 'wp_ajax_sp_webp_convert_batch', [ $this, 'ajax_convert_batch' ] );
				add_action( 'wp_ajax_sp_webp_prepare_url_replace', [ $this, 'ajax_prepare_url_replace' ] );
				add_action( 'wp_ajax_sp_webp_replace_urls_batch', [ $this, 'ajax_replace_urls_batch' ] );
				add_action( 'wp_ajax_sp_webp_prepare_unused_scan', [ $this, 'ajax_prepare_unused_scan' ] );
				add_action( 'wp_ajax_sp_webp_scan_unused_batch', [ $this, 'ajax_scan_unused_batch' ] );
				add_action( 'wp_ajax_sp_webp_delete_unused_batch', [ $this, 'ajax_delete_unused_batch' ] );
			}

			private function defaults(): array {
				return [
					'enabled_upload'     => 1,
					'quality'            => 90,
					'max_side'           => 2560,
					'delete_original'    => 1,
					'skip_animated_gif'  => 1,
					'batch_size'         => 20,
					'db_batch_size'      => 200,
				];
			}

			private function cfg(): array {
				$raw = get_option( self::OPT_KEY, [] );
				if ( ! is_array( $raw ) ) {
					$raw = [];
				}

				return array_merge( $this->defaults(), $raw );
			}

			private function sanitize_cfg( array $raw ): array {
				$d = $this->defaults();

				return [
					'enabled_upload'    => ! empty( $raw['enabled_upload'] ) ? 1 : 0,
					'quality'           => min( 100, max( 60, (int) ( $raw['quality'] ?? $d['quality'] ) ) ),
					'max_side'          => min( 8000, max( 320, (int) ( $raw['max_side'] ?? $d['max_side'] ) ) ),
					'delete_original'   => ! empty( $raw['delete_original'] ) ? 1 : 0,
					'skip_animated_gif' => ! empty( $raw['skip_animated_gif'] ) ? 1 : 0,
					'batch_size'        => min( 100, max( 1, (int) ( $raw['batch_size'] ?? $d['batch_size'] ) ) ),
					'db_batch_size'     => min( 500, max( 20, (int) ( $raw['db_batch_size'] ?? $d['db_batch_size'] ) ) ),
				];
			}

			public function allow_webp_mime( array $mimes ): array {
				$mimes['webp'] = 'image/webp';
				return $mimes;
			}

			public function fix_webp_filetype( array $data, string $file, string $filename, $mimes = null, string $real_mime = '' ): array {
				if ( ! is_array( $mimes ) ) {
					$mimes = [];
				}

				if ( ! empty( $data['ext'] ) || ! empty( $data['type'] ) ) {
					return $data;
				}

				$ext = strtolower( (string) pathinfo( $filename, PATHINFO_EXTENSION ) );
				if ( $ext === 'webp' ) {
					$data['ext']  = 'webp';
					$data['type'] = 'image/webp';
				}

				return $data;
			}

			private function supported_mimes_for_query( array $cfg ): array {
				return [ 'image/jpeg', 'image/png', 'image/gif' ];
			}

			private function is_supported_mime( string $mime, array $cfg ): bool {
				$mime = strtolower( trim( $mime ) );
				if ( $mime === '' ) {
					return false;
				}

				return in_array( $mime, $this->supported_mimes_for_query( $cfg ), true );
			}

			private function detect_mime( string $file, string $fallback = '' ): string {
				$mime = '';
				if ( is_file( $file ) ) {
					$mime = (string) wp_get_image_mime( $file );
					if ( $mime !== '' ) {
						return strtolower( $mime );
					}
				}

				$ext = strtolower( (string) pathinfo( $file, PATHINFO_EXTENSION ) );
				$mime_by_ext = [
					'jpg'  => 'image/jpeg',
					'jpeg' => 'image/jpeg',
					'png'  => 'image/png',
					'gif'  => 'image/gif',
					'webp' => 'image/webp',
				];
				if ( isset( $mime_by_ext[ $ext ] ) ) {
					return $mime_by_ext[ $ext ];
				}

				$mime = strtolower( trim( $fallback ) );
				if ( $mime !== '' ) {
					return $mime;
				}

				$check = wp_check_filetype( $file );
				return strtolower( (string) ( $check['type'] ?? '' ) );
			}

			private function is_animated_gif( string $file ): bool {
				if ( ! is_readable( $file ) ) {
					return false;
				}

				$handle = fopen( $file, 'rb' );
				if ( ! $handle ) {
					return false;
				}

				$frames = 0;
				$chunk  = '';

				while ( ! feof( $handle ) && $frames < 2 ) {
					$chunk .= (string) fread( $handle, 1024 * 100 );
					$frames += preg_match_all( '#\x00\x21\xF9\x04.{4}\x00\x2C#s', $chunk, $matches );
					$chunk = substr( $chunk, -20 );
				}

				fclose( $handle );

				return $frames > 1;
			}

			private function convert_image_file_to_webp( string $file, string $mime, array $cfg ): array {
				$result = [
					'status'       => 'skipped',
					'path'         => $file,
					'message'      => 'Skipped',
					'bytes_before' => 0,
					'bytes_after'  => 0,
					'resized'      => false,
				];

				if ( ! is_file( $file ) || ! is_readable( $file ) ) {
					$result['status']  = 'error';
					$result['message'] = 'Source file is not readable';
					return $result;
				}

				$mime = $this->detect_mime( $file, $mime );
				if ( $mime === 'image/webp' ) {
					$result['message'] = 'Existing WebP skipped';
					return $result;
				}

				if ( ! $this->is_supported_mime( $mime, $cfg ) ) {
					$result['message'] = 'Unsupported mime type: ' . $mime;
					return $result;
				}

				if ( $mime === 'image/gif' && ! empty( $cfg['skip_animated_gif'] ) && $this->is_animated_gif( $file ) ) {
					$result['message'] = 'Animated GIF skipped';
					return $result;
				}

				$image = wp_get_image_editor( $file );
				if ( is_wp_error( $image ) ) {
					$result['status']  = 'error';
					$result['message'] = $image->get_error_message();
					return $result;
				}

				$size = $image->get_size();
				$max  = (int) $cfg['max_side'];
				if ( $max > 0 && ! empty( $size['width'] ) && ! empty( $size['height'] ) && ( (int) $size['width'] > $max || (int) $size['height'] > $max ) ) {
					$resized = $image->resize( $max, $max, false );
					if ( is_wp_error( $resized ) ) {
						$result['status']  = 'error';
						$result['message'] = $resized->get_error_message();
						return $result;
					}
					$result['resized'] = true;
				}

				if ( method_exists( $image, 'set_quality' ) ) {
					$image->set_quality( (int) $cfg['quality'] );
				}

				$is_webp = $mime === 'image/webp' || strtolower( (string) pathinfo( $file, PATHINFO_EXTENSION ) ) === 'webp';
				$target  = $is_webp ? $file : (string) preg_replace( '~\.(jpe?g|png|gif|webp)$~i', '.webp', $file );
				$before  = (int) ( @filesize( $file ) ?: 0 );

				$target_dir = dirname( $target );
				if ( ! is_dir( $target_dir ) && ! wp_mkdir_p( $target_dir ) ) {
					$result['status']  = 'error';
					$result['message'] = 'Unable to create target directory';
					return $result;
				}

				$tmp_target  = $target . '.sp-tmp-' . wp_generate_uuid4() . '.webp';
				$result_save = $image->save( $tmp_target, 'image/webp' );

				if ( is_wp_error( $result_save ) ) {
					if ( is_file( $tmp_target ) ) {
						@unlink( $tmp_target );
					}
					$result['status']  = 'error';
					$result['message'] = $result_save->get_error_message();
					return $result;
				}

				$path = (string) ( $result_save['path'] ?? $tmp_target );
				if ( $path === '' || ! is_file( $path ) ) {
					$result['status']  = 'error';
					$result['message'] = 'WebP file was not created';
					return $result;
				}

				$bytes_after = (int) ( @filesize( $path ) ?: 0 );
				if ( $bytes_after <= 0 || ! @getimagesize( $path ) ) {
					@unlink( $path );
					$result['status']  = 'error';
					$result['message'] = 'Generated WebP file is invalid';
					return $result;
				}

				$norm_path   = wp_normalize_path( $path );
				$norm_target = wp_normalize_path( $target );
				if ( $norm_path !== $norm_target ) {
					if ( ! @rename( $path, $target ) ) {
						if ( ! @copy( $path, $target ) ) {
							@unlink( $path );
							$result['status']  = 'error';
							$result['message'] = 'Unable to move converted WebP into place';
							return $result;
						}
						@unlink( $path );
					}
					$path = $target;
				}

				$bytes_after = (int) ( @filesize( $path ) ?: 0 );
				if ( $bytes_after <= 0 || ! @getimagesize( $path ) ) {
					$result['status']  = 'error';
					$result['message'] = 'Final WebP file validation failed';
					return $result;
				}

				if ( ! $is_webp && ! empty( $cfg['delete_original'] ) ) {
					$norm_old = wp_normalize_path( $file );
					$norm_new = wp_normalize_path( $path );
					if ( $norm_old !== $norm_new && is_file( $file ) ) {
						@unlink( $file );
					}
				}

				$result['status']       = 'converted';
				$result['path']         = $path;
				$result['bytes_before'] = $before;
				$result['bytes_after']  = $bytes_after;
				$result['message']      = 'Converted';

				return $result;
			}

			private function path_to_relative_upload( string $path ): string {
				$up     = wp_get_upload_dir();
				$base   = wp_normalize_path( trailingslashit( (string) $up['basedir'] ) );
				$target = wp_normalize_path( $path );

				if ( str_starts_with( $target, $base ) ) {
					return ltrim( substr( $target, strlen( $base ) ), '/' );
				}

				$relative = _wp_relative_upload_path( $path );
				if ( is_string( $relative ) ) {
					return ltrim( $relative, '/' );
				}

				return '';
			}

			private function upload_url_from_path( string $path ): string {
				$relative = $this->path_to_relative_upload( $path );
				if ( $relative === '' ) {
					return '';
				}

				$up = wp_get_upload_dir();
				return trailingslashit( (string) $up['baseurl'] ) . str_replace( '\\', '/', $relative );
			}

			public function convert_on_upload( array $upload ): array {
				$cfg = $this->cfg();
				if ( empty( $cfg['enabled_upload'] ) ) {
					return $upload;
				}

				$file = isset( $upload['file'] ) ? (string) $upload['file'] : '';
				if ( $file === '' || ! is_file( $file ) ) {
					return $upload;
				}

				$mime   = isset( $upload['type'] ) ? (string) $upload['type'] : '';
				$result = $this->convert_image_file_to_webp( $file, $mime, $cfg );

				if ( $result['status'] !== 'converted' ) {
					return $upload;
				}

				$new_path = (string) $result['path'];
				if ( $new_path === '' || ! is_file( $new_path ) ) {
					return $upload;
				}

				$new_url = $this->upload_url_from_path( $new_path );
				if ( $new_url !== '' ) {
					$upload['url'] = $new_url;
				}

				$upload['file'] = $new_path;
				$upload['type'] = 'image/webp';

				return $upload;
			}

			private function count_supported_attachments( array $cfg ): int {
				global $wpdb;

				$mimes = $this->supported_mimes_for_query( $cfg );
				if ( empty( $mimes ) ) {
					return 0;
				}

				$placeholders = implode( ',', array_fill( 0, count( $mimes ), '%s' ) );
				$sql = $wpdb->prepare(
					"SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type='attachment' AND post_status='inherit' AND post_mime_type IN ($placeholders)",
					...$mimes
				);

				return (int) $wpdb->get_var( $sql );
			}

			private function query_next_attachment_ids( int $after_id, int $limit, array $cfg ): array {
				global $wpdb;

				$limit = min( 100, max( 1, $limit ) );
				$mimes = $this->supported_mimes_for_query( $cfg );
				if ( empty( $mimes ) ) {
					return [];
				}

				$placeholders = implode( ',', array_fill( 0, count( $mimes ), '%s' ) );
				$params       = array_merge( $mimes, [ $after_id, $limit ] );

				$sql = $wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE post_type='attachment' AND post_status='inherit' AND post_mime_type IN ($placeholders) AND ID > %d ORDER BY ID ASC LIMIT %d",
					...$params
				);

				$ids = $wpdb->get_col( $sql );
				if ( ! is_array( $ids ) ) {
					return [];
				}

				return array_values( array_filter( array_map( 'intval', $ids ) ) );
			}

			private function collect_generated_size_files( string $base_file, $meta ): array {
				if ( ! is_array( $meta ) || empty( $meta['sizes'] ) || ! is_array( $meta['sizes'] ) ) {
					return [];
				}

				$dir   = trailingslashit( dirname( $base_file ) );
				$files = [];

				foreach ( $meta['sizes'] as $size ) {
					if ( ! is_array( $size ) || empty( $size['file'] ) ) {
						continue;
					}

					$files[] = $dir . ltrim( (string) $size['file'], '/\\' );
				}

				return array_values( array_unique( $files ) );
			}

			private function delete_stale_files( string $old_file, array $old_sizes, string $new_file, array $new_sizes ): void {
				$keep = [];
				foreach ( array_merge( [ $new_file ], $new_sizes ) as $path ) {
					$keep[ wp_normalize_path( $path ) ] = true;
				}

				foreach ( array_merge( [ $old_file ], $old_sizes ) as $path ) {
					$normalized = wp_normalize_path( $path );
					if ( isset( $keep[ $normalized ] ) ) {
						continue;
					}

					if ( is_file( $path ) ) {
						@unlink( $path );
					}
				}
			}

			private function convert_attachment( int $attachment_id, array $cfg ): array {
				$file = (string) get_attached_file( $attachment_id );
				if ( $file === '' || ! is_file( $file ) ) {
					return [
						'status'     => 'skipped',
						'message'    => 'Attachment file missing',
						'bytes_saved'=> 0,
					];
				}

				$mime = $this->detect_mime( $file, (string) get_post_mime_type( $attachment_id ) );
				if ( ! $this->is_supported_mime( $mime, $cfg ) ) {
					return [
						'status'     => 'skipped',
						'message'    => 'Unsupported mime type: ' . $mime,
						'bytes_saved'=> 0,
					];
				}

				$old_file      = $file;
				$old_meta      = wp_get_attachment_metadata( $attachment_id );
				$old_size_files = $this->collect_generated_size_files( $old_file, $old_meta );
				$old_main_url   = $this->upload_url_from_path( $old_file );
				$old_ext        = strtolower( (string) pathinfo( $old_file, PATHINFO_EXTENSION ) );

				$conversion = $this->convert_image_file_to_webp( $file, $mime, $cfg );
				if ( $conversion['status'] !== 'converted' ) {
					return [
						'status'     => $conversion['status'],
						'message'    => (string) $conversion['message'],
						'bytes_saved'=> 0,
					];
				}

				$new_file = (string) $conversion['path'];
				if ( $new_file === '' || ! is_file( $new_file ) ) {
					return [
						'status'     => 'error',
						'message'    => 'Converted file missing after save',
						'bytes_saved'=> 0,
					];
				}

				$relative = $this->path_to_relative_upload( $new_file );
				if ( $relative === '' ) {
					return [
						'status'     => 'error',
						'message'    => 'Unable to resolve upload-relative path',
						'bytes_saved'=> 0,
					];
				}

				require_once ABSPATH . 'wp-admin/includes/image.php';

				update_attached_file( $attachment_id, $relative );
				wp_update_post( [
					'ID'             => $attachment_id,
					'post_mime_type' => 'image/webp',
				] );

				$new_meta = wp_generate_attachment_metadata( $attachment_id, $new_file );
				if ( is_wp_error( $new_meta ) ) {
					$size = @getimagesize( $new_file );
					$new_meta = [
						'width'  => (int) ( $size[0] ?? 0 ),
						'height' => (int) ( $size[1] ?? 0 ),
						'file'   => $relative,
						'sizes'  => [],
					];
				}

				wp_update_attachment_metadata( $attachment_id, $new_meta );

				if ( $old_ext !== '' && $old_ext !== 'webp' ) {
					update_post_meta( $attachment_id, '_sp_webp_original_ext', $old_ext );
				}
				if ( $old_main_url !== '' ) {
					update_post_meta( $attachment_id, '_sp_webp_original_url', $old_main_url );
				}

				if ( ! empty( $cfg['delete_original'] ) ) {
					$new_size_files = $this->collect_generated_size_files( $new_file, $new_meta );
					$this->delete_stale_files( $old_file, $old_size_files, $new_file, $new_size_files );
				}

				$bytes_before = (int) ( $conversion['bytes_before'] ?? 0 );
				$bytes_after  = (int) ( $conversion['bytes_after'] ?? 0 );
				$bytes_saved  = max( 0, $bytes_before - $bytes_after );
				delete_transient( self::URL_MAP_TRANSIENT );

				return [
					'status'      => 'converted',
					'message'     => ! empty( $conversion['resized'] ) ? 'Converted and resized' : 'Converted',
					'bytes_saved' => $bytes_saved,
				];
			}

			private function swap_webp_url_extension( string $url, string $target_ext ): string {
				$url = trim( $url );
				if ( $url === '' ) {
					return '';
				}

				$target_ext = strtolower( trim( $target_ext ) );
				if ( ! preg_match( '~^[a-z0-9]+$~', $target_ext ) ) {
					return '';
				}

				return (string) preg_replace( '~\.webp(?=([?#].*)?$)~i', '.' . $target_ext, $url );
			}

			private function attachment_url_map( int $attachment_id ): array {
				$map = [];

				$new_main_url = (string) wp_get_attachment_url( $attachment_id );
				if ( $new_main_url === '' || ! preg_match( '~\.webp(?:[?#].*)?$~i', $new_main_url ) ) {
					return $map;
				}

				$old_ext_meta = strtolower( (string) get_post_meta( $attachment_id, '_sp_webp_original_ext', true ) );
				$allowed_exts = [ 'jpg', 'jpeg', 'png', 'gif' ];
				$old_exts     = in_array( $old_ext_meta, $allowed_exts, true ) ? [ $old_ext_meta ] : $allowed_exts;

				$old_main_url = (string) get_post_meta( $attachment_id, '_sp_webp_original_url', true );
				if ( $old_main_url !== '' && $old_main_url !== $new_main_url ) {
					$map[ $old_main_url ] = $new_main_url;
				}

				foreach ( $old_exts as $old_ext ) {
					$old = $this->swap_webp_url_extension( $new_main_url, $old_ext );
					if ( $old !== '' && $old !== $new_main_url ) {
						$map[ $old ] = $new_main_url;
					}
				}

				$meta = wp_get_attachment_metadata( $attachment_id );
				if ( ! is_array( $meta ) || empty( $meta['sizes'] ) || ! is_array( $meta['sizes'] ) ) {
					return $map;
				}

				$base_dir_url = trailingslashit( dirname( $new_main_url ) );
				foreach ( $meta['sizes'] as $size ) {
					if ( ! is_array( $size ) || empty( $size['file'] ) ) {
						continue;
					}

					$new_size_url = $base_dir_url . ltrim( (string) $size['file'], '/\\' );
					if ( ! preg_match( '~\.webp(?:[?#].*)?$~i', $new_size_url ) ) {
						continue;
					}

					foreach ( $old_exts as $old_ext ) {
						$old = $this->swap_webp_url_extension( $new_size_url, $old_ext );
						if ( $old !== '' && $old !== $new_size_url ) {
							$map[ $old ] = $new_size_url;
						}
					}
				}

				return $map;
			}

			private function build_url_replacement_map(): array {
				$cached = get_transient( self::URL_MAP_TRANSIENT );
				if ( is_array( $cached ) && isset( $cached['map'] ) && is_array( $cached['map'] ) ) {
					return $cached['map'];
				}

				$map = [];

				$q = new WP_Query( [
					'post_type'              => 'attachment',
					'post_status'            => 'inherit',
					'post_mime_type'         => 'image/webp',
					'fields'                 => 'ids',
					'posts_per_page'         => 500,
					'paged'                  => 1,
					'no_found_rows'          => true,
					'orderby'                => 'ID',
					'order'                  => 'ASC',
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				] );

				while ( ! empty( $q->posts ) ) {
					foreach ( $q->posts as $attachment_id ) {
						$attachment_id = (int) $attachment_id;
						if ( $attachment_id <= 0 ) {
							continue;
						}

						foreach ( $this->attachment_url_map( $attachment_id ) as $old_url => $new_url ) {
							$old_url = trim( (string) $old_url );
							$new_url = trim( (string) $new_url );
							if ( $old_url === '' || $new_url === '' || $old_url === $new_url ) {
								continue;
							}
							$map[ $old_url ] = $new_url;
						}
					}

					$page = (int) $q->get( 'paged' );
					$q = new WP_Query( [
						'post_type'              => 'attachment',
						'post_status'            => 'inherit',
						'post_mime_type'         => 'image/webp',
						'fields'                 => 'ids',
						'posts_per_page'         => 500,
						'paged'                  => $page + 1,
						'no_found_rows'          => true,
						'orderby'                => 'ID',
						'order'                  => 'ASC',
						'update_post_meta_cache' => false,
						'update_post_term_cache' => false,
					] );
				}

				set_transient( self::URL_MAP_TRANSIENT, [ 'map' => $map ], 10 * MINUTE_IN_SECONDS );

				return $map;
			}

			private function replace_in_string( string $value, array $map, int &$hits = 0 ): string {
				$hits = 0;
				if ( $value === '' || empty( $map ) ) {
					return $value;
				}

				$updated = strtr( $value, $map );
				if ( $updated === $value ) {
					return $value;
				}

				foreach ( $map as $old => $new ) {
					if ( $old === '' || $old === $new ) {
						continue;
					}
					$hits += substr_count( $value, $old );
				}

				return $updated;
			}

			private function replace_recursive_value( $value, array $map, int &$hits = 0, bool &$changed = false ) {
				if ( is_string( $value ) ) {
					$local_hits = 0;
					$new_value  = $this->replace_in_string( $value, $map, $local_hits );
					if ( $new_value !== $value ) {
						$changed = true;
						$hits   += $local_hits;
					}
					return $new_value;
				}

				if ( is_array( $value ) ) {
					$out = [];
					foreach ( $value as $k => $v ) {
						$out[ $k ] = $this->replace_recursive_value( $v, $map, $hits, $changed );
					}
					return $out;
				}

				return $value;
			}

			private function replace_meta_value( string $raw_value, array $map, int &$hits = 0, bool &$changed = false ): string {
				$hits    = 0;
				$changed = false;

				if ( $raw_value === '' ) {
					return $raw_value;
				}

				if ( ! is_serialized( $raw_value, false ) ) {
					$updated = $this->replace_in_string( $raw_value, $map, $hits );
					$changed = $updated !== $raw_value;
					return $updated;
				}

				$decoded = @unserialize( $raw_value, [ 'allowed_classes' => false ] );
				if ( $decoded === false && $raw_value !== 'b:0;' ) {
					return $raw_value;
				}

				if ( is_object( $decoded ) ) {
					return $raw_value;
				}

				$updated_value = $this->replace_recursive_value( $decoded, $map, $hits, $changed );
				if ( ! $changed ) {
					return $raw_value;
				}

				return serialize( $updated_value );
			}

			private function process_posts_replace_batch( int $last_id, int $limit, array $map, bool $dry_run ): array {
				global $wpdb;

				$rows = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT ID, post_content, post_excerpt FROM {$wpdb->posts} WHERE ID > %d ORDER BY ID ASC LIMIT %d",
						$last_id,
						$limit
					),
					ARRAY_A
				);

				if ( ! is_array( $rows ) || empty( $rows ) ) {
					return [
						'done_phase'   => true,
						'next_last_id' => $last_id,
						'processed'    => 0,
						'changed'      => 0,
						'hits'         => 0,
						'log'          => [],
						'errors'       => 0,
					];
				}

				$processed = 0;
				$changed   = 0;
				$hits      = 0;
				$errors    = 0;
				$log       = [];

				foreach ( $rows as $row ) {
					$post_id = (int) ( $row['ID'] ?? 0 );
					if ( $post_id <= 0 ) {
						continue;
					}

					$last_id = max( $last_id, $post_id );
					$processed ++;

					$content_hits = 0;
					$excerpt_hits = 0;
					$new_content  = $this->replace_in_string( (string) ( $row['post_content'] ?? '' ), $map, $content_hits );
					$new_excerpt  = $this->replace_in_string( (string) ( $row['post_excerpt'] ?? '' ), $map, $excerpt_hits );

					if ( $new_content === (string) ( $row['post_content'] ?? '' ) && $new_excerpt === (string) ( $row['post_excerpt'] ?? '' ) ) {
						continue;
					}

					$changed ++;
					$hits += $content_hits + $excerpt_hits;

					if ( ! $dry_run ) {
						$ok = $wpdb->update(
							$wpdb->posts,
							[
								'post_content' => $new_content,
								'post_excerpt' => $new_excerpt,
							],
							[ 'ID' => $post_id ],
							[ '%s', '%s' ],
							[ '%d' ]
						);

						if ( $ok === false ) {
							$errors ++;
							$log[] = '[ERR] posts ID ' . $post_id . ': update failed';
							continue;
						}
					}

					if ( count( $log ) < 12 ) {
						$log[] = sprintf( '[OK] posts ID %d changed', $post_id );
					}
				}

				return [
					'done_phase'   => count( $rows ) < $limit,
					'next_last_id' => $last_id,
					'processed'    => $processed,
					'changed'      => $changed,
					'hits'         => $hits,
					'log'          => $log,
					'errors'       => $errors,
				];
			}

			private function process_postmeta_replace_batch( int $last_meta_id, int $limit, array $map, bool $dry_run ): array {
				global $wpdb;

				$rows = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT meta_id, post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_id > %d ORDER BY meta_id ASC LIMIT %d",
						$last_meta_id,
						$limit
					),
					ARRAY_A
				);

				if ( ! is_array( $rows ) || empty( $rows ) ) {
					return [
						'done_phase'   => true,
						'next_last_id' => $last_meta_id,
						'processed'    => 0,
						'changed'      => 0,
						'hits'         => 0,
						'log'          => [],
						'errors'       => 0,
					];
				}

				$processed = 0;
				$changed   = 0;
				$hits      = 0;
				$errors    = 0;
				$log       = [];

				foreach ( $rows as $row ) {
					$meta_id = (int) ( $row['meta_id'] ?? 0 );
					if ( $meta_id <= 0 ) {
						continue;
					}

					$last_meta_id = max( $last_meta_id, $meta_id );
					$processed ++;

					$local_hits   = 0;
					$local_change = false;
					$new_value    = $this->replace_meta_value( (string) ( $row['meta_value'] ?? '' ), $map, $local_hits, $local_change );

					if ( ! $local_change ) {
						continue;
					}

					$changed ++;
					$hits += $local_hits;

					if ( ! $dry_run ) {
						$ok = $wpdb->update(
							$wpdb->postmeta,
							[ 'meta_value' => $new_value ],
							[ 'meta_id' => $meta_id ],
							[ '%s' ],
							[ '%d' ]
						);

						if ( $ok === false ) {
							$errors ++;
							$log[] = '[ERR] postmeta #' . $meta_id . ': update failed';
							continue;
						}
					}

					if ( count( $log ) < 12 ) {
						$key = (string) ( $row['meta_key'] ?? '' );
						$log[] = sprintf( '[OK] postmeta #%d (%s) changed', $meta_id, $key !== '' ? $key : '-' );
					}
				}

				return [
					'done_phase'   => count( $rows ) < $limit,
					'next_last_id' => $last_meta_id,
					'processed'    => $processed,
					'changed'      => $changed,
					'hits'         => $hits,
					'log'          => $log,
					'errors'       => $errors,
				];
			}

			private function quote_identifier( string $name ): string {
				return '`' . str_replace( '`', '``', $name ) . '`';
			}

			private function normalize_relative_upload_path( string $path ): string {
				$path = trim( $path );
				if ( $path === '' ) {
					return '';
				}

				$path = (string) preg_replace( '~[?#].*$~', '', $path );
				$path = str_replace( '\\', '/', $path );

				return ltrim( $path, '/' );
			}

			private function relative_upload_from_url( string $url ): string {
				$url = trim( $url );
				if ( $url === '' ) {
					return '';
				}

				$up      = wp_get_upload_dir();
				$baseurl = trailingslashit( (string) ( $up['baseurl'] ?? '' ) );
				if ( $baseurl !== '' && str_starts_with( $url, $baseurl ) ) {
					return $this->normalize_relative_upload_path( substr( $url, strlen( $baseurl ) ) );
				}

				$url_path     = '/' . ltrim( str_replace( '\\', '/', (string) wp_parse_url( $url, PHP_URL_PATH ) ), '/' );
				$uploads_path = '/' . trim( str_replace( '\\', '/', (string) wp_parse_url( (string) ( $up['baseurl'] ?? '' ), PHP_URL_PATH ) ), '/' ) . '/';
				if ( $url_path === '/' || $uploads_path === '//' ) {
					return '';
				}

				$parts = explode( $uploads_path, $url_path, 2 );
				if ( count( $parts ) < 2 ) {
					return '';
				}

				return $this->normalize_relative_upload_path( (string) $parts[1] );
			}

			private function unused_scan_batch_size(): int {
				$cfg = $this->cfg();
				$raw = (int) ( $cfg['batch_size'] ?? 20 );

				return min( 12, max( 3, (int) ceil( $raw / 2 ) ) );
			}

			private function unused_delete_batch_size(): int {
				return 12;
			}

			private function count_all_image_attachments(): int {
				global $wpdb;

				return (int) $wpdb->get_var(
					"SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type='attachment' AND post_status='inherit' AND post_mime_type LIKE 'image/%'"
				);
			}

			private function query_next_image_attachment_ids( int $after_id, int $limit ): array {
				global $wpdb;

				$limit = min( 50, max( 1, $limit ) );
				$ids   = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT ID FROM {$wpdb->posts} WHERE post_type='attachment' AND post_status='inherit' AND post_mime_type LIKE 'image/%%' AND ID > %d ORDER BY ID ASC LIMIT %d",
						$after_id,
						$limit
					)
				);

				if ( ! is_array( $ids ) ) {
					return [];
				}

				return array_values( array_filter( array_map( 'intval', $ids ) ) );
			}

			private function attachment_id_search_needles( int $attachment_id ): array {
				$id     = (string) $attachment_id;
				$id_len = strlen( $id );

				return array_values( array_filter( array_unique( [
					'wp-image-' . $id,
					'"id":' . $id,
					'"id":"' . $id . '"',
					'"ID":' . $id,
					'"ID":"' . $id . '"',
					'"attachmentId":' . $id,
					'"attachment_id":' . $id,
					'i:' . $id . ';',
					's:' . $id_len . ':"' . $id . '";',
					'[gallery ids="' . $id,
					'ids="' . $id . ',',
					',' . $id . ',',
					',' . $id . '"',
				] ) ) );
			}

			private function attachment_relative_search_needles( int $attachment_id ): array {
				$needles  = [];
				$relative = (string) get_post_meta( $attachment_id, '_wp_attached_file', true );
				if ( $relative === '' ) {
					$file = (string) get_attached_file( $attachment_id );
					if ( $file !== '' ) {
						$relative = $this->path_to_relative_upload( $file );
					}
				}

				$push_relative = function ( string $value ) use ( &$needles ): void {
					$value = $this->normalize_relative_upload_path( $value );
					if ( $value !== '' ) {
						$needles[] = $value;
					}
				};

				$push_relative( $relative );

				$meta     = wp_get_attachment_metadata( $attachment_id );
				$base_dir = $relative !== '' ? trim( dirname( $relative ), '/\\' ) : '';
				if ( is_array( $meta ) && ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
					foreach ( $meta['sizes'] as $size ) {
						if ( ! is_array( $size ) || empty( $size['file'] ) ) {
							continue;
						}

						$size_relative = ltrim( (string) $size['file'], '/\\' );
						if ( $base_dir !== '' && $base_dir !== '.' ) {
							$size_relative = $base_dir . '/' . $size_relative;
						}
						$push_relative( $size_relative );
					}
				}

				$current_url = (string) wp_get_attachment_url( $attachment_id );
				$push_relative( $this->relative_upload_from_url( $current_url ) );

				$old_main_url = (string) get_post_meta( $attachment_id, '_sp_webp_original_url', true );
				$push_relative( $this->relative_upload_from_url( $old_main_url ) );

				foreach ( $this->attachment_url_map( $attachment_id ) as $old_url => $new_url ) {
					$push_relative( $this->relative_upload_from_url( (string) $old_url ) );
					$push_relative( $this->relative_upload_from_url( (string) $new_url ) );
				}

				return array_values( array_filter( array_unique( $needles ) ) );
			}

			private function attachment_url_search_needles( int $attachment_id ): array {
				$needles      = [];
				$current_url  = (string) wp_get_attachment_url( $attachment_id );
				$old_main_url = (string) get_post_meta( $attachment_id, '_sp_webp_original_url', true );

				$push = static function ( string $value ) use ( &$needles ): void {
					$value = trim( $value );
					if ( $value !== '' ) {
						$needles[] = $value;
					}
				};

				$push( $current_url );
				$push( $old_main_url );

				foreach ( $this->attachment_url_map( $attachment_id ) as $old_url => $new_url ) {
					$push( (string) $old_url );
					$push( (string) $new_url );
				}

				return array_values( array_filter( array_unique( $needles ) ) );
			}

			private function attachment_custom_table_search_needles( int $attachment_id ): array {
				$needles      = [];
				$current_url  = trim( (string) wp_get_attachment_url( $attachment_id ) );
				$old_main_url = trim( (string) get_post_meta( $attachment_id, '_sp_webp_original_url', true ) );

				$push = static function ( string $value ) use ( &$needles ): void {
					$value = trim( $value );
					if ( $value !== '' ) {
						$needles[] = $value;
					}
				};

				foreach ( $this->attachment_id_search_needles( $attachment_id ) as $needle ) {
					$push( (string) $needle );
				}

				foreach ( $this->attachment_relative_search_needles( $attachment_id ) as $needle ) {
					$push( (string) $needle );
				}

				$push( $current_url );
				$push( $old_main_url );

				return array_values( array_filter( array_unique( $needles ) ) );
			}

			private function should_skip_custom_search_table( string $table ): bool {
				$table = strtolower( trim( $table ) );
				if ( $table === '' ) {
					return true;
				}

				$patterns = [
					'~(?:^|_)wf(?:_|$)~',
					'~wordfence~',
					'~actionscheduler~',
					'~gravitysmtp~',
					'~mailpoet~',
					'~rankmath_analytics~',
					'~aioseo_~',
					'~redirection_404~',
					'~_logs?(?:_|$)~',
					'~_events?(?:_|$)~',
					'~_issues?(?:_|$)~',
				];

				foreach ( $patterns as $pattern ) {
					if ( preg_match( $pattern, $table ) ) {
						return true;
					}
				}

				return false;
			}

			private function custom_searchable_columns(): array {
				$cached = get_transient( self::UNUSED_SCHEMA_TRANSIENT );
				if ( is_array( $cached ) ) {
					return $cached;
				}

				global $wpdb;

				$rows = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT TABLE_NAME, COLUMN_NAME
							 FROM INFORMATION_SCHEMA.COLUMNS
							 WHERE TABLE_SCHEMA = %s
							   AND DATA_TYPE IN ('char','varchar','tinytext','text','mediumtext','longtext','json')
							 ORDER BY TABLE_NAME ASC, ORDINAL_POSITION ASC",
						DB_NAME
					),
					ARRAY_A
				);

				if ( ! is_array( $rows ) ) {
					return [];
				}

				$core_tables = array_filter( [
					$wpdb->posts,
					$wpdb->postmeta,
					$wpdb->options,
					$wpdb->termmeta ?? '',
					$wpdb->usermeta ?? '',
					$wpdb->comments ?? '',
				] );

				$schema = [];
				foreach ( $rows as $row ) {
					$table  = isset( $row['TABLE_NAME'] ) ? (string) $row['TABLE_NAME'] : '';
					$column = isset( $row['COLUMN_NAME'] ) ? (string) $row['COLUMN_NAME'] : '';
					if ( $table === '' || $column === '' ) {
						continue;
					}

					if ( in_array( $table, $core_tables, true ) ) {
						continue;
					}

					if ( $this->should_skip_custom_search_table( $table ) ) {
						continue;
					}

					$schema[ $table ][] = $column;
				}

				foreach ( $schema as $table => $columns ) {
					$schema[ $table ] = array_values( array_unique( array_filter( array_map( 'strval', $columns ) ) ) );
					if ( empty( $schema[ $table ] ) ) {
						unset( $schema[ $table ] );
					}
				}

				set_transient( self::UNUSED_SCHEMA_TRANSIENT, $schema, HOUR_IN_SECONDS );

				return $schema;
			}

			private function table_like_match_exists( string $table, array $columns, array $needles, string $where_sql = '1=1', array $where_params = [] ): bool {
				global $wpdb;

				$columns = array_values( array_filter( array_map( 'strval', $columns ) ) );
				$needles = array_values( array_filter( array_unique( array_map( 'strval', $needles ) ) ) );
				if ( $table === '' || empty( $columns ) || empty( $needles ) ) {
					return false;
				}

				$needles = array_values(
					array_filter(
						array_map(
							static fn( string $needle ): string => trim( $needle ),
							$needles
						)
					)
				);
				if ( empty( $needles ) ) {
					return false;
				}

				$needle_chunks = array_chunk( $needles, 4 );
				foreach ( $needle_chunks as $chunk ) {
					$params = $where_params;
					$groups = [];

					foreach ( $chunk as $needle ) {
						$columns_group = [];
						foreach ( $columns as $column ) {
							$columns_group[] = $this->quote_identifier( $column ) . ' LIKE %s';
							$params[]        = '%' . $wpdb->esc_like( $needle ) . '%';
						}

						if ( ! empty( $columns_group ) ) {
							$groups[] = '(' . implode( ' OR ', $columns_group ) . ')';
						}
					}

					if ( empty( $groups ) ) {
						continue;
					}

					$sql = 'SELECT 1 FROM ' . $this->quote_identifier( $table ) . ' WHERE ' . $where_sql . ' AND (' . implode( ' OR ', $groups ) . ') LIMIT 1';
					if ( ! empty( $params ) ) {
						$sql = $wpdb->prepare( $sql, ...$params );
					}

					if ( $wpdb->get_var( $sql ) ) {
						return true;
					}
				}

				return false;
			}

			private function find_attachment_usage( int $attachment_id ): array {
				global $wpdb;

				$attached_file = (string) get_attached_file( $attachment_id );
				$relative      = (string) get_post_meta( $attachment_id, '_wp_attached_file', true );
				$post_parent   = (int) wp_get_post_parent_id( $attachment_id );

				if ( $post_parent > 0 ) {
					return [
						'used'   => true,
						'reason' => 'Attachment is attached to a parent post. Kept conservatively.',
						'source' => 'post_parent',
					];
				}

				if ( $relative === '' && $attached_file === '' ) {
					return [
						'used'   => true,
						'reason' => 'Attachment path is empty. Kept conservatively.',
						'source' => 'empty-path',
					];
				}

				if ( $attached_file !== '' && ! is_file( $attached_file ) ) {
					return [
						'used'   => true,
						'reason' => 'Attachment file is missing on disk. Kept conservatively.',
						'source' => 'missing-file',
					];
				}

				$id_string = (string) $attachment_id;
				$exact_sql = [
					[
						'sql'    => $wpdb->prepare(
							"SELECT meta_id FROM {$wpdb->postmeta} WHERE post_id <> %d AND meta_value = %s LIMIT 1",
							$attachment_id,
							$id_string
						),
						'reason' => 'Exact attachment ID match found in postmeta.',
					],
					[
						'sql'    => $wpdb->prepare(
							"SELECT option_id FROM {$wpdb->options}
								 WHERE option_name NOT LIKE '_transient_%'
								   AND option_name NOT LIKE '_site_transient_%'
								   AND option_value = %s
								 LIMIT 1",
							$id_string
						),
						'reason' => 'Exact attachment ID match found in options.',
					],
				];

				if ( ! empty( $wpdb->termmeta ) ) {
					$exact_sql[] = [
						'sql'    => $wpdb->prepare(
							"SELECT meta_id FROM {$wpdb->termmeta} WHERE meta_value = %s LIMIT 1",
							$id_string
						),
						'reason' => 'Exact attachment ID match found in termmeta.',
					];
				}

				if ( ! empty( $wpdb->usermeta ) ) {
					$exact_sql[] = [
						'sql'    => $wpdb->prepare(
							"SELECT umeta_id FROM {$wpdb->usermeta} WHERE meta_value = %s LIMIT 1",
							$id_string
						),
						'reason' => 'Exact attachment ID match found in usermeta.',
					];
				}

				foreach ( $exact_sql as $check ) {
					if ( $wpdb->get_var( (string) $check['sql'] ) ) {
						return [
							'used'   => true,
							'reason' => (string) $check['reason'],
							'source' => 'exact-id',
						];
					}
				}

				$id_needles   = $this->attachment_id_search_needles( $attachment_id );
				$path_needles = $this->attachment_relative_search_needles( $attachment_id );
				$url_needles  = $this->attachment_url_search_needles( $attachment_id );
				$core_needles = array_values( array_unique( array_merge( $id_needles, $path_needles, $url_needles ) ) );
				$custom_needles = $this->attachment_custom_table_search_needles( $attachment_id );

				$core_targets = [
					[
						'table'   => $wpdb->posts,
						'columns' => [ 'post_content', 'post_excerpt' ],
						'where'   => 'ID <> %d',
						'params'  => [ $attachment_id ],
						'reason'  => 'Reference found in posts content or excerpt.',
					],
					[
						'table'   => $wpdb->postmeta,
						'columns' => [ 'meta_value' ],
						'where'   => 'post_id <> %d',
						'params'  => [ $attachment_id ],
						'reason'  => 'Reference found in postmeta.',
					],
					[
						'table'   => $wpdb->options,
						'columns' => [ 'option_value' ],
						'where'   => "option_name NOT LIKE '_transient_%' AND option_name NOT LIKE '_site_transient_%'",
						'params'  => [],
						'reason'  => 'Reference found in options.',
					],
				];

				if ( ! empty( $wpdb->termmeta ) ) {
					$core_targets[] = [
						'table'   => $wpdb->termmeta,
						'columns' => [ 'meta_value' ],
						'where'   => '1=1',
						'params'  => [],
						'reason'  => 'Reference found in termmeta.',
					];
				}

				if ( ! empty( $wpdb->usermeta ) ) {
					$core_targets[] = [
						'table'   => $wpdb->usermeta,
						'columns' => [ 'meta_value' ],
						'where'   => '1=1',
						'params'  => [],
						'reason'  => 'Reference found in usermeta.',
					];
				}

				if ( ! empty( $wpdb->comments ) ) {
					$core_targets[] = [
						'table'   => $wpdb->comments,
						'columns' => [ 'comment_content' ],
						'where'   => '1=1',
						'params'  => [],
						'reason'  => 'Reference found in comments.',
					];
				}

				foreach ( $core_targets as $target ) {
					if ( $this->table_like_match_exists(
						(string) $target['table'],
						(array) $target['columns'],
						$core_needles,
						(string) $target['where'],
						(array) $target['params']
					) ) {
						return [
							'used'   => true,
							'reason' => (string) $target['reason'],
							'source' => (string) $target['table'],
						];
					}
				}

				$custom_schema = $this->custom_searchable_columns();
				foreach ( $custom_schema as $table => $columns ) {
					if ( $this->table_like_match_exists( (string) $table, (array) $columns, $custom_needles ) ) {
						return [
							'used'   => true,
							'reason' => 'Reference found in custom database table ' . $table . '.',
							'source' => (string) $table,
						];
					}
				}

				return [
					'used'   => false,
					'reason' => sprintf(
						'No attachment ID or upload-path references found in scanned database tables. Core + %d custom tables checked.',
						count( $custom_schema )
					),
					'source' => 'none',
				];
			}

			private function build_unused_attachment_item( int $attachment_id, array $inspection ): array {
				$file        = (string) get_attached_file( $attachment_id );
				$relative    = (string) get_post_meta( $attachment_id, '_wp_attached_file', true );
				$label       = get_the_title( $attachment_id );
				$thumb_url   = (string) wp_get_attachment_image_url( $attachment_id, 'thumbnail' );
				$url         = (string) wp_get_attachment_url( $attachment_id );
				$parent_id   = (int) wp_get_post_parent_id( $attachment_id );
				$parent_title = $parent_id > 0 ? (string) get_the_title( $parent_id ) : '';

				if ( $relative === '' && $file !== '' ) {
					$relative = $this->path_to_relative_upload( $file );
				}

				return [
					'id'           => $attachment_id,
					'title'        => $label !== '' ? $label : 'Attachment #' . $attachment_id,
					'relative'     => $relative,
					'url'          => $url,
					'thumb_url'    => $thumb_url !== '' ? $thumb_url : $url,
					'edit_url'     => (string) get_edit_post_link( $attachment_id, '' ),
					'mime'         => (string) get_post_mime_type( $attachment_id ),
					'filesize'     => ( $file !== '' && is_file( $file ) ) ? (int) ( @filesize( $file ) ?: 0 ) : 0,
					'modified'     => ( $file !== '' && is_file( $file ) ) ? (string) wp_date( 'Y-m-d H:i:s', (int) @filemtime( $file ) ) : '',
					'parent_id'    => $parent_id,
					'parent_title' => $parent_title,
					'reason'       => (string) ( $inspection['reason'] ?? '' ),
					'file_exists'  => ( $file !== '' && is_file( $file ) ) ? 1 : 0,
				];
			}

			public function ajax_prepare_url_replace(): void {
				$this->ajax_guard();

				global $wpdb;
				delete_transient( self::URL_MAP_TRANSIENT );
				$map = $this->build_url_replacement_map();

				$total_posts = (int) $wpdb->get_var( "SELECT COUNT(ID) FROM {$wpdb->posts}" );
				$total_meta  = (int) $wpdb->get_var( "SELECT COUNT(meta_id) FROM {$wpdb->postmeta}" );

				wp_send_json_success( [
					'map_count'   => count( $map ),
					'total_posts' => $total_posts,
					'total_meta'  => $total_meta,
					'total_rows'  => $total_posts + $total_meta,
					'cursor'      => [
						'phase'   => 'posts',
						'last_id' => 0,
					],
				] );
			}

			public function ajax_replace_urls_batch(): void {
				$this->ajax_guard();

				$cfg        = $this->cfg();
				$limit      = (int) $cfg['db_batch_size'];
				$dry_run    = ! empty( $_POST['dry_run'] );
				$cursor_in  = isset( $_POST['cursor'] ) && is_array( $_POST['cursor'] ) ? (array) $_POST['cursor'] : [];
				$phase      = isset( $cursor_in['phase'] ) && in_array( $cursor_in['phase'], [ 'posts', 'postmeta' ], true ) ? (string) $cursor_in['phase'] : 'posts';
				$last_id    = isset( $cursor_in['last_id'] ) ? (int) $cursor_in['last_id'] : 0;

				@set_time_limit( 20 );

				$map = $this->build_url_replacement_map();
				if ( empty( $map ) ) {
					wp_send_json_success( [
						'done'      => true,
						'cursor'    => [ 'phase' => 'done', 'last_id' => 0 ],
						'processed' => 0,
						'changed'   => 0,
						'hits'      => 0,
						'errors'    => 0,
						'log'       => [ '[-] No URL mappings available.' ],
					] );
				}

				if ( $limit < 20 ) {
					$limit = 20;
				} elseif ( $limit > 120 ) {
					$limit = 120;
				}

				if ( $phase === 'posts' ) {
					$result = $this->process_posts_replace_batch( $last_id, $limit, $map, $dry_run );
					if ( $result['done_phase'] ) {
						$cursor = [ 'phase' => 'postmeta', 'last_id' => 0 ];
						$done   = false;
					} else {
						$cursor = [ 'phase' => 'posts', 'last_id' => (int) $result['next_last_id'] ];
						$done   = false;
					}
				} else {
					$result = $this->process_postmeta_replace_batch( $last_id, $limit, $map, $dry_run );
					if ( $result['done_phase'] ) {
						$cursor = [ 'phase' => 'done', 'last_id' => 0 ];
						$done   = true;
					} else {
						$cursor = [ 'phase' => 'postmeta', 'last_id' => (int) $result['next_last_id'] ];
						$done   = false;
					}
				}

				wp_send_json_success( [
					'done'      => $done,
					'cursor'    => $cursor,
					'processed' => (int) ( $result['processed'] ?? 0 ),
					'changed'   => (int) ( $result['changed'] ?? 0 ),
					'hits'      => (int) ( $result['hits'] ?? 0 ),
					'errors'    => (int) ( $result['errors'] ?? 0 ),
					'log'       => array_values( array_slice( (array) ( $result['log'] ?? [] ), 0, 20 ) ),
					'phase'     => $phase,
					'dry_run'   => $dry_run ? 1 : 0,
					'map_count' => count( $map ),
				] );
			}

			private function ajax_guard(): void {
				check_ajax_referer( self::NONCE_ACTION, 'nonce' );

				if ( ! current_user_can( 'manage_options' ) ) {
					wp_send_json_error( [ 'message' => 'Forbidden' ], 403 );
				}
			}

			public function ajax_save_settings(): void {
				$this->ajax_guard();

				$raw = isset( $_POST['cfg'] ) && is_array( $_POST['cfg'] ) ? (array) wp_unslash( $_POST['cfg'] ) : [];
				$cfg = $this->sanitize_cfg( $raw );
				update_option( self::OPT_KEY, $cfg, false );

				wp_send_json_success( [ 'cfg' => $cfg ] );
			}

			public function ajax_scan_media(): void {
				$this->ajax_guard();

				$cfg            = $this->cfg();
				$total_supported = $this->count_supported_attachments( $cfg );

				wp_send_json_success( [
					'total_supported' => $total_supported,
					'batch_size'      => (int) $cfg['batch_size'],
				] );
			}

			public function ajax_convert_batch(): void {
				$this->ajax_guard();

				$cfg     = $this->cfg();
				$last_id = isset( $_POST['last_id'] ) ? (int) $_POST['last_id'] : 0;
				$limit   = (int) $cfg['batch_size'];
				$limit_override = isset( $_POST['limit_override'] ) ? (int) $_POST['limit_override'] : 0;
				if ( $limit_override > 0 ) {
					$limit = min( max( 1, $limit_override ), max( 1, (int) $cfg['batch_size'] ) );
				}

				@set_time_limit( 20 );

				$ids = $this->query_next_attachment_ids( $last_id, $limit, $cfg );
				if ( empty( $ids ) ) {
					wp_send_json_success( [
						'done'        => true,
						'last_id'     => $last_id,
						'batch_total' => 0,
						'converted'   => 0,
						'skipped'     => 0,
						'errors'      => 0,
						'bytes_saved' => 0,
						'log'         => [],
					] );
				}

				$converted  = 0;
				$skipped    = 0;
				$errors     = 0;
				$bytes_saved = 0;
				$log        = [];

				foreach ( $ids as $id ) {
					$last_id = max( $last_id, (int) $id );
					$label   = get_the_title( $id );
					$label   = $label !== '' ? $label : ( 'Attachment #' . $id );

					$one = $this->convert_attachment( $id, $cfg );
					$status = (string) ( $one['status'] ?? 'skipped' );
					$message = (string) ( $one['message'] ?? '' );

					if ( $status === 'converted' ) {
						$converted ++;
						$bytes_saved += (int) ( $one['bytes_saved'] ?? 0 );
						$log[] = sprintf( '[OK] %s: %s', $label, $message );
					} elseif ( $status === 'error' ) {
						$errors ++;
						$log[] = sprintf( '[ERR] %s: %s', $label, $message );
					} else {
						$skipped ++;
						$log[] = sprintf( '[-] %s: %s', $label, $message );
					}
				}

				$has_more = ! empty( $this->query_next_attachment_ids( $last_id, 1, $cfg ) );

				wp_send_json_success( [
					'done'        => ! $has_more,
					'last_id'     => $last_id,
					'batch_total' => count( $ids ),
					'converted'   => $converted,
					'skipped'     => $skipped,
					'errors'      => $errors,
					'bytes_saved' => $bytes_saved,
					'log'         => $log,
				] );
			}

			public function ajax_prepare_unused_scan(): void {
				$this->ajax_guard();

				$schema        = $this->custom_searchable_columns();
				$custom_tables = count( $schema );
				$custom_columns = 0;
				foreach ( $schema as $columns ) {
					$custom_columns += count( (array) $columns );
				}

				wp_send_json_success( [
					'total_images'    => $this->count_all_image_attachments(),
					'batch_size'      => $this->unused_scan_batch_size(),
					'cursor'          => [ 'last_id' => 0 ],
					'custom_tables'   => $custom_tables,
					'custom_columns'  => $custom_columns,
					'search_strategy' => 'exact-id, core text columns, selected custom text columns',
				] );
			}

			public function ajax_scan_unused_batch(): void {
				$this->ajax_guard();

				$last_id = isset( $_POST['last_id'] ) ? (int) $_POST['last_id'] : 0;
				$limit   = isset( $_POST['limit'] ) ? (int) $_POST['limit'] : $this->unused_scan_batch_size();
				$limit   = min( 20, max( 1, $limit ) );

				@set_time_limit( 20 );

				$ids = $this->query_next_image_attachment_ids( $last_id, $limit );
				if ( empty( $ids ) ) {
					wp_send_json_success( [
						'done'        => true,
						'last_id'     => $last_id,
						'processed'   => 0,
						'unused'      => 0,
						'used'        => 0,
						'errors'      => 0,
						'items'       => [],
						'log'         => [],
					] );
				}

				$processed = 0;
				$unused    = 0;
				$used      = 0;
				$errors    = 0;
				$items     = [];
				$log       = [];

				foreach ( $ids as $attachment_id ) {
					$attachment_id = (int) $attachment_id;
					$last_id       = max( $last_id, $attachment_id );
					$processed ++;

					try {
						$inspection = $this->find_attachment_usage( $attachment_id );
						$title      = get_the_title( $attachment_id );
						$title      = $title !== '' ? $title : ( 'Attachment #' . $attachment_id );

						if ( ! empty( $inspection['used'] ) ) {
							$used ++;
							if ( count( $log ) < 12 ) {
								$log[] = sprintf( '[-] %s kept: %s', $title, (string) ( $inspection['reason'] ?? 'Usage found' ) );
							}
							continue;
						}

						$unused ++;
						$items[] = $this->build_unused_attachment_item( $attachment_id, $inspection );
						if ( count( $log ) < 12 ) {
							$log[] = sprintf( '[OK] %s marked unused', $title );
						}
					} catch ( Throwable $e ) {
						$errors ++;
						if ( count( $log ) < 12 ) {
							$log[] = sprintf( '[ERR] Attachment #%d: %s', $attachment_id, $e->getMessage() );
						}
					}
				}

				$has_more = ! empty( $this->query_next_image_attachment_ids( $last_id, 1 ) );

				wp_send_json_success( [
					'done'      => ! $has_more,
					'last_id'   => $last_id,
					'processed' => $processed,
					'unused'    => $unused,
					'used'      => $used,
					'errors'    => $errors,
					'items'     => $items,
					'log'       => $log,
				] );
			}

			public function ajax_delete_unused_batch(): void {
				$this->ajax_guard();

				$raw_ids = isset( $_POST['ids'] ) && is_array( $_POST['ids'] ) ? (array) wp_unslash( $_POST['ids'] ) : [];
				$ids     = array_values( array_unique( array_filter( array_map( 'intval', $raw_ids ) ) ) );
				$ids     = array_slice( $ids, 0, $this->unused_delete_batch_size() );

				if ( empty( $ids ) ) {
					wp_send_json_success( [
						'deleted_ids' => [],
						'skipped'     => [],
						'errors'      => 0,
						'log'         => [ '[-] No attachment IDs received.' ],
					] );
				}

				@set_time_limit( 20 );

				$deleted_ids = [];
				$skipped     = [];
				$errors      = 0;
				$log         = [];

				foreach ( $ids as $attachment_id ) {
					$attachment_id = (int) $attachment_id;
					$post          = get_post( $attachment_id );
					if ( ! $post || $post->post_type !== 'attachment' ) {
						$skipped[] = [
							'id'     => $attachment_id,
							'reason' => 'Attachment not found.',
						];
						if ( count( $log ) < 12 ) {
							$log[] = sprintf( '[-] Attachment #%d skipped: not found', $attachment_id );
						}
						continue;
					}

					$mime = (string) get_post_mime_type( $attachment_id );
					if ( ! str_starts_with( $mime, 'image/' ) ) {
						$skipped[] = [
							'id'     => $attachment_id,
							'reason' => 'Attachment is not an image.',
						];
						if ( count( $log ) < 12 ) {
							$log[] = sprintf( '[-] %s skipped: not an image', get_the_title( $attachment_id ) ?: ( 'Attachment #' . $attachment_id ) );
						}
						continue;
					}

					try {
						$inspection = $this->find_attachment_usage( $attachment_id );
						if ( ! empty( $inspection['used'] ) ) {
							$skipped[] = [
								'id'     => $attachment_id,
								'reason' => (string) ( $inspection['reason'] ?? 'Usage found before delete.' ),
							];
							if ( count( $log ) < 12 ) {
								$log[] = sprintf( '[-] %s skipped: %s', get_the_title( $attachment_id ) ?: ( 'Attachment #' . $attachment_id ), (string) ( $inspection['reason'] ?? 'Usage found before delete.' ) );
							}
							continue;
						}
					} catch ( Throwable $e ) {
						$errors ++;
						if ( count( $log ) < 12 ) {
							$log[] = sprintf( '[ERR] %s: %s', get_the_title( $attachment_id ) ?: ( 'Attachment #' . $attachment_id ), $e->getMessage() );
						}
						continue;
					}

					$deleted = wp_delete_attachment( $attachment_id, true );
					if ( $deleted ) {
						$deleted_ids[] = $attachment_id;
						if ( count( $log ) < 12 ) {
							$log[] = sprintf( '[OK] %s deleted', get_the_title( $attachment_id ) ?: ( 'Attachment #' . $attachment_id ) );
						}
						continue;
					}

					$errors ++;
					if ( count( $log ) < 12 ) {
						$log[] = sprintf( '[ERR] %s: delete failed', get_the_title( $attachment_id ) ?: ( 'Attachment #' . $attachment_id ) );
					}
				}

				wp_send_json_success( [
					'deleted_ids' => $deleted_ids,
					'skipped'     => $skipped,
					'errors'      => $errors,
					'log'         => $log,
				] );
			}

			public function menu(): void {
				add_options_page(
					'Uploads WebP Convert',
					'<span style="display:flex;align-items:center;gap:6px">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24">
                          <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m14.3 16-1.7-1.7q-1-1.1-1.7-1.4H9.7q-.6.2-1.7 1.4l-4 4m10.3-2.4.3-.3q1-1.2 1.7-1.3h1.2q.6.1 1.7 1.4l.8.8m-5.7-.6 4 4m0 0-1.5.1H7.2q-1.6 0-2.1-.2a1.8 1.8 0 0 1-1-1.5M18.2 20a2 2 0 0 0 1.5-1q.3-.6.2-2.2v-.3M12.5 4H7.2q-1.6 0-2.1.2a2 2 0 0 0-.9.9Q4 5.6 4 7.2v11.1m16-6.8v5M14 10l2-.4h.4l.2-.2.2-.2L21 5a1.4 1.4 0 0 0-2-2l-4.2 4.2-.2.2-.1.2-.1.4z"/>
                        </svg>
                       Uploads WebP
                    </span>',
					'manage_options',
					self::PAGE_SLUG,
					[ $this, 'page' ]
				);
			}

			public function admin_assets( string $hook ): void {
				if ( $hook !== 'settings_page_' . self::PAGE_SLUG ) {
					return;
				}

				wp_enqueue_script( 'jquery' );
			}

			public function page(): void {
				$cfg   = $this->cfg();
				$nonce = wp_create_nonce( self::NONCE_ACTION );
				?>
                <div class="sp-webp-admin">
                    <div class="sp-webp-admin__header">
                        <div class="sp-webp-admin__logo">
							<span class="sp-webp-admin__logo-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" fill="none" viewBox="0 0 24 24">
                                  <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m14.3 16-1.7-1.7q-1-1.1-1.7-1.4H9.7q-.6.2-1.7 1.4l-4 4m10.3-2.4.3-.3q1-1.2 1.7-1.3h1.2q.6.1 1.7 1.4l.8.8m-5.7-.6 4 4m0 0-1.5.1H7.2q-1.6 0-2.1-.2a1.8 1.8 0 0 1-1-1.5M18.2 20a2 2 0 0 0 1.5-1q.3-.6.2-2.2v-.3M12.5 4H7.2q-1.6 0-2.1.2a2 2 0 0 0-.9.9Q4 5.6 4 7.2v11.1m16-6.8v5M14 10l2-.4h.4l.2-.2.2-.2L21 5a1.4 1.4 0 0 0-2-2l-4.2 4.2-.2.2-.1.2-.1.4z"/>
                                </svg>
                            </span>
                            <span class="sp-webp-admin__logo-text">Uploads WebP Convert</span>
                        </div>
                        <div class="sp-webp-admin__actions">
                            <button type="button" class="sp-webp-btn sp-webp-btn--ghost" id="sp-webp-scan">Scan media</button>
                            <button type="button" class="sp-webp-btn sp-webp-btn--primary" id="sp-webp-start">Start bulk convert</button>
                            <button type="button" class="sp-webp-btn" id="sp-webp-stop" disabled>Stop</button>
                            <button type="button" class="sp-webp-btn" id="sp-webp-reset-progress">Reset saved progress</button>
                            <button type="button" class="sp-webp-btn sp-webp-btn--primary" id="sp-webp-save">Save settings</button>
                            <span class="sp-webp-saved" id="sp-webp-saved">Saved</span>
                        </div>
                    </div>

                    <div class="sp-webp-admin__body">
                        <aside class="sp-webp-sidebar">
                            <div class="sp-webp-panel">
                                <div class="sp-webp-panel__title">Conversion settings</div>

                                <div class="sp-webp-field">
                                    <div class="sp-webp-toggle-row">
                                        <span class="sp-webp-label" style="margin:0">Enable on upload</span>
                                        <label class="sp-webp-ios-toggle">
                                            <input type="checkbox" id="cfg-enabled-upload" <?php checked( ! empty( $cfg['enabled_upload'] ) ); ?>>
                                            <span class="sp-webp-ios-track"><span class="sp-webp-ios-thumb"></span></span>
                                        </label>
                                    </div>
                                    <p class="sp-webp-hint">Automatic conversion for newly uploaded images.</p>
                                </div>

                                <div class="sp-webp-field sp-webp-field--range">
                                    <div class="sp-webp-range-header">
                                        <label class="sp-webp-label">Quality</label>
                                        <span class="sp-webp-range-val" id="cfg-quality-v"><?php echo (int) $cfg['quality']; ?></span>
                                    </div>
                                    <input type="range" id="cfg-quality" class="sp-webp-range" min="60" max="100" value="<?php echo (int) $cfg['quality']; ?>" oninput="document.getElementById('cfg-quality-v').textContent=this.value">
                                    <p class="sp-webp-hint">90 by default: high quality with good compression.</p>
                                </div>

                                <div class="sp-webp-field sp-webp-field--range">
                                    <div class="sp-webp-range-header">
                                        <label class="sp-webp-label">Max side (px)</label>
                                        <span class="sp-webp-range-val" id="cfg-max-side-v"><?php echo (int) $cfg['max_side']; ?>px</span>
                                    </div>
                                    <input type="range" id="cfg-max-side" class="sp-webp-range" min="320" max="8000" step="10" value="<?php echo (int) $cfg['max_side']; ?>" oninput="document.getElementById('cfg-max-side-v').textContent=this.value+'px'">
                                    <p class="sp-webp-hint">Large images are resized proportionally before conversion.</p>
                                </div>

                                <div class="sp-webp-field">
                                    <div class="sp-webp-toggle-row">
                                        <span class="sp-webp-label" style="margin:0">Delete original source file</span>
                                        <label class="sp-webp-ios-toggle">
                                            <input type="checkbox" id="cfg-delete-original" <?php checked( ! empty( $cfg['delete_original'] ) ); ?>>
                                            <span class="sp-webp-ios-track"><span class="sp-webp-ios-thumb"></span></span>
                                        </label>
                                    </div>
                                    <p class="sp-webp-hint">Recommended for minimum disk usage.</p>
                                </div>

                                <div class="sp-webp-field">
                                    <div class="sp-webp-toggle-row">
                                        <span class="sp-webp-label" style="margin:0">Skip animated GIF</span>
                                        <label class="sp-webp-ios-toggle">
                                            <input type="checkbox" id="cfg-skip-animated" <?php checked( ! empty( $cfg['skip_animated_gif'] ) ); ?>>
                                            <span class="sp-webp-ios-track"><span class="sp-webp-ios-thumb"></span></span>
                                        </label>
                                    </div>
                                    <p class="sp-webp-hint">Prevents losing animation unexpectedly.</p>
                                </div>

                                <div class="sp-webp-field">
                                    <label class="sp-webp-label">Batch size</label>
                                    <input type="number" id="cfg-batch-size" class="sp-webp-input" min="1" max="100" value="<?php echo (int) $cfg['batch_size']; ?>">
                                    <p class="sp-webp-hint">Number of attachments processed per request.</p>
                                </div>

                            </div>

                            <div class="sp-webp-panel sp-webp-panel--usage">
                                <div class="sp-webp-panel__title">Safety notes</div>
                                <p class="sp-webp-hint">Run bulk conversion on staging first when possible.</p>
                                <p class="sp-webp-hint">If old image URLs are hardcoded, convert in small batches and verify pages.</p>
                                <p class="sp-webp-hint">For max disk savings keep "Delete original source file" enabled.</p>
                            </div>
                        </aside>

                        <main class="sp-webp-main">
                            <div class="sp-webp-panel">
                                <div class="sp-webp-panel__title">Bulk conversion status</div>
                                <div class="sp-webp-stats">
                                    <div class="sp-webp-stat"><div class="sp-webp-stat__label">Total in queue</div><div class="sp-webp-stat__value" id="spc-total">0</div></div>
                                    <div class="sp-webp-stat"><div class="sp-webp-stat__label">Converted</div><div class="sp-webp-stat__value" id="spc-converted">0</div></div>
                                    <div class="sp-webp-stat"><div class="sp-webp-stat__label">Skipped</div><div class="sp-webp-stat__value" id="spc-skipped">0</div></div>
                                    <div class="sp-webp-stat"><div class="sp-webp-stat__label">Errors</div><div class="sp-webp-stat__value" id="spc-errors">0</div></div>
                                    <div class="sp-webp-stat"><div class="sp-webp-stat__label">Saved</div><div class="sp-webp-stat__value" id="spc-saved-size">0 B</div></div>
                                </div>

                                <div class="sp-webp-progress-wrap">
                                    <div class="sp-webp-progress"><span id="spc-progress-bar"></span></div>
                                    <div class="sp-webp-progress-meta">
                                        <span id="spc-progress-text">0%</span>
                                        <span id="spc-status">Click "Scan media" to start.</span>
                                    </div>
                                </div>

                                <div class="sp-webp-log" id="sp-webp-log"></div>
                            </div>

                            <div class="sp-webp-panel" style="margin-top:16px;">
                                <div class="sp-webp-panel__title">Database URL replacement (ACF + Editor)</div>
                                <div class="sp-webp-progress-meta" style="margin-bottom:10px;">
                                    <span id="spr-progress-text">0%</span>
                                    <span id="spr-status">Click to replace old image URLs with WebP URLs.</span>
                                </div>
                                <div class="sp-webp-progress" style="margin-bottom:12px;"><span id="spr-progress-bar"></span></div>

                                <div class="sp-webp-stats" style="grid-template-columns: repeat(4, minmax(120px, 1fr)); margin-bottom:12px;">
                                    <div class="sp-webp-stat"><div class="sp-webp-stat__label">Rows scanned</div><div class="sp-webp-stat__value" id="spr-processed">0</div></div>
                                    <div class="sp-webp-stat"><div class="sp-webp-stat__label">Rows changed</div><div class="sp-webp-stat__value" id="spr-changed">0</div></div>
                                    <div class="sp-webp-stat"><div class="sp-webp-stat__label">URL hits</div><div class="sp-webp-stat__value" id="spr-hits">0</div></div>
                                    <div class="sp-webp-stat"><div class="sp-webp-stat__label">Map size</div><div class="sp-webp-stat__value" id="spr-map">0</div></div>
                                </div>

                                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px;">
                                    <button type="button" class="sp-webp-btn sp-webp-btn--primary" id="sp-webp-replace-all">Replace all URLs to WebP</button>
                                </div>

                                <div class="sp-webp-log" id="sp-webp-replace-log" style="height:220px;"></div>
                            </div>

                            <div class="sp-webp-panel" style="margin-top:16px;">
                                <div class="sp-webp-panel__title">Unused Images Finder</div>
                                <div class="sp-webp-progress-meta" style="margin-bottom:10px;">
                                    <span id="spu-progress-text">0%</span>
                                    <span id="spu-status">Scan the media library and check the database before deleting.</span>
                                </div>
                                <div class="sp-webp-progress" style="margin-bottom:12px;"><span id="spu-progress-bar"></span></div>

                                <div class="sp-webp-stats" style="grid-template-columns: repeat(5, minmax(120px, 1fr)); margin-bottom:12px;">
                                    <div class="sp-webp-stat"><div class="sp-webp-stat__label">Images scanned</div><div class="sp-webp-stat__value" id="spu-processed">0</div></div>
                                    <div class="sp-webp-stat"><div class="sp-webp-stat__label">Unused found</div><div class="sp-webp-stat__value" id="spu-unused">0</div></div>
                                    <div class="sp-webp-stat"><div class="sp-webp-stat__label">Used / kept</div><div class="sp-webp-stat__value" id="spu-used">0</div></div>
                                    <div class="sp-webp-stat"><div class="sp-webp-stat__label">Errors</div><div class="sp-webp-stat__value" id="spu-errors">0</div></div>
                                    <div class="sp-webp-stat"><div class="sp-webp-stat__label">Custom tables</div><div class="sp-webp-stat__value" id="spu-custom-tables">0</div></div>
                                </div>

                                <div class="sp-webp-unused-actions">
                                    <button type="button" class="sp-webp-btn sp-webp-btn--primary" id="sp-webp-unused-scan">Scan unused images</button>
                                    <button type="button" class="sp-webp-btn sp-webp-btn--danger" id="sp-webp-unused-delete-selected" disabled>Delete selected</button>
                                    <button type="button" class="sp-webp-btn sp-webp-btn--danger" id="sp-webp-unused-delete-all" disabled>Delete all found</button>
                                    <button type="button" class="sp-webp-btn" id="sp-webp-unused-clear">Clear results</button>
                                </div>

                                <p class="sp-webp-hint">The scan is conservative: it keeps files attached to parent posts, keeps pathless or missing-file attachments, and checks exact IDs plus path/URL references in core and custom text columns before marking anything unused.</p>

                                <div class="sp-webp-unused-table-wrap">
                                    <table class="sp-webp-unused-table">
                                        <thead>
                                        <tr>
                                            <th class="sp-webp-unused-table__check"><input type="checkbox" id="sp-webp-unused-select-all" disabled></th>
                                            <th>Preview</th>
                                            <th>Attachment</th>
                                            <th>Why considered unused</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                        </thead>
                                        <tbody id="sp-webp-unused-body">
                                        <tr class="sp-webp-unused-empty">
                                            <td colspan="6">No unused images found yet.</td>
                                        </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="sp-webp-log" id="sp-webp-unused-log" style="height:220px;margin-top:12px;"></div>
                            </div>
                        </main>
                    </div>
                </div>

                <style>
                    .sp-webp-admin * {
                        box-sizing: border-box;
                    }

                    #wpcontent:has(.sp-webp-admin) {
                        padding: 0;
                    }

                    .sp-webp-admin {
                        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                        font-size: 13px;
                        color: #1d2327;
                        background: #f0f0f1;
                        min-height: 100vh;
                    }

                    .sp-webp-admin__header {
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
                        box-shadow: 0 1px 3px rgba(0, 0, 0, .06);
                    }

                    .sp-webp-admin__logo {
                        display: flex;
                        align-items: center;
                        gap: 8px;
                    }

                    .sp-webp-admin__logo-text {
                        font-size: 16px;
                        font-weight: 700;
                    }

                    .sp-webp-admin__actions {
                        display: flex;
                        align-items: center;
                        gap: 8px;
                    }

                    .sp-webp-admin__body {
                        display: grid;
                        grid-template-columns: 320px 1fr;
                        min-height: calc(100vh - 88px);
                    }

                    .sp-webp-sidebar {
                        background: #fff;
                        border-right: 1px solid #dcdcde;
                        padding: 20px;
                        display: flex;
                        flex-direction: column;
                        gap: 16px;
                    }

                    .sp-webp-main {
                        padding: 20px;
                    }

                    .sp-webp-panel {
                        background: #fff;
                        border: 1px solid #dcdcde;
                        border-radius: 8px;
                        padding: 18px 20px;
                    }

                    .sp-webp-panel--usage {
                        background: #f8f8f8;
                    }

                    .sp-webp-panel__title {
                        font-size: 12px;
                        font-weight: 700;
                        text-transform: uppercase;
                        letter-spacing: .6px;
                        color: #787c82;
                        margin-bottom: 14px;
                        padding-bottom: 10px;
                        border-bottom: 1px solid #f0f0f1;
                    }

                    .sp-webp-field {
                        margin-bottom: 14px;
                    }

                    .sp-webp-field:last-child {
                        margin-bottom: 0;
                    }

                    .sp-webp-label {
                        display: block;
                        font-size: 12px;
                        font-weight: 600;
                        color: #444;
                        margin-bottom: 5px;
                    }

                    .sp-webp-input {
                        width: 100%;
                        padding: 7px 10px;
                        border: 1px solid #dcdcde;
                        border-radius: 5px;
                        font-size: 13px;
                    }

                    .sp-webp-input:focus {
                        outline: none;
                        border-color: #2271b1;
                        box-shadow: 0 0 0 1px #2271b1;
                    }

                    .sp-webp-hint {
                        font-size: 11px;
                        color: #777;
                        margin-top: 4px;
                        line-height: 1.4;
                    }

                    .sp-webp-toggle-row {
                        display: flex;
                        align-items: center;
                        justify-content: space-between;
                    }

                    .sp-webp-ios-toggle {
                        position: relative;
                        display: inline-block;
                        cursor: pointer;
                    }

                    .sp-webp-ios-toggle input {
                        position: absolute;
                        opacity: 0;
                        width: 0;
                        height: 0;
                    }

                    .sp-webp-ios-track {
                        display: block;
                        width: 40px;
                        height: 22px;
                        background: #c3c4c7;
                        border-radius: 22px;
                        transition: background .2s;
                        position: relative;
                    }

                    .sp-webp-ios-thumb {
                        position: absolute;
                        top: 2px;
                        left: 2px;
                        width: 18px;
                        height: 18px;
                        background: #fff;
                        border-radius: 50%;
                        transition: left .2s;
                        box-shadow: 0 1px 3px rgba(0, 0, 0, .25);
                    }

                    .sp-webp-ios-toggle input:checked ~ .sp-webp-ios-track {
                        background: #2271b1;
                    }

                    .sp-webp-ios-toggle input:checked ~ .sp-webp-ios-track .sp-webp-ios-thumb {
                        left: 20px;
                    }

                    .sp-webp-range-header {
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                    }

                    .sp-webp-range-val {
                        font-size: 12px;
                        font-weight: 700;
                        color: #2271b1;
                    }

                    .sp-webp-range {
                        width: 100%;
                        height: 4px;
                        appearance: none;
                        background: #e0e0e0;
                        border-radius: 2px;
                        outline: none;
                    }

                    .sp-webp-range::-webkit-slider-thumb {
                        appearance: none;
                        width: 16px;
                        height: 16px;
                        border-radius: 50%;
                        background: #2271b1;
                        cursor: pointer;
                    }

                    .sp-webp-range::-moz-range-thumb {
                        width: 16px;
                        height: 16px;
                        border: none;
                        border-radius: 50%;
                        background: #2271b1;
                        cursor: pointer;
                    }

                    .sp-webp-btn {
                        display: inline-flex;
                        align-items: center;
                        padding: 7px 12px;
                        border-radius: 5px;
                        border: 1px solid #dcdcde;
                        background: #fff;
                        color: #1d2327;
                        cursor: pointer;
                        font-size: 13px;
                    }

                    .sp-webp-btn--primary {
                        background: #2271b1;
                        border-color: #2271b1;
                        color: #fff;
                    }

                    .sp-webp-btn--ghost {
                        background: transparent;
                        border-color: #2271b1;
                        color: #2271b1;
                    }

                    .sp-webp-btn:disabled {
                        opacity: .45;
                        cursor: not-allowed;
                    }

                    .sp-webp-saved {
                        font-size: 12px;
                        font-weight: 600;
                        color: #00a32a;
                        opacity: 0;
                        transition: opacity .2s;
                    }

                    .sp-webp-saved.show {
                        opacity: 1;
                    }

                    .sp-webp-stats {
                        display: grid;
                        grid-template-columns: repeat(5, minmax(120px, 1fr));
                        gap: 10px;
                        margin-bottom: 16px;
                    }

                    .sp-webp-stat {
                        padding: 10px;
                        border: 1px solid #dcdcde;
                        border-radius: 6px;
                        background: #fff;
                    }

                    .sp-webp-stat__label {
                        font-size: 11px;
                        color: #777;
                        margin-bottom: 4px;
                    }

                    .sp-webp-stat__value {
                        font-size: 16px;
                        font-weight: 700;
                    }

                    .sp-webp-progress-wrap {
                        margin-bottom: 12px;
                    }

                    .sp-webp-progress {
                        width: 100%;
                        height: 8px;
                        background: #e6e7e8;
                        border-radius: 999px;
                        overflow: hidden;
                    }

                    .sp-webp-progress span {
                        display: block;
                        height: 100%;
                        width: 0;
                        background: linear-gradient(90deg, #2271b1 0%, #2b8bd5 100%);
                        transition: width .2s;
                    }

                    .sp-webp-progress-meta {
                        display: flex;
                        justify-content: space-between;
                        font-size: 12px;
                        color: #666;
                        margin-top: 8px;
                    }

                    .sp-webp-log {
                        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
                        font-size: 12px;
                        line-height: 1.4;
                        background: #0f172a;
                        color: #d5d9e2;
                        border-radius: 8px;
                        padding: 12px;
                        height: 320px;
                        overflow: auto;
                        white-space: pre-wrap;
                    }

                    .sp-webp-btn--danger {
                        background: #b42318;
                        border-color: #b42318;
                        color: #fff;
                    }

                    .sp-webp-unused-actions {
                        display: flex;
                        gap: 8px;
                        flex-wrap: wrap;
                        margin-bottom: 12px;
                    }

                    .sp-webp-unused-table-wrap {
                        border: 1px solid #dcdcde;
                        border-radius: 8px;
                        overflow: hidden;
                        background: #fff;
                    }

                    .sp-webp-unused-table {
                        width: 100%;
                        border-collapse: collapse;
                    }

                    .sp-webp-unused-table th,
                    .sp-webp-unused-table td {
                        padding: 10px 12px;
                        border-bottom: 1px solid #f0f0f1;
                        text-align: left;
                        vertical-align: top;
                    }

                    .sp-webp-unused-table thead th {
                        font-size: 11px;
                        text-transform: uppercase;
                        letter-spacing: .4px;
                        color: #666;
                        background: #f8f8f8;
                    }

                    .sp-webp-unused-table tbody tr:last-child td {
                        border-bottom: none;
                    }

                    .sp-webp-unused-table__check {
                        width: 40px;
                    }

                    .sp-webp-unused-preview {
                        width: 56px;
                        height: 56px;
                        border-radius: 8px;
                        object-fit: cover;
                        border: 1px solid #dcdcde;
                        background: #f8f8f8;
                        display: block;
                    }

                    .sp-webp-unused-title {
                        font-weight: 600;
                        margin-bottom: 4px;
                        max-width: 400px;
                        word-wrap: break-word;
                    }

                    .sp-webp-unused-meta {
                        font-size: 12px;
                        color: #666;
                        line-height: 1.45;
                        max-width: 400px;
                        word-wrap: break-word;
                    }

                    .sp-webp-unused-meta a {
                        color: #2271b1;
                        text-decoration: none;
                    }

                    .sp-webp-unused-status {
                        display: inline-flex;
                        align-items: center;
                        padding: 4px 8px;
                        border-radius: 999px;
                        font-size: 11px;
                        font-weight: 700;
                        background: #ecfdf3;
                        color: #027a48;
                    }

                    .sp-webp-unused-status--deleted {
                        background: #f3f4f6;
                        color: #475467;
                    }

                    .sp-webp-unused-status--skipped {
                        background: #fffaeb;
                        color: #b54708;
                    }

                    .sp-webp-unused-row--deleted {
                        opacity: .55;
                    }

                    .sp-webp-unused-empty td {
                        font-size: 13px;
                        color: #666;
                        text-align: center;
                        padding: 18px 12px;
                    }

                    @media (max-width: 1200px) {
                        .sp-webp-admin__body {
                            grid-template-columns: 1fr;
                        }

                        .sp-webp-sidebar {
                            border-right: none;
                            border-bottom: 1px solid #dcdcde;
                        }

                        .sp-webp-stats {
                            grid-template-columns: repeat(2, minmax(120px, 1fr));
                        }

                        .sp-webp-unused-table-wrap {
                            overflow: auto;
                        }
                    }
                </style>

                <script>
                    (function ($) {
                        const nonce = <?php echo wp_json_encode( $nonce ); ?>;
                        const STORAGE_KEY = 'sp_webp_bulk_progress_v1';
                        const STORAGE_TTL = 1000 * 60 * 60 * 24;
                        const state = {
                            total: 0,
                            lastId: 0,
                            processed: 0,
                            converted: 0,
                            skipped: 0,
                            errors: 0,
                            bytesSaved: 0
                        };
                        const convertNet = {retry: 0, maxRetry: 8, batchLimit: 20};
                        const replaceState = {
                            totalRows: 0,
                            processed: 0,
                            changed: 0,
                            hits: 0,
                            errors: 0,
                            mapCount: 0,
                            cursor: {phase: 'posts', last_id: 0}
                        };
                        const replaceNet = {retry: 0, maxRetry: 8};
                        const unusedState = {
                            total: 0,
                            lastId: 0,
                            processed: 0,
                            unused: 0,
                            used: 0,
                            errors: 0,
                            customTables: 0,
                            customColumns: 0,
                            deleteQueue: [],
                            deleteTotal: 0,
                            deleted: 0,
                            deleteSkipped: 0
                        };
                        const unusedNet = {retry: 0, maxRetry: 6, batchLimit: 8, deleteRetry: 0, deleteMaxRetry: 4, deleteBatch: 12};
                        const runState = {running: false, mode: ''};
                        let persistSuspended = false;
                        const storage = (function () {
                            try {
                                if (!window.localStorage) return null;
                                const probe = '__sp_webp_probe__';
                                window.localStorage.setItem(probe, '1');
                                window.localStorage.removeItem(probe);
                                return window.localStorage;
                            } catch (e) {
                                return null;
                            }
                        })();

                        const $saved = $('#sp-webp-saved');
                        const $status = $('#spc-status');
                        const $log = $('#sp-webp-log');
                        const $replaceStatus = $('#spr-status');
                        const $replaceLog = $('#sp-webp-replace-log');
                        const $unusedStatus = $('#spu-status');
                        const $unusedLog = $('#sp-webp-unused-log');
                        const $unusedBody = $('#sp-webp-unused-body');
                        const $unusedSelectAll = $('#sp-webp-unused-select-all');

                        function toInt(value, fallback) {
                            const n = Number(value);
                            return Number.isFinite(n) ? Math.trunc(n) : fallback;
                        }

                        function cleanCursor(cursor) {
                            const safe = cursor && typeof cursor === 'object' ? cursor : {};
                            const phaseRaw = String(safe.phase || 'posts');
                            const phase = ['posts', 'postmeta', 'done'].includes(phaseRaw) ? phaseRaw : 'posts';
                            return {
                                phase,
                                last_id: Math.max(0, toInt(safe.last_id, 0))
                            };
                        }

                        function buildStoragePayload() {
                            return {
                                v: 1,
                                ts: Date.now(),
                                state: {
                                    total: Math.max(0, toInt(state.total, 0)),
                                    lastId: Math.max(0, toInt(state.lastId, 0)),
                                    processed: Math.max(0, toInt(state.processed, 0)),
                                    converted: Math.max(0, toInt(state.converted, 0)),
                                    skipped: Math.max(0, toInt(state.skipped, 0)),
                                    errors: Math.max(0, toInt(state.errors, 0)),
                                    bytesSaved: Math.max(0, toInt(state.bytesSaved, 0))
                                },
                                convertNet: {
                                    batchLimit: Math.max(1, toInt(convertNet.batchLimit, 20))
                                },
                                replaceState: {
                                    totalRows: Math.max(0, toInt(replaceState.totalRows, 0)),
                                    processed: Math.max(0, toInt(replaceState.processed, 0)),
                                    changed: Math.max(0, toInt(replaceState.changed, 0)),
                                    hits: Math.max(0, toInt(replaceState.hits, 0)),
                                    errors: Math.max(0, toInt(replaceState.errors, 0)),
                                    mapCount: Math.max(0, toInt(replaceState.mapCount, 0)),
                                    cursor: cleanCursor(replaceState.cursor)
                                },
                                runState: {
                                    running: !!runState.running,
                                    mode: runState.mode === 'replace' ? 'replace' : (runState.mode === 'convert' ? 'convert' : '')
                                }
                            };
                        }

                        function persistProgress() {
                            if (persistSuspended) return;
                            if (!storage) return;
                            try {
                                storage.setItem(STORAGE_KEY, JSON.stringify(buildStoragePayload()));
                            } catch (e) {
                                // ignore quota/storage errors
                            }
                        }

                        function clearPersistedProgress() {
                            if (!storage) return;
                            try {
                                storage.removeItem(STORAGE_KEY);
                            } catch (e) {
                                // ignore storage errors
                            }
                        }

                        function restoreProgress() {
                            if (!storage) return null;
                            let parsed = null;
                            try {
                                const raw = storage.getItem(STORAGE_KEY);
                                if (!raw) return null;
                                parsed = JSON.parse(raw);
                            } catch (e) {
                                clearPersistedProgress();
                                return null;
                            }

                            if (!parsed || typeof parsed !== 'object') {
                                clearPersistedProgress();
                                return null;
                            }

                            const ts = toInt(parsed.ts, 0);
                            if (ts <= 0 || (Date.now() - ts) > STORAGE_TTL) {
                                clearPersistedProgress();
                                return null;
                            }

                            const savedState = parsed.state && typeof parsed.state === 'object' ? parsed.state : {};
                            const savedReplaceState = parsed.replaceState && typeof parsed.replaceState === 'object' ? parsed.replaceState : {};
                            const savedConvertNet = parsed.convertNet && typeof parsed.convertNet === 'object' ? parsed.convertNet : {};
                            const savedRun = parsed.runState && typeof parsed.runState === 'object' ? parsed.runState : {};

                            state.total = Math.max(0, toInt(savedState.total, state.total));
                            state.lastId = Math.max(0, toInt(savedState.lastId, state.lastId));
                            state.processed = Math.max(0, toInt(savedState.processed, state.processed));
                            state.converted = Math.max(0, toInt(savedState.converted, state.converted));
                            state.skipped = Math.max(0, toInt(savedState.skipped, state.skipped));
                            state.errors = Math.max(0, toInt(savedState.errors, state.errors));
                            state.bytesSaved = Math.max(0, toInt(savedState.bytesSaved, state.bytesSaved));

                            replaceState.totalRows = Math.max(0, toInt(savedReplaceState.totalRows, replaceState.totalRows));
                            replaceState.processed = Math.max(0, toInt(savedReplaceState.processed, replaceState.processed));
                            replaceState.changed = Math.max(0, toInt(savedReplaceState.changed, replaceState.changed));
                            replaceState.hits = Math.max(0, toInt(savedReplaceState.hits, replaceState.hits));
                            replaceState.errors = Math.max(0, toInt(savedReplaceState.errors, replaceState.errors));
                            replaceState.mapCount = Math.max(0, toInt(savedReplaceState.mapCount, replaceState.mapCount));
                            replaceState.cursor = cleanCursor(savedReplaceState.cursor);

                            convertNet.batchLimit = Math.max(
                                1,
                                toInt(savedConvertNet.batchLimit, Number($('#cfg-batch-size').val() || 20))
                            );

                            const savedMode = savedRun.mode === 'replace' ? 'replace' : (savedRun.mode === 'convert' ? 'convert' : '');
                            const shouldResume = !!savedRun.running && !!savedMode;

                            return {shouldResume, mode: savedMode};
                        }

                        function fmtBytes(bytes) {
                            let value = Number(bytes) || 0;
                            if (value <= 0) return '0 B';
                            const units = ['B', 'KB', 'MB', 'GB'];
                            let i = 0;
                            while (value >= 1024 && i < units.length - 1) {
                                value /= 1024;
                                i++;
                            }
                            return (i === 0 ? value.toFixed(0) : value.toFixed(2)) + ' ' + units[i];
                        }

                        function escapeHtml(value) {
                            return String(value == null ? '' : value)
                                .replace(/&/g, '&amp;')
                                .replace(/</g, '&lt;')
                                .replace(/>/g, '&gt;')
                                .replace(/"/g, '&quot;')
                                .replace(/'/g, '&#039;');
                        }

                        function appendUnusedLog(line) {
                            if (!line) return;
                            const ts = new Date().toLocaleTimeString();
                            $unusedLog.append('[' + ts + '] ' + line + '\n');
                            $unusedLog.scrollTop($unusedLog[0].scrollHeight);
                        }

                        function renderUnusedEmpty(message) {
                            $unusedBody.html(
                                '<tr class="sp-webp-unused-empty"><td colspan="6">' + escapeHtml(message || 'No unused images found yet.') + '</td></tr>'
                            );
                            $unusedSelectAll.prop('checked', false).prop('disabled', true);
                        }

                        function selectedUnusedIds() {
                            return $unusedBody.find('.sp-webp-unused-select:checked').map(function () {
                                return Number($(this).closest('tr').data('attachmentId') || 0);
                            }).get().filter(Boolean);
                        }

                        function allFoundUnusedIds() {
                            return $unusedBody.find('tr[data-attachment-id]').filter(function () {
                                const $row = $(this);
                                const $button = $row.find('.sp-webp-unused-delete-one');
                                return $button.length && !$button.prop('disabled');
                            }).map(function () {
                                return Number($(this).data('attachmentId') || 0);
                            }).get().filter(Boolean);
                        }

                        function refreshUnusedActionButtons() {
                            const selectedCount = selectedUnusedIds().length;
                            const foundCount = allFoundUnusedIds().length;
                            const locked = runState.running;
                            $('#sp-webp-unused-scan').prop('disabled', locked);
                            $('#sp-webp-unused-clear').prop('disabled', locked);
                            $('#sp-webp-unused-delete-selected').prop('disabled', locked || selectedCount === 0);
                            $('#sp-webp-unused-delete-all').prop('disabled', locked || foundCount === 0);
                            $unusedSelectAll.prop('disabled', foundCount === 0 || locked);
                            $unusedSelectAll.prop('checked', foundCount > 0 && selectedCount === foundCount);
                        }

                        function updateUnusedStats() {
                            $('#spu-processed').text(unusedState.processed);
                            $('#spu-unused').text(unusedState.unused);
                            $('#spu-used').text(unusedState.used);
                            $('#spu-errors').text(unusedState.errors);
                            $('#spu-custom-tables').text(unusedState.customTables);
                            const percent = unusedState.total > 0
                                ? Math.min(100, Math.round((unusedState.processed / unusedState.total) * 100))
                                : 0;
                            $('#spu-progress-bar').css('width', percent + '%');
                            $('#spu-progress-text').text(percent + '%');
                            refreshUnusedActionButtons();
                        }

                        function buildUnusedRow(item) {
                            const title = escapeHtml(item.title || ('Attachment #' + item.id));
                            const relative = escapeHtml(item.relative || '');
                            const reason = escapeHtml(item.reason || 'No references found');
                            const mime = escapeHtml(item.mime || '');
                            const modified = escapeHtml(item.modified || '');
                            const fileText = Number(item.file_exists || 0) ? fmtBytes(item.filesize || 0) : 'Missing on disk';
                            const parentText = Number(item.parent_id || 0) > 0
                                ? 'Attached to #' + escapeHtml(item.parent_id) + (item.parent_title ? ' (' + escapeHtml(item.parent_title) + ')' : '')
                                : 'Unattached';
                            const thumb = item.thumb_url ? '<img class="sp-webp-unused-preview" src="' + escapeHtml(item.thumb_url) + '" alt="">' : '<span class="sp-webp-unused-preview"></span>';
                            const links = [
                                item.url ? '<a href="' + escapeHtml(item.url) + '" target="_blank" rel="noopener">Open file</a>' : '',
                                item.edit_url ? '<a href="' + escapeHtml(item.edit_url) + '">Edit attachment</a>' : ''
                            ].filter(Boolean).join(' · ');

                            return [
                                '<tr data-attachment-id="' + Number(item.id || 0) + '">',
                                '<td class="sp-webp-unused-table__check"><input type="checkbox" class="sp-webp-unused-select"></td>',
                                '<td>' + thumb + '</td>',
                                '<td><div class="sp-webp-unused-title">' + title + '</div><div class="sp-webp-unused-meta">' + relative + '<br>' + escapeHtml(fileText) + ' · ' + mime + (modified ? ' · ' + modified : '') + '<br>' + escapeHtml(parentText) + (links ? '<br>' + links : '') + '</div></td>',
                                '<td class="sp-webp-unused-reason">' + reason + '</td>',
                                '<td><span class="sp-webp-unused-status">Unused</span></td>',
                                '<td><button type="button" class="sp-webp-btn sp-webp-btn--danger sp-webp-unused-delete-one">Delete</button></td>',
                                '</tr>'
                            ].join('');
                        }

                        function appendUnusedRows(items) {
                            if (!Array.isArray(items) || !items.length) {
                                if (!allFoundUnusedIds().length) {
                                    renderUnusedEmpty('No unused images found yet.');
                                }
                                refreshUnusedActionButtons();
                                return;
                            }

                            if ($unusedBody.find('.sp-webp-unused-empty').length) {
                                $unusedBody.empty();
                            }

                            items.forEach(function (item) {
                                $unusedBody.append(buildUnusedRow(item));
                            });
                            refreshUnusedActionButtons();
                        }

                        function markUnusedRowsDeleted(ids) {
                            ids.forEach(function (id) {
                                const $row = $unusedBody.find('tr[data-attachment-id="' + Number(id) + '"]');
                                if (!$row.length) return;
                                $row.addClass('sp-webp-unused-row--deleted');
                                $row.find('.sp-webp-unused-select').remove();
                                $row.find('.sp-webp-unused-status')
                                    .removeClass('sp-webp-unused-status--skipped')
                                    .addClass('sp-webp-unused-status--deleted')
                                    .text('Deleted');
                                $row.find('.sp-webp-unused-delete-one').remove();
                            });
                            refreshUnusedActionButtons();
                        }

                        function markUnusedRowsSkipped(items) {
                            (items || []).forEach(function (item) {
                                const id = Number(item.id || 0);
                                if (!id) return;
                                const $row = $unusedBody.find('tr[data-attachment-id="' + id + '"]');
                                if (!$row.length) return;
                                $row.find('.sp-webp-unused-select').prop('checked', false).prop('disabled', true);
                                $row.find('.sp-webp-unused-status')
                                    .removeClass('sp-webp-unused-status--deleted')
                                    .addClass('sp-webp-unused-status--skipped')
                                    .text('Skipped');
                                $row.find('.sp-webp-unused-reason').text(item.reason || 'Skipped');
                                $row.find('.sp-webp-unused-delete-one').prop('disabled', true);
                            });
                            refreshUnusedActionButtons();
                        }

                        function clearUnusedResults(options) {
                            const keepLog = options && options.keepLog;
                            unusedState.total = 0;
                            unusedState.lastId = 0;
                            unusedState.processed = 0;
                            unusedState.unused = 0;
                            unusedState.used = 0;
                            unusedState.errors = 0;
                            unusedState.customTables = 0;
                            unusedState.customColumns = 0;
                            unusedState.deleteQueue = [];
                            unusedState.deleteTotal = 0;
                            unusedState.deleted = 0;
                            unusedState.deleteSkipped = 0;
                            renderUnusedEmpty('No unused images found yet.');
                            if (!keepLog) {
                                $unusedLog.text('');
                            }
                            updateUnusedStats();
                        }

                        function appendLog(line) {
                            if (!line) return;
                            const ts = new Date().toLocaleTimeString();
                            $log.append('[' + ts + '] ' + line + '\n');
                            $log.scrollTop($log[0].scrollHeight);
                        }

                        function appendReplaceLog(line) {
                            if (!line) return;
                            const ts = new Date().toLocaleTimeString();
                            $replaceLog.append('[' + ts + '] ' + line + '\n');
                            $replaceLog.scrollTop($replaceLog[0].scrollHeight);
                        }

                        function updateStats() {
                            $('#spc-total').text(state.total);
                            $('#spc-converted').text(state.converted);
                            $('#spc-skipped').text(state.skipped);
                            $('#spc-errors').text(state.errors);
                            $('#spc-saved-size').text(fmtBytes(state.bytesSaved));
                            const percent = state.total > 0 ? Math.min(100, Math.round((state.processed / state.total) * 100)) : 0;
                            $('#spc-progress-bar').css('width', percent + '%');
                            $('#spc-progress-text').text(percent + '%');
                            persistProgress();
                        }

                        function setSaved() {
                            $saved.addClass('show');
                            setTimeout(() => $saved.removeClass('show'), 1200);
                        }

                        function updateReplaceStats() {
                            $('#spr-processed').text(replaceState.processed);
                            $('#spr-changed').text(replaceState.changed);
                            $('#spr-hits').text(replaceState.hits);
                            $('#spr-map').text(replaceState.mapCount);

                            const percent = replaceState.totalRows > 0
                                ? Math.min(100, Math.round((replaceState.processed / replaceState.totalRows) * 100))
                                : 0;
                            $('#spr-progress-bar').css('width', percent + '%');
                            $('#spr-progress-text').text(percent + '%');
                            persistProgress();
                        }

                        function gatherCfg() {
                            return {
                                enabled_upload: $('#cfg-enabled-upload').is(':checked') ? 1 : 0,
                                quality: Number($('#cfg-quality').val() || 90),
                                max_side: Number($('#cfg-max-side').val() || 2560),
                                delete_original: $('#cfg-delete-original').is(':checked') ? 1 : 0,
                                skip_animated_gif: $('#cfg-skip-animated').is(':checked') ? 1 : 0,
                                batch_size: Number($('#cfg-batch-size').val() || 20)
                            };
                        }

                        function saveSettings() {
                            $status.text('Saving settings...');
                            $.post(ajaxurl, {
                                action: 'sp_webp_save_settings',
                                nonce,
                                cfg: gatherCfg()
                            }, function (res) {
                                if (!res || !res.success) {
                                    $status.text('Settings save failed');
                                    appendLog('[ERR] Failed to save settings');
                                    return;
                                }
                                setSaved();
                                $status.text('Settings saved');
                                appendLog('[OK] Settings saved');
                            });
                        }

                        function scanMedia(callback) {
                            $status.text('Scanning media library...');
                            $.post(ajaxurl, {
                                action: 'sp_webp_scan_media',
                                nonce
                            }, function (res) {
                                if (!res || !res.success) {
                                    $status.text('Scan failed');
                                    appendLog('[ERR] Scan failed');
                                    if (typeof callback === 'function') callback(false);
                                    return;
                                }

                                state.total = Number(res.data.total_supported || 0);
                                state.lastId = 0;
                                state.processed = 0;
                                state.converted = 0;
                                state.skipped = 0;
                                state.errors = 0;
                                state.bytesSaved = 0;
                                convertNet.retry = 0;
                                convertNet.batchLimit = Math.max(1, Number(res.data.batch_size || $('#cfg-batch-size').val() || 20));
                                replaceState.totalRows = 0;
                                replaceState.processed = 0;
                                replaceState.changed = 0;
                                replaceState.hits = 0;
                                replaceState.errors = 0;
                                replaceState.mapCount = 0;
                                replaceState.cursor = {phase: 'posts', last_id: 0};
                                updateStats();
                                updateReplaceStats();

                                $status.text('Scan complete: ' + state.total + ' items in queue');
                                appendLog('[OK] Scan complete. Queue: ' + state.total + ' images');

                                if (typeof callback === 'function') callback(true);
                            });
                        }

                        function setRunning(running, mode) {
                            runState.running = !!running;
                            runState.mode = runState.running ? String(mode || '') : '';
                            $('#sp-webp-start').prop('disabled', runState.running);
                            $('#sp-webp-scan').prop('disabled', runState.running);
                            $('#sp-webp-stop').prop('disabled', !runState.running);
                            $('#sp-webp-reset-progress').prop('disabled', runState.running);
                            $('#sp-webp-save').prop('disabled', runState.running);
                            $('#sp-webp-replace-all').prop('disabled', runState.running);
                            refreshUnusedActionButtons();
                            persistProgress();
                        }

                        function resetSavedProgress() {
                            if (runState.running) return;

                            state.total = 0;
                            state.lastId = 0;
                            state.processed = 0;
                            state.converted = 0;
                            state.skipped = 0;
                            state.errors = 0;
                            state.bytesSaved = 0;

                            replaceState.totalRows = 0;
                            replaceState.processed = 0;
                            replaceState.changed = 0;
                            replaceState.hits = 0;
                            replaceState.errors = 0;
                            replaceState.mapCount = 0;
                            replaceState.cursor = {phase: 'posts', last_id: 0};

                            convertNet.retry = 0;
                            convertNet.batchLimit = Math.max(1, Number($('#cfg-batch-size').val() || 20));
                            replaceNet.retry = 0;

                            $log.text('');
                            $replaceLog.text('');
                            clearUnusedResults();
                            persistSuspended = true;
                            updateStats();
                            updateReplaceStats();
                            persistSuspended = false;
                            clearPersistedProgress();
                            $status.text('Saved progress reset');
                            $replaceStatus.text('Saved progress reset');
                            $unusedStatus.text('Saved progress reset');
                            appendLog('[OK] Local saved progress cleared');
                        }

                        function handleConvertNetworkIssue(message) {
                            if (!runState.running || runState.mode !== 'convert') return;
                            if (convertNet.retry < convertNet.maxRetry) {
                                convertNet.retry += 1;
                                convertNet.batchLimit = Math.max(1, Math.floor(convertNet.batchLimit / 2));
                                const waitMs = Math.min(8000, 800 * convertNet.retry);
                                $status.text('Retry ' + convertNet.retry + '/' + convertNet.maxRetry + ' after network error...');
                                appendLog('[ERR] ' + message + '. Retry ' + convertNet.retry + '/' + convertNet.maxRetry + ' in ' + (waitMs / 1000).toFixed(1) + 's. Batch=' + convertNet.batchLimit);
                                setTimeout(runBatch, waitMs);
                                return;
                            }

                            setRunning(false, '');
                            $status.text(message);
                            appendLog('[ERR] ' + message + '. Retries exhausted.');
                        }

                        function runBatch() {
                            if (!runState.running || runState.mode !== 'convert') return;

                            $.post(ajaxurl, {
                                action: 'sp_webp_convert_batch',
                                nonce,
                                last_id: state.lastId,
                                limit_override: convertNet.batchLimit
                            }, function (res) {
                                if (!runState.running || runState.mode !== 'convert') return;

                                if (!res || !res.success) {
                                    handleConvertNetworkIssue('Batch request failed');
                                    return;
                                }

                                const d = res.data || {};
                                convertNet.retry = 0;
                                state.lastId = Number(d.last_id || state.lastId);
                                state.processed += Number(d.batch_total || 0);
                                state.converted += Number(d.converted || 0);
                                state.skipped += Number(d.skipped || 0);
                                state.errors += Number(d.errors || 0);
                                state.bytesSaved += Number(d.bytes_saved || 0);

                                (d.log || []).forEach(appendLog);
                                updateStats();

                                if (d.done) {
                                    setRunning(false, '');
                                    $status.text('Bulk conversion completed');
                                    appendLog('[OK] Done. Converted: ' + state.converted + ', skipped: ' + state.skipped + ', errors: ' + state.errors + ', saved: ' + fmtBytes(state.bytesSaved));
                                    appendLog('[>] Starting URL replacement...');
                                    startReplaceAll();
                                    return;
                                }

                                $status.text('Processing...');
                                setTimeout(runBatch, 120);
                            }).fail(function () {
                                handleConvertNetworkIssue('Network error during batch');
                            });
                        }

                        function prepareReplace(callback) {
                            $replaceStatus.text('Preparing URL map...');
                            $.post(ajaxurl, {
                                action: 'sp_webp_prepare_url_replace',
                                nonce
                            }, function (res) {
                                if (!res || !res.success) {
                                    $replaceStatus.text('Prepare failed');
                                    appendReplaceLog('[ERR] Prepare mapping failed');
                                    if (typeof callback === 'function') callback(false);
                                    return;
                                }

                                const d = res.data || {};
                                replaceState.totalRows = Number(d.total_rows || 0);
                                replaceState.processed = 0;
                                replaceState.changed = 0;
                                replaceState.hits = 0;
                                replaceState.errors = 0;
                                replaceState.mapCount = Number(d.map_count || 0);
                                replaceState.cursor = d.cursor || {phase: 'posts', last_id: 0};
                                replaceNet.retry = 0;
                                updateReplaceStats();

                                if (replaceState.mapCount <= 0) {
                                    $replaceStatus.text('No mappings found');
                                    appendReplaceLog('[-] No mapped old image URLs found.');
                                    if (typeof callback === 'function') callback(false);
                                    return;
                                }

                                $replaceStatus.text('Ready: map ' + replaceState.mapCount + ', rows ' + replaceState.totalRows);
                                appendReplaceLog('[OK] Prepared mapping. URLs: ' + replaceState.mapCount + ', rows: ' + replaceState.totalRows);
                                if (typeof callback === 'function') callback(true);
                            });
                        }

                        function handleReplaceNetworkIssue(message) {
                            if (!runState.running || runState.mode !== 'replace') return;
                            if (replaceNet.retry < replaceNet.maxRetry) {
                                replaceNet.retry += 1;
                                const waitMs = Math.min(8000, 800 * replaceNet.retry);
                                $replaceStatus.text('Retry ' + replaceNet.retry + '/' + replaceNet.maxRetry + ' after network error...');
                                appendReplaceLog('[ERR] ' + message + '. Retry ' + replaceNet.retry + '/' + replaceNet.maxRetry + ' in ' + (waitMs / 1000).toFixed(1) + 's');
                                setTimeout(runReplaceBatch, waitMs);
                                return;
                            }

                            setRunning(false, '');
                            $replaceStatus.text(message);
                            appendReplaceLog('[ERR] ' + message + '. Retries exhausted.');
                        }

                        function runReplaceBatch() {
                            if (!runState.running || runState.mode !== 'replace') return;

                            $.post(ajaxurl, {
                                action: 'sp_webp_replace_urls_batch',
                                nonce,
                                dry_run: 0,
                                cursor: replaceState.cursor
                            }, function (res) {
                                if (!runState.running || runState.mode !== 'replace') return;

                                if (!res || !res.success) {
                                    handleReplaceNetworkIssue('URL replace batch failed');
                                    return;
                                }

                                const d = res.data || {};
                                replaceNet.retry = 0;
                                replaceState.cursor = d.cursor || replaceState.cursor;
                                replaceState.processed += Number(d.processed || 0);
                                replaceState.changed += Number(d.changed || 0);
                                replaceState.hits += Number(d.hits || 0);
                                replaceState.errors += Number(d.errors || 0);
                                replaceState.mapCount = Number(d.map_count || replaceState.mapCount);
                                (d.log || []).forEach(appendReplaceLog);
                                updateReplaceStats();

                                if (d.done) {
                                    setRunning(false, '');
                                    $replaceStatus.text('Replacement completed');
                                    appendReplaceLog('[OK] Done. Changed rows: ' + replaceState.changed + ', URL hits: ' + replaceState.hits);
                                    clearPersistedProgress();
                                    return;
                                }

                                $replaceStatus.text('Replacement in progress...');
                                setTimeout(runReplaceBatch, 120);
                            }).fail(function () {
                                handleReplaceNetworkIssue('Network error during URL replace');
                            });
                        }

                        function startReplaceAll() {
                            if (runState.running) return;
                            prepareReplace(function (ok) {
                                if (!ok) return;
                                setRunning(true, 'replace');
                                $replaceStatus.text('Starting URL replacement...');
                                appendReplaceLog('[>] URL replacement started');
                                runReplaceBatch();
                            });
                        }

                        function prepareUnusedScan(callback) {
                            $unusedStatus.text('Preparing unused-image scan...');
                            $.post(ajaxurl, {
                                action: 'sp_webp_prepare_unused_scan',
                                nonce
                            }, function (res) {
                                if (!res || !res.success) {
                                    $unusedStatus.text('Prepare failed');
                                    appendUnusedLog('[ERR] Failed to prepare unused-image scan');
                                    if (typeof callback === 'function') callback(false);
                                    return;
                                }

                                const d = res.data || {};
                                unusedState.total = Number(d.total_images || 0);
                                unusedState.lastId = 0;
                                unusedState.processed = 0;
                                unusedState.unused = 0;
                                unusedState.used = 0;
                                unusedState.errors = 0;
                                unusedState.customTables = Number(d.custom_tables || 0);
                                unusedState.customColumns = Number(d.custom_columns || 0);
                                unusedState.deleteQueue = [];
                                unusedState.deleteTotal = 0;
                                unusedState.deleted = 0;
                                unusedState.deleteSkipped = 0;
                                unusedNet.retry = 0;
                                unusedNet.batchLimit = Math.max(1, Number(d.batch_size || unusedNet.batchLimit || 8));
                                renderUnusedEmpty('Scanning in progress. Unused results will appear here.');
                                updateUnusedStats();
                                $unusedStatus.text('Ready: ' + unusedState.total + ' images, ' + unusedState.customTables + ' custom tables');
                                appendUnusedLog('[OK] Prepared scan. Images: ' + unusedState.total + ', custom tables: ' + unusedState.customTables + ', columns: ' + unusedState.customColumns);
                                if (typeof callback === 'function') callback(true);
                            }).fail(function () {
                                $unusedStatus.text('Prepare failed');
                                appendUnusedLog('[ERR] Failed to prepare unused-image scan');
                                if (typeof callback === 'function') callback(false);
                            });
                        }

                        function handleUnusedScanNetworkIssue(message) {
                            if (!runState.running || runState.mode !== 'unused-scan') return;
                            if (unusedNet.retry < unusedNet.maxRetry) {
                                unusedNet.retry += 1;
                                const waitMs = Math.min(8000, 800 * unusedNet.retry);
                                $unusedStatus.text('Retry ' + unusedNet.retry + '/' + unusedNet.maxRetry + ' after network error...');
                                appendUnusedLog('[ERR] ' + message + '. Retry ' + unusedNet.retry + '/' + unusedNet.maxRetry + ' in ' + (waitMs / 1000).toFixed(1) + 's');
                                setTimeout(runUnusedScanBatch, waitMs);
                                return;
                            }

                            setRunning(false, '');
                            $unusedStatus.text(message);
                            appendUnusedLog('[ERR] ' + message + '. Retries exhausted.');
                        }

                        function runUnusedScanBatch() {
                            if (!runState.running || runState.mode !== 'unused-scan') return;

                            $.post(ajaxurl, {
                                action: 'sp_webp_scan_unused_batch',
                                nonce,
                                last_id: unusedState.lastId,
                                limit: unusedNet.batchLimit
                            }, function (res) {
                                if (!runState.running || runState.mode !== 'unused-scan') return;

                                if (!res || !res.success) {
                                    handleUnusedScanNetworkIssue('Unused-image scan batch failed');
                                    return;
                                }

                                const d = res.data || {};
                                unusedNet.retry = 0;
                                unusedState.lastId = Number(d.last_id || unusedState.lastId);
                                unusedState.processed += Number(d.processed || 0);
                                unusedState.unused += Number(d.unused || 0);
                                unusedState.used += Number(d.used || 0);
                                unusedState.errors += Number(d.errors || 0);
                                appendUnusedRows(d.items || []);
                                (d.log || []).forEach(appendUnusedLog);
                                updateUnusedStats();

                                if (d.done) {
                                    setRunning(false, '');
                                    if (unusedState.unused <= 0) {
                                        renderUnusedEmpty('Scan complete. No unused images were found.');
                                    }
                                    $unusedStatus.text('Unused-image scan completed');
                                    appendUnusedLog('[OK] Done. Unused found: ' + unusedState.unused + ', kept: ' + unusedState.used + ', errors: ' + unusedState.errors);
                                    return;
                                }

                                $unusedStatus.text('Scanning database references...');
                                setTimeout(runUnusedScanBatch, 90);
                            }).fail(function () {
                                handleUnusedScanNetworkIssue('Network error during unused-image scan');
                            });
                        }

                        function startUnusedScan() {
                            if (runState.running) return;
                            clearUnusedResults();
                            prepareUnusedScan(function (ok) {
                                if (!ok) return;
                                if (unusedState.total <= 0) {
                                    $unusedStatus.text('No image attachments found');
                                    appendUnusedLog('[-] No image attachments found');
                                    return;
                                }

                                setRunning(true, 'unused-scan');
                                $unusedStatus.text('Starting unused-image scan...');
                                appendUnusedLog('[>] Unused-image scan started');
                                runUnusedScanBatch();
                            });
                        }

                        function handleUnusedDeleteNetworkIssue(message) {
                            if (!runState.running || runState.mode !== 'unused-delete') return;
                            if (unusedNet.deleteRetry < unusedNet.deleteMaxRetry) {
                                unusedNet.deleteRetry += 1;
                                const waitMs = Math.min(8000, 800 * unusedNet.deleteRetry);
                                $unusedStatus.text('Retry ' + unusedNet.deleteRetry + '/' + unusedNet.deleteMaxRetry + ' while deleting...');
                                appendUnusedLog('[ERR] ' + message + '. Retry ' + unusedNet.deleteRetry + '/' + unusedNet.deleteMaxRetry + ' in ' + (waitMs / 1000).toFixed(1) + 's');
                                setTimeout(runUnusedDeleteBatch, waitMs);
                                return;
                            }

                            setRunning(false, '');
                            $unusedStatus.text(message);
                            appendUnusedLog('[ERR] ' + message + '. Retries exhausted.');
                        }

                        function runUnusedDeleteBatch() {
                            if (!runState.running || runState.mode !== 'unused-delete') return;

                            const chunk = unusedState.deleteQueue.slice(0, unusedNet.deleteBatch);
                            if (!chunk.length) {
                                setRunning(false, '');
                                $unusedStatus.text('Delete completed');
                                appendUnusedLog('[OK] Delete done. Deleted: ' + unusedState.deleted + ', skipped: ' + unusedState.deleteSkipped + ', errors: ' + unusedState.errors);
                                refreshUnusedActionButtons();
                                return;
                            }

                            $.post(ajaxurl, {
                                action: 'sp_webp_delete_unused_batch',
                                nonce,
                                ids: chunk
                            }, function (res) {
                                if (!runState.running || runState.mode !== 'unused-delete') return;

                                if (!res || !res.success) {
                                    handleUnusedDeleteNetworkIssue('Delete batch failed');
                                    return;
                                }

                                const d = res.data || {};
                                const deletedIds = Array.isArray(d.deleted_ids) ? d.deleted_ids.map(Number).filter(Boolean) : [];
                                const skippedItems = Array.isArray(d.skipped) ? d.skipped : [];
                                unusedNet.deleteRetry = 0;
                                unusedState.deleteQueue = unusedState.deleteQueue.slice(chunk.length);
                                unusedState.deleted += deletedIds.length;
                                unusedState.deleteSkipped += skippedItems.length;
                                unusedState.errors += Number(d.errors || 0);
                                unusedState.unused = Math.max(0, unusedState.unused - deletedIds.length - skippedItems.length);
                                unusedState.used += skippedItems.length;
                                markUnusedRowsDeleted(deletedIds);
                                markUnusedRowsSkipped(skippedItems);
                                (d.log || []).forEach(appendUnusedLog);
                                updateUnusedStats();

                                if (!unusedState.deleteQueue.length) {
                                    setRunning(false, '');
                                    $unusedStatus.text('Delete completed');
                                    appendUnusedLog('[OK] Delete done. Deleted: ' + unusedState.deleted + ', skipped: ' + unusedState.deleteSkipped + ', errors: ' + unusedState.errors);
                                    return;
                                }

                                const processed = unusedState.deleteTotal - unusedState.deleteQueue.length;
                                $unusedStatus.text('Deleting unused images... ' + processed + '/' + unusedState.deleteTotal);
                                setTimeout(runUnusedDeleteBatch, 120);
                            }).fail(function () {
                                handleUnusedDeleteNetworkIssue('Network error during delete');
                            });
                        }

                        function startUnusedDelete(ids) {
                            const queue = (ids || []).map(Number).filter(Boolean);
                            if (runState.running || !queue.length) return;
                            const label = queue.length === 1 ? 'this image' : (queue.length + ' images');
                            if (!window.confirm('Delete ' + label + '? The tool will re-check DB usage right before removal.')) {
                                return;
                            }

                            unusedState.deleteQueue = queue.slice();
                            unusedState.deleteTotal = queue.length;
                            unusedState.deleted = 0;
                            unusedState.deleteSkipped = 0;
                            unusedNet.deleteRetry = 0;
                            setRunning(true, 'unused-delete');
                            $unusedStatus.text('Deleting unused images...');
                            appendUnusedLog('[>] Delete started for ' + queue.length + ' attachments');
                            runUnusedDeleteBatch();
                        }

                        $('#sp-webp-save').on('click', saveSettings);

                        $('#sp-webp-scan').on('click', function () {
                            scanMedia();
                        });

                        $('#sp-webp-start').on('click', function () {
                            if (runState.running) return;

                            const start = function () {
                                if (state.total <= 0) {
                                    $status.text('Queue is empty');
                                    appendLog('[-] Queue is empty');
                                    clearPersistedProgress();
                                    return;
                                }
                                setRunning(true, 'convert');
                                $status.text('Starting bulk conversion...');
                                appendLog('[>] Bulk conversion started');
                                runBatch();
                            };

                            if (state.total <= 0) {
                                scanMedia(function (ok) {
                                    if (ok) start();
                                });
                            } else {
                                start();
                            }
                        });

                        $('#sp-webp-stop').on('click', function () {
                            if (!runState.running) return;
                            const mode = runState.mode;
                            setRunning(false, '');
                            if (mode === 'replace') {
                                $replaceStatus.text('Stopped by user');
                                appendReplaceLog('[-] URL replace stopped by user');
                            } else if (mode === 'unused-scan') {
                                $unusedStatus.text('Stopped by user');
                                appendUnusedLog('[-] Unused-image scan stopped by user');
                            } else if (mode === 'unused-delete') {
                                $unusedStatus.text('Delete stopped by user');
                                appendUnusedLog('[-] Delete stopped by user');
                            } else {
                                $status.text('Stopped by user');
                                appendLog('[-] Conversion stopped by user');
                            }
                        });

                        $('#sp-webp-replace-all').on('click', function () {
                            startReplaceAll();
                        });

                        $('#sp-webp-reset-progress').on('click', function () {
                            resetSavedProgress();
                        });

                        $('#sp-webp-unused-scan').on('click', function () {
                            startUnusedScan();
                        });

                        $('#sp-webp-unused-clear').on('click', function () {
                            if (runState.running) return;
                            clearUnusedResults();
                            $unusedStatus.text('Results cleared');
                            appendUnusedLog('[OK] Unused-image results cleared');
                        });

                        $('#sp-webp-unused-delete-selected').on('click', function () {
                            startUnusedDelete(selectedUnusedIds());
                        });

                        $('#sp-webp-unused-delete-all').on('click', function () {
                            startUnusedDelete(allFoundUnusedIds());
                        });

                        $unusedSelectAll.on('change', function () {
                            const checked = $(this).is(':checked');
                            $unusedBody.find('.sp-webp-unused-select').prop('checked', checked);
                            refreshUnusedActionButtons();
                        });

                        $unusedBody.on('change', '.sp-webp-unused-select', function () {
                            refreshUnusedActionButtons();
                        });

                        $unusedBody.on('click', '.sp-webp-unused-delete-one', function () {
                            const id = Number($(this).closest('tr').data('attachmentId') || 0);
                            if (!id) return;
                            startUnusedDelete([id]);
                        });

                        const restored = restoreProgress();
                        updateStats();
                        updateReplaceStats();
                        clearUnusedResults({keepLog: true});

                        if (restored) {
                            appendLog('[i] Restored progress from local storage.');
                            if (restored.shouldResume && restored.mode === 'replace' && replaceState.mapCount > 0 && replaceState.cursor.phase !== 'done') {
                                $replaceStatus.text('Resuming URL replacement...');
                                appendReplaceLog('[>] Resuming URL replacement from saved progress');
                                setRunning(true, 'replace');
                                setTimeout(runReplaceBatch, 250);
                            } else if (restored.shouldResume && restored.mode === 'convert' && state.total > 0) {
                                $status.text('Resuming bulk conversion...');
                                appendLog('[>] Resuming conversion from saved progress');
                                setRunning(true, 'convert');
                                setTimeout(runBatch, 250);
                            } else {
                                appendLog('[i] Saved progress loaded. Click Start to continue.');
                            }
                        }
                    })(jQuery);
                </script>
				<?php
			}
		}

		if ( ! class_exists( 'SP_Uploads_WebP_Live_Repair' ) ) {
			class SP_Uploads_WebP_Live_Repair {
				private const PAGE_SLUG                  = 'sp-uploads-webp-live-repair';
				private const NONCE_ACTION               = 'sp_webp_live_repair_admin';
				private const BATCH_SIZE                 = 3;
				private const SOFT_LIMIT_SEC             = 8;
				private const CLEANUP_BATCH_SIZE         = 250;
				private const CLEANUP_FILES_OPTION       = 'sp_webp_live_cleanup_files';
				private const CLEANUP_CANDIDATES_OPTION  = 'sp_webp_live_cleanup_candidates';

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

					add_action( 'wp_ajax_sp_webp_live_repair_prepare_scan', [ $this, 'ajax_prepare_scan' ] );
					add_action( 'wp_ajax_sp_webp_live_repair_scan_batch', [ $this, 'ajax_scan_batch' ] );
					add_action( 'wp_ajax_sp_webp_live_repair_prepare', [ $this, 'ajax_prepare' ] );
					add_action( 'wp_ajax_sp_webp_live_repair_batch', [ $this, 'ajax_batch' ] );
					add_action( 'wp_ajax_sp_webp_live_cleanup_prepare_scan', [ $this, 'ajax_cleanup_prepare_scan' ] );
					add_action( 'wp_ajax_sp_webp_live_cleanup_scan_batch', [ $this, 'ajax_cleanup_scan_batch' ] );
					add_action( 'wp_ajax_sp_webp_live_cleanup_delete_batch', [ $this, 'ajax_cleanup_delete_batch' ] );
				}

				private function ajax_guard(): void {
					check_ajax_referer( self::NONCE_ACTION, 'nonce' );

					if ( ! current_user_can( 'manage_options' ) ) {
						wp_send_json_error( [ 'message' => 'Forbidden' ], 403 );
					}
				}

				private function normalize_relative_upload_path( string $path ): string {
					$path = trim( $path );
					if ( $path === '' ) {
						return '';
					}

					$path = (string) preg_replace( '~[?#].*$~', '', $path );
					$path = str_replace( '\\', '/', $path );

					return ltrim( $path, '/' );
				}

				private function relative_to_absolute_upload_path( string $relative ): string {
					$relative = $this->normalize_relative_upload_path( $relative );
					if ( $relative === '' ) {
						return '';
					}

					$up = wp_get_upload_dir();

					return wp_normalize_path( trailingslashit( (string) $up['basedir'] ) . $relative );
				}

				private function path_to_relative_upload( string $path ): string {
					$up     = wp_get_upload_dir();
					$base   = wp_normalize_path( trailingslashit( (string) $up['basedir'] ) );
					$target = wp_normalize_path( $path );

					if ( str_starts_with( $target, $base ) ) {
						return ltrim( substr( $target, strlen( $base ) ), '/' );
					}

					$relative = _wp_relative_upload_path( $path );
					if ( is_string( $relative ) ) {
						return ltrim( $relative, '/' );
					}

					return '';
				}

				private function relative_upload_from_url( string $url ): string {
					$url = trim( $url );
					if ( $url === '' ) {
						return '';
					}

					$up      = wp_get_upload_dir();
					$baseurl = trailingslashit( (string) ( $up['baseurl'] ?? '' ) );
					if ( $baseurl !== '' && str_starts_with( $url, $baseurl ) ) {
						return $this->normalize_relative_upload_path( substr( $url, strlen( $baseurl ) ) );
					}

					return '';
				}

				private function upload_url_from_relative( string $relative ): string {
					$relative = $this->normalize_relative_upload_path( $relative );
					if ( $relative === '' ) {
						return '';
					}

					$up = wp_get_upload_dir();

					return trailingslashit( (string) $up['baseurl'] ) . $relative;
				}

				private function image_extensions(): array {
					return [ 'jpg', 'jpeg', 'png', 'gif' ];
				}

				private function cleanup_scan_extensions(): array {
					return [ 'jpg', 'jpeg', 'png', 'gif', 'webp' ];
				}

				private function detect_mime( string $file, string $fallback = '' ): string {
					$mime = '';
					if ( is_file( $file ) ) {
						$mime = (string) wp_get_image_mime( $file );
						if ( $mime !== '' ) {
							return strtolower( $mime );
						}
					}

					$ext = strtolower( (string) pathinfo( $file, PATHINFO_EXTENSION ) );
					$mime_by_ext = [
						'jpg'  => 'image/jpeg',
						'jpeg' => 'image/jpeg',
						'png'  => 'image/png',
						'gif'  => 'image/gif',
						'webp' => 'image/webp',
					];
					if ( isset( $mime_by_ext[ $ext ] ) ) {
						return $mime_by_ext[ $ext ];
					}

					$mime = strtolower( trim( $fallback ) );
					if ( $mime !== '' ) {
						return $mime;
					}

					$check = wp_check_filetype( $file );

					return strtolower( (string) ( $check['type'] ?? '' ) );
				}

				private function get_cleanup_files(): array {
					$files = get_option( self::CLEANUP_FILES_OPTION, [] );

					return is_array( $files ) ? $files : [];
				}

				private function set_cleanup_files( array $files ): void {
					update_option( self::CLEANUP_FILES_OPTION, array_values( $files ), false );
				}

				private function get_cleanup_candidates(): array {
					$candidates = get_option( self::CLEANUP_CANDIDATES_OPTION, [] );

					return is_array( $candidates ) ? $candidates : [];
				}

				private function set_cleanup_candidates( array $candidates ): void {
					update_option( self::CLEANUP_CANDIDATES_OPTION, array_values( $candidates ), false );
				}

				private function merge_cleanup_candidates( array $existing, array $incoming ): array {
					$map = [];

					foreach ( array_merge( $existing, $incoming ) as $candidate ) {
						if ( ! is_array( $candidate ) ) {
							continue;
						}

						$relative = $this->normalize_relative_upload_path( (string) ( $candidate['relative'] ?? '' ) );
						if ( $relative === '' ) {
							continue;
						}

						if ( ! isset( $map[ $relative ] ) ) {
							$candidate['relative'] = $relative;
							$map[ $relative ]      = $candidate;
						}
					}

					ksort( $map, SORT_NATURAL | SORT_FLAG_CASE );

					return array_values( $map );
				}

				private function cleanup_candidates_count_by_type( array $candidates, string $type ): int {
					return count(
						array_filter(
							$candidates,
							static fn( $item ) => ( $item['type'] ?? '' ) === $type
						)
					);
				}

				private function is_valid_image_file( string $path ): bool {
					if ( $path === '' || ! is_file( $path ) || ! is_readable( $path ) ) {
						return false;
					}

					$size = (int) ( @filesize( $path ) ?: 0 );
					if ( $size <= 0 ) {
						return false;
					}

					return (bool) @getimagesize( $path );
				}

				private function is_zero_byte_file( string $path ): bool {
					return $path !== '' && is_file( $path ) && (int) ( @filesize( $path ) ?: 0 ) <= 0;
				}

				private function swap_extension( string $relative, string $target_ext ): string {
					$relative   = $this->normalize_relative_upload_path( $relative );
					$target_ext = strtolower( trim( $target_ext ) );
					if ( $relative === '' || $target_ext === '' ) {
						return '';
					}

					return (string) preg_replace( '~\.[a-z0-9]+$~i', '.' . $target_ext, $relative );
				}

				private function collect_upload_image_files(): array {
					$up      = wp_get_upload_dir();
					$basedir = wp_normalize_path( (string) ( $up['basedir'] ?? '' ) );
					if ( $basedir === '' || ! is_dir( $basedir ) ) {
						return [];
					}

					$files      = [];
					$extensions = array_fill_keys( $this->cleanup_scan_extensions(), true );

					try {
						$iterator = new RecursiveIteratorIterator(
							new RecursiveDirectoryIterator( $basedir, FilesystemIterator::SKIP_DOTS )
						);
					} catch ( Throwable $e ) {
						return [];
					}

					foreach ( $iterator as $item ) {
						if ( ! $item instanceof SplFileInfo || ! $item->isFile() ) {
							continue;
						}

						$ext = strtolower( (string) pathinfo( $item->getFilename(), PATHINFO_EXTENSION ) );
						if ( ! isset( $extensions[ $ext ] ) ) {
							continue;
						}

						$relative = $this->path_to_relative_upload( $item->getPathname() );
						if ( $relative !== '' ) {
							$files[] = $relative;
						}
					}

					sort( $files, SORT_NATURAL | SORT_FLAG_CASE );

					return array_values( array_unique( $files ) );
				}

				private function assess_cleanup_file( string $relative ): array {
					$relative = $this->normalize_relative_upload_path( $relative );
					$path     = $this->relative_to_absolute_upload_path( $relative );
					$ext      = strtolower( (string) pathinfo( $relative, PATHINFO_EXTENSION ) );

					if ( $relative === '' || $path === '' ) {
						return [
							'status'   => 'skip',
							'reason'   => 'Empty path',
							'relative' => $relative,
						];
					}

					if ( ! is_file( $path ) ) {
						return [
							'status'   => 'skip',
							'reason'   => 'File no longer exists',
							'relative' => $relative,
						];
					}

					if ( $this->is_zero_byte_file( $path ) ) {
						return [
							'status'   => 'candidate',
							'reason'   => '0-byte broken image',
							'type'     => 'zero_byte',
							'relative' => $relative,
						];
					}

					if ( in_array( $ext, [ 'jpg', 'jpeg', 'png' ], true ) ) {
						$webp_relative = $this->swap_extension( $relative, 'webp' );
						$webp_path     = $this->relative_to_absolute_upload_path( $webp_relative );
						if ( $this->is_valid_image_file( $webp_path ) ) {
							return [
								'status'        => 'candidate',
								'reason'        => 'Covered by valid WebP sibling',
								'type'          => 'covered_by_webp',
								'relative'      => $relative,
								'webp_relative' => $webp_relative,
							];
						}
					}

					return [
						'status'   => 'keep',
						'reason'   => 'Keep file',
						'relative' => $relative,
					];
				}

				private function build_legacy_cleanup_candidates(): array {
					$candidates = [];
					$query      = new WP_Query(
						[
							'post_type'              => 'attachment',
							'post_status'            => 'inherit',
							'post_mime_type'         => 'image/webp',
							'fields'                 => 'ids',
							'posts_per_page'         => 500,
							'paged'                  => 1,
							'no_found_rows'          => true,
							'orderby'                => 'ID',
							'order'                  => 'ASC',
							'update_post_meta_cache' => false,
							'update_post_term_cache' => false,
						]
					);

					while ( ! empty( $query->posts ) ) {
						foreach ( $query->posts as $attachment_id ) {
							$attachment_id = (int) $attachment_id;
							if ( $attachment_id <= 0 ) {
								continue;
							}

							foreach ( $this->attachment_legacy_cleanup_candidates( $attachment_id ) as $candidate ) {
								$candidates[] = $candidate;
							}
						}

						$page  = (int) $query->get( 'paged' );
						$query = new WP_Query(
							[
								'post_type'              => 'attachment',
								'post_status'            => 'inherit',
								'post_mime_type'         => 'image/webp',
								'fields'                 => 'ids',
								'posts_per_page'         => 500,
								'paged'                  => $page + 1,
								'no_found_rows'          => true,
								'orderby'                => 'ID',
								'order'                  => 'ASC',
								'update_post_meta_cache' => false,
								'update_post_term_cache' => false,
							]
						);
					}

					return $this->merge_cleanup_candidates( [], $candidates );
				}

				private function attachment_legacy_cleanup_candidates( int $attachment_id ): array {
					$current_relative = $this->get_attachment_relative( $attachment_id );
					$current_relative = $this->normalize_relative_upload_path( $current_relative );
					$current_path     = $this->relative_to_absolute_upload_path( $current_relative );
					$current_ext      = strtolower( (string) pathinfo( $current_relative, PATHINFO_EXTENSION ) );
					$current_mime     = strtolower( (string) get_post_mime_type( $attachment_id ) );

					if ( $current_relative === '' || $current_path === '' || ! $this->is_valid_image_file( $current_path ) ) {
						return [];
					}

					if ( $current_ext !== 'webp' && $current_mime !== 'image/webp' ) {
						return [];
					}

					$old_main_relative = $this->relative_upload_from_url( (string) get_post_meta( $attachment_id, '_sp_webp_original_url', true ) );
					$old_ext           = strtolower( (string) get_post_meta( $attachment_id, '_sp_webp_original_ext', true ) );
					if ( ! in_array( $old_ext, $this->image_extensions(), true ) ) {
						$old_ext = strtolower( (string) pathinfo( $old_main_relative, PATHINFO_EXTENSION ) );
					}

					if ( ! in_array( $old_ext, $this->image_extensions(), true ) ) {
						return [];
					}

					if ( $old_main_relative === '' ) {
						$old_main_relative = $this->swap_extension( $current_relative, $old_ext );
					}

					$old_main_relative = $this->normalize_relative_upload_path( $old_main_relative );
					if ( $old_main_relative === '' ) {
						return [];
					}

					$current_dir      = trim( dirname( $current_relative ), '/\\' );
					$current_dir      = ( $current_dir !== '' && $current_dir !== '.' ) ? $current_dir . '/' : '';
					$old_dir          = trim( dirname( $old_main_relative ), '/\\' );
					$old_dir          = ( $old_dir !== '' && $old_dir !== '.' ) ? $old_dir . '/' : '';
					$current_main_stem = (string) pathinfo( basename( $current_relative ), PATHINFO_FILENAME );
					$old_main_stem     = (string) pathinfo( basename( $old_main_relative ), PATHINFO_FILENAME );
					$candidates        = [];

					$add_candidate = function ( string $legacy_relative, string $paired_webp_relative, string $reason ) use ( $attachment_id, &$candidates ): void {
						$legacy_relative      = $this->normalize_relative_upload_path( $legacy_relative );
						$paired_webp_relative = $this->normalize_relative_upload_path( $paired_webp_relative );
						if ( $legacy_relative === '' || $paired_webp_relative === '' ) {
							return;
						}

						$legacy_path = $this->relative_to_absolute_upload_path( $legacy_relative );
						$paired_path = $this->relative_to_absolute_upload_path( $paired_webp_relative );
						if ( ! is_file( $legacy_path ) || ! $this->is_valid_image_file( $paired_path ) ) {
							return;
						}

						$candidates[] = [
							'status'             => 'candidate',
							'type'               => 'legacy_original',
							'reason'             => $reason,
							'relative'           => $legacy_relative,
							'webp_relative'      => $paired_webp_relative,
							'attachment_id'      => $attachment_id,
						];
					};

					$add_candidate( $old_main_relative, $current_relative, 'Mapped legacy original for WebP attachment' );

					$meta = wp_get_attachment_metadata( $attachment_id );
					if ( is_array( $meta ) && ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
						foreach ( $meta['sizes'] as $size ) {
							if ( ! is_array( $size ) || empty( $size['file'] ) ) {
								continue;
							}

							$current_size_file     = ltrim( (string) $size['file'], '/\\' );
							$current_size_relative = $current_dir . $current_size_file;
							$current_size_stem     = (string) pathinfo( $current_size_file, PATHINFO_FILENAME );
							$suffix                = '';

							if ( $current_main_stem !== '' && str_starts_with( $current_size_stem, $current_main_stem ) ) {
								$suffix = substr( $current_size_stem, strlen( $current_main_stem ) );
							} elseif ( ! empty( $size['width'] ) && ! empty( $size['height'] ) ) {
								$suffix = '-' . (int) $size['width'] . 'x' . (int) $size['height'];
							}

							if ( $suffix === '' ) {
								continue;
							}

							$legacy_size_relative = $old_dir . $old_main_stem . $suffix . '.' . $old_ext;
							$add_candidate( $legacy_size_relative, $current_size_relative, 'Mapped legacy resized image for WebP attachment' );
						}
					}

					return $this->merge_cleanup_candidates( [], $candidates );
				}

				private function cleanup_candidate_still_valid( array $candidate ): bool {
					$relative = $this->normalize_relative_upload_path( (string) ( $candidate['relative'] ?? '' ) );
					$type     = (string) ( $candidate['type'] ?? '' );
					$path     = $this->relative_to_absolute_upload_path( $relative );

					if ( $relative === '' || $path === '' || ! is_file( $path ) ) {
						return false;
					}

					if ( $type === 'zero_byte' ) {
						return $this->is_zero_byte_file( $path );
					}

					if ( $type === 'covered_by_webp' ) {
						$webp_relative = $this->normalize_relative_upload_path( (string) ( $candidate['webp_relative'] ?? '' ) );
						$webp_path     = $this->relative_to_absolute_upload_path( $webp_relative );

						return $this->is_valid_image_file( $webp_path );
					}

					if ( $type === 'legacy_original' ) {
						$webp_relative = $this->normalize_relative_upload_path( (string) ( $candidate['webp_relative'] ?? '' ) );
						$webp_path     = $this->relative_to_absolute_upload_path( $webp_relative );

						return $this->is_valid_image_file( $webp_path ) && $this->is_valid_image_file( $path );
					}

					return false;
				}

				private function attachment_title( int $attachment_id ): string {
					$title = get_the_title( $attachment_id );

					return $title !== '' ? $title : ( 'Attachment #' . $attachment_id );
				}

				private function get_attachment_relative( int $attachment_id ): string {
					$relative = $this->normalize_relative_upload_path( (string) get_post_meta( $attachment_id, '_wp_attached_file', true ) );
					if ( $relative !== '' ) {
						return $relative;
					}

					$file = (string) get_attached_file( $attachment_id );
					if ( $file !== '' ) {
						return $this->path_to_relative_upload( $file );
					}

					return '';
				}

				private function attachment_needs_live_repair( int $attachment_id, string $relative, $meta = null ): bool {
					$relative = $this->normalize_relative_upload_path( $relative );
					$ext      = strtolower( (string) pathinfo( $relative, PATHINFO_EXTENSION ) );
					$mime     = strtolower( (string) get_post_mime_type( $attachment_id ) );

					if ( $ext !== 'webp' && $mime !== 'image/webp' && (string) get_post_meta( $attachment_id, '_sp_webp_original_url', true ) === '' ) {
						return false;
					}

					$current_path = $this->relative_to_absolute_upload_path( $relative );
					if ( ! $this->is_valid_image_file( $current_path ) ) {
						return true;
					}

					if ( ! is_array( $meta ) ) {
						$meta = wp_get_attachment_metadata( $attachment_id );
					}

					if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
						$dir = trim( dirname( $relative ), '/\\' );
						$dir = ( $dir !== '' && $dir !== '.' ) ? $dir . '/' : '';

						foreach ( $meta['sizes'] as $size ) {
							if ( ! is_array( $size ) || empty( $size['file'] ) ) {
								continue;
							}

							$size_path = $this->relative_to_absolute_upload_path( $dir . ltrim( (string) $size['file'], '/\\' ) );
							if ( ! $this->is_valid_image_file( $size_path ) ) {
								return true;
							}
						}
					}

					return false;
				}

				private function find_source_relative( int $attachment_id, string $target_relative ): string {
					$target_relative = $this->normalize_relative_upload_path( $target_relative );
					$dir             = trim( dirname( $target_relative ), '/\\' );
					$dir_prefix      = ( $dir !== '' && $dir !== '.' ) ? $dir . '/' : '';
					$stem            = (string) pathinfo( basename( $target_relative ), PATHINFO_FILENAME );
					$candidates      = [];

					$original_url = (string) get_post_meta( $attachment_id, '_sp_webp_original_url', true );
					$original_ext = strtolower( (string) get_post_meta( $attachment_id, '_sp_webp_original_ext', true ) );
					$meta         = wp_get_attachment_metadata( $attachment_id );

					$original_relative = $this->relative_upload_from_url( $original_url );
					if ( $original_relative !== '' ) {
						$candidates[] = $original_relative;
					}

					if ( in_array( $original_ext, $this->image_extensions(), true ) ) {
						$candidates[] = $this->swap_extension( $target_relative, $original_ext );
					}

					foreach ( $this->image_extensions() as $ext ) {
						$candidates[] = $this->swap_extension( $target_relative, $ext );
						$candidates[] = $dir_prefix . $stem . '.' . $ext;
					}

					if ( is_array( $meta ) && ! empty( $meta['original_image'] ) ) {
						$original_basename = basename( (string) $meta['original_image'] );
						foreach ( $this->image_extensions() as $ext ) {
							$candidates[] = $dir_prefix . preg_replace( '~\.[a-z0-9]+$~i', '.' . $ext, $original_basename );
						}
					}

					$candidates = array_values(
						array_filter(
							array_unique(
								array_map( [ $this, 'normalize_relative_upload_path' ], $candidates )
							)
						)
					);

					foreach ( $candidates as $candidate_relative ) {
						$candidate_path = $this->relative_to_absolute_upload_path( $candidate_relative );
						if ( $this->is_valid_image_file( $candidate_path ) ) {
							$candidate_ext = strtolower( (string) pathinfo( $candidate_relative, PATHINFO_EXTENSION ) );
							if ( in_array( $candidate_ext, $this->image_extensions(), true ) ) {
								return $candidate_relative;
							}
						}
					}

					return '';
				}

				private function ensure_directory( string $file_path ): bool {
					$dir = dirname( $file_path );
					if ( is_dir( $dir ) ) {
						return true;
					}

					return wp_mkdir_p( $dir );
				}

				private function finalize_generated_webp( string $generated_path, string $target_path ): array {
					if ( ! $this->is_valid_image_file( $generated_path ) ) {
						if ( is_file( $generated_path ) ) {
							@unlink( $generated_path );
						}

						return [
							'status'  => 'error',
							'message' => 'Generated WebP is invalid',
						];
					}

					if ( wp_normalize_path( $generated_path ) !== wp_normalize_path( $target_path ) ) {
						if ( ! @rename( $generated_path, $target_path ) ) {
							if ( ! @copy( $generated_path, $target_path ) ) {
								@unlink( $generated_path );

								return [
									'status'  => 'error',
									'message' => 'Unable to move WebP into place',
								];
							}

							@unlink( $generated_path );
						}
					}

					if ( ! $this->is_valid_image_file( $target_path ) ) {
						return [
							'status'  => 'error',
							'message' => 'Final WebP validation failed',
						];
					}

					return [
						'status'  => 'ok',
						'message' => 'WebP created',
					];
				}

				private function create_webp_via_wp_editor( string $source_path, string $target_path ): array {
					$image = wp_get_image_editor( $source_path );
					if ( is_wp_error( $image ) ) {
						return [
							'status'  => 'error',
							'message' => $image->get_error_message(),
							'engine'  => 'wp_editor',
						];
					}

					if ( method_exists( $image, 'set_quality' ) ) {
						$image->set_quality( 90 );
					}

					$tmp_path = $target_path . '.sp-live-' . wp_generate_uuid4() . '.webp';
					$saved    = $image->save( $tmp_path, 'image/webp' );
					if ( is_wp_error( $saved ) ) {
						if ( is_file( $tmp_path ) ) {
							@unlink( $tmp_path );
						}

						return [
							'status'  => 'error',
							'message' => $saved->get_error_message(),
							'engine'  => get_class( $image ),
						];
					}

					$generated_path    = (string) ( $saved['path'] ?? $tmp_path );
					$result            = $this->finalize_generated_webp( $generated_path, $target_path );
					$result['engine']  = get_class( $image );

					return $result;
				}

				private function create_webp_via_gd( string $source_path, string $target_path ): array {
					if ( ! function_exists( 'imagewebp' ) ) {
						return [
							'status'  => 'error',
							'message' => 'GD WebP support is not available',
							'engine'  => 'gd',
						];
					}

					$mime = $this->detect_mime( $source_path );
					if ( $mime === 'image/jpeg' && function_exists( 'imagecreatefromjpeg' ) ) {
						$image = @imagecreatefromjpeg( $source_path );
					} elseif ( $mime === 'image/png' && function_exists( 'imagecreatefrompng' ) ) {
						$image = @imagecreatefrompng( $source_path );
					} elseif ( $mime === 'image/gif' && function_exists( 'imagecreatefromgif' ) ) {
						$image = @imagecreatefromgif( $source_path );
					} else {
						return [
							'status'  => 'error',
							'message' => 'Unsupported mime for GD fallback: ' . $mime,
							'engine'  => 'gd',
						];
					}

					if ( ! $image ) {
						return [
							'status'  => 'error',
							'message' => 'GD could not open source image',
							'engine'  => 'gd',
						];
					}

					if ( function_exists( 'imagepalettetotruecolor' ) ) {
						@imagepalettetotruecolor( $image );
					}
					@imagealphablending( $image, true );
					@imagesavealpha( $image, true );

					$tmp_path = $target_path . '.sp-live-gd-' . wp_generate_uuid4() . '.webp';
					$saved    = @imagewebp( $image, $tmp_path, 90 );
					@imagedestroy( $image );

					if ( ! $saved ) {
						if ( is_file( $tmp_path ) ) {
							@unlink( $tmp_path );
						}

						return [
							'status'  => 'error',
							'message' => 'GD imagewebp() failed',
							'engine'  => 'gd',
						];
					}

					$result           = $this->finalize_generated_webp( $tmp_path, $target_path );
					$result['engine'] = 'gd';

					return $result;
				}

				private function create_webp_from_source( string $source_path, string $target_path ): array {
					$result = [
						'status'  => 'error',
						'message' => 'Unable to create WebP',
					];

					if ( ! $this->is_valid_image_file( $source_path ) ) {
						$result['message'] = 'Source image is not readable';

						return $result;
					}

					if ( ! $this->ensure_directory( $target_path ) ) {
						$result['message'] = 'Unable to create target directory';

						return $result;
					}

					$source_mime = $this->detect_mime( $source_path );
					$source_size = @getimagesize( $source_path );

					$wp_result = $this->create_webp_via_wp_editor( $source_path, $target_path );
					if ( $wp_result['status'] === 'ok' ) {
						return $wp_result;
					}

					$gd_result = $this->create_webp_via_gd( $source_path, $target_path );
					if ( $gd_result['status'] === 'ok' ) {
						$gd_result['message'] = 'WebP created via GD fallback after ' . (string) ( $wp_result['engine'] ?? 'wp_editor' ) . ' failed';

						return $gd_result;
					}

					$result['message'] = sprintf(
						'WebP failed. mime=%s size=%sx%s wp=%s (%s) gd=%s (%s)',
						$source_mime !== '' ? $source_mime : 'unknown',
						(string) ( $source_size[0] ?? 0 ),
						(string) ( $source_size[1] ?? 0 ),
						(string) ( $wp_result['engine'] ?? 'wp_editor' ),
						(string) ( $wp_result['message'] ?? 'error' ),
						(string) ( $gd_result['engine'] ?? 'gd' ),
						(string) ( $gd_result['message'] ?? 'error' )
					);

					return $result;
				}

				private function assess_attachment( int $attachment_id ): array {
					$title            = $this->attachment_title( $attachment_id );
					$current_meta     = wp_get_attachment_metadata( $attachment_id );
					$current_relative = $this->get_attachment_relative( $attachment_id );

					if ( $current_relative === '' ) {
						return [
							'status'           => 'skipped',
							'title'            => $title,
							'message'          => 'Attachment file path is empty',
							'target_relative'  => '',
							'source_relative'  => '',
							'created_webp'     => false,
							'regenerated_only' => false,
						];
					}

					if ( ! $this->attachment_needs_live_repair( $attachment_id, $current_relative, $current_meta ) ) {
						return [
							'status'           => 'healthy',
							'title'            => $title,
							'message'          => 'Attachment already looks healthy',
							'target_relative'  => $current_relative,
							'source_relative'  => '',
							'created_webp'     => false,
							'regenerated_only' => false,
						];
					}

					$target_relative = $current_relative;
					$target_ext      = strtolower( (string) pathinfo( $target_relative, PATHINFO_EXTENSION ) );
					if ( $target_ext !== 'webp' ) {
						$target_relative = $this->swap_extension( $target_relative, 'webp' );
					}

					$source_relative = $this->find_source_relative( $attachment_id, $target_relative );
					if ( $source_relative === '' ) {
						return [
							'status'           => 'missing_source',
							'title'            => $title,
							'message'          => 'Source jpg/png/gif not found in uploads',
							'target_relative'  => $target_relative,
							'source_relative'  => '',
							'created_webp'     => false,
							'regenerated_only' => false,
						];
					}

					$target_path     = $this->relative_to_absolute_upload_path( $target_relative );
					$target_is_valid = $this->is_valid_image_file( $target_path );

					return [
						'status'           => 'repairable',
						'title'            => $title,
						'message'          => $target_is_valid ? 'Metadata needs regeneration' : 'Missing WebP can be recreated',
						'target_relative'  => $target_relative,
						'source_relative'  => $source_relative,
						'created_webp'     => ! $target_is_valid,
						'regenerated_only' => $target_is_valid,
					];
				}

				private function repair_attachment( int $attachment_id ): array {
					$assessment = $this->assess_attachment( $attachment_id );
					$title      = (string) $assessment['title'];

					if ( $assessment['status'] === 'healthy' || $assessment['status'] === 'skipped' ) {
						return [
							'status'       => 'skipped',
							'title'        => $title,
							'message'      => (string) $assessment['message'],
							'created_webp' => false,
							'regenerated'  => false,
						];
					}

					if ( $assessment['status'] === 'missing_source' ) {
						return [
							'status'       => 'missing_source',
							'title'        => $title,
							'message'      => (string) $assessment['message'],
							'created_webp' => false,
							'regenerated'  => false,
						];
					}

					$target_relative = (string) $assessment['target_relative'];
					$source_relative = (string) $assessment['source_relative'];
					$source_path     = $this->relative_to_absolute_upload_path( $source_relative );
					$target_path     = $this->relative_to_absolute_upload_path( $target_relative );
					$created         = false;

					if ( ! $this->is_valid_image_file( $target_path ) ) {
						$created_result = $this->create_webp_from_source( $source_path, $target_path );
						if ( $created_result['status'] !== 'ok' ) {
							return [
								'status'       => 'error',
								'title'        => $title,
								'message'      => $created_result['message'],
								'created_webp' => false,
								'regenerated'  => false,
							];
						}

						$created = true;
					}

					require_once ABSPATH . 'wp-admin/includes/image.php';

					update_attached_file( $attachment_id, $target_relative );
					wp_update_post(
						[
							'ID'             => $attachment_id,
							'post_mime_type' => 'image/webp',
							'guid'           => $this->upload_url_from_relative( $target_relative ),
						]
					);

					$new_meta = wp_generate_attachment_metadata( $attachment_id, $target_path );
					if ( is_wp_error( $new_meta ) || ! is_array( $new_meta ) ) {
						$size     = @getimagesize( $target_path );
						$new_meta = [
							'width'  => (int) ( $size[0] ?? 0 ),
							'height' => (int) ( $size[1] ?? 0 ),
							'file'   => $target_relative,
							'sizes'  => [],
						];
					}

					wp_update_attachment_metadata( $attachment_id, $new_meta );

					$source_ext = strtolower( (string) pathinfo( $source_relative, PATHINFO_EXTENSION ) );
					if ( in_array( $source_ext, $this->image_extensions(), true ) ) {
						update_post_meta( $attachment_id, '_sp_webp_original_ext', $source_ext );
					}

					if ( (string) get_post_meta( $attachment_id, '_sp_webp_original_url', true ) === '' ) {
						update_post_meta( $attachment_id, '_sp_webp_original_url', $this->upload_url_from_relative( $source_relative ) );
					}

					update_post_meta( $attachment_id, '_sp_webp_live_repair_at', current_time( 'mysql' ) );
					delete_transient( 'sp_webp_url_replace_map_cache' );

					return [
						'status'       => 'repaired',
						'title'        => $title,
						'message'      => $created ? 'WebP recreated and metadata regenerated' : 'Metadata regenerated from existing WebP',
						'created_webp' => $created,
						'regenerated'  => true,
					];
				}

				private function count_candidate_attachments(): int {
					global $wpdb;

					return (int) $wpdb->get_var(
						"SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type='attachment' AND post_status='inherit' AND post_mime_type LIKE 'image/%'"
					);
				}

				private function query_next_attachment_ids( int $after_id, int $limit ): array {
					global $wpdb;

					$limit = min( 20, max( 1, $limit ) );
					$ids   = $wpdb->get_col(
						$wpdb->prepare(
							"SELECT ID FROM {$wpdb->posts} WHERE post_type='attachment' AND post_status='inherit' AND post_mime_type LIKE 'image/%%' AND ID > %d ORDER BY ID ASC LIMIT %d",
							$after_id,
							$limit
						)
					);

					if ( ! is_array( $ids ) ) {
						return [];
					}

					return array_values( array_filter( array_map( 'intval', $ids ) ) );
				}

				public function ajax_prepare(): void {
					$this->ajax_guard();

					wp_send_json_success(
						[
							'total'      => $this->count_candidate_attachments(),
							'batch_size' => self::BATCH_SIZE,
						]
					);
				}

				public function ajax_prepare_scan(): void {
					$this->ajax_guard();

					wp_send_json_success(
						[
							'total'      => $this->count_candidate_attachments(),
							'batch_size' => self::BATCH_SIZE,
						]
					);
				}

				public function ajax_scan_batch(): void {
					$this->ajax_guard();

					$last_id = isset( $_POST['last_id'] ) ? (int) $_POST['last_id'] : 0;
					$limit   = isset( $_POST['limit'] ) ? (int) $_POST['limit'] : self::BATCH_SIZE;
					$limit   = min( 20, max( 1, $limit ) );

					@set_time_limit( 20 );

					$ids = $this->query_next_attachment_ids( $last_id, $limit );
					if ( empty( $ids ) ) {
						wp_send_json_success(
							[
								'done'            => true,
								'last_id'         => $last_id,
								'processed'       => 0,
								'repairable'      => 0,
								'create_webp'     => 0,
								'regenerate_only' => 0,
								'missing_source'  => 0,
								'healthy'         => 0,
								'skipped'         => 0,
								'errors'          => 0,
								'log'             => [],
							]
						);
					}

					$processed       = 0;
					$repairable      = 0;
					$create_webp     = 0;
					$regenerate_only = 0;
					$missing_source  = 0;
					$healthy         = 0;
					$skipped         = 0;
					$errors          = 0;
					$log             = [];
					$started_at      = microtime( true );

					foreach ( $ids as $attachment_id ) {
						$attachment_id = (int) $attachment_id;
						$last_id       = max( $last_id, $attachment_id );
						$processed ++;

						try {
							$result = $this->assess_attachment( $attachment_id );

							if ( $result['status'] === 'repairable' ) {
								$repairable ++;
								if ( ! empty( $result['created_webp'] ) ) {
									$create_webp ++;
								}
								if ( ! empty( $result['regenerated_only'] ) ) {
									$regenerate_only ++;
								}
								if ( count( $log ) < 12 ) {
									$log[] = '[TODO] ' . $result['title'] . ': ' . $result['message'];
								}
							} elseif ( $result['status'] === 'missing_source' ) {
								$missing_source ++;
								if ( count( $log ) < 12 ) {
									$log[] = '[-] ' . $result['title'] . ': ' . $result['message'];
								}
							} elseif ( $result['status'] === 'healthy' ) {
								$healthy ++;
							} elseif ( $result['status'] === 'skipped' ) {
								$skipped ++;
							} else {
								$errors ++;
								if ( count( $log ) < 12 ) {
									$log[] = '[ERR] ' . $result['title'] . ': ' . ( $result['message'] ?? 'Unexpected scan result' );
								}
							}
						} catch ( Throwable $e ) {
							$errors ++;
							if ( count( $log ) < 12 ) {
								$log[] = '[ERR] Attachment #' . $attachment_id . ': ' . $e->getMessage();
							}
						}

						if ( ( microtime( true ) - $started_at ) >= self::SOFT_LIMIT_SEC ) {
							if ( count( $log ) < 12 ) {
								$log[] = '[-] Soft time limit reached, continuing in next batch';
							}
							break;
						}
					}

					$has_more = ! empty( $this->query_next_attachment_ids( $last_id, 1 ) );

					wp_send_json_success(
						[
							'done'            => ! $has_more,
							'last_id'         => $last_id,
							'processed'       => $processed,
							'repairable'      => $repairable,
							'create_webp'     => $create_webp,
							'regenerate_only' => $regenerate_only,
							'missing_source'  => $missing_source,
							'healthy'         => $healthy,
							'skipped'         => $skipped,
							'errors'          => $errors,
							'log'             => $log,
						]
					);
				}

				public function ajax_batch(): void {
					$this->ajax_guard();

					$last_id = isset( $_POST['last_id'] ) ? (int) $_POST['last_id'] : 0;
					$limit   = isset( $_POST['limit'] ) ? (int) $_POST['limit'] : self::BATCH_SIZE;
					$limit   = min( 10, max( 1, $limit ) );

					@set_time_limit( 20 );
					wp_raise_memory_limit( 'image' );

					$ids = $this->query_next_attachment_ids( $last_id, $limit );
					if ( empty( $ids ) ) {
						wp_send_json_success(
							[
								'done'           => true,
								'last_id'        => $last_id,
								'processed'      => 0,
								'repaired'       => 0,
								'created_webp'   => 0,
								'regenerated'    => 0,
								'missing_source' => 0,
								'skipped'        => 0,
								'errors'         => 0,
								'log'            => [],
							]
						);
					}

					$processed      = 0;
					$repaired       = 0;
					$created_webp   = 0;
					$regenerated    = 0;
					$missing_source = 0;
					$skipped        = 0;
					$errors         = 0;
					$log            = [];
					$started_at     = microtime( true );

					foreach ( $ids as $attachment_id ) {
						$attachment_id = (int) $attachment_id;
						$last_id       = max( $last_id, $attachment_id );
						$processed ++;

						try {
							$result = $this->repair_attachment( $attachment_id );

							if ( $result['status'] === 'repaired' ) {
								$repaired ++;
								if ( ! empty( $result['created_webp'] ) ) {
									$created_webp ++;
								}
								if ( ! empty( $result['regenerated'] ) ) {
									$regenerated ++;
								}
								if ( count( $log ) < 12 ) {
									$log[] = '[OK] ' . $result['title'] . ': ' . $result['message'];
								}
							} elseif ( $result['status'] === 'missing_source' ) {
								$missing_source ++;
								if ( count( $log ) < 12 ) {
									$log[] = '[-] ' . $result['title'] . ': ' . $result['message'];
								}
							} elseif ( $result['status'] === 'skipped' ) {
								$skipped ++;
							} else {
								$errors ++;
								if ( count( $log ) < 12 ) {
									$log[] = '[ERR] ' . $result['title'] . ': ' . $result['message'];
								}
							}
						} catch ( Throwable $e ) {
							$errors ++;
							if ( count( $log ) < 12 ) {
								$log[] = '[ERR] Attachment #' . $attachment_id . ': ' . $e->getMessage();
							}
						}

						if ( ( microtime( true ) - $started_at ) >= self::SOFT_LIMIT_SEC ) {
							if ( count( $log ) < 12 ) {
								$log[] = '[-] Soft time limit reached, continuing in next batch';
							}
							break;
						}
					}

					$has_more = ! empty( $this->query_next_attachment_ids( $last_id, 1 ) );

					wp_send_json_success(
						[
							'done'           => ! $has_more,
							'last_id'        => $last_id,
							'processed'      => $processed,
							'repaired'       => $repaired,
							'created_webp'   => $created_webp,
							'regenerated'    => $regenerated,
							'missing_source' => $missing_source,
							'skipped'        => $skipped,
							'errors'         => $errors,
							'log'            => $log,
						]
					);
				}

				public function ajax_cleanup_prepare_scan(): void {
					$this->ajax_guard();

					$files = $this->collect_upload_image_files();
					$this->set_cleanup_files( $files );
					$this->set_cleanup_candidates( [] );

					wp_send_json_success(
						[
							'total'      => count( $files ),
							'batch_size' => self::CLEANUP_BATCH_SIZE,
						]
					);
				}

				public function ajax_cleanup_scan_batch(): void {
					$this->ajax_guard();

					$offset = isset( $_POST['offset'] ) ? (int) $_POST['offset'] : 0;
					$limit  = isset( $_POST['limit'] ) ? (int) $_POST['limit'] : self::CLEANUP_BATCH_SIZE;
					$limit  = min( 500, max( 1, $limit ) );

					$files = $this->get_cleanup_files();
					$total = count( $files );
					if ( $total <= 0 || $offset >= $total ) {
						$candidates = $this->get_cleanup_candidates();
						wp_send_json_success(
							[
								'done'            => true,
								'offset'          => $offset,
								'processed'       => 0,
								'candidates'      => count( $candidates ),
								'covered_by_webp' => count( array_filter( $candidates, static fn( $item ) => ( $item['type'] ?? '' ) === 'covered_by_webp' ) ),
								'zero_byte'       => count( array_filter( $candidates, static fn( $item ) => ( $item['type'] ?? '' ) === 'zero_byte' ) ),
								'kept'            => 0,
								'errors'          => 0,
								'log'             => [],
							]
						);
					}

					$batch      = array_slice( $files, $offset, $limit );
					$candidates = $this->get_cleanup_candidates();
					$processed  = 0;
					$found      = 0;
					$covered    = 0;
					$zero_byte  = 0;
					$kept       = 0;
					$errors     = 0;
					$log        = [];

					foreach ( $batch as $relative ) {
						$processed ++;
						try {
							$result = $this->assess_cleanup_file( (string) $relative );
							if ( ( $result['status'] ?? '' ) === 'candidate' ) {
								$candidates[] = $result;
								$found ++;
								if ( ( $result['type'] ?? '' ) === 'covered_by_webp' ) {
									$covered ++;
								} elseif ( ( $result['type'] ?? '' ) === 'zero_byte' ) {
									$zero_byte ++;
								}
								if ( count( $log ) < 12 ) {
									$log[] = '[TODO] ' . (string) $result['relative'] . ': ' . (string) $result['reason'];
								}
							} else {
								$kept ++;
							}
						} catch ( Throwable $e ) {
							$errors ++;
							if ( count( $log ) < 12 ) {
								$log[] = '[ERR] ' . (string) $relative . ': ' . $e->getMessage();
							}
						}
					}

					$this->set_cleanup_candidates( $candidates );
					$next_offset = $offset + count( $batch );

					wp_send_json_success(
						[
							'done'            => $next_offset >= $total,
							'offset'          => $next_offset,
							'processed'       => $processed,
							'candidates'      => count( $candidates ),
							'covered_by_webp' => $covered,
							'zero_byte'       => $zero_byte,
							'kept'            => $kept,
							'errors'          => $errors,
							'log'             => $log,
						]
					);
				}

				public function ajax_cleanup_delete_batch(): void {
					$this->ajax_guard();

					$offset     = isset( $_POST['offset'] ) ? (int) $_POST['offset'] : 0;
					$limit      = isset( $_POST['limit'] ) ? (int) $_POST['limit'] : self::CLEANUP_BATCH_SIZE;
					$limit      = min( 500, max( 1, $limit ) );
					$candidates = $this->get_cleanup_candidates();
					$total      = count( $candidates );

					if ( $total <= 0 || $offset >= $total ) {
						wp_send_json_success(
							[
								'done'      => true,
								'offset'    => $offset,
								'processed' => 0,
								'deleted'   => 0,
								'skipped'   => 0,
								'errors'    => 0,
								'log'       => [],
							]
						);
					}

					$batch     = array_slice( $candidates, $offset, $limit );
					$processed = 0;
					$deleted   = 0;
					$skipped   = 0;
					$errors    = 0;
					$log       = [];

					foreach ( $batch as $candidate ) {
						$processed ++;
						$relative = $this->normalize_relative_upload_path( (string) ( $candidate['relative'] ?? '' ) );
						$path     = $this->relative_to_absolute_upload_path( $relative );

						try {
							if ( ! $this->cleanup_candidate_still_valid( (array) $candidate ) ) {
								$skipped ++;
								if ( count( $log ) < 12 ) {
									$log[] = '[-] ' . $relative . ': candidate no longer valid';
								}
								continue;
							}

							if ( ! @unlink( $path ) ) {
								$errors ++;
								if ( count( $log ) < 12 ) {
									$log[] = '[ERR] ' . $relative . ': unable to delete file';
								}
								continue;
							}

							$deleted ++;
							if ( count( $log ) < 12 ) {
								$log[] = '[OK] Deleted ' . $relative;
							}
						} catch ( Throwable $e ) {
							$errors ++;
							if ( count( $log ) < 12 ) {
								$log[] = '[ERR] ' . $relative . ': ' . $e->getMessage();
							}
						}
					}

					$next_offset = $offset + count( $batch );

					wp_send_json_success(
						[
							'done'      => $next_offset >= $total,
							'offset'    => $next_offset,
							'processed' => $processed,
							'deleted'   => $deleted,
							'skipped'   => $skipped,
							'errors'    => $errors,
							'log'       => $log,
						]
					);
				}

				public function menu(): void {
					add_options_page(
						'Uploads WebP Live Repair',
						'<span style="display:flex;align-items:center;gap:6px">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 329.2 329.2">
                              <path fill="currentColor" d="M65 131.4c1.2 1 3 1.5 4.6 1.2q2.6-.7 3.8-3c.3-.6 3.3-5.9 13.5-5 13 1.2 21.4 7 21.5 7.1a5.4 5.4 0 0 0 6.8-.4l32.2-29.5a5.4 5.4 0 0 0 1.2-6.4l-13.5-27c14-23.9 42.3-25.8 50.8-25.8h9a5.4 5.4 0 0 0 3.6-9.3 75 75 0 0 0-52.3-19.5c-41 0-83.3 27.1-92 45.4a136 136 0 0 1-23 34.8 5.4 5.4 0 0 0 .2 7.8zM38.2 166.4a5.4 5.4 0 0 0 7.6-.7l18.8-22.9a5.4 5.4 0 0 0-.6-7.4l-35-31q-1.6-1.3-3.8-1.3-2.2.2-3.7 1.9L1.3 127.8a5.3 5.3 0 0 0 .6 7.6zM328 264.5q-.4-1-1.2-1.8l-92-88.1-37.3-36.5 77-71.3 4 3.9q1.6 1.5 3.7 1.6h.2q2.4-.2 4-2l28.3-33.6a5.4 5.4 0 0 0-.7-7.7l-10.5-8.4a5.4 5.4 0 0 0-6.8 0l-33.3 26.6a5.4 5.4 0 0 0-.4 8l4 4-77.2 71.4-28.5-27.8c-2-2-5.2-2-7.3-.2l-37 32.2a5.4 5.4 0 0 0-.3 7.8l27.9 29.8-3.6 3.2-13.5-13.7c-2-2-5.2-2.2-7.4-.3L2.7 262.5q-1 .8-1.4 2c-4 9 1.7 23.4 14 35.7 9.3 9.4 20.5 15.2 29.2 15.2q4.5 0 7.9-2 .6-.3 1-.8L162 204.3c2-2.1 2-5.5 0-7.6l-13.3-13.4 3.3-3.1 123.7 132.3 1.3 1q3.4 1.9 7.8 1.9c8.7 0 20-5.8 29.3-15.2 12.3-12.3 17.9-26.6 14-35.7m-309.2 9.3a2.7 2.7 0 0 1-1.7-4.7l98.6-85.4a2.7 2.7 0 0 1 3.5 4l-98.6 85.5q-.8.6-1.8.6M33 288q-1.1 0-1.9-.8c-1-1-1-2.7 0-3.8l93.1-90.6a2.7 2.7 0 0 1 3.8 3.9l-93.1 90.6q-.8.7-1.9.7m108-82.6-92 92.6q-.8.8-1.9.8t-1.9-.8c-1-1-1-2.7 0-3.8l92-92.6a2.7 2.7 0 1 1 3.9 3.8"/>
                            </svg>
						    WebP Live Repair
                        </span>',
						'manage_options',
						self::PAGE_SLUG,
						[ $this, 'page' ]
					);
				}

				public function admin_assets( string $hook ): void {
					if ( $hook !== 'settings_page_' . self::PAGE_SLUG ) {
						return;
					}

					wp_enqueue_script( 'jquery' );
				}

				public function page(): void {
					$nonce = wp_create_nonce( self::NONCE_ACTION );
					?>
                    <div class="sp-live-webp-admin">
                        <div class="sp-live-webp-admin__header">
                            <div>
                                <div class="sp-live-webp-admin__title">Uploads WebP Live Repair</div>
                                <div class="sp-live-webp-admin__subtitle">Recreates missing .webp files that the current DB already expects, then regenerates attachment metadata for Media Library previews.</div>
                            </div>
                            <div class="sp-live-webp-admin__actions">
                                <button type="button" class="sp-live-webp-btn" id="sp-live-webp-scan">Scan only</button>
                                <button type="button" class="sp-live-webp-btn sp-live-webp-btn--primary" id="sp-live-webp-start">Run live repair</button>
                                <button type="button" class="sp-live-webp-btn" id="sp-live-webp-cleanup-scan">Scan cleanup</button>
                                <button type="button" class="sp-live-webp-btn" id="sp-live-webp-cleanup-delete" disabled>Delete found cleanup files</button>
                                <button type="button" class="sp-live-webp-btn" id="sp-live-webp-stop" disabled>Stop</button>
                                <button type="button" class="sp-live-webp-btn" id="sp-live-webp-clear">Clear log</button>
                            </div>
                        </div>

                        <div class="sp-live-webp-panel" style="margin-bottom:16px;">
                            <div class="sp-live-webp-panel__title">Pre-scan</div>
                            <div class="sp-live-webp-stats">
                                <div class="sp-live-webp-stat"><div class="sp-live-webp-stat__label">Images</div><div class="sp-live-webp-stat__value" id="slws-total">0</div></div>
                                <div class="sp-live-webp-stat"><div class="sp-live-webp-stat__label">Scanned</div><div class="sp-live-webp-stat__value" id="slws-processed">0</div></div>
                                <div class="sp-live-webp-stat"><div class="sp-live-webp-stat__label">Need repair</div><div class="sp-live-webp-stat__value" id="slws-repairable">0</div></div>
                                <div class="sp-live-webp-stat"><div class="sp-live-webp-stat__label">Create WebP</div><div class="sp-live-webp-stat__value" id="slws-create">0</div></div>
                                <div class="sp-live-webp-stat"><div class="sp-live-webp-stat__label">Metadata only</div><div class="sp-live-webp-stat__value" id="slws-meta">0</div></div>
                                <div class="sp-live-webp-stat"><div class="sp-live-webp-stat__label">Missing source</div><div class="sp-live-webp-stat__value" id="slws-missing">0</div></div>
                                <div class="sp-live-webp-stat"><div class="sp-live-webp-stat__label">Healthy</div><div class="sp-live-webp-stat__value" id="slws-healthy">0</div></div>
                                <div class="sp-live-webp-stat"><div class="sp-live-webp-stat__label">Errors</div><div class="sp-live-webp-stat__value" id="slws-errors">0</div></div>
                            </div>
                            <div class="sp-live-webp-progress"><span id="slws-progress-bar"></span></div>
                            <div class="sp-live-webp-progress-meta">
                                <span id="slws-progress-text">0%</span>
                                <span id="slws-status">Run a scan first to see how many files can be repaired before creating anything.</span>
                            </div>
                        </div>

                        <div class="sp-live-webp-panel">
                            <div class="sp-live-webp-panel__title">Status</div>
                            <div class="sp-live-webp-stats">
                                <div class="sp-live-webp-stat"><div class="sp-live-webp-stat__label">Images</div><div class="sp-live-webp-stat__value" id="slw-total">0</div></div>
                                <div class="sp-live-webp-stat"><div class="sp-live-webp-stat__label">Processed</div><div class="sp-live-webp-stat__value" id="slw-processed">0</div></div>
                                <div class="sp-live-webp-stat"><div class="sp-live-webp-stat__label">Repaired</div><div class="sp-live-webp-stat__value" id="slw-repaired">0</div></div>
                                <div class="sp-live-webp-stat"><div class="sp-live-webp-stat__label">WebP created</div><div class="sp-live-webp-stat__value" id="slw-created">0</div></div>
                                <div class="sp-live-webp-stat"><div class="sp-live-webp-stat__label">Metadata regenerated</div><div class="sp-live-webp-stat__value" id="slw-regenerated">0</div></div>
                                <div class="sp-live-webp-stat"><div class="sp-live-webp-stat__label">Missing source</div><div class="sp-live-webp-stat__value" id="slw-missing">0</div></div>
                                <div class="sp-live-webp-stat"><div class="sp-live-webp-stat__label">Skipped</div><div class="sp-live-webp-stat__value" id="slw-skipped">0</div></div>
                                <div class="sp-live-webp-stat"><div class="sp-live-webp-stat__label">Errors</div><div class="sp-live-webp-stat__value" id="slw-errors">0</div></div>
                            </div>
                            <div class="sp-live-webp-progress"><span id="slw-progress-bar"></span></div>
                            <div class="sp-live-webp-progress-meta">
                                <span id="slw-progress-text">0%</span>
                                <span id="slw-status">Use this on live when the DB already points to .webp, but uploads were restored from older jpg/png files.</span>
                            </div>
                        </div>

                        <div class="sp-live-webp-panel" style="margin-top:16px;">
                            <div class="sp-live-webp-panel__title">Uploads Cleanup</div>
                            <div class="sp-live-webp-stats">
                                <div class="sp-live-webp-stat"><div class="sp-live-webp-stat__label">Files</div><div class="sp-live-webp-stat__value" id="slwc-total">0</div></div>
                                <div class="sp-live-webp-stat"><div class="sp-live-webp-stat__label">Scanned</div><div class="sp-live-webp-stat__value" id="slwc-processed">0</div></div>
                                <div class="sp-live-webp-stat"><div class="sp-live-webp-stat__label">Delete candidates</div><div class="sp-live-webp-stat__value" id="slwc-candidates">0</div></div>
                                <div class="sp-live-webp-stat"><div class="sp-live-webp-stat__label">Covered by WebP</div><div class="sp-live-webp-stat__value" id="slwc-covered">0</div></div>
                                <div class="sp-live-webp-stat"><div class="sp-live-webp-stat__label">0-byte broken</div><div class="sp-live-webp-stat__value" id="slwc-zero">0</div></div>
                                <div class="sp-live-webp-stat"><div class="sp-live-webp-stat__label">Deleted</div><div class="sp-live-webp-stat__value" id="slwc-deleted">0</div></div>
                                <div class="sp-live-webp-stat"><div class="sp-live-webp-stat__label">Skipped</div><div class="sp-live-webp-stat__value" id="slwc-skipped">0</div></div>
                                <div class="sp-live-webp-stat"><div class="sp-live-webp-stat__label">Errors</div><div class="sp-live-webp-stat__value" id="slwc-errors">0</div></div>
                            </div>
                            <div class="sp-live-webp-progress"><span id="slwc-progress-bar"></span></div>
                            <div class="sp-live-webp-progress-meta">
                                <span id="slwc-progress-text">0%</span>
                                <span id="slwc-status">Scans the whole uploads folder and finds jpg/jpeg/png with a valid same-name .webp sibling, plus 0-byte image files.</span>
                            </div>
                        </div>

                        <div class="sp-live-webp-panel" style="margin-top:16px;">
                            <div class="sp-live-webp-panel__title">Log</div>
                            <div class="sp-live-webp-log" id="sp-live-webp-log"></div>
                        </div>
                    </div>

                    <style>
                        .sp-live-webp-admin { margin: 20px 20px 0 0; }
                        .sp-live-webp-admin__header { display:flex; justify-content:space-between; gap:16px; margin-bottom:16px; align-items:flex-start; }
                        .sp-live-webp-admin__title { font-size:22px; font-weight:700; margin-bottom:6px; }
                        .sp-live-webp-admin__subtitle { font-size:13px; color:#666; max-width:900px; }
                        .sp-live-webp-admin__actions { display:flex; flex-wrap:wrap; gap:8px; }
                        .sp-live-webp-panel { background:#fff; border:1px solid #dcdcde; border-radius:8px; padding:18px 20px; }
                        .sp-live-webp-panel__title { font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#666; margin-bottom:14px; }
                        .sp-live-webp-stats { display:grid; grid-template-columns:repeat(4, minmax(150px, 1fr)); gap:10px; margin-bottom:14px; }
                        .sp-live-webp-stat { padding:10px; border:1px solid #dcdcde; border-radius:6px; }
                        .sp-live-webp-stat__label { font-size:11px; color:#777; margin-bottom:4px; }
                        .sp-live-webp-stat__value { font-size:18px; font-weight:700; }
                        .sp-live-webp-progress { height:8px; background:#e6e7e8; border-radius:999px; overflow:hidden; }
                        .sp-live-webp-progress span { display:block; height:100%; width:0; background:linear-gradient(90deg, #2271b1 0%, #2b8bd5 100%); }
                        .sp-live-webp-progress-meta { display:flex; justify-content:space-between; font-size:12px; color:#666; margin-top:8px; gap:16px; }
                        .sp-live-webp-log { font-family:ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size:12px; line-height:1.45; background:#0f172a; color:#d5d9e2; border-radius:8px; padding:12px; height:360px; overflow:auto; white-space:pre-wrap; }
                        .sp-live-webp-btn { display:inline-flex; align-items:center; padding:7px 12px; border-radius:6px; border:1px solid #dcdcde; background:#fff; cursor:pointer; }
                        .sp-live-webp-btn--primary { background:#2271b1; border-color:#2271b1; color:#fff; }
                        .sp-live-webp-btn:disabled { opacity:.5; cursor:not-allowed; }
                        @media (max-width: 1200px) {
                            .sp-live-webp-admin__header { flex-direction:column; }
                            .sp-live-webp-stats { grid-template-columns:repeat(2, minmax(160px, 1fr)); }
                        }
                    </style>

                    <script>
                        (function ($) {
                            const nonce = <?php echo wp_json_encode( $nonce ); ?>;
                            const run = {active: false, mode: '', retries: 0};
                            const scan = {
                                total: 0,
                                lastId: 0,
                                processed: 0,
                                repairable: 0,
                                create: 0,
                                meta: 0,
                                missing: 0,
                                healthy: 0,
                                errors: 0,
                                batchSize: <?php echo (int) self::BATCH_SIZE; ?>,
                                prepared: false,
                                failed: false
                            };
                            const state = {
                                total: 0,
                                lastId: 0,
                                processed: 0,
                                repaired: 0,
                                created: 0,
                                regenerated: 0,
                                missing: 0,
                                skipped: 0,
                                errors: 0,
                                batchSize: <?php echo (int) self::BATCH_SIZE; ?>,
                                prepared: false,
                                failed: false
                            };
                            const cleanup = {
                                total: 0,
                                offset: 0,
                                processed: 0,
                                candidates: 0,
                                covered: 0,
                                zero: 0,
                                deleted: 0,
                                skipped: 0,
                                errors: 0,
                                batchSize: <?php echo (int) self::CLEANUP_BATCH_SIZE; ?>,
                                prepared: false,
                                failed: false,
                                scanned: false
                            };
                            const $log = $('#sp-live-webp-log');

                            function appendLog(line) {
                                if (!line) return;
                                const ts = new Date().toLocaleTimeString();
                                $log.append('[' + ts + '] ' + line + '\n');
                                $log.scrollTop($log[0].scrollHeight);
                            }

                            function setRun(active) {
                                run.active = !!active;
                                run.mode = run.active ? run.mode : '';
                                $('#sp-live-webp-scan').prop('disabled', run.active);
                                $('#sp-live-webp-start').prop('disabled', run.active);
                                $('#sp-live-webp-cleanup-scan').prop('disabled', run.active);
                                $('#sp-live-webp-cleanup-delete').prop('disabled', run.active || !cleanup.scanned || cleanup.candidates <= 0);
                                $('#sp-live-webp-stop').prop('disabled', !run.active);
                            }

                            function updateCleanupStats() {
                                $('#slwc-total').text(cleanup.total);
                                $('#slwc-processed').text(cleanup.processed);
                                $('#slwc-candidates').text(cleanup.candidates);
                                $('#slwc-covered').text(cleanup.covered);
                                $('#slwc-zero').text(cleanup.zero);
                                $('#slwc-deleted').text(cleanup.deleted);
                                $('#slwc-skipped').text(cleanup.skipped);
                                $('#slwc-errors').text(cleanup.errors);
                                const percent = cleanup.total > 0 ? Math.min(100, Math.round((cleanup.processed / cleanup.total) * 100)) : 0;
                                $('#slwc-progress-bar').css('width', percent + '%');
                                $('#slwc-progress-text').text(percent + '%');
                                $('#sp-live-webp-cleanup-delete').prop('disabled', run.active || !cleanup.scanned || cleanup.candidates <= 0);
                            }

                            function updateScanStats() {
                                $('#slws-total').text(scan.total);
                                $('#slws-processed').text(scan.processed);
                                $('#slws-repairable').text(scan.repairable);
                                $('#slws-create').text(scan.create);
                                $('#slws-meta').text(scan.meta);
                                $('#slws-missing').text(scan.missing);
                                $('#slws-healthy').text(scan.healthy);
                                $('#slws-errors').text(scan.errors);
                                const percent = scan.total > 0 ? Math.min(100, Math.round((scan.processed / scan.total) * 100)) : 0;
                                $('#slws-progress-bar').css('width', percent + '%');
                                $('#slws-progress-text').text(percent + '%');
                            }

                            function updateStats() {
                                $('#slw-total').text(state.total);
                                $('#slw-processed').text(state.processed);
                                $('#slw-repaired').text(state.repaired);
                                $('#slw-created').text(state.created);
                                $('#slw-regenerated').text(state.regenerated);
                                $('#slw-missing').text(state.missing);
                                $('#slw-skipped').text(state.skipped);
                                $('#slw-errors').text(state.errors);
                                const percent = state.total > 0 ? Math.min(100, Math.round((state.processed / state.total) * 100)) : 0;
                                $('#slw-progress-bar').css('width', percent + '%');
                                $('#slw-progress-text').text(percent + '%');
                            }

                            function prepareScan(callback) {
                                $('#slws-status').text('Preparing scan...');
                                $.post(ajaxurl, {
                                    action: 'sp_webp_live_repair_prepare_scan',
                                    nonce
                                }, function (res) {
                                    if (!res || !res.success) {
                                        $('#slws-status').text('Prepare failed');
                                        appendLog('[ERR] Failed to prepare live repair scan');
                                        if (typeof callback === 'function') callback(false);
                                        return;
                                    }

                                    scan.total = Number(res.data.total || 0);
                                    scan.lastId = 0;
                                    scan.processed = 0;
                                    scan.repairable = 0;
                                    scan.create = 0;
                                    scan.meta = 0;
                                    scan.missing = 0;
                                    scan.healthy = 0;
                                    scan.errors = 0;
                                    scan.batchSize = Number(res.data.batch_size || scan.batchSize || 3);
                                    scan.prepared = true;
                                    scan.failed = false;
                                    run.retries = 0;
                                    updateScanStats();
                                    appendLog('[OK] Prepared live repair scan. Images: ' + scan.total);
                                    if (typeof callback === 'function') callback(true);
                                }).fail(function () {
                                    $('#slws-status').text('Prepare failed');
                                    appendLog('[ERR] Failed to prepare live repair scan');
                                    if (typeof callback === 'function') callback(false);
                                });
                            }

                            function prepare(callback) {
                                $('#slw-status').text('Preparing live repair...');
                                $.post(ajaxurl, {
                                    action: 'sp_webp_live_repair_prepare',
                                    nonce
                                }, function (res) {
                                    if (!res || !res.success) {
                                        $('#slw-status').text('Prepare failed');
                                        appendLog('[ERR] Failed to prepare live repair');
                                        if (typeof callback === 'function') callback(false);
                                        return;
                                    }

                                    state.total = Number(res.data.total || 0);
                                    state.lastId = 0;
                                    state.processed = 0;
                                    state.repaired = 0;
                                    state.created = 0;
                                    state.regenerated = 0;
                                    state.missing = 0;
                                    state.skipped = 0;
                                    state.errors = 0;
                                    state.batchSize = Number(res.data.batch_size || state.batchSize || 3);
                                    state.prepared = true;
                                    state.failed = false;
                                    run.retries = 0;
                                    updateStats();
                                    appendLog('[OK] Prepared live repair. Images: ' + state.total);
                                    if (typeof callback === 'function') callback(true);
                                }).fail(function () {
                                    $('#slw-status').text('Prepare failed');
                                    appendLog('[ERR] Failed to prepare live repair');
                                    if (typeof callback === 'function') callback(false);
                                });
                            }

                            function prepareCleanupScan(callback) {
                                $('#slwc-status').text('Preparing uploads cleanup scan...');
                                $.post(ajaxurl, {
                                    action: 'sp_webp_live_cleanup_prepare_scan',
                                    nonce
                                }, function (res) {
                                    if (!res || !res.success) {
                                        $('#slwc-status').text('Prepare failed');
                                        appendLog('[ERR] Failed to prepare uploads cleanup scan');
                                        if (typeof callback === 'function') callback(false);
                                        return;
                                    }

                                    cleanup.total = Number(res.data.total || 0);
                                    cleanup.offset = 0;
                                    cleanup.processed = 0;
                                    cleanup.candidates = 0;
                                    cleanup.covered = 0;
                                    cleanup.zero = 0;
                                    cleanup.deleted = 0;
                                    cleanup.skipped = 0;
                                    cleanup.errors = 0;
                                    cleanup.batchSize = Number(res.data.batch_size || cleanup.batchSize || 250);
                                    cleanup.prepared = true;
                                    cleanup.failed = false;
                                    cleanup.scanned = false;
                                    run.retries = 0;
                                    updateCleanupStats();
                                    appendLog('[OK] Prepared uploads cleanup scan. Files: ' + cleanup.total);
                                    if (typeof callback === 'function') callback(true);
                                }).fail(function () {
                                    $('#slwc-status').text('Prepare failed');
                                    appendLog('[ERR] Failed to prepare uploads cleanup scan');
                                    if (typeof callback === 'function') callback(false);
                                });
                            }

                            function runScanBatch() {
                                if (!run.active || run.mode !== 'scan') return;

                                $.post(ajaxurl, {
                                    action: 'sp_webp_live_repair_scan_batch',
                                    nonce,
                                    last_id: scan.lastId,
                                    limit: scan.batchSize
                                }, function (res) {
                                    if (!run.active || run.mode !== 'scan') return;

                                    if (!res || !res.success) {
                                        setRun(false);
                                        scan.failed = true;
                                        $('#slws-status').text('Scan failed');
                                        appendLog('[ERR] Live repair scan batch failed');
                                        return;
                                    }

                                    const d = res.data || {};
                                    run.retries = 0;
                                    scan.failed = false;
                                    scan.lastId = Number(d.last_id || scan.lastId);
                                    scan.processed += Number(d.processed || 0);
                                    scan.repairable += Number(d.repairable || 0);
                                    scan.create += Number(d.create_webp || 0);
                                    scan.meta += Number(d.regenerate_only || 0);
                                    scan.missing += Number(d.missing_source || 0);
                                    scan.healthy += Number(d.healthy || 0);
                                    scan.errors += Number(d.errors || 0);
                                    (d.log || []).forEach(appendLog);
                                    updateScanStats();

                                    if (d.done) {
                                        setRun(false);
                                        $('#slws-status').text('Scan completed');
                                        appendLog('[OK] Scan done. Need repair: ' + scan.repairable + ', create WebP: ' + scan.create + ', metadata only: ' + scan.meta + ', missing source: ' + scan.missing);
                                        return;
                                    }

                                    $('#slws-status').text('Scanning attachments...');
                                    setTimeout(runScanBatch, 80);
                                }).fail(function () {
                                    if (!run.active || run.mode !== 'scan') return;
                                    run.retries += 1;

                                    if (scan.batchSize > 1) {
                                        const oldBatch = scan.batchSize;
                                        scan.batchSize = Math.max(1, Math.floor(scan.batchSize / 2));
                                        $('#slws-status').text('Retrying scan with smaller batches...');
                                        appendLog('[-] Scan network error. Reducing batch size ' + oldBatch + ' -> ' + scan.batchSize);
                                        setTimeout(runScanBatch, 500);
                                        return;
                                    }

                                    if (run.retries <= 2) {
                                        $('#slws-status').text('Retrying current scan batch...');
                                        appendLog('[-] Scan network error. Retrying current batch.');
                                        setTimeout(runScanBatch, 900);
                                        return;
                                    }

                                    scan.failed = true;
                                    setRun(false);
                                    $('#slws-status').text('Scan failed');
                                    appendLog('[ERR] Scan network error near attachment ID > ' + scan.lastId);
                                });
                            }

                            function runBatch() {
                                if (!run.active || run.mode !== 'repair') return;

                                $.post(ajaxurl, {
                                    action: 'sp_webp_live_repair_batch',
                                    nonce,
                                    last_id: state.lastId,
                                    limit: state.batchSize
                                }, function (res) {
                                    if (!run.active || run.mode !== 'repair') return;

                                    if (!res || !res.success) {
                                        setRun(false);
                                        state.failed = true;
                                        $('#slw-status').text('Live repair failed');
                                        appendLog('[ERR] Live repair batch failed');
                                        return;
                                    }

                                    const d = res.data || {};
                                    run.retries = 0;
                                    state.failed = false;
                                    state.lastId = Number(d.last_id || state.lastId);
                                    state.processed += Number(d.processed || 0);
                                    state.repaired += Number(d.repaired || 0);
                                    state.created += Number(d.created_webp || 0);
                                    state.regenerated += Number(d.regenerated || 0);
                                    state.missing += Number(d.missing_source || 0);
                                    state.skipped += Number(d.skipped || 0);
                                    state.errors += Number(d.errors || 0);
                                    (d.log || []).forEach(appendLog);
                                    updateStats();

                                    if (d.done) {
                                        setRun(false);
                                        $('#slw-status').text('Live repair completed');
                                        appendLog('[OK] Live repair done. Repaired: ' + state.repaired + ', WebP created: ' + state.created + ', metadata regenerated: ' + state.regenerated);
                                        return;
                                    }

                                    $('#slw-status').text('Repairing live WebP files...');
                                    setTimeout(runBatch, 80);
                                }).fail(function () {
                                    if (!run.active) return;
                                    run.retries += 1;

                                    if (state.batchSize > 1) {
                                        const oldBatch = state.batchSize;
                                        state.batchSize = Math.max(1, Math.floor(state.batchSize / 2));
                                        $('#slw-status').text('Retrying with smaller batches...');
                                        appendLog('[-] Network error. Reducing batch size ' + oldBatch + ' -> ' + state.batchSize);
                                        setTimeout(runBatch, 500);
                                        return;
                                    }

                                    if (run.retries <= 2) {
                                        $('#slw-status').text('Retrying current batch...');
                                        appendLog('[-] Network error. Retrying current batch.');
                                        setTimeout(runBatch, 900);
                                        return;
                                    }

                                    state.failed = true;
                                    setRun(false);
                                    $('#slw-status').text('Live repair failed');
                                    appendLog('[ERR] Network error near attachment ID > ' + state.lastId);
                                });
                            }

                            function runCleanupScanBatch() {
                                if (!run.active || run.mode !== 'cleanup_scan') return;

                                $.post(ajaxurl, {
                                    action: 'sp_webp_live_cleanup_scan_batch',
                                    nonce,
                                    offset: cleanup.offset,
                                    limit: cleanup.batchSize
                                }, function (res) {
                                    if (!run.active || run.mode !== 'cleanup_scan') return;

                                    if (!res || !res.success) {
                                        setRun(false);
                                        cleanup.failed = true;
                                        $('#slwc-status').text('Cleanup scan failed');
                                        appendLog('[ERR] Uploads cleanup scan batch failed');
                                        return;
                                    }

                                    const d = res.data || {};
                                    run.retries = 0;
                                    cleanup.failed = false;
                                    cleanup.offset = Number(d.offset || cleanup.offset);
                                    cleanup.processed += Number(d.processed || 0);
                                    cleanup.candidates = Number(d.candidates || cleanup.candidates);
                                    cleanup.covered += Number(d.covered_by_webp || 0);
                                    cleanup.zero += Number(d.zero_byte || 0);
                                    cleanup.skipped += Number(d.kept || 0);
                                    cleanup.errors += Number(d.errors || 0);
                                    (d.log || []).forEach(appendLog);
                                    updateCleanupStats();

                                    if (d.done) {
                                        cleanup.scanned = true;
                                        setRun(false);
                                        $('#slwc-status').text('Cleanup scan completed');
                                        appendLog('[OK] Cleanup scan done. Candidates: ' + cleanup.candidates + ', covered by WebP: ' + cleanup.covered + ', 0-byte: ' + cleanup.zero);
                                        updateCleanupStats();
                                        return;
                                    }

                                    $('#slwc-status').text('Scanning uploads folder...');
                                    setTimeout(runCleanupScanBatch, 50);
                                }).fail(function () {
                                    if (!run.active || run.mode !== 'cleanup_scan') return;
                                    run.retries += 1;
                                    if (run.retries <= 2) {
                                        $('#slwc-status').text('Retrying cleanup scan batch...');
                                        appendLog('[-] Cleanup scan network error. Retrying current batch.');
                                        setTimeout(runCleanupScanBatch, 500);
                                        return;
                                    }

                                    cleanup.failed = true;
                                    setRun(false);
                                    $('#slwc-status').text('Cleanup scan failed');
                                    appendLog('[ERR] Cleanup scan network error near offset ' + cleanup.offset);
                                });
                            }

                            function runCleanupDeleteBatch() {
                                if (!run.active || run.mode !== 'cleanup_delete') return;

                                $.post(ajaxurl, {
                                    action: 'sp_webp_live_cleanup_delete_batch',
                                    nonce,
                                    offset: cleanup.offset,
                                    limit: cleanup.batchSize
                                }, function (res) {
                                    if (!run.active || run.mode !== 'cleanup_delete') return;

                                    if (!res || !res.success) {
                                        setRun(false);
                                        cleanup.failed = true;
                                        $('#slwc-status').text('Cleanup delete failed');
                                        appendLog('[ERR] Uploads cleanup delete batch failed');
                                        return;
                                    }

                                    const d = res.data || {};
                                    run.retries = 0;
                                    cleanup.failed = false;
                                    cleanup.offset = Number(d.offset || cleanup.offset);
                                    cleanup.processed += Number(d.processed || 0);
                                    cleanup.deleted += Number(d.deleted || 0);
                                    cleanup.skipped += Number(d.skipped || 0);
                                    cleanup.errors += Number(d.errors || 0);
                                    (d.log || []).forEach(appendLog);
                                    updateCleanupStats();

                                    if (d.done) {
                                        setRun(false);
                                        $('#slwc-status').text('Cleanup delete completed');
                                        appendLog('[OK] Cleanup delete done. Deleted: ' + cleanup.deleted + ', skipped: ' + cleanup.skipped);
                                        return;
                                    }

                                    $('#slwc-status').text('Deleting cleanup candidates...');
                                    setTimeout(runCleanupDeleteBatch, 50);
                                }).fail(function () {
                                    if (!run.active || run.mode !== 'cleanup_delete') return;
                                    run.retries += 1;
                                    if (run.retries <= 2) {
                                        $('#slwc-status').text('Retrying cleanup delete batch...');
                                        appendLog('[-] Cleanup delete network error. Retrying current batch.');
                                        setTimeout(runCleanupDeleteBatch, 500);
                                        return;
                                    }

                                    cleanup.failed = true;
                                    setRun(false);
                                    $('#slwc-status').text('Cleanup delete failed');
                                    appendLog('[ERR] Cleanup delete network error near offset ' + cleanup.offset);
                                });
                            }

                            $('#sp-live-webp-start').on('click', function () {
                                if (run.active) return;

                                if (state.prepared && state.failed && state.lastId > 0) {
                                    state.failed = false;
                                    run.retries = 0;
                                    run.mode = 'repair';
                                    setRun(true);
                                    $('#slw-status').text('Resuming live repair...');
                                    appendLog('[>] Resuming live repair from attachment ID > ' + state.lastId);
                                    runBatch();
                                    return;
                                }

                                prepare(function (ok) {
                                    if (!ok) return;
                                    if (state.total <= 0) {
                                        $('#slw-status').text('No image attachments found');
                                        appendLog('[-] No image attachments found');
                                        return;
                                    }

                                    run.mode = 'repair';
                                    setRun(true);
                                    $('#slw-status').text('Starting live repair...');
                                    appendLog('[>] Live repair started');
                                    runBatch();
                                });
                            });

                            $('#sp-live-webp-scan').on('click', function () {
                                if (run.active) return;

                                if (scan.prepared && scan.failed && scan.lastId > 0) {
                                    scan.failed = false;
                                    run.retries = 0;
                                    run.mode = 'scan';
                                    setRun(true);
                                    $('#slws-status').text('Resuming scan...');
                                    appendLog('[>] Resuming live repair scan from attachment ID > ' + scan.lastId);
                                    runScanBatch();
                                    return;
                                }

                                prepareScan(function (ok) {
                                    if (!ok) return;
                                    if (scan.total <= 0) {
                                        $('#slws-status').text('No image attachments found');
                                        appendLog('[-] No image attachments found');
                                        return;
                                    }

                                    run.mode = 'scan';
                                    setRun(true);
                                    $('#slws-status').text('Starting scan...');
                                    appendLog('[>] Live repair scan started');
                                    runScanBatch();
                                });
                            });

                            $('#sp-live-webp-cleanup-scan').on('click', function () {
                                if (run.active) return;

                                if (cleanup.prepared && cleanup.failed && cleanup.offset > 0 && !cleanup.scanned) {
                                    cleanup.failed = false;
                                    run.retries = 0;
                                    run.mode = 'cleanup_scan';
                                    setRun(true);
                                    $('#slwc-status').text('Resuming cleanup scan...');
                                    appendLog('[>] Resuming uploads cleanup scan from offset ' + cleanup.offset);
                                    runCleanupScanBatch();
                                    return;
                                }

                                prepareCleanupScan(function (ok) {
                                    if (!ok) return;
                                    if (cleanup.total <= 0) {
                                        $('#slwc-status').text('No image files found in uploads');
                                        appendLog('[-] No image files found in uploads');
                                        return;
                                    }

                                    run.mode = 'cleanup_scan';
                                    setRun(true);
                                    $('#slwc-status').text('Starting uploads cleanup scan...');
                                    appendLog('[>] Uploads cleanup scan started');
                                    runCleanupScanBatch();
                                });
                            });

                            $('#sp-live-webp-cleanup-delete').on('click', function () {
                                if (run.active || !cleanup.scanned || cleanup.candidates <= 0) return;

                                cleanup.offset = 0;
                                cleanup.processed = 0;
                                cleanup.deleted = 0;
                                cleanup.skipped = 0;
                                cleanup.errors = 0;
                                cleanup.failed = false;
                                run.retries = 0;
                                run.mode = 'cleanup_delete';
                                setRun(true);
                                $('#slwc-status').text('Starting cleanup delete...');
                                appendLog('[>] Uploads cleanup delete started');
                                updateCleanupStats();
                                runCleanupDeleteBatch();
                            });

                            $('#sp-live-webp-stop').on('click', function () {
                                if (!run.active) return;
                                setRun(false);
                                if (run.mode === 'scan') {
                                    $('#slws-status').text('Stopped by user');
                                    appendLog('[-] Live repair scan stopped by user');
                                } else if (run.mode === 'cleanup_scan') {
                                    $('#slwc-status').text('Stopped by user');
                                    appendLog('[-] Uploads cleanup scan stopped by user');
                                } else if (run.mode === 'cleanup_delete') {
                                    $('#slwc-status').text('Stopped by user');
                                    appendLog('[-] Uploads cleanup delete stopped by user');
                                } else {
                                    $('#slw-status').text('Stopped by user');
                                    appendLog('[-] Live repair stopped by user');
                                }
                            });

                            $('#sp-live-webp-clear').on('click', function () {
                                if (run.active) return;
                                $log.text('');
                                appendLog('[OK] Log cleared');
                            });

                            updateScanStats();
                            updateStats();
                            updateCleanupStats();
                        })(jQuery);
                    </script>
					<?php
				}
			}
		}

		SP_Uploads_WebP_Live_Repair::get();

		SP_Uploads_WebP_Convert::get();
	}
