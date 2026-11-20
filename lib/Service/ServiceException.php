<?php

declare(strict_types=1);

namespace OCA\Groceries\Service;

/** Service-layer error; controllers map it to HTTP status 400. */
class ServiceException extends \RuntimeException {
	public function getStatus(): int {
		return 400;
	}
}
