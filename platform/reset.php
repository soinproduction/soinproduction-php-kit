<?php
	/**
	 * WordPress Optimization & Cleanup Configuration
	 */

// ============================================================================
// SECURITY & PROTOCOLS
// ============================================================================

	add_filter('kses_allowed_protocols', function ($protocols) {
		return array_merge($protocols, ['viber', 'tg', 'whatsapp']);
	});

	add_filter('xmlrpc_enabled', '__return_false');

	if (!defined('DISALLOW_FILE_EDIT')) {
		define('DISALLOW_FILE_EDIT', true);
	}


// ============================================================================
// DISABLE DEFAULT POST TYPE & TAXONOMIES
// ============================================================================

	add_filter('register_post_type_args', function ($args, $post_type) {
		if ($post_type !== 'post') {
			return $args;
		}

		return array_merge($args, [
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_ui'             => false,
			'show_in_menu'        => false,
			'show_in_admin_bar'   => false,
			'show_in_nav_menus'   => false,
			'show_in_rest'        => false,
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'map_meta_cap'        => true,
			'supports'            => [],
		]);
	}, 10, 2);

	add_action('init', function () {
		if (function_exists('unregister_taxonomy_for_object_type')) {
			unregister_taxonomy_for_object_type('category', 'post');
			unregister_taxonomy_for_object_type('post_tag', 'post');
		}
	}, 99);

	add_filter('register_taxonomy_args', function ($args, $taxonomy) {
		if (!in_array($taxonomy, ['category', 'post_tag'], true)) {
			return $args;
		}

		return array_merge($args, [
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => false,
			'show_admin_column'  => false,
			'show_in_nav_menus'  => false,
			'show_in_rest'       => false,
			'rewrite'            => false,
			'query_var'          => false,
		]);
	}, 10, 2);

	add_action('admin_menu', function () {
		remove_menu_page('edit.php');
		remove_submenu_page('edit.php', 'edit-tags.php?taxonomy=category');
		remove_submenu_page('edit.php', 'edit-tags.php?taxonomy=post_tag');
	}, 999);

	add_action('admin_bar_menu', function ($bar) {
		$bar->remove_node('new-post');
	}, 999);

	add_action('template_redirect', function () {
		if (is_singular('post') || is_post_type_archive('post') || is_home() || is_category() || is_tag()) {
			global $wp_query;
			$wp_query->set_404();
			status_header(404);
			nocache_headers();

			$template = get_query_template('404');
			if ($template) {
				include $template;
				exit;
			}

			get_template_part('404');
			exit;
		}
	});

	add_filter('wp_sitemaps_post_types', function ($post_types) {
		unset($post_types['post']);
		return $post_types;
	});

	add_filter('wp_sitemaps_taxonomies', function ($taxonomies) {
		unset($taxonomies['category'], $taxonomies['post_tag']);
		return $taxonomies;
	});

	add_action('current_screen', function ($screen) {
		$disabled_screens = ['edit-post', 'post', 'edit-category', 'edit-post_tag'];

		if (in_array($screen->id, $disabled_screens, true)) {
			wp_die(
				__('This section is disabled.', 'theme'),
				'',
				['response' => 403]
			);
		}
	});


// ============================================================================
// ADMIN DASHBOARD CLEANUP
// ============================================================================

	add_action('wp_dashboard_setup', function () {
		$widgets_to_remove = [
			'dashboard_quick_press',
			'dashboard_recent_drafts',
			'dashboard_activity',
			'dashboard_primary',
			'dashboard_secondary',
			'dashboard_site_health',
			'dashboard_right_now',
			'dashboard_recent_comments',
		];

		foreach ($widgets_to_remove as $widget) {
			remove_meta_box($widget, 'dashboard', 'normal');
			remove_meta_box($widget, 'dashboard', 'side');
		}
	}, 20);


// ============================================================================
// DISABLE UPDATES
// ============================================================================

	add_filter('site_transient_update_plugins', function ($value) {
		if (isset($value->response) && is_array($value->response)) {
			$value->response = [];
		}
		return $value;
	});

	add_filter('site_transient_update_themes', function ($value) {
		if (isset($value->response) && is_array($value->response)) {
			$value->response = [];
		}
		return $value;
	});

	add_filter('automatic_updater_disabled', '__return_true');
	add_filter('auto_update_core', '__return_false');
	add_filter('auto_update_plugin', '__return_false');
	add_filter('auto_update_theme', '__return_false');


