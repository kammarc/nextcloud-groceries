<template>
	<ion-page>
		<ion-header :translucent="true">
			<ion-toolbar>
				<ion-buttons slot="start">
					<ion-back-button default-href="/" />
				</ion-buttons>
				<ion-title>{{ t('List settings') }}</ion-title>
			</ion-toolbar>
		</ion-header>

		<ion-content v-if="list">
			<ion-list :inset="true">
				<ion-item>
					<ion-input v-model="form.name" :label="t('Name')" label-placement="stacked" :readonly="!canEdit" @ionBlur="save({ name: form.name })" />
				</ion-item>
				<IconButton
					v-if="canEdit"
					:icon="list.icon"
					:image="list.image"
					allow-image
					fallback="🛒"
					:tint="list.image ? null : list.color"
					@update:icon="save({ icon: $event, image: null })"
					@update:image="save({ image: $event })" />
				<div v-if="canEdit" class="colors" role="radiogroup" :aria-label="t('Color')">
					<button
						v-for="c in LIST_COLORS"
						:key="c"
						type="button"
						role="radio"
						class="color"
						:class="{ on: list.color === c }"
						:aria-checked="list.color === c"
						:aria-label="c"
						:style="{ background: c }"
						@click="save({ color: c })" />
				</div>
				<ion-item>
					<ion-toggle :checked="grouped" @ionChange="setGrouped($event.detail.checked)">{{ t('Group by category') }}</ion-toggle>
				</ion-item>
				<ion-item>
					<ion-toggle :checked="showFrequent" @ionChange="setShowFrequent($event.detail.checked)">{{ t('Show frequently bought') }}</ion-toggle>
				</ion-item>
				<ion-item>
					<ion-select v-model="form.autoClearHours" :cancel-text="t('Cancel')" :ok-text="t('OK')" :label="t('Remove ticked items automatically')" label-placement="stacked" :disabled="!canEdit" @ionChange="save({ autoClearHours: form.autoClearHours })">
						<ion-select-option :value="0">{{ t('never') }}</ion-select-option>
						<ion-select-option v-for="h in [1, 3, 6, 12, 24, 48]" :key="h" :value="h">{{ t('after {hours} h', { hours: h }) }}</ion-select-option>
					</ion-select>
				</ion-item>
			</ion-list>

			<ion-list-header>{{ t('Sharing') }}</ion-list-header>
			<ion-list :inset="true">
				<ion-item v-for="m in members" :key="m.type + m.id">
					<ion-icon slot="start" :icon="m.type === 'group' ? peopleOutline : personOutline" />
					<ion-label>
						<h2>{{ m.displayName }}</h2>
						<p v-if="m.type === 'group'">{{ t('Group') }}</p>
					</ion-label>
					<ion-note v-if="m.permission === 'owner' || !isOwner" slot="end">{{ m.permission === 'owner' ? t('Owner') : permLabel(m.permission) }}</ion-note>
					<ion-select v-else slot="end" :value="m.permission" interface="popover" @ionChange="setPermission(m, $event.detail.value)">
						<ion-select-option value="edit">{{ t('can edit') }}</ion-select-option>
						<ion-select-option value="view">{{ t('can view') }}</ion-select-option>
						<ion-select-option value="remove">{{ t('Remove') }}</ion-select-option>
					</ion-select>
				</ion-item>
			</ion-list>

			<template v-if="isOwner">
				<ion-searchbar v-model="search" :placeholder="t('Search users and groups')" :debounce="300" @ionInput="doSearch" />
				<ion-list v-if="results.length" :inset="true">
					<ion-item v-for="r in results" :key="r.type + r.id" button :detail="false" @click="addMember(r)">
						<ion-icon slot="start" :icon="r.type === 'group' ? peopleOutline : personOutline" />
						<ion-label>{{ r.displayName }} <span v-if="r.type === 'group'" class="muted">({{ t('Group') }})</span></ion-label>
						<ion-icon slot="end" :icon="addCircleOutline" color="primary" />
					</ion-item>
				</ion-list>
			</template>

			<ion-list v-if="isShared" :inset="true">
				<ion-item>
					<ion-toggle :checked="showAddedBy" @ionChange="setShowAddedBy($event.detail.checked)">{{ t('Show who added items') }}</ion-toggle>
				</ion-item>
			</ion-list>

			<ion-list :inset="true">
				<ion-item v-if="isOwner" button :detail="false" @click="removeList">
					<ion-icon slot="start" :icon="trashOutline" color="danger" />
					<ion-label color="danger">{{ t('Delete list') }}</ion-label>
				</ion-item>
				<ion-item v-else button :detail="false" @click="leave">
					<ion-icon slot="start" :icon="exitOutline" color="danger" />
					<ion-label color="danger">{{ t('Leave list') }}</ion-label>
				</ion-item>
			</ion-list>
		</ion-content>
	</ion-page>
</template>

