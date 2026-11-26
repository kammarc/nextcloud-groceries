<template>
	<!-- presses on the header must not reach the sheet's drag gesture: only the handle moves the sheet -->
	<ion-header @touchstart.stop @mousedown.stop @pointerdown.stop>
		<ion-toolbar>
			<ion-title>{{ t('Add items') }}</ion-title>
			<ion-buttons slot="end">
				<ion-button strong @click="emit('close')">{{ t('Done') }}</ion-button>
			</ion-buttons>
		</ion-toolbar>
		<ion-toolbar>
			<ion-searchbar
				ref="searchbar"
				v-model="query"
				:placeholder="t('Search or add an item')"
				:debounce="0"
				enterkeyhint="enter"
				autocapitalize="sentences"
				@ionFocus="searchFocused = true"
				@ionBlur="searchFocused = false"
				@keydown.enter.prevent="submitRaw" />
		</ion-toolbar>
	</ion-header>

	<!-- same for the content (Ionic would still drag the sheet when the content is scrolled to the top) -->
	<ion-content @touchstart.stop @mousedown.stop @pointerdown.stop @pointerdown.capture="focusedAtPress = searchFocused">
		<!-- search results: list or tiles like the list itself; adding the typed text comes last -->
		<ion-list v-if="query.trim() && viewMode === 'list'" lines="full">
			<ion-item v-for="s in suggestions" :key="s.identity" button :detail="false" v-bind="pressFor(s)" @click="tap(s)">
				<ItemIcon slot="start" :icon="s.icon" :catalog-key="s.catalogKey" :category="s.category" :name="s.name" />
				<ion-label>
					<h2>{{ s.name }}</h2>
					<p>{{ categoryLabel(s.category, custom, lang).name }}</p>
				</ion-label>
				<ion-note v-if="onList.get(s.identity)?.quantity" slot="end" class="row-qty">{{ onList.get(s.identity)?.quantity }}</ion-note>
				<ion-icon v-if="onList.has(s.identity)" slot="end" :icon="checkmarkCircle" color="success" />
			</ion-item>
			<ion-item v-if="showRaw" button :detail="false" v-bind="pressFor(rawSuggestion)" @click="tapRaw">
				<ion-icon slot="start" :icon="addCircle" color="primary" class="raw-icon" />
				<ion-label class="ion-text-wrap">
					<h2>{{ rawLabel }}</h2>
					<p v-if="parsed.quantity">{{ parsed.quantity }}</p>
				</ion-label>
			</ion-item>
		</ion-list>

		<div v-else-if="query.trim()" class="tile-grid results">
			<button
				v-for="s in suggestions"
				:key="s.identity"
				type="button"
				class="pick ion-activatable"
				:class="{ on: onList.has(s.identity) }"
				v-bind="pressFor(s)"
				@click="tap(s)">
				<ion-ripple-effect />
				<span v-if="onList.get(s.identity)?.quantity" class="badge">{{ onList.get(s.identity)?.quantity }}</span>
				<ItemIcon :icon="s.icon" :catalog-key="s.catalogKey" :category="s.category" :name="s.name" large />
				<span v-fit-text="s.name" class="name"><span class="name-text">{{ s.name }}</span></span>
			</button>
			<button
				v-if="showRaw"
				type="button"
				class="pick raw ion-activatable"
				:aria-label="rawLabel"
				v-bind="pressFor(rawSuggestion)"
				@click="tapRaw">
				<ion-ripple-effect />
				<span v-if="parsed.quantity" class="badge">{{ parsed.quantity }}</span>
				<ion-icon :icon="addCircle" class="raw-tile-icon" />
				<span v-fit-text="typedName" class="name"><span class="name-text">{{ typedName }}</span></span>
			</button>
		</div>

		<template v-else>
			<p class="hint">{{ t('Long press an item to set the amount') }}</p>
			<template v-for="group in tileGroups" :key="group.key">
				<template v-if="group.entries.length">
					<button v-if="group.collapsible" type="button" class="section-title toggle" @click="toggleFrequent">
						<ion-icon :icon="frequentOpen ? chevronDown : chevronForward" />
						{{ group.title }}
					</button>
					<h3 v-else class="section-title">{{ group.title }}</h3>
					<template v-if="!group.collapsible || frequentOpen">
						<!-- rows in list view, tiles in grid view (like the list itself) -->
						<ion-list v-if="viewMode === 'list'" lines="full" class="group-list">
							<ion-item v-for="s in group.entries" :key="s.identity" button :detail="false" v-bind="pressFor(s)" @click="tap(s)">
								<ItemIcon slot="start" :icon="s.icon" :catalog-key="s.catalogKey" :category="s.category" :name="s.name" />
								<ion-label>
									<h2>{{ s.name }}</h2>
									<p>{{ categoryLabel(s.category, custom, lang).name }}</p>
								</ion-label>
								<ion-note v-if="onList.get(s.identity)?.quantity" slot="end" class="row-qty">{{ onList.get(s.identity)?.quantity }}</ion-note>
								<ion-icon v-if="onList.has(s.identity)" slot="end" :icon="checkmarkCircle" color="success" />
							</ion-item>
						</ion-list>
						<div v-else class="tile-grid">
							<button
								v-for="s in group.entries"
								:key="s.identity"
								type="button"
								class="pick ion-activatable"
								:class="{ on: onList.has(s.identity) }"
								v-bind="pressFor(s)"
								@click="tap(s)">
								<ion-ripple-effect />
								<span v-if="onList.get(s.identity)?.quantity" class="badge">{{ onList.get(s.identity)?.quantity }}</span>
								<ItemIcon :icon="s.icon" :catalog-key="s.catalogKey" :category="s.category" :name="s.name" large />
								<span v-fit-text="s.name" class="name"><span class="name-text">{{ s.name }}</span></span>
							</button>
						</div>
					</template>
				</template>
			</template>
		</template>

		<!-- long press: amount before adding (or updating an item already on the list); pulled up
		     it edits the entry itself. The same sheet as a long press in the list (ShoppingListView). -->
		<ItemSheetModal :entry="detailsEntry" @submit="submitDetails" @delete="deleteDetails" @close="details = null" />
	</ion-content>
