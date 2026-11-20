<?php

declare(strict_types=1);

namespace OCA\Groceries\Service;

/** Service-layer error; controllers map it to HTTP status 404. */
class NotFoundException extends ServiceException {
	public function getStatus(): int {
		return 404;
	}
}
