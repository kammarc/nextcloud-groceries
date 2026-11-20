import { onBeforeUnmount } from 'vue'

/**
 * Runs `task` every `intervalMs` while the page is visible, immediately when it becomes
 * visible again or the device comes back online, and never concurrently.
 * (Live updates without a push server: polls are a single cheap revision query.)
 */
export function usePolling(task: () => Promise<unknown>, intervalMs: number) {
	let timer: ReturnType<typeof setTimeout> | null = null
	let running = false
	let active = false

	const schedule = () => {
		if (timer) clearTimeout(timer)
		timer = active && document.visibilityState === 'visible' ? setTimeout(tick, intervalMs) : null
	}

	async function tick() {
		if (!active || running) return
		running = true
		try {
			await task()
		} catch {
			// offline or server error: try again next tick
		} finally {
			running = false
			schedule()
		}
	}

	const onWake = () => {
		if (document.visibilityState === 'visible') tick()
		else schedule()
	}

	function start() {
		if (active) return
		active = true
		document.addEventListener('visibilitychange', onWake)
		window.addEventListener('online', onWake)
		window.addEventListener('focus', onWake)
		tick()
	}

	function stop() {
		active = false
		if (timer) clearTimeout(timer)
		timer = null
		document.removeEventListener('visibilitychange', onWake)
		window.removeEventListener('online', onWake)
		window.removeEventListener('focus', onWake)
	}

	onBeforeUnmount(stop)
	return { start, stop, tick }
}
