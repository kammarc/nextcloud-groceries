#!/usr/bin/env node
/**
 * Builds lib/Catalog/catalog.json from catalog/categories.txt, items.txt, type-categories.txt and brands.txt,
 * plus item and department names in further languages from catalog/i18n/<code>.json
 * ({ "categories": { key: name }, "items": { key: name } }).
 * The JSON is shared by the PHP backend (matching recipe ingredients) and the
 * frontend (offline typeahead + icons).
 *
 * Usage: node scripts/build-catalog.mjs
 */
import { readdirSync, readFileSync, writeFileSync, mkdirSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import { createHash } from 'node:crypto'

const root = join(dirname(fileURLToPath(import.meta.url)), '..')

const slug = (s) => s.normalize('NFD').replace(/[̀-ͯ]/g, '')
	.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '')
const list = (s) => (s ?? '').split(';').map((x) => x.trim()).filter(Boolean)
const lines = (file) => readFileSync(join(root, 'catalog', file), 'utf8')
	.split(/\r?\n/).map((l) => l.trim())

const categories = []
for (const line of lines('categories.txt')) {
	if (!line || line.startsWith('#')) continue
	const [key, template, icon, en, de] = line.split('|')
	categories.push({ key, template, icon, names: { en, de } })
}
const catKeys = new Set(categories.map((c) => c.key))

const items = []
const seen = new Map()
let current = null
let lineNo = 0
const errors = []
for (const line of lines('items.txt')) {
	lineNo++
	if (!line) continue
	if (line.startsWith('#')) {
		const m = line.match(/^#\s*([a-z_]+)\s*$/)
		if (m && catKeys.has(m[1])) current = m[1]
		continue
	}
	if (!current) { errors.push(`line ${lineNo}: item outside category`); continue }
	const [icon, en, de, enSyn, deSyn, flags = ''] = line.split('|')
	if (!icon || !en || !de) { errors.push(`line ${lineNo}: missing fields: ${line}`); continue }
	const key = slug(en)
	if (seen.has(key)) { errors.push(`line ${lineNo}: duplicate key ${key} (first on line ${seen.get(key)})`); continue }
	seen.set(key, lineNo)
	const item = { key, category: current, icon, names: { en, de } }
	const syn = { en: list(enSyn), de: list(deSyn) }
	if (syn.en.length || syn.de.length) item.synonyms = syn
	if (flags.includes('s')) item.staple = true
	items.push(item)
}
// category of an item in pharmacy/hardware lists (grocery lists use item.category)
const byKey = new Map(items.map((i) => [i.key, i]))
const catType = new Map(categories.map((c) => [c.key, c.template]))
let type = null
for (const line of lines('type-categories.txt')) {
	if (!line) continue
	const m = line.match(/^#\s*([a-z]+)\s*$/)
	if (m) { type = m[1]; continue }
	if (line.startsWith('#')) continue
	const [cat, list] = line.split(':')
	if (catType.get(cat.trim()) !== type) { errors.push(`type-categories: ${cat} is not a ${type} category`); continue }
	for (const key of list.split(',').map((k) => k.trim()).filter(Boolean)) {
		const item = byKey.get(key)
		if (!item) { errors.push(`type-categories: unknown item ${key}`); continue }
		item.types = { ...(item.types ?? {}), [type]: cat.trim() }
	}
}

// brand names: searchable in both languages, so they go into both synonym lists
let brandCount = 0
for (const line of lines('brands.txt')) {
	if (!line || line.startsWith('#')) continue
	const i = line.indexOf(':')
	const item = byKey.get(line.slice(0, i).trim())
	if (i < 0 || !item) { errors.push(`brands: unknown item in "${line.slice(0, 40)}"`); continue }
	const syn = item.synonyms ?? (item.synonyms = { en: [], de: [] })
	for (const brand of line.slice(i + 1).split(',').map((b) => b.trim()).filter(Boolean)) {
		for (const lang of ['de', 'en']) {
			const have = new Set([item.names[lang], ...syn[lang]].map((x) => x.toLowerCase()))
			if (!have.has(brand.toLowerCase())) syn[lang].push(brand)
		}
		brandCount++
	}
}

// names in further languages (English and German come from the .txt files)
const catByKey = new Map(categories.map((c) => [c.key, c]))
const i18nDir = join(root, 'catalog', 'i18n')
const languages = []
const incomplete = []
for (const file of readdirSync(i18nDir).filter((f) => f.endsWith('.json')).sort()) {
	const code = file.slice(0, -5)
	if (code === 'en' || code === 'de') { errors.push(`i18n/${file}: en/de names belong in the .txt files`); continue }
	const tr = JSON.parse(readFileSync(join(i18nDir, file), 'utf8'))
	for (const [kind, map] of [['categories', catByKey], ['items', byKey]]) {
		for (const [key, name] of Object.entries(tr[kind] ?? {})) {
			const entry = map.get(key)
			if (!entry) { errors.push(`i18n/${file}: unknown ${kind} key ${key}`); continue }
			if (typeof name !== 'string' || !name.trim()) { errors.push(`i18n/${file}: empty name for ${key}`); continue }
			entry.names[code] = name.trim()
		}
		const missing = [...map.values()].filter((e) => !e.names[code]).length
		if (missing) incomplete.push(`${code}: ${missing} ${kind} without a name (English is shown)`)
	}
	languages.push(code)
}
if (incomplete.length) console.warn(incomplete.join('\n'))

if (errors.length) {
	console.error(errors.join('\n'))
	process.exit(1)
}

const body = { categories, items }
const version = createHash('sha1').update(JSON.stringify(body)).digest('hex').slice(0, 12)
const out = join(root, 'lib', 'Catalog', 'catalog.json')
mkdirSync(dirname(out), { recursive: true })
writeFileSync(out, JSON.stringify({ version, ...body }, null, '\t') + '\n')
console.log(`catalog.json: ${categories.length} categories, ${items.length} items, ${brandCount} brand names, ${2 + languages.length} languages, version ${version}`)
