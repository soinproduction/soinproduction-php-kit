<?php
/**
 * ACF Field Type: SP Related Posts
 *
 * Usage in ACF PHP / Builder:
 *
 *   ->addField('related_posts', 'sp_related_posts', [
 *       'label' => 'Related Posts',
 *       'count' => 3,
 *   ])
 *
 * Usage in ACF local field arrays:
 *
 *   [
 *       'key'   => 'field_related_posts',
 *       'label' => 'Related Posts',
 *       'name'  => 'related_posts',
 *       'type'  => 'sp_related_posts',
 *       'count' => 3,
 *   ]
 *
 * Returned value:
 *
 *   $related = get_field('related_posts');
 *
 *   [
 *       'count'     => 3,
 *       'post_type' => 'case_study',
 *       'ids'       => [123, 456, 789],
 *   ]
 *
 * Helpers:
 *
 *   $ids   = sp_acf_related_posts_ids(get_field('related_posts'));
 *   $query = sp_acf_related_posts_query(get_field('related_posts'), [
 *       'posts_per_page' => 3,
 *   ]);
 */

if (! defined('ABSPATH')) {
	exit;
}

if (! function_exists('sp_acf_related_posts_normalize_value')) {
	function sp_acf_related_posts_normalize_value($value): array
	{
		if (is_string($value)) {
			$decoded = json_decode($value, true);
			$value   = is_array($decoded) ? $decoded : [];
		}

		if (! is_array($value)) {
			$value = [];
		}

		$post_type = sanitize_key((string) ($value['post_type'] ?? ''));
		$ids       = array_values(array_unique(array_filter(array_map('absint', (array) ($value['ids'] ?? [])))));
		$count     = max(1, min(24, absint($value['count'] ?? count($ids) ?: 3)));

		return [
			'count'     => $count,
			'post_type' => $post_type,
			'ids'       => $ids,
		];
	}
}

if (! function_exists('sp_acf_related_posts_resolve_post_id')) {
	function sp_acf_related_posts_resolve_post_id($post_id = false): int
	{
		if (is_numeric($post_id)) {
			return absint($post_id);
		}

		if (is_string($post_id) && preg_match('/^post_(\d+)$/', $post_id, $matches)) {
			return absint($matches[1]);
		}

		if (! empty($_GET['post'])) {
			return absint($_GET['post']);
		}

		if (! empty($_POST['post_ID'])) {
			return absint($_POST['post_ID']);
		}

		if (! empty($_POST['post_id']) && is_numeric($_POST['post_id'])) {
			return absint($_POST['post_id']);
		}

		return 0;
	}
}

if (! function_exists('sp_acf_related_posts_setting_bool')) {
	function sp_acf_related_posts_setting_bool(array $field, string $key, bool $default): bool
	{
		if (! array_key_exists($key, $field)) {
			return $default;
		}

		return (bool) absint($field[$key]);
	}
}

if (! function_exists('sp_acf_related_posts_get_settings')) {
	function sp_acf_related_posts_get_settings(array $field = [], string $current_post_type = ''): array
	{
		$post_types = array_values(array_filter(array_map('sanitize_key', (array) ($field['candidate_post_types'] ?? []))));
		if (! $post_types && $current_post_type !== '') {
			$post_types = [$current_post_type];
		}

		$post_status = array_values(array_filter(array_map('sanitize_key', (array) ($field['post_status'] ?? []))));
		if (! $post_status) {
			$post_status = ['publish'];
		}

		$excluded_fields = $field['excluded_fields'] ?? [];
		if (is_string($excluded_fields)) {
			$excluded_fields = preg_split('/[\r\n,]+/', $excluded_fields) ?: [];
		}

		return [
			'count'            => max(1, min(24, absint($field['count'] ?? 3))),
			'candidate_limit'  => max(10, min(1000, absint($field['candidate_limit'] ?? 250))),
			'post_types'       => $post_types,
			'post_status'      => $post_status,
			'use_taxonomies'   => sp_acf_related_posts_setting_bool($field, 'use_taxonomies', true),
			'use_acf'          => sp_acf_related_posts_setting_bool($field, 'use_acf', true),
			'use_title'        => sp_acf_related_posts_setting_bool($field, 'use_title', true),
			'use_content'      => sp_acf_related_posts_setting_bool($field, 'use_content', false),
			'taxonomy_weight'  => max(0, min(100, absint($field['taxonomy_weight'] ?? 45))),
			'acf_weight'       => max(0, min(100, absint($field['acf_weight'] ?? 35))),
			'title_weight'     => max(0, min(100, absint($field['title_weight'] ?? 15))),
			'content_weight'   => max(0, min(100, absint($field['content_weight'] ?? 5))),
			'minimum_score'    => max(0, min(100, absint($field['minimum_score'] ?? 0))),
			'date_limit_days'  => max(0, absint($field['date_limit_days'] ?? 0)),
			'excluded_fields'  => array_values(array_filter(array_map('sanitize_key', (array) $excluded_fields))),
		];
	}
}

if (! function_exists('sp_acf_related_posts_public_post_type_choices')) {
	function sp_acf_related_posts_public_post_type_choices(): array
	{
		$post_types = get_post_types(['public' => true], 'objects');
		$choices    = [];

		foreach ($post_types as $post_type => $object) {
			$choices[$post_type] = $object->labels->singular_name ?: $object->label ?: $post_type;
		}

		return $choices;
	}
}

if (! function_exists('sp_acf_related_posts_post_type_label')) {
	function sp_acf_related_posts_post_type_label($post_type): string
	{
		$post_type = sanitize_key((string) $post_type);

		if ($post_type === '') {
			return __('No post type', 'targetized');
		}

		$object = get_post_type_object($post_type);
		if (! $object) {
			return $post_type;
		}

		return $object->labels->name ?: $object->label ?: $post_type;
	}
}

if (! function_exists('sp_acf_related_posts_taxonomies_for_post_types')) {
	function sp_acf_related_posts_taxonomies_for_post_types(array $post_types): array
	{
		$taxonomies = [];

		foreach ($post_types as $post_type) {
			$objects = get_object_taxonomies($post_type, 'objects');
			foreach ($objects as $taxonomy => $object) {
				if ($taxonomy === 'post_format') {
					continue;
				}

				if (! empty($object->public) || ! empty($object->show_ui)) {
					$taxonomies[$taxonomy] = $taxonomy;
				}
			}
		}

		return array_values($taxonomies);
	}
}