</template>

<script setup lang="ts">
import {
	IonButton, IonButtons, IonContent, IonHeader, IonIcon, IonItem, IonLabel, IonList, IonModal, IonNote, IonRippleEffect,
	IonSearchbar, IonTitle, IonToolbar, toastController,
} from '@ionic/vue'
import { addCircle, checkmarkCircle, chevronDown, chevronForward } from 'ionicons/icons'
import { computed, onMounted, ref } from 'vue'
import ItemIcon from './ItemIcon.vue'
import type { ItemSheetValue } from './ItemSheet.vue'
import ItemSheetModal from './ItemSheetModal.vue'
import { itemChanges, type ItemSheetEntry } from '../lib/itemSheet'
import { useLongPress } from '../lib/longpress'
import { vFitText } from '../lib/fit'
import { categoriesForTemplate, identityKey, itemCategory, items as catalogItems, itemName, searchCatalog, type CatalogItem } from '../lib/catalog'
import { normalize } from '../lib/normalize'
import { parseLine } from '../lib/parser'
import { categoryLabel } from '../lib/sorting'
import { haptic } from '../lib/platform'
import { lang, t } from '../i18n'
import { useData } from '../stores/data'
import type { HistoryEntry, Item } from '../types'

interface Suggestion {
	identity: string
	name: string
	catalogKey: string | null
	category: string
	icon: string | null
	/** free text typed by the user: let the server pick category and icon */
	raw?: boolean
}

const props = defineProps<{ listId: number }>()
/** added: identity (normName) of the item that was added/updated, so the list can scroll to it */
const emit = defineEmits<{ close: [], added: [identity: string] }>()
const data = useData()
const query = ref('')
const searchbar = ref<any>(null)
/** Whether the search field has focus (keyboard up), and whether it had when the last press began:
 * tapping an entry blurs it, so after adding it only gets focus back if it had it before. */
const searchFocused = ref(false)
const focusedAtPress = ref(false)

