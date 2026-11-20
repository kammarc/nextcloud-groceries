<?php

declare(strict_types=1);

namespace OCA\Groceries\Service;

/**
 * Splits free-text lines ("200 g Mehl (Type 405)", "Milch 2l", "3x Eier", "2 cups of flour")
 * into quantity, name and note.
 * Keep in sync with src/lib/parser.ts (both are tested against tests/fixtures/shared-cases.json).
 */
final class IngredientParser {
	/** Canonical unit => accepted spellings (compared lower-case, without trailing dot). */
	public const UNITS = [
		'mg' => ['mg', 'milligramm', 'milligram', 'milligrams'],
		'g' => ['g', 'gr', 'gramm', 'gram', 'grams', 'grammes'],
		'kg' => ['kg', 'kilo', 'kilos', 'kilogramm', 'kilogram', 'kilograms'],
		'ml' => ['ml', 'milliliter', 'millilitre', 'milliliters', 'millilitres'],
		'cl' => ['cl', 'zentiliter', 'centiliter'],
		'dl' => ['dl', 'deziliter', 'deciliter'],
		'l' => ['l', 'liter', 'litre', 'liters', 'litres', 'ltr'],
		'oz' => ['oz', 'ounce', 'ounces'],
		'lb' => ['lb', 'lbs', 'pound', 'pounds'],
		'EL' => ['el', 'essloffel', 'esslöffel', 'tbsp', 'tablespoon', 'tablespoons', 'tbs'],
		'TL' => ['tl', 'teeloffel', 'teelöffel', 'tsp', 'teaspoon', 'teaspoons'],
		'cup' => ['cup', 'cups', 'tasse', 'tassen'],
		'Prise' => ['prise', 'prisen', 'pinch', 'pinches'],
		'Msp' => ['msp', 'messerspitze', 'messerspitzen'],
		'Bund' => ['bund', 'bunch', 'bunches', 'bd'],
		'Stk' => ['stk', 'stück', 'stuck', 'st', 'pcs', 'pc', 'piece', 'pieces', 'stuk'],
		'Dose' => ['dose', 'dosen', 'can', 'cans', 'tin', 'tins', 'büchse', 'büchsen'],
		'Pck' => ['pck', 'pkg', 'packung', 'packungen', 'pack', 'packs', 'packet', 'packets', 'päckchen', 'pk', 'pckg', 'paket', 'pakete'],
		'Becher' => ['becher'],
		'Glas' => ['glas', 'gläser', 'jar', 'jars'],
		'Flasche' => ['flasche', 'flaschen', 'bottle', 'bottles', 'fl'],
		'Zehe' => ['zehe', 'zehen', 'clove', 'cloves'],
		'Scheibe' => ['scheibe', 'scheiben', 'slice', 'slices'],
		'Zweig' => ['zweig', 'zweige', 'sprig', 'sprigs', 'stiel', 'stiele'],
		'Blatt' => ['blatt', 'blätter', 'leaf', 'leaves'],
		'Handvoll' => ['handvoll', 'handful', 'handfuls'],
		'Beutel' => ['beutel', 'bag', 'bags', 'tüte', 'tüten'],
		'Stange' => ['stange', 'stangen', 'stick', 'sticks', 'stalk', 'stalks'],
		'Würfel' => ['würfel', 'cube', 'cubes'],
		'Kopf' => ['kopf', 'köpfe', 'head', 'heads'],
		'Knolle' => ['knolle', 'knollen', 'bulb', 'bulbs'],
		'Schale' => ['schale', 'schalen', 'tray', 'punnet', 'punnets'],
		'Netz' => ['netz', 'netze', 'net'],
		'Rolle' => ['rolle', 'rollen', 'roll', 'rolls'],
		'Tafel' => ['tafel', 'tafeln', 'bar', 'bars'],
		'Kiste' => ['kiste', 'kisten', 'crate', 'crates', 'harass'],
		'Tube' => ['tube', 'tuben', 'tubes'],
	];

	private const FRACTIONS = ['½' => '1/2', '¼' => '1/4', '¾' => '3/4', '⅓' => '1/3', '⅔' => '2/3', '⅛' => '1/8'];
	private const VAGUE = ['etwas', 'einige', 'ein paar', 'wenig', 'some', 'a few', 'a little', 'a bit of', 'nach belieben', 'n. b.', 'nb', 'to taste', 'ca.', 'ca', 'etwa', 'about', 'approx.', 'approx'];
	private const UNIT_ONLY = ['Prise', 'Msp', 'Bund', 'Handvoll', 'Pck', 'Dose', 'Glas', 'Becher', 'Flasche', 'Zehe', 'Zweig', 'Kopf', 'Knolle', 'Netz', 'Schale', 'Tafel', 'Kiste'];

	/** Number at the very start: 1/2, 1, 1.5, 1,5, 1 1/2, 2-3 (fraction alternative first) */
	private const NUM = '(?:\d+\/\d+|\d+(?:[.,]\d+)?(?:\s+\d+\/\d+)?)(?:\s*[-–]\s*(?:\d+(?:[.,]\d+)?|\d+\/\d+))?';

	/** @var array<string,string>|null spelling => canonical */
	private static ?array $unitLookup = null;

