<?php

declare(strict_types=1);

namespace OCA\Groceries\Service;

/**
 * Normalizes item names into comparison keys.
 * Keep in sync with src/lib/normalize.ts (both are tested against tests/fixtures/shared-cases.json).
 */
final class NameNormalizer {
	private const FOLD = [
		'ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'ß' => 'ss', 'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a',
		'å' => 'a', 'æ' => 'ae', 'ç' => 'c', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ì' => 'i',
		'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
		'ø' => 'o', 'œ' => 'oe', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ÿ' => 'y',
	];

	public static function normalize(string $name): string {
		$s = mb_strtolower(trim($name), 'UTF-8');
		$s = strtr($s, self::FOLD);
		$s = (string)preg_replace('/[^\p{L}\p{N}]+/u', ' ', $s);
		return trim((string)preg_replace('/\s+/', ' ', $s));
	}

	/**
	 * Singular/plural variants used for lenient lookups (not for identity).
	 *
	 * @return list<string>
	 */
	public static function variants(string $norm): array {
		$v = [$norm];
		foreach (['en', 'es', 'n', 's', 'e'] as $suffix) {
			if (mb_strlen($norm) > strlen($suffix) + 2 && str_ends_with($norm, $suffix)) {
				$v[] = mb_substr($norm, 0, -strlen($suffix));
			}
		}
		foreach (['s', 'n', 'en', 'e'] as $suffix) {
			$v[] = $norm . $suffix;
		}
		return array_values(array_unique($v));
	}
}