<script setup lang="ts">
import {
	alertController, IonBackButton, IonButtons, IonContent, IonHeader, IonIcon, IonInput, IonItem, IonLabel, IonList,
	IonListHeader, IonNote, IonPage, IonSearchbar, IonSelect, IonSelectOption, IonTitle, IonToggle, IonToolbar,
	onIonViewDidEnter, toastController,
} from '@ionic/vue'
import { addCircleOutline, exitOutline, peopleOutline, personOutline, trashOutline } from 'ionicons/icons'
import { computed, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from '../api/client'
import IconButton from '../components/IconButton.vue'
import { LIST_COLORS } from '../lib/listColors'
import { t } from '../i18n'
import { useData } from '../stores/data'
import type { ListSummary, Member } from '../types'

const route = useRoute()
const router = useRouter()
const data = useData()
const listId = Number(route.params.id)

const list = computed(() => data.list(listId))
const isOwner = computed(() => list.value?.permission === 'owner')
const canEdit = computed(() => list.value?.permission === 'owner' || list.value?.permission === 'edit')
const form = reactive({ name: '', icon: '', autoClearHours: 12 })
watch(list, (l) => {
	if (!l) return
	form.name = l.name
	form.icon = l.icon ?? ''
	form.autoClearHours = l.autoClearHours
}, { immediate: true })

const members = ref<Member[]>([])
const search = ref('')
const results = ref<Array<{ type: 'user' | 'group', id: string, displayName: string }>>([])

const permLabel = (p: string) => (p === 'view' ? t('can view') : t('can edit'))

async function loadMembers() {
	try {
		members.value = await api<Member[]>('GET', `/lists/${listId}/members`)
	} catch {
		// offline
	}
}
onIonViewDidEnter(() => {
	loadMembers()
	// prefs (e.g. "group by category") live in the list state; load it when coming from the overview
	data.syncList(listId).catch(() => {})
})

/** Per person, like list/grid view: show department headings or one big list. */
const grouped = computed(() => data.state(listId).meta?.prefs.grouped ?? true)
function setGrouped(on: boolean) {
	if (on !== grouped.value) data.setPrefs(listId, { grouped: on }).catch(() => {})
}

/** Per person: show the "frequently bought" section in the add-items screen. */
const showFrequent = computed(() => data.state(listId).meta?.prefs.showFrequent ?? true)
function setShowFrequent(on: boolean) {
	if (on !== showFrequent.value) data.setPrefs(listId, { showFrequent: on }).catch(() => {})
}

/** Per person, shared lists only: show "Added by …" under items others put on the list (off by default). */
const isShared = computed(() => members.value.length > 1)
const showAddedBy = computed(() => data.state(listId).meta?.prefs.showAddedBy ?? false)
function setShowAddedBy(on: boolean) {
	if (on !== showAddedBy.value) data.setPrefs(listId, { showAddedBy: on }).catch(() => {})
}

async function toast(message: string) {
	const tst = await toastController.create({ message, duration: 2500 })
	await tst.present()
}

async function save(patch: Partial<ListSummary>) {
	if (!canEdit.value || !list.value) return
	if (patch.name !== undefined && (!patch.name.trim() || patch.name === list.value.name)) return
	if (patch.autoClearHours !== undefined && patch.autoClearHours === list.value.autoClearHours) return
	try {
		await data.updateList(listId, patch)
	} catch (e) {
		toast(t('Could not save: {message}', { message: (e as Error).message }))
	}
}

async function putMembers(next: Array<{ type: string, id: string, permission: string }>) {
	try {
		members.value = await api<Member[]>('PUT', `/lists/${listId}/members`, { members: next })
		data.syncList(listId).catch(() => {})
	} catch (e) {
		toast(t('Could not save: {message}', { message: (e as Error).message }))
	}
}

const others = () => members.value.filter((m) => m.permission !== 'owner').map((m) => ({ type: m.type, id: m.id, permission: m.permission }))

function setPermission(m: Member, value: string) {
	const rest = others().filter((x) => !(x.type === m.type && x.id === m.id))
	putMembers(value === 'remove' ? rest : [...rest, { type: m.type, id: m.id, permission: value }])
}

function addMember(r: { type: 'user' | 'group', id: string }) {
	search.value = ''
	results.value = []
	if (members.value.some((m) => m.type === r.type && m.id === r.id)) return
	putMembers([...others(), { type: r.type, id: r.id, permission: 'edit' }])
}

async function doSearch() {
	const q = search.value.trim()
	if (q.length < 1) {
		results.value = []
		return
	}
	try {
		results.value = await api('GET', `/principals?search=${encodeURIComponent(q)}`)
	} catch {
		results.value = []
	}
}

function confirm(header: string, message: string, action: string): Promise<boolean> {
	return new Promise((resolve) => {
		alertController.create({
			header,
			message,
			buttons: [
				{ text: t('Cancel'), role: 'cancel', handler: () => resolve(false) },
				{ text: action, role: 'destructive', handler: () => resolve(true) },
			],
		}).then((a) => a.present())
	})
}

async function removeList() {
	if (!list.value || !(await confirm(t('Delete list'), t('Delete "{name}" with all items? This cannot be undone.', { name: list.value.name }), t('Delete')))) return
	await data.deleteList(listId)
	router.replace('/')
}

async function leave() {
	if (!list.value || !(await confirm(t('Leave list'), list.value.name, t('Leave list')))) return
	await data.leaveList(listId)
	router.replace('/')
}
</script>

<style scoped>
.colors {
	display: flex;
	gap: 12px;
	padding: 4px 16px 14px;
	background: var(--ion-item-background);
}
.color {
	width: 34px;
	height: 34px;
	border-radius: 50%;
	border: 3px solid transparent;
	box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.12);
}
.color.on {
	border-color: var(--ion-color-primary);
}
.muted {
	color: var(--ion-color-medium);
}
</style>
