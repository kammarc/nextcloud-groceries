#!/usr/bin/env node
/**
 * Generates src/lib/emoji-groups.json for the emoji picker from the Unicode emoji data
 * (devDependency unicode-emoji-json). Only the characters are kept (small bundle), groups are
 * re-ordered for a shopping app, and emoji newer than Emoji 14 are left out because many phones
 * cannot render them yet.
 *
 * Also writes src/lib/emoji-names/<lang>.js for the picker's search: per emoji its Unicode CLDR
 * name and keywords (devDependencies cldr-annotations-full and cldr-annotations-derived-full),
 * as one string "name|keyword|keyword…". These are loaded on demand, not bundled. The content is
 * JSON, but the extension is .js: Nextcloud's .htaccess only serves a few file types (css, js, fonts,
 * images, …) straight from app folders and routes .json requests to index.php (404).
 *
 * Usage: node scripts/build-emoji.mjs
 */
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs'
import { createRequire } from 'node:module'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const require = createRequire(import.meta.url)
const root = join(dirname(fileURLToPath(import.meta.url)), '..')
const data = JSON.parse(readFileSync(require.resolve('unicode-emoji-json/data-by-group.json'), 'utf8'))

const GROUPS = [
	['food_drink', '🍎', 'Food & drink', 'Essen & Trinken'],
	['objects', '💡', 'Objects', 'Objekte'],
	['animals_nature', '🌿', 'Animals & nature', 'Tiere & Natur'],
	['travel_places', '🏪', 'Places', 'Orte'],
	['activities', '⚽', 'Activities', 'Aktivitäten'],
	['smileys_emotion', '😀', 'Smileys', 'Smileys'],
	['people_body', '🧑', 'People', 'Menschen'],
	['symbols', '❤️', 'Symbols', 'Symbole'],
	['flags', '🏳️', 'Flags', 'Flaggen'],
]
const MAX_VERSION = 14.0

const bySlug = new Map(data.map((g) => [g.slug, g]))
const out = GROUPS.map(([key, icon, en, de]) => {
	const g = bySlug.get(key)
	if (!g) throw new Error('missing emoji group ' + key)
	return {
		key,
		icon,
		names: { en, de },
		emojis: g.emojis.filter((e) => parseFloat(e.emoji_version) <= MAX_VERSION).map((e) => e.emoji),
	}
})
writeFileSync(join(root, 'src', 'lib', 'emoji-groups.json'), JSON.stringify(out) + '\n')
console.log('emoji-groups.json: ' + out.map((g) => `${g.key} ${g.emojis.length}`).join(', '))

// search names: CLDR annotations (derived ones cover flags, keycaps, skin tones, …)
const codes = readFileSync(join(root, 'src', 'lib', 'languages.ts'), 'utf8').match(/code: '([a-z]+)'/g).map((m) => m.slice(7, -1))
const strip = (e) => e.replace(/️/g, '')
const load = (pkg, dir, locale) => {
	try {
		const json = JSON.parse(readFileSync(require.resolve(`${pkg}/${dir}/${locale}/annotations.json`), 'utf8'))
		return json[dir].annotations
	} catch {
		return {}
	}
}
const namesDir = join(root, 'src', 'lib', 'emoji-names')
mkdirSync(namesDir, { recursive: true })
const counts = []
for (const code of codes) {
	const locale = code === 'nb' ? 'no' : code // CLDR files Norwegian bokmål under "no"
	const all = { ...load('cldr-annotations-derived-full', 'annotationsDerived', locale), ...load('cldr-annotations-full', 'annotations', locale) }
	const byStripped = new Map(Object.entries(all).map(([e, a]) => [strip(e), a]))
	const names = {}
	for (const g of out) {
		for (const e of g.emojis) {
			const a = all[e] ?? byStripped.get(strip(e))
			if (!a) continue
			const words = [...(a.tts ?? []), ...(a.default ?? [])].map((w) => w.trim()).filter(Boolean)
			if (words.length) names[e] = [...new Set(words)].join('|')
		}
	}
	writeFileSync(join(namesDir, code + '.js'), JSON.stringify(names) + '\n')
	counts.push(`${code} ${Object.keys(names).length}`)
}
console.log('emoji-names: ' + counts.join(', '))
