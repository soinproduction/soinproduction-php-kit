<?php
// =============================================================================
// ARCHIVE BUILDER — ДОКУМЕНТАЦИЯ
// =============================================================================
//
// Файл регистрирует ACF-группу полей для настройки архива постов и держит
// весь runtime для вывода архива: query, taxonomy filters, sorting,
// posts-per-page, confirm/reset, pagination, empty state и AJAX endpoint.
//
// Подробная документация и примеры лежат рядом:
// core/acf/archive-builder/README.md
//
// =============================================================================
// 1. РЕГИСТРАЦИЯ ACF ПОЛЯ (fields.php)
// =============================================================================
//
//  ->addFields(archive_builder('archive', [
//
//      'label'           => 'Archive Settings',   // Заголовок группы в ACF
//
//      // Тип поста (фиксируется на уровне конфига, не меняется редактором)
//      'post_type'       => 'case_study',
//
//      // Включить блок фильтров
//      'filters_enabled' => 1,
//
//      // Confirm/reset controls:
//      // confirm => 1 — изменения фильтров/сортировки/per-page ждут кнопки
//      // reset   => 1 — можно отрендерить кнопку полного сброса
//      // disable_empty => 1 — выключать варианты фильтров, которые дадут 0 постов
//      'confirm'         => 1,
//      'reset'           => 1,
//      'disable_empty'   => 1,
//
//      // ── Taxonomy filters ───────────────────────────────────────────────
//      // Ключ — slug таксономии (должна существовать через taxonomy_exists()).
//      // 'enabled' => 1 — показывать фильтр на фронте
//      // 'ui'      — тип контрола:
//      //   'select'      — кастом-дропдаун, одиночный выбор
//      //   'multiselect' — кастом-дропдаун, множественный выбор (теги)
//      //   'radio'       — радио-кнопки, одиночный выбор
//      //   'checkbox'    — чекбоксы, множественный выбор
//      //   'buttons'     — кнопки-пилюли, одиночный выбор (по умолчанию)
//      'filters' => [
//          'case_study_industry' => ['enabled' => 1, 'ui' => 'checkbox'],
//          'case_study_service'  => ['enabled' => 1, 'ui' => 'radio'],
//          'case_study_location' => ['enabled' => 1, 'ui' => 'multiselect'],
//          'case_study_tag'      => ['enabled' => 1, 'ui' => 'buttons'],
//      ],
//
//      // Ограничить сам архив конкретными термами.
//      // Пусто = все термы выбранного post type.
//      'term_scope' => [
//          'case_study_industry' => ['manufacturing', 'healthcare'],
//      ],
//
//      // Постов на страницу
//      'per_page'        => 9,
//      'load_more_label' => 'Show More',
//      'all_label'       => 'All',
//
//      // Тип пагинации:
//      //   'pagination'     — numbered pages
//      //   'load_more'      — кнопка «Загрузить ещё»
//      //   'infinity_scroll' — автозагрузка при скролле
//      'pagination_type' => 'pagination',
//
//      // ── Дефолтная сортировка ──────────────────────────────────────────
//      // Применяется если у пользователя нет выбора в URL или sort-селекте.
//      //   'newest'     — по дате, сначала новые  (по умолчанию)
//      //   'oldest'     — по дате, сначала старые
//      //   'az'         — по заголовку A → Z
//      //   'za'         — по заголовку Z → A
//      //   'menu_order' — по Menu Order в WordPress
//      'order_mode'      => 'newest',
//
//      // URL/query args для состояния архива
//      'page_arg'        => 'page',
//      'url_page_arg'    => 'page',
//      'sort_arg'        => 'case_sort',
//      'per_page_arg'    => 'case_per_page',
//  ]))
//
//
// =============================================================================
// 2. ВЫВОД В ШАБЛОНЕ (index.php секции)
// =============================================================================
//
//  // ── Инициализация (обязательно первым) ────────────────────────────────
//  // $archive — значение ACF группы: get_field('archive') или $fields['archive']
//  // 2-й аргумент — путь к шаблону карточки
//  // 3-й аргумент — опции (все необязательные):
//  //   'action'           — AJAX action (по умолчанию 'sp_archive_query')
//  //   'sort_arg'         — URL-параметр сортировки (?case_sort=az)
//  //   'per_page_arg'     — URL-параметр количества постов (?case_per_page=12)
//  //   'page_arg'         — AJAX-параметр страницы (по умолчанию 'sp_page')
//  //   'url_page_arg'     — URL-параметр страницы  (по умолчанию 'page')
//  //   'empty_template'   — template_part для пустого результата
//
//  [php] sp_archive_setup($archive, 'php/cards/case-card', [
//      'sort_arg' => 'case_sort',   // необязательно
//  ]);
//
//
//  <!-- ── JSON конфиг для JS (обязательно в data-sp-archive обёртке) ──── -->
//  <section [?= sp_archive_attr(); ?]>
//
//      [?= sp_archive_config(); ?]       <!-- скрытый <script> с JSON конфигом -->
//
//      <!-- Фильтры (рендерит только включённые в ACF) -->
//      [php] sp_archive_filters(); [/php]
//
//      <!-- Сортировка (только если 'sort_arg' передан в setup) -->
//      [php] sp_archive_sort(); [/php]
//
//      <!-- Количество постов на страницу -->
//      [php] sp_archive_per_page([6 => '6', 9 => '9', 12 => '12']); [/php]
//
//      <!-- Confirm/reset, если включены в настройках поля -->
//      [php] sp_archive_confirm('Apply', 'main-button'); [/php]
//      [php] sp_archive_reset('Reset', 'main-button'); [/php]
//
//      <!-- Или сортировка с кастомными опциями: -->
//      [php] sp_archive_sort([
//          ['value' => 'newest', 'label' => 'Сначала новые'],
//          ['value' => 'oldest', 'label' => 'Сначала старые'],
//          ['value' => 'az',     'label' => 'По названию'],
//      ]); [/php]
//
//      <!-- Список карточек; wrapper с data-sp-archive-list создаёт helper -->
//      [php] sp_archive_cards(); [/php]
//
//      <!-- Пагинация / кнопка «ещё» / бесконечный скролл -->
//      [php] sp_archive_pagination(); [/php]
//
//  </section>
//
//
// =============================================================================
// 3. СТИЛИ (CSS custom properties)
// =============================================================================
//
//  Фильтры стилизуются через переменные в scss/general/_filter-controls.scss
//
//  Кнопки:    --filter-btn-bg, --filter-btn-bg-active, --filter-btn-color-active
//  Radio:     --filter-marker-size, --filter-marker-border-color, --filter-radio-dot-color
//  Checkbox:  --filter-cb-size, --filter-cb-bg-checked, --filter-cb-check-color
//  Общие:     --filter-font-size, --filter-gap, --filter-title-color
//
// =============================================================================

if (! defined('ABSPATH')) {
    exit;
}

// ---------------------------------------------------------------------------
// sp_archive — core archive helpers (moved from core/helpers/archive.php)
// ---------------------------------------------------------------------------

if (! function_exists('sp_archive_allowed_sort_values')) {
    function sp_archive_allowed_sort_values(): array {
        return ['newest', 'oldest', 'az', 'za', 'menu_order'];
    }
}

if (! function_exists('sp_archive_normalize_choice')) {
    function sp_archive_normalize_choice($value): string {
        $value = sanitize_title((string) $value);
        return $value === 'all' ? '' : $value;
    }
}

if (! function_exists('sp_archive_normalize_sort')) {
    function sp_archive_normalize_sort($value, string $fallback = 'newest'): string {
        $value    = sanitize_key((string) $value);
        $fallback = in_array($fallback, sp_archive_allowed_sort_values(), true) ? $fallback : 'newest';
        return in_array($value, sp_archive_allowed_sort_values(), true) ? $value : $fallback;
    }
}

if (! function_exists('sp_archive_normalize_mode')) {
    function sp_archive_normalize_mode($value): string {
        $value = sanitize_key((string) $value);
        return in_array($value, ['pagination', 'infinity_scroll', 'load_more'], true) ? $value : 'pagination';
    }
}

if (! function_exists('sp_archive_normalize_per_page')) {
    function sp_archive_normalize_per_page($value, int $fallback = 9): int {
        if (is_string($value) && strtolower(trim($value)) === 'all') {
            return -1;
        }

        $value = (int) $value;
        if ($value === -1 || $value > 0) {
            return $value;
        }

        return $fallback === -1 || $fallback > 0 ? $fallback : 9;
    }
}

if (! function_exists('sp_archive_current_language')) {
    function sp_archive_current_language(): string {
        if (function_exists('pll_current_language')) {
            $language = sanitize_key((string) pll_current_language('slug'));
            if ($language !== '') {
                return $language;
            }
        }

        return sanitize_key((string) apply_filters('wpml_current_language', ''));
    }
}

if (! function_exists('sp_archive_sort_args')) {
    function sp_archive_sort_args(string $sort): array {
        switch ($sort) {
            case 'oldest':     return ['orderby' => 'date',  'order' => 'ASC'];
            case 'az':         return ['orderby' => 'title', 'order' => 'ASC'];
            case 'za':         return ['orderby' => 'title', 'order' => 'DESC'];
            case 'menu_order': return ['orderby' => ['menu_order' => 'ASC', 'date' => 'DESC'], 'order' => 'DESC'];
            default:           return ['orderby' => 'date',  'order' => 'DESC'];
        }
    }
}

if (! function_exists('sp_archive_filter_options')) {
    function sp_archive_filter_options(string $taxonomy, string $all_label, array $include_slugs = [], string $terms_mode = 'children'): array {
        $options = ['all' => $all_label];
        if (! taxonomy_exists($taxonomy)) { return $options; }
        $include_slugs = sp_archive_filter_scope_slugs($taxonomy, $include_slugs, $terms_mode);
        $term_options = sp_archive_term_choices($taxonomy, [
            'hide_empty'    => true,
            'include_slugs' => $include_slugs,
            'parent_only'   => $terms_mode === 'parent' && empty($include_slugs),
        ]);

        foreach ($term_options as $slug => $label) {
            $options[$slug] = $label;
        }

        return $options;
    }
}

if (! function_exists('sp_archive_filter_scope_slugs')) {
    function sp_archive_filter_scope_slugs(string $taxonomy, array $include_slugs = [], string $mode = 'children'): array
    {
        if (! taxonomy_exists($taxonomy)) {
            return [];
        }

        $include_slugs = array_values(array_unique(array_filter(array_map('sanitize_title', $include_slugs))));

        if (empty($include_slugs)) {
            return [];
        }

        $taxonomy_object = get_taxonomy($taxonomy);

        if ($mode === 'parent') {
            if (! $taxonomy_object || empty($taxonomy_object->hierarchical)) {
                return $include_slugs;
            }

            $terms = get_terms([
                'taxonomy'   => $taxonomy,
                'hide_empty' => false,
                'slug'       => $include_slugs,
            ]);

            if (is_wp_error($terms) || empty($terms)) {
                return $include_slugs;
            }

            $output = [];

            foreach ($terms as $term) {
                if (! $term instanceof WP_Term) {
                    continue;
                }

                if ((int) $term->parent > 0) {
                    $parent = get_term((int) $term->parent, $taxonomy);
                    if ($parent instanceof WP_Term) {
                        $output[] = $parent->slug;
                    }
                    continue;
                }

                $output[] = $term->slug;
            }

            return array_values(array_unique(array_filter(array_map('sanitize_title', $output))));
        }

        if ($mode !== 'children') {
            return $include_slugs;
        }

        if (! $taxonomy_object || empty($taxonomy_object->hierarchical)) {
            return $include_slugs;
        }

        $terms = get_terms([
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
            'slug'       => $include_slugs,
        ]);

        if (is_wp_error($terms) || empty($terms)) {
            return $include_slugs;
        }

        $output = [];

        foreach ($terms as $term) {
            if (! $term instanceof WP_Term) {
                continue;
            }

            $children = get_terms([
                'taxonomy'   => $taxonomy,
                'hide_empty' => false,
                'child_of'   => (int) $term->term_id,
                'fields'     => 'id=>slug',
            ]);

            if (! is_wp_error($children) && ! empty($children)) {
                $output = array_merge($output, array_values($children));
                continue;
            }

            $output[] = $term->slug;
        }

        return array_values(array_unique(array_filter(array_map('sanitize_title', $output))));
    }
}

if (! function_exists('sp_archive_term_choices')) {
    function sp_archive_term_choices(string $taxonomy, array $args = []): array
    {
        if (! taxonomy_exists($taxonomy)) {
            return [];
        }

        $args = wp_parse_args($args, [
            'hide_empty'    => false,
            'include_slugs' => [],
            'parent_only'   => false,
        ]);

        $include_slugs = array_values(array_unique(array_filter(array_map('sanitize_title', (array) $args['include_slugs']))));
        $term_args = [
            'taxonomy'   => $taxonomy,
            'hide_empty' => ! empty($args['hide_empty']),
            'orderby'    => 'term_order',
            'order'      => 'ASC',
        ];

        if ($include_slugs) {
            $term_args['slug'] = $include_slugs;
        } elseif (! empty($args['parent_only'])) {
            $term_args['parent'] = 0;
        }

        $terms = get_terms($term_args);

        if (is_wp_error($terms) || empty($terms)) {
            return [];
        }

        // Sort terms in PHP by Content Manager's custom metadata order (_sp_cm_order)
        usort($terms, static function ($a, $b) {
            if (! ($a instanceof WP_Term) || ! ($b instanceof WP_Term)) {
                return 0;
            }
            $val_a = get_term_meta($a->term_id, '_sp_cm_order', true);
            $val_b = get_term_meta($b->term_id, '_sp_cm_order', true);

            $order_a = ($val_a === '' || ! is_numeric($val_a)) ? PHP_INT_MAX : (int) $val_a;
            $order_b = ($val_b === '' || ! is_numeric($val_b)) ? PHP_INT_MAX : (int) $val_b;

            if ($order_a === $order_b) {
                return $a->term_id <=> $b->term_id;
            }
            return $order_a <=> $order_b;
        });

        $by_parent = [];

        foreach ($terms as $term) {
            if (! $term instanceof WP_Term) {
                continue;
            }

            $by_parent[(int) $term->parent][] = $term;
        }

        $output = [];
        $render = static function (int $parent = 0, int $depth = 0) use (&$render, &$output, $by_parent): void {
            if (empty($by_parent[$parent])) {
                return;
            }

            foreach ($by_parent[$parent] as $term) {
                $prefix = $depth > 0 ? str_repeat('-- ', $depth) : '';
                $output[$term->slug] = $prefix . $term->name;
                $render((int) $term->term_id, $depth + 1);
            }
        };

        $render(0, 0);

        foreach ($terms as $term) {
            if ($term instanceof WP_Term && ! isset($output[$term->slug])) {
                $output[$term->slug] = $term->name;
            }
        }

        if (! empty($include_slugs)) {
            $sorted_output = [];
            foreach ($include_slugs as $slug) {
                if (isset($output[$slug])) {
                    $sorted_output[$slug] = $output[$slug];
                }
            }
            foreach ($output as $slug => $val) {
                if (! isset($sorted_output[$slug])) {
                    $sorted_output[$slug] = $val;
                }
            }
            $output = $sorted_output;
        }

        return $output;
    }
}

if (! function_exists('sp_archive_current_page')) {
    function sp_archive_current_page(string $internal_arg = 'sp_page', string $url_arg = 'page'): int {
        if (isset($_GET[$internal_arg])) { return max(1, (int) wp_unslash($_GET[$internal_arg])); }
        if (isset($_GET[$url_arg]))      { return max(1, (int) wp_unslash($_GET[$url_arg])); }
        return 1;
    }
}

