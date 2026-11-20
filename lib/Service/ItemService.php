<?php

declare(strict_types=1);

namespace OCA\Groceries\Service;

use OCA\Groceries\Db\HistoryEntry;
use OCA\Groceries\Db\HistoryMapper;
use OCA\Groceries\Db\Item;
use OCA\Groceries\Db\ItemMapper;
use OCA\Groceries\Db\ListMapper;
use OCA\Groceries\Db\ShoppingList;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\TTransactional;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Items on a list: add (with merging), edit, tick, delete, checkout, history, bulk import and delta sync.
 *
 * Every change bumps the list revision and stamps the changed rows with it, so clients can ask
 * "what changed after revision N" with a single indexed query.
 */
class ItemService {
	use TTransactional;

	public const MAX_BULK = 200;
	public const MAX_LINE = 500;
	public const MODE_MERGE = 'merge';
	public const MODE_SEPARATE = 'separate';

	public function __construct(
		private IDBConnection $db,
		private ListMapper $lists,
		private ItemMapper $items,
		private HistoryMapper $history,
		private ListAccessService $access,
		private ListMetaService $meta,
		private CatalogService $catalog,
		private EventService $events,
	) {
	}

	// ---- reading ------------------------------------------------------------------------------

	/**
	 * Delta sync. since = 0 returns a full snapshot.
	 *
	 * @return array{revision: int, metaRevision: int, full: bool, items: list<array>, meta?: array}
	 */
	public function changes(string $uid, int $listId, int $since): array {
		[$list, $perm] = $this->access->require($uid, $listId);
		$full = $since <= 0 || $since < $list->getMinRevision() || $since > $list->getRevision();
		$items = $this->items->findChanges($listId, $full ? 0 : $since);
		$out = [
			'revision' => $list->getRevision(),
			'metaRevision' => $list->getMetaRevision(),
			'full' => $full,
			'items' => array_map(fn (Item $i) => $i->jsonSerialize(), $items),
		];
		if ($full || $list->getMetaRevision() > $since) {
			$out['meta'] = $this->meta->getMeta($uid, $list, $perm);
		}
		return $out;
	}

	/** @return list<array> */
	public function getHistory(string $uid, int $listId): array {
		$this->access->require($uid, $listId);
		return array_map(fn (HistoryEntry $h) => $h->jsonSerialize(), $this->history->findRecent($listId));
	}

	public function forget(string $uid, int $listId, int $historyId): void {
		$this->access->require($uid, $listId, ListAccessService::EDIT);
		try {
			$this->history->delete($this->history->findInList($listId, $historyId));
		} catch (DoesNotExistException) {
			// already gone
		}
	}

	// ---- writing ------------------------------------------------------------------------------

	/**
	 * @param array{name?: string, quantity?: ?string, note?: ?string, category?: ?string, icon?: ?string, source?: string, sourceRef?: ?string, sourceLabel?: ?string} $data
	 * @param string|null $opId client-generated id that makes retries idempotent
	 * @return array{item: array, action: string, revision: int}
	 */
	public function create(string $uid, int $listId, array $data, ?string $opId = null): array {
		return $this->atomic(function () use ($uid, $listId, $data, $opId) {
			[$list] = $this->access->require($uid, $listId, ListAccessService::EDIT);
			if ($opId !== null && ($known = $this->findOp($opId)) !== null) {
				try {
					$item = $this->items->findInList($listId, $known);
					return ['item' => $item->jsonSerialize(), 'action' => 'duplicate', 'revision' => $list->getRevision()];
				} catch (DoesNotExistException) {
					// fall through and create again
				}
			}
			$name = trim((string)($data['name'] ?? ''));
			$quantity = $this->cleanText($data['quantity'] ?? null, 64);
			$note = $this->cleanText($data['note'] ?? null, 1000);
			if ($quantity === null && !isset($data['quantity'])) {
				// quick-add: "2 l Milch" typed into the name field
				$parsed = IngredientParser::parse($name);
				if ($parsed['quantity'] !== null) {
					$name = $parsed['name'];
					$quantity = $parsed['quantity'];
					$note ??= $parsed['note'];
				}
			}
			$this->validateItemName($name);
			$revision = $this->lists->bumpRevision($listId);
			$result = $this->addLine($list, $uid, [
				'name' => $name,
				'quantity' => $quantity,
				'note' => $note,
				'category' => $data['category'] ?? null,
				'icon' => $data['icon'] ?? null,
				'source' => $this->cleanText($data['source'] ?? null, 32) ?? 'manual',
				'sourceRef' => $this->cleanText($data['sourceRef'] ?? null, 255),
				'sourceLabel' => $this->cleanText($data['sourceLabel'] ?? null, 255),
			], self::MODE_MERGE, $revision);
			if ($opId !== null) {
				$this->recordOp($opId, $result['item']->getId());
			}
			return ['item' => $result['item']->jsonSerialize(), 'action' => $result['action'], 'revision' => $revision];
		}, $this->db);
	}

