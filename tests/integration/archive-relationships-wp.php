<?php
/**
 * Run with `wp eval-file tests/integration/archive-relationships-wp.php` on a local fixture
 * site with this PHP Kit version loaded. Temporary private post types, posts,
 * terms and archive transients are removed in finally.
 */
if (! defined('WP_CLI') || ! WP_CLI) {
    exit(1);
}

$checks = 0;
$check = static function ($condition, string $message) use (&$checks): void {
    ++$checks;
    if (! $condition) {
        throw new RuntimeException($message);
    }
};
$ids = [];
$tokens = [];
$old_get = $_GET;
$old_post = $_POST;
$ajax = false;
add_filter('wp_doing_ajax', static function () use (&$ajax): bool { return $ajax; });
$die_handler = static fn() => static function (): void { throw new RuntimeException('archive-test-json'); };
add_filter('wp_die_ajax_handler', $die_handler);
add_filter('wp_die_handler', $die_handler);
$fixture_name = 'sp-archive-test-' . wp_generate_uuid4();
$card = 'php/cards/' . $fixture_name . '-card';
$empty = 'php/cards/' . $fixture_name . '-empty';
$fixture_files = [];
add_action('get_template_part_' . $card, static function ($slug, $name, $args): void {
    echo '<article data-test-id="' . (int) $args['post_id'] . '"></article>';
}, 10, 3);
add_action('get_template_part_' . $empty, static function (): void { echo '<p>No matches</p>'; });

