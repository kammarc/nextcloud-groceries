<template>
	<!-- invisible copy of the sheet, rendered for a moment to measure how high it needs to be -->
	<div v-if="probing && entry" class="probe" :style="{ width: `${probeWidth}px` }" aria-hidden="true" inert>
		<ItemSheet ref="probe" v-bind="entry" />
	</div>
	<ion-modal :is-open="open" :initial-breakpoint="stops.first" :breakpoints="stops.list" @didDismiss="emit('close')">
		<ItemSheet v-if="open && entry" v-bind="entry" @submit="emit('submit', $event)" @delete="emit('delete')" />
	</ion-modal>
</template>

<script setup lang="ts">
import { IonModal } from '@ionic/vue'
import { nextTick, ref, watch } from 'vue'
import ItemSheet, { type ItemSheetValue } from './ItemSheet.vue'
import { itemSheetStops, type ItemSheetEntry } from '../lib/itemSheet'

/**
 * The long-press item sheet in its modal (list and add sheet). Ionic fixes a sheet's heights when
 * it opens, so the content is measured first: pulled up all the way, the sheet ends right below
 * its last row instead of leaving empty space.
 */
const props = defineProps<{ entry: ItemSheetEntry | null }>()
const emit = defineEmits<{ submit: [value: ItemSheetValue], delete: [], close: [] }>()

const open = ref(false)
const probing = ref(false)
const probe = ref<InstanceType<typeof ItemSheet> | null>(null)
const probeWidth = ref(0)
const stops = ref(itemSheetStops(0))

const frame = () => new Promise<void>((resolve) => requestAnimationFrame(() => resolve()))

watch(() => props.entry, async (entry, previous) => {
	if (!entry) {
		open.value = false
		return
	}
	if (previous && open.value) return
	// sheets are as wide as the app column (wide screens) or the screen
	probeWidth.value = document.querySelector('ion-app')?.clientWidth || window.innerWidth
	probing.value = true
	await nextTick()
	await frame()
	stops.value = itemSheetStops(probe.value?.height() ?? 0)
	probing.value = false
	if (props.entry === entry) open.value = true
})
</script>

<style scoped>
.probe {
	position: fixed;
	top: 0;
	left: -10000px;
	height: 2000px;
	visibility: hidden;
	pointer-events: none;
}
</style>
