<?php
if (! defined('ABSPATH')) exit;

/*
 * Universal Author Meta Box — platform/sp-author-meta/index.php
 *
 * Usage — add one line to any CPT index.php:
 *   sp_register_author_metabox( 'blog' );
 *   sp_register_author_metabox( ['blog', 'projects'] );
 *
 * Get author data in templates:
 *   $author = sp_get_post_author( get_the_ID() );
 *   $author['name']      — author display name
 *   $author['photo_id']  — attachment ID (0 if none)
 *   $author['photo_url'] — image URL ('' if none)
 */

//    // Has photo
//    sp_register_author_metabox( 'blog' );
//    sp_register_author_metabox( ['blog', 'projects'] );
//
//    // No photo
//    sp_register_author_metabox( 'testimonials', false );
//    sp_register_author_metabox( ['news', 'events'], false );
//
//    // No photo, but with position
//    sp_register_author_metabox( 'testimonials', false, true );



// --- Registry ---

global $_sp_author_meta_post_types;
$_sp_author_meta_post_types = [];

if (! function_exists('sp_register_author_metabox')) {
    function sp_register_author_metabox($post_types, bool $with_photo = true, bool $with_position = false): void
    {
        global $_sp_author_meta_post_types;
        foreach ((array) $post_types as $pt) {
            $pt = sanitize_key((string) $pt);
            if ($pt !== '') {
                $_sp_author_meta_post_types[$pt] = [
                    'with_photo'    => $with_photo,
                    'with_position' => $with_position,
                ];
            }
        }
    }
}


// --- Register meta box ---

add_action('add_meta_boxes', function () {
    global $_sp_author_meta_post_types;
    if (empty($_sp_author_meta_post_types)) return;
    foreach ($_sp_author_meta_post_types as $pt => $opts) {
        add_meta_box(
            'sp_custom_author',
            'Author',
            fn($post) => _sp_author_metabox_render(
                $post,
                $opts['with_photo'] ?? true,
                $opts['with_position'] ?? false
            ),
            $pt,
            'side'
        );
    }
});


// --- Render ---

if (! function_exists('_sp_author_icon')) {
    function _sp_author_icon(string $name): string
    {
        return match ($name) {
            'user' => '<svg class="sp-author-icon sp-author-icon--user" aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"/><path d="M4.5 21a7.5 7.5 0 0 1 15 0"/></svg>',
            'edit' => '<svg class="sp-author-icon sp-author-icon--edit" aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none"><path d="m4 20 4.25-1L19 8.25 15.75 5 5 15.75 4 20Z"/><path d="m13.75 7 3.25 3.25"/></svg>',
            'photo' => '<svg class="sp-author-icon sp-author-icon--photo" aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none"><path d="M3 5h18v15H3Z"/><path d="m3 17 5-5 4 4 3-3 6 6"/><path d="M16 9h4M18 7v4"/><path d="M8.5 11a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z"/></svg>',
            'upload' => '<svg class="sp-author-icon sp-author-icon--upload" aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none"><path d="M12 16V4M8 8l4-4 4 4"/><path d="M4 14v6h16v-6"/></svg>',
            'remove' => '<svg class="sp-author-icon sp-author-icon--remove" aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none"><path d="M4 7h16M9 7V4h6v3M7 7l1 13h8l1-13M10 11v5M14 11v5"/></svg>',
            default => '',
        };
    }
}

