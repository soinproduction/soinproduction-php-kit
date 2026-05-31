<?php
	if (!defined('ABSPATH')) exit;

	add_action('init', function () {
		$role_id   = 'content_admin';
		$role_name = 'Content Admin';

		$admin = get_role('administrator');
		if (!$admin) return;

		$admin_caps = is_array($admin->capabilities) ? $admin->capabilities : [];

		$deny = [
			'activate_plugins',
			'deactivate_plugins',
			'install_plugins',
			'delete_plugins',
			'update_plugins',
			'edit_plugins',
			'upload_plugins',
		];

		$target_caps = [];
		foreach ($admin_caps as $cap => $grant) {
			if ($grant && !in_array($cap, $deny, true)) {
				$target_caps[$cap] = true;
			}
		}

		$role = get_role($role_id);
		if (!$role) {
			add_role($role_id, $role_name, $target_caps);
		} else {
			$current = is_array($role->capabilities) ? $role->capabilities : [];

			foreach ($current as $cap => $grant) {
				if (!isset($target_caps[$cap]) && $grant) {
					$role->remove_cap($cap);
				}
			}

			foreach ($target_caps as $cap => $_) {
				if (empty($current[$cap])) {
					$role->add_cap($cap);
				}
			}
		}
	});

	function sp_is_content_admin(): bool {
		if (!is_user_logged_in()) return false;
		$user = wp_get_current_user();
		return in_array('content_admin', (array)$user->roles, true);
	}

	add_filter('map_meta_cap', function (array $caps, string $cap) {
		if (!sp_is_content_admin()) return $caps;

		$blocked = [
			'activate_plugins',
			'deactivate_plugins',
			'install_plugins',
			'delete_plugins',
			'update_plugins',
			'edit_plugins',
			'upload_plugins',
		];

		if (in_array($cap, $blocked, true)) {
			return ['do_not_allow'];
		}

		return $caps;
	}, 10, 2);

	add_action('admin_menu', function () {
		if (!sp_is_content_admin()) return;
		remove_menu_page('plugins.php');
		remove_submenu_page('plugins.php', 'plugin-install.php');
		remove_submenu_page('plugins.php', 'plugin-editor.php');
	}, 999);

	add_action('admin_init', function () {
		if (!sp_is_content_admin()) return;

		global $pagenow;

		$blocked_pages = [
			'plugins.php',
			'plugin-install.php',
			'plugin-editor.php',
			'update.php',
		];

		if (in_array($pagenow, $blocked_pages, true)) {
			wp_die(__('Недостаточно прав для доступа к этой странице.'), 403);
		}

		if ($pagenow === 'update.php' && isset($_REQUEST['action'])) {
			$actions = [
				'install-plugin','upload-plugin',
				'activate','activate-selected',
				'upgrade-plugin','update-plugin',
				'update-selected','delete-selected','bulk-plugins',
			];
			if (in_array($_REQUEST['action'], $actions, true)) {
				wp_die(__('Действие запрещено вашей ролью.'), 403);
			}
		}
	});

	add_action('admin_bar_menu', function (WP_Admin_Bar $bar) {
		if (!sp_is_content_admin()) return;
		$bar->remove_node('updates');
	}, 100);
