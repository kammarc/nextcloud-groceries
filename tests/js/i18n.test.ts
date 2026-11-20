import { readdirSync, readFileSync, statSync } from 'node:fs'
import { join } from 'node:path'
import { describe, expect, it } from 'vitest'
import { germanKeys } from '../../src/i18n'

/** Every string passed to t('…') in the app must have a German translation. */
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
		for (const m of src.matchAll(/\bn\([^,]+,\s*'([^']*)',\s*'([^']*)'\)/g)) {
			keys.add(m[1])
			keys.add(m[2])
		}
	}
	return keys
}

describe('German translations', () => {
	it('exist for every UI string', () => {
		const de = new Set(germanKeys())
		const missing = [...usedKeys()].filter((k) => !de.has(k)).sort()
		expect(missing).toEqual([])
	})
})
