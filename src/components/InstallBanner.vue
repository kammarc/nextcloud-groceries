<template>
	<ion-card v-if="visible" class="install">
		<ion-card-content>
			<div class="row">
				<img :src="iconUrl" alt="" class="app-icon">
				<div class="text">
					<strong>{{ t('Install the app') }}</strong>
					<p v-if="ios">{{ t('Add Groceries to your home screen: tap {share} and then "Add to Home Screen".', { share: '⎋' }) }}</p>
					<p v-else>{{ t('Get the full-screen app with one tap.') }}</p>
				</div>
				<ion-button fill="clear" size="small" :aria-label="t('Close')" @click="dismiss">
					<ion-icon slot="icon-only" :icon="close" />
				</ion-button>
			</div>
			<ion-button v-if="!ios && installPrompt" expand="block" class="ion-margin-top" @click="promptInstall">{{ t('Install') }}</ion-button>
		</ion-card-content>
	</ion-card>
</template>

<script setup lang="ts">
import { IonButton, IonCard, IonCardContent, IonIcon } from '@ionic/vue'
import { close } from 'ionicons/icons'
import { computed, ref } from 'vue'
import { config } from '../config'
import { t } from '../i18n'
import { installPrompt, isIos, isStandalone, promptInstall } from '../lib/platform'

const KEY = 'groceries:install-dismissed'
const dismissed = ref(false)
try {
	dismissed.value = localStorage.getItem(KEY) === '1'
} catch {
	// ignore
}

const ios = isIos()
const iconUrl = config.imgUrl + 'apple-touch-icon.png'
const visible = computed(() => !dismissed.value && !isStandalone() && (ios || installPrompt.value !== null))

function dismiss() {
	dismissed.value = true
	try {
		localStorage.setItem(KEY, '1')
	} catch {
		// ignore
	}
}
</script>

<style scoped>
.row {
	display: flex;
	align-items: center;
	gap: 12px;
}
.app-icon {
	width: 48px;
	height: 48px;
	border-radius: 11px;
}
.text {
	flex: 1;
}
.text p {
	margin: 2px 0 0;
}
</style>
