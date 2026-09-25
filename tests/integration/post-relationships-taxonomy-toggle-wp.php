<?php
/** Run with wp eval-file in a site using news_insights_category splitting. */
if (!defined('WP_CLI') || !WP_CLI) {
    exit(1);
}
$taxonomy = 'news_insights_category';
$key = 'field_pr_taxonomy_toggle_' . $taxonomy;
$check = static function ($value, $message) {
    if (!$value) {
        throw new RuntimeException($message);
    }
};
$check(acf_get_field($key), 'Category toggle is not registered');
$term = wp_insert_term('Relationship toggle test ' . wp_generate_uuid4(), $taxonomy);
$check(!is_wp_error($term), 'Could not create temporary category');
$id = (int) $term['term_id'];
try {
    $base = acf_get_field('field_rel_group_' . substr(md5('leadership|news_insights|linked_'), 0, 12) . '_leadership_news_insights');
    $check($base, 'Missing fixture relationship');
    $base['key'] .= '_toggle_test';
    $has_field = static function () use ($base, $taxonomy, $id) {
        $fields = _pr_taxonomy_fields($base, 'leadership', $taxonomy, false);
        return in_array($base['key'] . '_term_' . $id, array_column($fields, 'key'), true);
    };
    $check(_pr_taxonomy_relationship_enabled($id) && $has_field(), 'New category should be enabled by default');
    update_field($key, 0, 'term_' . $id);
    $check(!_pr_taxonomy_relationship_enabled($id) && !$has_field(), 'Disabled category still creates a field');
    update_field($key, 1, 'term_' . $id);
    $check(_pr_taxonomy_relationship_enabled($id) && $has_field(), 'Re-enabled category field missing');
    WP_CLI::success('Category toggle: default, saved off, and saved on states passed.');
} finally {
    wp_delete_term($id, $taxonomy);
}
