import { computed, inject, provide, type ComputedRef, type InjectionKey } from 'vue'
import { useData } from '../stores/data'

/**
 * "Sketch icons" list preference: item icons are drawn as one-colour pen sketches
 * (drawn by lib/sketchRender.ts) instead of colour emoji.
 */
const KEY: InjectionKey<ComputedRef<boolean>> = Symbol('sketchIcons')

/** Call in a per-list view; every ItemIcon below it (sheets included) follows the list's preference. */
export function provideSketchIcons(listId: number) {
	const data = useData()
	provide(KEY, computed(() => data.state(listId).meta?.prefs.sketchIcons ?? false))
}

export function useSketchIcons(): ComputedRef<boolean> | undefined {
	return inject(KEY, undefined)
}

/*
 * Lazy drawing for long emoji grids (the emoji picker has ~1850 emoji, ~8 ms each): an element is
 * drawn only once it scrolls near the screen, a few per animation frame so scrolling stays smooth.
 */
const waiting = new WeakMap<Element, () => void>()
const visible = new Map<Element, () => void>()
let observer: IntersectionObserver | undefined
let pumping = false

function pump() {
	const end = performance.now() + 10
	for (const [el, run] of visible) {
		if (performance.now() > end) break
		forget(el)
		run()
	}
	if (visible.size) requestAnimationFrame(pump)
	else pumping = false
}

function forget(el: Element) {
	waiting.delete(el)
	visible.delete(el)
	observer?.unobserve(el)
}

/** Runs `run` once `el` is (nearly) visible; returns a cancel function. */
export function whenVisible(el: Element, run: () => void): () => void {
	if (typeof IntersectionObserver === 'undefined') {
		run()
		return () => {}
	}
	observer ??= new IntersectionObserver((entries) => {
		for (const entry of entries) {
			const fn = waiting.get(entry.target)
			if (!fn) continue
			if (entry.isIntersecting) visible.set(entry.target, fn)
			else visible.delete(entry.target)
		}
		if (visible.size && !pumping) {
			pumping = true
			requestAnimationFrame(pump)
		}
	}, { rootMargin: '150px' })
	waiting.set(el, run)
	observer.observe(el)
	return () => forget(el)
}
