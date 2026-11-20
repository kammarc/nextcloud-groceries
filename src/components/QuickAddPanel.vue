<template>
	<ion-content class="ion-padding quick-add">
		<div class="head">
			<ItemIcon :icon="icon" :catalog-key="catalogKey" :category="category" :name="name" large />
			<div class="title">
				<h2>{{ name }}</h2>
				<p v-if="existing">{{ t('Already on the list') }}</p>
			</div>
		</div>

		<div class="stepper">
			<button type="button" class="step" :aria-label="'-'" :disabled="!canStep" @click="step(-1)">−</button>
			<input
				v-model="quantity"
				class="qty"
				:placeholder="t('Quantity')"
				inputmode="text"
				autocomplete="off"
				@keydown.enter.prevent="submit">
			<button type="button" class="step" :aria-label="'+'" @click="step(1)">+</button>
		</div>
		<div class="chips">
			<button v-for="q in quick" :key="q" type="button" class="chip" :class="{ on: quantity === q }" @click="quantity = q">{{ q }}</button>
		</div>

		<input v-model="note" class="note" :placeholder="t('Note')" autocomplete="off" @keydown.enter.prevent="submit">

		<ion-button expand="block" size="large" class="add" @click="submit">
			<ion-icon slot="start" :icon="existing ? checkmark : add" />
			{{ existing ? t('Update') : t('Add') }}
		</ion-button>
	</ion-content>
</template>

<script setup lang="ts">
import { IonButton, IonContent, IonIcon } from '@ionic/vue'
import { add, checkmark } from 'ionicons/icons'
import { computed, ref } from 'vue'
import ItemIcon from './ItemIcon.vue'
import { formatNumber, parseQuantity } from '../lib/quantity'
import { t } from '../i18n'

/** Long-press sheet of the add-items screen: set amount (and note) before adding. */
const props = defineProps<{
	name: string
	icon?: string | null
	catalogKey?: string | null
	category?: string | null
	initialQuantity?: string | null
	initialNote?: string | null
	/** the item is already on the list: the button updates it instead of adding */
	existing?: boolean
}>()
const emit = defineEmits<{ submit: [value: { quantity: string | null, note: string | null }] }>()

const quantity = ref(props.initialQuantity ?? '')
const note = ref(props.initialNote ?? '')
const quick = ['1', '2', '3', '4', '6', '10', '250 g', '500 g', '1 kg', '1 l', '2 l', '1 Pck']

/** +/- works on the number in front ("2 l" -> "3 l"); an empty field starts at 1. */
const canStep = computed(() => {
	const p = parseQuantity(quantity.value || '0')
	return !!p && p[0] > 1
})

function step(delta: number) {
	if (quantity.value.trim() === '') {
		quantity.value = delta > 0 ? '2' : '1'
		return
	}
	const p = parseQuantity(quantity.value)
	if (!p) return
	const next = Math.max(1, p[0] + delta)
	quantity.value = formatNumber(next) + (p[1] ? ' ' + p[1] : '')
}

function submit() {
	emit('submit', { quantity: quantity.value.trim() || null, note: note.value.trim() || null })
}
</script>

<style scoped>
.quick-add {
	--background: var(--app-surface);
}
.head {
	display: flex;
	align-items: center;
	gap: 14px;
	margin-bottom: 18px;
}
.title h2 {
	margin: 0;
	font-size: 22px;
	font-weight: 700;
}
.title p {
	margin: 2px 0 0;
	color: var(--app-muted);
}
.stepper {
	display: flex;
	align-items: stretch;
	gap: 8px;
}
.step {
	width: 56px;
	border: none;
	border-radius: 12px;
	background: var(--app-surface-2);
	color: var(--ion-text-color);
	font-size: 28px;
	line-height: 1;
}
.step:disabled {
	opacity: 0.4;
}
.qty,
.note {
	flex: 1;
	min-width: 0;
	height: 56px;
	padding: 0 14px;
	border: 2px solid transparent;
	border-radius: 12px;
	background: var(--app-bg);
	color: var(--ion-text-color);
	font: inherit;
	font-size: 20px;
	text-align: center;
	outline: none;
}
.qty:focus,
.note:focus {
	border-color: var(--ion-color-primary);
}
.note {
	width: 100%;
	margin-top: 12px;
	font-size: 16px;
	text-align: start;
}
.chips {
	display: flex;
	flex-wrap: wrap;
	gap: 6px;
	margin-top: 12px;
}
.chip {
	padding: 8px 14px;
	border: none;
	border-radius: 999px;
	background: var(--app-surface-2);
	color: var(--ion-text-color);
	font: inherit;
	font-size: 15px;
}
.chip.on {
	background: var(--ion-color-primary);
	color: var(--ion-color-primary-contrast);
}
.add {
	margin-top: 18px;
	--background: var(--app-buy);
	--background-activated: var(--app-buy-strong);
	--color: #ffffff;
}
</style>
