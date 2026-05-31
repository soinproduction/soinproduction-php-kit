<?php
if (! defined('ABSPATH')) {
	exit;
}

final class SP_Flowchimp
{

	private const OPT_KEY  = 'sp_flowchimp_options';
	private const PAGE_SLUG = 'sp-flowchimp';

	public static function init(): void
	{
		add_action('admin_menu',             [__CLASS__, 'add_admin_page']);
		add_action('admin_init',             [__CLASS__, 'register_settings']);
		add_action('admin_enqueue_scripts',  [__CLASS__, 'enqueue_assets']);
		add_action('wpcf7_before_send_mail', [__CLASS__, 'handle_submission'], 9);
		add_filter('wpcf7_skip_mail',        [__CLASS__, 'maybe_skip_mail'], 10, 2);
	}

	// ─── Menu ────────────────────────────────────────────────────────────────

	public static function add_admin_page(): void
	{

		add_options_page(
			'Mailchimp — Subscribe Settings',
			'<span style="display:flex;align-items:center;gap:5px;">
			<svg xmlns="http://www.w3.org/2000/svg" xml:space="preserve" width="20" height="20" viewBox="0 0 490 490">
				<path fill="currentColor" d="M279.8 490V357.8l50.5 51.5 49.7-48.6-135-137.6-135 137.6 49.7 48.6 50.5-51.5V490zM159.5 387.6l-27.8-27.1L245 245l113.3 115.5-27.8 27.1-66-67.3v154.4h-39V320.3zM57.9 111.1c-5.6 0-11.1-1.8-14.6-3.6l-2.7 10.9c3.3 1.8 9.8 3.5 16.5 3.5 16 0 23.6-8.3 23.6-18 0-8.3-4.9-13.7-15.2-17.5-7.5-2.8-10.8-4.5-10.8-8.2 0-3 2.8-5.6 8.5-5.6s9.8 1.6 12.2 2.7l3-10.6c-3.6-1.6-8.4-3-15-3-13.7 0-22 7.7-22 17.6 0 8.5 6.3 13.9 16 17.3 7 2.5 9.7 4.5 9.7 8.2 0 3.8-3.2 6.3-9.2 6.3"/>
				<path fill="currentColor" d="M29.9 191.5H460a30 30 0 0 0 29.9-29.8V29.9A30 30 0 0 0 460.1 0H30A30 30 0 0 0 0 29.9v131.8a30 30 0 0 0 29.9 29.8M15.3 30c0-8 6.5-14.6 14.6-14.6H460c8 0 14.6 6.5 14.6 14.6v131.8c0 8-6.5 14.5-14.6 14.5H30c-8 0-14.6-6.5-14.6-14.5z"/>
				<path fill="currentColor" d="M110.7 122c15.2 0 24.3-8.6 24.3-26.6V62.7H122v33.6q-.2 15.3-10.8 15.1c-6.7 0-10.5-5-10.5-15.1V62.7H87.3v32.6c0 18.6 8.7 26.7 23.4 26.7M181.5 116.4c3.7-3 6.1-7.1 6.1-12.4 0-7.5-5-12.6-11.6-14.4v-.1c6.6-2.5 9.6-7.3 9.6-12.5s-3-9.3-7-11.4c-4.2-2.6-9.2-3.3-17.2-3.3-6.6 0-13.6.5-17 1.2v57.3q4.4.8 14.2.9c11.6 0 18.7-2 23-5.3m-24.1-44.2q1.5-.2 5.6-.3 9.2 0 9.4 6.7c0 4.4-3.8 7.2-10.7 7.2h-4.3zm0 23h4.5c6.6 0 11.9 2.4 11.9 8.3 0 6.1-5.3 8.4-11.3 8.4q-3.3 0-5.1-.2zM209.5 111.1c-5.6 0-11-1.8-14.6-3.6l-2.7 10.9c3.3 1.8 9.9 3.5 16.5 3.5 16 0 23.6-8.3 23.6-18 0-8.3-4.9-13.7-15.2-17.5-7.5-2.8-10.8-4.5-10.8-8.2 0-3 2.8-5.6 8.5-5.6s9.9 1.6 12.2 2.7l3-10.6c-3.5-1.6-8.4-3-15-3-13.7 0-22 7.7-22 17.6 0 8.5 6.3 13.9 16 17.3 7 2.5 9.8 4.5 9.8 8.2 0 3.8-3.2 6.3-9.3 6.3M267 122c7 0 12.5-1.4 15-2.6l-2-10.3c-2.7 1-7 2-11 2-11.8 0-18.8-7.4-18.8-19.1 0-13 8.2-19.3 18.6-19.3 4.7 0 8.4 1 11.1 2.1l2.7-10.4a34 34 0 0 0-14.4-2.6c-17.6 0-31.8 11-31.8 31 0 16.6 10.4 29.1 30.5 29.1M301.5 98.1h4c5.3.1 7.9 2 9.4 9.4 1.8 7.1 3.1 12 4 13.6h13.6c-1.1-2.3-3-10-4.8-16.5-1.4-5.4-3.7-9.3-7.8-10.9v-.3c5-1.8 10.3-6.9 10.3-14.3q-.1-8.1-5.3-12.2c-4.2-3.3-10.2-4.6-18.9-4.6-7 0-13.3.5-17.6 1.2V121h13.1zm0-25.6q1.4-.4 6-.4c6 0 9.7 2.7 9.7 8s-4 8.5-10.5 8.5h-5.2zM337.6 62.7h13.2V121h-13.2zM397.4 116.4c3.6-3 6-7.1 6-12.4 0-7.5-5-12.6-11.6-14.4v-.1c6.6-2.5 9.6-7.3 9.6-12.5s-3-9.3-7-11.4c-4.2-2.6-9.2-3.3-17.2-3.3-6.6 0-13.6.5-17 1.2v57.3q4.4.8 14.2.9c11.6 0 18.7-2 23-5.3m-24.2-44.2q1.5-.2 5.6-.3c6 0 9.4 2.3 9.4 6.7s-3.7 7.2-10.7 7.2h-4.3zm0 23h4.5c6.6 0 11.9 2.4 11.9 8.3 0 6.1-5.3 8.4-11.3 8.4q-3.2 0-5.1-.2zM447.5 110.2h-24V96.4H445V85.6h-21.5V73.5h22.8V62.7h-36v58.4h37.2z"/>
			</svg>
			Mailchimp</span>',
			'manage_options',
			self::PAGE_SLUG,
			[__CLASS__, 'render_page']
		);
	}

