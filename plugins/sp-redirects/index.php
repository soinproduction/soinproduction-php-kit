<?php
if (!defined('ABSPATH')) {
	exit;
}

final class SP_Redirects
{
	private const OPT_KEY   = 'sp_redirects_rules';
	private const PAGE_SLUG = 'sp-redirects';

	public static function init(): void
	{
		add_action('template_redirect', [__CLASS__, 'maybe_redirect'], 1);
		add_action('admin_menu', [__CLASS__, 'add_admin_page']);
		add_action('admin_init', [__CLASS__, 'register_settings']);
		add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
		add_action('admin_post_sp_redirects_import', [__CLASS__, 'handle_import']);
	}

	public static function add_admin_page(): void
	{
		add_options_page(
			'SP Redirects',
			'<span style="display:flex;align-items:center;gap:5px;">
				<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
					<path d="M5 7h9.5a4.5 4.5 0 0 1 0 9H8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
					<path d="M10 4 5 7l5 3M11 13l-3 3 3 3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
				</svg>
				SP Redirects
			</span>',
			'manage_options',
			self::PAGE_SLUG,
			[__CLASS__, 'render_page']
		);
	}

	public static function register_settings(): void
	{
		register_setting(self::OPT_KEY, self::OPT_KEY, [
			'type'              => 'array',
			'sanitize_callback' => [__CLASS__, 'sanitize'],
			'default'           => [],
		]);
	}

	public static function enqueue_assets(string $hook): void
	{
		if ($hook !== 'settings_page_' . self::PAGE_SLUG) {
			return;
		}

		wp_register_style('sp-redirects-admin', false);
		wp_enqueue_style('sp-redirects-admin');
		wp_add_inline_style('sp-redirects-admin', self::css());

		wp_register_script('sp-redirects-admin', false, [], null, true);
		wp_enqueue_script('sp-redirects-admin');
		wp_add_inline_script('sp-redirects-admin', self::js());
	}

	public static function render_page(): void
	{
		if (!current_user_can('manage_options')) {
			return;
		}

		$rules = self::get_rules();
		$enabled_count = count(array_filter($rules, static fn($rule) => (int) ($rule['enabled'] ?? 1) === 1));
		?>
		<div class="wrap sp-redirects">
			<h1>SP Redirects</h1>
			<p class="description">Manage exact URL redirects and import migration maps from CSV or TSV files.</p>

			<?php self::render_notice(); ?>

			<div class="sp-redirects-metrics">
				<div class="sp-redirects-metric">
					<span class="sp-redirects-metric__label">Total rules</span>
					<span class="sp-redirects-metric__value"><?= esc_html(number_format_i18n(count($rules))); ?></span>
				</div>
				<div class="sp-redirects-metric">
					<span class="sp-redirects-metric__label">Enabled</span>
					<span class="sp-redirects-metric__value"><?= esc_html(number_format_i18n($enabled_count)); ?></span>
				</div>
				<div class="sp-redirects-metric">
					<span class="sp-redirects-metric__label">Default status</span>
					<span class="sp-redirects-metric__value">301</span>
				</div>
			</div>

			<form method="post" action="options.php">
				<?php settings_fields(self::OPT_KEY); ?>

				<section class="sp-redirects__card">
					<div class="sp-redirects__card-head">
						<h2>Redirect Rules</h2>
						<button type="button" class="button sp-redirects-add">+ Add row</button>
					</div>

					<div class="sp-redirects__table-wrap">
						<table class="widefat fixed striped sp-redirects__table">
							<thead>
							<tr>
								<th>OLD</th>
								<th>NEW</th>
								<th class="sp-redirects__status-col">Code</th>
								<th class="sp-redirects__enabled-col">On</th>
								<th class="sp-redirects__remove-col"></th>
							</tr>
							</thead>
							<tbody id="sp-redirects-rows">
							<?php foreach ($rules as $i => $rule) : ?>
								<?php self::render_row($i, $rule); ?>
							<?php endforeach; ?>
							<?php if (!$rules) : ?>
								<?php self::render_row(0, []); ?>
							<?php endif; ?>
							</tbody>
						</table>
					</div>

					<template id="sp-redirects-row-template">
						<?php self::render_row('__IDX__', []); ?>
					</template>
				</section>

				<?php submit_button('Save Redirects'); ?>
			</form>

			<section class="sp-redirects__card">
				<div class="sp-redirects__card-head">
					<h2>Import From File</h2>
				</div>
				<p class="description">Upload a <code>.csv</code>, <code>.tsv</code> or <code>.txt</code> file. The first columns should be <code>OLD</code>, <code>NEW</code>, <code>STATUS</code>. Header row is optional.</p>
				<form method="post" action="<?= esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" class="sp-redirects-upload">
					<input type="hidden" name="action" value="sp_redirects_import" />
					<?php wp_nonce_field('sp_redirects_import'); ?>
					<input type="file" name="redirects_file" accept=".csv,.tsv,.txt,text/csv,text/tab-separated-values,text/plain" required />
					<label>
						<input type="checkbox" name="replace_existing" value="1" />
						Replace existing rules
					</label>
					<button type="submit" class="button button-secondary">Upload and import</button>
				</form>
			</section>
		</div>
		<?php
	}

