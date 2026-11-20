<?php

declare(strict_types=1);

namespace OCA\Groceries\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * @method int getListId()
 * @method void setListId(int $listId)
 * @method string getNormName()
 * @method void setNormName(string $name)
 * @method string getDisplayName()
 * @method void setDisplayName(string $name)
 * @method string|null getCatalogKey()
 * @method void setCatalogKey(?string $key)
 * @method string getCategoryKey()
 * @method void setCategoryKey(string $key)
 * @method string|null getIcon()
 * @method void setIcon(?string $icon)
 * @method string|null getLastQuantity()
 * @method void setLastQuantity(?string $quantity)
 * @method int getUseCount()
 * @method void setUseCount(int $count)
 * @method int getLastUsedAt()
 * @method void setLastUsedAt(int $ts)
 */
class HistoryEntry extends Entity implements JsonSerializable {
	protected $listId = 0;
	protected $normName = '';
	protected $displayName = '';
	protected $catalogKey;
	protected $categoryKey = 'other';
	protected $icon;
	protected $lastQuantity;
	protected $useCount = 0;
	protected $lastUsedAt = 0;

	public function __construct() {
		$this->addType('listId', 'integer');
		$this->addType('useCount', 'integer');
		$this->addType('lastUsedAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'name' => $this->displayName,
			'normName' => $this->normName,
			'catalogKey' => $this->catalogKey,
			'category' => $this->categoryKey,
			'icon' => $this->icon,
			'lastQuantity' => $this->lastQuantity,
			'useCount' => $this->useCount,
			'lastUsedAt' => $this->lastUsedAt,
		];
	}
}
