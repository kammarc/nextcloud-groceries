/**
 * Mirrors lib/Service/IngredientParser.php — keep both in sync (shared test fixtures).
 * Splits "200 g Mehl (Type 405)", "Milch 2l", "3x Eier" into quantity, name and note.
 */
export const UNITS: Record<string, string[]> = {
	mg: ['mg', 'milligramm', 'milligram', 'milligrams'],
	g: ['g', 'gr', 'gramm', 'gram', 'grams', 'grammes'],
	kg: ['kg', 'kilo', 'kilos', 'kilogramm', 'kilogram', 'kilograms'],
	ml: ['ml', 'milliliter', 'millilitre', 'milliliters', 'millilitres'],
	cl: ['cl', 'zentiliter', 'centiliter'],
	dl: ['dl', 'deziliter', 'deciliter'],
	l: ['l', 'liter', 'litre', 'liters', 'litres', 'ltr'],
	oz: ['oz', 'ounce', 'ounces'],
	lb: ['lb', 'lbs', 'pound', 'pounds'],
	EL: ['el', 'essloffel', 'esslöffel', 'tbsp', 'tablespoon', 'tablespoons', 'tbs'],
	TL: ['tl', 'teeloffel', 'teelöffel', 'tsp', 'teaspoon', 'teaspoons'],
	cup: ['cup', 'cups', 'tasse', 'tassen'],
	Prise: ['prise', 'prisen', 'pinch', 'pinches'],
	Msp: ['msp', 'messerspitze', 'messerspitzen'],
	Bund: ['bund', 'bunch', 'bunches', 'bd'],
	Stk: ['stk', 'stück', 'stuck', 'st', 'pcs', 'pc', 'piece', 'pieces', 'stuk'],
	Dose: ['dose', 'dosen', 'can', 'cans', 'tin', 'tins', 'büchse', 'büchsen'],
	Pck: ['pck', 'pkg', 'packung', 'packungen', 'pack', 'packs', 'packet', 'packets', 'päckchen', 'pk', 'pckg', 'paket', 'pakete'],
	Becher: ['becher'],
	Glas: ['glas', 'gläser', 'jar', 'jars'],
	Flasche: ['flasche', 'flaschen', 'bottle', 'bottles', 'fl'],
	Zehe: ['zehe', 'zehen', 'clove', 'cloves'],
	Scheibe: ['scheibe', 'scheiben', 'slice', 'slices'],
	Zweig: ['zweig', 'zweige', 'sprig', 'sprigs', 'stiel', 'stiele'],
	Blatt: ['blatt', 'blätter', 'leaf', 'leaves'],
	Handvoll: ['handvoll', 'handful', 'handfuls'],
	Beutel: ['beutel', 'bag', 'bags', 'tüte', 'tüten'],
	Stange: ['stange', 'stangen', 'stick', 'sticks', 'stalk', 'stalks'],
	Würfel: ['würfel', 'cube', 'cubes'],
	Kopf: ['kopf', 'köpfe', 'head', 'heads'],
	Knolle: ['knolle', 'knollen', 'bulb', 'bulbs'],
	Schale: ['schale', 'schalen', 'tray', 'punnet', 'punnets'],
	Netz: ['netz', 'netze', 'net'],
	Rolle: ['rolle', 'rollen', 'roll', 'rolls'],
	Tafel: ['tafel', 'tafeln', 'bar', 'bars'],
	Kiste: ['kiste', 'kisten', 'crate', 'crates', 'harass'],
	Tube: ['tube', 'tuben', 'tubes'],
}

const FRACTIONS: Record<string, string> = { '½': '1/2', '¼': '1/4', '¾': '3/4', '⅓': '1/3', '⅔': '2/3', '⅛': '1/8' }
const VAGUE = ['etwas', 'einige', 'ein paar', 'wenig', 'some', 'a few', 'a little', 'a bit of', 'nach belieben', 'n. b.', 'nb', 'to taste', 'ca.', 'ca', 'etwa', 'about', 'approx.', 'approx']
const UNIT_ONLY = ['Prise', 'Msp', 'Bund', 'Handvoll', 'Pck', 'Dose', 'Glas', 'Becher', 'Flasche', 'Zehe', 'Zweig', 'Kopf', 'Knolle', 'Netz', 'Schale', 'Tafel', 'Kiste']
/** Number at the very start: 1/2, 1, 1.5, 1,5, 1 1/2, 2-3 (fraction alternative first) */
const NUM = '(?:\\d+\\/\\d+|\\d+(?:[.,]\\d+)?(?:\\s+\\d+\\/\\d+)?)(?:\\s*[-–]\\s*(?:\\d+(?:[.,]\\d+)?|\\d+\\/\\d+))?'

