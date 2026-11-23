<template>
	<ion-page>
		<ion-header :translucent="true">
			<ion-toolbar>
				<ion-buttons slot="start">
					<ion-back-button :default-href="`/list/${listId}`" />
				</ion-buttons>
				<ion-title>{{ t('Statistics') }}</ion-title>
				<ion-buttons slot="end">
					<ion-button :aria-label="t('Import')" :disabled="readonly" @click="fileInput?.click()">
						<ion-icon slot="icon-only" :icon="cloudUploadOutline" />
					</ion-button>
					<ion-button :aria-label="t('Export')" :disabled="!events.length" @click="exportJson">
						<ion-icon slot="icon-only" :icon="downloadOutline" />
					</ion-button>
				</ion-buttons>
			</ion-toolbar>
			<ion-toolbar>
				<ion-segment :value="period" @ionChange="period = $event.detail.value as Period">
					<ion-segment-button value="week"><ion-label>{{ t('Week') }}</ion-label></ion-segment-button>
					<ion-segment-button value="month"><ion-label>{{ t('Month') }}</ion-label></ion-segment-button>
					<ion-segment-button value="year"><ion-label>{{ t('Year') }}</ion-label></ion-segment-button>
					<ion-segment-button value="all"><ion-label>{{ t('All time') }}</ion-label></ion-segment-button>
				</ion-segment>
			</ion-toolbar>
		</ion-header>

		<ion-content>
			<input ref="fileInput" type="file" accept="application/json,.json" class="hidden" @change="importJson">

			<div v-if="loading" class="ion-text-center ion-padding"><ion-spinner /></div>

			<div v-else-if="!stats.totalBought && !stats.totalAdded" class="empty">
				<div class="big">📊</div>
				<p>{{ t('No purchases in this period yet.') }}</p>
			</div>

			<template v-else>
				<div class="cards">
					<div class="card"><span class="num">{{ stats.totalBought }}</span><span class="lbl">{{ t('Articles bought') }}</span></div>
					<div class="card"><span class="num">{{ stats.trips }}</span><span class="lbl">{{ t('Shopping trips') }}</span></div>
					<div class="card"><span class="num">{{ stats.perTrip }}</span><span class="lbl">{{ t('Articles per trip') }}</span></div>
					<div class="card"><span class="num">{{ stats.newItems }}</span><span class="lbl">{{ t('New articles') }}</span></div>
				</div>

				<h3 v-if="stats.topItems.length" class="section-title">{{ t('Most bought') }}</h3>
				<div v-if="stats.topItems.length" class="panel">
					<div v-for="(r, i) in stats.topItems" :key="r.normName" class="bar-row">
						<span class="rank">{{ i + 1 }}</span>
						<ItemIcon :icon="r.icon" :catalog-key="r.catalogKey" :category="r.category" :name="r.name" />
						<span class="bar-label">{{ r.name }}</span>
						<span class="bar"><span class="fill" :style="{ width: pct(r.count, stats.topItems[0].count) }" /></span>
						<span class="count">{{ r.count }}×</span>
					</div>
				</div>

				<h3 v-if="stats.users.length" class="section-title">{{ t('Who does what') }}</h3>
				<div v-if="stats.users.length" class="panel">
					<div class="user-head">
						<span />
						<span>{{ t('added') }}</span>
						<span>{{ t('bought') }}</span>
						<span>{{ t('trips') }}</span>
					</div>
					<div v-for="u in stats.users" :key="u.user" class="user-row">
						<span class="who">
							<strong>{{ person(u.user) }}</strong>
							<small v-if="u.favorite">♥ {{ u.favorite.name }}</small>
						</span>
						<span>{{ u.added }}</span>
						<span>{{ u.bought }}</span>
						<span>{{ u.trips }}</span>
					</div>
				</div>

				<h3 v-if="stats.categories.length" class="section-title">{{ t('Departments') }}</h3>
				<div v-if="stats.categories.length" class="panel">
					<div v-for="c in stats.categories.slice(0, 8)" :key="c.key" class="bar-row">
						<span class="emoji">{{ categoryLabel(c.key, custom, lang).icon }}</span>
						<span class="bar-label">{{ categoryLabel(c.key, custom, lang).name }}</span>
						<span class="bar"><span class="fill alt" :style="{ width: pct(c.count, stats.categories[0].count) }" /></span>
						<span class="count">{{ c.count }}</span>
					</div>
				</div>

				<h3 v-if="stats.totalBought" class="section-title">{{ t('Shopping days') }}</h3>
				<div v-if="stats.totalBought" class="panel">
					<div class="week">
						<div v-for="(count, i) in stats.weekdays" :key="i" class="day">
							<span class="col"><span class="col-fill" :style="{ height: pct(count, Math.max(...stats.weekdays)) }" /></span>
							<span class="day-name">{{ weekdayNames[i] }}</span>
						</div>
					</div>
					<p v-if="stats.busiestDay" class="fact">
						{{ t('Biggest shopping day: {date} with {count} articles', { date: formatDate(stats.busiestDay.date), count: stats.busiestDay.count }) }}
					</p>
					<p class="fact">{{ t('{days} days with purchases, {added} articles put on the list', { days: stats.shoppingDays, added: stats.totalAdded }) }}</p>
				</div>

				<!-- this tab's numbers as the year-review story -->
				<ion-button v-if="stats.totalBought" expand="block" class="story-button" @click="playStory">
					<ion-icon slot="start" :icon="playCircleOutline" />
					{{ t('Play as story') }}
				</ion-button>
			</template>
		</ion-content>
	</ion-page>
