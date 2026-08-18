<?php
/**
 * Determine whether the current request renders the Contact Form 7 editor.
 *
 * Contact Form 7 registers the same `wpcf7` slug as both a top-level menu and
 * a submenu. Depending on the WordPress/CF7 version, the resulting screen ID
 * can therefore use either the `toplevel_page_` or `contact_page_` prefix.
 */
function sp_cf7_redirects_is_editor_screen(): bool
{
    $screen = get_current_screen();
    $screen_id = $screen ? (string) $screen->id : '';
    $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';

    return in_array($screen_id, array(
        'toplevel_page_wpcf7',
        'contact_page_wpcf7',
        'contact_page_wpcf7-new',
    ), true) || in_array($page, array('wpcf7', 'wpcf7-new'), true);
}

// ============================================
// ADMIN STYLES
// ============================================
add_action('admin_head', 'cf7_custom_metabox_styles');

function cf7_custom_metabox_styles()
{
    if (! sp_cf7_redirects_is_editor_screen()) {
        return;
    }
?>
    <style>
        #cf7-submit-action-metabox {
            overflow: hidden;
            background: var(--sp-admin-surface);
            border: 1px solid var(--sp-admin-border);
            border-radius: var(--sp-admin-radius);
            box-shadow: var(--sp-admin-shadow-xs);
            margin: 20px 0;
            color: var(--sp-admin-text);
            font-family: var(--sp-admin-font);
        }

        #cf7-submit-action-metabox .metabox-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            background: var(--sp-admin-surface);
            border-bottom: 1px solid var(--sp-admin-border);
            cursor: pointer;
            transition: background var(--sp-admin-transition);
        }

        #cf7-submit-action-metabox .metabox-header:hover {
            background: var(--sp-admin-surface-subtle);
        }

        #cf7-submit-action-metabox .metabox-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 600;
            color: var(--sp-admin-text);
            margin: 0;
        }

        #cf7-submit-action-metabox .metabox-title svg {
            width: 18px;
            height: 18px;
            color: var(--sp-admin-accent);
        }

        #cf7-submit-action-metabox .metabox-toggle {
            width: 20px;
            height: 20px;
            color: var(--sp-admin-muted);
            transition: transform 0.2s ease;
        }

        #cf7-submit-action-metabox.closed .metabox-toggle {
            transform: rotate(-90deg);
        }

        #cf7-submit-action-metabox .metabox-content {
            padding: 16px;
        }

        #cf7-submit-action-metabox.closed .metabox-content {
            display: none;
        }

        #cf7-submit-action-metabox .cf7-field-group {
            margin-bottom: 16px;
        }

        #cf7-submit-action-metabox .cf7-field-group:last-child {
            margin-bottom: 0;
        }

        #cf7-submit-action-metabox .cf7-radio-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        #cf7-submit-action-metabox .cf7-radio-item {
            position: relative;
        }

        #cf7-submit-action-metabox .cf7-radio-item input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        #cf7-submit-action-metabox .cf7-radio-item label {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            background: var(--sp-admin-surface-subtle);
            border: 1px solid var(--sp-admin-border);
            border-radius: var(--sp-admin-radius-sm);
            font-size: 13px;
            font-weight: 500;
            color: var(--sp-admin-text-2);
            cursor: pointer;
            transition: border-color var(--sp-admin-transition), background var(--sp-admin-transition), box-shadow var(--sp-admin-transition), color var(--sp-admin-transition);
        }

        #cf7-submit-action-metabox .cf7-radio-item label:hover {
            background: var(--sp-admin-accent-softer);
            border-color: var(--sp-admin-accent-bright);
        }

        #cf7-submit-action-metabox .cf7-radio-item input:checked+label {
            background: var(--sp-admin-accent-soft);
            border-color: var(--sp-admin-accent);
            color: var(--sp-admin-text);
            box-shadow: inset 3px 0 0 var(--sp-admin-accent);
        }

        #cf7-submit-action-metabox .cf7-radio-item input:focus-visible+label {
            border-color: var(--sp-admin-accent);
            box-shadow: var(--sp-admin-focus);
        }

        #cf7-submit-action-metabox .cf7-radio-item label svg {
            width: 18px;
            height: 18px;
            color: var(--sp-admin-muted);
        }

        #cf7-submit-action-metabox .cf7-radio-item input:checked+label svg {
            color: var(--sp-admin-accent);
        }

        #cf7-submit-action-metabox .cf7-radio-item .radio-description {
            font-size: 11px;
            font-weight: 400;
            color: var(--sp-admin-muted);
            margin-left: auto;
            text-align: right;
        }

        #cf7-submit-action-metabox .cf7-conditional-fields {
            display: none;
            margin-top: 16px;
            padding: 14px;
            background: var(--sp-admin-surface-subtle);
            border: 1px solid var(--sp-admin-accent);
            border-radius: var(--sp-admin-radius-sm);
        }

        #cf7-submit-action-metabox .cf7-conditional-fields.active {
            display: block;
            animation: cf7SlideDown 0.25s ease;
        }

        @keyframes cf7SlideDown {
            from {
                opacity: 0;
                transform: translateY(-8px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        #cf7-submit-action-metabox .cf7-select-group {
            margin-bottom: 12px;
        }

        #cf7-submit-action-metabox .cf7-select-group:last-child {
            margin-bottom: 0;
        }

        #cf7-submit-action-metabox .cf7-select-label {
            display: block;
            font-size: 12px;
            font-weight: 500;
            color: var(--sp-admin-text-2);
            margin-bottom: 6px;
        }

        #cf7-submit-action-metabox select {
            width: 100%;
            min-height: 36px;
            padding: 0 28px 0 10px;
            border: 1px solid var(--sp-admin-border-strong);
            border-radius: var(--sp-admin-radius-sm);
            background: var(--sp-admin-input-bg) url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20width%3D%2220%22%20height%3D%2220%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%3E%3Cpath%20d%3D%22M5%206l5%205%205-5%202%201-7%207-7-7%202-1z%22%20fill%3D%22%23525b66%22%2F%3E%3C%2Fsvg%3E') no-repeat right 6px center;
            background-size: 16px 16px;
            color: var(--sp-admin-text);
            font-size: 13px;
            cursor: pointer;
            -webkit-appearance: none;
            appearance: none;
        }

        #cf7-submit-action-metabox select:hover {
            border-color: var(--sp-admin-accent-bright);
        }

        #cf7-submit-action-metabox select:focus {
            outline: none;
            border-color: var(--sp-admin-accent);
            box-shadow: var(--sp-admin-focus);
        }
    </style>