	// ─── Settings ─────────────────────────────────────────────────────────────

	public static function register_settings(): void
	{
		register_setting(self::OPT_KEY, self::OPT_KEY, [
			'type'              => 'array',
			'sanitize_callback' => [__CLASS__, 'sanitize'],
			'default'           => self::get_defaults(),
		]);
	}

	public static function get_defaults(): array
	{
		return [
			'api_key'       => '',
			'status_if_new' => 'pending',
			'skip_cf7_mail' => 0,
			'forms'         => [],
		];
	}

	public static function get_options(): array
	{
		$opt = get_option(self::OPT_KEY, []);
		$opt = is_array($opt) ? $opt : [];
		return wp_parse_args($opt, self::get_defaults());
	}

	public static function sanitize($input): array
	{
		$input = is_array($input) ? $input : [];

		$output = self::get_defaults();
		$output['api_key']       = trim((string) ($input['api_key'] ?? ''));
		$output['status_if_new'] = in_array($input['status_if_new'] ?? '', ['pending', 'subscribed'], true)
			? $input['status_if_new']
			: 'pending';
		$output['skip_cf7_mail'] = ! empty($input['skip_cf7_mail']) ? 1 : 0;

		$raw_forms = isset($input['forms']) && is_array($input['forms']) ? $input['forms'] : [];
		$forms = [];
		foreach ($raw_forms as $row) {
			$form_id     = absint($row['form_id'] ?? 0);
			$list_id     = sanitize_text_field($row['list_id'] ?? '');
			$email_field = sanitize_key($row['email_field'] ?? 'your-email') ?: 'your-email';
			if ($form_id && $list_id) {
				$forms[] = [
					'form_id'     => $form_id,
					'list_id'     => $list_id,
					'email_field' => $email_field,
				];
			}
		}
		$output['forms'] = $forms;

		return $output;
	}

