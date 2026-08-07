<?php
    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

    if ( ! class_exists( 'SP_Uploads_WebP_Convert' ) ) {
        class SP_Uploads_WebP_Convert {
            private const OPT_KEY      = 'sp_webp_convert_cfg';
            private const NONCE_ACTION = 'sp_webp_convert_admin';
            private const PAGE_SLUG    = 'sp-uploads-webp-convert';
            private const VERSION      = '2.2.0';
            private const URL_MAP_TRANSIENT = 'sp_webp_url_replace_map_cache';
            private const UNUSED_SCHEMA_TRANSIENT = 'sp_webp_unused_schema_cache';
            private const UNUSED_SCHEMA_CACHE_VERSION = 4;

            private static ?self $instance = null;
            private string $unused_schema_error = '';
            private string $last_table_search_error = '';

            public static function get(): self {
                if ( ! self::$instance ) {
                    self::$instance = new self();
                }

                return self::$instance;
            }

            private function __construct() {
                add_filter( 'wp_handle_upload', [ $this, 'convert_on_upload' ], 20 );
                add_filter( 'wp_unique_filename', [ $this, 'make_filename_unique_for_webp' ], 10, 3 );
                add_filter( 'upload_mimes', [ $this, 'allow_webp_mime' ] );
                add_filter( 'wp_check_filetype_and_ext', [ $this, 'fix_webp_filetype' ], 10, 5 );

                add_action( 'admin_menu', [ $this, 'menu' ] );
                add_action( 'admin_enqueue_scripts', [ $this, 'admin_assets' ] );
                add_filter( 'media_row_actions', [ $this, 'media_row_replace_action' ], 10, 2 );
                add_filter( 'attachment_fields_to_edit', [ $this, 'attachment_replace_field' ], 10, 2 );

                add_action( 'wp_ajax_sp_webp_save_settings', [ $this, 'ajax_save_settings' ] );
                add_action( 'wp_ajax_sp_webp_scan_media', [ $this, 'ajax_scan_media' ] );
                add_action( 'wp_ajax_sp_webp_convert_batch', [ $this, 'ajax_convert_batch' ] );
                add_action( 'wp_ajax_sp_webp_prepare_url_replace', [ $this, 'ajax_prepare_url_replace' ] );
                add_action( 'wp_ajax_sp_webp_replace_urls_batch', [ $this, 'ajax_replace_urls_batch' ] );
                add_action( 'wp_ajax_sp_webp_prepare_unused_scan', [ $this, 'ajax_prepare_unused_scan' ] );
                add_action( 'wp_ajax_sp_webp_scan_unused_batch', [ $this, 'ajax_scan_unused_batch' ] );
                add_action( 'wp_ajax_sp_webp_delete_unused_batch', [ $this, 'ajax_delete_unused_batch' ] );
                add_action( 'wp_ajax_sp_webp_replace_attachment_file', [ $this, 'ajax_replace_attachment_file' ] );
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

            private function image_mime_from_extension( string $ext ): string {
                $ext = strtolower( ltrim( trim( $ext ), '.' ) );
                $mime_by_ext = [
                        'jpg'  => 'image/jpeg',
                        'jpeg' => 'image/jpeg',
                        'png'  => 'image/png',
                        'gif'  => 'image/gif',
                        'webp' => 'image/webp',
                        'svg'  => 'image/svg+xml',
                ];

                return (string) ( $mime_by_ext[ $ext ] ?? '' );
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
                $mime_by_ext = $this->image_mime_from_extension( $ext );
                if ( $mime_by_ext !== '' ) {
                    return $mime_by_ext;
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

            private function image_dimensions( string $file ): array {
                $size = @getimagesize( $file );
                if ( ! is_array( $size ) || empty( $size[0] ) || empty( $size[1] ) ) {
                    return [];
                }

                return [
                        'width'  => (int) $size[0],
                        'height' => (int) $size[1],
                ];
            }

            private function needs_resize_for_max_side( array $size, int $max ): bool {
                return $max > 0
                       && ! empty( $size['width'] )
                       && ! empty( $size['height'] )
                       && ( (int) $size['width'] > $max || (int) $size['height'] > $max );
            }

            private function resized_dimensions_for_max_side( int $width, int $height, int $max ): array {
                if ( $width <= 0 || $height <= 0 || $max <= 0 ) {
                    return [ 0, 0 ];
                }

                $ratio = min( $max / $width, $max / $height, 1 );

                return [
                        max( 1, (int) round( $width * $ratio ) ),
                        max( 1, (int) round( $height * $ratio ) ),
                ];
            }

            private function shorthand_to_bytes( string $value ): int {
                $value = trim( $value );
                if ( $value === '' ) {
                    return 0;
                }

                if ( $value === '-1' ) {
                    return -1;
                }

                $unit = strtolower( substr( $value, -1 ) );
                $size = (float) $value;

                if ( $unit === 'g' ) {
                    $size *= 1024 * 1024 * 1024;
                } elseif ( $unit === 'm' ) {
                    $size *= 1024 * 1024;
                } elseif ( $unit === 'k' ) {
                    $size *= 1024;
                }

                return (int) $size;
            }

            private function has_memory_for_gd_resize( int $width, int $height, int $new_width, int $new_height ): bool {
                if ( function_exists( 'wp_raise_memory_limit' ) ) {
                    wp_raise_memory_limit( 'image' );
                }

                $limit = $this->shorthand_to_bytes( (string) ini_get( 'memory_limit' ) );
                if ( $limit <= 0 ) {
                    return true;
                }

                $source_pixels = $width * $height;
                $target_pixels = $new_width * $new_height;
                $needed        = (int) ceil( ( $source_pixels + $target_pixels ) * 5 + 32 * 1024 * 1024 );
                $available     = $limit - (int) memory_get_usage( true );

                return $available > $needed;
            }

            private function is_gd_image( $image ): bool {
                return is_resource( $image ) || ( class_exists( 'GdImage' ) && $image instanceof GdImage );
            }

            private function create_gd_image_from_file( string $file, string $mime ) {
                if ( $mime === 'image/jpeg' && function_exists( 'imagecreatefromjpeg' ) ) {
                    return @imagecreatefromjpeg( $file );
                }

                if ( $mime === 'image/png' && function_exists( 'imagecreatefrompng' ) ) {
                    return @imagecreatefrompng( $file );
                }

                if ( $mime === 'image/gif' && function_exists( 'imagecreatefromgif' ) ) {
                    return @imagecreatefromgif( $file );
                }

                return false;
            }

            private function destroy_gd_image( $image ): void {
                if ( $this->is_gd_image( $image ) ) {
                    imagedestroy( $image );
                }
            }

            private function prepare_resized_source_with_gd( string $file, string $mime, int $max ): array {
                $size = $this->image_dimensions( $file );
                if ( empty( $size ) ) {
                    return [
                            'status'  => 'skipped',
                            'message' => 'Unable to read source image dimensions before resize',
                    ];
                }

                [ $new_width, $new_height ] = $this->resized_dimensions_for_max_side( (int) $size['width'], (int) $size['height'], $max );
                if ( $new_width <= 0 || $new_height <= 0 ) {
                    return [
                            'status'  => 'skipped',
                            'message' => 'Unable to calculate resized image dimensions',
                    ];
                }

                if ( ! function_exists( 'imagecreatetruecolor' ) || ! function_exists( 'imagecopyresampled' ) ) {
                    return [
                            'status'  => 'skipped',
                            'message' => 'Large image skipped: GD resize functions are unavailable',
                    ];
                }

                if ( ! $this->has_memory_for_gd_resize( (int) $size['width'], (int) $size['height'], $new_width, $new_height ) ) {
                    return [
                            'status'  => 'skipped',
                            'message' => 'Large image skipped: PHP memory is too low for safe pre-resize',
                    ];
                }

                $source = $this->create_gd_image_from_file( $file, $mime );
                if ( ! $this->is_gd_image( $source ) ) {
                    return [
                            'status'  => 'skipped',
                            'message' => 'Large image skipped: GD cannot open this source file',
                    ];
                }

                $target = imagecreatetruecolor( $new_width, $new_height );
                if ( ! $this->is_gd_image( $target ) ) {
                    $this->destroy_gd_image( $source );
                    return [
                            'status'  => 'skipped',
                            'message' => 'Large image skipped: GD cannot allocate resized canvas',
                    ];
                }

                imagealphablending( $target, false );
                imagesavealpha( $target, true );

                if ( ! imagecopyresampled( $target, $source, 0, 0, 0, 0, $new_width, $new_height, (int) $size['width'], (int) $size['height'] ) ) {
                    $this->destroy_gd_image( $target );
                    $this->destroy_gd_image( $source );
                    return [
                            'status'  => 'skipped',
                            'message' => 'Large image skipped: GD resize failed',
                    ];
                }

                $tmp_ext  = $mime === 'image/jpeg' ? 'jpg' : 'png';
                $tmp_file = trailingslashit( dirname( $file ) ) . '.sp-webp-pre-resize-' . wp_generate_uuid4() . '.' . $tmp_ext;
                $saved    = false;

                if ( $tmp_ext === 'jpg' && function_exists( 'imagejpeg' ) ) {
                    $saved = @imagejpeg( $target, $tmp_file, 95 );
                } elseif ( function_exists( 'imagepng' ) ) {
                    $saved = @imagepng( $target, $tmp_file, 6 );
                }

                $this->destroy_gd_image( $target );
                $this->destroy_gd_image( $source );

                if ( ! $saved || ! is_file( $tmp_file ) || ! @getimagesize( $tmp_file ) ) {
                    if ( is_file( $tmp_file ) ) {
                        @unlink( $tmp_file );
                    }

                    return [
                            'status'  => 'skipped',
                            'message' => 'Large image skipped: resized temporary file was not created',
                    ];
                }

                return [
                        'status' => 'prepared',
                        'path'   => $tmp_file,
                ];
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

                $max          = (int) $cfg['max_side'];
                $working_file = $file;
                $tmp_source   = '';
                $source_size  = $this->image_dimensions( $file );
                if ( $this->needs_resize_for_max_side( $source_size, $max ) ) {
                    $prepared = $this->prepare_resized_source_with_gd( $file, $mime, $max );
                    if ( (string) ( $prepared['status'] ?? '' ) !== 'prepared' ) {
                        $result['status']  = (string) ( $prepared['status'] ?? 'skipped' );
                        $result['message'] = (string) ( $prepared['message'] ?? 'Large image skipped before conversion' );
                        return $result;
                    }

                    $working_file       = (string) $prepared['path'];
                    $tmp_source         = $working_file;
                    $result['resized']  = true;
                }

                $image = wp_get_image_editor( $working_file );
                if ( is_wp_error( $image ) ) {
                    if ( $tmp_source !== '' && is_file( $tmp_source ) ) {
                        @unlink( $tmp_source );
                    }
                    $result['status']  = 'error';
                    $result['message'] = $image->get_error_message();
                    return $result;
                }

                $size = $image->get_size();
                if ( ! $result['resized'] && $this->needs_resize_for_max_side( $size, $max ) ) {
                    $resized = $image->resize( $max, $max, false );
                    if ( is_wp_error( $resized ) ) {
                        if ( $tmp_source !== '' && is_file( $tmp_source ) ) {
                            @unlink( $tmp_source );
                        }
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
                    if ( $tmp_source !== '' && is_file( $tmp_source ) ) {
                        @unlink( $tmp_source );
                    }
                    $result['status']  = 'error';
                    $result['message'] = 'Unable to create target directory';
                    return $result;
                }

                $tmp_target  = $target . '.sp-tmp-' . wp_generate_uuid4() . '.webp';
                $result_save = $image->save( $tmp_target, 'image/webp' );

                if ( is_wp_error( $result_save ) ) {
                    if ( $tmp_source !== '' && is_file( $tmp_source ) ) {
                        @unlink( $tmp_source );
                    }
                    if ( is_file( $tmp_target ) ) {
                        @unlink( $tmp_target );
                    }
                    $result['status']  = 'error';
                    $result['message'] = $result_save->get_error_message();
                    return $result;
                }

                $path = (string) ( $result_save['path'] ?? $tmp_target );
                if ( $tmp_source !== '' && is_file( $tmp_source ) ) {
                    @unlink( $tmp_source );
                }

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

            /**
             * Ensures that the uploaded filename is unique not only for the source extension,
             * but also for the target .webp extension if conversion is enabled.
             *
             * @param string $filename Unique filename determined by WordPress.
             * @param string $ext      File extension (e.g. .jpg).
             * @param string $dir      Target directory path.
             * @return string Unique filename.
             */
            public function make_filename_unique_for_webp( string $filename, string $ext, string $dir ): string {
                $cfg = $this->cfg();
                if ( empty( $cfg['enabled_upload'] ) ) {
                    return $filename;
                }

                $ext_normalized = strtolower( ltrim( $ext, '.' ) );
                $supported_exts = [ 'jpg', 'jpeg', 'png', 'gif' ];
                if ( ! in_array( $ext_normalized, $supported_exts, true ) ) {
                    return $filename;
                }

                $info = pathinfo( $filename );
                $name = $info['filename'];

                // We need to find a suffix such that neither the original image ($name . $ext)
                // nor the converted webp ($name . '.webp') exists in the directory.
                $new_filename = $filename;
                $number       = 1;

                while ( file_exists( $dir . '/' . $new_filename ) || file_exists( $dir . '/' . $name . '.webp' ) ) {
                    $new_filename = $info['filename'] . '-' . $number . $ext;
                    $name         = $info['filename'] . '-' . $number;
                    $number++;
                }

                return $new_filename;
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

            private function is_replaceable_attachment_post( $post ): bool {
                if ( ! $post instanceof WP_Post || $post->post_type !== 'attachment' ) {
                    return false;
                }

                $mime = strtolower( (string) get_post_mime_type( $post ) );
                return str_starts_with( $mime, 'image/' );
            }

            private function current_user_can_replace_attachment( int $attachment_id ): bool {
                return current_user_can( 'upload_files' ) && current_user_can( 'edit_post', $attachment_id );
            }

            private function replacement_supported_mimes(): array {
                return [
                        'image/gif',
                        'image/jpeg',
                        'image/png',
                        'image/svg+xml',
                        'image/webp',
                ];
            }

            private function detect_replacement_upload_mime( string $tmp_file, string $original_name ): string {
                $mime = $this->detect_mime( $tmp_file );
                if ( $mime !== '' ) {
                    return $mime;
                }

                return $this->image_mime_from_extension( (string) pathinfo( $original_name, PATHINFO_EXTENSION ) );
            }

            private function uploaded_svg_looks_valid( string $tmp_file ): bool {
                if ( ! is_readable( $tmp_file ) ) {
                    return false;
                }

                $contents = (string) file_get_contents( $tmp_file, false, null, 0, 1024 * 1024 );
                if ( $contents === '' ) {
                    return false;
                }

                return (bool) preg_match( '~<svg[\s>]~i', $contents );
            }

            private function replacement_mime_matches_attachment( string $current_mime, string $replacement_mime ): bool {
                $current_mime     = strtolower( trim( $current_mime ) );
                $replacement_mime = strtolower( trim( $replacement_mime ) );

                if ( $current_mime === $replacement_mime ) {
                    return true;
                }

                return $current_mime === 'image/jpg' && $replacement_mime === 'image/jpeg';
            }

            private function fallback_attachment_metadata( string $file, string $relative, string $mime ): array {
                $meta = [
                        'file'  => $relative,
                        'sizes' => [],
                ];

                if ( $mime !== 'image/svg+xml' ) {
                    $size = @getimagesize( $file );
                    if ( is_array( $size ) ) {
                        $meta['width']  = (int) ( $size[0] ?? 0 );
                        $meta['height'] = (int) ( $size[1] ?? 0 );
                    }
                }

                return $meta;
            }

            private function replace_attachment_file_in_place( int $attachment_id, array $upload ): array {
                $post = get_post( $attachment_id );
                if ( ! $this->is_replaceable_attachment_post( $post ) ) {
                    return [
                            'status'  => 'error',
                            'message' => 'Attachment is not an image.',
                    ];
                }

                if ( ! $this->current_user_can_replace_attachment( $attachment_id ) ) {
                    return [
                            'status'  => 'error',
                            'message' => 'You are not allowed to replace this attachment.',
                    ];
                }

                $current_file = (string) get_attached_file( $attachment_id );
                if ( $current_file === '' || ! is_file( $current_file ) || ! is_writable( $current_file ) ) {
                    return [
                            'status'  => 'error',
                            'message' => 'Current attachment file is missing or not writable.',
                    ];
                }

                $relative = $this->path_to_relative_upload( $current_file );
                if ( $relative === '' ) {
                    return [
                            'status'  => 'error',
                            'message' => 'Current attachment is outside the uploads directory.',
                    ];
                }

                $error_code = (int) ( $upload['error'] ?? UPLOAD_ERR_NO_FILE );
                if ( $error_code !== UPLOAD_ERR_OK ) {
                    return [
                            'status'  => 'error',
                            'message' => 'Replacement upload failed with code ' . $error_code . '.',
                    ];
                }

                $tmp_file = isset( $upload['tmp_name'] ) ? (string) $upload['tmp_name'] : '';
                if ( $tmp_file === '' || ! is_uploaded_file( $tmp_file ) || ! is_readable( $tmp_file ) ) {
                    return [
                            'status'  => 'error',
                            'message' => 'Replacement upload is missing or unreadable.',
                    ];
                }

                $max_size = (int) wp_max_upload_size();
                $size     = (int) ( $upload['size'] ?? 0 );
                if ( $max_size > 0 && $size > $max_size ) {
                    return [
                            'status'  => 'error',
                            'message' => 'Replacement file exceeds the maximum upload size.',
                    ];
                }

                $current_mime     = $this->detect_mime( $current_file, (string) get_post_mime_type( $attachment_id ) );
                $replacement_mime = $this->detect_replacement_upload_mime( $tmp_file, (string) ( $upload['name'] ?? '' ) );
                if ( ! in_array( $replacement_mime, $this->replacement_supported_mimes(), true ) ) {
                    return [
                            'status'  => 'error',
                            'message' => 'Replacement file type is not supported.',
                    ];
                }

                if ( ! $this->replacement_mime_matches_attachment( $current_mime, $replacement_mime ) ) {
                    return [
                            'status'  => 'error',
                            'message' => 'Use the same image format to keep the existing filename and URL.',
                    ];
                }

                if ( $replacement_mime === 'image/svg+xml' ) {
                    if ( ! $this->uploaded_svg_looks_valid( $tmp_file ) ) {
                        return [
                                'status'  => 'error',
                                'message' => 'Replacement SVG does not look valid.',
                        ];
                    }
                } elseif ( ! @getimagesize( $tmp_file ) ) {
                    return [
                            'status'  => 'error',
                            'message' => 'Replacement file is not a readable image.',
                    ];
                }

                require_once ABSPATH . 'wp-admin/includes/image.php';

                $old_meta       = wp_get_attachment_metadata( $attachment_id );
                $old_size_files = $this->collect_generated_size_files( $current_file, $old_meta );
                $tmp_target     = trailingslashit( dirname( $current_file ) ) . '.sp-replace-' . wp_generate_uuid4() . '.' . strtolower( (string) pathinfo( $current_file, PATHINFO_EXTENSION ) );

                if ( ! @copy( $tmp_file, $tmp_target ) || ! is_file( $tmp_target ) ) {
                    if ( is_file( $tmp_target ) ) {
                        @unlink( $tmp_target );
                    }

                    return [
                            'status'  => 'error',
                            'message' => 'Unable to prepare replacement file.',
                    ];
                }

                $perms = @fileperms( $current_file );
                if ( $perms ) {
                    @chmod( $tmp_target, $perms & 0777 );
                }

                if ( ! @rename( $tmp_target, $current_file ) ) {
                    @unlink( $tmp_target );
                    return [
                            'status'  => 'error',
                            'message' => 'Unable to overwrite current attachment file.',
                    ];
                }

                wp_update_post( [
                        'ID'             => $attachment_id,
                        'post_mime_type' => $replacement_mime,
                ] );

                $new_meta = wp_generate_attachment_metadata( $attachment_id, $current_file );
                if ( is_wp_error( $new_meta ) || ! is_array( $new_meta ) ) {
                    $new_meta = $this->fallback_attachment_metadata( $current_file, $relative, $replacement_mime );
                }

                if ( empty( $new_meta['file'] ) ) {
                    $new_meta['file'] = $relative;
                }

                wp_update_attachment_metadata( $attachment_id, $new_meta );

                $new_size_files = $this->collect_generated_size_files( $current_file, $new_meta );
                $this->delete_stale_files( $current_file, $old_size_files, $current_file, $new_size_files );

                clean_post_cache( $attachment_id );
                delete_transient( self::URL_MAP_TRANSIENT );
                delete_transient( self::UNUSED_SCHEMA_TRANSIENT );

                $preview_url = (string) wp_get_attachment_image_url( $attachment_id, 'thumbnail' );
                if ( $preview_url === '' ) {
                    $preview_url = (string) wp_get_attachment_url( $attachment_id );
                }

                return [
                        'status'      => 'replaced',
                        'message'     => 'Attachment file replaced. ID, title, slug, filename, URL, alt, caption, and metadata fields were kept.',
                        'preview_url' => add_query_arg( 'sp-replaced', time(), $preview_url ),
                ];
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

            private function encode_path_segments( string $path ): string {
                $path = str_replace( '\\', '/', $path );
                if ( $path === '' ) {
                    return '';
                }

                $leading  = str_starts_with( $path, '/' ) ? '/' : '';
                $trailing = str_ends_with( $path, '/' ) ? '/' : '';
                $trimmed  = trim( $path, '/' );
                if ( $trimmed === '' ) {
                    return $leading;
                }

                $parts = array_map( 'rawurlencode', explode( '/', $trimmed ) );

                return $leading . implode( '/', $parts ) . $trailing;
            }

            private function encode_url_path_segments( string $value ): string {
                $value = str_replace( '\\', '/', trim( $value ) );
                if ( $value === '' ) {
                    return '';
                }

                $parts = wp_parse_url( $value );
                if ( is_array( $parts ) && ! empty( $parts['host'] ) ) {
                    $prefix = ! empty( $parts['scheme'] ) ? ( (string) $parts['scheme'] . '://' ) : '//';
                    $auth   = '';
                    if ( ! empty( $parts['user'] ) ) {
                        $auth = (string) $parts['user'];
                        if ( isset( $parts['pass'] ) ) {
                            $auth .= ':' . (string) $parts['pass'];
                        }
                        $auth .= '@';
                    }

                    $encoded = $prefix . $auth . (string) $parts['host'];
                    if ( isset( $parts['port'] ) ) {
                        $encoded .= ':' . (int) $parts['port'];
                    }
                    $encoded .= $this->encode_path_segments( (string) ( $parts['path'] ?? '' ) );
                    if ( isset( $parts['query'] ) ) {
                        $encoded .= '?' . (string) $parts['query'];
                    }
                    if ( isset( $parts['fragment'] ) ) {
                        $encoded .= '#' . (string) $parts['fragment'];
                    }

                    return $encoded;
                }

                return $this->encode_path_segments( $value );
            }

            private function push_search_needle_variants( array &$needles, string $value ): void {
                $value = trim( str_replace( '\\', '/', $value ) );
                if ( $value === '' ) {
                    return;
                }

                $base_candidates = [ $value ];
                $without_query   = (string) preg_replace( '~[?#].*$~', '', $value );
                if ( $without_query !== '' && $without_query !== $value ) {
                    $base_candidates[] = $without_query;
                }

                $decoded = rawurldecode( $value );
                if ( $decoded !== $value ) {
                    $base_candidates[] = $decoded;
                }

                $encoded = $this->encode_url_path_segments( $value );
                if ( $encoded !== '' && $encoded !== $value ) {
                    $base_candidates[] = $encoded;
                }

                foreach ( array_values( array_unique( $base_candidates ) ) as $candidate ) {
                    $candidate = trim( (string) $candidate );
                    if ( $candidate === '' ) {
                        continue;
                    }

                    $needles[] = $candidate;

                    if ( str_contains( $candidate, '/' ) ) {
                        $needles[] = str_replace( '/', '\\/', $candidate );
                    }

                    if ( str_contains( $candidate, '&' ) ) {
                        $needles[] = str_replace( '&', '&amp;', $candidate );
                    }
                }
            }

            private function expand_search_needles( array $needles, bool $include_basename = false ): array {
                $expanded = [];

                foreach ( $needles as $needle ) {
                    $needle = trim( (string) $needle );
                    if ( $needle === '' ) {
                        continue;
                    }

                    $this->push_search_needle_variants( $expanded, $needle );

                    if ( ! $include_basename ) {
                        continue;
                    }

                    $path = (string) wp_parse_url( $needle, PHP_URL_PATH );
                    if ( $path === '' ) {
                        $path = (string) preg_replace( '~[?#].*$~', '', $needle );
                    }

                    $basename = basename( str_replace( '\\', '/', rawurldecode( $path ) ) );
                    if ( $basename !== '' && str_contains( $basename, '.' ) ) {
                        $this->push_search_needle_variants( $expanded, $basename );
                    }
                }

                return array_values(
                        array_filter(
                                array_unique( $expanded ),
                                static fn( string $needle ): bool => trim( $needle ) !== ''
                        )
                );
            }

            private function push_database_path_search_needle_variants( array &$needles, string $value ): void {
                $value = trim( str_replace( '\\', '/', $value ) );
                if ( $value === '' ) {
                    return;
                }

                $candidates    = [ $value ];
                $without_query = (string) preg_replace( '~[?#].*$~', '', $value );
                if ( $without_query !== '' && $without_query !== $value ) {
                    $candidates[] = $without_query;
                }

                $decoded = rawurldecode( $value );
                if ( $decoded !== '' && $decoded !== $value ) {
                    $candidates[] = $decoded;
                }

                if ( str_contains( $value, '/' ) ) {
                    $encoded = $this->encode_url_path_segments( $value );
                    if ( $encoded !== '' && $encoded !== $value ) {
                        $candidates[] = $encoded;
                    }
                }

                foreach ( array_values( array_unique( $candidates ) ) as $candidate ) {
                    $candidate = trim( (string) $candidate );
                    if ( $candidate === '' ) {
                        continue;
                    }

                    $needles[] = $candidate;

                    if ( str_contains( $candidate, '/' ) ) {
                        $needles[] = str_replace( '/', '\\/', $candidate );
                    }

                    if ( str_contains( $candidate, '&' ) ) {
                        $needles[] = str_replace( '&', '&amp;', $candidate );
                    }
                }
            }

            private function attachment_all_search_needles( int $attachment_id ): array {
                $needles = array_merge(
                        $this->attachment_id_search_needles( $attachment_id ),
                        $this->attachment_relative_search_needles( $attachment_id ),
                        $this->attachment_url_search_needles( $attachment_id )
                );

                $up           = wp_get_upload_dir();
                $uploads_path = trim( str_replace( '\\', '/', (string) wp_parse_url( (string) ( $up['baseurl'] ?? '' ), PHP_URL_PATH ) ), '/' );

                foreach ( $this->attachment_relative_search_needles( $attachment_id ) as $relative ) {
                    $relative = $this->normalize_relative_upload_path( (string) $relative );
                    if ( $relative === '' ) {
                        continue;
                    }

                    $needles[] = '/' . $relative;
                    $needles[] = 'uploads/' . $relative;

                    if ( $uploads_path !== '' ) {
                        $needles[] = $uploads_path . '/' . $relative;
                        $needles[] = '/' . $uploads_path . '/' . $relative;
                    }
                }

                foreach ( $this->attachment_url_search_needles( $attachment_id ) as $url ) {
                    $path = (string) wp_parse_url( (string) $url, PHP_URL_PATH );
                    if ( $path !== '' ) {
                        $needles[] = $path;
                        $needles[] = ltrim( $path, '/' );
                    }
                }

                return $this->expand_search_needles( $needles );
            }

            private function attachment_database_path_search_needles( int $attachment_id ): array {
                $needles      = [];
                $up           = wp_get_upload_dir();
                $uploads_path = trim( str_replace( '\\', '/', (string) wp_parse_url( (string) ( $up['baseurl'] ?? '' ), PHP_URL_PATH ) ), '/' );

                foreach ( $this->attachment_relative_search_needles( $attachment_id ) as $relative ) {
                    $relative = $this->normalize_relative_upload_path( (string) $relative );
                    if ( $relative === '' ) {
                        continue;
                    }

                    $this->push_database_path_search_needle_variants( $needles, $relative );
                    $this->push_database_path_search_needle_variants( $needles, '/' . $relative );
                    $this->push_database_path_search_needle_variants( $needles, 'uploads/' . $relative );

                    if ( $uploads_path !== '' ) {
                        $this->push_database_path_search_needle_variants( $needles, $uploads_path . '/' . $relative );
                        $this->push_database_path_search_needle_variants( $needles, '/' . $uploads_path . '/' . $relative );
                    }
                }

                foreach ( $this->attachment_url_search_needles( $attachment_id ) as $url ) {
                    $url = trim( (string) $url );
                    if ( $url === '' ) {
                        continue;
                    }

                    $this->push_database_path_search_needle_variants( $needles, $url );

                    $path = (string) wp_parse_url( $url, PHP_URL_PATH );
                    if ( $path !== '' ) {
                        $this->push_database_path_search_needle_variants( $needles, $path );
                        $this->push_database_path_search_needle_variants( $needles, ltrim( $path, '/' ) );
                    }
                }

                return array_values(
                        array_filter(
                                array_unique( $needles ),
                                static fn( string $needle ): bool => trim( $needle ) !== ''
                        )
                );
            }

            private function attachment_database_search_needles( int $attachment_id ): array {
                return array_values(
                        array_filter(
                                array_unique(
                                        array_merge(
                                                $this->attachment_id_search_needles( $attachment_id ),
                                                $this->attachment_database_path_search_needles( $attachment_id )
                                        )
                                ),
                                static fn( string $needle ): bool => trim( $needle ) !== ''
                        )
                );
            }

            private function attachment_postmeta_search_needles( int $attachment_id ): array {
                return array_values(
                        array_filter(
                                array_unique(
                                        array_merge(
                                                $this->attachment_basic_id_search_needles( $attachment_id ),
                                                $this->attachment_database_path_search_needles( $attachment_id )
                                        )
                                ),
                                static fn( string $needle ): bool => trim( $needle ) !== ''
                        )
                );
            }

            private function unused_scan_batch_size(): int {
                $cfg = $this->cfg();
                $raw = (int) ( $cfg['batch_size'] ?? 20 );

                return min( 4, max( 1, (int) ceil( $raw / 10 ) ) );
            }

            private function unused_delete_batch_size(): int {
                return 4;
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

            private function attachment_basic_id_search_needles( int $attachment_id ): array {
                $id = (string) $attachment_id;

                return [
                        'wp-image-' . $id,
                        'wp-att-' . $id,
                        'attachment_' . $id,
                        'attachment-' . $id,
                        'data-id="' . $id . '"',
                        "data-id='" . $id . "'",
                        'data-attachment-id="' . $id . '"',
                        "data-attachment-id='" . $id . "'",
                        'data-image-id="' . $id . '"',
                        "data-image-id='" . $id . "'",
                        '[gallery ids="' . $id,
                        'ids="' . $id . '"',
                        'ids="' . $id . ',',
                        "ids='" . $id . "'",
                        "ids='" . $id . ',',
                ];
            }

            private function attachment_id_search_needles( int $attachment_id ): array {
                $id     = (string) $attachment_id;
                $keys   = [
                        'attachment',
                        'attachmentID',
                        'attachmentId',
                        'attachment_id',
                        'background',
                        'bg',
                        'icon',
                        'image',
                        'imageID',
                        'imageId',
                        'image_id',
                        'media',
                        'mediaID',
                        'mediaId',
                        'media_id',
                        'poster',
                        'posterID',
                        'posterId',
                        'poster_id',
                        'thumbnail',
                        'thumbnail_id',
                ];

                $needles = $this->attachment_basic_id_search_needles( $attachment_id );

                foreach ( $keys as $key ) {
                    $needles[] = '"' . $key . '":' . $id;
                    $needles[] = '"' . $key . '": ' . $id;
                    $needles[] = '"' . $key . '":"' . $id . '"';
                    $needles[] = '"' . $key . '": "' . $id . '"';
                    $needles[] = "'" . $key . "':" . $id;
                    $needles[] = "'" . $key . "': " . $id;
                    $needles[] = "'" . $key . "':'" . $id . "'";
                    $needles[] = "'" . $key . "': '" . $id . "'";
                }

                return array_values( array_filter( array_unique( $needles ) ) );
            }

            private function meta_key_suggests_attachment_reference( string $key ): bool {
                $key = strtolower( trim( $key ) );
                if ( $key === '' ) {
                    return false;
                }

                if ( preg_match( '~^_?sp_builder_import_backup_~', $key ) ) {
                    return false;
                }

                return (bool) preg_match( '~(^|[_\W-])(attachment|avatar|background|bg|file|gallery|icon|image|img|logo|media|photo|picture|poster|svg|thumbnail|thumb|video)([_\W-]|$)~', $key );
            }

            private function acf_field_suggests_attachment_reference( string $field_key ): bool {
                $field_key = trim( $field_key );
                if ( $field_key === '' || ! str_starts_with( $field_key, 'field_' ) || ! function_exists( 'acf_get_field' ) ) {
                    return false;
                }

                $field = acf_get_field( $field_key );
                if ( ! is_array( $field ) ) {
                    return false;
                }

                $type = strtolower( (string) ( $field['type'] ?? '' ) );
                return in_array( $type, [ 'file', 'gallery', 'image' ], true );
            }

            private function post_meta_key_suggests_attachment_reference( int $post_id, string $meta_key ): bool {
                if ( $this->meta_key_suggests_attachment_reference( $meta_key ) ) {
                    return true;
                }

                $field_meta = get_post_meta( $post_id, '_' . ltrim( $meta_key, '_' ), true );
                $field_key  = is_scalar( $field_meta ) ? (string) $field_meta : '';
                return $this->acf_field_suggests_attachment_reference( $field_key );
            }

            private function value_contains_attachment_id_reference( $value, int $attachment_id, int $depth = 0 ): bool {
                if ( $depth > 6 ) {
                    return false;
                }

                if ( is_array( $value ) ) {
                    foreach ( $value as $item ) {
                        if ( $this->value_contains_attachment_id_reference( $item, $attachment_id, $depth + 1 ) ) {
                            return true;
                        }
                    }

                    return false;
                }

                if ( is_object( $value ) ) {
                    return $this->value_contains_attachment_id_reference( get_object_vars( $value ), $attachment_id, $depth + 1 );
                }

                if ( is_int( $value ) ) {
                    return $value === $attachment_id;
                }

                if ( is_float( $value ) ) {
                    return (string) (int) $value === (string) $attachment_id;
                }

                if ( is_bool( $value ) || $value === null ) {
                    return false;
                }

                $text = trim( (string) $value );
                if ( $text === '' ) {
                    return false;
                }

                if ( $text === (string) $attachment_id ) {
                    return true;
                }

                if ( function_exists( 'is_serialized' ) && is_serialized( $text ) ) {
                    $unserialized = maybe_unserialize( $text );
                    if ( $unserialized !== $text && $this->value_contains_attachment_id_reference( $unserialized, $attachment_id, $depth + 1 ) ) {
                        return true;
                    }
                }

                $first = $text[0] ?? '';
                if ( $first === '{' || $first === '[' ) {
                    $decoded = json_decode( $text, true );
                    if ( json_last_error() === JSON_ERROR_NONE && $this->value_contains_attachment_id_reference( $decoded, $attachment_id, $depth + 1 ) ) {
                        return true;
                    }
                }

                return (bool) preg_match( '~(?<![0-9])' . preg_quote( (string) $attachment_id, '~' ) . '(?![0-9])~', $text );
            }

            private function exact_attachment_id_usage( int $attachment_id ): array {
                global $wpdb;

                $id = (string) $attachment_id;

                $postmeta_rows = $wpdb->get_results(
                        $wpdb->prepare(
                                "SELECT post_id, meta_key FROM {$wpdb->postmeta} WHERE post_id <> %d AND meta_value = %s LIMIT 100",
                                $attachment_id,
                                $id
                        ),
                        ARRAY_A
                );

                if ( is_array( $postmeta_rows ) ) {
                    foreach ( $postmeta_rows as $row ) {
                        $post_id  = (int) ( $row['post_id'] ?? 0 );
                        $meta_key = (string) ( $row['meta_key'] ?? '' );
                        if ( $post_id > 0 && $this->post_meta_key_suggests_attachment_reference( $post_id, $meta_key ) ) {
                            return [
                                    'used'   => true,
                                    'reason' => 'Exact attachment ID match found in media postmeta key ' . $meta_key . '.',
                                    'source' => 'exact-id:postmeta',
                            ];
                        }
                    }
                }

                $postmeta_value_rows = $wpdb->get_results(
                        $wpdb->prepare(
                                "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta}
                                 WHERE post_id <> %d
                                   AND meta_value LIKE %s
                                 LIMIT 1000",
                                $attachment_id,
                                '%' . $wpdb->esc_like( $id ) . '%'
                        ),
                        ARRAY_A
                );

                if ( is_array( $postmeta_value_rows ) ) {
                    foreach ( $postmeta_value_rows as $row ) {
                        $post_id    = (int) ( $row['post_id'] ?? 0 );
                        $meta_key   = (string) ( $row['meta_key'] ?? '' );
                        $meta_value = (string) ( $row['meta_value'] ?? '' );
                        if (
                                $post_id > 0
                                && $this->post_meta_key_suggests_attachment_reference( $post_id, $meta_key )
                                && $this->value_contains_attachment_id_reference( $meta_value, $attachment_id )
                        ) {
                            return [
                                    'used'   => true,
                                    'reason' => 'Attachment ID found inside media postmeta key ' . $meta_key . '.',
                                    'source' => 'exact-id:postmeta',
                            ];
                        }
                    }
                }

                $option_rows = $wpdb->get_results(
                        $wpdb->prepare(
                                "SELECT option_name FROM {$wpdb->options}
                                 WHERE option_name NOT LIKE '_transient_%'
                                   AND option_name NOT LIKE '_site_transient_%'
                                   AND option_value = %s
                                 LIMIT 100",
                                $id
                        ),
                        ARRAY_A
                );

                if ( is_array( $option_rows ) ) {
                    foreach ( $option_rows as $row ) {
                        $option_name = (string) ( $row['option_name'] ?? '' );
                        if ( $this->meta_key_suggests_attachment_reference( $option_name ) ) {
                            return [
                                    'used'   => true,
                                    'reason' => 'Exact attachment ID match found in media option ' . $option_name . '.',
                                    'source' => 'exact-id:options',
                            ];
                        }
                    }
                }

                if ( ! empty( $wpdb->termmeta ) ) {
                    $termmeta_rows = $wpdb->get_results(
                            $wpdb->prepare(
                                    "SELECT term_id, meta_key FROM {$wpdb->termmeta} WHERE meta_value = %s LIMIT 100",
                                    $id
                            ),
                            ARRAY_A
                    );

                    if ( is_array( $termmeta_rows ) ) {
                        foreach ( $termmeta_rows as $row ) {
                            $term_id  = (int) ( $row['term_id'] ?? 0 );
                            $meta_key = (string) ( $row['meta_key'] ?? '' );
                            $field_meta = $term_id > 0 ? get_term_meta( $term_id, '_' . ltrim( $meta_key, '_' ), true ) : '';
                            $field_key = is_scalar( $field_meta ) ? (string) $field_meta : '';
                            if ( $this->meta_key_suggests_attachment_reference( $meta_key ) || $this->acf_field_suggests_attachment_reference( $field_key ) ) {
                                return [
                                        'used'   => true,
                                        'reason' => 'Exact attachment ID match found in media termmeta key ' . $meta_key . '.',
                                        'source' => 'exact-id:termmeta',
                                ];
                            }
                        }
                    }
                }

                if ( ! empty( $wpdb->usermeta ) ) {
                    $usermeta_rows = $wpdb->get_results(
                            $wpdb->prepare(
                                    "SELECT user_id, meta_key FROM {$wpdb->usermeta} WHERE meta_value = %s LIMIT 100",
                                    $id
                            ),
                            ARRAY_A
                    );

                    if ( is_array( $usermeta_rows ) ) {
                        foreach ( $usermeta_rows as $row ) {
                            $user_id  = (int) ( $row['user_id'] ?? 0 );
                            $meta_key = (string) ( $row['meta_key'] ?? '' );
                            $field_meta = $user_id > 0 ? get_user_meta( $user_id, '_' . ltrim( $meta_key, '_' ), true ) : '';
                            $field_key = is_scalar( $field_meta ) ? (string) $field_meta : '';
                            if ( $this->meta_key_suggests_attachment_reference( $meta_key ) || $this->acf_field_suggests_attachment_reference( $field_key ) ) {
                                return [
                                        'used'   => true,
                                        'reason' => 'Exact attachment ID match found in media usermeta key ' . $meta_key . '.',
                                        'source' => 'exact-id:usermeta',
                                ];
                            }
                        }
                    }
                }

                return [
                        'used'   => false,
                        'reason' => '',
                        'source' => 'none',
                ];
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
                return $this->attachment_database_search_needles( $attachment_id );
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
                        '~duplicator~',
                        '~(?:^|_)gf_form$~',
                        '~(?:^|_)gf_(?:draft|entry|form_revisions|form_view|form_view_meta|form_view_stats|entry_meta|entry_notes)(?:_|$)~',
                        '~gravitysmtp~',
                        '~mailpoet~',
                        '~rank_math_(?:analytics|internal_links|link_genius|redirections(?:_cache)?)~',
                        '~rankmath_analytics~',
                        '~aioseo_~',
                        '~redirection_404~',
                        '~trustindex~',
                        '~wpmailsmtp~',
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
                if (
                        is_array( $cached )
                        && isset( $cached['version'], $cached['schema'] )
                        && (int) $cached['version'] === self::UNUSED_SCHEMA_CACHE_VERSION
                        && is_array( $cached['schema'] )
                ) {
                    $this->unused_schema_error = '';
                    return $cached['schema'];
                }

                $this->unused_schema_error = '';

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
                    $error = trim( (string) $wpdb->last_error );
                    $this->unused_schema_error = $error !== '' ? $error : 'Unable to inspect database text columns.';
                    return [];
                }

                $core_tables = array_filter( [
                        $wpdb->posts,
                        $wpdb->postmeta,
                        $wpdb->options,
                        $wpdb->commentmeta ?? '',
                        $wpdb->links ?? '',
                        $wpdb->sitemeta ?? '',
                        $wpdb->terms ?? '',
                        $wpdb->term_taxonomy ?? '',
                        $wpdb->termmeta ?? '',
                        $wpdb->users ?? '',
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

                set_transient(
                        self::UNUSED_SCHEMA_TRANSIENT,
                        [
                                'version' => self::UNUSED_SCHEMA_CACHE_VERSION,
                                'schema'  => $schema,
                        ],
                        HOUR_IN_SECONDS
                );

                return $schema;
            }

            private function table_like_match_exists( string $table, array $columns, array $needles, string $where_sql = '1=1', array $where_params = [] ): bool {
                global $wpdb;

                $this->last_table_search_error = '';

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

                $needle_chunks = array_chunk( $needles, 16 );
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

                    $error = trim( (string) $wpdb->last_error );
                    if ( $error !== '' ) {
                        $this->last_table_search_error = 'Database search failed in ' . $table . ': ' . $error;
                        return false;
                    }
                }

                return false;
            }

            private function normalize_filesystem_path( string $path ): string {
                $real = realpath( $path );

                return wp_normalize_path( $real ?: $path );
            }

            private function unused_filesystem_search_roots(): array {
                $roots = [];
                $seen  = [];

                $add = function ( string $label, string $path ) use ( &$roots, &$seen ): void {
                    $path = trim( $path );
                    if ( $path === '' || ! is_dir( $path ) ) {
                        return;
                    }

                    $path = $this->normalize_filesystem_path( $path );
                    if ( $this->should_skip_unused_filesystem_dir( $path ) ) {
                        return;
                    }

                    if ( isset( $seen[ $path ] ) ) {
                        return;
                    }

                    $seen[ $path ] = true;
                    $roots[]       = [
                            'label' => $label,
                            'path'  => $path,
                    ];
                };

                if ( function_exists( 'get_stylesheet_directory' ) ) {
                    $add( 'child theme', (string) get_stylesheet_directory() );
                }

                if ( function_exists( 'get_template_directory' ) ) {
                    $add( 'parent theme', (string) get_template_directory() );
                }

                if ( defined( 'WPMU_PLUGIN_DIR' ) ) {
                    $add( 'mu-plugins', (string) WPMU_PLUGIN_DIR );
                }

                if ( defined( 'WP_PLUGIN_DIR' ) ) {
                    $plugin_dir     = trailingslashit( (string) WP_PLUGIN_DIR );
                    $active_plugins = (array) get_option( 'active_plugins', [] );

                    if ( is_multisite() ) {
                        $active_plugins = array_merge(
                                $active_plugins,
                                array_keys( (array) get_site_option( 'active_sitewide_plugins', [] ) )
                        );
                    }

                    foreach ( array_unique( array_filter( array_map( 'strval', $active_plugins ) ) ) as $plugin_file ) {
                        $plugin_path = $plugin_dir . ltrim( $plugin_file, '/\\' );
                        if ( is_file( $plugin_path ) ) {
                            $add( 'active plugin: ' . dirname( $plugin_file ), dirname( $plugin_path ) );
                        }
                    }
                }

                $up = wp_get_upload_dir();
                $add( 'uploads text files', (string) ( $up['basedir'] ?? '' ) );

                return $roots;
            }

            private function should_skip_unused_filesystem_dir( string $path ): bool {
                $path = wp_normalize_path( $path );
                $name = strtolower( basename( $path ) );
                if ( $name === '' ) {
                    return false;
                }

                $skip_names = [
                        '.git',
                        '.idea',
                        '.svn',
                        '.vscode',
                        'advanced-custom-fields-pro',
                        'ai1wm-backups',
                        'all-in-one-wp-migration',
                        'backup',
                        'backups',
                        'breeze',
                        'cache',
                        'caches',
                        'cloudways-site-manager',
                        'duplicator-pro',
                        'gravityforms',
                        'logs',
                        'node_modules',
                        'languages',
                        'object-cache-pro',
                        'seo-by-rank-math',
                        'seo-by-rank-math-pro',
                        'seraphinite-accelerator-ext',
                        'tmp',
                        'upgrade',
                        'uploads-webpc',
                        'vendor',
                        'wflogs',
                        'wp-mail-smtp-pro',
                        'wp-reviews-plugin-for-google',
                        'wpvividbackups',
                ];

                if ( in_array( $name, $skip_names, true ) ) {
                    return true;
                }

                $skip_fragments = [
                        '/wp-content/cache/',
                        '/wp-content/uploads/cache/',
                        '/wp-content/uploads/wc-logs/',
                        '/wp-content/uploads/wpforms/cache/',
                ];

                foreach ( $skip_fragments as $fragment ) {
                    if ( str_contains( $path . '/', $fragment ) ) {
                        return true;
                    }
                }

                return false;
            }

            private function is_searchable_unused_filesystem_file( string $path ): bool {
                $ext = strtolower( (string) pathinfo( $path, PATHINFO_EXTENSION ) );
                if ( $ext === '' ) {
                    return false;
                }

                $allowed = [
                        'cjs',
                        'css',
                        'csv',
                        'htm',
                        'html',
                        'js',
                        'json',
                        'jsx',
                        'less',
                        'md',
                        'mjs',
                        'php',
                        'phtml',
                        'sass',
                        'scss',
                        'svg',
                        'ts',
                        'tsx',
                        'txt',
                        'webmanifest',
                        'xml',
                        'yaml',
                        'yml',
                ];

                return in_array( $ext, $allowed, true );
            }

            private function unused_filesystem_search_files(): array {
                static $cache = null;

                if ( is_array( $cache ) ) {
                    return $cache;
                }

                $files  = [];
                $errors = [];

                foreach ( $this->unused_filesystem_search_roots() as $root ) {
                    $root_path  = (string) ( $root['path'] ?? '' );
                    $root_label = (string) ( $root['label'] ?? 'filesystem' );
                    if ( $root_path === '' ) {
                        continue;
                    }

                    if ( ! is_readable( $root_path ) ) {
                        $errors[] = 'Unreadable filesystem scan root: ' . $root_path;
                        continue;
                    }

                    try {
                        $directory = new RecursiveDirectoryIterator( $root_path, FilesystemIterator::SKIP_DOTS );
                        $filter    = new RecursiveCallbackFilterIterator(
                                $directory,
                                function ( SplFileInfo $current ) use ( &$errors ): bool {
                                    $path = $current->getPathname();

                                    if ( $current->isLink() ) {
                                        return false;
                                    }

                                    if ( $current->isDir() ) {
                                        if ( $this->should_skip_unused_filesystem_dir( $path ) ) {
                                            return false;
                                        }

                                        if ( ! is_readable( $path ) ) {
                                            $errors[] = 'Unreadable filesystem scan directory: ' . $path;
                                            return false;
                                        }
                                    }

                                    return true;
                                }
                        );
                        $iterator  = new RecursiveIteratorIterator( $filter );

                        foreach ( $iterator as $file_info ) {
                            if ( ! $file_info instanceof SplFileInfo || ! $file_info->isFile() || $file_info->isLink() ) {
                                continue;
                            }

                            $file_path = $this->normalize_filesystem_path( $file_info->getPathname() );
                            if ( ! $this->is_searchable_unused_filesystem_file( $file_path ) ) {
                                continue;
                            }

                            if ( ! is_readable( $file_path ) ) {
                                $errors[] = 'Unreadable filesystem scan file: ' . $file_path;
                                continue;
                            }

                            $files[] = [
                                    'label' => $root_label,
                                    'path'  => $file_path,
                            ];
                        }
                    } catch ( Throwable $e ) {
                        $errors[] = 'Filesystem scan failed in ' . $root_path . ': ' . $e->getMessage();
                    }
                }

                $cache = [
                        'files'  => $files,
                        'errors' => array_values( array_unique( $errors ) ),
                ];

                return $cache;
            }

            private function file_contains_any_needle( string $file, array $needles ): ?bool {
                $needles = array_values(
                        array_filter(
                                array_unique( array_map( 'strval', $needles ) ),
                                static fn( string $needle ): bool => $needle !== ''
                        )
                );

                if ( empty( $needles ) ) {
                    return false;
                }

                if ( ! is_readable( $file ) ) {
                    return null;
                }

                $handle = @fopen( $file, 'rb' );
                if ( ! $handle ) {
                    return null;
                }

                $max_needle_length = max( array_map( 'strlen', $needles ) );
                $carry_length      = max( 0, $max_needle_length - 1 );
                $carry             = '';

                while ( ! feof( $handle ) ) {
                    $chunk = fread( $handle, 1024 * 1024 );
                    if ( $chunk === false ) {
                        fclose( $handle );
                        return null;
                    }

                    $haystack = $carry . $chunk;
                    foreach ( $needles as $needle ) {
                        if ( strpos( $haystack, $needle ) !== false ) {
                            fclose( $handle );
                            return true;
                        }
                    }

                    $carry = $carry_length > 0 ? substr( $haystack, -$carry_length ) : '';
                }

                fclose( $handle );

                return false;
            }

            private function attachment_filesystem_excluded_paths( int $attachment_id ): array {
                $excluded = [];
                $file     = (string) get_attached_file( $attachment_id );

                if ( $file !== '' ) {
                    $excluded[] = $this->normalize_filesystem_path( $file );
                    $meta       = wp_get_attachment_metadata( $attachment_id );
                    foreach ( $this->collect_generated_size_files( $file, $meta ) as $size_file ) {
                        $excluded[] = $this->normalize_filesystem_path( (string) $size_file );
                    }
                }

                return array_values( array_unique( array_filter( $excluded ) ) );
            }

            private function find_attachment_filesystem_usage( int $attachment_id, array $needles ): array {
                $search = $this->unused_filesystem_search_files();
                $files  = isset( $search['files'] ) && is_array( $search['files'] ) ? $search['files'] : [];
                $errors = isset( $search['errors'] ) && is_array( $search['errors'] ) ? $search['errors'] : [];

                if ( ! empty( $errors ) ) {
                    return [
                            'used'          => true,
                            'reason'        => (string) reset( $errors ) . '. Kept conservatively.',
                            'source'        => 'filesystem-error',
                            'files_checked' => 0,
                    ];
                }

                if ( empty( $files ) ) {
                    return [
                            'used'          => true,
                            'reason'        => 'No searchable filesystem files were available. Kept conservatively.',
                            'source'        => 'filesystem-empty',
                            'files_checked' => 0,
                    ];
                }

                $excluded      = array_flip( $this->attachment_filesystem_excluded_paths( $attachment_id ) );
                $files_checked = 0;

                foreach ( $files as $file_item ) {
                    $file_path = (string) ( $file_item['path'] ?? '' );
                    if ( $file_path === '' || isset( $excluded[ $file_path ] ) ) {
                        continue;
                    }

                    $files_checked ++;
                    $contains = $this->file_contains_any_needle( $file_path, $needles );
                    if ( $contains === null ) {
                        return [
                                'used'          => true,
                                'reason'        => 'Unable to inspect filesystem file ' . $file_path . '. Kept conservatively.',
                                'source'        => 'filesystem-unreadable',
                                'files_checked' => $files_checked,
                        ];
                    }

                    if ( $contains ) {
                        return [
                                'used'          => true,
                                'reason'        => 'Reference found in filesystem file ' . $file_path . '.',
                                'source'        => 'filesystem:' . $file_path,
                                'files_checked' => $files_checked,
                        ];
                    }
                }

                return [
                        'used'          => false,
                        'reason'        => '',
                        'source'        => 'filesystem',
                        'files_checked' => $files_checked,
                ];
            }

            private function find_attachment_usage( int $attachment_id ): array {
                global $wpdb;

                $attached_file = (string) get_attached_file( $attachment_id );
                $relative      = (string) get_post_meta( $attachment_id, '_wp_attached_file', true );

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

                $exact_usage = $this->exact_attachment_id_usage( $attachment_id );
                if ( ! empty( $exact_usage['used'] ) ) {
                    return $exact_usage;
                }

                $core_needles       = $this->attachment_database_search_needles( $attachment_id );
                $custom_needles     = $this->attachment_custom_table_search_needles( $attachment_id );
                $filesystem_needles = $this->attachment_all_search_needles( $attachment_id );

                if ( empty( $core_needles ) || empty( $custom_needles ) || empty( $filesystem_needles ) ) {
                    return [
                            'used'   => true,
                            'reason' => 'Reliable search needles could not be built. Kept conservatively.',
                            'source' => 'empty-needles',
                    ];
                }

                $core_targets = [
                        [
                                'table'   => $wpdb->posts,
                                'columns' => [ 'post_title', 'post_content', 'post_excerpt', 'post_name', 'guid' ],
                                'where'   => 'ID <> %d',
                                'params'  => [ $attachment_id ],
                                'reason'  => 'Reference found in posts table.',
                        ],
                        [
                                'table'   => $wpdb->postmeta,
                                'columns' => [ 'meta_value' ],
                                'needles' => $this->attachment_postmeta_search_needles( $attachment_id ),
                                'where'   => "post_id <> %d AND meta_key NOT LIKE '\\_sp\\_builder\\_import\\_backup\\_%'",
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

                if ( ! empty( $wpdb->term_taxonomy ) ) {
                    $core_targets[] = [
                            'table'   => $wpdb->term_taxonomy,
                            'columns' => [ 'description' ],
                            'where'   => '1=1',
                            'params'  => [],
                            'reason'  => 'Reference found in term taxonomy description.',
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

                if ( ! empty( $wpdb->users ) ) {
                    $core_targets[] = [
                            'table'   => $wpdb->users,
                            'columns' => [ 'user_url', 'display_name' ],
                            'where'   => '1=1',
                            'params'  => [],
                            'reason'  => 'Reference found in users table.',
                    ];
                }

                if ( ! empty( $wpdb->comments ) ) {
                    $core_targets[] = [
                            'table'   => $wpdb->comments,
                            'columns' => [ 'comment_author', 'comment_author_url', 'comment_content' ],
                            'where'   => '1=1',
                            'params'  => [],
                            'reason'  => 'Reference found in comments.',
                    ];
                }

                if ( ! empty( $wpdb->commentmeta ) ) {
                    $core_targets[] = [
                            'table'   => $wpdb->commentmeta,
                            'columns' => [ 'meta_value' ],
                            'where'   => '1=1',
                            'params'  => [],
                            'reason'  => 'Reference found in commentmeta.',
                    ];
                }

                if ( ! empty( $wpdb->links ) ) {
                    $core_targets[] = [
                            'table'   => $wpdb->links,
                            'columns' => [ 'link_url', 'link_image', 'link_name', 'link_description', 'link_notes', 'link_rss' ],
                            'where'   => '1=1',
                            'params'  => [],
                            'reason'  => 'Reference found in links table.',
                    ];
                }

                if ( ! empty( $wpdb->sitemeta ) ) {
                    $core_targets[] = [
                            'table'   => $wpdb->sitemeta,
                            'columns' => [ 'meta_value' ],
                            'where'   => '1=1',
                            'params'  => [],
                            'reason'  => 'Reference found in sitemeta.',
                    ];
                }

                foreach ( $core_targets as $target ) {
                    $matched = $this->table_like_match_exists(
                            (string) $target['table'],
                            (array) $target['columns'],
                            (array) ( $target['needles'] ?? $core_needles ),
                            (string) $target['where'],
                            (array) $target['params']
                    );

                    if ( $this->last_table_search_error !== '' ) {
                        return [
                                'used'   => true,
                                'reason' => $this->last_table_search_error . '. Kept conservatively.',
                                'source' => 'database-error',
                        ];
                    }

                    if ( $matched ) {
                        return [
                                'used'   => true,
                                'reason' => (string) $target['reason'],
                                'source' => (string) $target['table'],
                        ];
                    }
                }

                $custom_schema = $this->custom_searchable_columns();
                if ( $this->unused_schema_error !== '' ) {
                    return [
                            'used'   => true,
                            'reason' => 'Custom table schema scan failed: ' . $this->unused_schema_error . '. Kept conservatively.',
                            'source' => 'schema-error',
                    ];
                }

                foreach ( $custom_schema as $table => $columns ) {
                    $matched = $this->table_like_match_exists( (string) $table, (array) $columns, $custom_needles );

                    if ( $this->last_table_search_error !== '' ) {
                        return [
                                'used'   => true,
                                'reason' => $this->last_table_search_error . '. Kept conservatively.',
                                'source' => 'database-error',
                        ];
                    }

                    if ( $matched ) {
                        return [
                                'used'   => true,
                                'reason' => 'Reference found in custom database table ' . $table . '.',
                                'source' => (string) $table,
                        ];
                    }
                }

                $filesystem_usage = $this->find_attachment_filesystem_usage( $attachment_id, $filesystem_needles );
                if ( ! empty( $filesystem_usage['used'] ) ) {
                    return $filesystem_usage;
                }

                return [
                        'used'   => false,
                        'reason' => sprintf(
                                'No attachment ID, upload-path, URL, encoded URL, or filesystem references found. Core DB tables, %d custom tables, and %d static files checked.',
                                count( $custom_schema ),
                                (int) ( $filesystem_usage['files_checked'] ?? 0 )
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

            private function ajax_replace_guard(): void {
                check_ajax_referer( self::NONCE_ACTION, 'nonce' );

                if ( ! current_user_can( 'upload_files' ) ) {
                    wp_send_json_error( [ 'message' => 'Forbidden' ], 403 );
                }
            }

            public function ajax_replace_attachment_file(): void {
                $this->ajax_replace_guard();

                $attachment_id = isset( $_POST['attachment_id'] ) ? (int) $_POST['attachment_id'] : 0;
                if ( $attachment_id <= 0 ) {
                    wp_send_json_error( [ 'message' => 'Attachment ID is missing.' ], 400 );
                }

                if ( empty( $_FILES['replacement'] ) || ! is_array( $_FILES['replacement'] ) ) {
                    wp_send_json_error( [ 'message' => 'Replacement file is missing.' ], 400 );
                }

                $result = $this->replace_attachment_file_in_place( $attachment_id, $_FILES['replacement'] );
                if ( (string) ( $result['status'] ?? '' ) !== 'replaced' ) {
                    wp_send_json_error( [ 'message' => (string) ( $result['message'] ?? 'Replace failed.' ) ], 400 );
                }

                wp_send_json_success( [
                        'message'     => (string) ( $result['message'] ?? 'Attachment file replaced.' ),
                        'preview_url' => (string) ( $result['preview_url'] ?? '' ),
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
                        'search_strategy' => 'exact-id, URL/path variants, core/custom text columns, static filesystem files',
                ] );
            }

            public function ajax_scan_unused_batch(): void {
                $this->ajax_guard();

                $last_id = isset( $_POST['last_id'] ) ? (int) $_POST['last_id'] : 0;
                $limit   = isset( $_POST['limit'] ) ? (int) $_POST['limit'] : $this->unused_scan_batch_size();
                $limit   = min( 20, max( 1, $limit ) );

                @set_time_limit( 45 );

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

                @set_time_limit( 45 );

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

            private function replace_button_html( int $attachment_id, string $label = 'Replace file' ): string {
                return sprintf(
                        '<button type="button" class="button sp-webp-replace-trigger" data-attachment-id="%d">%s</button>',
                        $attachment_id,
                        esc_html( $label )
                );
            }

            public function media_row_replace_action( array $actions, WP_Post $post ): array {
                if ( ! $this->is_replaceable_attachment_post( $post ) || ! $this->current_user_can_replace_attachment( (int) $post->ID ) ) {
                    return $actions;
                }

                $actions['sp_webp_replace_file'] = sprintf(
                        '<a href="#" class="sp-webp-replace-trigger" data-attachment-id="%d">%s</a>',
                        (int) $post->ID,
                        esc_html__( 'Replace file', 'sp-webp-convert' )
                );

                return $actions;
            }

            public function attachment_replace_field( array $form_fields, WP_Post $post ): array {
                if ( ! $this->is_replaceable_attachment_post( $post ) || ! $this->current_user_can_replace_attachment( (int) $post->ID ) ) {
                    return $form_fields;
                }

                $form_fields['sp_webp_replace_file'] = [
                        'label' => esc_html__( 'Replace file', 'sp-webp-convert' ),
                        'input' => 'html',
                        'html'  => $this->replace_button_html( (int) $post->ID ) . '<p class="description">Uploads a new file into the existing attachment path. ID, title, slug, alt, caption, filename, and URL stay unchanged. Use the same image format.</p>',
                ];

                return $form_fields;
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

            private function should_load_replace_assets( string $hook ): bool {
                if ( $hook === 'upload.php' ) {
                    return true;
                }

                if ( $hook !== 'post.php' ) {
                    return false;
                }

                $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
                return $screen && (string) ( $screen->post_type ?? '' ) === 'attachment';
            }

            private function enqueue_replace_assets(): void {
                wp_register_style( 'sp-webp-replace-admin', false, [], self::VERSION );
                wp_enqueue_style( 'sp-webp-replace-admin' );
                wp_add_inline_style(
                        'sp-webp-replace-admin',
                        '.sp-webp-replace-trigger{min-height:36px;border-color:var(--sp-admin-border-strong,#d6dbe1);border-radius:var(--sp-admin-radius-sm,9px);background:var(--sp-admin-surface,#fff);color:var(--sp-admin-text,#1a1f24);font-weight:600}.sp-webp-replace-trigger:hover,.sp-webp-replace-trigger:focus{border-color:var(--sp-admin-accent,#3858e9);background:var(--sp-admin-accent-soft,#edf0ff);color:var(--sp-admin-accent-hover,#2145e6)}.sp-webp-replace-trigger:focus{box-shadow:var(--sp-admin-focus,0 0 0 3px rgba(56,88,233,.18))}.sp-webp-replace-trigger.is-busy{opacity:.65;pointer-events:none}.sp-webp-replace-status{display:inline-block;margin-left:8px;color:var(--sp-admin-muted,#525b66)}.compat-field-sp_webp_replace_file .field{display:flex;align-items:center;gap:8px;flex-wrap:wrap}'
                );

                wp_register_script( 'sp-webp-replace-admin', false, [ 'jquery' ], self::VERSION, true );
                wp_enqueue_script( 'sp-webp-replace-admin' );

                $data = [
                        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                        'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
                        'confirm' => 'Replace this attachment file? The attachment ID, title, slug, filename, URL, alt, and caption will stay unchanged. The new file must use the same image format.',
                ];

                wp_add_inline_script(
                        'sp-webp-replace-admin',
                        'window.spWebpReplace=' . wp_json_encode( $data ) . ';' . $this->replace_admin_script()
                );
            }

            private function replace_admin_script(): string {
                return <<<'JS'
(function ($) {
    function setBusy($button, busy) {
        const original = $button.data('spOriginalText') || $button.text();
        if (!$button.data('spOriginalText')) {
            $button.data('spOriginalText', original);
        }
        $button.toggleClass('is-busy', busy).prop('disabled', busy);
        $button.text(busy ? 'Replacing...' : original);
    }

    function showMessage(message) {
        window.alert(message);
    }

    function replaceAttachment($button, file) {
        const cfg = window.spWebpReplace || {};
        const attachmentId = Number($button.data('attachmentId') || 0);
        if (!attachmentId || !file) {
            return;
        }

        const form = new FormData();
        form.append('action', 'sp_webp_replace_attachment_file');
        form.append('nonce', cfg.nonce || '');
        form.append('attachment_id', String(attachmentId));
        form.append('replacement', file);

        setBusy($button, true);

        $.ajax({
            url: cfg.ajaxUrl || window.ajaxurl,
            method: 'POST',
            data: form,
            contentType: false,
            processData: false
        }).done(function (response) {
            if (!response || !response.success) {
                const message = response && response.data && response.data.message ? response.data.message : 'Replace failed.';
                showMessage(message);
                return;
            }

            showMessage(response.data && response.data.message ? response.data.message : 'Attachment file replaced.');
            window.location.reload();
        }).fail(function () {
            showMessage('Replace failed.');
        }).always(function () {
            setBusy($button, false);
        });
    }

    $(document).on('click', '.sp-webp-replace-trigger', function (event) {
        event.preventDefault();

        const $button = $(this);
        if ($button.hasClass('is-busy')) {
            return;
        }

        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/*,.svg,.webp';
        input.style.position = 'fixed';
        input.style.left = '-9999px';
        document.body.appendChild(input);

        input.addEventListener('change', function () {
            const file = input.files && input.files[0] ? input.files[0] : null;
            document.body.removeChild(input);
            if (!file) {
                return;
            }

            const cfg = window.spWebpReplace || {};
            if (!window.confirm(cfg.confirm || 'Replace this attachment file?')) {
                return;
            }

            replaceAttachment($button, file);
        });

        input.click();
    });
})(jQuery);
JS;
            }

            public function admin_assets( string $hook ): void {
                $is_settings = $hook === 'settings_page_' . self::PAGE_SLUG;
                $is_replace  = $this->should_load_replace_assets( $hook );

                if ( ! $is_settings && ! $is_replace ) {
                    return;
                }

                wp_enqueue_script( 'jquery' );

                if ( $is_replace ) {
                    $this->enqueue_replace_assets();
                }
            }

            public function page(): void {
                $cfg   = $this->cfg();
                $nonce = wp_create_nonce( self::NONCE_ACTION );
                ?>
                <div class="sp-webp-admin sp-admin-page">
                    <header class="sp-webp-admin__header sp-admin-header">
                        <div class="sp-webp-admin__logo sp-admin-header__identity">
							<span class="sp-webp-admin__logo-icon sp-admin-header__icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" fill="none" viewBox="0 0 24 24">
                                  <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m14.3 16-1.7-1.7q-1-1.1-1.7-1.4H9.7q-.6.2-1.7 1.4l-4 4m10.3-2.4.3-.3q1-1.2 1.7-1.3h1.2q.6.1 1.7 1.4l.8.8m-5.7-.6 4 4m0 0-1.5.1H7.2q-1.6 0-2.1-.2a1.8 1.8 0 0 1-1-1.5M18.2 20a2 2 0 0 0 1.5-1q.3-.6.2-2.2v-.3M12.5 4H7.2q-1.6 0-2.1.2a2 2 0 0 0-.9.9Q4 5.6 4 7.2v11.1m16-6.8v5M14 10l2-.4h.4l.2-.2.2-.2L21 5a1.4 1.4 0 0 0-2-2l-4.2 4.2-.2.2-.1.2-.1.4z"/>
                                </svg>
                            </span>
							<div class="sp-admin-header__copy">
								<h1>Uploads WebP Convert</h1>
								<p>Convert Media Library images to WebP and safely replace stored URLs.</p>
							</div>
                        </div>
                        <div class="sp-webp-admin__actions sp-admin-header__actions">
                            <button type="button" class="sp-webp-btn sp-webp-btn--ghost" id="sp-webp-scan">Scan media</button>
                            <button type="button" class="sp-webp-btn sp-webp-btn--primary" id="sp-webp-start">Start bulk convert</button>
                            <button type="button" class="sp-webp-btn" id="sp-webp-stop" disabled>Stop</button>
                            <button type="button" class="sp-webp-btn" id="sp-webp-reset-progress">Reset saved progress</button>
                            <button type="button" class="sp-webp-btn sp-webp-btn--primary" id="sp-webp-save">Save settings</button>
                            <span class="sp-webp-saved" id="sp-webp-saved">Saved</span>
                        </div>
                    </header>

                    <div class="sp-webp-admin__body">
                        <aside class="sp-webp-sidebar">
                            <div class="sp-webp-panel sp-admin-card">
                                <div class="sp-admin-card__header"><h2>Conversion settings</h2></div>

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

                            <div class="sp-webp-panel sp-webp-panel--usage sp-admin-card">
                                <div class="sp-admin-card__header"><h2>Safety notes</h2></div>
                                <p class="sp-webp-hint">Run bulk conversion on staging first when possible.</p>
                                <p class="sp-webp-hint">If old image URLs are hardcoded, convert in small batches and verify pages.</p>
                                <p class="sp-webp-hint">For max disk savings keep "Delete original source file" enabled.</p>
                            </div>
                        </aside>

                        <main class="sp-webp-main">
                            <div class="sp-webp-panel">
                                <div class="sp-admin-card__header"><h2>Bulk conversion status</h2></div>
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
                                <div class="sp-admin-card__header">
									<h2>Database URL replacement (ACF + Editor)</h2>
									<div class="sp-admin-card__actions">
										<button type="button" class="sp-webp-btn sp-webp-btn--primary" id="sp-webp-replace-all">Replace all URLs to WebP</button>
									</div>
								</div>
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

                                <div class="sp-webp-log" id="sp-webp-replace-log" style="height:220px;"></div>
                            </div>

                            <div class="sp-webp-panel" style="margin-top:16px;">
                                <div class="sp-admin-card__header">
									<h2>Unused Images Finder</h2>
									<div class="sp-webp-unused-actions sp-admin-card__actions">
										<button type="button" class="sp-webp-btn sp-webp-btn--primary" id="sp-webp-unused-scan">Scan unused images</button>
										<button type="button" class="sp-webp-btn sp-webp-btn--danger" id="sp-webp-unused-delete-selected" disabled>Delete selected</button>
										<button type="button" class="sp-webp-btn sp-webp-btn--danger" id="sp-webp-unused-delete-all" disabled>Delete all found</button>
										<button type="button" class="sp-webp-btn" id="sp-webp-unused-clear">Clear results</button>
									</div>
								</div>
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

                                <p class="sp-webp-hint">The scan is strict: it keeps parented, pathless, missing, uncertain, or referenced attachments; checks ID/path/URL/encoded variants across core and custom DB text columns; scans static theme/plugin/uploads text files; and repeats the usage check immediately before delete.</p>

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
                        padding-left: 20px;
                    }

                    .sp-webp-admin {
                        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                        font-size: 13px;
                        color: var(--sp-admin-text);
                        background: var(--sp-admin-canvas);
                        min-height: 100vh;
                    }

                    .sp-webp-admin__header {
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
                        box-shadow: var(--sp-admin-shadow);
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
                        background: var(--sp-admin-surface);
                        border-right: 1px solid var(--sp-admin-border);
                        padding: 20px;
                        display: flex;
                        flex-direction: column;
                        gap: 16px;
                    }

                    .sp-webp-main {
                        padding: 20px;
                    }

                    .sp-webp-panel {
                        background: var(--sp-admin-surface);
                        border: 1px solid var(--sp-admin-border);
                        border-radius: var(--sp-admin-radius);
                        padding: 18px 20px;
                    }

                    .sp-webp-panel--usage {
                        background: var(--sp-admin-surface-subtle);
                    }

                    .sp-webp-panel__title {
                        font-size: 12px;
                        font-weight: 700;
                        text-transform: uppercase;
                        letter-spacing: .6px;
                        color: var(--sp-admin-muted);
                        margin-bottom: 14px;
                        padding-bottom: 10px;
                        border-bottom: 1px solid var(--sp-admin-border);
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
                        color: var(--sp-admin-text);
                        margin-bottom: 5px;
                    }

                    .sp-webp-input {
                        width: 100%;
                        padding: 7px 10px;
                        border: 1px solid var(--sp-admin-border-strong);
                        border-radius: var(--sp-admin-radius-sm);
                        background: var(--sp-admin-input-bg);
                        font-size: 13px;
                    }

                    .sp-webp-input:focus {
                        outline: none;
                        border-color: var(--sp-admin-accent);
                        box-shadow: var(--sp-admin-focus);
                    }

                    .sp-webp-hint {
                        font-size: 11px;
                        color: var(--sp-admin-muted);
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
                        border: 1px solid var(--sp-admin-border-strong);
                        background: var(--sp-admin-border-strong);
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
                        background: var(--sp-admin-surface);
                        border-radius: 50%;
                        transition: left .2s;
                        box-shadow: 0 1px 3px rgba(0, 0, 0, .25);
                    }

                    .sp-webp-ios-toggle input:checked ~ .sp-webp-ios-track {
                        border-color: var(--sp-admin-accent);
                        background: var(--sp-admin-accent);
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
                        color: var(--sp-admin-accent);
                    }

                    .sp-webp-range {
                        width: 100%;
                        height: 4px;
                        appearance: none;
                        background: var(--sp-admin-border);
                        border-radius: 2px;
                        outline: none;
                    }

                    .sp-webp-range::-webkit-slider-thumb {
                        appearance: none;
                        width: 16px;
                        height: 16px;
                        border-radius: 50%;
                        background: var(--sp-admin-accent);
                        cursor: pointer;
                    }

                    .sp-webp-range::-moz-range-thumb {
                        width: 16px;
                        height: 16px;
                        border: none;
                        border-radius: 50%;
                        background: var(--sp-admin-accent);
                        cursor: pointer;
                    }

                    .sp-webp-btn {
                        display: inline-flex;
                        align-items: center;
                        padding: 7px 12px;
                        border-radius: var(--sp-admin-radius-sm);
                        border: 1px solid var(--sp-admin-border-strong);
                        background: var(--sp-admin-surface);
                        color: var(--sp-admin-text);
                        cursor: pointer;
                        font-size: 13px;
                    }

                    .sp-webp-btn--primary {
                        background: var(--sp-admin-accent);
                        border-color: var(--sp-admin-accent);
                        color: var(--color-on-accent);
                    }

                    .sp-webp-btn--ghost {
                        background: transparent;
                        border-color: var(--sp-admin-accent);
                        color: var(--sp-admin-accent);
                    }

                    .sp-webp-btn:disabled {
                        opacity: .45;
                        cursor: not-allowed;
                    }

                    .sp-webp-saved {
                        font-size: 12px;
                        font-weight: 600;
                        color: var(--sp-admin-success);
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
                        border: 1px solid var(--sp-admin-border);
                        border-radius: var(--sp-admin-radius-sm);
                        background: var(--sp-admin-surface);
                    }

                    .sp-webp-stat__label {
                        font-size: 11px;
                        color: var(--sp-admin-muted);
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
                        background: var(--sp-admin-border);
                        border-radius: 999px;
                        overflow: hidden;
                    }

                    .sp-webp-progress span {
                        display: block;
                        height: 100%;
                        width: 0;
                        background: linear-gradient(90deg, var(--sp-admin-accent) 0%, var(--sp-admin-accent-bright) 100%);
                        transition: width .2s;
                    }

                    .sp-webp-progress-meta {
                        display: flex;
                        justify-content: space-between;
                        font-size: 12px;
                        color: var(--sp-admin-muted);
                        margin-top: 8px;
                    }

                    .sp-webp-log {
                        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
                        font-size: 12px;
                        line-height: 1.4;
                        background: #1a1f24;
                        color: #e7eaee;
                        border-radius: var(--sp-admin-radius-sm);
                        padding: 12px;
                        height: 320px;
                        overflow: auto;
                        white-space: pre-wrap;
                    }

                    .sp-webp-btn--danger {
                        background: var(--sp-admin-danger);
                        border-color: var(--sp-admin-danger);
                        color: #fff;
                    }

                    .sp-webp-unused-actions {
                        display: flex;
                        gap: 8px;
                        flex-wrap: wrap;
                        margin-bottom: 12px;
                    }

                    .sp-webp-unused-table-wrap {
                        border: 1px solid var(--sp-admin-border);
                        border-radius: var(--sp-admin-radius-sm);
                        overflow: hidden;
                        background: var(--sp-admin-surface);
                    }

                    .sp-webp-unused-table {
                        width: 100%;
                        border-collapse: collapse;
                    }

                    .sp-webp-unused-table th,
                    .sp-webp-unused-table td {
                        padding: 10px 12px;
                        border-bottom: 1px solid var(--sp-admin-border);
                        text-align: left;
                        vertical-align: top;
                    }

                    .sp-webp-unused-table thead th {
                        font-size: 11px;
                        text-transform: uppercase;
                        letter-spacing: .4px;
                        color: var(--sp-admin-muted);
                        background: var(--sp-admin-surface-subtle);
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
                        border: 1px solid var(--sp-admin-border);
                        background: var(--sp-admin-surface-subtle);
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
                        color: var(--sp-admin-muted);
                        line-height: 1.45;
                        max-width: 400px;
                        word-wrap: break-word;
                    }

                    .sp-webp-unused-meta a {
                        color: var(--sp-admin-accent);
                        text-decoration: none;
                    }

                    .sp-webp-unused-status {
                        display: inline-flex;
                        align-items: center;
                        padding: 4px 8px;
                        border-radius: 999px;
                        font-size: 11px;
                        font-weight: 700;
                        border: 1px solid #a9dfbf;
                        background: #eefaf3;
                        color: #176b3a;
                    }

                    .sp-webp-unused-status--deleted {
                        border-color: var(--sp-admin-border);
                        background: var(--sp-admin-surface-subtle);
                        color: var(--sp-admin-muted);
                    }

                    .sp-webp-unused-status--skipped {
                        border-color: #f6c990;
                        background: #fff8ef;
                        color: #8a4c09;
                    }

                    .sp-webp-unused-row--deleted {
                        opacity: .55;
                    }

                    .sp-webp-unused-empty td {
                        font-size: 13px;
                        color: var(--sp-admin-muted);
                        text-align: center;
                        padding: 18px 12px;
                    }

                    @media (max-width: 1200px) {
                        .sp-webp-admin__body {
                            grid-template-columns: 1fr;
                        }

                        .sp-webp-sidebar {
                            border-right: none;
                            border-bottom: 1px solid var(--sp-admin-border);
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

        SP_Uploads_WebP_Convert::get();
    }
