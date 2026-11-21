<?php

declare(strict_types=1);

namespace OCA\Groceries\Service;

use OCA\Groceries\Db\HistoryEntry;
use OCA\Groceries\Db\HistoryMapper;
use OCA\Groceries\Db\Item;
use OCA\Groceries\Db\ItemMapper;
use OCA\Groceries\Db\ListMapper;
use OCA\Groceries\Db\ShoppingList;
use OCA\Groceries\Db\StoreMapper;
use OCP\AppFramework\Db\TTransactional;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Backup of a user's whole library: every list they can see with its items, stores, custom
 * categories, item overrides, history, purchase log (statistics) and the user's own preferences.
 *
 * Import always creates new lists owned by the importing user (sharing is not restored).
 * In "replace" mode the user's own lists are deleted first; lists other people share with
 * the user are never touched, and are not duplicated when the user still has access to them.
 */
class LibraryService {
	use TTransactional;

	public const FORMAT = 'groceries-library';
	public const VERSION = 1;
	public const MODE_ADD = 'add';
	public const MODE_REPLACE = 'replace';

	private const MAX_LISTS = 200;
	private const MAX_ITEMS = 5000;
	private const MAX_HISTORY = 10000;
	private const MAX_EVENTS_TOTAL = 200000;
	private const MAX_STORES = 100;
	private const MAX_CATEGORIES = 200;
	private const MAX_OVERRIDES = 10000;

	public function __construct(
		private IDBConnection $db,
		private ListMapper $listMapper,
		private ItemMapper $items,
		private HistoryMapper $history,
		private StoreMapper $stores,
		private ListService $lists,
		private ListAccessService $access,
		private ListMetaService $meta,
		private CatalogService $catalog,
		private EventService $events,
	) {
	}

	// ---- export -------------------------------------------------------------------------------

	public function export(string $uid): array {
		$out = [];
		foreach ($this->lists->getLists($uid) as $summary) {
			$listId = (int)$summary['id'];
			[$list] = $this->access->require($uid, $listId);
			$out[] = [
				'id' => $listId,
				'name' => $list->getName(),
				'icon' => $list->getIcon(),
				'image' => $list->getImage(),
				'color' => $list->getColor(),
				'template' => $list->getTemplate(),
				'autoClearHours' => $list->getAutoClearHours(),
				'owner' => $list->getOwnerUid(),
				'permission' => $summary['permission'],
				'createdAt' => $list->getCreatedAt(),
				'categories' => $this->meta->getCustomCategories($listId),
				'overrides' => (object)$this->meta->getOverrides($listId),
				'stores' => array_map(fn ($s) => $s->jsonSerialize(), $this->stores->findByList($listId)),
				'prefs' => $this->meta->getPrefs($uid, $listId),
				'items' => array_map(fn (Item $i) => $i->jsonSerialize(), $this->items->findChanges($listId, 0)),
				'history' => array_map(fn (HistoryEntry $h) => $h->jsonSerialize(), $this->history->findRecent($listId, self::MAX_HISTORY)),
				'events' => $this->events->getEvents($uid, $listId),
			];
		}
		return [
			'format' => self::FORMAT,
			'version' => self::VERSION,
			'exportedAt' => gmdate('c'),
			'user' => $uid,
			'lists' => $out,
		];
	}

	// ---- import -------------------------------------------------------------------------------

