<template>
	<span
		ref="el"
		class="sketch-glyph"
		:class="{ ready: !!mask }"
		:style="{ width: `${size}px`, height: `${size}px`, '--sketch': mask ? `url(${mask})` : 'none' }"
		aria-hidden="true" />
</template>

<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { whenVisible } from '../lib/sketch'
import { cachedEmoji, sketchEmoji } from '../lib/sketchRender'

/**
 * An emoji as a "hand-drawn icons" pen sketch (callers show the plain emoji when the list setting
 * is off). `lazy`: draw only once it scrolls into view, for long grids like the emoji picker.
 */
const props = defineProps<{ emoji: string, size: number, lazy?: boolean }>()

const el = ref<HTMLElement | null>(null)
const mask = ref('')
let cancel = () => {}

function draw() {
	cancel()
	const done = cachedEmoji(props.emoji)
	if (done !== undefined) mask.value = done
	else if (props.lazy && el.value) {
		mask.value = ''
		const emoji = props.emoji
		cancel = whenVisible(el.value, () => {
			if (emoji === props.emoji) mask.value = sketchEmoji(emoji)
		})
	} else mask.value = sketchEmoji(props.emoji)
}

onMounted(draw)
watch(() => props.emoji, draw)
onBeforeUnmount(() => cancel())
</script>

<style scoped>
.sketch-glyph {
	display: inline-block;
	flex-shrink: 0;
	vertical-align: middle;
}
.sketch-glyph.ready {
	background-color: var(--ion-text-color, currentColor);
	-webkit-mask: var(--sketch) center / contain no-repeat;
	mask: var(--sketch) center / contain no-repeat;
}
</style>
