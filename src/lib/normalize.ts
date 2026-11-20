/**
 * Mirrors lib/Service/NameNormalizer.php — keep both in sync (shared test fixtures).
 */
const FOLD: Record<string, string> = {
	ä: 'a', ö: 'o', ü: 'u', ß: 'ss', à: 'a', á: 'a', â: 'a', ã: 'a',
	å: 'a', æ: 'ae', ç: 'c', è: 'e', é: 'e', ê: 'e', ë: 'e', ì: 'i',
	í: 'i', î: 'i', ï: 'i', ñ: 'n', ò: 'o', ó: 'o', ô: 'o', õ: 'o',
	ø: 'o', œ: 'oe', ù: 'u', ú: 'u', û: 'u', ÿ: 'y',
}
const FOLD_RE = new RegExp('[' + Object.keys(FOLD).join('') + ']', 'g')

export function normalize(name: string): string {
	return name.trim().toLowerCase()
		.replace(FOLD_RE, (c) => FOLD[c])
		.replace(/[^\p{L}\p{N}]+/gu, ' ')
		.replace(/\s+/g, ' ')
		.trim()
}

/** Singular/plural variants for lenient lookups. */
export function variants(norm: string): string[] {
	const v = [norm]
	for (const suffix of ['en', 'es', 'n', 's', 'e']) {
		if (norm.length > suffix.length + 2 && norm.endsWith(suffix)) {
			v.push(norm.slice(0, -suffix.length))
		}
	}
	for (const suffix of ['s', 'n', 'en', 'e']) {
		v.push(norm + suffix)
	}
	return [...new Set(v)]
}
