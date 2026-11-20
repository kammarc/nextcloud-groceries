<?php

declare(strict_types=1);

namespace OCA\Groceries\Public;

/**
 * Stable, in-process API for other Nextcloud apps (e.g. Cookbook).
 *
 * Usage from another app (no hard dependency needed):
 *
 *   if ($appManager->isEnabledForUser('groceries')
 *       && interface_exists(\OCA\Groceries\Public\IShoppingListService::class)) {
 *       $svc = \OCP\Server::get(\OCA\Groceries\Public\IShoppingListService::class);
 *       $lists = $svc->getWritableLists($uid);
 *       $svc->addItems($uid, $lists[0]['id'], [new ItemLine('200 g Mehl')], ['source' => 'cookbook', 'sourceRef' => '42', 'sourceLabel' => 'Pizza']);
 *   }
 *
 * @since 0.1.0
 */
interface IShoppingListService {
	public const API_VERSION = 1;

	/**
	 * Lists the user may add items to.
	 *
	 * @return list<array{id: int, name: string, icon: ?string, color: ?string, template: string, openCount: int}>
	 */
	public function getWritableLists(string $uid): array;

	/**
	 * Adds (or, with dryRun, previews) items.
	 *
	 * @param list<ItemLine|array> $lines
	 * @param array{mode?: 'merge'|'separate', source?: string, sourceRef?: ?string, sourceLabel?: ?string, dryRun?: bool, skipStaples?: bool} $options
	 * @return array{revision: int, rows: list<array>, added: int, merged: int, reopened: int, skipped: int}
	 * @throws \RuntimeException when the list does not exist, is not writable, or input is invalid
	 */
	public function addItems(string $uid, int $listId, array $lines, array $options = []): array;

	/**
	 * Parses free-text ingredient lines without touching any list.
	 *
	 * @param list<string> $lines
	 * @return list<array{line: string, quantity: ?string, name: string, note: ?string, category: string, catalogKey: ?string, catalogName: ?string, icon: ?string, staple: bool}>
	 */
	public function parseLines(array $lines, string $language = 'en'): array;

	/**
	 * Removes all items previously added with the given source/sourceRef (e.g. one recipe).
	 */
	public function removeBySource(string $uid, int $listId, string $source, string $sourceRef): int;
}