	/**
	 * @param array{name?: string, quantity?: ?string, note?: ?string, category?: ?string, icon?: ?string, checked?: bool} $data
	 * @return array{item: array, revision: int}
	 */
	public function update(string $uid, int $listId, int $itemId, array $data): array {
		return $this->atomic(function () use ($uid, $listId, $itemId, $data) {
			[$list] = $this->access->require($uid, $listId, ListAccessService::EDIT);
			$item = $this->findItem($listId, $itemId);
			if ($item->getDeleted()) {
				// someone else removed it meanwhile; nothing to do
				return ['item' => $item->jsonSerialize(), 'revision' => $list->getRevision()];
			}
			$explicitCategory = isset($data['category']) && $data['category'] !== '' ? (string)$data['category'] : null;
			if ($explicitCategory !== null && !$this->isValidCategory($list, $explicitCategory)) {
				throw new ValidationException('Unknown category');
			}
			if (array_key_exists('name', $data) && trim((string)$data['name']) !== $item->getName()) {
				$name = trim((string)$data['name']);
				$this->validateItemName($name);
				$r = $this->resolve($list, $name, $explicitCategory, null);
				$item->setName($name);
				$item->setNormName($r['norm']);
				$item->setCatalogKey($r['catalogKey']);
				$item->setCategoryKey($r['category']);
				$item->setIcon($r['icon']);
			}
			if (array_key_exists('quantity', $data)) {
				$item->setQuantity($this->cleanText($data['quantity'], 64));
			}
			if (array_key_exists('note', $data)) {
				$item->setNote($this->cleanText($data['note'], 1000));
			}
			if ($explicitCategory !== null && $explicitCategory !== $item->getCategoryKey()) {
				$item->setCategoryKey($explicitCategory);
				$this->meta->setOverride($listId, $item->getNormName(), $explicitCategory, null);
				$this->touchHistory($listId, $item, false);
			}
			if (array_key_exists('icon', $data)) {
				$icon = $this->cleanText($data['icon'], 16);
				$item->setIcon($icon);
				if ($icon !== null) {
					$this->meta->setOverride($listId, $item->getNormName(), null, $icon);
					$this->touchHistory($listId, $item, false);
				}
			}
			if (array_key_exists('checked', $data)) {
				$checked = (bool)$data['checked'];
				if ($checked && $item->getStatus() !== Item::STATUS_CHECKED) {
					$item->setStatus(Item::STATUS_CHECKED);
					$item->setCheckedBy($uid);
					$item->setCheckedAt(time());
					$this->events->record($item, $uid, 'check');
				} elseif (!$checked && $item->getStatus() !== Item::STATUS_OPEN) {
					$item->setStatus(Item::STATUS_OPEN);
					$item->setCheckedBy(null);
					$item->setCheckedAt(null);
					$this->events->undoLastCheck($item);
				}
			}
			$revision = $this->lists->bumpRevision($listId);
			$item->setRevision($revision);
			$item->setUpdatedAt(time());
			$this->items->update($item);
			return ['item' => $item->jsonSerialize(), 'revision' => $revision];
		}, $this->db);
	}

	public function delete(string $uid, int $listId, int $itemId): int {
		return $this->atomic(function () use ($uid, $listId, $itemId) {
			[$list] = $this->access->require($uid, $listId, ListAccessService::EDIT);
			try {
				$item = $this->items->findInList($listId, $itemId);
			} catch (DoesNotExistException) {
				return $list->getRevision();
			}
			if ($item->getDeleted()) {
				return $list->getRevision();
			}
			$revision = $this->lists->bumpRevision($listId);
			$this->tombstone($item, $revision);
			return $revision;
		}, $this->db);
	}

