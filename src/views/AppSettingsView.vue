<template>
	<ion-page>
		<ion-header :translucent="true">
			<ion-toolbar>
				<ion-buttons slot="start">
					<ion-back-button default-href="/" :text="ios ? t('Lists') : undefined" />
				</ion-buttons>
				<ion-title>{{ t('Settings') }}</ion-title>
			</ion-toolbar>
		</ion-header>

		<ion-content :fullscreen="true">
			<ion-header collapse="condense">
				<ion-toolbar>
					<ion-title size="large">{{ t('Settings') }}</ion-title>
				</ion-toolbar>
			</ion-header>

			<ion-list-header>{{ t('App') }}</ion-list-header>
			<ion-list :inset="true">
				<ion-item>
					<ion-select :value="language" :label="t('Language')" interface="action-sheet" :cancel-text="t('Cancel')" @ionChange="changeLanguage($event.detail.value)">
						<ion-select-option value="auto">{{ t('Automatic (Nextcloud)') }}</ion-select-option>
						<ion-select-option v-for="l in LANGUAGES" :key="l.code" :value="l.code">{{ l.name }}</ion-select-option>
					</ion-select>
				</ion-item>
				<ion-item>
					<ion-toggle :checked="mode === 'light'" @ionChange="setTheme({ mode: $event.detail.checked ? 'light' : 'dark' })">{{ t('Light design') }}</ion-toggle>
				</ion-item>
				<div class="theme-row">
					<span class="theme-label">{{ t('Background') }}</span>
					<div class="swatches" role="radiogroup" :aria-label="t('Background')">
						<button
							v-for="s in schemes"
							:key="s.key"
							type="button"
							role="radio"
							class="swatch"
							:class="{ on: theme === s.key }"
							:style="{ background: mode === 'light' ? s.light : s.dark }"
							:aria-label="s.label"
							:aria-checked="theme === s.key"
							@click="setTheme({ theme: s.key })" />
					</div>
				</div>
				<div class="theme-row">
					<span class="theme-label">{{ t('Entries') }}</span>
					<div class="swatches" role="radiogroup" :aria-label="t('Entries')">
						<button
							v-for="c in entryColors"
							:key="c.key"
							type="button"
							role="radio"
							class="swatch"
							:class="{ on: entries === c.key }"
							:style="{ background: c.color }"
							:aria-label="c.label"
							:aria-checked="entries === c.key"
							@click="setTheme({ entries: c.key })" />
					</div>
				</div>
				<ion-item>
					<ion-toggle :checked="wakeLockEnabled" @ionChange="setWakeLockEnabled($event.detail.checked)">{{ t('Keep screen on while shopping') }}</ion-toggle>
				</ion-item>
			</ion-list>

			<ion-list-header>{{ t('Backup') }}</ion-list-header>
			<ion-list :inset="true">
				<ion-item button :detail="false" :disabled="!!transferring" @click="exportAll">
					<ion-icon slot="start" :icon="downloadOutline" />
					<ion-label>
						<h2>{{ t('Export library') }}</h2>
						<p>{{ t('All lists with items, history, statistics and settings') }}</p>
					</ion-label>
					<ion-spinner v-if="transferring === 'export'" slot="end" name="crescent" />
				</ion-item>
				<ion-item button :detail="false" :disabled="!!transferring" @click="fileInput?.click()">
					<ion-icon slot="start" :icon="cloudUploadOutline" />
					<ion-label>
						<h2>{{ t('Import library') }}</h2>
						<p>{{ t('Restore a library export') }}</p>
					</ion-label>
					<ion-spinner v-if="transferring === 'import'" slot="end" name="crescent" />
				</ion-item>
			</ion-list>
			<input ref="fileInput" type="file" accept="application/json,.json" class="hidden" @change="importAll">

			<ion-list-header>{{ t('Help') }}</ion-list-header>
			<ion-list :inset="true">
				<ion-item button :detail="true" @click="openWelcome">
					<ion-icon slot="start" :icon="helpCircleOutline" />
					<ion-label>{{ t('Quick guide') }}</ion-label>
				</ion-item>
				<ion-item button :detail="true" @click="installGuideOpen = true">
					<ion-icon slot="start" :icon="phonePortraitOutline" />
					<ion-label>{{ t('Install as app') }}</ion-label>
				</ion-item>
				<ion-item button :detail="true" :href="issuesUrl" target="_blank" rel="noopener">
					<ion-icon slot="start" :icon="bugOutline" />
					<ion-label>{{ t('Report a bug') }}</ion-label>
				</ion-item>
			</ion-list>
			<InstallGuide :is-open="installGuideOpen" @close="installGuideOpen = false" />
			<!-- on a computer: QR code to bring the app to the phone -->
			<PhoneQrCard v-if="desktop" />

			<ion-list-header>{{ t('Account') }}</ion-list-header>
			<ion-list :inset="true">
				<ion-item>
					<ion-icon slot="start" :icon="personCircleOutline" />
					<ion-label>
						<h2>{{ data.user?.displayName ?? '–' }}</h2>
						<p>{{ config.serverName }} · {{ serverHost }}</p>
					</ion-label>
				</ion-item>
				<ion-item button :detail="false" :disabled="busy" @click="logout">
					<ion-icon slot="start" :icon="logOutOutline" color="danger" />
					<ion-label color="danger">{{ t('Log out') }}</ion-label>
				</ion-item>
			</ion-list>

			<p class="version">Groceries {{ config.version }}</p>
		</ion-content>
	</ion-page>
