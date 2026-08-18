<?php
if (! defined('ABSPATH')) exit;

/**
 * Rich post-submit messages for Contact Form 7.
 *
 * Each CF7 form is linked one-to-one with a hidden settings post containing
 * ACF WYSIWYG fields. Editors manage that post in an iframe modal opened from
 * the existing Submit Action panel.
 */
final class SP_CF7_Messages
{
    private const POST_TYPE = 'sp-cf7-message';
    private const FORM_META_KEY = '_sp_cf7_message_post_id';
    private const MESSAGE_FORM_META_KEY = '_sp_cf7_form_id';
    private const ACF_GROUP_KEY = 'group_sp_cf7_messages';
    public static function init(): void
    {
        add_action('init', [self::class, 'register_post_type'], 8);
        add_action('acf/init', [self::class, 'register_acf_fields']);

        add_filter('sp_cf7_redirect_action_choices', [self::class, 'add_action_choice'], 10, 2);
        add_filter('sp_cf7_redirect_action_types', [self::class, 'add_allowed_action_type']);
        add_action('sp_cf7_redirect_action_fields', [self::class, 'render_action_fields'], 10, 2);

        add_action('admin_footer', [self::class, 'render_admin_modal'], 30);
        add_action('admin_post_sp_cf7_messages_editor', [self::class, 'render_editor_iframe']);

        add_action('wpcf7_save_contact_form', [self::class, 'sync_form_settings_post'], 20, 1);
        add_action('before_delete_post', [self::class, 'delete_linked_settings_post'], 10, 2);
    }

