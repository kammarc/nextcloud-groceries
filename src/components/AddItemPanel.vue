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
				@keydown.enter.prevent="submitRaw" />
		</ion-toolbar>
	</ion-header>

	<!-- same for the content (Ionic would still drag the sheet when the content is scrolled to the top) -->
	<ion-content @touchstart.stop @mousedown.stop @pointerdown.stop>
		<ion-list v-if="query.trim()" lines="full">
			<ion-item v-if="showRaw" button :detail="false" v-bind="pressFor(rawSuggestion)" @click="tapRaw">
				<ion-icon slot="start" :icon="addCircle" color="primary" class="raw-icon" />
				<ion-label class="ion-text-wrap">
					<h2>{{ t('Add "{name}"', { name: parsed.name }) }}</h2>
					<p v-if="parsed.quantity || parsed.note">{{ [parsed.quantity, parsed.note].filter(Boolean).join(' · ') }}</p>
				</ion-label>
			</ion-item>
			<ion-item v-for="s in suggestions" :key="s.identity" button :detail="false" v-bind="pressFor(s)" @click="tap(s)">
				<ItemIcon slot="start" :icon="s.icon" :catalog-key="s.catalogKey" :category="s.category" :name="s.name" />
				<ion-label>
					<h2>{{ s.name }}</h2>
					<p>{{ categoryLabel(s.category, custom, lang).name }}</p>
				</ion-label>
				<ion-icon v-if="onList.has(s.identity)" slot="end" :icon="checkmarkCircle" color="success" />
			</ion-item>
		</ion-list>

		<template v-else>
			<p class="hint">{{ t('Long press an item to set the amount') }}</p>
			<template v-for="group in tileGroups" :key="group.key">
				<template v-if="group.entries.length">
					<button v-if="group.collapsible" type="button" class="section-title toggle" @click="toggleFrequent">
						<ion-icon :icon="frequentOpen ? chevronDown : chevronForward" />
						{{ group.title }}
					</button>
					<h3 v-else class="section-title">{{ group.title }}</h3>
					<div v-if="!group.collapsible || frequentOpen" class="tile-grid">
						<button
							v-for="s in group.entries"
							:key="s.identity"
							type="button"
							class="pick ion-activatable"
							:class="[nameFit(s.name), { on: onList.has(s.identity) }]"
							v-bind="pressFor(s)"
							@click="tap(s)">
							<ion-ripple-effect />
							<span v-if="onList.get(s.identity)?.quantity" class="badge">{{ onList.get(s.identity)?.quantity }}</span>
							<ItemIcon :icon="s.icon" :catalog-key="s.catalogKey" :category="s.category" :name="s.name" large />
							<span class="name">{{ s.name }}</span>
						</button>
					</div>
				</template>
			</template>
		</template>

		<!-- long press: amount + note before adding (or updating an item already on the list) -->
		<ion-modal :is-open="details !== null" :initial-breakpoint="0.62" :breakpoints="[0, 0.62, 0.9]" @didDismiss="details = null">
			<QuickAddPanel
				v-if="details"
				:name="details.s.name"
				:icon="details.s.icon"
				:catalog-key="details.s.catalogKey"
				:category="details.s.category"
				:initial-quantity="details.quantity"
				:initial-note="details.note"
				:existing="!!details.existing"
				@submit="submitDetails" />
		</ion-modal>
	</ion-content>
</template>

<script setup lang="ts">
import {
	IonButton, IonButtons, IonContent, IonHeader, IonIcon, IonItem, IonLabel, IonList, IonModal, IonRippleEffect,
	IonSearchbar, IonTitle, IonToolbar, toastController,
} from '@ionic/vue'
import { addCircle, checkmarkCircle, chevronDown, chevronForward } from 'ionicons/icons'
import { computed, onMounted, ref } from 'vue'
import ItemIcon from './ItemIcon.vue'
import QuickAddPanel from './QuickAddPanel.vue'
import { useLongPress } from '../lib/longpress'
import { nameFit } from '../lib/fit'
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

const state = computed(() => data.state(props.listId))
const custom = computed(() => state.value.meta?.categories ?? [])
const template = computed(() => data.list(props.listId)?.template ?? 'grocery')
const items = computed(() => data.items(props.listId))
const onList = computed(() => new Map(items.value.filter((i) => !i.checked).map((i) => [i.normName, i])))
const parsed = computed(() => parseLine(query.value))

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
	identity: identityKey(parsed.value.name), name: parsed.value.name, catalogKey: null, category: 'other', icon: null, raw: true,
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

const details = ref<{ s: Suggestion, existing?: Item, quantity: string | null, note: string | null } | null>(null)

function openDetails(s: Suggestion) {
	const existing = onList.value.get(s.identity)
	const hist = state.value.history.find((h) => h.normName === s.identity)
	const typed = query.value.trim() !== ''
	details.value = {
		s,
		existing,
		quantity: existing?.quantity ?? (typed ? parsed.value.quantity : null) ?? hist?.lastQuantity ?? null,
		note: existing?.note ?? (typed ? parsed.value.note : null),
	}
}

async function submitDetails(v: { quantity: string | null, note: string | null }) {
	const d = details.value
	details.value = null
	if (!d) return
	if (d.existing) {
		data.updateItem(props.listId, d.existing.id, { quantity: v.quantity, note: v.note })
		emit('added', d.existing.normName)
		haptic()
		const toast = await toastController.create({ message: t('Updated {name}', { name: d.s.name }), duration: 900, position: 'bottom', cssClass: 'compact-toast' })
		await toast.present()
	} else {
		data.addItem(props.listId, d.s.raw
			? { name: d.s.name, quantity: v.quantity, note: v.note }
			: { name: d.s.name, quantity: v.quantity, note: v.note, category: d.s.category, icon: d.s.icon })
		added(d.s.name, d.s.raw ? identityKey(d.s.name) : d.s.identity)
	}
	resetQuery()
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
			note: query.value ? parsed.value.note : null,
			category: s.category,
			icon: s.icon,
		})
		added(s.name, s.identity)
	}
	resetQuery()
}

function submitRaw() {
	const p = parsed.value
	if (!p.name.trim()) return
	data.addItem(props.listId, { name: p.name, quantity: p.quantity, note: p.note })
	added(p.name, identityKey(p.name))
	resetQuery()
}

function focus() {
	setTimeout(() => searchbar.value?.$el?.setFocus?.(), 50)
}

function resetQuery() {
	query.value = ''
	focus()
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
.pick {
	position: relative;
	overflow: hidden;
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 4px;
	padding: 10px 4px;
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
.pick .name {
	max-width: 100%;
	font-size: 12px;
	text-align: center;
	line-height: 1.15;
	overflow: hidden;
	display: -webkit-box;
	-webkit-line-clamp: 3;
	-webkit-box-orient: vertical;
	overflow-wrap: anywhere;
	-webkit-hyphens: auto;
	hyphens: auto;
}
/* long names: smaller text and icon so the whole name fits the tile */
.pick.long .name {
	font-size: 11px;
}
.pick.xlong .name {
	font-size: 10.5px;
	letter-spacing: -0.1px;
}
.pick.long :deep(.item-icon.large) {
	width: 44px;
	height: 44px;
	font-size: 34px;
}
.pick.xlong :deep(.item-icon.large) {
	width: 36px;
	height: 36px;
	font-size: 28px;
}
</style>