const state = computed(() => data.state(props.listId))
const custom = computed(() => state.value.meta?.categories ?? [])
const template = computed(() => data.list(props.listId)?.template ?? 'grocery')
const items = computed(() => data.items(props.listId))
const onList = computed(() => new Map(items.value.filter((i) => !i.checked).map((i) => [i.normName, i])))
const parsed = computed(() => parseLine(query.value))
/** Typed text without the amount. Entries have no notes, so what the parser splits off as one
 * ("Mehl (Type 405)", "Milch, fettarm") stays part of the name. */
const typedName = computed(() => (parsed.value.note ? `${parsed.value.name} (${parsed.value.note})` : parsed.value.name))
/** search results look like the list: rows in list view, tiles in grid view */
const viewMode = computed(() => state.value.meta?.prefs.viewMode ?? 'list')
const rawLabel = computed(() => t('Add "{name}"', { name: typedName.value }))

const fromHistory = (h: HistoryEntry): Suggestion => ({
	identity: h.normName, name: h.name, catalogKey: h.catalogKey, category: h.category, icon: h.icon,
})
const fromCatalog = (c: CatalogItem): Suggestion => ({
	identity: `c:${c.key}`, name: itemName(c, lang), catalogKey: c.key, category: itemCategory(c, template.value), icon: c.icon,
})

const suggestions = computed<Suggestion[]>(() => {
	const q = normalize(parsed.value.name)
	if (!q) return []
	const out = new Map<string, Suggestion>()
	const hist = state.value.history
		.map((h) => ({ h, n: normalize(h.name) }))
		.filter(({ n }) => n.startsWith(q) || n.includes(' ' + q) || (q.length >= 3 && n.includes(q)))
		.sort((a, b) => Number(b.n.startsWith(q)) - Number(a.n.startsWith(q)) || b.h.useCount - a.h.useCount)
		.slice(0, 5)
	for (const { h } of hist) out.set(h.normName, fromHistory(h))
	for (const c of searchCatalog(parsed.value.name, lang, 12)) {
		if (!out.has(`c:${c.key}`)) out.set(`c:${c.key}`, fromCatalog(c))
	}
	return [...out.values()].slice(0, 14)
})

const showRaw = computed(() => {
	const first = suggestions.value[0]
	return !first || normalize(first.name) !== normalize(parsed.value.name) || !!parsed.value.quantity
})

const tileGroups = computed(() => {
	const now = Date.now() / 1000
	const frecency = (h: HistoryEntry) => h.useCount / (1 + (now - h.lastUsedAt) / 86400 / 14)
	const history = [...state.value.history]
		.sort((a, b) => frecency(b) - frecency(a))
		.slice(0, 36)
		.map(fromHistory)
	const known = new Set(history.map((s) => s.identity))
	const cats = categoriesForTemplate(template.value).filter((c) => c.key !== 'other').slice(0, 14).map((c) => c.key)
	const perCat = new Map<string, number>()
	const popular = catalogItems
		.filter((c) => cats.includes(itemCategory(c, template.value)) && !known.has(`c:${c.key}`))
		.filter((c) => {
			const cat = itemCategory(c, template.value)
			const n = perCat.get(cat) ?? 0
			perCat.set(cat, n + 1)
			return n < (history.length > 12 ? 2 : 4)
		})
		.map(fromCatalog)
	return [
		// hidden completely when switched off in the list settings
		{ key: 'frequent', title: t('Frequently bought'), entries: showFrequent.value ? history : [], collapsible: true },
		{ key: 'suggestions', title: t('Suggestions'), entries: popular, collapsible: false },
	]
})

// ---- "frequently bought": per-person on/off (list settings) + collapsed state on this device ----
const showFrequent = computed(() => state.value.meta?.prefs.showFrequent ?? true)
const FREQUENT_KEY = 'groceries:frequent-collapsed'
const frequentOpen = ref(readCollapsed())
function readCollapsed(): boolean {
	try {
		return localStorage.getItem(FREQUENT_KEY) !== '1'
	} catch {
		return true
	}
}
function toggleFrequent() {
	frequentOpen.value = !frequentOpen.value
	try {
		localStorage.setItem(FREQUENT_KEY, frequentOpen.value ? '0' : '1')
	} catch {
		// ignore
	}
}