// ============================================================================
// CLEAN WP HEAD
// ============================================================================

	add_action('after_setup_theme', function () {
		$actions_to_remove = [
			['wp_head', 'wp_generator'],
			['wp_head', 'rsd_link'],
			['wp_head', 'wlwmanifest_link'],
			['wp_head', 'wp_shortlink_wp_head'],
			['wp_head', 'feed_links', 2],
			['wp_head', 'feed_links_extra', 3],
			['wp_head', 'print_emoji_detection_script', 7],
			['wp_print_styles', 'print_emoji_styles'],
			['admin_print_scripts', 'print_emoji_detection_script'],
			['admin_print_styles', 'print_emoji_styles'],
			['wp_head', 'rest_output_link_wp_head', 10],
			['wp_head', 'wp_oembed_add_discovery_links'],
			['wp_head', 'wp_oembed_add_host_js'],
			['template_redirect', 'rest_output_link_header', 11],
			['wp_head', 'rel_canonical'],
			['wp_head', 'wp_resource_hints', 2],
		];

		foreach ($actions_to_remove as $action) {
			remove_action(...$action);
		}

		remove_filter('oembed_dataparse', 'wp_filter_oembed_result', 10);
		remove_filter('the_content_feed', 'wp_staticize_emoji');
		remove_filter('comment_text_rss', 'wp_staticize_emoji');
		remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
	});


// ============================================================================
// DISABLE GUTENBERG
// ============================================================================

	add_filter('use_block_editor_for_post', '__return_false', 10);
	add_filter('use_block_editor_for_post_type', '__return_false', 10);
	add_filter('use_widgets_block_editor', '__return_false', 10);
	add_filter('gutenberg_use_widgets_block_editor', '__return_false', 10);
	add_filter('wp_use_widgets_block_editor', '__return_false', 10);
	add_filter('emoji_svg_url', '__return_false');

	add_action('init', function () {
		if (is_admin()) {
			return;
		}

		// WP global styles / duotone / classic styles hooks.
		remove_action('wp_enqueue_scripts', 'wp_enqueue_global_styles');
		remove_action('wp_footer', 'wp_enqueue_global_styles', 1);
		remove_action('wp_enqueue_scripts', 'wp_enqueue_classic_theme_styles');
		remove_action('wp_footer', 'wp_enqueue_stored_styles', 1);
		remove_action('wp_body_open', 'wp_global_styles_render_svg_filters');
		remove_action('in_admin_header', 'wp_global_styles_render_svg_filters');
	}, 20);

	add_action('wp_enqueue_scripts', function () {
		if (is_admin()) {
			return;
		}

		$style_handles = [
			'wp-block-library',
			'wp-block-library-theme',
			'global-styles',
			'classic-theme-styles',
			'wc-block-style',
			'core-block-supports',
		];

		foreach ($style_handles as $handle) {
			wp_dequeue_style($handle);
			wp_deregister_style($handle);
		}

		// Front users do not need dashicons.
		if (!is_user_logged_in()) {
			wp_dequeue_style('dashicons');
			wp_deregister_style('dashicons');
		}
	}, 999);

	add_action('after_setup_theme', function () {
		remove_theme_support('wp-block-styles');
		remove_theme_support('wp-global-styles');
		remove_theme_support('core-block-patterns');
	});


