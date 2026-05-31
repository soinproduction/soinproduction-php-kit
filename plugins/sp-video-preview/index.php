<?php
    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

    final class SP_Video_Preview {

        private const META_POSTER_ID = '_sp_video_poster_id';
        private const META_GENERATED = '_sp_video_poster_generated';

        private const META_CONTROLS = '_sp_video_controls';
        private const META_MUTED    = '_sp_video_muted';
        private const META_AUTOPLAY = '_sp_video_autoplay';
        private const META_LOOP     = '_sp_video_loop';

        private const NONCE_ACTION = 'sp_video_preview_action';

        public static function init(): void {
            if ( is_admin() ) {
                add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_assets' ] );
                add_filter( 'attachment_fields_to_edit', [ __CLASS__, 'attachment_fields_to_edit' ], 10, 2 );
                add_filter( 'attachment_fields_to_save', [ __CLASS__, 'attachment_fields_to_save' ], 10, 2 );

                add_action( 'wp_ajax_sp_video_preview_save_frame', [ __CLASS__, 'ajax_save_frame' ] );
                add_action( 'wp_ajax_sp_video_preview_set_existing', [ __CLASS__, 'ajax_set_existing' ] );
                add_action( 'wp_ajax_sp_video_preview_remove', [ __CLASS__, 'ajax_remove' ] );
            }

            add_filter( 'display_media_attrs', [ __CLASS__, 'display_media_attrs' ], 10, 4 );
        }

        public static function display_media_attrs( array $attrs, array $data, string $type, array $image_array ): array {
            if ( $type !== 'video' ) {
                return $attrs;
            }

            $video_id = isset( $data['id'] ) ? (int) $data['id'] : 0;

            if ( $video_id <= 0 || ! self::is_video_attachment( $video_id ) ) {
                return $attrs;
            }

            $attrs['controls'] = self::get_video_option( $video_id, self::META_CONTROLS, false );
            $attrs['muted']    = self::get_video_option( $video_id, self::META_MUTED, true );
            $attrs['autoplay'] = self::get_video_option( $video_id, self::META_AUTOPLAY, true );
            $attrs['loop']     = self::get_video_option( $video_id, self::META_LOOP, true );

            return $attrs;
        }

        private static function get_video_option( int $video_id, string $meta_key, bool $default ): bool {
            $value = get_post_meta( $video_id, $meta_key, true );

            if ( $value === '' ) {
                return $default;
            }

            return (bool) (int) $value;
        }

        public static function enqueue_admin_assets(): void {
            wp_enqueue_media();

            wp_register_style( 'sp-video-preview-admin-inline', false );
            wp_enqueue_style( 'sp-video-preview-admin-inline' );
            wp_add_inline_style( 'sp-video-preview-admin-inline', self::admin_css() );

            wp_register_script( 'sp-video-preview-admin-inline', false, [ 'jquery' ], null, true );
            wp_enqueue_script( 'sp-video-preview-admin-inline' );

            $payload = [
                    'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                    'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
                    'i18n'    => [
                            'processing'  => 'Processing...',
                            'saving'      => 'Saving...',
                            'removing'    => 'Removing...',
                            'error'       => 'Could not complete the action.',
                            'selectImage' => 'Select poster image',
                            'useImage'    => 'Use this image',
                    ],
            ];

            wp_add_inline_script(
                    'sp-video-preview-admin-inline',
                    'window.SPVideoPreview=' . wp_json_encode( $payload ) . ';',
                    'before'
            );

            wp_add_inline_script( 'sp-video-preview-admin-inline', self::admin_js() );
        }

        public static function attachment_fields_to_edit( array $form_fields, WP_Post $post ): array {
            if ( ! self::is_video_attachment( (int) $post->ID ) ) {
                return $form_fields;
            }

            $video_url = wp_get_attachment_url( $post->ID );
            if ( ! $video_url ) {
                return $form_fields;
            }

            $poster_id  = self::get_poster_id( (int) $post->ID );
            $poster_url = $poster_id ? wp_get_attachment_url( $poster_id ) : '';

            $video_controls = self::get_video_option( (int) $post->ID, self::META_CONTROLS, false );
            $video_muted    = self::get_video_option( (int) $post->ID, self::META_MUTED, true );
            $video_autoplay = self::get_video_option( (int) $post->ID, self::META_AUTOPLAY, true );
            $video_loop     = self::get_video_option( (int) $post->ID, self::META_LOOP, true );

            ob_start();
            ?>
            <div class="sp-vp" data-attachment-id="<?php echo esc_attr( (string) $post->ID ); ?>">
                <div class="sp-vp__grid">
                    <div class="sp-vp__left">
                        <video class="sp-vp__video" src="<?php echo esc_url( $video_url ); ?>" controls preload="metadata" playsinline></video>

                        <div class="sp-vp__controls">
                            <input class="sp-vp__range" type="range" min="0" max="0" step="0.01" value="0" />
                            <span class="sp-vp__time">00:00 / 00:00</span>
                        </div>

                        <div class="sp-vp__actions">
                            <button type="button" class="button button-primary sp-vp__capture">Capture current frame</button>
                            <button type="button" class="button sp-vp__choose">Choose image</button>
                            <button type="button" class="button sp-vp__remove <?php echo $poster_id ? '' : 'is-hidden'; ?>">Remove poster</button>
                        </div>

                        <p class="description">Move to the desired frame and click "Capture current frame".</p>
                    </div>

                    <div class="sp-vp__right">
                        <div class="sp-vp__poster-box <?php echo $poster_url ? 'has-image' : ''; ?>">
                            <img class="sp-vp__poster" src="<?php echo esc_url( $poster_url ); ?>" alt="Poster preview" <?php echo $poster_url ? '' : 'style="display:none"'; ?> />
                            <span class="sp-vp__empty" <?php echo $poster_url ? 'style="display:none"' : ''; ?>>No poster selected</span>
                        </div>

                        <input type="hidden" class="sp-vp__poster-id" name="attachments[<?php echo esc_attr( (string) $post->ID ); ?>][sp_video_poster_id]" value="<?php echo esc_attr( (string) $poster_id ); ?>" />

                        <label class="sp-vp__label" for="sp-vp-url-<?php echo esc_attr( (string) $post->ID ); ?>">Poster URL</label>
                        <input id="sp-vp-url-<?php echo esc_attr( (string) $post->ID ); ?>" class="sp-vp__url widefat" type="text" readonly value="<?php echo esc_attr( $poster_url ); ?>" />

                        <div class="sp-vp__toggles">
                            <label>
                                <input type="checkbox" name="attachments[<?php echo esc_attr( (string) $post->ID ); ?>][sp_video_controls]" value="1" <?php checked( $video_controls ); ?>>
                                Controls
                            </label>

                            <label>
                                <input type="checkbox" name="attachments[<?php echo esc_attr( (string) $post->ID ); ?>][sp_video_muted]" value="1" <?php checked( $video_muted ); ?>>
                                Muted
                            </label>

                            <label>
                                <input type="checkbox" name="attachments[<?php echo esc_attr( (string) $post->ID ); ?>][sp_video_autoplay]" value="1" <?php checked( $video_autoplay ); ?>>
                                Autoplay
                            </label>

                            <label>
                                <input type="checkbox" name="attachments[<?php echo esc_attr( (string) $post->ID ); ?>][sp_video_loop]" value="1" <?php checked( $video_loop ); ?>>
                                Loop
                            </label>
                        </div>

                        <div class="sp-vp__status" aria-live="polite"></div>
                    </div>
                </div>
            </div>
            <?php
            $html = (string) ob_get_clean();

            $form_fields['sp_video_preview'] = [
                    'label'         => 'Video Poster',
                    'input'         => 'html',
                    'html'          => $html,
                    'show_in_edit'  => true,
                    'show_in_modal' => true,
            ];

            return $form_fields;
        }

        public static function attachment_fields_to_save( array $post, array $attachment ): array {
            $attachment_id = isset( $post['ID'] ) ? (int) $post['ID'] : 0;

            if ( $attachment_id <= 0 || ! self::is_video_attachment( $attachment_id ) ) {
                return $post;
            }

            if ( ! current_user_can( 'upload_files' ) ) {
                return $post;
            }

            if ( array_key_exists( 'sp_video_poster_id', $attachment ) ) {
                self::set_poster_id( $attachment_id, (int) $attachment['sp_video_poster_id'] );
            }

            update_post_meta( $attachment_id, self::META_CONTROLS, ! empty( $attachment['sp_video_controls'] ) ? 1 : 0 );
            update_post_meta( $attachment_id, self::META_MUTED, ! empty( $attachment['sp_video_muted'] ) ? 1 : 0 );
            update_post_meta( $attachment_id, self::META_AUTOPLAY, ! empty( $attachment['sp_video_autoplay'] ) ? 1 : 0 );
            update_post_meta( $attachment_id, self::META_LOOP, ! empty( $attachment['sp_video_loop'] ) ? 1 : 0 );

            return $post;
        }

        public static function ajax_save_frame(): void {
            self::guard_ajax();

            $attachment_id = isset( $_POST['attachment_id'] ) ? (int) $_POST['attachment_id'] : 0;
            $image_data    = isset( $_POST['image_data'] ) ? (string) $_POST['image_data'] : '';

            if ( $attachment_id <= 0 || ! self::is_video_attachment( $attachment_id ) ) {
                wp_send_json_error( [ 'message' => 'Invalid video attachment.' ], 400 );
            }

            if ( ! preg_match( '~^data:image/(png|jpe?g|webp);base64,(.+)$~', $image_data, $matches ) ) {
                wp_send_json_error( [ 'message' => 'Unsupported image data.' ], 400 );
            }

            $ext     = strtolower( $matches[1] );
            $encoded = str_replace( ' ', '+', $matches[2] );
            $binary  = base64_decode( $encoded, true );

            if ( $binary === false || $binary === '' ) {
                wp_send_json_error( [ 'message' => 'Broken image data.' ], 400 );
            }

            if ( strlen( $binary ) > 25 * 1024 * 1024 ) {
                wp_send_json_error( [ 'message' => 'Image is too large.' ], 413 );
            }

            $video_title = sanitize_title( (string) get_the_title( $attachment_id ) );
            if ( $video_title === '' ) {
                $video_title = 'video';
            }

            $mime_map = [
                    'jpg'  => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                    'png'  => 'image/png',
                    'webp' => 'image/webp',
            ];

            $mime = $mime_map[ $ext ] ?? 'image/jpeg';

            $file_name = sprintf(
                    '%s-poster-%s.%s',
                    $video_title,
                    gmdate( 'Ymd-His' ),
                    $ext === 'jpeg' ? 'jpg' : $ext
            );

            $upload = wp_upload_bits( $file_name, null, $binary );

            if ( ! empty( $upload['error'] ) ) {
                wp_send_json_error( [ 'message' => (string) $upload['error'] ], 500 );
            }

            require_once ABSPATH . 'wp-admin/includes/image.php';

            $poster_id = wp_insert_attachment(
                    [
                            'post_mime_type' => $mime,
                            'post_title'     => sanitize_text_field( get_the_title( $attachment_id ) . ' Poster' ),
                            'post_status'    => 'inherit',
                            'post_parent'    => $attachment_id,
                    ],
                    $upload['file'],
                    $attachment_id,
                    true
            );

            if ( is_wp_error( $poster_id ) || ! $poster_id ) {
                wp_send_json_error( [ 'message' => 'Could not create image attachment.' ], 500 );
            }

            $metadata = wp_generate_attachment_metadata( $poster_id, $upload['file'] );

            if ( ! is_wp_error( $metadata ) ) {
                wp_update_attachment_metadata( $poster_id, $metadata );
            }

            update_post_meta( $poster_id, self::META_GENERATED, 1 );
            self::set_poster_id( $attachment_id, (int) $poster_id, true );

            wp_send_json_success(
                    [
                            'video_id'   => (int) $attachment_id,
                            'poster_id'  => (int) $poster_id,
                            'poster_url' => (string) wp_get_attachment_url( $poster_id ),
                    ]
            );
        }

        public static function ajax_set_existing(): void {
            self::guard_ajax();

            $attachment_id = isset( $_POST['attachment_id'] ) ? (int) $_POST['attachment_id'] : 0;
            $poster_id     = isset( $_POST['poster_id'] ) ? (int) $_POST['poster_id'] : 0;

            if ( $attachment_id <= 0 || ! self::is_video_attachment( $attachment_id ) ) {
                wp_send_json_error( [ 'message' => 'Invalid video attachment.' ], 400 );
            }

            if ( $poster_id <= 0 || ! self::is_image_attachment( $poster_id ) ) {
                wp_send_json_error( [ 'message' => 'Invalid poster image.' ], 400 );
            }

            if ( ! self::set_poster_id( $attachment_id, $poster_id ) ) {
                wp_send_json_error( [ 'message' => 'Could not save poster.' ], 500 );
            }

            wp_send_json_success(
                    [
                            'video_id'   => (int) $attachment_id,
                            'poster_id'  => $poster_id,
                            'poster_url' => (string) wp_get_attachment_url( $poster_id ),
                    ]
            );
        }

        public static function ajax_remove(): void {
            self::guard_ajax();

            $attachment_id = isset( $_POST['attachment_id'] ) ? (int) $_POST['attachment_id'] : 0;

            if ( $attachment_id <= 0 || ! self::is_video_attachment( $attachment_id ) ) {
                wp_send_json_error( [ 'message' => 'Invalid video attachment.' ], 400 );
            }

            self::set_poster_id( $attachment_id, 0 );

            wp_send_json_success(
                    [
                            'video_id'   => (int) $attachment_id,
                            'poster_id'  => 0,
                            'poster_url' => '',
                    ]
            );
        }

        private static function guard_ajax(): void {
            if ( ! current_user_can( 'upload_files' ) ) {
                wp_send_json_error( [ 'message' => 'No permissions.' ], 403 );
            }

            $nonce = isset( $_POST['nonce'] ) ? (string) $_POST['nonce'] : '';

            if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
                wp_send_json_error( [ 'message' => 'Bad nonce.' ], 403 );
            }
        }

        private static function is_video_attachment( int $attachment_id ): bool {
            $mime = (string) get_post_mime_type( $attachment_id );

            return $mime !== '' && str_starts_with( $mime, 'video/' );
        }

        private static function is_image_attachment( int $attachment_id ): bool {
            $mime = (string) get_post_mime_type( $attachment_id );

            return $mime !== '' && str_starts_with( $mime, 'image/' );
        }

        private static function get_poster_id( int $video_attachment_id ): int {
            $poster_id = (int) get_post_meta( $video_attachment_id, self::META_POSTER_ID, true );

            if ( $poster_id > 0 ) {
                return $poster_id;
            }

            return (int) get_post_thumbnail_id( $video_attachment_id );
        }

        private static function set_poster_id( int $video_attachment_id, int $poster_id, bool $delete_old_generated = false ): bool {
            $current = self::get_poster_id( $video_attachment_id );

            if ( $poster_id > 0 && ! self::is_image_attachment( $poster_id ) ) {
                return false;
            }

            if ( $poster_id <= 0 ) {
                delete_post_meta( $video_attachment_id, self::META_POSTER_ID );
                delete_post_thumbnail( $video_attachment_id );

                if ( $delete_old_generated && $current > 0 && (int) get_post_meta( $current, self::META_GENERATED, true ) === 1 ) {
                    wp_delete_attachment( $current, true );
                }

                return true;
            }

            update_post_meta( $video_attachment_id, self::META_POSTER_ID, $poster_id );
            set_post_thumbnail( $video_attachment_id, $poster_id );

            if ( $delete_old_generated && $current > 0 && $current !== $poster_id && (int) get_post_meta( $current, self::META_GENERATED, true ) === 1 ) {
                wp_delete_attachment( $current, true );
            }

            return true;
        }

        private static function normalize_video_attachment_id( $video ): int {
            if ( is_array( $video ) ) {
                if ( isset( $video['ID'] ) ) {
                    $video = $video['ID'];
                } elseif ( isset( $video['url'] ) ) {
                    $video = $video['url'];
                }
            }

            if ( is_numeric( $video ) ) {
                $id = (int) $video;

                return self::is_video_attachment( $id ) ? $id : 0;
            }

            if ( is_string( $video ) && $video !== '' ) {
                $id = (int) attachment_url_to_postid( $video );

                return self::is_video_attachment( $id ) ? $id : 0;
            }

            return 0;
        }

        public static function poster_id( $video ): int {
            $video_id = self::normalize_video_attachment_id( $video );

            if ( $video_id <= 0 ) {
                return 0;
            }

            $poster_id = self::get_poster_id( $video_id );

            return self::is_image_attachment( $poster_id ) ? $poster_id : 0;
        }

        public static function poster_url( $video, string $size = 'full' ): string {
            $poster_id = self::poster_id( $video );

            if ( $poster_id <= 0 ) {
                return '';
            }

            if ( $size !== 'full' ) {
                $by_size = wp_get_attachment_image_url( $poster_id, $size );

                if ( $by_size ) {
                    return (string) $by_size;
                }
            }

            return (string) wp_get_attachment_url( $poster_id );
        }

        private static function admin_css(): string {
            return <<<'CSS'
		.sp-vp{margin:8px 0 2px;max-width:740px;}
		.sp-vp__grid{display:grid;grid-template-columns:minmax(0,1fr);gap:12px;align-items:start;}
		.sp-vp__video{width:100%;max-width:100%;aspect-ratio:16/9;height:auto;border:1px solid #dcdcde;border-radius:8px;background:#000;display:block;}
		.sp-vp__controls{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px;align-items:center;margin-top:8px;}
		.sp-vp__range{width:100%;margin:0;}
		.sp-vp__range[disabled]{opacity:.45;cursor:not-allowed;}
		.sp-vp__time{font-size:12px;color:#50575e;min-width:92px;text-align:right;}
		.sp-vp__actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;}
		.sp-vp__right{display:block;}
		.sp-vp__poster-box{width:100%;max-width:260px;aspect-ratio:16/9;display:flex;align-items:center;justify-content:center;border:1px dashed #c3c4c7;border-radius:8px;background:#f6f7f7;overflow:hidden;margin-bottom:8px;}
		.sp-vp__poster-box.has-image{border-style:solid;background:#fff;}
		.sp-vp__poster{width:100%;height:100%;object-fit:cover;display:block;}
		.sp-vp__empty{font-size:12px;color:#646970;text-align:center;padding:8px;}
		.sp-vp__label{display:block;font-size:12px;margin:0 0 4px;color:#1d2327;}
		.sp-vp__url{font-size:12px;width:100%;}
		.sp-vp__toggles{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:6px 12px;margin-top:10px;font-size:12px;}
		.sp-vp__toggles label{display:flex;align-items:center;gap:6px;margin:0;}
		.sp-vp__status{min-height:18px;margin-top:6px;font-size:12px;color:#646970;}
		.sp-vp__status.is-ok{color:#0a7f42;}
		.sp-vp__status.is-error{color:#b32d2e;}
		.sp-vp .is-hidden{display:none !important;}
		@media (max-width:782px){.sp-vp{max-width:none;}}
		CSS;
        }

        private static function admin_js(): string {
            return <<<'JS'
		(function($){
		  const CFG = window.SPVideoPreview || {};
		  const INIT_ATTR = 'data-sp-vp-init';

		  function i18n(key, fallback){
			if (CFG.i18n && typeof CFG.i18n[key] === 'string' && CFG.i18n[key] !== '') {
			  return CFG.i18n[key];
			}
			return fallback;
		  }

		  function secondsToClock(seconds){
			const s = Math.max(0, Math.floor(Number(seconds) || 0));
			const m = Math.floor(s / 60);
			const r = s % 60;
			return String(m).padStart(2, '0') + ':' + String(r).padStart(2, '0');
		  }

		  function setStatus($root, text, type){
			const $status = $root.find('.sp-vp__status');
			$status.removeClass('is-ok is-error').text(text || '');
			if (type === 'ok') {
			  $status.addClass('is-ok');
			}
			if (type === 'error') {
			  $status.addClass('is-error');
			}
		  }

		  function setBusy($root, busy){
			$root.toggleClass('is-busy', !!busy);
			$root.find('button').prop('disabled', !!busy);
		  }

		  function updateTime($root, video){
			const duration = Number(video.duration);
			const safeDuration = Number.isFinite(duration) && duration > 0 ? duration : 0;
			const current = Number(video.currentTime) || 0;
			$root.find('.sp-vp__time').text(secondsToClock(current) + ' / ' + secondsToClock(safeDuration));
		  }

		  function syncTimeline($root, video){
			const $range = $root.find('.sp-vp__range');
			if (!video) {
			  if ($range.length) {
				$range.prop('disabled', true).attr('max', '0').val('0');
			  }
			  $root.find('.sp-vp__time').text('00:00 / 00:00');
			  return;
			}

			const duration = Number(video.duration);
			const hasDuration = Number.isFinite(duration) && duration > 0;

			if ($range.length) {
			  $range.prop('disabled', !hasDuration);
			  $range.attr('max', hasDuration ? String(duration) : '0');

			  if (hasDuration && !$range.is(':active')) {
				const current = Math.min(Math.max(Number(video.currentTime) || 0, 0), duration);
				$range.val(String(current));
			  }
			}

			updateTime($root, video);
		  }

		  function resolveVideoElement($root){
			if (!$root || !$root.length) {
			  return null;
			}

			const primary = $root.find('video.sp-vp__video').get(0);
			if (primary) {
			  return primary;
			}

			return $root.find('video').get(0) || null;
		  }

		  function bindVideoEvents($root, video){
			if (!video || typeof video.addEventListener !== 'function') {
			  return;
			}

			const attachmentId = String($root.data('attachment-id') || '');
			const bindKey = '__spVpBound_' + attachmentId;
			if (video[bindKey] === true) {
			  return;
			}
			video[bindKey] = true;

			const sync = function(){
			  syncTimeline($root, resolveVideoElement($root) || video);
			};

			['loadedmetadata', 'durationchange', 'loadeddata', 'canplay', 'timeupdate', 'seeked', 'play', 'pause'].forEach(function(eventName){
			  video.addEventListener(eventName, sync);
			});
		  }

		  function setVideoPoster($root, posterUrl){
			const video = resolveVideoElement($root) || $root.find('.sp-vp__video').get(0);
			if (!video) {
			  return;
			}

			if (posterUrl) {
			  video.setAttribute('poster', posterUrl);
			} else {
			  video.removeAttribute('poster');
			}
		  }

		  function updateMediaLibraryPreview($root, videoId, posterUrl){
			const attachmentId = Number(videoId) || 0;
			if (!attachmentId) {
			  return;
			}

			const withBust = function(url){
			  if (!url) {
				return '';
			  }
			  return url + (url.indexOf('?') === -1 ? '?' : '&') + 'spvp=' + Date.now();
			};

			const applyModelPreview = function(model, url){
			  if (!model || !url) {
				return;
			  }
			  const image = { src: url, width: 512, height: 288 };
			  model.set('image', image);
			  model.set('thumb', image);
			  model.trigger('change');
			};

			const uiPosterUrl = posterUrl ? withBust(posterUrl) : '';

			if (window.wp && wp.media && wp.media.model && wp.media.model.Attachment) {
			  const model = wp.media.model.Attachment.get(attachmentId);
			  if (model) {
				if (uiPosterUrl) {
				  applyModelPreview(model, uiPosterUrl);
				  if (typeof model.fetch === 'function') {
					const req = model.fetch();
					if (req && typeof req.always === 'function') {
					  req.always(function(){
						applyModelPreview(model, uiPosterUrl);
					  });
					}
				  }
				} else if (typeof model.fetch === 'function') {
				  model.fetch();
				}
			  }
			}

			if (window.wp && wp.media && typeof wp.media.attachment === 'function') {
			  const attached = wp.media.attachment(attachmentId);
			  if (attached && typeof attached.fetch === 'function') {
				const req = attached.fetch();
				if (uiPosterUrl && req && typeof req.always === 'function') {
				  req.always(function(){
					applyModelPreview(attached, uiPosterUrl);
				  });
				}
			  }
			}

			if ($root && $root.length) {
			  const $modal = $root.closest('.media-modal, .media-frame, .attachments-browser, body');
			  if (uiPosterUrl) {
				$modal.find('video').each(function(){
				  if (typeof this.setAttribute === 'function') {
					this.setAttribute('poster', uiPosterUrl);
				  }
				});
				$modal.find('.attachment-details .thumbnail img, .attachment .thumbnail img').attr('src', uiPosterUrl);
			  }
			}

			if (!posterUrl) {
			  return;
			}

			const $tile = $('.attachment[data-id="' + attachmentId + '"]');
			if (!$tile.length) {
			  return;
			}

			const $thumb = $tile.find('.thumbnail').first();
			if (!$thumb.length) {
			  return;
			}

			if (!$thumb.find('img').length) {
			  $thumb.html('<div class="centered"><img alt="" draggable="false" /></div>');
			}

			$thumb.find('img').first().attr('src', uiPosterUrl);
		  }

		  function updatePosterUI($root, posterId, posterUrl){
			const $img = $root.find('.sp-vp__poster');
			const $box = $root.find('.sp-vp__poster-box');
			const $empty = $root.find('.sp-vp__empty');
			const $remove = $root.find('.sp-vp__remove');

			$root.find('.sp-vp__poster-id').val(posterId ? String(posterId) : '');
			$root.find('.sp-vp__url').val(posterUrl || '');
			const rootNode = $root.get(0);

			if (rootNode) {
			  rootNode.__spVpPosterUrl = String(posterUrl || '');
			}

			setVideoPoster($root, posterUrl || '');
			updateMediaLibraryPreview($root, $root.data('attachment-id'), posterUrl || '');

			if (posterUrl) {
			  $img.attr('src', posterUrl).show();
			  $box.addClass('has-image');
			  $empty.hide();
			  $remove.removeClass('is-hidden');
			} else {
			  $img.attr('src', '').hide();
			  $box.removeClass('has-image');
			  $empty.show();
			  $remove.addClass('is-hidden');
			}

			if (rootNode) {
			  initOne(rootNode);
			}
		  }

		  function ensurePosterPreviewFromUrl($root){
			const url = String($root.find('.sp-vp__url').val() || '');
			const $img = $root.find('.sp-vp__poster');
			const $box = $root.find('.sp-vp__poster-box');
			const $empty = $root.find('.sp-vp__empty');
			const $remove = $root.find('.sp-vp__remove');

			if (url) {
			  if (!$img.attr('src')) {
				$img.attr('src', url);
			  }
			  $img.show();
			  $box.addClass('has-image');
			  $empty.hide();
			  $remove.removeClass('is-hidden');
			}
		  }

		  function sendAction(action, data){
			return $.ajax({
			  url: CFG.ajaxUrl || window.ajaxurl,
			  method: 'POST',
			  dataType: 'json',
			  data: Object.assign({
				action: action,
				nonce: CFG.nonce || ''
			  }, data || {})
			});
		  }

		  function ensureVideoReady(video){
			return video
			  && Number(video.videoWidth) > 0
			  && Number(video.videoHeight) > 0
			  && Number(video.readyState) >= 2;
		  }

		  function initOne(root){
			if (!root) {
			  return;
			}

			const $root = $(root);
			const attachmentId = String($root.data('attachment-id') || '');
			if (!attachmentId) {
			  return;
			}

			const prevAttachmentId = String(root.getAttribute(INIT_ATTR) || '');
			if (prevAttachmentId && prevAttachmentId !== attachmentId && root.__spVpTimer) {
			  clearInterval(root.__spVpTimer);
			  root.__spVpTimer = null;
			}
			root.setAttribute(INIT_ATTR, attachmentId);

			const range = $root.find('.sp-vp__range').get(0);
			const sync = function(){
			  const activeVideo = resolveVideoElement($root);
			  bindVideoEvents($root, activeVideo);
			  syncTimeline($root, activeVideo);
			  ensurePosterPreviewFromUrl($root);
			};

			if (range) {
			  const onScrub = function(){
				const video = resolveVideoElement($root);
				if (!video) {
				  return;
				}

				const duration = Number(video.duration);
				if (!Number.isFinite(duration) || duration <= 0) {
				  return;
				}

				const nextTime = Number(range.value);
				if (!Number.isFinite(nextTime)) {
				  return;
				}

				try {
				  video.currentTime = Math.min(Math.max(nextTime, 0), duration);
				  updateTime($root, video);
				} catch (e) {
				  setStatus($root, i18n('error', 'Error'), 'error');
				}
			  };

			  range.oninput = onScrub;
			  range.onchange = onScrub;
			}

			const posterUrl = String($root.find('.sp-vp__url').val() || '');
			if (posterUrl) {
			  if (root.__spVpPosterUrl !== posterUrl) {
				root.__spVpPosterUrl = posterUrl;
				setVideoPoster($root, posterUrl);
				updateMediaLibraryPreview($root, $root.data('attachment-id'), posterUrl);
			  }
			} else {
			  root.__spVpPosterUrl = '';
			}

			sync();

			if (!root.__spVpTimer) {
			  root.__spVpTimer = window.setInterval(function(){
				if (!document.body || !document.body.contains(root)) {
				  clearInterval(root.__spVpTimer);
				  root.__spVpTimer = null;
				  return;
				}
				sync();
			  }, 500);
			}
		  }

		  function scanAndInit(){
			$('.sp-vp').each(function(){
			  initOne(this);
			});
		  }

		  let scanTimer = null;

		  function scheduleScan(){
			if (scanTimer) {
			  clearTimeout(scanTimer);
			}
			scanTimer = setTimeout(scanAndInit, 50);
		  }

		  $(scanAndInit);
		  $(document).ajaxComplete(scheduleScan);

		  if (window.MutationObserver && document.body) {
			const observer = new MutationObserver(function(){
			  scheduleScan();
			});
			observer.observe(document.body, { childList: true, subtree: true });
		  }

		  $(document).on('click', '.sp-vp__capture', function(){
			const $btn = $(this);
			const $root = $btn.closest('.sp-vp');
			const video = resolveVideoElement($root);
			const attachmentId = Number($root.data('attachment-id')) || 0;

			if (!attachmentId || !video || !ensureVideoReady(video)) {
			  setStatus($root, 'Video metadata is not ready yet. Press play once and try again.', 'error');
			  return;
			}

			const canvas = document.createElement('canvas');
			canvas.width = video.videoWidth;
			canvas.height = video.videoHeight;

			const ctx = canvas.getContext('2d');
			if (!ctx) {
			  setStatus($root, i18n('error', 'Error'), 'error');
			  return;
			}

			setBusy($root, true);
			setStatus($root, i18n('processing', 'Processing...'));

			try {
			  ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
			} catch (e) {
			  setBusy($root, false);
			  setStatus($root, 'Unable to draw frame from this video.', 'error');
			  return;
			}

			canvas.toBlob(function(blob){
			  if (!blob) {
				setBusy($root, false);
				setStatus($root, i18n('error', 'Error'), 'error');
				return;
			  }

			  const reader = new FileReader();

			  reader.onload = function(){
				sendAction('sp_video_preview_save_frame', {
				  attachment_id: attachmentId,
				  image_data: String(reader.result || '')
				}).done(function(resp){
				  if (resp && resp.success && resp.data) {
					updatePosterUI($root, resp.data.poster_id || 0, resp.data.poster_url || '');
					setStatus($root, 'Poster saved.', 'ok');
				  } else {
					setStatus($root, (resp && resp.data && resp.data.message) ? resp.data.message : i18n('error', 'Error'), 'error');
				  }
				}).fail(function(){
				  setStatus($root, i18n('error', 'Error'), 'error');
				}).always(function(){
				  setBusy($root, false);
				});
			  };

			  reader.onerror = function(){
				setBusy($root, false);
				setStatus($root, i18n('error', 'Error'), 'error');
			  };

			  reader.readAsDataURL(blob);
			}, 'image/jpeg', 0.92);
		  });

		  $(document).on('click', '.sp-vp__choose', function(){
			const $btn = $(this);
			const $root = $btn.closest('.sp-vp');
			const attachmentId = Number($root.data('attachment-id')) || 0;

			if (!attachmentId || !window.wp || !wp.media) {
			  setStatus($root, i18n('error', 'Error'), 'error');
			  return;
			}

			const frame = wp.media({
			  title: i18n('selectImage', 'Select poster image'),
			  button: { text: i18n('useImage', 'Use this image') },
			  library: { type: 'image' },
			  multiple: false
			});

			frame.on('select', function(){
			  const selected = frame.state().get('selection').first();
			  if (!selected) {
				return;
			  }

			  const data = selected.toJSON();
			  const posterId = Number(data.id) || 0;

			  if (!posterId) {
				setStatus($root, i18n('error', 'Error'), 'error');
				return;
			  }

			  setBusy($root, true);
			  setStatus($root, i18n('saving', 'Saving...'));

			  sendAction('sp_video_preview_set_existing', {
				attachment_id: attachmentId,
				poster_id: posterId
			  }).done(function(resp){
				if (resp && resp.success && resp.data) {
				  updatePosterUI($root, resp.data.poster_id || 0, resp.data.poster_url || '');
				  setStatus($root, 'Poster updated.', 'ok');
				} else {
				  setStatus($root, (resp && resp.data && resp.data.message) ? resp.data.message : i18n('error', 'Error'), 'error');
				}
			  }).fail(function(){
				setStatus($root, i18n('error', 'Error'), 'error');
			  }).always(function(){
				setBusy($root, false);
			  });
			});

			frame.open();
		  });

		  $(document).on('click', '.sp-vp__remove', function(){
			const $btn = $(this);
			const $root = $btn.closest('.sp-vp');
			const attachmentId = Number($root.data('attachment-id')) || 0;

			if (!attachmentId) {
			  return;
			}

			setBusy($root, true);
			setStatus($root, i18n('removing', 'Removing...'));

			sendAction('sp_video_preview_remove', {
			  attachment_id: attachmentId
			}).done(function(resp){
			  if (resp && resp.success) {
				updatePosterUI($root, 0, '');
				setStatus($root, 'Poster removed.', 'ok');
			  } else {
				setStatus($root, (resp && resp.data && resp.data.message) ? resp.data.message : i18n('error', 'Error'), 'error');
			  }
			}).fail(function(){
			  setStatus($root, i18n('error', 'Error'), 'error');
			}).always(function(){
			  setBusy($root, false);
			});
		  });
		})(jQuery);
		JS;
        }
    }

    SP_Video_Preview::init();

    if ( ! function_exists( 'sp_video_poster_id' ) ) {
        function sp_video_poster_id( $video ): int {
            return SP_Video_Preview::poster_id( $video );
        }
    }

    if ( ! function_exists( 'sp_video_poster_url' ) ) {
        function sp_video_poster_url( $video, string $size = 'full' ): string {
            return SP_Video_Preview::poster_url( $video, $size );
        }
    }
