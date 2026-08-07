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
//      // Постов на страницу
//      'per_page'        => 9,
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
//  [php] sp_archive_setup($archive, 'template_parts/section-archive-cases/card', [
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
    function sp_archive_filter_options(string $taxonomy, string $all_label): array {
        $options = ['all' => $all_label];
        if (! taxonomy_exists($taxonomy)) { return $options; }
        $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => true, 'orderby' => 'name', 'order' => 'ASC']);
        if (is_wp_error($terms) || empty($terms)) { return $options; }
        foreach ($terms as $term) {
            if ($term instanceof WP_Term) { $options[$term->slug] = $term->name; }
        }
        return $options;
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
            ];
        }
        return $output;
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
            'sort'          => 'newest',
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

            $terms = get_terms([
                'taxonomy'   => $taxonomy,
                'hide_empty' => true,
                'fields'     => 'id=>slug',
            ]);

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
                    'per_page'      => 1,
                    'paged'         => 1,
                    'sort'          => $args['sort'],
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
        $args      = wp_parse_args($args, ['post_type' => 'post', 'filters' => [], 'filter_values' => [], 'per_page' => 9, 'paged' => 1, 'sort' => 'newest', 'favorite_first' => false]);
        $post_type = sanitize_key((string) $args['post_type']);
        $post_type = post_type_exists($post_type) ? $post_type : 'post';
        $per_page  = max(1, (int) $args['per_page']);
        $paged     = max(1, (int) $args['paged']);
        $sort      = sp_archive_normalize_sort($args['sort']);
        $order     = sp_archive_sort_args($sort);
        $filters   = sp_archive_normalize_filters($args['filters']);
        $values    = is_array($args['filter_values']) ? $args['filter_values'] : [];
        $tax_query = [];
        foreach ($filters as $filter) {
            $terms = sp_archive_normalize_filter_terms($values[$filter['name']] ?? '');
            if (empty($terms)) { continue; }
            $tax_query[] = ['taxonomy' => $filter['taxonomy'], 'field' => $filter['field'], 'terms' => $terms, 'operator' => $filter['operator']];
        }
        if (count($tax_query) > 1) { $tax_query['relation'] = 'AND'; }
        $orderby = $order['orderby'];
        $qa = ['post_type' => $post_type, 'post_status' => 'publish', 'ignore_sticky_posts' => true, 'no_found_rows' => false, 'posts_per_page' => $per_page, 'paged' => $paged];
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
        $args = wp_parse_args($args, ['post_type' => 'post', 'filters' => [], 'filter_values' => [], 'per_page' => 9, 'paged' => 1, 'sort' => 'newest', 'pagination_mode' => 'pagination', 'favorite_first' => false]);
        $mode        = sp_archive_normalize_mode($args['pagination_mode']);
        $per_page    = max(1, (int) $args['per_page']);
        $paged       = max(1, (int) $args['paged']);
        $query_page  = $paged;
        $query_limit = $per_page;
        if (! wp_doing_ajax() && ($mode === 'infinity_scroll' || $mode === 'load_more') && $paged > 1) {
            $query_page  = 1;
            $query_limit = $per_page * $paged;
        }
        $qa           = sp_archive_query_args(['post_type' => $args['post_type'], 'filters' => $args['filters'], 'filter_values' => $args['filter_values'], 'per_page' => $query_limit, 'paged' => $query_page, 'sort' => sp_archive_normalize_sort($args['sort']), 'favorite_first' => ! empty($args['favorite_first'])]);
        $query        = new WP_Query($qa);
        $total_found  = (int) $query->found_posts;
        $total_pages  = max(1, (int) ceil($total_found / $per_page));
        $current_page = max(1, min($paged, $total_pages));
        if ($current_page !== $paged) {
            wp_reset_postdata();
            $query_page  = $current_page;
            $query_limit = $per_page;
            if (! wp_doing_ajax() && ($mode === 'infinity_scroll' || $mode === 'load_more') && $current_page > 1) {
                $query_page  = 1;
                $query_limit = $per_page * $current_page;
            }
            $qa           = sp_archive_query_args(['post_type' => $args['post_type'], 'filters' => $args['filters'], 'filter_values' => $args['filter_values'], 'per_page' => $query_limit, 'paged' => $query_page, 'sort' => sp_archive_normalize_sort($args['sort']), 'favorite_first' => ! empty($args['favorite_first'])]);
            $query        = new WP_Query($qa);
            $total_found  = (int) $query->found_posts;
            $total_pages  = max(1, (int) ceil($total_found / $per_page));
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
        if (strpos($template, 'template_parts/') !== 0 && strpos($template, 'templates/') !== 0) { return ''; }
        return is_readable(THEME_DIR . '/' . $template . '.php') ? $template : '';
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

if (! function_exists('sp_archive_render_cards')) {
    function sp_archive_render_cards(WP_Query $query, string $template, array $args = []): string {
        $template       = sp_archive_sanitize_template($template);
        $empty_template = sp_archive_sanitize_template($args['empty_template'] ?? '');
        $item_args      = isset($args['template_args']) && is_array($args['template_args']) ? $args['template_args'] : [];
        $archive_post_ids = array_map(
            static fn($post): int => $post instanceof WP_Post ? (int) $post->ID : (int) $post,
            is_array($query->posts) ? $query->posts : []
        );
        $archive_loop_index = 0;
        ob_start();
        if ($template !== '' && $query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                echo sp_archive_render_template(
                    $template,
                    array_merge(
                        $item_args,
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

if (! function_exists('sp_archive_pagination_data')) {
    function sp_archive_pagination_data(array $args): array {
        $data = [
            'post_type'        => sanitize_key($args['post_type'] ?? 'post'),
            'template'         => sp_archive_sanitize_template($args['template'] ?? ''),
            'filters'          => sp_archive_normalize_filters($args['filters'] ?? []),
            'filter_values'    => is_array($args['filter_values'] ?? null) ? $args['filter_values'] : [],
            'per_page'         => max(1, (int) ($args['per_page'] ?? 9)),
            'query_arg'        => sanitize_key($args['query_arg']     ?? 'sp_page'),
            'url_query_arg'    => sanitize_key($args['url_query_arg'] ?? 'page'),
            'sort'             => sp_archive_normalize_sort($args['sort'] ?? 'newest'),
            'pagination_mode'  => sp_archive_normalize_mode($args['pagination_mode'] ?? 'pagination'),
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

function sp_archive_builder_defaults(): array
{
    $post_types = array_keys(sp_archive_builder_post_type_choices());

    return [
        'post_type'       => $post_types[0] ?? 'post',
        'filters_enabled' => 0,
        'confirm'         => 0,
        'reset'           => 0,
        'disable_empty'   => 0,
        'filters'         => [],
        'per_page'        => 9,
        'pagination_type' => 'pagination',
        'order_mode'      => 'newest',
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
    $value['per_page']        = max(1, (int) $value['per_page']);

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
        } elseif (! empty($filter['enabled'])) {
            $tax = sanitize_key($taxonomy);
            $ui  = sanitize_key($filter['ui'] ?? 'buttons');
        } else {
            continue;
        }

        if ($tax === '') {
            continue;
        }

        $filters[] = [
            'taxonomy' => $tax,
            'ui'       => in_array($ui, ['buttons', 'select', 'multiselect', 'radio', 'checkbox'], true) ? $ui : 'buttons',
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

    if (! empty($config['filters_enabled']) && $selected_terms) {
        $tax_query = [];

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

        if ($tax_query) {
            $args['tax_query'] = count($tax_query) > 1
                ? array_merge(['relation' => 'AND'], $tax_query)
                : $tax_query;
        }
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

        $terms = get_terms([
            'taxonomy'   => $taxonomy,
            'hide_empty' => true,
        ]);

        if (is_wp_error($terms) || empty($terms)) {
            continue;
        }

        $output[] = [
            'taxonomy' => $taxonomy,
            'label'    => get_taxonomy($taxonomy)->labels->name ?? $taxonomy,
            'ui'       => $filter['ui'],
            'terms'    => $terms,
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
        'disableEmpty'   => (bool) $config['disable_empty'],
        'disable_empty'  => (bool) $config['disable_empty'],
        'perPage'        => $config['per_page'],
        'paginationType' => $config['pagination_type'],
        'orderMode'      => $config['order_mode'],
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
                'filters'         => [],
                'per_page'        => 9,
                'pagination_type' => 'pagination',
                'order_mode'      => 'newest',
            ];
        }

        public function render_field_settings(array $field): void
        {
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

            // Output the filters setting manually using ACF 6 flexbox structure
            $taxonomy_map   = sp_archive_builder_taxonomies_by_post_type();
            $filters        = $field['filters'] ?? [];
            $current_type   = $field['post_type'] ?? 'post';
            
            // Map current filters to check if enabled and get their UI type
            $enabled_filters = [];
            foreach ($filters as $tax => $filter) {
                if (is_array($filter) && isset($filter['taxonomy'])) {
                    $enabled_filters[$filter['taxonomy']] = $filter['ui'] ?? 'buttons';
                } elseif (is_array($filter) && ! empty($filter['enabled'])) {
                    $enabled_filters[$tax] = $filter['ui'] ?? 'buttons';
                }
            }

            $taxonomies_for = $taxonomy_map[$current_type] ?? [];
            $field_prefix   = ! empty($field['prefix']) ? $field['prefix'] : "acf_fields[{$field['key']}]";
            ?>
            <div class="acf-field acf-field-setting-filters" data-name="filters" data-setting="archive_builder">
                <div class="acf-label">
                    <label><?php _e('Filters', 'acf'); ?></label>
                    <p class="description"><?php _e('Select taxonomies and their UI representation.', 'acf'); ?></p>
                </div>
                <div class="acf-input">
                    <div
                        class="sp-archive-builder-settings sp-admin-component sp-acf-component<?php echo empty($taxonomies_for) ? ' is-empty' : ''; ?>"
                        data-sp-admin-component
                        data-taxonomies="<?php echo esc_attr(wp_json_encode($taxonomy_map)); ?>"
                        data-field-key="<?php echo esc_attr($field['key']); ?>"
                        data-field-prefix="<?php echo esc_attr($field_prefix); ?>"
                        data-empty-label="<?php echo esc_attr__('No taxonomies available for this post type.', 'acf'); ?>"
                        data-error-label="<?php echo esc_attr__('Taxonomy options could not be loaded.', 'acf'); ?>"
                        data-display-label="<?php echo esc_attr__('Display as', 'acf'); ?>"
                        data-ui-labels="<?php echo esc_attr(wp_json_encode([
                            'buttons'     => __('Buttons', 'acf'),
                            'select'      => __('Select', 'acf'),
                            'multiselect' => __('Multi-select', 'acf'),
                            'radio'       => __('Radio', 'acf'),
                            'checkbox'    => __('Checkbox', 'acf'),
                        ])); ?>"
                    >
                        <div class="sp-archive-builder-settings__list" data-sp-archive-settings-filter-list>
                            <?php if (! empty($taxonomies_for)) : ?>
                                <?php foreach ($taxonomies_for as $tax_name => $tax_label) : ?>
                                    <?php 
                                    $enabled = isset($enabled_filters[$tax_name]);
                                    $ui      = $enabled_filters[$tax_name] ?? 'buttons';
                                    $this->render_settings_taxonomy_row($field_prefix, $tax_name, $tax_label, $enabled, $ui); 
                                    ?>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <div class="sp-archive-builder-settings__empty sp-acf-status is-empty" role="status" aria-live="polite">
                                    <span class="dashicons dashicons-filter" aria-hidden="true"></span>
                                    <span><?php esc_html_e('No taxonomies available for this post type.', 'acf'); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        }

        private function render_settings_taxonomy_row(string $field_prefix, string $tax_name, string $tax_label, bool $enabled, string $ui): void
        {
            $input_prefix = "{$field_prefix}[filters][{$tax_name}]";
            $ui_choices   = [
                'buttons'     => __('Buttons', 'acf'),
                'select'      => __('Select', 'acf'),
                'multiselect' => __('Multi-select', 'acf'),
                'radio'       => __('Radio', 'acf'),
                'checkbox'    => __('Checkbox', 'acf'),
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
                    <div class="sp-archive-builder-settings__tax-ui" role="radiogroup" aria-label="<?php echo esc_attr($tax_label); ?>" aria-disabled="<?php echo $enabled ? 'false' : 'true'; ?>">
                        <?php foreach ($ui_choices as $ui_value => $ui_label) : ?>
                            <label class="sp-archive-builder-settings__ui-btn">
                                <input type="radio" name="<?php echo esc_attr($input_prefix); ?>[ui]" value="<?php echo esc_attr($ui_value); ?>" <?php checked($ui, $ui_value); ?><?php echo $enabled ? '' : ' tabindex="-1"'; ?>>
                                <span><?php echo esc_html($ui_label); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php
        }



        public function render_field(array $field): void
        {
            $value = sp_archive_builder_normalize($field['value'] ?? []);
            $name  = $field['name'];

            $per_page_choices = [
                3  => '3 ' . __('posts', 'acf'),
                4  => '4 ' . __('posts', 'acf'),
                6  => '6 ' . __('posts', 'acf'),
                8  => '8 ' . __('posts', 'acf'),
                9  => '9 ' . __('posts', 'acf'),
                12 => '12 ' . __('posts', 'acf'),
                15 => '15 ' . __('posts', 'acf'),
                16 => '16 ' . __('posts', 'acf'),
                20 => '20 ' . __('posts', 'acf'),
                24 => '24 ' . __('posts', 'acf'),
                30 => '30 ' . __('posts', 'acf'),
                -1 => __('Show all', 'acf'),
            ];
            $post_type = $field['post_type'] ?? 'post';
            $choices = sp_archive_builder_post_type_choices();
            $post_type_label = $choices[$post_type] ?? $post_type;
            ?>
            <div class="sp-archive-builder-card sp-admin-component sp-acf-component" data-sp-admin-component>
                <div class="sp-archive-builder-card__header">
                    <span class="dashicons dashicons-layout"></span>
                    <strong><?php printf(esc_html__('Archive Settings (%s)', 'acf'), esc_html($post_type_label)); ?></strong>
                </div>

                <div class="sp-archive-builder-card__grid">
                    <!-- Posts per page dropdown -->
                    <div class="sp-archive-builder-card__field">
                        <label for="<?php echo esc_attr($name); ?>-per-page"><?php esc_html_e('Number of posts', 'acf'); ?></label>
                        <select id="<?php echo esc_attr($name); ?>-per-page" name="<?php echo esc_attr($name); ?>[per_page]">
                            <?php foreach ($per_page_choices as $val => $label) : ?>
                                <option value="<?php echo esc_attr((string)$val); ?>" <?php selected($value['per_page'], $val); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Pagination Type Segmented Control -->
                    <div class="sp-archive-builder-card__field">
                        <label><?php esc_html_e('Pagination type', 'acf'); ?></label>
                        <div class="sp-archive-builder-card__segmented" role="radiogroup" aria-label="<?php echo esc_attr__('Pagination type', 'acf'); ?>">
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

            // Only save the editor-level choices to post meta
            return [
                'per_page'        => max(-1, (int) ($value['per_page'] ?? 9)),
                'pagination_type' => in_array($value['pagination_type'] ?? '', ['pagination', 'load_more', 'infinity_scroll'], true) ? $value['pagination_type'] : 'pagination',
                'order_mode'      => in_array($value['order_mode'] ?? '', ['newest', 'oldest', 'az', 'za', 'menu_order'], true) ? $value['order_mode'] : 'newest',
            ];
        }

        public function format_value($value, $post_id, array $field)
        {
            $value = is_array($value) ? $value : [];

            // Merge field-level structure settings
            $value['post_type']       = $field['post_type'] ?? 'post';
            $value['filters_enabled'] = ! empty($field['filters_enabled']) ? 1 : 0;
            $value['confirm']         = ! empty($field['confirm']) ? 1 : 0;
            $value['reset']           = ! empty($field['reset']) ? 1 : 0;
            $value['disable_empty']   = ! empty($field['disable_empty']) ? 1 : 0;
            $value['filters']         = $field['filters'] ?? [];

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
.sp-archive-builder-card,
.sp-archive-builder-settings {
    color: var(--sp-acf-text);
    container-type: inline-size;
    min-width: 0;
}

.sp-archive-builder-card {
    background: var(--sp-acf-surface);
    border: 1px solid var(--sp-acf-border);
    border-radius: var(--sp-acf-radius);
    box-shadow: var(--sp-acf-shadow);
    overflow: hidden;
}

.sp-archive-builder-card__header {
    align-items: center;
    background: var(--sp-acf-surface-soft);
    border-bottom: 1px solid var(--sp-acf-border);
    display: flex;
    gap: 7px;
    min-height: 46px;
    padding: 12px 16px;
}

.sp-archive-builder-card__header .dashicons {
    color: var(--sp-acf-accent);
    flex: 0 0 18px;
    font-size: 18px;
    height: 18px;
    width: 18px;
}

.sp-archive-builder-card__header strong {
    color: var(--sp-acf-text);
    font-size: 13px;
    font-weight: 700;
}

.sp-archive-builder-card__grid {
    align-items: end;
    display: grid;
    gap: 16px;
    grid-template-columns: 200px minmax(280px, 1fr) 200px;
    padding: 16px;
}

.sp-archive-builder-card__field {
    display: grid;
    gap: 6px;
    min-width: 0;
}

.sp-archive-builder-card__field > label {
    color: var(--sp-acf-text);
    display: block !important;
    font-size: 13px;
    font-weight: 600;
    line-height: 1.4 !important;
    margin: 0 !important;
    padding: 0 !important;
}

.sp-archive-builder-card select {
    background-color: var(--sp-acf-input-bg);
    border: 1px solid var(--sp-acf-border-strong);
    border-radius: var(--sp-acf-radius);
    color: var(--sp-acf-text);
    font-size: 13px;
    height: 40px !important;
    line-height: 1 !important;
    padding: 0 30px 0 10px;
    transition: background var(--sp-acf-transition), border-color var(--sp-acf-transition), box-shadow var(--sp-acf-transition);
    width: 100%;
}

.sp-archive-builder-card select:hover:not(:disabled) {
    border-color: var(--sp-acf-accent-bright);
}

.sp-archive-builder-card select:focus-visible {
    border-color: var(--sp-acf-accent);
    box-shadow: var(--sp-acf-focus);
    outline: 0;
}

.sp-archive-builder-card__segmented,
.sp-archive-builder-settings__tax-ui {
    background: var(--sp-acf-segment-bg);
    border: 1px solid var(--sp-acf-border);
    border-radius: var(--sp-acf-radius);
    display: grid;
    gap: 2px;
    padding: 3px;
}

.sp-archive-builder-card__segmented {
    grid-template-columns: repeat(3, minmax(0, 1fr));
    min-height: 40px;
}

.sp-archive-builder-card__segment,
.sp-archive-builder-settings__ui-btn {
    cursor: pointer;
    display: flex;
    min-width: 0;
    position: relative;
}

.sp-archive-builder-card__segment input,
.sp-archive-builder-settings__ui-btn input {
    clip: rect(0 0 0 0);
    clip-path: inset(50%);
    height: 1px;
    overflow: hidden;
    position: absolute;
    white-space: nowrap;
    width: 1px;
}

.sp-archive-builder-card__segment span,
.sp-archive-builder-settings__ui-btn span {
    align-items: center;
    background: transparent;
    border: 1px solid transparent;
    border-radius: var(--sp-acf-radius);
    color: var(--sp-acf-text-muted);
    display: flex;
    flex: 1;
    font-weight: 600;
    justify-content: center;
    min-width: 0;
    text-align: center;
    transition: background var(--sp-acf-transition), border-color var(--sp-acf-transition), box-shadow var(--sp-acf-transition), color var(--sp-acf-transition), transform var(--sp-acf-transition);
    white-space: nowrap;
}

.sp-archive-builder-card__segment span {
    font-size: 13px;
    min-height: 32px;
    padding: 0 10px;
}

.sp-archive-builder-card__segment:hover input:not(:disabled) + span,
.sp-archive-builder-settings__tax-ui[aria-disabled="false"] .sp-archive-builder-settings__ui-btn:hover span {
    background: var(--sp-acf-accent-soft);
    color: var(--sp-acf-accent-hover);
}

.sp-archive-builder-card__segment input:active + span,
.sp-archive-builder-settings__ui-btn input:active + span {
    transform: translateY(1px);
}

.sp-archive-builder-card__segment input:checked + span,
.sp-archive-builder-settings__ui-btn input:checked + span {
    background: var(--sp-acf-surface);
    border-color: var(--sp-acf-border-strong);
    box-shadow: var(--sp-acf-shadow), inset 0 -2px 0 var(--sp-acf-accent);
    color: var(--sp-acf-accent);
}

.sp-archive-builder-card__segment input:focus-visible + span,
.sp-archive-builder-settings__ui-btn input:focus-visible + span {
    border-color: var(--sp-acf-accent);
    box-shadow: var(--sp-acf-focus);
    outline: 0;
}

.sp-archive-builder-card__segment input:disabled + span {
    background: var(--sp-acf-surface-soft);
    color: var(--sp-acf-text-subtle);
    cursor: not-allowed;
    opacity: .68;
}

.sp-archive-builder-settings__list {
    display: grid;
    gap: 8px;
    margin-top: 8px;
}

.sp-archive-builder-settings__tax-row {
    align-items: center;
    background: var(--sp-acf-surface);
    border: 1px solid var(--sp-acf-border);
    border-radius: var(--sp-acf-radius);
    display: flex;
    gap: 16px;
    justify-content: space-between;
    min-height: 74px;
    padding: 12px 14px;
    transition: background var(--sp-acf-transition), border-color var(--sp-acf-transition), box-shadow var(--sp-acf-transition);
}

.sp-archive-builder-settings__tax-row:hover {
    background: var(--sp-acf-surface-soft);
    border-color: var(--sp-acf-border-strong);
}

.sp-archive-builder-settings__tax-row:focus-within {
    border-color: var(--sp-acf-accent);
    box-shadow: var(--sp-acf-focus);
}

.sp-archive-builder-settings__tax-row.is-active {
    background: var(--sp-acf-accent-soft);
    border-color: var(--sp-acf-accent);
    box-shadow: inset 3px 0 0 var(--sp-acf-accent);
}

.sp-archive-builder-settings__tax-row.is-active:focus-within {
    box-shadow: var(--sp-acf-focus), inset 3px 0 0 var(--sp-acf-accent);
}

.sp-archive-builder-settings__tax-main {
    flex: 1 1 auto;
    min-width: 0;
}

.sp-archive-builder-settings__tax-label {
    align-items: center;
    cursor: pointer;
    display: flex;
    gap: 12px;
    margin: 0 !important;
    min-width: 0;
    padding: 0 !important;
}

.sp-archive-builder-settings__checkbox {
    accent-color: var(--sp-acf-accent);
    flex: 0 0 auto;
    margin: 0 !important;
}

.sp-archive-builder-settings__checkbox:focus-visible {
    box-shadow: var(--sp-acf-focus);
    outline: 0;
}

.sp-archive-builder-settings__tax-copy {
    display: grid;
    gap: 3px;
    min-width: 0;
}

.sp-archive-builder-settings__tax-name {
    color: var(--sp-acf-text);
    font-size: 14px;
    font-weight: 700;
    line-height: 1.3;
}

.sp-archive-builder-settings__tax-slug {
    color: var(--sp-acf-text-subtle);
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    font-size: 12px;
    line-height: 1.3;
}

.sp-archive-builder-settings__tax-side {
    align-items: end;
    display: grid;
    flex: 0 0 auto;
    gap: 6px;
    min-width: min(430px, 58%);
}

.sp-archive-builder-settings__tax-side-label {
    color: var(--sp-acf-text-muted);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .04em;
    text-transform: uppercase;
}

.sp-archive-builder-settings__tax-ui {
    grid-template-columns: repeat(5, minmax(0, 1fr));
    min-width: 0;
    transition: opacity var(--sp-acf-transition);
    width: 100%;
}

.sp-archive-builder-settings__tax-ui[aria-disabled="true"] {
    cursor: not-allowed;
    opacity: .52;
    pointer-events: none;
}

.sp-archive-builder-settings__ui-btn span {
    font-size: 12px;
    min-height: 30px;
    padding: 0 8px;
}

.sp-archive-builder-settings__empty {
    align-items: center;
    background: var(--sp-acf-surface-soft);
    border: 1px dashed var(--sp-acf-border-strong);
    border-radius: var(--sp-acf-radius);
    color: var(--sp-acf-text-muted);
    display: flex;
    gap: 8px;
    justify-content: center;
    min-height: 78px;
    padding: 14px;
    text-align: center;
}

.sp-archive-builder-settings__empty .dashicons {
    color: var(--sp-acf-text-subtle);
}

.sp-archive-builder-settings__empty.is-error {
    background: rgb(231 76 60 / 6%);
    border-color: var(--sp-acf-error);
    color: var(--sp-acf-error);
}

@container (max-width: 760px) {
    .sp-archive-builder-card__grid {
        grid-template-columns: 1fr;
    }

    .sp-archive-builder-settings__tax-row {
        align-items: stretch;
        flex-direction: column;
    }

    .sp-archive-builder-settings__tax-side {
        align-items: stretch;
        min-width: 0;
        width: 100%;
    }
}

@container (max-width: 520px) {
    .sp-archive-builder-card__segmented,
    .sp-archive-builder-settings__tax-ui {
        grid-template-columns: 1fr;
    }

    .sp-archive-builder-card__segment span,
    .sp-archive-builder-settings__ui-btn span {
        min-height: var(--sp-acf-control-height);
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
            return null;
        }
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[character];
        });
    }

    function labels($settings) {
        var uiLabels = {};

        try {
            uiLabels = JSON.parse($settings.attr('data-ui-labels') || '{}');
        } catch (error) {
            uiLabels = {};
        }

        return {
            empty: $settings.attr('data-empty-label') || 'No taxonomies available for this post type.',
            error: $settings.attr('data-error-label') || 'Taxonomy options could not be loaded.',
            display: $settings.attr('data-display-label') || 'Display as',
            ui: $.extend({
                buttons: 'Buttons',
                select: 'Select',
                multiselect: 'Multi-select',
                radio: 'Radio',
                checkbox: 'Checkbox'
            }, uiLabels)
        };
    }

    function renderStatus($settings, state, message) {
        var isError = state === 'error';
        var $status = $('<div>', {
            'class': 'sp-archive-builder-settings__empty sp-acf-status ' + (isError ? 'is-error' : 'is-empty'),
            role: 'status',
            'aria-live': 'polite'
        });

        $status
            .append($('<span>', {
                'class': 'dashicons ' + (isError ? 'dashicons-warning' : 'dashicons-filter'),
                'aria-hidden': 'true'
            }))
            .append($('<span>').text(message));

        $settings
            .removeClass('is-empty is-error')
            .addClass(isError ? 'is-error' : 'is-empty')
            .find('[data-sp-archive-settings-filter-list]')
            .empty()
            .append($status);
    }

    function buildTaxonomyRow(fieldPrefix, taxName, taxLabel, enabled, ui, copy) {
        var inputPrefix = fieldPrefix + '[filters][' + taxName + ']';
        var activeClass = enabled ? ' is-active' : '';
        var checkedEnabled = enabled ? ' checked' : '';
        var tabIndex = enabled ? '' : ' tabindex="-1"';
        var modes = [
            { value: 'buttons', label: copy.ui.buttons },
            { value: 'select', label: copy.ui.select },
            { value: 'multiselect', label: copy.ui.multiselect },
            { value: 'radio', label: copy.ui.radio },
            { value: 'checkbox', label: copy.ui.checkbox }
        ];
        var uiMarkup = '';

        modes.forEach(function (mode) {
            var checked = ui === mode.value ? ' checked' : '';
            uiMarkup += [
                '      <label class="sp-archive-builder-settings__ui-btn">',
                '        <input type="radio" name="' + escapeHtml(inputPrefix) + '[ui]" value="' + escapeHtml(mode.value) + '"' + checked + tabIndex + '>',
                '        <span>' + escapeHtml(mode.label) + '</span>',
                '      </label>'
            ].join('');
        });

        return [
            '<div class="sp-archive-builder-settings__tax-row' + activeClass + '" data-taxonomy="' + escapeHtml(taxName) + '">',
            '  <div class="sp-archive-builder-settings__tax-main">',
            '    <label class="sp-archive-builder-settings__tax-label">',
            '      <input type="checkbox" name="' + escapeHtml(inputPrefix) + '[enabled]" value="1"' + checkedEnabled + ' class="sp-archive-builder-settings__checkbox">',
            '      <span class="sp-archive-builder-settings__tax-copy">',
            '        <span class="sp-archive-builder-settings__tax-name">' + escapeHtml(taxLabel) + '</span>',
            '        <span class="sp-archive-builder-settings__tax-slug">' + escapeHtml(taxName) + '</span>',
            '      </span>',
            '    </label>',
            '  </div>',
            '  <div class="sp-archive-builder-settings__tax-side">',
            '    <span class="sp-archive-builder-settings__tax-side-label">' + escapeHtml(copy.display) + '</span>',
            '    <div class="sp-archive-builder-settings__tax-ui" role="radiogroup" aria-label="' + escapeHtml(taxLabel) + '" aria-disabled="' + (enabled ? 'false' : 'true') + '">',
                     uiMarkup,
            '    </div>',
            '  </div>',
            '</div>'
        ].join('');
    }

    function syncTaxonomyOptions($settings) {
        var $row = $settings.closest('.acf-field-settings');
        var $postTypeSelect = $row.find('.acf-field-setting-post_type select');
        var postType = $postTypeSelect.val() || 'post';
        
        var postTypeName = $postTypeSelect.attr('name');
        var fieldPrefix = $settings.attr('data-field-prefix') || 'acf_fields[' + ($settings.attr('data-field-key') || 'field_temp') + ']';
        if (postTypeName) {
            fieldPrefix = postTypeName.replace(/\[post_type\]$/, '');
        }
        
        var taxonomyMap = taxonomies($settings);
        var copy = labels($settings);
        var $list = $settings.find('[data-sp-archive-settings-filter-list]');

        if (taxonomyMap === null) {
            renderStatus($settings, 'error', copy.error);
            return;
        }

        var map = taxonomyMap[postType] || {};
        
        // Save current user choices to preserve selections when toggling post types
        var currentValues = {};
        $list.find('.sp-archive-builder-settings__tax-row').each(function () {
            var $taxRow = $(this);
            var tax = $taxRow.attr('data-taxonomy');
            var enabled = $taxRow.find('.sp-archive-builder-settings__checkbox').is(':checked');
            var ui = $taxRow.find('input[name$="[ui]"]:checked').val() || 'buttons';
            currentValues[tax] = { enabled: enabled, ui: ui };
        });

        var html = '';
        var taxNames = Object.keys(map);
        if (taxNames.length > 0) {
            taxNames.forEach(function (taxonomy) {
                var existing = currentValues[taxonomy] || {};
                var enabled = existing.enabled || false;
                var ui = existing.ui || 'buttons';
                html += buildTaxonomyRow(fieldPrefix, taxonomy, map[taxonomy], enabled, ui, copy);
            });
        } else {
            renderStatus($settings, 'empty', copy.empty);
            return;
        }

        $settings.removeClass('is-empty is-error');
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

    $(document).on('change', '.acf-field-setting-post_type select', function () {
        var $row = $(this).closest('.acf-field-settings');
        var $settings = $row.find('.sp-archive-builder-settings');
        if ($settings.length) {
            syncTaxonomyOptions($settings);
        }
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
        var enabled = $cb.is(':checked');
        var $taxUi = $row.find('.sp-archive-builder-settings__tax-ui');

        $row.toggleClass('is-active', enabled);
        $taxUi.attr('aria-disabled', enabled ? 'false' : 'true');
        $taxUi.find('input[type="radio"]').attr('tabindex', enabled ? null : '-1');
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
        $options   = sp_archive_filter_options($taxonomy, $all_label);
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
                get_template_part('templates/ui/filter-radio', null, $common);
                break;
            case 'checkbox':
                get_template_part('templates/ui/filter-checkbox', null, $common);
                break;
            case 'buttons':
                get_template_part('templates/ui/filter-buttons', null, $common);
                break;
            case 'multiselect':
                get_template_part('templates/ui/select', null, array_merge($common, [
                    'placeholder' => $all_label,
                    'mode'        => 'multiple',
                ]));
                break;
            case 'select':
            default:
                get_template_part('templates/ui/select', null, array_merge($common, [
                    'placeholder' => $all_label,
                    'mode'        => 'single',
                ]));
                break;
        }
    }
}

/**
 * Render pagination (wraps templates/ui/pagination).
 *
 * @param  array $args {
 *   'current'          int
 *   'total'            int
 *   'mode'             string  'pagination'|'load_more'|'infinity_scroll'
 *   'action'           string
 *   'page_arg'         string
 *   'url_page_arg'     string
 *   'pagination_data'  array   Data blob embedded in <script> for AJAX pagination
 * }
 */
if (! function_exists('sp_archive_render_pagination')) {
    function sp_archive_render_pagination(array $args): void
    {
        get_template_part('templates/ui/pagination', null, [
            'current'       => (int) ($args['current']   ?? 1),
            'total'         => (int) ($args['total']     ?? 1),
            'mode'          => $args['mode']             ?? 'pagination',
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
 * @param  string $card_template Path to card template, e.g. 'template_parts/section-archive-cases/card'.
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
            ];
        }

        $current_filters = sp_archive_filter_values($archive_filters, wp_unslash($_GET));

        $default_sort = $config['order_mode'];
        $current_sort = isset($_GET[$sort_arg]) && $sort_arg !== ''
            ? sp_archive_normalize_sort(wp_unslash($_GET[$sort_arg]), $default_sort)
            : $default_sort;
        $current_per_page = isset($_GET[$per_page_arg])
            ? max(1, (int) wp_unslash($_GET[$per_page_arg]))
            : (int) $config['per_page'];

        $paged = sp_archive_current_page($page_arg, $url_page_arg);

        $query_data = sp_archive_prepare_query([
            'post_type'       => $config['post_type'],
            'filters'         => $archive_filters,
            'filter_values'   => $current_filters,
            'per_page'        => $current_per_page,
            'paged'           => $paged,
            'sort'            => $current_sort,
            'pagination_mode' => $config['pagination_type'],
            'favorite_first'   => $favorite_first,
        ]);

        $filter_availability = ! empty($config['disable_empty'])
            ? sp_archive_filter_availability([
                'post_type'     => $config['post_type'],
                'filters'       => $archive_filters,
                'filter_values' => $current_filters,
                'sort'          => $current_sort,
            ])
            : [];

        $archive_token = sp_archive_register_config([
            'post_type'        => $config['post_type'],
            'per_page'         => $config['per_page'],
            'pagination_type'  => $config['pagination_type'],
            'order_mode'       => $config['order_mode'],
            'confirm'          => $config['confirm'],
            'reset'            => $config['reset'],
            'disable_empty'    => $config['disable_empty'],
            'filters'          => $archive_filters,
            'card_template'    => $card_template,
            'empty_template'   => $empty_template,
            'page_arg'         => $page_arg,
            'url_page_arg'     => $url_page_arg,
            'sort_arg'         => $sort_arg,
            'per_page_arg'     => $per_page_arg,
            'favorite_first'   => $favorite_first,
        ]);

        $pagination_data = array_merge(
            sp_archive_pagination_data([
                'post_type'        => $config['post_type'],
                'template'         => $card_template,
                'filters'          => $archive_filters,
                'filter_values'    => $current_filters,
                'per_page'         => $current_per_page,
                'query_arg'        => $page_arg,
                'url_query_arg'    => $url_page_arg,
                'sort'             => $current_sort,
                'pagination_mode'  => $config['pagination_type'],
            ]),
            ['archive_token' => $archive_token]
        );

        _sp_archive_ctx([
            'config'          => $config,
            'card_template'   => $card_template,
            'empty_template'  => $empty_template,
            'archive_filters' => $archive_filters,
            'current_filters' => $current_filters,
            'filter_availability' => $filter_availability,
            'default_sort'    => $default_sort,
            'current_sort'    => $current_sort,
            'current_per_page' => $current_per_page,
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
            'favorite_first'   => $favorite_first,
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
            'per_page_arg'    => $ctx['per_page_arg'],
            'pagination_mode' => $ctx['config']['pagination_type'],
            'confirm'         => $ctx['confirm'],
            'reset'           => $ctx['reset'],
            'disable_empty'   => $ctx['config']['disable_empty'],
            'favorite_first'  => $ctx['favorite_first'],
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

        foreach ($ctx['config']['filters'] as $filter) {
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

        // Format options for templates/ui/select: [value => label, ...]
        $select_options = [];
        foreach ($options as $opt) {
            $select_options[ $opt['value'] ] = $opt['label'];
        }

        get_template_part('templates/ui/select', null, [
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
            $options = [
                3  => '3',
                6  => '6',
                9  => '9',
                12 => '12',
                15 => '15',
                24 => '24',
            ];
        }

        $select_options = [];
        foreach ($options as $value => $label) {
            $value = max(1, (int) $value);
            if ($value > 0) {
                $select_options[(string) $value] = (string) $label;
            }
        }

        get_template_part('templates/ui/select', null, [
            'name'    => $ctx['per_page_arg'],
            'value'   => (string) $ctx['current_per_page'],
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
            . '<span class="main-button__text">' . esc_html($label) . '</span>'
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
            . '<span class="main-button__text">' . esc_html($label) . '</span>'
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

        $query = $ctx['query'];
        $archive_post_ids = array_map(
            static fn($post): int => $post instanceof WP_Post ? (int) $post->ID : (int) $post,
            is_array($query->posts) ? $query->posts : []
        );
        $archive_loop_index = 0;
        ?>
        <div class="<?= esc_attr($class); ?>"
             data-sp-archive-list
             data-loader="false"
             data-total="<?= esc_attr((string) $ctx['total_found']); ?>">
            <?php if ($query->have_posts()) : ?>
                <?php while ($query->have_posts()) : $query->the_post(); ?>
                    <?php
                    get_template_part(
                        $ctx['card_template'],
                        null,
                        [
                            'post_id'            => (int) get_the_ID(),
                            'archive_loop_index' => $archive_loop_index,
                            'archive_post_ids'   => $archive_post_ids,
                        ]
                    );
                    $archive_loop_index++;
                    ?>
                <?php endwhile; wp_reset_postdata(); ?>
            <?php elseif (! empty($ctx['empty_template'])) : ?>
                <?php get_template_part($ctx['empty_template']); ?>
            <?php endif; ?>
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

        if (! wp_verify_nonce($nonce, 'ajax_global')) {
            wp_send_json_error(['code' => 'invalid_nonce']);
        }

        $token  = sanitize_key($source['archive_token'] ?? '');
        $config = sp_archive_get_config($token);

        if (! $config) {
            // Transient expired — tell JS to reload the page to get a fresh token
            wp_send_json_error(['code' => 'config_expired', 'reload' => true]);
        }

        // All sensitive values come from server-side config
        $post_type        = $config['post_type'];
        $card_template    = $config['card_template'];
        $empty_template   = $config['empty_template'] ?? '';
        $archive_filters  = $config['filters'];       // [{name, query_arg, taxonomy}]
        $default_per_page = (int) $config['per_page'];
        $per_page         = max(1, (int) ($source['per_page'] ?? $default_per_page));
        $pagination_type  = $config['pagination_type'];
        $page_arg         = $config['page_arg'];
        $url_page_arg     = $config['url_page_arg'];
        $sort_arg     = $config['sort_arg'] ?? '';
        $default_sort = $config['order_mode'];
        $disable_empty = ! empty($config['disable_empty']);
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
            'per_page'        => $per_page,
            'paged'           => $paged,
            'sort'            => $sort,
            'pagination_mode' => $pagination_type,
            'favorite_first'   => $favorite_first,
        ]);

        $query        = $query_data['query'];
        $total_pages  = $query_data['total_pages'];
        $current_page = $query_data['current_page'];
        $filter_availability = $disable_empty
            ? sp_archive_filter_availability([
                'post_type'     => $post_type,
                'filters'       => $archive_filters,
                'filter_values' => $filter_values,
                'sort'          => $sort,
            ])
            : [];

        // Render cards HTML
        $html = sp_archive_render_cards($query, $card_template, [
            'empty_template' => $empty_template,
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
                    'per_page'         => $per_page,
                    'query_arg'        => $page_arg,
                    'url_query_arg'    => $url_page_arg,
                    'sort'             => $sort,
                    'pagination_mode'  => $pagination_type,
                ]),
                ['archive_token' => $token]  // keep token alive through pagination
            );

            $pagination = sp_archive_render_template(
                'templates/ui/pagination',
                [
                    'current'       => $current_page,
                    'total'         => $total_pages,
                    'ajax'          => true,
                    'action'        => 'sp_archive_query',
                    'query_arg'     => $page_arg,
                    'url_query_arg' => $url_page_arg,
                    'data'          => $pagination_data,
                ]
            );
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
