# Groceries for Nextcloud

Shared shopping lists for the whole family. Groceries is a [Nextcloud](https://nextcloud.com) app
with an installable web app that looks and behaves like a native app on iPhone and Android.

- **Install once, stay logged in.** Each phone gets its own Nextcloud app password the first time
  it connects. App passwords don't expire, so the app keeps working until you log out in the app or
  revoke the device under *Settings → Security → Devices & sessions*.
- **Live updates.** When someone in the family adds or ticks an item, everyone else sees it
  within about 3 seconds. This works through polling, so the server needs no extra push service.
- **Sorted by aisle.** Items are grouped by store department. You save the department order of each
  store you shop at, and the list follows the order of the store you pick.
- **Tick while shopping.** Ticked items move to an "In the cart" section, so the list shows only
  what is left. "Finish shopping" clears the cart, or the app clears it automatically after a set
  number of hours.
- **Several lists, shared with users or groups.** List types for groceries, pharmacy and hardware
  store, each with its own departments. Each person can be an editor or a viewer. Sharing follows
  the server's Nextcloud sharing settings. Everyone sorts the lists overview by drag and drop.
- **List or grid.** A classic list, or icon tiles similar to a home screen.
- **Built-in catalog.** About 600 items in German and English with icons and departments, plus
  thousands of synonyms and common brand names ("Nutella", "Zewa", "Persil"). A history of what you
  bought before gives one-tap suggestions.
- **Works offline in the store.** Changes are queued on the phone and sync when it is back online.
- **Statistics.** Most bought items, who adds and who shops how often, departments and weekdays,
  for the last week, month, year or all time, also as a short story. Can be exported and imported
  as JSON. From 15 December the app offers a year-in-review story.
- **Light and dark colour schemes** in four colours, German and English interface.
- **Integration API.** Other apps can add recipe ingredients, with a preview first. See
  [docs/cookbook-integration.md](docs/cookbook-integration.md).

## Requirements

- Nextcloud 30–33 and PHP 8.1 or newer. Automated tests run against Nextcloud 31 and 32.
- **HTTPS.** Installing the app on a phone requires it (except on `localhost`), and without it
  passwords and lists would travel unencrypted.
- Nextcloud's background jobs (cron) for clearing the cart automatically.
- The usual `.htaccess` or nginx rules of Nextcloud. Without pretty URLs the app is reachable under
  `/index.php/apps/groceries/`.

## Installation (administrators)

Groceries is not in the Nextcloud app store yet, so it is installed by hand as a folder named
`groceries` in one of your Nextcloud's app directories. You need shell access to the server and
the `occ` command.

### 1. Find the app directory

Nextcloud loads apps from the folders listed under `apps_paths` in `config/config.php`. Use the
writable one, usually:

| Setup | App directory |
|---|---|
| Regular installation | `/var/www/nextcloud/apps/` or, if configured, `/var/www/nextcloud/custom_apps/` |
| Official Docker image (`nextcloud`) | `/var/www/html/custom_apps/` inside the container (a volume) |
| Nextcloud All-in-One | `/var/www/html/custom_apps/` in the `nextcloud-aio-nextcloud` container |
| Snap | `/var/snap/nextcloud/current/nextcloud/extra-apps/` |

### 2. Put the app there

**From a release (recommended):** download `groceries-<version>.tar.gz` from the
[releases page](https://github.com/kammarc/nextcloud-groceries/releases) and extract it into the
app directory, so that you get `<app directory>/groceries/appinfo/info.xml`:

```sh
cd /var/www/nextcloud/custom_apps
sudo -u www-data tar -xzf /path/to/groceries-0.2.0.tar.gz
```

For Docker, copy the extracted folder into the container instead (never bind-mount it over
`/var/www/html`, see [Development](#run-in-a-local-nextcloud)):

```sh
tar -xzf groceries-0.2.0.tar.gz
docker cp groceries nextcloud:/var/www/html/custom_apps/
docker exec nextcloud chown -R www-data:www-data /var/www/html/custom_apps/groceries
```

**From source:** clone the repository on a machine with Node.js 20+, run `npm ci && make appstore`
and use the tarball from `build/` as above. A plain `git clone` into the app directory does not
work, because the built `js/` and `css/` folders are not in git.

### 3. Enable it

```sh
sudo -u www-data php occ app:enable groceries        # Docker: docker exec -u www-data nextcloud php occ app:enable groceries
```

or in Nextcloud under *Apps → Your apps → Groceries → Enable*. Enabling creates the database tables.

### 4. Check the server

- The site must be reachable over **HTTPS**.
- **Background jobs** should run by cron (*Administration settings → Basic settings*), otherwise
  ticked items are not cleared automatically.
- Users then find the phone link in their personal settings under *Groceries*.

### Updating

Replace the `groceries` folder with the one from the new release, then run
`sudo -u www-data php occ upgrade` (or open Nextcloud once in the browser and confirm the update).
Lists and settings are kept; database changes are applied automatically.

### Uninstalling

`occ app:disable groceries`, then *Apps → Groceries → Remove* (or `occ app:remove groceries`).

## Install on a phone

1. Open `https://<your-nextcloud>/apps/groceries/` on the phone. The easiest way: scan the QR code
   shown in your personal settings in Nextcloud (section *Groceries*), or in the app itself when you
   open it on a computer.
2. Add it to the home screen:
   - **iPhone (Safari):** tap Share, then *Add to Home Screen*.
   - **Android (Chrome):** tap *Install* in the app, or use the browser menu, then *Install app*.
3. Open the app from the home screen and tap **Connect to Nextcloud** once.

On Android, if Chrome is already logged in to your Nextcloud, step 3 happens automatically.
On the first start, a short guide explains the basics; it can be opened again under
*Settings → Quick guide*.

## Development

Needs Node.js 20+, PHP 8.1+ and Composer.

```sh
npm ci && composer install
npm run build              # catalog.json, js/ and css/ bundle, THIRD-PARTY-NOTICES.md
npm run dev                # development build, rebuilt on every change
npm test                   # Vitest: parser/catalog parity, offline projection, statistics, translations
npm run typecheck
composer test:unit         # PHPUnit (same shared fixtures as the TS tests)
composer lint              # PHP syntax check
make appstore              # release tarball in build/
```

### Run in a local Nextcloud

```sh
docker run -d --name groceries-dev -p 8089:80 -e SQLITE_DATABASE=nextcloud \
  -e NEXTCLOUD_ADMIN_USER=admin -e NEXTCLOUD_ADMIN_PASSWORD=admin nextcloud:31-apache
# wait until `docker exec -u www-data groceries-dev php occ status` says "installed: true"
npm run build
sh scripts/dev-sync.sh groceries-dev      # copy the app in, enable it, reset OPcache
```

Then open <http://localhost:8089/apps/groceries/> and log in as `admin` / `admin`.

> ⚠️ Do **not** bind-mount the repository into `/var/www/html` of the official image. Its
> entrypoint runs `rsync --delete` over `/var/www/html` and deletes the mounted files.
> Use `scripts/dev-sync.sh` instead, and run it again after every build.

End-to-end checks against that container need two users in a common group. The tests create many
lists, so brute-force protection and rate limits are switched off on this test server (never do
that on a real one):

```sh
docker exec -u www-data -e OC_PASS=Test-Pass-4711 groceries-dev sh -c '
  php occ user:add --password-from-env anna && php occ user:add --password-from-env ben &&
  php occ group:add family && php occ group:adduser family anna && php occ group:adduser family ben &&
  php occ config:system:set auth.bruteforce.protection.enabled --value=false --type=boolean &&
  php occ config:system:set ratelimit.protection.enabled --value=false --type=boolean'
BASE=http://localhost:8089 A=anna:Test-Pass-4711 B=ben:Test-Pass-4711 bash tests/integration/api-smoke.sh
# browser test in iPhone and Android emulation (needs: npm i --no-save playwright && npx playwright install chromium)
BASE=http://localhost:8089 USER_NAME=anna PASS=Test-Pass-4711 node tests/e2e/mobile-smoke.mjs
```

### Layout

| Path | Contents |
|---|---|
| `appinfo/`, `lib/` | Nextcloud app: OCS API, services, migrations, background jobs, `Public/` integration API |
| `catalog/*.txt` | Catalog sources (items, departments, list-type departments, brand names); `scripts/build-catalog.mjs` turns them into `lib/Catalog/catalog.json` |
| `src/` | Vue 3 + Ionic web app: `stores/data.ts` (sync and offline outbox), `sync/projection.ts` (optimistic state), views and components |
| `serviceworker/sw.js` | Service worker, served by `PageController::serviceWorker()` |
| `l10n/` | Server-side translations, generated by `scripts/build-l10n.mjs` (the web app's texts are in `src/i18n.ts`) |
| `scripts/` | Build scripts (catalog, emoji list, icons, translations, third-party notices) and `dev-sync.sh` |
| `tests/` | Shared fixtures, PHPUnit, Vitest, API smoke test, browser smoke test |

### How sync works

- **Revisions.** Every change increments a per-list `revision`, and each changed row (including
  deletion markers) is stamped with it.
- **Polling.** Clients poll `GET /lists/{id}/changes?since=N` every 3 s while a list is open and
  visible, and the lists overview every 15 s. When nothing has changed, the answer is small.
- **Offline changes.** Local edits go into an outbox in IndexedDB and are sent in order. Each
  create carries a client-generated `opId`, so retries are idempotent. Until the server confirms a
  change, the UI shows the server state with the pending changes applied on top (optimistic UI).
- **Duplicates.** Adding an item that is already on the list adds the quantities together
  ("500 g" + "1 kg" = "1.5 kg"). Adding a ticked item reopens it. The server and the client use
  the same parser, matcher and quantity logic, tested against the same fixtures.

### Catalog

Items live in `catalog/items.txt`, one line per item:
`emoji|English name|German name|English synonyms;…|German synonyms;…|flags`. Brand names are in
`catalog/brands.txt` (`item_key: Brand, Brand, …`). A test makes sure no word belongs to two
items. Run `npm run build` (or `npm run catalog`) after changing them.

## Security

- **Authentication.** Each device gets its own Nextcloud app password (Login Flow v2). It is sent only
  as an `Authorization` header to the app's OCS API, never in URLs, and it can be revoked per device
  under *Settings → Security → Devices & sessions*. Logging out in the app revokes it.
- **On the device.** The app password is encrypted at rest (AES-GCM, non-extractable key). Logout
  deletes credentials, key and cached lists.
- **Permissions.** Every API call checks the caller's access to the list (owner, edit, view). Item,
  store and history ids are always looked up inside that list.
- **Sharing.** Follows Nextcloud's sharing settings. The people search is Nextcloud's sharee
  search, so user enumeration limits apply.
- **Web page.** Strict Content Security Policy with a per-request nonce, no inline scripts, no
  third-party resources, no CORS. The cached app page contains no personal data.
- **Abuse limits.** Per-user rate limits and size limits on all endpoints that create data.

Please report vulnerabilities privately, see [SECURITY.md](SECURITY.md).

## Contributing

Bug reports and pull requests are welcome, see [CONTRIBUTING.md](CONTRIBUTING.md).

## Transparency: built with AI assistance

This project was written to a large extent by **Claude**, an AI model by Anthropic, used as a coding
assistant (Claude Code). That covers architecture, backend and frontend code, the item catalog, the
tests and this documentation. The project owner defined the requirements and made the product and
design decisions; the automated tests (unit, API and browser tests) were also written and run with
the AI. Please review and test it like any other third-party code before relying on it, and report
issues.

## License

Groceries is released under the [MIT License](LICENSE).

The built web app contains open source packages under their own licenses (MIT, Apache-2.0); their
notices are in [THIRD-PARTY-NOTICES.md](THIRD-PARTY-NOTICES.md). Emoji are drawn by the device's
system font. "Nextcloud" is a trademark of Nextcloud GmbH; this app is not an official Nextcloud
product. Brand names in the item catalog are trademarks of their owners and are used only so that
typed product names are recognised.
