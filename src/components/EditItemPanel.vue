<template>
	<!-- like the add sheet: header and content never start the sheet drag, only the handle does -->
	<ion-header @touchstart.stop @mousedown.stop @pointerdown.stop>
		<ion-toolbar>
			<ion-buttons slot="start">
				<ion-button @click="emit('close')">{{ t('Cancel') }}</ion-button>
			</ion-buttons>
			<ion-title>{{ t('Edit item') }}</ion-title>
			<ion-buttons slot="end">
				<ion-button strong :disabled="!form.name.trim()" @click="save">{{ t('Save') }}</ion-button>
			</ion-buttons>
		</ion-toolbar>
	</ion-header>
	<ion-content class="ion-padding-vertical" @touchstart.stop @mousedown.stop @pointerdown.stop>
		<div class="hero">
			<ItemIcon :icon="form.icon || null" :catalog-key="item.catalogKey" :category="form.category" :name="form.name || '?'" large />
		</div>
		<ion-list :inset="true">
			<ion-item>
				<ion-input v-model="form.name" :label="t('Name')" label-placement="stacked" autocapitalize="sentences" />
			</ion-item>
			<ion-item>
				<ion-input v-model="form.quantity" :label="t('Quantity')" label-placement="stacked" placeholder="2 · 500 g · 1 l" />
			</ion-item>
			<div class="chips">
				<ion-chip v-for="q in quickQuantities" :key="q" :outline="form.quantity !== q" @click="form.quantity = q">{{ q }}</ion-chip>
			</div>
			<ion-item>
				<ion-textarea v-model="form.note" :label="t('Note')" label-placement="stacked" :auto-grow="true" autocapitalize="sentences" />
			</ion-item>
		</ion-list>

		<ion-list :inset="true">
			<ion-item>
				<ion-select v-model="form.category" :label="t('Category')" interface="alert" :cancel-text="t('Cancel')" :ok-text="t('OK')" :interface-options="{ header: t('Category') }">
					<ion-select-option v-for="c in categoryOptions" :key="c.key" :value="c.key">{{ c.icon }} {{ c.name }}</ion-select-option>
				</ion-select>
			</ion-item>
			<IconButton :icon="form.icon || null" :fallback="categoryIcon" @update:icon="form.icon = $event" />
			<div class="chips emoji">
				<button v-for="e in emojiChoices" :key="e" type="button" class="emoji-btn" :class="{ on: form.icon === e }" @click="form.icon = e">{{ e }}</button>
			</div>
		</ion-list>

		<ion-list :inset="true">
			<ion-item button :detail="false" @click="emit('delete')">
				<ion-icon slot="start" :icon="trashOutline" color="danger" />
				<ion-label color="danger">{{ t('Delete') }}</ion-label>
			</ion-item>
		</ion-list>
	</ion-content>
</template>

<script setup lang="ts">
import {
	IonButton, IonButtons, IonChip, IonContent, IonHeader, IonIcon, IonInput, IonItem, IonLabel, IonList,
	IonSelect, IonSelectOption, IonTextarea, IonTitle, IonToolbar,
} from '@ionic/vue'
import { trashOutline } from 'ionicons/icons'
import { computed, reactive } from 'vue'
import IconButton from './IconButton.vue'
import ItemIcon from './ItemIcon.vue'
import { categoriesForTemplate, categoryName, getCategory, matchCatalog } from '../lib/catalog'
import { lang, t } from '../i18n'
import type { CustomCategory, Item, ItemData } from '../types'

const props = defineProps<{ item: Item, template: string, custom: CustomCategory[] }>()
const emit = defineEmits<{ close: [], delete: [], save: [data: ItemData] }>()

const form = reactive({
	name: props.item.name,
	quantity: props.item.quantity ?? '',
	note: props.item.note ?? '',
	category: props.item.category,
	icon: props.item.icon ?? '',
})

const quickQuantities = ['1', '2', '3', '4', '6', '500 g', '1 kg', '1 l', '1 Pck']

const categoryOptions = computed(() => [
	...categoriesForTemplate(props.template).map((c) => ({ key: c.key, name: categoryName(c, lang), icon: c.icon })),
	...props.custom.map((c) => ({ key: c.key, name: c.name, icon: c.icon ?? '🏷️' })),
])

const categoryIcon = computed(() => getCategory(form.category)?.icon ?? '❔')

const emojiChoices = computed(() => {
	const choices = new Set<string>()
	const m = matchCatalog(form.name)
	if (m) choices.add(m.item.icon)
	const cat = getCategory(form.category)
	if (cat) choices.add(cat.icon)
	for (const e of ['🛒', '🍎', '🥕', '🥖', '🧀', '🥩', '🐟', '🥫', '🍝', '🧂', '🍫', '🧃', '🍷', '🧻', '🧴', '💊', '🔨', '🌱', '🎁', '⭐']) choices.add(e)
	return [...choices]
})

function save() {
	const d: ItemData = {}
	if (form.name.trim() !== props.item.name) d.name = form.name.trim()
	if ((form.quantity.trim() || null) !== props.item.quantity) d.quantity = form.quantity.trim() || null
	if ((form.note.trim() || null) !== props.item.note) d.note = form.note.trim() || null
	if (form.category !== props.item.category) d.category = form.category
	if ((form.icon.trim() || null) !== props.item.icon) d.icon = form.icon.trim() || null
	emit('save', d)
}
</script>

<style scoped>
.hero {
	display: flex;
	justify-content: center;
	padding: 8px 0 0;
}
.chips {
	display: flex;
	flex-wrap: wrap;
	gap: 2px;
	padding: 4px 12px 8px;
}
.emoji-btn {
	width: 42px;
	height: 42px;
	border: 2px solid transparent;
	border-radius: 12px;
	background: transparent;
	font-size: 26px;
	line-height: 1;
}
.emoji-btn.on {
	border-color: var(--ion-color-primary);
}
</style>