try {
    foreach ([$card, $empty] as $template) {
        $file = THEME_DIR . '/' . $template . '.php';
        if (file_put_contents($file, '<?php // Temporary archive test fixture.') === false) {
            throw new RuntimeException('Cannot create template fixture');
        }
        $fixture_files[] = $file;
    }
    foreach (['sp_arc_item', 'sp_arc_service', 'sp_arc_market'] as $type) {
        register_post_type($type, ['public' => false]);
    }
    register_taxonomy('sp_arc_category', 'sp_arc_item', ['public' => false]);
    $make = static function (string $type, string $title, string $status = 'publish') use (&$ids): int {
        $id = wp_insert_post(['post_type' => $type, 'post_title' => $title, 'post_status' => $status], true);
        if (is_wp_error($id)) { throw new RuntimeException($id->get_error_message()); }
        $ids[] = (int) $id;
        return (int) $id;
    };
    $a = $make('sp_arc_item', 'A');
    $b = $make('sp_arc_item', 'B');
    $c = $make('sp_arc_item', 'C');
    $d = $make('sp_arc_item', 'D');
    $draft_item = $make('sp_arc_item', 'Draft', 'draft');
    $market = $make('sp_arc_market', 'Market');
    $service = $make('sp_arc_service', 'Service');
    $other_service = $make('sp_arc_service', 'Other');
    $empty_service = $make('sp_arc_service', 'Empty');
    $draft_service = $make('sp_arc_service', 'Hidden', 'draft');
    update_post_meta($market, 'linked_sp_arc_item', [$a, $b, $c, $draft_item]);
    update_post_meta($service, 'linked_sp_arc_item', [(string) $b, (string) $c, (string) $d]);
    update_post_meta($other_service, 'linked_sp_arc_item', [$a]);
    update_post_meta($draft_service, 'linked_sp_arc_item', [$a]);
    wp_set_object_terms($b, 'Included', 'sp_arc_category');

    $definitions = [
        'industry' => ['post_type' => 'sp_arc_market', 'reverse_field' => 'linked_sp_arc_item', 'query_arg' => 'industry-id', 'all_label' => 'Industries'],
        'service' => ['post_type' => 'sp_arc_service', 'reverse_field' => 'linked_sp_arc_item'],
    ];
    $filters = sp_archive_normalize_relationship_filters($definitions);
    $base = ['post_type' => 'sp_arc_item', 'filters' => $filters, 'sort' => 'az', 'per_page' => -1];
    $query = static fn(array $values, array $extra = []) => new WP_Query(sp_archive_query_args(array_merge($base, ['filter_values' => $values], $extra)));
    $post_ids = static fn($query) => array_map('intval', wp_list_pluck($query->posts, 'ID'));

    $check(count($filters) === 2 && $filters[0]['type'] === 'relationship', 'Definitions normalize');
    $check(sp_archive_normalize_relationship_filters(['bad' => ['post_type' => 'missing', 'reverse_field' => 'x']]) === [], 'Unknown source type rejected');
    $check(sp_archive_normalize_relationship_filters(['bad' => ['post_type' => 'sp_arc_market']]) === [], 'Missing reverse field rejected');
    $check(! isset(sp_archive_relationship_options($filters[1])[$draft_service]), 'Draft sources excluded');
    $check($post_ids($query([])) === [$a, $b, $c, $d], 'No filters return all published archive posts');
    $check($post_ids($query(['industry' => (string) $market])) === [$a, $b, $c], 'One relationship excludes draft target');
    $check($post_ids($query(['industry' => (string) $market, 'service' => (string) $service])) === [$b, $c], 'Two relationships intersect');
    $check($post_ids($query(['service' => $service . '|' . $other_service])) === [$a, $b, $c, $d], 'Multiple values within one filter union');
    $check($post_ids($query(['service' => (string) $empty_service])) === [], 'Empty relationship returns no posts');
    $check($post_ids($query(['service' => (string) $draft_service])) === [], 'Draft source ID cannot be forged');
    $check($post_ids($query(['service' => (string) $market])) === [], 'Wrong source type cannot be forged');
    $check($post_ids($query(['service' => $service . 'abc'])) === [], 'Malformed ID does not coerce');
    $check($post_ids($query(['service' => $service . '!'])) === [], 'Punctuation must not sanitize into a valid ID');
    $check($post_ids($query(['service' => ['unexpected' => ['nested']]])) === [], 'Nested input cannot broaden query');
    $check(sp_archive_filter_values($filters, ['service' => '!!!'])['service'] === '__invalid__', 'Malformed request remains restrictive');
    $check($post_ids($query(['service' => '999999999'])) === [], 'Unknown ID returns no posts');
    $check($post_ids($query(['service' => (string) $service], ['post__in' => [$c, $a, $b], 'sort' => 'manual'])) === [$c, $b], 'Manual selection intersection preserves its order');
    $check($post_ids($query(['service' => (string) $service], ['post__in' => []])) === [], 'Empty manual selection stays empty');
    $check($post_ids($query(['service' => (string) $service], ['term_scope' => ['sp_arc_category' => ['included']]])) === [$b], 'Taxonomy scope combines with relationships');
    $taxonomy_filter = ['name' => 'category', 'taxonomy' => 'sp_arc_category', 'field' => 'slug', 'operator' => 'IN'];
    $check($post_ids($query(['service' => (string) $service, 'category' => 'included'], ['filters' => array_merge($filters, [$taxonomy_filter])])) === [$b], 'Taxonomy filter combines with relationships');
    $paged = $query(['service' => (string) $service], ['per_page' => 1, 'paged' => 2, 'sort' => 'za']);
    $check($post_ids($paged) === [$c] && (int) $paged->found_posts === 3 && (int) $paged->max_num_pages === 3, 'SQL sorting, pagination and totals');
    $check(sp_archive_filter_values($filters, ['industry-id' => (string) $market])['industry'] === (string) $market, 'Custom URL argument works');

    $availability = sp_archive_filter_availability(array_merge($base, ['filter_values' => ['industry' => (string) $market]]));
    $check($availability['service'][$service] === true && $availability['service'][$empty_service] === false, 'Availability uses relationship intersections');
    $config = ['post_type' => 'sp_arc_item', 'filters_enabled' => 1, 'relationship_filters' => $definitions, 'per_page' => 1, 'order_mode' => 'az'];
    $legacy = new WP_Query(sp_archive_builder_query_args(array_merge($config, ['per_page' => -1]), ['industry-id' => (string) $market, 'service' => (string) $service]));
    $check($post_ids($legacy) === [$b, $c], 'Low-level builder query helper supports relationship values');
    $_GET = ['industry-id' => (string) $market, 'service' => (string) $service];
    sp_archive_setup($config, $card, ['sort_arg' => 'archive-sort', 'empty_template' => $empty]);
    $ctx = _sp_archive_ctx();
    $tokens[] = $ctx['archive_token'];
    $check($post_ids($ctx['query']) === [$b] && $ctx['total_pages'] === 2, 'Initial setup applies relationships');
    $stored = sp_archive_get_config($ctx['archive_token']);
    $check($stored['filters'][0]['reverse_field'] === 'linked_sp_arc_item', 'Server token retains trusted definition');
    ob_start();
    sp_archive_config();
    $json = ob_get_clean();
    $check(str_contains($json, '"type":"relationship"') && str_contains($json, '"current_filters"'), 'Browser config contains filter type and current values');
    $rendered = [];
    $capture = static function ($slug, $name, $args) use (&$rendered): void { $rendered[] = $args; };
    $select_template = sp_archive_component_template('select');
    add_action('get_template_part_' . $select_template, $capture, 10, 3);
    ob_start();
    sp_archive_filters();
    ob_end_clean();
    remove_action('get_template_part_' . $select_template, $capture, 10);
    $check(count($rendered) === 2 && isset($rendered[0]['options'][$market]) && $rendered[0]['value'] === (string) $market, 'Relationship controls render published options and selection');

    $ajax = true;
    $request = static function (array $extra) use (&$ctx): array {
        $_POST = array_merge([
            'nonce' => wp_create_nonce(apply_filters('sp_archive_nonce_action', 'ajax_global')),
            'archive_token' => $ctx['archive_token'],
            'per_page' => 1,
            'sort' => 'az',
        ], $extra);
        ob_start();
        try { sp_archive_ajax_query(); }
        catch (RuntimeException $e) { if ($e->getMessage() !== 'archive-test-json') { ob_end_clean(); throw $e; } }
        $response = json_decode(ob_get_clean(), true);
        if (! is_array($response)) { throw new RuntimeException('Invalid JSON response'); }
        return $response;
    };
    $response = $request(['industry' => (string) $market, 'service' => (string) $service, 'paged' => 2]);
    $check($response['success'] && $response['data']['found'] === 2 && $response['data']['max_pages'] === 2 && str_contains($response['data']['html'], 'data-test-id="' . $c . '"'), 'AJAX returns second page of intersection');
    $response = $request(['service' => (string) $empty_service]);
    $check($response['success'] && $response['data']['found'] === 0 && $response['data']['html'] === '<p>No matches</p>', 'AJAX empty state');
    $response = $request(['service' => (string) $service, 'sort' => 'za']);
    $check($response['success'] && str_contains($response['data']['html'], 'data-test-id="' . $d . '"'), 'AJAX applies requested sorting after filtering');
    $response = $request(['service' => (string) $service, 'post_type' => 'sp_arc_market', 'template' => 'evil', 'filters' => '[{"reverse_field":"evil"}]']);
    $check($response['success'] && $response['data']['found'] === 3 && str_contains($response['data']['html'], 'data-test-id="' . $b . '"'), 'Client cannot replace trusted configuration');
    $response = $request(['nonce' => 'invalid']);
    $check(! $response['success'] && $response['data']['code'] === 'invalid_nonce', 'Invalid nonce rejected');
    $response = $request(['archive_token' => 'expired']);
    $check(! $response['success'] && $response['data']['code'] === 'config_expired', 'Expired token rejected');

    $ajax = false;
    $_GET = ['service' => (string) $service, 'page' => '2'];
    sp_archive_setup(array_merge($config, ['pagination_type' => 'load_more']), $card);
    $ctx = _sp_archive_ctx();
    $tokens[] = $ctx['archive_token'];
    $check($post_ids($ctx['query']) === [$b, $c] && $ctx['total_pages'] === 3, 'Reloaded load-more URL renders cumulative pages');
    $ajax = true;
    $response = $request(['service' => (string) $service, 'paged' => 3]);
    $check($response['success'] && substr_count($response['data']['html'], '<article') === 1 && str_contains($response['data']['html'], 'data-test-id="' . $d . '"') && ! $response['data']['has_next'], 'Load-more AJAX returns only next batch');
    $ajax = false;
    $_GET = ['service' => (string) $service];
    sp_archive_setup(array_merge($config, ['source_mode' => 'manual', 'manual_posts' => ['ids' => [$c, $a, $b]], 'per_page' => 10]), $card, ['sort_arg' => 'archive-sort']);
    $ctx = _sp_archive_ctx();
    $tokens[] = $ctx['archive_token'];
    $check($post_ids($ctx['query']) === [$c, $b] && $ctx['sort_arg'] === '', 'Manual setup preserves order and disables sorting');
    sp_archive_setup(array_merge($config, ['filters_enabled' => 0]), $card);
    $ctx = _sp_archive_ctx();
    $tokens[] = $ctx['archive_token'];
    $check($ctx['archive_filters'] === [] && $ctx['total_found'] === 4, 'Disabled relationship filters do not restrict query');
    sp_archive_setup(array_merge($config, ['relationship_filters' => ['paged' => $definitions['service']]]), $card);
    $ctx = _sp_archive_ctx();
    $tokens[] = $ctx['archive_token'];
    $check($ctx['archive_filters'] === [], 'Reserved payload name rejected');
    $field_type = acf_get_field_type('archive_builder');
    $formatted = $field_type->format_value([], 0, array_merge($field_type->defaults, $config));
    $check(count($formatted['relationship_filters']) === 2, 'ACF formatting preserves field-level relationship definitions');
} finally {
    foreach ($fixture_files as $file) { unlink($file); }
    $_GET = $old_get;
    $_POST = $old_post;
    foreach ($tokens as $token) { delete_transient('sp_arc_' . $token); }
    foreach ($ids as $id) { wp_delete_post($id, true); }
    foreach (get_terms(['taxonomy' => 'sp_arc_category', 'hide_empty' => false, 'fields' => 'ids']) as $term) {
        wp_delete_term($term, 'sp_arc_category');
    }
    unregister_taxonomy('sp_arc_category');
    foreach (['sp_arc_item', 'sp_arc_service', 'sp_arc_market'] as $type) { unregister_post_type($type); }
}
WP_CLI::success('Archive relationship filters: ' . $checks . ' integration checks passed.');
