import { isPlatform } from '@ionic/vue'
import { ref } from 'vue'
import { config } from '../config'

export const isIos = () => isPlatform('ios')

export function isStandalone(): boolean {
	return window.matchMedia?.('(display-mode: standalone)').matches || (navigator as any).standalone === true
}

/** A browser on a computer (not a phone or tablet, not the installed app). */
export const isDesktopBrowser = () => !isStandalone() && isPlatform('desktop')

/** The app's address as the user reaches it (also behind proxies), e.g. for the phone QR code. */
export const appAddress = () => new URL(config.appUrl, window.location.origin).href

/** Nextcloud's start page on this server. */
export const nextcloudHome = () => new URL(new URL(config.serverUrl, window.location.origin).pathname, window.location.origin).href

/** Short tick on Android (iOS Safari has no vibration API). */
export function haptic(ms = 10) {
	try {
		navigator.vibrate?.(ms)
	} catch {
		// unsupported
	}
}

// ---- install prompt (Android/desktop Chrome) ------------------------------------------------
export const installPrompt = ref<any>(null)
window.addEventListener('beforeinstallprompt', (e) => {
	e.preventDefault()
	installPrompt.value = e
})
window.addEventListener('appinstalled', () => { installPrompt.value = null })

export async function promptInstall() {
	const p = installPrompt.value
	if (!p) return
	p.prompt()
	await p.userChoice.catch(() => null)
	installPrompt.value = null
}

// ---- keep screen awake while a list is open -------------------------------------------------
const WAKE_KEY = 'groceries:wakelock'
let sentinel: any = null
let wanted = false

function readFlag(): boolean {
	try {
		return localStorage.getItem(WAKE_KEY) === '1'
	} catch {
		return false
	}
}

export const wakeLockEnabled = ref(readFlag())

export function setWakeLockEnabled(on: boolean) {
	wakeLockEnabled.value = on
	try {
		localStorage.setItem(WAKE_KEY, on ? '1' : '0')
	} catch {
		// ignore
	}
	if (wanted) {
		if (on) acquireWakeLock()
		else releaseWakeLock(true)
	}
}

export async function acquireWakeLock() {
	wanted = true
	if (!wakeLockEnabled.value || sentinel || !('wakeLock' in navigator)) return
	try {
		sentinel = await (navigator as any).wakeLock.request('screen')
		sentinel.addEventListener('release', () => { sentinel = null })
	} catch {
		sentinel = null
	}
}

/** @param keepWanted release now but re-acquire when the setting is switched back on */
export async function releaseWakeLock(keepWanted = false) {
	wanted = keepWanted
	try {
		await sentinel?.release()
	} catch {
		// ignore
	}
	sentinel = null
}

document.addEventListener('visibilitychange', () => {
	if (document.visibilityState === 'visible' && wanted) acquireWakeLock()
})
