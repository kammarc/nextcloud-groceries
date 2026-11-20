<template>
	<button type="button" class="icon-button" @click="open = true">
		<span class="preview" :style="tint ? { background: tint } : undefined">
			<AppIcon :icon="icon" :image="image" :size="44" :fallback="fallback" />
		</span>
		<span class="label">{{ label ?? t('Icon') }}</span>
		<ion-icon :icon="chevronForward" class="chevron" />
	</button>
	<ion-modal :is-open="open" @didDismiss="open = false">
		<EmojiPicker
			v-if="open"
			:selected="image ? null : icon"
			:allow-image="allowImage"
			:has-image="!!image"
			@pick="pickEmoji"
			@pick-image="pickImage"
			@remove-image="pickImage(null)"
			@close="open = false" />
	</ion-modal>
</template>

<script setup lang="ts">
import { IonIcon, IonModal } from '@ionic/vue'
import { chevronForward } from 'ionicons/icons'
import { ref } from 'vue'
import AppIcon from './AppIcon.vue'
import EmojiPicker from './EmojiPicker.vue'
import { t } from '../i18n'

/** Shows the current icon; tapping opens the emoji picker (and optionally an image picker). */
withDefaults(defineProps<{ icon?: string | null, image?: string | null, allowImage?: boolean, label?: string, fallback?: string, tint?: string | null }>(), {
	tint: null,
	icon: null,
	image: null,
	label: undefined,
	fallback: '❔',
})
const emit = defineEmits<{ 'update:icon': [icon: string], 'update:image': [image: string | null] }>()
const open = ref(false)

function pickEmoji(e: string) {
	emit('update:icon', e)
	emit('update:image', null)
	open.value = false
}

function pickImage(dataUrl: string | null) {
	emit('update:image', dataUrl)
	open.value = false
}
</script>

<style scoped>
.icon-button {
	display: flex;
	align-items: center;
	gap: 14px;
	width: 100%;
	padding: 10px 16px;
	border: none;
	background: transparent;
	color: var(--ion-text-color);
	font: inherit;
	text-align: start;
}
.preview {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 56px;
	height: 56px;
	border-radius: 14px;
	background: var(--app-overlay);
}
.label {
	flex: 1;
	font-size: 16px;
}
.chevron {
	color: var(--ion-color-medium);
}
</style>
