<template>
	<!-- never scrolls: the sheet is exactly as high as its content (ItemSheetModal measures it) -->
	<ion-content class="item-sheet" :scroll-y="false">
		<div ref="body" class="body">
			<!-- top part (first sheet height): amount and the add/update button -->
			<div class="head">
				<ItemIcon :icon="form.icon || null" :catalog-key="catalogKey" :category="form.category" :name="form.name || '?'" large />
				<div class="title">
					<h2>{{ form.name || '?' }}</h2>
					<p v-if="existing && !inList">{{ t('Already on the list') }}</p>
				</div>
			</div>

			<div class="stepper">
				<button type="button" class="step" :aria-label="'-'" :disabled="!canStep" @click="step(-1)">−</button>
				<input
					v-model="form.quantity"
					class="qty"
					:placeholder="t('Quantity')"
					inputmode="text"
					autocomplete="off"
					@keydown.enter.prevent="submit">
				<button type="button" class="step" :aria-label="'+'" @click="step(1)">+</button>
			</div>
			<div class="chips">
				<button v-for="q in quick" :key="q" type="button" class="chip" :class="{ on: form.quantity === q }" @click="form.quantity = q">{{ q }}</button>
			</div>

			<ion-button expand="block" size="large" class="submit" :disabled="!form.name.trim()" @click="submit">
				<ion-icon slot="start" :icon="existing ? checkmark : add" />
				{{ existing ? t('Update') : t('Add') }}
			</ion-button>

			<!-- lower part, shown when the sheet is pulled up: the entry itself -->
			<h3 class="section">{{ t('Edit item') }}</h3>
			<input
				v-model="form.name"
				class="field name"
				:placeholder="t('Name')"
				:aria-label="t('Name')"
				autocomplete="off"
				autocapitalize="sentences"
				@keydown.enter.prevent="submit">
			<div class="split">
				<div class="field category">
					<ion-select
						v-model="form.category"
						:aria-label="t('Category')"
						interface="alert"
						:cancel-text="t('Cancel')"
						:ok-text="t('OK')"
						:interface-options="{ header: t('Category') }">
						<ion-select-option v-for="c in categoryOptions" :key="c.key" :value="c.key">{{ c.icon }} {{ c.name }}</ion-select-option>
					</ion-select>
				</div>
				<button type="button" class="field icon-box" :aria-label="t('Icon')" @click="pickerOpen = true">
					<ItemIcon :icon="form.icon || null" :catalog-key="catalogKey" :category="form.category" :name="form.name || '?'" />
				</button>
			</div>
			<ion-button v-if="existing" expand="block" fill="clear" color="danger" class="delete" @click="emit('delete')">
				<ion-icon slot="start" :icon="trashOutline" />
				{{ t('Delete') }}
			</ion-button>
		</div>

		<ion-modal :is-open="pickerOpen" @didDismiss="pickerOpen = false">
			<EmojiPicker v-if="pickerOpen" :selected="form.icon || null" @pick="pickIcon" @close="pickerOpen = false" />
		</ion-modal>
	</ion-content>
</template>

<script setup lang="ts">
import { IonButton, IonContent, IonIcon, IonModal, IonSelect, IonSelectOption } from '@ionic/vue'
import { add, checkmark, trashOutline } from 'ionicons/icons'
import { computed, reactive, ref } from 'vue'
import EmojiPicker from './EmojiPicker.vue'
import ItemIcon from './ItemIcon.vue'
import { categoriesForTemplate, categoryName } from '../lib/catalog'
import { formatNumber, parseQuantity } from '../lib/quantity'
import { lang, t } from '../i18n'
import type { CustomCategory } from '../types'

export interface ItemSheetValue {
	name: string
	quantity: string | null
	category: string
	icon: string | null
}

/**
 * Long-press sheet for an entry, the same in the list and in the add-items sheet. At its first
 * height it shows the amount with the add/update button; pulled up, it also edits the entry itself
 * (name, category, icon) and, for an item on the list, deletes it. Heights: lib/itemSheet.ts.
 */
const props = defineProps<{
	name: string
	icon?: string | null
	catalogKey?: string | null
	category: string
	quantity?: string | null
	/** the item is already on the list: the button updates it instead of adding */
	existing?: boolean
	/** opened from the list itself (no "already on the list" hint) */
	inList?: boolean
	template: string
	custom: CustomCategory[]
}>()
const emit = defineEmits<{ submit: [value: ItemSheetValue], delete: [] }>()