const unitLookup = new Map<string, string>()
for (const [canonical, spellings] of Object.entries(UNITS)) {
	for (const sp of spellings) unitLookup.set(sp, canonical)
}

const escapeRe = (s: string) => s.replace(/[.*+?^${}()|[\]\\/]/g, '\\$&')

export interface ParsedLine {
	quantity: string | null
	name: string
	note: string | null
}

export function canonicalUnit(word: string): string | null {
	const w = word.toLowerCase().replace(/\.+$/, '')
	return unitLookup.get(w) ?? null
}

function takeUnit(rest: string): [string | null, string] {
	const m = rest.match(/^(\p{L}+\.?)(?:\s+|$)(.*)$/u)
	if (m) {
		const canonical = canonicalUnit(m[1])
		if (canonical !== null) return [canonical, m[2]]
	}
	return [null, rest]
}

const cleanNumber = (n: string) => n.trim().replace(/\s*[-–]\s*/g, '-').replace(/\s+/g, ' ')
const isDigit = (c: string | undefined) => c !== undefined && c >= '0' && c <= '9'

export function parseLine(line: string): ParsedLine {
	let s = line.trim().replace(/[½¼¾⅓⅔⅛]/g, (c) => FRACTIONS[c])
	s = s.replace(/(\d)(\d\/\d)/g, '$1 $2')
	s = s.replace(/^[-*•·\s]+/u, '')
	s = s.replace(/\s+/gu, ' ').trim()

	const notes: string[] = []
	if (/\(([^)]*)\)/.test(s)) {
		for (const m of s.matchAll(/\(([^)]*)\)/g)) {
			if (m[1].trim() !== '') notes.push(m[1].trim())
		}
		s = s.replace(/\s*\([^)]*\)\s*/g, ' ').trim()
	}
	// a comma that is not a decimal separator ("1,5 l") starts a note (no lookbehind: old Safari)
	for (let i = 0; i < s.length; i++) {
		if (s[i] === ',' && !(isDigit(s[i - 1]) && isDigit(s[i + 1]))) {
			const head = s.slice(0, i).trim()
			const rest = s.slice(i + 1).trim()
			if (head !== '' && rest !== '') {
				notes.push(rest)
				s = head
			}
			break
		}
	}

	let quantity: string | null = null
	let vaguePrefix: string | null = null
	for (const v of VAGUE) {
		if (new RegExp('^' + escapeRe(v) + '(?=\\s|$)', 'iu').test(s)) {
			vaguePrefix = v
			s = s.slice(v.length).trim()
			break
		}
	}

	let m: RegExpMatchArray | null
	if ((m = s.match(/^(\d+)\s*[x×]\s+(.+)$/iu)) || (m = s.match(/^[x×]\s*(\d+)\s+(.+)$/iu))) {
		quantity = m[1]
		s = m[2]
	} else if ((m = s.match(new RegExp('^(' + NUM + ')(\\s*)(.*)$', 'u')))
		// a number glued to letters is only a quantity if the letters are a unit: "200g" yes, "7up" no
		&& (m[2] !== '' || m[3] === '' || takeUnit(m[3])[0] !== null)) {
		const num = cleanNumber(m[1])
		let [unit, rest] = takeUnit(m[3])
		rest = rest.replace(/^(of|von|vom)\s+/iu, '')
		if (rest.trim() === '') {
			quantity = null
			s = m[0]
		} else {
			quantity = unit !== null ? `${num} ${unit}` : num
			s = rest
		}
	} else {
		let [unit, rest] = takeUnit(s)
		rest = rest.replace(/^(of|von)\s+/iu, '')
		if (unit !== null && rest.trim() !== '' && UNIT_ONLY.includes(unit)) {
			quantity = `1 ${unit}`
			s = rest
		}
	}

	if (quantity === null) {
		if ((m = s.match(/^(.+?)\s+[x×]\s*(\d+)$/iu)) || (m = s.match(/^(.+?)\s+(\d+)\s*[x×]$/iu))) {
			quantity = m[2]
			s = m[1]
		} else if ((m = s.match(new RegExp('^(.+?)\\s+(' + NUM + ')\\s*([\\p{L}.]*)$', 'u')))) {
			const unitPart = m[3]
			const canonical = unitPart === '' ? null : canonicalUnit(unitPart)
			if (unitPart === '' || canonical !== null) {
				quantity = cleanNumber(m[2]) + (canonical !== null ? ` ${canonical}` : '')
				s = m[1]
			}
		}
	}

	if (vaguePrefix !== null && quantity === null) quantity = vaguePrefix

	let name = s.replace(/^[\s\-–:;.]+|[\s\-–:;.]+$/gu, '').trim()
	if (name === '') name = line.trim()
	return { quantity, name, note: notes.length ? notes.join(', ') : null }
}
