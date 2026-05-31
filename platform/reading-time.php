<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! function_exists( 'sp_reading_time' ) ) {
	/**
	 * Returns estimated reading time as a string.
	 *
	 * Usage:
	 *   echo sp_reading_time( get_post_field( 'post_content', $post_id ) );
	 *   // → "5 min read"  or  "5–6 min read"
	 *
	 * Custom label:
	 *   echo sp_reading_time( $content, 'min read' );
	 *
	 * @param string $content  Raw HTML or plain text.
	 * @param string $label    Suffix label.
	 * @return string
	 */
	function sp_reading_time( string $content, string $label = 'min read' ): string {
		// Strip HTML tags and decode entities
		$text  = wp_strip_all_tags( $content );
		$text  = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		// Count words — preg_match_all returns the count directly
		$words = (int) preg_match_all( '/\S+/u', $text );

		if ( $words === 0 ) return '< 1 ' . $label;

		// 200 wpm average
		$minutes = $words / 200;

		if ( $minutes < 1 ) return '1 ' . $label;

		$low  = (int) floor( $minutes );
		$high = $low + 1;

		// Show single number when very close to a whole minute
		if ( ( $minutes - $low ) <= 0.15 ) return $low . ' ' . $label;

		return sprite( 15, 15, 'clock' ) . $low . '–' . $high . ' ' . $label;
	}
}
