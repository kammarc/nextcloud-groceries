<template>
	<div v-if="visible" class="phone-card">
		<div class="code">
			<QrCode :text="address" :label="t('QR code with the address of the app')" />
		</div>
		<div class="text">
			<strong>📱 {{ t('Use it on your phone') }}</strong>
			<p>{{ t('Scan the code with the phone camera, open the link and log in once. Then add the app to the home screen:') }}</p>
			<ul>
				<li><b>iPhone:</b> {{ t('Safari → Share → "Add to Home Screen"') }}</li>
				<li><b>Android:</b> {{ t('Chrome → ⋮ → "Install app"') }}</li>
			</ul>
			<p class="address">{{ address }}</p>
		</div>
		<ion-button v-if="dismissible" fill="clear" size="small" class="close" :aria-label="t('Close')" @click="dismiss">
			<ion-icon slot="icon-only" :icon="close" />
		</ion-button>
	</div>
</template>

<script setup lang="ts">
import { IonButton, IonIcon } from '@ionic/vue'
import { close } from 'ionicons/icons'
import { computed, ref } from 'vue'
import QrCode from './QrCode.vue'
import { t } from '../i18n'
import { appAddress } from '../lib/platform'

/** dismissible: card in the lists overview (closing it is remembered on this device) */
const props = defineProps<{ dismissible?: boolean }>()

const KEY = 'groceries:phone-card-dismissed'
const dismissed = ref(false)
if (props.dismissible) {
	try {
		dismissed.value = localStorage.getItem(KEY) === '1'
	} catch {
		// ignore
	}
}
const visible = computed(() => !dismissed.value)
const address = appAddress()

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
.phone-card {
	position: relative;
	display: flex;
	gap: 18px;
	align-items: center;
	margin: 12px 16px;
	padding: 16px 44px 16px 16px;
	border-radius: 14px;
	background: var(--app-surface);
}
.code {
	flex: 0 0 132px;
	padding: 6px;
	border-radius: 10px;
	background: #ffffff;
}
.text {
	min-width: 0;
	font-size: 14px;
	line-height: 1.4;
}
.text strong {
	font-size: 16px;
}
.text p {
	margin: 6px 0;
	color: var(--app-muted);
}
.text ul {
	margin: 0;
	padding-left: 18px;
}
.address {
	font-size: 12px;
	overflow-wrap: anywhere;
	user-select: all;
}
.close {
	position: absolute;
	top: 4px;
	right: 4px;
}
</style>
