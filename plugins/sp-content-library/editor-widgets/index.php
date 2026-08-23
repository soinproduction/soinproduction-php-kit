<?php

    if (! defined('ABSPATH')) {
        exit;
    }

    if ( ! class_exists( 'SP_Widgets_Plugin', false ) ) {
    final class SP_Widgets_Plugin
    {
        private const VERSION = '1.2.0';
        private const BUTTON  = 'sp_widgets';

        public static function init(): void
        {
            add_action('init', [__CLASS__, 'serve_tinymce_script'], 0);
        }

        public static function get_plugin_url(): string
        {
            $file = __DIR__ . '/script.js';
            $ver  = file_exists($file) ? (string) filemtime($file) : self::VERSION;

            return add_query_arg(
                    [
                            'sp_widgets_js' => '1',
                            'ver'           => $ver,
                    ],
                    home_url('/')
            );
        }

        public static function register_tinymce_plugin(array $plugins): array
        {
            $plugins[self::BUTTON] = self::get_plugin_url();
            return $plugins;
        }

        public static function serve_tinymce_script(): void
        {
            if (! isset($_GET['sp_widgets_js']) || '1' !== (string) $_GET['sp_widgets_js']) {
                return;
            }

            header('Content-Type: application/javascript; charset=utf-8');
            header('Cache-Control: public, max-age=31536000');
            echo self::tinymce_script();
            exit;
        }

        private static function tinymce_script(): string
        {
            $file_path = __DIR__ . '/script.js';
            if (file_exists($file_path)) {
                return file_get_contents($file_path);
            }
            return '';
        }
    }

    SP_Widgets_Plugin::init();
    }

    if ( ! class_exists( 'SP_Editor_Widgets', false ) ) {
    final class SP_Editor_Widgets
    {
        public static function init(): void
        {

            add_shortcode( 'widget', [ self::class, 'render_shortcode' ] );

            add_action( 'wp_ajax_sp_get_widgets_list', [ self::class, 'get_widgets_list' ] );
            add_action( 'wp_ajax_sp_create_new_widget', [ self::class, 'create_new_widget' ] );
            add_action( 'wp_ajax_sp_duplicate_widget', [ self::class, 'duplicate_widget' ] );
            add_action( 'wp_ajax_sp_render_widget_preview', [ self::class, 'render_widget_preview' ] );
            add_action( 'wp_ajax_sp_render_widget_previews', [ self::class, 'render_widget_previews' ] );

            add_action( 'admin_enqueue_scripts', [ self::class, 'print_ajax_nonce' ], 5 );

            add_action( 'admin_post_sp_edit_widget_iframe', [ self::class, 'edit_widget_iframe' ] );

            add_action( 'init', function() {
                $action = isset( $_GET['action'] ) ? $_GET['action'] : ( isset( $_POST['action'] ) ? $_POST['action'] : '' );
                if ( $action === 'sp_edit_widget_iframe' ) {
                    show_admin_bar( false );
                    add_filter( 'show_admin_bar', '__return_false', 9999 );

                    remove_action( 'wp_enqueue_scripts', 'theme_enqueue_styles' );
                    remove_action( 'wp_enqueue_scripts', 'theme_enqueue_scripts' );
                }
            }, 1 );
        }

        public static function render_shortcode( $atts )
        {
            $a = shortcode_atts( [
                    'id'    => 0,
                    'align' => '',
            ], $atts );

            $widget_id = intval( $a['id'] );
            if ( ! $widget_id ) {
                return '';
            }

            $widget_post = get_post( $widget_id );
            if ( ! $widget_post || $widget_post->post_type !== 'for-editor' ) {
                return '';
            }
			if (
				$widget_post->post_status !== 'publish'
				&& ! current_user_can( 'read_post', $widget_id )
				&& ! current_user_can( 'edit_post', $widget_id )
			) {
				return '';
			}

            $blocks = function_exists( 'for_editor_get_blocks' ) ? for_editor_get_blocks( $widget_id ) : get_field( 'blocks', $widget_id );
            if ( empty( $blocks ) || ! is_array( $blocks ) || ! function_exists( 'display_blocks' ) ) {
                return '';
            }

            global $post;
            $previous_post = $post;
            $post = get_post( $widget_id );
            setup_postdata( $post );

            ob_start();
            display_blocks( $blocks, false, false );
            $html = (string) ob_get_clean();

            wp_reset_postdata();
            $post = $previous_post;
            if ( $post instanceof WP_Post ) {
                setup_postdata( $post );
            }

            $align = strtolower( trim( (string) $a['align'] ) );
            $align = in_array( $align, [ 'left', 'center', 'right' ], true ) ? $align : '';

            if ( $align ) {
                return '<div class="sp-editor-widget-align sp-editor-widget-align--' . esc_attr( $align ) . '" style="text-align:' . esc_attr( $align ) . '">' . $html . '</div>';
            }

            return $html;
        }

        public static function print_ajax_nonce( string $hook_suffix = '' ): void
        {
            if ( ! current_user_can( 'edit_posts' ) ) {
                return;
            }

            if ( ! in_array( $hook_suffix, [ 'post.php', 'post-new.php' ], true ) ) {
                return;
            }

            $payload = [
                    'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
                    'nonce'           => wp_create_nonce( 'sp_widgets_ajax' ),
                    'catalogCacheKey' => 'spEditorWidgetsCatalog:v2:' . get_current_user_id() . ':' . wp_cache_get_last_changed( 'posts' ),
            ];

            if ( class_exists( \SoinProduction\Kit\AdminBootstrap::class ) ) {
                \SoinProduction\Kit\AdminBootstrap::set( 'editorWidgets', $payload );
                \SoinProduction\Kit\AdminBootstrap::exposeLegacyGlobal( 'SP_WIDGETS_NONCE', 'editorWidgets', 'nonce' );
                return;
            }

            printf(
                    '<script>window.SP_WIDGETS_NONCE = %s;</script>',
                    wp_json_encode( $payload['nonce'] )
            );
        }

        private static function verify_ajax_request(): void
        {
            if ( ! current_user_can( 'edit_posts' ) ) {
                wp_send_json_error( 'Permission denied' );
            }
            if ( ! check_ajax_referer( 'sp_widgets_ajax', 'nonce', false ) ) {
                wp_send_json_error( 'Invalid nonce' );
            }
        }

        public static function get_widgets_list(): void
        {
            self::verify_ajax_request();

            $query = new WP_Query( [
                    'post_type'      => 'for-editor',
                    'posts_per_page' => -1,
                    'post_status'    => 'any',
                    'orderby'        => 'title',
                    'order'          => 'ASC',
                    'no_found_rows'  => true,
            ] );

            $list = [];
            if ( $query->have_posts() ) {
                while ( $query->have_posts() ) {
                    $query->the_post();
                    $widget_id    = get_the_ID();
                    if ( ! current_user_can( 'read_post', $widget_id ) && ! current_user_can( 'edit_post', $widget_id ) ) {
                        continue;
                    }
                    $blocks       = self::get_widget_blocks( $widget_id );
                    $block_labels = function_exists( 'for_editor_get_block_labels' )
                            ? for_editor_get_block_labels( $widget_id )
                            : self::get_widget_block_labels( $blocks );
                    $type_label   = $block_labels ? implode( ', ', $block_labels ) : __( 'No blocks', 'sp-widgets' );

                    $list[] = [
                            'id'          => $widget_id,
                            'title'       => get_the_title() ?: '(No Title #' . $widget_id . ')',
                            'type'        => 'blocks',
                            'type_label'  => $type_label,
                            'preview_url' => self::get_widget_preview_url( $widget_id, $blocks ),
                    ];
                }
                wp_reset_postdata();
            }

            wp_send_json_success( $list );
        }

        private static function get_widget_blocks( int $widget_id ): array
        {
            $blocks = function_exists( 'for_editor_get_blocks' )
                    ? for_editor_get_blocks( $widget_id )
                    : ( function_exists( 'get_field' ) ? get_field( 'blocks', $widget_id ) : [] );

            return is_array( $blocks ) ? $blocks : [];
        }

        private static function get_widget_block_labels( array $blocks ): array
        {
            $labels = [];

            foreach ( $blocks as $block ) {
                $layout = is_array( $block ) ? (string) ( $block['acf_fc_layout'] ?? '' ) : '';
                if ( $layout === '' ) {
                    continue;
                }

                $layout   = preg_replace( '/^block_/', '', $layout );
                $labels[] = ucwords( str_replace( '_', ' ', $layout ) );
            }

            return $labels;
        }

        private static function get_widget_preview_url( int $widget_id, array $blocks ): string
        {
            $preview_url = get_the_post_thumbnail_url( $widget_id, 'medium_large' );
            if ( is_string( $preview_url ) && $preview_url !== '' ) {
                return esc_url_raw( $preview_url );
            }

            foreach ( $blocks as $block ) {
                $layout = is_array( $block ) ? sanitize_key( (string) ( $block['acf_fc_layout'] ?? '' ) ) : '';
                if ( $layout === '' ) {
                    continue;
                }

                $filename = str_replace( '_', '-', $layout ) . '.png';
                $file     = get_template_directory() . '/admin/acf-flex-preview/' . $filename;

                if ( ! is_readable( $file ) ) {
                    continue;
                }

                return esc_url_raw(
                        get_template_directory_uri() . '/admin/acf-flex-preview/' . $filename
                        . '?ver=' . rawurlencode( (string) filemtime( $file ) )
                );
            }

            return '';
        }

        public static function create_new_widget(): void
        {
            self::verify_ajax_request();
            $post_type = get_post_type_object( 'for-editor' );
            $create_cap = $post_type && ! empty( $post_type->cap->create_posts )
                    ? (string) $post_type->cap->create_posts
                    : 'edit_posts';
            if ( ! current_user_can( $create_cap ) ) {
                wp_send_json_error( 'Permission denied', 403 );
            }

            $title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
            if ( ! $title ) {
                wp_send_json_error( 'Title is required' );
            }
            $status = $post_type && ! empty( $post_type->cap->publish_posts ) && current_user_can( (string) $post_type->cap->publish_posts )
                    ? 'publish'
                    : 'draft';
            $post_id = wp_insert_post( [
                    'post_title'  => $title,
                    'post_type'   => 'for-editor',
                    'post_status' => $status,
            ] );
            if ( is_wp_error( $post_id ) ) {
                wp_send_json_error( $post_id->get_error_message() );
            }
            wp_send_json_success( [ 'id' => $post_id, 'status' => $status ] );
        }

        public static function duplicate_widget(): void
        {
            self::verify_ajax_request();

            $source_id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
            $source    = $source_id ? get_post( $source_id ) : null;
            if ( ! $source || $source->post_type !== 'for-editor' ) {
                wp_send_json_error( 'Widget not found' );
            }
            if ( ! current_user_can( 'edit_post', $source_id ) ) {
                wp_send_json_error( 'Permission denied', 403 );
            }

            $post_type = get_post_type_object( 'for-editor' );
            $create_cap = $post_type && ! empty( $post_type->cap->create_posts )
                    ? (string) $post_type->cap->create_posts
                    : 'edit_posts';
            if ( ! current_user_can( $create_cap ) ) {
                wp_send_json_error( 'Permission denied', 403 );
            }

            $title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
            if ( ! $title ) {
                $title = $source->post_title . ' (Copy)';
            }

            $status = $post_type && ! empty( $post_type->cap->publish_posts ) && current_user_can( (string) $post_type->cap->publish_posts )
                    ? 'publish'
                    : 'draft';
            $new_id = wp_insert_post( [
                    'post_title'   => $title,
                    'post_type'    => 'for-editor',
                    'post_status'  => $status,
                    'post_content' => $source->post_content,
                    'post_excerpt' => $source->post_excerpt,
            ] );

            if ( is_wp_error( $new_id ) ) {
                wp_send_json_error( $new_id->get_error_message() );
            }

            if ( ! class_exists( \SoinProduction\Kit\PostDuplicator::class ) ) {
                wp_delete_post( $new_id, true );
                wp_send_json_error( 'Post duplicator is unavailable', 500 );
            }

            \SoinProduction\Kit\PostDuplicator::copyAssociatedData( $source_id, (int) $new_id );
            do_action( 'sp_editor_widget_after_duplicate', $source_id, (int) $new_id );

            wp_send_json_success( [ 'id' => $new_id, 'status' => $status ] );
        }

        private static function get_widget_preview_payload( int $widget_id ): ?array
        {
            if ( $widget_id <= 0 ) {
                return null;
            }

            $post = get_post( $widget_id );
            if (
                ! $post
                || $post->post_type !== 'for-editor'
                || ( ! current_user_can( 'read_post', $widget_id ) && ! current_user_can( 'edit_post', $widget_id ) )
            ) {
                return null;
            }

            $html = self::render_shortcode( [ 'id' => $widget_id ] );
            if ( $html === '' ) {
                $html = '<span class="sp-editor-widget-empty">' . esc_html__( 'Widget preview unavailable', 'sp-widgets' ) . '</span>';
            }

            return [
                    'id'    => $widget_id,
                    'title' => get_the_title( $widget_id ) ?: sprintf( '#%d', $widget_id ),
                    'type'  => 'blocks',
                    'html'  => $html,
            ];
        }

        public static function render_widget_preview(): void
        {
            self::verify_ajax_request();

            $widget_id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
            if ( ! $widget_id ) {
                wp_send_json_error( 'Invalid widget ID' );
            }

            $payload = self::get_widget_preview_payload( $widget_id );
            if ( $payload === null ) {
                wp_send_json_error( 'Widget not found' );
            }

            wp_send_json_success( $payload );
        }

        public static function render_widget_previews(): void
        {
            self::verify_ajax_request();

            $ids = isset( $_POST['ids'] ) && is_array( $_POST['ids'] )
                    ? array_values( array_unique( array_filter( array_map( 'absint', wp_unslash( $_POST['ids'] ) ) ) ) )
                    : [];
            $ids = array_slice( $ids, 0, 50 );

            $previews = [];
            foreach ( $ids as $widget_id ) {
                $payload = self::get_widget_preview_payload( $widget_id );
                if ( $payload !== null ) {
                    $previews[ (string) $widget_id ] = $payload;
                }
            }

            wp_send_json_success( [ 'previews' => $previews ] );
        }

        public static function edit_widget_iframe(): void
        {
            check_admin_referer( 'sp_widgets_ajax', 'nonce' );
            if ( ! current_user_can( 'edit_posts' ) ) {
                wp_die( 'Permission denied' );
            }
            $widget_id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
            $iframe_mode = isset( $_GET['mode'] ) ? sanitize_text_field( $_GET['mode'] ) : 'insert';
            if (
                ! $widget_id
                || get_post_type( $widget_id ) !== 'for-editor'
                || ! current_user_can( 'edit_post', $widget_id )
            ) {
                wp_die( 'Invalid widget ID' );
            }

            self::bootstrap_iframe_tinymce();

            if ( function_exists( 'acf_form_head' ) ) {
                acf_form_head();
            }

            global $hook_suffix, $current_screen;
            $hook_suffix = 'sp-widget-iframe';
            if ( function_exists( 'set_current_screen' ) ) {
                try {
                    set_current_screen( 'sp-widget-iframe' );
                } catch ( Exception $e ) {

                }
            }

            iframe_header( __( 'Edit Widget' ) );
            ?>
            <style>
                html, body {
                    background: #fff !important;
                    margin: 0 !important;
                    padding: 0 !important;
                    height: 100% !important;
                }
                body.iframe {
                    padding: 20px !important;
                    box-sizing: border-box !important;
                }
                #adminmenuwrap, #adminmenuback, #wpadminbar, #wpfooter {
                    display: none !important;
                }
                #wpcontent {
                    margin-left: 0 !important;
                    padding: 0 !important;
                }
                .acf-form-submit {
                    display: none !important;
                }
                .acf-form-submit input[type="submit"] {
                    display: none !important;
                }
            </style>
            <?php
            if ( isset( $_GET['sp_widget_saved'] ) && $_GET['sp_widget_saved'] === '1' ) {
                ?>
                <script>
                    if ( window.parent && window.parent !== window ) {
                        window.parent.postMessage({ type: 'sp_widget_saved', id: <?php echo $widget_id; ?> }, window.location.origin);
                    }
                </script>
                <?php
            }
            ?>
            <div class="sp-iframe-form-wrap">
                <?php
                    if ( function_exists( 'acf_form' ) ) {
                        acf_form([
                                'post_id'      => $widget_id,
                                'form'         => true,
                                'return'       => esc_url_raw( add_query_arg( 'sp_widget_saved', '1', remove_query_arg( 'sp_widget_saved', wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) ),
                                'submit_value' => 'Save Changes',
                        ]);
                    }
                ?>
            </div>
            <?php
            iframe_footer();
            exit;
        }

        private static function bootstrap_iframe_tinymce(): void
        {
            add_filter( 'mce_external_plugins', [ self::class, 'iframe_mce_external_plugins' ], 100 );
            add_filter( 'tiny_mce_before_init', [ self::class, 'iframe_tiny_mce_before_init' ], 100, 2 );
            add_action( 'admin_print_footer_scripts', [ self::class, 'print_iframe_acf_wysiwyg_bridge' ], 100 );
        }

        public static function iframe_mce_external_plugins( array $plugins ): array
        {
            foreach ( self::iframe_external_plugins() as $plugin => $url ) {
                $plugins[ $plugin ] = $url;
            }
            return $plugins;
        }

        public static function iframe_tiny_mce_before_init( array $mce_init, string $editor_id ): array
        {
            unset( $editor_id );
            $mce_init['menubar'] = false;
            $mce_init['textcolor_map'] = wp_json_encode( self::iframe_textcolor_map(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
            $mce_init['textcolor_rows'] = self::iframe_textcolor_rows();

            $plugins = array_filter( array_map( 'trim', explode( ',', (string) ( $mce_init['plugins'] ?? '' ) ) ) );
            foreach ( [ 'wordpress', 'wplink', 'paste', 'textcolor', 'colorpicker', 'lists', 'table' ] as $plugin ) {
                if ( ! in_array( $plugin, $plugins, true ) ) {
                    $plugins[] = $plugin;
                }
            }
            $mce_init['plugins'] = implode( ',', $plugins );

            $editor_style = self::editor_style_url();
            $content_css = array_filter( array_map( 'trim', explode( ',', (string) ( $mce_init['content_css'] ?? '' ) ) ) );
            if ( ! in_array( $editor_style, $content_css, true ) ) {
                $content_css[] = $editor_style;
            }
            $mce_init['content_css'] = implode( ',', $content_css );

            return $mce_init;
        }

        public static function print_iframe_acf_wysiwyg_bridge(): void
        {
            $external_plugins = wp_json_encode( self::iframe_external_plugins(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
            $textcolor_map = wp_json_encode( self::iframe_textcolor_map(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
            $style_url = self::editor_style_url();
            ?>
            <script>
                (function(window) {
                    'use strict';
                    var externalPlugins = <?php echo $external_plugins; ?>;
                    var textcolorMap = <?php echo $textcolor_map; ?>;
                    var textcolorRows = <?php echo (int) self::iframe_textcolor_rows(); ?>;
                    var editorStyle = <?php echo wp_json_encode( $style_url, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); ?>;

                    function unique(list) {
                        var out = [];
                        (list || []).forEach(function(item) {
                            item = String(item || '').trim();
                            if (item && out.indexOf(item) === -1) {
                                out.push(item);
                            }
                        });
                        return out;
                    }

                    function normalizeCsv(value) {
                        return unique(String(value || '').split(','));
                    }

                    function patchSettings(settings) {
                        settings = settings || {};
                        settings.menubar = false;
                        settings.wp_autoresize_on = false;
                        settings.external_plugins = Object.assign({}, settings.external_plugins || {}, externalPlugins);
                        settings.textcolor_map = textcolorMap.slice();
                        settings.textcolor_rows = textcolorRows;

                        var plugins = normalizeCsv(settings.plugins);
                        [
                            'wordpress',
                            'wplink',
                            'paste',
                            'textcolor',
                            'colorpicker',
                            'lists',
                            'table'
                        ].forEach(function(plugin) {
                            if (plugins.indexOf(plugin) === -1) {
                                plugins.push(plugin);
                            }
                        });
                        settings.plugins = plugins.join(',');

                        var contentCss = normalizeCsv(settings.content_css);
                        if (contentCss.indexOf(editorStyle) === -1) {
                            contentCss.push(editorStyle);
                        }
                        settings.content_css = contentCss.join(',');

                        return settings;
                    }

                    function installAcfFilter() {
                        if (!window.acf || typeof window.acf.addFilter !== 'function') {
                            window.setTimeout(installAcfFilter, 20);
                            return;
                        }

                        window.acf.addFilter('wysiwyg_tinymce_settings', function(settings) {
                            return patchSettings(settings);
                        });

                        if (typeof window.acf.addAction === 'function') {
                            window.acf.addAction('wysiwyg_tinymce_init', function(editor) {
                                if (editor && typeof editor.save === 'function') {
                                    editor.on('change keyup undo redo', function() {
                                        editor.save();
                                    });
                                }
                            });
                        }
                    }

                    function syncActiveEditorForTextMode(button) {
                        if (!window.tinymce || typeof window.tinymce.get !== 'function') {
                            return;
                        }
                        var wrap = button && button.closest ? button.closest('.wp-editor-wrap') : null;
                        var textarea = wrap ? wrap.querySelector('textarea.wp-editor-area, textarea') : null;
                        var id = textarea ? textarea.id : '';
                        var editor = id ? window.tinymce.get(id) : null;

                        if (!editor && window.tinymce.activeEditor) {
                            editor = window.tinymce.activeEditor;
                            textarea = editor.id ? document.getElementById(editor.id) : textarea;
                        }

                        if (!editor || !textarea || typeof editor.getContent !== 'function') {
                            return;
                        }
                        var content = editor.getContent({ format: 'html' });
                        if (!content) {
                            return;
                        }
                        textarea.value = content;
                        if (typeof editor.save === 'function') {
                            editor.save();
                        }
                    }

                    document.addEventListener('mousedown', function(event) {
                        var button = event.target && event.target.closest ? event.target.closest('.wp-switch-editor.switch-html, .wp-switch-editor[id$="-html"]') : null;
                        if (button) {
                            syncActiveEditorForTextMode(button);
                        }
                    }, true);

                    if (window.tinyMCEPreInit && window.tinyMCEPreInit.mceInit) {
                        Object.keys(window.tinyMCEPreInit.mceInit).forEach(function(id) {
                            if (id === 'acf_content' || id.indexOf('acf-editor-') === 0) {
                                window.tinyMCEPreInit.mceInit[id] = patchSettings(window.tinyMCEPreInit.mceInit[id]);
                            }
                        });
                    }
                    installAcfFilter();
                })(window);
            </script>
            <?php
        }

        private static function iframe_external_plugins(): array
        {

            $editor_helper = get_template_directory() . '/core/helpers/custom-editor.php';
            if (file_exists($editor_helper)) {
                require_once $editor_helper;
            }

            $plugin_map = function_exists('sp_get_tinymce_plugin_class_map') ? sp_get_tinymce_plugin_class_map() : [];
            $plugins = [];
            foreach ( $plugin_map as $button => $class_name ) {

                if ( $button === 'sp_widgets' ) {
                    continue;
                }
                if ( class_exists( $class_name ) && method_exists( $class_name, 'register_tinymce_plugin' ) ) {
                    $plugins = call_user_func( [ $class_name, 'register_tinymce_plugin' ], $plugins );
                }
            }
            return $plugins;
        }

        private static function iframe_textcolor_map(): array
        {
            $map = [];
            $palette = function_exists( 'color_palette_config' ) ? color_palette_config() : [];
            foreach ( (array) $palette as $hex => $label ) {
                $map[] = strtoupper( ltrim( (string) $hex, '#' ) );
                $map[] = (string) $label;
            }
            return $map;
        }

        private static function iframe_textcolor_rows(): int
        {
            $colors_count = (int) floor( count( self::iframe_textcolor_map() ) / 2 );
            return max( 1, (int) ceil( $colors_count / 8 ) );
        }

        private static function editor_style_url(): string
        {
            $file = get_template_directory() . '/assets/css/for-editor.css';
            $ver  = file_exists( $file ) ? (string) filemtime( $file ) : ( defined( '_S_VERSION' ) ? (string) _S_VERSION : '1.0.0' );
            return get_template_directory_uri() . '/assets/css/for-editor.css?ver=' . rawurlencode( $ver );
        }
    }

    SP_Editor_Widgets::init();
    }