	/**
	 * @return array{lists: int, items: int, history: int, events: int, skipped: int, deleted: int}
	 */
	public function import(string $uid, array $library, string $mode): array {
		if (($library['format'] ?? null) !== self::FORMAT || !is_array($library['lists'] ?? null)) {
			throw new ValidationException('Not a Groceries library export');
		}
		if ((int)($library['version'] ?? 0) > self::VERSION) {
			throw new ValidationException('This export was made by a newer version of the app');
		}
		if (!in_array($mode, [self::MODE_ADD, self::MODE_REPLACE], true)) {
			throw new ValidationException('mode must be add or replace');
		}
		$lists = array_values(array_filter($library['lists'], 'is_array'));
		if (count($lists) > self::MAX_LISTS) {
			throw new ValidationException('At most ' . self::MAX_LISTS . ' lists per import');
		}
		$eventCount = 0;
		foreach ($lists as $l) {
			$eventCount += is_array($l['events'] ?? null) ? count($l['events']) : 0;
		}
		if ($eventCount > self::MAX_EVENTS_TOTAL) {
			throw new ValidationException('Too many statistics entries');
		}

		return $this->atomic(function () use ($uid, $lists, $mode) {
			$summary = ['lists' => 0, 'items' => 0, 'history' => 0, 'events' => 0, 'skipped' => 0, 'deleted' => 0];
			if ($mode === self::MODE_REPLACE) {
				foreach ($this->listMapper->findForUser($uid, $this->access->getGroupIds($uid)) as $own) {
					if ($own->getOwnerUid() === $uid) {
						$this->lists->purgeList($own);
						$summary['deleted']++;
					}
				}
			}
			$newIds = [];
			foreach ($lists as $data) {
				if ($this->stillShared($uid, $data)) {
					$summary['skipped']++;
					$newIds[] = (int)$data['id'];
					continue;
				}
				$counts = $this->importList($uid, $data);
				$newIds[] = $counts['id'];
				$summary['lists']++;
				$summary['items'] += $counts['items'];
				$summary['history'] += $counts['history'];
				$summary['events'] += $counts['events'];
			}
			if ($mode === self::MODE_REPLACE) {
				// the exported order of the lists screen, followed by whatever else the user can see
				$this->lists->setListOrder($uid, array_merge($newIds, $this->lists->getListOrder($uid)));
			}
			return $summary;
		}, $this->db);
	}

	/** A list someone else shares with the user that is still there: nothing to restore. */
	private function stillShared(string $uid, array $data): bool {
		$owner = $data['owner'] ?? null;
		if (!is_string($owner) || $owner === $uid || !is_numeric($data['id'] ?? null)) {
			return false;
		}
		try {
			[$list] = $this->access->require($uid, (int)$data['id']);
			return $list->getOwnerUid() === $owner;
		} catch (ServiceException) {
			return false;
		}
	}

