<template>
	<span class="item-icon" :class="{ letter: !emoji, large }" :style="!emoji ? { background: tint } : undefined" aria-hidden="true">
		{{ emoji || letter }}
	</span>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { getCategory, getItem } from '../lib/catalog'

const props = defineProps<{
	icon?: string | null
	catalogKey?: string | null
	category?: string | null
	name: string
	large?: boolean
}>()

const emoji = computed(() => props.icon || getItem(props.catalogKey)?.icon || (props.category && props.category !== 'other' ? getCategory(props.category)?.icon : '') || '')
const letter = computed(() => (props.name.trim()[0] ?? '?').toUpperCase())
const tint = computed(() => {
	let h = 0
	for (const c of props.name) h = (h * 31 + c.charCodeAt(0)) % 360
	return `hsl(${h} 45% 55%)`
})
</script>

<style scoped>
.item-icon {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 36px;
	height: 36px;
	font-size: 26px;
	line-height: 1;
	flex-shrink: 0;
}
.item-icon.large {
	width: 52px;
	height: 52px;
	font-size: 40px;
}
.item-icon.letter {
	border-radius: 50%;
	color: #fff;
	font-size: 17px;
	font-weight: 600;
}
.item-icon.letter.large {
	font-size: 24px;
}
</style>