	/**
	 * @return array{quantity: ?string, name: string, note: ?string}
	 */
	public static function parse(string $line): array {
		$s = strtr(trim($line), self::FRACTIONS);
		$s = (string)preg_replace('/(\d)(\d\/\d)/u', '$1 $2', $s); // "1½" became "11/2" -> "1 1/2"
		$s = (string)preg_replace('/^[\-\*•·\s]+/u', '', $s); // list bullets
		$s = trim((string)preg_replace('/\s+/u', ' ', $s));

		$notes = [];
		// parenthesised notes
		if (preg_match_all('/\(([^)]*)\)/u', $s, $m)) {
			foreach ($m[1] as $n) {
				if (trim($n) !== '') {
					$notes[] = trim($n);
				}
			}
			$s = trim((string)preg_replace('/\s*\([^)]*\)\s*/u', ' ', $s));
		}
		// a comma that is not a decimal separator ("1,5 l") starts a note
		if (preg_match('/(?<!\d),|,(?!\d)/u', $s, $cm, PREG_OFFSET_CAPTURE)) {
			$pos = $cm[0][1];
			$head = trim(substr($s, 0, $pos));
			$rest = trim(substr($s, $pos + 1));
			if ($head !== '' && $rest !== '') {
				$notes[] = $rest;
				$s = $head;
			}
		}

		$quantity = null;
		$vaguePrefix = null;
		foreach (self::VAGUE as $v) {
			if (preg_match('/^' . preg_quote($v, '/') . '(?=\s|$)/iu', $s)) {
				$vaguePrefix = $v;
				$s = trim(mb_substr($s, mb_strlen($v)));
				break;
			}
		}

		// "3x Eier", "x3 Eier"
		if (preg_match('/^(\d+)\s*[x×]\s+(.+)$/iu', $s, $m) || preg_match('/^[x×]\s*(\d+)\s+(.+)$/iu', $s, $m)) {
			$quantity = $m[1];
			$s = $m[2];
		} elseif (preg_match('/^(' . self::NUM . ')(\s*)(.*)$/u', $s, $m)
			// a number glued to letters is only a quantity if the letters are a unit: "200g" yes, "7up" no
			&& ($m[2] !== '' || $m[3] === '' || self::takeUnit($m[3])[0] !== null)) {
			$num = self::cleanNumber($m[1]);
			$rest = $m[3];
			[$unit, $rest] = self::takeUnit($rest);
			$rest = (string)preg_replace('/^(of|von|vom)\s+/iu', '', $rest);
			if (trim($rest) === '') {
				// a bare number is not an item ("2") — treat everything as the name
				$quantity = null;
				$s = $m[0];
			} else {
				$quantity = $unit !== null ? $num . ' ' . $unit : $num;
				$s = $rest;
			}
		} else {
			// unit without number: "Prise Salz", "pinch of salt", "Bund Petersilie"
			[$unit, $rest] = self::takeUnit($s);
			$rest = (string)preg_replace('/^(of|von)\s+/iu', '', $rest);
			if ($unit !== null && trim($rest) !== '' && in_array($unit, self::UNIT_ONLY, true)) {
				$quantity = '1 ' . $unit;
				$s = $rest;
			}
		}

		// trailing quantity: "Milch 2l", "Milch 2 l", "Eier x6", "Eier 6x", "Eier 6 Stk"
		if ($quantity === null) {
			if (preg_match('/^(.+?)\s+[x×]\s*(\d+)$/iu', $s, $m) || preg_match('/^(.+?)\s+(\d+)\s*[x×]$/iu', $s, $m)) {
				$quantity = $m[2];
				$s = $m[1];
			} elseif (preg_match('/^(.+?)\s+(' . self::NUM . ')\s*([\p{L}.]*)$/u', $s, $m)) {
				$unitPart = $m[3];
				$canonical = $unitPart === '' ? null : self::canonicalUnit($unitPart);
				if ($unitPart === '' || $canonical !== null) {
					$quantity = self::cleanNumber($m[2]) . ($canonical !== null ? ' ' . $canonical : '');
					$s = $m[1];
				}
			}
		}

		if ($vaguePrefix !== null && $quantity === null) {
			$quantity = $vaguePrefix;
		}

		$name = trim((string)preg_replace('/^[\s\-–:;.]+|[\s\-–:;.]+$/u', '', $s));
		if ($name === '') {
			$name = trim($line);
		}
		$note = $notes === [] ? null : implode(', ', $notes);
		return ['quantity' => $quantity, 'name' => $name, 'note' => $note];
	}

	public static function canonicalUnit(string $word): ?string {
		if (self::$unitLookup === null) {
			self::$unitLookup = [];
			foreach (self::UNITS as $canonical => $spellings) {
				foreach ($spellings as $sp) {
					self::$unitLookup[$sp] = $canonical;
				}
			}
		}
		$w = rtrim(mb_strtolower($word, 'UTF-8'), '.');
		return self::$unitLookup[$w] ?? null;
	}

	/**
	 * @return array{0: ?string, 1: string} [canonical unit or null, remaining text]
	 */
	private static function takeUnit(string $rest): array {
		if (preg_match('/^([\p{L}]+\.?)(?:\s+|$)(.*)$/u', $rest, $m)) {
			$canonical = self::canonicalUnit($m[1]);
			if ($canonical !== null) {
				return [$canonical, $m[2]];
			}
		}
		return [null, $rest];
	}

	private static function cleanNumber(string $n): string {
		$n = (string)preg_replace('/\s*[-–]\s*/u', '-', trim($n));
		return (string)preg_replace('/\s+/u', ' ', $n);
	}
}
