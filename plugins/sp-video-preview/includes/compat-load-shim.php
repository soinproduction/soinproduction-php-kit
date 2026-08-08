<?php
	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	$sp_vp_index = __DIR__ . '/index.php';

	if ( function_exists( 'opcache_invalidate' ) && is_file( $sp_vp_index ) ) {
		@opcache_invalidate( $sp_vp_index, true );
	}

	add_action( 'admin_enqueue_scripts', static function (): void {
		if ( ! is_admin() ) {
			return;
		}

		wp_enqueue_script( 'jquery' );

		$shim = <<<'JS'
			(function () {
				const $ = window.jQuery;
				if (!$ || !$.fn || $.fn.__spVideoPreviewLoadShim === true) {
					return;
				}
			
				const originalLoad = $.fn.load;
			
				$.fn.load = function () {
					if (arguments.length === 0) {
						const node = this && this[0] ? this[0] : null;
						if (node && typeof node.load === 'function') {
							try {
								node.load();
							} catch (e) {
								
							}
						}
						return this;
					}
			
					if (typeof originalLoad === 'function') {
						return originalLoad.apply(this, arguments);
					}
			
					return this;
				};
			
				$.fn.__spVideoPreviewLoadShim = true;
			})();
		JS;

		wp_add_inline_script( 'jquery', $shim, 'after' );
	}, 0 );