const form = reactive({
	name: props.name,
	quantity: props.quantity ?? '',
	category: props.category,
	icon: props.icon ?? '',
})
const quick = ['1', '2', '3', '4', '6', '10', '250 g', '500 g', '1 kg', '1 l', '2 l', '1 Pck']
const pickerOpen = ref(false)

/** +/- works on the number in front ("2 l" -> "3 l"); an empty field starts at 1. */
const canStep = computed(() => {
	const p = parseQuantity(form.quantity || '0')
	return !!p && p[0] > 1
})

function step(delta: number) {
	if (form.quantity.trim() === '') {
		form.quantity = delta > 0 ? '2' : '1'
		return
	}
	const p = parseQuantity(form.quantity)
	if (!p) return
	const next = Math.max(1, p[0] + delta)
	form.quantity = formatNumber(next) + (p[1] ? ' ' + p[1] : '')
}

const categoryOptions = computed(() => [
	...categoriesForTemplate(props.template).map((c) => ({ key: c.key, name: categoryName(c, lang), icon: c.icon })),
	...props.custom.map((c) => ({ key: c.key, name: c.name, icon: c.icon ?? '🏷️' })),
])

function pickIcon(emoji: string) {
	form.icon = emoji
	pickerOpen.value = false
}

const body = ref<HTMLElement | null>(null)
defineExpose({
	/** Natural height of the whole sheet in px (for ItemSheetModal). */
	height: () => body.value?.getBoundingClientRect().height ?? 0,
})

function submit() {
	if (!form.name.trim()) return
	emit('submit', {
		name: form.name.trim(),
		quantity: form.quantity.trim() || null,
		category: form.category,
		icon: form.icon.trim() || null,
	})
}
</script>

<style scoped>
.item-sheet {
	--background: var(--app-surface);
}
.body {
	padding: 16px 16px calc(16px + var(--ion-safe-area-bottom, 0px));
}
.head {
	display: flex;
	align-items: center;
	gap: 14px;
	margin-bottom: 18px;
}
.title {
	min-width: 0;
}
.title h2 {
	margin: 0;
	font-size: 22px;
	font-weight: 700;
	overflow: hidden;
	white-space: nowrap;
	text-overflow: ellipsis;
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
.field {
	min-width: 0;
	height: 56px;
	border: 2px solid transparent;
	border-radius: 12px;
	background: var(--app-bg);
	color: var(--ion-text-color);
	font: inherit;
	outline: none;
}
.qty {
	flex: 1;
	padding: 0 14px;
	font-size: 20px;
	text-align: center;
}
.qty:focus,
.field:focus,
.field:focus-within {
	border-color: var(--ion-color-primary);
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
.submit {
	margin-top: 18px;
	--background: var(--app-buy);
	--background-activated: var(--app-buy-strong);
	--color: #ffffff;
}
.section {
	margin: 28px 2px 0;
	font-size: 13px;
	font-weight: 600;
	letter-spacing: 0.04em;
	text-transform: uppercase;
	color: var(--app-muted);
}
.field.name {
	display: block;
	width: 100%;
	margin-top: 10px;
	padding: 0 14px;
	font-size: 16px;
}
/* category 3/4, icon 1/4 */
.split {
	display: grid;
	grid-template-columns: 3fr 1fr;
	gap: 8px;
	margin-top: 10px;
}
.field.category {
	display: flex;
	align-items: center;
	padding: 0 14px;
}
.field.category ion-select {
	width: 100%;
	min-height: 0;
	--padding-top: 0;
	--padding-bottom: 0;
}
/* just the current category, centred (no label, no arrow): the whole box opens the choice */
.field.category ion-select::part(container) {
	flex: 1;
	justify-content: center;
}
.field.category ion-select::part(text) {
	flex: 0 1 auto;
	text-align: center;
}
.field.category ion-select::part(icon) {
	display: none;
}
.icon-box {
	display: flex;
	align-items: center;
	justify-content: center;
	padding: 0;
}
.delete {
	margin-top: 22px;
	margin-bottom: 0;
}
</style>