<?php
}

// ============================================
// ADMIN SCRIPTS
// ============================================
add_action('admin_footer', 'cf7_custom_metabox_scripts');

function cf7_custom_metabox_scripts()
{
    if (! sp_cf7_redirects_is_editor_screen()) {
        return;
    }
?>
    <script>
        jQuery(document).ready(function($) {
            var $metabox = $('#cf7-submit-action-metabox');
            var $sidebar = $('#informationdiv').closest('.postbox-container');

            if ($sidebar.length && $metabox.length) {
                $sidebar.find('#informationdiv').before($metabox);
            }

            $('input[name="cf7_action_type"]').on('change', function() {
                var val = $(this).val();
                $('.cf7-conditional-fields').removeClass('active');
                $('.cf7-conditional-fields').filter(function() {
                    return String($(this).data('cf7-action') || '') === String(val || '');
                }).addClass('active');
            });
        });
    </script>
<?php
}

// ============================================
// METABOX HTML
// ============================================
add_action('wpcf7_admin_misc_pub_section', 'cf7_custom_redirect_metabox');

function cf7_custom_redirect_metabox($form_id)
{
    if (is_object($form_id) && method_exists($form_id, 'id')) {
        $form_id = $form_id->id();
    }
    $form_id = absint($form_id);

    if (! $form_id) {
        return;
    }

    $action_type   = get_post_meta($form_id, '_cf7_action_type', true);
    $redirect_page = get_post_meta($form_id, '_cf7_redirect_page', true);
    $success_modal = get_post_meta($form_id, '_cf7_success_modal', true);
    $error_modal   = get_post_meta($form_id, '_cf7_error_modal', true);

    $pages = get_posts(array(
        'post_type'      => 'page',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ));

    $modals = get_posts(array(
        'post_type'      => 'modals',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ));

    wp_nonce_field('cf7_redirect_settings', 'cf7_redirect_nonce');

    $action_type = $action_type !== '' ? sanitize_key((string) $action_type) : 'none';
    $action_choices = apply_filters('sp_cf7_redirect_action_choices', array(
        'none' => array(
            'label'       => 'Default',
            'description' => '',
        ),
        'redirect' => array(
            'label'       => 'Redirect',
            'description' => 'Go to page',
        ),
        'modal' => array(
            'label'       => 'Modal',
            'description' => 'Open popup',
        ),
    ), $form_id);
?>

    <div id="cf7-submit-action-metabox" class="sp-admin-component" data-sp-admin-component>
        <div class="metabox-header">
            <h3 class="metabox-title">
                Submit Action
            </h3>
        </div>

        <div class="metabox-content">
            <div class="cf7-field-group">
                <div class="cf7-radio-group">
                    <?php foreach ((array) $action_choices as $choice_value => $choice) :
                        $choice_value = sanitize_key((string) $choice_value);
                        if ($choice_value === '' || ! is_array($choice)) continue;

                        $choice_label = (string) ($choice['label'] ?? $choice_value);
                        $choice_description = (string) ($choice['description'] ?? '');
                        $choice_id = 'cf7_action_' . $choice_value;
                        ?>
                        <div class="cf7-radio-item">
                            <input
                                type="radio"
                                name="cf7_action_type"
                                id="<?php echo esc_attr($choice_id); ?>"
                                value="<?php echo esc_attr($choice_value); ?>"
                                <?php checked($action_type, $choice_value); ?>>
                            <label for="<?php echo esc_attr($choice_id); ?>">
                                <?php echo esc_html($choice_label); ?>
                                <?php if ($choice_description !== '') : ?>
                                    <span class="radio-description"><?php echo esc_html($choice_description); ?></span>
                                <?php endif; ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Redirect Options -->
            <div class="cf7-conditional-fields cf7-redirect-fields <?php echo $action_type === 'redirect' ? 'active' : ''; ?>" data-cf7-action="redirect">
                <div class="cf7-select-group">
                    <label class="cf7-select-label" for="cf7_redirect_page">Thank You Page</label>
                    <select name="cf7_redirect_page" id="cf7_redirect_page">
                        <option value="">— Select Page —</option>
                        <?php foreach ($pages as $page) : ?>
                            <option value="<?php echo esc_attr(get_permalink($page->ID)); ?>" <?php selected($redirect_page, get_permalink($page->ID)); ?>>
                                <?php echo esc_html($page->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Modal Options -->
            <div class="cf7-conditional-fields cf7-modal-fields <?php echo $action_type === 'modal' ? 'active' : ''; ?>" data-cf7-action="modal">
                <div class="cf7-select-group">
                    <label class="cf7-select-label" for="cf7_success_modal">Success Modal</label>
                    <select name="cf7_success_modal" id="cf7_success_modal">
                        <option value="">— Select Modal —</option>
                        <?php foreach ($modals as $modal) : ?>
                            <option value="<?php echo esc_attr($modal->ID); ?>" <?php selected($success_modal, $modal->ID); ?>>
                                <?php echo esc_html($modal->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="cf7-select-group">
                    <label class="cf7-select-label" for="cf7_error_modal">Error Modal</label>
                    <select name="cf7_error_modal" id="cf7_error_modal">
                        <option value="">— Select Modal —</option>
                        <?php foreach ($modals as $modal) : ?>
                            <option value="<?php echo esc_attr($modal->ID); ?>" <?php selected($error_modal, $modal->ID); ?>>
                                <?php echo esc_html($modal->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <?php do_action('sp_cf7_redirect_action_fields', $form_id, $action_type); ?>
        </div>
    </div>

<?php
}

// ============================================
// SAVE METABOX DATA
// ============================================
add_action('wpcf7_save_contact_form', 'cf7_save_redirect_settings', 10, 1);

function cf7_save_redirect_settings($contact_form)
{
    if (
        ! isset($_POST['cf7_redirect_nonce'])
        || ! wp_verify_nonce(
            sanitize_text_field(wp_unslash($_POST['cf7_redirect_nonce'])),
            'cf7_redirect_settings'
        )
    ) {
        return;
    }

    if (is_object($contact_form) && method_exists($contact_form, 'id')) {
        $form_id = $contact_form->id();
    } else {
        $form_id = absint($contact_form);
    }

    if (! $form_id || ! current_user_can('wpcf7_edit_contact_form', $form_id)) {
        return;
    }

    $allowed_action_types = apply_filters('sp_cf7_redirect_action_types', array('none', 'redirect', 'modal'));
    $allowed_action_types = array_values(array_filter(array_map('sanitize_key', (array) $allowed_action_types)));
    $action_type = isset($_POST['cf7_action_type'])
        ? sanitize_key(wp_unslash($_POST['cf7_action_type']))
        : 'none';
    if (! in_array($action_type, $allowed_action_types, true)) {
        $action_type = 'none';
    }

    $redirect_page = isset($_POST['cf7_redirect_page'])
        ? esc_url_raw(wp_unslash($_POST['cf7_redirect_page']))
        : '';
    $success_modal = isset($_POST['cf7_success_modal'])
        ? absint(wp_unslash($_POST['cf7_success_modal']))
        : 0;
    $error_modal = isset($_POST['cf7_error_modal'])
        ? absint(wp_unslash($_POST['cf7_error_modal']))
        : 0;

    update_post_meta($form_id, '_cf7_action_type', $action_type);
    update_post_meta($form_id, '_cf7_redirect_page', $redirect_page);
    update_post_meta($form_id, '_cf7_success_modal', $success_modal);
    update_post_meta($form_id, '_cf7_error_modal', $error_modal);
}

// ============================================
// ADD DATA ATTRIBUTES TO FORM (FRONTEND)
// ============================================
add_action('wp_loaded', 'cf7_add_data_attributes_to_forms');

function cf7_add_data_attributes_to_forms()
{
    if (is_admin()) {
        return;
    }

    ob_start(function ($html) {
        // Find all CF7 forms
        $pattern = '/<div([^>]*)class="([^"]*wpcf7[^"]*)"([^>]*)data-wpcf7-id="(\d+)"([^>]*)>/';

        $html = preg_replace_callback($pattern, function ($matches) {
            $before_class = $matches[1];
            $class        = $matches[2];
            $between      = $matches[3];
            $form_id      = $matches[4];
            $after_id     = $matches[5];

            // Always add data-loader="false"
            $data_attrs = 'data-loader="false" ';

            // Get form settings
            $action_type = get_post_meta($form_id, '_cf7_action_type', true);

            if ($action_type && $action_type !== 'none') {
                $data_attrs .= 'data-action-type="' . esc_attr($action_type) . '" ';

                if ($action_type === 'redirect') {
                    $redirect_page = get_post_meta($form_id, '_cf7_redirect_page', true);
                    if ($redirect_page) {
                        $data_attrs .= 'data-redirect-url="' . esc_attr($redirect_page) . '" ';
                    }
                }

                if ($action_type === 'modal') {
                    $success_modal = get_post_meta($form_id, '_cf7_success_modal', true);
                    $error_modal   = get_post_meta($form_id, '_cf7_error_modal', true);

                    if ($success_modal) {
                        $data_attrs .= 'data-success-modal="' . esc_attr($success_modal) . '" ';
                    }
                    if ($error_modal) {
                        $data_attrs .= 'data-error-modal="' . esc_attr($error_modal) . '" ';
                    }
                }
            }

            return '<div ' . $data_attrs . $before_class . 'class="' . $class . '"' . $between . 'data-wpcf7-id="' . $form_id . '"' . $after_id . '>';
        }, $html);

        return $html;
    });
}
