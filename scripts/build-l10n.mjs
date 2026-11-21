#!/usr/bin/env node
/**
 * German translations for the PHP-rendered parts (Nextcloud settings page) in Nextcloud's l10n format.
 * The mobile app itself is translated in src/i18n.ts.
 * Fails when a string used with $l->t() in PHP has no translation.
 *
 * Usage: node scripts/build-l10n.mjs
 */
import { copyFileSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const root = join(dirname(fileURLToPath(import.meta.url)), '..')

const de = {
	'Groceries': 'Einkaufslisten',
	'Groceries on your phone': 'Einkaufslisten auf dem Handy',
	'Open this address on your phone and add it to the home screen. You log in once; the app then keeps its own device password, which you can revoke any time under Security → Devices & sessions.':
		'Öffne diese Adresse auf dem Handy und füge sie zum Home-Bildschirm hinzu. Du meldest dich einmal an; die App behält danach ihr eigenes Gerätepasswort, das du jederzeit unter Sicherheit → Geräte & Sitzungen widerrufen kannst.',
	'iPhone / iPad (Safari)': 'iPhone / iPad (Safari)',
	'Open the address in Safari.': 'Öffne die Adresse in Safari.',
	'Tap the Share button, then "Add to Home Screen".': 'Tippe auf „Teilen“ und dann auf „Zum Home-Bildschirm“.',
	'Open Groceries from the home screen and tap "Connect to Nextcloud".': 'Öffne die App vom Home-Bildschirm und tippe auf „Mit Nextcloud verbinden“.',
	'QR code with the address of the app': 'QR-Code mit der Adresse der App',
	'Scan the code with the phone camera, or open this address on the phone:': 'Scanne den Code mit der Handykamera oder öffne diese Adresse auf dem Handy:',
	'Android (Chrome)': 'Android (Chrome)',
	'Open the address in Chrome.': 'Öffne die Adresse in Chrome.',
	'Tap "Install app" in the app or the browser menu (⋮ → "Install app").': 'Tippe in der App auf „Installieren“ oder im Browsermenü auf ⋮ → „App installieren“.',
}

const used = new Set()
for (const file of ['templates/personal.php', 'lib/Settings/PersonalSection.php']) {
	const src = readFileSync(join(root, file), 'utf8')
	for (const m of src.matchAll(/->t\('((?:[^'\\]|\\.)*)'\)/g)) used.add(m[1].replace(/\\'/g, "'"))
}
const missing = [...used].filter((k) => !(k in de))
if (missing.length) {
	console.error('Missing German translations:\n' + missing.join('\n'))
	process.exit(1)
}

const plural = 'nplurals=2; plural=(n != 1);'
mkdirSync(join(root, 'l10n'), { recursive: true })
const json = JSON.stringify({ translations: de, pluralForm: plural }, null, '\t') + '\n'
const js = `OC.L10N.register(\n\t"groceries",\n\t${JSON.stringify(de, null, '\t').replace(/\n/g, '\n\t')},\n\t"${plural}"\n);\n`
for (const code of ['de', 'de_DE']) {
	writeFileSync(join(root, 'l10n', `${code}.json`), json)
	writeFileSync(join(root, 'l10n', `${code}.js`), js)
}
console.log(`l10n: ${used.size} PHP strings translated (de, de_DE)`)
