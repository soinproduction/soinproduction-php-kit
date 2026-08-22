<?php
declare(strict_types=1);

namespace SoinProduction\Kit;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Collects small, screen-specific admin payloads and emits them once.
 *
 * Large collections and mutable data should stay behind REST/AJAX endpoints.
 */
final class AdminBootstrap {
	private const ELEMENT_ID = 'sp-admin-bootstrap';

	private static array $features = [];
	private static array $legacyGlobals = [];
	private static bool $scheduled = false;
	private static bool $printed = false;

	public static function set(string $feature, array $data): void {
		if (!preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $feature) || !is_admin()) {
			return;
		}

		self::$features[$feature] = $data;
		self::schedule();
	}

	public static function exposeLegacyGlobal(string $global, string $feature, string $key = ''): void {
		if (!preg_match('/^[A-Za-z_$][A-Za-z0-9_$]*$/', $global)) {
			return;
		}

		if (!preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $feature)) {
			return;
		}
		if ($key !== '' && !preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $key)) {
			return;
		}

		self::$legacyGlobals[$global] = [
			'feature' => $feature,
			'key'     => $key,
		];
		self::schedule();
	}

	private static function schedule(): void {
		if (self::$scheduled || self::$printed) {
			return;
		}

		self::$scheduled = true;
		add_action('admin_print_footer_scripts', [self::class, 'print'], 19);
	}

	public static function print(): void {
		if (self::$printed || self::$features === []) {
			return;
		}

		self::$printed = true;
		$screen = function_exists('get_current_screen') ? get_current_screen() : null;
		$payload = [
			'schema'   => 1,
			'screen'   => [
				'id'        => $screen ? (string) $screen->id : '',
				'base'      => $screen ? (string) $screen->base : '',
				'postType'  => $screen ? (string) $screen->post_type : '',
				'taxonomy'  => $screen ? (string) $screen->taxonomy : '',
			],
			'rest'     => [
				'root'  => esc_url_raw(rest_url()),
				'nonce' => wp_create_nonce('wp_rest'),
			],
			'features' => self::$features,
		];

		$payload = apply_filters('sp_admin_bootstrap_payload', $payload, $screen);
		if (!is_array($payload)) {
			return;
		}

		$json = wp_json_encode(
			$payload,
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
		);
		$legacy = wp_json_encode(
			self::$legacyGlobals,
			JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
		);
		if (!is_string($json) || !is_string($legacy)) {
			return;
		}

		echo '<script type="application/json" id="' . esc_attr(self::ELEMENT_ID) . '">' . $json . '</script>';
		echo '<script>(function(){var n=document.getElementById(' . wp_json_encode(self::ELEMENT_ID) . '),d={};try{d=n?JSON.parse(n.textContent||"{}"):{};}catch(e){}window.SPAdminBootstrap=d;window.SPAdminData={get:function(k,f){return d.features&&Object.prototype.hasOwnProperty.call(d.features,k)?d.features[k]:f;}};var m=' . $legacy . ';Object.keys(m).forEach(function(g){var x=m[g],v=d.features?d.features[x.feature]:undefined;if(x.key&&v!==undefined){v=v[x.key];}if(v!==undefined){window[g]=v;}});}());</script>';
	}
}
