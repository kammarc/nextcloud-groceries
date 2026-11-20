import { describe, expect, it } from 'vitest'
import { buildExport, computeStats, parseImport, reviewYear, yearRange, type StatEvent } from '../../src/lib/stats'

const at = (y: number, m: number, d: number, h = 10) => Math.floor(new Date(y, m - 1, d, h).getTime() / 1000)
let n = 0
const ev = (type: 'add' | 'check', user: string, name: string, time: number, category = 'produce'): StatEvent => ({
	id: `e${n++}`, user, type, normName: name.toLowerCase(), name, catalogKey: null, category, icon: null, quantity: null, at: time,
})

const events: StatEvent[] = [
	ev('add', 'anna', 'Milch', at(2026, 3, 1), 'dairy'),
	ev('add', 'anna', 'Brot', at(2026, 3, 1), 'bakery'),
	ev('add', 'ben', 'Bier', at(2026, 3, 1), 'drinks'),
	ev('check', 'ben', 'Milch', at(2026, 3, 2, 9), 'dairy'),
	ev('check', 'ben', 'Brot', at(2026, 3, 2, 9), 'bakery'),
	ev('check', 'ben', 'Bier', at(2026, 3, 2, 18), 'drinks'),
	ev('add', 'anna', 'Milch', at(2026, 3, 5), 'dairy'),
	ev('check', 'anna', 'Milch', at(2026, 3, 6), 'dairy'),
	ev('check', 'anna', 'Milch', at(2025, 12, 20), 'dairy'),
]

describe('computeStats', () => {
	const year = yearRange(2026)
	const s = computeStats(events, year.from, year.to)

	it('counts purchases, adds and the most bought items', () => {
		expect(s.totalBought).toBe(4)
		expect(s.totalAdded).toBe(4)
		expect(s.topItems[0]).toMatchObject({ name: 'Milch', count: 2 })
	})

	it('counts one trip per person and day', () => {
		// ben on 2 March (two purchases at different times), anna on 6 March
		expect(s.trips).toBe(2)
		expect(s.shoppingDays).toBe(2)
		expect(s.perTrip).toBe(2)
		const ben = s.users.find((u) => u.user === 'ben')!
		expect(ben).toMatchObject({ added: 1, bought: 3, trips: 1 })
		expect(s.users.find((u) => u.user === 'anna')).toMatchObject({ added: 3, bought: 1, trips: 1 })
	})

	it('knows which items were bought for the first time', () => {
		// Milch was already bought in December 2025, Brot and Bier are new in 2026
		expect(s.newItems).toBe(2)
	})

	it('computes categories, weekdays and the busiest day', () => {
		expect(s.categories[0]).toEqual({ key: 'dairy', count: 2 })
		expect(s.weekdays.reduce((a, b) => a + b, 0)).toBe(4)
		expect(s.busiestDay).toEqual({ date: '2026-03-02', count: 3 })
	})

	it('handles "all time" and empty periods', () => {
		expect(computeStats(events, null, at(2030, 1, 1)).totalBought).toBe(5)
		expect(computeStats(events, at(2027, 1, 1), at(2028, 1, 1))).toMatchObject({ totalBought: 0, trips: 0, perTrip: 0, busiestDay: null })
	})
})

describe('export / import', () => {
	it('round-trips through JSON and rejects foreign files', () => {
		const text = JSON.stringify(buildExport('Wocheneinkauf', events))
		expect(parseImport(text)).toHaveLength(events.length)
		expect(() => parseImport('{"hello":1}')).toThrow()
	})
})

describe('yearly review window', () => {
	it('opens on 15 December and stays until the end of January', () => {
		expect(reviewYear(new Date(2026, 11, 14))).toBeNull()
		expect(reviewYear(new Date(2026, 11, 15))).toBe(2026)
		expect(reviewYear(new Date(2027, 0, 20))).toBe(2026)
		expect(reviewYear(new Date(2027, 1, 1))).toBeNull()
	})
})
