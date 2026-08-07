<?php
/**
 * ACF Field Type: SP Table
 *
 * Usage in ACF PHP / Builder:
 *
 *   ->addField('comparison_table', 'sp_acf_table', [
 *       'label'           => 'Comparison Table',
 *       'default_mode'    => 'compare', // 'table' | 'compare'
 *       'default_columns' => 4,
 *       'default_rows'    => 5,
 *   ])
 *
 * Usage in ACF local field arrays:
 *
 *   [
 *       'key'             => 'field_comparison_table',
 *       'label'           => 'Comparison Table',
 *       'name'            => 'comparison_table',
 *       'type'            => 'sp_acf_table',
 *       'default_mode'    => 'compare',
 *       'default_columns' => 4,
 *       'default_rows'    => 5,
 *   ]
 *
 * Returned value:
 *
 *   $table = get_field('comparison_table');
 *
 *   [
 *       'mode'    => 'table' | 'compare',
 *       'columns' => [
 *           ['id' => 'col_1', 'label' => 'Title 1'],
 *       ],
 *       'rows'    => [
 *           [
 *               'id'    => 'row_1',
 *               'cells' => [
 *                   ['type' => 'text',  'text' => 'Feature name'],
 *                   ['type' => 'check', 'text' => ''],
 *                   ['type' => 'cross', 'text' => ''],
 *                   ['type' => 'empty', 'text' => ''],
 *               ],
 *           ],
 *       ],
 *   ]
 *
 * Frontend render helper:
 *
 *   echo sp_acf_render_table(get_field('comparison_table'), [
 *       'class' => 'pricing-table',
 *   ]);
 *
 * Render by field name:
 *
 *   echo sp_acf_table('comparison_table');
 *
 * Or echo directly:
 *
 *   sp_acf_the_table(get_field('comparison_table'));
 *   sp_acf_the_table_field('comparison_table');
 */

if (! defined('ABSPATH')) {
	exit;
}

if (! function_exists('sp_acf_table_normalize')) {
	function sp_acf_table_normalize($value, array $field = [], int $max_columns = 12, int $max_rows = 200): array
	{
		if (is_string($value)) {
			$json    = function_exists('wp_unslash') ? wp_unslash($value) : stripslashes($value);
			$decoded = json_decode($json, true);
			$value   = is_array($decoded) ? $decoded : [];
		}

		if (! is_array($value)) {
			$value = [];
		}

		$mode = sanitize_key((string) ($value['mode'] ?? ($field['default_mode'] ?? 'table')));
		if (! in_array($mode, ['table', 'compare'], true)) {
			$mode = 'table';
		}

		$default_columns = max(1, min($max_columns, absint($field['default_columns'] ?? ($mode === 'compare' ? 4 : 2))));
		$default_rows    = max(0, min($max_rows, absint($field['default_rows'] ?? ($mode === 'compare' ? 5 : 4))));

		$raw_columns = is_array($value['columns'] ?? null) ? array_values($value['columns']) : [];
		if (! $raw_columns) {
			for ($i = 0; $i < $default_columns; $i++) {
				$raw_columns[] = [
					'id'    => 'col_' . ($i + 1),
					'label' => $mode === 'compare'
						? ($i === 0 ? __('Feature', 'targetized') : __('Compare', 'targetized') . ' ' . $i)
						: __('Title', 'targetized') . ' ' . ($i + 1),
					'width' => 1,
					'align' => $mode === 'compare' && $i > 0 ? 'center' : 'left',
				];
			}
		}

		$columns = [];
		foreach (array_slice($raw_columns, 0, $max_columns) as $index => $column) {
			$id = sanitize_key((string) ($column['id'] ?? ''));
			if ($id === '') {
				$id = 'col_' . ($index + 1);
			}

			$align = sanitize_key((string) ($column['align'] ?? ''));
			if (! in_array($align, ['left', 'center', 'right'], true)) {
				$align = $mode === 'compare' && $index > 0 ? 'center' : 'left';
			}

			$columns[] = [
				'id'    => $id,
				'label' => sanitize_text_field((string) ($column['label'] ?? '')),
				'width' => max(1, min(12, absint($column['width'] ?? 1))),
				'align' => $align,
			];
		}

		if (! $columns) {
			$columns[] = [
				'id'    => 'col_1',
				'label' => __('Title 1', 'targetized'),
				'width' => 1,
				'align' => 'left',
			];
		}

		$has_saved_rows = array_key_exists('rows', $value) && is_array($value['rows']);
		$raw_rows       = $has_saved_rows ? array_values($value['rows']) : [];
		if (! $has_saved_rows) {
			for ($i = 0; $i < $default_rows; $i++) {
				$raw_rows[] = [
					'id'    => 'row_' . ($i + 1),
					'cells' => [],
				];
			}
		}

		$rows = [];
		foreach (array_slice($raw_rows, 0, $max_rows) as $row_index => $row) {
			$cells     = [];
			$raw_cells = is_array($row['cells'] ?? null) ? array_values($row['cells']) : [];

			foreach ($columns as $column_index => $column) {
				$cell = is_array($raw_cells[$column_index] ?? null) ? $raw_cells[$column_index] : [];
				$type = sanitize_key((string) ($cell['type'] ?? 'text'));

				if (! in_array($type, ['text', 'check', 'cross', 'empty'], true)) {
					$type = 'text';
				}
				if ($mode === 'table' || $column_index === 0) {
					$type = 'text';
				} elseif ($mode === 'compare') {
					$type = in_array($type, ['cross', 'empty'], true) ? 'cross' : 'check';
				}

				$cells[] = [
					'type' => $type,
					'text' => sanitize_textarea_field((string) ($cell['text'] ?? '')),
				];
			}

			$row_id = sanitize_key((string) ($row['id'] ?? ''));
			if ($row_id === '') {
				$row_id = 'row_' . ($row_index + 1);
			}

			$rows[] = [
				'id'    => $row_id,
				'cells' => $cells,
			];
		}

		return [
			'mode'    => $mode,
			'columns' => $columns,
			'rows'    => $rows,
		];
	}
}

if (! function_exists('sp_acf_table_boolean_icon')) {
	function sp_acf_table_boolean_icon(string $type): string
	{
		if (! function_exists('sprite')) {
			return '';
		}

		ob_start();
		sprite(24, 24, $type === 'cross' ? 'cross' : 'check');

		return (string) ob_get_clean();
	}
}

if (! function_exists('sp_acf_table_render_cell')) {
	function sp_acf_table_render_cell(array $cell, string $empty_value = ''): string
	{
		$type = sanitize_key((string) ($cell['type'] ?? 'text'));
		$text = (string) ($cell['text'] ?? '');

		if ($type === 'check') {
			return '<span class="sp-table__mark sp-table__mark--check" aria-label="' . esc_attr__('Yes', 'targetized') . '">' . sp_acf_table_boolean_icon('check') . '</span>';
		}

		if ($type === 'cross') {
			return '<span class="sp-table__mark sp-table__mark--cross" aria-label="' . esc_attr__('No', 'targetized') . '">' . sp_acf_table_boolean_icon('cross') . '</span>';
		}

		if ($type === 'empty') {
			return esc_html($empty_value);
		}

		return nl2br(esc_html($text));
	}
}

