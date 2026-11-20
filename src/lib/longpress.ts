/**
 * Long-press helper that works on iOS Safari too (which does not fire `contextmenu` on long press).
 * Returns handlers to spread on the element; `consumeClick()` tells a click handler to ignore
 * the click that ends a long press.
 */
export function useLongPress(onLongPress: () => void, delay = 480) {
	let timer: ReturnType<typeof setTimeout> | null = null
	let fired = false
	let startX = 0
	let startY = 0

	const cancel = () => {
		if (timer) clearTimeout(timer)
		timer = null
	}

	return {
		handlers: {
			onPointerdown(e: PointerEvent) {
				fired = false
				startX = e.clientX
				startY = e.clientY
				cancel()
				timer = setTimeout(() => {
					fired = true
					try { navigator.vibrate?.(15) } catch { /* ignore */ }
					onLongPress()
				}, delay)
			},
			onPointermove(e: PointerEvent) {
				if (Math.abs(e.clientX - startX) > 8 || Math.abs(e.clientY - startY) > 8) cancel()
			},
			onPointerup: cancel,
			onPointercancel: cancel,
			onPointerleave: cancel,
			onContextmenu(e: Event) {
				e.preventDefault()
			},
		},
		consumeClick(): boolean {
			if (fired) {
				fired = false
				return true
			}
			return false
		},
	}
}
