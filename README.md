# Groceries for Nextcloud

Shared shopping lists for the whole family. Groceries is a [Nextcloud](https://nextcloud.com) app
that you install on iPhone and Android like a native app. A short guide in the app shows how.

## Features

- Stay logged in: one app password per phone, no repeated logins
- Live updates within seconds for everyone on the list
- Items sorted by store department, with a saved order per store
- "In the cart" section while shopping, cleared automatically
- Several lists, shared with users or groups as editor or viewer
- List types for groceries, pharmacy and hardware store
- List or tile view
- Catalog of about 600 items with icons, synonyms and brand names
- Suggestions from your own shopping history
- Works offline in the store and syncs later
- Statistics and a year-in-review story
- Backup and restore of all lists, history and statistics as one file
- Light and dark mode in four colours
- German and English
- API for other apps, e.g. to add recipe ingredients

## Requirements

- Nextcloud 30–33, PHP 8.1 or newer
- HTTPS
- Nextcloud background jobs (cron), to clear the cart automatically

Groceries will be available in the Nextcloud app store soon.

## Security

- Each device gets its own Nextcloud app password, revocable under
  *Settings → Security → Devices & sessions*. Logging out in the app revokes it.
- The password is stored encrypted on the device. Logging out deletes it and all cached lists.
- Every request checks your access to the list. Sharing follows your Nextcloud's sharing settings.
- Strict Content Security Policy, no third-party resources, no tracking.
- Rate and size limits against abuse.

Please report vulnerabilities privately, see [SECURITY.md](SECURITY.md).

## Contributing

Bug reports, catalog additions, translations and pull requests are welcome. See
[CONTRIBUTING.md](CONTRIBUTING.md) and the [development guide](docs/development.md).

## Built with AI assistance

This project was written to a large extent by **Claude**, an AI model by Anthropic, used as a coding
assistant (Claude Code). That covers architecture, backend and frontend code, the item catalog, the
tests and this documentation. The project owner defined the requirements and made the product and
design decisions. Please review and test it like any other third-party code before relying on it,
and report issues.

## License

[MIT License](LICENSE). The built web app contains open source packages under their own licenses,
see [THIRD-PARTY-NOTICES.md](THIRD-PARTY-NOTICES.md).

"Nextcloud" is a trademark of Nextcloud GmbH; this app is not an official Nextcloud product. Brand
names in the item catalog are trademarks of their owners and are used only so that typed product
names are recognised.
