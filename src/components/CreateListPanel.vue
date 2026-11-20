<template>
	<ion-header>
		<ion-toolbar>
			<ion-buttons slot="start">
				<ion-button @click="emit('close')">{{ t('Cancel') }}</ion-button>
			</ion-buttons>
			<ion-title>{{ t('New list') }}</ion-title>
			<ion-buttons slot="end">
				<ion-button strong :disabled="!name.trim() || busy" @click="create">{{ t('Create') }}</ion-button>
			</ion-buttons>
		</ion-toolbar>
	</ion-header>
	<ion-content class="ion-padding">
		<div class="templates">
			<button v-for="tpl in templates" :key="tpl.key" type="button" class="tpl" :class="{ on: template === tpl.key }" :style="{ background: color }" @click="pick(tpl)">
				<span class="emoji">{{ tpl.icon }}</span>
				<span>{{ tpl.label }}</span>
			</button>
		</div>
		<ion-list :inset="true">
			<ion-item>
				<ion-input v-model="name" :label="t('Name')" label-placement="stacked" autocapitalize="sentences" @keydown.enter="create" />
			</ion-item>
		</ion-list>
		<ion-list :inset="true">
			<IconButton v-model:icon="icon" v-model:image="image" allow-image fallback="🛒" :tint="color" />
		</ion-list>
		<div class="colors">
			<button v-for="c in colors" :key="c" type="button" class="color" :class="{ on: color === c }" :style="{ background: c }" :aria-label="c" @click="color = c" />
		</div>
		<p v-if="error" class="error">{{ error }}</p>
	</ion-content>
</template>

<script setup lang="ts">
import { IonButton, IonButtons, IonContent, IonHeader, IonInput, IonItem, IonList, IonTitle, IonToolbar } from '@ionic/vue'
import { ref } from 'vue'
import IconButton from './IconButton.vue'
import { LIST_COLORS } from '../lib/listColors'
import { t } from '../i18n'
import { useData } from '../stores/data'
import type { ListSummary } from '../types'

const emit = defineEmits<{ close: [], created: [list: ListSummary] }>()
const data = useData()

const templates = [
	{ key: 'grocery', icon: '🛒', label: t('Groceries') },
	{ key: 'pharmacy', icon: '💊', label: t('Pharmacy') },
	{ key: 'hardware', icon: '🔨', label: t('Hardware store') },
	{ key: 'custom', icon: '📝', label: t('Other list') },
]
const colors = LIST_COLORS

const template = ref('grocery')
const name = ref(t('Groceries'))
const icon = ref<string | null>('🛒')
const image = ref<string | null>(null)
const color = ref<string>(colors[0])
const busy = ref(false)
const error = ref('')

function pick(tpl: typeof templates[number]) {
	const previous = templates.find((x) => x.key === template.value)
	if (!name.value.trim() || name.value === previous?.label) name.value = tpl.label
	if (!icon.value || icon.value === previous?.icon) icon.value = tpl.icon
	template.value = tpl.key
}

async function create() {
	if (!name.value.trim() || busy.value) return
	busy.value = true
	error.value = ''
	try {
		const list = await data.createList({ name: name.value.trim(), template: template.value, icon: icon.value || null, image: image.value, color: color.value })
		emit('created', list)
	} catch (e) {
		error.value = (e as Error).message
	} finally {
		busy.value = false
	}
}
</script>

<style scoped>
.templates {
	display: grid;
	grid-template-columns: repeat(4, 1fr);
	gap: 8px;
}
.tpl {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 4px;
	padding: 10px 2px;
	border: 2px solid transparent;
	border-radius: 14px;
	background: var(--app-overlay);
	/* the list colours are light pastels: dark text on them in every theme */
	color: #1d2a24;
	font: inherit;
	transition: background 0.2s;
	font-size: 12px;
}
.tpl.on {
	border-color: var(--ion-color-primary);
}
.tpl .emoji {
	font-size: 30px;
}
.colors {
	display: flex;
	gap: 12px;
	justify-content: center;
	padding: 8px;
}
.color {
	width: 36px;
	height: 36px;
	border-radius: 50%;
	border: 3px solid transparent;
	box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.1);
}
.color.on {
	border-color: var(--ion-color-primary);
}
.error {
	color: var(--ion-color-danger);
	text-align: center;
}
</style>