// ---- long press: set amount before adding --------------------------------------------------
const rawSuggestion = computed<Suggestion>(() => ({
	identity: identityKey(typedName.value), name: typedName.value, catalogKey: null, category: 'other', icon: null, raw: true,
}))
let pressTarget: Suggestion | null = null
const longPress = useLongPress(() => { if (pressTarget) openDetails(pressTarget) })

/** Long-press handlers that remember which entry is being pressed. */
function pressFor(s: Suggestion) {
	return {
		...longPress.handlers,
		onPointerdown: (e: PointerEvent) => {
			pressTarget = s
			longPress.handlers.onPointerdown(e)
		},
	}
}

function tap(s: Suggestion) {
	if (longPress.consumeClick()) return
	choose(s)
}

function tapRaw() {
	if (longPress.consumeClick()) return
	submitRaw()
}

const details = ref<{ s: Suggestion, existing?: Item, quantity: string | null, refocus: boolean } | null>(null)
const detailsEntry = computed<ItemSheetEntry | null>(() => {
	const d = details.value
	if (!d) return null
	return {
		name: d.existing?.name ?? d.s.name,
		icon: d.existing ? d.existing.icon : d.s.icon,
		catalogKey: d.existing?.catalogKey ?? d.s.catalogKey,
		category: d.existing?.category ?? d.s.category,
		quantity: d.quantity,
		existing: !!d.existing,
		template: template.value,
		custom: custom.value,
	}
})

function openDetails(s: Suggestion) {
	const existing = onList.value.get(s.identity)
	const hist = state.value.history.find((h) => h.normName === s.identity)
	const typed = query.value.trim() !== ''
	details.value = {
		s,
		existing,
		quantity: existing?.quantity ?? (typed ? parsed.value.quantity : null) ?? hist?.lastQuantity ?? null,
		refocus: searchFocused.value || focusedAtPress.value,
	}
}

async function submitDetails(v: ItemSheetValue) {
	const d = details.value
	details.value = null
	if (!d) return
	if (d.existing) {
		const changes = itemChanges(d.existing, v)
		if (Object.keys(changes).length) data.updateItem(props.listId, d.existing.id, changes)
		emit('added', d.existing.normName)
		haptic()
		const toast = await toastController.create({ message: t('Updated {name}', { name: d.s.name }), duration: 900, position: 'bottom', cssClass: 'compact-toast' })
		await toast.present()
	} else {
		// typed text: the server picks category and icon, unless they were chosen in the sheet
		const keepGuess = d.s.raw && v.category === d.s.category && v.icon === d.s.icon
		data.addItem(props.listId, keepGuess
			? { name: v.name, quantity: v.quantity }
			: { name: v.name, quantity: v.quantity, category: v.category, icon: v.icon })
		added(v.name, d.s.raw || v.name !== d.s.name ? identityKey(v.name) : d.s.identity)
	}
	resetQuery(d.refocus)
}

/** Long-press sheet of an item already on the list: take it off. */
function deleteDetails() {
	const d = details.value
	details.value = null
	if (!d?.existing) return
	data.deleteItem(props.listId, d.existing)
	haptic(5)
	resetQuery(d.refocus)
}

async function added(name: string, identity: string) {
	emit('added', identity)
	haptic()
	const toast = await toastController.create({ message: t('Added {name}', { name }), duration: 900, position: 'bottom', cssClass: 'compact-toast' })
	await toast.present()
}

function choose(s: Suggestion) {
	const existing = onList.value.get(s.identity)
	if (existing) {
		// tapping something that is already on the list takes it off again (like a toggle)
		data.deleteItem(props.listId, existing)
		haptic(5)
	} else {
		data.addItem(props.listId, {
			name: s.name,
			quantity: query.value ? parsed.value.quantity : null,
			category: s.category,
			icon: s.icon,
		})
		added(s.name, s.identity)
	}
	resetQuery()
}

