#!/usr/bin/env node
/**
 * Raises the version in appinfo/info.xml, package.json and package-lock.json.
 * Run with "minor" by the Version workflow (.github/workflows/version.yml) on every push to main;
 * can also be run by hand:
 *   node scripts/bump-version.mjs          patch: 0.2.6 -> 0.2.7
 *   node scripts/bump-version.mjs minor    0.2.6 -> 0.3.0
 *   node scripts/bump-version.mjs major    0.2.6 -> 1.0.0
 * Prints the new version.
 */
import { readFileSync, writeFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const root = join(dirname(fileURLToPath(import.meta.url)), '..')
const part = process.argv[2] ?? 'patch'
if (!['patch', 'minor', 'major'].includes(part)) {
	console.error(`unknown part "${part}" (patch, minor or major)`)
	process.exit(1)
}

const read = (f) => readFileSync(join(root, f), 'utf8')
const parse = (v) => {
	const m = /^(\d+)\.(\d+)\.(\d+)$/.exec(v ?? '')
	if (!m) throw new Error(`not a version: ${v}`)
	return m.slice(1).map(Number)
}
const cmp = (a, b) => a[0] - b[0] || a[1] - b[1] || a[2] - b[2]

const infoXml = read('appinfo/info.xml')
const pkg = read('package.json')
const infoVersion = parse(/<version>([^<]+)<\/version>/.exec(infoXml)?.[1])
const pkgVersion = parse(JSON.parse(pkg).version)

// continue from the higher one if they ever got out of step
let [major, minor, patch] = cmp(infoVersion, pkgVersion) >= 0 ? infoVersion : pkgVersion
if (part === 'major') [major, minor, patch] = [major + 1, 0, 0]
else if (part === 'minor') [minor, patch] = [minor + 1, 0]
else patch++
const next = `${major}.${minor}.${patch}`

// plain text replacements keep the files' formatting (tabs, line endings) as they are
writeFileSync(join(root, 'appinfo/info.xml'), infoXml.replace(/<version>[^<]+<\/version>/, `<version>${next}</version>`))
writeFileSync(join(root, 'package.json'), pkg.replace(/("version":\s*")[^"]+(")/, `$1${next}$2`))
// package-lock.json: the version of the project itself (top level and packages[""]), not of dependencies
const lock = read('package-lock.json')
const lockJson = JSON.parse(lock)
const name = lockJson.name
let out = lock.replace(new RegExp(`^(\\t?\\s*"name":\\s*"${name}",\\s*\\n\\s*"version":\\s*")[^"]+(")`, 'm'), `$1${next}$2`)
out = out.replace(/("packages":\s*\{\s*\n\s*"":\s*\{\s*\n\s*"name":\s*"[^"]+",\s*\n\s*"version":\s*")[^"]+(")/, `$1${next}$2`)
const check = JSON.parse(out)
if (check.version !== next || check.packages?.['']?.version !== next) throw new Error('could not update package-lock.json')
writeFileSync(join(root, 'package-lock.json'), out)

console.log(next)
