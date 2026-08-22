<?php

declare(strict_types=1);

namespace SoinProduction\Kit;

/**
 * Copies the data associated with a WordPress post to an already-created post.
 *
 * The target keeps the source language but starts a new translation group. This
 * is important because WPML and Polylang cannot place two posts of the same
 * language in one translation group.
 */
final class PostDuplicator
{
	/**
	 * Copy post meta, regular taxonomy terms and multilingual language details.
	 */
	public static function copyAssociatedData(int $sourceId, int $targetId): bool
	{
		$source = get_post($sourceId);
		$target = get_post($targetId);

		if (! $source instanceof \WP_Post || ! $target instanceof \WP_Post) {
			return false;
		}

		if ($source->post_type !== $target->post_type) {
			return false;
		}

		self::copyMeta($sourceId, $targetId);
		self::copyTerms($source, $targetId);
		$languageProvider = self::copyLanguage($source, $targetId);

		do_action('sp_post_duplicator_after_copy', $sourceId, $targetId, $languageProvider);

		return true;
	}

	/**
	 * Copy all ordinary post meta while excluding editor and translation state.
	 */
	public static function copyMeta(int $sourceId, int $targetId): void
	{
		$skipKeys = [
			'_edit_lock',
			'_edit_last',
			'_wp_old_slug',
			'_icl_lang_duplicate_of',
		];

		$skipKeys = apply_filters('sp_post_duplicator_excluded_meta_keys', $skipKeys, $sourceId, $targetId);
		$skipKeys = is_array($skipKeys) ? array_map('strval', $skipKeys) : [];
		$meta = get_post_meta($sourceId);

		if (! is_array($meta)) {
			return;
		}

		foreach ($meta as $metaKey => $values) {
			$metaKey = (string) $metaKey;
			if (in_array($metaKey, $skipKeys, true)) {
				continue;
			}

			delete_post_meta($targetId, $metaKey);
			foreach ((array) $values as $value) {
				add_post_meta($targetId, $metaKey, maybe_unserialize($value));
			}
		}
	}

	/**
	 * Copy ordinary terms without cloning plugin-owned language relationships.
	 */
	public static function copyTerms(\WP_Post $source, int $targetId): void
	{
		$taxonomies = get_object_taxonomies($source->post_type, 'names');
		if (! is_array($taxonomies) || $taxonomies === []) {
			return;
		}

		$excluded = [
			'language',
			'post_translations',
			'term_language',
			'term_translations',
		];
		$excluded = apply_filters(
			'sp_post_duplicator_excluded_taxonomies',
			$excluded,
			$source->ID,
			$targetId
		);
		$excluded = is_array($excluded) ? array_map('strval', $excluded) : [];

		foreach ($taxonomies as $taxonomy) {
			$taxonomy = (string) $taxonomy;
			if ($taxonomy === '' || in_array($taxonomy, $excluded, true)) {
				continue;
			}

			$termIds = wp_get_object_terms($source->ID, $taxonomy, ['fields' => 'ids']);
			if (is_wp_error($termIds) || ! is_array($termIds)) {
				continue;
			}

			$termIds = array_values(array_filter(array_map('intval', $termIds), static fn (int $id): bool => $id > 0));
			wp_set_object_terms($targetId, $termIds, $taxonomy, false);
		}
	}

	/**
	 * Preserve the language while intentionally starting a new translation group.
	 *
	 * @return string `polylang`, `wpml`, or an empty string when no provider acted.
	 */
	public static function copyLanguage(\WP_Post $source, int $targetId): string
	{
		$providers = apply_filters(
			'sp_post_duplicator_language_providers',
			['polylang', 'wpml'],
			$source->ID,
			$targetId
		);
		$providers = is_array($providers) ? array_map('strval', $providers) : [];

		foreach ($providers as $provider) {
			if ($provider === 'polylang' && self::copyPolylangLanguage($source, $targetId)) {
				return 'polylang';
			}

			if ($provider === 'wpml' && self::copyWpmlLanguage($source, $targetId)) {
				return 'wpml';
			}
		}

		return '';
	}

	private static function copyPolylangLanguage(\WP_Post $source, int $targetId): bool
	{
		if (! function_exists('pll_get_post_language') || ! function_exists('pll_set_post_language')) {
			return false;
		}

		if (function_exists('pll_is_translated_post_type') && ! pll_is_translated_post_type($source->post_type)) {
			return false;
		}

		$language = pll_get_post_language($source->ID, 'slug');
		if (! is_string($language) || $language === '') {
			return false;
		}

		pll_set_post_language($targetId, $language);

		return true;
	}

	private static function copyWpmlLanguage(\WP_Post $source, int $targetId): bool
	{
		$isTranslated = apply_filters('wpml_is_translated_post_type', null, $source->post_type);
		if ($isTranslated === false) {
			return false;
		}

		$details = apply_filters(
			'wpml_element_language_details',
			null,
			[
				'element_id'   => $source->ID,
				'element_type' => $source->post_type,
			]
		);

		$languageCode = is_object($details) && isset($details->language_code)
			? (string) $details->language_code
			: '';

		if ($languageCode === '') {
			return false;
		}

		$elementType = apply_filters('wpml_element_type', $source->post_type);
		$elementType = is_string($elementType) && $elementType !== ''
			? $elementType
			: 'post_' . $source->post_type;

		do_action(
			'wpml_set_element_language_details',
			[
				'element_id'            => $targetId,
				'element_type'          => $elementType,
				'trid'                  => false,
				'language_code'         => $languageCode,
				'source_language_code'  => null,
				'check_duplicates'      => true,
			]
		);

		return true;
	}
}