if (! function_exists('sp_acf_related_posts_terms_map')) {
	function sp_acf_related_posts_terms_map(int $post_id, array $taxonomies): array
	{
		$map = [];

		foreach ($taxonomies as $taxonomy) {
			$terms = wp_get_post_terms($post_id, $taxonomy, ['fields' => 'ids']);
			if (is_wp_error($terms) || ! $terms) {
				continue;
			}

			$map[$taxonomy] = array_values(array_unique(array_map('absint', $terms)));
		}

		return $map;
	}
}

if (! function_exists('sp_acf_related_posts_tokenize_text')) {
	function sp_acf_related_posts_tokenize_text(string $text): array
	{
		$text = wp_strip_all_tags(strip_shortcodes($text));
		$text = html_entity_decode($text, ENT_QUOTES, get_bloginfo('charset') ?: 'UTF-8');
		$text = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);

		if (! preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}_-]{2,}/u', $text, $matches)) {
			return [];
		}

		$stop_words = [
			'the' => true, 'and' => true, 'for' => true, 'with' => true, 'from' => true,
			'this' => true, 'that' => true, 'your' => true, 'you' => true, 'are' => true,
			'was' => true, 'were' => true, 'but' => true, 'not' => true, 'all' => true,
			'или' => true, 'для' => true, 'это' => true, 'как' => true, 'что' => true,
		];

		$tokens = [];
		foreach ($matches[0] as $token) {
			if (isset($stop_words[$token])) {
				continue;
			}

			$tokens[$token] = true;
			if (count($tokens) >= 500) {
				break;
			}
		}

		return array_keys($tokens);
	}
}

if (! function_exists('sp_acf_related_posts_extract_tokens')) {
	function sp_acf_related_posts_extract_tokens($value, array $excluded_keys = [], int $depth = 0): array
	{
		if ($depth > 8) {
			return [];
		}

		if (is_string($value)) {
			return sp_acf_related_posts_tokenize_text($value);
		}

		if (is_numeric($value)) {
			return [];
		}

		if ($value instanceof WP_Post) {
			return sp_acf_related_posts_tokenize_text($value->post_title . ' ' . $value->post_excerpt);
		}

		if ($value instanceof WP_Term) {
			return sp_acf_related_posts_tokenize_text($value->name . ' ' . $value->description);
		}

		if (! is_array($value)) {
			return [];
		}

		$tokens = [];
		foreach ($value as $key => $item) {
			$key_string = is_string($key) ? sanitize_key($key) : '';
			if ($key_string !== '' && (isset($excluded_keys[$key_string]) || substr($key_string, 0, 1) === '_')) {
				continue;
			}

			foreach (sp_acf_related_posts_extract_tokens($item, $excluded_keys, $depth + 1) as $token) {
				$tokens[$token] = true;
				if (count($tokens) >= 500) {
					break 2;
				}
			}
		}

		return array_keys($tokens);
	}
}

if (! function_exists('sp_acf_related_posts_overlap_ratio')) {
	function sp_acf_related_posts_overlap_ratio(array $source, array $candidate): float
	{
		$source    = array_values(array_unique(array_filter($source)));
		$candidate = array_values(array_unique(array_filter($candidate)));

		if (! $source || ! $candidate) {
			return 0.0;
		}

		$source_map = array_fill_keys($source, true);
		$matches    = 0;

		foreach ($candidate as $item) {
			if (isset($source_map[$item])) {
				$matches++;
			}
		}

		return $matches > 0 ? min(1.0, $matches / max(1, min(count($source), count($candidate)))) : 0.0;
	}
}

if (! function_exists('sp_acf_related_posts_terms_score')) {
	function sp_acf_related_posts_terms_score(array $source_terms, array $candidate_terms): float
	{
		$source_flat    = [];
		$candidate_flat = [];

		foreach ($source_terms as $taxonomy => $ids) {
			foreach ((array) $ids as $id) {
				$source_flat[] = $taxonomy . ':' . absint($id);
			}
		}

		foreach ($candidate_terms as $taxonomy => $ids) {
			foreach ((array) $ids as $id) {
				$candidate_flat[] = $taxonomy . ':' . absint($id);
			}
		}

		return sp_acf_related_posts_overlap_ratio($source_flat, $candidate_flat);
	}
}

if (! function_exists('sp_acf_related_posts_add_reason')) {
	function sp_acf_related_posts_add_reason(array &$reasons, string $key, string $label, float $score): void
	{
		if ($score <= 0) {
			return;
		}

		$reasons[] = [
			'key'   => sanitize_key($key),
			'label' => $label,
			'score' => round($score, 2),
		];
	}
}

if (! function_exists('sp_acf_related_posts_profile')) {
	function sp_acf_related_posts_profile(int $post_id, array $taxonomies, array $settings, array $field): array
	{
		$excluded = [];
		foreach ($settings['excluded_fields'] as $field_name) {
			$excluded[$field_name] = true;
		}

		if (! empty($field['name'])) {
			$excluded[sanitize_key((string) $field['name'])] = true;
		}

		$post = get_post($post_id);
		if (! $post) {
			return [
				'terms'   => [],
				'acf'     => [],
				'title'   => [],
				'content' => [],
				'date'    => '',
			];
		}

		$acf_fields = [];
		if (! empty($settings['use_acf']) && function_exists('get_fields')) {
			$acf_fields = get_fields($post_id, false);
			$acf_fields = is_array($acf_fields) ? $acf_fields : [];
		}

		return [
			'terms'   => ! empty($settings['use_taxonomies']) ? sp_acf_related_posts_terms_map($post_id, $taxonomies) : [],
			'acf'     => ! empty($settings['use_acf']) ? sp_acf_related_posts_extract_tokens($acf_fields, $excluded) : [],
			'title'   => ! empty($settings['use_title']) ? sp_acf_related_posts_tokenize_text((string) $post->post_title) : [],
			'content' => ! empty($settings['use_content']) ? sp_acf_related_posts_tokenize_text((string) ($post->post_excerpt . ' ' . $post->post_content)) : [],
			'date'    => (string) $post->post_date_gmt,
		];
	}
}