	/** Removes every ticked item ("finish shopping"). */
	public function checkout(string $uid, int $listId): array {
		return $this->atomic(function () use ($uid, $listId) {
			[$list] = $this->access->require($uid, $listId, ListAccessService::EDIT);
			return $this->clearChecked($list, null);
		}, $this->db);
	}

	/** Removes every live item that was added by an integration, e.g. all ingredients of one recipe. */
	public function removeSource(string $uid, int $listId, string $source, string $sourceRef): array {
		return $this->atomic(function () use ($uid, $listId, $source, $sourceRef) {
			[$list] = $this->access->require($uid, $listId, ListAccessService::EDIT);
			$items = $this->items->findLiveBySource($listId, $source, $sourceRef);
			if ($items === []) {
				return ['removed' => 0, 'revision' => $list->getRevision()];
			}
			$revision = $this->lists->bumpRevision($listId);
			foreach ($items as $item) {
				$this->tombstone($item, $revision);
			}
			return ['removed' => count($items), 'revision' => $revision];
		}, $this->db);
	}

	/** Used by the background job: clears items ticked more than autoClearHours ago. */
	public function autoClear(ShoppingList $list): int {
		if ($list->getAutoClearHours() <= 0) {
			return 0;
		}
		return $this->atomic(function () use ($list) {
			return $this->clearChecked($list, time() - $list->getAutoClearHours() * 3600)['removed'];
		}, $this->db);
	}

