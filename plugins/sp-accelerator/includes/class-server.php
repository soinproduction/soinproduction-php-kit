<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Installs conservative static-asset caching and compression rules on Apache /
 * LiteSpeed. Automatic maintenance uses WordPress markers, so foreign
 * .htaccess directives are never replaced.
 */
final class SP_Accelerator_Server {
	private const MARKER = 'SP Accelerator';

	public function path(): string {
		if ( ! function_exists( 'get_home_path' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$home = function_exists( 'get_home_path' ) ? get_home_path() : ABSPATH;
		return trailingslashit( $home ) . '.htaccess';
	}

	/** @return array{code:string,label:string,detail:string} */
	public function status(): array {
		$server = strtolower( (string) ( $_SERVER['SERVER_SOFTWARE'] ?? '' ) );
		$path     = $this->path();
		$contents = is_readable( $path ) ? file_get_contents( $path ) : '';
		$marker   = preg_quote( self::MARKER, '~' );
		$begin_count = is_string( $contents ) ? preg_match_all( '~^# BEGIN ' . $marker . '\r?$~m', $contents, $begin_matches, PREG_OFFSET_CAPTURE ) : 0;
		$end_count   = is_string( $contents ) ? preg_match_all( '~^# END ' . $marker . '\r?$~m', $contents, $end_matches, PREG_OFFSET_CAPTURE ) : 0;
		$begin = $begin_count === 1 ? (int) $begin_matches[0][0][1] : false;
		$end   = $end_count === 1 ? (int) $end_matches[0][0][1] : false;
		if ( $begin_count !== $end_count || $begin_count > 1 || ( $begin !== false && $end !== false && $end <= $begin ) ) {
			return [
				'code'   => 'broken',
				'label'  => 'Marker повреждён',
				'detail' => 'Найдена только часть SP Accelerator marker. Проверьте .htaccess вручную перед изменением.',
			];
		}
		if ( $begin !== false && $end !== false ) {
			$block    = substr( (string) $contents, $begin, $end - $begin );
			$expected = implode( "\n", $this->rules() );
			$policy_start = strpos( $block, '# SP Accelerator static policy v2' );
			$installed_policy = $policy_start === false ? '' : trim( substr( $block, $policy_start ) );
			if ( strpos( $server, 'nginx' ) !== false ) {
				return [
					'code'   => 'ignored',
					'label'  => 'Marker не применяется',
					'detail' => 'SP Accelerator marker найден и может быть безопасно удалён, но Nginx игнорирует .htaccess; настройте policy в Nginx или CDN.',
				];
			}
			if ( $installed_policy !== $expected ) {
				return [
					'code'   => 'outdated',
					'label'  => 'Нужно обновить rules',
					'detail' => 'SP Accelerator marker найден, но его policy не совпадает с текущей версией.',
				];
			}
			return [
				'code'   => 'active',
				'label'  => 'Rules установлены',
				'detail' => 'Marker содержит актуальную cache/compression policy. Наличие модулей и фактические response headers проверяются отдельным HTTP-тестом.',
			];
		}
		if ( strpos( $server, 'nginx' ) !== false ) {
			return [
				'code'   => 'manual',
				'label'  => 'Нужна настройка Nginx',
				'detail' => 'Nginx не читает .htaccess. Настройте долгий browser cache и Brotli/GZIP в конфигурации сервера или CDN.',
			];
		}

		$directory = dirname( $path );
		if ( ( is_file( $path ) && ! is_writable( $path ) ) || ( ! is_file( $path ) && ! is_writable( $directory ) ) ) {
			return [
				'code'   => 'readonly',
				'label'  => 'Нет прав на запись',
				'detail' => 'WordPress не может безопасно обновить корневой .htaccess.',
			];
		}

		return [
			'code'   => 'missing',
			'label'  => 'Не установлены',
			'detail' => 'PageSpeed всё ещё может ругаться на TTL статических файлов и отсутствие server compression.',
		];
	}

	/** @return true|WP_Error */
	public function install() {
		if ( is_multisite() && ! is_super_admin() ) {
			return new WP_Error( 'network_permissions', 'В Multisite серверные правила может менять только super admin.' );
		}
		$status = $this->status();
		if ( in_array( $status['code'], [ 'manual', 'ignored', 'readonly', 'broken' ], true ) ) {
			return new WP_Error( 'server_rules_unavailable', $status['detail'] );
		}

		if ( ! function_exists( 'insert_with_markers' ) ) {
			require_once ABSPATH . 'wp-admin/includes/misc.php';
		}
		if ( ! insert_with_markers( $this->path(), self::MARKER, $this->rules() ) ) {
			return new WP_Error( 'server_rules_write_failed', 'Не удалось записать SP Accelerator marker в .htaccess.' );
		}

		return $this->status()['code'] === 'active'
			? true
			: new WP_Error( 'server_rules_verify_failed', 'Правила были записаны, но проверка marker не прошла.' );
	}

	/** @return true|WP_Error */
	public function remove() {
		if ( is_multisite() && ! is_super_admin() ) {
			return new WP_Error( 'network_permissions', 'В Multisite серверные правила может менять только super admin.' );
		}
		if ( ! in_array( $this->status()['code'], [ 'active', 'outdated', 'ignored' ], true ) ) {
			return new WP_Error( 'server_rules_not_owned', 'SP Accelerator marker в .htaccess не найден.' );
		}
		return $this->remove_marker_block();
	}

	/** @return true|WP_Error */
	private function remove_marker_block() {
		$handle = @fopen( $this->path(), 'r+' );
		if ( $handle === false ) {
			return new WP_Error( 'server_rules_remove_failed', 'Не удалось открыть .htaccess для удаления SP Accelerator marker.' );
		}
		if ( ! @flock( $handle, LOCK_EX ) ) {
			fclose( $handle );
			return new WP_Error( 'server_rules_remove_failed', 'Не удалось заблокировать .htaccess для безопасного удаления SP Accelerator marker.' );
		}

		try {
			if ( rewind( $handle ) === false ) {
				return new WP_Error( 'server_rules_remove_failed', 'Не удалось прочитать .htaccess под блокировкой.' );
			}
			$contents = stream_get_contents( $handle );
			if ( ! is_string( $contents ) ) {
				return new WP_Error( 'server_rules_remove_failed', 'Не удалось прочитать .htaccess под блокировкой.' );
			}

			$marker        = preg_quote( self::MARKER, '~' );
			$begin_count   = preg_match_all( '~^# BEGIN ' . $marker . '\r?$~m', $contents, $begin_matches, PREG_OFFSET_CAPTURE );
			$end_count     = preg_match_all( '~^# END ' . $marker . '\r?$~m', $contents, $end_matches, PREG_OFFSET_CAPTURE );
			if ( $begin_count !== 1 || $end_count !== 1 ) {
				return new WP_Error( 'server_rules_marker_ambiguous', 'SP Accelerator marker изменился во время удаления или содержит неоднозначные границы. Проверьте .htaccess вручную.' );
			}

			$begin_offset = (int) $begin_matches[0][0][1];
			$end_offset   = (int) $end_matches[0][0][1];
			$end_length   = strlen( (string) $end_matches[0][0][0] );
			if ( $end_offset <= $begin_offset ) {
				return new WP_Error( 'server_rules_marker_ambiguous', 'Границы SP Accelerator marker расположены неверно. Проверьте .htaccess вручную.' );
			}

			$block_end = $end_offset + $end_length;
			if ( substr( $contents, $block_end, 1 ) === "\n" ) {
				$block_end++;
			}
			$updated = substr( $contents, 0, $begin_offset ) . substr( $contents, $block_end );
			if ( ! $this->write_locked_contents( $handle, $updated ) ) {
				// Best-effort rollback while the same exclusive lock is still held.
				$this->write_locked_contents( $handle, $contents );
				return new WP_Error( 'server_rules_remove_failed', 'Не удалось удалить SP Accelerator marker из .htaccess.' );
			}

			return true;
		} finally {
			@flock( $handle, LOCK_UN );
			fclose( $handle );
		}
	}

	/** @param resource $handle */
	private function write_locked_contents( $handle, string $contents ): bool {
		if ( rewind( $handle ) === false || ! ftruncate( $handle, 0 ) ) {
			return false;
		}

		$length  = strlen( $contents );
		$written = 0;
		while ( $written < $length ) {
			$bytes = fwrite( $handle, substr( $contents, $written, 8192 ) );
			if ( $bytes === false || $bytes === 0 ) {
				return false;
			}
			$written += $bytes;
		}

		return fflush( $handle );
	}

	/** @return string[] */
	private function rules(): array {
		return [
			'# SP Accelerator static policy v2',
			'<IfModule mod_expires.c>',
			'ExpiresActive On',
			'ExpiresByType text/css "access plus 1 year"',
			'ExpiresByType text/javascript "access plus 1 year"',
			'ExpiresByType application/javascript "access plus 1 year"',
			'ExpiresByType application/wasm "access plus 1 year"',
			'ExpiresByType font/woff2 "access plus 1 year"',
			'ExpiresByType font/woff "access plus 1 year"',
			'ExpiresByType application/font-woff "access plus 1 year"',
			'ExpiresByType image/avif "access plus 3 months"',
			'ExpiresByType image/webp "access plus 3 months"',
			'ExpiresByType image/jpeg "access plus 3 months"',
			'ExpiresByType image/png "access plus 3 months"',
			'ExpiresByType image/gif "access plus 3 months"',
			'ExpiresByType image/svg+xml "access plus 3 months"',
			'ExpiresByType image/x-icon "access plus 3 months"',
			'</IfModule>',
			'<IfModule mod_headers.c>',
			'<FilesMatch "\\.(?:css|js|mjs|wasm|woff2?|ttf|otf)$">',
			'Header set Cache-Control "public, max-age=31536000, immutable"',
			'</FilesMatch>',
			'<FilesMatch "\\.(?:avif|webp|jpe?g|png|gif|svg|ico)$">',
			'Header set Cache-Control "public, max-age=7776000"',
			'</FilesMatch>',
			'</IfModule>',
			'<IfModule mod_brotli.c>',
			'AddOutputFilterByType BROTLI_COMPRESS text/html text/plain text/css text/javascript application/javascript application/json application/xml image/svg+xml',
			'</IfModule>',
			'<IfModule !mod_brotli.c>',
			'<IfModule mod_deflate.c>',
			'AddOutputFilterByType DEFLATE text/html text/plain text/css text/javascript application/javascript application/json application/xml image/svg+xml',
			'</IfModule>',
			'</IfModule>',
		];
	}
}