	private static function render_row($index, array $rule): void
	{
		$old     = esc_attr($rule['old'] ?? '');
		$new     = esc_attr($rule['new'] ?? '');
		$status  = (int) ($rule['status'] ?? 301);
		$enabled = !isset($rule['enabled']) || (int) $rule['enabled'] === 1;
		$key     = esc_attr(self::OPT_KEY);
		?>
		<tr>
			<td><input type="text" class="large-text" name="<?= $key; ?>[rules][<?= esc_attr((string) $index); ?>][old]" value="<?= $old; ?>" placeholder="/old-url/" /></td>
			<td><input type="text" class="large-text" name="<?= $key; ?>[rules][<?= esc_attr((string) $index); ?>][new]" value="<?= $new; ?>" placeholder="/new-url/" /></td>
			<td>
				<select name="<?= $key; ?>[rules][<?= esc_attr((string) $index); ?>][status]">
					<?php foreach ([301, 302, 307, 308] as $code) : ?>
						<option value="<?= esc_attr((string) $code); ?>" <?php selected($status, $code); ?>><?= esc_html((string) $code); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
			<td><input type="checkbox" name="<?= $key; ?>[rules][<?= esc_attr((string) $index); ?>][enabled]" value="1" <?php checked($enabled); ?> /></td>
			<td><button type="button" class="button-link-delete sp-redirects-remove">Remove</button></td>
		</tr>
		<?php
	}

	public static function sanitize($input): array
	{
		$input = is_array($input) ? $input : [];
		$rules = [];
		$raw_rules = isset($input['rules']) && is_array($input['rules']) ? $input['rules'] : $input;

		foreach ((array) $raw_rules as $row) {
			if (!is_array($row)) {
				continue;
			}
			$rule = self::sanitize_rule((array) $row);
			if ($rule) {
				$rules[] = $rule;
			}
		}

		foreach (self::parse_bulk((string) ($input['bulk'] ?? '')) as $rule) {
			$rules[] = $rule;
		}

		return self::dedupe_rules($rules);
	}

	public static function handle_import(): void
	{
		if (!current_user_can('manage_options')) {
			wp_die('Forbidden', 'Forbidden', ['response' => 403]);
		}
		check_admin_referer('sp_redirects_import');

		$redirect = admin_url('options-general.php?page=' . self::PAGE_SLUG);
		$file = $_FILES['redirects_file'] ?? null;
		if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
			wp_safe_redirect(add_query_arg(['sp_redirects_error' => rawurlencode('File upload failed.')], $redirect));
			exit;
		}

		$name = sanitize_file_name((string) ($file['name'] ?? ''));
		$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
		if (!in_array($ext, ['csv', 'tsv', 'txt'], true)) {
			wp_safe_redirect(add_query_arg(['sp_redirects_error' => rawurlencode('Use CSV, TSV or TXT file.')], $redirect));
			exit;
		}

		$contents = file_get_contents((string) ($file['tmp_name'] ?? ''));
		if (!is_string($contents) || trim($contents) === '') {
			wp_safe_redirect(add_query_arg(['sp_redirects_error' => rawurlencode('Uploaded file is empty.')], $redirect));
			exit;
		}

