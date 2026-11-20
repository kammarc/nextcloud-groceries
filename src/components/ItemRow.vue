<template>
	<ion-item-sliding ref="sliding" class="card-row" :class="item.checked ? 'tone-done' : 'tone-buy'" :disabled="readonly">
		<ion-item-options side="start" @ionSwipe="edit">
			<ion-item-option color="primary" expandable @click="edit">
				<ion-icon slot="icon-only" :icon="createOutline" />
			</ion-item-option>
		</ion-item-options>

		<ion-item
			:button="!readonly"
			:detail="false"
			lines="none"
			class="item-row"
			:class="{ checked: item.checked, leaving, pending: item.pending }"
			v-bind="longPress.handlers"
			@click="onClick">
			<div slot="start" class="lead">
				<ion-icon v-if="item.checked" :icon="checkmarkCircle" class="check" />
				<ItemIcon v-else :icon="item.icon" :catalog-key="item.catalogKey" :category="item.category" :name="item.name" />
			</div>
			<ion-label class="ion-text-wrap">
				<h2>{{ item.name }}</h2>
				<p v-if="subtitle">{{ subtitle }}</p>
			</ion-label>
			<span v-if="item.quantity" slot="end" class="qty">{{ item.quantity }}</span>
		</ion-item>

		<ion-item-options side="end" @ionSwipe="remove">
			<ion-item-option color="danger" expandable @click="remove">
				<ion-icon slot="icon-only" :icon="trashOutline" />
			</ion-item-option>
		</ion-item-options>
	</ion-item-sliding>
</template>

<script setup lang="ts">
import { IonIcon, IonItem, IonItemOption, IonItemOptions, IonItemSliding, IonLabel } from '@ionic/vue'
import { checkmarkCircle, createOutline, trashOutline } from 'ionicons/icons'
import { computed, ref } from 'vue'
import ItemIcon from './ItemIcon.vue'
import { useLongPress } from '../lib/longpress'
import { t } from '../i18n'
import type { Item } from '../types'

const props = withDefaults(defineProps<{
	item: Item
	readonly?: boolean
	leaving?: boolean
	people?: Record<string, string>
	uid?: string
}>(), { people: undefined, uid: undefined })
const emit = defineEmits<{ toggle: [], edit: [], delete: [] }>()
const sliding = ref<any>(null)

const longPress = useLongPress(() => { if (!props.readonly) emit('edit') })

const subtitle = computed(() => {
	const parts: string[] = []
	if (props.item.note) parts.push(props.item.note)
	if (props.item.sourceLabel) parts.push(t('from {source}', { source: props.item.sourceLabel }))
	if (props.people && props.uid && props.item.addedBy && props.item.addedBy !== props.uid && !props.item.checked) {
		const who = props.people[props.item.addedBy]
		if (who) parts.push(t('Added by {name}', { name: who }))
	}
	return parts.join(' · ')
})

function onClick() {
	if (longPress.consumeClick() || props.readonly) return
	emit('toggle')
}

async function close() {
	await sliding.value?.$el?.close?.()
}

async function edit() {
	await close()
	emit('edit')
}

async function remove() {
	await close()
	emit('delete')
}
</script>

<style scoped>
.card-row {
	/* side spacing comes from the parent list's padding (margin here would overflow the width) */
	margin: 6px 0;
	border-radius: 12px;
	overflow: hidden;
}
.item-row {
	--background: var(--app-buy);
	--background-activated: #000;
	--background-activated-opacity: 0.12;
	--color: #ffffff;
	--min-height: 58px;
	--padding-start: 10px;
	transition: opacity 0.22s ease, transform 0.22s ease;
}
.tone-done .item-row {
	--background: var(--app-done);
}
.item-row h2 {
	font-size: 17px;
	font-weight: 600;
	color: #ffffff;
}
.item-row p {
	color: rgba(255, 255, 255, 0.82);
}
.item-row.checked h2 {
	text-decoration: line-through;
	color: rgba(255, 255, 255, 0.7);
}
.item-row.leaving {
	opacity: 0;
	transform: translateX(24px);
}
.item-row.pending h2::after {
	content: ' •';
	color: rgba(255, 255, 255, 0.6);
}
.lead {
	display: flex;
	align-items: center;
	justify-content: center;
	width: 42px;
	margin-inline-end: 12px;
}
.check {
	font-size: 28px;
	color: var(--ion-color-success);
}
.qty {
	padding: 2px 10px;
	border-radius: 999px;
	background: rgba(255, 255, 255, 0.92);
	color: var(--app-buy-strong);
	font-size: 14px;
	font-weight: 700;
	font-variant-numeric: tabular-nums;
}
.tone-done .qty {
	color: var(--app-done);
}
.add {
	color: rgba(255, 255, 255, 0.9);
	font-size: 22px;
}
</style>