	// ─── Assets ───────────────────────────────────────────────────────────────

	public static function enqueue_assets(string $hook): void
	{
		if ($hook !== 'settings_page_' . self::PAGE_SLUG) {
			return;
		}

		wp_register_style('sp-flowchimp-admin', false);
		wp_enqueue_style('sp-flowchimp-admin');
		wp_add_inline_style('sp-flowchimp-admin', self::get_css());

		wp_register_script('sp-flowchimp-admin', false, [], null, true);
		wp_enqueue_script('sp-flowchimp-admin');
		wp_add_inline_script('sp-flowchimp-admin', self::get_js());
	}

	// ─── Admin Page ───────────────────────────────────────────────────────────

	public static function render_page(): void
	{
		if (! current_user_can('manage_options')) return;

		$opt   = self::get_options();
		$forms = self::get_all_cf7_forms();
		$api_set = trim($opt['api_key']) !== '';
		$forms_count = count($opt['forms']);
?>
		<div class="wrap sp-fc-wrap">
			<h1>CF7 → Mailchimp</h1>
			<p class="description">Connect Contact Form 7 subscription forms to Mailchimp audience lists.</p>

			<div class="sp-fc-metrics">
				<div class="sp-fc-metric">
					<span class="sp-fc-metric__label">API Key</span>
					<span class="sp-fc-metric__value">
						<span class="sp-fc-badge <?= $api_set ? 'is-ok' : 'is-warn'; ?>"><?= $api_set ? 'Set' : 'Missing'; ?></span>
					</span>
				</div>
				<div class="sp-fc-metric">
					<span class="sp-fc-metric__label">Mapped forms</span>
					<span class="sp-fc-metric__value"><?= esc_html($forms_count); ?></span>
				</div>
				<div class="sp-fc-metric">
					<span class="sp-fc-metric__label">Opt-in mode</span>
					<span class="sp-fc-metric__value"><?= $opt['status_if_new'] === 'pending' ? 'Double opt-in' : 'Instant'; ?></span>
				</div>
				<div class="sp-fc-metric">
					<span class="sp-fc-metric__label">Skip CF7 mail</span>
					<span class="sp-fc-metric__value"><?= $opt['skip_cf7_mail'] ? 'Yes' : 'No'; ?></span>
				</div>
			</div>

			<form method="post" action="options.php" class="sp-fc-form">
				<?php settings_fields(self::OPT_KEY); ?>

				<section class="sp-fc-card">
					<div class="sp-fc-card-head">
						<h2>API Settings</h2>
					</div>
					<table class="form-table sp-fc-table">
						<tr>
							<th><label for="sp-fc-api-key">Mailchimp API Key</label></th>
							<td>
								<input id="sp-fc-api-key" type="text" class="regular-text" autocomplete="off"
									name="<?= esc_attr(self::OPT_KEY); ?>[api_key]"
									value="<?= esc_attr($opt['api_key']); ?>"
									placeholder="xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx-us21" />
								<p class="description">Format: <code>key-datacenter</code>, e.g. <code>abc123-us21</code></p>
							</td>
						</tr>
						<tr>
							<th>Subscription mode</th>
							<td>
								<fieldset>
									<label>
										<input type="radio" name="<?= esc_attr(self::OPT_KEY); ?>[status_if_new]"
											value="pending" <?php checked($opt['status_if_new'], 'pending'); ?>>
										Double opt-in (pending) — recommended
									</label><br>
									<label>
										<input type="radio" name="<?= esc_attr(self::OPT_KEY); ?>[status_if_new]"
											value="subscribed" <?php checked($opt['status_if_new'], 'subscribed'); ?>>
										Subscribe immediately (no confirmation email)
									</label>
								</fieldset>
							</td>
						</tr>
						<tr>
							<th>CF7 emails</th>
							<td>
								<label>
									<input type="checkbox" name="<?= esc_attr(self::OPT_KEY); ?>[skip_cf7_mail]"
										value="1" <?php checked(1, (int) $opt['skip_cf7_mail']); ?>>
									Do not send CF7 notification emails for subscription forms
								</label>
							</td>
						</tr>
					</table>
				</section>

				<section class="sp-fc-card">
					<div class="sp-fc-card-head">
						<h2>Form → Audience Mapping</h2>
						<button type="button" class="button sp-fc-add-row">+ Add form</button>
					</div>
					<p class="description" style="margin-bottom:12px;">Each row maps one CF7 form to a Mailchimp audience list. You can add as many as you need.</p>

					<div id="sp-fc-rows">
						<?php foreach ($opt['forms'] as $i => $row) : ?>
							<?php self::render_form_row($i, $row, $forms); ?>
						<?php endforeach; ?>

						<?php if (empty($opt['forms'])) : ?>
							<?php self::render_form_row(0, [], $forms); ?>
						<?php endif; ?>
					</div>

					<template id="sp-fc-row-tpl">
						<?php self::render_form_row('__IDX__', [], $forms, true); ?>
					</template>
				</section>

				<div class="sp-fc-submit-row">
					<?php submit_button('Save Settings', 'primary', 'submit', false); ?>
				</div>
			</form>
		</div>
	<?php
	}

