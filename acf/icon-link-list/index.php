<?php
/**
 * ACF Field Type: SP Icon Link List
 *
 * Compact sortable icon + native WP/ACF link picker list.
 */

if (! defined('ABSPATH')) {
	exit;
}

if (! function_exists('sp_acf_icon_link_list_normalize')) {
	function sp_acf_icon_link_list_normalize($value, array $field = []): array
	{
		if (is_string($value)) {
			$decoded = json_decode($value, true);
			if (! is_array($decoded)) {
				$decoded = json_decode(wp_unslash($value), true);
			}
			$value = is_array($decoded) ? $decoded : [];
		}

		if (! is_array($value)) {
			return [];
		}

		if (isset($value['items']) && is_array($value['items'])) {
			$value = $value['items'];
		} elseif (isset($value['rows']) && is_array($value['rows'])) {
			$value = $value['rows'];
		} elseif (isset($value['link']) || isset($value['icon']) || isset($value['icon_id'])) {
			$value = [$value];
		}

		$max_items = max(0, absint($field['max_items'] ?? 0));
		$items     = [];

		foreach ($value as $row) {
			if (! is_array($row)) {
				continue;
			}

			$icon = $row['icon'] ?? null;
			if (is_array($icon)) {
				$icon_id = absint($icon['ID'] ?? $icon['id'] ?? 0);
			} else {
				$icon_id = absint($row['icon_id'] ?? $icon ?? 0);
			}

			$link   = is_array($row['link'] ?? null) ? $row['link'] : [];
			$url    = esc_url_raw(trim((string) ($link['url'] ?? $row['url'] ?? '')));
			$title  = sanitize_text_field((string) ($link['title'] ?? $row['title'] ?? ''));
			$target = sanitize_key((string) ($link['target'] ?? $row['target'] ?? '_self'));
			$target = in_array($target, ['_self', '_blank'], true) ? $target : '_self';

			if ($icon_id <= 0 && $url === '' && $title === '') {
				continue;
			}

			$items[] = [
				'icon_id' => $icon_id,
				'link'    => [
					'url'    => $url,
					'title'  => $title,
					'target' => $target,
				],
			];

			if ($max_items > 0 && count($items) >= $max_items) {
				break;
			}
		}

		return $items;
	}
}

if (! function_exists('sp_acf_icon_link_list_format')) {
	function sp_acf_icon_link_list_format($value, array $field = []): array
	{
		$items = sp_acf_icon_link_list_normalize($value, $field);

		foreach ($items as &$item) {
			$icon_id = absint($item['icon_id'] ?? 0);
			$url     = $icon_id ? wp_get_attachment_url($icon_id) : '';

			if (! $url && $icon_id) {
				$url = wp_get_attachment_image_url($icon_id, 'thumbnail');
			}

			$item['icon'] = $icon_id && $url ? [
				'ID'        => $icon_id,
				'id'        => $icon_id,
				'url'       => $url,
				'alt'       => (string) get_post_meta($icon_id, '_wp_attachment_image_alt', true),
				'title'     => get_the_title($icon_id),
				'mime_type' => get_post_mime_type($icon_id),
			] : null;
		}
		unset($item);

		return $items;
	}
}

if (! function_exists('sp_icon_link_list')) {
	function sp_icon_link_list(string $name, array $args = []): StoutLogic\AcfBuilder\FieldsBuilder
	{
		$builder = new StoutLogic\AcfBuilder\FieldsBuilder($name);
		$builder->addField($name, 'sp_icon_link_list', $args);

		return $builder;
	}
}

