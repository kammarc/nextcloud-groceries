<?php

declare(strict_types=1);

namespace OCA\Groceries\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * @method int getListId()
 * @method void setListId(int $listId)
 * @method string getPrincipalType()
 * @method void setPrincipalType(string $type)
 * @method string getPrincipalId()
 * @method void setPrincipalId(string $id)
 * @method string getPermission()
 * @method void setPermission(string $permission)
 */
class Member extends Entity implements JsonSerializable {
	public const TYPE_USER = 'user';
	public const TYPE_GROUP = 'group';
	public const PERM_EDIT = 'edit';
	public const PERM_VIEW = 'view';

	protected $listId = 0;
	// null, not a default value: Entity setters only mark a field as changed when the value differs,
	// so a default of "user" made insert() skip the column for user shares (NOT NULL violation)
	protected $principalType = null;
	protected $principalId = null;
	protected $permission = null;

	public function __construct() {
		$this->addType('listId', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'type' => $this->principalType,
			'id' => $this->principalId,
			'permission' => $this->permission,
		];
	}
}
