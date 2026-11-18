<?php
if (!defined('ABSPATH')) { exit; }

/** Page-level configuration is opt-in; structural choices stay server-defined. */
function sp_archive_editor_post_types(array $field): array
{
    $choices = sp_archive_builder_post_type_choices();
    $allowed = array_filter(array_map('sanitize_key', (array) ($field['allowed_post_types'] ?? [])));
    return $allowed ? array_intersect_key($choices, array_flip($allowed)) : $choices;
}

function sp_archive_editor_sources(array $field, string $post_type): array
{
    $sources = [];
    if (!isset(sp_archive_editor_post_types($field)[$post_type])) { return []; }
    foreach (get_object_taxonomies($post_type, 'objects') as $name => $object) {
        if (in_array($name, ['post_format', 'nav_menu'], true) || (empty($object->show_ui) && empty($object->public))) { continue; }
        $sources['taxonomy:' . $name] = [
            'kind' => 'taxonomy', 'label' => sp_archive_builder_taxonomy_label($name, $object),
            'definition' => ['name' => $name, 'taxonomy' => $name],
        ];
    }
    $definitions = $field['editor_relationships'][$post_type] ?? [];
    foreach (sp_archive_normalize_relationship_filters($definitions) as $definition) {
        $sources['relationship:' . $definition['name']] = [
            'kind' => 'relationship',
            'label' => $definition['label'] ?: (get_post_type_object($definition['post_type'])->labels->name ?? $definition['name']),
            'definition' => $definition,
        ];
    }
    return $sources;
}

/** Only source IDs, display labels and UI choices are accepted from the editor. */
function sp_archive_editor_config($value, array $field): array
{
    $value = is_array($value) ? $value : [];
    $choices = sp_archive_editor_post_types($field);
    $default_type = (string) ($field['post_type'] ?? 'post');
    $post_type = is_string($value['post_type'] ?? null) ? $value['post_type'] : $default_type;
    if (!isset($choices[$post_type])) { $post_type = isset($choices[$default_type]) ? $default_type : (string) array_key_first($choices); }
    $configured = !empty($value['editor_configured']);
    $config = sp_archive_builder_normalize(array_merge(array_intersect_key($field, sp_archive_builder_defaults()), $value, ['post_type' => $post_type]));
    $sources = sp_archive_editor_sources($field, $post_type);
    $rows = [];
    if ($configured) {
        $rows = is_array($value['editor_filters'] ?? null) ? $value['editor_filters'] : [];
    } else {
        foreach ($config['filters'] as $filter) {
            $rows[] = ['source' => 'taxonomy:' . $filter['taxonomy']] + $filter;
        }
        foreach ($config['relationship_filters'] as $filter) {
            $rows[] = ['source' => 'relationship:' . $filter['name']] + $filter;
        }
    }
    $config['filters'] = [];
    $config['relationship_filters'] = [];
    $config['filter_order'] = [];
    $config['editor_filters'] = [];
    $seen = [];
    foreach ($rows as $row) {
        if (!is_array($row) || !is_string($row['source'] ?? null)) { continue; }
        $key = $row['source'];
        if (!isset($sources[$key]) || isset($seen[$key])) { continue; }
        $seen[$key] = true;
        $source = $sources[$key];
        $ui = is_string($row['ui'] ?? null) && in_array($row['ui'], ['buttons', 'checkbox', 'select', 'multiselect', 'radio'], true) ? $row['ui'] : 'checkbox';
        $label = is_scalar($row['label'] ?? null) ? sanitize_text_field((string) $row['label']) : '';
        $mode = in_array($row['terms_mode'] ?? '', ['selected', 'children', 'parent'], true) ? $row['terms_mode'] : 'children';
        $config['editor_filters'][] = ['source' => $key, 'label' => $label, 'ui' => $ui, 'terms_mode' => $mode];
        $definition = array_merge($source['definition'], ['ui' => $ui, 'label' => $label ?: $source['label']]);
        if ($source['kind'] === 'taxonomy') {
            $definition['terms_mode'] = $mode;
            $config['filters'][] = $definition;
        } else {
            $config['relationship_filters'][] = $definition;
        }
        $config['filter_order'][] = $definition['name'];
    }
    if (!$choices) { $config['source_mode'] = 'manual'; }
    $config['filters_enabled'] = $config['editor_filters'] ? 1 : 0;
    $config['editor_configured'] = 1;
    $config['search_enabled'] = $configured ? (!empty($value['search_enabled']) ? 1 : 0) : (int) ($field['search_enabled'] ?? 1);
    $manual = $value['manual_by_type'][$post_type] ?? $value['manual_posts'] ?? [];
    $manual = is_array($manual) ? $manual : [];
    $ids = is_array($manual['ids'] ?? null) ? $manual['ids'] : [];
    $ids = array_values(array_unique(array_filter(array_map('absint', $ids), static fn(int $id): bool => get_post_type($id) === $post_type)));
    $config['manual_posts'] = ['mode' => 'manual', 'ids' => $ids];
    unset($config['manual_by_type'], $config['editor_relationships'], $config['allowed_post_types']);
    return $config;
}