add_action('acf/include_field_types', function (): void {
	if (! class_exists('acf_field') || class_exists('SP_ACF_Field_Icon_Link_List')) {
		return;
	}

	class SP_ACF_Field_Icon_Link_List extends acf_field
	{
		private static bool $assets_hooked = false;

		public function initialize(): void
		{
			$this->name     = 'sp_icon_link_list';
			$this->label    = __('Icon Link List', 'targetized');
			$this->category = 'content';
			$this->defaults = [
				'max_items'    => 0,
				'button_label' => __('Add item', 'targetized'),
			];
		}

		public function render_field_settings(array $field): void
		{
			acf_render_field_setting($field, [
				'label'        => __('Max Items', 'targetized'),
				'instructions' => __('Leave 0 for unlimited items.', 'targetized'),
				'type'         => 'number',
				'name'         => 'max_items',
				'min'          => 0,
			]);

			acf_render_field_setting($field, [
				'label' => __('Button Label', 'targetized'),
				'type'  => 'text',
				'name'  => 'button_label',
			]);
		}

		public function render_field(array $field): void
		{
			$value        = sp_acf_icon_link_list_format($field['value'] ?? [], $field);
			$input_value  = wp_json_encode(sp_acf_icon_link_list_normalize($value, $field), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			$max_items    = max(0, absint($field['max_items'] ?? 0));
			$button_label = (string) ($field['button_label'] ?? __('Add item', 'targetized'));

			if ($button_label === '') {
				$button_label = __('Add item', 'targetized');
			}

			?>
			<div
				class="sp-icon-link-list sp-admin-component sp-acf-component<?php echo empty($value) ? ' is-empty' : ' is-filled'; ?>"
				data-sp-admin-component
				data-sp-icon-link-list
				data-max-items="<?php echo esc_attr((string) $max_items); ?>"
				data-label-drag="<?php echo esc_attr__('Drag item', 'targetized'); ?>"
				data-label-select-icon="<?php echo esc_attr__('Select icon', 'targetized'); ?>"
				data-label-remove-icon="<?php echo esc_attr__('Remove icon', 'targetized'); ?>"
				data-label-remove-item="<?php echo esc_attr__('Remove item', 'targetized'); ?>"
				data-label-select-link="<?php echo esc_attr__('Select Link', 'acf'); ?>"
				data-label-external="<?php echo esc_attr__('Opens in a new window/tab', 'acf'); ?>"
				data-label-edit="<?php echo esc_attr__('Edit', 'acf'); ?>"
				data-label-remove="<?php echo esc_attr__('Remove', 'acf'); ?>"
				data-label-media-title="<?php echo esc_attr__('Select icon', 'targetized'); ?>"
				data-label-media-button="<?php echo esc_attr__('Use icon', 'targetized'); ?>"
				data-status-moving="<?php echo esc_attr__('Moving item.', 'targetized'); ?>"
				data-status-reordered="<?php echo esc_attr__('Item order updated.', 'targetized'); ?>"
				data-status-added="<?php echo esc_attr__('Item added.', 'targetized'); ?>"
				data-status-removed="<?php echo esc_attr__('Item removed.', 'targetized'); ?>"
				data-status-icon-selected="<?php echo esc_attr__('Icon selected.', 'targetized'); ?>"
				data-status-icon-removed="<?php echo esc_attr__('Icon removed.', 'targetized'); ?>"
				data-status-link-updated="<?php echo esc_attr__('Link updated.', 'targetized'); ?>"
				data-status-link-removed="<?php echo esc_attr__('Link removed.', 'targetized'); ?>"
				aria-busy="false"
			>
				<input
					type="hidden"
					name="<?php echo esc_attr($field['name']); ?>"
					value="<?php echo esc_attr((string) $input_value); ?>"
					data-sp-icon-link-input
				>

				<div class="sp-icon-link-list__rows" data-sp-icon-link-rows role="list">
					<?php foreach ($value as $item) : ?>
						<?php self::render_row($item); ?>
					<?php endforeach; ?>
				</div>

				<div class="sp-icon-link-list__empty" data-sp-icon-link-empty role="status">
					<span class="dashicons dashicons-admin-links" aria-hidden="true"></span>
					<?php esc_html_e('No links yet.', 'targetized'); ?>
				</div>

				<button type="button" class="button button-primary sp-icon-link-list__add" data-sp-icon-link-add>
					<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
					<?php echo esc_html($button_label); ?>
				</button>

				<p class="screen-reader-text" data-sp-icon-link-status role="status" aria-live="polite" aria-atomic="true"></p>
			</div>
			<?php
		}

		public function update_value($value, $post_id, $field)
		{
			return sp_acf_icon_link_list_normalize($value, is_array($field) ? $field : []);
		}

		public function format_value($value, $post_id, $field)
		{
			return sp_acf_icon_link_list_format($value, is_array($field) ? $field : []);
		}

		public function validate_value($valid, $value, $field, $input)
		{
			if ($valid !== true) {
				return $valid;
			}

			$items = sp_acf_icon_link_list_normalize($value, is_array($field) ? $field : []);

			if (! empty($field['required']) && empty($items)) {
				return __('Please add at least one icon link item.', 'targetized');
			}

			return $valid;
		}

		public function input_admin_enqueue_scripts(): void
		{
			wp_enqueue_media();
			wp_enqueue_script('jquery-ui-sortable');
			wp_enqueue_script('wplink');
			wp_enqueue_style('editor-buttons');

			if (self::$assets_hooked) {
				return;
			}

			self::$assets_hooked = true;
			add_action('admin_footer', [__CLASS__, 'print_assets']);
		}

		private static function render_row(array $item = []): void
		{
			$icon_id  = absint($item['icon_id'] ?? 0);
			$icon     = is_array($item['icon'] ?? null) ? $item['icon'] : [];
			$icon_url = (string) ($icon['url'] ?? '');
			$link     = is_array($item['link'] ?? null) ? $item['link'] : [];
			$url      = (string) ($link['url'] ?? '');
			$title    = (string) ($link['title'] ?? '');
			$target   = (string) ($link['target'] ?? '_self');
			$target   = in_array($target, ['_self', '_blank'], true) ? $target : '_self';
			?>
			<div class="sp-icon-link-list__row" data-sp-icon-link-row role="listitem">
				<button type="button" class="sp-icon-link-list__drag" data-sp-icon-link-drag aria-label="<?php echo esc_attr__('Drag item', 'targetized'); ?>">
					<span class="dashicons dashicons-menu" aria-hidden="true"></span>
				</button>

				<div class="sp-icon-link-list__icon">
					<input type="hidden" value="<?php echo esc_attr((string) $icon_id); ?>" data-sp-icon-id>
					<button type="button" class="sp-icon-link-list__preview" data-sp-icon-select aria-label="<?php echo esc_attr__('Select icon', 'targetized'); ?>">
						<?php if ($icon_url !== '') : ?>
							<img src="<?php echo esc_url($icon_url); ?>" alt="">
						<?php else : ?>
							<span class="dashicons dashicons-format-image"></span>
						<?php endif; ?>
					</button>
					<button type="button" class="sp-icon-link-list__remove-icon<?php echo $icon_id ? '' : ' is-hidden'; ?>" data-sp-icon-remove aria-label="<?php echo esc_attr__('Remove icon', 'targetized'); ?>">
						<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
					</button>
				</div>

				<div class="sp-icon-link-list__link-field acf-field-link default">
					<?php self::render_link_control($url, $title, $target); ?>
				</div>

				<button type="button" class="sp-icon-link-list__remove-row" data-sp-icon-link-remove aria-label="<?php echo esc_attr__('Remove item', 'targetized'); ?>">
					<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
				</button>
			</div>
			<?php
		}

		private static function render_link_control(string $url = '', string $title = '', string $target = '_self'): void
		{
			?>
			<div class="acf-link sp-icon-link-list__link<?php echo $url !== '' ? ' -value' : ''; ?><?php echo $target === '_blank' ? ' -external' : ''; ?>" data-sp-link-control>
				<div class="acf-hidden">
					<a class="link-node" href="<?php echo esc_url($url); ?>" target="<?php echo esc_attr($target); ?>" data-sp-link-node><?php echo esc_html($title); ?></a>
					<input type="hidden" class="input-url" value="<?php echo esc_attr($url); ?>" data-sp-link-url>
					<input type="hidden" class="input-title" value="<?php echo esc_attr($title); ?>" data-sp-link-title>
					<input type="hidden" class="input-target" value="<?php echo esc_attr($target); ?>" data-sp-link-target>
				</div>

				<a href="#" class="button" data-sp-link-edit target=""><?php esc_html_e('Select Link', 'acf'); ?></a>

				<div class="link-wrap">
					<span class="link-title" data-sp-link-title-preview><?php echo esc_html($title); ?></span>
					<a class="link-url" href="<?php echo esc_url($url); ?>" target="_blank" data-sp-link-url-preview><?php echo esc_html($url); ?></a>
					<i class="acf-icon -link-ext acf-js-tooltip" title="<?php echo esc_attr__('Opens in a new window/tab', 'acf'); ?>"></i>
					<a class="acf-icon -pencil -clear acf-js-tooltip" data-sp-link-edit href="#" title="<?php echo esc_attr__('Edit', 'acf'); ?>"></a>
					<a class="acf-icon -cancel -clear acf-js-tooltip" data-sp-link-clear href="#" title="<?php echo esc_attr__('Remove', 'acf'); ?>"></a>
				</div>
			</div>
			<?php
		}

		public static function print_assets(): void
		{
			if (class_exists('_WP_Editors')) {
				_WP_Editors::wp_link_dialog();
			}
			?>
			<style id="sp-acf-icon-link-list-css">
				.sp-icon-link-list {
					background: var(--sp-acf-surface);
					border: 1px solid var(--sp-acf-border);
					border-radius: var(--sp-acf-radius);
					box-shadow: var(--sp-acf-shadow);
					color: var(--sp-acf-text);
					container-type: inline-size;
					max-width: 100%;
					overflow: hidden;
					padding: 12px;
					position: relative;
				}

				.sp-icon-link-list.is-loading::before {
					animation: sp-icon-link-list-loading .9s ease-in-out infinite;
					background: linear-gradient(90deg, transparent, var(--sp-acf-accent-bright), transparent);
					content: "";
					height: 2px;
					inset: 0 0 auto;
					position: absolute;
					transform: translateX(-100%);
					z-index: 3;
				}

				.sp-icon-link-list.is-loading > :not([data-sp-icon-link-input], [data-sp-icon-link-status]) {
					opacity: .62;
					pointer-events: none;
				}

				@keyframes sp-icon-link-list-loading {
					to {
						transform: translateX(100%);
					}
				}

				.sp-icon-link-list__rows {
					display: grid;
					gap: 8px;
				}

				.sp-icon-link-list.is-filled .sp-icon-link-list__rows {
					margin-bottom: 12px;
				}

				.sp-icon-link-list__row {
					align-items: center;
					background: var(--sp-acf-surface-soft);
					border: 1px solid var(--sp-acf-border);
					border-radius: var(--sp-acf-radius);
					display: grid;
					gap: 12px;
					grid-template-columns: 76px minmax(0, 1fr);
					min-width: 0;
					padding: 10px;
					position: relative;
					transition: background var(--sp-acf-transition), border-color var(--sp-acf-transition), box-shadow var(--sp-acf-transition), opacity var(--sp-acf-transition);
				}

				.sp-icon-link-list__row:hover {
					border-color: var(--sp-acf-border-strong);
				}

				.sp-icon-link-list__row:focus-within {
					border-color: var(--sp-acf-accent);
					box-shadow: var(--sp-acf-focus);
				}

				.sp-icon-link-list__row.is-filled {
					background: var(--sp-acf-surface);
					border-color: var(--sp-acf-border-strong);
				}

				.sp-icon-link-list__row.is-dragging,
				.sp-icon-link-list__row.ui-sortable-helper {
					background: var(--sp-acf-surface);
					border-color: var(--sp-acf-accent);
					box-shadow: 0 12px 30px rgb(16 24 40 / 16%);
					opacity: .96;
					z-index: 100000;
				}

				.sp-icon-link-list.is-sorting .sp-icon-link-list__row:not(.is-dragging):not(.ui-sortable-helper) {
					opacity: .62;
				}

				.sp-icon-link-list__placeholder {
					background: var(--sp-acf-accent-soft);
					border: 1px dashed var(--sp-acf-accent);
					border-radius: var(--sp-acf-radius);
					min-height: 98px;
					transition: background var(--sp-acf-transition), border-color var(--sp-acf-transition);
				}

				.sp-icon-link-list__placeholder.is-drop-target {
					background: #e5ecff;
					border-style: solid;
				}

				.sp-icon-link-list__drag,
				.sp-icon-link-list__remove-row,
				.sp-icon-link-list__remove-icon {
					align-items: center;
					appearance: none;
					background: var(--sp-acf-surface);
					border: 1px solid var(--sp-acf-border-strong);
					border-radius: var(--sp-acf-radius);
					box-shadow: none;
					color: var(--sp-acf-text-muted);
					cursor: pointer;
					display: inline-flex;
					height: 28px;
					justify-content: center;
					padding: 0;
					transition: background var(--sp-acf-transition), border-color var(--sp-acf-transition), box-shadow var(--sp-acf-transition), color var(--sp-acf-transition), transform var(--sp-acf-transition);
					width: 28px;
				}

				.sp-icon-link-list__drag:hover {
					background: var(--sp-acf-accent-soft);
					border-color: var(--sp-acf-accent);
					color: var(--sp-acf-accent-hover);
				}

				.sp-icon-link-list__drag:focus-visible,
				.sp-icon-link-list__remove-row:focus-visible,
				.sp-icon-link-list__remove-icon:focus-visible {
					outline: 0;
				}

				.sp-icon-link-list__drag:focus-visible {
					border-color: var(--sp-acf-accent);
					box-shadow: var(--sp-acf-focus);
					color: var(--sp-acf-accent);
				}

				.sp-icon-link-list__drag,
				.sp-icon-link-list__remove-row {
					position: absolute;
					top: 0;
					z-index: 2;
				}

				.sp-icon-link-list__drag {
					cursor: grab;
					left: 0;
					transform: translate(-50%, -50%);
				}

				.sp-icon-link-list__drag:active {
					cursor: grabbing;
				}

				.sp-icon-link-list__remove-row {
					right: 0;
					transform: translate(50%, -50%);
				}

				.sp-icon-link-list__remove-row:hover,
				.sp-icon-link-list__remove-icon:hover {
					background: rgb(204 24 24 / 7%);
					border-color: var(--sp-acf-danger);
					color: var(--sp-acf-danger-hover);
				}

				.sp-icon-link-list__remove-row:focus-visible,
				.sp-icon-link-list__remove-icon:focus-visible {
					border-color: var(--sp-acf-danger);
					box-shadow: var(--sp-acf-danger-focus);
					color: var(--sp-acf-danger);
				}

				.sp-icon-link-list__drag .dashicons,
				.sp-icon-link-list__remove-row .dashicons,
				.sp-icon-link-list__remove-icon .dashicons {
					font-size: 16px;
					height: 16px;
					line-height: 16px;
					width: 16px;
				}

				.sp-icon-link-list__icon {
					position: relative;
					width: 76px;
				}

				.sp-icon-link-list__preview {
					align-items: center;
					appearance: none;
					background: var(--sp-acf-surface-soft);
					border: 1px dashed var(--sp-acf-border-strong) !important;
					border-radius: var(--sp-acf-radius);
					box-shadow: none;
					color: var(--sp-acf-text-muted);
					cursor: pointer;
					display: inline-flex;
					font: inherit;
					height: 76px;
					justify-content: center;
					line-height: 1;
					outline: 0;
					padding: 0;
					transition: background var(--sp-acf-transition), border-color var(--sp-acf-transition), box-shadow var(--sp-acf-transition), color var(--sp-acf-transition);
					width: 76px;
				}

				.sp-icon-link-list__preview:hover {
					background: var(--sp-acf-accent-soft);
					border-color: var(--sp-acf-accent) !important;
					color: var(--sp-acf-accent-hover);
				}

				.sp-icon-link-list__preview:focus-visible {
					border-color: var(--sp-acf-accent) !important;
					box-shadow: var(--sp-acf-focus);
				}

				.sp-icon-link-list__row.has-icon .sp-icon-link-list__preview {
					background: var(--sp-acf-surface);
					border-style: solid !important;
				}

				.sp-icon-link-list__preview img {
					background: transparent;
					display: block;
					height: 100%;
					object-fit: contain;
					padding: 6px;
					width: 100%;
				}

				.sp-icon-link-list__remove-icon {
					position: absolute;
					right: -7px;
					top: -7px;
				}

				.sp-icon-link-list__link-field,
				.sp-icon-link-list__link {
					min-width: 0;
					width: 100%;
				}

				.sp-icon-link-list__link > .button[data-sp-link-edit] {
					font-weight: 700;
				}

				.sp-icon-link-list__link.-value > .button[data-sp-link-edit],
				.sp-icon-link-list__link:not(.-value) .link-wrap {
					display: none;
				}

				.sp-icon-link-list__link .link-wrap {
					background: var(--sp-acf-input-bg);
					border: 1px solid var(--sp-acf-border-strong);
					border-radius: var(--sp-acf-radius);
					margin: 0;
					max-width: 100%;
					min-height: 54px;
					overflow: hidden;
					padding: 8px 72px 8px 10px;
				}

				.sp-icon-link-list__link .link-wrap:focus-within {
					border-color: var(--sp-acf-accent);
					box-shadow: var(--sp-acf-focus);
				}

				.sp-icon-link-list__link .link-title,
				.sp-icon-link-list__link .link-url {
					max-width: 100%;
					overflow: hidden;
					text-overflow: ellipsis;
					white-space: nowrap;
				}

				.sp-icon-link-list__link .link-title {
					color: var(--sp-acf-text);
					font-weight: 700;
				}

				.sp-icon-link-list__link .link-url {
					color: var(--sp-acf-text-muted);
				}

				.sp-icon-link-list__link .acf-icon.-pencil,
				.sp-icon-link-list__link .acf-icon.-cancel {
					border-radius: var(--sp-acf-radius);
				}

				.sp-icon-link-list__add.button.button-primary {
					gap: 6px;
					min-height: 38px;
					padding-inline: 16px;
				}

				.sp-icon-link-list__add .dashicons {
					font-size: 16px;
					height: 16px;
					line-height: 16px;
					width: 16px;
				}

				.sp-icon-link-list__empty {
					align-items: center;
					background: var(--sp-acf-surface-soft);
					border: 1px dashed var(--sp-acf-border-strong);
					border-radius: var(--sp-acf-radius);
					color: var(--sp-acf-text-muted);
					display: flex;
					gap: 8px;
					justify-content: center;
					margin-bottom: 12px;
					min-height: 82px;
					padding: 14px;
					text-align: center;
				}

				.sp-icon-link-list__empty .dashicons {
					color: var(--sp-acf-text-subtle);
				}

				.sp-icon-link-list__empty.is-hidden,
				.sp-icon-link-list .is-hidden {
					display: none;
				}

				body.sp-icon-link-list-wplink #wp-link-wrap .sp-link-settings-drawer,
				body.sp-icon-link-list-wplink #wp-link-wrap .wp-link-style-field,
				body.sp-icon-link-list-wplink #wp-link-wrap .wp-link-icon-pos-field,
				body.sp-icon-link-list-wplink #wp-link-wrap .wp-link-icon-field {
					display: none !important;
				}

				@container (max-width: 430px) {
					.sp-icon-link-list__row {
						align-items: stretch;
						grid-template-columns: 1fr;
					}

					.sp-icon-link-list__icon,
					.sp-icon-link-list__preview {
						width: 100%;
					}

					.sp-icon-link-list__preview {
						height: 92px;
					}

					.sp-icon-link-list__add.button.button-primary {
						width: 100%;
					}
				}

				@media (prefers-reduced-motion: reduce) {
					.sp-icon-link-list.is-loading::before {
						animation: none;
						transform: none;
					}
				}
			</style>

			<script id="sp-acf-icon-link-list-js">
					(function($) {
						'use strict';

						function copy($field, key, fallback) {
							return $field.attr('data-' + key) || fallback;
						}

						function announce($field, message) {
							var $status = $field.find('[data-sp-icon-link-status]');

							$status.removeClass('is-error').addClass('is-success').text('');
							window.setTimeout(function() {
								$status.text(message || '');
							}, 20);
						}

						function setMediaBusy($field, $control, isBusy) {
							$field
								.toggleClass('is-loading', isBusy)
								.attr('aria-busy', isBusy ? 'true' : 'false');
							$control.attr('aria-busy', isBusy ? 'true' : 'false');
						}

						function bindMediaBusy(frame, $field, $control) {
							setMediaBusy($field, $control, true);
							frame.on('close', function() {
								setMediaBusy($field, $control, false);
							});
						}

						function previewMarkup(url) {
							return url ? $('<img>', {src: url, alt: ''}) : $('<span>', {'class': 'dashicons dashicons-format-image'});
						}

						function createLinkControl($field) {
							return $('<div>', {'class': 'acf-link sp-icon-link-list__link', 'data-sp-link-control': ''})
							.append($('<div>', {'class': 'acf-hidden'})
								.append($('<a>', {'class': 'link-node', href: '', target: '_self', 'data-sp-link-node': ''}))
								.append($('<input>', {type: 'hidden', 'class': 'input-url', value: '', 'data-sp-link-url': ''}))
								.append($('<input>', {type: 'hidden', 'class': 'input-title', value: '', 'data-sp-link-title': ''}))
								.append($('<input>', {type: 'hidden', 'class': 'input-target', value: '_self', 'data-sp-link-target': ''}))
							)
								.append($('<a>', {href: '#', 'class': 'button', 'data-sp-link-edit': '', target: ''}).text(copy($field, 'label-select-link', 'Select Link')))
								.append($('<div>', {'class': 'link-wrap'})
									.append($('<span>', {'class': 'link-title', 'data-sp-link-title-preview': ''}))
									.append($('<a>', {'class': 'link-url', href: '', target: '_blank', 'data-sp-link-url-preview': ''}))
									.append($('<i>', {'class': 'acf-icon -link-ext acf-js-tooltip', title: copy($field, 'label-external', 'Opens in a new window/tab')}))
									.append($('<a>', {href: '#', 'class': 'acf-icon -pencil -clear acf-js-tooltip', 'data-sp-link-edit': '', title: copy($field, 'label-edit', 'Edit')}))
									.append($('<a>', {href: '#', 'class': 'acf-icon -cancel -clear acf-js-tooltip', 'data-sp-link-clear': '', title: copy($field, 'label-remove', 'Remove')}))
								);
						}

						function createRow($field) {
							return $('<div>', {'class': 'sp-icon-link-list__row', 'data-sp-icon-link-row': '', role: 'listitem'})
								.append($('<button>', {type: 'button', 'class': 'sp-icon-link-list__drag', 'data-sp-icon-link-drag': '', 'aria-label': copy($field, 'label-drag', 'Drag item')})
									.append($('<span>', {'class': 'dashicons dashicons-menu', 'aria-hidden': 'true'})))
								.append($('<div>', {'class': 'sp-icon-link-list__icon'})
									.append($('<input>', {type: 'hidden', value: '', 'data-sp-icon-id': ''}))
									.append($('<button>', {type: 'button', 'class': 'sp-icon-link-list__preview', 'data-sp-icon-select': '', 'aria-label': copy($field, 'label-select-icon', 'Select icon')}).append(previewMarkup('')))
									.append($('<button>', {type: 'button', 'class': 'sp-icon-link-list__remove-icon is-hidden', 'data-sp-icon-remove': '', 'aria-label': copy($field, 'label-remove-icon', 'Remove icon')})
										.append($('<span>', {'class': 'dashicons dashicons-no-alt', 'aria-hidden': 'true'}))))
								.append($('<div>', {'class': 'sp-icon-link-list__link-field acf-field-link default'}).append(createLinkControl($field)))
								.append($('<button>', {type: 'button', 'class': 'sp-icon-link-list__remove-row', 'data-sp-icon-link-remove': '', 'aria-label': copy($field, 'label-remove-item', 'Remove item')})
								.append($('<span>', {'class': 'dashicons dashicons-no-alt', 'aria-hidden': 'true'})));
					}

					function linkValueFromRow($row) {
						var $node = $row.find('[data-sp-link-node]');
						var target = $node.attr('target') || $row.find('[data-sp-link-target]').val() || '_self';

						return {
							url: $node.attr('href') || $row.find('[data-sp-link-url]').val() || '',
							title: $node.text() || $row.find('[data-sp-link-title]').val() || '',
							target: target === '_blank' ? '_blank' : '_self'
						};
					}

					function setLinkValue($row, value) {
						value = $.extend({url: '', title: '', target: '_self'}, value || {});
						value.target = value.target === '_blank' ? '_blank' : '_self';

						var $control = $row.find('[data-sp-link-control]');
						var $node = $row.find('[data-sp-link-node]');

						$control.toggleClass('-value', !!value.url).toggleClass('-external', value.target === '_blank');
						$node.text(value.title || '').attr('href', value.url || '').attr('target', value.target);
						$row.find('[data-sp-link-url]').val(value.url || '');
						$row.find('[data-sp-link-title]').val(value.title || '');
						$row.find('[data-sp-link-target]').val(value.target);
						$row.find('[data-sp-link-title-preview]').text(value.title || '');
						$row.find('[data-sp-link-url-preview]').attr('href', value.url || '').text(value.url || '');
					}

					function itemFromRow($row) {
						return {
							icon_id: parseInt($row.find('[data-sp-icon-id]').val(), 10) || 0,
							link: linkValueFromRow($row)
						};
					}

					function hasData(item) {
						return !!(item.icon_id || item.link.url || item.link.title);
					}

					function forceDefaultLinkModal() {
						var $wrap = $('#wp-link-wrap');
						$wrap.removeClass('sp-link-picker-enhanced');
						$wrap.find('.sp-link-settings-drawer').hide();
						$('#wp-link').find('.wp-link-style-field, .wp-link-icon-pos-field, .wp-link-icon-field').hide();
					}

					function sync($field) {
						var items = [];
						var maxItems = parseInt($field.data('max-items'), 10) || 0;
						var $rows = $field.find('[data-sp-icon-link-row]');

							$rows.each(function() {
								var $row = $(this);
								var item = itemFromRow($row);

								$row
									.toggleClass('has-icon', item.icon_id > 0)
									.toggleClass('has-link', !!item.link.url)
									.toggleClass('is-filled', hasData(item));

								if (hasData(item)) {
								items.push(item);
							}
						});

						var encoded = JSON.stringify(items);
						var $input = $field.find('[data-sp-icon-link-input]');
						if ($input.val() !== encoded) {
							$input.val(encoded).trigger('change');
						}

							var hasRows = $rows.length > 0;
							var isAtLimit = maxItems > 0 && $rows.length >= maxItems;

							$field.toggleClass('is-empty', !hasRows).toggleClass('is-filled', hasRows);
							$field.find('[data-sp-icon-link-empty]').toggleClass('is-hidden', hasRows).attr('aria-hidden', hasRows ? 'true' : 'false');
							$field.find('[data-sp-icon-link-add]').prop('disabled', isAtLimit).attr('aria-disabled', isAtLimit ? 'true' : 'false');
					}

					function init($scope) {
						$scope.find('[data-sp-icon-link-list]').addBack('[data-sp-icon-link-list]').each(function() {
							var $field = $(this);
							var $rows = $field.find('[data-sp-icon-link-rows]');

							$field.find('[data-sp-icon-link-row]').each(function() {
								var $row = $(this);
								setLinkValue($row, linkValueFromRow($row));
							});

							if (!$rows.data('sp-icon-link-sortable')) {
								$rows.data('sp-icon-link-sortable', true).sortable({
									axis: 'y',
									cancel: 'input,textarea,select,option,a',
									distance: 3,
									forceHelperSize: true,
									forcePlaceholderSize: true,
									handle: '[data-sp-icon-link-drag]',
									items: '[data-sp-icon-link-row]',
									placeholder: 'sp-icon-link-list__placeholder',
									start: function(event, ui) {
										$(document.body).addClass('sp-icon-link-list-is-sorting');
										$field.addClass('is-sorting');
										ui.item.addClass('is-dragging');
										ui.placeholder.addClass('is-drop-target');
										announce($field, copy($field, 'status-moving', 'Moving item.'));
									},
									change: function(event, ui) {
										ui.placeholder.addClass('is-drop-target');
									},
									stop: function(event, ui) {
										$(document.body).removeClass('sp-icon-link-list-is-sorting');
										$field.removeClass('is-sorting');
										ui.item.removeClass('is-dragging');
										sync($field);
										announce($field, copy($field, 'status-reordered', 'Item order updated.'));
									},
									update: function() {
										sync($field);
									}
								});
							}

							sync($field);
						});
					}

					$(document).on('click', '[data-sp-icon-link-add]', function(event) {
						event.preventDefault();

						var $field = $(this).closest('[data-sp-icon-link-list]');
						var maxItems = parseInt($field.data('max-items'), 10) || 0;

						if (maxItems > 0 && $field.find('[data-sp-icon-link-row]').length >= maxItems) {
							return;
						}

							var $row = createRow($field);
							$field.find('[data-sp-icon-link-rows]').append($row);
							init($field);
							announce($field, copy($field, 'status-added', 'Item added.'));
							$row.find('[data-sp-icon-select]').trigger('focus');
					});

					$(document).on('click', '[data-sp-icon-link-remove]', function(event) {
						event.preventDefault();
						var $field = $(this).closest('[data-sp-icon-link-list]');
							$(this).closest('[data-sp-icon-link-row]').remove();
							sync($field);
							announce($field, copy($field, 'status-removed', 'Item removed.'));
					});

					$(document).on('click', '[data-sp-icon-select]', function(event) {
						event.preventDefault();

							var $control = $(this);
							var $row = $control.closest('[data-sp-icon-link-row]');
							var $field = $row.closest('[data-sp-icon-link-list]');
							var frame = wp.media({
								title: copy($field, 'label-media-title', 'Select icon'),
								button: {text: copy($field, 'label-media-button', 'Use icon')},
							library: {type: 'image'},
							multiple: false
						});

						frame.on('select', function() {
							var attachment = frame.state().get('selection').first().toJSON();
							var url = attachment.url || (attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : '');
							$row.find('[data-sp-icon-id]').val(attachment.id || '');
							$row.find('[data-sp-icon-select]').empty().append(previewMarkup(url));
								$row.find('[data-sp-icon-remove]').removeClass('is-hidden');
								sync($field);
								announce($field, copy($field, 'status-icon-selected', 'Icon selected.'));
					});

						bindMediaBusy(frame, $field, $control);
						frame.open();
					});

					$(document).on('click', '[data-sp-icon-remove]', function(event) {
						event.preventDefault();
						var $row = $(this).closest('[data-sp-icon-link-row]');
						var $field = $row.closest('[data-sp-icon-link-list]');
						$row.find('[data-sp-icon-id]').val('');
						$row.find('[data-sp-icon-select]').empty().append(previewMarkup(''));
							$(this).addClass('is-hidden');
							sync($field);
							announce($field, copy($field, 'status-icon-removed', 'Icon removed.'));
					});

					$(document).on('click', '[data-sp-link-edit]', function(event) {
						event.preventDefault();

						var $control = $(this);
						var $row = $control.closest('[data-sp-icon-link-row]');
						var $field = $row.closest('[data-sp-icon-link-list]');
						var $node = $row.find('[data-sp-link-node]');

						if (window.acf && acf.wpLink && typeof acf.wpLink.open === 'function') {
							$('body').addClass('sp-icon-link-list-wplink');
							setMediaBusy($field, $control, true);
							$(document).off('wplink-close.spIconLinkList').one('wplink-close.spIconLinkList', function() {
								$('body').removeClass('sp-icon-link-list-wplink');
								setMediaBusy($field, $control, false);
							});
							acf.wpLink.open($node);
							forceDefaultLinkModal();
							setTimeout(forceDefaultLinkModal, 30);
							setTimeout(forceDefaultLinkModal, 120);
						}
					});

					$(document).on('click', '[data-sp-link-clear]', function(event) {
						event.preventDefault();
						var $row = $(this).closest('[data-sp-icon-link-row]');
						var $field = $row.closest('[data-sp-icon-link-list]');
							setLinkValue($row, {url: '', title: '', target: '_self'});
							sync($field);
							announce($field, copy($field, 'status-link-removed', 'Link removed.'));
					});

					$(document).on('change', '[data-sp-link-node]', function() {
						var $row = $(this).closest('[data-sp-icon-link-row]');
						var $field = $row.closest('[data-sp-icon-link-list]');
							setLinkValue($row, linkValueFromRow($row));
							sync($field);
							announce($field, copy($field, 'status-link-updated', 'Link updated.'));
					});

					$(document).on('submit', 'form', function() {
						init($(this));
					});

					$(function() {
						init($(document));
					});

					if (window.acf) {
						window.acf.addAction('append', function($element) {
							init($element);
						});
						window.acf.addAction('validation_begin', function($form) {
							init($form);
						});
					}
				})(jQuery);
			</script>
			<?php
		}
	}

	acf_register_field_type('SP_ACF_Field_Icon_Link_List');
});
