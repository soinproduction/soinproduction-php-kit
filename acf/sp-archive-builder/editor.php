<?php
if (!defined('ABSPATH')) { exit; }

/** Page-level configuration is opt-in; structural choices stay server-defined. */
function sp_archive_editor_post_types(array $field): array
{
    $choices = sp_archive_builder_post_type_choices();
    $allowed = array_filter(array_map('sanitize_key', (array) ($field['allowed_post_types'] ?? [])));
    return $allowed ? array_intersect_key($choices, array_flip($allowed)) : $choices;
}

/** Pair metadata comes from registered ACF fields, never from generated key names. */
function sp_archive_editor_relationships(array $field, string $post_type): array
{
    $definitions = $field['editor_relationships'][$post_type] ?? [];
    $definitions = sp_archive_normalize_relationship_filters($definitions);
    $seen = [];
    foreach ($definitions as $definition) { $seen[$definition['post_type'] . '|' . $definition['reverse_field']] = true; }
    if (function_exists('acf_get_field_groups') && function_exists('acf_get_fields')) {
        foreach (acf_get_field_groups(['post_type' => $post_type]) as $group) {
            foreach ((array) acf_get_fields($group) as $registered) {
                $pair = $registered['sp_archive_relationship'] ?? null;
                if (!is_array($pair) || empty($pair['field']) || empty($pair['post_type']) || empty($pair['reverse_field'])) { continue; }
                $identity = $pair['post_type'] . '|' . $pair['reverse_field'];
                if (isset($seen[$identity])) { continue; }
                $object = get_post_type_object($pair['post_type']);
                if (!$object) { continue; }
                $candidate = sp_archive_normalize_relationship_filters([
                    $pair['field'] => ['post_type' => $pair['post_type'], 'reverse_field' => $pair['reverse_field'], 'label' => $object->labels->name, 'ui' => 'checkbox'],
                ]);
                if (!$candidate) { continue; }
                $seen[$identity] = true;
                $definitions[] = $candidate[0];
            }
        }
    }
    return $definitions;
}

