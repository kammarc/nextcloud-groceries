# Changelog

## Unreleased

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
