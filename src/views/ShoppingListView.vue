<template>
	<ion-page>
		<ion-header :translucent="true" class="list-header" :class="{ 'slid-out': sheetOpen }">
			<ion-toolbar>
				<ion-buttons slot="start">
					<ion-back-button default-href="/" :text="ios ? t('Lists') : undefined" />
				</ion-buttons>
				<ion-title>{{ list?.name ?? '' }}</ion-title>
				<ion-buttons slot="end">
					<ion-button :aria-label="viewMode === 'list' ? t('Grid view') : t('List view')" @click="toggleView">
						<ion-icon slot="icon-only" :icon="viewMode === 'list' ? gridOutline : listOutline" />
					</ion-button>
					<ion-button :aria-label="t('Settings')" @click="openMenu">
						<ion-icon slot="icon-only" :icon="ios ? ellipsisHorizontalCircleOutline : ellipsisVertical" />
					</ion-button>
				</ion-buttons>
			</ion-toolbar>
		</ion-header>

		<ion-content ref="content" :fullscreen="true">
			<ion-header collapse="condense">
				<ion-toolbar>
					<ion-title size="large">{{ list?.name ?? '' }}</ion-title>
				</ion-toolbar>
			</ion-header>

			<ion-refresher slot="fixed" @ionRefresh="refresh">
				<ion-refresher-content />
			</ion-refresher>

			<SyncStatus />

			<div v-if="stores.length" class="store-bar">
				<button type="button" class="store-chip" @click="pickStore">
					<AppIcon :icon="activeStore?.icon" :image="activeStore?.image" :size="22" />
					<span>{{ activeStore?.name ?? t('Default order') }}</span>
					<ion-icon :icon="swapVertical" />
				</button>
			</div>

			<div v-if="!loaded" class="ion-text-center ion-padding">
				<ion-spinner />
			</div>

			<div v-else-if="open.length === 0" class="empty">
				<div class="big">{{ checked.length ? '🎉' : (list?.icon || '🛒') }}</div>
				<h2>{{ checked.length ? t('All done – enjoy!') : t('Nothing left to buy') }}</h2>
				<p v-if="!checked.length && !readonly">{{ t('Your list is empty. Tap + to add items.') }}</p>
			</div>

			<!-- ungrouped: one big list in aisle order -->
			<ion-list v-if="viewMode === 'list' && !grouped && open.length" lines="none" class="cards first">
				<ItemRow
					:data-norm="item.normName"
					:class="{ flash: highlight === item.normName }"
					v-for="item in sections.flatMap((s) => s.items)"
					:key="String(item.id)"
					:item="item"
					:readonly="readonly"
					:leaving="leaving.has(String(item.id))"
					:people="meta?.people"
					:uid="data.uid"
					@toggle="toggle(item)"
					@edit="editing = item"
					@delete="remove(item)" />
			</ion-list>

			<!-- ungrouped grid: one continuous block of tiles, already in aisle order -->
			<section v-if="viewMode === 'grid' && !grouped && open.length" class="dept">
				<div class="tile-grid first">
					<ItemTile
						:data-norm="item.normName"
						:class="{ flash: highlight === item.normName }"
						v-for="item in sections.flatMap((s) => s.items)"
						:key="String(item.id)"
						:item="item"
						:readonly="readonly"
						:leaving="leaving.has(String(item.id))"
						@toggle="toggle(item)"
						@edit="editing = item" />
				</div>
			</section>

			<!-- grouped (list or grid): what is still to buy, with a heading per department -->
			<section v-for="section in grouped ? sections : []" :key="section.key" class="dept">
				<h3 class="section-title">
					<span class="emoji">{{ section.icon }}</span>
					<span class="name">{{ section.name }}</span>
					<span class="count">{{ section.items.length }}</span>
				</h3>
				<ion-list v-if="viewMode === 'list'" lines="none" class="cards">
					<ItemRow
					:data-norm="item.normName"
					:class="{ flash: highlight === item.normName }"
						v-for="item in section.items"
						:key="String(item.id)"
						:item="item"
						:readonly="readonly"
						:leaving="leaving.has(String(item.id))"
						:people="meta?.people"
						:uid="data.uid"
						@toggle="toggle(item)"
						@edit="editing = item"
						@delete="remove(item)" />
				</ion-list>
				<div v-else class="tile-grid">
					<ItemTile
						:data-norm="item.normName"
						:class="{ flash: highlight === item.normName }"
						v-for="item in section.items"
						:key="String(item.id)"
						:item="item"
						:readonly="readonly"
						:leaving="leaving.has(String(item.id))"
						@toggle="toggle(item)"
						@edit="editing = item" />
				</div>
			</section>

			<!-- ticked items -->
			<section v-if="checked.length" class="dept">
				<button type="button" class="section-title toggle" @click="cartOpen = !cartOpen">
					<ion-icon :icon="cartOpen ? chevronDown : chevronForward" />
					<span class="name">{{ t('In the cart ({count})', { count: checked.length }) }}</span>
				</button>
				<template v-if="cartOpen">
					<ion-list v-if="viewMode === 'list'" lines="none" class="cards">
						<ItemRow
					:data-norm="item.normName"
					:class="{ flash: highlight === item.normName }"
							v-for="item in checked"
							:key="String(item.id)"
							:item="item"
							:readonly="readonly"
							@toggle="toggle(item)"
							@edit="editing = item"
							@delete="remove(item)" />
					</ion-list>
					<div v-else class="tile-grid">
						<ItemTile v-for="item in checked" :key="String(item.id)" :item="item" :readonly="readonly" @toggle="toggle(item)" @edit="editing = item" />
					</div>
					<ion-button v-if="!readonly" expand="block" class="ion-margin" @click="finish">
						<ion-icon slot="start" :icon="checkmarkDoneOutline" />
						{{ t('Finish shopping') }}
					</ion-button>
				</template>
			</section>

			<!-- while the add sheet is open, extra room lets even the last item scroll up into the visible strip -->
			<div class="fab-spacer" :class="{ 'sheet-open': sheetOpen }" />

			<ion-fab v-if="!readonly" slot="fixed" vertical="bottom" horizontal="end">
				<ion-fab-button :aria-label="t('Add item')" @click="adding = true">
					<ion-icon :icon="add" />
				</ion-fab-button>
			</ion-fab>
		</ion-content>

		<!-- sheet over 6/7 of the screen with a drag handle; the list stays visible (no dimming) above it -->
		<ion-modal
			:is-open="adding"
			:initial-breakpoint="SHEET"
			:breakpoints="[0, SHEET]"
			:backdrop-breakpoint="SHEET"
			:expand-to-scroll="false"
			:handle="true"
			class="add-sheet"
			@didDismiss="adding = false">
			<AddItemPanel v-if="adding" :list-id="listId" @close="adding = false" @added="onAdded" />
		</ion-modal>

		<!-- edit sheet (long press / swipe): same sheet behaviour as adding -->
		<ion-modal
			:is-open="editing !== null"
			:initial-breakpoint="SHEET"
			:breakpoints="[0, SHEET]"
			:backdrop-breakpoint="SHEET"
			:expand-to-scroll="false"
			:handle="true"
			class="add-sheet"
			@didDismiss="editing = null">
			<EditItemPanel
				v-if="editing"
				:item="editing"
				:template="list?.template ?? 'grocery'"
				:custom="meta?.categories ?? []"
				@close="editing = null"
				@save="saveEdit"
				@delete="deleteEditing" />
		</ion-modal>
	</ion-page>