	/**
	 * Adds many free-text lines at once (recipe ingredients). With dryRun nothing is written and
	 * the returned plan shows what would happen, so integrations can offer a review dialog.
	 *
	 * @param list<array{line?: string, name?: string, quantity?: ?string, note?: ?string, category?: ?string, enabled?: bool}> $lines
	 * @param array{mode?: string, source?: string, sourceRef?: ?string, sourceLabel?: ?string, dryRun?: bool, skipStaples?: bool} $options
	 * @return array{revision: int, rows: list<array>, added: int, merged: int, reopened: int, skipped: int}
	 */
	public function bulk(string $uid, int $listId, array $lines, array $options = []): array {
		if (count($lines) > self::MAX_BULK) {
			throw new ValidationException('At most ' . self::MAX_BULK . ' items per request');
		}
		$mode = ($options['mode'] ?? self::MODE_MERGE) === self::MODE_SEPARATE ? self::MODE_SEPARATE : self::MODE_MERGE;
		$dryRun = (bool)($options['dryRun'] ?? false);
		$skipStaples = (bool)($options['skipStaples'] ?? true);
		$source = $this->cleanText($options['source'] ?? null, 32) ?? 'api';
		$sourceRef = $this->cleanText($options['sourceRef'] ?? null, 255);
		$sourceLabel = $this->cleanText($options['sourceLabel'] ?? null, 255);

		return $this->atomic(function () use ($uid, $listId, $lines, $mode, $dryRun, $skipStaples, $source, $sourceRef, $sourceLabel) {
			[$list] = $this->access->require($uid, $listId, $dryRun ? ListAccessService::VIEW : ListAccessService::EDIT);

			// 1. parse + resolve every line, combining duplicates inside the batch
			$rows = [];
			$byNorm = [];
			foreach ($lines as $i => $input) {
				$line = trim((string)($input['line'] ?? ''));
				if (mb_strlen($line) > self::MAX_LINE) {
					throw new ValidationException('Line ' . ($i + 1) . ' is too long');
				}
				$parsed = $line !== '' ? IngredientParser::parse($line) : ['quantity' => null, 'name' => '', 'note' => null];
				$name = trim((string)($input['name'] ?? '')) ?: $parsed['name'];
				$quantity = array_key_exists('quantity', $input) ? $this->cleanText($input['quantity'], 64) : $parsed['quantity'];
				$note = array_key_exists('note', $input) ? $this->cleanText($input['note'], 1000) : $parsed['note'];
				if ($name === '') {
					continue;
				}
				$category = isset($input['category']) && $this->isValidCategory($list, (string)$input['category']) ? (string)$input['category'] : null;
				$r = $this->resolve($list, $name, $category, null);
				$staple = $r['catalogKey'] !== null && (bool)($this->catalog->getItem($r['catalogKey'])['staple'] ?? false);
				$enabled = array_key_exists('enabled', $input) ? (bool)$input['enabled'] : !($staple && $skipStaples);
				$row = [
					'line' => $line,
					'name' => $name,
					'quantity' => $quantity,
					'note' => $note,
					'category' => $r['category'],
					'catalogKey' => $r['catalogKey'],
					'icon' => $r['icon'],
					'normName' => $r['norm'],
					'staple' => $staple,
					'enabled' => $enabled,
				];
				if ($mode === self::MODE_MERGE && $enabled && isset($byNorm[$r['norm']])) {
					$idx = $byNorm[$r['norm']];
					$rows[$idx]['quantity'] = QuantityMerger::merge($rows[$idx]['quantity'], $quantity);
					$rows[$idx]['note'] = $this->appendNote($rows[$idx]['note'], $note);
					$rows[$idx]['line'] .= "\n" . $line;
					continue;
				}
				if ($enabled) {
					$byNorm[$r['norm']] = count($rows);
				}
				$rows[] = $row;
			}

			// 2. figure out what happens with each row
			foreach ($rows as $idx => $row) {
				if (!$row['enabled']) {
					$rows[$idx]['action'] = 'skip';
					continue;
				}
				$rows[$idx]['action'] = 'add';
				if ($mode === self::MODE_MERGE) {
					foreach ($this->items->findLiveByNormName($listId, $row['normName']) as $existing) {
						$rows[$idx]['existingId'] = $existing->getId();
						if ($existing->getStatus() === Item::STATUS_OPEN) {
							$rows[$idx]['action'] = 'merge';
							$rows[$idx]['resultQuantity'] = QuantityMerger::merge($existing->getQuantity(), $row['quantity']);
						} else {
							$rows[$idx]['action'] = 'reopen';
						}
						break;
					}
				}
			}

			$summary = ['added' => 0, 'merged' => 0, 'reopened' => 0, 'skipped' => 0];
			foreach ($rows as $row) {
				$summary[match ($row['action']) { 'add' => 'added', 'merge' => 'merged', 'reopen' => 'reopened', default => 'skipped' }]++;
			}
			if ($dryRun) {
				return ['revision' => $list->getRevision(), 'rows' => $rows] + $summary;
			}

			// 3. apply with a single revision
			$revision = $list->getRevision();
			if (count($rows) > $summary['skipped']) {
				$revision = $this->lists->bumpRevision($listId);
			}
			foreach ($rows as $idx => $row) {
				if ($row['action'] === 'skip') {
					continue;
				}
				$result = $this->addLine($list, $uid, [
					'name' => $row['name'],
					'quantity' => $row['quantity'],
					'note' => $mode === self::MODE_SEPARATE && $sourceLabel !== null ? $this->appendNote($row['note'], $sourceLabel) : $row['note'],
					'category' => $row['category'],
					'icon' => null,
					'source' => $source,
					'sourceRef' => $sourceRef,
					'sourceLabel' => $sourceLabel,
				], $mode, $revision);
				$rows[$idx]['itemId'] = $result['item']->getId();
			}
			return ['revision' => $revision, 'rows' => $rows] + $summary;
		}, $this->db);
	}

	// ---- internals ----------------------------------------------------------------------------

	/**
	 * Resolves the identity key, catalog item, category and icon for a name.
	 * Category precedence: explicit > per-list override > history > catalog > "other".
	 *
	 * @return array{norm: string, catalogKey: ?string, category: string, icon: ?string}
	 */
	public function resolve(ShoppingList $list, string $name, ?string $explicitCategory, ?string $explicitIcon): array {
		$listId = $list->getId();
		$match = $this->catalog->match($name);
		$norm = ($match !== null && $match['exact']) ? 'c:' . $match['item']['key'] : NameNormalizer::normalize($name);
		$override = $this->meta->getOverride($listId, $norm);
		$hist = $this->history->findByNormName($listId, $norm);

		$category = $explicitCategory;
		if ($category === null && $override !== null && $override['category'] !== null && $this->isValidCategory($list, $override['category'])) {
			$category = $override['category'];
		}
		if ($category === null && $hist !== null && $hist->getCategoryKey() !== 'other' && $this->isValidCategory($list, $hist->getCategoryKey())) {
			$category = $hist->getCategoryKey();
		}
		$category ??= $match !== null ? $this->catalog->itemCategory($match['item'], $list->getTemplate()) : 'other';

		$icon = $explicitIcon ?? $override['icon'] ?? $match['item']['icon'] ?? null;
		return [
			'norm' => $norm,
			'catalogKey' => $match['item']['key'] ?? null,
			'category' => $category,
			'icon' => $icon,
		];
	}

