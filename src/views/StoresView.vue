<template>
	<ion-page>
		<ion-header :translucent="true">
			<ion-toolbar>
				<ion-buttons slot="start">
					<ion-back-button :default-href="`/list/${listId}`" />
				</ion-buttons>
				<ion-title>{{ t('Stores') }}</ion-title>
				<ion-buttons slot="end">
					<ion-button v-if="!readonly" :aria-label="t('New store')" @click="editor = { mode: 'new-store' }">
						<ion-icon slot="icon-only" :icon="add" />
					</ion-button>
				</ion-buttons>
			</ion-toolbar>
			<ion-toolbar v-if="stores.length">
				<div class="store-tabs">
					<button v-for="s in stores" :key="s.id" type="button" class="store-tab" :class="{ on: s.id === selectedId }" @click="selectedId = s.id">
						<AppIcon :icon="s.icon" :image="s.image" :size="22" />
						<span>{{ s.name }}</span>
					</button>
				</div>
			</ion-toolbar>
		</ion-header>

		<ion-content>
			<div v-if="!stores.length" class="empty">
				<div class="big">🏪</div>
				<p>{{ t('Drag the categories into the order you walk through the store.') }}</p>
				<ion-button v-if="!readonly" @click="editor = { mode: 'new-store' }">{{ t('New store') }}</ion-button>
			</div>

			<template v-else-if="selected">
				<ion-list v-if="!readonly" :inset="true">
					<ion-item button :detail="false" @click="useStore">
						<ion-icon slot="start" :icon="selected.id === activeId ? checkmarkCircle : ellipseOutline" color="primary" />
						<ion-label>{{ t('Shopping here') }}</ion-label>
					</ion-item>
				</ion-list>

				<p class="hint">{{ t('Drag the categories into the order you walk through the store.') }}</p>
				<ion-list :inset="true">
					<ion-reorder-group :disabled="readonly" @ionItemReorder="onReorder">
						<ion-item v-for="key in order" :key="key">
							<span slot="start" class="emoji">{{ label(key).icon }}</span>
							<ion-label>{{ label(key).name }}</ion-label>
							<ion-reorder slot="end" />
						</ion-item>
					</ion-reorder-group>
				</ion-list>

				<ion-list v-if="!readonly" :inset="true">
					<ion-item button :detail="false" @click="editor = { mode: 'edit-store' }">
						<ion-icon slot="start" :icon="createOutline" />
						<ion-label>{{ t('Edit store') }}</ion-label>
					</ion-item>
					<ion-item button :detail="false" @click="editor = { mode: 'new-category' }">
						<ion-icon slot="start" :icon="pricetagOutline" />
						<ion-label>{{ t('New category') }}</ion-label>
					</ion-item>
					<ion-item button :detail="false" @click="removeStore">
						<ion-icon slot="start" :icon="trashOutline" color="danger" />
						<ion-label color="danger">{{ t('Delete store') }}</ion-label>
					</ion-item>
				</ion-list>
			</template>
		</ion-content>

		<ion-modal :is-open="editor !== null" @didDismiss="editor = null">
			<NameIconPanel
				v-if="editor?.mode === 'new-store'"
				:title="t('New store')"
				:name-label="t('Store name')"
				initial-icon="🏪"
				allow-image
				@close="editor = null"
				@save="createStore" />
			<NameIconPanel
				v-else-if="editor?.mode === 'edit-store' && selected"
				:title="t('Edit store')"
				:name-label="t('Store name')"
				:initial-name="selected.name"
				:initial-icon="selected.icon"
				:initial-image="selected.image"
				allow-image
				@close="editor = null"
				@save="saveStore" />
			<NameIconPanel
				v-else-if="editor?.mode === 'new-category'"
				:title="t('New category')"
				:name-label="t('Category name')"
				initial-icon="🏷️"
				@close="editor = null"
				@save="createCategory" />
		</ion-modal>
	</ion-page>
</template>

