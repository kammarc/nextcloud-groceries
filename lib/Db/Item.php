<?php

declare(strict_types=1);

namespace OCA\Groceries\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * @method int getListId()
 * @method void setListId(int $listId)
 * @method string|null getCatalogKey()
 * @method void setCatalogKey(?string $key)
 * @method string getName()
 * @method void setName(string $name)
 * @method string getNormName()
 * @method void setNormName(string $name)
 * @method string getCategoryKey()
 * @method void setCategoryKey(string $key)
 * @method string|null getQuantity()
 * @method void setQuantity(?string $quantity)
 * @method string|null getNote()
 * @method void setNote(?string $note)
 * @method string|null getIcon()
 * @method void setIcon(?string $icon)
 * @method int getStatus()
 * @method void setStatus(int $status)
 * @method string getAddedBy()
 * @method void setAddedBy(string $uid)
 * @method string|null getCheckedBy()
 * @method void setCheckedBy(?string $uid)
 * @method int|null getCheckedAt()
 * @method void setCheckedAt(?int $ts)
 * @method string getSource()
 * @method void setSource(string $source)
 * @method string|null getSourceRef()
 * @method void setSourceRef(?string $ref)
 * @method string|null getSourceLabel()
 * @method void setSourceLabel(?string $label)
 * @method int getRevision()
 * @method void setRevision(int $revision)
 * @method int getDeleted()
 * @method void setDeleted(int $deleted)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $ts)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $ts)
 */
class Item extends Entity implements JsonSerializable {
	public const STATUS_OPEN = 0;
	public const STATUS_CHECKED = 1;

	protected $listId = 0;
	protected $catalogKey;
	protected $name = '';
	protected $normName = '';
	protected $categoryKey = 'other';
	protected $quantity;
	protected $note;
	protected $icon;
	protected $status = self::STATUS_OPEN;
	protected $addedBy = '';
	protected $checkedBy;
	protected $checkedAt;
	protected $source = 'manual';
	protected $sourceRef;
	protected $sourceLabel;
	protected $revision = 0;
	protected $deleted = 0;
	protected $createdAt = 0;
	protected $updatedAt = 0;

	public function __construct() {
		$this->addType('listId', 'integer');
		$this->addType('status', 'integer');
		$this->addType('checkedAt', 'integer');
		$this->addType('revision', 'integer');
		$this->addType('deleted', 'integer');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
	}

	public function isOpen(): bool {
		return $this->status === self::STATUS_OPEN && $this->deleted === 0;
	}

	public function jsonSerialize(): array {
		if ($this->deleted) {
			return ['id' => $this->getId(), 'deleted' => true, 'revision' => $this->revision];
		}
		return [
			'id' => $this->getId(),
			'listId' => $this->listId,
			'catalogKey' => $this->catalogKey,
			'name' => $this->name,
			'normName' => $this->normName,
			'category' => $this->categoryKey,
			'quantity' => $this->quantity,
			'note' => $this->note,
			'icon' => $this->icon,
			'checked' => $this->status === self::STATUS_CHECKED,
			'addedBy' => $this->addedBy,
			'checkedBy' => $this->checkedBy,
			'checkedAt' => $this->checkedAt,
			'source' => $this->source,
			'sourceRef' => $this->sourceRef,
			'sourceLabel' => $this->sourceLabel,
			'revision' => $this->revision,
			'createdAt' => $this->createdAt,
			'updatedAt' => $this->updatedAt,
		];
	}
}
