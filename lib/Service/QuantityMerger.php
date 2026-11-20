<?php

declare(strict_types=1);

namespace OCA\Groceries\Service;

/**
 * Combines two quantity strings, e.g. "500 g" + "1 kg" = "1.5 kg", "2" + "3" = "5", "1 Dose" + "2 EL" = "1 Dose + 2 EL".
 * Keep in sync with src/lib/quantity.ts (both are tested against tests/fixtures/shared-cases.json).
 */
final class QuantityMerger {
	/** unit => [base unit, factor] */
	private const CONVERT = [
		'mg' => ['g', 0.001], 'g' => ['g', 1.0], 'kg' => ['g', 1000.0],
		'ml' => ['ml', 1.0], 'cl' => ['ml', 10.0], 'dl' => ['ml', 100.0], 'l' => ['ml', 1000.0],
	];

	public static function merge(?string $a, ?string $b): ?string {
		$a = $a !== null && trim($a) !== '' ? trim($a) : null;
		$b = $b !== null && trim($b) !== '' ? trim($b) : null;
		if ($a === null || $b === null) {
			return $a ?? $b;
		}
		$pa = self::parse($a);
		$pb = self::parse($b);
		if ($pa !== null && $pb !== null) {
			[$na, $ua] = $pa;
			[$nb, $ub] = $pb;
			if ($ua === $ub) {
				return self::format($na + $nb) . ($ua !== '' ? ' ' . $ua : '');
			}
			if (isset(self::CONVERT[$ua], self::CONVERT[$ub]) && self::CONVERT[$ua][0] === self::CONVERT[$ub][0]) {
				[$base] = self::CONVERT[$ua];
				$sum = $na * self::CONVERT[$ua][1] + $nb * self::CONVERT[$ub][1];
				return self::formatBase($sum, $base);
			}
		}
		return $a . ' + ' . $b;
	}

	/** @return array{0: float, 1: string}|null */
	public static function parse(string $q): ?array {
		if (!preg_match('/^(\d+\/\d+|\d+(?:[.,]\d+)?(?:\s+\d+\/\d+)?)\s*(.*)$/u', trim($q), $m)) {
			return null;
		}
		$num = self::toFloat($m[1]);
		$unitText = trim($m[2]);
		$unit = $unitText === '' ? '' : (IngredientParser::canonicalUnit($unitText) ?? $unitText);
		return [$num, $unit];
	}

	private static function toFloat(string $n): float {
		$n = str_replace(',', '.', trim($n));
		if (preg_match('/^(\d+)\s+(\d+)\/(\d+)$/', $n, $m)) {
			return (float)$m[1] + ((int)$m[3] === 0 ? 0 : (float)$m[2] / (float)$m[3]);
		}
		if (preg_match('/^(\d+)\/(\d+)$/', $n, $m)) {
			return (int)$m[2] === 0 ? 0.0 : (float)$m[1] / (float)$m[2];
		}
		return (float)$n;
	}

	private static function formatBase(float $value, string $base): string {
		if ($base === 'g') {
			return $value >= 1000 ? self::format($value / 1000) . ' kg' : self::format($value) . ' g';
		}
		return $value >= 1000 ? self::format($value / 1000) . ' l' : self::format($value) . ' ml';
	}

	public static function format(float $n): string {
		$s = number_format(round($n, 2), 2, '.', '');
		return rtrim(rtrim($s, '0'), '.');
	}
}
