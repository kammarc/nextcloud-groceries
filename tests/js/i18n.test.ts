import { readdirSync, readFileSync, statSync } from 'node:fs'
import { join } from 'node:path'
import { describe, expect, it } from 'vitest'
import { dictionary } from '../../src/i18n'
import { LANGUAGES } from '../../src/lib/languages'

/** Every string passed to t('…') or n(…) in the app must be translated in every language. */
function sourceFiles(dir: string): string[] {
	return readdirSync(dir).flatMap((f) => {
		const p = join(dir, f)
		return statSync(p).isDirectory() ? sourceFiles(p) : /\.(ts|vue)$/.test(f) ? [p] : []
	})
}

function usedKeys(): Set<string> {
	const keys = new Set<string>()
	for (const file of sourceFiles(join(__dirname, '../../src'))) {
		const src = readFileSync(file, 'utf8')
		for (const m of src.matchAll(/\bt\(\s*'((?:[^'\\]|\\.)*)'/g)) keys.add(m[1].replace(/\\'/g, "'"))
		for (const m of src.matchAll(/\bt\(\s*"((?:[^"\\]|\\.)*)"/g)) keys.add(m[1].replace(/\\"/g, '"'))
		for (const m of src.matchAll(/\bn\([^,]+,\s*'([^']*)',\s*'([^']*)'\)/g)) {
			keys.add(m[1])
			keys.add(m[2])
		}
	}
	return keys
}

const placeholders = (s: string) => [...s.matchAll(/\{(\w+)\}/g)].map((m) => m[1]).sort()
const used = usedKeys()

describe.each(LANGUAGES.filter((l) => l.code !== 'en').map((l) => l.code))('%s translations', (code) => {
	const dict = dictionary(code)

	it('exist for every UI string', () => {
		const missing = [...used].filter((k) => !dict[k]).sort()
		expect(missing).toEqual([])
	})

	it('keep the {placeholders}', () => {
		const wrong = Object.entries(dict)
			.filter(([k, v]) => used.has(k) && placeholders(k).join() !== placeholders(v).join())
			.map(([k]) => k)
		expect(wrong).toEqual([])
	})

	it('have no outdated strings', () => {
		// "<plural>|<category>" keys are extra plural forms (see n() in i18n.ts)
		const unused = Object.keys(dict).filter((k) => !used.has(k) && !used.has(k.split('|')[0])).sort()
		expect(unused).toEqual([])
	})
})
