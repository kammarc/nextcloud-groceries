<?php

declare(strict_types=1);

namespace OCA\Groceries\Tests\Unit;

use OCA\Groceries\Service\CatalogService;
use OCA\Groceries\Service\IngredientParser;
use OCA\Groceries\Service\NameNormalizer;
use OCA\Groceries\Service\QuantityMerger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The same fixtures are run against the TypeScript ports (tests/js/shared.test.ts),
 * so server-side and offline client-side behaviour stay identical.
 */
class SharedCasesTest extends TestCase {
	private static function cases(string $key): array {
		$all = json_decode((string)file_get_contents(__DIR__ . '/../fixtures/shared-cases.json'), true);
		return $all[$key];
	}

	public static function normalizeCases(): array {
		return array_map(fn ($c) => [$c[0], $c[1]], self::cases('normalize'));
	}

	public static function parseCases(): array {
		$out = [];
		foreach (self::cases('parse') as $c) {
			$out[$c['line']] = [$c['line'], ['quantity' => $c['quantity'], 'name' => $c['name'], 'note' => $c['note']]];
		}
		return $out;
	}

	public static function mergeCases(): array {
		return self::cases('merge');
	}

	public static function matchCases(): array {
		$out = [];
		foreach (self::cases('match') as $c) {
			$out[$c[0]] = $c;
		}
		return $out;
	}

	#[DataProvider('normalizeCases')]
	public function testNormalize(string $input, string $expected): void {
		$this->assertSame($expected, NameNormalizer::normalize($input));
	}

	#[DataProvider('parseCases')]
	public function testParse(string $line, array $expected): void {
		$this->assertSame($expected, IngredientParser::parse($line));
	}

	#[DataProvider('mergeCases')]
	public function testMerge(?string $a, ?string $b, string $expected): void {
		$this->assertSame($expected, QuantityMerger::merge($a, $b));
	}

	#[DataProvider('matchCases')]
	public function testMatch(string $name, ?string $key, bool $exact): void {
		$m = (new CatalogService())->match($name);
		$this->assertSame($key, $m['item']['key'] ?? null);
		if ($key !== null) {
			$this->assertSame($exact, $m['exact']);
		}
	}

	public function testListTypeCategories(): void {
		$catalog = new CatalogService();
		$ibu = $catalog->getItem('ibuprofen');
		$this->assertSame('care', $catalog->itemCategory($ibu, 'grocery'));
		$this->assertSame('pharmacy_pain', $catalog->itemCategory($ibu, 'pharmacy'));
		$this->assertSame('other', $catalog->itemCategory($catalog->getItem('milk'), 'hardware'));
		$this->assertSame('other', $catalog->getCategoryKeysFor('pharmacy')[0]);
		$this->assertNotContains('dairy', $catalog->getCategoryKeysFor('pharmacy'));
		$this->assertContains('produce', $catalog->getCategoryKeysFor('custom'));
	}

	public function testCatalogIsConsistent(): void {
		$catalog = new CatalogService();
		$data = $catalog->getData();
		$categoryKeys = array_column($data['categories'], 'key');
		$this->assertContains('other', $categoryKeys);
		$this->assertGreaterThan(400, count($data['items']));
		foreach ($data['items'] as $item) {
			$this->assertContains($item['category'], $categoryKeys, $item['key']);
			$this->assertTrue($catalog->isCategoryFor($item['category'], 'grocery'), $item['key']);
			foreach ($item['types'] ?? [] as $type => $cat) {
				$this->assertTrue($catalog->isCategoryFor($cat, $type), $item['key'] . ' ' . $type);
			}
			$this->assertNotSame('', $item['icon'], $item['key']);
			// every item must be found by its own names
			foreach ($item['names'] as $name) {
				$this->assertNotNull($catalog->match($name), $name);
			}
		}
	}
}
