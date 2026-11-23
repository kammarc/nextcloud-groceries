<template>
	<ion-page>
		<ion-header :translucent="true">
			<ion-toolbar>
				<!-- in a browser (not the installed app): way back to the rest of Nextcloud -->
				<ion-buttons v-if="inBrowser" slot="start">
					<ion-button :href="homeUrl" :aria-label="t('Back to Nextcloud')" class="to-nextcloud">
						<ion-icon slot="start" :icon="chevronBack" />
						<span class="label">Nextcloud</span>
					</ion-button>
				</ion-buttons>
				<ion-title>{{ t('Grocery lists') }}</ion-title>
				<ion-buttons slot="end">
					<ion-button
						v-if="data.lists.length"
						:aria-label="editing ? t('Done') : t('Edit lists')"
						:aria-pressed="editing"
						:class="{ 'edit-on': editing }"
						@click="toggleEdit">
						<ion-icon slot="icon-only" :icon="editing ? checkmark : createOutline" />
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
			<!-- on a computer: QR code to bring the app to the phone -->
			<PhoneQrCard v-if="desktop" dismissible />

			<!-- edit mode: drag the handles to sort, delete (or leave) lists; tapping a list does nothing -->
			<ion-list v-if="editing && data.lists.length" :inset="true" class="editing">
				<ion-reorder-group :disabled="data.lists.length < 2" @ionItemReorder="onReorder">
					<ion-item v-for="list in data.lists" :key="list.id">
						<ion-button
							slot="start"
							fill="clear"
							color="danger"
							class="remove"
							:aria-label="list.permission === 'owner' ? t('Delete list') : t('Leave list')"
							@click="removeList(list)">
							<ion-icon slot="icon-only" :icon="list.permission === 'owner' ? trashOutline : exitOutline" />
						</ion-button>
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
	alertController, IonButton, IonButtons, IonContent, IonHeader, IonIcon, IonItem, IonItemOption, IonItemOptions, IonItemSliding,
	IonLabel, IonList, IonModal, IonPage, IonRefresher, IonRefresherContent, IonReorder, IonReorderGroup, IonSpinner,
	IonTitle, IonToolbar, toastController,
	onIonViewDidEnter, onIonViewWillLeave,
} from '@ionic/vue'
import { add, checkmark, chevronBack, createOutline, exitOutline, settingsOutline, trashOutline } from 'ionicons/icons'
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import CreateListPanel from '../components/CreateListPanel.vue'
import AppIcon from '../components/AppIcon.vue'
import InstallBanner from '../components/InstallBanner.vue'
import PhoneQrCard from '../components/PhoneQrCard.vue'
import SyncStatus from '../components/SyncStatus.vue'
import { n, t } from '../i18n'
import { setLastList } from '../lib/lastList'
import { isDesktopBrowser, isStandalone, nextcloudHome } from '../lib/platform'
import { showWelcomeOnce } from '../lib/welcome'
import { useData } from '../stores/data'
import { usePolling } from '../sync/polling'
import { checkYearReview } from '../lib/review'
import type { ListSummary } from '../types'

const data = useData()
const router = useRouter()
const creating = ref(false)
const editing = ref(false)
const inBrowser = !isStandalone()
const desktop = isDesktopBrowser()
const homeUrl = nextcloudHome()

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
	// first start on this device: short introduction; otherwise, from 15 December, the yearly review
	if (!showWelcomeOnce()) checkYearReview()
})
onIonViewWillLeave(() => {
	polling.stop()
	editing.value = false
})

/** Edit mode on/off; background refreshes pause meanwhile so nothing jumps under the finger. */
function toggleEdit() {
	editing.value = !editing.value
	if (editing.value) polling.stop()
	else polling.start()
}

/** Owners delete the list for everybody; members only leave it. */
async function removeList(list: ListSummary) {
	const owner = list.permission === 'owner'
	const confirmed = await new Promise<boolean>((resolve) => {
		alertController.create({
			header: owner ? t('Delete list') : t('Leave list'),
			message: owner ? t('Delete "{name}" with all items? This cannot be undone.', { name: list.name }) : list.name,
			buttons: [
				{ text: t('Cancel'), role: 'cancel', handler: () => resolve(false) },
				{ text: owner ? t('Delete') : t('Leave list'), role: 'destructive', handler: () => resolve(true) },
			],
		}).then((a) => a.present())
	})
	if (!confirmed) return
	try {
		if (owner) await data.deleteList(list.id)
		else await data.leaveList(list.id)
	} catch (e) {
		const tst = await toastController.create({ message: (e as Error).message, duration: 3500, position: 'bottom' })
		await tst.present()
		return
	}
	if (!data.lists.length) toggleEdit()
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
/* narrow screens: only the arrow, the title needs the room */
@media (max-width: 599px) {
	.to-nextcloud .label {
		display: none;
	}
}
.edit-on {
	--color: var(--app-accent);
}
.editing ion-item {
	--background: var(--app-surface-2);
}
.remove {
	margin-inline: -8px 4px;
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