</template>

<script setup lang="ts">
import {
	IonBackButton, IonButton, IonButtons, IonContent, IonHeader, IonIcon, IonLabel, IonPage, IonSegment,
	IonSegmentButton, IonSpinner, IonTitle, IonToolbar, onIonViewDidEnter, toastController,
} from '@ionic/vue'
import { cloudUploadOutline, downloadOutline, playCircleOutline } from 'ionicons/icons'
import { computed, ref } from 'vue'
import { useRoute } from 'vue-router'
import ItemIcon from '../components/ItemIcon.vue'
import { api } from '../api/client'
import { lang, t } from '../i18n'
import { fileSlug, saveJsonFile, today } from '../lib/file'
import { openStatsStory } from '../lib/review'
import { categoryLabel } from '../lib/sorting'
import { buildExport, computeStats, parseImport, periodRange, type Period, type StatEvent } from '../lib/stats'
import { provideSketchIcons } from '../lib/sketch'
import { useData } from '../stores/data'

const route = useRoute()
const data = useData()
const listId = Number(route.params.id)
provideSketchIcons(listId)

const period = ref<Period>('month')
const events = ref<StatEvent[]>([])
const loading = ref(true)
const fileInput = ref<HTMLInputElement | null>(null)

const list = computed(() => data.list(listId))
const readonly = computed(() => list.value?.permission === 'view')
const custom = computed(() => data.state(listId).meta?.categories ?? [])
const stats = computed(() => {
	const { from, to } = periodRange(period.value)
	return computeStats(events.value, from, to)
})

const weekdayNames = Array.from({ length: 7 }, (_, i) => new Intl.DateTimeFormat(lang, { weekday: 'short' }).format(new Date(2024, 0, 1 + i)))
const dateFmt = new Intl.DateTimeFormat(lang, { day: 'numeric', month: 'long', year: 'numeric' })
const formatDate = (iso: string) => dateFmt.format(new Date(iso + 'T12:00:00'))
const pct = (v: number, max: number) => `${max ? Math.max(4, Math.round((v / max) * 100)) : 0}%`
const person = (uid: string) => data.state(listId).meta?.people?.[uid] ?? uid

function playStory() {
	openStatsStory(period.value, list.value?.name ?? t('Statistics'), stats.value, data.state(listId).meta?.people ?? {})
}

async function load() {
	loading.value = true
	try {
		events.value = await api<StatEvent[]>('GET', `/lists/${listId}/events`)
	} catch {
		// offline: keep what we have
	} finally {
		loading.value = false
	}
}
onIonViewDidEnter(() => {
	load()
	if (!data.state(listId).meta) data.syncList(listId).catch(() => {})
})

