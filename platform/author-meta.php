<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/*
 * Universal Author Meta Box — core/platform/author-meta.php
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



// --- Registry ---

global $_sp_author_meta_post_types;
$_sp_author_meta_post_types = [];

if ( ! function_exists( 'sp_register_author_metabox' ) ) {
    function sp_register_author_metabox( $post_types, bool $with_photo = true ): void {
        global $_sp_author_meta_post_types;
        foreach ( (array) $post_types as $pt ) {
            $pt = sanitize_key( (string) $pt );
            if ( $pt !== '' ) {
                $_sp_author_meta_post_types[ $pt ] = [
                        'with_photo' => $with_photo,
                ];
            }
        }
    }
}


// --- Register meta box ---

add_action( 'add_meta_boxes', function () {
    global $_sp_author_meta_post_types;
    if ( empty( $_sp_author_meta_post_types ) ) return;
    foreach ( $_sp_author_meta_post_types as $pt => $opts ) {
        add_meta_box(
                'sp_custom_author',
                'Author',
                fn( $post ) => _sp_author_metabox_render( $post, $opts['with_photo'] ?? true ),
                $pt,
                'side'
        );
    }
} );


// --- Render ---

function _sp_author_metabox_render( WP_Post $post, bool $with_photo = true ): void {
    $custom      = get_post_meta( $post->ID, '_custom_author', true );
    $author_type = get_post_meta( $post->ID, '_author_type', true ) ?: 'user';
    $photo_id    = (int) get_post_meta( $post->ID, '_author_photo_id', true );
    $photo_url   = $photo_id ? wp_get_attachment_image_url( $photo_id, 'thumbnail' ) : '';
    $users       = get_users( [ 'fields' => [ 'ID', 'display_name' ] ] );

    wp_nonce_field( 'sp_author_meta_nonce', 'sp_author_meta_nonce' );
    ?>
    <style>
        .sp-author-switcher {
            display: flex;
            gap: 6px;
            margin-bottom: 10px;
        }
        .sp-author-switcher label {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            padding: 5px 10px;
            border: 1px solid #dcdcde;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            color: #50575e;
            transition: all .15s ease;
            user-select: none;
        }
        .sp-author-switcher label:has(input:checked) {
            border-color: #2271b1;
            background: #f0f6fc;
            color: #2271b1;
        }
        .sp-author-switcher input[type="radio"] { display: none; }

        .sp-author-row select,
        .sp-author-row input[type="text"] {
            width: 100%;
            padding: 5px 7px;
            border: 1px solid #dcdcde;
            border-radius: 4px;
            font-size: 13px;
            color: #2c3338;
            box-shadow: none;
        }
        .sp-author-row select:focus,
        .sp-author-row input[type="text"]:focus {
            border-color: #2271b1;
            box-shadow: 0 0 0 1px #2271b1;
            outline: none;
        }

        .sp-author-photo-wrap { margin-top: 10px; }
        .sp-author-photo-row {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-bottom: 8px;
        }
        .sp-author-photo-col { flex-shrink: 0; }
        .sp-author-photo-preview {
            display: block;
            width: 64px;
            height: 64px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid #dcdcde;
            cursor: pointer;
        }
        .sp-author-photo-placeholder {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            border: 2px dashed #dcdcde;
            background: #f6f7f7;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            cursor: pointer;
        }
        .sp-author-photo-placeholder.is-hidden,
        .sp-author-photo-preview.is-hidden { display: none; }
        .sp-author-name-col { flex: 1; }
        .sp-author-name-col input[type="text"],
        .sp-author-name-col select {
            width: 100%;
            padding: 5px 7px;
            border: 1px solid #dcdcde;
            border-radius: 4px;
            font-size: 13px;
            color: #2c3338;
            box-shadow: none;
            box-sizing: border-box;
        }
        .sp-author-name-col input[type="text"]:focus,
        .sp-author-name-col select:focus {
            border-color: #2271b1;
            box-shadow: 0 0 0 1px #2271b1;
            outline: none;
        }
        .sp-author-photo-btns { display: flex; gap: 6px; }
        .sp-author-photo-btns .button { font-size: 11px; }
    </style>

    <!-- Type switcher -->
    <div class="sp-author-switcher">
        <label>
            <input type="radio" name="author_type" value="user" <?= checked( $author_type, 'user', false ) ?>>
            👤 Select user
        </label>
        <label>
            <input type="radio" name="author_type" value="custom" <?= checked( $author_type, 'custom', false ) ?>>
            ✏️ Custom
        </label>
    </div>

    <?php if ( $with_photo ) : ?>
        <!-- Photo + name -->
        <div class="sp-author-photo-wrap">
            <div class="sp-author-photo-row">
                <div class="sp-author-photo-col">
                    <img
                            id="sp_author_photo_preview"
                            src="<?= esc_url( $photo_url ) ?>"
                            class="sp-author-photo-preview<?= $photo_url ? '' : ' is-hidden' ?>"
                            alt="Author photo"
                    />
                    <div
                            id="sp_author_photo_placeholder"
                            class="sp-author-photo-placeholder<?= $photo_url ? ' is-hidden' : '' ?>"
                            title="Upload photo"
                    >👤</div>
                    <input type="hidden" name="author_photo_id" id="sp_author_photo_id" value="<?= esc_attr( $photo_id ?: '' ) ?>">
                </div>
                <div class="sp-author-name-col">
                    <!-- user select -->
                    <div id="sp_author_user_row" class="sp-author-row">
                        <select name="custom_author_user">
                            <option value="">— none —</option>
                            <?php foreach ( $users as $user ) : ?>
                                <option value="<?= esc_attr( $user->ID ) ?>" <?= selected( $post->post_author, $user->ID, false ) ?>>
                                    <?= esc_html( $user->display_name ) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <!-- custom name -->
                    <div id="sp_author_custom_row" class="sp-author-row">
                        <input type="text" name="custom_author" value="<?= esc_attr( $custom ) ?>" placeholder="John Doe" />
                    </div>
                </div>
            </div>
            <div class="sp-author-photo-btns">
                <button type="button" class="button" id="sp_author_photo_upload">
                    <?= $photo_url ? 'Change photo' : 'Upload photo' ?>
                </button>
                <button type="button" class="button" id="sp_author_photo_remove"<?= $photo_url ? '' : ' style="display:none"' ?>>
                    Remove
                </button>
            </div>
        </div>
    <?php else : ?>
        <!-- Name only (no photo) -->
        <div class="sp-author-photo-wrap">
            <div id="sp_author_user_row" class="sp-author-row" style="margin-bottom:6px;">
                <select name="custom_author_user">
                    <option value="">— none —</option>
                    <?php foreach ( $users as $user ) : ?>
                        <option value="<?= esc_attr( $user->ID ) ?>" <?= selected( $post->post_author, $user->ID, false ) ?>>
                            <?= esc_html( $user->display_name ) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div id="sp_author_custom_row" class="sp-author-row">
                <input type="text" name="custom_author" value="<?= esc_attr( $custom ) ?>" placeholder="John Doe" />
            </div>
        </div>
    <?php endif; ?>

    <script>
        (function () {
            const radios      = document.querySelectorAll( 'input[name="author_type"]' );
            const userRow     = document.getElementById( 'sp_author_user_row' );
            const customRow   = document.getElementById( 'sp_author_custom_row' );
            <?php if ( $with_photo ) : ?>
            const uploadBtn   = document.getElementById( 'sp_author_photo_upload' );
            const removeBtn   = document.getElementById( 'sp_author_photo_remove' );
            const hiddenId    = document.getElementById( 'sp_author_photo_id' );
            const preview     = document.getElementById( 'sp_author_photo_preview' );
            const placeholder = document.getElementById( 'sp_author_photo_placeholder' );
            let mediaFrame    = null;
            <?php endif; ?>

            function toggleType() {
                const val = document.querySelector( 'input[name="author_type"]:checked' ).value;
                userRow.style.display   = val === 'user'   ? 'block' : 'none';
                customRow.style.display = val === 'custom' ? 'block' : 'none';
            }
            toggleType();
            radios.forEach( r => r.addEventListener( 'change', toggleType ) );

            <?php if ( $with_photo ) : ?>
            placeholder.addEventListener( 'click', () => uploadBtn.click() );
            preview.addEventListener( 'click', () => uploadBtn.click() );

            uploadBtn.addEventListener( 'click', function () {
                if ( mediaFrame ) { mediaFrame.open(); return; }
                mediaFrame = wp.media( {
                    title:    'Select Author Photo',
                    button:   { text: 'Use this photo' },
                    library:  { type: 'image' },
                    multiple: false,
                } );
                mediaFrame.on( 'select', function () {
                    const att = mediaFrame.state().get( 'selection' ).first().toJSON();
                    hiddenId.value          = att.id;
                    preview.src             = att.sizes?.thumbnail?.url || att.url;
                    preview.classList.remove( 'is-hidden' );
                    placeholder.classList.add( 'is-hidden' );
                    uploadBtn.textContent   = 'Change photo';
                    removeBtn.style.display = '';
                } );
                mediaFrame.open();
            } );

            removeBtn.addEventListener( 'click', function () {
                hiddenId.value          = '';
                preview.src             = '';
                preview.classList.add( 'is-hidden' );
                placeholder.classList.remove( 'is-hidden' );
                uploadBtn.textContent   = 'Upload photo';
                removeBtn.style.display = 'none';
            } );
            <?php endif; ?>
        } )();
    </script>
    <?php
}


// --- Save ---

add_action( 'save_post', function ( int $post_id ) {
    global $_sp_author_meta_post_types;

    if ( empty( $_sp_author_meta_post_types[ get_post_type( $post_id ) ] ) ) return;

    if (
            ! isset( $_POST['sp_author_meta_nonce'] ) ||
            ! wp_verify_nonce( $_POST['sp_author_meta_nonce'], 'sp_author_meta_nonce' ) ||
            ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
    ) return;

    $opts       = $_sp_author_meta_post_types[ get_post_type( $post_id ) ];
    $with_photo = $opts['with_photo'] ?? true;

    $type = sanitize_text_field( $_POST['author_type'] ?? 'user' );
    update_post_meta( $post_id, '_author_type', $type );

    if ( $type === 'custom' ) {
        update_post_meta( $post_id, '_custom_author', sanitize_text_field( $_POST['custom_author'] ?? '' ) );
        delete_post_meta( $post_id, '_author_user_id' );
    } else {
        delete_post_meta( $post_id, '_custom_author' );
        $uid = ! empty( $_POST['custom_author_user'] ) ? (int) $_POST['custom_author_user'] : 0;
        if ( $uid ) update_post_meta( $post_id, '_author_user_id', $uid );
        else        delete_post_meta( $post_id, '_author_user_id' );
    }

    if ( $with_photo ) {
        $photo_id = ( isset( $_POST['author_photo_id'] ) && $_POST['author_photo_id'] !== '' )
                ? (int) $_POST['author_photo_id'] : 0;

        if ( $photo_id > 0 ) update_post_meta( $post_id, '_author_photo_id', $photo_id );
        else                 delete_post_meta( $post_id, '_author_photo_id' );
    }
} );


// --- Helper ---

if ( ! function_exists( 'sp_get_post_author' ) ) {
    function sp_get_post_author( int $post_id, string $photo_size = 'thumbnail' ): array {
        $type     = get_post_meta( $post_id, '_author_type', true ) ?: 'user';
        $photo_id = (int) get_post_meta( $post_id, '_author_photo_id', true );

        if ( $type === 'custom' ) {
            $name = (string) get_post_meta( $post_id, '_custom_author', true );
        } else {
            $uid  = (int) get_post_meta( $post_id, '_author_user_id', true );
            if ( $uid ) {
                $u    = get_userdata( $uid );
                $name = $u ? $u->display_name : '';
            } else {
                $p    = get_post( $post_id );
                $name = $p ? get_the_author_meta( 'display_name', $p->post_author ) : '';
            }
        }

        $photo = null;
        if ( $photo_id > 0 ) {
            $src = wp_get_attachment_image_src( $photo_id, $photo_size );
            if ( is_array( $src ) && ! empty( $src[0] ) ) {
                $photo = [
                        'ID'     => $photo_id,
                        'url'    => (string) $src[0],
                        'width'  => (int) ( $src[1] ?? 0 ),
                        'height' => (int) ( $src[2] ?? 0 ),
                        'alt'    => (string) ( get_post_meta( $photo_id, '_wp_attachment_image_alt', true ) ?: '' ),
                ];
            }
        }

        return [
                'name'     => $name,
                'photo_id' => $photo_id,
                'photo'    => $photo,
        ];
    }
}
