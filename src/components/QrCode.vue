<template>
	<svg class="qr" :viewBox="`0 0 ${qr.size} ${qr.size}`" role="img" :aria-label="label" shape-rendering="crispEdges">
		<rect :width="qr.size" :height="qr.size" fill="#ffffff" />
		<path :d="qr.d" fill="#000000" />
	</svg>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { qrPath } from '../lib/qr'

const props = defineProps<{ text: string, label?: string }>()
const qr = computed(() => qrPath(props.text))
</script>

<style scoped>
.qr {
	display: block;
	width: 100%;
	height: auto;
	border-radius: 8px;
}
</style>
