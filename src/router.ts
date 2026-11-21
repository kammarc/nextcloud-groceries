import { createRouter, createWebHashHistory } from '@ionic/vue-router'
import type { RouteRecordRaw } from 'vue-router'
import { getCredentials, tryConvertSession } from './auth/session'
import { getLastList, setLastList } from './lib/lastList'
import AppSettingsView from './views/AppSettingsView.vue'
import ConnectView from './views/ConnectView.vue'
import HistoryView from './views/HistoryView.vue'
import ListSettingsView from './views/ListSettingsView.vue'
import ListsView from './views/ListsView.vue'
import ShoppingListView from './views/ShoppingListView.vue'
import StatsView from './views/StatsView.vue'
import StoresView from './views/StoresView.vue'

const routes: RouteRecordRaw[] = [
	{ path: '/', component: ListsView },
	{ path: '/connect', component: ConnectView, meta: { public: true } },
	{ path: '/settings', component: AppSettingsView },
	{ path: '/list/:id', component: ShoppingListView },
	{ path: '/list/:id/history', component: HistoryView },
	{ path: '/list/:id/stores', component: StoresView },
	{ path: '/list/:id/stats', component: StatsView },
	{ path: '/list/:id/settings', component: ListSettingsView },
	{ path: '/:pathMatch(.*)*', redirect: '/' },
]

// Hash history: the server only knows the app's root URL, deep links stay client-side.
export const router = createRouter({
	history: createWebHashHistory(),
	routes,
})

let sessionTried = false
async function authenticated(): Promise<boolean> {
	if (await getCredentials()) return true
	if (!sessionTried) {
		sessionTried = true
		if (await tryConvertSession()) return true
	}
	return false
}

router.beforeEach(async (to) => {
	if (to.meta.public) return true
	return (await authenticated()) ? true : '/connect'
})

/**
 * App (re)launched on the overview (iOS and Android restart killed home-screen apps at the
 * start_url): reopen the list shown last, if it still exists. Call after the initial navigation
 * and before mounting. The list is pushed on top of the overview, so it shows directly, while the
 * back button / Android back gesture still lead to the overview instead of closing the app.
 */
export async function restoreLastList(knownList: (id: number) => boolean) {
	if (router.currentRoute.value.path !== '/') return
	const id = getLastList()
	if (!id) return
	if (knownList(id)) await router.push(`/list/${id}`)
	else setLastList(null)
}