async function toast(message: string) {
	const tst = await toastController.create({ message, duration: 2800, position: 'bottom' })
	await tst.present()
}

async function exportJson() {
	const name = list.value?.name ?? 'list'
	await saveJsonFile(`groceries-${fileSlug(name)}-${today()}.json`, buildExport(name, events.value))
}

async function importJson(ev: Event) {
	const input = ev.target as HTMLInputElement
	const file = input.files?.[0]
	input.value = ''
	if (!file) return
	try {
		const imported = parseImport(await file.text())
		const res = await api<{ imported: number, skipped: number }>('POST', `/lists/${listId}/events`, { events: imported })
		await toast(t('{imported} entries imported, {skipped} skipped', res))
		await load()
	} catch (e) {
		await toast(t('Import failed: {message}', { message: (e as Error).message }))
	}
}
</script>

<style scoped>
.story-button {
	margin: 20px 16px calc(24px + var(--ion-safe-area-bottom, 0px));
	--border-radius: 14px;
	height: 50px;
	font-weight: 700;
}
.hidden {
	display: none;
}
.empty {
	text-align: center;
	padding: 56px 24px;
	color: var(--app-muted);
}
.empty .big {
	font-size: 56px;
}
.cards {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 10px;
	padding: 16px 12px 0;
}
.card {
	display: flex;
	flex-direction: column;
	gap: 2px;
	padding: 14px;
	border-radius: 14px;
	background: var(--app-surface);
}
.card:first-child {
	background: var(--app-buy);
	color: #ffffff;
}
.num {
	font-size: 28px;
	font-weight: 800;
	line-height: 1.1;
}
.lbl {
	font-size: 13px;
	opacity: 0.8;
}
.panel {
	margin: 0 12px;
	padding: 10px 12px;
	border-radius: 14px;
	background: var(--app-surface);
}
.bar-row {
	display: flex;
	align-items: center;
	gap: 10px;
	padding: 6px 0;
}
.rank {
	width: 18px;
	color: var(--app-muted);
	font-weight: 700;
	text-align: end;
}
.emoji {
	width: 30px;
	font-size: 22px;
	text-align: center;
}
.bar-label {
	width: 34%;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}
.bar {
	flex: 1;
	height: 10px;
	border-radius: 5px;
	background: var(--app-overlay);
	overflow: hidden;
}
.fill {
	display: block;
	height: 100%;
	border-radius: 5px;
	background: var(--app-buy);
}
.fill.alt {
	background: var(--app-recent);
}
.count {
	min-width: 34px;
	text-align: end;
	font-variant-numeric: tabular-nums;
	color: var(--app-muted);
}
.user-head,
.user-row {
	display: grid;
	grid-template-columns: 1fr 64px 64px 52px;
	align-items: center;
	gap: 6px;
	padding: 6px 0;
	text-align: center;
}
.user-head {
	font-size: 12px;
	color: var(--app-muted);
}
.user-row + .user-row {
	border-top: 1px solid var(--app-border);
}
.who {
	display: flex;
	flex-direction: column;
	text-align: start;
	min-width: 0;
}
.who small {
	color: var(--app-muted);
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}
.week {
	display: flex;
	justify-content: space-between;
	gap: 6px;
	height: 120px;
	padding-top: 6px;
}
.day {
	display: flex;
	flex: 1;
	flex-direction: column;
	align-items: center;
	gap: 4px;
}
.col {
	display: flex;
	flex: 1;
	align-items: flex-end;
	width: 100%;
	border-radius: 6px;
	background: var(--app-overlay);
	overflow: hidden;
}
.col-fill {
	width: 100%;
	border-radius: 6px;
	background: var(--app-accent);
}
.day-name {
	font-size: 12px;
	color: var(--app-muted);
}
.fact {
	margin: 10px 0 2px;
	font-size: 14px;
	color: var(--app-muted);
}
ion-content {
	--padding-bottom: 32px;
}
</style>
