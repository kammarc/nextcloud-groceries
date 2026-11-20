<template>
	<div v-if="message" class="sync-status" :class="{ warn: !data.online }" role="status">
		<ion-icon :icon="data.online ? syncOutline : cloudOfflineOutline" />
		<span>{{ message }}</span>
	</div>
</template>

<script setup lang="ts">
import { IonIcon } from '@ionic/vue'
import { cloudOfflineOutline, syncOutline } from 'ionicons/icons'
import { computed } from 'vue'
import { t } from '../i18n'
import { useData } from '../stores/data'

const data = useData()
const message = computed(() => {
	if (!data.online) return t('Offline – changes will sync later')
	if (data.pendingCount > 2) return t('{count} changes waiting to sync', { count: data.pendingCount })
	return ''
})
</script>

<style scoped>
.sync-status {
	display: flex;
	align-items: center;
	gap: 8px;
	margin: 8px 16px;
	padding: 8px 12px;
	border-radius: 10px;
	font-size: 14px;
	background: var(--ion-color-step-100, #eee);
}
.sync-status.warn {
	background: rgba(var(--ion-color-warning-rgb), 0.18);
}
</style>
