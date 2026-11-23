<template>
	<ion-page>
		<ion-header :translucent="true">
			<ion-toolbar>
				<ion-buttons slot="start">
					<ion-back-button :default-href="`/list/${listId}`" />
				</ion-buttons>
				<ion-title>{{ t('History') }}</ion-title>
			</ion-toolbar>
			<ion-toolbar>
				<ion-segment v-model="mode">
					<ion-segment-button value="recent">
						<ion-label>{{ t('Recently used') }}</ion-label>
					</ion-segment-button>
					<ion-segment-button value="frequent">
						<ion-label>{{ t('Frequently bought') }}</ion-label>
					</ion-segment-button>
				</ion-segment>
			</ion-toolbar>
		</ion-header>
		<ion-content>
			<ion-refresher slot="fixed" @ionRefresh="refresh">
				<ion-refresher-content />
			</ion-refresher>
			<p v-if="!entries.length" class="ion-padding ion-text-center">{{ t('No history yet.') }}</p>
			<ion-list v-else>
				<ion-item-sliding v-for="h in entries" :key="h.normName">
					<ion-item button :detail="false" @click="add(h)">
						<ItemIcon slot="start" :icon="h.icon" :catalog-key="h.catalogKey" :category="h.category" :name="h.name" />
						<ion-label>
							<h2>{{ h.name }}</h2>
							<p>{{ categoryLabel(h.category, custom, lang).name }} · {{ h.useCount }}× · {{ formatDate(h.lastUsedAt) }}</p>
						</ion-label>
						<ion-icon v-if="onList.has(h.normName)" slot="end" :icon="checkmarkCircle" color="success" />
						<ion-icon v-else slot="end" :icon="addCircleOutline" color="primary" />
					</ion-item>
					<ion-item-options side="end">
						<ion-item-option color="danger" @click="forget(h)">{{ t('Forget') }}</ion-item-option>
					</ion-item-options>
				</ion-item-sliding>
			</ion-list>
		</ion-content>
	</ion-page>
</template>

<script setup lang="ts">
import {
	IonBackButton, IonButtons, IonContent, IonHeader, IonIcon, IonItem, IonItemOption, IonItemOptions, IonItemSliding,
	IonLabel, IonList, IonPage, IonRefresher, IonRefresherContent, IonSegment, IonSegmentButton, IonTitle, IonToolbar,
	onIonViewDidEnter, toastController,
} from '@ionic/vue'
import { addCircleOutline, checkmarkCircle } from 'ionicons/icons'
import { computed, ref } from 'vue'
import { useRoute } from 'vue-router'
import ItemIcon from '../components/ItemIcon.vue'
import { lang, t } from '../i18n'
import { categoryLabel } from '../lib/sorting'
import { haptic } from '../lib/platform'
import { provideSketchIcons } from '../lib/sketch'
import { useData } from '../stores/data'
import type { HistoryEntry } from '../types'

const route = useRoute()
const data = useData()
const listId = Number(route.params.id)
provideSketchIcons(listId)
const mode = ref<'recent' | 'frequent'>('recent')

const state = computed(() => data.state(listId))
const custom = computed(() => state.value.meta?.categories ?? [])
const onList = computed(() => new Set(data.items(listId).filter((i) => !i.checked).map((i) => i.normName)))
const entries = computed(() => [...state.value.history].sort((a, b) => mode.value === 'recent'
	? b.lastUsedAt - a.lastUsedAt
	: b.useCount - a.useCount || b.lastUsedAt - a.lastUsedAt))

const dateFmt = new Intl.DateTimeFormat(lang, { day: 'numeric', month: 'short' })
const formatDate = (ts: number) => dateFmt.format(new Date(ts * 1000))

onIonViewDidEnter(() => { data.loadHistory(listId).catch(() => {}) })

async function refresh(ev: CustomEvent) {
	try {
		await data.loadHistory(listId)
	} catch {
		// offline
	} finally {
		(ev.target as any).complete()
	}
}

async function add(h: HistoryEntry) {
	if (onList.value.has(h.normName)) return
	data.addItem(listId, { name: h.name, category: h.category, icon: h.icon })
	haptic()
	const toast = await toastController.create({ message: t('Added {name}', { name: h.name }), duration: 900, position: 'bottom' })
	await toast.present()
}

function forget(h: HistoryEntry) {
	if (h.id > 0) data.forgetHistory(listId, h.id).catch(() => {})
}
</script>
