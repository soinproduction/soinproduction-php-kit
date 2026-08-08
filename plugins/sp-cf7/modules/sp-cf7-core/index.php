<?php

	add_filter( 'wpcf7_autop_or_not', '__return_false' );

//  Replace the shortcode [theme-url] with the URL of the theme's assets directory in Contact Form 7 forms

	add_filter( 'wpcf7_form_elements', function ( $content ) {
		$content   = str_replace( '[theme-url]', BUILD, $content );

		return $content;
	});


	add_filter( 'wpcf7_form_elements', function ( string $html ): string {
		return do_shortcode( $html );
	}, 999 );


	add_filter( 'shortcode_atts_wpcf7', function ( $out, $pairs, $atts ) {
		if ( ! isset( $atts['html_class'] ) ) {
			$out['html_class'] = 'main-form grid-cols';
		}

		return $out;
	}, 10, 3 );



	function acf_load_cf7_forms( $field ) {
		$field['choices'] = array();

		$args  = array(
			'post_type'      => 'wpcf7_contact_form',
			'posts_per_page' => - 1,
		);
		$forms = get_posts( $args );

		if ( $forms ) {
			foreach ( $forms as $form ) {
				$field['choices'][ $form->ID ] = $form->post_title;
			}
		}

		return $field;
	}

	add_filter( 'acf/load_field/name=form_select', 'acf_load_cf7_forms' );

