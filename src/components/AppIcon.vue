<template>
	<img v-if="image" :src="image" alt="" class="app-icon img" :style="sizeStyle">
	<span v-else class="app-icon emoji" :style="sizeStyle" aria-hidden="true">{{ icon || fallback }}</span>
</template>

<script setup lang="ts">
import { computed } from 'vue'

/** An emoji or an uploaded picture (data: URL), e.g. for stores and lists. */
const props = withDefaults(defineProps<{ icon?: string | null, image?: string | null, size?: number, fallback?: string }>(), {
	icon: null,
	image: null,
	size: 28,
	fallback: '🏪',
})
const sizeStyle = computed(() => ({ width: `${props.size}px`, height: `${props.size}px`, fontSize: `${Math.round(props.size * 0.82)}px` }))
</script>

<style scoped>
.app-icon {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	flex-shrink: 0;
	line-height: 1;
}
.img {
	object-fit: cover;
	border-radius: 22%;
}
</style>