	private static function render_form_row($index, array $row, array $cf7_forms, bool $is_template = false): void
	{
		$opt_key     = self::OPT_KEY;
		$form_id     = (int) ($row['form_id'] ?? 0);
		$list_id     = esc_attr($row['list_id'] ?? '');
		$email_field = esc_attr($row['email_field'] ?? 'your-email');
	?>
		<div class="sp-fc-row" <?= $is_template ? 'style="display:none"' : ''; ?>>
			<div class="sp-fc-row__fields">
				<div class="sp-fc-row__field">
					<label>CF7 Form</label>
					<select name="<?= $opt_key; ?>[forms][<?= $index; ?>][form_id]">
						<option value="0">— select form —</option>
						<?php foreach ($cf7_forms as $id => $title) : ?>
							<option value="<?= (int) $id; ?>" <?php selected($form_id, (int) $id); ?>>
								<?= esc_html($title); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="sp-fc-row__field">
					<label>Mailchimp List ID</label>
					<input type="text"
						name="<?= $opt_key; ?>[forms][<?= $index; ?>][list_id]"
						value="<?= $list_id; ?>"
						placeholder="e.g. a1b2c3d4e5" />
				</div>
				<div class="sp-fc-row__field">
					<label>Email field name <span class="sp-fc-hint">(CF7 field name attribute)</span></label>
					<input type="text"
						name="<?= $opt_key; ?>[forms][<?= $index; ?>][email_field]"
						value="<?= $email_field; ?>"
						placeholder="your-email" />
				</div>
			</div>
			<button type="button" class="sp-fc-remove-row" title="Remove row">✕</button>
		</div>
<?php
	}

	// ─── Submission Handler ───────────────────────────────────────────────────

	public static function handle_submission($cf7): void
	{
		if (! defined('WPCF7_VERSION')) return;

		$opt        = self::get_options();
		$current_id = (int) $cf7->id();
		$row        = self::find_form_row($opt, $current_id);

		if (! $row || empty($row['list_id'])) return;

		$submission = \WPCF7_Submission::get_instance();
		if (! $submission) return;

		$email = self::extract_email($submission, $row['email_field'] ?? 'your-email');
		if (! $email) return;

		$language = self::get_current_language();
		$api      = new SP_Flowchimp_MC_API($opt['api_key']);
		$api->upsert($row['list_id'], $email, $language, $opt['status_if_new']);
	}

