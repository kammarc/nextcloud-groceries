import { ref } from 'vue'
import { api } from '../api/client'
import { useData } from '../stores/data'
import { t } from '../i18n'
import { computeStats, reviewYear, yearRange, type Period, type StatEvent, type Stats } from './stats'

/**
 * Yearly review ("Reel"): from 15 December the app announces the review of the year once per device;
 * the review itself is a short story of slides built from the purchase logs of all the user's lists.
 */
export interface ReviewData {
	/** small line above the title, big title (the year, or the period of a statistics tab) */
	kicker: string
	title: string
	/** last line of the story, and the text when nothing was bought */
	outro: string
	empty: string
	stats: Stats
	people: Record<string, string>
	/** the list with the most purchases */
	topList: { name: string, icon: string | null, bought: number } | null
	listCount: number
}

/** year announced by the card, null = no card */
export const reviewPrompt = ref<number | null>(null)
/** data of the open story, null = closed */
export const reviewReel = ref<ReviewData | null>(null)
export const reviewLoading = ref(false)

const SEEN_KEY = 'groceries:review-seen'

function seenYear(): number {
	try {
		return Number(localStorage.getItem(SEEN_KEY) ?? 0)
	} catch {
		return 0
	}
}

/** Shows the announcement when it is review time and this device has not watched that year yet. */
export function checkYearReview(now = new Date()) {
	const year = reviewYear(now)
	if (year !== null && seenYear() < year && reviewPrompt.value === null && reviewReel.value === null) {
		reviewPrompt.value = year
	}
}

export function dismissReviewPrompt() {
	reviewPrompt.value = null
}

function markSeen(year: number) {
	try {
		if (seenYear() < year) localStorage.setItem(SEEN_KEY, String(year))
	} catch {
		// ignore
	}
}

/** Collects the purchase logs of all lists and opens the story. */
export async function openYearReview(year: number) {
	const data = useData()
	reviewPrompt.value = null
	reviewLoading.value = true
	try {
		const all: StatEvent[] = []
		const people: Record<string, string> = {}
		let topList: ReviewData['topList'] = null
		const { from, to } = yearRange(year)
		for (const list of data.lists) {
			try {
				const events = await api<StatEvent[]>('GET', `/lists/${list.id}/events`)
				all.push(...events)
				const bought = events.filter((e) => e.type === 'check' && e.at >= from && e.at < to).length
				if (bought > 0 && (!topList || bought > topList.bought)) topList = { name: list.name, icon: list.icon, bought }
				if (!data.state(list.id).meta) await data.syncList(list.id).catch(() => {})
				Object.assign(people, data.state(list.id).meta?.people ?? {})
			} catch {
				// a list that cannot be read (offline, removed) is just left out
			}
		}
		reviewReel.value = {
			kicker: t('Your shopping year'),
			title: String(year),
			outro: t('See you next year!'),
			empty: t('No purchases recorded in {year} yet.', { year }),
			stats: computeStats(all, from, to),
			people,
			topList,
			listCount: data.lists.length,
		}
		markSeen(year)
	} finally {
		reviewLoading.value = false
	}
}

/** The same story for one list and one tab of the statistics screen (week, month, year, all time). */
export function openStatsStory(period: Period, listName: string, stats: Stats, people: Record<string, string>) {
	const titles: Record<Period, string> = {
		week: t('The last 7 days'),
		month: t('The last 30 days'),
		year: t('The last 12 months'),
		all: t('All time'),
	}
	reviewReel.value = {
		kicker: listName,
		title: titles[period],
		outro: t('Happy shopping!'),
		empty: t('No purchases in this period yet.'),
		stats,
		people,
		topList: null,
		listCount: 1,
	}
}

export function closeYearReview() {
	reviewReel.value = null
}
