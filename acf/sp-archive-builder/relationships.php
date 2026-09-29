<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Relationship filters are server-defined. Each option is a published source
 * post whose reverse_field contains an array of IDs belonging to the archive.
 */
function sp_archive_normalize_relationship_filters($filters): array
{
    $output = [];
    foreach (sp_archive_decode_json_array($filters) as $key => $filter) {
        if (! is_array($filter) || (isset($filter['enabled']) && ! $filter['enabled'])) {
            continue;
        }
        $name = sanitize_key((string) ($filter['name'] ?? (is_string($key) ? $key : '')));
        $post_type = sanitize_key((string) ($filter['post_type'] ?? ''));
        $reverse_field = sanitize_key((string) ($filter['reverse_field'] ?? ''));
        if ($name === '' || $reverse_field === '' || ! post_type_exists($post_type)) {
            continue;
        }
        $ui = sanitize_key((string) ($filter['ui'] ?? 'select'));
        $output[$name] = [
            'type'          => 'relationship',
            'name'          => $name,
            'query_arg'     => sanitize_key((string) ($filter['query_arg'] ?? $name)) ?: $name,
            'post_type'     => $post_type,
            'reverse_field' => $reverse_field,
            'ui'            => in_array($ui, ['select', 'multiselect', 'buttons', 'radio', 'checkbox'], true) ? $ui : 'select',
            'label'         => sanitize_text_field((string) ($filter['label'] ?? '')),
            'all_label'     => sanitize_text_field((string) ($filter['all_label'] ?? '')),
            'lang'          => sanitize_key((string) ($filter['lang'] ?? '')),
        ];
    }
    return array_values($output);
}

/** Published source posts only; shared by rendering, validation and availability. */
function sp_archive_relationship_options(array $filter): array
{
    static $cache = [];
    $post_type = $filter['post_type'];
    $language = $filter['lang'] ?? '';
    $key = $post_type . ':' . $language;
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    $args = [
        'post_type'        => $post_type,
        'post_status'      => 'publish',
        'posts_per_page'   => -1,
        'orderby'         => ['title' => 'ASC', 'ID' => 'ASC'],
        'suppress_filters' => false,
    ];
    if ($language !== '') {
        $args['lang'] = $language;
    }
    $options = [];
    foreach (get_posts($args) as $post) {
        $options[(string) $post->ID] = $post->post_title;
    }
    return $cache[$key] = $options;
}

/** Keep malformed non-empty input restrictive instead of turning it into All. */
function sp_archive_relationship_values($value): array
{
    $values = is_array($value) ? $value : (is_string($value) ? explode('|', $value) : [$value]);
    $output = [];
    foreach ($values as $item) {
        if ($item === null || $item === '' || $item === 'all') { continue; }
        $item = is_string($item) || is_int($item) ? trim((string) $item) : '__invalid__';
        if ($item === '' || $item === 'all') { continue; }
        $output[] = preg_match('/^[1-9][0-9]*$/', $item) ? $item : '__invalid__';
    }
    return array_values(array_unique($output));
}

/** OR within a filter, AND between filters and an optional manual selection. */
function sp_archive_relationship_post_ids(array $filters, array $values, ?array $post_ids = null): ?array
{
    foreach ($filters as $filter) {
        if (($filter['type'] ?? '') !== 'relationship') {
            continue;
        }
        $selected = sp_archive_relationship_values($values[$filter['name']] ?? '');
        if (! $selected) {
            continue;
        }
        $options = sp_archive_relationship_options($filter);
        $matched = [];
        foreach ($selected as $id) {
            // Do not coerce a malformed value into a different, valid post ID.
            if (! preg_match('/^[1-9][0-9]*$/', $id) || ! array_key_exists($id, $options)) {
                continue;
            }
            $related = get_post_meta((int) $id, $filter['reverse_field'], true);
            if (is_array($related)) {
                $matched = array_merge($matched, array_filter(array_map('absint', $related)));
            }
        }
        $matched = array_values(array_unique($matched));
        $post_ids = $post_ids === null ? $matched : array_values(array_intersect($post_ids, $matched));
    }
    return $post_ids;
}
