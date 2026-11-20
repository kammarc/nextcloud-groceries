<?php

declare(strict_types=1);

namespace OCA\Groceries\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * A store (market) with its own aisle order of categories.
 *
 * @method int getListId()
 * @method void setListId(int $listId)
 * @method string getName()
 * @method void setName(string $name)
 * @method string|null getIcon()
 * @method void setIcon(?string $icon)
 * @method string|null getCategoryOrder()
 * @method void setCategoryOrder(?string $json)
 * @method string|null getImage()
 * @method void setImage(?string $dataUrl)
 */
class Store extends Entity implements JsonSerializable {
	protected $listId = 0;
	protected $name = '';
	protected $icon;
	protected $categoryOrder;
	protected $image;

	public function __construct() {
		$this->addType('listId', 'integer');
	}

	/** @return list<string> */
	public function getOrderArray(): array {
		$decoded = json_decode($this->categoryOrder ?? '[]', true);
		return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'name' => $this->name,
			'icon' => $this->icon,
			'image' => $this->image,
			'order' => $this->getOrderArray(),
		];
	}
}
