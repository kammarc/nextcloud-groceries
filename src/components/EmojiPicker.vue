<template>
	<ion-header>
		<ion-toolbar>
			<ion-title>{{ t('Choose icon') }}</ion-title>
			<ion-buttons slot="end">
				<ion-button @click="emit('close')">{{ t('Close') }}</ion-button>
			</ion-buttons>
		</ion-toolbar>
		<ion-toolbar class="group-bar">
			<div class="groups" role="tablist">
				<button
					v-for="g in groups"
					:key="g.key"
					type="button"
					role="tab"
					class="group-tab"
					:class="{ on: active === g.key }"
					:aria-label="groupName(g)"
					@click="jump(g.key)">
					<SketchGlyph v-if="sketch" :emoji="g.icon" :size="29" />
					<template v-else>{{ g.icon }}</template>
				</button>
			</div>
		</ion-toolbar>
	</ion-header>
	<ion-content ref="content" :scroll-events="true" @ionScroll="onScroll">
		<div v-if="allowImage || hasImage" class="image-actions">
			<ion-button v-if="allowImage" expand="block" fill="solid" @click="fileInput?.click()">
				<ion-icon slot="start" :icon="imageOutline" />
				{{ t('Choose image') }}
			</ion-button>
			<ion-button v-if="hasImage" expand="block" fill="clear" color="medium" @click="emit('removeImage')">
				{{ t('Remove image') }}
			</ion-button>
			<input ref="fileInput" type="file" accept="image/*" class="hidden" @change="onFile">
			<p v-if="error" class="error">{{ error }}</p>
		</div>
		<section v-for="g in groups" :id="'emoji-' + g.key" :key="g.key" class="group" :data-key="g.key">
			<h3>{{ groupName(g) }}</h3>
			<div class="grid">
				<button v-for="e in g.emojis" :key="e" type="button" class="emoji" :class="{ selected: e === selected }" :aria-label="sketch ? e : undefined" @click="emit('pick', e)">
					<SketchGlyph v-if="sketch" :emoji="e" :size="39" lazy />
					<template v-else>{{ e }}</template>
				</button>
			</div>
		</section>
	</ion-content>
</template>

<script setup lang="ts">
import { IonButton, IonButtons, IonContent, IonHeader, IonIcon, IonTitle, IonToolbar } from '@ionic/vue'
import { imageOutline } from 'ionicons/icons'
import { ref } from 'vue'
import groupsData from '../lib/emoji-groups.json'
import { imageFileToDataUrl } from '../lib/image'
import { useSketchIcons } from '../lib/sketch'
import SketchGlyph from './SketchGlyph.vue'
import { t } from '../i18n'

interface EmojiGroup { key: string, icon: string, names: Record<'en' | 'de', string>, emojis: string[] }

/** Group headings (the English names in emoji-groups.json, translated like all UI texts). */
const GROUP_NAMES: Record<string, string> = {
	food_drink: t('Food & drink'),
	objects: t('Objects'),
	animals_nature: t('Animals & nature'),
	travel_places: t('Places'),
	activities: t('Activities'),
	smileys_emotion: t('Smileys'),
	people_body: t('People'),
	symbols: t('Symbols'),
	flags: t('Flags'),
}
const groupName = (g: EmojiGroup) => GROUP_NAMES[g.key] ?? g.names.en

defineProps<{ selected?: string | null, allowImage?: boolean, hasImage?: boolean }>()
const emit = defineEmits<{ pick: [emoji: string], pickImage: [dataUrl: string], removeImage: [], close: [] }>()

/** "Hand-drawn icons" list setting (when opened from such a list): show the emoji as pen sketches. */
const sketch = useSketchIcons()

const groups = groupsData as EmojiGroup[]
const active = ref(groups[0].key)
const content = ref<any>(null)
const fileInput = ref<HTMLInputElement | null>(null)
const error = ref('')

async function jump(key: string) {
	active.value = key
	const el = document.getElementById('emoji-' + key)
	const scroller = await content.value?.$el?.getScrollElement?.()
	if (el && scroller) scroller.scrollTo({ top: el.offsetTop - 4, behavior: 'smooth' })
}

/** Highlights the tab of the group currently at the top. */
function onScroll(ev: CustomEvent) {
	const top = ev.detail.scrollTop + 24
	let current = groups[0].key
	for (const g of groups) {
		const el = document.getElementById('emoji-' + g.key)
		if (el && el.offsetTop <= top) current = g.key
	}
	active.value = current
}

async function onFile(ev: Event) {
	const file = (ev.target as HTMLInputElement).files?.[0]
	if (!file) return
	error.value = ''
	try {
		emit('pickImage', await imageFileToDataUrl(file))
	} catch (e) {
		error.value = (e as Error).message
	} finally {
		(ev.target as HTMLInputElement).value = ''
	}
}
</script>

<style scoped>
.group-bar {
	--min-height: 48px;
}
.groups {
	display: flex;
	justify-content: space-between;
	overflow-x: auto;
	padding: 0 8px;
	scrollbar-width: none;
}
.group-tab {
	flex: 0 0 auto;
	width: 40px;
	height: 40px;
	border: none;
	border-radius: 12px;
	background: transparent;
	font-size: 22px;
	opacity: 0.55;
	transition: opacity 0.15s, background 0.15s;
}
.group-tab.on {
	opacity: 1;
	background: var(--app-overlay-strong);
}
.image-actions {
	padding: 12px 12px 0;
}
.hidden {
	display: none;
}
.error {
	color: var(--ion-color-danger);
	text-align: center;
}
.group h3 {
	margin: 18px 16px 6px;
	font-size: 13px;
	font-weight: 600;
	letter-spacing: 0.03em;
	text-transform: uppercase;
	color: var(--ion-color-medium);
}
.grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(46px, 1fr));
	padding: 0 8px 8px;
}
.emoji {
	height: 48px;
	border: none;
	border-radius: 12px;
	background: transparent;
	font-size: 30px;
	line-height: 1;
	-webkit-tap-highlight-color: transparent;
}
.emoji:active,
.emoji.selected {
	background: var(--app-overlay-strong);
}
</style>
