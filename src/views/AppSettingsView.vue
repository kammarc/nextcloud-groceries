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
						<ion-select-option value="de">Deutsch</ion-select-option>
						<ion-select-option value="en">English</ion-select-option>
					</ion-select>
				</ion-item>
				<ion-item>
					<ion-toggle :checked="mode === 'light'" @ionChange="setTheme({ mode: $event.detail.checked ? 'light' : 'dark' })">{{ t('Light design') }}</ion-toggle>
				</ion-item>
				<div class="theme-row">
					<span class="theme-label">{{ t('Colour scheme') }}</span>
					<div class="swatches">
						<button
							v-for="s in schemes"
							:key="s.key"
							type="button"
							class="swatch"
							:class="{ on: theme === s.key }"
							:style="{ background: s.bg }"
							:aria-label="s.label"
							:aria-pressed="theme === s.key"
							@click="setTheme({ theme: s.key })">
							<span class="dot" :style="{ background: s.buy }" />
						</button>
					</div>
				</div>
				<ion-item>
					<ion-toggle :checked="wakeLockEnabled" @ionChange="setWakeLockEnabled($event.detail.checked)">{{ t('Keep screen on while shopping') }}</ion-toggle>
				</ion-item>
			</ion-list>

			<ion-list-header>{{ t('Help') }}</ion-list-header>
			<ion-list :inset="true">
				<ion-item button :detail="true" @click="openWelcome">
					<ion-icon slot="start" :icon="helpCircleOutline" />
					<ion-label>{{ t('Quick guide') }}</ion-label>
				</ion-item>
			</ion-list>
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
	IonListHeader, IonPage, IonSelect, IonSelectOption, IonTitle, IonToggle, IonToolbar,
} from '@ionic/vue'
import { helpCircleOutline, logOutOutline, personCircleOutline } from 'ionicons/icons'
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { logoutDevice } from '../auth/logout'
import PhoneQrCard from '../components/PhoneQrCard.vue'
import { config } from '../config'
import { getLanguagePreference, setLanguagePreference, t, type LanguagePreference } from '../i18n'
import { isDesktopBrowser, isIos, setWakeLockEnabled, wakeLockEnabled } from '../lib/platform'
import { openWelcome } from '../lib/welcome'
import { useData } from '../stores/data'
import { mode, setTheme, theme, type ThemeName } from '../lib/theme'

/** Swatch = page colour of the scheme with a dot in its "to buy" colour. */
const schemes: Array<{ key: ThemeName, label: string, bg: string, buy: string }> = [
	{ key: 'blue', label: t('Blue'), bg: '#13283f', buy: '#ef7350' },
	{ key: 'red', label: t('Red'), bg: '#3a1520', buy: '#e39a35' },
	{ key: 'green', label: t('Green'), bg: '#153b33', buy: '#e8833a' },
	{ key: 'purple', label: t('Purple'), bg: '#271a3a', buy: '#e65a73' },
]

const data = useData()
const router = useRouter()
const desktop = isDesktopBrowser()
const ios = isIos()
const busy = ref(false)
const language = ref<LanguagePreference>(getLanguagePreference())
const serverHost = window.location.host

function changeLanguage(value: LanguagePreference) {
	if (value === language.value) return
	language.value = value
	setLanguagePreference(value)
	// texts are resolved at startup; a reload applies the new language everywhere
	window.location.reload()
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
	box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.25);
}
.swatch.on {
	border-color: var(--app-accent);
}
.dot {
	position: absolute;
	right: 2px;
	bottom: 2px;
	width: 12px;
	height: 12px;
	border-radius: 50%;
}
.version {
	text-align: center;
	color: var(--ion-color-medium);
	font-size: 12px;
	padding: 8px 0 24px;
}
</style>
