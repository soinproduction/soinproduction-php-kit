<?php
if (!defined('ABSPATH')) {
	exit;
}

final class SP_CF7_Mail_Viewer
{
	private const POST_TYPE = 'sp_cf7_mail';
	private const PAGE_SLUG = 'sp-cf7-mail-viewer';
	private const MAX_LOGS  = 300;

	public static function init(): void
	{
		add_action('init', [__CLASS__, 'register_post_type']);
		add_action('admin_menu', [__CLASS__, 'add_admin_page']);
		add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
		add_filter('wpcf7_mail_components', [__CLASS__, 'capture_mail'], 20, 3);
		add_action('admin_post_sp_cf7_mail_delete', [__CLASS__, 'delete_log']);
		add_action('admin_post_sp_cf7_mail_clear', [__CLASS__, 'clear_logs']);
	}

	public static function register_post_type(): void
	{
		register_post_type(self::POST_TYPE, [
			'label'        => 'CF7 Mail Logs',
			'public'       => false,
			'show_ui'      => false,
			'supports'     => ['title', 'editor'],
			'capability_type' => 'post',
		]);
	}

	public static function add_admin_page(): void
	{
		add_menu_page(
			'CF7 Mail Viewer',
			'CF7 Mail Viewer',
			'manage_options',
			self::PAGE_SLUG,
			[__CLASS__, 'render_page'],
			'dashicons-list-view',
			99
		);
	}

	public static function enqueue_assets(string $hook): void
	{
		if ($hook !== 'toplevel_page_' . self::PAGE_SLUG) {
			return;
		}

		wp_register_style('sp-cf7-mail-viewer', false);
		wp_enqueue_style('sp-cf7-mail-viewer');
		wp_add_inline_style('sp-cf7-mail-viewer', self::css());
	}