if (! function_exists('sp_acf_render_table')) {
	function sp_acf_render_table($value, array $args = []): string
	{
		$args = wp_parse_args($args, [
			'class'        => '',
			'caption'      => '',
			'empty_value'  => '',
			'render_empty' => false,
		]);

		if (! $args['render_empty'] && empty($value)) {
			return '';
		}

		$table = sp_acf_table_normalize($value);
		if (empty($table['columns']) || empty($table['rows'])) {
			return '';
		}

		$column_count  = count($table['columns']);
		$column_weight = array_sum(array_map(static function (array $column): int {
			return max(1, absint($column['width'] ?? 1));
		}, $table['columns']));
		$column_weight = max(1, $column_weight);
		$classes       = [
			'sp-table',
			'sp-table--' . $table['mode'],
			'sp-table--cols-' . $column_count,
		];
		if ($table['mode'] === 'table') {
			$classes[] = 'sp-table--simple';
		}
		$extra   = preg_split('/\s+/', trim((string) $args['class'])) ?: [];
		foreach ($extra as $class) {
			$class = sanitize_html_class($class);
			if ($class !== '') {
				$classes[] = $class;
			}
		}

		ob_start();
		?>
		<div class="sp-table-wrapper sp-table-wrapper--<?php echo esc_attr($table['mode']); ?>" style="--sp-table-columns: <?php echo esc_attr((string) $column_count); ?>">
			<table class="<?php echo esc_attr(implode(' ', array_unique($classes))); ?>" data-columns="<?php echo esc_attr((string) $column_count); ?>">
				<?php if ((string) $args['caption'] !== '') : ?>
					<caption><?php echo esc_html((string) $args['caption']); ?></caption>
				<?php endif; ?>
				<colgroup>
					<?php foreach ($table['columns'] as $column) : ?>
						<?php $column_width = round((max(1, absint($column['width'] ?? 1)) / $column_weight) * 100, 4); ?>
						<col style="width: <?php echo esc_attr((string) $column_width); ?>%;">
					<?php endforeach; ?>
				</colgroup>
				<thead>
					<tr>
						<?php foreach ($table['columns'] as $column) : ?>
							<th scope="col" style="text-align: <?php echo esc_attr($column['align']); ?>;"><?php echo esc_html($column['label']); ?></th>
						<?php endforeach; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($table['rows'] as $row) : ?>
						<tr>
							<?php foreach ($row['cells'] as $index => $cell) : ?>
								<?php
								$is_header_cell = $table['mode'] === 'compare' && $index === 0;
								$tag            = $is_header_cell ? 'th' : 'td';
								$scope          = $is_header_cell ? ' scope="row"' : '';
								$align          = $table['columns'][$index]['align'] ?? 'left';
								?>
								<<?php echo $tag . $scope; ?> style="text-align: <?php echo esc_attr($align); ?>;">
									<?php echo sp_acf_table_render_cell($cell, (string) $args['empty_value']); ?>
								</<?php echo $tag; ?>>
							<?php endforeach; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
		return trim((string) ob_get_clean());
	}
}

if (! function_exists('sp_acf_the_table')) {
	function sp_acf_the_table($value, array $args = []): void
	{
		echo sp_acf_render_table($value, $args);
	}
}

if (! function_exists('sp_acf_table')) {
	function sp_acf_table(string $field_name, $post_id = false, array $args = []): string
	{
		if (! function_exists('get_field')) {
			return '';
		}

		return sp_acf_render_table(get_field($field_name, $post_id), $args);
	}
}

if (! function_exists('sp_acf_the_table_field')) {
	function sp_acf_the_table_field(string $field_name, $post_id = false, array $args = []): void
	{
		echo sp_acf_table($field_name, $post_id, $args);
	}
}