if (! function_exists('sp_acf_related_posts_calculate')) {
	function sp_acf_related_posts_calculate($post_id, array $field = []): array
	{
		static $cache = [];

		$post_id = sp_acf_related_posts_resolve_post_id($post_id);
		$settings = sp_acf_related_posts_get_settings($field);

		if ($post_id <= 0 || get_post_status($post_id) === false) {
			return [
				'count'           => $settings['count'],
				'post_type'       => '',
				'ids'             => [],
				'items'           => [],
				'available_count' => 0,
			];
		}

		$current_post_type = (string) get_post_type($post_id);
		$settings          = sp_acf_related_posts_get_settings($field, $current_post_type);
		$cache_key         = $post_id . ':' . md5(wp_json_encode($settings) . '|' . (string) ($field['name'] ?? ''));

		if (isset($cache[$cache_key])) {
			return $cache[$cache_key];
		}

		$post_types = $settings['post_types'] ?: [$current_post_type];
		$taxonomies = sp_acf_related_posts_taxonomies_for_post_types($post_types);
		$source     = sp_acf_related_posts_profile($post_id, $taxonomies, $settings, $field);

		$query_args = [
			'post_type'              => $post_types,
			'post_status'            => $settings['post_status'],
			'post__not_in'           => [$post_id],
			'posts_per_page'         => $settings['candidate_limit'],
			'fields'                 => 'ids',
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'suppress_filters'       => false,
		];

		if ($settings['date_limit_days'] > 0) {
			$query_args['date_query'] = [
				[
					'after'     => $settings['date_limit_days'] . ' days ago',
					'inclusive' => true,
				],
			];
		}

		$candidate_ids = get_posts($query_args);
		$items         = [];

		foreach ($candidate_ids as $candidate_id) {
			$candidate_id = absint($candidate_id);
			if ($candidate_id <= 0) {
				continue;
			}

			$candidate = sp_acf_related_posts_profile($candidate_id, $taxonomies, $settings, $field);
			$score     = 0.0;
			$reasons   = [];

			if ($settings['use_taxonomies']) {
				$reason_score = $settings['taxonomy_weight'] * sp_acf_related_posts_terms_score($source['terms'], $candidate['terms']);
				$score += $reason_score;
				sp_acf_related_posts_add_reason($reasons, 'taxonomies', __('Shared terms', 'targetized'), $reason_score);
			}

			if ($settings['use_acf']) {
				$reason_score = $settings['acf_weight'] * sp_acf_related_posts_overlap_ratio($source['acf'], $candidate['acf']);
				$score += $reason_score;
				sp_acf_related_posts_add_reason($reasons, 'acf', __('ACF content', 'targetized'), $reason_score);
			}

			if ($settings['use_title']) {
				$reason_score = $settings['title_weight'] * sp_acf_related_posts_overlap_ratio($source['title'], $candidate['title']);
				$score += $reason_score;
				sp_acf_related_posts_add_reason($reasons, 'title', __('Title match', 'targetized'), $reason_score);
			}

			if ($settings['use_content']) {
				$reason_score = $settings['content_weight'] * sp_acf_related_posts_overlap_ratio($source['content'], $candidate['content']);
				$score += $reason_score;
				sp_acf_related_posts_add_reason($reasons, 'content', __('Content match', 'targetized'), $reason_score);
			}

			if ($settings['minimum_score'] > 0 && $score < $settings['minimum_score']) {
				continue;
			}

			$items[] = [
				'id'      => $candidate_id,
				'score'   => round($score, 3),
				'date'    => $candidate['date'],
				'reasons' => $reasons,
			];
		}

		usort($items, static function (array $a, array $b): int {
			if ((float) $a['score'] === (float) $b['score']) {
				return strcmp((string) $b['date'], (string) $a['date']);
			}

			return (float) $a['score'] < (float) $b['score'] ? 1 : -1;
		});

		$available_count = count($items);
		$count           = $available_count > 0 ? min($settings['count'], $available_count) : $settings['count'];
		$items           = array_slice($items, 0, $count);
		$ids             = array_map('absint', wp_list_pluck($items, 'id'));

		$cache[$cache_key] = [
			'count'           => $count,
			'post_type'       => $current_post_type,
			'ids'             => $ids,
			'items'           => $items,
			'available_count' => $available_count,
		];

		return $cache[$cache_key];
	}
}

if (! function_exists('sp_acf_related_posts_ids')) {
	function sp_acf_related_posts_ids($value): array
	{
		$value = sp_acf_related_posts_normalize_value($value);

		return $value['ids'];
	}
}

if (! function_exists('sp_acf_related_posts_query')) {
	function sp_acf_related_posts_query($value, array $args = []): WP_Query
	{
		$value = sp_acf_related_posts_normalize_value($value);
		$ids   = $value['ids'];

		$args = wp_parse_args($args, [
			'post_type'      => $value['post_type'] ?: 'any',
			'post_status'    => 'publish',
			'post__in'       => $ids ?: [0],
			'posts_per_page' => $ids ? count($ids) : 0,
			'orderby'        => 'post__in',
		]);

		return new WP_Query($args);
	}
}

if (! function_exists('sp_acf_related_posts_admin_config')) {
	function sp_acf_related_posts_admin_config(array $field): array
	{
		$config_keys = [
			'name',
			'count',
			'candidate_post_types',
			'post_status',
			'candidate_limit',
			'use_taxonomies',
			'use_acf',
			'use_title',
			'use_content',
			'taxonomy_weight',
			'acf_weight',
			'title_weight',
			'content_weight',
			'minimum_score',
			'date_limit_days',
			'excluded_fields',
		];

		$config = [];
		foreach ($config_keys as $key) {
			if (array_key_exists($key, $field)) {
				$config[$key] = $field[$key];
			}
		}

		return $config;
	}
}

if (! function_exists('sp_acf_related_posts_hidden_inputs')) {
	function sp_acf_related_posts_hidden_inputs(string $field_name, array $value): string
	{
		$value = sp_acf_related_posts_normalize_value($value);

		ob_start();
		?>
		<input type="hidden" name="<?php echo esc_attr($field_name); ?>[post_type]" value="<?php echo esc_attr($value['post_type']); ?>">
		<?php foreach ($value['ids'] as $related_id) : ?>
			<input type="hidden" name="<?php echo esc_attr($field_name); ?>[ids][]" value="<?php echo esc_attr((string) absint($related_id)); ?>">
		<?php endforeach; ?>
		<?php
		return (string) ob_get_clean();
	}
}

