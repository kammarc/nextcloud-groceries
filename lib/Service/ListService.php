<?php

declare(strict_types=1);

namespace OCA\Groceries\Service;

use OCA\Groceries\Db\HistoryMapper;
use OCA\Groceries\Db\ItemMapper;
use OCA\Groceries\Db\ListMapper;
use OCA\Groceries\Db\Member;
use OCA\Groceries\Db\MemberMapper;
use OCA\Groceries\Db\ShoppingList;
use OCA\Groceries\Db\Store;
use OCA\Groceries\Db\StoreMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\TTransactional;
use OCP\Collaboration\Collaborators\ISearch;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\Share\IManager as IShareManager;
use OCP\Share\IShare;

/**
 * Lists, sharing, stores and custom categories.
 */
class ListService {
	use TTransactional;

	public const TEMPLATES = ['grocery', 'pharmacy', 'hardware', 'custom'];
	private const DEFAULT_ICONS = ['grocery' => '🛒', 'pharmacy' => '💊', 'hardware' => '🔨', 'custom' => '📝'];

	public function __construct(
		private IDBConnection $db,
		private ListMapper $lists,
		private MemberMapper $members,
		private ItemMapper $items,
		private HistoryMapper $history,
		private StoreMapper $stores,
		private ListAccessService $access,
		private ListMetaService $meta,
		private CatalogService $catalog,
		private IUserManager $userManager,
		private IGroupManager $groupManager,
		private ItemService $itemService,
		private EventService $events,
		private IShareManager $shareManager,
		private ISearch $collaboratorSearch,
		private IConfig $config,
	) {
	}

	/** @return list<array> lists visible to the user, with permission and open item count */
	public function getLists(string $uid): array {
		$lists = $this->lists->findForUser($uid, $this->access->getGroupIds($uid));
		$counts = $this->items->countOpen(array_map(fn (ShoppingList $l) => $l->getId(), $lists));
		$out = [];
		foreach ($lists as $list) {
			$perm = $this->access->getPermission($uid, $list);
			if ($perm === null) {
				continue;
			}
			$out[] = $list->jsonSerialize() + [
				'permission' => $perm,
				'openCount' => $counts[$list->getId()] ?? 0,
				'shared' => $list->getOwnerUid() !== $uid || $this->members->findByList($list->getId()) !== [],
			];
		}
		// the user's own order (drag and drop on the lists screen); lists not in it come last
		$position = array_flip($this->getListOrder($uid));
		$fallback = count($position);
		$index = array_flip(array_keys($out));
		uksort($out, fn ($a, $b) => [($position[$out[$a]['id']] ?? $fallback), $index[$a]] <=> [($position[$out[$b]['id']] ?? $fallback), $index[$b]]);
		return array_values($out);
	}

	/** @return list<int> list ids in the user's order */
	public function getListOrder(string $uid): array {
		$ids = json_decode($this->config->getUserValue($uid, 'groceries', 'list_order', '[]'), true);
		return is_array($ids) ? array_values(array_filter($ids, 'is_int')) : [];
	}

	/**
	 * Saves the user's order of the lists screen (per user, the same on all devices).
	 *
	 * @param array<mixed> $ids
	 * @return list<int>
	 */
	public function setListOrder(string $uid, array $ids): array {
		if (count($ids) > 1000) {
			throw new ValidationException('Too many lists');
		}
		$clean = [];
		foreach ($ids as $id) {
			if (!is_int($id) && !(is_string($id) && ctype_digit($id))) {
				throw new ValidationException('List ids must be numbers');
			}
			$clean[(int)$id] = true;
		}
		$order = array_keys($clean);
		$this->config->setUserValue($uid, 'groceries', 'list_order', (string)json_encode($order));
		return $order;
	}

	public function create(string $uid, string $name, string $template = 'grocery', ?string $icon = null, ?string $color = null, ?string $image = null): ShoppingList {
		$name = $this->validateName($name);
		if (!in_array($template, self::TEMPLATES, true)) {
			throw new ValidationException('Unknown template');
		}
		$list = new ShoppingList();
		$list->setName($name);
		$list->setOwnerUid($uid);
		$list->setTemplate($template);
		$list->setIcon($this->validateIcon($icon) ?? self::DEFAULT_ICONS[$template]);
		$list->setColor($this->validateColor($color));
		$list->setImage($this->validateImage($image));
		$list->setAutoClearHours(12);
		$list->setRevision(1);
		$list->setMetaRevision(1);
		$list->setCreatedAt(time());
		$list->setUpdatedAt(time());
		return $this->lists->insert($list);
	}

