<?php

declare(strict_types=1);

namespace OCA\Groceries\Controller;

use OCA\Groceries\Service\ForbiddenException;
use OCA\Groceries\Service\ServiceException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Shared helpers for the OCS controllers.
 */
trait ApiHelper {
	private IUserSession $userSession;

	protected function uid(): string {
		$user = $this->userSession->getUser();
		if ($user === null) {
			throw new ForbiddenException('Not logged in');
		}
		return $user->getUID();
	}

	/**
	 * Runs $fn and maps service exceptions to OCS error responses.
	 */
	protected function run(callable $fn, int $status = Http::STATUS_OK): DataResponse {
		try {
			return new DataResponse($fn(), $status);
		} catch (ServiceException $e) {
			return new DataResponse(['message' => $e->getMessage()], $e->getStatus());
		}
	}

	/**
	 * Only the given keys that are actually present in the request (so "absent" and "null" differ).
	 *
	 * @param list<string> $keys
	 */
	protected function only(IRequest $request, array $keys): array {
		$params = $request->getParams();
		$out = [];
		foreach ($keys as $k) {
			if (array_key_exists($k, $params)) {
				$out[$k] = $params[$k];
			}
		}
		return $out;
	}
}
