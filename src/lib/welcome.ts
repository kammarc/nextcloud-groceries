import { ref } from 'vue'

/** Short introduction, shown once per device on the first start; reopened from the app settings. */
const KEY = 'groceries:welcome-seen'

export const welcomeOpen = ref(false)

function seen(): boolean {
	try {
		return localStorage.getItem(KEY) === '1'
	} catch {
		return true
	}
}

/** Opens the introduction unless this device has seen it; true if it was opened. */
export function showWelcomeOnce(): boolean {
	if (seen() || welcomeOpen.value) return false
	welcomeOpen.value = true
	return true
}

export function openWelcome() {
	welcomeOpen.value = true
}

export function closeWelcome() {
	welcomeOpen.value = false
	try {
		localStorage.setItem(KEY, '1')
	} catch {
		// private mode: shown again next time
	}
}