function sp_archive_editor_sources(array $field, string $post_type, bool $available_only = true): array
{
    $sources = [];
    if (!isset(sp_archive_editor_post_types($field)[$post_type])) { return []; }
    foreach (get_object_taxonomies($post_type, 'objects') as $name => $object) {
        if (in_array($name, ['post_format', 'nav_menu'], true) || (empty($object->show_ui) && empty($object->public))) { continue; }
        $sources['taxonomy:' . $name] = [
            'kind' => 'taxonomy', 'label' => sp_archive_builder_taxonomy_label($name, $object),
            'hierarchical' => !empty($object->hierarchical),
            'definition' => ['name' => $name, 'taxonomy' => $name],
        ];
    }
    foreach (sp_archive_editor_relationships($field, $post_type) as $definition) {
        $sources['relationship:' . $definition['name']] = [
            'kind' => 'relationship', 'hierarchical' => false,
            'label' => $definition['label'] ?: (get_post_type_object($definition['post_type'])->labels->name ?? $definition['name']),
            'definition' => $definition,
        ];
    }
    if ($available_only && !empty($field['editor_sources_configured'])) {
        $allowed = (array) ($field['editor_filter_sources'][$post_type] ?? []);
        $sources = array_intersect_key($sources, array_flip(array_filter($allowed, 'is_string')));
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
    if ($configured && !array_key_exists('term_scope', $value)) { $value['term_scope'] = []; }
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
        $enabled = !isset($row['enabled']) || !empty($row['enabled']);
        $config['editor_filters'][] = ['source' => $key, 'enabled' => $enabled ? 1 : 0, 'label' => $label, 'ui' => $ui, 'terms_mode' => $mode];
        if (!$enabled) { continue; }
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
    $config['filters_enabled'] = $config['filter_order'] ? 1 : 0;
    $config['editor_configured'] = 1;
    $config['search_enabled'] = $configured ? (!empty($value['search_enabled']) ? 1 : 0) : (int) ($field['search_enabled'] ?? 1);
    $manual = $value['manual_by_type'][$post_type] ?? $value['manual_posts'] ?? [];
    $manual = is_array($manual) ? $manual : [];
    $ids = is_array($manual['ids'] ?? null) ? $manual['ids'] : [];
    $ids = array_values(array_unique(array_filter(array_map('absint', $ids), static fn(int $id): bool => get_post_type($id) === $post_type)));
    $config['manual_posts'] = ['mode' => 'manual', 'ids' => $ids];
    unset($config['manual_by_type'], $config['editor_relationships'], $config['allowed_post_types'], $config['editor_filter_sources'], $config['editor_sources_configured']);
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
        $strings = ['Edit filter settings', 'Edit', 'Source:', 'Name on website', 'Category options', 'Filter settings', 'Include subcategories', 'Top-level categories', 'Only selected categories', 'Source', 'Label', 'Display', 'Terms', 'Buttons', 'Checkboxes', 'Select', 'Multiple select', 'Radio buttons', 'Include child terms', 'Top-level terms', 'Selected terms only', 'Move filter up', 'Move filter down', 'Remove filter', 'Filter moved up.', 'Filter moved down.', 'Filter removed.', 'Filter order updated.', 'No terms yet. Add terms to this post type first.', 'Unavailable term:'];
        $i18n = array_combine($strings, array_map(static fn(string $text): string => __($text, 'acf'), $strings));
        $sources = [];
        $taxonomies = [];
        foreach ($choices as $type => $label) {
            $sources[$type] = sp_archive_editor_sources($field, $type);
            foreach (sp_archive_editor_sources($field, $type, false) as $source) {
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
            <label class="sp-archive-editor__type"><?= esc_html__('Content type', 'acf') ?>
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
        <div class="sp-archive-editor__layout">
        <div class="sp-archive-editor__block">
            <h4><?= esc_html__('Limit the list', 'acf') ?></h4>
            <p class="description"><?= esc_html__('Choose categories or tags to limit which posts appear. Leave everything unchecked to show all. Visitors cannot change this selection.', 'acf') ?></p>
            <div data-archive-editor-scope></div>
        </div>
        <div class="sp-archive-editor__block">
            <h4><?= esc_html__('Filters on website', 'acf') ?></h4>
            <p class="description"><?= esc_html__('Switch on the filters visitors can use. Drag enabled rows or use the arrows to change their order.', 'acf') ?></p>
            <input type="hidden" name="<?= esc_attr($name) ?>[editor_filters]" value="">
            <div data-archive-editor-rows></div>
            <div data-archive-editor-values></div>
            <p data-archive-editor-empty><?= esc_html__('No filters are available for this content type. Enable sources in the Archive Builder field settings.', 'acf') ?></p>
            <span class="screen-reader-text" aria-live="polite" data-archive-editor-status></span>
        </div>
        </div>
        <div class="sp-archive-editor__block sp-archive-editor__options">
            <input type="hidden" name="<?= esc_attr($name) ?>[search_enabled]" value="0">
            <label><input type="checkbox" name="<?= esc_attr($name) ?>[search_enabled]" value="1" <?= checked(!empty($value['search_enabled']), true, false) ?>> <?= esc_html__('Show search', 'acf') ?></label>
        </div>
    </div>
    <?php
}


/** Field Groups controls capabilities; page values control the displayed archive. */
function sp_archive_editor_render_field_catalog(array $field): void
{
    $prefix = !empty($field['prefix']) ? $field['prefix'] : "acf_fields[{$field['key']}]";
    $catalog_field = $field;
    $catalog_field['allowed_post_types'] = [];
    $configured = !empty($field['editor_sources_configured']);
    ?>
    <div class="acf-field acf-field-setting-editor_filter_sources" data-name="editor_filter_sources" data-setting="archive_builder">
        <div class="acf-label">
            <label><?= esc_html__('Available filters', 'acf') ?></label>
            <p class="description"><?= esc_html__('Choose which filters page editors may switch on. Labels, display style and order are set on each page.', 'acf') ?></p>
        </div>
        <div class="acf-input">
            <input type="hidden" name="<?= esc_attr($prefix) ?>[editor_sources_configured]" value="1">
            <input type="hidden" name="<?= esc_attr($prefix) ?>[editor_filter_sources]" value="">
            <div class="sp-archive-field-catalog" data-archive-field-catalog
                 data-initial="<?= esc_attr__('Defaults for new sections', 'acf') ?>"
                 data-fixed="<?= esc_attr__('Content selection and filters', 'acf') ?>"
                 data-display="<?= esc_attr__('Buttons and labels', 'acf') ?>"
                 data-advanced="<?= esc_attr__('Advanced: request parameters', 'acf') ?>">
                <?php foreach (sp_archive_editor_post_types($catalog_field) as $type => $label) : ?>
                    <?php $sources = sp_archive_editor_sources($catalog_field, $type, false); ?>
                    <details data-catalog-type="<?= esc_attr($type) ?>" <?= $type === ($field['post_type'] ?? '') ? 'open' : '' ?>>
                        <summary><?= esc_html($label) ?></summary>
                        <div class="sp-archive-field-catalog__choices">
                            <?php foreach ($sources as $id => $source) : ?>
                                <label><input type="checkbox" name="<?= esc_attr($prefix) ?>[editor_filter_sources][<?= esc_attr($type) ?>][]" value="<?= esc_attr($id) ?>" <?= checked(!$configured || in_array($id, (array) ($field['editor_filter_sources'][$type] ?? []), true), true, false) ?>><?= esc_html($source['label']) ?></label>
                            <?php endforeach; ?>
                            <?php if (!$sources) : ?><p class="description"><?= esc_html__('No categories, tags or registered relationships for this content type.', 'acf') ?></p><?php endif; ?>
                        </div>
                    </details>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php
}
