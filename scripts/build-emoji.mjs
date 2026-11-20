#!/usr/bin/env node
/**
 * Generates src/lib/emoji-groups.json for the emoji picker from the Unicode emoji data
 * (devDependency unicode-emoji-json). Only the characters are kept (small bundle), groups are
 * re-ordered for a shopping app, and emoji newer than Emoji 14 are left out because many phones
 * cannot render them yet.
 *
 * Usage: node scripts/build-emoji.mjs
 */
import { readFileSync, writeFileSync } from 'node:fs'
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
