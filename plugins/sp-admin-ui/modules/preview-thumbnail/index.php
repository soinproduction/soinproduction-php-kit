<?php

    /**
     * Универсальная колонка с ACF-изображением + AJAX-сохранение прямо из списка.
     *
     * @param string $type 'post' | 'term'
     * @param string $object слаг пост-типа или таксономии
     * @param string $acf_field name ACF image-поля
     * @param string $column_label заголовок колонки
     * @param string $after после какой колонки вставить ('' = auto: 'title' для постов, 'name' для терминов)
     */

//	add_action( 'after_setup_theme', function () {
//		register_acf_thumb_column(
//			type:         'term',
//			object:       'media_platform',
//			column_label: 'Icon',
//			after:        'name',
//			acf_field:    'icon',
//		);
//	} );
//
//	add_action( 'after_setup_theme', function () {
//		register_acf_thumb_column(
//			type:         'post',
//			object:       'linear_drains',
//			column_label: 'Finish',
//			after:        'title',
//			acf_field:    'thumb',
//		);
//	} );
//
//	add_action( 'after_setup_theme', function () {
//		register_acf_thumb_column(
//			type:         'post',
//			object:       'awards_media',
//			column_label: 'contain',
//			after:        'title',
//			acf_field:    '',       // пусто = WP thumbnail
//		);
//	} );


    function register_acf_thumb_column(
            string $type,
            string $object,
            string $column_label = 'Thumb',
            string $after = '',
            string $acf_field = '',
            string $size = '',
    ): void {

        // ── Парсим размер ──────────────────────────────────────────────────────
        $thumb_w = 60;
        $thumb_h = 60;

        if ( $size !== '' ) {
            $parts = explode( 'x', strtolower( $size ), 2 );
            if ( count( $parts ) === 2 ) {
                $thumb_w = max( 1, (int) $parts[0] );
                $thumb_h = max( 1, (int) $parts[1] );
            } elseif ( count( $parts ) === 1 && (int) $parts[0] > 0 ) {
                $thumb_w = $thumb_h = max( 1, (int) $parts[0] );
            }
        }

        $col_width = $thumb_w ;

        $after      = $after ?: ( $type === 'post' ? 'title' : 'name' );
        $column_key = 'sp_thumb_' . $object;
        $nonce_key  = 'sp_save_thumb_' . $object;

        // ── ACF key ────────────────────────────────────────────────────────────
        $get_acf_key = static fn( int $id ): int|string => $type === 'post' ? $id : "{$object}_{$id}";

        // ── Получить URL изображения ───────────────────────────────────────────
        $get_image_url = static function ( int $id ) use ( $acf_field, $get_acf_key ): string {
            if ( $acf_field === '' ) {
                return get_the_post_thumbnail_url( $id, 'large' ) ?: '';
            }

            $image = get_field( $acf_field, $get_acf_key( $id ) );
            if ( ! $image ) {
                return '';
            }
            if ( is_array( $image ) ) {
                return $image['sizes']['thumbnail'] ?? $image['sizes']['medium'] ?? $image['url'] ?? '';
            }
            if ( is_numeric( $image ) ) {
                return wp_get_attachment_image_url( (int) $image, 'thumbnail' ) ?: '';
            }

            return (string) $image;
        };

        // ── Получить ID изображения ────────────────────────────────────────────
        $get_image_id = static function ( int $id ) use ( $acf_field, $get_acf_key ): int {
            if ( $acf_field === '' ) {
                return (int) get_post_thumbnail_id( $id );
            }

            $image = get_field( $acf_field, $get_acf_key( $id ) );
            if ( ! $image ) {
                return 0;
            }
            if ( is_array( $image ) ) {
                return (int) ( $image['ID'] ?? 0 );
            }
            if ( is_numeric( $image ) ) {
                return (int) $image;
            }

            return 0;
        };

        // ── Вставить колонку после нужного ключа ──────────────────────────────
        $inject_column = static function ( array $columns ) use ( $column_key, $column_label, $after ): array {
            $result = [];
            foreach ( $columns as $key => $label ) {
                $result[ $key ] = $label;
                if ( $key === $after ) {
                    $result[ $column_key ] = __( $column_label, 'ACF Fields' );
                }
            }
            if ( ! isset( $result[ $column_key ] ) ) {
                $result[ $column_key ] = __( $column_label, 'ACF Fields' );
            }

            return $result;
        };

        // ── Рендер ячейки ──────────────────────────────────────────────────────
        $render_cell = static function ( int $id ) use ( $object, $type, $nonce_key, $get_image_url, $get_image_id, $thumb_w, $thumb_h ): string {
            $src    = $get_image_url( $id );
            $img_id = $get_image_id( $id );

            ob_start(); ?>
            <div class="sp-tax-thumb-cell"
                 data-id="<?php echo esc_attr( $id ); ?>"
                 data-object="<?php echo esc_attr( $object ); ?>"
                 data-type="<?php echo esc_attr( $type ); ?>"
                 data-img-id="<?php echo esc_attr( $img_id ); ?>"
                 data-original-img-id="<?php echo esc_attr( $img_id ); ?>"
                 data-nonce="<?php echo esc_attr( wp_create_nonce( $nonce_key ) ); ?>"
                 style="width:<?php echo $thumb_w; ?>px;">

                <div class="sp-tax-thumb"
                     style="width:<?php echo $thumb_w; ?>px;height:<?php echo $thumb_h; ?>px;">
                    <?php if ( $src ) : ?>
                        <img src="<?php echo esc_url( $src ); ?>"
                             style="width:100%;height:100%;object-fit:contain;display:block;">
                    <?php endif; ?>
                    <div class="sp-tax-thumb-overlay"
                         style="position:absolute;inset:0;background:rgba(0,0,0,.4);display:<?php echo $src ? 'none' : 'flex'; ?>;align-items:center;justify-content:center;">
                        <span class="dashicons dashicons-camera" style="color:#fff;font-size:20px;"></span>
                    </div>
                </div>

                <button type="button" class="button-link sp-tax-thumb-save"
                        aria-label="<?php esc_attr_e( 'Save', 'ACF Fields' ); ?>">
                    <span class="dashicons dashicons-saved" aria-hidden="true"></span>
                </button>

                <span class="sp-tax-thumb-status" aria-live="polite"></span>
            </div>
            <?php
            return ob_get_clean();
        };

        // ── Ширина колонки ─────────────────────────────────────────────────────
        add_action( 'admin_head', function () use ( $column_key, $col_width ) {
            $screen = get_current_screen();
            if ( ! $screen || ! in_array( $screen->base, [ 'edit-tags', 'edit' ], true ) ) {
                return;
            }
            echo '<style>.column-' . esc_attr( $column_key ) . ' { width: ' . (int) $col_width . 'px !important; }</style>';
        } );

        // ── Регистрация хуков колонки ──────────────────────────────────────────

        if ( $type === 'post' ) {

            add_filter( "manage_{$object}_posts_columns",
                    fn( $columns ) => $inject_column( $columns )
            );

            add_action( "manage_{$object}_posts_custom_column",
                    function ( string $column, int $post_id ) use ( $column_key, $render_cell ): void {
                        if ( $column === $column_key ) {
                            echo $render_cell( $post_id );
                        }
                    }, 10, 2
            );

        } elseif ( $type === 'term' ) {

            add_filter( "manage_edit-{$object}_columns",
                    fn( $columns ) => $inject_column( $columns )
            );

            add_filter( "manage_{$object}_custom_column",
                    function ( string $out, string $column, int $term_id ) use ( $column_key, $render_cell ): string {
                        return $column === $column_key ? $render_cell( $term_id ) : $out;
                    }, 10, 3
            );
        }

        // ── AJAX сохранение ────────────────────────────────────────────────────

        add_action( "wp_ajax_sp_save_thumb_{$object}", function () use ( $object, $type, $acf_field, $nonce_key, $get_acf_key ) {
            check_ajax_referer( $nonce_key, 'nonce' );

            if ( ! current_user_can( 'manage_categories' ) ) {
                wp_send_json_error( [ 'message' => 'Insufficient permissions' ] );
            }

            $id     = (int) ( $_POST['id'] ?? 0 );
            $img_id = (int) ( $_POST['img_id'] ?? 0 );

            if ( ! $id ) {
                wp_send_json_error( [ 'message' => 'Invalid ID' ] );
            }

            if ( $acf_field === '' ) {
                $img_id ? set_post_thumbnail( $id, $img_id ) : delete_post_thumbnail( $id );
            } else {
                update_field( $acf_field, $img_id ?: null, $get_acf_key( $id ) );
            }

            wp_send_json_success( [
                    'message' => __( 'Saved', 'ACF Fields' ),
                    'img_id'  => $img_id,
            ] );
        } );
    }