if (! function_exists('sp_acf_related_posts_count_text')) {
	function sp_acf_related_posts_count_text(int $count): string
	{
		return sprintf(_n('%d match', '%d matches', $count, 'targetized'), $count);
	}
}

if (! function_exists('sp_acf_related_posts_available_text')) {
	function sp_acf_related_posts_available_text(int $shown, int $available): string
	{
		if ($available <= 0) {
			return __('No available posts', 'targetized');
		}

		return sprintf(
			__('Showing %1$d of %2$d available', 'targetized'),
			$shown,
			$available
		);
	}
}

if (! function_exists('sp_acf_related_posts_render_results')) {
	function sp_acf_related_posts_render_results(array $result): string
	{
		$items = is_array($result['items'] ?? null) ? $result['items'] : [];

		ob_start();
		if ($items) :
			?>
			<div class="sp-related-posts-field__grid">
				<?php foreach ($items as $item) : ?>
					<?php
					$item_id = absint($item['id'] ?? 0);
					if ($item_id <= 0) {
						continue;
					}

					$title     = get_the_title($item_id) ?: '#' . $item_id;
					$thumb_url = get_the_post_thumbnail_url($item_id, 'thumbnail');
					$reasons   = is_array($item['reasons'] ?? null) ? $item['reasons'] : [];
					?>
					<article class="sp-related-posts-field__card">
						<div class="sp-related-posts-field__thumb">
							<?php if ($thumb_url) : ?>
								<img src="<?php echo esc_url($thumb_url); ?>" alt="">
							<?php else : ?>
								<span class="dashicons dashicons-admin-post"></span>
							<?php endif; ?>
						</div>

						<div class="sp-related-posts-field__content">
							<div class="sp-related-posts-field__card-head">
								<strong class="sp-related-posts-field__title"><?php echo esc_html($title); ?></strong>
								<span class="sp-related-posts-field__score">
									<?php echo esc_html(number_format_i18n((float) ($item['score'] ?? 0), 2)); ?>
								</span>
							</div>

							<?php if ($reasons) : ?>
								<div class="sp-related-posts-field__reasons" aria-label="<?php echo esc_attr__('Similarity reasons', 'targetized'); ?>">
									<?php foreach ($reasons as $reason) : ?>
										<span class="sp-related-posts-field__reason">
											<?php echo esc_html((string) ($reason['label'] ?? '')); ?>
											<b><?php echo esc_html(number_format_i18n((float) ($reason['score'] ?? 0), 1)); ?></b>
										</span>
									<?php endforeach; ?>
								</div>
							<?php else : ?>
								<div class="sp-related-posts-field__no-reasons">
									<?php echo esc_html__('No strong similarity signals.', 'targetized'); ?>
								</div>
							<?php endif; ?>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<div class="sp-related-posts-field__empty">
				<span class="dashicons dashicons-randomize"></span>
				<strong><?php echo esc_html__('No related posts found', 'targetized'); ?></strong>
				<span><?php echo esc_html__('Save the post after adding terms or ACF content, then refresh the count.', 'targetized'); ?></span>
			</div>
			<?php
		endif;

		return (string) ob_get_clean();
	}
}

if (! function_exists('sp_related_posts')) {
	function sp_related_posts(string $name, array $args = []): StoutLogic\AcfBuilder\FieldsBuilder
	{
		$builder = new StoutLogic\AcfBuilder\FieldsBuilder($name);
		$builder->addField($name, 'sp_related_posts', $args);

		return $builder;
	}
}

add_action('wp_ajax_sp_acf_related_posts_refresh', function (): void {
	if (! current_user_can('edit_posts')) {
		wp_send_json_error([
			'message' => __('You do not have permission to refresh related posts.', 'targetized'),
		], 403);
	}

	check_ajax_referer('sp_acf_related_posts_refresh', 'nonce');

	$post_id = sp_acf_related_posts_resolve_post_id($_POST['post_id'] ?? false);
	if ($post_id <= 0 || ! current_user_can('edit_post', $post_id)) {
		wp_send_json_error([
			'message' => __('Could not detect current post.', 'targetized'),
		], 400);
	}

	$config = [];
	if (! empty($_POST['field_config'])) {
		$decoded = json_decode(wp_unslash((string) $_POST['field_config']), true);
		$config  = is_array($decoded) ? $decoded : [];
	}

	$config['count'] = max(1, min(24, absint($_POST['count'] ?? ($config['count'] ?? 3))));
	$result          = sp_acf_related_posts_calculate($post_id, $config);
	$value           = sp_acf_related_posts_normalize_value($result);

	wp_send_json_success([
		'count'          => $value['count'],
		'post_type'      => $value['post_type'],
		'post_type_label' => sp_acf_related_posts_post_type_label($value['post_type']),
		'ids'            => $value['ids'],
		'found'          => count($value['ids']),
		'available'      => absint($result['available_count'] ?? count($value['ids'])),
		'foundText'      => sp_acf_related_posts_available_text(count($value['ids']), absint($result['available_count'] ?? count($value['ids']))),
		'html'           => sp_acf_related_posts_render_results($result),
	]);
});