add_action('acf/include_field_types', function (): void {
	if (! class_exists('acf_field') || class_exists('SP_ACF_Field_Table')) {
		return;
	}

	class SP_ACF_Field_Table extends acf_field
	{
		private const MAX_COLUMNS = 12;
		private const MAX_ROWS    = 200;

		private static bool $assets_hooked = false;

		public function initialize(): void
		{
			$this->name     = 'sp_acf_table';
			$this->label    = __('SP Table', 'targetized');
			$this->category = 'content';
			$this->defaults = [
				'default_mode'    => 'table',
				'default_columns' => 2,
				'default_rows'    => 4,
			];
		}

		public function render_field_settings(array $field): void
		{
			acf_render_field_setting($field, [
				'label'        => __('Default Mode', 'targetized'),
				'instructions' => __('Initial table mode for a new empty value.', 'targetized'),
				'type'         => 'select',
				'name'         => 'default_mode',
				'choices'      => [
					'table'   => __('Simple table', 'targetized'),
					'compare' => __('Comparison table', 'targetized'),
				],
			]);

			acf_render_field_setting($field, [
				'label'        => __('Default Columns', 'targetized'),
				'instructions' => __('Used only when the field has no saved value yet.', 'targetized'),
				'type'         => 'number',
				'name'         => 'default_columns',
				'min'          => 1,
				'max'          => self::MAX_COLUMNS,
			]);

			acf_render_field_setting($field, [
				'label'        => __('Default Rows', 'targetized'),
				'instructions' => __('Used only when the field has no saved value yet.', 'targetized'),
				'type'         => 'number',
				'name'         => 'default_rows',
				'min'          => 0,
				'max'          => self::MAX_ROWS,
			]);
		}

		public function render_field(array $field): void
		{
			$value  = self::normalize_value($field['value'] ?? null, $field);
			$input  = wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			$config = wp_json_encode([
				'maxColumns' => self::MAX_COLUMNS,
				'maxRows'    => self::MAX_ROWS,
				'i18n'       => [
					'mode'          => __('Table type', 'targetized'),
					'table'         => __('Text table', 'targetized'),
					'compare'       => __('Comparison table', 'targetized'),
					'addColumn'     => __('Add column', 'targetized'),
					'addRow'        => __('Add row', 'targetized'),
					'removeColumn'  => __('Remove column', 'targetized'),
					'removeRow'     => __('Remove row', 'targetized'),
					'moveLeft'      => __('Move left', 'targetized'),
					'moveRight'     => __('Move right', 'targetized'),
					'narrower'      => __('Narrower', 'targetized'),
					'wider'         => __('Wider', 'targetized'),
					'alignLeft'     => __('Align left', 'targetized'),
					'alignCenter'   => __('Align center', 'targetized'),
					'alignRight'    => __('Align right', 'targetized'),
					'moveUp'        => __('Move up', 'targetized'),
					'moveDown'      => __('Move down', 'targetized'),
					'text'          => __('Text', 'targetized'),
					'check'         => __('Check', 'targetized'),
					'cross'         => __('No', 'targetized'),
					'empty'         => __('Empty', 'targetized'),
					'noRows'        => __('No rows yet. Add the first row.', 'targetized'),
					'column'        => __('Column', 'targetized'),
					'cellText'      => __('Cell text', 'targetized'),
					'featureColumn' => __('Feature', 'targetized'),
					'compareColumn' => __('Compare', 'targetized'),
					'columnsCount'  => __('Columns: %d', 'targetized'),
					'rowsCount'     => __('Rows: %d', 'targetized'),
					'tableUpdated'  => __('Table updated.', 'targetized'),
				],
			], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

			?>
			<div class="sp-acf-table sp-admin-component sp-acf-component" data-sp-admin-component data-sp-acf-table data-config="<?php echo esc_attr((string) $config); ?>">
				<input
					type="hidden"
					class="sp-acf-table__input"
					name="<?php echo esc_attr($field['name']); ?>"
					value="<?php echo esc_attr((string) $input); ?>"
				>
				<div class="sp-acf-table__toolbar" aria-label="<?php echo esc_attr__('Table controls', 'targetized'); ?>"></div>
				<div class="sp-acf-table__stage"></div>
				<div class="sp-acf-table__status sp-acf-status" role="status" aria-live="polite" aria-atomic="true"></div>
			</div>
			<?php
		}

		public function update_value($value, $post_id, $field)
		{
			return self::normalize_value($value, is_array($field) ? $field : []);
		}

		public function format_value($value, $post_id, $field)
		{
			return self::normalize_value($value, is_array($field) ? $field : []);
		}

		public function input_admin_enqueue_scripts(): void
		{
			if (self::$assets_hooked) {
				return;
			}

			self::$assets_hooked = true;
			add_action('admin_footer', [__CLASS__, 'print_assets']);
		}

		public static function normalize_value($value, array $field = []): array
		{
			return sp_acf_table_normalize($value, $field, self::MAX_COLUMNS, self::MAX_ROWS);
		}

		public static function render_frontend_table($value, array $args = []): string
		{
			return sp_acf_render_table($value, $args);
		}

		private static function render_frontend_cell(array $cell, string $empty_value): string
		{
			return sp_acf_table_render_cell($cell, $empty_value);
		}

		public static function print_assets(): void
		{
			?>
			<style>
					.sp-acf-table {
						container-type: inline-size;
						border: 1px solid var(--sp-acf-border);
						border-radius: var(--sp-acf-radius);
						background: var(--sp-acf-surface);
						box-shadow: var(--sp-acf-shadow);
						overflow: hidden;
					}

					.sp-acf-table__toolbar {
						display: flex;
						flex-wrap: wrap;
						align-items: center;
						gap: 8px;
						padding: 10px 12px;
						border-bottom: 1px solid var(--sp-acf-border);
						background: var(--sp-acf-surface-soft);
					}

					.sp-acf-table__toolbar-label {
						display: inline-flex;
						align-items: center;
						gap: 10px;
						min-height: var(--sp-acf-control-height);
						color: var(--sp-acf-text);
						font-weight: 700;
					}

					.sp-acf-table__toolbar-label > span:first-child {
						max-width: 70px;
						font-size: 12px;
						line-height: 1.15;
					}

					.sp-acf-table__mode-switcher {
						display: inline-grid;
						grid-template-columns: repeat(2, minmax(132px, 1fr));
						min-height: var(--sp-acf-control-height);
						border: 1px solid var(--sp-acf-border-strong);
						border-radius: var(--sp-acf-radius);
						background: var(--sp-acf-segment-bg);
					}

					.sp-acf-table__mode-option {
						display: inline-flex;
						align-items: center;
						justify-content: center;
						min-height: calc(var(--sp-acf-control-height) - 2px);
						margin: 0;
						padding: 0 12px;
						border: 0;
						border-right: 1px solid var(--sp-acf-border-strong);
						border-radius: var(--sp-acf-radius);
						background: transparent;
						color: var(--sp-acf-text);
						font-size: 13px;
						font-weight: 700;
						line-height: 1;
						box-shadow: none;
						cursor: pointer;
						transition: background var(--sp-acf-transition), color var(--sp-acf-transition), box-shadow var(--sp-acf-transition);
					}

					.sp-acf-table__mode-option:last-child {
						border-right: 0;
					}

					.sp-acf-table__mode-option:hover:not(.is-active) {
						background: var(--sp-acf-surface-soft);
						color: var(--sp-acf-accent-hover);
					}

					.sp-acf-table__mode-option:active:not(.is-active) {
						background: var(--sp-acf-accent-soft);
						color: var(--sp-acf-accent-hover);
					}

					.sp-acf-table__mode-option.is-active {
						background: var(--sp-acf-accent);
						color: var(--color-on-accent);
					}

					.sp-acf-table__mode-option:focus-visible {
						position: relative;
						z-index: 2;
						box-shadow: var(--sp-acf-focus);
						outline: 0;
					}

					.sp-acf-table__button {
						display: inline-flex;
						align-items: center;
						justify-content: center;
						gap: 6px;
						min-height: var(--sp-acf-control-height);
						margin: 0;
						padding: 0 12px;
						border: 1px solid var(--sp-acf-border-strong);
						border-radius: var(--sp-acf-radius);
						background: var(--sp-acf-surface);
						color: var(--sp-acf-accent-hover);
						font-size: 13px;
						font-weight: 700;
						line-height: 1;
						box-shadow: none;
						cursor: pointer;
						transition: background var(--sp-acf-transition), border-color var(--sp-acf-transition), box-shadow var(--sp-acf-transition), color var(--sp-acf-transition);
					}

					.sp-acf-table__button:hover:not(:disabled) {
						border-color: var(--sp-acf-accent);
						background: var(--sp-acf-accent-soft);
						color: var(--sp-acf-accent-hover);
					}

					.sp-acf-table__button:active:not(:disabled) {
						border-color: var(--sp-acf-accent-hover);
						background: var(--sp-acf-accent);
						color: var(--color-on-accent);
					}

					.sp-acf-table__button:focus-visible {
						border-color: var(--sp-acf-accent);
						box-shadow: var(--sp-acf-focus);
						outline: 0;
					}

					.sp-acf-table__button--danger {
						color: var(--sp-acf-danger);
					}

					.sp-acf-table__button--danger:hover:not(:disabled) {
						border-color: var(--sp-acf-danger);
						background: var(--sp-acf-danger);
						color: #fff;
					}

					.sp-acf-table__button--danger:active:not(:disabled) {
						border-color: var(--sp-acf-danger);
						background: var(--sp-acf-danger);
						color: #fff;
					}

					.sp-acf-table__button--danger:focus-visible {
						border-color: var(--sp-acf-danger);
						box-shadow: var(--sp-acf-danger-focus);
					}

					.sp-acf-table__stage {
						padding: 12px;
						overflow-x: auto;
						background: var(--sp-acf-surface);
					}

					.sp-acf-table__empty {
						padding: 24px;
						border: 1px dashed var(--sp-acf-border-strong);
						border-radius: var(--sp-acf-radius);
						background: var(--sp-acf-surface-soft);
						color: var(--sp-acf-text-muted);
						text-align: center;
					}

					.sp-acf-table__grid {
						display: grid;
						min-width: max(680px, 100%);
						border-top: 1px solid var(--sp-acf-border);
						border-left: 1px solid var(--sp-acf-border);
					}

					.sp-acf-table__grid--compare {
						border-top: 0;
						border-left: 0;
					}

					.sp-acf-table__cell,
					.sp-acf-table__head {
						position: relative;
						min-width: 160px;
						min-height: 70px;
						border-right: 1px solid var(--sp-acf-border);
						border-bottom: 1px solid var(--sp-acf-border);
						background: var(--sp-acf-surface-soft);
					}

					.sp-acf-table__head {
						display: flex;
						align-items: center;
						background: var(--sp-acf-accent-bright);
					}

					.sp-acf-table__grid--compare .sp-acf-table__head {
						border-right: 0;
						border-bottom-color: var(--sp-acf-accent-bright);
						background: transparent;
					}

					.sp-acf-table__grid--compare .sp-acf-table__cell {
						border-right: 0;
						background: var(--sp-acf-surface);
					}

					.sp-acf-table__head-input,
					.sp-acf-table__cell-text {
						display: block;
						box-sizing: border-box;
						width: 100%;
						min-height: 100%;
						margin: 0;
						border: 0 !important;
						border-radius: var(--sp-acf-radius) !important;
						background: transparent;
						box-shadow: none !important;
						color: var(--sp-acf-text);
						font-size: 14px;
						line-height: 1.35;
						transition: background var(--sp-acf-transition), box-shadow var(--sp-acf-transition), color var(--sp-acf-transition);
					}

					.sp-acf-table__head-input {
						height: 100%;
						padding: 14px !important;
						color: var(--color-on-accent);
						font-size: 18px;
						font-weight: 800;
					}

					.sp-acf-table__grid--compare .sp-acf-table__head-input {
						color: var(--sp-acf-accent);
					}

					.sp-acf-table__head-input::placeholder {
						color: rgb(255 255 255 / 75%);
					}

					.sp-acf-table__grid--compare .sp-acf-table__head-input::placeholder {
						color: color-mix(in srgb, var(--sp-acf-accent-bright) 65%, transparent);
					}

					.sp-acf-table__head-input:focus,
					.sp-acf-table__cell-text:focus {
						position: relative;
						z-index: 2;
						background: var(--sp-acf-input-bg);
						box-shadow: inset 0 0 0 2px var(--sp-acf-accent), var(--sp-acf-focus) !important;
						color: var(--sp-acf-text);
						outline: 0;
					}

					.sp-acf-table__cell-text {
						resize: vertical;
						padding: 42px 12px 14px !important;
					}

					.sp-acf-table__cell--state {
						display: flex;
						align-items: center;
						justify-content: center;
						min-height: 70px;
					}

					.sp-acf-table__state-preview {
						display: inline-flex;
						align-items: center;
						justify-content: center;
						width: 24px;
						height: 24px;
					}

					.sp-acf-table__state-preview svg {
						display: block;
						width: 24px;
						height: 24px;
					}

					.sp-acf-table__state-button {
						display: inline-flex;
						align-items: center;
						justify-content: center;
						width: 40px;
						height: 40px;
						padding: 0;
						border: 1px solid transparent;
						border-radius: var(--sp-acf-radius);
						background: transparent;
						box-shadow: none;
						cursor: pointer;
						transition: background var(--sp-acf-transition), border-color var(--sp-acf-transition), box-shadow var(--sp-acf-transition);
					}

					.sp-acf-table__state-button:hover {
						border-color: var(--sp-acf-border-strong);
						background: var(--sp-acf-accent-soft);
					}

					.sp-acf-table__state-button:active {
						border-color: var(--sp-acf-accent);
						background: var(--sp-acf-accent-soft);
					}

					.sp-acf-table__state-button:focus-visible {
						border-color: var(--sp-acf-accent);
						box-shadow: var(--sp-acf-focus);
						outline: 0;
					}

					.sp-acf-table__cell-tools,
					.sp-acf-table__head-tools,
					.sp-acf-table__row-tools {
						position: absolute;
						z-index: 3;
						display: inline-flex;
						gap: 3px;
						opacity: 0;
						transition: opacity var(--sp-acf-transition);
					}

					.sp-acf-table__cell:hover .sp-acf-table__cell-tools,
					.sp-acf-table__cell:focus-within .sp-acf-table__cell-tools,
					.sp-acf-table__head:hover .sp-acf-table__head-tools,
					.sp-acf-table__head:focus-within .sp-acf-table__head-tools,
					.sp-acf-table__row-start:hover .sp-acf-table__row-tools,
					.sp-acf-table__row-start:focus-within .sp-acf-table__row-tools {
						opacity: 1;
					}

					.sp-acf-table__head-tools {
						top: 6px;
						right: 6px;
					}

					.sp-acf-table__cell-tools {
						right: 6px;
						bottom: 6px;
					}

					.sp-acf-table__row-start {
						position: relative;
					}

					.sp-acf-table__row-tools {
						top: 6px;
						right: 6px;
					}

					.sp-acf-table__mini {
						display: inline-flex;
						align-items: center;
						justify-content: center;
						width: var(--sp-acf-control-height);
						height: var(--sp-acf-control-height);
						padding: 0;
						border: 1px solid var(--sp-acf-border-strong);
						border-radius: var(--sp-acf-radius);
						background: var(--sp-acf-surface);
						color: var(--sp-acf-text);
						font-size: 12px;
						font-weight: 800;
						line-height: 1;
						cursor: pointer;
						transition: background var(--sp-acf-transition), border-color var(--sp-acf-transition), box-shadow var(--sp-acf-transition), color var(--sp-acf-transition);
					}

					.sp-acf-table__mini:hover:not(:disabled):not(.is-active) {
						border-color: var(--sp-acf-accent);
						background: var(--sp-acf-accent-soft);
						color: var(--sp-acf-accent-hover);
					}

					.sp-acf-table__mini:active:not(:disabled):not(.is-active) {
						border-color: var(--sp-acf-accent-hover);
						background: var(--sp-acf-accent);
						color: var(--color-on-accent);
					}

					.sp-acf-table__mini.is-active {
						border-color: var(--sp-acf-accent);
						background: var(--sp-acf-accent);
						color: var(--color-on-accent);
					}

					.sp-acf-table__mini:focus-visible {
						position: relative;
						z-index: 2;
						border-color: var(--sp-acf-accent);
						box-shadow: var(--sp-acf-focus);
						outline: 0;
					}

					.sp-acf-table__mini--danger:hover:not(:disabled) {
						border-color: var(--sp-acf-danger);
						background: var(--sp-acf-danger);
						color: #fff;
					}

					.sp-acf-table__mini--danger:active:not(:disabled) {
						border-color: var(--sp-acf-danger);
						background: var(--sp-acf-danger);
						color: #fff;
					}

					.sp-acf-table__mini--danger:focus-visible {
						border-color: var(--sp-acf-danger);
						box-shadow: var(--sp-acf-danger-focus);
					}

					.sp-acf-table__mini svg {
						display: block;
						width: 16px;
						height: 16px;
					}

					.sp-acf-table :is(button, input, textarea):disabled {
						border-color: var(--sp-acf-border) !important;
						background: var(--sp-acf-surface-soft) !important;
						box-shadow: none !important;
						color: var(--sp-acf-text-subtle) !important;
						cursor: not-allowed !important;
						opacity: .68;
						pointer-events: none;
					}

					.sp-acf-table__status {
						min-height: 30px;
						padding: 7px 12px;
						border-top: 1px solid var(--sp-acf-border);
						background: var(--sp-acf-surface-soft);
						color: var(--sp-acf-text-muted);
						font-size: 12px;
						line-height: 1.3;
					}

					@container (max-width: 760px) {
						.sp-acf-table__toolbar-label {
							flex: 1 1 100%;
							justify-content: space-between;
						}

						.sp-acf-table__mode-switcher {
							flex: 1 1 auto;
							grid-template-columns: repeat(2, minmax(0, 1fr));
						}

						.sp-acf-table__button {
							flex: 1 1 calc(50% - 4px);
						}
					}

					@container (max-width: 460px) {
						.sp-acf-table__toolbar-label {
							align-items: stretch;
							flex-direction: column;
						}

						.sp-acf-table__toolbar-label > span:first-child {
							max-width: none;
						}

						.sp-acf-table__button {
							flex-basis: 100%;
						}

						.sp-acf-table__stage {
							padding: 8px;
						}
					}

					@media (hover: none), (pointer: coarse) {
						.sp-acf-table__cell-tools,
						.sp-acf-table__head-tools,
						.sp-acf-table__row-tools {
							opacity: 1;
						}

						.sp-acf-table__mini {
							width: 40px;
							height: 40px;
						}
					}
			</style>
			<?php
			$admin_icons_json = wp_json_encode([
				'check' => sp_acf_table_boolean_icon('check'),
				'cross' => sp_acf_table_boolean_icon('cross'),
			], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			?>
			<script>
				(function () {
					'use strict';

					var selector = '[data-sp-acf-table]';
					var boundTables = typeof WeakSet === 'function' ? new WeakSet() : null;
					var booleanIcons = <?php echo $admin_icons_json ?: '{}'; ?>;

					function parseJson(value, fallback) {
						try {
							return JSON.parse(value || '');
						} catch (error) {
							return fallback;
						}
					}

					function uid(prefix) {
						return prefix + '_' + Math.random().toString(36).slice(2, 8) + Date.now().toString(36).slice(-4);
					}

					function escapeHtml(value) {
						return String(value == null ? '' : value)
							.replace(/&/g, '&amp;')
							.replace(/</g, '&lt;')
							.replace(/>/g, '&gt;')
							.replace(/"/g, '&quot;')
							.replace(/'/g, '&#039;');
					}

					function formatMessage(template, value) {
						var message = String(template || '');
						return message.indexOf('%d') === -1
							? message + ' ' + value
							: message.replace('%d', String(value));
					}

					function disabledAttributes(disabled) {
						return disabled ? ' disabled aria-disabled="true"' : '';
					}

					function pressedAttribute(pressed) {
						return ' aria-pressed="' + (pressed ? 'true' : 'false') + '"';
					}

					function getConfig(root) {
						var config = parseJson(root.getAttribute('data-config'), {});
						config.i18n = config.i18n || {};
						config.maxColumns = parseInt(config.maxColumns || 12, 10);
						config.maxRows = parseInt(config.maxRows || 200, 10);
						return config;
					}

					function normalizeState(state, config) {
						state = state && typeof state === 'object' ? state : {};
						state.mode = state.mode === 'compare' ? 'compare' : 'table';
						state.columns = Array.isArray(state.columns) && state.columns.length ? state.columns : [
							{ id: 'col_1', label: state.mode === 'compare' ? config.i18n.featureColumn : 'Title 1' },
							{ id: 'col_2', label: state.mode === 'compare' ? config.i18n.compareColumn + ' 1' : 'Title 2' }
						];
							state.columns = state.columns.slice(0, config.maxColumns).map(function (column, index) {
								var align = ['left', 'center', 'right'].indexOf(column.align) !== -1
									? column.align
									: (state.mode === 'compare' && index > 0 ? 'center' : 'left');
								return {
									id: column.id || 'col_' + (index + 1),
									label: column.label || '',
									width: Math.max(1, Math.min(12, parseInt(column.width || 1, 10) || 1)),
									align: align
								};
							});
						state.rows = Array.isArray(state.rows) ? state.rows.slice(0, config.maxRows) : [];
						state.rows = state.rows.map(function (row, rowIndex) {
							var cells = Array.isArray(row.cells) ? row.cells : [];
							return {
								id: row.id || 'row_' + (rowIndex + 1),
									cells: state.columns.map(function (column, columnIndex) {
										var cell = cells[columnIndex] || {};
										var type = ['text', 'check', 'cross', 'empty'].indexOf(cell.type) !== -1 ? cell.type : 'text';
										if (state.mode === 'table' || columnIndex === 0) {
											type = 'text';
										} else if (state.mode === 'compare') {
											type = ['cross', 'empty'].indexOf(type) !== -1 ? 'cross' : 'check';
										}
										return {
											type: type,
										text: cell.text || ''
									};
								})
							};
						});
						return state;
					}

					function readState(root) {
						var input = root.querySelector('.sp-acf-table__input');
						var config = getConfig(root);
						return normalizeState(parseJson(input ? input.value : '', {}), config);
					}

					function writeState(root, state) {
						var input = root.querySelector('.sp-acf-table__input');
						if (input) {
							input.value = JSON.stringify(state);
							input.dispatchEvent(new Event('input', { bubbles: true }));
							input.dispatchEvent(new Event('change', { bubbles: true }));
						}
					}

					function makeCellTools(cell) {
						var types = [
							{ type: 'text', label: 'T' },
							{ type: 'check', label: '✓' },
							{ type: 'cross', label: '−' },
							{ type: 'empty', label: '∅' }
						];
						return '<div class="sp-acf-table__cell-tools">' + types.map(function (item) {
							var active = cell.type === item.type ? ' is-active' : '';
							return '<button type="button" class="sp-acf-table__mini' + active + '" data-action="cell-type" data-type="' + item.type + '"' + pressedAttribute(cell.type === item.type) + '>' + item.label + '</button>';
						}).join('') + '</div>';
					}

					function booleanIcon(type) {
						return booleanIcons[type === 'cross' ? 'cross' : 'check'] || '';
					}

					function makeBooleanTools(cell, i18n) {
						var types = [
							{ type: 'check', label: booleanIcon('check'), title: i18n.check || 'Check' },
							{ type: 'cross', label: booleanIcon('cross'), title: i18n.cross || 'No' }
						];
						return '<div class="sp-acf-table__cell-tools">' + types.map(function (item) {
							var active = cell.type === item.type ? ' is-active' : '';
							return '<button type="button" class="sp-acf-table__mini' + active + '" data-action="cell-type" data-type="' + item.type + '" aria-label="' + escapeHtml(item.title) + '" title="' + escapeHtml(item.title) + '"' + pressedAttribute(cell.type === item.type) + '>' + item.label + '</button>';
						}).join('') + '</div>';
					}

					function alignToFlex(align) {
						if (align === 'right') {
							return 'flex-end';
						}
						if (align === 'center') {
							return 'center';
						}
						return 'flex-start';
					}

					function makeAlignTools(column, i18n) {
						var options = [
							{ align: 'left', label: 'L', title: i18n.alignLeft || 'Align left' },
							{ align: 'center', label: 'C', title: i18n.alignCenter || 'Align center' },
							{ align: 'right', label: 'R', title: i18n.alignRight || 'Align right' }
						];

						return options.map(function (option) {
							var active = column.align === option.align ? ' is-active' : '';
							return '<button type="button" class="sp-acf-table__mini' + active + '" data-action="column-align" data-align="' + option.align + '" aria-label="' + escapeHtml(option.title) + '" title="' + escapeHtml(option.title) + '"' + pressedAttribute(column.align === option.align) + '>' + option.label + '</button>';
						}).join('');
					}

					function renderToolbar(root, state, config) {
						var i18n = config.i18n;
						var toolbar = root.querySelector('.sp-acf-table__toolbar');
						var atMaxColumns = state.columns.length >= config.maxColumns;
						var atMaxRows = state.rows.length >= config.maxRows;
						var atMinColumns = state.columns.length <= 1;
						var hasRows = state.rows.length > 0;
						toolbar.innerHTML =
							'<div class="sp-acf-table__toolbar-label">' +
								'<span>' + escapeHtml(i18n.mode || 'Mode') + '</span>' +
								'<span class="sp-acf-table__mode-switcher" role="group" aria-label="' + escapeHtml(i18n.mode || 'Mode') + '">' +
									'<button type="button" class="sp-acf-table__mode-option' + (state.mode === 'table' ? ' is-active' : '') + '" data-action="mode" data-mode="table"' + pressedAttribute(state.mode === 'table') + '>' + escapeHtml(i18n.table || 'Simple table') + '</button>' +
									'<button type="button" class="sp-acf-table__mode-option' + (state.mode === 'compare' ? ' is-active' : '') + '" data-action="mode" data-mode="compare"' + pressedAttribute(state.mode === 'compare') + '>' + escapeHtml(i18n.compare || 'Comparison table') + '</button>' +
								'</span>' +
							'</div>' +
							'<button type="button" class="sp-acf-table__button" data-action="add-column"' + disabledAttributes(atMaxColumns) + '>+ ' + escapeHtml(i18n.addColumn || 'Add column') + '</button>' +
							'<button type="button" class="sp-acf-table__button" data-action="add-row"' + disabledAttributes(atMaxRows) + '>+ ' + escapeHtml(i18n.addRow || 'Add row') + '</button>' +
							'<button type="button" class="sp-acf-table__button sp-acf-table__button--danger" data-action="remove-last-column"' + disabledAttributes(atMinColumns) + '>− ' + escapeHtml(i18n.removeColumn || 'Remove column') + '</button>' +
							'<button type="button" class="sp-acf-table__button sp-acf-table__button--danger" data-action="remove-last-row"' + disabledAttributes(!hasRows) + '>− ' + escapeHtml(i18n.removeRow || 'Remove row') + '</button>';
					}

					function renderStatePreview(type, i18n) {
						var nextType = type === 'cross' ? 'check' : 'cross';
						var label = type === 'cross' ? (i18n.cross || 'No') : (i18n.check || 'Check');
						return '<button type="button" class="sp-acf-table__state-button" data-action="toggle-bool" data-type="' + nextType + '" aria-label="' + escapeHtml(label) + '"' + pressedAttribute(type === 'check') + '>' +
							'<span class="sp-acf-table__state-preview sp-acf-table__state-preview--' + type + '">' + booleanIcon(type) + '</span>' +
						'</button>';
					}

					function renderGrid(root, state, config) {
						var i18n = config.i18n;
						var stage = root.querySelector('.sp-acf-table__stage');
						var columns = state.columns.length;
						var gridClass = 'sp-acf-table__grid';
						if (state.mode === 'compare') {
							gridClass += ' sp-acf-table__grid--compare';
						}
						var templateColumns = state.columns.map(function (column) {
							return 'minmax(160px, ' + Math.max(1, Math.min(12, parseInt(column.width || 1, 10) || 1)) + 'fr)';
						}).join(' ');
						var html = '<div class="' + gridClass + '" style="grid-template-columns: ' + templateColumns + ';">';

						state.columns.forEach(function (column, columnIndex) {
							var width = Math.max(1, Math.min(12, parseInt(column.width || 1, 10) || 1));
							html += '<div class="sp-acf-table__head" data-col="' + columnIndex + '">' +
								'<input class="sp-acf-table__head-input" data-bind="column-label" data-col="' + columnIndex + '" value="' + escapeHtml(column.label) + '" placeholder="' + escapeHtml((i18n.column || 'Column') + ' ' + (columnIndex + 1)) + '" style="text-align: ' + column.align + ';">' +
								'<div class="sp-acf-table__head-tools">' +
									'<button type="button" class="sp-acf-table__mini" data-action="decrease-column-width" aria-label="' + escapeHtml(i18n.narrower || 'Narrower') + '" title="' + escapeHtml(i18n.narrower || 'Narrower') + '"' + disabledAttributes(width <= 1) + '>−</button>' +
									'<button type="button" class="sp-acf-table__mini" data-action="increase-column-width" aria-label="' + escapeHtml(i18n.wider || 'Wider') + '" title="' + escapeHtml(i18n.wider || 'Wider') + '"' + disabledAttributes(width >= 12) + '>+</button>' +
									makeAlignTools(column, i18n) +
									'<button type="button" class="sp-acf-table__mini" data-action="move-column-left" aria-label="' + escapeHtml(i18n.moveLeft || 'Move left') + '" title="' + escapeHtml(i18n.moveLeft || 'Move left') + '"' + disabledAttributes(columnIndex === 0) + '>‹</button>' +
									'<button type="button" class="sp-acf-table__mini" data-action="move-column-right" aria-label="' + escapeHtml(i18n.moveRight || 'Move right') + '" title="' + escapeHtml(i18n.moveRight || 'Move right') + '"' + disabledAttributes(columnIndex === columns - 1) + '>›</button>' +
									'<button type="button" class="sp-acf-table__mini sp-acf-table__mini--danger" data-action="remove-column" aria-label="' + escapeHtml(i18n.removeColumn || 'Remove column') + '" title="' + escapeHtml(i18n.removeColumn || 'Remove column') + '"' + disabledAttributes(columns <= 1) + '>×</button>' +
								'</div>' +
							'</div>';
						});

						if (!state.rows.length) {
							html += '<div class="sp-acf-table__empty" style="grid-column: 1 / -1;">' + escapeHtml(i18n.noRows || 'No rows yet.') + '</div>';
						}

							state.rows.forEach(function (row, rowIndex) {
								row.cells.forEach(function (cell, columnIndex) {
									var columnAlign = state.columns[columnIndex] ? state.columns[columnIndex].align : 'left';
									var stateCell = cell.type !== 'text';
									var rowStart = columnIndex === 0 ? ' sp-acf-table__row-start' : '';
									html += '<div class="sp-acf-table__cell' + rowStart + (stateCell ? ' sp-acf-table__cell--state' : '') + '" data-row="' + rowIndex + '" data-col="' + columnIndex + '" style="text-align: ' + columnAlign + ';' + (stateCell ? ' justify-content: ' + alignToFlex(columnAlign) + ';' : '') + '">';

								if (columnIndex === 0) {
									html += '<div class="sp-acf-table__row-tools">' +
										'<button type="button" class="sp-acf-table__mini" data-action="move-row-up" aria-label="' + escapeHtml(i18n.moveUp || 'Move up') + '" title="' + escapeHtml(i18n.moveUp || 'Move up') + '"' + disabledAttributes(rowIndex === 0) + '>↑</button>' +
										'<button type="button" class="sp-acf-table__mini" data-action="move-row-down" aria-label="' + escapeHtml(i18n.moveDown || 'Move down') + '" title="' + escapeHtml(i18n.moveDown || 'Move down') + '"' + disabledAttributes(rowIndex === state.rows.length - 1) + '>↓</button>' +
										'<button type="button" class="sp-acf-table__mini sp-acf-table__mini--danger" data-action="remove-row" aria-label="' + escapeHtml(i18n.removeRow || 'Remove row') + '" title="' + escapeHtml(i18n.removeRow || 'Remove row') + '">×</button>' +
									'</div>';
								}

									if (stateCell) {
										html += renderStatePreview(cell.type, i18n);
									} else {
										html += '<textarea class="sp-acf-table__cell-text" data-bind="cell-text" data-row="' + rowIndex + '" data-col="' + columnIndex + '" placeholder="' + escapeHtml(i18n.cellText || 'Cell text') + '" style="text-align: ' + columnAlign + ';">' + escapeHtml(cell.text) + '</textarea>';
									}

									if (state.mode === 'compare' && columnIndex > 0) {
										html += makeBooleanTools(cell, i18n);
									}

								html += '</div>';
							});
						});

						html += '</div>';
						stage.innerHTML = html;
					}

					function updateStatus(root, state, config, announce) {
						var status = root.querySelector('.sp-acf-table__status');
						var i18n = config.i18n;
						var countText = formatMessage(i18n.columnsCount || 'Columns: %d', state.columns.length) +
							' · ' + formatMessage(i18n.rowsCount || 'Rows: %d', state.rows.length);

						root.setAttribute('data-columns', String(state.columns.length));
						root.setAttribute('data-rows', String(state.rows.length));
						if (status) {
							status.textContent = announce && i18n.tableUpdated
								? i18n.tableUpdated + ' ' + countText
								: countText;
						}
					}

					function render(root, announce) {
						var config = getConfig(root);
						var state = readState(root);
						root.setAttribute('data-mode', state.mode);
						renderToolbar(root, state, config);
						renderGrid(root, state, config);
						updateStatus(root, state, config, Boolean(announce));
						writeState(root, state);
					}

					function captureFocus(button, action, row, col) {
						if (document.activeElement !== button) {
							return null;
						}

						return {
							action: action,
							row: row,
							col: col,
							type: action === 'cell-type' ? (button.getAttribute('data-type') || '') : '',
							align: button.getAttribute('data-align') || '',
							mode: button.getAttribute('data-mode') || '',
							scope: button.closest('.sp-acf-table__head') ? 'head' : (button.closest('.sp-acf-table__cell') ? 'cell' : 'toolbar')
						};
					}

					function restoreFocus(root, focus, state) {
						if (!focus) {
							return;
						}

						var row = focus.row;
						var col = focus.col;
						if (focus.action === 'move-row-up') {
							row = Math.max(0, row - 1);
						} else if (focus.action === 'move-row-down') {
							row = Math.min(state.rows.length - 1, row + 1);
						} else if (focus.action === 'remove-row') {
							row = Math.max(0, Math.min(row, state.rows.length - 1));
						} else if (focus.action === 'move-column-left') {
							col = Math.max(0, col - 1);
						} else if (focus.action === 'move-column-right') {
							col = Math.min(state.columns.length - 1, col + 1);
						} else if (focus.action === 'remove-column') {
							col = Math.max(0, Math.min(col, state.columns.length - 1));
						}

						var selectorParts = '[data-action="' + focus.action + '"]';
						if (focus.type) {
							selectorParts += '[data-type="' + focus.type + '"]';
						}
						if (focus.align) {
							selectorParts += '[data-align="' + focus.align + '"]';
						}
						if (focus.mode) {
							selectorParts += '[data-mode="' + focus.mode + '"]';
						}

						var context = root;
						if (focus.scope === 'head' && state.columns.length) {
							context = root.querySelector('.sp-acf-table__head[data-col="' + col + '"]') || root;
						} else if (focus.scope === 'cell' && state.rows.length && state.columns.length) {
							context = root.querySelector('.sp-acf-table__cell[data-row="' + row + '"][data-col="' + col + '"]') || root;
						}

						var target = context.querySelector(selectorParts) || root.querySelector(selectorParts);
						if (!target || target.disabled) {
							return;
						}

						window.requestAnimationFrame(function () {
							try {
								target.focus({ preventScroll: true });
							} catch (error) {
								target.focus();
							}
						});
					}

					function setMode(state, mode, config) {
						state.mode = mode === 'compare' ? 'compare' : 'table';
						if (state.mode === 'compare' && state.columns.length < 2) {
							state.columns.push({ id: uid('col'), label: (config.i18n.compareColumn || 'Compare') + ' 1', width: 1, align: 'center' });
						}
						if (state.mode === 'compare') {
							state.columns.forEach(function (column, index) {
								if (!column.label) {
									column.label = index === 0 ? (config.i18n.featureColumn || 'Feature') : (config.i18n.compareColumn || 'Compare') + ' ' + index;
								}
								if (!column.align) {
									column.align = index === 0 ? 'left' : 'center';
								}
							});
						}
							state.rows.forEach(function (row) {
								row.cells.forEach(function (cell, index) {
									if (state.mode === 'table' || index === 0) {
										cell.type = 'text';
									} else if (state.mode === 'compare') {
										cell.type = ['cross', 'empty'].indexOf(cell.type) !== -1 ? 'cross' : 'check';
									}
								});
							});
					}

					function swap(items, a, b) {
						if (a < 0 || b < 0 || a >= items.length || b >= items.length) {
							return;
						}
						var temp = items[a];
						items[a] = items[b];
						items[b] = temp;
					}

					function bind(root) {
						if (!root) {
							return;
						}
						if (boundTables && boundTables.has(root)) {
							return;
						}
						if (boundTables) {
							boundTables.add(root);
						} else if (root._spAcfTableReady) {
							return;
						} else {
							root._spAcfTableReady = true;
						}
						render(root, false);

						root.addEventListener('input', function (event) {
							var target = event.target;
							var state = readState(root);

							if (target.matches('[data-bind="column-label"]')) {
								state.columns[parseInt(target.getAttribute('data-col'), 10)].label = target.value;
								writeState(root, state);
							}

							if (target.matches('[data-bind="cell-text"]')) {
								var row = parseInt(target.getAttribute('data-row'), 10);
								var col = parseInt(target.getAttribute('data-col'), 10);
								state.rows[row].cells[col].text = target.value;
								writeState(root, state);
							}
						});

						root.addEventListener('click', function (event) {
							var button = event.target.closest('[data-action]');
							if (!button || !root.contains(button) || button.matches('select') || button.disabled || button.getAttribute('aria-disabled') === 'true') {
								return;
							}

							var config = getConfig(root);
							var state = readState(root);
							var action = button.getAttribute('data-action');
							var cellWrap = button.closest('.sp-acf-table__cell, .sp-acf-table__head');
							var row = cellWrap ? parseInt(cellWrap.getAttribute('data-row') || '0', 10) : 0;
							var col = cellWrap ? parseInt(cellWrap.getAttribute('data-col') || '0', 10) : 0;
							var focus = captureFocus(button, action, row, col);

							if (action === 'mode') {
								setMode(state, button.getAttribute('data-mode'), config);
							}

							if (action === 'add-column' && state.columns.length < config.maxColumns) {
								state.columns.push({
									id: uid('col'),
									label: state.mode === 'compare' ? (config.i18n.compareColumn || 'Compare') + ' ' + state.columns.length : 'Title ' + (state.columns.length + 1),
									width: 1,
									align: state.mode === 'compare' ? 'center' : 'left'
								});
								state.rows.forEach(function (item) {
									item.cells.push({ type: state.mode === 'compare' ? 'check' : 'text', text: '' });
								});
							}

							if (action === 'remove-last-column' && state.columns.length > 1) {
								state.columns.pop();
								state.rows.forEach(function (item) {
									item.cells.pop();
								});
							}

							if (action === 'decrease-column-width' && state.columns[col]) {
								state.columns[col].width = Math.max(1, (parseInt(state.columns[col].width || 1, 10) || 1) - 1);
							}

							if (action === 'increase-column-width' && state.columns[col]) {
								state.columns[col].width = Math.min(12, (parseInt(state.columns[col].width || 1, 10) || 1) + 1);
							}

							if (action === 'column-align' && state.columns[col]) {
								var align = button.getAttribute('data-align') || 'left';
								state.columns[col].align = ['left', 'center', 'right'].indexOf(align) !== -1 ? align : 'left';
							}

							if (action === 'add-row' && state.rows.length < config.maxRows) {
								state.rows.push({
									id: uid('row'),
									cells: state.columns.map(function (column, index) {
										return { type: state.mode === 'compare' && index > 0 ? 'check' : 'text', text: '' };
									})
								});
							}

							if (action === 'remove-last-row' && state.rows.length) {
								state.rows.pop();
							}

							if (action === 'remove-column' && state.columns.length > 1) {
								state.columns.splice(col, 1);
								state.rows.forEach(function (item) {
									item.cells.splice(col, 1);
								});
							}

							if (action === 'move-column-left') {
								swap(state.columns, col, col - 1);
								state.rows.forEach(function (item) {
									swap(item.cells, col, col - 1);
								});
							}

							if (action === 'move-column-right') {
								swap(state.columns, col, col + 1);
								state.rows.forEach(function (item) {
									swap(item.cells, col, col + 1);
								});
							}

							if (action === 'remove-row') {
								state.rows.splice(row, 1);
							}

							if (action === 'move-row-up') {
								swap(state.rows, row, row - 1);
							}

							if (action === 'move-row-down') {
								swap(state.rows, row, row + 1);
							}

							if ((action === 'cell-type' || action === 'toggle-bool') && state.rows[row] && state.rows[row].cells[col]) {
								state.rows[row].cells[col].type = button.getAttribute('data-type') === 'cross' ? 'cross' : 'check';
							}

							setMode(state, state.mode, config);
							writeState(root, state);
							render(root, true);
							restoreFocus(root, focus, readState(root));
						});
					}

					function init(scope) {
						scope = scope && scope.querySelectorAll ? scope : document;
						if (scope.matches && scope.matches(selector)) {
							bind(scope);
						}
						scope.querySelectorAll(selector).forEach(bind);
					}

					if (document.readyState === 'loading') {
						document.addEventListener('DOMContentLoaded', function () { init(document); });
					} else {
						init(document);
					}

					if (window.acf && typeof window.acf.addAction === 'function') {
						window.acf.addAction('append', function ($el) {
							var node = $el && $el[0] ? $el[0] : document;
							init(node);
						});
						window.acf.addAction('new_field/type=sp_acf_table', function (field) {
							var node = field && field.$el && field.$el[0] ? field.$el[0] : document;
							init(node);
						});
					}

					if (typeof MutationObserver === 'function') {
						new MutationObserver(function (mutations) {
							mutations.forEach(function (mutation) {
								mutation.addedNodes.forEach(function (node) {
									if (node && node.nodeType === 1) {
										init(node);
									}
								});
							});
						}).observe(document.documentElement, {
							childList: true,
							subtree: true
						});
					}
				})();
			</script>
			<?php
		}
	}

	acf_register_field_type('SP_ACF_Field_Table');
});