function submitRaw() {
	const name = typedName.value
	if (!name.trim()) return
	data.addItem(props.listId, { name, quantity: parsed.value.quantity })
	added(name, identityKey(name))
	resetQuery()
}

function focus() {
	setTimeout(() => searchbar.value?.$el?.setFocus?.(), 50)
}

function resetQuery(refocus = searchFocused.value || focusedAtPress.value) {
	query.value = ''
	if (refocus) focus()
}

onMounted(() => setTimeout(focus, 350))
</script>

<style scoped>
.raw-icon {
	font-size: 32px;
	margin-inline: 2px 14px;
}
.section-title {
	display: flex;
	align-items: center;
	gap: 6px;
	margin: 20px 16px 8px;
	font-size: 13px;
	font-weight: 600;
	letter-spacing: 0.02em;
	text-transform: uppercase;
	color: var(--ion-color-medium);
}
.section-title.toggle {
	width: calc(100% - 32px);
	padding: 0;
	border: none;
	background: transparent;
	font-family: inherit;
	text-align: start;
	cursor: pointer;
}
.group-list {
	padding-top: 0;
	padding-bottom: 0;
}
/* rows: long press sets the amount, so no text selection / callout */
ion-list ion-item {
	-webkit-user-select: none;
	user-select: none;
	-webkit-touch-callout: none;
}
.row-qty {
	margin-inline-end: 8px;
	font-size: 14px;
	font-weight: 600;
	color: var(--app-buy-strong);
}
.hint {
	margin: 12px 16px 0;
	font-size: 13px;
	color: var(--app-muted);
}
.pick {
	-webkit-user-select: none;
	user-select: none;
	-webkit-touch-callout: none;
}
.badge {
	position: absolute;
	top: 5px;
	right: 5px;
	max-width: calc(100% - 10px);
	padding: 2px 8px;
	border-radius: 12px;
	background: rgba(255, 255, 255, 0.94);
	color: var(--app-buy-strong);
	font-size: 13px;
	font-weight: 700;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}
.tile-grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(88px, 1fr));
	gap: 6px;
	padding: 0 12px;
}
/* phones: always 3 per row, whatever the screen width */
@media (max-width: 599px) {
	.tile-grid {
		grid-template-columns: repeat(3, minmax(0, 1fr));
	}
}
.pick {
	position: relative;
	overflow: hidden;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	gap: 4px;
	aspect-ratio: 1;
	padding: 6px 4px;
	/* children size against the tile width (cqw), so every tile looks the same */
	container-type: inline-size;
	border: none;
	border-radius: 10px;
	background: var(--app-recent);
	color: #ffffff;
	font: inherit;
	-webkit-tap-highlight-color: transparent;
}
.pick.on {
	background: var(--app-buy);
}
.tile-grid.results {
	padding-top: 12px;
}
/* "add what I typed": outlined instead of filled */
.pick.raw {
	background: transparent;
	color: var(--ion-text-color);
	box-shadow: inset 0 0 0 2px var(--ion-color-primary);
}
.raw-tile-icon {
	flex-shrink: 0;
	font-size: 46cqw;
	color: var(--ion-color-primary);
}
/* the icon has one size for all tiles, whatever the name: long names shrink their text instead */
.pick :deep(.item-icon.large) {
	width: 46cqw;
	height: 46cqw;
	font-size: 35cqw;
}
.pick :deep(.item-icon.letter.large) {
	font-size: 21cqw;
}
/* a fixed two-line box, so the labels of all tiles sit at the same height;
   v-fit-text shrinks the font when a name needs more than two lines */
.pick .name {
	flex: 0 0 auto;
	display: flex;
	align-items: center;
	justify-content: center;
	width: 100%;
	height: 2.4em;
	font-size: 12px;
	overflow: hidden;
}
.pick .name-text {
	display: block;
	max-width: 100%;
	font-family: var(--app-item-font);
	font-weight: var(--app-item-weight);
	-webkit-text-stroke: var(--app-item-stroke) currentColor;
	line-height: 1.15;
	text-align: center;
	overflow-wrap: break-word;
	-webkit-hyphens: auto;
	hyphens: auto;
}
</style>
