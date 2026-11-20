/**
 * Mirrors lib/Service/QuantityMerger.php — keep both in sync (shared test fixtures).
 */
import { canonicalUnit } from './parser'

const CONVERT: Record<string, [string, number]> = {
	mg: ['g', 0.001], g: ['g', 1], kg: ['g', 1000],
	ml: ['ml', 1], cl: ['ml', 10], dl: ['ml', 100], l: ['ml', 1000],
}

function toNumber(n: string): number {
	n = n.trim().replace(',', '.')
	let m = n.match(/^(\d+)\s+(\d+)\/(\d+)$/)
	if (m) return Number(m[1]) + (Number(m[3]) === 0 ? 0 : Number(m[2]) / Number(m[3]))
	m = n.match(/^(\d+)\/(\d+)$/)
	if (m) return Number(m[2]) === 0 ? 0 : Number(m[1]) / Number(m[2])
	return Number(n)
}

export function parseQuantity(q: string): [number, string] | null {
	const m = q.trim().match(/^(\d+\/\d+|\d+(?:[.,]\d+)?(?:\s+\d+\/\d+)?)\s*(.*)$/u)
	if (!m) return null
	const unitText = m[2].trim()
	return [toNumber(m[1]), unitText === '' ? '' : (canonicalUnit(unitText) ?? unitText)]
}

export function formatNumber(n: number): string {
	return (Math.round(n * 100) / 100).toFixed(2).replace(/0+$/, '').replace(/\.$/, '')
}

function formatBase(value: number, base: string): string {
	if (base === 'g') return value >= 1000 ? `${formatNumber(value / 1000)} kg` : `${formatNumber(value)} g`
	return value >= 1000 ? `${formatNumber(value / 1000)} l` : `${formatNumber(value)} ml`
}

export function mergeQuantity(a: string | null | undefined, b: string | null | undefined): string | null {
	const qa = a && a.trim() !== '' ? a.trim() : null
	const qb = b && b.trim() !== '' ? b.trim() : null
	if (qa === null || qb === null) return qa ?? qb
	const pa = parseQuantity(qa)
	const pb = parseQuantity(qb)
	if (pa && pb) {
		const [na, ua] = pa
		const [nb, ub] = pb
		if (ua === ub) return formatNumber(na + nb) + (ua !== '' ? ` ${ua}` : '')
		if (CONVERT[ua] && CONVERT[ub] && CONVERT[ua][0] === CONVERT[ub][0]) {
			return formatBase(na * CONVERT[ua][1] + nb * CONVERT[ub][1], CONVERT[ua][0])
		}
	}
	return `${qa} + ${qb}`
}