	/**
	 * @param array{name: string, quantity: ?string, note: ?string, category: ?string, icon: ?string, source: string, sourceRef: ?string, sourceLabel: ?string} $line
	 * @return array{item: Item, action: string}
	 */
	private function addLine(ShoppingList $list, string $uid, array $line, string $mode, int $revision): array {
		$listId = $list->getId();
		$category = $line['category'] !== null && $line['category'] !== '' && $this->isValidCategory($list, $line['category']) ? $line['category'] : null;
		$r = $this->resolve($list, $line['name'], $category, $this->cleanText($line['icon'], 16));
		$now = time();

		if ($mode === self::MODE_MERGE) {
			foreach ($this->items->findLiveByNormName($listId, $r['norm']) as $existing) {
				if ($existing->getStatus() === Item::STATUS_OPEN) {
					$existing->setQuantity(QuantityMerger::merge($existing->getQuantity(), $line['quantity']));
					$existing->setNote($this->appendNote($existing->getNote(), $line['note']));
					if ($existing->getSourceLabel() !== null && $line['sourceLabel'] !== null && !str_contains($existing->getSourceLabel(), $line['sourceLabel'])) {
						$existing->setSourceLabel(mb_substr($existing->getSourceLabel() . ', ' . $line['sourceLabel'], 0, 255));
					} elseif ($existing->getSourceLabel() === null && $line['sourceLabel'] !== null) {
						$existing->setSourceLabel($line['sourceLabel']);
						$existing->setSource($line['source']);
						$existing->setSourceRef($line['sourceRef']);
					}
					$action = 'merge';
				} else {
					$existing->setStatus(Item::STATUS_OPEN);
					$existing->setCheckedBy(null);
					$existing->setCheckedAt(null);
					$existing->setName($line['name']);
					$existing->setQuantity($line['quantity']);
					$existing->setNote($line['note']);
					$existing->setCategoryKey($r['category']);
					$existing->setIcon($r['icon']);
					$existing->setSource($line['source']);
					$existing->setSourceRef($line['sourceRef']);
					$existing->setSourceLabel($line['sourceLabel']);
					$existing->setAddedBy($uid);
					$action = 'reopen';
				}
				$existing->setRevision($revision);
				$existing->setUpdatedAt($now);
				$this->items->update($existing);
				$this->touchHistory($listId, $existing, true);
				$this->events->record($existing, $uid, 'add');
				return ['item' => $existing, 'action' => $action];
			}
		}

		$item = new Item();
		$item->setListId($listId);
		$item->setName($line['name']);
		$item->setNormName($r['norm']);
		$item->setCatalogKey($r['catalogKey']);
		$item->setCategoryKey($r['category']);
		$item->setIcon($r['icon']);
		$item->setQuantity($line['quantity']);
		$item->setNote($line['note']);
		$item->setStatus(Item::STATUS_OPEN);
		$item->setAddedBy($uid);
		$item->setSource($line['source']);
		$item->setSourceRef($line['sourceRef']);
		$item->setSourceLabel($line['sourceLabel']);
		$item->setRevision($revision);
		$item->setDeleted(0);
		$item->setCreatedAt($now);
		$item->setUpdatedAt($now);
		$item = $this->items->insert($item);
		$this->touchHistory($listId, $item, true);
		$this->events->record($item, $uid, 'add');
		return ['item' => $item, 'action' => 'add'];
	}

	private function touchHistory(int $listId, Item $item, bool $used): void {
		$h = $this->history->findByNormName($listId, $item->getNormName());
		$isNew = $h === null;
		$h ??= new HistoryEntry();
		$h->setListId($listId);
		$h->setNormName($item->getNormName());
		$h->setDisplayName($item->getName());
		$h->setCatalogKey($item->getCatalogKey());
		$h->setCategoryKey($item->getCategoryKey());
		$h->setIcon($item->getIcon());
		if ($used) {
			$h->setLastQuantity($item->getQuantity());
			$h->setUseCount(($isNew ? 0 : $h->getUseCount()) + 1);
			$h->setLastUsedAt(time());
		}
		$isNew ? $this->history->insert($h) : $this->history->update($h);
	}