	/**
	 * @param array{name?: string, icon?: ?string, color?: ?string, template?: string, autoClearHours?: int} $data
	 */
	public function update(string $uid, int $listId, array $data): ShoppingList {
		return $this->atomic(function () use ($uid, $listId, $data) {
			[$list] = $this->access->require($uid, $listId, ListAccessService::EDIT);
			if (array_key_exists('name', $data)) {
				$list->setName($this->validateName((string)$data['name']));
			}
			if (array_key_exists('icon', $data)) {
				$list->setIcon($this->validateIcon($data['icon']));
			}
			if (array_key_exists('image', $data)) {
				$list->setImage($this->validateImage($data['image']));
			}
			if (array_key_exists('color', $data)) {
				$list->setColor($this->validateColor($data['color']));
			}
			$typeChanged = false;
			if (array_key_exists('template', $data)) {
				if (!in_array($data['template'], self::TEMPLATES, true)) {
					throw new ValidationException('Unknown template');
				}
				$typeChanged = CatalogService::listType($data['template']) !== CatalogService::listType($list->getTemplate());
				$list->setTemplate($data['template']);
			}
			if (array_key_exists('autoClearHours', $data)) {
				$list->setAutoClearHours(max(0, min(24 * 30, (int)$data['autoClearHours'])));
			}
			$this->lists->update($list);
			$revision = $this->lists->bumpRevision($listId, true);
			if ($typeChanged) {
				// items move into the new list type's categories; store orders keep only valid ones
				$this->itemService->recategorize($list, $revision);
				foreach ($this->stores->findByList($listId) as $store) {
					$store->setCategoryOrder(json_encode($this->validateOrder($list, $store->getOrderArray())));
					$this->stores->update($store);
				}
			}
			return $this->lists->findById($listId);
		}, $this->db);
	}

	public function delete(string $uid, int $listId): void {
		$this->atomic(function () use ($uid, $listId) {
			[$list] = $this->access->require($uid, $listId, ListAccessService::OWNER);
			$this->purgeList($list);
		}, $this->db);
	}

	public function purgeList(ShoppingList $list): void {
		$id = $list->getId();
		$this->items->deleteByList($id);
		$this->members->deleteByList($id);
		$this->history->deleteByList($id);
		$this->stores->deleteByList($id);
		$this->meta->deleteByList($id);
		$this->events->deleteByList($id);
		$this->lists->delete($list);
	}

	/** @return list<array> */
	public function getMembers(string $uid, int $listId): array {
		[$list] = $this->access->require($uid, $listId);
		$out = [[
			'type' => Member::TYPE_USER,
			'id' => $list->getOwnerUid(),
			'displayName' => $this->userManager->getDisplayName($list->getOwnerUid()) ?? $list->getOwnerUid(),
			'permission' => ListAccessService::OWNER,
		]];
		foreach ($this->members->findByList($listId) as $m) {
			$out[] = $m->jsonSerialize() + ['displayName' => $this->principalName($m->getPrincipalType(), $m->getPrincipalId())];
		}
		return $out;
	}

	/**
	 * Replaces the member set of a list. Only the owner may share.
	 *
	 * @param list<array{type: string, id: string, permission?: string}> $members
	 */
	public function setMembers(string $uid, int $listId, array $members): array {
		$this->atomic(function () use ($uid, $listId, $members) {
			[$list] = $this->access->require($uid, $listId, ListAccessService::OWNER);
			$wanted = [];
			foreach ($members as $m) {
				$type = (string)($m['type'] ?? '');
				$id = (string)($m['id'] ?? '');
				$perm = (string)($m['permission'] ?? Member::PERM_EDIT);
				if (!in_array($type, [Member::TYPE_USER, Member::TYPE_GROUP], true) || $id === '') {
					throw new ValidationException('Invalid member');
				}
				if (!in_array($perm, [Member::PERM_EDIT, Member::PERM_VIEW], true)) {
					throw new ValidationException('Invalid permission');
				}
				if ($type === Member::TYPE_USER && $id === $list->getOwnerUid()) {
					continue;
				}
				if ($type === Member::TYPE_USER && !$this->userManager->userExists($id)) {
					throw new ValidationException('Unknown user ' . $id);
				}
				if ($type === Member::TYPE_GROUP && !$this->groupManager->groupExists($id)) {
					throw new ValidationException('Unknown group ' . $id);
				}
				$wanted[$type . ':' . $id] = [$type, $id, $perm];
			}
			$current = $this->members->findByList($listId);
			$already = [];
			foreach ($current as $m) {
				$already[$m->getPrincipalType() . ':' . $m->getPrincipalId()] = true;
			}
			// new shares follow the server's sharing policy (existing ones stay, so a policy change
			// never silently locks people out; the admin can still remove them)
			foreach ($wanted as $k => [$type, $id]) {
				if (!isset($already[$k])) {
					$this->assertMayShareWith($uid, $type, $id);
				}
			}
			$existing = [];
			foreach ($current as $m) {
				$k = $m->getPrincipalType() . ':' . $m->getPrincipalId();
				if (!isset($wanted[$k])) {
					$this->members->delete($m);
				} else {
					$existing[$k] = true;
					if ($m->getPermission() !== $wanted[$k][2]) {
						$m->setPermission($wanted[$k][2]);
						$this->members->update($m);
					}
				}
			}
			foreach ($wanted as $k => [$type, $id, $perm]) {
				if (isset($existing[$k])) {
					continue;
				}
				$m = new Member();
				$m->setListId($listId);
				$m->setPrincipalType($type);
				$m->setPrincipalId($id);
				$m->setPermission($perm);
				$this->members->insert($m);
			}
			$this->lists->bumpRevision($listId, true);
		}, $this->db);
		return $this->getMembers($uid, $listId);
	}