	/** @return array{id: int, items: int, history: int, events: int} */
	private function importList(string $uid, array $data): array {
		$template = in_array($data['template'] ?? null, ListService::TEMPLATES, true) ? $data['template'] : 'grocery';
		$list = $this->lists->create(
			$uid,
			$this->str($data['name'] ?? null, 255) ?? 'List',
			$template,
			$this->str($data['icon'] ?? null, 16),
			$this->color($data['color'] ?? null),
			$this->image($data['image'] ?? null),
		);
		$listId = $list->getId();
		if (is_numeric($data['autoClearHours'] ?? null)) {
			$this->lists->update($uid, $listId, ['autoClearHours' => (int)$data['autoClearHours']]);
		}

		// custom categories keep their keys: items, overrides and store orders refer to them
		foreach (array_slice($this->arr($data['categories'] ?? null), 0, self::MAX_CATEGORIES) as $c) {
			$key = $c['key'] ?? null;
			$name = trim((string)($c['name'] ?? ''));
			if (!is_string($key) || !preg_match('/^custom_[a-z0-9]{1,40}$/', $key) || $name === '' || $this->meta->customCategoryExists($listId, $key)) {
				continue;
			}
			$this->meta->addCustomCategory($listId, mb_substr($name, 0, 255), $this->str($c['icon'] ?? null, 16), $key);
		}

		$overrides = is_array($data['overrides'] ?? null) ? $data['overrides'] : [];
		foreach (array_slice($overrides, 0, self::MAX_OVERRIDES, true) as $norm => $o) {
			$norm = (string)$norm; // numeric-looking names come back as int keys from JSON
			if ($norm === '' || !is_array($o)) {
				continue;
			}
			$category = $this->validCategory($list, $o['category'] ?? null);
			$icon = $this->str($o['icon'] ?? null, 16);
			if ($category !== null || $icon !== null) {
				$this->meta->setOverride($listId, mb_substr($norm, 0, 255), $category, $icon);
			}
		}

		$storeIds = [];
		foreach (array_slice($this->arr($data['stores'] ?? null), 0, self::MAX_STORES) as $s) {
			$name = trim((string)($s['name'] ?? ''));
			if ($name === '') {
				continue;
			}
			$store = $this->lists->createStore($uid, $listId, mb_substr($name, 0, 255), $this->str($s['icon'] ?? null, 16),
				is_array($s['order'] ?? null) ? $s['order'] : null, $this->image($s['image'] ?? null));
			if (is_numeric($s['id'] ?? null)) {
				$storeIds[(int)$s['id']] = $store->getId();
			}
		}

		$prefs = is_array($data['prefs'] ?? null) ? $data['prefs'] : [];
		$this->meta->setPrefs($uid, $listId, [
			'activeStoreId' => is_numeric($prefs['activeStoreId'] ?? null) ? ($storeIds[(int)$prefs['activeStoreId']] ?? null) : null,
			'viewMode' => ($prefs['viewMode'] ?? null) === 'grid' ? 'grid' : 'list',
			'grouped' => (bool)($prefs['grouped'] ?? true),
			'showFrequent' => (bool)($prefs['showFrequent'] ?? true),
			'showAddedBy' => (bool)($prefs['showAddedBy'] ?? false),
		]);

		// the new list is not shared with anybody, so entries of other people are credited to the
		// importer (the same rule as the statistics import: nobody can fake someone else's purchases)
		$revision = $this->listMapper->bumpRevision($listId);
		$itemCount = 0;
		foreach (array_slice($this->arr($data['items'] ?? null), 0, self::MAX_ITEMS) as $i) {
			if (!empty($i['deleted'])) {
				continue;
			}
			$name = trim((string)($i['name'] ?? ''));
			if ($name === '' || mb_strlen($name) > 255) {
				continue;
			}
			$normName = $this->str($i['normName'] ?? null, 255);
			$catalogKey = $this->str($i['catalogKey'] ?? null, 64);
			$category = $this->validCategory($list, $i['category'] ?? null);
			if ($normName === null || $category === null) {
				$r = $this->resolve($list, $name, $category);
				$normName ??= $r['norm'];
				$catalogKey ??= $r['catalogKey'];
				$category ??= $r['category'];
			}
			$checked = (bool)($i['checked'] ?? false);
			$item = new Item();
			$item->setListId($listId);
			$item->setName($name);
			$item->setNormName($normName);
			$item->setCatalogKey($catalogKey);
			$item->setCategoryKey($category);
			$item->setIcon($this->str($i['icon'] ?? null, 16));
			$item->setQuantity($this->str($i['quantity'] ?? null, 64));
			$item->setNote($this->str($i['note'] ?? null, 1000));
			$item->setStatus($checked ? Item::STATUS_CHECKED : Item::STATUS_OPEN);
			$item->setAddedBy($uid);
			$item->setCheckedBy($checked ? $uid : null);
			$item->setCheckedAt($checked ? $this->time($i['checkedAt'] ?? null) : null);
			$item->setSource($this->str($i['source'] ?? null, 32) ?? 'manual');
			$item->setSourceRef($this->str($i['sourceRef'] ?? null, 255));
			$item->setSourceLabel($this->str($i['sourceLabel'] ?? null, 255));
			$item->setRevision($revision);
			$item->setDeleted(0);
			$item->setCreatedAt($this->time($i['createdAt'] ?? null));
			$item->setUpdatedAt($this->time($i['updatedAt'] ?? null));
			$this->items->insert($item);
			$itemCount++;
		}

		$historyCount = 0;
		$seen = [];
		foreach (array_slice($this->arr($data['history'] ?? null), 0, self::MAX_HISTORY) as $h) {
			$name = trim((string)($h['name'] ?? ''));
			$norm = $this->str($h['normName'] ?? null, 255);
			if ($name === '' || $norm === null || isset($seen[$norm])) {
				continue;
			}
			$seen[$norm] = true;
			$entry = new HistoryEntry();
			$entry->setListId($listId);
			$entry->setNormName($norm);
			$entry->setDisplayName(mb_substr($name, 0, 255));
			$entry->setCatalogKey($this->str($h['catalogKey'] ?? null, 64));
			$entry->setCategoryKey($this->validCategory($list, $h['category'] ?? null) ?? 'other');
			$entry->setIcon($this->str($h['icon'] ?? null, 16));
			$entry->setLastQuantity($this->str($h['lastQuantity'] ?? null, 64));
			$entry->setUseCount(max(0, (int)($h['useCount'] ?? 0)));
			$entry->setLastUsedAt($this->time($h['lastUsedAt'] ?? null));
			$this->history->insert($entry);
			$historyCount++;
		}

		$eventCount = 0;
		foreach ($this->arr($data['events'] ?? null) as $e) {
			if (!in_array($e['type'] ?? null, EventService::TYPES, true) || !is_string($e['normName'] ?? null)
				|| !is_string($e['name'] ?? null) || !is_numeric($e['at'] ?? null)) {
				continue;
			}
			// keep the event id where it is free, so a later statistics import can skip duplicates
			$eventUid = is_string($e['id'] ?? null) && $e['id'] !== '' && strlen($e['id']) <= 64 && !$this->eventExists($e['id'])
				? $e['id'] : bin2hex(random_bytes(16));
			$qb = $this->db->getQueryBuilder();
			$qb->insert('grocery_events')->values([
				'list_id' => $qb->createNamedParameter($listId, IQueryBuilder::PARAM_INT),
				'event_uid' => $qb->createNamedParameter($eventUid),
				'uid' => $qb->createNamedParameter($uid),
				'type' => $qb->createNamedParameter($e['type']),
				'norm_name' => $qb->createNamedParameter(mb_substr($e['normName'], 0, 255)),
				'display_name' => $qb->createNamedParameter(mb_substr($e['name'], 0, 255)),
				'catalog_key' => $qb->createNamedParameter($this->str($e['catalogKey'] ?? null, 64)),
				'category_key' => $qb->createNamedParameter($this->str($e['category'] ?? null, 64) ?? 'other'),
				'icon' => $qb->createNamedParameter($this->str($e['icon'] ?? null, 64)),
				'quantity' => $qb->createNamedParameter($this->str($e['quantity'] ?? null, 64)),
				'created_at' => $qb->createNamedParameter((int)$e['at'], IQueryBuilder::PARAM_INT),
			]);
			$qb->executeStatement();
			$eventCount++;
		}

		return ['id' => $listId, 'items' => $itemCount, 'history' => $historyCount, 'events' => $eventCount];
	}

