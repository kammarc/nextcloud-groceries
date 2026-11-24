<template>
	<button
		type="button"
		class="tile ion-activatable"
		:class="[item.checked ? 'tone-done' : 'tone-buy', { leaving, pending: item.pending }]"
		:disabled="readonly"
		v-bind="longPress.handlers"
		@click="onClick">
		<ion-ripple-effect />
		<span v-if="item.quantity" class="badge">{{ item.quantity }}</span>
		<ItemIcon :icon="item.icon" :catalog-key="item.catalogKey" :category="item.category" :name="item.name" large />
		<span v-fit-text="item.name" class="name"><span class="name-text">{{ item.name }}</span></span>
	</button>
</template>

<script setup lang="ts">
import { IonRippleEffect } from '@ionic/vue'
import ItemIcon from './ItemIcon.vue'
import { vFitText } from '../lib/fit'
import { useLongPress } from '../lib/longpress'
import type { Item } from '../types'

const props = defineProps<{ item: Item, readonly?: boolean, leaving?: boolean }>()
const emit = defineEmits<{ toggle: [], edit: [] }>()
const longPress = useLongPress(() => { if (!props.readonly) emit('edit') })

function onClick() {
	if (longPress.consumeClick()) return
	emit('toggle')
}
</script>

<style scoped>
.tile {
	/* children size against the tile width (cqw), so every tile looks the same */
	container-type: inline-size;
	position: relative;
	overflow: hidden;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	gap: 6px;
	aspect-ratio: 1;
	padding: 6px 5px;
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
/* the icon has one size for all tiles, whatever the name: long names shrink their text instead */
.tile :deep(.item-icon.large) {
	width: 46cqw;
	height: 46cqw;
	font-size: 35cqw;
}
.tile :deep(.item-icon.letter.large) {
	font-size: 21cqw;
}
/* a fixed two-line box, so the labels of all tiles sit at the same height;
   v-fit-text shrinks the font when a name needs more than two lines */
.name {
	flex: 0 0 auto;
	display: flex;
	align-items: center;
	justify-content: center;
	width: 100%;
	height: 2.4em;
	font-size: 15px;
	line-height: 1.2;
	overflow: hidden;
}
.name-text {
	display: block;
	max-width: 100%;
	font-family: var(--app-item-font);
	font-weight: var(--app-item-weight);
	-webkit-text-stroke: var(--app-item-stroke) currentColor;
	line-height: 1.15;
	text-align: center;
	overflow-wrap: break-word;
	-webkit-hyphens: auto;
	hyphens: auto;
}
.tone-done .name-text {
	text-decoration: line-through;
	opacity: 0.75;
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
.tile.pending .name-text::after {
	content: ' •';
	opacity: 0.6;
}
</style>