// ============================================================================
// DISABLE COMMENTS
// ============================================================================

	add_filter('comments_open', '__return_false', 20, 2);
	add_filter('pings_open', '__return_false', 20, 2);
	add_filter('comments_array', '__return_empty_array', 10, 2);
	add_filter('comment_notification_recipients', '__return_empty_array');
	add_filter('comment_moderation_recipients', '__return_empty_array');

	add_action('admin_init', function () {
		if (is_admin_bar_showing()) {
			remove_action('admin_bar_menu', 'wp_admin_bar_comments_menu', 60);
		}

		$comment_metaboxes = [
			'dashboard_recent_comments',
			'commentsdiv',
			'commentstatusdiv',
			'trackbacksdiv',
		];

		foreach ($comment_metaboxes as $metabox) {
			remove_meta_box($metabox, 'post', 'normal');
			remove_meta_box($metabox, 'dashboard', 'normal');
		}
	});

	add_action('admin_menu', function () {
		remove_menu_page('edit-comments.php');
	});

	add_filter('rest_endpoints', function ($endpoints) {
		if (isset($endpoints['/wp/v2/comments'])) {
			unset($endpoints['/wp/v2/comments']);
		}
		return $endpoints;
	});

	add_action('after_switch_theme', function () {
		update_option('default_comment_status', 'closed');
		update_option('default_ping_status', 'closed');
		update_option('comment_registration', '1');
		update_option('comment_moderation', '0');
		update_option('comment_previously_approved', '0');
		update_option('comments_notify', '0');
		update_option('moderation_notify', '0');
	});

	function sp_reset_find_page_by_title(string $title): int {
		$page = get_page_by_title($title, OBJECT, 'page');
		return $page instanceof WP_Post ? (int) $page->ID : 0;
	}

	function sp_reset_ensure_home_page(): int {
		$page_id = sp_reset_find_page_by_title('Home');

		if ($page_id > 0) {
			$page = get_post($page_id);
			if ($page instanceof WP_Post && $page->post_status === 'trash') {
				wp_untrash_post($page_id);
			}

			return $page_id;
		}

		$page_id = wp_insert_post([
			'post_type'    => 'page',
			'post_title'   => 'Home',
			'post_name'    => 'home',
			'post_status'  => 'publish',
			'post_content' => '',
		], true);

		return is_wp_error($page_id) ? 0 : (int) $page_id;
	}

	function sp_reset_delete_default_content(): void {
		$default_post = get_post(1);
		if ($default_post instanceof WP_Post && $default_post->post_type === 'post' && $default_post->post_title === 'Hello world!') {
			wp_delete_post($default_post->ID, true);
		}

		$sample_page = get_post(2);
		if ($sample_page instanceof WP_Post && $sample_page->post_type === 'page' && $sample_page->post_title === 'Sample Page') {
			wp_delete_post($sample_page->ID, true);
		}
	}

	add_action('after_switch_theme', function () {
		sp_reset_delete_default_content();

		$home_page_id = sp_reset_ensure_home_page();

		if ($home_page_id > 0) {
			update_option('show_on_front', 'page');
			update_option('page_on_front', $home_page_id);
			update_option('page_for_posts', 0);
		}
	});


// ============================================================================
// AUTOSAVE & REVISIONS
// ============================================================================

	if (!defined('AUTOSAVE_INTERVAL')) {
		define('AUTOSAVE_INTERVAL', 300);
	}

	if (!defined('EMPTY_TRASH_DAYS')) {
		define('EMPTY_TRASH_DAYS', 0);
	}

	if (!defined('WP_POST_REVISIONS')) {
		define('WP_POST_REVISIONS', false);
	}


// ============================================================================
// PERFORMANCE OPTIMIZATIONS
// ============================================================================

	add_action('wp_enqueue_scripts', function () {
		wp_deregister_script('jquery-migrate');

		wp_dequeue_script('wp-embed');
	});

// Version stripping removed to allow cache busting via _S_VERSION

	add_filter('heartbeat_settings', function ($settings) {
		$settings['interval'] = 60;
		return $settings;
	});

	add_action('init', function () {
		if (!is_admin()) {
			wp_deregister_script('heartbeat');
		}
	});


// ============================================================================
// CUSTOM PAGE QUERY ARG (DISABLE WP DEFAULT FRONT BEHAVIOR FOR `page`)
// ============================================================================

	add_filter('query_vars', function (array $vars): array {
		if (is_admin()) {
			return $vars;
		}

		return array_values(
			array_filter(
				$vars,
				static fn(string $var): bool => $var !== 'page'
			)
		);
	}, 999);

	add_filter('request', function (array $query_vars): array {
		if (is_admin()) {
			return $query_vars;
		}

		if (isset($query_vars['page'])) {
			unset($query_vars['page']);
		}

		return $query_vars;
	}, 999);

	add_filter('redirect_canonical', function ($redirect_url, $requested_url) {
		if (is_admin()) {
			return $redirect_url;
		}

		if (!isset($_GET['page'])) {
			return $redirect_url;
		}

		$page = sanitize_text_field(wp_unslash((string) $_GET['page']));
		if ($page === '' || !preg_match('/^\d+$/', $page)) {
			return $redirect_url;
		}

		// Keep custom `?page=N` untouched for front-end custom pagination.
		return false;
	}, 999, 2);


// ============================================================================
// REWRITE RULES FLUSH
// ============================================================================

	add_action('after_switch_theme', function () {
		flush_rewrite_rules();
	});