	public function leave(string $uid, int $listId): void {
		[$list] = $this->access->require($uid, $listId);
		if ($list->getOwnerUid() === $uid) {
			throw new ValidationException('The owner cannot leave a list; delete it instead');
		}
		foreach ($this->members->findByList($listId) as $m) {
			if ($m->getPrincipalType() === Member::TYPE_USER && $m->getPrincipalId() === $uid) {
				$this->members->delete($m);
			}
		}
	}

	/**
	 * Users and groups to share with.
	 *
	 * @return list<array{type: string, id: string, displayName: string}>
	 */
	public function searchPrincipals(string $uid, string $term, int $limit = 20): array {
		if ($this->shareManager->sharingDisabledForUser($uid)) {
			return [];
		}
		$types = [IShare::TYPE_USER];
		if ($this->shareManager->allowGroupSharing()) {
			$types[] = IShare::TYPE_GROUP;
		}
		// Nextcloud's own sharee search: honours user enumeration limits ("only users in the same
		// group", "only on full match", phone book), excluded groups and group-members-only sharing
		[$result] = $this->collaboratorSearch->search($term, $types, false, $limit, 0);
		$out = [];
		$seen = [];
		foreach (['exact', null] as $bucket) {
			$source = $bucket === null ? $result : ($result['exact'] ?? []);
			foreach (['users' => Member::TYPE_USER, 'groups' => Member::TYPE_GROUP] as $key => $type) {
				foreach ($source[$key] ?? [] as $row) {
					$id = (string)($row['value']['shareWith'] ?? '');
					if ($id === '' || ($type === Member::TYPE_USER && $id === $uid) || isset($seen[$type . ':' . $id])) {
						continue;
					}
					$seen[$type . ':' . $id] = true;
					$out[] = ['type' => $type, 'id' => $id, 'displayName' => (string)($row['label'] ?? $id)];
				}
			}
		}
		return $out;
	}

	/**
	 * The same rules Nextcloud applies to file sharing: sharing switched off for the user (excluded
	 * groups), group sharing switched off, and "only share with members of your own groups".
	 */
	private function assertMayShareWith(string $uid, string $type, string $id): void {
		if ($this->shareManager->sharingDisabledForUser($uid)) {
			throw new ForbiddenException('Sharing is disabled for your account');
		}
		if ($type === Member::TYPE_GROUP && !$this->shareManager->allowGroupSharing()) {
			throw new ForbiddenException('Sharing with groups is disabled on this server');
		}
		if (!$this->shareManager->shareWithGroupMembersOnly()) {
			return;
		}
		$excluded = $this->shareManager->shareWithGroupMembersOnlyExcludeGroupsList();
		$mine = array_diff($this->access->getGroupIds($uid), is_array($excluded) ? $excluded : []);
		$allowed = $type === Member::TYPE_GROUP
			? in_array($id, $mine, true)
			: array_intersect($mine, $this->access->getGroupIds($id)) !== [];
		if (!$allowed) {
			throw new ForbiddenException('You can only share with members of your own groups');
		}
	}

	// ---- stores -------------------------------------------------------------------------------

	public function createStore(string $uid, int $listId, string $name, ?string $icon, ?array $order, ?string $image = null): Store {
		return $this->atomic(function () use ($uid, $listId, $name, $icon, $order, $image) {
			[$list] = $this->access->require($uid, $listId, ListAccessService::EDIT);
			$store = new Store();
			$store->setListId($listId);
			$store->setName($this->validateName($name));
			$store->setIcon($this->validateIcon($icon));
			$store->setImage($this->validateImage($image));
			$store->setCategoryOrder(json_encode($this->validateOrder($list, $order ?? $this->catalog->getCategoryKeysFor($list->getTemplate()))));
			$store = $this->stores->insert($store);
			$this->lists->bumpRevision($listId, true);
			return $store;
		}, $this->db);
	}

