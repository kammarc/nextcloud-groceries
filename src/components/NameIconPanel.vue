<template>
	<ion-header>
		<ion-toolbar>
			<ion-buttons slot="start">
				<ion-button @click="emit('close')">{{ t('Cancel') }}</ion-button>
			</ion-buttons>
			<ion-title>{{ title }}</ion-title>
			<ion-buttons slot="end">
				<ion-button strong :disabled="!name.trim()" @click="save">{{ t('Save') }}</ion-button>
			</ion-buttons>
		</ion-toolbar>
	</ion-header>
	<ion-content class="ion-padding-vertical">
		<ion-list :inset="true">
			<ion-item>
				<ion-input v-model="name" :label="nameLabel ?? t('Name')" label-placement="stacked" autocapitalize="sentences" @keydown.enter="save" />
			</ion-item>
		</ion-list>
		<ion-list :inset="true">
			<IconButton v-model:icon="icon" v-model:image="image" :allow-image="allowImage" :fallback="fallback" />
		</ion-list>
	</ion-content>
</template>

<script setup lang="ts">
import { IonButton, IonButtons, IonContent, IonHeader, IonInput, IonItem, IonList, IonTitle, IonToolbar } from '@ionic/vue'
import { ref } from 'vue'
import IconButton from './IconButton.vue'
import { t } from '../i18n'

/** Name + icon (emoji or, with allowImage, a picture) editor used for stores and categories. */
const props = defineProps<{
	title: string
	nameLabel?: string
	initialName?: string
	initialIcon?: string | null
	initialImage?: string | null
	allowImage?: boolean
	fallback?: string
}>()
const emit = defineEmits<{ close: [], save: [value: { name: string, icon: string | null, image: string | null }] }>()

const name = ref(props.initialName ?? '')
const icon = ref<string | null>(props.initialIcon ?? null)
const image = ref<string | null>(props.initialImage ?? null)

function save() {
	if (!name.value.trim()) return
	emit('save', { name: name.value.trim(), icon: icon.value, image: image.value })
}
</script>
