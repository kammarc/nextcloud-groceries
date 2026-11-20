# Adding recipe ingredients to a shopping list (Cookbook integration)

This document has two parts:

1. **What Groceries offers today.** Any app can use it now.
2. **A proposal for Nextcloud Cookbook.** It turns the Bring!-specific export from
   [cookbook#3602](https://github.com/nextcloud/cookbook/pull/3602) into a generic
   "Add to shopping list" hook, with Bring! and Groceries as two providers.

---

## 1. Groceries integration API

### Detecting Groceries

Groceries advertises itself in the Nextcloud capabilities
(`/ocs/v2.php/cloud/capabilities`, or `@nextcloud/capabilities` in the frontend):

```json
"groceries": {
  "version": "0.2.0",
  "api": "v1",
  "features": ["lists", "bulk-add", "dry-run", "parse", "remove-by-source"],
  "endpoints": {
    "lists": "/ocs/v2.php/apps/groceries/api/v1/lists",
    "bulk": "/ocs/v2.php/apps/groceries/api/v1/lists/{listId}/items/bulk",
    "parse": "/ocs/v2.php/apps/groceries/api/v1/parse",
    "app": "/apps/groceries/"
  }
}
```

### REST (OCS)

All calls need the header `OCS-APIRequest: true`. From a logged-in page, the session cookie and
request token are enough (`@nextcloud/axios` sends both).

| Call | Purpose |
|---|---|
| `GET /lists` | The user's lists. Each list has a `permission` field (`owner`, `edit` or `view`), so a picker can offer only writable lists. |
| `POST /parse` `{lines: [...], lang: "de"}` | Parses ingredient lines without writing anything. Returns `quantity`, `name`, `note`, `category`, `catalogKey`, `icon` and `staple` for each line. |
| `POST /lists/{id}/items/bulk` | Adds many lines at once (see below). |
| `DELETE /lists/{id}/sources/{source}/{sourceRef}` | Removes everything a recipe added, e.g. `/sources/cookbook/42`. |

**Bulk request.** The item shape matches the Bring! PR's `{line, name?, enabled?}`, so its mapping
dialog can be reused unchanged:

```json
{
  "items": [
    {"line": "200 g Mehl (Type 405)"},
    {"line": "2 Zwiebeln, fein gehackt"},
    {"line": "1 Prise Salz", "enabled": false},
    {"line": "3 Eier", "name": "Eier", "quantity": "3"}
  ],
  "mode": "merge",
  "source": "cookbook",
  "sourceRef": "42",
  "recipeName": "Zwiebelkuchen",
  "dryRun": true,
  "skipStaples": true
}
```

- **`dryRun: true`** writes nothing and returns the plan. Use it for a preview or review dialog.
  It corresponds to the Bring! PR's `preview` endpoint.
- **`mode`:**
  - `merge` (default) adds quantities to an item that is already on the list:
    "500 g" + "1 kg" becomes "1.5 kg". If the item was already ticked, it is reopened.
  - `separate` always creates new items and notes the recipe name on each one.
- **`skipStaples`:** pantry staples such as salt, pepper, flour, sugar and water are disabled by
  default. The user can re-enable them in the dialog.
- **Limits:** at most 200 items per request and 500 characters per line, the same as the Bring! PR.
  Bulk and parse calls are rate-limited to 60 per minute per user.

**Response.** It is the same for a dry run and a real request:

```json
{
  "revision": 57,
  "added": 0, "merged": 1, "reopened": 0, "skipped": 1,
  "rows": [
    {"line": "200 g Mehl (Type 405)", "name": "Mehl", "quantity": "200 g", "note": "Type 405",
     "category": "ingredients", "catalogKey": "flour", "icon": "🌾", "normName": "c:flour",
     "staple": true, "enabled": false, "action": "skip"},
    {"line": "2 Zwiebeln, fein gehackt", "name": "Zwiebeln", "quantity": "2", "note": "fein gehackt",
     "category": "produce", "catalogKey": "onions", "icon": "🧅", "normName": "c:onions",
     "staple": false, "enabled": true, "action": "merge", "existingId": 12, "resultQuantity": "3"}
  ]
}
```

Items added this way show "from: Zwiebelkuchen" in the app. Other family members see them
within a few seconds.

### PHP (in-process, no HTTP)

```php
use OCA\Groceries\Public\IShoppingListService;
use OCA\Groceries\Public\ItemLine;

if ($appManager->isEnabledForUser('groceries') && interface_exists(IShoppingListService::class)) {
    $groceries = \OCP\Server::get(IShoppingListService::class);
    $lists = $groceries->getWritableLists($uid);
    $result = $groceries->addItems($uid, $lists[0]['id'], [
        new ItemLine('200 g Mehl (Type 405)'),
        new ItemLine('2 Zwiebeln, fein gehackt'),
    ], ['source' => 'cookbook', 'sourceRef' => '42', 'sourceLabel' => 'Zwiebelkuchen', 'dryRun' => false]);
}
```

`IShoppingListService` (`lib/Public/IShoppingListService.php`) is the stable contract. Its
methods are `getWritableLists()`, `addItems()`, `parseLines()` and `removeBySource()`.

---

## 2. Proposal for Cookbook: a generic shopping-list hook

The Bring! PR adds a `BringController`, a `BringClient` and a `BringMergeService` under
`/webapp/bring/*`, together with a frontend dialog. Most of that is not specific to Bring!:
parsing lines, previewing, merging, letting the user pick a list, and turning the feature on
in settings. Only "talk to the Bring! API" is.

### Interface in Cookbook

```php
namespace OCA\Cookbook\ShoppingList;

interface IShoppingListProvider {
    /** Stable id, e.g. "bring", "groceries" */
    public function getId(): string;
    /** Shown in the UI, e.g. "Bring!", "Groceries" */
    public function getLabel(): string;
    /** False when the provider is not set up for this user (e.g. Bring! not logged in) */
    public function isAvailable(string $uid): bool;
    /** @return list<array{id: string, name: string}> */
    public function getLists(string $uid): array;
    /** @param list<array{line: string, name?: string, enabled?: bool}> $lines */
    public function preview(string $uid, string $listId, array $lines, string $mode, string $recipeName): array;
    public function export(string $uid, string $listId, array $lines, string $mode, string $recipeName, string $recipeId): array;
}

/** Dispatched by Cookbook; providers (also from other apps) register themselves. */
class RegisterShoppingListProvidersEvent extends \OCP\EventDispatcher\Event {
    private array $providers = [];
    public function register(IShoppingListProvider $p): void { $this->providers[$p->getId()] = $p; }
    public function getProviders(): array { return $this->providers; }
}
```

### Changes in Cookbook

- `BringController` becomes a generic `ShoppingListController`, with routes
  `/webapp/shopping-list/providers`, `.../{provider}/lists`, `.../{provider}/preview` and
  `.../{provider}/export`.
- The current Bring! code becomes `BringProvider implements IShoppingListProvider`. Its login and
  disconnect routes stay Bring!-specific.
- The frontend shows **one** "Add to shopping list" button on the recipe page:
  - If more than one provider is available, the user first picks a provider, then a list.
  - The existing mapping dialog is reused for every provider.
- The per-user setting changes from `bring_enabled` to a default provider and list, plus
  "ask every time".

### What Groceries would contribute

Once Cookbook ships the event (not implemented in either app yet):

- Groceries listens for `RegisterShoppingListProvidersEvent` and registers a
  `GroceriesProvider`. It is a thin wrapper around `IShoppingListService`:
  - `preview()` calls `addItems(... dryRun: true)`
  - `export()` calls `addItems(... dryRun: false)`
- The listener only exists in Groceries. Cookbook needs no hard dependency on it, and Groceries
  needs none on Cookbook (it checks `class_exists` for the event).
- Until Cookbook ships the event, its frontend can already call the Groceries REST API directly.
  It detects Groceries through the capability shown above.

### Why it is worth doing

- **Self-hosted alternative:** families who don't want a third-party account get the same
  "recipe → shopping list" feature entirely inside Nextcloud.
- **Better categories:** Groceries' parser and catalog (DE/EN, about 600 items with synonyms and brand names) sort recipe
  ingredients into the right aisle automatically.
- **No hard-coding:** other list apps can register too, such as Deck or Tasks lists, without
  Cookbook knowing about them.