    public static function register_post_type(): void
    {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name'          => __('CF7 Messages', 'sp-cf7'),
                'singular_name' => __('CF7 Messages', 'sp-cf7'),
            ],
            'public'              => false,
            'publicly_queryable'  => false,
            'exclude_from_search' => true,
            'show_ui'             => false,
            'show_in_menu'        => false,
            'show_in_rest'        => false,
            'has_archive'         => false,
            'rewrite'             => false,
            'supports'            => ['title'],
        ]);
    }

    public static function register_acf_fields(): void
    {
        if (! function_exists('acf_add_local_field_group')) return;

        acf_add_local_field_group([
            'key' => self::ACF_GROUP_KEY,
            'title' => __('Form messages', 'sp-cf7'),
            'fields' => [
                [
                    'key' => 'field_sp_cf7_success_message',
                    'label' => __('Success message', 'sp-cf7'),
                    'name' => 'success_message',
                    'type' => 'wysiwyg',
                    'instructions' => __('Shown after a successful form submission.', 'sp-cf7'),
                    'tabs' => 'all',
                    'toolbar' => 'full',
                    'media_upload' => 1,
                    'delay' => 0,
                ],
                [
                    'key' => 'field_sp_cf7_error_message',
                    'label' => __('Error message', 'sp-cf7'),
                    'name' => 'error_message',
                    'type' => 'wysiwyg',
                    'instructions' => __('Shown for validation, spam, aborted and mail delivery errors.', 'sp-cf7'),
                    'tabs' => 'all',
                    'toolbar' => 'full',
                    'media_upload' => 1,
                    'delay' => 0,
                ],
            ],
            'location' => [
                [
                    [
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => self::POST_TYPE,
                    ],
                ],
            ],
            'menu_order' => 0,
            'position' => 'normal',
            'style' => 'seamless',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
            'hide_on_screen' => ['permalink', 'the_content', 'excerpt', 'discussion', 'comments', 'revisions'],
            'active' => true,
        ]);
    }

    public static function add_action_choice(array $choices, int $form_id): array
    {
        unset($form_id);
        $choices['message'] = [
            'label'       => __('Custom message', 'sp-cf7'),
            'description' => __('Rich success/error content', 'sp-cf7'),
        ];

        return $choices;
    }

    public static function add_allowed_action_type(array $types): array
    {
        $types[] = 'message';
        return array_values(array_unique($types));
    }

    public static function render_action_fields(int $form_id, string $action_type): void
    {
        $message_post_id = self::find_settings_post_id($form_id);
        $editor_url = $message_post_id > 0 ? self::get_editor_url($form_id) : '';
        ?>
        <div class="cf7-conditional-fields sp-cf7-message-fields <?php echo $action_type === 'message' ? 'active' : ''; ?>" data-cf7-action="message">
            <?php if ($editor_url !== '') : ?>
                <button
                    type="button"
                    class="button button-secondary sp-cf7-message-edit"
                    data-sp-cf7-message-editor="<?php echo esc_url($editor_url); ?>">
                    <?php echo esc_html__('Edit messages', 'sp-cf7'); ?>
                </button>
                <p class="description">
                    <?php echo esc_html__('Configure the rich success and error messages for this form.', 'sp-cf7'); ?>
                </p>
            <?php else : ?>
                <p class="description">
                    <?php echo esc_html__('Save the form before editing its custom messages.', 'sp-cf7'); ?>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function sync_form_settings_post($contact_form): void
    {
        $form_id = self::get_contact_form_id($contact_form);
        if ($form_id <= 0 || ! current_user_can('wpcf7_edit_contact_form', $form_id)) return;

        $message_post_id = self::find_settings_post_id($form_id);
        $action_type = sanitize_key((string) get_post_meta($form_id, '_cf7_action_type', true));
        if ($message_post_id <= 0 && $action_type !== 'message') return;

        self::get_or_create_settings_post($form_id, true);
    }

    public static function delete_linked_settings_post(int $post_id, WP_Post $post): void
    {
        if ($post->post_type !== 'wpcf7_contact_form') return;

        $message_post_id = self::find_settings_post_id($post_id);
        if ($message_post_id > 0) {
            wp_delete_post($message_post_id, true);
        }
    }

    private static function get_contact_form_id($contact_form): int
    {
        if (is_object($contact_form) && method_exists($contact_form, 'id')) {
            return absint($contact_form->id());
        }

        return absint($contact_form);
    }

    private static function find_settings_post_id(int $form_id): int
    {
        if ($form_id <= 0) return 0;

        $linked_id = (int) get_post_meta($form_id, self::FORM_META_KEY, true);
        if (
            $linked_id > 0
            && get_post_type($linked_id) === self::POST_TYPE
            && (int) get_post_meta($linked_id, self::MESSAGE_FORM_META_KEY, true) === $form_id
        ) {
            return $linked_id;
        }

        $matches = get_posts([
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_key'       => self::MESSAGE_FORM_META_KEY,
            'meta_value'     => $form_id,
            'orderby'        => 'ID',
            'order'          => 'ASC',
            'no_found_rows'  => true,
        ]);

        $message_post_id = isset($matches[0]) ? absint($matches[0]) : 0;
        if ($message_post_id > 0) {
            update_post_meta($form_id, self::FORM_META_KEY, $message_post_id);
        }

        return $message_post_id;
    }

    public static function get_settings_post_id(int $form_id): int
    {
        return self::find_settings_post_id($form_id);
    }

    public static function get_message(int $form_id, string $message_type, bool $format_value = true): string
    {
        $field_name = sanitize_key($message_type);
        if (! in_array($field_name, ['success_message', 'error_message'], true)) return '';

        $message_post_id = self::find_settings_post_id($form_id);
        if ($message_post_id <= 0) return '';

        $value = function_exists('get_field')
            ? get_field($field_name, $message_post_id, $format_value)
            : get_post_meta($message_post_id, $field_name, true);

        return is_string($value) ? $value : '';
    }

    private static function get_or_create_settings_post(int $form_id, bool $sync_title = false): int
    {
        if (
            $form_id <= 0
            || get_post_type($form_id) !== 'wpcf7_contact_form'
            || ! current_user_can('wpcf7_edit_contact_form', $form_id)
        ) {
            return 0;
        }

        $message_post_id = self::find_settings_post_id($form_id);
        $title = sprintf(
            __('Messages — %s', 'sp-cf7'),
            get_the_title($form_id) ?: sprintf(__('Form #%d', 'sp-cf7'), $form_id)
        );

        if ($message_post_id <= 0) {
            $message_post_id = wp_insert_post([
                'post_type'   => self::POST_TYPE,
                'post_status' => 'publish',
                'post_title'  => $title,
            ], true);

            if (is_wp_error($message_post_id)) return 0;

            $message_post_id = absint($message_post_id);
            update_post_meta($message_post_id, self::MESSAGE_FORM_META_KEY, $form_id);
            update_post_meta($form_id, self::FORM_META_KEY, $message_post_id);
        } elseif ($sync_title && get_the_title($message_post_id) !== $title) {
            wp_update_post([
                'ID'         => $message_post_id,
                'post_title' => $title,
            ]);
        }

        return $message_post_id;
    }

    private static function get_editor_url(int $form_id): string
    {
        $url = add_query_arg([
            'action'  => 'sp_cf7_messages_editor',
            'form_id' => $form_id,
        ], admin_url('admin-post.php'));

        return self::add_editor_nonce($url, $form_id);
    }

    private static function add_editor_nonce(string $url, int $form_id): string
    {
        return add_query_arg(
            '_wpnonce',
            wp_create_nonce('sp_cf7_messages_editor_' . $form_id),
            $url
        );
    }

    public static function render_admin_modal(): void
    {
        if (
            ! function_exists('sp_cf7_redirects_is_editor_screen')
            || ! sp_cf7_redirects_is_editor_screen()
        ) {
            return;
        }
        ?>
        <style>
            .sp-cf7-message-modal[hidden] { display: none !important; }
            .sp-cf7-message-modal { position: fixed; inset: 0; z-index: 100100; display: flex; align-items: center; justify-content: center; padding: 24px; background: rgb(0 0 0 / 55%); }
            .sp-cf7-message-modal__dialog { display: flex; flex-direction: column; width: min(1080px, 100%); height: min(820px, calc(100vh - 48px)); overflow: hidden; border: 1px solid var(--sp-admin-border, #c3c4c7); border-radius: var(--sp-admin-radius, 4px); background: var(--sp-admin-surface, #fff); box-shadow: 0 20px 60px rgb(0 0 0 / 24%); }
            .sp-cf7-message-modal__header { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 12px 16px; border-bottom: 1px solid var(--sp-admin-border, #dcdcde); }
            .sp-cf7-message-modal__header h2 { margin: 0; font-size: 16px; }
            .sp-cf7-message-modal__close { display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; padding: 0; border: 0; background: transparent; color: var(--sp-admin-text, #1d2327); font-size: 26px; line-height: 1; cursor: pointer; }
            .sp-cf7-message-modal__frame { flex: 1; width: 100%; min-height: 0; border: 0; background: #fff; }
            .sp-cf7-message-modal__status { min-height: 18px; margin-left: auto; color: var(--sp-admin-success, #008a20); font-size: 12px; }
            .sp-cf7-message-fields .button { display: inline-flex; width: 100%; align-items: center; justify-content: center; }
            .sp-cf7-message-fields .description { margin: 8px 0 0; }
        </style>

        <div class="sp-cf7-message-modal" id="sp-cf7-message-modal" hidden>
            <div class="sp-cf7-message-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="sp-cf7-message-modal-title">
                <div class="sp-cf7-message-modal__header">
                    <h2 id="sp-cf7-message-modal-title"><?php echo esc_html__('Form messages', 'sp-cf7'); ?></h2>
                    <span class="sp-cf7-message-modal__status" aria-live="polite"></span>
                    <button type="button" class="sp-cf7-message-modal__close" aria-label="<?php echo esc_attr__('Close', 'sp-cf7'); ?>">&times;</button>
                </div>
                <iframe class="sp-cf7-message-modal__frame" title="<?php echo esc_attr__('Edit form messages', 'sp-cf7'); ?>"></iframe>
            </div>
        </div>

        <script>
            (function() {
                'use strict';

                var modal = document.getElementById('sp-cf7-message-modal');
                if (!modal) return;

                var frame = modal.querySelector('.sp-cf7-message-modal__frame');
                var closeButton = modal.querySelector('.sp-cf7-message-modal__close');
                var status = modal.querySelector('.sp-cf7-message-modal__status');
                var lastTrigger = null;

                function closeModal() {
                    modal.hidden = true;
                    document.body.style.overflow = '';
                    frame.src = 'about:blank';
                    status.textContent = '';
                    if (lastTrigger) lastTrigger.focus();
                }

                document.addEventListener('click', function(event) {
                    var trigger = event.target.closest('[data-sp-cf7-message-editor]');
                    if (!trigger) return;

                    event.preventDefault();
                    lastTrigger = trigger;
                    status.textContent = '';
                    frame.src = trigger.getAttribute('data-sp-cf7-message-editor');
                    modal.hidden = false;
                    document.body.style.overflow = 'hidden';
                    closeButton.focus();
                });

                closeButton.addEventListener('click', closeModal);
                modal.addEventListener('click', function(event) {
                    if (event.target === modal) closeModal();
                });
                document.addEventListener('keydown', function(event) {
                    if (!modal.hidden && event.key === 'Escape') closeModal();
                });
                window.addEventListener('message', function(event) {
                    if (event.origin !== window.location.origin) return;
                    if (!event.data || event.data.type !== 'sp_cf7_messages_saved') return;
                    status.textContent = '<?php echo esc_js(__('Saved.', 'sp-cf7')); ?>';
                });
            })();
        </script>
        <?php
    }

    public static function render_editor_iframe(): void
    {
        $form_id = isset($_GET['form_id']) ? absint(wp_unslash($_GET['form_id'])) : 0;
        if (
            $form_id <= 0
            || get_post_type($form_id) !== 'wpcf7_contact_form'
            || ! current_user_can('wpcf7_edit_contact_form', $form_id)
        ) {
            wp_die(esc_html__('You are not allowed to edit these form messages.', 'sp-cf7'));
        }

        if (
            ! isset($_GET['_wpnonce'])
            || ! wp_verify_nonce(
                sanitize_text_field(wp_unslash($_GET['_wpnonce'])),
                'sp_cf7_messages_editor_' . $form_id
            )
        ) {
            wp_die(esc_html__('The message editor link has expired. Close the window and open it again.', 'sp-cf7'));
        }

        $message_post_id = self::get_or_create_settings_post($form_id, true);
        if ($message_post_id <= 0 || ! function_exists('acf_form')) {
            wp_die(esc_html__('The message editor is unavailable.', 'sp-cf7'));
        }

        if (function_exists('acf_form_head')) {
            acf_form_head();
        }

        show_admin_bar(false);
        global $hook_suffix;
        $hook_suffix = 'sp-cf7-messages-iframe';
        if (function_exists('set_current_screen')) {
            try {
                set_current_screen('sp-cf7-messages-iframe');
            } catch (Throwable $error) {
                unset($error);
            }
        }

        iframe_header(__('Edit form messages', 'sp-cf7'));

        $return_url = add_query_arg([
            'action'  => 'sp_cf7_messages_editor',
            'form_id' => $form_id,
            'saved'   => 1,
        ], admin_url('admin-post.php'));
        $return_url = self::add_editor_nonce($return_url, $form_id);
        ?>
        <style>
            html, body { min-height: 100%; background: #fff !important; }
            body.iframe { box-sizing: border-box; margin: 0 !important; padding: 20px !important; }
            #wpadminbar, #adminmenuwrap, #adminmenuback, #wpfooter { display: none !important; }
            #wpcontent { margin-left: 0 !important; padding: 0 !important; }
            .acf-form-submit { position: sticky; bottom: 0; z-index: 5; margin: 20px -20px -20px; padding: 14px 20px; border-top: 1px solid #dcdcde; background: #fff; }
            .acf-form-submit .button { min-height: 36px; padding: 0 18px; }
        </style>

        <?php if (isset($_GET['saved']) && (string) $_GET['saved'] === '1') : ?>
            <script>
                if (window.parent && window.parent !== window) {
                    window.parent.postMessage({ type: 'sp_cf7_messages_saved' }, window.location.origin);
                }
            </script>
        <?php endif; ?>

        <div class="sp-cf7-message-editor">
            <?php
            acf_form([
                'post_id'      => $message_post_id,
                'field_groups' => [self::ACF_GROUP_KEY],
                'form'         => true,
                'return'       => esc_url_raw($return_url),
                'submit_value' => __('Save messages', 'sp-cf7'),
                'updated_message' => __('Messages saved.', 'sp-cf7'),
            ]);
            ?>
        </div>
        <?php
        iframe_footer();
        exit;
    }

}

SP_CF7_Messages::init();

if (! function_exists('sp_cf7_messages_get_post_id')) {
    function sp_cf7_messages_get_post_id(int $form_id): int
    {
        return SP_CF7_Messages::get_settings_post_id($form_id);
    }
}

if (! function_exists('sp_cf7_messages_get_message')) {
    function sp_cf7_messages_get_message(
        int $form_id,
        string $message_type,
        bool $format_value = true
    ): string {
        return SP_CF7_Messages::get_message($form_id, $message_type, $format_value);
    }
}