</template>

<script setup lang="ts">
import {
	actionSheetController, alertController, IonBackButton, IonButton, IonButtons, IonContent, IonFab, IonFabButton,
	IonHeader, IonIcon, IonList, IonModal, IonPage, IonRefresher, IonRefresherContent, IonSpinner, IonTitle,
	IonToolbar, onIonViewDidEnter, onIonViewWillLeave, toastController,
} from '@ionic/vue'
import {
	add, checkmarkDoneOutline, chevronDown, chevronForward, ellipsisHorizontalCircleOutline, ellipsisVertical,
	gridOutline, listOutline, swapVertical,
} from 'ionicons/icons'
import { computed, nextTick, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AddItemPanel from '../components/AddItemPanel.vue'
import AppIcon from '../components/AppIcon.vue'
import EditItemPanel from '../components/EditItemPanel.vue'
import ItemRow from '../components/ItemRow.vue'
import ItemTile from '../components/ItemTile.vue'
import SyncStatus from '../components/SyncStatus.vue'
import { ApiError } from '../api/client'
import { lang, t } from '../i18n'
import { buildSections } from '../lib/sorting'
import { acquireWakeLock, haptic, isIos, releaseWakeLock } from '../lib/platform'
import { useData } from '../stores/data'
import { usePolling } from '../sync/polling'
import type { Item, ItemData } from '../types'

const route = useRoute()
const router = useRouter()
const data = useData()
const ios = isIos()

// Ionic keeps this page alive underneath pushed pages, so pin the id instead of following the route
const listId = Number(route.params.id)
const state = computed(() => data.state(listId))
const meta = computed(() => state.value.meta)
const list = computed(() => data.list(listId))
const loaded = computed(() => state.value.lastSync > 0)
const readonly = computed(() => list.value?.permission === 'view')
const viewMode = computed(() => meta.value?.prefs.viewMode ?? 'list')
/** department headings on/off (list settings) for list and grid; off = one big list/grid, still in aisle order */
const grouped = computed(() => meta.value?.prefs.grouped ?? true)
const stores = computed(() => meta.value?.stores ?? [])
const activeStore = computed(() => stores.value.find((s) => s.id === meta.value?.prefs.activeStoreId) ?? null)

const items = computed(() => data.items(listId))
const open = computed(() => items.value.filter((i) => !i.checked))
const checked = computed(() => items.value.filter((i) => i.checked).sort((a, b) => (b.checkedAt ?? 0) - (a.checkedAt ?? 0)))
const sections = computed(() => buildSections(open.value, list.value?.template ?? 'grocery', activeStore.value, meta.value?.categories ?? [], lang))

const leaving = reactive(new Set<string>())

// ---- add/edit sheets: cover 6/7 of the screen; the list scrolls to the item concerned ---------
const SHEET = 6 / 7
const content = ref<any>(null)
const highlight = ref<string | null>(null)
let highlightTimer: ReturnType<typeof setTimeout> | null = null

/** Scrolls the list so the item just added (or merged) sits right below the toolbar, and flashes it. */
function onAdded(identity: string) {
	scrollToItem(identity, true)
}

/** Brings an item into the visible strip above an open sheet (optionally with a short highlight). */
async function scrollToItem(identity: string, flash: boolean) {
	if (flash) {
		highlight.value = identity
		if (highlightTimer) clearTimeout(highlightTimer)
		highlightTimer = setTimeout(() => { highlight.value = null }, 1600)
	}
	await nextTick()
	// the ion-content web component (has getScrollElement/scrollToPoint)
	const root: any = content.value?.$el
	if (!root) return
	const items: HTMLElement[] = [...(root as HTMLElement).querySelectorAll<HTMLElement>('[data-norm]')]
	const el = items.find((e) => e.dataset.norm === identity)
	if (!el) return
	const scroller: HTMLElement = await root.getScrollElement()
	// the toolbar is slid out while the sheet is open: only the status bar area stays covered
	const headerHeight = sheetOpen.value
		? parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--ion-safe-area-top')) || 0
		: (root.parentElement?.querySelector('ion-header')?.getBoundingClientRect().height ?? 0)
	const top = el.getBoundingClientRect().top - scroller.getBoundingClientRect().top + scroller.scrollTop
	;(root as any).scrollToPoint(0, Math.max(0, top - headerHeight - 8), 300)
}
const cartOpen = ref(false)
const adding = ref(false)
const editing = ref<Item | null>(null)
/** add or edit sheet open: the toolbar slides out and the list gets room to scroll */
const sheetOpen = computed(() => adding.value || editing.value !== null)
// the edited item moves into view above the edit sheet
watch(editing, (item) => { if (item) scrollToItem(item.normName, false) })

const polling = usePolling(async () => {
	try {
		await data.syncList(listId)
	} catch (e) {
		if (e instanceof ApiError && e.status === 404) {
			data.forgetList(listId)
			router.replace('/')
		}
		throw e
	}
}, 3000)

onIonViewDidEnter(() => {
	polling.start()
	acquireWakeLock()
})
// The phone's back button / back gesture closes an open sheet instead of leaving the list:
// opening one adds a history entry (same URL), going back removes it again.
const SHEET_STATE = 'groceriesSheet'
watch(sheetOpen, (open) => {
	if (open) {
		history.pushState({ ...(history.state ?? {}), [SHEET_STATE]: true }, '')
	} else if (history.state?.[SHEET_STATE]) {
		history.back()
	}
})
function onPopState() {
	if (adding.value) adding.value = false
	if (editing.value) editing.value = null
}
window.addEventListener('popstate', onPopState)
onBeforeUnmount(() => window.removeEventListener('popstate', onPopState))

onIonViewWillLeave(() => {
	polling.stop()
	releaseWakeLock()
})

async function refresh(ev: CustomEvent) {
	try {
		await data.syncList(listId)
		await data.loadHistory(listId)
	} catch {
		// shown via SyncStatus
	} finally {
		(ev.target as any).complete()
	}
}

function toggle(item: Item) {
	if (readonly.value) return
	const id = String(item.id)
	if (item.checked) {
		haptic(5)
		data.setChecked(listId, item.id, false)
		return
	}
	haptic()
	leaving.add(id)
	setTimeout(() => {
		leaving.delete(id)
		data.setChecked(listId, item.id, true)
	}, 230)
}

async function remove(item: Item) {
	const undo = data.deleteItem(listId, item)
	const toast = await toastController.create({
		message: `${item.name} ✕`,
		duration: 3500,
		position: 'bottom',
		buttons: [{ text: t('Undo'), handler: undo }],
	})
	await toast.present()
}

function saveEdit(d: ItemData) {
	if (editing.value && Object.keys(d).length) data.updateItem(listId, editing.value.id, d)
	editing.value = null
}

function deleteEditing() {
	const item = editing.value
	editing.value = null
	if (item) remove(item)
}

async function finish() {
	const alert = await alertController.create({
		header: t('Finish shopping'),
		message: t('Remove all ticked items from the list?'),
		buttons: [
			{ text: t('Cancel'), role: 'cancel' },
			{ text: t('Done'), role: 'confirm', handler: () => { data.checkout(listId); cartOpen.value = false; haptic(20) } },
		],
	})
	await alert.present()
}

function toggleView() {
	data.setPrefs(listId, { viewMode: viewMode.value === 'list' ? 'grid' : 'list' }).catch(() => {})
}

async function pickStore() {
	const sheet = await actionSheetController.create({
		header: t('Which store are you shopping in?'),
		buttons: [
			...stores.value.map((s) => ({
				text: `${s.image ? '' : (s.icon ?? '🏪') + ' '}${s.name}${s.id === activeStore.value?.id ? ' ✓' : ''}`,
				handler: () => { data.setPrefs(listId, { activeStoreId: s.id }).catch(() => {}) },
			})),
			{ text: `${t('Default order')}${activeStore.value ? '' : ' ✓'}`, handler: () => { data.setPrefs(listId, { activeStoreId: null }).catch(() => {}) } },
		],
	})
	await sheet.present()
}

/** The ⋯ menu (no cancel entry: tap outside to close). */
async function openMenu() {
	const sheet = await actionSheetController.create({
		buttons: [
			{ text: t('History'), handler: () => { router.push(`/list/${listId}/history`) } },
			{ text: t('Statistics'), handler: () => { router.push(`/list/${listId}/stats`) } },
			{ text: t('Stores & aisle order'), handler: () => { router.push(`/list/${listId}/stores`) } },
			{ text: t('List settings'), handler: () => { router.push(`/list/${listId}/settings`) } },		],
	})
	await sheet.present()
}
</script>

<style scoped>
.store-bar {
	padding: 4px 12px 0;
	display: flex;
	justify-content: flex-end;
}
.store-chip {
	display: inline-flex;
	align-items: center;
	gap: 8px;
	padding: 6px 12px 6px 8px;
	border: none;
	border-radius: 999px;
	background: var(--app-overlay);
	color: var(--ion-text-color);
	font: inherit;
	font-size: 15px;
	font-weight: 600;
}
.dept .emoji {
	font-size: 20px;
}
.section-title.toggle {
	width: calc(100% - 32px);
	border: none;
	background: transparent;
	font-family: inherit;
	padding: 0;
	text-align: start;
	cursor: pointer;
}
ion-list.cards {
	background: transparent;
	padding: 0 12px;
}
ion-list.cards.first {
	margin-top: 12px;
}
.tile-grid.first {
	margin-top: 12px;
}
.tile-grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
	gap: 6px;
	padding: 0 12px;
}
.empty {
	text-align: center;
	padding: 48px 24px 8px;
}
.empty .big {
	font-size: 64px;
}
.empty p {
	color: var(--app-muted);
}
.fab-spacer {
	height: calc(96px + var(--ion-safe-area-bottom, 0px));
}
.fab-spacer.sheet-open {
	height: 100vh;
}
/* the toolbar slides up out of view while the add sheet is open (more room for the list) */
.list-header {
	transition: transform 0.28s ease, opacity 0.28s ease;
}
.list-header.slid-out {
	transform: translateY(-100%);
	opacity: 0;
	pointer-events: none;
}
</style>