</template>

<script setup lang="ts">
import {
	alertController, IonBackButton, IonButtons, IonContent, IonHeader, IonIcon, IonItem, IonLabel, IonList,
	IonListHeader, IonPage, IonSelect, IonSelectOption, IonSpinner, IonTitle, IonToggle, IonToolbar, toastController,
} from '@ionic/vue'
import {
	bugOutline, cloudUploadOutline, downloadOutline, helpCircleOutline, logOutOutline, personCircleOutline, phonePortraitOutline,
} from 'ionicons/icons'
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { logoutDevice } from '../auth/logout'
import InstallGuide from '../components/InstallGuide.vue'
import PhoneQrCard from '../components/PhoneQrCard.vue'
import { config } from '../config'
import { getLanguagePreference, setLanguagePreference, t, type LanguagePreference } from '../i18n'
import { saveJsonFile, today } from '../lib/file'
import { getLastList, setLastList } from '../lib/lastList'
import { applySettings, fetchLibrary, importLibrary, parseLibrary, type ImportMode } from '../lib/library'
import { LANGUAGES } from '../lib/languages'
import { isDesktopBrowser, isIos, setWakeLockEnabled, wakeLockEnabled } from '../lib/platform'
import { openWelcome } from '../lib/welcome'
import { useData } from '../stores/data'
import { entries, mode, setTheme, theme, type EntryColor, type ThemeName } from '../lib/theme'

/** Background swatch = page colour of the scheme in the current light/dark mode. */
const schemes: Array<{ key: ThemeName, label: string, dark: string, light: string }> = [
	{ key: 'blue', label: t('Blue'), dark: '#13283f', light: '#eef3f9' },
	{ key: 'red', label: t('Red'), dark: '#3a1520', light: '#f8eff1' },
	{ key: 'green', label: t('Green'), dark: '#153b33', light: '#eef5f1' },
	{ key: 'purple', label: t('Purple'), dark: '#271a3a', light: '#f3f0f8' },
]
/** Colour of the tiles of items still to buy (theme.css, data-entries). */
const entryColors: Array<{ key: EntryColor, label: string, color: string }> = [
	{ key: 'coral', label: t('Coral'), color: '#ef7350' },
	{ key: 'amber', label: t('Amber'), color: '#e39a35' },
	{ key: 'orange', label: t('Orange'), color: '#e8833a' },
	{ key: 'pink', label: t('Pink'), color: '#e65a73' },
]

const data = useData()
const router = useRouter()
const desktop = isDesktopBrowser()
const ios = isIos()
const busy = ref(false)
const language = ref<LanguagePreference>(getLanguagePreference())
const serverHost = window.location.host
const installGuideOpen = ref(false)
const issuesUrl = 'https://github.com/kammarc/nextcloud-groceries/issues'

