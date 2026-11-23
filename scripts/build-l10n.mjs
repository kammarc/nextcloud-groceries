#!/usr/bin/env node
/**
 * Translations for the PHP-rendered parts (Nextcloud settings page, manifest description) in
 * Nextcloud's l10n format, from scripts/l10n/<code>.json (English text as the key).
 * The mobile app itself is translated in src/locales/.
 * Fails when a string used with ->t() in PHP has no translation in one of the languages.
 *
 * Usage: node scripts/build-l10n.mjs
 */
import { mkdirSync, readdirSync, readFileSync, writeFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const root = join(dirname(fileURLToPath(import.meta.url)), '..')

/** Nextcloud language codes written per source file (Nextcloud has no plain "pt"). */
const TARGETS = { de: ['de', 'de_DE'], pt: ['pt_BR', 'pt_PT'] }

const used = new Set()
for (const file of ['templates/personal.php', 'lib/Settings/PersonalSection.php', 'lib/Controller/PageController.php']) {
	const src = readFileSync(join(root, file), 'utf8')
	for (const m of src.matchAll(/->t\('((?:[^'\\]|\\.)*)'\)/g)) used.add(m[1].replace(/\\'/g, "'"))
}

const srcDir = join(root, 'scripts', 'l10n')
const errors = []
const written = []
mkdirSync(join(root, 'l10n'), { recursive: true })
for (const file of readdirSync(srcDir).filter((f) => f.endsWith('.json')).sort()) {
	const code = file.slice(0, -5)
	const tr = JSON.parse(readFileSync(join(srcDir, file), 'utf8'))
	const missing = [...used].filter((k) => !tr[k])
	if (missing.length) errors.push(`${file}: missing\n  ${missing.join('\n  ')}`)
	// only strings that are still in use
	const translations = Object.fromEntries([...used].filter((k) => tr[k]).map((k) => [k, tr[k]]))
	// Nextcloud wants a plural rule; none of these strings is plural, so a simple one is enough
	const plural = code === 'ja' ? 'nplurals=1; plural=0;' : 'nplurals=2; plural=(n != 1);'
	const json = JSON.stringify({ translations, pluralForm: plural }, null, '\t') + '\n'
	const js = `OC.L10N.register(\n\t"groceries",\n\t${JSON.stringify(translations, null, '\t').replace(/\n/g, '\n\t')},\n\t"${plural}"\n);\n`
	for (const target of TARGETS[code] ?? [code]) {
		writeFileSync(join(root, 'l10n', `${target}.json`), json)
		writeFileSync(join(root, 'l10n', `${target}.js`), js)
		written.push(target)
	}
}
if (errors.length) {
	console.error(errors.join('\n'))
	process.exit(1)
}
console.log(`l10n: ${used.size} PHP strings in ${written.length} languages (${written.join(', ')})`)