<script setup lang="ts">
import {
	alertController, IonBackButton, IonButton, IonButtons, IonContent, IonHeader, IonIcon, IonItem, IonLabel, IonList,
	IonModal, IonPage, IonReorder, IonReorderGroup, IonTitle, IonToolbar, toastController,
} from '@ionic/vue'
import { add, checkmarkCircle, createOutline, ellipseOutline, pricetagOutline, trashOutline } from 'ionicons/icons'
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import AppIcon from '../components/AppIcon.vue'
import NameIconPanel from '../components/NameIconPanel.vue'
import { lang, t } from '../i18n'
import { categoryLabel, categoryOrder } from '../lib/sorting'
import { useData } from '../stores/data'

type Editor = { mode: 'new-store' | 'edit-store' | 'new-category' }
type Saved = { name: string, icon: string | null, image: string | null }

const route = useRoute()
const data = useData()
const listId = Number(route.params.id)

const meta = computed(() => data.state(listId).meta)
const list = computed(() => data.list(listId))
const readonly = computed(() => list.value?.permission === 'view')
const stores = computed(() => meta.value?.stores ?? [])
const activeId = computed(() => meta.value?.prefs.activeStoreId ?? null)
const custom = computed(() => meta.value?.categories ?? [])
const selectedId = ref<number | null>(activeId.value ?? stores.value[0]?.id ?? null)
watch(stores, (s) => {
	if (!s.find((x) => x.id === selectedId.value)) selectedId.value = s[0]?.id ?? null
})
const selected = computed(() => stores.value.find((s) => s.id === selectedId.value) ?? null)
const order = computed(() => categoryOrder(list.value?.template ?? 'grocery', selected.value, custom.value))
const label = (key: string) => categoryLabel(key, custom.value, lang)
const editor = ref<Editor | null>(null)

async function failed(e: unknown) {
	const toast = await toastController.create({ message: t('Could not save: {message}', { message: (e as Error).message }), duration: 3000 })
	await toast.present()
}

async function createStore(v: Saved) {
	editor.value = null
	try {
		const store = await data.createStore(listId, v.name, v.image ? null : v.icon, order.value, v.image)
		selectedId.value = store.id
	} catch (e) {
		failed(e)
	}
}

async function saveStore(v: Saved) {
	editor.value = null
	if (!selected.value) return
	try {
		await data.updateStore(listId, selected.value.id, { name: v.name, icon: v.image ? null : v.icon, image: v.image })
	} catch (e) {
		failed(e)
	}
}

async function createCategory(v: Saved) {
	editor.value = null
	try {
		await data.createCategory(listId, v.name, v.icon)
	} catch (e) {
		failed(e)
	}
}

async function removeStore() {
	if (!selected.value) return
	const store = selected.value
	const alert = await alertController.create({
		header: t('Delete store'),
		message: store.name,
		buttons: [
			{ text: t('Cancel'), role: 'cancel' },
			{ text: t('Delete'), role: 'destructive', handler: () => { data.deleteStore(listId, store.id).catch(failed) } },
		],
	})
	await alert.present()
}

function useStore() {
	if (selected.value) data.setPrefs(listId, { activeStoreId: selected.value.id }).catch(() => {})
}

function onReorder(ev: CustomEvent) {
	const next = ev.detail.complete([...order.value]) as string[]
	if (selected.value) data.updateStore(listId, selected.value.id, { order: next }).catch(failed)
}
</script>

<style scoped>
.store-tabs {
	display: flex;
	gap: 8px;
	overflow-x: auto;
	padding: 4px 12px 8px;
	scrollbar-width: none;
}
.store-tab {
	display: inline-flex;
	align-items: center;
	gap: 8px;
	flex: 0 0 auto;
	padding: 6px 14px 6px 8px;
	border: 2px solid transparent;
	border-radius: 999px;
	background: var(--app-overlay);
	color: var(--ion-text-color);
	font: inherit;
	font-size: 15px;
}
.store-tab.on {
	border-color: var(--ion-color-primary);
	background: rgba(var(--ion-color-primary-rgb), 0.18);
}
.hint {
	margin: 16px 20px 0;
	color: var(--ion-color-medium);
	font-size: 14px;
}
.emoji {
	font-size: 22px;
	margin-inline-end: 12px;
}
.empty {
	text-align: center;
	padding: 48px 24px;
}
.empty .big {
	font-size: 64px;
}
</style>
