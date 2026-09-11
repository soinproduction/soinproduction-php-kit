<?php
declare(strict_types=1);

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['background_attachments'] = [
	11 => [ 'mime' => 'image/jpeg', 'url' => 'https://example.test/original.jpg', 'full' => 'https://example.test/full.jpg' ],
	21 => [ 'mime' => 'video/mp4', 'url' => 'https://example.test/background.mp4' ],
	22 => [ 'mime' => 'video/webm', 'url' => 'https://example.test/background.webm' ],
	23 => [ 'mime' => 'image/png', 'url' => 'https://example.test/poster-original.png', 'full' => 'https://example.test/poster-full.png' ],
	24 => [ 'mime' => 'application/pdf', 'url' => 'https://example.test/not-video.pdf' ],
];

function add_action( ...$args ): void {}
function apply_filters( string $hook, $value ) { return $value; }
function absint( $value ): int { return abs( (int) $value ); }
function sanitize_key( string $value ): string { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ) ?? ''; }
function sanitize_hex_color( string $value ) { return preg_match( '/^#[0-9a-f]{6}$/i', $value ) ? strtoupper( $value ) : null; }
function sanitize_html_class( string $value ): string { return preg_replace( '/[^a-z0-9_-]/i', '', $value ) ?? ''; }
function esc_attr( string $value ): string { return htmlspecialchars( $value, ENT_QUOTES ); }
function esc_url( string $value ): string { return $value; }
function wp_parse_args( $args, array $defaults = [] ): array { return array_merge( $defaults, is_array( $args ) ? $args : [] ); }
function get_post_mime_type( int $attachment_id ): string { return $GLOBALS['background_attachments'][ $attachment_id ]['mime'] ?? ''; }
function wp_get_attachment_url( int $attachment_id ) { return $GLOBALS['background_attachments'][ $attachment_id ]['url'] ?? false; }
function wp_get_attachment_image_url( int $attachment_id, string $size = 'thumbnail' ) {
	$attachment = $GLOBALS['background_attachments'][ $attachment_id ] ?? [];
	return $attachment[ $size ] ?? $attachment['url'] ?? false;
}
function wp_get_attachment_image_srcset(): bool { return false; }
function wp_get_attachment_image_sizes(): bool { return false; }

require dirname( __DIR__ ) . '/acf/sp-background-media/index.php';

$video = sp_background_media_normalize_variant( [
	'media_type' => 'video',
	'mp4_id'     => 21,
	'webm_id'    => 22,
	'poster_id'  => 23,
	'position_x' => 40,
] );
$legacy_video = sp_background_media_normalize_variant( [
	'attachment_id' => 21,
	'poster_id'     => 23,
] );
$image = sp_background_media_normalize_variant( [
	'attachment_id' => 11,
] );
$inherited = sp_background_media_normalize_variant( [
	'position_x' => 25,
], $video );
$invalid = sp_background_media_normalize_variant( [
	'media_type' => 'video',
	'mp4_id'     => 24,
] );

ob_start();
display_background_media( [
	'desktop' => [
		'media_type' => 'video',
		'mp4_id'     => 21,
		'webm_id'    => 22,
		'poster_id'  => 23,
	],
] );
$html = (string) ob_get_clean();

$webm_position = strpos( $html, 'background.webm' );
$mp4_position  = strpos( $html, 'background.mp4' );
$checks = [
	'video variant keeps both source IDs'       => $video['mp4_id'] === 21 && $video['webm_id'] === 22,
	'WEBM is preferred before MP4 fallback'     => $video['video_sources'][0]['mime_type'] === 'video/webm' && $video['video_sources'][1]['mime_type'] === 'video/mp4',
	'poster uses its full-size URL'              => $video['poster_url'] === 'https://example.test/poster-full.png',
	'legacy single MP4 migrates automatically'  => $legacy_video['media_type'] === 'video' && $legacy_video['mp4_id'] === 21,
	'legacy image migrates automatically'       => $image['media_type'] === 'image' && $image['image_id'] === 11,
	'inherited video keeps both formats'         => $inherited['inherited'] === true && $inherited['mp4_id'] === 21 && $inherited['webm_id'] === 22,
	'inherited variant keeps local focal point' => (float) $inherited['position_x'] === 25.0,
	'invalid video source is rejected'           => $invalid === [],
	'frontend renders full poster'               => str_contains( $html, 'poster="https://example.test/poster-full.png"' ),
	'frontend renders WEBM before MP4'           => false !== $webm_position && false !== $mp4_position && $webm_position < $mp4_position,
];

$failed = array_keys( array_filter( $checks, static fn( bool $passed ): bool => ! $passed ) );
if ( $failed ) {
	fwrite( STDERR, "Background Media checks failed:\n- " . implode( "\n- ", $failed ) . "\n" );
	exit( 1 );
}

echo 'Background Media: ' . count( $checks ) . " checks passed.\n";