	/** @return array{norm: string, catalogKey: ?string, category: string} */
	private function resolve(ShoppingList $list, string $name, ?string $category): array {
		$match = $this->catalog->match($name);
		return [
			'norm' => ($match !== null && $match['exact']) ? 'c:' . $match['item']['key'] : NameNormalizer::normalize($name),
			'catalogKey' => $match['item']['key'] ?? null,
			'category' => $category ?? ($match !== null ? $this->catalog->itemCategory($match['item'], $list->getTemplate()) : 'other'),
		];
	}

	private function validCategory(ShoppingList $list, mixed $key): ?string {
		if (!is_string($key) || $key === '') {
			return null;
		}
		return $this->catalog->isCategoryFor($key, $list->getTemplate()) || $this->meta->customCategoryExists($list->getId(), $key) ? $key : null;
	}

	private function eventExists(string $eventUid): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')->from('grocery_events')->where($qb->expr()->eq('event_uid', $qb->createNamedParameter($eventUid)))->setMaxResults(1);
		$result = $qb->executeQuery();
		$found = $result->fetchOne() !== false;
		$result->closeCursor();
		return $found;
	}

	/** @return list<array> */
	private function arr(mixed $value): array {
		return is_array($value) ? array_values(array_filter($value, 'is_array')) : [];
	}

	private function str(mixed $value, int $max): ?string {
		if (!is_string($value)) {
			return null;
		}
		$value = trim($value);
		return $value === '' ? null : mb_substr($value, 0, $max);
	}

	/** Invalid colours and pictures are dropped instead of failing the whole import. */
	private function color(mixed $value): ?string {
		return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? $value : null;
	}

	private function image(mixed $value): ?string {
		return is_string($value) && strlen($value) <= 300_000
			&& preg_match('#^data:image/(png|jpeg|webp|gif);base64,[A-Za-z0-9+/=]+$#', $value) ? $value : null;
	}

	private function time(mixed $value): int {
		return is_numeric($value) && (int)$value > 0 ? (int)$value : time();
	}
}
