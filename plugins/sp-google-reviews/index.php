<?php
    /**
     * Plugin Name: SP Google Reviews
     * Description: Imports Google reviews via SerpAPI into the Review CPT.
     * Version:     1.4.0
     * Requires PHP: 7.4
     */

    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

    require_once __DIR__ . '/includes/class-widget-builder.php';

    final class SP_Reviews_Importer {

        private const OPT_KEY = 'sp_reviews_importer_options';

        private const META_PROVIDER    = '_sp_review_provider';
        private const META_EXTERNAL_ID = '_sp_review_external_id';
        private const META_REVIEW_URL  = '_sp_review_url';
        private const META_PLACE_ID    = '_sp_review_place_id';
        private const META_SOURCE_ID   = '_sp_review_source_id';
        private const META_LANGUAGE    = '_sp_review_language';
        private const META_IMAGES      = '_sp_review_images';

        private const STATS_OPT_RATING = 'sp_google_reviews_rating';
        private const STATS_OPT_COUNT  = 'sp_google_reviews_count';
        private const STATS_OPT_LAST   = 'sp_google_reviews_last_fetch';
        private const STATS_OPT_BY_LANG = 'sp_google_reviews_stats_by_language';

        private const PROVIDER = 'serpapi';

        public static function init(): void {
            SP_Google_Reviews_Widget_Builder::init();
            add_action( 'admin_menu',            [ __CLASS__, 'add_admin_page' ] );
            add_action( 'admin_init',            [ __CLASS__, 'register_settings' ] );
            add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_assets' ] );
            add_action( 'admin_notices',         [ __CLASS__, 'render_import_notice' ] );
            add_action( 'restrict_manage_posts', [ __CLASS__, 'add_list_button' ] );
            add_action( 'admin_post_sp_reviews_import', [ __CLASS__, 'handle_import' ] );
            add_action( 'wp_ajax_sp_reviews_import',    [ __CLASS__, 'ajax_import_handler' ] );
            add_action( 'wp_enqueue_scripts',    [ __CLASS__, 'enqueue_frontend_assets' ] );
            add_shortcode( 'google_reviews_widget', [ __CLASS__, 'shortcode_widget' ] );
            add_filter( 'pll_get_post_types', [ __CLASS__, 'register_polylang_post_type' ], 10, 2 );
            add_action( 'init', [ __CLASS__, 'register_wpml_post_type' ], 20 );
            add_action( 'add_meta_boxes_review', [ __CLASS__, 'register_review_media_metabox' ] );

            // Exclude review-owned media from the Media Library.
            add_filter( 'ajax_query_attachments_args', [ __CLASS__, 'exclude_avatars_from_media_library' ] );
            add_action( 'pre_get_posts',               [ __CLASS__, 'exclude_avatars_from_media_list' ] );

            // Auto-delete review-owned media when a review is deleted.
            add_action( 'before_delete_post',          [ __CLASS__, 'delete_review_thumbnail' ] );

            // Clear cache when attachments are deleted
            add_action( 'delete_attachment',           [ __CLASS__, 'delete_review_avatars_cache' ] );
        }

        public static function register_polylang_post_type( array $post_types, bool $is_settings ): array {
            $post_types['review'] = 'review';
            return $post_types;
        }

        public static function register_wpml_post_type(): void {
            if ( apply_filters( 'wpml_is_translated_post_type', null, 'review' ) ) {
                return;
            }
            do_action( 'wpml_set_translation_mode_for_post_type', 'review', 'translate' );
        }

        public static function register_review_media_metabox(): void {
            add_meta_box(
                    'sp-review-photos',
                    'Review Photos',
                    [ __CLASS__, 'render_review_media_metabox' ],
                    'review',
                    'normal',
                    'default'
            );
        }

        public static function render_review_media_metabox( WP_Post $post ): void {
            $image_ids = array_values( array_filter( array_map(
                    'absint',
                    (array) get_post_meta( $post->ID, self::META_IMAGES, true )
            ) ) );
            ?>
            <div class="sp-review-photos sp-admin-component" data-sp-admin-component>
                <?php if ( $image_ids === [] ) : ?>
                    <p class="description">No review photos have been imported. Run Google Reviews sync with overwrite enabled.</p>
                <?php else : ?>
                    <div class="sp-review-photos__grid">
                        <?php foreach ( $image_ids as $image_id ) :
                            $edit_url = get_edit_post_link( $image_id );
                            $image    = wp_get_attachment_image( $image_id, 'medium', false, [
                                    'class'   => 'sp-review-photos__image',
                                    'loading' => 'lazy',
                            ] );
                            if ( ! is_string( $image ) || $image === '' ) {
                                continue;
                            }
                            ?>
                            <a class="sp-review-photos__item" href="<?php echo esc_url( $edit_url ?: '#' ); ?>">
                                <?php echo $image; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <p class="description"><?php echo esc_html( sprintf( '%d photo(s) imported from Google.', count( $image_ids ) ) ); ?></p>
                <?php endif; ?>
            </div>
            <style>
                #sp-review-photos .inside { margin: 0; padding: 16px; }
                .sp-review-photos__grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 12px; margin-bottom: 12px; }
                .sp-review-photos__item { display: block; overflow: hidden; border: 1px solid #dcdcde; background: #f6f7f7; aspect-ratio: 1; }
                .sp-review-photos__image { display: block; width: 100%; height: 100%; object-fit: cover; }
            </style>
            <?php
        }

        // -------------------------------------------------------------------------
        // Options
        // -------------------------------------------------------------------------

        public static function get_options(): array {
            $opt = get_option( self::OPT_KEY, [] );
            $opt = is_array( $opt ) ? $opt : [];

            $opt = wp_parse_args( $opt, [
                    'api_key'         => '',
                    'place_id'        => '',
                    'min_rating'      => 1,
                    'limit'           => 30,
                    'language'        => substr( get_locale(), 0, 2 ) ?: 'en',
                    'fallback_rating' => '',
                    'fallback_count'  => '',
                    'overwrite'       => 1,
                    'load_widget_styles' => 1,
            ] );

            $opt['api_key']   = is_string( $opt['api_key'] ) ? trim( $opt['api_key'] ) : '';
            $opt['place_id']  = is_string( $opt['place_id'] ) ? trim( $opt['place_id'] ) : '';
            $opt['language']  = is_string( $opt['language'] ) ? trim( $opt['language'] ) : 'en';
            $opt['min_rating'] = max( 1, min( 5, (int) $opt['min_rating'] ) );
            $opt['limit']      = max( 1, min( 200, (int) $opt['limit'] ) );
            $opt['overwrite']  = ! empty( $opt['overwrite'] ) ? 1 : 0;
            $opt['load_widget_styles'] = ! empty( $opt['load_widget_styles'] ) ? 1 : 0;

            $opt['fallback_rating'] = is_string( $opt['fallback_rating'] ) ? trim( $opt['fallback_rating'] ) : '';
            $opt['fallback_count']  = is_string( $opt['fallback_count'] ) ? trim( $opt['fallback_count'] ) : '';

            return $opt;
        }

        public static function sanitize_options( $input ): array {
            $input = is_array( $input ) ? $input : [];

            $api_key = isset( $input['api_key'] ) ? trim( (string) $input['api_key'] ) : '';
            $api_key = preg_replace( '~[^A-Za-z0-9]+~', '', $api_key );

            $place_id = isset( $input['place_id'] ) ? trim( (string) $input['place_id'] ) : '';
            $place_id = preg_replace( '~[^A-Za-z0-9_\-]+~', '', $place_id );

            $language = isset( $input['language'] ) ? trim( (string) $input['language'] ) : 'en';
            $language = preg_replace( '~[^a-z]+~', '', strtolower( $language ) );
            $language = $language ?: 'en';

            $min_rating = max( 1, min( 5, (int) ( $input['min_rating'] ?? 1 ) ) );
            $limit      = max( 1, min( 200, (int) ( $input['limit'] ?? 30 ) ) );
            $overwrite  = ! empty( $input['overwrite'] ) ? 1 : 0;
            $load_widget_styles = ! empty( $input['load_widget_styles'] ) ? 1 : 0;

            $fallback_rating = isset( $input['fallback_rating'] ) ? trim( (string) $input['fallback_rating'] ) : '';
            $fallback_count  = isset( $input['fallback_count'] ) ? trim( (string) $input['fallback_count'] ) : '';

            return compact( 'api_key', 'place_id', 'language', 'min_rating', 'limit', 'fallback_rating', 'fallback_count', 'overwrite', 'load_widget_styles' );
        }

        private static function can_import_now( array $opt ): bool {
            return trim( (string) ( $opt['api_key'] ?? '' ) ) !== ''
                   && trim( (string) ( $opt['place_id'] ?? '' ) ) !== '';
        }

        /**
         * Return the content languages that must be synchronized.
         *
         * The array key is the Polylang/WPML language code. `provider` is the
         * two-letter language sent to SerpAPI as `hl`.
         */
        private static function get_import_languages( array $opt ): array {
            $fallback = self::normalize_provider_language( (string) ( $opt['language'] ?? 'en' ) );

            if ( function_exists( 'pll_languages_list' ) ) {
                $slugs   = pll_languages_list( [ 'fields' => 'slug' ] );
                $locales = pll_languages_list( [ 'fields' => 'locale' ] );
                $result  = [];

                foreach ( (array) $slugs as $index => $slug ) {
                    $code = sanitize_key( (string) $slug );
                    if ( $code === '' ) {
                        continue;
                    }
                    $result[ $code ] = self::normalize_provider_language( (string) ( $locales[ $index ] ?? $code ) );
                }

                if ( $result !== [] ) {
                    return $result;
                }
            }

            $wpml_languages = apply_filters( 'wpml_active_languages', null, [
                    'skip_missing' => 0,
                    'orderby'      => 'code',
            ] );
            if ( ! is_array( $wpml_languages ) || $wpml_languages === [] ) {
                global $sitepress;
                if ( is_object( $sitepress ) && method_exists( $sitepress, 'get_active_languages' ) ) {
                    $wpml_languages = $sitepress->get_active_languages();
                }
            }
            if ( is_array( $wpml_languages ) && $wpml_languages !== [] ) {
                $result = [];
                foreach ( $wpml_languages as $key => $language ) {
                    $language = is_array( $language ) ? $language : [];
                    $code = sanitize_key( (string) ( $language['language_code'] ?? $key ) );
                    if ( $code === '' ) {
                        continue;
                    }
                    $result[ $code ] = self::normalize_provider_language(
                            (string) ( $language['default_locale'] ?? $code )
                    );
                }

                if ( $result !== [] ) {
                    return $result;
                }
            }

            return [ $fallback => $fallback ];
        }

        private static function normalize_provider_language( string $language ): string {
            $language = strtolower( trim( str_replace( '_', '-', $language ) ) );
            $language = explode( '-', $language )[0] ?? '';
            $language = preg_replace( '/[^a-z]/', '', $language );
            return $language !== '' ? $language : 'en';
        }

        private static function assign_post_language( int $post_id, string $language ): void {
            if ( function_exists( 'pll_set_post_language' ) ) {
                pll_set_post_language( $post_id, $language );
                return;
            }

            if ( has_action( 'wpml_set_element_language_details' ) ) {
                do_action( 'wpml_set_element_language_details', [
                        'element_id'           => $post_id,
                        'element_type'         => apply_filters( 'wpml_element_type', 'review' ),
                        'trid'                 => false,
                        'language_code'        => $language,
                        'source_language_code' => null,
                ] );
            }
        }

        private static function connect_post_translations( array $posts_by_language ): void {
            $posts_by_language = array_filter( array_map( 'absint', $posts_by_language ) );
            if ( count( $posts_by_language ) < 2 ) {
                return;
            }

            if ( function_exists( 'pll_save_post_translations' ) ) {
                pll_save_post_translations( $posts_by_language );
                return;
            }

            if ( ! has_action( 'wpml_set_element_language_details' ) ) {
                return;
            }

            $default_language = (string) apply_filters( 'wpml_default_language', '' );
            $source_language  = isset( $posts_by_language[ $default_language ] )
                    ? $default_language
                    : (string) array_key_first( $posts_by_language );
            $source_id   = (int) $posts_by_language[ $source_language ];
            $element_type = apply_filters( 'wpml_element_type', 'review' );
            $trid = apply_filters( 'wpml_element_trid', null, $source_id, $element_type );

            if ( ! $trid ) {
                self::assign_post_language( $source_id, $source_language );
                $trid = apply_filters( 'wpml_element_trid', null, $source_id, $element_type );
            }
            if ( ! $trid ) {
                return;
            }

            foreach ( $posts_by_language as $language => $post_id ) {
                if ( (int) $post_id === $source_id ) {
                    continue;
                }
                do_action( 'wpml_set_element_language_details', [
                        'element_id'           => (int) $post_id,
                        'element_type'         => $element_type,
                        'trid'                 => (int) $trid,
                        'language_code'        => (string) $language,
                        'source_language_code' => $source_language,
                ] );
            }
        }

        // -------------------------------------------------------------------------
        // Admin page
        // -------------------------------------------------------------------------

        public static function add_admin_page(): void {
            add_options_page(
                    'Google Reviews',
                    '<span style="display:flex;align-items:center;gap:5px;">
				<svg xmlns="http://www.w3.org/2000/svg" fill="none" width="20" height="20" viewBox="0 0 24 24">
					<path fill="currentColor" d="m19.8 10.8-.1-.4h-7.5v3.2h4.5a4.4 4.4 0 0 1-4.4 3.3q-2 0-3.5-1.4A5 5 0 0 1 7.3 12q0-2 1.5-3.5A5 5 0 0 1 12.3 7q1.6 0 3 1.2L17.5 6a8.1 8.1 0 0 0-11 .3 8 8 0 0 0-.2 11.3 8 8 0 0 0 9 1.8q1.4-.6 2.4-1.7a8 8 0 0 0 2.1-5.5z"/>
				</svg> Google Reviews
			</span>',
                    'manage_options',
                    'sp-google-reviews',
                    [ __CLASS__, 'render_admin_page' ]
            );
        }

        public static function register_settings(): void {
            register_setting( 'sp_google_reviews', self::OPT_KEY, [
                    'type'              => 'array',
                    'sanitize_callback' => [ __CLASS__, 'sanitize_options' ],
                    'default'           => [],
            ] );

            SP_Google_Reviews_Widget_Builder::register_settings();
        }

        public static function enqueue_admin_assets( string $hook ): void {
            if ( $hook !== 'settings_page_sp-google-reviews' ) {
                return;
            }

            wp_enqueue_media();
            wp_enqueue_style( 'wp-color-picker' );
            wp_enqueue_script( 'wp-color-picker' );

            wp_register_style( 'sp-gr-admin', false );
            wp_enqueue_style( 'sp-gr-admin' );
            wp_add_inline_style( 'sp-gr-admin', self::admin_css() );

            wp_add_inline_style( 'sp-gr-admin', SP_Google_Reviews_Widget_Builder::admin_css() );

            wp_register_script( 'sp-gr-admin', false, [ 'jquery' ], null, true );
            wp_enqueue_script( 'sp-gr-admin' );
            wp_add_inline_script( 'sp-gr-admin', self::admin_js() );

            wp_add_inline_script( 'sp-gr-admin', SP_Google_Reviews_Widget_Builder::admin_js() );
        }

        public static function render_admin_page(): void {
            if ( ! current_user_can( 'manage_options' ) ) {
                return;
            }

            $active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'sync';
            if ( $active_tab === 'widgets' ) {
                SP_Google_Reviews_Widget_Builder::render_admin_page();
                return;
            }

            $opt  = self::get_options();
            $import_languages = self::get_import_languages( $opt );
            $is_multilingual  = function_exists( 'pll_languages_list' ) || has_filter( 'wpml_active_languages' );
            $last = get_option( self::STATS_OPT_LAST, '' );
            $last = is_string( $last ) ? trim( $last ) : '';

            $ready        = self::can_import_now( $opt );
            $place_id_set = $opt['place_id'] !== '';
            $api_key_set  = $opt['api_key'] !== '';

            $stats_rating = (float) str_replace( ',', '.', (string) get_option( self::STATS_OPT_RATING, '0.0' ) );
            $stats_count  = max( 0, (int) get_option( self::STATS_OPT_COUNT, 0 ) );
            $last_label   = $last !== '' ? mysql2date( 'Y-m-d H:i', $last ) : 'Never';

            $sync_url      = wp_nonce_url( admin_url( 'admin-post.php?action=sp_reviews_import' ), 'sp_reviews_import' );
            $reviews_url   = admin_url( 'edit.php?post_type=review' );
            $shortcode_txt = '[google_reviews_widget]';

            $languages = [
                'en' => 'English',
                'uk' => 'Ukrainian',
                'fr' => 'French',
                'es' => 'Spanish',
                'de' => 'German',
                'it' => 'Italian',
                'pl' => 'Polish',
                'nl' => 'Dutch',
                'pt' => 'Portuguese',
                'ru' => 'Russian',
            ];
            ?>
            <div class="wrap sp-gr-admin-wrap sp-admin-page">
                <header class="sp-admin-header">
                    <div class="sp-admin-header__identity">
                        <span class="sp-admin-header__icon dashicons dashicons-star-filled" aria-hidden="true"></span>
                        <div class="sp-admin-header__copy">
                            <h1>Google Reviews</h1>
                            <p>Manage Google reviews synchronization via SerpAPI.</p>
                        </div>
                    </div>
                    <div class="sp-admin-header__actions">
                        <button type="submit" class="button button-primary" form="sp-gr-settings">Save settings</button>
                    </div>
                </header>

                <?php self::render_admin_tabs( 'sync' ); ?>

                <section class="sp-gr-card sp-admin-card">
                    <div class="sp-gr-card-head sp-admin-card__header"><h2>Sync Status</h2></div>
                    <div class="sp-gr-metrics sp-admin-metrics">
                        <div class="sp-gr-metric sp-admin-metric">
                            <span class="sp-gr-metric__label">Provider</span>
                            <span class="sp-gr-metric__value">SerpAPI</span>
                        </div>
                        <div class="sp-gr-metric sp-admin-metric">
                            <span class="sp-gr-metric__label">Ready to sync</span>
                            <span id="sp-gr-ready-badge" class="sp-gr-badge <?php echo $ready ? 'is-ok' : 'is-warn'; ?>">
							<?php echo $ready ? 'Yes' : 'No'; ?>
						</span>
                        </div>
                        <div class="sp-gr-metric sp-admin-metric">
                            <span class="sp-gr-metric__label">Average rating</span>
                            <span class="sp-gr-metric__value"><?php echo esc_html( number_format( $stats_rating, 1, '.', '' ) ); ?></span>
                        </div>
                        <div class="sp-gr-metric sp-admin-metric">
                            <span class="sp-gr-metric__label">Reviews in DB</span>
                            <span class="sp-gr-metric__value"><?php echo esc_html( number_format_i18n( $stats_count ) ); ?></span>
                        </div>
                        <div class="sp-gr-metric sp-admin-metric">
                            <span class="sp-gr-metric__label">Last sync</span>
                            <span class="sp-gr-metric__value"><?php echo esc_html( $last_label ); ?></span>
                        </div>
                    </div>

                    <!-- Sleek Progress Sync Card -->
                    <div id="sp-gr-sync-progress" class="sp-gr-progress-card" style="display:none; margin-bottom: 20px;">
                        <div class="sp-gr-progress-header">
                            <span class="sp-gr-progress-title">Synchronizing Google Reviews...</span>
                            <span id="sp-gr-progress-percent">0%</span>
                        </div>
                        <div class="sp-gr-progress-bar-bg">
                            <div id="sp-gr-progress-bar-fill" class="sp-gr-progress-bar-fill"></div>
                        </div>
                        <p class="description" style="margin: 0;">Fetching reviews pages from Google Maps via SerpAPI. Please do not close or reload this page.</p>
                    </div>

                    <div class="sp-gr-actions">
                        <input type="hidden" id="sp-gr-sync-nonce" value="<?php echo esc_attr( wp_create_nonce( 'sp_reviews_import_nonce' ) ); ?>" />
                        <a id="sp-gr-sync-btn"
                           href="<?php echo esc_url( $sync_url ); ?>"
                           class="button button-primary <?php echo $ready ? '' : 'is-disabled'; ?>"
                           aria-disabled="<?php echo $ready ? 'false' : 'true'; ?>"
                           title="<?php echo esc_attr( $ready ? 'Sync reviews now' : 'Fill required fields first' ); ?>">
                            Sync now
                        </a>
                        <a href="<?php echo esc_url( $reviews_url ); ?>" class="button">Open Reviews</a>
                        <button type="button" class="button sp-gr-copy-btn" data-copy="<?php echo esc_attr( $shortcode_txt ); ?>">
                            Copy shortcode
                        </button>
                    </div>
                    <p class="description">Widget shortcode: <code><?php echo esc_html( $shortcode_txt ); ?></code></p>
                    <p id="sp-gr-copy-status" class="sp-gr-copy-status" aria-live="polite"></p>
                </section>

                <form id="sp-gr-settings" method="post" action="options.php" class="sp-gr-settings-form">
                    <?php settings_fields( 'sp_google_reviews' ); ?>

                    <section class="sp-gr-card sp-admin-card">
                        <div class="sp-gr-card-head sp-admin-card__header"><h2>Provider Settings</h2></div>
                        <table class="form-table sp-gr-form-table">

                            <tr class="sp-gr-row">
                                <th scope="row"><label for="sp-api-key">SerpAPI Key</label></th>
                                <td>
                                    <input id="sp-api-key" type="text" class="regular-text"
                                           name="<?php echo esc_attr( self::OPT_KEY ); ?>[api_key]"
                                           value="<?php echo esc_attr( $opt['api_key'] ); ?>"
                                           placeholder="Insert SerpAPI key" autocomplete="off" />
                                    <p class="description">
                                        Free 100 queries/month &rarr;
                                        <a href="https://serpapi.com/users/sign_up" target="_blank" rel="noopener">serpapi.com</a>
                                    </p>
                                </td>
                            </tr>

                            <tr class="sp-gr-row">
                                <th scope="row"><label for="sp-place-id">Place ID</label></th>
                                <td>
                                    <input id="sp-place-id" type="text" class="regular-text"
                                           name="<?php echo esc_attr( self::OPT_KEY ); ?>[place_id]"
                                           value="<?php echo esc_attr( $opt['place_id'] ); ?>"
                                           placeholder="ChIJ..." />
                                    <p class="description">Place ID from Google Maps (the <code>query_place_id</code> parameter from the Google maps link).</p>
                                </td>
                            </tr>

                            <tr class="sp-gr-row">
                                <th scope="row"><label for="sp-language">Reviews Language</label></th>
                                <td>
                                    <?php if ( $is_multilingual ) : ?>
                                        <input type="hidden"
                                               name="<?php echo esc_attr( self::OPT_KEY ); ?>[language]"
                                               value="<?php echo esc_attr( $opt['language'] ); ?>" />
                                        <strong><?php echo esc_html( implode( ', ', array_keys( $import_languages ) ) ); ?></strong>
                                        <p class="description">Detected from Polylang/WPML. Every active language is synchronized and linked automatically.</p>
                                    <?php else : ?>
                                    <select id="sp-language" name="<?php echo esc_attr( self::OPT_KEY ); ?>[language]">
                                        <?php foreach ( $languages as $code => $name ) : ?>
                                            <option value="<?php echo esc_attr( $code ); ?>" <?php selected( $opt['language'], $code ); ?>>
                                                <?php echo esc_html( $name . ' (' . $code . ')' ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description">Select the language for the fetched reviews.</p>
                                    <?php endif; ?>
                                </td>
                            </tr>

                            <tr class="sp-gr-row">
                                <th scope="row"><label for="sp-min-rating">Minimum Rating</label></th>
                                <td>
                                    <input id="sp-min-rating" type="number" class="small-text"
                                           min="1" max="5"
                                           name="<?php echo esc_attr( self::OPT_KEY ); ?>[min_rating]"
                                           value="<?php echo esc_attr( (string) $opt['min_rating'] ); ?>" />
                                    <p class="description">Only reviews with this rating or higher will be imported.</p>
                                </td>
                            </tr>

                            <tr class="sp-gr-row">
                                <th scope="row"><label for="sp-limit">Import Limit</label></th>
                                <td>
                                    <input id="sp-limit" type="number" class="small-text"
                                           min="1" max="200"
                                           name="<?php echo esc_attr( self::OPT_KEY ); ?>[limit]"
                                           value="<?php echo esc_attr( (string) $opt['limit'] ); ?>" />
                                    <p class="description">Every ~10 reviews = 1 query to SerpAPI. Fetching 30 reviews requires 3 queries.</p>
                                </td>
                            </tr>

                            <tr class="sp-gr-row">
                                <th scope="row">Overwrite Existing Reviews</th>
                                <td>
                                    <label>
                                        <input type="checkbox"
                                               name="<?php echo esc_attr( self::OPT_KEY ); ?>[overwrite]"
                                               value="1" <?php checked( ! empty( $opt['overwrite'] ) ); ?> />
                                        Update text, rating, and avatar of already imported reviews during sync.
                                    </label>
                                </td>
                            </tr>

                        </table>
                    </section>

                    <section class="sp-gr-card sp-admin-card">
                        <div class="sp-gr-card-head sp-admin-card__header"><h2>Widget Overrides</h2></div>
                        <table class="form-table sp-gr-form-table">
                            <tr class="sp-gr-row">
                                <th scope="row"><label for="sp-fallback-rating">Average Rating Override</label></th>
                                <td>
                                    <input id="sp-fallback-rating" type="text" class="small-text"
                                           name="<?php echo esc_attr( self::OPT_KEY ); ?>[fallback_rating]"
                                           value="<?php echo esc_attr( $opt['fallback_rating'] ); ?>"
                                           placeholder="(leave blank to use actual)" />
                                    <p class="description">If empty, the actual calculated average rating will be used.</p>
                                </td>
                            </tr>
                            <tr class="sp-gr-row">
                                <th scope="row"><label for="sp-fallback-count">Review Count Override</label></th>
                                <td>
                                    <input id="sp-fallback-count" type="text" class="small-text"
                                           name="<?php echo esc_attr( self::OPT_KEY ); ?>[fallback_count]"
                                           value="<?php echo esc_attr( $opt['fallback_count'] ); ?>"
                                           placeholder="(leave blank to use actual)" />
                                    <p class="description">If empty, the actual count of imported reviews will be used.</p>
                                </td>
                            </tr>
                            <tr class="sp-gr-row">
                                <th scope="row">Frontend styles</th>
                                <td>
                                    <label>
                                        <input type="checkbox"
                                               name="<?php echo esc_attr( self::OPT_KEY ); ?>[load_widget_styles]"
                                               value="1" <?php checked( ! empty( $opt['load_widget_styles'] ) ); ?> />
                                        Load the plugin's widget CSS.
                                    </label>
                                    <p class="description">Disable this to keep only semantic widget markup and style <code>.sp-gr-widget</code> in your theme.</p>
                                </td>
                            </tr>
                        </table>
                    </section>

                    <div class="sp-gr-submit-row">
                        <div class="sp-gr-readiness">
 						<span id="sp-gr-place-badge" class="sp-gr-inline-badge <?php echo $place_id_set ? 'is-ok' : 'is-warn'; ?>">
							Place ID: <?php echo $place_id_set ? 'set' : 'missing'; ?>
						</span>
                            <span id="sp-gr-api-badge" class="sp-gr-inline-badge <?php echo $api_key_set ? 'is-ok' : 'is-warn'; ?>">
							SerpAPI key: <?php echo $api_key_set ? 'set' : 'missing'; ?>
						</span>
                        </div>
                    </div>
                </form>
            </div>
            <?php
        }

        public static function render_admin_tabs( string $active_tab ): void {
            $tabs = [
                    'sync'    => [ 'label' => 'Synchronization', 'icon' => 'dashicons-update' ],
                    'widgets' => [ 'label' => 'Widget Builder', 'icon' => 'dashicons-layout' ],
            ];
            ?>
            <nav class="nav-tab-wrapper sp-gr-tabs" aria-label="Google Reviews sections">
                <?php foreach ( $tabs as $tab => $item ) :
                    $url = add_query_arg( [ 'page' => 'sp-google-reviews', 'tab' => $tab ], admin_url( 'options-general.php' ) );
                    ?>
                    <a class="nav-tab <?php echo $active_tab === $tab ? 'nav-tab-active' : ''; ?>"
                       href="<?php echo esc_url( $url ); ?>" <?php echo $active_tab === $tab ? 'aria-current="page"' : ''; ?>>
                        <span class="dashicons <?php echo esc_attr( $item['icon'] ); ?>" aria-hidden="true"></span>
                        <span><?php echo esc_html( $item['label'] ); ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
            <?php
        }

        // -------------------------------------------------------------------------
        // Admin CSS
        // -------------------------------------------------------------------------

        private static function admin_css(): string {
            return <<<'CSS'
.sp-gr-admin-wrap { max-width: 1180px; color: var(--sp-admin-text, #1a1f24); }
.sp-gr-admin-wrap .description { color: var(--sp-admin-muted, #525b66); }
.sp-gr-card { background:var(--sp-admin-surface, #fff); border:1px solid var(--sp-admin-border, #e7eaee); border-radius:var(--sp-admin-radius, 0); padding:16px; margin-bottom:14px; box-shadow:var(--sp-admin-shadow-xs, 0 1px 2px rgb(26 31 36 / 4%)); }
.sp-gr-card-head { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:12px; }
.sp-gr-card-head h2 { margin:0; color:var(--sp-admin-text, #1a1f24); font-size:17px; line-height:1.35; }
.sp-gr-metrics { display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:10px; margin-bottom:14px; }
.sp-gr-metric { border:1px solid var(--sp-admin-border, #e7eaee); border-radius:var(--sp-admin-radius-sm, 0); background:var(--sp-admin-surface-alt, #f8fafc); padding:10px; }
.sp-gr-metric__label { display:block; margin-bottom:4px; color:var(--sp-admin-muted, #525b66); font-size:12px; }
.sp-gr-metric__value { display:block; color:var(--sp-admin-text, #1a1f24); font-size:16px; font-weight:600; }
.sp-gr-badge { display:inline-flex; align-items:center; justify-content:center; min-width:62px; height:28px; padding:0 10px; border-radius:var(--sp-admin-radius-pill, 0); font-weight:600; font-size:12px; border:1px solid transparent; }
.sp-gr-badge.is-ok  { color:color-mix(in srgb, var(--sp-admin-success, #27ae60) 68%, var(--sp-admin-text, #1a1f24)); background:color-mix(in srgb, var(--sp-admin-success, #27ae60) 10%, var(--sp-admin-surface, #fff)); border-color:color-mix(in srgb, var(--sp-admin-success, #27ae60) 30%, var(--sp-admin-border, #e7eaee)); }
.sp-gr-badge.is-warn { color:color-mix(in srgb, var(--sp-admin-warning, #f2994a) 64%, var(--sp-admin-text, #1a1f24)); background:color-mix(in srgb, var(--sp-admin-warning, #f2994a) 11%, var(--sp-admin-surface, #fff)); border-color:color-mix(in srgb, var(--sp-admin-warning, #f2994a) 32%, var(--sp-admin-border, #e7eaee)); }
.sp-gr-actions { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:8px; }
.sp-gr-actions .button.is-disabled { opacity:.55; pointer-events:none; }
.sp-gr-copy-status { min-height:20px; margin:4px 0 0; font-size:12px; }
.sp-gr-copy-status.is-ok  { color:color-mix(in srgb, var(--sp-admin-success, #27ae60) 72%, var(--sp-admin-text, #1a1f24)); }
.sp-gr-copy-status.is-err { color:color-mix(in srgb, var(--sp-admin-danger, #e74c3c) 72%, var(--sp-admin-text, #1a1f24)); }
.sp-gr-settings-form .sp-gr-form-table { margin-top:0; }
.sp-gr-settings-form .sp-gr-form-table th { width:260px; color:var(--sp-admin-text-2, #525b66); }
.sp-gr-settings-form .sp-gr-form-table td { color:var(--sp-admin-text, #1a1f24); }
.sp-gr-settings-form .regular-text { max-width:560px; width:100%; }
.sp-gr-settings-form .small-text { min-width:110px; }
.sp-gr-submit-row { display:flex; flex-wrap:wrap; align-items:center; gap:10px 12px; }
.sp-gr-readiness { display:flex; flex-wrap:wrap; gap:8px; }
.sp-gr-inline-badge { display:inline-flex; align-items:center; justify-content:center; height:26px; padding:0 10px; border-radius:var(--sp-admin-radius-pill, 0); font-size:12px; font-weight:600; border:1px solid transparent; }
.sp-gr-inline-badge.is-ok  { color:color-mix(in srgb, var(--sp-admin-success, #27ae60) 68%, var(--sp-admin-text, #1a1f24)); background:color-mix(in srgb, var(--sp-admin-success, #27ae60) 10%, var(--sp-admin-surface, #fff)); border-color:color-mix(in srgb, var(--sp-admin-success, #27ae60) 30%, var(--sp-admin-border, #e7eaee)); }
.sp-gr-inline-badge.is-warn { color:color-mix(in srgb, var(--sp-admin-warning, #f2994a) 64%, var(--sp-admin-text, #1a1f24)); background:color-mix(in srgb, var(--sp-admin-warning, #f2994a) 11%, var(--sp-admin-surface, #fff)); border-color:color-mix(in srgb, var(--sp-admin-warning, #f2994a) 32%, var(--sp-admin-border, #e7eaee)); }

.sp-gr-progress-card {
  background: var(--sp-admin-surface, #fff);
  border: 1px solid var(--sp-admin-border, #e7eaee);
  border-radius: var(--sp-admin-radius, 0);
  padding: 16px;
  margin-bottom: 14px;
  box-shadow: var(--sp-admin-shadow-xs, 0 1px 2px rgb(26 31 36 / 4%));
}
.sp-gr-progress-header {
  display: flex;
  justify-content: space-between;
  font-weight: 600;
  font-size: 14px;
  color: var(--sp-admin-text, #1a1f24);
  margin-bottom: 8px;
}
.sp-gr-progress-bar-bg {
  width: 100%;
  height: 8px;
  background: var(--sp-admin-surface-alt, #f8fafc);
  box-shadow: inset 0 0 0 1px var(--sp-admin-border, #e7eaee);
  border-radius: var(--sp-admin-radius-pill, 0);
  overflow: hidden;
  margin-bottom: 10px;
}
.sp-gr-progress-bar-fill {
  width: 0%;
  height: 100%;
  background: linear-gradient(90deg, var(--sp-admin-accent, #3858e9) 0%, var(--sp-admin-accent-bright, #487de4) 100%);
  border-radius: var(--sp-admin-radius-pill, 0);
  transition: width var(--sp-admin-transition, 160ms cubic-bezier(.2, .8, .2, 1));
}

@media (max-width:1200px) { .sp-gr-metrics { grid-template-columns:repeat(3,minmax(0,1fr)); } }
@media (max-width:782px)  { .sp-gr-metrics { grid-template-columns:repeat(2,minmax(0,1fr)); } .sp-gr-settings-form .sp-gr-form-table th { width:auto; } }
CSS;
        }

        // -------------------------------------------------------------------------
        // Admin JS
        // -------------------------------------------------------------------------

        private static function admin_js(): string {
            return <<<'JS'
jQuery(function ($) {

	function setBadge($el, ok, okText, warnText) {
		if (!$el.length) return;
		$el.removeClass('is-ok is-warn').addClass(ok ? 'is-ok' : 'is-warn').text(ok ? okText : warnText);
	}

	function updateReadiness() {
		var placeSet = $.trim($('#sp-place-id').val()) !== '';
		var apiSet   = $.trim($('#sp-api-key').val()) !== '';
		var ready    = placeSet && apiSet;

		setBadge($('#sp-gr-place-badge'), placeSet, 'Place ID: set',      'Place ID: missing');
		setBadge($('#sp-gr-api-badge'),   apiSet,   'SerpAPI key: set',   'SerpAPI key: missing');
		setBadge($('#sp-gr-ready-badge'), ready,    'Yes',                'No');

		var $sync = $('#sp-gr-sync-btn');
		$sync.toggleClass('is-disabled', !ready).attr('aria-disabled', ready ? 'false' : 'true');
		$sync.attr('title', ready ? 'Sync reviews now' : 'Fill required fields first');
	}

	$(document).on('input change', '#sp-place-id, #sp-api-key', updateReadiness);
	updateReadiness();

	$(document).on('click', '#sp-gr-sync-btn', function (e) {
		e.preventDefault();
		if ($(this).hasClass('is-disabled') || $(this).attr('aria-disabled') === 'true') {
			return;
		}

		var $btn = $(this);
		$btn.addClass('is-disabled').attr('aria-disabled', 'true');
		
		var $progress = $('#sp-gr-sync-progress');
		var $fill = $('#sp-gr-progress-bar-fill');
		var $percent = $('#sp-gr-progress-percent');
		
		$('.notice-success, .notice-error').remove();
		$progress.slideDown();
		$fill.css('width', '0%');
		$percent.text('0%');

		var currentWidth = 0;
		var interval = setInterval(function () {
			if (currentWidth < 90) {
				currentWidth += Math.random() * 15;
				currentWidth = Math.min(currentWidth, 90);
				$fill.css('width', currentWidth + '%');
				$percent.text(Math.round(currentWidth) + '%');
			}
		}, 800);

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'sp_reviews_import',
				nonce: $('#sp-gr-sync-nonce').val()
			},
			success: function (res) {
				clearInterval(interval);
				if (res.success) {
					$fill.css('width', '100%');
					$percent.text('100%');
					
					setTimeout(function () {
						$progress.slideUp();
						$btn.removeClass('is-disabled').attr('aria-disabled', 'false');
						
						var $notice = $('<div class="notice notice-success is-dismissible"><p>' + res.data.message + '</p></div>');
						$('.sp-gr-admin-wrap h1').after($notice);
						
						$('.sp-gr-metric').each(function () {
							var label = $(this).find('.sp-gr-metric__label').text().trim();
							if (label === 'Average rating') {
								$(this).find('.sp-gr-metric__value').text(res.data.rating);
							} else if (label === 'Reviews in DB') {
								$(this).find('.sp-gr-metric__value').text(res.data.count);
							} else if (label === 'Last sync') {
								$(this).find('.sp-gr-metric__value').text(res.data.last);
							}
						});
					}, 1000);
				} else {
					clearInterval(interval);
					$progress.slideUp();
					$btn.removeClass('is-disabled').attr('aria-disabled', 'false');
					var $notice = $('<div class="notice notice-error is-dismissible"><p>' + (res.data ? res.data.message : 'Sync failed.') + '</p></div>');
					$('.sp-gr-admin-wrap h1').after($notice);
				}
			},
			error: function () {
				clearInterval(interval);
				$progress.slideUp();
				$btn.removeClass('is-disabled').attr('aria-disabled', 'false');
				var $notice = $('<div class="notice notice-error is-dismissible"><p>Server error during sync.</p></div>');
				$('.sp-gr-admin-wrap h1').after($notice);
			}
		});
	});

	$(document).on('click', '.sp-gr-copy-btn', function (e) {
		e.preventDefault();
		var text = $.trim($(this).data('copy') || '');
		if (!text) return;

		var done = function (ok) {
			var $s = $('#sp-gr-copy-status').removeClass('is-ok is-err');
			$s.addClass(ok ? 'is-ok' : 'is-err').text(ok ? 'Shortcode copied' : 'Failed to copy');
		};

		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).then(function(){ done(true); }, function(){ done(false); });
		} else {
			var $t = $('<textarea readonly>').css({position:'absolute',left:'-9999px'}).val(text);
			$('body').append($t); $t[0].select();
			done(!!document.execCommand('copy'));
			$t.remove();
		}
	});
});
JS;
        }

        // -------------------------------------------------------------------------
        // Import notices + list button
        // -------------------------------------------------------------------------

        public static function render_import_notice(): void {
            if ( ! is_admin() || ! current_user_can( 'manage_options' ) || ! isset( $_GET['sp_reviews_import'] ) ) {
                return;
            }

            $ok  = ( (string) $_GET['sp_reviews_import'] === '1' );
            $msg = '';
            if ( isset( $_GET['sp_import_msg'] ) ) {
                $msg = sanitize_text_field( rawurldecode( wp_unslash( (string) $_GET['sp_import_msg'] ) ) );
            }
            if ( $msg === '' ) {
                $msg = $ok ? 'Reviews synced successfully.' : 'Reviews sync failed.';
            }

            $class = $ok ? 'notice notice-success is-dismissible' : 'notice notice-error is-dismissible';
            echo '<div class="' . esc_attr( $class ) . '"><p>' . esc_html( $msg ) . '</p></div>';
        }

        public static function add_list_button(): void {
            global $typenow;
            if ( $typenow !== 'review' || ! current_user_can( 'manage_options' ) ) {
                return;
            }
            if ( ! self::can_import_now( self::get_options() ) ) {
                return;
            }
            $url = wp_nonce_url( admin_url( 'admin-post.php?action=sp_reviews_import&redirect=edit' ), 'sp_reviews_import' );
            echo '<a href="' . esc_url( $url ) . '" class="button button-primary" style="margin-right:6px;">Sync Reviews</a>';
        }

        // -------------------------------------------------------------------------
        // Import handler
        // -------------------------------------------------------------------------

        public static function handle_import(): void {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( 'Forbidden', 'Forbidden', [ 'response' => 403 ] );
            }

            check_admin_referer( 'sp_reviews_import' );

            $opt = self::get_options();
            if ( ! self::can_import_now( $opt ) ) {
                wp_safe_redirect( wp_get_referer() ?: admin_url() );
                exit;
            }

            $result   = self::fetch_and_upsert( $opt );
            $redirect = add_query_arg( [
                    'sp_reviews_import' => $result['success'] ? '1' : '0',
                    'sp_import_msg'     => $result['message'],
            ], wp_get_referer() ?: admin_url( 'edit.php?post_type=review' ) );

            wp_safe_redirect( $redirect );
            exit;
        }

        // -------------------------------------------------------------------------
        // AJAX import handler
        // -------------------------------------------------------------------------

        public static function ajax_import_handler(): void {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_send_json_error( [ 'message' => 'Forbidden' ] );
            }

            check_ajax_referer( 'sp_reviews_import_nonce', 'nonce' );

            $opt = self::get_options();
            if ( ! self::can_import_now( $opt ) ) {
                wp_send_json_error( [ 'message' => 'API Key and Place ID are required.' ] );
            }

            $result = self::fetch_and_upsert( $opt );

            if ( ! empty( $result['success'] ) ) {
                $stats_rating = (float) str_replace( ',', '.', (string) get_option( self::STATS_OPT_RATING, '0.0' ) );
                $stats_count  = max( 0, (int) get_option( self::STATS_OPT_COUNT, 0 ) );
                $last         = get_option( self::STATS_OPT_LAST, '' );
                $last_label   = $last !== '' ? mysql2date( 'Y-m-d H:i', $last ) : 'Never';

                wp_send_json_success( [
                    'message' => $result['message'],
                    'rating'  => number_format( $stats_rating, 1, '.', '' ),
                    'count'   => number_format_i18n( $stats_count ),
                    'last'    => $last_label,
                ] );
            } else {
                wp_send_json_error( [ 'message' => $result['message'] ] );
            }
        }

        private static function normalize_result( array $result, string $fallback = '' ): array {
            $ok  = ! empty( $result['success'] );
            $msg = trim( (string) ( $result['message'] ?? $result['error'] ?? '' ) );
            if ( $msg === '' ) {
                $msg = $fallback ?: ( $ok ? 'Import completed.' : 'Import failed.' );
            }
            return array_merge( $result, [ 'success' => $ok, 'message' => $msg ] );
        }

        // -------------------------------------------------------------------------
        // Fetch + upsert
        // -------------------------------------------------------------------------

        private static function fetch_and_upsert( array $opt ): array {
            $imported = $updated = $skipped = 0;
            $languages = self::get_import_languages( $opt );
            $translations = [];

            foreach ( $languages as $content_language => $provider_language ) {
                $language_opt = $opt;
                $language_opt['language']         = $provider_language;
                $language_opt['content_language'] = $content_language;
                $data = self::fetch_serpapi_reviews( $language_opt );

                if ( empty( $data['success'] ) ) {
                    return self::normalize_result(
                            $data,
                            sprintf( 'Failed to fetch %s reviews from SerpAPI.', $content_language )
                    );
                }

                foreach ( (array) ( $data['reviews'] ?? [] ) as $item ) {
                    if ( ! is_array( $item ) ) { $skipped++; continue; }

                    $source_id = (string) ( $item['source_id'] ?? '' );
                    $ext_id    = (string) ( $item['external_id'] ?? '' );
                    $legacy_id = (string) ( $item['legacy_external_id'] ?? '' );
                    if ( $source_id === '' || $ext_id === '' ) { $skipped++; continue; }

                    $post_id = self::find_existing_review_id(
                            self::PROVIDER,
                            $source_id,
                            $content_language,
                            $legacy_id
                    );

                    if ( $post_id > 0 ) {
                        $translations[ $source_id ][ $content_language ] = $post_id;
                        if ( empty( $opt['overwrite'] ) ) {
                            update_post_meta( $post_id, self::META_EXTERNAL_ID, $ext_id );
                            update_post_meta( $post_id, self::META_SOURCE_ID, $source_id );
                            update_post_meta( $post_id, self::META_LANGUAGE, $content_language );
                            self::assign_post_language( $post_id, $content_language );
                            $skipped++;
                            continue;
                        }
                        $ok = self::update_review_post( $post_id, self::PROVIDER, $ext_id, $language_opt, $item );
                        $ok ? $updated++ : $skipped++;
                        continue;
                    }

                    $new_id = self::insert_review_post( self::PROVIDER, $ext_id, $language_opt, $item );
                    if ( $new_id > 0 ) {
                        $translations[ $source_id ][ $content_language ] = $new_id;
                        $imported++;
                    } else {
                        $skipped++;
                    }
                }
            }

            foreach ( $translations as $posts_by_language ) {
                self::connect_post_translations( $posts_by_language );
            }

            self::update_stats_from_posts( array_keys( $languages ) );
            update_option( self::STATS_OPT_LAST, current_time( 'mysql' ) );

            return self::normalize_result( [
                    'success' => true,
                    'message' => sprintf(
                            'Import completed for %d language(s). Imported: %d, Updated: %d, Skipped: %d',
                            count( $languages ),
                            $imported,
                            $updated,
                            $skipped
                    ),
            ] );
        }

        // -------------------------------------------------------------------------
        // SerpAPI fetcher
        // -------------------------------------------------------------------------

        private static function fetch_serpapi_reviews( array $opt ): array {
            $api_key    = (string) $opt['api_key'];
            $place_id   = (string) $opt['place_id'];
            $limit      = (int) $opt['limit'];
            $min_rating = (int) $opt['min_rating'];
            $language   = (string) ( $opt['language'] ?: 'en' );

            $max_pages = max( 1, (int) ceil( $limit / 10 ) );

            $out             = [];
            $seen            = [];
            $next_page_token = null;
            $place_info      = [];

            for ( $page = 0; $page < $max_pages; $page++ ) {
                $args = [
                        'engine'   => 'google_maps_reviews',
                        'place_id' => $place_id,
                        'api_key'  => $api_key,
                        'hl'       => $language,
                        'sort_by'  => 'newestFirst',
                ];

                if ( $next_page_token !== null ) {
                    $args['next_page_token'] = $next_page_token;
                }

                $resp = wp_remote_get(
                        'https://serpapi.com/search.json?' . http_build_query( $args ),
                        [ 'timeout' => 25, 'headers' => [ 'Accept' => 'application/json' ] ]
                );

                if ( is_wp_error( $resp ) ) {
                    if ( empty( $out ) ) {
                        return [ 'success' => false, 'error' => $resp->get_error_message() ];
                    }
                    break;
                }

                $code = (int) wp_remote_retrieve_response_code( $resp );
                $body = json_decode( wp_remote_retrieve_body( $resp ), true );

                if ( $code < 200 || $code >= 300 ) {
                    $err = isset( $body['error'] ) ? (string) $body['error'] : 'HTTP ' . $code;
                    if ( empty( $out ) ) {
                        return [ 'success' => false, 'error' => $err ];
                    }
                    break;
                }

                if ( $place_info === [] && ! empty( $body['place_info'] ) ) {
                    $pi = $body['place_info'];
                    $place_info = [
                            'rating' => isset( $pi['rating'] ) ? (float) $pi['rating'] : 0.0,
                            'count'  => isset( $pi['reviews'] ) ? (int) $pi['reviews'] : 0,
                    ];
                }

                $reviews = isset( $body['reviews'] ) && is_array( $body['reviews'] ) ? $body['reviews'] : [];
                if ( empty( $reviews ) ) {
                    break;
                }

                $added = 0;
                foreach ( $reviews as $r ) {
                    $rating = isset( $r['rating'] ) ? (int) $r['rating'] : 0;
                    if ( $rating < $min_rating ) {
                        continue;
                    }

                    $user_link = (string) ( $r['user']['link'] ?? '' );
                    $iso_date  = (string) ( $r['iso_date'] ?? $r['date'] ?? '' );
                    $snippet   = (string) ( $r['snippet'] ?? '' );
                    $legacy_id = md5( $user_link . '|' . $iso_date . '|' . $snippet );
                    $source_id = sanitize_text_field( (string) ( $r['review_id'] ?? '' ) );
                    $source_id = $source_id !== '' ? $source_id : $legacy_id;

                    if ( isset( $seen[ $source_id ] ) ) {
                        continue;
                    }
                    $seen[ $source_id ] = true;

                    $ts = 0;
                    if ( ! empty( $r['iso_date'] ) ) {
                        $dt = date_create( $r['iso_date'] );
                        if ( $dt ) {
                            $ts = $dt->getTimestamp();
                        }
                    }

                    $translated_snippet = trim( (string) ( $r['translated_snippet'] ?? '' ) );
                    $out[] = [
                            'external_id'        => $source_id,
                            'legacy_external_id' => $legacy_id,
                            'source_id'           => $source_id,
                            'author'      => (string) ( $r['user']['name'] ?? 'Anonymous' ),
                            'text'        => $translated_snippet !== '' ? $translated_snippet : $snippet,
                            'rating'      => $rating,
                            'timestamp'   => $ts,
                            'avatar_url'  => (string) ( $r['user']['thumbnail'] ?? '' ),
                            'image_urls'  => self::normalize_review_image_urls( $r['images'] ?? [] ),
                            'url'         => (string) ( $r['link'] ?? $user_link ),
                     ];
                    $added++;

                    if ( count( $out ) >= $limit ) {
                        break 2;
                    }
                }

                $next_page_token = $body['serpapi_pagination']['next_page_token'] ?? null;
                if ( $next_page_token === null || $added === 0 ) {
                    break;
                }
            }

            return [ 'success' => true, 'message' => '', 'reviews' => $out, 'place_info' => $place_info ];
        }

        // -------------------------------------------------------------------------
        // DB helpers
        // -------------------------------------------------------------------------

        private static function find_existing_review_id( string $provider, string $source_id, string $language, string $legacy_id = '' ): int {
            $base_args = [
                    'post_type'      => 'review',
                    'post_status'    => [ 'publish', 'future', 'draft', 'pending', 'private' ],
                    'posts_per_page' => 1,
                    'fields'         => 'ids',
                    'suppress_filters' => true,
            ];
            $q = new WP_Query( array_merge( $base_args, [
                    'meta_query'     => [
                            [ 'key' => self::META_PROVIDER,    'value' => $provider ],
                            [ 'key' => self::META_SOURCE_ID,   'value' => $source_id ],
                            [ 'key' => self::META_LANGUAGE,    'value' => $language ],
                    ],
            ] ) );

            if ( ! empty( $q->posts ) ) {
                return (int) $q->posts[0];
            }

            $unassigned = new WP_Query( array_merge( $base_args, [
                    'meta_query' => [
                            [ 'key' => self::META_PROVIDER,  'value' => $provider ],
                            [ 'key' => self::META_SOURCE_ID, 'value' => $source_id ],
                            [ 'key' => self::META_LANGUAGE,  'compare' => 'NOT EXISTS' ],
                    ],
            ] ) );
            if ( ! empty( $unassigned->posts ) ) {
                return (int) $unassigned->posts[0];
            }

            if ( $legacy_id === '' ) {
                return 0;
            }

            $legacy = new WP_Query( array_merge( $base_args, [
                    'meta_query' => [
                            [ 'key' => self::META_PROVIDER,    'value' => $provider ],
                            [ 'key' => self::META_EXTERNAL_ID, 'value' => $legacy_id ],
                            [ 'key' => self::META_LANGUAGE,    'compare' => 'NOT EXISTS' ],
                    ],
            ] ) );

            return ! empty( $legacy->posts ) ? (int) $legacy->posts[0] : 0;
        }

        private static function insert_review_post( string $provider, string $external_id, array $opt, array $item ): int {
            $author = (string) ( $item['author'] ?? 'Anonymous' );
            $text   = (string) ( $item['text'] ?? '' );
            $rating = (int) ( $item['rating'] ?? 0 );
            $ts     = (int) ( $item['timestamp'] ?? 0 );
            $date   = $ts > 0 ? gmdate( 'Y-m-d H:i:s', $ts ) : current_time( 'mysql' );

            $post_id = wp_insert_post( [
                    'post_type'    => 'review',
                    'post_status'  => 'publish',
                    'post_title'   => wp_strip_all_tags( $author ),
                    'post_content' => wp_kses_post( $text ),
                    'post_date'    => get_date_from_gmt( $date ),
            ], true );

            if ( is_wp_error( $post_id ) || ! $post_id ) {
                return 0;
            }

            self::apply_review_meta( (int) $post_id, $provider, $external_id, $opt, $item, $rating );
            self::assign_post_language( (int) $post_id, (string) ( $opt['content_language'] ?? $opt['language'] ?? 'en' ) );

            return (int) $post_id;
        }

        private static function update_review_post( int $post_id, string $provider, string $external_id, array $opt, array $item ): bool {
            $author = (string) ( $item['author'] ?? 'Anonymous' );
            $text   = (string) ( $item['text'] ?? '' );
            $rating = (int) ( $item['rating'] ?? 0 );
            $ts     = (int) ( $item['timestamp'] ?? 0 );
            $date   = $ts > 0 ? gmdate( 'Y-m-d H:i:s', $ts ) : current_time( 'mysql' );

            $res = wp_update_post( [
                    'ID'           => $post_id,
                    'post_title'   => wp_strip_all_tags( $author ),
                    'post_content' => wp_kses_post( $text ),
                    'post_date'    => get_date_from_gmt( $date ),
            ], true );

            if ( is_wp_error( $res ) ) {
                return false;
            }

            self::apply_review_meta( $post_id, $provider, $external_id, $opt, $item, $rating );
            self::assign_post_language( $post_id, (string) ( $opt['content_language'] ?? $opt['language'] ?? 'en' ) );

            return true;
        }

        private static function apply_review_meta( int $post_id, string $provider, string $external_id, array $opt, array $item, int $rating ): void {
            update_post_meta( $post_id, self::META_PROVIDER,    $provider );
            update_post_meta( $post_id, self::META_EXTERNAL_ID, $external_id );
            update_post_meta( $post_id, self::META_PLACE_ID,    (string) ( $opt['place_id'] ?? '' ) );
            update_post_meta( $post_id, self::META_SOURCE_ID,   (string) ( $item['source_id'] ?? $external_id ) );
            update_post_meta( $post_id, self::META_LANGUAGE,    (string) ( $opt['content_language'] ?? $opt['language'] ?? 'en' ) );

            if ( ! empty( $item['url'] ) ) {
                update_post_meta( $post_id, self::META_REVIEW_URL, (string) $item['url'] );
            }

            $val = (string) max( 1, min( 5, $rating ) );
            update_post_meta( $post_id, 'stars', $val );
            if ( function_exists( 'update_field' ) ) {
                update_field( 'stars', $val, $post_id );
            }

            if ( ! empty( $item['avatar_url'] ) ) {
                self::set_thumbnail_from_url( $post_id, (string) $item['avatar_url'], ! empty( $opt['overwrite'] ) );
            }

            if ( ! empty( $item['image_urls'] ) && is_array( $item['image_urls'] ) ) {
                self::set_review_images_from_urls( $post_id, $item['image_urls'], ! empty( $opt['overwrite'] ) );
            }
        }

        // -------------------------------------------------------------------------
        // Review media sideload
        // -------------------------------------------------------------------------

        private static function normalize_review_image_urls( $images ): array {
            if ( ! is_array( $images ) ) {
                return [];
            }

            $urls = [];
            foreach ( $images as $image ) {
                $url = is_string( $image )
                        ? $image
                        : ( is_array( $image ) ? (string) ( $image['image'] ?? $image['thumbnail'] ?? '' ) : '' );
                $url = trim( $url );
                if ( $url !== '' && self::is_safe_remote_url( $url ) ) {
                    $urls[ $url ] = $url;
                }
            }

            return array_values( $urls );
        }

        private static function set_thumbnail_from_url( int $post_id, string $url, bool $overwrite ): void {
            $url = trim( $url );
            if ( $url === '' || ! self::is_safe_remote_url( $url ) ) {
                return;
            }
            if ( has_post_thumbnail( $post_id ) && ! $overwrite ) {
                return;
            }

            $old_thumbnail_id = get_post_thumbnail_id( $post_id );
            $attachment_id = self::sideload_review_media( $post_id, $url, 'review-avatar-' . $post_id . '.jpg' );
            if ( $attachment_id <= 0 ) {
                return;
            }

            set_post_thumbnail( $post_id, $attachment_id );
            if ( $overwrite && $old_thumbnail_id && $old_thumbnail_id !== $attachment_id ) {
                self::delete_owned_attachment( $post_id, (int) $old_thumbnail_id );
            }
            self::delete_review_avatars_cache();
        }

        private static function set_review_images_from_urls( int $post_id, array $urls, bool $overwrite ): void {
            $existing_ids = array_values( array_filter( array_map(
                    'absint',
                    (array) get_post_meta( $post_id, self::META_IMAGES, true )
            ) ) );
            if ( $existing_ids !== [] && ! $overwrite ) {
                return;
            }

            $new_ids = [];
            foreach ( array_values( array_unique( $urls ) ) as $index => $url ) {
                $attachment_id = self::sideload_review_media(
                        $post_id,
                        (string) $url,
                        sprintf( 'review-image-%d-%d.jpg', $post_id, $index + 1 )
                );
                if ( $attachment_id > 0 ) {
                    $new_ids[] = $attachment_id;
                }
            }

            // A temporary network failure must not destroy an existing gallery.
            if ( $new_ids === [] ) {
                return;
            }

            update_post_meta( $post_id, self::META_IMAGES, $new_ids );
            foreach ( array_diff( $existing_ids, $new_ids ) as $old_id ) {
                self::delete_owned_attachment( $post_id, (int) $old_id );
            }
            self::delete_review_avatars_cache();
        }

        private static function sideload_review_media( int $post_id, string $url, string $filename ): int {
            $url = trim( $url );
            if ( $url === '' || ! self::is_safe_remote_url( $url ) ) {
                return 0;
            }

            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';

            $tmp = download_url( $url );
            if ( is_wp_error( $tmp ) ) {
                return 0;
            }

            $attachment_id = media_handle_sideload( [
                    'name'     => sanitize_file_name( $filename ),
                    'tmp_name' => $tmp,
            ], $post_id );

            if ( is_wp_error( $attachment_id ) ) {
                @unlink( $tmp );
                return 0;
            }

            return (int) $attachment_id;
        }

        private static function delete_owned_attachment( int $post_id, int $attachment_id ): void {
            if ( $attachment_id > 0 && (int) wp_get_post_parent_id( $attachment_id ) === $post_id ) {
                wp_delete_attachment( $attachment_id, true );
            }
        }

        private static function is_safe_remote_url( string $url ): bool {
            if ( ! wp_http_validate_url( $url ) ) {
                return false;
            }
            $parts  = wp_parse_url( $url );
            $scheme = strtolower( (string) ( $parts['scheme'] ?? '' ) );
            if ( ! in_array( $scheme, [ 'http', 'https' ], true ) ) {
                return false;
            }
            $host = strtolower( (string) ( $parts['host'] ?? '' ) );
            if ( $host === '' || in_array( $host, [ 'localhost', '127.0.0.1', '::1' ], true ) ) {
                return false;
            }
            if ( filter_var( $host, FILTER_VALIDATE_IP ) &&
                 ! filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
                return false;
            }
            return true;
        }

        // -------------------------------------------------------------------------
        // Stats
        // -------------------------------------------------------------------------

        private static function update_stats_from_posts( array $languages ): void {
            $stats = [];

            foreach ( $languages as $language ) {
                $q = new WP_Query( [
                    'post_type'      => 'review',
                    'post_status'    => 'publish',
                    'posts_per_page' => -1,
                    'fields'         => 'ids',
                    'suppress_filters' => true,
                    'meta_query'     => [
                            [ 'key' => self::META_LANGUAGE, 'value' => (string) $language ],
                    ],
                ] );

                $ids   = is_array( $q->posts ) ? $q->posts : [];
                $count = count( $ids );
                $sum   = array_reduce( $ids, fn( $carry, $id ) => $carry + self::get_stars_value( (int) $id ), 0.0 );
                $stats[ (string) $language ] = [
                        'count'  => $count,
                        'rating' => $count > 0 ? number_format( $sum / $count, 1, '.', '' ) : '0.0',
                ];
            }

            update_option( self::STATS_OPT_BY_LANG, $stats );

            $primary = $stats !== [] ? reset( $stats ) : [ 'count' => 0, 'rating' => '0.0' ];
            update_option( self::STATS_OPT_COUNT,  (int) $primary['count'] );
            update_option( self::STATS_OPT_RATING, (string) $primary['rating'] );
        }

        private static function get_current_content_language( array $opt ): string {
            if ( function_exists( 'pll_current_language' ) ) {
                $language = pll_current_language( 'slug' );
                if ( is_string( $language ) && $language !== '' ) {
                    return sanitize_key( $language );
                }
            }

            $language = apply_filters( 'wpml_current_language', null );
            if ( is_string( $language ) && $language !== '' ) {
                return sanitize_key( $language );
            }

            return self::normalize_provider_language( (string) ( $opt['language'] ?? 'en' ) );
        }

        // -------------------------------------------------------------------------
        // Shortcode / Widget
        // -------------------------------------------------------------------------

        public static function enqueue_frontend_assets(): void {
            $enabled = ! empty( self::get_options()['load_widget_styles'] );
            if ( ! apply_filters( 'sp_google_reviews_load_widget_styles', $enabled ) ) {
                return;
            }
            wp_register_style( 'sp-google-reviews-widget', false );
            wp_enqueue_style( 'sp-google-reviews-widget' );
            wp_add_inline_style( 'sp-google-reviews-widget', SP_Google_Reviews_Widget_Builder::frontend_css() );
        }

        public static function shortcode_widget( $atts ): string {
            return SP_Google_Reviews_Widget_Builder::render_shortcode( $atts );
        }

        // -------------------------------------------------------------------------
        // Public helper (for theme/other plugins)
        // -------------------------------------------------------------------------

        public static function get_review_data( int $post_id ): ?array {
            $post_id = absint( $post_id );
            if ( $post_id <= 0 ) { return null; }

            $post = get_post( $post_id );
            if ( ! ( $post instanceof WP_Post ) || $post->post_type !== 'review' ) { return null; }

            $thumb = get_the_post_thumbnail_url( $post_id, 'thumbnail' );
            $image_ids = array_values( array_filter( array_map(
                    'absint',
                    (array) get_post_meta( $post_id, self::META_IMAGES, true )
            ) ) );
            $images = [];
            foreach ( $image_ids as $image_id ) {
                $image_url = wp_get_attachment_image_url( $image_id, 'full' );
                if ( is_string( $image_url ) && $image_url !== '' ) {
                    $images[] = $image_url;
                }
            }

            return [
                    'id'        => $post_id,
                    'name'      => get_the_title( $post_id ),
                    'content'   => apply_filters( 'the_content', $post->post_content ),
                    'raw'       => $post->post_content,
                    'stars'     => self::get_stars_value( $post_id ),
                    'date'      => get_the_date( 'Y-m-d H:i:s', $post_id ),
                    'timestamp' => get_post_time( 'U', true, $post_id ),
                    'thumb'     => is_string( $thumb ) ? $thumb : '',
                    'images'    => $images,
                    'image_ids' => $image_ids,
                    'url'       => (string) get_post_meta( $post_id, self::META_REVIEW_URL, true ),
                    'provider'  => (string) get_post_meta( $post_id, self::META_PROVIDER, true ),
                    'language'  => (string) get_post_meta( $post_id, self::META_LANGUAGE, true ),
            ];
        }

        private static function get_stars_value( int $post_id ): float {
            $val = function_exists( 'get_field' ) ? get_field( 'stars', $post_id ) : null;
            if ( $val === null || $val === '' || $val === false ) {
                $val = get_post_meta( $post_id, 'stars', true );
            }
            $val = is_string( $val ) ? str_replace( ',', '.', $val ) : $val;
            if ( ! is_numeric( $val ) ) { return 0.0; }
            return max( 0.0, min( 5.0, (float) $val ) );
        }

        // -------------------------------------------------------------------------
        // Avatar Management & Exclude Logic
        // -------------------------------------------------------------------------

        public static function delete_review_avatars_cache(): void {
            delete_transient( 'sp_review_avatar_ids' );
        }

        public static function get_review_avatar_ids(): array {
            static $ids = null;
            if ( $ids !== null ) {
                return $ids;
            }

            $ids = get_transient( 'sp_review_avatar_ids' );
            if ( ! is_array( $ids ) ) {
                global $wpdb;
                $ids = $wpdb->get_col( "
                    SELECT ID FROM {$wpdb->posts} 
                    WHERE post_type = 'attachment' 
                    AND (
                        post_parent IN (SELECT ID FROM {$wpdb->posts} WHERE post_type = 'review')
                        OR post_name LIKE 'review-avatar-%'
                        OR post_name LIKE 'review-image-%'
                        OR guid LIKE '%review-avatar-%'
                        OR guid LIKE '%review-image-%'
                    )
                " );
                $ids = array_map( 'intval', $ids );
                set_transient( 'sp_review_avatar_ids', $ids, HOUR_IN_SECONDS );
            }
            return $ids;
        }

        public static function exclude_avatars_from_media_library( array $args ): array {
            $exclude_ids = self::get_review_avatar_ids();
            if ( ! empty( $exclude_ids ) ) {
                if ( ! empty( $args['post__not_in'] ) ) {
                    $args['post__not_in'] = array_merge( (array) $args['post__not_in'], $exclude_ids );
                } else {
                    $args['post__not_in'] = $exclude_ids;
                }
            }
            return $args;
        }

        public static function exclude_avatars_from_media_list( WP_Query $query ): void {
            if ( ! is_admin() || ! $query->is_main_query() ) {
                return;
            }

            global $pagenow;
            if ( $pagenow === 'upload.php' || ( $query->get( 'post_type' ) === 'attachment' && ! $query->get( 'post_parent' ) ) ) {
                $exclude_ids = self::get_review_avatar_ids();
                if ( ! empty( $exclude_ids ) ) {
                    $not_in = $query->get( 'post__not_in' );
                    if ( ! empty( $not_in ) ) {
                        $not_in = array_merge( (array) $not_in, $exclude_ids );
                    } else {
                        $not_in = $exclude_ids;
                    }
                    $query->set( 'post__not_in', $not_in );
                }
            }
        }

        public static function delete_review_thumbnail( int $post_id ): void {
            if ( get_post_type( $post_id ) !== 'review' ) {
                return;
            }

            $thumbnail_id = get_post_thumbnail_id( $post_id );
            if ( $thumbnail_id ) {
                self::delete_owned_attachment( $post_id, (int) $thumbnail_id );
            }

            $image_ids = (array) get_post_meta( $post_id, self::META_IMAGES, true );
            foreach ( array_filter( array_map( 'absint', $image_ids ) ) as $image_id ) {
                self::delete_owned_attachment( $post_id, (int) $image_id );
            }

            self::delete_review_avatars_cache();
        }
    }

    SP_Reviews_Importer::init();