function sp_archive_editor_stored_value(array $config): array
{
    return array_intersect_key($config, array_flip([
        'editor_configured', 'post_type', 'editor_filters', 'term_scope', 'search_enabled',
        'source_mode', 'manual_posts', 'per_page', 'pagination_type', 'order_mode',
    ]));
}

function sp_archive_editor_render(array $field, array $value, string $position): void
{
    $name = $field['name'];
    $choices = sp_archive_editor_post_types($field);
    $post_type = $value['post_type'];
    if ($position === 'before') {
        $strings = ['Source', 'Label', 'Display', 'Terms', 'Buttons', 'Checkboxes', 'Select', 'Multiple select', 'Radio buttons', 'Include child terms', 'Top-level terms', 'Selected terms only', 'Move filter up', 'Move filter down', 'Remove filter', 'Filter moved up.', 'Filter moved down.', 'Filter removed.', 'Filter order updated.', 'No terms yet. Add terms to this post type first.', 'Unavailable term:'];
        $i18n = array_combine($strings, array_map(static fn(string $text): string => __($text, 'acf'), $strings));
        $sources = [];
        $taxonomies = [];
        foreach ($choices as $type => $label) {
            $sources[$type] = sp_archive_editor_sources($field, $type);
            foreach ($sources[$type] as $source) {
                if ($source['kind'] !== 'taxonomy') { continue; }
                $tax = $source['definition']['taxonomy'];
                $taxonomies[$type][$tax] = ['label' => $source['label'], 'terms' => sp_archive_term_choices($tax, ['hide_empty' => false])];
            }
        }
        ?>
        <div class="sp-archive-editor" data-sp-archive-editor
             data-name="<?= esc_attr($name) ?>" data-i18n="<?= esc_attr(wp_json_encode($i18n)) ?>" data-sources="<?= esc_attr(wp_json_encode($sources)) ?>"
             data-taxonomies="<?= esc_attr(wp_json_encode($taxonomies)) ?>"
             data-rows="<?= esc_attr(wp_json_encode($value['editor_filters'])) ?>"
             data-scope="<?= esc_attr(wp_json_encode($value['term_scope'])) ?>">
            <input type="hidden" name="<?= esc_attr($name) ?>[editor_configured]" value="1">
            <label class="sp-archive-editor__type"><?= esc_html__('Post type', 'acf') ?>
                <select name="<?= esc_attr($name) ?>[post_type]" data-archive-editor-type>
                    <?php foreach ($choices as $type => $label) : ?><option value="<?= esc_attr($type) ?>" <?= selected($post_type, $type, false) ?>><?= esc_html($label) ?></option><?php endforeach; ?>
                </select>
            </label>
        </div>
        <?php
        return;
    }
    ?>
    <div class="sp-archive-editor__settings" data-archive-editor-settings>
        <div class="sp-archive-editor__block">
            <h4><?= esc_html__('Content restrictions', 'acf') ?></h4>
            <p class="description"><?= esc_html__('Only posts matching these terms appear in this section. Visitors cannot remove these restrictions. Leave terms unchecked to include all.', 'acf') ?></p>
            <div data-archive-editor-scope></div>
        </div>
        <div class="sp-archive-editor__block">
            <h4><?= esc_html__('Visitor filters', 'acf') ?></h4>
            <p class="description"><?= esc_html__('Add the filters visitors can use. Drag rows or use the arrow buttons to set their order.', 'acf') ?></p>
            <input type="hidden" name="<?= esc_attr($name) ?>[editor_filters]" value="">
            <div data-archive-editor-rows></div>
            <p data-archive-editor-empty><?= esc_html__('No visitor filters. Add a filter to let visitors narrow the results.', 'acf') ?></p>
            <button type="button" class="button" data-archive-editor-add><?= esc_html__('Add filter', 'acf') ?></button>
            <span class="screen-reader-text" aria-live="polite" data-archive-editor-status></span>
        </div>
        <div class="sp-archive-editor__block sp-archive-editor__options">
            <input type="hidden" name="<?= esc_attr($name) ?>[search_enabled]" value="0">
            <label><input type="checkbox" name="<?= esc_attr($name) ?>[search_enabled]" value="1" <?= checked(!empty($value['search_enabled']), true, false) ?>> <?= esc_html__('Show search', 'acf') ?></label>
        </div>
    </div>
    <?php
}