function _sp_author_metabox_render(WP_Post $post, bool $with_photo = true, bool $with_position = false): void
{
    $custom      = get_post_meta($post->ID, '_custom_author', true);
    $author_type = get_post_meta($post->ID, '_author_type', true) ?: 'user';
    $photo_id    = (int) get_post_meta($post->ID, '_author_photo_id', true);
    $photo_url   = $photo_id ? wp_get_attachment_image_url($photo_id, 'thumbnail') : '';
    $position    = get_post_meta($post->ID, '_author_position', true);
    $users       = get_users(['fields' => ['ID', 'display_name']]);

    wp_nonce_field('sp_author_meta_nonce', 'sp_author_meta_nonce');
?>
    <style>
        #sp_custom_author .inside {
            margin-top: 0;
            padding: 14px;
        }

        .sp-author-switcher {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 4px;
            margin-bottom: 14px;
            padding: 4px;
            border: 1px solid var(--color-border, #e7eaee);
            border-radius: 0;
            background: var(--color-surface-alt, #f8fafc);
        }

        .sp-author-switcher label {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            min-height: 36px;
            padding: 6px 10px;
            border: 1px solid transparent;
            border-radius: 0;
            background: transparent;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            color: var(--color-text-2, #525b66);
            transition: border-color var(--sp-admin-transition, 160ms ease), background var(--sp-admin-transition, 160ms ease), box-shadow var(--sp-admin-transition, 160ms ease), color var(--sp-admin-transition, 160ms ease);
            user-select: none;
        }

        .sp-author-switcher label:has(input:checked) {
            border-color: var(--color-accent, #3858e9);
            background: var(--sp-admin-accent-soft, #edf0ff);
            box-shadow: none;
            color: var(--color-accent-hover, #2145e6);
        }

        .sp-author-switcher label:hover:not(:has(input:checked)) {
            border-color: var(--color-border-strong, #d6dbe1);
            background: var(--color-surface, #fff);
            color: var(--color-text, #1a1f24);
        }

        .sp-author-switcher label:has(input:focus-visible) {
            box-shadow: var(--sp-admin-focus, 0 0 0 3px rgb(56 88 233 / 18%));
        }

        .sp-author-switcher input[type="radio"] {
            display: none;
        }

        .sp-author-row select,
        .sp-author-row input[type="text"] {
            width: 100%;
            min-height: 38px;
            padding: 7px 10px;
            border: 1px solid var(--color-border-strong, #d6dbe1);
            border-radius: 0;
            background: var(--color-input-bg, #fdfefe);
            font-size: 13px;
            color: var(--color-text, #1a1f24);
            box-shadow: inset 0 1px 0 rgb(255 255 255 / 80%);
            transition: border-color var(--sp-admin-transition, 160ms ease), box-shadow var(--sp-admin-transition, 160ms ease), background var(--sp-admin-transition, 160ms ease);
        }

        .sp-author-row select:focus,
        .sp-author-row input[type="text"]:focus {
            border-color: var(--color-accent, #3858e9);
            background: var(--color-surface, #fff);
            box-shadow: var(--sp-admin-focus, 0 0 0 3px rgb(56 88 233 / 18%));
            outline: none;
        }

        .sp-author-photo-wrap {
            margin-top: 12px;
        }

        .sp-author-photo-row {
            display: flex;
            gap: 12px;
            align-items: center;
            margin-bottom: 10px;
        }

        .sp-author-photo-col {
            flex-shrink: 0;
        }

        .sp-author-photo-preview {
            display: block;
            width: 64px;
            height: 64px;
            object-fit: cover;
            border: 1px solid var(--color-border-strong, #d6dbe1);
            border-radius: 0;
            box-shadow: none;
            cursor: pointer;
            transition: border-color var(--sp-admin-transition, 160ms ease), box-shadow var(--sp-admin-transition, 160ms ease);
        }

        .sp-author-photo-placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 64px;
            height: 64px;
            margin: 0;
            padding: 0;
            border: 1px dashed var(--color-border-strong, #d6dbe1);
            border-radius: 0;
            background: var(--color-surface-alt, #f8fafc);
            color: var(--color-text-3, #8a919b);
            box-shadow: none;
            cursor: pointer;
            transition: border-color var(--sp-admin-transition, 160ms ease), background var(--sp-admin-transition, 160ms ease), color var(--sp-admin-transition, 160ms ease);
        }

        .sp-author-photo-preview:hover,
        .sp-author-photo-placeholder:hover {
            border-color: var(--color-accent, #3858e9);
            box-shadow: none;
        }

        .sp-author-photo-placeholder:hover {
            background: var(--sp-admin-accent-soft, #edf0ff);
            color: var(--color-accent-hover, #2145e6);
        }

        .sp-author-photo-preview:focus-visible,
        .sp-author-photo-placeholder:focus-visible {
            border-color: var(--color-accent, #3858e9);
            box-shadow: var(--sp-admin-focus, 0 0 0 3px rgb(56 88 233 / 18%));
            outline: 0;
        }

        .sp-author-photo-placeholder.is-hidden,
        .sp-author-photo-preview.is-hidden {
            display: none;
        }

        .sp-author-name-col {
            flex: 1;
        }

        .sp-author-name-col input[type="text"],
        .sp-author-name-col select {
            width: 100%;
            min-height: 38px;
            padding: 7px 10px;
            border: 1px solid var(--color-border-strong, #d6dbe1);
            border-radius: 0;
            background: var(--color-input-bg, #fdfefe);
            font-size: 13px;
            color: var(--color-text, #1a1f24);
            box-shadow: inset 0 1px 0 rgb(255 255 255 / 80%);
            box-sizing: border-box;
            transition: border-color var(--sp-admin-transition, 160ms ease), box-shadow var(--sp-admin-transition, 160ms ease), background var(--sp-admin-transition, 160ms ease);
        }

        .sp-author-name-col input[type="text"]:focus,
        .sp-author-name-col select:focus {
            border-color: var(--color-accent, #3858e9);
            background: var(--color-surface, #fff);
            box-shadow: var(--sp-admin-focus, 0 0 0 3px rgb(56 88 233 / 18%));
            outline: none;
        }

        .sp-author-photo-btns {
            display: flex;
            gap: 8px;
        }

        .sp-author-photo-btns .button {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            min-height: 34px;
            padding: 0 11px;
            border-color: var(--color-border-strong, #d6dbe1);
            border-radius: 0;
            background: var(--color-surface, #fff);
            box-shadow: none;
            color: var(--color-text, #1a1f24);
            font-size: 11px;
            font-weight: 600;
        }

        .sp-author-photo-btns .button:hover {
            border-color: var(--color-accent, #3858e9);
            background: var(--sp-admin-accent-soft, #edf0ff);
            box-shadow: none;
            color: var(--color-accent-hover, #2145e6);
        }

        .sp-author-photo-btns .button:focus-visible {
            box-shadow: var(--sp-admin-focus, 0 0 0 3px rgb(56 88 233 / 18%));
            outline: 0;
        }

        #sp_custom_author .sp-author-icon {
            display: block;
            flex: 0 0 auto;
            width: 16px;
            height: 16px;
            stroke: currentColor;
            stroke-width: 1.75;
            stroke-linecap: square;
            stroke-linejoin: miter;
            pointer-events: none;
        }

        #sp_custom_author .sp-author-photo-placeholder .sp-author-icon {
            width: 24px;
            height: 24px;
        }
    </style>

    <!-- Type switcher -->
    <div class="sp-author-switcher">
        <label>
            <input type="radio" name="author_type" value="user" <?= checked($author_type, 'user', false) ?>>
            <?= _sp_author_icon('user') ?>
            <span>Select user</span>
        </label>
        <label>
            <input type="radio" name="author_type" value="custom" <?= checked($author_type, 'custom', false) ?>>
            <?= _sp_author_icon('edit') ?>
            <span>Custom</span>
        </label>
    </div>

    <?php if ($with_photo) : ?>
        <!-- Photo + name -->
        <div class="sp-author-photo-wrap">
            <div class="sp-author-photo-row">
                <div class="sp-author-photo-col">
                    <img
                        id="sp_author_photo_preview"
                        src="<?= esc_url($photo_url) ?>"
                        class="sp-author-photo-preview<?= $photo_url ? '' : ' is-hidden' ?>"
                        alt="Author photo" />
                    <button
                        type="button"
                        id="sp_author_photo_placeholder"
                        class="sp-author-photo-placeholder<?= $photo_url ? ' is-hidden' : '' ?>"
                        title="Upload photo"
                        aria-label="Upload author photo"><?= _sp_author_icon('photo') ?></button>
                    <input type="hidden" name="author_photo_id" id="sp_author_photo_id" value="<?= esc_attr($photo_id ?: '') ?>">
                </div>
                <div class="sp-author-name-col">
                    <!-- user select -->
                    <div id="sp_author_user_row" class="sp-author-row">
                        <select name="custom_author_user">
                            <option value="">— none —</option>
                            <?php foreach ($users as $user) : ?>
                                <option value="<?= esc_attr($user->ID) ?>" <?= selected($post->post_author, $user->ID, false) ?>>
                                    <?= esc_html($user->display_name) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <!-- custom name -->
                    <div id="sp_author_custom_row" class="sp-author-row">
                        <input type="text" name="custom_author" value="<?= esc_attr($custom) ?>" placeholder="John Doe" />
                    </div>
                    <?php if ($with_position) : ?>
                        <!-- position -->
                        <div class="sp-author-row" style="margin-top: 6px;">
                            <input type="text" name="author_position" value="<?= esc_attr($position) ?>" placeholder="Position" />
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="sp-author-photo-btns">
                <button type="button" class="button" id="sp_author_photo_upload">
                    <?= _sp_author_icon('upload') ?>
                    <span data-sp-author-upload-label><?= $photo_url ? 'Change photo' : 'Upload photo' ?></span>
                </button>
                <button type="button" class="button" id="sp_author_photo_remove" <?= $photo_url ? '' : ' style="display:none"' ?>>
                    <?= _sp_author_icon('remove') ?>
                    <span>Remove</span>
                </button>
            </div>
        </div>
    <?php else : ?>
        <!-- Name only (no photo) -->
        <div class="sp-author-photo-wrap">
            <div id="sp_author_user_row" class="sp-author-row" style="margin-bottom:6px;">
                <select name="custom_author_user">
                    <option value="">— none —</option>
                    <?php foreach ($users as $user) : ?>
                        <option value="<?= esc_attr($user->ID) ?>" <?= selected($post->post_author, $user->ID, false) ?>>
                            <?= esc_html($user->display_name) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div id="sp_author_custom_row" class="sp-author-row">
                <input type="text" name="custom_author" value="<?= esc_attr($custom) ?>" placeholder="John Doe" />
            </div>
            <?php if ($with_position) : ?>
                <!-- position -->
                <div class="sp-author-row" style="margin-top: 6px;">
                    <input type="text" name="author_position" value="<?= esc_attr($position) ?>" placeholder="Position" />
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <script>
        (function() {
            const radios = document.querySelectorAll('input[name="author_type"]');
            const userRow = document.getElementById('sp_author_user_row');
            const customRow = document.getElementById('sp_author_custom_row');
            <?php if ($with_photo) : ?>
                const uploadBtn = document.getElementById('sp_author_photo_upload');
                const removeBtn = document.getElementById('sp_author_photo_remove');
                const uploadLabel = uploadBtn.querySelector('[data-sp-author-upload-label]');
                const hiddenId = document.getElementById('sp_author_photo_id');
                const preview = document.getElementById('sp_author_photo_preview');
                const placeholder = document.getElementById('sp_author_photo_placeholder');
                let mediaFrame = null;
            <?php endif; ?>

            function toggleType() {
                const val = document.querySelector('input[name="author_type"]:checked').value;
                userRow.style.display = val === 'user' ? 'block' : 'none';
                customRow.style.display = val === 'custom' ? 'block' : 'none';
            }
            toggleType();
            radios.forEach(r => r.addEventListener('change', toggleType));

            <?php if ($with_photo) : ?>
                placeholder.addEventListener('click', () => uploadBtn.click());
                preview.addEventListener('click', () => uploadBtn.click());

                uploadBtn.addEventListener('click', function() {
                    if (mediaFrame) {
                        mediaFrame.open();
                        return;
                    }
                    mediaFrame = wp.media({
                        title: 'Select Author Photo',
                        button: {
                            text: 'Use this photo'
                        },
                        library: {
                            type: 'image'
                        },
                        multiple: false,
                    });
                    mediaFrame.on('select', function() {
                        const att = mediaFrame.state().get('selection').first().toJSON();
                        hiddenId.value = att.id;
                        preview.src = att.sizes?.thumbnail?.url || att.url;
                        preview.classList.remove('is-hidden');
                        placeholder.classList.add('is-hidden');
                        uploadLabel.textContent = 'Change photo';
                        removeBtn.style.display = '';
                    });
                    mediaFrame.open();
                });

                removeBtn.addEventListener('click', function() {
                    hiddenId.value = '';
                    preview.src = '';
                    preview.classList.add('is-hidden');
                    placeholder.classList.remove('is-hidden');
                    uploadLabel.textContent = 'Upload photo';
                    removeBtn.style.display = 'none';
                });
            <?php endif; ?>
        })();
    </script>
<?php
}


// --- Save ---

add_action('save_post', function (int $post_id) {
    global $_sp_author_meta_post_types;

    if (empty($_sp_author_meta_post_types[get_post_type($post_id)])) return;

    if (
        ! isset($_POST['sp_author_meta_nonce']) ||
        ! wp_verify_nonce($_POST['sp_author_meta_nonce'], 'sp_author_meta_nonce') ||
        (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
    ) return;

    $opts          = $_sp_author_meta_post_types[get_post_type($post_id)];
    $with_photo    = $opts['with_photo'] ?? true;
    $with_position = $opts['with_position'] ?? false;

    $type = sanitize_text_field($_POST['author_type'] ?? 'user');
    update_post_meta($post_id, '_author_type', $type);

    if ($type === 'custom') {
        update_post_meta($post_id, '_custom_author', sanitize_text_field($_POST['custom_author'] ?? ''));
        delete_post_meta($post_id, '_author_user_id');
    } else {
        delete_post_meta($post_id, '_custom_author');
        $uid = ! empty($_POST['custom_author_user']) ? (int) $_POST['custom_author_user'] : 0;
        if ($uid) update_post_meta($post_id, '_author_user_id', $uid);
        else        delete_post_meta($post_id, '_author_user_id');
    }

    if ($with_photo) {
        $photo_id = (isset($_POST['author_photo_id']) && $_POST['author_photo_id'] !== '')
            ? (int) $_POST['author_photo_id'] : 0;

        if ($photo_id > 0) update_post_meta($post_id, '_author_photo_id', $photo_id);
        else                 delete_post_meta($post_id, '_author_photo_id');
    }

    if ($with_position) {
        $pos = isset($_POST['author_position']) ? sanitize_text_field($_POST['author_position']) : '';
        if ($pos !== '') update_post_meta($post_id, '_author_position', $pos);
        else               delete_post_meta($post_id, '_author_position');
    }
});


// --- Helper ---

if (! function_exists('sp_get_post_author')) {
    function sp_get_post_author(int $post_id, string $photo_size = 'thumbnail'): array
    {
        $type     = get_post_meta($post_id, '_author_type', true) ?: 'user';
        $photo_id = (int) get_post_meta($post_id, '_author_photo_id', true);

        if ($type === 'custom') {
            $name = (string) get_post_meta($post_id, '_custom_author', true);
        } else {
            $uid  = (int) get_post_meta($post_id, '_author_user_id', true);
            if ($uid) {
                $u    = get_userdata($uid);
                $name = $u ? $u->display_name : '';
            } else {
                $p    = get_post($post_id);
                $name = $p ? get_the_author_meta('display_name', $p->post_author) : '';
            }
        }

        $photo = null;
        if ($photo_id > 0) {
            $src = wp_get_attachment_image_src($photo_id, $photo_size);
            if (is_array($src) && ! empty($src[0])) {
                $photo = [
                    'ID'     => $photo_id,
                    'url'    => (string) $src[0],
                    'width'  => (int) ($src[1] ?? 0),
                    'height' => (int) ($src[2] ?? 0),
                    'alt'    => (string) (get_post_meta($photo_id, '_wp_attachment_image_alt', true) ?: ''),
                ];
            }
        }

        $position = (string) get_post_meta($post_id, '_author_position', true);

        return [
            'name'     => $name,
            'photo_id' => $photo_id,
            'photo'    => $photo,
            'position' => $position,
        ];
    }
}