add_action('acf/include_field_types', function (): void {
	if (! class_exists('acf_field') || class_exists('SP_ACF_Field_Related_Posts')) {
		return;
	}

	class SP_ACF_Field_Related_Posts extends acf_field
	{
		public function initialize(): void
		{
			$this->name     = 'sp_related_posts';
			$this->label    = __('SP Related Posts', 'targetized');
			$this->category = 'relational';
			$this->defaults = [
				'count'                 => 3,
				'candidate_post_types'  => [],
				'post_status'           => ['publish'],
				'candidate_limit'       => 250,
				'use_taxonomies'        => 1,
				'use_acf'               => 1,
				'use_title'             => 1,
				'use_content'           => 0,
				'taxonomy_weight'       => 45,
				'acf_weight'            => 35,
				'title_weight'          => 15,
				'content_weight'        => 5,
				'minimum_score'         => 0,
				'date_limit_days'       => 0,
				'excluded_fields'       => [],
			];
		}

		public function render_field_settings(array $field): void
		{
			acf_render_field_setting($field, [
				'label'        => __('Default Posts Count', 'targetized'),
				'instructions' => __('Initial count for new posts. Editors can change it directly in the field.', 'targetized'),
				'type'         => 'number',
				'name'         => 'count',
				'min'          => 1,
				'max'          => 24,
			]);

			acf_render_field_setting($field, [
				'label'        => __('Candidate Post Types', 'targetized'),
				'instructions' => __('Leave empty to use the current post type automatically.', 'targetized'),
				'type'         => 'select',
				'name'         => 'candidate_post_types',
				'choices'      => sp_acf_related_posts_public_post_type_choices(),
				'multiple'     => 1,
				'ui'           => 1,
				'allow_null'   => 1,
			]);

			acf_render_field_setting($field, [
				'label'        => __('Post Status', 'targetized'),
				'type'         => 'select',
				'name'         => 'post_status',
				'choices'      => [
					'publish' => __('Published', 'targetized'),
					'private' => __('Private', 'targetized'),
					'draft'   => __('Draft', 'targetized'),
					'any'     => __('Any', 'targetized'),
				],
				'multiple'     => 1,
				'ui'           => 1,
				'allow_null'   => 0,
			]);

			acf_render_field_setting($field, [
				'label'        => __('Candidate Limit', 'targetized'),
				'instructions' => __('Maximum posts scanned before scoring. Keep this reasonable for large sites.', 'targetized'),
				'type'         => 'number',
				'name'         => 'candidate_limit',
				'min'          => 10,
				'max'          => 1000,
			]);

			foreach ([
				'use_taxonomies' => __('Score by taxonomies', 'targetized'),
				'use_acf'        => __('Score by ACF fields', 'targetized'),
				'use_title'      => __('Score by title', 'targetized'),
				'use_content'    => __('Score by excerpt/content', 'targetized'),
			] as $name => $label) {
				acf_render_field_setting($field, [
					'label'        => $label,
					'type'         => 'true_false',
					'name'         => $name,
					'ui'           => 1,
				]);
			}

			foreach ([
				'taxonomy_weight' => __('Taxonomy Weight', 'targetized'),
				'acf_weight'      => __('ACF Weight', 'targetized'),
				'title_weight'    => __('Title Weight', 'targetized'),
				'content_weight'  => __('Content Weight', 'targetized'),
				'minimum_score'   => __('Minimum Score', 'targetized'),
			] as $name => $label) {
				acf_render_field_setting($field, [
					'label' => $label,
					'type'  => 'number',
					'name'  => $name,
					'min'   => 0,
					'max'   => 100,
				]);
			}

			acf_render_field_setting($field, [
				'label'        => __('Date Limit Days', 'targetized'),
				'instructions' => __('0 means no date limit.', 'targetized'),
				'type'         => 'number',
				'name'         => 'date_limit_days',
				'min'          => 0,
			]);

			acf_render_field_setting($field, [
				'label'        => __('Excluded ACF Field Names', 'targetized'),
				'instructions' => __('Optional. One field name per line, ignored during ACF text scanning.', 'targetized'),
				'type'         => 'textarea',
				'name'         => 'excluded_fields',
				'rows'         => 4,
			]);
		}

		public function render_field(array $field): void
		{
			$post_id       = sp_acf_related_posts_resolve_post_id($field['post_id'] ?? false);
			$raw_value     = $field['value'] ?? [];
			$current_value = sp_acf_related_posts_normalize_value($raw_value);
			$has_count     = is_array($raw_value) && array_key_exists('count', $raw_value);
			$field['count'] = $has_count ? $current_value['count'] : max(1, min(24, absint($field['count'] ?? 3)));
			$result        = sp_acf_related_posts_calculate($post_id, $field);
			$value         = sp_acf_related_posts_normalize_value($result);
			$config        = sp_acf_related_posts_admin_config($field);
			$shown_count     = count($value['ids']);
			$available       = absint($result['available_count'] ?? $shown_count);
			$count_max       = max(1, $available);
			$post_type_label = sp_acf_related_posts_post_type_label($value['post_type']);

			?>
			<div
				class="sp-related-posts-field sp-admin-component sp-acf-component<?php echo empty($value['ids']) ? ' is-empty' : ' is-filled'; ?>"
				data-sp-admin-component
				data-sp-related-posts
				data-field-name="<?php echo esc_attr($field['name']); ?>"
				data-post-id="<?php echo esc_attr((string) $post_id); ?>"
				data-nonce="<?php echo esc_attr(wp_create_nonce('sp_acf_related_posts_refresh')); ?>"
				data-available-count="<?php echo esc_attr((string) $available); ?>"
				data-status-loading="<?php echo esc_attr__('Refreshing related posts…', 'targetized'); ?>"
				data-status-success="<?php echo esc_attr__('Related posts refreshed.', 'targetized'); ?>"
				data-status-error="<?php echo esc_attr__('Could not refresh related posts.', 'targetized'); ?>"
				aria-busy="false"
			>
				<div data-sp-related-posts-hidden>
					<?php echo sp_acf_related_posts_hidden_inputs((string) $field['name'], $value); ?>
				</div>
				<input type="hidden" data-sp-related-posts-config value="<?php echo esc_attr(wp_json_encode($config)); ?>">

				<div class="sp-related-posts-field__header">
					<div class="sp-related-posts-field__intro">
						<strong><?php echo esc_html__('Related posts', 'targetized'); ?></strong>
						<span><?php echo esc_html__('Calculated from shared terms and content signals.', 'targetized'); ?></span>
					</div>

					<div class="sp-related-posts-field__controls">
						<div class="sp-related-posts-field__post-type">
							<span><?php echo esc_html__('Post type', 'targetized'); ?></span>
							<strong data-sp-related-posts-post-type-label><?php echo esc_html($post_type_label); ?></strong>
							<code data-sp-related-posts-post-type><?php echo esc_html($value['post_type'] ?: '-'); ?></code>
						</div>

						<div class="sp-related-posts-field__count">
							<span><?php echo esc_html__('Posts count', 'targetized'); ?></span>
							<div class="sp-related-posts-field__stepper">
								<button
									type="button"
									class="sp-related-posts-field__step"
									data-sp-related-posts-step="-1"
									aria-label="<?php echo esc_attr__('Decrease posts count', 'targetized'); ?>"
									<?php disabled($available <= 0 || $value['count'] <= 1); ?>
								>−</button>
								<input
									type="number"
									name="<?php echo esc_attr($field['name']); ?>[count]"
									value="<?php echo esc_attr((string) min($value['count'], $count_max)); ?>"
									min="1"
									max="<?php echo esc_attr((string) $count_max); ?>"
									step="1"
									inputmode="numeric"
									data-sp-related-posts-count
									<?php echo $available <= 0 ? 'readonly' : ''; ?>
								>
								<button
									type="button"
									class="sp-related-posts-field__step"
									data-sp-related-posts-step="1"
									aria-label="<?php echo esc_attr__('Increase posts count', 'targetized'); ?>"
									<?php disabled($available <= 0 || $value['count'] >= $count_max); ?>
								>+</button>
							</div>
						</div>
						<div class="sp-related-posts-field__meta">
							<span data-sp-related-posts-found>
								<?php echo esc_html(sp_acf_related_posts_available_text($shown_count, $available)); ?>
							</span>
						</div>
					</div>
				</div>

				<div class="sp-related-posts-field__body" data-sp-related-posts-results>
					<?php echo sp_acf_related_posts_render_results($result); ?>
				</div>

				<div class="sp-related-posts-field__status sp-acf-status" data-sp-related-posts-status role="status" aria-live="polite" aria-atomic="true"></div>
			</div>
			<?php
		}

		public function update_value($value, $post_id, $field)
		{
			$post_id = sp_acf_related_posts_resolve_post_id($post_id);
			if ($post_id <= 0) {
				return sp_acf_related_posts_normalize_value($value);
			}

			$value = sp_acf_related_posts_normalize_value($value);
			$field = is_array($field) ? $field : [];
			$field['count'] = $value['count'];

			return sp_acf_related_posts_normalize_value(sp_acf_related_posts_calculate($post_id, $field));
		}

		public function format_value($value, $post_id, $field)
		{
			return sp_acf_related_posts_normalize_value($value);
		}

		public function input_admin_head(): void
		{
			?>
			<style>
				.sp-related-posts-field {
					background: var(--sp-acf-surface);
					border: 1px solid var(--sp-acf-border);
					border-radius: var(--sp-acf-radius);
					box-shadow: var(--sp-acf-shadow);
					color: var(--sp-acf-text);
					container-type: inline-size;
					max-width: 100%;
					min-width: 0;
					overflow: hidden;
					position: relative;
					width: 100%;
				}

				.sp-related-posts-field.is-loading::after {
					animation: sp-related-posts-loading .9s ease-in-out infinite;
					background: linear-gradient(90deg, transparent, var(--sp-acf-accent-bright), transparent);
					content: "";
					height: 2px;
					left: 0;
					position: absolute;
					right: 0;
					top: 0;
					transform: translateX(-100%);
					z-index: 2;
				}

				@keyframes sp-related-posts-loading {
					to {
						transform: translateX(100%);
					}
				}

				.sp-related-posts-field__header {
					align-items: center;
					background: var(--sp-acf-surface-soft);
					border-bottom: 1px solid var(--sp-acf-border);
					display: flex;
					flex-wrap: wrap;
					gap: 12px 18px;
					justify-content: space-between;
					padding: 16px;
				}

				.sp-related-posts-field__intro {
					display: grid;
					gap: 4px;
					min-width: 220px;
				}

				.sp-related-posts-field__intro strong {
					color: var(--sp-acf-text);
					font-size: 14px;
					font-weight: 700;
					line-height: 1.2;
				}

				.sp-related-posts-field__intro span,
				.sp-related-posts-field__meta,
				.sp-related-posts-field__no-reasons {
					color: var(--sp-acf-text-muted);
					font-size: 13px;
					line-height: 1.35;
				}

				.sp-related-posts-field__controls,
				.sp-related-posts-field__count {
					align-items: center;
					display: flex;
					flex-wrap: wrap;
					gap: 8px;
				}

				.sp-related-posts-field__controls {
					justify-content: flex-end;
				}

				.sp-related-posts-field__count {
					color: var(--sp-acf-text);
					font-size: 13px;
					font-weight: 700;
					margin: 0;
				}

				.sp-related-posts-field__post-type {
					display: none;
				}

				.sp-related-posts-field__stepper {
					align-items: stretch;
					display: inline-grid;
					grid-template-columns: 38px 62px 38px;
					min-height: 38px;
				}

				.sp-related-posts-field__step {
					align-items: center;
					appearance: none;
					background: var(--sp-acf-input-bg);
					border: 1px solid var(--sp-acf-border-strong);
					border-radius: var(--sp-acf-radius);
					box-shadow: none;
					color: var(--sp-acf-accent);
					cursor: pointer;
					display: flex;
					font-size: 18px;
					font-weight: 800;
					justify-content: center;
					line-height: 1;
					margin: 0;
					min-height: 38px;
					padding: 0;
					transition: background var(--sp-acf-transition), border-color var(--sp-acf-transition), box-shadow var(--sp-acf-transition), color var(--sp-acf-transition), transform var(--sp-acf-transition);
				}

				.sp-related-posts-field__step:first-child {
					border-right: 0;
				}

				.sp-related-posts-field__step:last-child {
					border-left: 0;
				}

				.sp-related-posts-field__step:hover:not(:disabled) {
					background: var(--sp-acf-accent-soft);
					border-color: var(--sp-acf-accent);
					color: var(--sp-acf-accent-hover);
				}

				.sp-related-posts-field__step:active:not(:disabled) {
					transform: translateY(1px);
				}

				.sp-related-posts-field__step:focus-visible {
					border-color: var(--sp-acf-accent);
					box-shadow: var(--sp-acf-focus);
					outline: 0;
					position: relative;
					z-index: 1;
				}

				.sp-related-posts-field__step:disabled {
					background: var(--sp-acf-surface-soft);
					color: var(--sp-acf-text-subtle);
					cursor: not-allowed;
				}

				.sp-related-posts-field__count input[type="number"] {
					-moz-appearance: textfield;
					background: var(--sp-acf-input-bg);
					border: 1px solid var(--sp-acf-border-strong);
					border-radius: var(--sp-acf-radius);
					box-shadow: none;
					color: var(--sp-acf-text);
					font-weight: 700;
					margin: 0;
					min-height: 38px;
					text-align: center;
					transition: background var(--sp-acf-transition), border-color var(--sp-acf-transition), box-shadow var(--sp-acf-transition), color var(--sp-acf-transition);
					width: 62px;
				}

				.sp-related-posts-field__count input[type="number"]::-webkit-inner-spin-button,
				.sp-related-posts-field__count input[type="number"]::-webkit-outer-spin-button {
					-webkit-appearance: none;
					margin: 0;
				}

				.sp-related-posts-field__count input[type="number"]:hover:not([readonly]) {
					border-color: var(--sp-acf-accent-bright);
				}

				.sp-related-posts-field__count input[type="number"]:focus-visible {
					border-color: var(--sp-acf-accent);
					box-shadow: var(--sp-acf-focus);
					outline: 0;
				}

				.sp-related-posts-field__count input[type="number"][readonly] {
					background: var(--sp-acf-surface-soft);
					color: var(--sp-acf-text-subtle);
					cursor: not-allowed;
				}

				.sp-related-posts-field__meta {
					align-items: center;
					color: var(--sp-acf-text-muted);
					display: flex;
					flex-wrap: wrap;
					gap: 8px;
				}

				.sp-related-posts-field__badge {
					background: var(--sp-acf-surface);
					border: 1px solid var(--sp-acf-border);
					border-radius: var(--sp-acf-radius);
					color: var(--sp-acf-text);
					display: inline-flex;
					font-size: 12px;
					font-weight: 700;
					line-height: 1;
					padding: 7px 9px;
				}

				.sp-related-posts-field__body {
					padding: 16px;
					transition: opacity var(--sp-acf-transition);
				}

				.sp-related-posts-field.is-loading .sp-related-posts-field__body {
					opacity: .58;
					pointer-events: none;
				}

				.sp-related-posts-field__grid {
					display: grid;
					gap: 12px;
					grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
				}

				.sp-related-posts-field__card {
					background: var(--sp-acf-surface);
					border: 1px solid var(--sp-acf-border);
					border-radius: var(--sp-acf-radius);
					display: grid;
					gap: 12px;
					grid-template-columns: 76px minmax(0, 1fr);
					margin: 0;
					min-width: 0;
					padding: 12px;
				}

				.sp-related-posts-field__thumb {
					align-items: center;
					aspect-ratio: 1;
					background: var(--sp-acf-surface-soft);
					border: 1px dashed var(--sp-acf-border-strong);
					border-radius: var(--sp-acf-radius);
					color: var(--sp-acf-text-subtle);
					display: flex;
					justify-content: center;
					overflow: hidden;
				}

				.sp-related-posts-field__thumb img {
					display: block;
					height: 100%;
					object-fit: cover;
					width: 100%;
				}

				.sp-related-posts-field__content {
					display: grid;
					gap: 10px;
					min-width: 0;
				}

				.sp-related-posts-field__card-head {
					align-items: start;
					display: grid;
					gap: 8px;
					grid-template-columns: minmax(0, 1fr) auto;
				}

				.sp-related-posts-field__title {
					color: var(--sp-acf-text);
					font-size: 14px;
					font-weight: 700;
					line-height: 1.35;
					min-width: 0;
				}

				.sp-related-posts-field__score {
					background: var(--sp-acf-accent-soft);
					border: 1px solid var(--sp-acf-accent-bright);
					border-radius: var(--sp-acf-radius);
					color: var(--sp-acf-accent);
					display: inline-flex;
					font-size: 12px;
					font-weight: 800;
					line-height: 1;
					padding: 6px 8px;
				}

				.sp-related-posts-field__reasons {
					display: flex;
					flex-wrap: wrap;
					gap: 6px;
				}

				.sp-related-posts-field__reason {
					align-items: center;
					background: var(--sp-acf-surface-soft);
					border: 1px solid var(--sp-acf-border);
					border-radius: var(--sp-acf-radius);
					color: var(--sp-acf-text-muted);
					display: inline-flex;
					font-size: 12px;
					font-weight: 600;
					gap: 6px;
					line-height: 1;
					padding: 7px 8px;
				}

				.sp-related-posts-field__reason b {
					color: var(--sp-acf-text);
					font-weight: 800;
				}

				.sp-related-posts-field__empty {
					align-items: center;
					background: var(--sp-acf-surface-soft);
					border: 1px dashed var(--sp-acf-border-strong);
					border-radius: var(--sp-acf-radius);
					color: var(--sp-acf-text-muted);
					display: grid;
					gap: 7px;
					justify-items: center;
					min-height: 138px;
					padding: 18px;
					text-align: center;
				}

				.sp-related-posts-field__empty strong {
					color: var(--sp-acf-text);
				}

				.sp-related-posts-field__empty .dashicons {
					color: var(--sp-acf-text-subtle);
					font-size: 28px;
					height: 28px;
					width: 28px;
				}

				.sp-related-posts-field__status {
					border: 1px solid var(--sp-acf-border);
					border-left-width: 3px;
					border-radius: var(--sp-acf-radius);
					font-size: 13px;
					line-height: 1.4;
					margin: 0 16px 16px;
					padding: 9px 11px;
				}

				.sp-related-posts-field__status:empty {
					display: none;
				}

				.sp-related-posts-field__status.is-loading {
					background: var(--sp-acf-accent-soft);
					border-color: var(--sp-acf-accent);
					color: var(--sp-acf-accent-hover);
				}

				.sp-related-posts-field__status.is-success {
					background: rgb(39 174 96 / 7%);
					border-color: var(--sp-acf-success);
					color: var(--sp-acf-success);
				}

				.sp-related-posts-field__status.is-error {
					background: rgb(231 76 60 / 7%);
					border-color: var(--sp-acf-error);
					color: var(--sp-acf-error);
				}

				@container (max-width: 520px) {
					.sp-related-posts-field__header,
					.sp-related-posts-field__controls,
					.sp-related-posts-field__count {
						align-items: stretch;
						display: grid;
						width: 100%;
					}

					.sp-related-posts-field__stepper {
						grid-template-columns: 42px minmax(0, 1fr) 42px;
						width: 100%;
					}

					.sp-related-posts-field__count input[type="number"] {
						width: 100%;
					}

					.sp-related-posts-field__card {
						grid-template-columns: 64px minmax(0, 1fr);
					}
				}

				@container (max-width: 360px) {
					.sp-related-posts-field__card {
						grid-template-columns: 1fr;
					}

					.sp-related-posts-field__thumb {
						max-width: 88px;
					}
				}

				@media (prefers-reduced-motion: reduce) {
					.sp-related-posts-field.is-loading::after {
						animation: none;
						transform: none;
					}
				}
			</style>
			<script>
				(function($) {
					var timers = {};

					function parseConfig($field) {
						var raw = $field.find('[data-sp-related-posts-config]').val() || '{}';

						try {
							return JSON.parse(raw);
						} catch (error) {
							return {};
						}
					}

					function esc(value) {
						return $('<div>').text(value || '').html();
					}

					function renderHiddenInputs(fieldName, data) {
						var html = '';
						var ids = Array.isArray(data.ids) ? data.ids : [];

						html += '<input type="hidden" name="' + esc(fieldName) + '[post_type]" value="' + esc(data.post_type || '') + '">';
						ids.forEach(function(id) {
							html += '<input type="hidden" name="' + esc(fieldName) + '[ids][]" value="' + parseInt(id, 10) + '">';
						});

						return html;
					}

					function getAvailable($field) {
						return Math.max(0, parseInt($field.attr('data-available-count'), 10) || 0);
					}

					function getInputMax($field) {
						return Math.max(1, getAvailable($field) || (parseInt($field.find('[data-sp-related-posts-count]').attr('max'), 10) || 1));
					}

					function clampCount($field, value) {
						var max = getInputMax($field);
						return Math.max(1, Math.min(max, parseInt(value, 10) || 1));
					}

					function statusCopy($field, key, fallback) {
						return $field.attr('data-status-' + key) || fallback;
					}

					function setStatus($field, state, message) {
						var $status = $field.find('[data-sp-related-posts-status]');

						$field.removeClass('is-error is-success');
						$status.removeClass('is-loading is-error is-success');

						if (state) {
							$status.addClass('is-' + state);
						}

						if (state === 'error' || state === 'success') {
							$field.addClass('is-' + state);
						}

						$status.text(message || '');
					}

					function syncStepper($field, value) {
						var available = getAvailable($field);
						var max = getInputMax($field);
						var count = clampCount($field, value);
						var $input = $field.find('[data-sp-related-posts-count]');
						var isBusy = $field.hasClass('is-loading');

						$input
							.attr('max', max)
							.attr('aria-disabled', isBusy || available <= 0 ? 'true' : 'false')
							.val(count)
							.prop('readonly', isBusy || available <= 0);
						$field.find('[data-sp-related-posts-step="-1"]').prop('disabled', isBusy || available <= 0 || count <= 1);
						$field.find('[data-sp-related-posts-step="1"]').prop('disabled', isBusy || available <= 0 || count >= max);
					}

					function setBusy($field, isBusy) {
						$field
							.toggleClass('is-loading', isBusy)
							.attr('aria-busy', isBusy ? 'true' : 'false');
						syncStepper($field, $field.find('[data-sp-related-posts-count]').val());
					}

					function errorMessage($field, response) {
						if (response && response.data && typeof response.data.message === 'string' && response.data.message) {
							return response.data.message;
						}

						return statusCopy($field, 'error', 'Could not refresh related posts.');
					}

					function refresh($field) {
						if ($field.hasClass('is-loading')) {
							return;
						}

						var fieldName = $field.attr('data-field-name') || '';
						var count = clampCount($field, $field.find('[data-sp-related-posts-count]').val());
						var config = parseConfig($field);

						config.count = count;
						syncStepper($field, config.count);
						setBusy($field, true);
						setStatus($field, 'loading', statusCopy($field, 'loading', 'Refreshing related posts…'));

						$.post(ajaxurl, {
							action: 'sp_acf_related_posts_refresh',
							nonce: $field.attr('data-nonce') || '',
							post_id: $field.attr('data-post-id') || '',
							count: config.count,
							field_config: JSON.stringify(config)
						}).done(function(response) {
							if (! response || ! response.success || ! response.data) {
								setStatus($field, 'error', errorMessage($field, response));
								return;
							}

							$field.find('[data-sp-related-posts-hidden]').html(renderHiddenInputs(fieldName, response.data));
							$field.find('[data-sp-related-posts-results]').html(response.data.html || '');
							$field.attr('data-available-count', parseInt(response.data.available, 10) || 0);
							$field.find('[data-sp-related-posts-post-type]').text(response.data.post_type || '-');
							$field.find('[data-sp-related-posts-post-type-label]').text(response.data.post_type_label || '<?php echo esc_js(__('No post type', 'targetized')); ?>');
							$field.find('[data-sp-related-posts-found]').text(response.data.foundText || '');
							syncStepper($field, response.data.count || config.count);
							$field
								.toggleClass('is-empty', ! Array.isArray(response.data.ids) || response.data.ids.length === 0)
								.toggleClass('is-filled', Array.isArray(response.data.ids) && response.data.ids.length > 0);
							setStatus($field, 'success', statusCopy($field, 'success', 'Related posts refreshed.'));
						}).fail(function(xhr) {
							setStatus($field, 'error', errorMessage($field, xhr && xhr.responseJSON ? xhr.responseJSON : null));
						}).always(function() {
							setBusy($field, false);
						});
					}

					$(document).on('input change', '[data-sp-related-posts-count]', function() {
						var $field = $(this).closest('[data-sp-related-posts]');
						var key = $field.attr('data-field-name') || Math.random().toString(36);

						clearTimeout(timers[key]);
						timers[key] = setTimeout(function() {
							refresh($field);
						}, 300);
					});

					$(document).on('click', '[data-sp-related-posts-step]', function() {
						var $field = $(this).closest('[data-sp-related-posts]');
						var step = parseInt($(this).attr('data-sp-related-posts-step'), 10) || 0;
						var $input = $field.find('[data-sp-related-posts-count]');
						var next = clampCount($field, (parseInt($input.val(), 10) || 1) + step);
						var key = $field.attr('data-field-name') || Math.random().toString(36);

						clearTimeout(timers[key]);
						$input.val(next);
						syncStepper($field, next);
						refresh($field);
					});

					$(function() {
						$('[data-sp-related-posts]').each(function() {
							var $field = $(this);
							$field.attr('aria-busy', 'false');
							syncStepper($field, $field.find('[data-sp-related-posts-count]').val());
						});
					});
				})(jQuery);
			</script>
			<?php
		}
	}

	acf_register_field_type('SP_ACF_Field_Related_Posts');
});
