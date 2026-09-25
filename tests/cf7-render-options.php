<?php
/** Run: php tests/cf7-render-options.php */
define('ABSPATH', __DIR__ . '/');
$GLOBALS['hooks'] = [];
$GLOBALS['meta'] = [];
function add_filter($name, $callback, $priority = 10, $argc = 1) { $GLOBALS['hooks'][$name][$priority][] = [$callback, $argc]; }
function add_action(...$args) { add_filter(...$args); }
function apply_filters($name, $value, ...$args) {
    $hooks = $GLOBALS['hooks'][$name] ?? []; ksort($hooks);
    foreach ($hooks as $callbacks) foreach ($callbacks as [$callback, $argc]) $value = $callback(...array_slice([$value, ...$args], 0, $argc));
    return $value;
}
function do_action($name, ...$args) {
    $hooks = $GLOBALS['hooks'][$name] ?? []; ksort($hooks);
    foreach ($hooks as $callbacks) foreach ($callbacks as [$callback, $argc]) $callback(...array_slice($args, 0, $argc));
}
function sanitize_key($s) { return preg_replace('/[^a-z0-9_-]/', '', strtolower($s)); }
function absint($n) { return abs((int) $n); }
function get_post_meta($id, $key, $single) { return $GLOBALS['meta'][$id][$key] ?? ''; }
function update_post_meta($id, $key, $value) { $GLOBALS['meta'][$id][$key] = $value; }
function get_posts($args) { return []; }
function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
function esc_html($s) { return esc_attr($s); }
function esc_html__($s, $domain = '') { return esc_html($s); }
function __($s, $domain = '') { return $s; }
function wp_kses_post($s) { return $s; }
function do_shortcode($s) { return '<form>CF7</form>'; }
function wp_nonce_field(...$args) {}
function selected($a, $b) { if ($a == $b) echo 'selected'; }
function checked($a, $b) { if ($a == $b) echo 'checked'; }
function wp_verify_nonce(...$args) { return true; }
function current_user_can(...$args) { return true; }
function wp_unslash($s) { return $s; }
function sanitize_text_field($s) { return $s; }
function esc_url_raw($s) { return $s; }
function is_admin() { return false; }
require dirname(__DIR__) . '/src/Bootstrapper.php';
require dirname(__DIR__) . '/plugins/sp-cf7/modules/sp-cf7-redirects/index.php';
require dirname(__DIR__) . '/plugins/sp-cf7/modules/sp-cf7-messages/index.php';
function check($condition, $message) { if (!$condition) throw new RuntimeException($message); echo "PASS $message\n"; }
function config($value) {
    $p = new ReflectionProperty(SoinProduction\Kit\Bootstrapper::class, 'moduleConfigs');
    $p->setAccessible(true); $p->setValue(null, ['plugins' => ['sp-cf7' => $value]]);
}
check(sp_cf7_allowed_submit_actions() === ['none', 'redirect', 'modal', 'message'], 'Default actions remain compatible');
config(['submit_actions'=>['redirect', 'message']]);
check(sp_cf7_allowed_submit_actions() === ['none', 'redirect', 'message'], 'Config restricts extension actions and keeps Default');
$GLOBALS['meta'][6]['_cf7_action_type'] = 'modal';
$GLOBALS['meta'][6]['_cf7_success_modal'] = 99;
ob_start(); cf7_custom_redirect_metabox(6); $html = ob_get_clean();
check(!str_contains($html, 'cf7_action_modal') && !str_contains($html, 'cf7-modal-fields') && str_contains($html, 'cf7_action_message'), 'Disabled modal choice and fields absent');
$_POST = ['cf7_redirect_nonce'=>'nonce', 'cf7_action_type'=>'modal'];
cf7_save_redirect_settings(6);
check($GLOBALS['meta'][6]['_cf7_action_type'] === 'none' && $GLOBALS['meta'][6]['_cf7_success_modal'] === 99, 'Saving rejects disabled action without erasing modal target');
$GLOBALS['meta'][6]['_cf7_action_type'] = 'modal';
ob_start(); cf7_add_data_attributes_to_forms(); echo '<div class="wpcf7" data-wpcf7-id="6"></div>'; ob_end_flush(); $html = ob_get_clean();
check(!str_contains($html, 'data-action-type') && !str_contains($html, 'data-success-modal'), 'Frontend ignores saved disabled actions');
config(['submit_actions'=>[]]);
ob_start(); SP_CF7_Messages::render_action_fields(6, 'message'); $html = ob_get_clean();
check($html === '' && sp_cf7_allowed_submit_actions() === ['none'], 'Empty action list hides custom message fields');
add_action('sp_cf7_before_form', function($id, $args) { echo '<h2>' . esc_html($args['title'] ?? 'Legacy') . '</h2>'; }, 10, 2);
add_action('sp_cf7_after_form', function($id, $args) { echo '<p>After</p>'; }, 10, 2);
ob_start(); display_form(6, ['title'=>'News & updates']); $html = ob_get_clean();
check(strpos($html, 'data-cf7-message-form') < strpos($html, '<h2>') && str_contains($html, '<h2>News &amp; updates</h2>'), 'Heading receives escaped instance context inside form state');
check(strpos($html, '<p>After</p>') < strpos($html, 'data-cf7-message="success"') && strpos($html, '<p>After</p>') > strpos($html, '</form>'), 'After hook stays inside form state');
ob_start(); display_form(6); $html = ob_get_clean(); check(str_contains($html, '<h2>Legacy</h2>'), 'Single-argument calls still work');
ob_start(); display_form(0); check(ob_get_clean() === '', 'Zero ID produces no markup or hooks');
add_filter('sp_cf7_modules', function($modules) { $GLOBALS['loaded_modules'] = $modules; return []; });
foreach ([null, [], ['sp-cf7-core'], ['submit_actions'=>['none']], ['modules'=>[], 'submit_actions'=>['none']]] as $value) {
    config($value);
    require dirname(__DIR__) . '/plugins/sp-cf7/index.php';
    $expected = $value === null || isset($value['submit_actions']) && !array_key_exists('modules', $value) ? 8 : ($value === ['sp-cf7-core'] ? 1 : 0);
    check(count($GLOBALS['loaded_modules']) === $expected, 'Module loader compatibility: ' . json_encode($value));
}

ob_start(); display_form(6, ['message_target' => '#contact-1']); $html = ob_get_clean();
check(str_contains($html, 'data-cf7-message-target="#contact-1"'), 'Message target passed to frontend');
ob_start(); display_form(6, ['message_target' => '[data-title="contact"]']); $html = ob_get_clean();
check(str_contains($html, '&quot;contact&quot;'), 'Message selector attribute escaped');
ob_start(); display_form(6); $html = ob_get_clean();
check(!str_contains($html, 'data-cf7-message-target='), 'Message target remains opt-in');
