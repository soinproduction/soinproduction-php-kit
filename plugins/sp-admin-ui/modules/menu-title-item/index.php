<?php
	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	function sp_menu_title_item_class(): string {
		return 'menu-item-heading';
	}

	function sp_menu_title_item_is_heading( $item ): bool {
		if ( ! is_object( $item ) ) {
			return false;
		}

		$classes = ! empty( $item->classes ) ? (array) $item->classes : [];

		return in_array( sp_menu_title_item_class(), $classes, true );
	}

	function sp_menu_title_item_hide_meta_key(): string {
		return '_sp_menu_title_hide_on_front';
	}

	function sp_menu_title_item_is_hidden_on_front( $item ): bool {
		$item_id = is_object( $item ) && isset( $item->ID ) ? (int) $item->ID : 0;

		if ( ! $item_id ) {
			return false;
		}

		return get_post_meta( $item_id, sp_menu_title_item_hide_meta_key(), true ) === '1';
	}

	function sp_menu_title_item_attribute_string( array $attributes ): string {
		$parts = [];

		foreach ( $attributes as $name => $value ) {
			if ( $value === '' || $value === null || $value === false ) {
				continue;
			}

			if ( $value === true ) {
				$parts[] = esc_attr( $name );
				continue;
			}

			$parts[] = sprintf( '%s="%s"', esc_attr( $name ), esc_attr( (string) $value ) );
		}

		return $parts ? ' ' . implode( ' ', $parts ) : '';
	}

	function sp_menu_title_item_render( $item, $args = null, int $depth = 0 ): string {
		if ( sp_menu_title_item_is_hidden_on_front( $item ) ) {
			return '';
		}

		$title = is_object( $item ) && isset( $item->title )
			? apply_filters( 'the_title', $item->title, $item->ID ?? 0 )
			: '';

		$title = wp_kses_post( $title );
		$plain = trim( wp_strip_all_tags( html_entity_decode( $title, ENT_QUOTES, 'UTF-8' ) ) );

		if ( $plain === '' ) {
			return '';
		}

		$attributes = apply_filters( 'sp_menu_title_item_attributes', [
			'class'      => 'menu-title-item',
			'data-depth' => (string) max( 0, $depth ),
		], $item, $args, $depth );

		$attributes = is_array( $attributes ) ? $attributes : [];

		return sprintf(
			'<span%s><span class="menu-title-item__text">%s</span></span>',
			sp_menu_title_item_attribute_string( $attributes ),
			$title
		);
	}

	add_action( 'load-nav-menus.php', function () {
		add_meta_box(
			'add-sp-menu-title-item',
			__( 'Menu Title', 'ACF Fields' ),
			'sp_render_menu_title_item_metabox',
			'nav-menus',
			'side',
			'default'
		);
	} );

	function sp_render_menu_title_item_metabox(): void {
		global $nav_menu_selected_id;

		$input_id  = 'sp-menu-title-item-name';
		$button_id = 'submit-sp-menu-title-item';
		?>
        <div id="sp-menu-title-item" class="customlinkdiv sp-admin-component">
            <p id="sp-menu-title-item-name-wrap" class="wp-clearfix">
                <label class="howto" for="<?php echo esc_attr( $input_id ); ?>">
					<?php esc_html_e( 'Menu Title', 'ACF Fields' ); ?>
                </label>
                <input id="<?php echo esc_attr( $input_id ); ?>" type="text" <?php wp_nav_menu_disabled_check( $nav_menu_selected_id ); ?>class="regular-text menu-item-textbox"/>
            </p>

            <p class="howto">
				<?php esc_html_e( 'Adds a non-clickable heading item that can be dragged, nested and saved like regular menu items.', 'ACF Fields' ); ?>
            </p>

            <p class="button-controls wp-clearfix">
                <span class="add-to-menu">
	                    <input id="<?php echo esc_attr( $button_id ); ?>" type="button" <?php wp_nav_menu_disabled_check( $nav_menu_selected_id ); ?>class="button right" value="<?php echo esc_attr__( 'Add to Menu', 'ACF Fields' ); ?>"/>
                    <span class="spinner"></span>
                </span>
            </p>
        </div>
		<?php
	}

	add_action( 'admin_enqueue_scripts', function ( string $hook_suffix ): void {
		if ( $hook_suffix !== 'nav-menus.php' ) {
			return;
		}

		$heading_class = wp_json_encode( sp_menu_title_item_class() );
		$script        = <<<JS
jQuery(function ($) {
	const input = $('#sp-menu-title-item-name');
	const button = $('#submit-sp-menu-title-item');
	const metabox = $('#sp-menu-title-item');
	const headingClass = {$heading_class};

	if (!input.length || !button.length || !window.wpNavMenu) {
		return;
	}

	const syncHeadingItems = (scope) => {
		$(scope || '#menu-to-edit').find('.menu-item').each(function () {
			const item = $(this);
			const classesInput = item.find('.edit-menu-item-classes');
			const urlField = item.find('.field-url');
			const urlInput = item.find('.edit-menu-item-url');
			const classes = String(classesInput.val() || '');
			const isHeading = classes.split(/\\s+/).includes(headingClass);

			if (!isHeading) {
				return;
			}

			urlInput.val('');
			urlField.hide();
			item.find('.item-type').text('Menu Title');
		});
	};

	const addMenuTitleItem = () => {
		const title = String(input.val() || '').trim();

		if (!title) {
			input.trigger('focus');
			return false;
		}

		$('.spinner', metabox).addClass('is-active');

		window.wpNavMenu.addItemToMenu({
			'-1': {
				'menu-item-type': 'custom',
				'menu-item-url': '#',
				'menu-item-title': title,
				'menu-item-classes': headingClass
			}
		}, window.wpNavMenu.addMenuItemToBottom, function () {
			$('.spinner', metabox).removeClass('is-active');
			input.val('').trigger('focus');
			syncHeadingItems();
		});

		return false;
	};

	button.on('click', function (event) {
		event.preventDefault();
		event.stopPropagation();
		addMenuTitleItem();
	});

	input.on('keydown', function (event) {
		if (event.key === 'Enter') {
			event.preventDefault();
			addMenuTitleItem();
		}
	});

	$(document).on('menu-item-added', function (event, item) {
		syncHeadingItems(item || '#menu-to-edit');
	});

	syncHeadingItems();
});
JS;

		wp_add_inline_script( 'nav-menu', $script, 'after' );
	} );

	add_filter( 'wp_setup_nav_menu_item', function ( $item ) {
		if ( ! sp_menu_title_item_is_heading( $item ) ) {
			return $item;
		}

		$item->type_label = __( 'Menu Title', 'ACF Fields' );
		$item->url        = '';
		$item->sp_menu_title_hide_on_front = sp_menu_title_item_is_hidden_on_front( $item );

		return $item;
	} );

	add_action( 'wp_nav_menu_item_custom_fields', function ( $item_id, $item ) {
		if ( ! sp_menu_title_item_is_heading( $item ) ) {
			return;
		}

		$field_id = 'edit-menu-item-sp-hide-title-' . (int) $item_id;
		$value    = sp_menu_title_item_is_hidden_on_front( $item );
		?>
        <p class="field-sp-menu-title-hide description description-wide">
            <label for="<?php echo esc_attr( $field_id ); ?>">
                <input
                    id="<?php echo esc_attr( $field_id ); ?>"
                    type="checkbox"
                    class="widefat code edit-menu-item-sp-hide-title"
                    name="menu-item-sp-hide-title[<?php echo (int) $item_id; ?>]"
                    value="1"
					<?php checked( $value ); ?>
                />
				<?php esc_html_e( 'Hide title on frontend', 'ACF Fields' ); ?>
            </label>
        </p>
		<?php
	}, 10, 2 );

	add_action( 'wp_update_nav_menu_item', function ( $menu_id, $menu_item_db_id ) {
		$value = isset( $_POST['menu-item-sp-hide-title'][ $menu_item_db_id ] ) ? '1' : '';

		if ( $value === '1' ) {
			update_post_meta( $menu_item_db_id, sp_menu_title_item_hide_meta_key(), '1' );
			return;
		}

		delete_post_meta( $menu_item_db_id, sp_menu_title_item_hide_meta_key() );
	}, 10, 2 );

	add_filter( 'nav_menu_link_attributes', function ( $atts, $item ) {
		if ( ! sp_menu_title_item_is_heading( $item ) ) {
			return $atts;
		}

		$atts['href'] = '';

		return $atts;
	}, 10, 2 );
