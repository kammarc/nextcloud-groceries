<template>
	<ion-page>
		<ion-header :translucent="true">
			<ion-toolbar>
				<ion-title>{{ t('Grocery lists') }}</ion-title>
				<ion-buttons slot="end">
					<ion-button
						v-if="data.lists.length > 1"
						:aria-label="reordering ? t('Done') : t('Sort lists')"
						:aria-pressed="reordering"
						:class="{ 'sort-on': reordering }"
						@click="toggleReorder">
						<ion-icon slot="icon-only" :icon="reordering ? checkmark : swapVerticalOutline" />
					</ion-button>
					<ion-button :aria-label="t('Settings')" router-link="/settings">
						<ion-icon slot="icon-only" :icon="settingsOutline" />
					</ion-button>
					<ion-button :aria-label="t('New list')" @click="creating = true">
						<ion-icon slot="icon-only" :icon="add" />
					</ion-button>
				</ion-buttons>
			</ion-toolbar>
		</ion-header>

		<ion-content :fullscreen="true">
			<ion-header collapse="condense">
				<ion-toolbar>
					<ion-title size="large">{{ t('Grocery lists') }}</ion-title>
				</ion-toolbar>
			</ion-header>

			<ion-refresher slot="fixed" @ionRefresh="refresh">
				<ion-refresher-content />
			</ion-refresher>

			<SyncStatus />
			<InstallBanner />

			<!-- sort mode: drag the handles, tapping a list does nothing -->
			<ion-list v-if="reordering && data.lists.length" :inset="true" class="sorting">
				<ion-reorder-group :disabled="false" @ionItemReorder="onReorder">
					<ion-item v-for="list in data.lists" :key="list.id">
						<div slot="start" class="list-icon" :style="{ background: list.image ? 'transparent' : (list.color || 'var(--ion-color-step-100, #e9e9ee)') }"><AppIcon :icon="list.icon" :image="list.image" :size="list.image ? 40 : 26" fallback="🛒" /></div>
						<ion-label><h2>{{ list.name }}</h2></ion-label>
						<ion-reorder slot="end" />
					</ion-item>
				</ion-reorder-group>
			</ion-list>

			<ion-list v-else-if="data.lists.length" :inset="true">
				<ion-item-sliding v-for="list in data.lists" :key="list.id">
					<ion-item button :router-link="`/list/${list.id}`" :detail="true">
						<div slot="start" class="list-icon" :style="{ background: list.image ? 'transparent' : (list.color || 'var(--ion-color-step-100, #e9e9ee)') }"><AppIcon :icon="list.icon" :image="list.image" :size="list.image ? 40 : 26" fallback="🛒" /></div>
						<ion-label>
							<h2>{{ list.name }}</h2>
							<p>
								{{ n(openCount(list.id), 'item', 'items') }}
								<template v-if="list.shared"> · {{ t('shared') }}</template>
								<template v-if="list.permission === 'view'"> · {{ t('read-only') }}</template>
							</p>
						</ion-label>
					</ion-item>
					<ion-item-options side="end">
						<ion-item-option color="medium" @click="router.push(`/list/${list.id}/settings`)">
							<ion-icon slot="icon-only" :icon="settingsOutline" />
						</ion-item-option>
					</ion-item-options>
				</ion-item-sliding>
			</ion-list>

			<div v-else-if="data.listsLoaded" class="empty">
				<div class="big">🛒</div>
				<h2>{{ t('No lists yet') }}</h2>
				<p>{{ t('Create your first shopping list to get started.') }}</p>
				<ion-button @click="creating = true">{{ t('Create list') }}</ion-button>
			</div>

			<div v-else class="ion-text-center ion-padding">
				<ion-spinner />
			</div>
		</ion-content>

		<ion-modal :is-open="creating" :initial-breakpoint="0.75" :breakpoints="[0, 0.75, 1]" @didDismiss="creating = false">
			<CreateListPanel @close="creating = false" @created="onCreated" />
		</ion-modal>
	</ion-page>
</template>

<script setup lang="ts">
import {
	IonButton, IonButtons, IonContent, IonHeader, IonIcon, IonItem, IonItemOption, IonItemOptions, IonItemSliding,
	IonLabel, IonList, IonModal, IonPage, IonRefresher, IonRefresherContent, IonReorder, IonReorderGroup, IonSpinner,
	IonTitle, IonToolbar,
	onIonViewDidEnter, onIonViewWillLeave,
} from '@ionic/vue'
import { add, checkmark, settingsOutline, swapVerticalOutline } from 'ionicons/icons'
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import CreateListPanel from '../components/CreateListPanel.vue'
import AppIcon from '../components/AppIcon.vue'
import InstallBanner from '../components/InstallBanner.vue'
import SyncStatus from '../components/SyncStatus.vue'
import { n, t } from '../i18n'
import { setLastList } from '../lib/lastList'
import { useData } from '../stores/data'
import { usePolling } from '../sync/polling'
import { checkYearReview } from '../lib/review'
import type { ListSummary } from '../types'

const data = useData()
const router = useRouter()
const creating = ref(false)
const reordering = ref(false)

/** Uses the locally projected count when we have the list cached (includes offline changes). */
function openCount(listId: number): number {
	const st = data.states[listId]
	if (st && st.lastSync > 0) return data.items(listId).filter((i) => !i.checked).length
	return data.lists.find((l) => l.id === listId)?.openCount ?? 0
}

const polling = usePolling(() => data.loadLists(), 15000)
onIonViewDidEnter(() => {
	// back on the overview on purpose: the next launch starts here too
	setLastList(null)
	polling.start()
	// from 15 December: announce the yearly review once per device
	checkYearReview()
})
onIonViewWillLeave(() => {
	polling.stop()
	reordering.value = false
})

/** Sort mode on/off; background refreshes pause meanwhile so nothing jumps under the finger. */
function toggleReorder() {
	reordering.value = !reordering.value
	if (reordering.value) polling.stop()
	else polling.start()
}

function onReorder(ev: CustomEvent) {
	const ids: number[] = ev.detail.complete(data.lists.map((l) => l.id))
	data.reorderLists(ids)
}

async function refresh(ev: CustomEvent) {
	try {
		await data.flush()
		await data.loadLists()
	} catch {
		// shown via SyncStatus
	} finally {
		(ev.target as any).complete()
	}
}

function onCreated(list: ListSummary) {
	creating.value = false
	router.push(`/list/${list.id}`)
}
</script>

<style scoped>
.sort-on {
	--color: var(--app-accent);
}
.sorting ion-item {
	--background: var(--app-surface-2);
}
.list-icon {
	width: 40px;
	height: 40px;
	border-radius: 10px;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 24px;
	margin-inline-end: 4px;
}
ion-label h2 {
	font-weight: 600;
}
.empty {
	text-align: center;
	padding: 48px 24px;
}
.empty .big {
	font-size: 64px;
}
</style>