function changeLanguage(value: LanguagePreference) {
	if (value === language.value) return
	language.value = value
	setLanguagePreference(value)
	// texts are resolved at startup; a reload applies the new language everywhere
	window.location.reload()
}

const transferring = ref<'export' | 'import' | false>(false)
const fileInput = ref<HTMLInputElement | null>(null)

async function toast(message: string) {
	const tst = await toastController.create({ message, duration: 3500, position: 'bottom' })
	await tst.present()
}

async function exportAll() {
	transferring.value = 'export'
	try {
		const library = await fetchLibrary()
		await saveJsonFile(`groceries-library-${today()}.json`, library)
	} catch (e) {
		await toast(t('Export failed: {message}', { message: (e as Error).message }))
	} finally {
		transferring.value = false
	}
}

function askImportMode(listCount: number): Promise<ImportMode | null> {
	return new Promise((resolve) => {
		alertController.create({
			header: t('Import library'),
			message: t('The file contains {count} lists. Add them to your lists, or replace all lists you own? Replacing deletes your current lists, their history and statistics.', { count: listCount }),
			buttons: [
				{ text: t('Cancel'), role: 'cancel', handler: () => resolve(null) },
				{ text: t('Replace'), role: 'destructive', handler: () => resolve('replace') },
				{ text: t('Add'), handler: () => resolve('add') },
			],
		}).then((a) => a.present())
	})
}

async function importAll(ev: Event) {
	const input = ev.target as HTMLInputElement
	const file = input.files?.[0]
	input.value = ''
	if (!file) return
	let library
	try {
		library = parseLibrary(await file.text())
	} catch (e) {
		await toast(t('Import failed: {message}', { message: (e as Error).message }))
		return
	}
	const importMode = await askImportMode(library.lists.length)
	if (!importMode) return
	transferring.value = 'import'
	try {
		const res = await importLibrary(library, importMode)
		// drop what this device still caches of lists that are gone now
		const lists = await data.loadLists()
		const ids = new Set(lists.map((l) => l.id))
		for (const id of Object.keys(data.states).map(Number)) if (!ids.has(id)) data.forgetList(id)
		const last = getLastList()
		if (last && !ids.has(last)) setLastList(null)
		const reload = applySettings(library.settings)
		language.value = getLanguagePreference()
		await toast(t('{lists} lists with {items} items imported', { lists: res.lists, items: res.items }))
		if (reload) window.location.reload()
	} catch (e) {
		await toast(t('Import failed: {message}', { message: (e as Error).message }))
	} finally {
		transferring.value = false
	}
}

async function logout() {
	const confirmed = await new Promise<boolean>((resolve) => {
		alertController.create({
			header: t('Log out'),
			message: t('Log out of Nextcloud on this device? You will need to log in again.'),
			buttons: [
				{ text: t('Cancel'), role: 'cancel', handler: () => resolve(false) },
				{ text: t('Log out'), role: 'destructive', handler: () => resolve(true) },
			],
		}).then((a) => a.present())
	})
	if (!confirmed) return
	busy.value = true
	await logoutDevice()
	await data.reset()
	busy.value = false
	// stay in the page: an immediate reload would hit Nextcloud with the just-invalidated
	// session cookie and get redirected to its own login page
	router.replace('/connect')
}
</script>

<style scoped>
.theme-row {
	display: flex;
	align-items: center;
	gap: 12px;
	padding: 12px 16px;
	background: var(--ion-item-background);
	border-bottom: 1px solid var(--app-border);
}
.theme-label {
	flex: 1;
}
.swatches {
	display: flex;
	gap: 10px;
}
.swatch {
	position: relative;
	width: 38px;
	height: 38px;
	border: 3px solid transparent;
	border-radius: 50%;
	box-shadow: inset 0 0 0 1px rgba(var(--app-text-rgb), 0.25);
}
.swatch.on {
	border-color: var(--app-accent);
}
.hidden {
	display: none;
}
.version {
	text-align: center;
	color: var(--ion-color-medium);
	font-size: 12px;
	padding: 8px 0 24px;
}
</style>
