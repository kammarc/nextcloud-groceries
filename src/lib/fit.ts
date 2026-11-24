import { watch, type Directive } from 'vue'
import { ITEM_FONT_FAMILY, itemFont } from './theme'

/**
 * v-fit-text: shrinks a tile label until it fits its box. The box (the element) has a fixed
 * height (two lines at the base size) and holds one inner element with the text; when that
 * text needs more than two lines, e.g. long German compounds like "Spülmaschinentabs", the
 * font gets smaller instead of the icon, so all icons and labels in a grid line up.
 */
const MIN_PX = 8
const STEP_PX = 0.5

function fit(el: HTMLElement) {
	const text = el.firstElementChild as HTMLElement | null
	if (!text || !el.isConnected) return
	text.style.fontSize = ''
	let size = parseFloat(getComputedStyle(text).fontSize) || 13
	const overflows = () => text.scrollHeight > el.clientHeight + 0.5 || text.scrollWidth > el.clientWidth + 0.5
	while (size > MIN_PX && overflows()) {
		size -= STEP_PX
		text.style.fontSize = `${size}px`
	}
}

// one observer for all labels: refit when a tile changes width (rotation, window resize)
const widths = new WeakMap<Element, number>()
const observer = typeof ResizeObserver !== 'undefined'
	? new ResizeObserver((entries) => {
		for (const e of entries) {
			const w = Math.round(e.contentRect.width)
			if (widths.get(e.target) === w) continue
			widths.set(e.target, w)
			fit(e.target as HTMLElement)
		}
	})
	: null

// all mounted labels, to refit them when the item font is switched in the settings
const labels = new Set<HTMLElement>()
watch(itemFont, (font) => {
	const refit = () => labels.forEach(fit)
	// wait for the new font, otherwise the labels are measured in the fallback font
	const ready = font !== 'standard' && document.fonts ? document.fonts.load(`16px "${ITEM_FONT_FAMILY[font]}"`) : Promise.resolve()
	ready.then(refit, refit)
})

export const vFitText: Directive<HTMLElement, unknown> = {
	mounted(el) {
		fit(el)
		labels.add(el)
		observer?.observe(el)
		// the web font can arrive after the first layout and change the line breaks
		document.fonts?.ready.then(() => fit(el)).catch(() => {})
	},
	updated(el, binding) {
		if (binding.value !== binding.oldValue) fit(el)
	},
	unmounted(el) {
		labels.delete(el)
		observer?.unobserve(el)
	},
}