// =========================================================================
// CSS — один раз для всех колонок
// =========================================================================

    add_action( 'admin_head', function () {
        $screen = get_current_screen();
        if ( ! $screen || ! in_array( $screen->base, [ 'edit-tags', 'edit' ], true ) ) {
            return;
        }
        ?>
        <style>

            .sp-tax-thumb {
                position: relative;
                overflow: hidden;
                border: 2px dashed var(--color-border-strong, #d6dbe1);
                border-radius: var(--sp-admin-radius-sm, 9px);
                background: var(--color-surface-alt, #f8fafc);
                box-shadow: var(--sp-admin-shadow-xs, 0 1px 2px rgb(26 31 36 / 4%));
                cursor: pointer;
                transition: border-color var(--sp-admin-transition, 160ms ease), box-shadow var(--sp-admin-transition, 160ms ease), transform var(--sp-admin-transition, 160ms ease);
            }

            .sp-tax-thumb-cell {
                position: relative;
                display: flex;
            }

            .sp-tax-thumb:hover,
            .sp-tax-thumb:focus-within {
                border-color: var(--color-accent, #3858e9);
                box-shadow: var(--sp-admin-focus, 0 0 0 3px rgb(56 88 233 / 18%));
                transform: translateY(-1px);
            }

            .sp-tax-thumb:hover .sp-tax-thumb-overlay {
                display: flex !important;
            }

            .sp-tax-thumb-save {
                opacity: 0;
                visibility: hidden;
                position: absolute;
                right: -12px;
                bottom: -5px;
                width: 30px;
                height: 30px;
                padding: 0 !important;
                border: 1px solid var(--color-accent, #3858e9) !important;
                border-radius: 9px;
                background: linear-gradient(145deg, var(--color-accent-bright, #487de4), var(--color-accent, #3858e9));
                box-shadow: 0 5px 12px rgb(56 88 233 / 22%);
                color: var(--color-on-accent, #fff);
                display: flex;
                align-items: center;
                justify-content: center;
                line-height: 1;
                cursor: pointer;
                transition: opacity var(--sp-admin-transition, 160ms ease), background var(--sp-admin-transition, 160ms ease), box-shadow var(--sp-admin-transition, 160ms ease), transform var(--sp-admin-transition, 160ms ease);
            }

            .sp-tax-thumb-save .dashicons {
                width: 17px;
                height: 17px;
                font-size: 17px;
                line-height: 17px;
            }

            .sp-tax-thumb-save:hover,
            .sp-tax-thumb-save:focus {
                background: var(--color-accent-hover, #2145e6);
                box-shadow: 0 7px 16px rgb(33 69 230 / 24%);
                color: var(--color-on-accent, #fff);
                transform: translateY(-1px);
            }

            .sp-tax-thumb-save:focus-visible {
                box-shadow: var(--sp-admin-focus, 0 0 0 3px rgb(56 88 233 / 18%));
                outline: 0;
            }

            .sp-tax-thumb-cell.is-dirty .sp-tax-thumb-save {
                opacity: 1;
                visibility: visible;
                z-index: 10;
            }

            .sp-tax-thumb-save[disabled] {
                opacity: .5;
                pointer-events: none;
            }

            .sp-tax-thumb-status {
                display: block;
                position: absolute;
                left: 50%;
                top: 105%;
                transform: translateX(-50%);
                font-size: 10px;
                white-space: nowrap;
                color: var(--color-success, #27ae60);
                min-height: 14px;
            }

            .sp-tax-thumb-status.is-error {
                color: var(--color-error, #e74c3c);
            }
        </style>
        <?php
    } );


// =========================================================================
// JS — один раз для всех колонок
// =========================================================================

    add_action( 'admin_footer', function () {
        $screen = get_current_screen();
        if ( ! $screen || ! in_array( $screen->base, [ 'edit-tags', 'edit' ], true ) ) {
            return;
        }

        wp_enqueue_media();
        ?>
        <script>
            (function ($) {

                function setStatus($cell, msg, isError) {
                    var $s = $cell.find('.sp-tax-thumb-status');
                    $s.text(msg || '').toggleClass('is-error', !!isError);
                    if (!isError && msg) {
                        setTimeout(function () {
                            $s.text('');
                        }, 1500);
                    }
                }

                function syncDirty($cell) {
                    $cell.toggleClass('is-dirty',
                        String($cell.data('img-id') || '') !== String($cell.data('original-img-id') || '')
                    );
                }

                /* ── Клик по превью — медиабиблиотека ── */
                $(document).on('click', '.sp-tax-thumb', function () {
                    var $thumb = $(this);
                    var $cell = $thumb.closest('.sp-tax-thumb-cell');

                    var frame = wp.media({
                        title: 'Select image',
                        button: {text: 'Use this image'},
                        multiple: false,
                        library: {type: 'image'},
                    });

                    frame.on('select', function () {
                        var att = frame.state().get('selection').first().toJSON();
                        var src = att.sizes?.thumbnail?.url ?? att.url;

                        if ($thumb.find('img').length) {
                            $thumb.find('img').attr('src', src);
                        } else {
                            $thumb.prepend(
                                '<img src="' + src + '" style="width:100%;height:100%;object-fit:contain;display:block;">'
                            );
                        }

                        $thumb.find('.sp-tax-thumb-overlay').hide();
                        $cell.data('img-id', att.id).attr('data-img-id', att.id);
                        setStatus($cell, '', false);
                        syncDirty($cell);
                    });

                    frame.open();
                });

                /* ── Клик Save ── */
                $(document).on('click', '.sp-tax-thumb-save', function (e) {
                    e.preventDefault();
                    e.stopPropagation();

                    var $btn = $(this).prop('disabled', true);
                    var $cell = $btn.closest('.sp-tax-thumb-cell');

                    setStatus($cell, 'Saving...', false);

                    $.post(ajaxurl, {
                        action: 'sp_save_thumb_' + $cell.data('object'),
                        nonce: $cell.data('nonce'),
                        id: $cell.data('id'),
                        img_id: $cell.data('img-id') || '',
                    })
                        .done(function (res) {
                            if (!res || !res.success) {
                                setStatus($cell, res?.data?.message || 'Error', true);
                                return;
                            }
                            var current = $cell.data('img-id') || '';
                            $cell.data('original-img-id', current).attr('data-original-img-id', current);
                            syncDirty($cell);
                            setStatus($cell, res.data?.message || 'Saved', false);
                        })
                        .fail(function () {
                            setStatus($cell, 'Error', true);
                        })
                        .always(function () {
                            $btn.prop('disabled', false);
                        });
                });

            })(jQuery);
        </script>
        <?php
    } );