if (! function_exists('sp_archive_decode_json_array')) {
    function sp_archive_decode_json_array($value): array {
        if (is_array($value)) { return $value; }
        if (! is_string($value) || $value === '') { return []; }
        $decoded = json_decode(wp_unslash($value), true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (! function_exists('sp_archive_normalize_filters')) {
    function sp_archive_normalize_filters($filters): array {
        $filters = sp_archive_decode_json_array($filters);
        $output  = [];
        foreach ($filters as $filter) {
            if (! is_array($filter)) { continue; }
            $name     = sanitize_key($filter['name']     ?? $filter['key'] ?? '');
            $taxonomy = sanitize_key($filter['taxonomy'] ?? '');
            if ($name === '' || $taxonomy === '' || ! taxonomy_exists($taxonomy)) { continue; }
            $output[] = [
                'name'      => $name,
                'query_arg' => sanitize_key($filter['query_arg'] ?? $name) ?: $name,
                'taxonomy'  => $taxonomy,
                'ui'        => sanitize_key($filter['ui'] ?? 'buttons') ?: 'buttons',
                'field'     => sanitize_key($filter['field']    ?? 'slug') ?: 'slug',
                'operator'  => sanitize_key($filter['operator'] ?? 'IN')   ?: 'IN',
                'terms'     => array_values(array_unique(array_filter(array_map('sanitize_title', (array) ($filter['terms'] ?? []))))),
                'terms_mode' => in_array(($filter['terms_mode'] ?? 'children'), ['selected', 'children', 'parent'], true) ? ($filter['terms_mode'] ?? 'children') : 'children',
            ];
        }
        return $output;
    }
}

if (! function_exists('sp_archive_normalize_term_scope')) {
    function sp_archive_normalize_term_scope($scope): array
    {
        $scope = sp_archive_decode_json_array($scope);
        $output = [];

        foreach ($scope as $taxonomy => $terms) {
            $taxonomy = sanitize_key((string) $taxonomy);

            if ($taxonomy === '' || ! taxonomy_exists($taxonomy)) {
                continue;
            }

            if (is_array($terms) && array_key_exists('terms', $terms)) {
                $terms = $terms['terms'];
            }

            $terms = array_filter(array_map('sanitize_title', (array) $terms));
            $terms = array_values(array_unique($terms));

            if (empty($terms)) {
                continue;
            }

            $output[$taxonomy] = $terms;
        }

        return $output;
    }
}

if (! function_exists('sp_archive_builder_normalize_field_filters')) {
    function sp_archive_builder_normalize_field_filters($filters): array
    {
        $filters = sp_archive_decode_json_array($filters);
        $output  = [];

        foreach ((array) $filters as $taxonomy => $filter) {
            if (! is_array($filter)) {
                continue;
            }

            $taxonomy = sanitize_key((string) ($filter['taxonomy'] ?? $taxonomy));

            if ($taxonomy === '' || ! taxonomy_exists($taxonomy)) {
                continue;
            }

            $has_settings = isset($filter['taxonomy']) || ! empty($filter['enabled']);

            if (! $has_settings) {
                continue;
            }

            $ui = sanitize_key((string) ($filter['ui'] ?? 'buttons'));
            $terms_mode = sanitize_key((string) ($filter['terms_mode'] ?? 'children'));

            $output[] = [
                'taxonomy'   => $taxonomy,
                'ui'         => in_array($ui, ['buttons', 'select', 'multiselect', 'radio', 'checkbox'], true) ? $ui : 'buttons',
                'terms_mode' => in_array($terms_mode, ['selected', 'children', 'parent'], true) ? $terms_mode : 'children',
            ];
        }

        return $output;
    }
}

add_action('admin_init', function (): void {
    if (empty($_POST) || empty($_POST['acf_fields']) || ! is_array($_POST['acf_fields'])) {
        return;
    }

    $posted_fields = wp_unslash($_POST['acf_fields']);

    foreach ($posted_fields as $field_id => $posted_field) {
        if (! is_array($posted_field)) {
            continue;
        }

        $archive_setting_keys = [
            'term_scope',
            'filters',
            'load_more_label',
            'all_label',
            'group_on_all',
            'action',
            'page_arg',
            'url_page_arg',
            'sort_arg',
            'per_page_arg',
        ];
        $has_archive_setting = false;

        foreach ($archive_setting_keys as $setting_key) {
            if (array_key_exists($setting_key, $posted_field)) {
                $has_archive_setting = true;
                break;
            }
        }

        if (($posted_field['type'] ?? '') !== 'archive_builder' && ! $has_archive_setting) {
            continue;
        }

        if (array_key_exists('term_scope', $posted_field)) {
            $_POST['acf_fields'][$field_id]['term_scope'] = sp_archive_normalize_term_scope($posted_field['term_scope']);
        }

        if (array_key_exists('filters', $posted_field)) {
            $_POST['acf_fields'][$field_id]['filters'] = sp_archive_builder_normalize_field_filters($posted_field['filters']);
        }

        if (array_key_exists('load_more_label', $posted_field)) {
            $_POST['acf_fields'][$field_id]['load_more_label'] = sanitize_text_field((string) $posted_field['load_more_label']);
        }

        if (array_key_exists('all_label', $posted_field)) {
            $_POST['acf_fields'][$field_id]['all_label'] = sanitize_text_field((string) $posted_field['all_label']);
        }

        if (array_key_exists('group_on_all', $posted_field)) {
            $_POST['acf_fields'][$field_id]['group_on_all'] = ! empty($posted_field['group_on_all']) ? 1 : 0;
        } elseif (($posted_field['type'] ?? '') === 'archive_builder') {
            $_POST['acf_fields'][$field_id]['group_on_all'] = 0;
        }

        foreach (['action', 'page_arg', 'url_page_arg', 'sort_arg', 'per_page_arg'] as $query_arg_key) {
            if (array_key_exists($query_arg_key, $posted_field)) {
                $_POST['acf_fields'][$field_id][$query_arg_key] = sanitize_key((string) $posted_field[$query_arg_key]);
            }
        }

        $_POST['acf_fields'][$field_id]['save'] = '';
    }
}, 1);

if (! function_exists('sp_archive_builder_posted_field_settings')) {
    function sp_archive_builder_posted_field_settings(array $field): array
    {
        if (empty($_POST['acf_fields']) || ! is_array($_POST['acf_fields'])) {
            return [];
        }

        $posted_fields = wp_unslash($_POST['acf_fields']);
        $candidates = array_filter(array_map('strval', [
            $field['ID'] ?? '',
            $field['id'] ?? '',
            $field['key'] ?? '',
        ]));

        foreach ($candidates as $candidate) {
            if (isset($posted_fields[$candidate]) && is_array($posted_fields[$candidate])) {
                return $posted_fields[$candidate];
            }
        }

        foreach ($posted_fields as $posted_field) {
            if (! is_array($posted_field)) {
                continue;
            }

            if (! empty($field['key']) && ! empty($posted_field['key']) && $posted_field['key'] === $field['key']) {
                return $posted_field;
            }
        }

        return [];
    }
}

if (! function_exists('sp_archive_builder_saved_field_settings')) {
    function sp_archive_builder_saved_field_settings(array $field): array
    {
        $field_id = (int) ($field['ID'] ?? $field['id'] ?? 0);

        if ($field_id <= 0) {
            return [];
        }

        $content = get_post_field('post_content', $field_id, 'raw');

        if (! is_string($content) || $content === '') {
            return [];
        }

        $settings = maybe_unserialize($content);

        return is_array($settings) ? $settings : [];
    }
}

if (! function_exists('sp_archive_builder_field_term_scope')) {
    function sp_archive_builder_field_term_scope(array $field): array
    {
        $term_scope = sp_archive_normalize_term_scope($field['term_scope'] ?? []);

        if ($term_scope) {
            return $term_scope;
        }

        $saved_settings = sp_archive_builder_saved_field_settings($field);

        return sp_archive_normalize_term_scope($saved_settings['term_scope'] ?? []);
    }
}

if (! function_exists('sp_archive_builder_field_load_more_label')) {
    function sp_archive_builder_field_load_more_label(array $field): string
    {
        $label = sanitize_text_field((string) ($field['load_more_label'] ?? ''));

        if ($label !== '') {
            return $label;
        }

        $saved_settings = sp_archive_builder_saved_field_settings($field);
        $label = sanitize_text_field((string) ($saved_settings['load_more_label'] ?? ''));

        return $label !== '' ? $label : 'Show More';
    }
}

if (! function_exists('sp_archive_builder_field_all_label')) {
    function sp_archive_builder_field_all_label(array $field): string
    {
        $label = sanitize_text_field((string) ($field['all_label'] ?? ''));

        if ($label !== '') {
            return $label;
        }

        $saved_settings = sp_archive_builder_saved_field_settings($field);
        $label = sanitize_text_field((string) ($saved_settings['all_label'] ?? ''));

        return $label !== '' ? $label : 'All';
    }
}

if (! function_exists('sp_archive_builder_field_query_arg')) {
    function sp_archive_builder_field_query_arg(array $field, string $key, string $default = ''): string
    {
        $value = sanitize_key((string) ($field[$key] ?? ''));

        if ($value !== '') {
            return $value;
        }

        $saved_settings = sp_archive_builder_saved_field_settings($field);
        $value = sanitize_key((string) ($saved_settings[$key] ?? ''));

        return $value !== '' ? $value : $default;
    }
}

if (! function_exists('sp_archive_builder_field_bool')) {
    function sp_archive_builder_field_bool(array $field, string $key, bool $default = false): bool
    {
        if (array_key_exists($key, $field)) {
            return ! empty($field[$key]);
        }

        $saved_settings = sp_archive_builder_saved_field_settings($field);

        if (array_key_exists($key, $saved_settings)) {
            return ! empty($saved_settings[$key]);
        }

        return $default;
    }
}

if (! function_exists('sp_archive_builder_field_filters')) {
    function sp_archive_builder_field_filters(array $field): array
    {
        $filters = sp_archive_builder_normalize_field_filters($field['filters'] ?? []);

        if (empty($filters)) {
            $filters = sp_archive_builder_normalize(['post_type' => $field['post_type'] ?? 'post', 'filters' => $field['filters'] ?? []])['filters'];
        }

        if (! empty($filters)) {
            return $filters;
        }

        $saved_settings = sp_archive_builder_saved_field_settings($field);
        $filters = sp_archive_builder_normalize_field_filters($saved_settings['filters'] ?? []);

        if (! empty($filters)) {
            return $filters;
        }

        return sp_archive_builder_normalize([
            'post_type' => $field['post_type'] ?? 'post',
            'filters'   => $saved_settings['filters'] ?? [],
        ])['filters'];
    }
}

if (! function_exists('sp_archive_normalize_filter_terms')) {
    function sp_archive_normalize_filter_terms($value): array {
        // JS sends multiple values joined with '|' — split before normalization
        if (is_string($value) && str_contains($value, '|')) {
            $value = explode('|', $value);
        }
        $values = is_array($value) ? $value : [$value];
        $output = [];
        foreach ($values as $item) {
            $item = sp_archive_normalize_choice($item);
            if ($item !== '') { $output[] = $item; }
        }
        return array_values(array_unique($output));
    }
}

if (! function_exists('sp_archive_filter_values')) {
    function sp_archive_filter_values(array $filters, array $source): array {
        $output = [];
        foreach ($filters as $filter) {
            $name = sanitize_key($filter['name'] ?? $filter['key'] ?? '');
            if ($name === '') { continue; }
            $query_arg = sanitize_key($filter['query_arg'] ?? $name) ?: $name;
            $value = '';
            if (array_key_exists($query_arg, $source))      { $value = $source[$query_arg]; }
            elseif (array_key_exists($name, $source))       { $value = $source[$name]; }
            $terms           = sp_archive_normalize_filter_terms($value);
            $output[$name]   = count($terms) > 1 ? $terms : ($terms[0] ?? '');
        }
        return $output;
    }
}

if (! function_exists('sp_archive_filter_availability')) {
    function sp_archive_filter_availability(array $args = []): array
    {
        $args = wp_parse_args($args, [
            'post_type'     => 'post',
            'filters'       => [],
            'filter_values' => [],
            'term_scope'    => [],
            'sort'          => 'newest',
            'favorite_first' => false,
            'lang'          => '',
        ]);

        $filters = sp_archive_normalize_filters($args['filters']);

        if (empty($filters)) {
            return [];
        }

        $values = is_array($args['filter_values']) ? $args['filter_values'] : [];
        $output = [];

        foreach ($filters as $filter) {
            $name     = $filter['name'];
            $taxonomy = $filter['taxonomy'];

            if (! taxonomy_exists($taxonomy)) {
                continue;
            }

            $term_scope = sp_archive_normalize_term_scope($args['term_scope']);
            $include    = sp_archive_filter_scope_slugs($taxonomy, $term_scope[$taxonomy] ?? [], $filter['terms_mode'] ?? 'children');
            $terms_args = [
                'taxonomy'   => $taxonomy,
                'hide_empty' => true,
                'fields'     => 'id=>slug',
            ];

            if ($include) {
                $terms_args['slug'] = $include;
            }

            $terms = get_terms($terms_args);

            if (is_wp_error($terms) || empty($terms)) {
                $output[$name] = ['all' => true];
                continue;
            }

            $current_terms = sp_archive_normalize_filter_terms($values[$name] ?? '');
            $is_multiple   = in_array($filter['ui'], ['checkbox', 'multiselect'], true);

            $output[$name] = ['all' => true];

            foreach ($terms as $slug) {
                $slug = sanitize_title((string) $slug);

                if ($slug === '') {
                    continue;
                }

                if (in_array($slug, $current_terms, true)) {
                    $output[$name][$slug] = true;
                    continue;
                }

                $candidate_values = $values;
                $candidate_values[$name] = $is_multiple
                    ? array_values(array_unique(array_merge($current_terms, [$slug])))
                    : $slug;

                $query_args = sp_archive_query_args([
                    'post_type'     => $args['post_type'],
                    'filters'       => $filters,
                    'filter_values' => $candidate_values,
                    'term_scope'    => $term_scope,
                    'per_page'      => 1,
                    'paged'         => 1,
                    'sort'          => $args['sort'],
                    'favorite_first' => ! empty($args['favorite_first']),
                    'lang'          => $args['lang'],
                ]);

                $query_args['posts_per_page'] = 1;
                $query_args['fields']         = 'ids';
                $query_args['no_found_rows']  = true;

                $query = new WP_Query($query_args);
                $output[$name][$slug] = $query->have_posts();
                wp_reset_postdata();
            }
        }

        return $output;
    }
}

if (! function_exists('sp_archive_query_args')) {
    function sp_archive_query_args(array $args = []): array {
        $args      = wp_parse_args($args, ['post_type' => 'post', 'filters' => [], 'filter_values' => [], 'term_scope' => [], 'per_page' => 9, 'paged' => 1, 'sort' => 'newest', 'favorite_first' => false, 'lang' => '']);
        $post_type = sanitize_key((string) $args['post_type']);
        $post_type = post_type_exists($post_type) ? $post_type : 'post';
        $per_page  = sp_archive_normalize_per_page($args['per_page']);
        $paged     = max(1, (int) $args['paged']);
        $sort      = sp_archive_normalize_sort($args['sort']);
        $order     = sp_archive_sort_args($sort);
        $filters   = sp_archive_normalize_filters($args['filters']);
        $values    = is_array($args['filter_values']) ? $args['filter_values'] : [];
        $tax_query = [];
        foreach (sp_archive_normalize_term_scope($args['term_scope']) as $taxonomy => $terms) {
            $tax_query[] = ['taxonomy' => $taxonomy, 'field' => 'slug', 'terms' => $terms, 'operator' => 'IN'];
        }
        foreach ($filters as $filter) {
            $terms = sp_archive_normalize_filter_terms($values[$filter['name']] ?? '');
            if (empty($terms)) { continue; }
            $tax_query[] = ['taxonomy' => $filter['taxonomy'], 'field' => $filter['field'], 'terms' => $terms, 'operator' => $filter['operator']];
        }
        if (count($tax_query) > 1) { $tax_query['relation'] = 'AND'; }
        $orderby = $order['orderby'];
        $qa = ['post_type' => $post_type, 'post_status' => 'publish', 'ignore_sticky_posts' => true, 'no_found_rows' => false, 'posts_per_page' => $per_page, 'paged' => $paged];
        $language = sanitize_key((string) $args['lang']);
        if ($language !== '') { $qa['lang'] = $language; }
        if (is_array($orderby)) { $qa['orderby'] = $orderby; } else { $qa['orderby'] = $orderby; $qa['order'] = $order['order']; }
        if ($tax_query) { $qa['tax_query'] = $tax_query; }

        if (! empty($args['favorite_first'])) {
            $qa['meta_query'] = [
                'relation' => 'OR',
                'sp_favorite_first' => [
                    'key'     => '_sp_favorite_post',
                    'compare' => 'EXISTS',
                    'type'    => 'NUMERIC',
                ],
                'sp_favorite_missing' => [
                    'key'     => '_sp_favorite_post',
                    'compare' => 'NOT EXISTS',
                ],
            ];

            $qa['orderby'] = is_array($qa['orderby'])
                ? ['sp_favorite_first' => 'DESC'] + $qa['orderby']
                : ['sp_favorite_first' => 'DESC', $qa['orderby'] => $qa['order'] ?? 'DESC'];

            unset($qa['order']);
        }

        return $qa;
    }
}

if (! function_exists('sp_archive_prepare_query')) {
    function sp_archive_prepare_query(array $args = []): array {
        $args = wp_parse_args($args, ['post_type' => 'post', 'filters' => [], 'filter_values' => [], 'term_scope' => [], 'per_page' => 9, 'paged' => 1, 'sort' => 'menu_order', 'pagination_mode' => 'pagination', 'group_filter' => [], 'favorite_first' => false, 'lang' => '']);
        $mode        = sp_archive_normalize_mode($args['pagination_mode']);
        $per_page    = sp_archive_normalize_per_page($args['per_page']);
        $paged       = $per_page === -1 ? 1 : max(1, (int) $args['paged']);
        $filter_values = is_array($args['filter_values']) ? $args['filter_values'] : [];
        $group_filter = is_array($args['group_filter']) ? $args['group_filter'] : [];
        if ($group_filter) {
            $qa = sp_archive_query_args([
                'post_type'     => $args['post_type'],
                'filters'       => $args['filters'],
                'filter_values' => $filter_values,
                'term_scope'    => $args['term_scope'],
                'per_page'      => -1,
                'paged'         => 1,
                'sort'          => sp_archive_normalize_sort($args['sort']),
                'favorite_first' => ! empty($args['favorite_first']),
                'lang'          => $args['lang'],
            ]);

            $query = new WP_Query($qa);
            $posts = sp_archive_order_posts_by_group_filter(
                array_values(array_filter(
                    is_array($query->posts) ? $query->posts : [],
                    static fn($post): bool => $post instanceof WP_Post
                )),
                $group_filter
            );

            $total_found  = count($posts);
            $total_pages  = $per_page === -1 ? 1 : max(1, (int) ceil($total_found / $per_page));
            $current_page = max(1, min($paged, $total_pages));
            $slice_offset = $per_page === -1 ? 0 : ($current_page - 1) * $per_page;
            $slice_limit  = $per_page === -1 ? null : $per_page;

            if (! wp_doing_ajax() && ($mode === 'infinity_scroll' || $mode === 'load_more') && $current_page > 1) {
                $slice_offset = 0;
                $slice_limit  = $per_page * $current_page;
            }

            $query->posts = $slice_limit === null
                ? array_slice($posts, $slice_offset)
                : array_slice($posts, $slice_offset, $slice_limit);
            $query->post_count = count($query->posts);
            $query->found_posts = $total_found;
            $query->max_num_pages = $total_pages;
            $query->current_post = -1;
            $query->in_the_loop = false;

            return ['query' => $query, 'total_found' => $total_found, 'total_pages' => $total_pages, 'current_page' => $current_page];
        }

        $query_page  = $paged;
        $query_limit = $per_page;
        if (! wp_doing_ajax() && ($mode === 'infinity_scroll' || $mode === 'load_more') && $paged > 1) {
            $query_page  = 1;
            $query_limit = $per_page * $paged;
        }
        $qa           = sp_archive_query_args(['post_type' => $args['post_type'], 'filters' => $args['filters'], 'filter_values' => $filter_values, 'term_scope' => $args['term_scope'], 'per_page' => $query_limit, 'paged' => $query_page, 'sort' => sp_archive_normalize_sort($args['sort']), 'favorite_first' => ! empty($args['favorite_first']), 'lang' => $args['lang']]);
        $query        = new WP_Query($qa);
        $total_found  = (int) $query->found_posts;
        $total_pages  = $per_page === -1 ? 1 : max(1, (int) ceil($total_found / $per_page));
        $current_page = max(1, min($paged, $total_pages));
        if ($current_page !== $paged) {
            wp_reset_postdata();
            $query_page  = $current_page;
            $query_limit = $per_page;
            if (! wp_doing_ajax() && ($mode === 'infinity_scroll' || $mode === 'load_more') && $current_page > 1) {
                $query_page  = 1;
                $query_limit = $per_page * $current_page;
            }
            $qa           = sp_archive_query_args(['post_type' => $args['post_type'], 'filters' => $args['filters'], 'filter_values' => $filter_values, 'term_scope' => $args['term_scope'], 'per_page' => $query_limit, 'paged' => $query_page, 'sort' => sp_archive_normalize_sort($args['sort']), 'favorite_first' => ! empty($args['favorite_first']), 'lang' => $args['lang']]);
            $query        = new WP_Query($qa);
            $total_found  = (int) $query->found_posts;
            $total_pages  = $per_page === -1 ? 1 : max(1, (int) ceil($total_found / $per_page));
        }
        return ['query' => $query, 'total_found' => $total_found, 'total_pages' => $total_pages, 'current_page' => $current_page];
    }
}

if (! function_exists('sp_archive_terms')) {
    function sp_archive_terms(int $post_id, string $taxonomy): array {
        $terms = get_the_terms($post_id, $taxonomy);
        return is_array($terms) && ! is_wp_error($terms) ? $terms : [];
    }
}

if (! function_exists('sp_archive_render_terms')) {
    function sp_archive_render_terms(array $terms, string $class = ''): void {
        if (empty($terms)) { return; }
        $class = $class !== '' ? $class : 'sp-archive__tag';
        foreach ($terms as $term) {
            if ($term instanceof WP_Term) {
                echo '<span class="' . esc_attr($class) . '">' . esc_html($term->name) . '</span>';
            }
        }
    }
}

if (! function_exists('sp_archive_sanitize_template')) {
    function sp_archive_sanitize_template($template): string {
        $template = trim(str_replace('\\', '/', wp_unslash((string) $template)));
        $template = preg_replace('/\.php$/', '', $template);
        $template = trim((string) $template, '/');
        if ($template === '' || strpos($template, '..') !== false) { return ''; }
        $allowed_prefixes = apply_filters(
            'sp_archive_template_prefixes',
            ['template_parts/', 'templates/', 'php/cards/', 'php/templates/']
        );
        $is_allowed = false;

        foreach ((array) $allowed_prefixes as $prefix) {
            $prefix = trim(str_replace('\\', '/', (string) $prefix), '/');
            if ($prefix !== '' && strpos($template, $prefix . '/') === 0) {
                $is_allowed = true;
                break;
            }
        }

        if (! $is_allowed) { return ''; }
        return is_readable(THEME_DIR . '/' . $template . '.php') ? $template : '';
    }
}

if (! function_exists('sp_archive_component_template')) {
    function sp_archive_component_template(string $component): string {
        $component = sanitize_key($component);
        if ($component === '') { return ''; }

        $configured = (string) apply_filters('sp_archive_component_template', '', $component);
        $candidates = array_filter([
            $configured,
            'templates/ui/' . $component,
            'php/templates/ui/' . $component,
            'php/templates/' . $component,
            'template_parts/ui/' . $component,
        ]);

        foreach (array_unique($candidates) as $candidate) {
            $candidate = sp_archive_sanitize_template($candidate);
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }
}

if (! function_exists('sp_archive_pagination_template')) {
    function sp_archive_pagination_template(): string {
        $configured = (string) apply_filters('sp_archive_pagination_template', '');
        if ($configured !== '') {
            $configured = sp_archive_sanitize_template($configured);
            if ($configured !== '') { return $configured; }
        }

        return sp_archive_component_template('pagination');
    }
}

if (! function_exists('sp_archive_sanitize_class_string')) {
    function sp_archive_sanitize_class_string($classes): string {
        $classes = preg_split('/\s+/', trim((string) $classes));
        $classes = array_map('sanitize_html_class', is_array($classes) ? $classes : []);
        return implode(' ', array_filter($classes));
    }
}

if (! function_exists('sp_archive_render_template')) {
    function sp_archive_render_template(string $template, array $args = []): string {
        $template = sp_archive_sanitize_template($template);
        if ($template === '') { return ''; }
        ob_start();
        get_template_part($template, null, $args);
        return ob_get_clean();
    }
}

if (! function_exists('sp_archive_template_args_for_index')) {
    function sp_archive_template_args_for_index(array $template_args, int $index): array {
        $args = $template_args;

        unset($args['class_names'], $args['by_index']);

        if (! isset($args['class_name']) && ! empty($template_args['class_names']) && is_array($template_args['class_names'])) {
            $class_names = array_values(array_filter(array_map('strval', $template_args['class_names'])));

            if (! empty($class_names)) {
                $args['class_name'] = $class_names[$index % count($class_names)];
            }
        }

        if (! empty($template_args['by_index']) && is_array($template_args['by_index'])) {
            $by_index = array_values($template_args['by_index']);

            if (! empty($by_index)) {
                $indexed_args = $by_index[$index] ?? $by_index[$index % count($by_index)] ?? [];

                if (is_array($indexed_args)) {
                    $args = array_merge($args, $indexed_args);
                }
            }
        }

        return $args;
    }
}

if (! function_exists('sp_archive_render_cards')) {
    function sp_archive_render_cards(WP_Query $query, string $template, array $args = []): string {
        $template       = sp_archive_sanitize_template($template);
        $empty_template = sp_archive_sanitize_template($args['empty_template'] ?? '');
        $item_args      = isset($args['template_args']) && is_array($args['template_args']) ? $args['template_args'] : [];
        $start_index    = max(0, (int) ($args['start_index'] ?? 0));
        $group_filter   = is_array($args['group_filter'] ?? null) ? $args['group_filter'] : [];
        $filter_values  = is_array($args['filter_values'] ?? null) ? $args['filter_values'] : [];
        $archive_post_ids = array_map(
            static fn($post): int => $post instanceof WP_Post ? (int) $post->ID : (int) $post,
            is_array($query->posts) ? $query->posts : []
        );
        $archive_loop_index = $start_index;
        ob_start();
        if ($template !== '' && $query->have_posts()) {
            if ($group_filter) {
                $grouped_html = sp_archive_render_grouped_cards($query, $template, [
                    'group_filter'  => $group_filter,
                    'template_args'  => $item_args,
                    'start_index'    => $start_index,
                ]);

                if ($grouped_html !== '') {
                    echo $grouped_html;
                    wp_reset_postdata();
                    return ob_get_clean();
                }
            }

            while ($query->have_posts()) {
                $query->the_post();
                $template_args = sp_archive_template_args_for_index($item_args, $archive_loop_index);
                echo sp_archive_render_template(
                    $template,
                    array_merge(
                        $template_args,
                        [
                            'post_id'            => (int) get_the_ID(),
                            'archive_loop_index' => $archive_loop_index,
                            'archive_post_ids'   => $archive_post_ids,
                        ]
                    )
                );
                $archive_loop_index++;
            }
        } elseif ($empty_template !== '') {
            echo sp_archive_render_template($empty_template);
        }
        wp_reset_postdata();
        return ob_get_clean();
    }
}

if (! function_exists('sp_archive_has_active_filter_values')) {
    function sp_archive_has_active_filter_values(array $values): bool
    {
        foreach ($values as $value) {
            if (is_array($value)) {
                if (! empty(array_filter($value, static fn($item): bool => (string) $item !== ''))) {
                    return true;
                }
                continue;
            }

            if ((string) $value !== '') {
                return true;
            }
        }

        return false;
    }
}

if (! function_exists('sp_archive_group_terms_for_filter')) {
    /**
     * Return ordered group terms for the same term set used by a filter.
     *
     * @return array<string, WP_Term>
     */
    function sp_archive_group_terms_for_filter(array $filter): array
    {
        $taxonomy = sanitize_key((string) ($filter['taxonomy'] ?? ''));

        if ($taxonomy === '' || ! taxonomy_exists($taxonomy)) {
            return [];
        }

        $include_slugs = sp_archive_filter_scope_slugs(
            $taxonomy,
            is_array($filter['terms'] ?? null) ? $filter['terms'] : [],
            (string) ($filter['terms_mode'] ?? 'children')
        );
        $choices = sp_archive_term_choices($taxonomy, [
            'hide_empty'    => true,
            'include_slugs' => $include_slugs,
            'parent_only'   => ($filter['terms_mode'] ?? 'children') === 'parent' && empty($include_slugs),
        ]);

        if (empty($choices)) {
            return [];
        }

        $terms = get_terms([
            'taxonomy'   => $taxonomy,
            'hide_empty' => true,
            'slug'       => array_keys($choices),
        ]);

        if (is_wp_error($terms) || empty($terms)) {
            return [];
        }

        $by_slug = [];

        foreach ($terms as $term) {
            if ($term instanceof WP_Term) {
                $by_slug[$term->slug] = $term;
            }
        }

        $ordered = [];

        foreach (array_keys($choices) as $slug) {
            if (isset($by_slug[$slug])) {
                $ordered[$slug] = $by_slug[$slug];
            }
        }

        return $ordered;
    }
}

if (! function_exists('sp_archive_order_posts_by_group_filter')) {
    /**
     * Reorder posts by group terms while preserving the current query sort inside each group.
     *
     * @param WP_Post[] $posts
     * @return WP_Post[]
     */
    function sp_archive_order_posts_by_group_filter(array $posts, array $filter): array
    {
        $taxonomy = sanitize_key((string) ($filter['taxonomy'] ?? ''));
        $terms = sp_archive_group_terms_for_filter($filter);

        if ($taxonomy === '' || empty($terms) || empty($posts)) {
            return $posts;
        }

        $term_slugs = array_keys($terms);
        $grouped = array_fill_keys($term_slugs, []);
        $ungrouped = [];

        foreach ($posts as $post) {
            if (! $post instanceof WP_Post) {
                continue;
            }

            $post_terms = get_the_terms($post, $taxonomy);
            $post_slugs = [];

            if (! is_wp_error($post_terms) && ! empty($post_terms)) {
                foreach ($post_terms as $term) {
                    if ($term instanceof WP_Term) {
                        $post_slugs[] = $term->slug;
                    }
                }
            }

            $matched_slug = '';

            foreach ($term_slugs as $slug) {
                if (in_array($slug, $post_slugs, true)) {
                    $matched_slug = $slug;
                    break;
                }
            }

            if ($matched_slug !== '') {
                $grouped[$matched_slug][] = $post;
                continue;
            }

            $ungrouped[] = $post;
        }

        $ordered = [];

        foreach ($term_slugs as $slug) {
            if (! empty($grouped[$slug])) {
                $ordered = array_merge($ordered, $grouped[$slug]);
            }
        }

        return array_merge($ordered, $ungrouped);
    }
}

if (! function_exists('sp_archive_render_card_item')) {
    function sp_archive_render_card_item(string $template, array $item_args, int $index, array $post_ids): string
    {
        $template_args = sp_archive_template_args_for_index($item_args, $index);

        return sp_archive_render_template(
            $template,
            array_merge(
                $template_args,
                [
                    'post_id'            => (int) get_the_ID(),
                    'archive_loop_index' => $index,
                    'archive_post_ids'   => $post_ids,
                ]
            )
        );
    }
}

if (! function_exists('sp_archive_render_grouped_cards')) {
    function sp_archive_render_grouped_cards(WP_Query $query, string $template, array $args = []): string
    {
        $filter   = is_array($args['group_filter'] ?? null) ? $args['group_filter'] : [];
        $terms    = sp_archive_group_terms_for_filter($filter);
        $taxonomy = sanitize_key((string) ($filter['taxonomy'] ?? ''));

        if ($taxonomy === '' || empty($terms)) {
            return '';
        }

        $item_args = isset($args['template_args']) && is_array($args['template_args']) ? $args['template_args'] : [];
        $start_index = max(0, (int) ($args['start_index'] ?? 0));
        $posts = array_values(array_filter(
            is_array($query->posts) ? $query->posts : [],
            static fn($post): bool => $post instanceof WP_Post
        ));

        if (empty($posts)) {
            return '';
        }

        $archive_post_ids = array_map(static fn(WP_Post $post): int => (int) $post->ID, $posts);
        $term_slugs = array_keys($terms);
        $grouped = array_fill_keys($term_slugs, []);
        $ungrouped = [];

        foreach ($posts as $post) {
            $post_terms = get_the_terms($post, $taxonomy);
            $matched_slug = '';

            if (! is_wp_error($post_terms) && ! empty($post_terms)) {
                $post_slugs = [];

                foreach ($post_terms as $term) {
                    if ($term instanceof WP_Term) {
                        $post_slugs[] = $term->slug;
                    }
                }

                foreach ($term_slugs as $slug) {
                    if (in_array($slug, $post_slugs, true)) {
                        $matched_slug = $slug;
                        break;
                    }
                }
            }

            if ($matched_slug !== '') {
                $grouped[$matched_slug][] = $post;
            } else {
                $ungrouped[] = $post;
            }
        }

        $archive_loop_index = $start_index;
        ob_start();

        foreach ($terms as $slug => $term) {
            if (empty($grouped[$slug])) {
                continue;
            }

            echo '<div class="full-row d-[inherit] gap-[inherit] grid-cols-[inherit] mb-[clamp(2rem,2.9296875vw,4rem)]" data-sp-archive-group="' . esc_attr($slug) . '">';
            echo '<h3 class="sp-archive-group__title h3-medium color-[var(--cl-c)]" style="grid-column: 1 / -1;">' . esc_html($term->name) . '</h3>';

            foreach ($grouped[$slug] as $group_post) {
                $GLOBALS['post'] = $group_post;
                setup_postdata($group_post);
                echo sp_archive_render_card_item($template, $item_args, $archive_loop_index, $archive_post_ids);
                $archive_loop_index++;
            }

            echo '</div>';
        }

        foreach ($ungrouped as $group_post) {
            $GLOBALS['post'] = $group_post;
            setup_postdata($group_post);
            echo sp_archive_render_card_item($template, $item_args, $archive_loop_index, $archive_post_ids);
            $archive_loop_index++;
        }

        wp_reset_postdata();

        return ob_get_clean();
    }
}

if (! function_exists('sp_archive_pagination_data')) {
    function sp_archive_pagination_data(array $args): array {
        $data = [
            'post_type'        => sanitize_key($args['post_type'] ?? 'post'),
            'template'         => sp_archive_sanitize_template($args['template'] ?? ''),
            'filters'          => sp_archive_normalize_filters($args['filters'] ?? []),
            'filter_values'    => is_array($args['filter_values'] ?? null) ? $args['filter_values'] : [],
            'term_scope'       => sp_archive_normalize_term_scope($args['term_scope'] ?? []),
            'per_page'         => sp_archive_normalize_per_page($args['per_page'] ?? 9),
            'load_more_label'  => sanitize_text_field((string) ($args['load_more_label'] ?? 'Show More')),
            'query_arg'        => sanitize_key($args['query_arg']     ?? 'sp_page'),
            'url_query_arg'    => sanitize_key($args['url_query_arg'] ?? 'page'),
            'sort'             => sp_archive_normalize_sort($args['sort'] ?? 'menu_order'),
            'pagination_mode'  => sp_archive_normalize_mode($args['pagination_mode'] ?? 'pagination'),
            'favorite_first'   => ! empty($args['favorite_first']),
            'lang'             => sanitize_key((string) ($args['lang'] ?? '')),
        ];
        foreach ($data['filter_values'] as $key => $value) {
            $data[sanitize_key($key)] = $value;
        }
        return $data;
    }
}

function sp_archive_builder_post_type_choices(): array
{
    if (function_exists('sp_acf_smart_relationship_post_type_choices')) {
        return sp_acf_smart_relationship_post_type_choices();
    }

    $choices = [];

    foreach (get_post_types(['show_ui' => true], 'objects') as $post_type => $object) {
        if (in_array($post_type, ['attachment', 'acf-field', 'acf-field-group'], true)) {
            continue;
        }

        $choices[$post_type] = $object->labels->menu_name ?? $object->label ?? $post_type;
    }

    natcasesort($choices);

    return $choices;
}

function sp_archive_builder_taxonomy_label(string $taxonomy, WP_Taxonomy $object): string
{
    if (function_exists('sp_acf_smart_relationship_taxonomy_label')) {
        return sp_acf_smart_relationship_taxonomy_label($taxonomy, $object);
    }

    return $object->labels->menu_name ?? $object->label ?? $taxonomy;
}

function sp_archive_builder_taxonomies_by_post_type(): array
{
    $map = [];

    foreach (sp_archive_builder_post_type_choices() as $post_type => $label) {
        $map[$post_type] = [];
        $taxonomies      = get_object_taxonomies($post_type, 'objects');

        foreach ($taxonomies as $taxonomy => $object) {
            if (in_array($taxonomy, ['post_format', 'nav_menu'], true)) {
                continue;
            }

            if (empty($object->show_ui) && empty($object->public) && empty($object->publicly_queryable)) {
                continue;
            }

            $map[$post_type][$taxonomy] = sp_archive_builder_taxonomy_label($taxonomy, $object);
        }

        natcasesort($map[$post_type]);
    }

    return $map;
}

function sp_archive_builder_terms_by_post_type(): array
{
    $map = [];

    foreach (sp_archive_builder_taxonomies_by_post_type() as $post_type => $taxonomies) {
        $map[$post_type] = [];

        foreach ($taxonomies as $taxonomy => $label) {
            $map[$post_type][$taxonomy] = sp_archive_term_choices($taxonomy, [
                'hide_empty' => false,
            ]);
        }
    }

    return $map;
}

function sp_archive_builder_defaults(): array
{
    $post_types = array_keys(sp_archive_builder_post_type_choices());

    return [
        'post_type'       => $post_types[0] ?? 'post',
        'filters_enabled' => 0,
        'confirm'         => 0,
        'reset'           => 0,
        'disable_empty'   => 0,
        'term_scope'      => [],
        'filters'         => [],
        'per_page'        => 9,
        'load_more_label' => 'Show More',
        'all_label'       => 'All',
        'group_on_all'    => 0,
        'favorite_first'  => 0,
        'pagination_type' => 'pagination',
        'order_mode'      => 'newest',
        'action'          => 'sp_archive_query',
        'page_arg'        => 'sp_page',
        'url_page_arg'    => 'page',
        'sort_arg'        => '',
        'per_page_arg'    => 'per_page',
    ];
}

function sp_archive_builder_normalize($value): array
{
    $value = is_array($value) ? $value : [];
    $value = wp_parse_args($value, sp_archive_builder_defaults());

    $value['post_type']       = sanitize_key($value['post_type']);
    $value['filters_enabled'] = ! empty($value['filters_enabled']) ? 1 : 0;
    $value['confirm']         = ! empty($value['confirm']) ? 1 : 0;
    $value['reset']           = ! empty($value['reset']) ? 1 : 0;
    $value['disable_empty']   = ! empty($value['disable_empty']) ? 1 : 0;
    $value['group_on_all']    = ! empty($value['group_on_all']) ? 1 : 0;
    $value['favorite_first']  = ! empty($value['favorite_first']) ? 1 : 0;
    $term_scope = sp_archive_normalize_term_scope($value['term_scope'] ?? []);
    $allowed_taxonomies = get_object_taxonomies($value['post_type']);
    $value['term_scope'] = array_intersect_key($term_scope, array_flip($allowed_taxonomies));
    $value['per_page']        = sp_archive_normalize_per_page($value['per_page']);
    $value['load_more_label'] = sanitize_text_field((string) ($value['load_more_label'] ?? ''));
    $value['all_label'] = sanitize_text_field((string) ($value['all_label'] ?? ''));

    if ($value['load_more_label'] === '') {
        $value['load_more_label'] = 'Show More';
    }

    if ($value['all_label'] === '') {
        $value['all_label'] = 'All';
    }

    $value['action']       = sanitize_key((string) ($value['action'] ?? 'sp_archive_query')) ?: 'sp_archive_query';
    $value['page_arg']     = sanitize_key((string) ($value['page_arg'] ?? 'sp_page')) ?: 'sp_page';
    $value['url_page_arg'] = sanitize_key((string) ($value['url_page_arg'] ?? 'page')) ?: 'page';
    $value['sort_arg']     = sanitize_key((string) ($value['sort_arg'] ?? ''));
    $value['per_page_arg'] = sanitize_key((string) ($value['per_page_arg'] ?? 'per_page')) ?: 'per_page';

    if (! in_array($value['pagination_type'], ['pagination', 'load_more', 'infinity_scroll'], true)) {
        $value['pagination_type'] = 'pagination';
    }

    if (! in_array($value['order_mode'], ['newest', 'oldest', 'az', 'za', 'menu_order'], true)) {
        $value['order_mode'] = 'newest';
    }

    $filters = [];
    foreach ((array) $value['filters'] as $taxonomy => $filter) {
        if (! is_array($filter)) {
            continue;
        }

        $tax = '';
        $ui  = 'buttons';

        if (isset($filter['taxonomy'])) {
            $tax = sanitize_key($filter['taxonomy']);
            $ui  = sanitize_key($filter['ui'] ?? 'buttons');
            $terms_mode = sanitize_key($filter['terms_mode'] ?? 'children');
        } elseif (! empty($filter['enabled'])) {
            $tax = sanitize_key($taxonomy);
            $ui  = sanitize_key($filter['ui'] ?? 'buttons');
            $terms_mode = sanitize_key($filter['terms_mode'] ?? 'children');
        } else {
            continue;
        }

        if ($tax === '') {
            continue;
        }

        $filters[] = [
            'taxonomy'   => $tax,
            'ui'         => in_array($ui, ['buttons', 'select', 'multiselect', 'radio', 'checkbox'], true) ? $ui : 'buttons',
            'terms_mode' => in_array($terms_mode, ['selected', 'children', 'parent'], true) ? $terms_mode : 'children',
        ];
    }

    $value['filters'] = $filters;

    return $value;
}

function sp_get_archive_builder_config($value): array
{
    return sp_archive_builder_normalize($value);
}

function sp_archive_builder_order_args(array $config): array
{
    switch ($config['order_mode'] ?? 'newest') {
        case 'oldest':
            return ['orderby' => 'date', 'order' => 'ASC'];
        case 'az':
            return ['orderby' => 'title', 'order' => 'ASC'];
        case 'za':
            return ['orderby' => 'title', 'order' => 'DESC'];
        case 'menu_order':
            return ['orderby' => ['menu_order' => 'ASC', 'date' => 'DESC']];
        case 'newest':
        default:
            return ['orderby' => 'date', 'order' => 'DESC'];
    }
}

function sp_archive_builder_query_args($value, array $selected_terms = [], int $paged = 1): array
{
    $config = sp_archive_builder_normalize($value);
    $args   = [
        'post_type'      => $config['post_type'],
        'post_status'    => 'publish',
        'posts_per_page' => $config['per_page'],
        'paged'          => max(1, $paged),
    ];

    $args = array_merge($args, sp_archive_builder_order_args($config));

    $tax_query = [];

    foreach ($config['term_scope'] as $taxonomy => $terms) {
        $tax_query[] = [
            'taxonomy' => $taxonomy,
            'field'    => 'slug',
            'terms'    => $terms,
            'operator' => 'IN',
        ];
    }

    if (! empty($config['filters_enabled']) && $selected_terms) {
        foreach ($selected_terms as $taxonomy => $terms) {
            $taxonomy = sanitize_key($taxonomy);
            $terms    = array_filter(array_map('sanitize_title', (array) $terms));

            if ($taxonomy === '' || empty($terms)) {
                continue;
            }

            $tax_query[] = [
                'taxonomy' => $taxonomy,
                'field'    => 'slug',
                'terms'    => $terms,
            ];
        }

    }

    if ($tax_query) {
        $args['tax_query'] = count($tax_query) > 1
            ? array_merge(['relation' => 'AND'], $tax_query)
            : $tax_query;
    }

    return $args;
}

function sp_archive_builder_filter_terms($value): array
{
    $config = sp_archive_builder_normalize($value);

    if (empty($config['filters_enabled'])) {
        return [];
    }

    $output = [];

    foreach ($config['filters'] as $filter) {
        $taxonomy = $filter['taxonomy'];

        if (! taxonomy_exists($taxonomy)) {
            continue;
        }

        $term_scope = sp_archive_filter_scope_slugs($taxonomy, $config['term_scope'][$taxonomy] ?? [], $filter['terms_mode'] ?? 'children');
        $terms_args = [
            'taxonomy'   => $taxonomy,
            'hide_empty' => true,
        ];

        if ($term_scope) {
            $terms_args['slug'] = $term_scope;
        }

        $terms = get_terms($terms_args);

        if (is_wp_error($terms) || empty($terms)) {
            continue;
        }

        $output[] = [
            'taxonomy'   => $taxonomy,
            'label'      => get_taxonomy($taxonomy)->labels->name ?? $taxonomy,
            'ui'         => $filter['ui'],
            'terms_mode' => $filter['terms_mode'] ?? 'children',
            'terms'      => $terms,
        ];
    }

    return $output;
}

function sp_archive_builder_data_config($value): string
{
    $config = sp_archive_builder_normalize($value);

    return wp_json_encode([
        'postType'       => $config['post_type'],
        'filtersEnabled' => (bool) $config['filters_enabled'],
        'filters'        => $config['filters'],
        'termScope'      => $config['term_scope'],
        'disableEmpty'   => (bool) $config['disable_empty'],
        'disable_empty'  => (bool) $config['disable_empty'],
        'perPage'        => $config['per_page'],
        'loadMoreLabel'  => $config['load_more_label'],
        'allLabel'       => $config['all_label'],
        'groupOnAll'     => (bool) $config['group_on_all'],
        'paginationType' => $config['pagination_type'],
        'orderMode'      => $config['order_mode'],
        'action'         => $config['action'],
        'pageArg'        => $config['page_arg'],
        'urlPageArg'     => $config['url_page_arg'],
        'sortArg'        => $config['sort_arg'],
        'perPageArg'     => $config['per_page_arg'],
    ]) ?: '{}';
}

add_action('acf/include_field_types', function (): void {
    if (! class_exists('acf_field') || class_exists('SP_ACF_Field_Archive_Builder', false)) {
        return;
    }

    class SP_ACF_Field_Archive_Builder extends acf_field
    {
        public function initialize(): void
        {
            $this->name     = 'archive_builder';
            $this->label    = __('Archive Builder', 'acf');
            $this->category = 'layout';
            $this->defaults = [
                'post_type'       => 'post',
                'filters_enabled' => 0,
                'confirm'         => 0,
                'reset'           => 0,
                'disable_empty'   => 0,
                'term_scope'      => [],
                'filters'         => [],
                'per_page'        => 9,
                'per_page_choices' => [],
                'load_more_label' => 'Show More',
                'all_label'       => 'All',
                'group_on_all'    => 0,
                'pagination_type' => 'pagination',
                'order_mode'      => 'newest',
                'action'          => 'sp_archive_query',
                'page_arg'        => 'sp_page',
                'url_page_arg'    => 'page',
                'sort_arg'        => '',
                'per_page_arg'    => 'per_page',
            ];
        }

        public function render_field_settings(array $field): void
        {
            $field['load_more_label'] = sp_archive_builder_field_load_more_label($field);
            $field['all_label']       = sp_archive_builder_field_all_label($field);
            $field['action']          = sp_archive_builder_field_query_arg($field, 'action', 'sp_archive_query');
            $field['page_arg']        = sp_archive_builder_field_query_arg($field, 'page_arg', 'sp_page');
            $field['url_page_arg']    = sp_archive_builder_field_query_arg($field, 'url_page_arg', 'page');
            $field['sort_arg']        = sp_archive_builder_field_query_arg($field, 'sort_arg', '');
            $field['per_page_arg']    = sp_archive_builder_field_query_arg($field, 'per_page_arg', 'per_page');
            $field['group_on_all']    = sp_archive_builder_field_bool($field, 'group_on_all') ? 1 : 0;

            // Target post type setting
            acf_render_field_setting($field, [
                'label'        => __('Target Post Type', 'acf'),
                'instructions' => __('Select the post type to query for this archive.', 'acf'),
                'type'         => 'select',
                'name'         => 'post_type',
                'choices'      => sp_archive_builder_post_type_choices(),
                'ui'           => 0,
            ]);

            // Enable Filters setting
            acf_render_field_setting($field, [
                'label'        => __('Enable Filters', 'acf'),
                'instructions' => __('Allow visitors to filter results by taxonomies.', 'acf'),
                'type'         => 'true_false',
                'name'         => 'filters_enabled',
                'ui'           => 1,
            ]);

            acf_render_field_setting($field, [
                'label'        => __('Confirm Button', 'acf'),
                'instructions' => __('Allow templates to render an apply-filters button.', 'acf'),
                'type'         => 'true_false',
                'name'         => 'confirm',
                'ui'           => 1,
            ]);

            acf_render_field_setting($field, [
                'label'        => __('Reset Button', 'acf'),
                'instructions' => __('Allow templates to render a reset-filters button.', 'acf'),
                'type'         => 'true_false',
                'name'         => 'reset',
                'ui'           => 1,
            ]);

            acf_render_field_setting($field, [
                'label'        => __('Disable Empty Options', 'acf'),
                'instructions' => __('Disable filter options that would return an empty archive result.', 'acf'),
                'type'         => 'true_false',
                'name'         => 'disable_empty',
                'ui'           => 1,
            ]);

            acf_render_field_setting($field, [
                'label'        => __('Group on All', 'acf'),
                'instructions' => __('Show taxonomy headings only when no filter value is selected. Uses the first enabled filter taxonomy.', 'acf'),
                'type'         => 'true_false',
                'name'         => 'group_on_all',
                'ui'           => 1,
            ]);

            acf_render_field_setting($field, [
                'label'        => __('Load more button text', 'acf'),
                'instructions' => __('Text rendered inside the load-more button.', 'acf'),
                'type'         => 'text',
                'name'         => 'load_more_label',
                'placeholder'  => __('Show More', 'acf'),
            ]);

            acf_render_field_setting($field, [
                'label'        => __('All filter label', 'acf'),
                'instructions' => __('Text used for the empty/all option in archive filters.', 'acf'),
                'type'         => 'text',
                'name'         => 'all_label',
                'placeholder'  => __('All', 'acf'),
            ]);

            acf_render_field_setting($field, [
                'label'        => __('AJAX action', 'acf'),
                'instructions' => __('WordPress AJAX action used by this archive.', 'acf'),
                'type'         => 'text',
                'name'         => 'action',
                'placeholder'  => 'sp_archive_query',
            ]);

            acf_render_field_setting($field, [
                'label'        => __('Internal page argument', 'acf'),
                'instructions' => __('Request key sent to AJAX pagination.', 'acf'),
                'type'         => 'text',
                'name'         => 'page_arg',
                'placeholder'  => 'sp_page',
            ]);

            acf_render_field_setting($field, [
                'label'        => __('URL page argument', 'acf'),
                'instructions' => __('URL query key used for pagination links, e.g. stories_page instead of page.', 'acf'),
                'type'         => 'text',
                'name'         => 'url_page_arg',
                'placeholder'  => 'page',
            ]);

            acf_render_field_setting($field, [
                'label'        => __('Sort argument', 'acf'),
                'instructions' => __('URL/query key used by the archive sort control. Leave empty to disable the sort query argument.', 'acf'),
                'type'         => 'text',
                'name'         => 'sort_arg',
                'placeholder'  => 'case_sort',
            ]);

            acf_render_field_setting($field, [
                'label'        => __('Per-page argument', 'acf'),
                'instructions' => __('URL/query key used by the posts-per-page control.', 'acf'),
                'type'         => 'text',
                'name'         => 'per_page_arg',
                'placeholder'  => 'per_page',
            ]);

            // Output the filters setting manually using ACF 6 flexbox structure
            $taxonomy_map   = sp_archive_builder_taxonomies_by_post_type();
            $terms_map      = sp_archive_builder_terms_by_post_type();
            $term_scope     = sp_archive_builder_field_term_scope($field);
            $filters        = sp_archive_builder_field_filters($field);
            $current_type   = $field['post_type'] ?? 'post';

            // Map current filters to check if enabled and get their UI type
            $enabled_filters = [];
            $filter_term_modes = [];
            $selected_filters = [];
            foreach ($filters as $tax => $filter) {
                if (is_array($filter) && isset($filter['taxonomy'])) {
                    $enabled_filters[$filter['taxonomy']] = $filter['ui'] ?? 'buttons';
                    $filter_term_modes[$filter['taxonomy']] = $filter['terms_mode'] ?? 'children';
                    $selected_filters[$filter['taxonomy']] = [
                        'enabled'    => true,
                        'ui'         => $filter['ui'] ?? 'buttons',
                        'termsMode'  => $filter['terms_mode'] ?? 'children',
                    ];
                } elseif (is_array($filter) && ! empty($filter['enabled'])) {
                    $enabled_filters[$tax] = $filter['ui'] ?? 'buttons';
                    $filter_term_modes[$tax] = $filter['terms_mode'] ?? 'children';
                    $selected_filters[$tax] = [
                        'enabled'    => true,
                        'ui'         => $filter['ui'] ?? 'buttons',
                        'termsMode'  => $filter['terms_mode'] ?? 'children',
                    ];
                }
            }

            $taxonomies_for = $taxonomy_map[$current_type] ?? [];
            $field_prefix   = ! empty($field['prefix']) ? $field['prefix'] : "acf_fields[{$field['key']}]";
            ?>
            <div class="acf-field acf-field-setting-term_scope" data-name="term_scope" data-setting="archive_builder">
                <div class="acf-label">
                    <label><?php _e('Term Scope', 'acf'); ?></label>
                    <p class="description"><?php _e('Limit this archive to all terms or only selected terms from the chosen post type taxonomies.', 'acf'); ?></p>
                </div>
                <div class="acf-input">
                    <div class="sp-archive-builder-scope" data-taxonomies="<?php echo esc_attr(wp_json_encode($taxonomy_map)); ?>" data-terms="<?php echo esc_attr(wp_json_encode($terms_map)); ?>" data-selected-scope="<?php echo esc_attr(wp_json_encode($term_scope)); ?>" data-field-key="<?php echo esc_attr($field['key']); ?>" data-field-prefix="<?php echo esc_attr($field_prefix); ?>">
                        <div class="sp-archive-builder-scope__list" data-sp-archive-settings-scope-list>
                            <?php if (! empty($taxonomies_for)) : ?>
                                <?php foreach ($taxonomies_for as $tax_name => $tax_label) : ?>
                                    <?php $this->render_settings_scope_row($field_prefix, $tax_name, $tax_label, $terms_map[$current_type][$tax_name] ?? [], $term_scope[$tax_name] ?? []); ?>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <p class="description" style="margin: 0;"><?php esc_html_e('No taxonomies available for this post type.', 'acf'); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="acf-field acf-field-setting-filters" data-name="filters" data-setting="archive_builder">
                <div class="acf-label">
                    <label><?php _e('Filters', 'acf'); ?></label>
                    <p class="description"><?php _e('Select taxonomies and their UI representation.', 'acf'); ?></p>
                </div>
                <div class="acf-input">
                    <div class="sp-archive-builder-settings" data-taxonomies="<?php echo esc_attr(wp_json_encode($taxonomy_map)); ?>" data-selected-filters="<?php echo esc_attr(wp_json_encode($selected_filters)); ?>" data-field-key="<?php echo esc_attr($field['key']); ?>" data-field-prefix="<?php echo esc_attr($field_prefix); ?>">
                        <div class="sp-archive-builder-settings__list" data-sp-archive-settings-filter-list>
                            <?php if (! empty($taxonomies_for)) : ?>
                                <?php foreach ($taxonomies_for as $tax_name => $tax_label) : ?>
                                    <?php
                                    $enabled = isset($enabled_filters[$tax_name]);
                                    $ui      = $enabled_filters[$tax_name] ?? 'buttons';
                                    $terms_mode = $filter_term_modes[$tax_name] ?? 'children';
                                    $this->render_settings_taxonomy_row($field_prefix, $tax_name, $tax_label, $enabled, $ui, $terms_mode);
                                    ?>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <p class="description" style="margin: 0;"><?php esc_html_e('No taxonomies available for this post type.', 'acf'); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        }

        private function render_settings_taxonomy_row(string $field_prefix, string $tax_name, string $tax_label, bool $enabled, string $ui, string $terms_mode = 'children'): void
        {
            $input_prefix = "{$field_prefix}[filters][{$tax_name}]";
            $ui_choices   = [
                'buttons'     => __('Buttons', 'acf'),
                'select'      => __('Select', 'acf'),
                'multiselect' => __('Multi-select', 'acf'),
                'radio'       => __('Radio', 'acf'),
                'checkbox'    => __('Checkbox', 'acf'),
            ];
            $term_mode_choices = [
                'children' => __('Children', 'acf'),
                'parent'   => __('Parent', 'acf'),
                'selected' => __('Selected', 'acf'),
            ];
            ?>
            <div class="sp-archive-builder-settings__tax-row<?php echo $enabled ? ' is-active' : ''; ?>" data-taxonomy="<?php echo esc_attr($tax_name); ?>">
                <div class="sp-archive-builder-settings__tax-main">
                    <label class="sp-archive-builder-settings__tax-label">
                        <input type="checkbox" name="<?php echo esc_attr($input_prefix); ?>[enabled]" value="1" <?php checked($enabled); ?> class="sp-archive-builder-settings__checkbox">
                        <span class="sp-archive-builder-settings__tax-copy">
                            <span class="sp-archive-builder-settings__tax-name"><?php echo esc_html($tax_label); ?></span>
                            <span class="sp-archive-builder-settings__tax-slug"><?php echo esc_html($tax_name); ?></span>
                        </span>
                    </label>
                </div>

                <div class="sp-archive-builder-settings__tax-side">
                    <span class="sp-archive-builder-settings__tax-side-label"><?php esc_html_e('Display as', 'acf'); ?></span>
                    <div class="sp-archive-builder-settings__tax-ui" role="radiogroup" aria-label="<?php echo esc_attr($tax_label); ?>">
                        <?php foreach ($ui_choices as $ui_value => $ui_label) : ?>
                            <label class="sp-archive-builder-settings__ui-btn">
                                <input type="radio" name="<?php echo esc_attr($input_prefix); ?>[ui]" value="<?php echo esc_attr($ui_value); ?>" <?php checked($ui, $ui_value); ?>>
                                <span><?php echo esc_html($ui_label); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <span class="sp-archive-builder-settings__tax-side-label"><?php esc_html_e('Terms', 'acf'); ?></span>
                    <div class="sp-archive-builder-settings__term-mode" role="radiogroup" aria-label="<?php echo esc_attr($tax_label); ?>">
                        <?php foreach ($term_mode_choices as $mode_value => $mode_label) : ?>
                            <label class="sp-archive-builder-settings__ui-btn">
                                <input type="radio" name="<?php echo esc_attr($input_prefix); ?>[terms_mode]" value="<?php echo esc_attr($mode_value); ?>" <?php checked($terms_mode, $mode_value); ?>>
                                <span><?php echo esc_html($mode_label); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php
        }

        private function render_settings_scope_row(string $field_prefix, string $tax_name, string $tax_label, array $terms, array $selected_terms): void
        {
            $input_prefix = "{$field_prefix}[term_scope][{$tax_name}][terms]";
            ?>
            <div class="sp-archive-builder-scope__tax-row" data-taxonomy="<?php echo esc_attr($tax_name); ?>">
                <div class="sp-archive-builder-scope__tax-header">
                    <span class="sp-archive-builder-settings__tax-copy">
                        <span class="sp-archive-builder-settings__tax-name"><?php echo esc_html($tax_label); ?></span>
                        <span class="sp-archive-builder-settings__tax-slug"><?php echo esc_html($tax_name); ?></span>
                    </span>
                    <span class="sp-archive-builder-scope__hint"><?php esc_html_e('No terms selected means all terms.', 'acf'); ?></span>
                </div>

                <?php if ($terms) : ?>
                    <div class="sp-archive-builder-scope__terms">
                        <input type="hidden" name="<?php echo esc_attr($input_prefix); ?>[]" value="">
                        <?php foreach ($terms as $term_slug => $term_label) : ?>
                            <label class="sp-archive-builder-scope__term">
                                <input type="checkbox" name="<?php echo esc_attr($input_prefix); ?>[]" value="<?php echo esc_attr($term_slug); ?>" <?php checked(in_array($term_slug, $selected_terms, true)); ?>>
                                <span><?php echo esc_html($term_label); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <p class="description" style="margin: 8px 0 0;"><?php esc_html_e('No terms found for this taxonomy.', 'acf'); ?></p>
                <?php endif; ?>
            </div>
            <?php
        }

        public function update_field($field)
        {
            $field = is_array($field) ? $field : [];
            $posted_field = sp_archive_builder_posted_field_settings($field);
            $raw_term_scope = array_key_exists('term_scope', $posted_field)
                ? $posted_field['term_scope']
                : ($field['term_scope'] ?? []);
            $field['term_scope'] = sp_archive_normalize_term_scope($raw_term_scope);
            $field['filters'] = sp_archive_builder_normalize([
                'post_type' => $posted_field['post_type'] ?? $field['post_type'] ?? 'post',
                'filters'   => array_key_exists('filters', $posted_field)
                    ? $posted_field['filters']
                    : ($field['filters'] ?? []),
            ])['filters'];

            if (array_key_exists('filters', $posted_field)) {
                $field['filters'] = sp_archive_builder_normalize_field_filters($posted_field['filters']);
            }
            $field['load_more_label'] = sanitize_text_field((string) (
                $posted_field['load_more_label']
                ?? $field['load_more_label']
                ?? 'Show More'
            ));

            if ($field['load_more_label'] === '') {
                $field['load_more_label'] = 'Show More';
            }

            $field['all_label'] = sanitize_text_field((string) (
                $posted_field['all_label']
                ?? $field['all_label']
                ?? 'All'
            ));

            if ($field['all_label'] === '') {
                $field['all_label'] = 'All';
            }

            $field['group_on_all'] = $posted_field
                ? (! empty($posted_field['group_on_all']) ? 1 : 0)
                : (! empty($field['group_on_all'] ?? 0) ? 1 : 0);
            $field['action']       = sanitize_key((string) ($posted_field['action'] ?? $field['action'] ?? 'sp_archive_query')) ?: 'sp_archive_query';
            $field['page_arg']     = sanitize_key((string) ($posted_field['page_arg'] ?? $field['page_arg'] ?? 'sp_page')) ?: 'sp_page';
            $field['url_page_arg'] = sanitize_key((string) ($posted_field['url_page_arg'] ?? $field['url_page_arg'] ?? 'page')) ?: 'page';
            $field['sort_arg']     = sanitize_key((string) ($posted_field['sort_arg'] ?? $field['sort_arg'] ?? ''));
            $field['per_page_arg'] = sanitize_key((string) ($posted_field['per_page_arg'] ?? $field['per_page_arg'] ?? 'per_page')) ?: 'per_page';

            return $field;
        }



        public function render_field(array $field): void
        {
            $value = sp_archive_builder_normalize($field['value'] ?? []);
            $name  = $field['name'];

            $per_page_choices = self::per_page_choices($field);
            $post_type = $field['post_type'] ?? 'post';
            $choices = sp_archive_builder_post_type_choices();
            $post_type_label = $choices[$post_type] ?? $post_type;
            ?>
            <div class="sp-archive-builder-card">
                <div class="sp-archive-builder-card__header">
                    <span class="dashicons dashicons-layout" style="color: #2271b1; font-size: 18px; margin-right: 6px;"></span>
                    <strong><?php printf(esc_html__('Archive Settings (%s)', 'acf'), esc_html($post_type_label)); ?></strong>
                </div>

                <div class="sp-archive-builder-card__grid">
                    <!-- Posts per page dropdown -->
                    <div class="sp-archive-builder-card__field">
                        <label for="<?php echo esc_attr($name); ?>-per-page"><?php esc_html_e('Number of posts', 'acf'); ?></label>
                        <select id="<?php echo esc_attr($name); ?>-per-page" name="<?php echo esc_attr($name); ?>[per_page]">
                            <?php foreach ($per_page_choices as $val => $label) : ?>
                                <option value="<?php echo esc_attr($val === -1 ? 'all' : (string) $val); ?>" <?php selected($value['per_page'], $val); ?>>
                                    <?php if ($val === -1) : ?>
                                        <?php echo esc_html($label); ?>
                                    <?php else : ?>
                                        <?php printf(esc_html(_n('%d post', '%d posts', (int) $label, 'acf')), (int) $label); ?>
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Pagination Type Segmented Control -->
                    <div class="sp-archive-builder-card__field">
                        <label><?php esc_html_e('Pagination type', 'acf'); ?></label>
                        <div class="sp-archive-builder-card__segmented">
                            <label class="sp-archive-builder-card__segment">
                                <input type="radio" name="<?php echo esc_attr($name); ?>[pagination_type]" value="pagination" <?php checked($value['pagination_type'], 'pagination'); ?>>
                                <span><?php esc_html_e('Pagination', 'acf'); ?></span>
                            </label>
                            <label class="sp-archive-builder-card__segment">
                                <input type="radio" name="<?php echo esc_attr($name); ?>[pagination_type]" value="load_more" <?php checked($value['pagination_type'], 'load_more'); ?>>
                                <span><?php esc_html_e('Load more', 'acf'); ?></span>
                            </label>
                            <label class="sp-archive-builder-card__segment">
                                <input type="radio" name="<?php echo esc_attr($name); ?>[pagination_type]" value="infinity_scroll" <?php checked($value['pagination_type'], 'infinity_scroll'); ?>>
                                <span><?php esc_html_e('Infinite scroll', 'acf'); ?></span>
                            </label>
                        </div>
                    </div>

                    <!-- Order dropdown -->
                    <div class="sp-archive-builder-card__field">
                        <label for="<?php echo esc_attr($name); ?>-order-mode"><?php esc_html_e('Sorting order', 'acf'); ?></label>
                        <select id="<?php echo esc_attr($name); ?>-order-mode" name="<?php echo esc_attr($name); ?>[order_mode]">
                            <option value="newest" <?php selected($value['order_mode'], 'newest'); ?>><?php esc_html_e('Newest first', 'acf'); ?></option>
                            <option value="oldest" <?php selected($value['order_mode'], 'oldest'); ?>><?php esc_html_e('Oldest first', 'acf'); ?></option>
                            <option value="az" <?php selected($value['order_mode'], 'az'); ?>><?php esc_html_e('Alphabetical (A-Z)', 'acf'); ?></option>
                            <option value="za" <?php selected($value['order_mode'], 'za'); ?>><?php esc_html_e('Alphabetical (Z-A)', 'acf'); ?></option>
                            <option value="menu_order" <?php selected($value['order_mode'], 'menu_order'); ?>><?php esc_html_e('Menu order', 'acf'); ?></option>
                        </select>
                    </div>

                </div>
            </div>
            <?php
        }

        public function update_value($value, $post_id, array $field)
        {
            $value = is_array($value) ? $value : [];
            $per_page = sp_archive_normalize_per_page($value['per_page'] ?? ($field['per_page'] ?? 9));
            $allowed = array_keys(self::per_page_choices($field));

            if (! in_array($per_page, $allowed, true)) {
                $default = sp_archive_normalize_per_page($field['per_page'] ?? 9);
                $per_page = in_array($default, $allowed, true) ? $default : (int) reset($allowed);
            }

            // Only save the editor-level choices to post meta
            return [
                'per_page'        => $per_page,
                'pagination_type' => in_array($value['pagination_type'] ?? '', ['pagination', 'load_more', 'infinity_scroll'], true) ? $value['pagination_type'] : 'pagination',
                'order_mode'      => in_array($value['order_mode'] ?? '', ['newest', 'oldest', 'az', 'za', 'menu_order'], true) ? $value['order_mode'] : 'newest',
            ];
        }

        public function format_value($value, $post_id, array $field)
        {
            $value = is_array($value) ? $value : [];
            $allowed = array_keys(self::per_page_choices($field));
            $current = sp_archive_normalize_per_page($value['per_page'] ?? ($field['per_page'] ?? 9));

            if (! in_array($current, $allowed, true)) {
                $default = sp_archive_normalize_per_page($field['per_page'] ?? 9);
                $value['per_page'] = in_array($default, $allowed, true) ? $default : (int) reset($allowed);
            }

            // Merge field-level structure settings
            $value['post_type']       = $field['post_type'] ?? 'post';
            $value['filters_enabled'] = ! empty($field['filters_enabled']) ? 1 : 0;
            $value['confirm']         = ! empty($field['confirm']) ? 1 : 0;
            $value['reset']           = ! empty($field['reset']) ? 1 : 0;
            $value['disable_empty']   = ! empty($field['disable_empty']) ? 1 : 0;
            $value['group_on_all']    = sp_archive_builder_field_bool($field, 'group_on_all') ? 1 : 0;
            $value['term_scope']      = $field['term_scope'] ?? [];
            $value['filters']         = $field['filters'] ?? [];
            $value['load_more_label'] = sp_archive_builder_field_load_more_label($field);
            $value['all_label']       = sp_archive_builder_field_all_label($field);
            $value['action']          = sp_archive_builder_field_query_arg($field, 'action', 'sp_archive_query');
            $value['page_arg']        = sp_archive_builder_field_query_arg($field, 'page_arg', 'sp_page');
            $value['url_page_arg']    = sp_archive_builder_field_query_arg($field, 'url_page_arg', 'page');
            $value['sort_arg']        = sp_archive_builder_field_query_arg($field, 'sort_arg', '');
            $value['per_page_arg']    = sp_archive_builder_field_query_arg($field, 'per_page_arg', 'per_page');

            foreach ([
                'action',
                'page_arg',
                'url_page_arg',
                'sort_arg',
                'per_page_arg',
            ] as $runtime_key) {
                if (array_key_exists($runtime_key, $field)) {
                    $value[$runtime_key] = $field[$runtime_key];
                }
            }

            return sp_archive_builder_normalize($value);
        }

        private static function per_page_choices(array $field): array
        {
            $configured = isset($field['per_page_choices']) && is_array($field['per_page_choices'])
                ? $field['per_page_choices']
                : [];

            if ($configured) {
                $choices = [];
                foreach ($configured as $value) {
                    if ((is_string($value) && strtolower(trim($value)) === 'all') || (int) $value === -1) {
                        $choices[-1] = __('Show all', 'acf');
                        continue;
                    }

                    $value = (int) $value;
                    if ($value > 0) {
                        $choices[$value] = $value;
                    }
                }
                if ($choices) { return $choices; }
            }

            $choices = array_combine(range(1, 24), range(1, 24));
            $choices[-1] = __('Show all', 'acf');
            return $choices;
        }

        public function input_admin_enqueue_scripts(): void
        {
            $this->enqueue_assets();
        }

        public function field_group_admin_enqueue_scripts(): void
        {
            $this->enqueue_assets();
        }

        private function enqueue_assets(): void
        {
            $handle = 'sp-archive-builder';

            if (! wp_style_is($handle, 'registered')) {
                wp_register_style($handle, false);
                wp_add_inline_style($handle, self::css());
            }

            wp_enqueue_style($handle);

            if (! wp_script_is($handle, 'registered')) {
                wp_register_script($handle, false, ['jquery'], null, true);
                wp_add_inline_script($handle, self::js());
            }

            wp_enqueue_script($handle);
        }

        private static function css(): string
        {
            return <<<'CSS'
.sp-archive-builder-card {
    --sp-media-border: #d0d5dd;
    --sp-media-brand: var(--wp-admin-theme-color, #2271b1);
    --sp-media-soft: #f7f8fc;

    background: #fff;
    border: 1px solid var(--sp-media-border);
    border-radius: 0;
    box-shadow: 0 1px 2px rgba(16, 24, 40, .04);
}
.sp-archive-builder-card__header {
    padding: 12px 16px;
    border-bottom: 1px solid var(--sp-media-border);
    background: var(--sp-media-soft);
    display: flex;
    align-items: center;
}
.sp-archive-builder-card__header strong {
    font-weight: 600;
    color: #475467;
    font-size: 13px;
}
.sp-archive-builder-card__grid {
    display: grid;
    grid-template-columns: 200px minmax(0, 1fr) 200px;
    gap: 20px;
    padding: 16px;
    align-items: flex-end;
}
@media (max-width: 900px) {
    .sp-archive-builder-card__grid {
        grid-template-columns: 1fr;
    }
}
.sp-archive-builder-card__field {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.sp-archive-builder-card__field > label {
    font-weight: 600;
    color: #475467;
    font-size: 13px;
    display: block !important;
    margin: 0 0 6px 0 !important;
    padding: 0 !important;
    line-height: 1.4 !important;
}
.sp-archive-builder-card select {
    height: 40px !important;
    box-sizing: border-box !important;
    border-radius: 0;
    border: 1px solid var(--sp-media-border);
    padding: 0 8px;
    background-color: #fff;
    color: #475467;
    font-size: 13px;
    line-height: 1 !important;
}
.sp-archive-builder-card__segmented {
    display: flex;
    background: #e9edf5;
    border-radius: 0;
    padding: 3px;
    border: 0;
    height: 40px !important;
    box-sizing: border-box !important;
    align-items: stretch;
}
.sp-archive-builder-card__segment {
    position: relative;
    cursor: pointer;
    flex: 1;
    display: flex;
    align-items: stretch;
}
.sp-archive-builder-card__segment input {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}
.sp-archive-builder-card__segment span {
    display: flex;
    align-items: center;
    justify-content: center;
    flex: 1;
    height: 34px;
    padding: 0 12px;
    border-radius: 0;
    font-size: 13px;
    font-weight: 600;
    color: #475467;
    transition: background .15s ease, color .15s ease, box-shadow .15s ease;
    white-space: nowrap;
    text-align: center;
}

.sp-archive-builder-card__segment input:checked + span {
    background: #fff;
    color: var(--sp-media-brand);
    box-shadow: 0 1px 3px rgba(16, 24, 40, .1);
}


/* Settings styling - checklist */
.sp-archive-builder-settings__list {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-top: 8px;
}
.sp-archive-builder-settings__tax-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 14px 16px;
    background: #fff;
    border: 1px solid #d0d5dd;
    box-sizing: border-box;
    border-radius: 0;
    min-height: 74px;
    transition: background 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease;
}
.sp-archive-builder-settings__tax-row.is-active {
    background: #f7f8fc;
    border-color: var(--wp-admin-theme-color, #2271b1);
    box-shadow: inset 0 0 0 1px rgba(34, 113, 177, 0.08);
}
.sp-archive-builder-settings__tax-main {
    min-width: 0;
    flex: 1 1 auto;
}
.sp-archive-builder-settings__tax-label {
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
    margin: 0 !important;
    padding: 0 !important;
    min-width: 0;
}
.sp-archive-builder-settings__checkbox {
    margin: 0 !important;
    flex: 0 0 auto;
}
.sp-archive-builder-settings__tax-copy {
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 3px;
}
.sp-archive-builder-settings__tax-name {
    font-weight: 600;
    color: #344054;
    font-size: 15px;
    line-height: 1.3;
}
.sp-archive-builder-settings__tax-slug {
    color: #667085;
    font-size: 12px;
    line-height: 1.3;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
}
.sp-archive-builder-settings__tax-side {
    flex: 0 0 auto;
    min-width: 360px;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 6px;
}
.sp-archive-builder-settings__tax-side-label {
    color: #667085;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.02em;
    text-transform: uppercase;
}
.sp-archive-builder-settings__tax-ui,
.sp-archive-builder-settings__term-mode {
    display: grid;
    background: #e9edf5;
    padding: 3px;
    border-radius: 0;
    opacity: 0.5;
    transition: opacity 0.15s ease;
    width: 100%;
    min-width: 360px;
    gap: 3px;
}
.sp-archive-builder-settings__tax-ui {
    grid-template-columns: repeat(3, minmax(0, 1fr));
}
.sp-archive-builder-settings__term-mode {
    grid-template-columns: repeat(3, minmax(0, 1fr));
}
.sp-archive-builder-settings__tax-row.is-active .sp-archive-builder-settings__tax-ui,
.sp-archive-builder-settings__tax-row.is-active .sp-archive-builder-settings__term-mode {
    opacity: 1;
}
.sp-archive-builder-settings__ui-btn {
    position: relative;
    cursor: pointer;
    display: flex;
    flex: 1 1 0;
}
.sp-archive-builder-settings__ui-btn input {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}
.sp-archive-builder-settings__ui-btn span {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 30px;
    padding: 0 10px;
    font-size: 12px;
    font-weight: 600;
    color: #475467;
    border-radius: 0;
    transition: background 0.15s ease, color 0.15s ease;
    text-align: center;
    white-space: nowrap;
}
.sp-archive-builder-settings__ui-btn input:checked + span {
    background: #fff;
    color: var(--wp-admin-theme-color, #2271b1);
    box-shadow: 0 1px 2px rgba(16, 24, 40, 0.08);
}
.sp-archive-builder-scope__list {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-top: 8px;
}
.sp-archive-builder-scope__tax-row {
    padding: 14px 16px;
    background: #fff;
    border: 1px solid #d0d5dd;
    box-sizing: border-box;
}
.sp-archive-builder-scope__tax-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
}
.sp-archive-builder-scope__hint {
    color: #667085;
    font-size: 12px;
    line-height: 1.4;
    text-align: right;
}
.sp-archive-builder-scope__terms {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 12px;
}
.sp-archive-builder-scope__term {
    position: relative;
    margin: 0 !important;
}
.sp-archive-builder-scope__term input {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}
.sp-archive-builder-scope__term span {
    display: block;
    padding: 7px 10px;
    border: 1px solid #d0d5dd;
    background: #fff;
    color: #475467;
    font-size: 12px;
    font-weight: 600;
    line-height: 1.2;
    cursor: pointer;
    transition: background .15s ease, color .15s ease, border-color .15s ease;
}
.sp-archive-builder-scope__term input:checked + span {
    background: var(--wp-admin-theme-color, #2271b1);
    border-color: var(--wp-admin-theme-color, #2271b1);
    color: #fff;
}
@media (max-width: 920px) {
    .sp-archive-builder-settings__tax-row {
        align-items: stretch;
        flex-direction: column;
    }
    .sp-archive-builder-settings__tax-side {
        min-width: 0;
        width: 100%;
        align-items: stretch;
    }
    .sp-archive-builder-settings__tax-ui {
        min-width: 0;
        width: 100%;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .sp-archive-builder-scope__tax-header {
        flex-direction: column;
    }
    .sp-archive-builder-scope__hint {
        text-align: left;
    }
}
CSS;
        }


        private static function js(): string
        {
            return <<<'JS'
(function ($) {
    function taxonomies($field) {
        try {
            return JSON.parse($field.attr('data-taxonomies') || '{}');
        } catch (e) {
            return {};
        }
    }

    function terms($field) {
        try {
            return JSON.parse($field.attr('data-terms') || '{}');
        } catch (e) {
            return {};
        }
    }

    function selectedScope($field) {
        try {
            return JSON.parse($field.attr('data-selected-scope') || '{}');
        } catch (e) {
            return {};
        }
    }

    function selectedFilters($field) {
        try {
            return JSON.parse($field.attr('data-selected-filters') || '{}');
        } catch (e) {
            return {};
        }
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function buildTaxonomyRow(fieldPrefix, taxName, taxLabel, enabled, ui, termsMode) {
        var inputPrefix = fieldPrefix + '[filters][' + taxName + ']';
        var activeClass = enabled ? ' is-active' : '';
        var checkedEnabled = enabled ? ' checked' : '';
        termsMode = termsMode || 'children';
        var modes = [
            { value: 'buttons', label: 'Buttons' },
            { value: 'select', label: 'Select' },
            { value: 'multiselect', label: 'Multi-select' },
            { value: 'radio', label: 'Radio' },
            { value: 'checkbox', label: 'Checkbox' }
        ];
        var uiMarkup = '';
        var termsModeMarkup = '';

        modes.forEach(function (mode) {
            var checked = ui === mode.value ? ' checked' : '';
            uiMarkup += [
                '      <label class="sp-archive-builder-settings__ui-btn">',
                '        <input type="radio" name="' + inputPrefix + '[ui]" value="' + mode.value + '"' + checked + '>',
                '        <span>' + mode.label + '</span>',
                '      </label>'
            ].join('');
        });

        [
            { value: 'children', label: 'Children' },
            { value: 'parent', label: 'Parent' },
            { value: 'selected', label: 'Selected' }
        ].forEach(function (mode) {
            var checked = termsMode === mode.value ? ' checked' : '';
            termsModeMarkup += [
                '      <label class="sp-archive-builder-settings__ui-btn">',
                '        <input type="radio" name="' + inputPrefix + '[terms_mode]" value="' + mode.value + '"' + checked + '>',
                '        <span>' + mode.label + '</span>',
                '      </label>'
            ].join('');
        });

        return [
            '<div class="sp-archive-builder-settings__tax-row' + activeClass + '" data-taxonomy="' + taxName + '">',
            '  <div class="sp-archive-builder-settings__tax-main">',
            '    <label class="sp-archive-builder-settings__tax-label">',
            '      <input type="checkbox" name="' + inputPrefix + '[enabled]" value="1"' + checkedEnabled + ' class="sp-archive-builder-settings__checkbox">',
            '      <span class="sp-archive-builder-settings__tax-copy">',
            '        <span class="sp-archive-builder-settings__tax-name">' + taxLabel + '</span>',
            '        <span class="sp-archive-builder-settings__tax-slug">' + taxName + '</span>',
            '      </span>',
            '    </label>',
            '  </div>',
            '  <div class="sp-archive-builder-settings__tax-side">',
            '    <span class="sp-archive-builder-settings__tax-side-label">Display as</span>',
            '    <div class="sp-archive-builder-settings__tax-ui" role="radiogroup" aria-label="' + taxLabel + '">',
                     uiMarkup,
            '    </div>',
            '    <span class="sp-archive-builder-settings__tax-side-label">Terms</span>',
            '    <div class="sp-archive-builder-settings__term-mode" role="radiogroup" aria-label="' + taxLabel + '">',
                     termsModeMarkup,
            '    </div>',
            '  </div>',
            '</div>'
        ].join('');
    }

    function buildScopeRow(fieldPrefix, taxName, taxLabel, termMap, selectedTerms) {
        var inputPrefix = fieldPrefix + '[term_scope][' + taxName + '][terms]';
        var selected = {};
        var termSlugs = Object.keys(termMap || {});
        var termsMarkup = '';

        (selectedTerms || []).forEach(function (slug) {
            selected[slug] = true;
        });

        if (termSlugs.length > 0) {
            termsMarkup += '<input type="hidden" name="' + inputPrefix + '[]" value="">';
            termSlugs.forEach(function (slug) {
                var checked = selected[slug] ? ' checked' : '';
                termsMarkup += [
                    '<label class="sp-archive-builder-scope__term">',
                    '  <input type="checkbox" name="' + inputPrefix + '[]" value="' + escapeHtml(slug) + '"' + checked + '>',
                    '  <span>' + escapeHtml(termMap[slug]) + '</span>',
                    '</label>'
                ].join('');
            });
            termsMarkup = '<div class="sp-archive-builder-scope__terms">' + termsMarkup + '</div>';
        } else {
            termsMarkup = '<p class="description" style="margin: 8px 0 0;">No terms found for this taxonomy.</p>';
        }

        return [
            '<div class="sp-archive-builder-scope__tax-row" data-taxonomy="' + escapeHtml(taxName) + '">',
            '  <div class="sp-archive-builder-scope__tax-header">',
            '    <span class="sp-archive-builder-settings__tax-copy">',
            '      <span class="sp-archive-builder-settings__tax-name">' + escapeHtml(taxLabel) + '</span>',
            '      <span class="sp-archive-builder-settings__tax-slug">' + escapeHtml(taxName) + '</span>',
            '    </span>',
            '    <span class="sp-archive-builder-scope__hint">No terms selected means all terms.</span>',
            '  </div>',
                 termsMarkup,
            '</div>'
        ].join('');
    }

    function currentFieldPrefix($row, $settings) {
        var $postTypeSelect = $row.find('.acf-field-setting-post_type select');
        var postTypeName = $postTypeSelect.attr('name');
        var fieldPrefix = $settings.attr('data-field-prefix') || 'acf_fields[' + ($settings.attr('data-field-key') || 'field_temp') + ']';
        if (postTypeName) {
            fieldPrefix = postTypeName.replace(/\[post_type\]$/, '');
        }
        return fieldPrefix;
    }

    function syncTaxonomyOptions($settings) {
        var $row = $settings.closest('.acf-field-settings');
        var $postTypeSelect = $row.find('.acf-field-setting-post_type select');
        var postType = $postTypeSelect.val() || 'post';
        var fieldPrefix = currentFieldPrefix($row, $settings);
        
        var map = taxonomies($settings)[postType] || {};
        var $list = $settings.find('[data-sp-archive-settings-filter-list]');
        var savedValues = selectedFilters($settings);
        var initialized = $settings.attr('data-ui-initialized') === '1';
        
        // Save current user choices to preserve selections when toggling post types
        var currentValues = {};
        $list.find('.sp-archive-builder-settings__tax-row').each(function () {
            var $taxRow = $(this);
            var tax = $taxRow.attr('data-taxonomy');
            var enabled = $taxRow.find('.sp-archive-builder-settings__checkbox').is(':checked');
            var ui = $taxRow.find('input[name$="[ui]"]:checked').val() || 'buttons';
            var termsMode = $taxRow.find('input[name$="[terms_mode]"]:checked').val() || 'children';
            currentValues[tax] = { enabled: enabled, ui: ui, termsMode: termsMode };
        });

        var html = '';
        var taxNames = Object.keys(map);
        if (taxNames.length > 0) {
            taxNames.forEach(function (taxonomy) {
                var existing = initialized
                    ? (currentValues[taxonomy] || savedValues[taxonomy] || {})
                    : (savedValues[taxonomy] || currentValues[taxonomy] || {});
                var enabled = existing.enabled || false;
                var ui = existing.ui || 'buttons';
                var termsMode = existing.termsMode || existing.terms_mode || 'children';
                html += buildTaxonomyRow(fieldPrefix, taxonomy, map[taxonomy], enabled, ui, termsMode);
            });
        } else {
            html = '<p class="description" style="margin: 0;">No taxonomies available for this post type.</p>';
        }

        $list.html(html);
        $settings.attr('data-ui-initialized', '1');
    }

    function syncScopeOptions($scope) {
        var $row = $scope.closest('.acf-field-settings');
        var $postTypeSelect = $row.find('.acf-field-setting-post_type select');
        var postType = $postTypeSelect.val() || 'post';
        var fieldPrefix = currentFieldPrefix($row, $scope);
        var taxonomyMap = taxonomies($scope)[postType] || {};
        var termMap = terms($scope)[postType] || {};
        var savedScope = selectedScope($scope);
        var $list = $scope.find('[data-sp-archive-settings-scope-list]');
        var currentValues = {};

        $list.find('.sp-archive-builder-scope__tax-row').each(function () {
            var $scopeRow = $(this);
            var tax = $scopeRow.attr('data-taxonomy');
            currentValues[tax] = [];
            $scopeRow.find('input[type="checkbox"]:checked').each(function () {
                currentValues[tax].push($(this).val());
            });
        });

        var html = '';
        var taxNames = Object.keys(taxonomyMap);
        if (taxNames.length > 0) {
            taxNames.forEach(function (taxonomy) {
                html += buildScopeRow(fieldPrefix, taxonomy, taxonomyMap[taxonomy], termMap[taxonomy] || {}, currentValues[taxonomy] || savedScope[taxonomy] || []);
            });
        } else {
            html = '<p class="description" style="margin: 0;">No taxonomies available for this post type.</p>';
        }

        $list.html(html);
    }

    function toggleFiltersVisibility($settings) {
        var $row = $settings.closest('.acf-field-settings');
        var $enabledInput = $row.find('.acf-field-setting-filters_enabled input');
        var enabled = $enabledInput.is(':checked');
        var $filtersField = $row.find('.acf-field-setting-filters');
        
        if (enabled) {
            $filtersField.show();
        } else {
            $filtersField.hide();
        }
    }

    function markArchiveBuilderFieldDirty($inside) {
        var $settings = $inside.closest('.acf-field-settings').find('.sp-archive-builder-scope, .sp-archive-builder-settings').first();
        var fieldPrefix = $settings.attr('data-field-prefix') || '';

        if (!fieldPrefix) {
            return;
        }

        var $saveInput = $('input[name="' + fieldPrefix + '[save]"]');
        $saveInput.val('');
    }

    $(document).on('change', '.acf-field-setting-post_type select', function () {
        var $row = $(this).closest('.acf-field-settings');
        var $settings = $row.find('.sp-archive-builder-settings');
        if ($settings.length) {
            syncTaxonomyOptions($settings);
        }
        var $scope = $row.find('.sp-archive-builder-scope');
        if ($scope.length) {
            syncScopeOptions($scope);
        }
        markArchiveBuilderFieldDirty($(this));
    });

    $(document).on('change', '.acf-field-setting-filters_enabled input', function () {
        var $row = $(this).closest('.acf-field-settings');
        var $settings = $row.find('.sp-archive-builder-settings');
        if ($settings.length) {
            toggleFiltersVisibility($settings);
        }
    });

    $(document).on('change', '.sp-archive-builder-settings__checkbox', function () {
        var $cb = $(this);
        var $row = $cb.closest('.sp-archive-builder-settings__tax-row');
        $row.toggleClass('is-active', $cb.is(':checked'));
        markArchiveBuilderFieldDirty($cb);
    });

    function enableFilterRow($input) {
        var $row = $input.closest('.sp-archive-builder-settings__tax-row');
        var $checkbox = $row.find('.sp-archive-builder-settings__checkbox');

        if (!$checkbox.is(':checked')) {
            $checkbox.prop('checked', true);
            $row.addClass('is-active');
        }
    }

    $(document).on('change', '.sp-archive-builder-settings__tax-ui input[type="radio"]', function () {
        enableFilterRow($(this));
        markArchiveBuilderFieldDirty($(this));
    });

    $(document).on('change', '.sp-archive-builder-settings__term-mode input[type="radio"]', function () {
        enableFilterRow($(this));
        markArchiveBuilderFieldDirty($(this));
    });

    $(document).on('change', '.sp-archive-builder-scope input[type="checkbox"]', function () {
        markArchiveBuilderFieldDirty($(this));
    });

    $(document).on('mousedown click', '#publish, .acf-btn-publish, button[type="submit"], input[type="submit"]', function () {
        $('.sp-archive-builder-scope').each(function () {
            markArchiveBuilderFieldDirty($(this));
        });
        $('.sp-archive-builder-settings').each(function () {
            markArchiveBuilderFieldDirty($(this));
        });
    });

    // Initialize settings
    if (window.acf) {
        acf.addAction('ready_field_object', function (field) {
            var $el = $(field.$el || field);
            var $settings = $el.find('.sp-archive-builder-settings');
            if ($settings.length) {
                syncTaxonomyOptions($settings);
                toggleFiltersVisibility($settings);
            }
            var $scope = $el.find('.sp-archive-builder-scope');
            if ($scope.length) {
                syncScopeOptions($scope);
            }
        });
    }
})(jQuery);
JS;
        }
    }


    acf_register_field_type('SP_ACF_Field_Archive_Builder');
});

if (! function_exists('archive_builder') && class_exists('StoutLogic\AcfBuilder\FieldsBuilder')) {
    function archive_builder(string $name, array $args = []): StoutLogic\AcfBuilder\FieldsBuilder
    {
        $builder = new StoutLogic\AcfBuilder\FieldsBuilder($name . '_archive_builder');
        $builder->addField($name, 'archive_builder', $args);

        return $builder;
    }
}

// ---------------------------------------------------------------------------
// Universal render helpers for archive sections
// ---------------------------------------------------------------------------


/**
 * Render a single archive filter as a custom select.
 *
 * @param  array  $filter          One entry from $config['filters']: ['taxonomy', 'ui', ...]
 * @param  array  $current_filters Active filter values keyed by taxonomy slug.
 * @param  string $all_label       Label for "All" / empty option (default 'All').
 * @param  string $class           Extra class added to each rendered filter control.
 * @param  array  $disabled_options Option availability map: [slug => bool].
 */
if (! function_exists('sp_archive_render_filter')) {
    function sp_archive_render_filter(array $filter, array $current_filters, string $all_label = '', string $class = '', array $disabled_options = []): void
    {
        $taxonomy = $filter['taxonomy'] ?? '';

        if (! $taxonomy || ! taxonomy_exists($taxonomy)) {
            return;
        }

        $tax_obj   = get_taxonomy($taxonomy);
        $tax_label = $tax_obj ? ($tax_obj->labels->singular_name ?? $taxonomy) : $taxonomy;
        $title     = $tax_label . ':';
        $all_label = $all_label !== '' ? $all_label : __('All', THEME_SLUG);
        $options   = sp_archive_filter_options($taxonomy, $all_label, $filter['terms'] ?? [], $filter['terms_mode'] ?? 'children');
        $raw_value = $current_filters[$taxonomy] ?? '';
        $value     = $raw_value !== '' ? $raw_value : 'all';
        $ui        = $filter['ui'] ?? 'select';

        $common = [
            'name'      => $taxonomy,
            'value'     => $value,
            'options'   => $options,
            'title'     => $title,
            'all_label' => $all_label,
            'class'     => $class,
            'disabled_options' => $disabled_options,
        ];

        switch ($ui) {
            case 'radio':
                $template = sp_archive_component_template('filter-radio');
                break;
            case 'checkbox':
                $template = sp_archive_component_template('filter-checkbox');
                break;
            case 'buttons':
                $template = sp_archive_component_template('filter-buttons');
                break;
            case 'multiselect':
                $template = sp_archive_component_template('select');
                $common = array_merge($common, [
                    'placeholder' => $all_label,
                    'mode'        => 'multiple',
                ]);
                break;
            case 'select':
            default:
                $template = sp_archive_component_template('select');
                $common = array_merge($common, [
                    'placeholder' => $all_label,
                    'mode'        => 'single',
                ]);
                break;
        }

        if ($template !== '') {
            get_template_part($template, null, $common);
        }
    }
}

/**
 * Render pagination (wraps php/templates/pagination).
 *
 * @param  array $args {
 *   'current'          int
 *   'total'            int
 *   'mode'             string  'pagination'|'load_more'|'infinity_scroll'
 *   'load_more_label'  string
 *   'action'           string
 *   'page_arg'         string
 *   'url_page_arg'     string
 *   'pagination_data'  array   Data blob embedded in <script> for AJAX pagination
 * }
 */
if (! function_exists('sp_archive_render_pagination')) {
    function sp_archive_render_pagination(array $args): void
    {
        $template = sp_archive_pagination_template();
        if ($template === '') { return; }

        get_template_part($template, null, [
            'current'       => (int) ($args['current']   ?? 1),
            'total'         => (int) ($args['total']     ?? 1),
            'mode'          => $args['mode']             ?? 'pagination',
            'load_more_label' => $args['load_more_label'] ?? 'Show More',
            'ajax'          => true,
            'action'        => $args['action']           ?? 'sp_archive_query',
            'query_arg'     => $args['page_arg']         ?? 'sp_page',
            'url_query_arg' => $args['url_page_arg']     ?? 'page',
            'data'          => $args['pagination_data']  ?? [],
        ]);
    }
}

/**
 * Full archive render — the main entry point for archive sections.
 *
 * Sets up the query, renders filters, cards, and pagination.
 * The card template receives ['post_id' => int] in $args.
 *
 * @param  array  $config        Normalized archive_builder config.
 * @param  string $card_template Path to card template, e.g. 'php/cards/case-card'.
 * @param  array  $opts {
 *   'action'           string  default 'sp_archive_query'
 *   'page_arg'         string  default 'sp_page'
 *   'url_page_arg'     string  default 'page'
 *   'sort_arg'         string  default ''
 *   'empty_template'   string  Template path for the empty state
 *   'list_class'       string  CSS classes for the cards grid wrapper
 *   'section_attrs'    string  Extra HTML attributes for <section> (already escaped)
 *   'before_filters'   string  Raw HTML to output before filter row
 *   'after_filters'    string  Raw HTML to output after filter row
 * }
 * @param  string $section_class  CSS class(es) for the <section> element.
 */
if (! function_exists('sp_archive_render')) {
    /**
     * Convenience wrapper — renders a complete archive section in one call.
     * For custom markup, use sp_archive_setup() + granular functions instead.
     */
    function sp_archive_render(array $config, string $card_template, array $opts = [], string $section_class = 'sp-archive'): void
    {
        sp_archive_setup($config, $card_template, $opts);

        $list_class  = $opts['list_class']   ?? '';
        $extra_attrs = $opts['section_attrs'] ?? '';
        ?>
        <section class="<?= esc_attr($section_class); ?>" <?= sp_archive_attr(); ?> <?= $extra_attrs; ?>>

            <?= $opts['before_filters'] ?? ''; ?>
            <?php sp_archive_filters(); ?>
            <?= $opts['after_filters'] ?? ''; ?>

            <?= $opts['before_list'] ?? ''; ?>
            <?php sp_archive_cards($list_class); ?>
            <?php sp_archive_pagination(); ?>
            <?= $opts['after_list'] ?? ''; ?>

            <?php sp_archive_config(); ?>

        </section>
        <?php
    }
}

// ---------------------------------------------------------------------------
// Granular archive API
//
//  1. sp_archive_setup()      — run query, init context (call once at top)
//  2. sp_archive_attr()       — returns data-sp-archive for <section>
//  3. sp_archive_filters()    — render filter selects
//  4. sp_archive_cards()      — render cards grid (data-sp-archive-list wrapper)
//  5. sp_archive_pagination() — render pagination
//  6. sp_archive_config()     — output hidden JSON config (must be inside <section>)
// ---------------------------------------------------------------------------

if (! function_exists('_sp_archive_ctx')) {
    /** Internal: get / set archive context. */
    function _sp_archive_ctx(?array $set = null): ?array
    {
        static $ctx;
        if ($set !== null) {
            $ctx = $set;
        }
        return $ctx ?? null;
    }
}

if (! function_exists('sp_archive_setup')) {
    /**
     * Initialize archive context: normalize config, run WP_Query, register token.
     * Must be called before any other sp_archive_*() function in the template.
     *
     * @param  array  $config        get_sub_field('archive') — archive_builder value.
     * @param  string $card_template Path to card template (receives ['post_id' => int]).
     * @param  array  $opts {
     *   'action'           string
     *   'page_arg'         string
     *   'url_page_arg'     string
     *   'sort_arg'         string
     *   'template_args'    array Additional args passed to each card template.
     * }
     */
    function sp_archive_setup(array $config, string $card_template, array $opts = []): void
    {
        $config = sp_archive_builder_normalize($config);

        $action         = $opts['action']         ?? ($config['action'] ?? 'sp_archive_query');
        $page_arg       = $opts['page_arg']       ?? ($config['page_arg'] ?? 'sp_page');
        $url_page_arg   = $opts['url_page_arg']   ?? ($config['url_page_arg'] ?? 'page');
        $sort_arg       = $opts['sort_arg']       ?? ($config['sort_arg'] ?? '');
        $per_page_arg   = $opts['per_page_arg']   ?? ($config['per_page_arg'] ?? 'per_page');
        $favorite_first = ! empty($opts['favorite_first'] ?? ($config['favorite_first'] ?? false));
        $template_args  = isset($opts['template_args']) && is_array($opts['template_args'])
            ? $opts['template_args']
            : [];
        $empty_template = sp_archive_sanitize_template(
            $opts['empty_template'] ?? dirname($card_template) . '/empty'
        );

        // Build filters — only for registered taxonomies
        $archive_filters = [];
        foreach ($config['filters'] as $filter) {
            $tax = $filter['taxonomy'] ?? '';
            if (! $tax || ! taxonomy_exists($tax)) {
                continue;
            }
            $archive_filters[] = [
                'name'      => $tax,
                'query_arg' => $tax,
                'taxonomy'  => $tax,
                'ui'        => $filter['ui'] ?? 'buttons',
                'terms_mode' => $filter['terms_mode'] ?? 'children',
                'terms'     => $config['term_scope'][$tax] ?? [],
            ];
        }

        $current_filters = sp_archive_filter_values($archive_filters, wp_unslash($_GET));

        $default_sort = $config['order_mode'];
        $current_sort = isset($_GET[$sort_arg]) && $sort_arg !== ''
            ? sp_archive_normalize_sort(wp_unslash($_GET[$sort_arg]), $default_sort)
            : $default_sort;
        $current_per_page = isset($_GET[$per_page_arg])
            ? sp_archive_normalize_per_page(wp_unslash($_GET[$per_page_arg]), (int) $config['per_page'])
            : (int) $config['per_page'];

        if ($current_per_page === -1 && (int) $config['per_page'] !== -1) {
            $current_per_page = (int) $config['per_page'];
        }

        $language = sp_archive_current_language();

        $paged = sp_archive_current_page($page_arg, $url_page_arg);

        $query_data = sp_archive_prepare_query([
            'post_type'       => $config['post_type'],
            'filters'         => $archive_filters,
            'filter_values'   => $current_filters,
            'term_scope'      => $config['term_scope'],
            'per_page'        => $current_per_page,
            'paged'           => $paged,
            'sort'            => $current_sort,
            'pagination_mode' => $config['pagination_type'],
            'group_filter'    => ! empty($config['group_on_all']) ? ($archive_filters[0] ?? []) : [],
            'favorite_first'  => $favorite_first,
            'lang'            => $language,
        ]);

        $filter_availability = ! empty($config['disable_empty'])
            ? sp_archive_filter_availability([
                'post_type'     => $config['post_type'],
                'filters'       => $archive_filters,
                'filter_values' => $current_filters,
                'term_scope'    => $config['term_scope'],
                'sort'          => $current_sort,
                'favorite_first' => $favorite_first,
                'lang'          => $language,
            ])
            : [];

        $archive_token = sp_archive_register_config([
            'post_type'        => $config['post_type'],
            'per_page'         => $config['per_page'],
            'load_more_label'  => $config['load_more_label'],
            'all_label'        => $config['all_label'],
            'pagination_type'  => $config['pagination_type'],
            'order_mode'       => $config['order_mode'],
            'confirm'          => $config['confirm'],
            'reset'            => $config['reset'],
            'disable_empty'    => $config['disable_empty'],
            'group_on_all'     => $config['group_on_all'],
            'favorite_first'   => $favorite_first,
            'lang'             => $language,
            'term_scope'       => $config['term_scope'],
            'filters'          => $archive_filters,
            'action'           => $action,
            'card_template'    => $card_template,
            'template_args'    => $template_args,
            'empty_template'   => $empty_template,
            'page_arg'         => $page_arg,
            'url_page_arg'     => $url_page_arg,
            'sort_arg'         => $sort_arg,
            'per_page_arg'     => $per_page_arg,
        ]);

        $pagination_data = array_merge(
            sp_archive_pagination_data([
                'post_type'        => $config['post_type'],
                'template'         => $card_template,
                'filters'          => $archive_filters,
                'filter_values'    => $current_filters,
                'term_scope'       => $config['term_scope'],
                'per_page'         => $current_per_page,
                'load_more_label'  => $config['load_more_label'],
                'query_arg'        => $page_arg,
                'url_query_arg'    => $url_page_arg,
                'sort'             => $current_sort,
                'pagination_mode'  => $config['pagination_type'],
                'favorite_first'   => $favorite_first,
                'lang'             => $language,
            ]),
            ['archive_token' => $archive_token]
        );

        _sp_archive_ctx([
            'config'          => $config,
            'card_template'   => $card_template,
            'template_args'   => $template_args,
            'empty_template'  => $empty_template,
            'archive_filters' => $archive_filters,
            'current_filters' => $current_filters,
            'filter_availability' => $filter_availability,
            'default_sort'    => $default_sort,
            'current_sort'    => $current_sort,
            'current_per_page' => $current_per_page,
            'favorite_first'  => $favorite_first,
            'lang'             => $language,
            'confirm'         => $config['confirm'],
            'reset'           => $config['reset'],
            'query'           => $query_data['query'],
            'total_found'     => (int) $query_data['total_found'],
            'total_pages'     => (int) $query_data['total_pages'],
            'current_page'    => (int) $query_data['current_page'],
            'archive_token'   => $archive_token,
            'pagination_data' => $pagination_data,
            'action'          => $action,
            'page_arg'        => $page_arg,
            'url_page_arg'    => $url_page_arg,
            'sort_arg'        => $sort_arg,
            'per_page_arg'    => $per_page_arg,
        ]);
    }
}

if (! function_exists('sp_archive_attr')) {
    /**
     * Returns the section identifier attribute for JS.
     * Usage: <section class="my-section" <?= sp_archive_attr(); ?>>
     */
    function sp_archive_attr(): string
    {
        return 'data-sp-archive';
    }
}

if (! function_exists('sp_archive_config')) {
    /**
     * Output a hidden JSON config block inside the <section>.
     * JS reads this instead of dozens of data-* attributes.
     * Must be placed anywhere inside the <section data-sp-archive> element.
     */
    function sp_archive_config(): void
    {
        $ctx = _sp_archive_ctx();
        if (! $ctx) {
            return;
        }

        $cfg = [
            'action'          => $ctx['action'],
            'archive_token'   => $ctx['archive_token'],
            'post_type'       => $ctx['config']['post_type'],
            'template'        => $ctx['card_template'],
            'filters'         => $ctx['archive_filters'],
            'current_filters' => $ctx['current_filters'],
            'per_page'        => $ctx['current_per_page'],
            'page_arg'        => $ctx['page_arg'],
            'url_page_arg'    => $ctx['url_page_arg'],
            'sort_arg'        => $ctx['sort_arg'],
            'default_sort'    => $ctx['default_sort'],
            'sort_mode'       => $ctx['current_sort'],
            'default_per_page' => $ctx['config']['per_page'],
            'load_more_label'  => $ctx['config']['load_more_label'],
            'all_label'        => $ctx['config']['all_label'],
            'per_page_arg'    => $ctx['per_page_arg'],
            'pagination_mode' => $ctx['config']['pagination_type'],
            'confirm'         => $ctx['confirm'],
            'reset'           => $ctx['reset'],
            'disable_empty'   => $ctx['config']['disable_empty'],
            'group_on_all'    => $ctx['config']['group_on_all'],
            'favorite_first'  => $ctx['favorite_first'],
            'lang'            => $ctx['lang'],
            'term_scope'      => $ctx['config']['term_scope'],
            'filter_availability' => $ctx['filter_availability'],
            'current_page'    => $ctx['current_page'],
            'total_pages'     => $ctx['total_pages'],
        ];

        echo '<script type="application/json" data-sp-archive-config>'
            . wp_json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            . "</script>\n";
    }
}

if (! function_exists('sp_archive_filters')) {
    /**
     * Render all archive filter selects.
     * Requires sp_archive_setup() to have been called first.
     */
    function sp_archive_filters(string $all_label = '', string $class = ''): void
    {
        $ctx = _sp_archive_ctx();

        if (! $ctx || empty($ctx['config']['filters']) || ! $ctx['config']['filters_enabled']) {
            return;
        }

        $all_label = $all_label !== ''
            ? $all_label
            : sanitize_text_field((string) ($ctx['config']['all_label'] ?? 'All'));

        foreach ($ctx['archive_filters'] as $filter) {
            $taxonomy = $filter['taxonomy'] ?? '';
            $disabled_options = $ctx['filter_availability'][$taxonomy] ?? [];
            sp_archive_render_filter($filter, $ctx['current_filters'], $all_label, $class, $disabled_options);
        }
    }
}

if (! function_exists('sp_archive_sort')) {
    /**
     * Render a sort select for the archive.
     * Requires sp_archive_setup() with 'sort_arg' option to have been called first.
     *
     * Accepted sort values: 'newest' | 'oldest' | 'az' | 'za' | 'menu_order'
     *
     * @param array  $options  Custom options list. Each item: ['value' => string, 'label' => string].
     *                         Defaults to newest/oldest/a-z/z-a.
     * @param string $title    Label shown above the select.
     */
    function sp_archive_sort(array $options = [], string $title = '', string $class = ''): void
    {
        $ctx = _sp_archive_ctx();
        if (! $ctx) {
            return;
        }

        $sort_arg = $ctx['sort_arg'];
        if (! $sort_arg) {
            return;
        }


        if (empty($options)) {
            $options = [
                ['value' => 'newest',     'label' => __('Newest first',      THEME_SLUG)],
                ['value' => 'oldest',     'label' => __('Oldest first',      THEME_SLUG)],
                ['value' => 'az',         'label' => __('A → Z',             THEME_SLUG)],
                ['value' => 'za',         'label' => __('Z → A',             THEME_SLUG)],
                ['value' => 'menu_order', 'label' => __('Featured',          THEME_SLUG)],
            ];
        }

        // Format options for php/templates/select: [value => label, ...]
        $select_options = [];
        foreach ($options as $opt) {
            $select_options[ $opt['value'] ] = $opt['label'];
        }

        $template = sp_archive_component_template('select');
        if ($template === '') { return; }

        get_template_part($template, null, [
            'name'    => $sort_arg,
            'value'   => $ctx['current_sort'],
            'options' => $select_options,
            'title'   => $title ?: __('Sort:', THEME_SLUG),
            'mode'    => 'single',
            'class'   => $class,
        ]);
    }
}

if (! function_exists('sp_archive_per_page')) {
    /**
     * Render a posts-per-page select for the archive.
     *
     * @param array  $options [value => label]
     * @param string $title   Label shown above the select.
     * @param string $class   Extra class added to the select control.
     */
    function sp_archive_per_page(array $options = [], string $title = '', string $class = ''): void
    {
        $ctx = _sp_archive_ctx();
        if (! $ctx) {
            return;
        }

        if (empty($options)) {
            $options = array_combine(range(1, 24), range(1, 24));
            $options['all'] = __('Show all', THEME_SLUG);
        }

        $select_options = [];
        foreach ($options as $value => $label) {
            $value = sp_archive_normalize_per_page($value);
            if ($value === -1 || $value > 0) {
                $select_options[$value === -1 ? 'all' : (string) $value] = (string) $label;
            }
        }

        $template = sp_archive_component_template('select');
        if ($template === '') { return; }

        get_template_part($template, null, [
            'name'    => $ctx['per_page_arg'],
            'value'   => $ctx['current_per_page'] === -1 ? 'all' : (string) $ctx['current_per_page'],
            'options' => $select_options,
            'title'   => $title ?: __('Posts per page:', THEME_SLUG),
            'mode'    => 'single',
            'class'   => $class,
        ]);
    }
}

if (! function_exists('sp_archive_confirm')) {
    /**
     * Render archive confirm/apply button when enabled by archive_builder('confirm' => 1).
     */
    function sp_archive_confirm(string $label = '', string $class = ''): void
    {
        $ctx = _sp_archive_ctx();
        if (! $ctx || empty($ctx['confirm'])) {
            return;
        }

        $label = $label !== '' ? $label : __('Apply filters', THEME_SLUG);
        $class = sp_archive_sanitize_class_string($class);

        echo '<button class="' . esc_attr($class) . '" type="button" data-sp-archive-confirm disabled>'
            . '<span>' . esc_html($label) . '</span>'
            . '</button>';
    }
}

if (! function_exists('sp_archive_has_active_controls')) {
    function sp_archive_has_active_controls(array $filters, string $sort, string $default_sort, int $per_page, int $default_per_page): bool
    {
        foreach ($filters as $value) {
            if (is_array($value)) {
                if (! empty(array_filter($value))) {
                    return true;
                }
                continue;
            }

            if ((string) $value !== '') {
                return true;
            }
        }

        return ($sort !== '' && $sort !== $default_sort) || $per_page !== $default_per_page;
    }
}

if (! function_exists('sp_archive_reset')) {
    /**
     * Render archive reset button when enabled by archive_builder('reset' => 1).
     */
    function sp_archive_reset(string $label = '', string $class = ''): void
    {
        $ctx = _sp_archive_ctx();
        if (! $ctx || empty($ctx['reset'])) {
            return;
        }

        $label = $label !== '' ? $label : __('Clear filters', THEME_SLUG);
        $class = sp_archive_sanitize_class_string($class);
        $disabled = sp_archive_has_active_controls(
            $ctx['current_filters'],
            $ctx['current_sort'],
            $ctx['default_sort'],
            (int) $ctx['current_per_page'],
            (int) $ctx['config']['per_page']
        ) ? '' : ' disabled';

        echo '<button class="' . esc_attr($class) . '" type="button" data-sp-archive-reset' . $disabled . '>'
            . '<span>' . esc_html($label) . '</span>'
            . '</button>';
    }
}

if (! function_exists('sp_archive_cards')) {
    /**
     * Render the archive cards grid.
     * Wraps cards in a <div data-sp-archive-list> that JS monitors for AJAX updates.
     * Requires sp_archive_setup() to have been called first.
     *
     * @param string $class CSS classes for the wrapper div.
     */
    function sp_archive_cards(string $class = ''): void
    {
        $ctx = _sp_archive_ctx();
        if (! $ctx) {
            return;
        }

        ?>
        <div class="<?= esc_attr($class); ?>"
             data-sp-archive-list
             data-loader="false"
             data-total="<?= esc_attr((string) $ctx['total_found']); ?>">
            <?= sp_archive_render_cards(
                $ctx['query'],
                $ctx['card_template'],
                [
                    'empty_template' => $ctx['empty_template'],
                    'template_args'  => $ctx['template_args'],
                    'start_index'    => $ctx['config']['pagination_type'] === 'pagination'
                        ? (($ctx['current_page'] - 1) * $ctx['current_per_page'])
                        : 0,
                    'group_filter'   => ! empty($ctx['config']['group_on_all']) ? ($ctx['archive_filters'][0] ?? []) : [],
                    'filter_values'  => $ctx['current_filters'],
                ]
            ); ?>
        </div>
        <?php
    }
}

if (! function_exists('sp_archive_pagination')) {
    /**
     * Render archive pagination.
     * Requires sp_archive_setup() to have been called first.
     */
    function sp_archive_pagination(string $class = ''): void
    {
        $ctx = _sp_archive_ctx();
        if (! $ctx) {
            return;
        }

        ob_start();
        sp_archive_render_pagination([
            'current'          => $ctx['current_page'],
            'total'            => $ctx['total_pages'],
            'mode'             => $ctx['config']['pagination_type'],
            'load_more_label'  => $ctx['config']['load_more_label'],
            'action'           => $ctx['action'],
            'page_arg'         => $ctx['page_arg'],
            'url_page_arg'     => $ctx['url_page_arg'],
            'pagination_data'  => $ctx['pagination_data'],
        ]);
        $pagination = trim((string) ob_get_clean());

        echo '<div class="' . esc_attr($class) . '" data-sp-archive-pagination>' . $pagination . '</div>';
    }
}

// ---------------------------------------------------------------------------
// Config token registry (transient-based — AJAX handler reads by token)
// ---------------------------------------------------------------------------

if (! function_exists('sp_archive_register_config')) {
    /**
     * Store an archive config in a transient and return a deterministic token.
     * Same config = same token (idempotent, no duplicate transients).
     */
    function sp_archive_register_config(array $config): string
    {
        $json  = wp_json_encode($config) ?: '{}';
        $token = substr(wp_hash($json), 0, 20);

        set_transient('sp_arc_' . $token, $config, DAY_IN_SECONDS);

        return $token;
    }
}

if (! function_exists('sp_archive_get_config')) {
    /** Retrieve a previously registered archive config by token. */
    function sp_archive_get_config(string $token): ?array
    {
        $token  = sanitize_key($token);
        $config = $token ? get_transient('sp_arc_' . $token) : null;

        return is_array($config) ? $config : null;
    }
}

// ---------------------------------------------------------------------------
// Dedicated AJAX handler: sp_archive_query
// All sensitive config (post_type, template, filters) comes from the transient.
// Client sends only: archive_token, paged, sort, and filter values by taxonomy slug.
// ---------------------------------------------------------------------------

if (! function_exists('sp_archive_ajax_query')) {
    function sp_archive_ajax_query(): void
    {
        nocache_headers();

        $source = wp_unslash($_POST);
        $nonce  = (string) ($source['nonce'] ?? '');

        $nonce_action = sanitize_key((string) apply_filters('sp_archive_nonce_action', 'ajax_global')) ?: 'ajax_global';
        if (! wp_verify_nonce($nonce, $nonce_action)) {
            wp_send_json_error(['code' => 'invalid_nonce']);
        }

        $token  = sanitize_key($source['archive_token'] ?? '');
        $config = sp_archive_get_config($token);

        if (! $config) {
            // Transient expired — tell JS to reload the page to get a fresh token
            wp_send_json_error(['code' => 'config_expired', 'reload' => true]);
        }

        $language = sanitize_key((string) ($config['lang'] ?? ''));
        if ($language === '' && function_exists('pll_languages_list')) {
            $referer_path = trim((string) wp_parse_url((string) wp_get_referer(), PHP_URL_PATH), '/');
            $path_language = sanitize_key((string) strtok($referer_path, '/'));
            $languages = array_map('sanitize_key', (array) pll_languages_list(['fields' => 'slug']));
            $language = in_array($path_language, $languages, true)
                ? $path_language
                : (function_exists('pll_default_language') ? sanitize_key((string) pll_default_language('slug')) : '');
        }

        if ($language !== '' && function_exists('PLL')) {
            $language_object = PLL()->model->get_language($language);
            if ($language_object) {
                PLL()->curlang = $language_object;
                if (! empty($language_object->locale)) {
                    switch_to_locale((string) $language_object->locale);
                }
            }
        } elseif ($language !== '' && has_action('wpml_switch_language')) {
            do_action('wpml_switch_language', $language);
        }

        // All sensitive values come from server-side config
        $post_type        = $config['post_type'];
        $card_template    = $config['card_template'];
        $template_args    = isset($config['template_args']) && is_array($config['template_args']) ? $config['template_args'] : [];
        $empty_template   = $config['empty_template'] ?? '';
        $archive_filters  = $config['filters'];       // [{name, query_arg, taxonomy}]
        $default_per_page = sp_archive_normalize_per_page($config['per_page']);
        $max_per_page     = (int) apply_filters('sp_archive_ajax_max_per_page', 48, $config);
        $max_per_page     = max(1, min(100, $max_per_page));
        $requested_per_page = sp_archive_normalize_per_page($source['per_page'] ?? $default_per_page, $default_per_page);
        $per_page = $requested_per_page === -1 && $default_per_page === -1
            ? -1
            : min($max_per_page, $requested_per_page > 0 ? $requested_per_page : max(1, $default_per_page));
        $pagination_type  = $config['pagination_type'];
        $load_more_label  = sanitize_text_field((string) ($config['load_more_label'] ?? 'Show More'));
        $action           = sanitize_key((string) ($config['action'] ?? 'sp_archive_query')) ?: 'sp_archive_query';
        $page_arg         = $config['page_arg'];
        $url_page_arg     = $config['url_page_arg'];
        $sort_arg     = $config['sort_arg'] ?? '';
        $default_sort = $config['order_mode'];
        $disable_empty = ! empty($config['disable_empty']);
        $term_scope = sp_archive_normalize_term_scope($config['term_scope'] ?? []);
        $favorite_first = ! empty($config['favorite_first']) || $card_template === 'template_parts/section-archive-blog/card';

        // Client provides: paged, sort (only if sort_arg configured), and filter values
        $paged         = max(1, (int) ($source['paged'] ?? 1));
        $sort          = ($sort_arg && isset($source['sort']))
            ? sp_archive_normalize_sort($source['sort'], $default_sort)
            : $default_sort;
        $filter_values = sp_archive_filter_values($archive_filters, $source);

        // Run query
        $query_data = sp_archive_prepare_query([
            'post_type'       => $post_type,
            'filters'         => $archive_filters,
            'filter_values'   => $filter_values,
            'term_scope'      => $term_scope,
            'per_page'        => $per_page,
            'paged'           => $paged,
            'sort'            => $sort,
            'pagination_mode' => $pagination_type,
            'group_filter'    => ! empty($config['group_on_all']) ? ($archive_filters[0] ?? []) : [],
            'favorite_first'  => $favorite_first,
            'lang'            => $language,
        ]);

        $query        = $query_data['query'];
        $total_pages  = $query_data['total_pages'];
        $current_page = $query_data['current_page'];
        $filter_availability = $disable_empty
            ? sp_archive_filter_availability([
                'post_type'     => $post_type,
                'filters'       => $archive_filters,
                'filter_values' => $filter_values,
                'term_scope'    => $term_scope,
                'sort'          => $sort,
                'favorite_first' => $favorite_first,
                'lang'          => $language,
            ])
            : [];

        // Render cards HTML
        $html = sp_archive_render_cards($query, $card_template, [
            'empty_template' => $empty_template,
            'template_args'  => $template_args,
            'start_index'    => ($current_page - 1) * $per_page,
            'group_filter'   => ! empty($config['group_on_all']) ? ($archive_filters[0] ?? []) : [],
            'filter_values'  => $filter_values,
        ]);

        // Render pagination (embed token so next pagination click works too)
        $pagination = '';

        if ($pagination_type === 'pagination' && $total_pages > 1) {
            $pagination_data = array_merge(
                sp_archive_pagination_data([
                    'post_type'        => $post_type,
                    'template'         => $card_template,
                    'filters'          => $archive_filters,
                    'filter_values'    => $filter_values,
                    'term_scope'       => $term_scope,
                    'per_page'         => $per_page,
                    'load_more_label'  => $load_more_label,
                    'query_arg'        => $page_arg,
                    'url_query_arg'    => $url_page_arg,
                    'sort'             => $sort,
                    'pagination_mode'  => $pagination_type,
                    'favorite_first'   => $favorite_first,
                    'lang'             => $language,
                ]),
                ['archive_token' => $token]  // keep token alive through pagination
            );

            ob_start();
            sp_archive_render_pagination([
                'current'          => $current_page,
                'total'            => $total_pages,
                'mode'             => $pagination_type,
                'load_more_label'  => $load_more_label,
                'action'           => $action,
                'page_arg'         => $page_arg,
                'url_page_arg'     => $url_page_arg,
                'pagination_data'  => $pagination_data,
            ]);
            $pagination = trim((string) ob_get_clean());
        }

        wp_send_json_success([
            'html'         => $html,
            'pagination'   => $pagination,
            'found'        => (int) $query_data['total_found'],
            'max_pages'    => $total_pages,
            'current_page' => $current_page,
            'has_next'     => $current_page < $total_pages,
            'filter_availability' => $filter_availability,
        ]);
    }
}

add_action('wp_ajax_sp_archive_query',        'sp_archive_ajax_query');
add_action('wp_ajax_nopriv_sp_archive_query', 'sp_archive_ajax_query');
