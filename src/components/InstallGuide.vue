<template>
	<ion-modal :is-open="isOpen" @didDismiss="emit('close')">
		<ion-header>
			<ion-toolbar>
				<ion-title>{{ t('Install as app') }}</ion-title>
				<ion-buttons slot="end">
					<ion-button @click="emit('close')">{{ t('Done') }}</ion-button>
				</ion-buttons>
			</ion-toolbar>
		</ion-header>
		<ion-content class="ion-padding">
			<div class="guide">
				<p v-if="standalone" class="state">✅ {{ t('You are already using the installed app.') }}</p>
				<template v-else>
					<p class="lead">{{ t('Groceries can be installed like a normal app: with its own icon, full screen and without the browser bar.') }}</p>
					<ion-button v-if="installPrompt" expand="block" class="now" @click="promptInstall">{{ t('Install now') }}</ion-button>
				</template>

				<section v-for="s in sections" :key="s.key" :class="{ current: s.key === current }">
					<h2>
						<span aria-hidden="true">{{ s.emoji }}</span> {{ s.title }}
						<span v-if="s.key === current" class="badge">{{ t('This device') }}</span>
					</h2>
					<div v-for="b in s.browsers" :key="b.name" class="browser">
						<strong>{{ b.name }}</strong>
						<p>{{ b.steps }}</p>
					</div>
				</section>

				<p class="tip">{{ t('Then open Groceries from the home screen or app menu and log in once.') }}</p>
			</div>
		</ion-content>
	</ion-modal>
</template>

<script setup lang="ts">
import { IonButton, IonButtons, IonContent, IonHeader, IonModal, IonTitle, IonToolbar, isPlatform } from '@ionic/vue'
import { t } from '../i18n'
import { installPrompt, isIos, isStandalone, promptInstall } from '../lib/platform'

defineProps<{ isOpen: boolean }>()
const emit = defineEmits<{ close: [] }>()

const standalone = isStandalone()
const current = isIos() ? 'ios' : isPlatform('android') ? 'android' : 'desktop'

const sections = [
	{
		key: 'ios',
		emoji: '📱',
		title: t('iPhone & iPad'),
		browsers: [
			{ name: 'Safari', steps: t('Tap Share (⎋) → "Add to Home Screen" → Add.') },
		],
	},
	{
		key: 'android',
		emoji: '🤖',
		title: 'Android',
		browsers: [
			{ name: 'Chrome', steps: t('Menu ⋮ → "Install app" or "Add to home screen".') },
			{ name: 'Firefox', steps: t('Menu ⋮ → "Install" or "Add to home screen".') },
			{ name: 'Samsung Internet', steps: t('Menu ≡ → "Add page to" → "Home screen".') },
		],
	},
	{
		key: 'desktop',
		emoji: '💻',
		title: t('Computer'),
		browsers: [
			{ name: 'Chrome / Edge', steps: t('Click the install icon at the right of the address bar, or menu ⋮ → "Cast, save and share" → "Install page as app" (Edge: ⋯ → Apps → "Install this site as an app").') },
			{ name: 'Safari (Mac)', steps: t('File → "Add to Dock".') },
			{ name: 'Firefox', steps: t('Firefox cannot install web apps on computers. Use Chrome, Edge or Safari for this.') },
		],
	},
]
// the guide for this device first
sections.sort((a, b) => (a.key === current ? -1 : b.key === current ? 1 : 0))
</script>

<style scoped>
.guide {
	max-width: 560px;
	margin: 0 auto;
	padding-bottom: calc(var(--ion-safe-area-bottom, 0px) + 16px);
}
.lead,
.state {
	margin: 0 0 16px;
}
.now {
	margin-bottom: 16px;
}
section {
	padding: 12px 14px;
	margin-bottom: 12px;
	border-radius: 12px;
	background: var(--app-surface-2, var(--ion-item-background));
	border: 2px solid transparent;
}
section.current {
	border-color: var(--app-accent);
}
h2 {
	display: flex;
	align-items: center;
	gap: 6px;
	margin: 0 0 8px;
	font-size: 17px;
	font-weight: 700;
}
.badge {
	margin-inline-start: auto;
	padding: 2px 8px;
	border-radius: 10px;
	font-size: 12px;
	font-weight: 600;
	background: var(--app-accent);
	color: #fff;
}
.browser + .browser {
	margin-top: 8px;
}
.browser p {
	margin: 2px 0 0;
	color: var(--app-muted);
}
.tip {
	color: var(--app-muted);
	font-size: 14px;
}
</style>
