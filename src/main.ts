import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { IonicVue, toastController } from '@ionic/vue'

import '@ionic/vue/css/core.css'
import '@ionic/vue/css/normalize.css'
import '@ionic/vue/css/structure.css'
import '@ionic/vue/css/typography.css'
import '@ionic/vue/css/padding.css'
import '@ionic/vue/css/text-alignment.css'
import '@ionic/vue/css/flex-utils.css'
import '@ionic/vue/css/display.css'
import './theme.css'

import App from './App.vue'
import { restoreLastList, router } from './router'
import { config } from './config'
import { lang, t } from './i18n'
import { setUnauthorizedHandler } from './api/client'
import { clearCredentials } from './auth/session'
import { useData } from './stores/data'
import { applyTheme } from './lib/theme'

// colour scheme + light/dark mode (per device), before anything renders
applyTheme()

const pinia = createPinia()
const app = createApp(App)
	.use(IonicVue, {
		// platform styling is chosen automatically: iOS look on iPhone/iPad, Material on Android
		swipeBackEnabled: true,
		hardwareBackButton: true,
		backButtonText: t('Back'),
	})
	.use(pinia)
	.use(router)

// correct language for screen readers and automatic hyphenation of long German item names
document.documentElement.lang = lang

const data = useData(pinia)

// device was revoked in Nextcloud (Security → Devices & sessions) or the password changed
setUnauthorizedHandler(async () => {
	await clearCredentials()
	await data.reset()
	if (router.currentRoute.value.path !== '/connect') router.replace('/connect')
})

window.addEventListener('online', () => {
	data.online = true
	data.flush().catch(() => {})
})
window.addEventListener('offline', () => { data.online = false })

data.hydrate().finally(() => {
	router.isReady()
		.then(() => restoreLastList((id) => data.list(id) !== null))
		.catch(() => {})
		.then(() => app.mount('#groceries-app'))
})

// ---- service worker (offline start + "installable" requirement) --------------------------------
const secure = location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1'
if ('serviceWorker' in navigator && secure) {
	window.addEventListener('load', async () => {
		try {
			const reg = await navigator.serviceWorker.register(config.serviceWorkerUrl, { scope: config.appUrl })
			reg.addEventListener('updatefound', () => {
				const worker = reg.installing
				worker?.addEventListener('statechange', async () => {
					if (worker.state === 'installed' && navigator.serviceWorker.controller) {
						const toast = await toastController.create({
							message: t('Update available'),
							position: 'bottom',
							buttons: [{ text: t('Reload'), handler: () => location.reload() }],
						})
						await toast.present()
					}
				})
			})
		} catch (e) {
			console.warn('Service worker registration failed', e)
		}
	})
}
