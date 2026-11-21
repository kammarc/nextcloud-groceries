<template>
	<ion-modal :is-open="welcomeOpen" class="welcome-modal" @didDismiss="closeWelcome">
		<ion-content class="ion-padding">
			<div class="welcome">
				<img :src="iconUrl" alt="" class="logo">
				<h1>{{ t('Welcome!') }}</h1>
				<p class="lead">{{ t('Shared shopping lists for the whole family. This is how it works:') }}</p>

				<ol class="steps">
					<li v-for="s in steps" :key="s.title">
						<span class="emoji" aria-hidden="true">{{ s.emoji }}</span>
						<div>
							<strong>{{ s.title }}</strong>
							<p>{{ s.text }}</p>
						</div>
					</li>
				</ol>

				<!-- on a computer: bring the app to the phone -->
				<PhoneQrCard v-if="desktop" class="phone" />

				<ion-button expand="block" size="large" class="go" @click="closeWelcome">{{ t("Let's go") }}</ion-button>
				<p class="again">{{ t('You can open this guide again in the settings (⚙).') }}</p>
			</div>
		</ion-content>
	</ion-modal>
</template>

<script setup lang="ts">
import { IonButton, IonContent, IonModal } from '@ionic/vue'
import PhoneQrCard from './PhoneQrCard.vue'
import { config } from '../config'
import { t } from '../i18n'
import { isDesktopBrowser } from '../lib/platform'
import { closeWelcome, welcomeOpen } from '../lib/welcome'

const iconUrl = config.imgUrl + 'app-192.png'
const desktop = isDesktopBrowser()

const steps = [
	{ emoji: '🛒', title: t('Lists'), text: t('Create a list with ＋ at the top, e.g. for the weekly shop, the pharmacy or the hardware store.') },
	{ emoji: '➕', title: t('Add items'), text: t('In a list, tap the round ＋ button and search. You can type the amount right away, e.g. "2 l milk".') },
	{ emoji: '✅', title: t('Shopping'), text: t('Tap an item as soon as it is in the cart. A long press edits amount, note and department.') },
	{ emoji: '👨‍👩‍👧', title: t('Together'), text: t('Share a list under ⋯ → List settings. Changes show up for everyone within seconds.') },
	{ emoji: '🏪', title: t('Sorted like your store'), text: t('Under ⋯ → Stores & aisle order you set the order of the departments in your store.') },
]
</script>

<style scoped>
.welcome-modal {
	--width: 100%;
	--height: 100%;
	--border-radius: 0;
}
.welcome {
	max-width: 560px;
	margin: 0 auto;
	padding: calc(var(--ion-safe-area-top, 0px) + 16px) 4px calc(var(--ion-safe-area-bottom, 0px) + 16px);
}
.logo {
	display: block;
	width: 72px;
	height: 72px;
	margin: 0 auto 8px;
	border-radius: 18px;
}
h1 {
	margin: 0;
	text-align: center;
	font-size: 28px;
	font-weight: 800;
}
.lead {
	margin: 8px 0 20px;
	text-align: center;
	color: var(--app-muted);
}
.steps {
	margin: 0;
	padding: 0;
	list-style: none;
}
.steps li {
	display: flex;
	gap: 14px;
	align-items: flex-start;
	margin-bottom: 10px;
	padding: 12px 14px;
	border-radius: 14px;
	background: var(--app-surface);
}
.emoji {
	flex: 0 0 auto;
	font-size: 26px;
	line-height: 1.2;
}
.steps p {
	margin: 2px 0 0;
	color: var(--app-muted);
	font-size: 14px;
	line-height: 1.4;
}
.phone {
	margin: 16px 0 0;
}
.go {
	margin-top: 20px;
	font-weight: 700;
}
.again {
	margin: 10px 0 0;
	text-align: center;
	font-size: 13px;
	color: var(--app-muted);
}
</style>