	public function updateStore(string $uid, int $listId, int $storeId, array $data): Store {
		return $this->atomic(function () use ($uid, $listId, $storeId, $data) {
			[$list] = $this->access->require($uid, $listId, ListAccessService::EDIT);
			$store = $this->findStore($listId, $storeId);
			if (array_key_exists('name', $data)) {
				$store->setName($this->validateName((string)$data['name']));
			}
			if (array_key_exists('icon', $data)) {
				$store->setIcon($this->validateIcon($data['icon']));
			}
			if (array_key_exists('image', $data)) {
				$store->setImage($this->validateImage($data['image']));
			}
			if (array_key_exists('order', $data) && is_array($data['order'])) {
				$store->setCategoryOrder(json_encode($this->validateOrder($list, $data['order'])));
			}
			$this->stores->update($store);
			$this->lists->bumpRevision($listId, true);
			return $store;
		}, $this->db);
	}

	public function deleteStore(string $uid, int $listId, int $storeId): void {
		$this->atomic(function () use ($uid, $listId, $storeId) {
			$this->access->require($uid, $listId, ListAccessService::EDIT);
			$this->stores->delete($this->findStore($listId, $storeId));
			$this->lists->bumpRevision($listId, true);
		}, $this->db);
	}

	public function createCategory(string $uid, int $listId, string $name, ?string $icon): array {
		return $this->atomic(function () use ($uid, $listId, $name, $icon) {
			$this->access->require($uid, $listId, ListAccessService::EDIT);
			$name = $this->validateName($name);
			$icon = $this->validateIcon($icon);
			$key = $this->meta->addCustomCategory($listId, $name, $icon);
			$this->lists->bumpRevision($listId, true);
			return ['key' => $key, 'name' => $name, 'icon' => $icon];
		}, $this->db);
	}

	public function deleteCategory(string $uid, int $listId, string $key): void {
		$this->atomic(function () use ($uid, $listId, $key) {
			$this->access->require($uid, $listId, ListAccessService::EDIT);
			$this->meta->deleteCustomCategory($listId, $key);
			$this->lists->bumpRevision($listId, true);
		}, $this->db);
	}

	private function findStore(int $listId, int $storeId): Store {
		try {
			return $this->stores->findInList($listId, $storeId);
		} catch (DoesNotExistException) {
			throw new NotFoundException('Store not found');
		}
	}

	/** @return list<string> */
	private function validateOrder(ShoppingList $list, array $order): array {
		$out = [];
		foreach ($order as $key) {
			if (!is_string($key)) {
				continue;
			}
			if ($this->catalog->isCategoryFor($key, $list->getTemplate()) || $this->meta->customCategoryExists($list->getId(), $key)) {
				$out[$key] = true;
			}
		}
		return array_keys($out);
	}

	private function principalName(string $type, string $id): string {
		if ($type === Member::TYPE_GROUP) {
			return $this->groupManager->get($id)?->getDisplayName() ?? $id;
		}
		return $this->userManager->getDisplayName($id) ?? $id;
	}

	private function validateName(string $name): string {
		$name = trim($name);
		if ($name === '' || mb_strlen($name) > 255) {
			throw new ValidationException('Name must be between 1 and 255 characters');
		}
		return $name;
	}

	private function validateIcon(mixed $icon): ?string {
		if ($icon === null || $icon === '') {
			return null;
		}
		$icon = trim((string)$icon);
		if (mb_strlen($icon) > 16) {
			throw new ValidationException('Icon must be an emoji');
		}
		return $icon;
	}

	/** Store and list pictures are small data: URLs (the app scales them to 160 px before upload). */
	private function validateImage(mixed $image): ?string {
		if ($image === null || $image === '') {
			return null;
		}
		$image = (string)$image;
		if (strlen($image) > 300_000 || !preg_match('#^data:image/(png|jpeg|webp|gif);base64,[A-Za-z0-9+/=]+$#', $image)) {
			throw new ValidationException('Image must be a PNG, JPEG, WebP or GIF data URL below 300 KB');
		}
		return $image;
	}

	private function validateColor(mixed $color): ?string {
		if ($color === null || $color === '') {
			return null;
		}
		if (!preg_match('/^#[0-9a-fA-F]{6}$/', (string)$color)) {
			throw new ValidationException('Color must be #rrggbb');
		}
		return (string)$color;
	}
}
