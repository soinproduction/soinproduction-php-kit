<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! function_exists( 'sp_reading_time_word_count' ) ) {
	function sp_reading_time_word_count( string $content ): int {
		if ( function_exists( 'strip_shortcodes' ) ) {
			$content = strip_shortcodes( $content );
		}

		$content = preg_replace( '~https?://\S+|www\.\S+~i', ' ', $content );
		$text = wp_strip_all_tags( $content );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		return (int) preg_match_all( '/\S+/u', $text );
	}
}

if ( ! function_exists( 'sp_reading_time_format' ) ) {
	function sp_reading_time_format( int $words, string $label = 'min read' ): string {
		if ( $words === 0 ) return '< 1 ' . $label;

		$minutes = $words / 200;

		if ( $minutes < 1 ) return '1 ' . $label;

		$low  = (int) floor( $minutes );
		$high = $low + 1;

		if ( ( $minutes - $low ) <= 0.15 ) return $low . ' ' . $label;

		ob_start();
		if ( function_exists( 'sprite' ) ) {
			sprite( 15, 15, 'clock' );
		}
		$icon = ob_get_clean();

		return $icon . $low . '–' . $high . ' ' . $label;
	}
}

if ( ! function_exists( 'sp_reading_time_skip_keys' ) ) {
	function sp_reading_time_skip_keys(): array {
		return apply_filters( 'sp_reading_time_skip_keys', [
			'acf_fc_layout',
			'archive',
			'attachment',
			'avatar',
			'background',
			'bg',
			'button',
			'buttons',
			'class',
			'classes',
			'color',
			'cover',
			'css',
			'file',
			'full_row',
			'gallery',
			'icon',
			'id',
			'image',
			'images',
			'link',
			'links',
			'logo',
			'media',
			'mime',
			'mime_type',
			'post_type',
			'preview',
			'rel',
			'section_bg',
			'section_id',
			'sizes',
			'style',
			'target',
			'taxonomy',
			'type',
			'url',
			'video',
		] );
	}
}

if ( ! function_exists( 'sp_reading_time_is_media_array' ) ) {
	function sp_reading_time_is_media_array( array $value ): bool {
		$keys = array_map( 'sanitize_key', array_keys( $value ) );

		if ( in_array( 'url', $keys, true ) && ( in_array( 'id', $keys, true ) || in_array( 'mime_type', $keys, true ) || in_array( 'sizes', $keys, true ) ) ) {
			return true;
		}

		return in_array( 'filename', $keys, true ) && in_array( 'filesize', $keys, true );
	}
}

if ( ! function_exists( 'sp_reading_time_is_link_array' ) ) {
	function sp_reading_time_is_link_array( array $value ): bool {
		$keys = array_map( 'sanitize_key', array_keys( $value ) );

		return in_array( 'url', $keys, true ) && ( in_array( 'title', $keys, true ) || in_array( 'target', $keys, true ) );
	}
}

if ( ! function_exists( 'sp_reading_time_is_countable_string' ) ) {
	function sp_reading_time_is_countable_string( string $value ): bool {
		$value = trim( $value );

		if ( $value === '' || is_numeric( $value ) ) {
			return false;
		}

		if ( filter_var( $value, FILTER_VALIDATE_URL ) || filter_var( $value, FILTER_VALIDATE_EMAIL ) ) {
			return false;
		}

		if ( preg_match( '/^#(?:[0-9a-f]{3}){1,2}$/i', $value ) ) {
			return false;
		}

		if ( preg_match( '~^(?:https?:)?//|^data:|^mailto:|^tel:~i', $value ) ) {
			return false;
		}

		if ( preg_match( '~\.(?:jpe?g|png|gif|webp|svg|avif|mp4|webm|pdf)(?:[?#].*)?$~i', $value ) ) {
			return false;
		}

		return true;
	}
}

if ( ! function_exists( 'sp_reading_time_collect_text' ) ) {
	function sp_reading_time_collect_text( $value, string $key = '' ): string {
		$key = sanitize_key( $key );

		if ( in_array( $key, sp_reading_time_skip_keys(), true ) ) {
			return '';
		}

		if ( is_array( $value ) ) {
			if ( sp_reading_time_is_media_array( $value ) || sp_reading_time_is_link_array( $value ) ) {
				return '';
			}

			$text = [];

			foreach ( $value as $item_key => $item ) {
				$text[] = sp_reading_time_collect_text( $item, is_string( $item_key ) ? $item_key : '' );
			}

			return implode( ' ', array_filter( $text ) );
		}

		if ( is_object( $value ) ) {
			return '';
		}

		if ( is_string( $value ) ) {
			return sp_reading_time_is_countable_string( $value ) ? trim( $value ) : '';
		}

		return '';
	}
}

if ( ! function_exists( 'sp_reading_time_post_content' ) ) {
	function sp_reading_time_post_content( int $post_id = 0, array $acf_fields = [] ): string {
		$post_id = $post_id > 0 ? $post_id : (int) get_the_ID();

		if ( $post_id <= 0 || ! get_post( $post_id ) ) {
			return '';
		}

		$parts = [
			(string) get_post_field( 'post_content', $post_id ),
		];

		if ( function_exists( 'get_field' ) ) {
			if ( empty( $acf_fields ) && function_exists( 'get_fields' ) ) {
				$parts[] = sp_reading_time_collect_text( get_fields( $post_id ) );
			} else {
				$acf_fields = apply_filters( 'sp_reading_time_acf_fields', $acf_fields, $post_id );
				$acf_fields = is_array( $acf_fields ) ? $acf_fields : [];

				foreach ( $acf_fields as $field_name ) {
					$field_name = sanitize_key( (string) $field_name );

					if ( $field_name === '' ) {
						continue;
					}

					$parts[] = sp_reading_time_collect_text( get_field( $field_name, $post_id ), $field_name );
				}
			}
		}

		return trim( implode( ' ', array_filter( $parts ) ) );
	}
}

if ( ! function_exists( 'sp_reading_time_for_post' ) ) {
	function sp_reading_time_for_post( int $post_id = 0, string $label = 'min read', array $acf_fields = [] ): string {
		return sp_reading_time( sp_reading_time_post_content( $post_id, $acf_fields ), $label );
	}
}

if ( ! function_exists( 'sp_reading_time' ) ) {
	/**
	 * Returns estimated reading time as a string.
	 *
	 * Usage:
	 *   echo sp_reading_time( get_post_field( 'post_content', $post_id ) );
	 *   echo sp_reading_time_for_post( $post_id );
	 *   // → "5 min read"  or  "5–6 min read"
	 *
	 * Custom label:
	 *   echo sp_reading_time( $content, 'min read' );
	 *
	 * @param mixed $content  Raw HTML/plain text, null for current post, or an integer post ID.
	 * @param string $label    Suffix label.
	 * @return string
	 */
	function sp_reading_time( $content = null, string $label = 'min read' ): string {
		if ( $content === null ) {
			$content = sp_reading_time_post_content();
		} elseif ( is_int( $content ) ) {
			$content = sp_reading_time_post_content( $content );
		}

		return sp_reading_time_format( sp_reading_time_word_count( (string) $content ), $label );
	}
}
