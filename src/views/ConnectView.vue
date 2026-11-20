<template>
	<ion-page>
		<ion-content class="ion-padding" :fullscreen="true">
			<div class="connect">
				<img :src="iconUrl" alt="" class="logo">
				<h1>Groceries</h1>
				<p class="server">{{ config.serverName }}</p>
				<p class="hint">{{ t('Log in once – this device then stays connected.') }}</p>

				<p v-if="!secure" class="insecure">
					⚠️ {{ t('This connection is not encrypted (no HTTPS). Your password and lists could be read by others on the network. Ask the administrator to set up HTTPS.') }}
				</p>

				<ion-button v-if="!flow" expand="block" size="large" :fill="secure ? 'solid' : 'outline'" :disabled="busy" @click="connect">
					<ion-spinner v-if="busy" slot="start" name="crescent" />
					{{ secure ? t('Connect to Nextcloud') : t('Connect anyway') }}
				</ion-button>

				<template v-else>
					<ion-spinner name="dots" />
					<p>{{ t('Waiting for login in the browser window…') }}</p>
					<a :href="flow.loginUrl" target="_blank" rel="noopener" class="manual">{{ t('If no window opened, tap here') }}</a>
					<ion-button fill="clear" @click="cancel">{{ t('Cancel') }}</ion-button>
				</template>

				<p v-if="error" class="error">{{ error }}</p>
			</div>
		</ion-content>
	</ion-page>
</template>

<script setup lang="ts">
import { IonButton, IonContent, IonPage, IonSpinner } from '@ionic/vue'
import { onBeforeUnmount, ref } from 'vue'
import { useRouter } from 'vue-router'
import { config } from '../config'
import { t } from '../i18n'
import { isSecureConnection, pollLoginFlow, startLoginFlow, type LoginFlow } from '../auth/session'
import { useData } from '../stores/data'

const router = useRouter()
const data = useData()
const flow = ref<LoginFlow | null>(null)
const busy = ref(false)
const error = ref('')
const iconUrl = config.imgUrl + 'app-192.png'
const secure = isSecureConnection()
let timer: ReturnType<typeof setInterval> | null = null
let polling = false

// The flow is prepared ahead of time so the tap can open the login page synchronously
// (popup blockers and iOS standalone mode only allow window.open directly in a user gesture).
let prepared: { flow: LoginFlow, at: number } | null = null
async function prepare() {
	try {
		prepared = { flow: await startLoginFlow(), at: Date.now() }
	} catch {
		prepared = null
	}
}
prepare()

async function connect() {
	error.value = ''
	if (!prepared || Date.now() - prepared.at > 10 * 60 * 1000) {
		busy.value = true
		await prepare()
		busy.value = false
		if (!prepared) {
			error.value = t('Login failed. Please try again.')
			return
		}
	}
	flow.value = prepared.flow
	prepared = null
	window.open(flow.value.loginUrl, '_blank')
	timer = setInterval(poll, 2000)
}

async function poll() {
	if (!flow.value || polling) return
	polling = true
	try {
		if (await pollLoginFlow(flow.value)) {
			stop()
			await data.reset()
			router.replace('/')
		}
	} catch {
		stop()
		error.value = t('Login failed. Please try again.')
	} finally {
		polling = false
	}
}

function stop() {
	if (timer) clearInterval(timer)
	timer = null
	flow.value = null
}

function cancel() {
	stop()
	prepare()
}

// iOS pauses timers while the login sheet covers the app: poll as soon as we are visible again
function onVisible() {
	if (document.visibilityState === 'visible') poll()
}
document.addEventListener('visibilitychange', onVisible)
window.addEventListener('focus', onVisible)
onBeforeUnmount(() => {
	stop()
	document.removeEventListener('visibilitychange', onVisible)
	window.removeEventListener('focus', onVisible)
})
</script>

<style scoped>
.connect {
	max-width: 420px;
	min-height: 100%;
	margin: 0 auto;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	text-align: center;
	gap: 8px;
}
.logo {
	width: 96px;
	height: 96px;
	border-radius: 22px;
	box-shadow: 0 6px 24px rgba(0, 0, 0, 0.15);
}
h1 {
	margin: 12px 0 0;
	font-weight: 700;
}
.server {
	margin: 0;
	color: var(--ion-color-medium);
}
.hint {
	margin: 12px 0 20px;
}
ion-button[expand='block'] {
	width: 100%;
}
.manual {
	margin-top: 8px;
}
.insecure {
	margin: 0 0 16px;
	padding: 12px 14px;
	border-radius: 12px;
	background: rgba(220, 60, 60, 0.18);
	color: #ffb4b4;
	font-size: 14px;
	line-height: 1.4;
	text-align: left;
}
.error {
	color: var(--ion-color-danger);
}
</style>
