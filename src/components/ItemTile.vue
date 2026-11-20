<template>
	<button
		type="button"
		class="tile ion-activatable"
		:class="[item.checked ? 'tone-done' : 'tone-buy', fit, { leaving, pending: item.pending }]"
		:disabled="readonly"
		v-bind="longPress.handlers"
		@click="onClick">
		<ion-ripple-effect />
		<span v-if="item.quantity" class="badge">{{ item.quantity }}</span>
		<ItemIcon :icon="item.icon" :catalog-key="item.catalogKey" :category="item.category" :name="item.name" large />
		<span class="name">{{ item.name }}</span>
	</button>
</template>

<script setup lang="ts">
import { IonRippleEffect } from '@ionic/vue'
import { computed } from 'vue'
import ItemIcon from './ItemIcon.vue'
import { nameFit } from '../lib/fit'
import { useLongPress } from '../lib/longpress'
import type { Item } from '../types'

const props = defineProps<{ item: Item, readonly?: boolean, leaving?: boolean }>()
const emit = defineEmits<{ toggle: [], edit: [] }>()
const fit = computed(() => nameFit(props.item.name))
const longPress = useLongPress(() => { if (!props.readonly) emit('edit') })

function onClick() {
	if (longPress.consumeClick()) return
	emit('toggle')
}
</script>

<style scoped>
.tile {
	position: relative;
	overflow: hidden;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	gap: 6px;
	aspect-ratio: 1;
	padding: 8px 4px;
	border: none;
	border-radius: 10px;
	background: var(--app-buy);
	color: #ffffff;
	font: inherit;
	transition: transform 0.22s ease, opacity 0.22s ease, background 0.2s;
	-webkit-tap-highlight-color: transparent;
	user-select: none;
	-webkit-user-select: none;
	-webkit-touch-callout: none;
}
.tile.tone-done {
	background: var(--app-done);
}
.tile:active {
	transform: scale(0.95);
}
.tile.leaving {
	transform: scale(0.6);
	opacity: 0;
}
.tone-done .name {
	text-decoration: line-through;
	opacity: 0.75;
}
.name {
	font-size: 13px;
	font-weight: 500;
	line-height: 1.15;
	text-align: center;
	/* a plain block capped at 3 lines: -webkit-box/line-clamp with hyphenation reserved more
	   height than the drawn lines on Android, which pushed icon and name off-centre */
	display: block;
	flex: 0 0 auto;
	width: 100%;
	max-height: 3.45em;
	overflow: hidden;
	overflow-wrap: break-word;
	-webkit-hyphens: auto;
	hyphens: auto;
}
/* long names: smaller text and icon so the whole name fits the tile */
.long .name {
	font-size: 12px;
}
.xlong .name {
	font-size: 11px;
	letter-spacing: -0.1px;
}
.long :deep(.item-icon.large) {
	width: 44px;
	height: 44px;
	font-size: 34px;
}
.xlong :deep(.item-icon.large) {
	width: 36px;
	height: 36px;
	font-size: 28px;
}
.badge {
	position: absolute;
	top: 6px;
	right: 6px;
	max-width: calc(100% - 12px);
	padding: 2px 9px;
	border-radius: 12px;
	background: rgba(255, 255, 255, 0.94);
	color: var(--app-buy-strong);
	font-size: 14px;
	font-weight: 700;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}
.tile.pending .name::after {
	content: ' •';
	opacity: 0.6;
}
</style>
