<?php

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	if ( ! function_exists( 'register_acf_text_column' ) ) {
		/**
		 * Register a read-only text column for a post type or taxonomy list table.
		 *
		 * A non-empty ACF field reads a scalar ACF value. When the field is empty,
		 * posts use their native excerpt and terms use their native description.
		 */
		function register_acf_text_column(
			string $type,
			string $object,
			string $column_label = 'Text',
			string $after = '',
			string $acf_field = '',
			string $column_key = '',
			int $width = 0,
			int $max_words = 0,
			string $prefix = '',
			string $suffix = '',
		): void {
			$type   = sanitize_key( $type );
			$object = sanitize_key( $object );

			if ( ! in_array( $type, [ 'post', 'term' ], true ) || $object === '' ) {
				return;
			}

			$acf_field  = sanitize_key( $acf_field );
			$source_key = $acf_field !== '' ? $acf_field : ( $type === 'post' ? 'excerpt' : 'description' );
			$column_key = sanitize_key( $column_key !== '' ? $column_key : "sp_text_{$object}_{$source_key}" );
			$after      = sanitize_key( $after !== '' ? $after : ( $type === 'post' ? 'title' : 'name' ) );
			$width      = max( 0, $width );
			$max_words  = max( 0, $max_words );

			if ( $column_key === '' ) {
				return;
			}

			$get_acf_key = static fn( int $id ): int|string => $type === 'post' ? $id : "{$object}_{$id}";

			$normalize_text = static function ( mixed $value ): string {
				if ( is_scalar( $value ) || $value instanceof Stringable ) {
					return trim( wp_strip_all_tags( (string) $value ) );
				}

				return '';
			};

			$get_text = static function ( int $id ) use ( $type, $object, $acf_field, $get_acf_key, $normalize_text, $max_words, $prefix, $suffix ): string {
				if ( $acf_field !== '' ) {
					$value = function_exists( 'get_field' ) ? get_field( $acf_field, $get_acf_key( $id ) ) : '';
				} elseif ( $type === 'post' ) {
					$value = get_the_excerpt( $id );
				} else {
					$value = get_term_field( 'description', $id, $object );
					$value = is_wp_error( $value ) ? '' : $value;
				}

				$text = $normalize_text( $value );
				if ( $text === '' ) {
					return '';
				}

				if ( $max_words > 0 ) {
					$text = wp_trim_words( $text, $max_words );
				}

				return $prefix . $text . $suffix;
			};

			$inject_column = static function ( array $columns ) use ( $column_key, $column_label, $after ): array {
				$result = [];

				foreach ( $columns as $key => $label ) {
					$result[ $key ] = $label;
					if ( $key === $after ) {
						$result[ $column_key ] = __( $column_label, 'ACF Fields' );
					}
				}

				if ( ! isset( $result[ $column_key ] ) ) {
					$result[ $column_key ] = __( $column_label, 'ACF Fields' );
				}

				return $result;
			};

			$render_cell = static function ( int $id ) use ( $get_text ): string {
				$text = $get_text( $id );

				return $text !== ''
					? '<div class="sp-admin-text-column__value">' . nl2br( esc_html( $text ) ) . '</div>'
					: '<span class="sp-admin-text-column__empty" aria-label="' . esc_attr__( 'Empty', 'ACF Fields' ) . '">&mdash;</span>';
			};

			if ( $width > 0 ) {
				add_action( 'admin_head', static function () use ( $type, $object, $column_key, $width ): void {
					$screen = get_current_screen();
					if ( ! $screen ) {
						return;
					}

					$is_target_screen = $type === 'post'
						? $screen->base === 'edit' && $screen->post_type === $object
						: $screen->base === 'edit-tags' && $screen->taxonomy === $object;

					if ( $is_target_screen ) {
						echo '<style>.column-' . esc_attr( $column_key ) . '{width:' . (int) $width . 'px}</style>';
					}
				} );
			}

			if ( $type === 'post' ) {
				add_filter( "manage_{$object}_posts_columns", static fn( array $columns ): array => $inject_column( $columns ) );

				add_action( "manage_{$object}_posts_custom_column", static function ( string $column, int $post_id ) use ( $column_key, $render_cell ): void {
					if ( $column === $column_key ) {
						echo $render_cell( $post_id );
					}
				}, 10, 2 );
			} else {
				add_filter( "manage_edit-{$object}_columns", static fn( array $columns ): array => $inject_column( $columns ) );

				add_filter( "manage_{$object}_custom_column", static function ( string $output, string $column, int $term_id ) use ( $column_key, $render_cell ): string {
					return $column === $column_key ? $render_cell( $term_id ) : $output;
				}, 10, 3 );
			}
		}
	}

	add_action( 'admin_head', static function (): void {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->base, [ 'edit', 'edit-tags' ], true ) ) {
			return;
		}
		?>
		<style>
			.sp-admin-text-column__value {
				max-width: 100%;
				overflow-wrap: anywhere;
				white-space: normal;
			}

			.sp-admin-text-column__empty {
				color: #8c8f94;
			}
		</style>
		<?php
	} );
