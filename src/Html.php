<?php
declare(strict_types=1);

namespace SoinProduction\Kit;

final class Html
{
	/**
	 * Normalize a whitespace-separated HTML class list without changing valid
	 * utility-class syntax. Attribute escaping remains the renderer's job.
	 *
	 * @param mixed $classes A class string or an array of class strings.
	 */
	public static function classNames($classes): string
	{
		$values = is_array($classes) ? $classes : [$classes];
		$tokens = [];

		foreach ($values as $value) {
			if (! is_scalar($value) && ! $value instanceof \Stringable) {
				continue;
			}

			$parts = preg_split('/\s+/u', trim((string) $value), -1, PREG_SPLIT_NO_EMPTY);
			if (! is_array($parts)) {
				continue;
			}

			foreach ($parts as $part) {
				$part = (string) preg_replace('/[\x00-\x1F\x7F]+/u', '', $part);
				if ($part !== '') {
					$tokens[] = $part;
				}
			}
		}

		return implode(' ', array_values(array_unique($tokens)));
	}
}
