/**
 * Shopping statistics, computed in the app from the purchase log (server: EventService).
 * "add"   = somebody put an item on the list
 * "check" = somebody ticked it off while shopping (= bought)
 * A shopping trip = one person ticking off at least one item on a calendar day.
 */
export interface StatEvent {
	id: string
	user: string
	type: 'add' | 'check'
	normName: string
	name: string
	catalogKey: string | null
	category: string
	icon: string | null
	quantity: string | null
	/** unix seconds */
	at: number
}

export type Period = 'week' | 'month' | 'year' | 'all'

export interface Ranked {
	normName: string
	name: string
	icon: string | null
	catalogKey: string | null
	category: string
	count: number
}

export interface UserStat {
	user: string
	added: number
	bought: number
	trips: number
	/** the item this person bought most often */
	favorite: Ranked | null
}

export interface Stats {
	totalBought: number
	totalAdded: number
	/** household trips: calendar days with at least one purchase */
	shoppingDays: number
	/** per-person trips summed up */
	trips: number
	perTrip: number
	/** items bought for the very first time in this period */
	newItems: number
	topItems: Ranked[]
	users: UserStat[]
	categories: Array<{ key: string, count: number }>
	/** purchases per weekday, Monday first */
	weekdays: number[]
	busiestDay: { date: string, count: number } | null
	topList?: string
}

const DAY = 86400

/** [from, to) in unix seconds; from = null means "since the beginning". */
export function periodRange(period: Period, now = Date.now()): { from: number | null, to: number } {
	const to = Math.floor(now / 1000) + 1
	const days = { week: 7, month: 30, year: 365, all: 0 }[period]
	return { from: days ? to - days * DAY : null, to }
}

/** Calendar year [1 Jan, 1 Jan next year) in local time. */
export function yearRange(year: number): { from: number, to: number } {
	return { from: Math.floor(new Date(year, 0, 1).getTime() / 1000), to: Math.floor(new Date(year + 1, 0, 1).getTime() / 1000) }
}

/** Local calendar date YYYY-MM-DD. */
export function localDate(at: number): string {
	const d = new Date(at * 1000)
	return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

function rank(events: StatEvent[], limit: number): Ranked[] {
	const map = new Map<string, Ranked>()
	for (const e of events) {
		const r = map.get(e.normName)
		if (r) {
			r.count++
			// show the most recent spelling and icon
			r.name = e.name
			if (e.icon) r.icon = e.icon
		} else {
			map.set(e.normName, { normName: e.normName, name: e.name, icon: e.icon, catalogKey: e.catalogKey, category: e.category, count: 1 })
		}
	}
	return [...map.values()].sort((a, b) => b.count - a.count || a.name.localeCompare(b.name)).slice(0, limit)
}

/**
 * @param all  every event of the list(s) (needed to tell which items were bought for the first time)
 * @param from start of the period (unix seconds, inclusive) or null for everything
 * @param to   end of the period (exclusive)
 */
export function computeStats(all: StatEvent[], from: number | null, to: number): Stats {
	const inRange = all.filter((e) => (from === null || e.at >= from) && e.at < to)
	const checks = inRange.filter((e) => e.type === 'check')
	const adds = inRange.filter((e) => e.type === 'add')

	const firstBought = new Map<string, number>()
	for (const e of all) {
		if (e.type !== 'check') continue
		const prev = firstBought.get(e.normName)
		if (prev === undefined || e.at < prev) firstBought.set(e.normName, e.at)
	}
	let newItems = 0
	for (const at of firstBought.values()) if ((from === null || at >= from) && at < to) newItems++

	const tripKeys = new Set(checks.map((e) => `${e.user}|${localDate(e.at)}`))
	const days = new Map<string, number>()
	for (const e of checks) days.set(localDate(e.at), (days.get(localDate(e.at)) ?? 0) + 1)
	let busiestDay: Stats['busiestDay'] = null
	for (const [date, count] of days) if (!busiestDay || count > busiestDay.count) busiestDay = { date, count }

	const weekdays = [0, 0, 0, 0, 0, 0, 0]
	for (const e of checks) weekdays[(new Date(e.at * 1000).getDay() + 6) % 7]++

	const cats = new Map<string, number>()
	for (const e of checks) cats.set(e.category, (cats.get(e.category) ?? 0) + 1)

	const users = new Map<string, UserStat>()
	const user = (u: string) => {
		if (!users.has(u)) users.set(u, { user: u, added: 0, bought: 0, trips: 0, favorite: null })
		return users.get(u)!
	}
	for (const e of adds) user(e.user).added++
	for (const e of checks) user(e.user).bought++
	for (const key of tripKeys) user(key.split('|')[0]).trips++
	for (const u of users.values()) u.favorite = rank(checks.filter((e) => e.user === u.user), 1)[0] ?? null

	return {
		totalBought: checks.length,
		totalAdded: adds.length,
		shoppingDays: days.size,
		trips: tripKeys.size,
		perTrip: tripKeys.size ? Math.round((checks.length / tripKeys.size) * 10) / 10 : 0,
		newItems,
		topItems: rank(checks, 10),
		users: [...users.values()].sort((a, b) => b.bought - a.bought || b.added - a.added),
		categories: [...cats.entries()].map(([key, count]) => ({ key, count })).sort((a, b) => b.count - a.count),
		weekdays,
		busiestDay,
	}
}

/** Export file format (also what import accepts). */
export interface StatsExport {
	format: 'groceries-events'
	version: 1
	list: string
	exportedAt: string
	events: StatEvent[]
}

export function buildExport(listName: string, events: StatEvent[]): StatsExport {
	return { format: 'groceries-events', version: 1, list: listName, exportedAt: new Date().toISOString(), events }
}

/** Accepts an export file (or a plain array of events); throws on anything else. */
export function parseImport(text: string): StatEvent[] {
	const data = JSON.parse(text)
	const events = Array.isArray(data) ? data : data?.format === 'groceries-events' ? data.events : null
	if (!Array.isArray(events)) throw new Error('not a Groceries statistics export')
	return events.filter((e) => e && (e.type === 'add' || e.type === 'check') && typeof e.id === 'string' && typeof e.at === 'number')
}

// ---- yearly review ----------------------------------------------------------------------------

/** The review of a year is offered from 15 December until the end of January. */
export function reviewYear(now = new Date()): number | null {
	if (now.getMonth() === 11 && now.getDate() >= 15) return now.getFullYear()
	if (now.getMonth() === 0) return now.getFullYear() - 1
	return null
}
