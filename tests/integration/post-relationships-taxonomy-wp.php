<?php
/** Run with wp eval-file in a fixture site configured for leadership/news_insights splitting. */
if (!defined('WP_CLI') || !WP_CLI) {
    exit(1);
}
$check = static function ($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$base_key = 'field_rel_group_' . substr(md5('leadership|news_insights|linked_'), 0, 12) . '_leadership_news_insights';
$base = acf_get_field($base_key);
$terms = array_slice(array_values(array_filter(get_terms(['taxonomy'=>'news_insights_category', 'hide_empty'=>false]), static fn($term) => _pr_taxonomy_relationship_enabled((int) $term->term_id))), 0, 2);
$check($base && count($terms) === 2, 'Missing fixture configuration');
$a = (int) $terms[0]->term_id;
$b = (int) $terms[1]->term_id;
$ak = $base_key . '_term_' . $a;
$bk = $base_key . '_term_' . $b;
$uk = $base_key . '_uncategorized';
$check(acf_get_field($ak) && acf_get_field($bk), 'Split fields missing');
$has_uncategorized = (bool) acf_get_field($uk);
$ids = [];
$old_post = $_POST;
try {
    foreach (['leadership', 'news_insights', 'news_insights', 'news_insights', 'news_insights'] as $type) {
        $id = wp_insert_post(['post_type'=>$type, 'post_status'=>'draft', 'post_title'=>'Relationship integration test'], true);
        $check(!is_wp_error($id), 'Fixture creation failed');
        $ids[] = $id;
    }
    [$leader, $first, $second, $both, $none] = $ids;
    wp_set_object_terms($first, [$a], 'news_insights_category');
    wp_set_object_terms($second, [$b], 'news_insights_category');
    wp_set_object_terms($both, [$a, $b], 'news_insights_category');
    update_field($base_key, [$first, $second, $both, $none], $leader);
    $read = static fn($key) => apply_filters('acf/load_value/key='.$key, null, $leader, acf_get_field($key));
    $check($read($ak) === [$first, $both], 'Existing links not projected');
    if ($has_uncategorized) {
        $check($read($uk) === [$none], 'Uncategorized projection failed');
    }
    $query = apply_filters('acf/fields/relationship/query/key='.$ak, ['post_type'=>'news_insights','post_status'=>'draft','post__in'=>[$first,$second,$both,$none],'fields'=>'ids']);
    $found = get_posts($query);
    $check(count($found)===2 && in_array($first,$found) && in_array($both,$found), 'Category query scope failed');
    $label = apply_filters('acf/fields/relationship/result/key='.$ak, 'Title', get_post($both), acf_get_field($ak), $leader);
    $shows_labels = has_filter('acf/fields/relationship/result/key='.$ak);
    $check($shows_labels ? (str_contains($label, esc_html($terms[0]->name)) && str_contains($label, esc_html($terms[1]->name))) : $label === 'Title', 'Term label visibility mismatch');
    // Multi-term item remains selected in one field; empty sibling must not remove it.
    $_POST['acf'] = [$ak=>[$both], $bk=>[]];
    if ($has_uncategorized) {
        $_POST['acf'][$uk] = [$none];
    }
    acf_save_post($leader);
    $saved = get_post_meta($leader, 'linked_news_insights', true);
    sort($saved);
    $check($saved === [$both, $none], 'Batch union or hidden uncategorized preservation failed');
    $check(in_array($leader, get_post_meta($both, 'linked_leadership', true), true), 'Reverse link missing');
    $check(!in_array($leader, (array) get_post_meta($first, 'linked_leadership', true), true), 'Reverse unlink failed');
    $check(!metadata_exists('post', $leader, $base['name'].'_term_'.$a), 'Virtual metadata leaked');
    // Updating one field preserves the other category and uncategorized links.
    update_field($ak, [$first], $leader);
    $check(get_post_meta($leader, 'linked_news_insights', true) === [$none, $first], 'Programmatic scoped update failed');
    wp_set_object_terms($first, [$b], 'news_insights_category');
    $check($read($ak) === [] && $read($bk) === [$first], 'Reclassified links not reflected');
    update_field($base_key, [], $leader);
    $check(!in_array($leader, (array) get_post_meta($none, 'linked_leadership', true), true), 'Canonical unlink failed');
    WP_CLI::success('Taxonomy relationships: projection, filtering, labels, batch union, reverse sync, scoped updates and reclassification passed.');
} finally {
    $_POST = $old_post;
    foreach (array_reverse($ids) as $id) {
        wp_delete_post($id, true);
    }
}
