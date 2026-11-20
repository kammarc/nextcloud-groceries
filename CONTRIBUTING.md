# Contributing

Thanks for helping! Bug reports, catalog additions, translations and code are all welcome.

## Reporting bugs

Open an issue with the Groceries and Nextcloud versions, the phone and browser, the steps to
reproduce, and what you expected. Screenshots help. Security problems go to [SECURITY.md](SECURITY.md)
instead.

## Pull requests

1. Set up the development environment as described in the [README](README.md#development).
2. Keep changes focused; one topic per pull request.
3. Before you push, run:

   ```sh
   npm run typecheck && npm test && npm run build
   composer lint && composer test:unit
   ```

4. Parsing, matching and quantity logic exists twice, in PHP (`lib/Service/`) and TypeScript
   (`src/lib/`). Change both and add a case to `tests/fixtures/shared-cases.json`.
5. Every text in the web app needs a German translation in `src/i18n.ts` (a test checks this).
6. Add a line to the "unreleased" section of [CHANGELOG.md](CHANGELOG.md).

By contributing you agree that your contribution is released under the project's
[MIT License](LICENSE).

## Catalog

Items are in `catalog/items.txt`, brand names in `catalog/brands.txt` (format described at the top
of each file). Only add brands that clearly stand for one product. `npm test` fails if a word would
belong to two items.
