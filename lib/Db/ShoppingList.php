<?php

declare(strict_types=1);

namespace OCA\Groceries\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * @method string getName()
 * @method void setName(string $name)
 * @method string|null getIcon()
 * @method void setIcon(?string $icon)
 * @method string|null getImage()
 * @method void setImage(?string $dataUrl)
 * @method string|null getColor()
 * @method void setColor(?string $color)
 * @method string getOwnerUid()
 * @method void setOwnerUid(string $uid)
 * @method string getTemplate()
 * @method void setTemplate(string $template)
 * @method int getAutoClearHours()
 * @method void setAutoClearHours(int $hours)
 * @method int getRevision()
 * @method void setRevision(int $revision)
 * @method int getMetaRevision()
 * @method void setMetaRevision(int $revision)
 * @method int getMinRevision()
 * @method void setMinRevision(int $revision)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $ts)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $ts)
 */
class ShoppingList extends Entity implements JsonSerializable {
	protected $name = '';
	protected $icon;
	protected $image;
	protected $color;
	protected $ownerUid = '';
	protected $template = 'grocery';
	protected $autoClearHours = 12;
	protected $revision = 0;
	protected $metaRevision = 0;
	protected $minRevision = 0;
	protected $createdAt = 0;
	protected $updatedAt = 0;

	public function __construct() {
		$this->addType('autoClearHours', 'integer');
		$this->addType('revision', 'integer');
		$this->addType('metaRevision', 'integer');
		$this->addType('minRevision', 'integer');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'name' => $this->name,
			'icon' => $this->icon,
			'image' => $this->image,
			'color' => $this->color,
			'owner' => $this->ownerUid,
			'template' => $this->template,
			'autoClearHours' => $this->autoClearHours,
			'revision' => $this->revision,
			'metaRevision' => $this->metaRevision,
			'createdAt' => $this->createdAt,
			'updatedAt' => $this->updatedAt,
		];
	}
}
