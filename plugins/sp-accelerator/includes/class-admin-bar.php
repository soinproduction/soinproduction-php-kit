<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SP_Accelerator_Admin_Bar {
	/** @var SP_Accelerator_Config */
	private $config;

	/** @var SP_Accelerator_Cache */
	private $cache;

	public function __construct( SP_Accelerator_Config $config, SP_Accelerator_Cache $cache ) {
		$this->config = $config;
		$this->cache  = $cache;
	}

	public function register(): void {
		add_action( 'admin_bar_menu', [ $this, 'menu' ], 90 );
		add_action( 'admin_post_sp_accelerator_toolbar_purge', [ $this, 'purge' ] );
	}

	/** @param WP_Admin_Bar $bar */
	public function menu( $bar ): void {
		if ( ! is_admin_bar_showing() || ! current_user_can( 'manage_options' ) || ! $this->config->enabled( 'page_cache' ) ) {
			return;
		}

		$purged = isset( $_GET['sp_accelerator_cache'] ) && wp_unslash( $_GET['sp_accelerator_cache'] ) === 'purged';
		$bar->add_node( [
			'id'    => 'sp-accelerator-cache',
			'title' => $purged ? 'Cache cleared' : 'SP Cache',
			'href'  => admin_url( 'options-general.php?page=sp-accelerator' ),
			'meta'  => [ 'title' => 'SP Accelerator' ],
		] );
		$bar->add_node( [
			'id'     => 'sp-accelerator-cache-purge',
			'parent' => 'sp-accelerator-cache',
			'title'  => 'Clear cache',
			'href'   => wp_nonce_url(
				admin_url( 'admin-post.php?action=sp_accelerator_toolbar_purge' ),
				'sp_accelerator_toolbar_purge'
			),
		] );
	}

	public function purge(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Недостаточно прав для очистки кеша SP Accelerator.', '', [ 'response' => 403 ] );
		}

		check_admin_referer( 'sp_accelerator_toolbar_purge' );
		$purged = $this->cache->purge_all();
		$target = wp_get_referer();
		$target = is_string( $target ) && $target !== '' ? $target : admin_url();
		$target = add_query_arg( 'sp_accelerator_cache', $purged ? 'purged' : 'error', $target );

		wp_safe_redirect( $target );
		exit;
	}
}
