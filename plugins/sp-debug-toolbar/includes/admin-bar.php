<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'sp_debug_toolbar_query_monitor_link' ) ) {
	/** @param WP_Admin_Bar $bar */
	function sp_debug_toolbar_query_monitor_link( $bar ): void {
		if (
			! defined( 'DEV_MODE' )
			|| ! DEV_MODE
			|| ! is_admin_bar_showing()
			|| ! current_user_can( 'manage_options' )
			|| defined( 'QM_VERSION' )
		) {
			return;
		}

		$plugin = 'query-monitor/query-monitor.php';
		if ( defined( 'WP_PLUGIN_DIR' ) && is_file( trailingslashit( WP_PLUGIN_DIR ) . $plugin ) ) {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}

			$title = 'Activate Query Monitor';
			$url = wp_nonce_url(
				self_admin_url( 'plugins.php?action=activate&plugin=' . rawurlencode( $plugin ) ),
				'activate-plugin_' . $plugin
			);
		} else {
			if ( ! current_user_can( 'install_plugins' ) ) {
				return;
			}

			$title = 'Install Query Monitor';
			$url = wp_nonce_url(
				self_admin_url( 'update.php?action=install-plugin&plugin=query-monitor' ),
				'install-plugin_query-monitor'
			);
		}

		$bar->add_node( [
			'id'    => 'sp-query-monitor',
			'title' => $title,
			'href'  => $url,
			'meta'  => [ 'title' => $title ],
		] );
	}

	add_action( 'admin_bar_menu', 'sp_debug_toolbar_query_monitor_link', 95 );
}