	public static function capture_mail(array $components, $contact_form, $mail): array
	{
		try {
			if (!class_exists('WPCF7_Submission')) {
				return $components;
			}

			$submission = WPCF7_Submission::get_instance();
			$posted     = $submission ? $submission->get_posted_data() : [];
			$form_id    = is_object($contact_form) && method_exists($contact_form, 'id') ? (int) $contact_form->id() : 0;
			$form_title = is_object($contact_form) && method_exists($contact_form, 'title') ? (string) $contact_form->title() : 'Contact Form 7';
			$mail_name  = is_object($mail) && method_exists($mail, 'name') ? (string) $mail->name() : 'mail';
			$is_html    = self::is_html_mail($components);
			$subject    = sanitize_text_field((string) ($components['subject'] ?? '(no subject)'));

			$post_id = wp_insert_post([
				'post_type'    => self::POST_TYPE,
				'post_status'  => 'private',
				'post_title'   => $subject,
				'post_content' => (string) ($components['body'] ?? ''),
				'meta_input'   => [
					'_sp_cf7_mail_form_id'     => $form_id,
					'_sp_cf7_mail_form_title'  => $form_title,
					'_sp_cf7_mail_template'    => $mail_name,
					'_sp_cf7_mail_recipient'   => (string) ($components['recipient'] ?? ''),
					'_sp_cf7_mail_sender'      => (string) ($components['sender'] ?? ''),
					'_sp_cf7_mail_headers'     => (string) ($components['additional_headers'] ?? ''),
					'_sp_cf7_mail_attachments' => wp_json_encode((array) ($components['attachments'] ?? []), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
					'_sp_cf7_mail_posted'      => wp_json_encode(self::clean_posted_data($posted), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
					'_sp_cf7_mail_is_html'     => $is_html ? '1' : '0',
					'_sp_cf7_mail_remote_ip'   => self::remote_ip(),
				],
			], true);

			if (!is_wp_error($post_id) && $post_id) {
				self::trim_logs();
			}
		} catch (Throwable $e) {
			error_log('SP CF7 Mail Viewer capture failed: ' . $e->getMessage());
		}

		return $components;
	}

	public static function render_page(): void
	{
		if (!current_user_can('manage_options')) {
			return;
		}

		$selected = isset($_GET['mail_id']) ? absint($_GET['mail_id']) : 0;
		$logs     = self::get_logs();
		$current  = $selected ? get_post($selected) : ($logs[0] ?? null);
		$count    = count($logs);
		?>
		<div class="wrap sp-cf7-mail">
			<div class="sp-cf7-mail__top">
				<div>
					<h1>CF7 Mail Viewer</h1>
					<p class="description">Captured outgoing Contact Form 7 emails after mail-tag replacement.</p>
				</div>
				<div class="sp-cf7-mail__actions">
					<span class="sp-cf7-mail__metric"><?= esc_html(number_format_i18n($count)); ?> stored</span>
					<a class="button button-secondary" href="<?= esc_url(wp_nonce_url(admin_url('admin-post.php?action=sp_cf7_mail_clear'), 'sp_cf7_mail_clear')); ?>">Clear logs</a>
				</div>
			</div>

			<div class="sp-cf7-mail__layout">
				<aside class="sp-cf7-mail__list">
					<?php if (empty($logs)) : ?>
						<div class="sp-cf7-mail__empty">No captured emails yet.</div>
					<?php endif; ?>

					<?php foreach ($logs as $log) : ?>
						<?php
						$is_active = $current instanceof WP_Post && (int) $current->ID === (int) $log->ID;
						$url = add_query_arg(['page' => self::PAGE_SLUG, 'mail_id' => (int) $log->ID], admin_url('admin.php'));
						?>
						<a class="sp-cf7-mail__item <?= $is_active ? 'is-active' : ''; ?>" href="<?= esc_url($url); ?>">
							<span class="sp-cf7-mail__item-icon" aria-hidden="true">&#9993;</span>
							<strong><?= esc_html(get_the_title($log)); ?></strong>
							<span><?= esc_html(get_post_meta($log->ID, '_sp_cf7_mail_form_title', true)); ?></span>
							<small><?= esc_html(mysql2date('M j, Y H:i', $log->post_date)); ?></small>
						</a>
					<?php endforeach; ?>
				</aside>

				<main class="sp-cf7-mail__preview">
					<?php if ($current instanceof WP_Post && $current->post_type === self::POST_TYPE) : ?>
						<?php self::render_mail($current); ?>
					<?php else : ?>
						<div class="sp-cf7-mail__empty">Select an email to preview.</div>
					<?php endif; ?>
				</main>
			</div>
		</div>
		<?php
	}

	private static function render_mail(WP_Post $mail): void
	{
		$meta = [
			'Form'      => get_post_meta($mail->ID, '_sp_cf7_mail_form_title', true),
			'Template'  => get_post_meta($mail->ID, '_sp_cf7_mail_template', true),
			'To'        => get_post_meta($mail->ID, '_sp_cf7_mail_recipient', true),
			'From'      => get_post_meta($mail->ID, '_sp_cf7_mail_sender', true),
			'IP'        => get_post_meta($mail->ID, '_sp_cf7_mail_remote_ip', true),
			'Captured'  => mysql2date('M j, Y H:i:s', $mail->post_date),
		];
		$is_html = get_post_meta($mail->ID, '_sp_cf7_mail_is_html', true) === '1';
		$posted  = json_decode((string) get_post_meta($mail->ID, '_sp_cf7_mail_posted', true), true);
		$posted  = is_array($posted) ? $posted : [];
		$body_rows = self::parse_body_rows((string) $mail->post_content);
		?>
		<section class="sp-cf7-mail__card">
			<div class="sp-cf7-mail__subject">
				<span>Subject</span>
				<h2><?= esc_html(get_the_title($mail)); ?></h2>
			</div>
			<div class="sp-cf7-mail__meta">
				<?php foreach ($meta as $label => $value) : ?>
					<div><span><?= esc_html($label); ?></span><strong><?= esc_html((string) $value); ?></strong></div>
				<?php endforeach; ?>
			</div>
		</section>

		<section class="sp-cf7-mail__card">
			<h2>Email Content</h2>
			<?php if (!empty($body_rows)) : ?>
				<table class="widefat striped sp-cf7-mail__fields sp-cf7-mail__body-table">
					<thead>
					<tr>
						<th>Field</th>
						<th>Value</th>
					</tr>
					</thead>
					<tbody>
					<?php foreach ($body_rows as $row) : ?>
						<tr>
							<th><?= esc_html($row['label']); ?></th>
							<td><?= esc_html($row['value']); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<div class="sp-cf7-mail__body <?= $is_html ? 'is-html' : 'is-plain'; ?>">
					<?php if ($is_html) : ?>
						<?= wp_kses_post($mail->post_content); ?>
					<?php else : ?>
						<?= wpautop(esc_html($mail->post_content)); ?>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</section>

		<section class="sp-cf7-mail__card">
			<h2>Submitted Fields</h2>
			<?php if (empty($posted)) : ?>
				<p class="description">No posted fields captured.</p>
			<?php else : ?>
				<table class="widefat striped sp-cf7-mail__fields">
					<tbody>
					<?php foreach ($posted as $key => $value) : ?>
						<tr>
							<th><?= esc_html((string) $key); ?></th>
							<td><?= esc_html(is_array($value) ? implode(', ', array_map('strval', $value)) : (string) $value); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</section>

		<p>
			<a class="button button-link-delete" href="<?= esc_url(wp_nonce_url(admin_url('admin-post.php?action=sp_cf7_mail_delete&mail_id=' . (int) $mail->ID), 'sp_cf7_mail_delete_' . (int) $mail->ID)); ?>">Delete this log</a>
		</p>
		<?php
	}

	public static function delete_log(): void
	{
		$id = isset($_GET['mail_id']) ? absint($_GET['mail_id']) : 0;
		if (!current_user_can('manage_options') || !$id) {
			wp_die('Forbidden', 'Forbidden', ['response' => 403]);
		}
		check_admin_referer('sp_cf7_mail_delete_' . $id);
		wp_delete_post($id, true);
		wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE_SLUG));
		exit;
	}