		$imported = self::parse_bulk($contents);
		$existing = !empty($_POST['replace_existing']) ? [] : self::get_rules();
		update_option(self::OPT_KEY, [
			'rules' => self::dedupe_rules(array_merge($existing, $imported)),
		]);

		wp_safe_redirect(add_query_arg(['sp_redirects_imported' => count($imported)], $redirect));
		exit;
	}

	public static function maybe_redirect(): void
	{
		if (is_admin() || wp_doing_ajax() || wp_doing_cron() || (defined('REST_REQUEST') && REST_REQUEST)) {
			return;
		}

		$current = self::current_path();
		if ($current === '') {
			return;
		}

		foreach (self::get_rules() as $rule) {
			if ((int) ($rule['enabled'] ?? 1) !== 1 || empty($rule['old']) || empty($rule['new'])) {
				continue;
			}

			if (!self::paths_match($current, (string) $rule['old'])) {
				continue;
			}

			$target = self::target_url((string) $rule['new']);
			if (!$target || self::same_url($target)) {
				return;
			}

			wp_redirect($target, (int) $rule['status']);
			exit;
		}
	}

	private static function get_rules(): array
	{
		$rules = get_option(self::OPT_KEY, []);
		return is_array($rules) ? $rules : [];
	}

	private static function sanitize_rule(array $row): ?array
	{
		$old = self::normalize_path((string) ($row['old'] ?? ''));
		$new = trim((string) ($row['new'] ?? ''));
		$status = (int) ($row['status'] ?? 301);

		if ($old === '' || $new === '') {
			return null;
		}
		if (!in_array($status, [301, 302, 307, 308], true)) {
			$status = 301;
		}

		return [
			'old'     => $old,
			'new'     => self::sanitize_target($new),
			'status'  => $status,
			'enabled' => !empty($row['enabled']) ? 1 : 0,
		];
	}

	private static function parse_bulk(string $raw): array
	{
		$raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
		$raw = trim(str_replace("\r\n", "\n", (string) $raw));
		if ($raw === '') {
			return [];
		}

		$out = [];
		foreach (explode("\n", $raw) as $line) {
			$line = trim($line);
			if ($line === '') {
				continue;
			}

			$delimiter = str_contains($line, "\t") ? "\t" : (substr_count($line, ';') > substr_count($line, ',') ? ';' : ',');
			$cols = str_getcsv($line, $delimiter);
			$cols = array_map(static fn($col) => trim((string) $col), $cols);

			if (count($cols) < 2 || in_array(strtolower($cols[0]), ['old', 'from', 'source'], true)) {
				continue;
			}

			$rule = self::sanitize_rule([
				'old'     => $cols[0] ?? '',
				'new'     => $cols[1] ?? '',
				'status'  => $cols[2] ?? 301,
				'enabled' => 1,
			]);
			if ($rule) {
				$out[] = $rule;
			}
		}
		return $out;
	}

	private static function render_notice(): void
	{
		if (isset($_GET['sp_redirects_imported'])) {
			$count = max(0, absint($_GET['sp_redirects_imported']));
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(sprintf('Imported %d redirect rules.', $count)) . '</p></div>';
		}
		if (isset($_GET['sp_redirects_error'])) {
			$message = sanitize_text_field(wp_unslash((string) $_GET['sp_redirects_error']));
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($message) . '</p></div>';
		}
	}

	private static function normalize_path(string $value): string
	{
		$value = trim($value);
		if ($value === '') {
			return '';
		}
		if (preg_match('~^https?://~i', $value)) {
			$parts = wp_parse_url($value);
			$value = ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');
		}
		if ($value[0] !== '/') {
			$value = '/' . $value;
		}
		return esc_url_raw($value);
	}

	private static function sanitize_target(string $value): string
	{
		$value = trim($value);
		if ($value === '') {
			return '';
		}
		if (preg_match('~^https?://~i', $value)) {
			return esc_url_raw($value);
		}
		if ($value[0] !== '/') {
			$value = '/' . $value;
		}
		return esc_url_raw($value);
	}

	private static function dedupe_rules(array $rules): array
	{
		$out = [];
		foreach ($rules as $rule) {
			$out[$rule['old']] = $rule;
		}
		return array_values($out);
	}

	private static function current_path(): string
	{
		$uri = $_SERVER['REQUEST_URI'] ?? '';
		$parts = wp_parse_url((string) $uri);
		$path = $parts['path'] ?? '/';
		$query = isset($parts['query']) ? '?' . $parts['query'] : '';
		return self::normalize_path($path . $query);
	}

	private static function paths_match(string $current, string $old): bool
	{
		if ($current === $old) {
			return true;
		}
		if (str_contains($old, '?')) {
			return false;
		}
		$current_no_query = strtok($current, '?') ?: $current;
		$old_no_query = strtok($old, '?') ?: $old;
		return untrailingslashit($current_no_query) === untrailingslashit($old_no_query);
	}

	private static function target_url(string $target): string
	{
		if (preg_match('~^https?://~i', $target)) {
			return $target;
		}
		return home_url($target);
	}

	private static function same_url(string $target): bool
	{
		$current = home_url(self::current_path());
		return untrailingslashit($current) === untrailingslashit($target);
	}

	private static function css(): string
	{
		return <<<'CSS'
.sp-redirects .description { color: #667085; }
.sp-redirects-metrics { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin: 14px 0; }
.sp-redirects-metric { background: #fff; border: 1px solid #dcdcde; border-radius: 10px; padding: 12px 14px; }
.sp-redirects-metric__label { display: block; font-size: 12px; color: #667085; margin-bottom: 4px; }
.sp-redirects-metric__value { display: block; font-size: 16px; font-weight: 600; color: #1d2939; }
.sp-redirects__card { background: #fff; border: 1px solid #dcdcde; border-radius: 12px; padding: 16px 20px; margin-bottom: 14px; box-shadow: 0 1px 0 rgba(16,24,40,.03); }
.sp-redirects__card-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 12px; }
.sp-redirects__card-head h2, .sp-redirects__card h2 { margin: 0; font-size: 16px; line-height: 1.35; }
.sp-redirects__table-wrap { overflow: auto; }
.sp-redirects__table { margin-top: 0 !important; }
.sp-redirects__table th { color: #344054; }
.sp-redirects__table td { vertical-align: middle; }
.sp-redirects__table input[type=text] { width: 100%; max-width: 100%; }
.sp-redirects__table input[type=text], .sp-redirects__table select { height: 34px;width:100%; min-height: 34px; padding: 0 10px; border: 1px solid #d0d5dd; border-radius: 6px; font-size: 13px; }
.sp-redirects__status-col { width: 90px; }
.sp-redirects__enabled-col { width: 60px; }
.sp-redirects__remove-col { width: 90px; }
.sp-redirects-remove { color: #b42318; }
.sp-redirects-upload { display: flex; align-items: center; flex-wrap: wrap; gap: 10px 14px; margin-top: 12px; padding: 12px; background: #f9fafb; border: 1px solid #eaecf0; border-radius: 8px; }
.sp-redirects-upload input[type=file] { min-width: 280px; }
.sp-redirects-upload label { color: #344054; }
.sp-redirects .button { min-height: 30px; line-height: 2.15384615; }
@media (max-width: 782px) { .sp-redirects-metrics { grid-template-columns: 1fr; } }
CSS;
	}

	private static function js(): string
	{
		return <<<'JS'
document.addEventListener('click', function (event) {
	if (event.target.matches('.sp-redirects-add')) {
		event.preventDefault();
		var rows = document.getElementById('sp-redirects-rows');
		var tpl = document.getElementById('sp-redirects-row-template');
		if (!rows || !tpl) return;
		var index = Date.now();
		var holder = document.createElement('tbody');
		holder.innerHTML = tpl.innerHTML.replace(/__IDX__/g, String(index)).trim();
		if (holder.firstElementChild) rows.appendChild(holder.firstElementChild);
	}
	if (event.target.matches('.sp-redirects-remove')) {
		event.preventDefault();
		var row = event.target.closest('tr');
		if (row) row.remove();
	}
});
JS;
	}
}

SP_Redirects::init();
