<?php


//    Usage examples:
//
//    1. Default (All sources & displays allowed):
//    ->addField('media', 'sp_universal_media', [
//        'label' => 'Media',
//        'required' => 0,
//        'wrapper' => [
//            'width' => 50,
//        ],
//    ])
//
//    2. Restrict to specific sources and display modes:
//    ->addField('media', 'sp_universal_media', [
//        'label' => 'Media',
//        'sources' => ['library', 'youtube'],      // library, youtube, vimeo
//        'displays' => ['inline', 'fancybox'],     // inline, fancybox, background
//        'wrapper' => [
//            'width' => 50,
//        ],
//    ])

    if (! defined('ABSPATH')) {
        exit;
    }

    if (! function_exists('sp_universal_media_provider_from_url')) {
        function sp_universal_media_provider_from_url(string $url): string
        {
            $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
            $host = preg_replace('/^www\./', '', $host);

            if (in_array($host, ['youtu.be', 'youtube.com', 'm.youtube.com', 'music.youtube.com', 'youtube-nocookie.com'], true)) {
                return 'youtube';
            }

            if ($host === 'vimeo.com' || str_ends_with($host, '.vimeo.com')) {
                return 'vimeo';
            }

            return '';
        }
    }

    if (! function_exists('sp_universal_media_embed_url')) {
        function sp_universal_media_embed_url(string $url, string $provider, array $playback = []): string
        {
            $path = trim((string) wp_parse_url($url, PHP_URL_PATH), '/');
            $embed_url = '';

            if ($provider === 'youtube') {
                $host     = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
                $video_id = '';

                if (str_contains($host, 'youtu.be')) {
                    $video_id = explode('/', $path)[0] ?? '';
                } elseif (preg_match('#^(embed|shorts)/([^/]+)#', $path, $matches)) {
                    $video_id = $matches[2];
                } else {
                    parse_str((string) wp_parse_url($url, PHP_URL_QUERY), $query);
                    $video_id = (string) ($query['v'] ?? '');
                }

                if (preg_match('/^[A-Za-z0-9_-]{6,}$/', $video_id)) {
                    $embed_url = 'https://www.youtube-nocookie.com/embed/' . rawurlencode($video_id);
                    $params    = [
                            'autoplay'    => ! empty($playback['autoplay']) ? 1 : 0,
                            'mute'        => ! empty($playback['muted']) ? 1 : 0,
                            'loop'        => ! empty($playback['loop']) ? 1 : 0,
                            'controls'    => ! empty($playback['controls']) ? 1 : 0,
                            'playsinline' => ! empty($playback['playsinline']) ? 1 : 0,
                            'rel'         => 0,
                    ];

                    if (! empty($playback['loop'])) {
                        $params['playlist'] = $video_id;
                    }

                    return add_query_arg($params, $embed_url);
                }
            }

            if ($provider === 'vimeo' && preg_match('/(?:^|\/)(\d+)(?:\/([A-Za-z0-9]+))?(?:$|\/)/', '/' . $path, $matches)) {
                $embed_url = 'https://player.vimeo.com/video/' . rawurlencode($matches[1]);
                parse_str((string) wp_parse_url($url, PHP_URL_QUERY), $input_query);
                $privacy_hash = sanitize_text_field((string) ($input_query['h'] ?? ($matches[2] ?? '')));
                $params = [];

                if ($privacy_hash !== '') {
                    $params['h'] = $privacy_hash;
                }

                $params = array_merge($params, [
                        'autoplay'    => ! empty($playback['autoplay']) ? 1 : 0,
                        'muted'       => ! empty($playback['muted']) ? 1 : 0,
                        'loop'        => ! empty($playback['loop']) ? 1 : 0,
                        'controls'    => ! empty($playback['controls']) ? 1 : 0,
                        'playsinline' => ! empty($playback['playsinline']) ? 1 : 0,
                        'dnt'         => 1,
                ]);

                return add_query_arg($params, $embed_url);
            }

            return '';
        }
    }

    if (! function_exists('sp_universal_media_settings')) {
        function sp_universal_media_settings(array $value): array
        {
            return [
                    'autoplay'    => ! empty($value['autoplay']),
                    'muted'       => ! empty($value['muted']),
                    'loop'        => ! empty($value['loop']),
                    'controls'    => ! array_key_exists('controls', $value) || ! empty($value['controls']),
                    'playsinline' => ! array_key_exists('playsinline', $value) || ! empty($value['playsinline']),
                    'custom_play' => ! array_key_exists('custom_play', $value) || ! empty($value['custom_play']),
            ];
        }
    }

    if (! function_exists('sp_universal_media_youtube_preview_url')) {
        function sp_universal_media_youtube_preview_url(string $url): string
        {
            $embed_url = sp_universal_media_embed_url($url, 'youtube');
            $path      = trim((string) wp_parse_url($embed_url, PHP_URL_PATH), '/');
            $video_id  = basename($path);

            return $video_id !== '' ? 'https://i.ytimg.com/vi/' . rawurlencode($video_id) . '/hqdefault.jpg' : '';
        }
    }

    if (! function_exists('sp_get_universal_media')) {
        function sp_get_universal_media($value): array
        {
            if (! is_array($value)) {
                return [];
            }

            $source  = sanitize_key((string) ($value['source'] ?? 'library'));
            $display = sanitize_key((string) ($value['display'] ?? 'inline'));
            $display = in_array($display, ['inline', 'fancybox', 'background'], true) ? $display : 'inline';
            $shared  = [
                    'display'    => $display,
                    'poster_id'  => absint($value['poster_id'] ?? 0),
                    'playback'   => sp_universal_media_settings($value),
            ];

            if ($source === 'library') {
                $attachment_id = absint($value['attachment_id'] ?? 0);
                $url           = $attachment_id ? wp_get_attachment_url($attachment_id) : '';

                if (! $attachment_id || ! $url) {
                    return [];
                }

                $mime       = (string) get_post_mime_type($attachment_id);
                $media_type = str_starts_with($mime, 'video/') ? 'video' : (str_starts_with($mime, 'image/') ? 'image' : '');

                if ($media_type === '') {
                    return [];
                }

                return array_merge($shared, [
                        'source'        => 'library',
                        'attachment_id' => $attachment_id,
                        'url'           => $url,
                        'mime_type'     => $mime,
                        'media_type'    => $media_type,
                        'alt'           => (string) get_post_meta($attachment_id, '_wp_attachment_image_alt', true),
                        'title'         => (string) get_the_title($attachment_id),
                ]);
            }

            $url      = esc_url_raw((string) ($value['url'] ?? ''));
            $provider = sp_universal_media_provider_from_url($url);
            $embed    = $provider ? sp_universal_media_embed_url($url, $provider, $shared['playback']) : '';

            if (! $url || ! $provider || ! $embed) {
                return [];
            }

            return array_merge($shared, [
                    'source'     => $provider,
                    'url'        => $url,
                    'embed_url'  => $embed,
                    'media_type' => 'embed',
            ]);
        }
    }

    if (! function_exists('display_universal_media')) {
        /**
         * Render the Universal Media ACF field value.
         *
         * Example: display_universal_media(get_sub_field('media'), ['class' => 'hero__media']);
         */
        function display_universal_media($value, array $args = []): void
        {
            $media = sp_get_universal_media($value);

            if (empty($media)) {
                return;
            }

            $args = wp_parse_args($args, [
                    'class'       => '',
                    'loading'     => 'lazy',
                    'button_text' => __('Play video', 'LDW'),
            ]);

            $extra_classes = preg_split('/\s+/', trim((string) $args['class'])) ?: [];
            $extra_classes = array_filter(array_map('sanitize_html_class', $extra_classes));
            $class         = trim('universal-media ' . implode(' ', $extra_classes));
            $playback      = $media['playback'];

            foreach (['autoplay', 'muted', 'loop', 'controls', 'playsinline', 'custom_play'] as $setting) {
                if (array_key_exists($setting, $args)) {
                    $playback[$setting] = (bool) $args[$setting];
                }
            }

            $poster_id  = array_key_exists('poster_id', $args) ? absint($args['poster_id']) : absint($media['poster_id']);
            $poster_url = $poster_id ? wp_get_attachment_image_url($poster_id, 'full') : '';
            $embed_url  = $media['media_type'] === 'embed'
                    ? sp_universal_media_embed_url($media['url'], $media['source'], $playback)
                    : '';
            $video_attributes = ($playback['controls'] ? ' controls' : '')
                                . ($playback['autoplay'] ? ' autoplay' : '')
                                . ($playback['muted'] ? ' muted' : '')
                                . ($playback['loop'] ? ' loop' : '')
                                . ($playback['playsinline'] ? ' playsinline' : '');
            $uses_custom_play = $media['display'] === 'inline'
                                && $media['media_type'] === 'video'
                                && ! empty($playback['custom_play'])
                                && empty($playback['autoplay']);
            $inline_video_attributes = $uses_custom_play
                    ? ($playback['muted'] ? ' muted' : '')
                      . ($playback['loop'] ? ' loop' : '')
                      . ($playback['playsinline'] ? ' playsinline' : '')
                    : $video_attributes;
            $popup_video_attributes = ($playback['controls'] ? ' controls' : '')
                                      . ($playback['muted'] ? ' muted' : '')
                                      . ($playback['loop'] ? ' loop' : '')
                                      . ($playback['playsinline'] ? ' playsinline' : '')
                                      . ($playback['autoplay'] ? ' data-sp-autoplay="true"' : '');

            if ($media['display'] === 'background') {
                $class = trim($class . ' universal-media--background');
                echo '<div class="' . esc_attr($class) . '">';

                if ($media['media_type'] === 'image') {
                    echo wp_get_attachment_image($media['attachment_id'], 'full', false, [
                            'class'   => 'universal-media__background-item universal-media__image',
                            'loading' => $args['loading'] === 'eager' ? 'eager' : 'lazy',
                    ]);
                } elseif ($media['media_type'] === 'video') {
                    echo '<video class="universal-media__background-item universal-media__video"'
                         . $video_attributes
                         . ($poster_url ? ' poster="' . esc_url($poster_url) . '"' : '')
                         . ' preload="metadata"><source src="' . esc_url($media['url']) . '" type="' . esc_attr($media['mime_type']) . '"></video>';
                } else {
                    echo '<iframe class="universal-media__background-item" src="' . esc_url($embed_url) . '" title="' . esc_attr(ucfirst($media['source']) . ' background video') . '" loading="lazy" allow="autoplay; fullscreen; picture-in-picture"></iframe>';
                }

                echo '</div>';
                return;
            }

            if ($media['display'] === 'fancybox') {
                $fancybox_id = 'universal-media-' . wp_unique_id();
                $popup_id    = 'universal-media-popup-' . wp_unique_id();
                $is_video    = $media['media_type'] !== 'image';
                $preview_url = $poster_url;

                if (! $preview_url && $media['source'] === 'youtube') {
                    $preview_url = sp_universal_media_youtube_preview_url($media['url']);
                }

                if ($media['media_type'] === 'image') {
                    $href      = $media['url'];
                    $data_type = '';
                } elseif ($media['media_type'] === 'video') {
                    $href      = '#' . $popup_id;
                    $data_type = 'inline';
                } else {
                    $href      = $embed_url;
                    $data_type = 'iframe';
                }

                echo '<a class="' . esc_attr($class . ' universal-media--trigger') . '" href="' . esc_url($href) . '"'
                     . ' data-fancybox="' . esc_attr($fancybox_id) . '"'
                     . ($data_type !== '' ? ' data-type="' . esc_attr($data_type) . '"' : '')
                     . ($is_video ? ' aria-label="' . esc_attr((string) $args['button_text']) . '"' : '') . '>';

                if ($media['media_type'] === 'image') {
                    echo wp_get_attachment_image($media['attachment_id'], 'full', false, [
                            'class'   => 'universal-media__image',
                            'loading' => $args['loading'] === 'eager' ? 'eager' : 'lazy',
                    ]);
                } elseif ($preview_url) {
                    echo '<img class="universal-media__image universal-media__poster" src="' . esc_url($preview_url) . '" alt="" loading="' . esc_attr($args['loading']) . '">';
                } else {
                    echo '<span class="universal-media__placeholder"></span>';
                }

                if ($is_video) {
                    echo '<span class="universal-media__play" aria-hidden="true"><span></span></span>';
                }

                echo '</a>';

                if ($media['media_type'] === 'video') {
                    echo '<div class="universal-media__popup" id="' . esc_attr($popup_id) . '">';
                    echo '<video class="universal-media__popup-video"' . $popup_video_attributes
                         . ($poster_url ? ' poster="' . esc_url($poster_url) . '"' : '')
                         . ' preload="none"><source src="' . esc_url($media['url']) . '" type="' . esc_attr($media['mime_type']) . '"></video>';
                    echo '</div>';
                }

                return;
            }

            $class = $uses_custom_play ? trim($class . ' universal-media--inline-player') : $class;
            echo '<div class="' . esc_attr($class) . '"' . ($uses_custom_play ? ' data-sp-inline-player' : '') . '>';

            if ($media['media_type'] === 'image') {
                echo wp_get_attachment_image($media['attachment_id'], 'full', false, [
                        'class'   => 'universal-media__image',
                        'loading' => $args['loading'] === 'eager' ? 'eager' : 'lazy',
                ]);
            } elseif ($media['media_type'] === 'video') {
                echo '<video class="universal-media__video"'
                     . $inline_video_attributes
                     . ($poster_url ? ' poster="' . esc_url($poster_url) . '"' : '')
                     . ($uses_custom_play ? ' data-sp-inline-video' : '')
                     . ' preload="' . ($uses_custom_play ? 'none' : 'metadata') . '"><source src="' . esc_url($media['url']) . '" type="' . esc_attr($media['mime_type']) . '"></video>';

                if ($uses_custom_play) {
                    if ($poster_url) {
                        echo '<img class="universal-media__inline-poster" src="' . esc_url($poster_url) . '" alt="" data-sp-inline-poster>';
                    }

                    echo '<button class="universal-media__play universal-media__play--button" type="button" data-sp-inline-play aria-label="' . esc_attr((string) $args['button_text']) . '"><span></span></button>';
                }
            } else {
                echo '<div class="universal-media__embed">';
                echo '<iframe src="' . esc_url($embed_url) . '" title="' . esc_attr(ucfirst($media['source']) . ' video') . '" loading="lazy" allow="autoplay; fullscreen; picture-in-picture"></iframe>';
                echo '</div>';
            }

            echo '</div>';
        }
    }

    if (! class_exists('SP_ACF_Field_Universal_Media') && class_exists('acf_field')) {
        class SP_ACF_Field_Universal_Media extends acf_field
        {
            public function initialize(): void
            {
                $this->name     = 'sp_universal_media';
                $this->label    = __('Universal Media', 'wardlaw');
                $this->category = 'content';
                $this->defaults = [
                        'sources'  => ['library', 'youtube', 'vimeo'],
                        'displays' => ['inline', 'fancybox', 'background'],
                ];
            }

            public function render_field_settings($field)
            {
                acf_render_field_setting($field, [
                        'label'        => __('Allowed Sources', 'wardlaw'),
                        'instructions' => '',
                        'type'         => 'checkbox',
                        'name'         => 'sources',
                        'choices'      => [
                                'library' => __('Media Library', 'wardlaw'),
                                'youtube' => 'YouTube',
                                'vimeo'   => 'Vimeo',
                        ],
                        'layout'       => 'horizontal',
                ]);

                acf_render_field_setting($field, [
                        'label'        => __('Allowed Display Modes', 'wardlaw'),
                        'instructions' => '',
                        'type'         => 'checkbox',
                        'name'         => 'displays',
                        'choices'      => [
                                'inline'     => __('Inline', 'wardlaw'),
                                'fancybox'   => __('Fancybox', 'wardlaw'),
                                'background' => __('Background', 'wardlaw'),
                        ],
                        'layout'       => 'horizontal',
                ]);
            }

            public function render_field($field): void
            {
                $value         = is_array($field['value']) ? $field['value'] : [];
                $sources       = ! empty($field['sources']) ? (array) $field['sources'] : ['library', 'youtube', 'vimeo'];
                $displays      = ! empty($field['displays']) ? (array) $field['displays'] : ['inline', 'fancybox', 'background'];

                $source        = sanitize_key((string) ($value['source'] ?? ''));
                if (! in_array($source, $sources, true)) {
                    $source = reset($sources) ?: 'library';
                }

                $display       = sanitize_key((string) ($value['display'] ?? ''));
                if (! in_array($display, $displays, true)) {
                    $display = reset($displays) ?: 'inline';
                }

                $attachment_id = absint($value['attachment_id'] ?? 0);
                $url           = (string) ($value['url'] ?? '');
                $preview_url   = $attachment_id ? wp_get_attachment_image_url($attachment_id, 'full') : '';
                $file_name     = $attachment_id ? basename((string) get_attached_file($attachment_id)) : '';
                $is_video      = $attachment_id && str_starts_with((string) get_post_mime_type($attachment_id), 'video/');
                $poster_id     = absint($value['poster_id'] ?? 0);
                $poster_url    = $poster_id ? wp_get_attachment_image_url($poster_id, 'full') : '';
                $playback      = sp_universal_media_settings($value);
                $display_name  = $field['name'] . '[display]';
                $has_sources   = count($sources) > 1;
                $has_displays  = count($displays) > 1;
                $tabs_class    = $has_sources && $has_displays ? ' has-two-groups' : ' has-one-group';
                ?>
				<div class="sp-universal-media sp-admin-component sp-acf-component" data-sp-universal-media data-sp-admin-component="universal-media" aria-busy="false">
					<input type="hidden" name="<?php echo esc_attr($field['name']); ?>[source]" value="<?php echo esc_attr($source); ?>" data-sp-media-source>
					<span class="screen-reader-text" data-sp-media-status aria-live="polite" aria-atomic="true"></span>

					<?php if ($has_sources || $has_displays) : ?>
						<div class="sp-universal-media__tabs<?php echo esc_attr($tabs_class); ?>">
							<?php if ($has_sources) : ?>
								<div class="sp-universal-media__sources" role="group" aria-label="<?php esc_attr_e('Media source', 'wardlaw'); ?>" style="--sp-media-control-count: <?php echo esc_attr((string) count($sources)); ?>;">
									<?php if (in_array('library', $sources, true)) : ?>
										<button type="button" class="sp-universal-media__tab" data-sp-media-source-option="library" aria-pressed="<?php echo $source === 'library' ? 'true' : 'false'; ?>">
											<span class="dashicons dashicons-format-image" aria-hidden="true"></span>
											<?php esc_html_e('Media', 'wardlaw'); ?>
										</button>
									<?php endif; ?>
									<?php if (in_array('youtube', $sources, true)) : ?>
										<button type="button" class="sp-universal-media__tab" data-sp-media-source-option="youtube" aria-pressed="<?php echo $source === 'youtube' ? 'true' : 'false'; ?>">YouTube</button>
									<?php endif; ?>
									<?php if (in_array('vimeo', $sources, true)) : ?>
										<button type="button" class="sp-universal-media__tab" data-sp-media-source-option="vimeo" aria-pressed="<?php echo $source === 'vimeo' ? 'true' : 'false'; ?>">Vimeo</button>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($has_displays) : ?>
                                <div class="sp-universal-media__display">
									<div class="sp-universal-media__toggle" role="group" style="--sp-media-control-count: <?php echo esc_attr((string) count($displays)); ?>;" aria-label="<?php esc_attr_e('Display mode', 'wardlaw'); ?>">
                                        <?php if (in_array('inline', $displays, true)) : ?>
                                            <label>
                                                <input type="radio" name="<?php echo esc_attr($display_name); ?>" value="inline" <?php checked($display === 'inline'); ?>>
                                                <span><?php esc_html_e('Inline', 'wardlaw'); ?></span>
                                            </label>
                                        <?php endif; ?>
                                        <?php if (in_array('fancybox', $displays, true)) : ?>
                                            <label>
                                                <input type="radio" name="<?php echo esc_attr($display_name); ?>" value="fancybox" <?php checked($display === 'fancybox'); ?>>
                                                <span><?php esc_html_e('Fancybox', 'wardlaw'); ?></span>
                                            </label>
                                        <?php endif; ?>
                                        <?php if (in_array('background', $displays, true)) : ?>
                                            <label>
                                                <input type="radio" name="<?php echo esc_attr($display_name); ?>" value="background" <?php checked($display === 'background'); ?>>
                                                <span><?php esc_html_e('Background', 'wardlaw'); ?></span>
                                            </label>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php else : ?>
                                <input type="hidden" name="<?php echo esc_attr($display_name); ?>" value="<?php echo esc_attr($display); ?>">
                            <?php endif; ?>
                        </div>
                    <?php else : ?>
                        <input type="hidden" name="<?php echo esc_attr($display_name); ?>" value="<?php echo esc_attr($display); ?>">
                    <?php endif; ?>

                    <div class="sp-universal-media__panel" data-sp-media-panel="library">
                        <input type="hidden" name="<?php echo esc_attr($field['name']); ?>[attachment_id]" value="<?php echo esc_attr((string) $attachment_id); ?>" data-sp-media-id>
                        <input type="hidden" value="<?php echo $is_video ? 'video' : 'image'; ?>" data-sp-media-type>
                        <div class="sp-universal-media__picker<?php echo $attachment_id ? ' is-filled' : ''; ?>" data-sp-media-preview>
                            <?php if ($preview_url && ! $is_video) : ?>
                                <img src="<?php echo esc_url($preview_url); ?>" alt="">
                            <?php elseif ($attachment_id) : ?>
                                <span class="sp-universal-media__file">
									<span class="dashicons dashicons-format-video" aria-hidden="true"></span>
								<?php echo esc_html($file_name); ?>
							</span>
                            <?php else : ?>
                                <span class="sp-universal-media__empty">
									<span class="dashicons dashicons-upload" aria-hidden="true"></span>
								<span><?php esc_html_e('Image or video from Media Library', 'wardlaw'); ?></span>
							</span>
                            <?php endif; ?>
                        </div>
                        <div class="sp-universal-media__actions">
                            <button type="button" class="button button-primary" data-sp-media-select>
                                <?php echo $attachment_id ? esc_html__('Replace media', 'wardlaw') : esc_html__('Select media', 'wardlaw'); ?>
                            </button>
                            <button type="button" class="button-link-delete<?php echo $attachment_id ? '' : ' is-hidden'; ?>" data-sp-media-remove><?php esc_html_e('Remove', 'wardlaw'); ?></button>
                        </div>
                    </div>

                    <div class="sp-universal-media__panel sp-universal-media__remote" data-sp-media-panel="remote">
                        <label class="sp-universal-media__url">
                            <span class="sp-universal-media__label" data-sp-media-url-label><?php esc_html_e('Video URL', 'wardlaw'); ?></span>
                            <input type="url" name="<?php echo esc_attr($field['name']); ?>[url]" value="<?php echo esc_attr($url); ?>" placeholder="https://www.youtube.com/watch?v=..." data-sp-media-url>
                        </label>
                        <p class="description" data-sp-media-url-help><?php esc_html_e('Paste a public video link. It will be embedded automatically.', 'wardlaw'); ?></p>
                    </div>

                    <div class="sp-universal-media__advanced" data-sp-media-advanced>
                        <div class="sp-universal-media__poster" data-sp-media-poster-panel>
                            <span class="sp-universal-media__label"><?php esc_html_e('Cover image', 'wardlaw'); ?></span>
                            <p class="description" data-sp-poster-help><?php echo esc_html($is_video ? __('Required for uploaded video.', 'wardlaw') : __('Shown as the clickable preview before the video opens.', 'wardlaw')); ?></p>
                            <input type="hidden" name="<?php echo esc_attr($field['name']); ?>[poster_id]" value="<?php echo esc_attr((string) $poster_id); ?>" data-sp-poster-id>
                            <div class="sp-universal-media__poster-preview<?php echo $poster_url ? ' is-filled' : ''; ?>" data-sp-poster-preview>
                                <?php if ($poster_url) : ?>
                                    <img src="<?php echo esc_url($poster_url); ?>" alt="">
                                <?php else : ?>
                                    <span data-sp-poster-empty><?php echo esc_html($is_video ? __('Required cover image', 'wardlaw') : __('Optional cover image', 'wardlaw')); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="sp-universal-media__actions">
                                <button type="button" class="button" data-sp-poster-select>
                                    <?php echo $poster_id ? esc_html__('Replace cover', 'wardlaw') : esc_html__('Select cover', 'wardlaw'); ?>
                                </button>
                                <button type="button" class="button-link-delete<?php echo $poster_id ? '' : ' is-hidden'; ?>" data-sp-poster-remove><?php esc_html_e('Remove', 'wardlaw'); ?></button>
                            </div>
                        </div>

                        <div class="sp-universal-media__playback" data-sp-media-playback>
                            <span class="sp-universal-media__label"><?php esc_html_e('Playback', 'wardlaw'); ?></span>
                            <div class="sp-universal-media__checks">
                                <?php
                                    $options = [
                                            'autoplay'    => __('Autoplay', 'wardlaw'),
                                            'muted'       => __('Muted', 'wardlaw'),
                                            'loop'        => __('Loop', 'wardlaw'),
                                            'controls'    => __('Controls', 'wardlaw'),
                                            'playsinline' => __('Play inline on mobile', 'wardlaw'),
                                            'custom_play' => __('Custom play button (Inline)', 'wardlaw'),
                                    ];
                                    foreach ($options as $option => $label) :
                                        ?>
                                        <label class="sp-universal-media__check"<?php echo $option === 'custom_play' ? ' data-sp-custom-play-option' : ''; ?>>
                                            <input type="hidden" name="<?php echo esc_attr($field['name'] . '[' . $option . ']'); ?>" value="0">
                                            <input type="checkbox" name="<?php echo esc_attr($field['name'] . '[' . $option . ']'); ?>" value="1" <?php checked(! empty($playback[$option])); ?>>
                                            <span><?php echo esc_html($label); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                            </div>
                            <p class="description"><?php esc_html_e('Browsers normally require Muted for Autoplay.', 'wardlaw'); ?></p>
                        </div>
                    </div>

                </div>
                <?php
            }

            public function update_value($value, $post_id, $field)
            {
                $value   = is_array($value) ? $value : [];
                $source  = sanitize_key((string) ($value['source'] ?? 'library'));
                $display = sanitize_key((string) ($value['display'] ?? 'inline'));
                $display = in_array($display, ['inline', 'fancybox', 'background'], true) ? $display : 'inline';
                $options = [
                        'display'     => $display,
                        'poster_id'   => absint($value['poster_id'] ?? 0),
                        'autoplay'    => ! empty($value['autoplay']) ? 1 : 0,
                        'muted'       => ! empty($value['muted']) ? 1 : 0,
                        'loop'        => ! empty($value['loop']) ? 1 : 0,
                        'controls'    => ! empty($value['controls']) ? 1 : 0,
                        'playsinline' => ! empty($value['playsinline']) ? 1 : 0,
                        'custom_play' => ! empty($value['custom_play']) ? 1 : 0,
                ];

                if ($source === 'library') {
                    $attachment_id = absint($value['attachment_id'] ?? 0);
                    return $attachment_id ? array_merge([
                            'source'        => 'library',
                            'attachment_id' => $attachment_id,
                    ], $options) : '';
                }

                $url = esc_url_raw((string) ($value['url'] ?? ''));

                return $url ? array_merge([
                        'source'  => $source === 'vimeo' ? 'vimeo' : 'youtube',
                        'url'     => $url,
                ], $options) : '';
            }

            public function validate_value($valid, $value, $field, $input)
            {
                if ($valid !== true) {
                    return $valid;
                }

                $value  = is_array($value) ? $value : [];
                $source = sanitize_key((string) ($value['source'] ?? 'library'));
                $poster_id = absint($value['poster_id'] ?? 0);

                if ($poster_id && ! str_starts_with((string) get_post_mime_type($poster_id), 'image/')) {
                    return __('Please select an image file for the cover.', 'wardlaw');
                }

                if ($source === 'library') {
                    $attachment_id = absint($value['attachment_id'] ?? 0);

                    if (! $attachment_id && ! empty($field['required'])) {
                        return __('Please select an image or video.', 'wardlaw');
                    }

                    if ($attachment_id) {
                        $mime = (string) get_post_mime_type($attachment_id);
                        if (! str_starts_with($mime, 'image/') && ! str_starts_with($mime, 'video/')) {
                            return __('Please select an image or video file.', 'wardlaw');
                        }

                        if (str_starts_with($mime, 'video/') && ! $poster_id) {
                            return __('Please select a cover image for the uploaded video.', 'wardlaw');
                        }
                    }

                    return $valid;
                }

                $url = esc_url_raw((string) ($value['url'] ?? ''));
                if ($url === '') {
                    return ! empty($field['required']) ? __('Please enter a video URL.', 'wardlaw') : $valid;
                }

                if (sp_universal_media_provider_from_url($url) !== $source || sp_universal_media_embed_url($url, $source) === '') {
                    return $source === 'youtube'
                            ? __('Please enter a valid YouTube video URL.', 'wardlaw')
                            : __('Please enter a valid Vimeo video URL.', 'wardlaw');
                }

                return $valid;
            }

            public function input_admin_enqueue_scripts(): void
            {
                wp_enqueue_media();
            }

            public function input_admin_head(): void
            {
                ?>
				<style id="sp-acf-universal-media-css">
					.sp-universal-media {
						--sp-media-border: var(--sp-acf-border, #d0d5dd);
						--sp-media-brand: var(--sp-acf-accent, #3858e9);
						--sp-media-soft: var(--sp-acf-surface-soft, #f7f8fc);
						background: var(--sp-acf-surface, #fff);
						border: 1px solid var(--sp-media-border);
						border-radius: 0;
						box-shadow: var(--sp-admin-shadow-xs, 0 1px 2px rgb(26 31 36 / 4%));
						box-sizing: border-box;
						color: var(--sp-acf-text);
                        container-type: inline-size;
                        max-width: 100%;
                        min-width: 0;
                        overflow: hidden;
                        width: 100%;
                    }

                    .sp-universal-media *,
                    .sp-universal-media *::before,
                    .sp-universal-media *::after {
                        box-sizing: border-box;
                    }

					.sp-universal-media__tabs {
						align-items: stretch;
						background: var(--sp-acf-surface-subtle);
                        border-bottom: 1px solid var(--sp-media-border);
                        display: flex;
                        flex-wrap: wrap;
                        gap: 8px 16px;
                        justify-content: space-between;
                        padding: 16px;
                    }

                    .sp-universal-media__sources {
                        display: grid;
                        flex: 1 1 100%;
                        grid-template-columns: repeat(var(--sp-media-control-count, 3), minmax(0, 1fr));
                        gap: 4px;
                        justify-content: space-between;
                        min-width: 0;
                        width: 100%;
                    }

                    .sp-universal-media__tabs.has-two-groups .sp-universal-media__sources,
                    .sp-universal-media__tabs.has-two-groups .sp-universal-media__display {
                        flex: 0 1 calc(50% - 8px);
                        width: auto;
                    }

                    .sp-universal-media__tabs.has-one-group .sp-universal-media__sources,
                    .sp-universal-media__tabs.has-one-group .sp-universal-media__display {
                        flex: 1 1 100%;
                        width: 100%;
                    }

					.sp-universal-media__sources,
					.sp-universal-media__toggle {
						background: var(--sp-acf-border);
                        border-radius: 0;
                        padding: 3px;
                    }

                    .sp-universal-media__tab,
                    .sp-universal-media__toggle span {
                        align-items: center;
                        background: transparent;
                        border: 0;
                        border-radius: 0;
						color: var(--sp-acf-text-2);
                        cursor: pointer;
                        display: inline-flex;
                        font-size: 13px;
                        font-weight: 600;
                        gap: 5px;
                        justify-content: center;
                        line-height: 1.2;
                        min-height: 34px;
                        min-width: 0;
                        padding: 0 14px;
                        text-align: center;
						transition: background var(--sp-acf-transition), color var(--sp-acf-transition), box-shadow var(--sp-acf-transition), transform var(--sp-acf-transition);
                        white-space: normal;
                        width: 100%;
                    }

                    .sp-universal-media__tab .dashicons {
                        font-size: 16px;
                        height: 16px;
                        width: 16px;
                    }

					.sp-universal-media__tab:hover,
					.sp-universal-media__toggle span:hover {
						color: var(--sp-acf-accent-hover);
                    }

					.sp-universal-media__tab.is-active,
					.sp-universal-media__toggle input:checked + span {
						background: var(--sp-acf-surface);
						box-shadow: 0 1px 3px rgba(16, 24, 40, .1);
						color: var(--sp-media-brand);
					}

					.sp-universal-media__tab:focus-visible,
					.sp-universal-media__toggle input:focus-visible + span {
						box-shadow: var(--sp-acf-focus);
						outline: 0;
						position: relative;
						z-index: 1;
					}

					.sp-universal-media__tab:active,
					.sp-universal-media__toggle label:active span {
						box-shadow: inset 0 1px 2px rgb(26 31 36 / 18%);
						transform: translateY(1px);
					}

					.sp-universal-media__tab:disabled,
					.sp-universal-media__toggle input:disabled + span,
					.sp-universal-media__check input:disabled + span {
						cursor: not-allowed;
						opacity: .5;
						transform: none;
					}

                    .sp-universal-media__panel {
                        min-width: 0;
                        padding: 16px;
                    }

					.sp-universal-media__picker {
						align-items: center;
						background: var(--sp-media-soft);
						border: 1px dashed var(--sp-acf-border-strong);
                        border-radius: 0;
                        display: flex;
                        justify-content: center;
                        margin-bottom: 12px;
                        min-height: 122px;
                        min-width: 0;
						overflow: hidden;
						padding: 12px;
						transition: background var(--sp-acf-transition), border-color var(--sp-acf-transition), opacity var(--sp-acf-transition);
					}

					.sp-universal-media__picker.is-filled {
						background: var(--sp-acf-surface-subtle);
						border-color: var(--sp-acf-border);
						border-style: solid;
                    }

					.sp-universal-media__picker img {
						border-radius: 0;
						display: block;
						max-width: 100%;
                        object-fit: contain;
                    }

					.sp-universal-media__empty,
					.sp-universal-media__file {
						align-items: center;
						color: var(--sp-acf-text-2);
                        display: flex;
                        flex-direction: column;
                        font-size: 13px;
                        gap: 7px;
                        min-width: 0;
                        text-align: center;
                    }

                    .sp-universal-media__empty span:last-child,
                    .sp-universal-media__file span:last-child {
                        overflow-wrap: anywhere;
                    }

					.sp-universal-media__empty .dashicons {
						color: var(--sp-acf-muted);
                        font-size: 27px;
                        height: 27px;
                        width: 27px;
                    }

                    .sp-universal-media__file .dashicons {
                        color: var(--sp-media-brand);
                        font-size: 30px;
                        height: 30px;
                        width: 30px;
                    }

                    .sp-universal-media__actions {
                        align-items: center;
                        display: flex;
                        flex-wrap: wrap;
                        gap: 8px;
                    }

					.sp-universal-media__actions .button {
						border-radius: 0;
						border-color: var(--sp-acf-border-strong);
						background: var(--sp-acf-surface);
						box-shadow: var(--sp-admin-shadow-xs, 0 1px 2px rgb(26 31 36 / 4%));
						color: var(--sp-acf-text);
						display: inline-flex;
						align-items: center;
						justify-content: center;
						gap: 7px;
						line-height: 32px;
						min-height: 34px;
						min-width: 0;
						padding: 0 12px;
						transition: background var(--sp-acf-transition), border-color var(--sp-acf-transition), box-shadow var(--sp-acf-transition), color var(--sp-acf-transition), transform var(--sp-acf-transition);
					}

					.sp-universal-media__actions .button:hover {
						border-color: var(--sp-acf-accent);
						background: var(--sp-acf-accent-soft);
						color: var(--sp-acf-accent-hover);
					}

					.sp-universal-media__actions .button-primary,
					.sp-universal-media__actions .button-primary:hover {
						border-color: var(--sp-acf-accent);
						background: linear-gradient(180deg, var(--sp-acf-accent-bright), var(--sp-acf-accent));
						color: var(--color-on-accent, #fff);
					}

					.sp-universal-media__actions .button-primary:hover {
						border-color: var(--sp-acf-accent-hover);
						background: var(--sp-acf-accent-hover);
					}

					.sp-universal-media__actions .button:focus-visible {
						box-shadow: var(--sp-acf-focus);
						outline: 0;
					}

					.sp-universal-media__actions .button:active {
						box-shadow: inset 0 1px 2px rgb(26 31 36 / 22%);
						transform: translateY(1px);
					}

					.sp-universal-media__actions .button:disabled,
					.sp-universal-media__actions .button.disabled,
					.sp-universal-media__actions .button[aria-disabled="true"] {
						cursor: not-allowed;
						opacity: .5;
						pointer-events: none;
						transform: none;
					}

					.sp-universal-media__actions .button[aria-busy="true"]::after {
						animation: sp-universal-media-spin .7s linear infinite;
						border: 2px solid currentColor;
						border-right-color: transparent;
						border-radius: 50%;
						content: "";
						height: 12px;
						width: 12px;
					}

					.sp-universal-media .button-link-delete {
						align-items: center;
						background-color: #fff3f2;
						border: 1px solid #f4bbb5;
						box-sizing: border-box;
						color: var(--sp-acf-danger) !important;
						cursor: pointer;
						display: inline-flex;
						font-size: 13px;
						font-weight: 600;
						justify-content: center;
						line-height: 32px;
						margin: 0;
						min-height: 34px;
						padding: 0 12px;
						text-decoration: none;
						transition: background var(--sp-acf-transition), border-color var(--sp-acf-transition), box-shadow var(--sp-acf-transition), color var(--sp-acf-transition), transform var(--sp-acf-transition);
						white-space: nowrap;
						-webkit-appearance: none;
						border-radius: 0;
					}

					.sp-universal-media .button-link-delete:hover {
						background-color: #fde8e6 !important;
						border-color: var(--sp-acf-danger);
						color: #a92e24 !important;
					}

					.sp-universal-media .button-link-delete:focus-visible {
						border-color: var(--sp-acf-danger);
						box-shadow: 0 0 0 3px rgb(231 76 60 / 18%);
						outline: 0;
					}

					.sp-universal-media .button-link-delete:active {
						background-color: #fbd6d2 !important;
						box-shadow: inset 0 1px 2px rgb(169 46 36 / 22%);
						transform: translateY(1px);
					}

					.sp-universal-media .button-link-delete:disabled,
					.sp-universal-media .button-link-delete[aria-disabled="true"] {
						cursor: not-allowed;
						opacity: .5;
						pointer-events: none;
						transform: none;
                    }

                    .sp-universal-media .button-link-delete.is-hidden {
                        display: none;
                    }

					.sp-universal-media__label {
						color: var(--sp-acf-text-2);
                        display: block;
                        font-size: 12px;
                        font-weight: 600;
                        letter-spacing: .01em;
                        margin-bottom: 6px;
                    }

					.sp-universal-media__url input[type="url"] {
						border-color: var(--sp-media-border);
						border-radius: 0;
						box-shadow: none;
						background: var(--sp-acf-input-bg);
						color: var(--sp-acf-text);
						height: 42px;
						padding: 0 12px;
						transition: background var(--sp-acf-transition), border-color var(--sp-acf-transition), box-shadow var(--sp-acf-transition);
						width: 100%;
					}

					.sp-universal-media__url input[type="url"]:focus-visible {
						border-color: var(--sp-media-brand);
						box-shadow: var(--sp-acf-focus);
						outline: 0;
					}

					.sp-universal-media__url input[type="url"]:invalid,
					.sp-universal-media__url input[type="url"][aria-invalid="true"] {
						border-color: var(--sp-acf-danger);
						box-shadow: 0 0 0 1px rgb(231 76 60 / 12%);
					}

					.sp-universal-media__url input[type="url"]:invalid:focus-visible,
					.sp-universal-media__url input[type="url"][aria-invalid="true"]:focus-visible {
						box-shadow: 0 0 0 3px rgb(231 76 60 / 18%);
					}

					.sp-universal-media__url input[type="url"]:disabled {
						background: var(--sp-acf-surface-subtle);
						color: var(--sp-acf-muted);
						cursor: not-allowed;
						opacity: .65;
					}

					.sp-universal-media__remote .description {
						color: var(--sp-acf-text-2);
						margin: 8px 0 0;
					}

					.sp-universal-media__remote:has(input:invalid) .description,
					.sp-universal-media__remote:has(input[aria-invalid="true"]) .description {
						color: var(--sp-acf-danger);
					}

					.sp-universal-media__advanced {
						background: var(--sp-acf-surface);
                        border-top: 1px solid var(--sp-media-border);
                        display: grid;
                        gap: 20px;
                        grid-template-columns: repeat(auto-fit, minmax(min(230px, 100%), 1fr));
                        min-width: 0;
                        padding: 16px;
                    }

                    .sp-universal-media__advanced[hidden],
                    .sp-universal-media__poster[hidden] {
                        display: none;
                    }

					.sp-universal-media__poster .description {
						color: var(--sp-acf-text-2);
                        margin: -2px 0 10px;
                    }

					.sp-universal-media__poster-preview {
						align-items: center;
						background: var(--sp-media-soft);
						border: 1px dashed var(--sp-acf-border-strong);
						color: var(--sp-acf-text-2);
                        display: flex;
                        font-size: 12px;
                        justify-content: center;
                        margin-bottom: 10px;
                        min-height: 76px;
						overflow: hidden;
						padding: 8px;
						transition: background var(--sp-acf-transition), border-color var(--sp-acf-transition), opacity var(--sp-acf-transition);
					}

					.sp-universal-media__poster-preview.is-filled {
						background: var(--sp-acf-surface-subtle);
						border-color: var(--sp-acf-border);
						border-style: solid;
                    }

                    .sp-universal-media__poster-preview img {
                        display: block;
                        max-height: 100px;
                        max-width: 100%;
                        object-fit: contain;
                    }

					.sp-universal-media__checks {
                        display: flex;
                        flex-wrap: wrap;
                        gap: 8px;
                    }

                    .sp-universal-media__check {
                        position: relative;
                    }

                    .sp-universal-media__check input[type="checkbox"] {
                        clip: rect(0 0 0 0);
                        clip-path: inset(50%);
                        height: 1px;
                        overflow: hidden;
                        position: absolute;
                        white-space: nowrap;
                        width: 1px;
                    }

					.sp-universal-media__check span {
						background: var(--sp-media-soft);
						border: 1px solid var(--sp-media-border);
						color: var(--sp-acf-text-2);
                        cursor: pointer;
                        display: inline-flex;
                        font-size: 12px;
						font-weight: 600;
						padding: 8px 11px;
						transition: background var(--sp-acf-transition), border-color var(--sp-acf-transition), box-shadow var(--sp-acf-transition), color var(--sp-acf-transition), transform var(--sp-acf-transition);
					}

					.sp-universal-media__check input:checked + span {
						background: var(--sp-acf-accent-soft);
						border-color: var(--sp-media-brand);
						color: var(--sp-media-brand);
					}

					.sp-universal-media__check input:focus-visible + span {
						box-shadow: var(--sp-acf-focus);
						outline: 0;
					}

					.sp-universal-media__check:active span {
						box-shadow: inset 0 1px 2px rgb(26 31 36 / 18%);
						transform: translateY(1px);
					}

					.sp-universal-media__playback .description {
						color: var(--sp-acf-text-2);
                        margin: 10px 0 0;
                    }

                    .sp-universal-media__display {
                        align-items: center;
                        display: block;
                        flex: 1 1 100%;
                        margin-left: 0;
                        min-width: 0;
                        width: 100%;
                    }

                    .sp-universal-media__toggle {
                        display: grid;
                        gap: 2px;
                        grid-template-columns: repeat(var(--sp-media-control-count, 3), minmax(0, 1fr));
                        justify-content: space-between;
                        width: 100%;
                    }

                    .sp-universal-media__toggle label {
                        display: block;
                        min-width: 0;
                    }

                    .sp-universal-media__toggle input {
                        clip: rect(0 0 0 0);
                        clip-path: inset(50%);
                        height: 1px;
                        overflow: hidden;
                        position: absolute;
                        white-space: nowrap;
                        width: 1px;
                    }

					.sp-universal-media__toggle input:focus-visible + span {
						box-shadow: var(--sp-acf-focus);
						outline: 0;
						position: relative;
						z-index: 1;
					}

					.sp-universal-media[aria-busy="true"] :is(.sp-universal-media__picker, .sp-universal-media__poster-preview) {
						opacity: .65;
					}

					.sp-universal-media[aria-busy="true"] .sp-universal-media__actions .button[aria-busy="true"] {
						cursor: progress;
					}

					@keyframes sp-universal-media-spin {
						to {
							transform: rotate(360deg);
						}
					}

                    @container (max-width: 640px) {
                        .sp-universal-media__tabs {
                            gap: 8px;
                            justify-content: initial;
                        }

                        .sp-universal-media__display,
                        .sp-universal-media__sources {
                            flex-basis: 100%;
                            width: 100%;
                        }
                    }

                    @container (max-width: 430px) {
                        .sp-universal-media__tabs {
                            padding: 4px;
                        }

                        .sp-universal-media__sources,
                        .sp-universal-media__toggle {
                            grid-template-columns: 1fr;
                        }

                        .sp-universal-media__panel,
                        .sp-universal-media__advanced {
                            padding: 12px;
                        }

                        .sp-universal-media__actions .button,
                        .sp-universal-media .button-link-delete {
                            text-align: center;
                            width: 100%;
                        }
                    }

					@media (max-width: 782px) {
                        .sp-universal-media__display {
                            flex-basis: 100%;
                            margin: 6px 0 0;
                        }

						.sp-universal-media__advanced {
							grid-template-columns: 1fr;
						}
					}

					@media (prefers-reduced-motion: reduce) {
						.sp-universal-media *,
						.sp-universal-media *::before,
						.sp-universal-media *::after {
							scroll-behavior: auto !important;
							transition-duration: .01ms !important;
						}

						.sp-universal-media :is(.sp-universal-media__tab, .sp-universal-media__toggle span, .sp-universal-media__check span, .button, .button-link-delete):active {
							transform: none;
						}

						.sp-universal-media__actions .button[aria-busy="true"]::after {
							animation: none;
						}
					}
				</style>
                <?php
            }

            public function input_admin_footer(): void
            {
                ?>
                <script id="sp-acf-universal-media-js">
					(function ($) {
						'use strict';

						function announce($field, message) {
							var $status = $field.find('[data-sp-media-status]');
							$status.text('');
							window.setTimeout(function () {
								$status.text(message || '');
							}, 20);
						}

						function setBusy($field, $control, busy) {
							$field.attr('aria-busy', busy ? 'true' : 'false');
							$control
								.attr('aria-disabled', busy ? 'true' : 'false')
								.attr('aria-busy', busy ? 'true' : 'false');
						}

						function bindFrameBusy(frame, $field, $control) {
							frame.on('open', function () {
								setBusy($field, $control, true);
							});
							frame.on('close', function () {
								setBusy($field, $control, false);
							});
						}

                        function sourceValue($field) {
                            return $field.find('[data-sp-media-source]').val() || 'library';
                        }

                        function syncField($field) {
                            var source = sourceValue($field);
                            var isLibrary = source === 'library';
                            var provider = source === 'vimeo' ? 'Vimeo' : 'YouTube';
                            var localType = $field.find('[data-sp-media-type]').val() || 'image';
                            var isVideo = !isLibrary || localType === 'video';
                            var isUploadedVideo = isLibrary && localType === 'video';
                            var $displayInput = $field.find('input[name$="[display]"]');
                            var display = $displayInput.is(':radio') ? ($displayInput.filter(':checked').val() || 'inline') : ($displayInput.val() || 'inline');
                            var needsCover = isUploadedVideo || (isVideo && display === 'fancybox');

							$field.find('[data-sp-media-source-option]').each(function () {
								var isActive = $(this).data('sp-media-source-option') === source;
								$(this)
									.toggleClass('is-active', isActive)
									.attr('aria-pressed', isActive ? 'true' : 'false');
                            });
                            $field.find('[data-sp-media-panel="library"]').toggle(isLibrary);
                            $field.find('[data-sp-media-panel="remote"]').toggle(!isLibrary);
                            $field.find('[data-sp-media-url-label]').text(provider + ' URL');
                            $field.find('[data-sp-media-url]').attr('placeholder', source === 'vimeo'
                                ? 'https://player.vimeo.com/video/123456789?h=privacy_hash'
                                : 'https://www.youtube.com/watch?v=...');
                            $field.find('[data-sp-media-url-help]').text(source === 'vimeo'
                                ? 'For unlisted Vimeo videos, paste the embed URL including its h= privacy hash.'
                                : 'Paste a public video link. It will be embedded automatically.');
                            $field.find('[data-sp-media-advanced]').prop('hidden', !isVideo);
                            $field.find('[data-sp-media-poster-panel]').prop('hidden', !isVideo || !needsCover);
                            $field.find('[data-sp-custom-play-option]').toggle(isUploadedVideo && display === 'inline');
                            $field.find('[data-sp-poster-help]').text(isUploadedVideo
                                ? 'Required for uploaded video.'
                                : 'Shown as the clickable preview before the video opens.');
                            $field.find('[data-sp-poster-empty]').text(isUploadedVideo ? 'Required cover image' : 'Optional cover image');
                        }

						function emptyPreview() {
							return $('<span>', {'class': 'sp-universal-media__empty'})
								.append($('<span>', {'class': 'dashicons dashicons-upload', 'aria-hidden': 'true'}))
								.append($('<span>').text('Image or video from Media Library'));
                        }

                        function updatePreview($field, attachment) {
                            var $preview = $field.find('[data-sp-media-preview]');
                            var previewUrl = attachment.url;

                            $field.find('[data-sp-media-id]').val(attachment.id).trigger('change');
                            $field.find('[data-sp-media-type]').val(attachment.type);
                            $field.find('[data-sp-media-remove]').removeClass('is-hidden');
                            $field.find('[data-sp-media-select]').text('Replace media');
							$preview.addClass('is-filled').empty();
							syncField($field);
							announce($field, (attachment.type === 'image' ? 'Image selected: ' : 'Video selected: ') + (attachment.filename || attachment.title || 'media'));

                            if (attachment.type === 'image') {
                                $('<img>', {src: previewUrl, alt: ''}).appendTo($preview);
                                return;
                            }

							$('<span>', {'class': 'sp-universal-media__file'})
								.append($('<span>', {'class': 'dashicons dashicons-format-video', 'aria-hidden': 'true'}))
                                .append($('<span>').text(attachment.filename || attachment.title || 'Video selected'))
                                .appendTo($preview);
                        }

                        function updatePoster($field, attachment) {
                            var $preview = $field.find('[data-sp-poster-preview]');
                            var previewUrl = attachment.url;

                            $field.find('[data-sp-poster-id]').val(attachment.id).trigger('change');
                            $field.find('[data-sp-poster-remove]').removeClass('is-hidden');
							$field.find('[data-sp-poster-select]').text('Replace cover');
							$preview.addClass('is-filled').empty().append($('<img>', {src: previewUrl, alt: ''}));
							announce($field, 'Cover image selected: ' + (attachment.filename || attachment.title || 'image'));
                        }

                        $(document).on('click', '[data-sp-media-source-option]', function (event) {
                            event.preventDefault();

                            var $field = $(this).closest('[data-sp-universal-media]');
                            $field.find('[data-sp-media-source]').val($(this).data('sp-media-source-option')).trigger('change');
                            syncField($field);
                        });

						$(document).on('click', '[data-sp-media-select]', function (event) {
							event.preventDefault();

							var $control = $(this);
							var $field = $(this).closest('[data-sp-universal-media]');
                            var frame = wp.media({
                                title: 'Select image or video',
                                button: {text: 'Use this media'},
                                library: {type: ['image', 'video']},
                                multiple: false
                            });

							frame.on('select', function () {
								updatePreview($field, frame.state().get('selection').first().toJSON());
							});

							bindFrameBusy(frame, $field, $control);
							frame.open();
                        });

						$(document).on('click', '[data-sp-poster-select]', function (event) {
							event.preventDefault();

							var $control = $(this);
							var $field = $(this).closest('[data-sp-universal-media]');
                            var frame = wp.media({
                                title: 'Select cover image',
                                button: {text: 'Use this cover'},
                                library: {type: 'image'},
                                multiple: false
                            });

							frame.on('select', function () {
								updatePoster($field, frame.state().get('selection').first().toJSON());
							});

							bindFrameBusy(frame, $field, $control);
							frame.open();
                        });

                        $(document).on('click', '[data-sp-media-remove]', function (event) {
                            event.preventDefault();

                            var $field = $(this).closest('[data-sp-universal-media]');
                            $field.find('[data-sp-media-id]').val('').trigger('change');
                            $field.find('[data-sp-media-type]').val('image');
                            $field.find('[data-sp-media-preview]').removeClass('is-filled').empty().append(emptyPreview());
							$field.find('[data-sp-media-select]').text('Select media');
							$(this).addClass('is-hidden');
							syncField($field);
							announce($field, 'Media removed.');
                        });

                        $(document).on('click', '[data-sp-poster-remove]', function (event) {
                            event.preventDefault();

                            var $field = $(this).closest('[data-sp-universal-media]');
                            $field.find('[data-sp-poster-id]').val('').trigger('change');
                            $field.find('[data-sp-poster-preview]').removeClass('is-filled').empty().append($('<span>', {'data-sp-poster-empty': ''}).text('Optional cover image'));
							$field.find('[data-sp-poster-select]').text('Select cover');
							$(this).addClass('is-hidden');
							syncField($field);
							announce($field, 'Cover image removed.');
                        });

                        $(document).on('change', '[data-sp-universal-media] input[name$="[display]"]', function () {
                            var $field = $(this).closest('[data-sp-universal-media]');

                            if ($(this).val() === 'background') {
                                $field.find('input[name$="[autoplay]"][type="checkbox"], input[name$="[muted]"][type="checkbox"], input[name$="[loop]"][type="checkbox"], input[name$="[playsinline]"][type="checkbox"]').prop('checked', true);
                                $field.find('input[name$="[controls]"][type="checkbox"]').prop('checked', false);
                            }

                            syncField($field);
                        });

						function init($scope) {
							$scope.find('[data-sp-universal-media]').addBack('[data-sp-universal-media]').each(function () {
								var $field = $(this);
								$field.attr('aria-busy', 'false');
								syncField($field);
                            });
                        }

                        $(function () {
                            init($(document));
                        });

                        if (window.acf) {
                            window.acf.addAction('append', function ($element) {
                                init($element);
                            });
                        }
                    })(jQuery);
                </script>
                <?php
            }
        }

        acf_register_field_type('SP_ACF_Field_Universal_Media');
    }