	public static function maybe_skip_mail(bool $skip, $cf7): bool
	{
		$opt = self::get_options();
		if (empty($opt['skip_cf7_mail'])) return $skip;

		$row = self::find_form_row($opt, (int) $cf7->id());
		return $row ? true : $skip;
	}

	private static function find_form_row(array $opt, int $form_id): ?array
	{
		foreach ($opt['forms'] as $row) {
			if ((int) ($row['form_id'] ?? 0) === $form_id) return $row;
		}
		return null;
	}

	private static function extract_email($submission, string $email_field = 'your-email'): ?string
	{
		$posted = $submission->get_posted_data();
		$email  = '';

		if (isset($posted[$email_field])) {
			$email = is_array($posted[$email_field]) ? reset($posted[$email_field]) : $posted[$email_field];
		} else {
			foreach ($posted as $key => $val) {
				if (stripos($key, 'email') !== false) {
					$email = is_array($val) ? reset($val) : $val;
					break;
				}
			}
		}

		$email = sanitize_email((string) $email);
		return is_email($email) ? $email : null;
	}

	private static function get_current_language(): string
	{
		if (function_exists('wpml_current_language')) {
			$code = wpml_current_language();
			if ($code) return $code;
		}
		return substr(get_locale(), 0, 2) ?: 'en';
	}

	// ─── CF7 Forms helper ─────────────────────────────────────────────────────

	public static function get_all_cf7_forms(): array
	{
		if (! post_type_exists('wpcf7_contact_form')) return [];

		$posts = get_posts([
			'post_type'      => 'wpcf7_contact_form',
			'posts_per_page' => -1,
			'post_status'    => ['publish', 'draft', 'pending', 'private'],
			'orderby'        => 'title',
			'order'          => 'ASC',
		]);

		$out = [];
		foreach ($posts as $p) {
			$out[(int) $p->ID] = sprintf('%s (#%d)', $p->post_title, $p->ID);
		}
		return $out;
	}

	// ─── Inline CSS ───────────────────────────────────────────────────────────

