# Changelog

## Unreleased

- List settings → "Hand-drawn icons": item icons are drawn as one-colour pen sketches in the
  text colour instead of colour emoji (per person and list, off by default)
- 18 more languages: French, Spanish, Italian, Portuguese, Dutch, Polish, Czech, Swedish, Danish,
  Norwegian, Finnish, Hungarian, Romanian, Turkish, Russian, Ukrainian, Greek and Japanese, for the
  app, the item catalog and the settings page. The app follows the Nextcloud language, or the one
  chosen under Settings → Language
- Lists: a field at the bottom of a list replaces the round ＋ button and opens the add sheet; in
  grid view the search results in the add sheet are tiles, and "Add …" for your own text is the
  last entry
- Lists overview: the edit button (pencil) sorts lists and shows a button to delete a list (or
  leave a list shared with you)
- Settings → Help: guide for installing the app on phone and computer, and "Report a bug"
- Fix: Chrome and other browsers reported the app as "not installable" because Nextcloud answered
  the manifest request with a redirect loop
- The app version is shown only in the app settings, no longer in the list settings
- Backup: Settings → Backup exports the whole library (all lists with items, stores, custom
  categories, history, statistics, per-list preferences and the app settings) as one JSON file, and
  imports it again, either added to the existing lists or replacing all lists you own. Imported lists
  belong to you and are not shared; entries by other people are credited to you
- Shared lists: "Added by …" under items is now off by default and can be switched on per person
  under List settings → "Show who added items"
- Add sheet: tapping an entry no longer brings up the keyboard if the search field wasn't focused
  before
- Welcome guide on the first start (lists, adding items, shopping, sharing, store order); can be
  opened again under Settings → Quick guide
- QR code with the app's address: in the personal settings section and, when the app is opened on a
  computer, as a card in the lists overview and in the welcome guide, so the phone just scans it
- On a computer: the app shows as a centred column instead of stretching across the window, and a
  "Nextcloud" button in the top bar leads back to Nextcloud (hidden in the installed phone app)
- German texts in standard German instead of Swiss spelling (ß, „…“) in the app, the settings page
  and the catalog (e.g. Sahne, Zucchini, Rote Bete, Weißwein); the Swiss words stay searchable.
  The separate Swiss translation files (de_CH) were removed.

## 0.2.0 – first public release

- Installable web app for iPhone and Android (iOS and Material look), German and English
- Login once per device with a Nextcloud app password (Login Flow v2, or taken over from an existing
  browser session); logout revokes the device
- Shared lists for users and groups with edit or view permission, following Nextcloud's sharing
  settings; list types for groceries, pharmacy and hardware store, each with its own departments
- Live sync by polling, offline outbox and optimistic UI
- Departments with a per-store aisle order, grouped or ungrouped, list or grid view, cart section,
  "finish shopping" and automatic clearing
- Add sheet with search, recently and frequently bought items ("Frequently bought" can be switched
  off per person), amount sheet on long press; edit sheet with name, amount, note, department and icon
- Catalog of about 600 items (German and English) with icons, synonyms and about 2,300 brand names
- Lists screen sortable by drag and drop (per user, same on all devices)
- Statistics per list (week, month, year, all time), JSON export/import, story view, and a
  yearly review from 15 December
- Four colour schemes, each in dark and light
- Integration API for other apps (bulk add with preview, ingredient parser, remove by recipe),
  capability and `IShoppingListService` for in-process use
- Security: encrypted credentials on the device, strict Content Security Policy, per-user rate limits
