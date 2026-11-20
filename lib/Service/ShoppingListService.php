<?php

declare(strict_types=1);

namespace OCA\Groceries\Service;

use OCA\Groceries\Public\IShoppingListService;
use OCA\Groceries\Public\ItemLine;

/**
 * Implementation of the public integration API.
 */
class ShoppingListService implements IShoppingListService {
	public function __construct(
		private ListService $lists,
		private ItemService $items,
		private CatalogService $catalog,
	) {
	}

	public function getWritableLists(string $uid): array {
		$out = [];
		foreach ($this->lists->getLists($uid) as $l) {
			if ($l['permission'] === ListAccessService::VIEW) {
				continue;
			}
			$out[] = [
				'id' => (int)$l['id'],
				'name' => (string)$l['name'],
				'icon' => $l['icon'],
				'color' => $l['color'],
				'template' => (string)$l['template'],
				'openCount' => (int)$l['openCount'],
			];
		}
		return $out;
	}

	public function addItems(string $uid, int $listId, array $lines, array $options = []): array {
		$arrays = array_map(fn ($l) => $l instanceof ItemLine ? $l->toArray() : ItemLine::fromArray((array)$l)->toArray(), $lines);
		return $this->items->bulk($uid, $listId, $arrays, $options);
	}

	public function parseLines(array $lines, string $language = 'en'): array {
		$out = [];
		foreach (array_slice($lines, 0, ItemService::MAX_BULK) as $line) {
			$line = mb_substr(trim((string)$line), 0, ItemService::MAX_LINE);
			$p = IngredientParser::parse($line);
			$match = $this->catalog->match($p['name']);
			$out[] = [
				'line' => $line,
				'quantity' => $p['quantity'],
				'name' => $p['name'],
				'note' => $p['note'],
				'category' => $match['item']['category'] ?? 'other',
				'catalogKey' => $match['item']['key'] ?? null,
				'catalogName' => $match !== null ? ($match['item']['names'][$language] ?? $match['item']['names']['en']) : null,
				'icon' => $match['item']['icon'] ?? null,
				'staple' => (bool)($match['item']['staple'] ?? false),
			];
		}
		return $out;
	}

	public function removeBySource(string $uid, int $listId, string $source, string $sourceRef): int {
		return $this->items->removeSource($uid, $listId, $source, $sourceRef)['removed'];
	}
}