	private static function get_css(): string
	{
		return <<<'CSS'
.sp-fc-wrap { max-width: 960px; }
.sp-fc-subtitle { font-size: 14px; font-weight: 400; color: #667085; margin-left: 8px; }
.sp-fc-wrap .description { color: #667085; }
.sp-fc-metrics { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin: 14px 0; }
.sp-fc-metric { background: #fff; border: 1px solid #dcdcde; border-radius: 10px; padding: 12px 14px; }
.sp-fc-metric__label { display: block; font-size: 12px; color: #667085; margin-bottom: 4px; }
.sp-fc-metric__value { display: block; font-size: 16px; font-weight: 600; color: #1d2939; }
.sp-fc-badge { display: inline-flex; align-items: center; height: 24px; padding: 0 10px; border-radius: 999px; font-size: 12px; font-weight: 600; border: 1px solid transparent; }
.sp-fc-badge.is-ok { color: #0f5132; background: #d1e7dd; border-color: #badbcc; }
.sp-fc-badge.is-warn { color: #7a2e0f; background: #fff4e5; border-color: #ffd8a8; }
.sp-fc-card { background: #fff; border: 1px solid #dcdcde; border-radius: 12px; padding: 16px 20px; margin-bottom: 14px; box-shadow: 0 1px 0 rgba(16,24,40,.03); }
.sp-fc-card-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 12px; }
.sp-fc-card-head h2 { margin: 0; font-size: 16px; line-height: 1.35; }
.sp-fc-table { margin-top: 0 !important; }
.sp-fc-table th { width: 220px; color: #344054; }
.sp-fc-form .regular-text { max-width: 440px; width: 100%; }
.sp-fc-row { display: flex; align-items: flex-start; gap: 10px; background: #f9fafb; border: 1px solid #eaecf0; border-radius: 8px; padding: 12px; margin-bottom: 8px; }
.sp-fc-row__fields { flex: 1; display: grid; grid-template-columns: .6fr 1fr .75fr; gap: 10px; }
.sp-fc-row__field { display: flex; flex-direction: column; gap: 4px; }
.sp-fc-row__field label { font-size: 12px; font-weight: 600; color: #344054; }
.sp-fc-row__field select, .sp-fc-row__field input[type=text] { height: 36px; padding: 0 10px; border: 1px solid #d0d5dd; border-radius: 6px; font-size: 13px; }
.sp-fc-row__field--wide { grid-column: 1 / -1; }
.sp-fc-hint { font-weight: 400; color: #667085; }
.sp-fc-remove-row { flex-shrink: 0; margin-top: 22px; background: none; border: 1px solid #fda29b; color: #b42318; border-radius: 6px; width: 28px; height: 28px; cursor: pointer; font-size: 12px; display: flex; align-items: center; justify-content: center; }
.sp-fc-remove-row:hover { background: #fef3f2; }
.sp-fc-submit-row { margin-top: 4px; }
@media (max-width: 782px) { .sp-fc-metrics { grid-template-columns: repeat(2, 1fr); } .sp-fc-row__fields { grid-template-columns: 1fr; } }
CSS;
	}

	// ─── Inline JS ────────────────────────────────────────────────────────────

	private static function get_js(): string
	{
		return <<<'JS'
(function () {
	var container = document.getElementById('sp-fc-rows');
	var tpl       = document.getElementById('sp-fc-row-tpl');
	var addBtn    = document.querySelector('.sp-fc-add-row');
	if (!container || !tpl || !addBtn) return;

	function reindex() {
		container.querySelectorAll('.sp-fc-row').forEach(function (row, i) {
			row.querySelectorAll('[name]').forEach(function (el) {
				el.name = el.name.replace(/\[forms\]\[\d+\]/, '[forms][' + i + ']');
			});
		});
	}

	addBtn.addEventListener('click', function () {
		var idx  = container.querySelectorAll('.sp-fc-row').length;
		var html = tpl.innerHTML.replace(/__IDX__/g, idx);
		var div  = document.createElement('div');
		div.innerHTML = html.trim();
		var newRow = div.firstElementChild;
		newRow.style.display = '';
		container.appendChild(newRow);
	});

	container.addEventListener('click', function (e) {
		if (!e.target.classList.contains('sp-fc-remove-row')) return;
		var row = e.target.closest('.sp-fc-row');
		if (row) { row.remove(); reindex(); }
	});
})();
JS;
	}
}

// ─── Mailchimp API ───────────────────────────────────────────────────────────

class SP_Flowchimp_MC_API
{

	private string $api_key;
	private string $dc;

	public function __construct(string $api_key)
	{
		$this->api_key = $api_key;
		$pos = strpos($api_key, '-');
		$this->dc = $pos !== false ? substr($api_key, $pos + 1) : 'us1';
	}

	public function upsert(string $list_id, string $email, string $language, string $status): bool
	{
		if (! $this->api_key || ! $list_id) return false;

		$url = sprintf(
			'https://%s.api.mailchimp.com/3.0/lists/%s/members/%s',
			$this->dc,
			$list_id,
			md5(strtolower($email))
		);

		$response = wp_remote_request($url, [
			'method'  => 'PUT',
			'timeout' => 15,
			'headers' => [
				'Authorization' => 'apikey ' . $this->api_key,
				'Content-Type'  => 'application/json',
			],
			'body' => wp_json_encode([
				'email_address' => $email,
				'status_if_new' => $status,
				'language'      => $language,
			]),
		]);

		if (is_wp_error($response)) {
			error_log('[SP Flowchimp] HTTP error: ' . $response->get_error_message());
			return false;
		}

		$code = wp_remote_retrieve_response_code($response);
		if ($code >= 200 && $code < 300) return true;

		error_log(sprintf('[SP Flowchimp] API %d: %s', $code, wp_remote_retrieve_body($response)));
		return false;
	}
}

SP_Flowchimp::init();
