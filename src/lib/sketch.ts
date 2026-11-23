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
