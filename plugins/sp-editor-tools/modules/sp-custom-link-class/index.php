<?php

if (! defined('ABSPATH')) {
	exit;
}

final class SP_Custom_Link_Class_Plugin
{
	private const VERSION       = '1.0.0';
	private const SCRIPT_HANDLE = 'link-class-field';
	private const STYLE_HANDLE  = 'link-picker-preview';

	public static function init(): void
	{
		add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);
		add_action('acf/render_field/type=link', [__CLASS__, 'render_acf_link_extras']);
		add_filter('acf/update_value/type=link', [__CLASS__, 'update_acf_link_value'], 10, 3);
		add_filter('acf/format_value/type=link', [__CLASS__, 'format_acf_link_value'], 10, 3);
	}

	public static function enqueue_admin_assets(?string $hook = null): void
	{
		unset($hook);

		$script_path = __DIR__ . '/script.js';
		$script_ver  = file_exists($script_path) ? (string) filemtime($script_path) : self::VERSION;
		$script_url  = class_exists(\SoinProduction\Kit\Bootstrapper::class)
			? \SoinProduction\Kit\Bootstrapper::pathToUrl($script_path)
			: '';
		$style_path  = get_template_directory() . '/assets/css/for-link-picker.css';
		$style_ver   = file_exists($style_path) ? (string) filemtime($style_path) : (defined('_S_VERSION') ? (string) _S_VERSION : self::VERSION);

		wp_enqueue_media();
		wp_enqueue_style(
			self::STYLE_HANDLE,
			get_template_directory_uri() . '/assets/css/for-link-picker.css',
			[],
			$style_ver
		);
		if ($script_url !== '') {
			wp_enqueue_script(
				self::SCRIPT_HANDLE,
				$script_url,
				['jquery', 'media-views'],
				$script_ver,
				true
			);
		}
		wp_localize_script(self::SCRIPT_HANDLE, 'ACF_LINK_EXTRAS', [
			'choices' => function_exists('buttons_config') ? buttons_config() : [],
		]);
		wp_localize_script(self::SCRIPT_HANDLE, 'TAG_STYLE_SELECTOR', [
			'classes' => function_exists('typography_config') ? typography_config() : [],
		]);
		wp_localize_script(self::SCRIPT_HANDLE, 'UIA_LINKPICKER', [
			'favoriteIconIDs' => self::resolve_favorite_icon_ids(),
			'spriteUrl'       => function_exists('sp_icons_sprite_url') ? sp_icons_sprite_url() : '',
			'pickerCssUrl'    => get_template_directory_uri() . '/assets/css/for-link-picker.css?ver=' . rawurlencode($style_ver),
			'byId'            => self::resolve_icons_map(),
		]);
	}

	public static function render_acf_link_extras(array $field): void
	{
		if (self::is_default_link_field($field)) {
			return;
		}

		$value           = is_array($field['value']) ? $field['value'] : [];
		$before_icon_url = (string) ($value['_before_icon_url'] ?? '');
		$after_icon_url  = (string) ($value['_after_icon_url'] ?? '');
		$link_class      = (string) ($value['_class'] ?? '');

		if ($before_icon_url === '' && $after_icon_url === '' && ! empty($value['_icon_url'])) {
			$position = strtolower((string) ($value['_icon_pos'] ?? 'before'));
			if ($position === 'after') {
				$after_icon_url = (string) $value['_icon_url'];
			} else {
				$before_icon_url = (string) $value['_icon_url'];
			}
		}

		$name = (string) ($field['name'] ?? '');
		?>
		<div class="acf-link-extras" data-acf-link-extras="1">
			<input type="hidden"
				class="acf-link-before-icon-input"
				name="<?php echo esc_attr($name); ?>[_before_icon_url]"
				value="<?php echo esc_attr($before_icon_url); ?>">

			<input type="hidden"
				class="acf-link-after-icon-input"
				name="<?php echo esc_attr($name); ?>[_after_icon_url]"
				value="<?php echo esc_attr($after_icon_url); ?>">

			<input type="hidden"
				class="acf-link-class-input"
				name="<?php echo esc_attr($name); ?>[_class]"
				value="<?php echo esc_attr($link_class); ?>">

			<span class="acf-link-extras-data"
				data-before-icon-url="<?php echo esc_attr($before_icon_url); ?>"
				data-after-icon-url="<?php echo esc_attr($after_icon_url); ?>"
				data-link-class="<?php echo esc_attr($link_class); ?>"
				hidden></span>

			<div class="acf-link-icon-preview" style="display:none"></div>
		</div>
		<?php
	}

	public static function update_acf_link_value($value, $post_id, array $field)
	{
		unset($post_id);

		if (self::is_default_link_field($field)) {
			return $value;
		}

		$value = is_array($value) ? $value : [];

		foreach (['_before_icon_url', '_after_icon_url'] as $key) {
			$url = ! empty($value[$key]) ? esc_url_raw((string) $value[$key]) : '';
			if ($url !== '') {
				$value[$key] = $url;
			} else {
				unset($value[$key]);
			}
		}

		$class = isset($value['_class']) ? trim((string) $value['_class']) : '';
		if ($class !== '') {
			$value['_class'] = $class;
		} else {
			unset($value['_class']);
		}

		unset($value['_icon_url'], $value['_icon_pos']);

		return $value;
	}

	public static function format_acf_link_value($value, $post_id, array $field)
	{
		unset($post_id);

		if (self::is_default_link_field($field) || ! is_array($value)) {
			return $value;
		}

		if (! isset($value['_before_icon_url']) && ! isset($value['_after_icon_url']) && ! empty($value['_icon_url'])) {
			$position = strtolower((string) ($value['_icon_pos'] ?? 'before'));
			if ($position === 'after') {
				$value['_after_icon_url'] = $value['_icon_url'];
			} else {
				$value['_before_icon_url'] = $value['_icon_url'];
			}
		}

		$value['_before_icon_url'] = isset($value['_before_icon_url']) ? (string) $value['_before_icon_url'] : '';
		$value['_after_icon_url']  = isset($value['_after_icon_url']) ? (string) $value['_after_icon_url'] : '';
		$value['_class']           = isset($value['_class']) ? (string) $value['_class'] : '';

		return $value;
	}

	private static function is_default_link_field(array $field): bool
	{
		$class = (string) ($field['wrapper']['class'] ?? $field['class'] ?? '');
		$classes = preg_split('/\s+/', trim($class), -1, PREG_SPLIT_NO_EMPTY) ?: [];

		return in_array('default', $classes, true);
	}

	private static function resolve_favorite_icon_ids(): array
	{
		if (! function_exists('get_field')) {
			return [];
		}

		$icon_group = get_field('button_icons_box', 'ui_assets');
		$gallery = is_array($icon_group) ? ($icon_group['button_icons'] ?? []) : [];
		if (! is_array($gallery)) {
			return [];
		}

		$ids = [];
		foreach ($gallery as $item) {
			$ids[] = is_array($item) ? (int) ($item['ID'] ?? $item['id'] ?? 0) : (int) $item;
		}

		return array_values(array_filter(array_unique($ids)));
	}

	private static function resolve_icons_map(): array
	{
		if (! defined('SP_UI_ICONS_UPLOAD_DIR') || ! defined('SP_UI_ICONS_MANIFEST_FILE')) {
			return [];
		}

		$upload_dir = wp_get_upload_dir();
		if (empty($upload_dir['basedir'])) {
			return [];
		}

		$manifest = trailingslashit((string) $upload_dir['basedir']) . SP_UI_ICONS_UPLOAD_DIR . '/' . SP_UI_ICONS_MANIFEST_FILE;
		if (! file_exists($manifest)) {
			return [];
		}

		$json = file_get_contents($manifest);
		$data = is_string($json) ? json_decode($json, true) : null;
		if (! is_array($data)) {
			return [];
		}

		$map = [];
		foreach ($data as $slug => $row) {
			if (isset($row['attId'])) {
				$map[(int) $row['attId']] = (string) $slug;
			}
		}

		return $map;
	}
}

SP_Custom_Link_Class_Plugin::init();
