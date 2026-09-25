<?php
if (! defined('ABSPATH')) {
    exit;
}

if (! function_exists('sp_interactive_map_points')) {
    function sp_interactive_map_points($value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $points = isset($value['points']) && is_array($value['points']) ? $value['points'] : [];

        return array_values(array_filter($points, static function ($point): bool {
            return is_array($point) && isset($point['x'], $point['y']);
        }));
    }
}

if (! function_exists('display_interactive_map')) {
    function display_interactive_map($value, array $args = []): void
    {
        if (! is_array($value)) {
            return;
        }

        $map_id = absint($value['map_id'] ?? 0);
        $points = sp_interactive_map_points($value);

        if (! $map_id) {
            return;
        }

        $script = __DIR__ . '/assets/map-module.js';
        $script_url = \SoinProduction\Kit\Bootstrapper::pathToUrl($script);
        if ($script_url !== '') {
            wp_enqueue_script('sp-interactive-map', $script_url, [], (string) filemtime($script), true);
        }

        $class = trim('position-[relative] ' . (string) ($args['class'] ?? ''));
        $marker_class = $args['marker_class'] ?? '';
        $zoom_enabled = !empty($value['zoom_enabled']);
        ?>
        <div class="<?php echo esc_attr($class); ?>"<?= $zoom_enabled ? ' data-map-zoom' : ''; ?>>
            <div data-map-zoom-viewport class="[--map-edge-fade:0px] [mask-image:linear-gradient(to_right,transparent,rgba(0,0,0,.15)_calc(var(--map-edge-fade)*.2),rgba(0,0,0,.5)_calc(var(--map-edge-fade)*.5),rgba(0,0,0,.85)_calc(var(--map-edge-fade)*.8),black_var(--map-edge-fade),black_calc(100%_-_var(--map-edge-fade)),rgba(0,0,0,.85)_calc(100%_-_var(--map-edge-fade)*.8),rgba(0,0,0,.5)_calc(100%_-_var(--map-edge-fade)*.5),rgba(0,0,0,.15)_calc(100%_-_var(--map-edge-fade)*.2),transparent),linear-gradient(to_bottom,transparent,rgba(0,0,0,.15)_calc(var(--map-edge-fade)*.2),rgba(0,0,0,.5)_calc(var(--map-edge-fade)*.5),rgba(0,0,0,.85)_calc(var(--map-edge-fade)*.8),black_var(--map-edge-fade),black_calc(100%_-_var(--map-edge-fade)),rgba(0,0,0,.85)_calc(100%_-_var(--map-edge-fade)*.8),rgba(0,0,0,.5)_calc(100%_-_var(--map-edge-fade)*.5),rgba(0,0,0,.15)_calc(100%_-_var(--map-edge-fade)*.2),transparent)] [mask-composite:intersect] <?= $zoom_enabled ? 'overflow-hidden data-[zoomed=true]:cursor-grab data-[zoomed=true]:touch-none data-[dragging=true]:cursor-grabbing data-[dragging=true]:select-none' : ''; ?>">
            <div class="position-[relative] origin-center"<?= $zoom_enabled ? ' data-map-zoom-layer' : ''; ?>>
                <?php echo wp_get_attachment_image($map_id, 'full', false, ['class' => 'd-[block] w-full h-auto! object-contain text-[transparent]', 'loading' => $args['loading'] ?? 'lazy']); ?>

                <?php foreach ($points as $index => $point) :
                        $x = max(0, min(100, (float) ($point['x'] ?? 50)));
                        $y = max(0, min(100, (float) ($point['y'] ?? 50)));
                        // Formatted ACF subfields are keyed by their configured names.
                        $fields = $point['fields'] ?? [];
                        $saved_loops = acf()->loop->loops;
                        $context = $value['_acf_context'] ?? [];
                        acf_add_loop([
                            'name' => ($context['name'] ?? 'map') . '_tooltip_point',
                            'field' => ['type' => 'group', 'sub_fields' => $context['sub_fields'] ?? []],
                            // ACF includes the row index in its formatted-value cache key.
                            'value' => [$index => $point['_acf_raw_fields'] ?? []],
                            'i' => $index,
                            'post_id' => $context['post_id'] ?? get_the_ID(),
                        ]);
                        ob_start();
                        try {
                            get_template_part($args['tooltip_template'] ?? 'php/templates/map-tooltips/default', null, [
                                'point' => $point,
                                'index' => $index,
                                'map' => $value,
                            ]);
                        } finally {
                            $tooltip_html = trim(ob_get_clean());
                            acf()->loop->loops = $saved_loops;
                        }
                        $name = (string) ($fields['title'] ?? '');
                        $image = $fields['image'] ?? null;
                    ?>
                    <div data-map-point class="position-[absolute] left-(--x) top-(--y) -translate-x-1/2 -translate-y-1/2 hover:z-20 focus-within:z-20" style="--x: <?php echo esc_attr((string) $x); ?>%; --y: <?php echo esc_attr((string) $y); ?>%;">
                        <button type="button" data-map-marker class="<?php echo esc_attr($marker_class); ?>" aria-label="<?php echo esc_attr($name ?: sprintf(__('Map point %d', 'acf'), $index + 1)); ?>">
                            <?php if ($image) : ?>
                                <?php display_image($image, 0, 0, 'w-full h-full object-cover'); ?>
                            <?php else : ?>
                                <span class="w-[.6rem] aspect-square rounded-full bg-(--bg-a)"></span>
                            <?php endif; ?>
                        </button>

                        <?php if ($tooltip_html !== '') : ?>
                            <div data-map-tooltip role="region" aria-label="<?= esc_attr($name ?: __('Map details', 'acf')); ?>" class="position-[fixed] z-[100] w-fit max-w-[calc(100vw-2.4rem)] max-h-[calc(100dvh-2.4rem)] rounded-[1.2rem] shadow-lg opacity-0 invisible pointer-events-none data-[open=true]:opacity-100 data-[open=true]:visible data-[open=true]:pointer-events-auto [translate:var(--tooltip-enter-x,0px)_var(--tooltip-enter-y,8px)] data-[open=true]:[translate:0_0] [transition:opacity_.22s_ease,translate_.28s_cubic-bezier(.22,1,.36,1),visibility_0s_.22s] data-[open=true]:[transition-delay:0s] motion-reduce:transition-none motion-reduce:[translate:0_0]">
                                <div class="max-h-[inherit] max-w-[inherit] overflow-auto rounded-[inherit]">
                                    <?php echo $tooltip_html; // Escaping is handled by the tooltip template. ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            </div>
            <?php if ($zoom_enabled) : ?>
                <div class="<?= esc_attr($args['zoom_controls_class'] ?? ''); ?> [&[hidden]]:d-[none]" data-map-zoom-controls hidden>
                    <button type="button" class="position-[relative] d-[block] p-0 shrink-0 w-[clamp(4rem,4vw,6rem)] aspect-square border-0 rounded-[1.2rem] bg-[#f5f5f5] text-[#101010] cursor-pointer transition-colors duration-200 enabled:hover:bg-[#e9e9e9] disabled:opacity-100 disabled:text-[#999] disabled:cursor-default focus-visible:outline-2 focus-visible:outline-offset-2" data-map-zoom-in aria-label="<?= esc_attr__('Zoom in', 'acf'); ?>"><span aria-hidden="true" class="position-[absolute] left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 w-[36%] h-[2px] rounded-[2px] bg-current"></span><span aria-hidden="true" class="position-[absolute] left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 w-[36%] h-[2px] rounded-[2px] bg-current rotate-90"></span></button>
                    <button type="button" class="position-[relative] d-[block] p-0 shrink-0 w-[clamp(4rem,4vw,6rem)] aspect-square border-0 rounded-[1.2rem] bg-[#f5f5f5] text-[#101010] cursor-pointer transition-colors duration-200 enabled:hover:bg-[#e9e9e9] disabled:opacity-100 disabled:text-[#999] disabled:cursor-default focus-visible:outline-2 focus-visible:outline-offset-2" data-map-zoom-out aria-label="<?= esc_attr__('Zoom out', 'acf'); ?>" disabled><span aria-hidden="true" class="position-[absolute] left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 w-[36%] h-[2px] rounded-[2px] bg-current"></span></button>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}

add_action('acf/include_field_types', function (): void {
    if (! class_exists('acf_field') || class_exists('SP_ACF_Field_Interactive_Map', false)) {
        return;
    }

    class SP_ACF_Field_Interactive_Map extends acf_field
    {
        public function initialize(): void
        {
            $this->name     = 'sp_interactive_map';
            $this->label    = __('Interactive Map', 'acf');
            $this->category = 'content';
            $this->defaults = [
                'mode'           => 'multiple',
                'default_map_id' => 0,
                'sub_fields'     => [],
                'layout'         => 'block',
            ];
            $this->add_field_filter('acf/prepare_field_for_export', [$this, 'prepare_field_for_export']);
            $this->add_field_filter('acf/prepare_field_for_import', [$this, 'prepare_field_for_import']);
        }

        public function load_field($field)
        {
            return acf_get_field_type('group')->load_field($field);
        }

        public function prepare_field_for_export($field)
        {
            return acf_get_field_type('group')->prepare_field_for_export($field);
        }

        public function prepare_field_for_import($field)
        {
            return acf_get_field_type('group')->prepare_field_for_import($field);
        }

        public function duplicate_field($field)
        {
            return acf_get_field_type('group')->duplicate_field($field);
        }

        public function delete_field($field)
        {
            acf_get_field_type('group')->delete_field($field);
        }

        private function point_group(array $field, string $point_id): array
        {
            return acf_get_valid_field([
                'key' => $field['key'] . '_point_fields',
                'name' => $field['name'] . '_point_' . sanitize_key($point_id),
                'type' => 'group',
                'layout' => $field['layout'] ?? 'block',
                'sub_fields' => $field['sub_fields'] ?? [],
            ]);
        }

        public function load_value($value, $post_id, $field)
        {
            if (!is_array($value)) return $value;
            foreach ($value['points'] ?? [] as $index => $point) {
                $value['points'][$index]['fields'] = !empty($point['_custom_fields'])
                    ? acf_get_value($post_id, $this->point_group($field, (string) ($point['_id'] ?? $index)))
                    : [];
            }
            return $value;
        }

        public function delete_value($post_id, $meta_key, $field)
        {
            $value = acf_get_metadata($post_id, $meta_key);
            foreach (is_array($value) ? ($value['points'] ?? []) : [] as $index => $point) {
                if (!empty($point['_custom_fields'])) {
                    acf_delete_value($post_id, $this->point_group($field, (string) ($point['_id'] ?? $index)));
                }
            }
        }

        public function render_field_settings($field): void
        {
            acf_render_field_setting($field, [
                'label'        => __('Mode', 'acf'),
                'instructions' => __('Single mode keeps one tooltip open in the editor; clicking the map only updates its coordinates.', 'acf'),
                'type'         => 'radio',
                'name'         => 'mode',
                'choices'      => [
                    'multiple' => __('Multiple points', 'acf'),
                    'single'   => __('Single point', 'acf'),
                ],
                'layout'       => 'horizontal',
            ]);

            acf_get_field_type('group')->render_field_settings($field);

            acf_render_field_setting($field, [
                'label'         => __('Default map image', 'acf'),
                'instructions'  => __('Used when this field has no custom map selected yet.', 'acf'),
                'type'          => 'image',
                'name'          => 'default_map_id',
                'return_format' => 'id',
                'preview_size'  => 'medium',
                'library'       => 'all',
            ]);
        }

        public function render_field($field): void
        {
            $value = is_array($field['value']) ? $field['value'] : [];
            $saved_map_id = $this->attachment_id($value['map_id'] ?? 0);
            $default_map_id = $this->attachment_id($field['default_map_id'] ?? 0);
            $map_id = $saved_map_id ?: $default_map_id;
            $points = isset($value['points']) && is_array($value['points']) ? $value['points'] : [];
            $map_url = $map_id ? wp_get_attachment_image_url($map_id, 'large') : '';
            $mode = ($field['mode'] ?? 'multiple') === 'single' ? 'single' : 'multiple';

            if ($mode === 'single') {
                $points = [reset($points) ?: []];
            }
            ?>
            <div class="sp-interactive-map" data-sp-interactive-map data-sp-map-mode="<?php echo esc_attr($mode); ?>" data-sp-map-name="<?php echo esc_attr($field['name']); ?>">
                <input type="hidden" name="<?php echo esc_attr($field['name']); ?>[map_id]" value="<?php echo esc_attr((string) $saved_map_id); ?>" data-sp-map-id>

                <?php
                acf_render_field_wrap([
                    'key' => '',
                    'name' => 'zoom_enabled',
                    'prefix' => $field['name'],
                    'label' => __('Enable map zoom', 'acf'),
                    'instructions' => __('Show zoom in and zoom out controls for this map on the website.', 'acf'),
                    'type' => 'true_false',
                    'ui' => 1,
                    'value' => !empty($value['zoom_enabled']) ? 1 : 0,
                    'default_value' => 0,
                    'wrapper' => ['class' => 'sp-interactive-map__zoom-setting'],
                ]);
                ?>

                <div class="sp-interactive-map__body">
                    <div class="sp-interactive-map__canvas<?php echo $map_url ? ' is-filled' : ''; ?>" data-sp-map-canvas>
                        <div class="sp-interactive-map__map-actions">
                            <button type="button" class="button sp-interactive-map__icon-action" data-sp-map-select title="<?php echo $map_id ? esc_attr__('Replace map', 'acf') : esc_attr__('Select map', 'acf'); ?>" aria-label="<?php echo $map_id ? esc_attr__('Replace map', 'acf') : esc_attr__('Select map', 'acf'); ?>">
                                <span class="dashicons dashicons-format-image"></span>
                            </button>
                            <button type="button" class="button-link-delete sp-interactive-map__icon-action is-danger<?php echo $map_id ? '' : ' is-hidden'; ?>" data-sp-map-remove title="<?php esc_attr_e('Remove map', 'acf'); ?>" aria-label="<?php esc_attr_e('Remove map', 'acf'); ?>">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        </div>
                        <div class="sp-interactive-map__empty" data-sp-map-empty<?php echo $map_url ? ' hidden' : ''; ?>>
                            <span class="dashicons dashicons-format-image"></span>
                            <span><?php esc_html_e('Select a map image, then click on it to add markers.', 'acf'); ?></span>
                        </div>
                        <div class="sp-interactive-map__stage" data-sp-map-stage<?php echo $map_url ? '' : ' hidden'; ?>>
                            <?php if ($map_url) : ?>
                                <img src="<?php echo esc_url($map_url); ?>" alt="" data-sp-map-image>
                            <?php endif; ?>
                            <div class="sp-interactive-map__loupe" data-sp-map-loupe aria-hidden="true"></div>
                            <div class="sp-interactive-map__markers" data-sp-map-markers></div>
                        </div>
                    </div>

                    <div class="sp-interactive-map__points">
                        <div class="sp-interactive-map__points-head">
                            <strong><?php esc_html_e('Tooltips', 'acf'); ?></strong>
                            <?php if ($mode !== 'single') : ?>
                                <button type="button" class="button" data-sp-map-add-point><?php esc_html_e('Add point', 'acf'); ?></button>
                            <?php endif; ?>
                        </div>

                        <div class="sp-interactive-map__point-list" data-sp-map-points>
                            <?php foreach ($points as $index => $point) : ?>
                                <?php $this->render_point($field['name'], (string) $index, is_array($point) ? $point : [], $field); ?>
                            <?php endforeach; ?>
                        </div>
                        <script type="text/html" data-sp-map-point-template>
                            <?php $this->render_point($field['name'], '__INDEX__', [], $field); ?>
                        </script>
                    </div>
                </div>
            </div>
            <?php
        }

        private function attachment_id($value): int
        {
            if (is_array($value)) {
                return absint($value['ID'] ?? $value['id'] ?? 0);
            }

            return absint($value);
        }

        private function render_point(string $field_name, string $index, array $point, array $field): void
        {
            $x = isset($point['x']) ? (float) $point['x'] : 50;
            $y = isset($point['y']) ? (float) $point['y'] : 50;
            $name = '';
            $locked = ! empty($point['locked']);
            $base = $field_name . '[points][' . $index . ']';
            ?>
            <div class="sp-interactive-map__point" data-sp-map-point data-point-index="<?php echo esc_attr($index); ?>">
                <input type="hidden" name="<?php echo esc_attr($base); ?>[x]" value="<?php echo esc_attr((string) $x); ?>" data-sp-point-x>
                <input type="hidden" name="<?php echo esc_attr($base); ?>[y]" value="<?php echo esc_attr((string) $y); ?>" data-sp-point-y>
                <input type="hidden" name="<?php echo esc_attr($base); ?>[locked]" value="<?php echo $locked ? '1' : '0'; ?>" data-sp-point-locked>

                <div class="sp-interactive-map__point-head">
                    <span data-sp-point-title><?php echo esc_html($name ?: __('New point', 'acf')); ?></span>
                    <div class="sp-interactive-map__point-actions">
                        <button type="button" class="sp-interactive-map__icon-action<?php echo $locked ? ' is-active' : ''; ?>" data-sp-map-lock-point aria-pressed="<?php echo $locked ? 'true' : 'false'; ?>" title="<?php esc_attr_e('Lock position', 'acf'); ?>">
                            <span class="dashicons <?php echo $locked ? 'dashicons-lock' : 'dashicons-unlock'; ?>"></span>
                        </button>
                        <button type="button" class="sp-interactive-map__icon-action is-danger" data-sp-map-remove-point title="<?php esc_attr_e('Remove point', 'acf'); ?>">
                            <span class="dashicons dashicons-trash"></span>
                        </button>
                    </div>
                </div>

                <input type="hidden" name="<?php echo esc_attr($base); ?>[_id]" value="<?php echo esc_attr((string) ($point['_id'] ?? $index)); ?>">
                <div class="sp-interactive-map__custom-fields">
                    <?php
                    $group = $this->point_group($field, (string) ($point['_id'] ?? $index));
                    $group['value'] = $point['fields'] ?? [];
                    $group['label'] = '';
                    // Prepare first: ACF otherwise replaces "fields" with the group key.
                    $group = acf_prepare_field($group);
                    $group['name'] = $base . '[fields]';
                    $group['id'] = acf_idify($group['name']);
                    acf_render_field_wrap($group);
                    ?>
                </div>
            </div>
            <?php
        }

        public function update_value($value, $post_id, $field)
        {
            $value = is_array($value) ? $value : [];
            $points = [];
            $previous = acf_get_metadata($post_id, $field['name']);
            $old_points = is_array($previous) ? ($previous['points'] ?? []) : [];

            foreach ((array) ($value['points'] ?? []) as $point_index => $point) {
                if (($field['mode'] ?? 'multiple') === 'single' && $points) break;
                if (! is_array($point)) {
                    continue;
                }

                $point_id = sanitize_key((string) ($point['_id'] ?? $point_index));
                $old_point = [];
                foreach ($old_points as $old_index => $candidate) {
                    if ((string) ($candidate['_id'] ?? $old_index) === $point_id) {
                        $old_point = $candidate;
                        break;
                    }
                }
                $point = array_replace($old_point, $point);
                $group = $this->point_group($field, $point_id);
                acf_update_value($point['fields'] ?? [], $post_id, $group);

                $x = max(0, min(100, (float) ($point['x'] ?? 50)));
                $y = max(0, min(100, (float) ($point['y'] ?? 50)));
                $locked = ! empty($point['locked']) ? 1 : 0;

                $points[] = [
                    '_id' => $point_id,
                    '_custom_fields' => true,
                    'x'          => round($x, 2),
                    'y'          => round($y, 2),
                    'locked'     => $locked,
                ];
            }

            if (($field['mode'] ?? 'multiple') === 'single') {
                $points = array_slice($points, 0, 1);
            }

            $remaining_ids = array_column($points, '_id');
            foreach ($old_points as $old_index => $old_point) {
                $old_id = (string) ($old_point['_id'] ?? $old_index);
                if (!empty($old_point['_custom_fields']) && !in_array($old_id, $remaining_ids, true)) {
                    acf_delete_value($post_id, $this->point_group($field, $old_id));
                }
            }

            $map_id = $this->attachment_id($value['map_id'] ?? 0);
            $zoom_enabled = !empty($value['zoom_enabled']);
            if (! $map_id && ! $points && ! $zoom_enabled) {
                return '';
            }

            return [
                'map_id'  => $map_id,
                'zoom_enabled' => $zoom_enabled,
                'points'  => $points,
            ];
        }

        public function format_value($value, $post_id, $field)
        {
            $value = is_array($value) ? $value : [];
            $value['zoom_enabled'] = !empty($value['zoom_enabled']);
            $value['points'] = isset($value['points']) && is_array($value['points']) ? $value['points'] : [];

            // Preserve raw values and schema for a native ACF loop in tooltip templates.
            $value['_acf_context'] = [
                'name' => $field['name'],
                'sub_fields' => $field['sub_fields'] ?? [],
                'post_id' => $post_id,
            ];
            foreach ($value['points'] as $index => $point) {
                if (isset($point['fields'])) {
                    $value['points'][$index]['_acf_raw_fields'] = $point['fields'];
                    $value['points'][$index]['fields'] = acf_format_value(
                        $point['fields'], $post_id,
                        $this->point_group($field, (string) ($point['_id'] ?? $index))
                    );
                }
            }

            if (empty($value['map_id'])) {
                $default_map_id = $this->attachment_id($field['default_map_id'] ?? 0);

                if ($default_map_id) {
                    $value['map_id'] = $default_map_id;
                }
            }

            return $value;
        }

        public function validate_value($valid, $value, $field, $input)
        {
            if ($valid !== true) {
                return $valid;
            }

            $value = is_array($value) ? $value : [];

            foreach ((array) ($value['points'] ?? []) as $index => $point) {
                if (is_array($point)) {
                    acf_validate_value($point['fields'] ?? [], $this->point_group($field, (string) $index), $input . '[points][' . $index . '][fields]');
                }
            }

            $attachment_id = $this->attachment_id($value['map_id'] ?? 0);
            $attachment_id = $attachment_id ?: $this->attachment_id($field['default_map_id'] ?? 0);

            if (! empty($field['required']) && ! $attachment_id) {
                return __('Please select a map image.', 'acf');
            }

            if ($attachment_id && ! str_starts_with((string) get_post_mime_type($attachment_id), 'image/')) {
                return __('Please select an image file for the map.', 'acf');
            }

            return $valid;
        }

        public function input_admin_enqueue_scripts(): void
        {
            wp_enqueue_media();
        }

        public function input_admin_head(): void
        {
            ?>
            <style id="sp-acf-interactive-map-css">
                .sp-interactive-map {
                    --sp-map-border: #d0d5dd;
                    --sp-map-brand: var(--wp-admin-theme-color);
                    --sp-map-soft: #f7f8fc;
                    background: #fff;
                    border: 1px solid var(--sp-map-border);
                    box-sizing: border-box;
                    container-name: sp-interactive-map;
                    container-type: inline-size;
                    overflow: hidden;
                    width: 100%;
                }

                .sp-interactive-map *,
                .sp-interactive-map *::before,
                .sp-interactive-map *::after {
                    box-sizing: border-box;
                }

                .sp-interactive-map > .acf-field.sp-interactive-map__zoom-setting {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 16px;
                    margin: 0;
                    padding: 14px 16px;
                    border: 0;
                    border-bottom: 1px solid var(--sp-map-border);
                    background: var(--sp-map-soft);
                }

                .sp-interactive-map__zoom-setting > .acf-label {
                    flex: 1;
                    width: auto;
                    margin: 0;
                    padding: 0;
                }

                .sp-interactive-map__zoom-setting > .acf-label label {
                    margin: 0;
                    color: #344054;
                    font-size: 13px;
                    line-height: 1.5;
                }

                .sp-interactive-map__zoom-setting > .acf-label .description {
                    margin: 3px 0 0;
                    color: #667085;
                    font-size: 12px;
                    line-height: 1.5;
                }

                .sp-interactive-map__zoom-setting > .acf-input {
                    flex: 0 0 auto;
                    width: auto;
                    margin: 0;
                    padding: 0;
                }

                .sp-interactive-map__label,
                .sp-interactive-map__point-grid label span {
                    color: #344054;
                    display: block;
                    font-size: 12px;
                    font-weight: 600;
                    margin-bottom: 6px;
                }

                .sp-interactive-map__actions {
                    align-items: center;
                    display: flex;
                    flex-wrap: wrap;
                    gap: 8px;
                }

                .sp-interactive-map .button,
                .sp-interactive-map .button-link-delete {
                    border-radius: 0;
                }

                .sp-interactive-map .button-link-delete {
                    appearance: none;
                    background: transparent;
                    border: 0;
                    color: #b32d2e;
                    cursor: pointer;
                    font-size: 13px;
                    line-height: 1.4;
                    padding: 0;
                    text-decoration: underline;
                }

                .sp-interactive-map .button-link-delete:hover {
                    color: #8a2424;
                }

                .sp-interactive-map .button-link-delete.is-hidden {
                    display: none;
                }

                .sp-interactive-map__body {
                    align-items: stretch;
                    display: grid;
                    gap: 16px;
                    grid-template-columns: minmax(0, 1.4fr) minmax(320px, .6fr);
                    min-height: 420px;
                    padding: 16px;
                }

                .sp-interactive-map__canvas {
                    align-items: center;
                    background: var(--sp-map-soft);
                    border: 1px dashed #b8c1d1;
                    display: flex;
                    justify-content: center;
                    min-height: 320px;
                    overflow: hidden;
                    position: relative;
                }

                .sp-interactive-map__map-actions {
                    align-items: center;
                    display: flex;
                    gap: 4px;
                    justify-content: flex-start;
                    position: absolute;
                    left: 12px;
                    top: 12px;
                    z-index: 5;
                }

                .sp-interactive-map__map-actions .button {
                    align-items: center;
                    background: #fff;
                    display: inline-flex;
                    height: 32px;
                    justify-content: center;
                    min-height: 32px;
                    padding: 0;
                    width: 32px;
                }

                .sp-interactive-map__map-actions .button:hover {
                    color: var(--sp-map-brand);
                }

                .sp-interactive-map__map-actions .button-link-delete {
                    align-items: center;
                    background: transparent;
                    border: 0;
                    display: inline-flex;
                    height: 32px;
                    justify-content: center;
                    padding: 0;
                    text-decoration: none;
                    width: 32px;
                }

                .sp-interactive-map__map-actions .dashicons {
                    font-size: 17px;
                    height: 17px;
                    line-height: 17px;
                    width: 17px;
                }

                .sp-interactive-map__canvas.is-filled {
                    border-style: solid;
                    min-height: 0;
                }

                .sp-interactive-map__canvas.is-filled .sp-interactive-map__map-actions {
                    background: rgba(255, 255, 255, .92);
                    border: 1px solid var(--sp-map-border);
                    box-shadow: 0 6px 18px rgba(16, 24, 40, .08);
                    padding: 6px;
                }

                .sp-interactive-map__empty {
                    align-items: center;
                    color: #667085;
                    display: flex;
                    flex-direction: column;
                    gap: 8px;
                    inset: 0;
                    justify-content: center;
                    min-height: 320px;
                    padding: 24px;
                    text-align: center;
                }

                .sp-interactive-map__empty[hidden],
                .sp-interactive-map__stage[hidden] {
                    display: none !important;
                }

                .sp-interactive-map__empty .dashicons {
                    color: #98a2b3;
                    font-size: 34px;
                    height: 34px;
                    width: 34px;
                }

                .sp-interactive-map__stage {
                    cursor: crosshair;
                    margin: auto;
                    position: relative;
                    width: 100%;
                }

                .sp-interactive-map__stage img {
                    display: block;
                    height: auto;
                    user-select: none;
                    width: 100%;
                }

                .sp-interactive-map__markers {
                    inset: 0;
                    position: absolute;
                    z-index: 2;
                }

                .sp-interactive-map__loupe {
                    background-color: rgba(255, 255, 255, .92);
                    background-repeat: no-repeat;
                    border: 2px solid var(--sp-map-brand);
                    box-shadow: 0 16px 34px rgba(15, 23, 42, .18);
                    height: 340px;
                    left: auto;
                    opacity: 0;
                    pointer-events: none;
                    position: absolute;
                    right: 16px;
                    top: 16px;
                    transform: scale(.86);
                    transform-origin: top right;
                    transition: opacity .16s ease, transform .22s cubic-bezier(.22, 1, .36, 1);
                    visibility: hidden;
                    width: 340px;
                    z-index: 4;
                }

                .sp-interactive-map__loupe::before,
                .sp-interactive-map__loupe::after {
                    background: rgba(34, 113, 177, .78);
                    content: "";
                    left: 50%;
                    position: absolute;
                    top: 50%;
                    transform: translate(-50%, -50%);
                    z-index: 1;
                }

                .sp-interactive-map__loupe::before {
                    height: 100%;
                    width: 1px;
                }

                .sp-interactive-map__loupe::after {
                    height: 1px;
                    width: 100%;
                }

                .sp-interactive-map__loupe.is-active {
                    opacity: 1;
                    transform: scale(1);
                    visibility: visible;
                }

                .sp-interactive-map__marker {
                    align-items: center;
                    background: var(--sp-map-brand);
                    border: 2px solid #fff;
                    border-radius: 999px;
                    box-shadow: 0 4px 14px rgba(0, 0, 0, .25);
                    color: #fff;
                    cursor: grab;
                    display: inline-flex;
                    font-size: 11px;
                    font-weight: 700;
                    height: 22px;
                    justify-content: center;
                    margin: -11px 0 0 -11px;
                    overflow: hidden;
                    position: absolute;
                    width: 22px;
                }

                .sp-interactive-map__marker.is-active,
                .sp-interactive-map__marker.is-dragging {
                    box-shadow: 0 0 0 4px rgba(34, 113, 177, .2), 0 4px 14px rgba(0, 0, 0, .25);
                    cursor: grabbing;
                    transform: scale(1.08);
                    z-index: 2;
                }

                .sp-interactive-map__marker.is-locked {
                    cursor: pointer;
                    opacity: .72;
                }

                .sp-interactive-map__points {
                    border: 1px solid var(--sp-map-border);
                    min-height: 320px;
                    min-width: 0;
                }

                .sp-interactive-map__points-head {
                    align-items: center;
                    background: var(--sp-map-soft);
                    border-bottom: 1px solid var(--sp-map-border);
                    display: flex;
                    justify-content: space-between;
                    padding: 12px;
                }

                .sp-interactive-map__point-list {
                    display: grid;
                    gap: 12px;
                    max-height: 620px;
                    overflow: auto;
                    padding: 12px;
                }

                .sp-interactive-map__point {
                    border: 1px solid var(--sp-map-border);
                    background: #fff;
                }

                .sp-interactive-map__point.is-active {
                    border-color: var(--sp-map-brand);
                    box-shadow: 0 0 0 1px var(--sp-map-brand);
                }

                .sp-interactive-map__point-head {
                    align-items: center;
                    background: #f9fafb;
                    border-bottom: 1px solid var(--sp-map-border);
                    display: flex;
                    gap: 10px;
                    justify-content: space-between;
                    padding: 10px 12px;
                }

                .sp-interactive-map__point-actions {
                    align-items: center;
                    display: inline-flex;
                    flex: 0 0 auto;
                    gap: 4px;
                }

                .sp-interactive-map__icon-action {
                    align-items: center;
                    appearance: none;
                    background: transparent;
                    border: 0;
                    color: #667085;
                    cursor: pointer;
                    display: inline-flex;
                    height: 28px;
                    justify-content: center;
                    padding: 0;
                    width: 28px;
                }

                .sp-interactive-map__icon-action:hover,
                .sp-interactive-map__icon-action.is-active {
                    color: var(--sp-map-brand);
                }

                .sp-interactive-map__icon-action.is-danger:hover {
                    color: #b32d2e;
                }

                .sp-interactive-map[data-sp-map-mode="single"] [data-sp-map-remove-point] {
                    display: none;
                }

                .sp-interactive-map__icon-action .dashicons {
                    font-size: 17px;
                    height: 17px;
                    line-height: 17px !important;
                    width: 17px;
                }

                .sp-interactive-map__point-head span {
                    font-weight: 600;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    white-space: nowrap;
                }

                .sp-interactive-map__custom-fields > .acf-field {
                    margin: 0;
                    padding: 0;
                    border: 0;
                    width: 100%;
                }

                .sp-interactive-map__custom-fields > .acf-field::before,
                .sp-interactive-map__custom-fields > .acf-field > .acf-label {
                    display: none;
                }

                .sp-interactive-map__custom-fields > .acf-field > .acf-input {
                    width: 100%;
                    margin: 0;
                    padding: 0;
                }

                .sp-interactive-map__custom-fields > .acf-field > .acf-input > .acf-fields {
                    border: 0;
                }

                .sp-interactive-map__point-grid {
                    display: grid;
                    gap: 10px;
                    grid-template-columns: 84px minmax(0, 1fr);
                    padding: 12px;
                }

                .sp-interactive-map__photo {
                    align-items: center;
                    background: var(--sp-map-soft);
                    border: 1px dashed #b8c1d1;
                    color: #98a2b3;
                    display: flex;
                    height: 84px;
                    justify-content: center;
                    overflow: hidden;
                    width: 84px;
                }

                .sp-interactive-map__photo img {
                    display: block;
                    height: 100%;
                    object-fit: cover;
                    width: 100%;
                }

                .sp-interactive-map__photo-actions {
                    align-content: start;
                    display: flex;
                    flex-wrap: wrap;
                    gap: 8px;
                }

                .sp-interactive-map__point-grid label {
                    grid-column: 1 / -1;
                    margin: 0;
                }

                .sp-interactive-map__point-grid > .acf-field {
                    grid-column: 1 / -1;
                    margin: 0;
                    padding: 0;
                    width: 100%;
                }

                .sp-interactive-map__point-grid > .acf-field::before {
                    display: none;
                }

                .sp-interactive-map__point-grid > .acf-field > .acf-label,
                .sp-interactive-map__point-grid > .acf-field > .acf-input {
                    float: none;
                    margin: 0;
                    padding: 0;
                    width: 100%;
                }

                .sp-interactive-map__point-grid input,
                .sp-interactive-map__point-grid select,
                .sp-interactive-map__point-grid textarea {
                    border-color: var(--sp-map-border);
                    border-radius: 0;
                    box-shadow: none;
                    max-width: none;
                    width: 100%;
                }

                @container sp-interactive-map (max-width: 1100px) {
                    .sp-interactive-map__body {
                        grid-template-columns: 1fr;
                        min-height: 0;
                    }

                    .sp-interactive-map__points {
                        min-height: 0;
                    }

                    .sp-interactive-map__point-list {
                        max-height: 520px;
                    }
                }

                @container sp-interactive-map (max-width: 640px) {
                    .sp-interactive-map__body {
                        gap: 10px;
                        padding: 10px;
                    }

                    .sp-interactive-map__canvas {
                        min-height: 220px;
                    }

                    .sp-interactive-map__empty {
                        min-height: 220px;
                        padding: 18px;
                    }

                    .sp-interactive-map__map-actions {
                        left: 8px;
                        top: 8px;
                    }

                    .sp-interactive-map__canvas.is-filled .sp-interactive-map__map-actions {
                        padding: 4px;
                    }

                    .sp-interactive-map__loupe {
                        height: 220px;
                        width: 220px;
                    }

                    .sp-interactive-map__points-head {
                        gap: 10px;
                    }

                    .sp-interactive-map__point-list {
                        max-height: none;
                        padding: 8px;
                    }

                    .sp-interactive-map__point-grid {
                        grid-template-columns: 1fr;
                        padding: 10px;
                    }

                    .sp-interactive-map__photo {
                        height: 104px;
                        width: 104px;
                    }

                    .sp-interactive-map__photo-actions .button,
                    .sp-interactive-map__photo-actions .button-link-delete {
                        max-width: 100%;
                        white-space: normal;
                    }
                }

                @container sp-interactive-map (max-width: 420px) {
                    .sp-interactive-map__body {
                        padding: 8px;
                    }

                    .sp-interactive-map__canvas {
                        min-height: 180px;
                    }

                    .sp-interactive-map__empty {
                        min-height: 180px;
                    }

                    .sp-interactive-map__points-head {
                        align-items: stretch;
                        flex-direction: column;
                    }

                    .sp-interactive-map__points-head .button {
                        justify-content: center;
                        width: 100%;
                    }

                    .sp-interactive-map__loupe {
                        height: min(170px, calc(100cqw - 32px));
                        width: min(170px, calc(100cqw - 32px));
                    }

                    .sp-interactive-map__point-head {
                        align-items: flex-start;
                    }

                    .sp-interactive-map__photo-actions {
                        align-items: flex-start;
                        flex-direction: column;
                    }

                    .sp-interactive-map__photo-actions .button {
                        width: 100%;
                    }

                    .sp-interactive-map__point-grid input,
                    .sp-interactive-map__point-grid select,
                    .sp-interactive-map__point-grid textarea {
                        min-width: 0;
                    }
                }
            </style>
            <?php
        }

        public function input_admin_footer(): void
        {
            ?>
            <script id="sp-acf-interactive-map-js">
                (function ($) {
                    'use strict';

                    var pointCounter = 0;
                    var activeDrag = null;

                    function uniqueIndex() {
                        pointCounter += 1;
                        return 'point_' + Date.now() + '_' + pointCounter;
                    }

                    function pointTemplate($field, index, x, y) {
                        var template = $field.find('[data-sp-map-point-template]').html() || '';
                        var $point = $(template.replace(/__INDEX__/g, index).trim());

                        $point.addClass('is-active').attr('data-point-index', index);
                        $point.find('[data-sp-point-x]').val(x);
                        $point.find('[data-sp-point-y]').val(y);

                        return $point;
                    }

                    function selectPoint($field, index) {
                        $field.find('[data-sp-map-point], [data-sp-map-marker]').removeClass('is-active');
                        $field.find('[data-sp-map-point][data-point-index="' + index + '"], [data-sp-map-marker][data-point-index="' + index + '"]').addClass('is-active');
                        updateLoupe($field, index);
                    }

                    function hideLoupe($field) {
                        $field.find('[data-sp-map-loupe]').removeClass('is-active');
                    }

                    function updateLoupe($field, index) {
                        var $stage = $field.find('[data-sp-map-stage]');
                        var $image = $stage.find('[data-sp-map-image]');
                        var $point = $field.find('[data-sp-map-point][data-point-index="' + index + '"]');
                        var $loupe = $stage.find('[data-sp-map-loupe]');

                        if (!$stage.length || !$image.length || !$point.length || !$loupe.length || $stage.prop('hidden')) {
                            hideLoupe($field);
                            return;
                        }

                        var width = $stage.width();
                        var height = $stage.height();
                        var loupeWidth = $loupe.outerWidth();
                        var loupeHeight = $loupe.outerHeight();
                        var zoom = 2.65;
                        var xPercent = parseFloat($point.find('[data-sp-point-x]').val() || 50);
                        var yPercent = parseFloat($point.find('[data-sp-point-y]').val() || 50);
                        var x = width * xPercent / 100;
                        var y = height * yPercent / 100;
                        var margin = 16;
                        var safeGap = 28;
                        var candidates = [
                            {
                                left: margin,
                                top: margin,
                                right: 'auto',
                                bottom: 'auto',
                                origin: 'top left'
                            },
                            {
                                left: 'auto',
                                top: margin,
                                right: margin,
                                bottom: 'auto',
                                origin: 'top right'
                            },
                            {
                                left: margin,
                                top: 'auto',
                                right: 'auto',
                                bottom: margin,
                                origin: 'bottom left'
                            },
                            {
                                left: 'auto',
                                top: 'auto',
                                right: margin,
                                bottom: margin,
                                origin: 'bottom right'
                            }
                        ].map(function (candidate) {
                            var rectLeft = candidate.left === 'auto' ? width - loupeWidth - margin : candidate.left;
                            var rectTop = candidate.top === 'auto' ? height - loupeHeight - margin : candidate.top;
                            var centerX = rectLeft + loupeWidth / 2;
                            var centerY = rectTop + loupeHeight / 2;
                            var overlapsPoint = x >= rectLeft - safeGap
                                && x <= rectLeft + loupeWidth + safeGap
                                && y >= rectTop - safeGap
                                && y <= rectTop + loupeHeight + safeGap;

                            return {
                                left: candidate.left,
                                top: candidate.top,
                                right: candidate.right,
                                bottom: candidate.bottom,
                                origin: candidate.origin,
                                score: Math.hypot(x - centerX, y - centerY) - (overlapsPoint ? 100000 : 0)
                            };
                        }).sort(function (a, b) {
                            return b.score - a.score;
                        });
                        var placement = candidates[0];

                        if (!width || !height) {
                            hideLoupe($field);
                            return;
                        }

                        $loupe.css({
                            left: placement.left,
                            top: placement.top,
                            right: placement.right,
                            bottom: placement.bottom,
                            transformOrigin: placement.origin,
                            backgroundImage: 'url("' + $image.attr('src') + '")',
                            backgroundSize: (width * zoom) + 'px ' + (height * zoom) + 'px',
                            backgroundPosition: (loupeWidth / 2 - x * zoom) + 'px ' + (loupeHeight / 2 - y * zoom) + 'px'
                        }).addClass('is-active');
                    }

                    function scrollToPoint($field, index) {
                        var $list = $field.find('[data-sp-map-points]');
                        var $point = $field.find('[data-sp-map-point][data-point-index="' + index + '"]');

                        if (!$list.length || !$point.length) {
                            return;
                        }

                        function align() {
                            var list = $list.get(0);
                            var point = $point.get(0);
                            var listRect = list.getBoundingClientRect();
                            var pointRect = point.getBoundingClientRect();
                            var targetListScroll = list.scrollTop + pointRect.top - listRect.top - 12;

                            $list.stop(true).animate({
                                scrollTop: Math.max(0, targetListScroll)
                            }, 180);
                        }

                        align();

                        setTimeout(align, 80);
                    }

                    function renderMarkers($field) {
                        var $markers = $field.find('[data-sp-map-markers]').empty();

                        $field.find('[data-sp-map-point]').each(function (i) {
                            var $point = $(this);
                            var index = $point.data('point-index');
                            var x = parseFloat($point.find('[data-sp-point-x]').val() || 50);
                            var y = parseFloat($point.find('[data-sp-point-y]').val() || 50);
                            var title = $point.find('[data-sp-point-name]').val() || 'Point ' + (i + 1);
                            var isLocked = $point.find('[data-sp-point-locked]').val() === '1';
                            var $marker = $('<button type="button" class="sp-interactive-map__marker" data-sp-map-marker>')
                                .attr('data-point-index', index)
                                .attr('title', title)
                                .css({left: x + '%', top: y + '%'})
                                .text(i + 1);

                            if (isLocked) {
                                $marker.addClass('is-locked');
                            }

                            if ($point.hasClass('is-active')) {
                                $marker.addClass('is-active');
                            }

                            $markers.append($marker);
                        });

                        var $active = $field.find('[data-sp-map-point].is-active').first();

                        if ($active.length) {
                            updateLoupe($field, $active.data('point-index'));
                        } else {
                            hideLoupe($field);
                        }
                    }

                    function syncPointLink($point) {
                        var $acfLink = $point.find('.sp-interactive-map__acf-link .acf-link');

                        if (!$acfLink.length) {
                            return;
                        }

                        var $linkNode = $acfLink.find('.link-node');
                        var url = $acfLink.find('input.input-url').val() || $linkNode.attr('href') || '';
                        var title = $acfLink.find('input.input-title').val() || $.trim($linkNode.text()) || '';
                        var target = $acfLink.find('input.input-target').val() || $linkNode.attr('target') || '';

                        $point.find('[data-sp-point-link-url]').val(url);
                        $point.find('[data-sp-point-link-title]').val(title);
                        $point.find('[data-sp-point-link-target]').val(target);
                    }

                    function syncLinks($scope) {
                        $scope.find('[data-sp-map-point]').each(function () {
                            syncPointLink($(this));
                        });
                    }

                    function setMap($field, attachment) {
                        var url = attachment.sizes && attachment.sizes.large ? attachment.sizes.large.url : attachment.url;

                        $field.find('[data-sp-map-id]').val(attachment.id).trigger('change');
                        $field.find('[data-sp-map-select]').attr({title: 'Replace map', 'aria-label': 'Replace map'});
                        $field.find('[data-sp-map-remove]').removeClass('is-hidden');
                        $field.find('[data-sp-map-empty]').prop('hidden', true);
                        $field.find('[data-sp-map-canvas]').addClass('is-filled');

                        var $stage = $field.find('[data-sp-map-stage]').prop('hidden', false);
                        var $image = $stage.find('[data-sp-map-image]');

                        if ($image.length) {
                            $image.attr('src', url);
                        } else {
                            $('<img>', {src: url, alt: '', 'data-sp-map-image': ''}).prependTo($stage);
                        }

                        renderMarkers($field);
                    }

                    function addPoint($field, x, y) {
                        var index = uniqueIndex();
                        var $point = pointTemplate($field, index, x, y);

                        $field.find('[data-sp-map-point]').removeClass('is-active');
                        $field.find('[data-sp-map-points]').append($point);
                        if (window.acf) {
                            window.acf.doAction('append', $point);
                        }
                        syncLinks($point);
                        selectPoint($field, index);
                        renderMarkers($field);
                        scrollToPoint($field, index);

                        setTimeout(function () {
                            $point.find('[data-sp-point-name]').trigger('focus');
                        }, 20);
                    }

                    function updateSinglePoint($field, x, y) {
                        var $point = $field.find('[data-sp-map-point]').first();

                        if (!$point.length) {
                            addPoint($field, x, y);
                            return;
                        }

                        var index = $point.data('point-index');

                        $point.find('[data-sp-point-x]').val(x).trigger('change');
                        $point.find('[data-sp-point-y]').val(y).trigger('change');
                        selectPoint($field, index);
                        renderMarkers($field);
                        scrollToPoint($field, index);
                    }

                    function mediaFrame(options, callback) {
                        var frame = wp.media({
                            title: options.title,
                            button: {text: options.button},
                            library: {type: 'image'},
                            multiple: false
                        });

                        frame.on('select', function () {
                            callback(frame.state().get('selection').first().toJSON());
                        });

                        frame.open();
                    }

                    function init($scope) {
                        $scope.find('[data-sp-interactive-map]').addBack('[data-sp-interactive-map]').each(function () {
                            syncLinks($(this));
                            renderMarkers($(this));
                        });
                    }

                    $(document).on('click', '[data-sp-map-select]', function (event) {
                        event.preventDefault();
                        var $field = $(this).closest('[data-sp-interactive-map]');

                        mediaFrame({title: 'Select map image', button: 'Use this map'}, function (attachment) {
                            setMap($field, attachment);
                        });
                    });

                    $(document).on('click', '[data-sp-point-image-select]', function (event) {
                        event.preventDefault();
                        var $point = $(this).closest('[data-sp-map-point]');

                        mediaFrame({title: 'Select tooltip photo', button: 'Use this photo'}, function (attachment) {
                            var url = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
                            var $field = $point.closest('[data-sp-interactive-map]');

                            $point.find('[data-sp-point-image-id]').val(attachment.id).trigger('change');
                            $point.find('[data-sp-point-image-select]').text('Replace photo');
                            $point.find('[data-sp-point-image-remove]').removeClass('is-hidden');
                            $point.find('[data-sp-point-image-preview]').addClass('is-filled').empty().append($('<img>', {src: url, alt: ''}));
                            renderMarkers($field);
                        });
                    });

                    $(document).on('click', '[data-sp-map-remove]', function (event) {
                        event.preventDefault();
                        var $field = $(this).closest('[data-sp-interactive-map]');

                        $field.find('[data-sp-map-id]').val('').trigger('change');
                        $field.find('[data-sp-map-select]').attr({title: 'Select map', 'aria-label': 'Select map'});
                        $field.find('[data-sp-map-remove]').addClass('is-hidden');
                        $field.find('[data-sp-map-empty]').prop('hidden', false);
                        $field.find('[data-sp-map-canvas]').removeClass('is-filled');
                        $field.find('[data-sp-map-stage]').prop('hidden', true).find('[data-sp-map-image]').remove();
                        hideLoupe($field);
                    });

                    $(document).on('click', '[data-sp-point-image-remove]', function (event) {
                        event.preventDefault();
                        var $point = $(this).closest('[data-sp-map-point]');

                        $point.find('[data-sp-point-image-id]').val('').trigger('change');
                        $point.find('[data-sp-point-image-select]').text('Select photo');
                        $point.find('[data-sp-point-image-remove]').addClass('is-hidden');
                        $point.find('[data-sp-point-image-preview]').removeClass('is-filled').empty().append($('<span>', {'class': 'dashicons dashicons-format-image'}));
                        renderMarkers($point.closest('[data-sp-interactive-map]'));
                    });

                    $(document).on('click', '[data-sp-map-remove-point]', function (event) {
                        event.preventDefault();
                        var $field = $(this).closest('[data-sp-interactive-map]');

                        if ($field.data('sp-map-mode') === 'single') {
                            return;
                        }

                        var $point = $(this).closest('[data-sp-map-point]');
                        if (window.acf) window.acf.doAction('remove', $point);
                        $point.remove();
                        renderMarkers($field);
                    });

                    $(document).on('click', '[data-sp-map-lock-point]', function (event) {
                        event.preventDefault();
                        event.stopPropagation();

                        var $button = $(this);
                        var $point = $button.closest('[data-sp-map-point]');
                        var $field = $point.closest('[data-sp-interactive-map]');
                        var $input = $point.find('[data-sp-point-locked]');
                        var locked = $input.val() !== '1';

                        $input.val(locked ? '1' : '0').trigger('change');
                        $button.toggleClass('is-active', locked).attr('aria-pressed', locked ? 'true' : 'false');
                        $button.find('.dashicons').toggleClass('dashicons-lock', locked).toggleClass('dashicons-unlock', !locked);
                        renderMarkers($field);
                    });

                    $(document).on('click', '[data-sp-map-add-point]', function (event) {
                        event.preventDefault();
                        addPoint($(this).closest('[data-sp-interactive-map]'), 50, 50);
                    });

                    $(document).on('input', '[data-sp-point-name]', function () {
                        var $point = $(this).closest('[data-sp-map-point]');
                        var $field = $point.closest('[data-sp-interactive-map]');

                        $point.find('[data-sp-point-title]').text($(this).val() || 'New point');
                        renderMarkers($field);
                    });

                    $(document).on('input change', '[data-sp-map-point] textarea, [data-sp-map-point] .acf-link input', function () {
                        var $field = $(this).closest('[data-sp-interactive-map]');

                        syncLinks($field);
                        renderMarkers($field);
                    });

                    $(document).on('click', '[data-sp-map-point] .acf-link a, [data-sp-map-point] .acf-link button', function () {
                        var $field = $(this).closest('[data-sp-interactive-map]');

                        setTimeout(function () {
                            syncLinks($field);
                            renderMarkers($field);
                        }, 100);
                    });

                    $(document).on('submit', 'form', function () {
                        syncLinks($(this));
                    });

                    $(document).on('click', '[data-sp-map-stage]', function (event) {
                        if ($(event.target).closest('[data-sp-map-marker]').length) {
                            return;
                        }

                        var $stage = $(this);
                        var $field = $stage.closest('[data-sp-interactive-map]');
                        var offset = $stage.offset();
                        var width = $stage.width();
                        var height = $stage.height();

                        if (! width || ! height) {
                            return;
                        }

                        var x = Math.round(Math.max(0, Math.min(100, ((event.pageX - offset.left) / width) * 100)) * 100) / 100;
                        var y = Math.round(Math.max(0, Math.min(100, ((event.pageY - offset.top) / height) * 100)) * 100) / 100;

                        if ($field.data('sp-map-mode') === 'single') {
                            var $singlePoint = $field.find('[data-sp-map-point]').first();

                            if ($singlePoint.length && $singlePoint.find('[data-sp-point-locked]').val() === '1') {
                                selectPoint($field, $singlePoint.data('point-index'));
                                return;
                            }

                            updateSinglePoint($field, x, y);
                            return;
                        }

                        addPoint($field, x, y);
                    });

                    $(document).on('click', '[data-sp-map-marker]', function (event) {
                        event.preventDefault();
                        event.stopPropagation();

                        var $marker = $(this);
                        var $field = $marker.closest('[data-sp-interactive-map]');
                        var index = $marker.data('point-index');
                        selectPoint($field, index);
                        scrollToPoint($field, index);
                    });

                    $(document).on('click', '[data-sp-map-point]', function () {
                        var $point = $(this);
                        selectPoint($point.closest('[data-sp-interactive-map]'), $point.data('point-index'));
                    });

                    $(document).on('mousedown', '[data-sp-map-marker]', function (event) {
                        event.preventDefault();
                        event.stopPropagation();

                        var $marker = $(this);
                        var $field = $marker.closest('[data-sp-interactive-map]');
                        var $stage = $field.find('[data-sp-map-stage]');
                        var index = $marker.data('point-index');
                        var $point = $field.find('[data-sp-map-point][data-point-index="' + index + '"]');

                        selectPoint($field, index);
                        scrollToPoint($field, index);

                        if ($point.find('[data-sp-point-locked]').val() === '1') {
                            return;
                        }

                        $marker.addClass('is-dragging');

                        activeDrag = {
                            $field: $field,
                            $stage: $stage,
                            $point: $point,
                            $marker: $marker
                        };
                    });

                    $(document).on('mousemove.spInteractiveMap', function (event) {
                        if (! activeDrag) {
                            return;
                        }

                        var offset = activeDrag.$stage.offset();
                        var width = activeDrag.$stage.width();
                        var height = activeDrag.$stage.height();

                        if (! width || ! height) {
                            return;
                        }

                        var x = Math.round(Math.max(0, Math.min(100, ((event.pageX - offset.left) / width) * 100)) * 100) / 100;
                        var y = Math.round(Math.max(0, Math.min(100, ((event.pageY - offset.top) / height) * 100)) * 100) / 100;

                        activeDrag.$marker.css({left: x + '%', top: y + '%'});
                        activeDrag.$point.find('[data-sp-point-x]').val(x).trigger('change');
                        activeDrag.$point.find('[data-sp-point-y]').val(y).trigger('change');
                        updateLoupe(activeDrag.$field, activeDrag.$point.data('point-index'));
                    });

                    $(document).on('mouseup.spInteractiveMap', function () {
                        if (! activeDrag) {
                            return;
                        }

                        activeDrag.$marker.removeClass('is-dragging');
                        renderMarkers(activeDrag.$field);
                        activeDrag = null;
                    });

                    $(function () {
                        init($(document));
                    });

                    if (window.acf) {
                        window.acf.addAction('append', function ($element) {
                            init($element);
                        });
                    }
                })(jQuery);
            </script>
            <?php
        }
    }

    acf_register_field_type('SP_ACF_Field_Interactive_Map');
});
