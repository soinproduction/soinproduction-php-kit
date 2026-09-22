<?php
if (!defined('ABSPATH')) {
    exit;
}

/** Taxonomy fields are editing views of the original bidirectional relationship. */
function _pr_taxonomy_fields(array $base, string $source_type, string $taxonomy, array|bool $show_taxonomies, bool $show_uncategorized = true): array
{
    $hide_taxonomies = $show_taxonomies === false;
    $target_type = $base['post_type'][0];
    $show_taxonomies = array_values(array_filter((array) $show_taxonomies, static fn($name) => is_string($name) && is_object_in_taxonomy($target_type, $name)));
    if ($taxonomy && is_object_in_taxonomy($target_type, $taxonomy)) {
        if (!$hide_taxonomies) {
            $show_taxonomies[] = $taxonomy;
        }
    } else {
        $taxonomy = '';
    }
    $show_taxonomies = array_unique($show_taxonomies);
    $fields = [$base];
    $scopes = [];
    if ($taxonomy) {
        _pr_register_taxonomy_toggle($taxonomy);
        $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false, 'orderby' => 'name']);
        if (!is_wp_error($terms)) {
            foreach ($terms as $term) {
                if (!_pr_taxonomy_relationship_enabled((int) $term->term_id)) {
                    continue;
                }
                $scopes[$base['key'] . '_term_' . $term->term_id] = (int) $term->term_id;
                $field = $base;
                $field['key'] .= '_term_' . $term->term_id;
                $field['name'] .= '_term_' . $term->term_id;
                $field['label'] = $term->name;
                $fields[] = $field;
            }
            if ($show_uncategorized) {
                $field = $base;
                $field['key'] .= '_uncategorized';
                $field['name'] .= '_uncategorized';
                $field['label'] = __('Without category', 'ACF Fields');
                $fields[] = $field;
                $scopes[$field['key']] = 0;
            }
            // Keep the canonical field registered for get_field()/update_field(), but hide its UI.
            add_filter('acf/prepare_field/key=' . $base['key'], '__return_false');
        }
    }

    foreach ($fields as $field) {
        if ($show_taxonomies) {
            add_filter('acf/fields/relationship/result/key=' . $field['key'], static function ($title, $post) use ($show_taxonomies) {
                $names = wp_get_object_terms($post->ID, $show_taxonomies, ['fields' => 'names']);
                return !is_wp_error($names) && $names ? $title . ' <small>— ' . esc_html(implode(', ', $names)) . '</small>' : $title;
            }, 10, 2);
        }
        if (!array_key_exists($field['key'], $scopes)) {
            continue;
        }
        $term_id = $scopes[$field['key']];
        add_filter('acf/fields/relationship/query/key=' . $field['key'], static function ($query) use ($taxonomy, $term_id) {
            $scope = $term_id ? ['taxonomy' => $taxonomy, 'terms' => [$term_id], 'include_children' => false] : ['taxonomy' => $taxonomy, 'operator' => 'NOT EXISTS'];
            $query['tax_query'] = empty($query['tax_query']) ? [$scope] : ['relation' => 'AND', $query['tax_query'], $scope];
            return $query;
        });
        add_filter('acf/load_value/key=' . $field['key'], static function ($value, $post_id) use ($base, $taxonomy, $term_id) {
            return _pr_taxonomy_scope_ids(_pr_normalize_ids(get_post_meta($post_id, $base['name'], true)), $taxonomy, $term_id);
        }, 10, 2);
    }
    if ($scopes) {
        // Merge all submitted categories together, so posts with multiple terms are not
        // removed by an empty sibling field later in the same save operation.
        add_action('acf/save_post', static function ($post_id) use ($base, $source_type, $taxonomy, $scopes) {
            $submitted = array_intersect_key((array) ($_POST['acf'] ?? []), $scopes);
            if ($submitted) {
                _pr_save_taxonomy_fields($post_id, $base, $source_type, $taxonomy, $scopes, $submitted);
            }
        }, 5);
        add_filter('acf/pre_update_value', static function ($check, $value, $post_id, $field) use ($base, $source_type, $taxonomy, $scopes) {
            if ($check !== null || !array_key_exists($field['key'], $scopes)) {
                return $check;
            }
            if (!doing_action('acf/save_post')) {
                _pr_save_taxonomy_fields($post_id, $base, $source_type, $taxonomy, $scopes, [$field['key'] => $value]);
            }
            // Virtual fields never store duplicate relationship metadata.
            return true;
        }, 10, 4);
    }
    return $fields;
}

/** Existing categories stay enabled until explicitly switched off. */
function _pr_taxonomy_relationship_enabled(int $term_id): bool
{
    $enabled = get_term_meta($term_id, 'pr_relationship_enabled', true);
    return $enabled === '' || (bool) $enabled;
}

function _pr_register_taxonomy_toggle(string $taxonomy): void
{
    static $registered = [];
    if (isset($registered[$taxonomy])) {
        return;
    }
    $registered[$taxonomy] = true;
    acf_add_local_field_group([
        'key' => 'group_pr_taxonomy_toggle_' . $taxonomy,
        'title' => __('Relationships', 'ACF Fields'),
        'fields' => [[
            'key' => 'field_pr_taxonomy_toggle_' . $taxonomy,
            'name' => 'pr_relationship_enabled',
            'label' => __('Show Relationship field', 'ACF Fields'),
            'instructions' => __('Show a separate selection field for this category on related posts. Turning this off preserves existing relationships.', 'ACF Fields'),
            'type' => 'true_false',
            'ui' => 1,
            'default_value' => 1,
        ]],
        'location' => [[['param' => 'taxonomy', 'operator' => '==', 'value' => $taxonomy]]],
        'active' => true,
    ]);
}

function _pr_taxonomy_scope_ids(array $ids, string $taxonomy, int $term_id): array
{
    return array_values(array_filter($ids, static function ($id) use ($taxonomy, $term_id) {
        $terms = wp_get_object_terms($id, $taxonomy, ['fields' => 'ids']);
        return !is_wp_error($terms) && ($term_id ? in_array($term_id, $terms, true) : !$terms);
    }));
}

function _pr_save_taxonomy_fields($post_id, array $base, string $source_type, string $taxonomy, array $scopes, array $submitted): void
{
    if (get_post_type($post_id) !== $source_type) {
        return;
    }
    $existing = _pr_normalize_ids(get_post_meta($post_id, $base['name'], true));
    $remove = [];
    $add = [];
    foreach ($submitted as $key => $value) {
        $remove = array_merge($remove, _pr_taxonomy_scope_ids($existing, $taxonomy, $scopes[$key]));
        $add = array_merge($add, _pr_taxonomy_scope_ids(_pr_normalize_ids($value), $taxonomy, $scopes[$key]));
    }
    update_field($base['key'], _pr_normalize_ids(array_merge(array_diff($existing, $remove), $add)), $post_id);
}
