/**
 * The list that was open when the app went away (per device). iOS often kills a backgrounded
 * home-screen app and relaunches it at the manifest's start_url, which would land on the
 * overview; the router uses this to reopen the list instead.
 */
const KEY = 'groceries:lastList'

export function getLastList(): number | null {
	try {
		const id = Number(localStorage.getItem(KEY))
		return Number.isInteger(id) && id > 0 ? id : null
	} catch {
		return null
	}
}

export function setLastList(id: number | null) {
	try {
		if (id) localStorage.setItem(KEY, String(id))
		else localStorage.removeItem(KEY)
	} catch {
		// private mode: no restore
	}
}