	/** @return array{removed: int, revision: int} */
	private function clearChecked(ShoppingList $list, ?int $checkedBefore): array {
		$items = $this->items->findChecked($list->getId(), $checkedBefore);
		if ($items === []) {
			return ['removed' => 0, 'revision' => $list->getRevision()];
		}
		$revision = $this->lists->bumpRevision($list->getId());
		foreach ($items as $item) {
			$this->tombstone($item, $revision);
		}
		return ['removed' => count($items), 'revision' => $revision];
	}

	private function tombstone(Item $item, int $revision): void {
		$item->setDeleted(1);
		$item->setRevision($revision);
		$item->setUpdatedAt(time());
		$this->items->update($item);
	}

	private function findItem(int $listId, int $itemId): Item {
		try {
			return $this->items->findInList($listId, $itemId);
		} catch (DoesNotExistException) {
			throw new NotFoundException('Item not found');
		}
	}

	/** A category of the list's type (grocery, pharmacy, hardware) or one of its custom categories. */
	private function isValidCategory(ShoppingList $list, string $key): bool {
		return $this->catalog->isCategoryFor($key, $list->getTemplate()) || $this->meta->customCategoryExists($list->getId(), $key);
	}

	/**
	 * After a list changed its type (e.g. grocery -> pharmacy), move its items into that type's categories.
	 * Must run inside a transaction.
	 */
	public function recategorize(ShoppingList $list, int $revision): void {
		foreach ($this->items->findChanges($list->getId(), 0) as $item) {
			if ($this->isValidCategory($list, $item->getCategoryKey())) {
				continue;
			}
			$catalogItem = $item->getCatalogKey() !== null ? $this->catalog->getItem($item->getCatalogKey()) : null;
			$item->setCategoryKey($catalogItem !== null ? $this->catalog->itemCategory($catalogItem, $list->getTemplate()) : 'other');
			$item->setRevision($revision);
			$item->setUpdatedAt(time());
			$this->items->update($item);
		}
	}

	private function validateItemName(string $name): void {
		if ($name === '' || mb_strlen($name) > 255) {
			throw new ValidationException('Item name must be between 1 and 255 characters');
		}
	}

	private function cleanText(mixed $value, int $max): ?string {
		if ($value === null) {
			return null;
		}
		$value = trim((string)$value);
		return $value === '' ? null : mb_substr($value, 0, $max);
	}

	private function appendNote(?string $a, ?string $b): ?string {
		if ($a === null || $a === '') {
			return $b;
		}
		if ($b === null || $b === '' || str_contains($a, $b)) {
			return $a;
		}
		return mb_substr($a . '; ' . $b, 0, 1000);
	}

	private function findOp(string $opId): ?int {
		$qb = $this->db->getQueryBuilder();
		$qb->select('item_id')->from('grocery_ops')
			->where($qb->expr()->eq('op_id', $qb->createNamedParameter(mb_substr($opId, 0, 64))));
		$result = $qb->executeQuery();
		$id = $result->fetchOne();
		$result->closeCursor();
		return $id === false ? null : (int)$id;
	}

	private function recordOp(string $opId, int $itemId): void {
		// an op id whose item is gone (list deleted, tombstone purged) may be reused
		$del = $this->db->getQueryBuilder();
		$del->delete('grocery_ops')
			->where($del->expr()->eq('op_id', $del->createNamedParameter(mb_substr($opId, 0, 64))));
		$del->executeStatement();

		$qb = $this->db->getQueryBuilder();
		$qb->insert('grocery_ops')->values([
			'op_id' => $qb->createNamedParameter(mb_substr($opId, 0, 64)),
			'item_id' => $qb->createNamedParameter($itemId, IQueryBuilder::PARAM_INT),
			'created_at' => $qb->createNamedParameter(time(), IQueryBuilder::PARAM_INT),
		]);
		$qb->executeStatement();
	}

	public function purgeOps(int $before): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('grocery_ops')
			->where($qb->expr()->lt('created_at', $qb->createNamedParameter($before, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}
}