	public static function clear_logs(): void
	{
		if (!current_user_can('manage_options')) {
			wp_die('Forbidden', 'Forbidden', ['response' => 403]);
		}
		check_admin_referer('sp_cf7_mail_clear');
		foreach (self::get_logs(-1) as $log) {
			wp_delete_post((int) $log->ID, true);
		}
		wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE_SLUG));
		exit;
	}

	private static function get_logs(int $limit = 60): array
	{
		return get_posts([
			'post_type'      => self::POST_TYPE,
			'post_status'    => 'private',
			'posts_per_page' => $limit,
			'orderby'        => 'date',
			'order'          => 'DESC',
		]);
	}

	private static function trim_logs(): void
	{
		$logs = self::get_logs(-1);
		if (count($logs) <= self::MAX_LOGS) {
			return;
		}
		foreach (array_slice($logs, self::MAX_LOGS) as $log) {
			wp_delete_post((int) $log->ID, true);
		}
	}

	private static function is_html_mail(array $components): bool
	{
		$headers = strtolower((string) ($components['additional_headers'] ?? ''));
		$body = (string) ($components['body'] ?? '');
		return str_contains($headers, 'content-type: text/html') || preg_match('~<(?:!doctype\s+html|html|body)[\s>]~i', $body) === 1;
	}

	private static function parse_body_rows(string $body): array
	{
		$text = wp_strip_all_tags($body, true);
		$text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, get_bloginfo('charset') ?: 'UTF-8');
		$text = preg_replace('/\s+/', ' ', trim((string) $text));
		if ($text === '') {
			return [];
		}

		$pattern = '/\b([A-Z][A-Za-z0-9]*(?: [A-Z][A-Za-z0-9]*){0,3}):\s*/';
		if (!preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
			return [];
		}

		$rows = [];
		$count = count($matches[0]);
		for ($i = 0; $i < $count; $i++) {
			$label = trim((string) $matches[1][$i][0]);
			$value_start = (int) $matches[0][$i][1] + strlen((string) $matches[0][$i][0]);
			$value_end = $i + 1 < $count ? (int) $matches[0][$i + 1][1] : strlen($text);
			$value = trim(substr($text, $value_start, max(0, $value_end - $value_start)));

			if ($label === '' || $value === '') {
				continue;
			}

			$rows[] = [
				'label' => $label,
				'value' => $value,
			];
		}

		return count($rows) >= 2 ? $rows : [];
	}

	private static function clean_posted_data($posted): array
	{
		$out = [];
		foreach ((array) $posted as $key => $value) {
			if (str_starts_with((string) $key, '_')) {
				continue;
			}
			$out[$key] = is_array($value)
				? array_map(static fn($item) => sanitize_text_field((string) $item), $value)
				: sanitize_text_field((string) $value);
		}
		return $out;
	}

	private static function remote_ip(): string
	{
		$ip = $_SERVER['REMOTE_ADDR'] ?? '';
		return sanitize_text_field((string) $ip);
	}

	private static function css(): string
	{
		return <<<'CSS'
.sp-cf7-mail { max-width: 1180px; }
.sp-cf7-mail .description { color: #667085; }
.sp-cf7-mail__top { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin: 12px 0 14px; }
.sp-cf7-mail__top h1 { margin: 0 0 6px; }
.sp-cf7-mail__actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.sp-cf7-mail__metric { display: inline-flex; align-items: center; height: 28px; padding: 0 10px; border-radius: 999px; font-size: 12px; font-weight: 600; color: #0f5132; background: #d1e7dd; border: 1px solid #badbcc; }
.sp-cf7-mail__layout { display: grid; grid-template-columns: 340px minmax(0, 1fr); gap: 14px; }
.sp-cf7-mail__list { background: #fff; border: 1px solid #dcdcde; border-radius: 12px; max-height: calc(100vh - 190px); overflow: auto; box-shadow: 0 1px 0 rgba(16,24,40,.03); }
.sp-cf7-mail__item { display: grid; grid-template-columns: 32px minmax(0,1fr); column-gap: 10px; padding: 12px 14px; border-bottom: 1px solid #eaecf0; text-decoration: none; color: #1d2939; }
.sp-cf7-mail__item:hover, .sp-cf7-mail__item.is-active { background: #f9fafb; }
.sp-cf7-mail__item.is-active { box-shadow: inset 3px 0 0 #2271b1; }
.sp-cf7-mail__item-icon { grid-row: 1 / 4; display: flex !important; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 8px; background: #f0f6fc; color: #2271b1; font-size: 15px; }
.sp-cf7-mail__item strong { display: block; font-size: 13px; line-height: 1.35; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sp-cf7-mail__item span:not(.sp-cf7-mail__item-icon), .sp-cf7-mail__item small { display: block; margin-top: 4px; color: #667085; }
.sp-cf7-mail__preview { min-width: 0; }
.sp-cf7-mail__card { background: #fff; border: 1px solid #dcdcde; border-radius: 12px; padding: 16px; margin-bottom: 14px; box-shadow: 0 1px 0 rgba(16,24,40,.03); }
.sp-cf7-mail__card h2 { margin: 0 0 12px; font-size: 17px; line-height: 1.35; }
.sp-cf7-mail__subject span, .sp-cf7-mail__meta span { display: block; font-size: 12px; color: #667085; margin-bottom: 4px; }
.sp-cf7-mail__subject h2 { margin: 0; font-size: 20px; line-height: 1.3; color: #1d2939; }
.sp-cf7-mail__meta { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; margin-top: 14px; }
.sp-cf7-mail__meta div { border: 1px solid #eaecf0; border-radius: 10px; background: #f9fafb; padding: 10px; }
.sp-cf7-mail__meta strong { display: block; font-size: 14px; font-weight: 600; color: #1d2939; overflow-wrap: anywhere; }
.sp-cf7-mail__body { padding: 14px; border: 1px solid #eaecf0; background: #f9fafb; border-radius: 8px; overflow: auto; }
.sp-cf7-mail__body.is-plain { font-size: 14px; line-height: 1.65; }
.sp-cf7-mail__fields th { width: 220px; color: #344054; }
.sp-cf7-mail pre { white-space: pre-wrap; margin: 0; padding: 14px; background: #f9fafb; border: 1px solid #eaecf0; border-radius: 8px; color: #1d2939; }
.sp-cf7-mail__empty { padding: 24px; color: #667085; text-align: center; }
@media(max-width:1100px){.sp-cf7-mail__layout{grid-template-columns:1fr}.sp-cf7-mail__meta{grid-template-columns:1fr}.sp-cf7-mail__top{flex-direction:column}}
CSS;
	}
}

SP_CF7_Mail_Viewer::init();
