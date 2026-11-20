<template>
	<!-- announcement card: "your shopping year is ready" -->
	<ion-modal
		:is-open="reviewPrompt !== null || reviewLoading"
		:initial-breakpoint="0.5"
		:breakpoints="[0, 0.5]"
		:handle="true"
		class="add-sheet"
		@didDismiss="dismissReviewPrompt">
		<ion-content class="ion-padding prompt">
			<div class="confetti" aria-hidden="true">🎉</div>
			<h2>{{ t('Your shopping year {year} is ready', { year: shownYear }) }}</h2>
			<p>{{ t('A short look back at what your household bought this year.') }}</p>
			<ion-button expand="block" size="large" class="go" :disabled="reviewLoading" @click="open">
				<ion-spinner v-if="reviewLoading" slot="start" name="crescent" />
				{{ t('Show my year') }}
			</ion-button>
			<ion-button expand="block" fill="clear" @click="dismissReviewPrompt">{{ t('Later') }}</ion-button>
		</ion-content>
	</ion-modal>
</template>

<script setup lang="ts">
import { IonButton, IonContent, IonModal, IonSpinner } from '@ionic/vue'
import { ref, watch } from 'vue'
import { t } from '../i18n'
import { dismissReviewPrompt, openYearReview, reviewLoading, reviewPrompt } from '../lib/review'

// keep the year while the story is being loaded (the prompt itself is cleared then)
const shownYear = ref(new Date().getFullYear())
watch(reviewPrompt, (y) => { if (y !== null) shownYear.value = y }, { immediate: true })

function open() {
	openYearReview(shownYear.value)
}
</script>

<style scoped>
.prompt {
	text-align: center;
}
.confetti {
	margin-top: 10px;
	font-size: 64px;
	animation: pop 0.6s ease;
}
@keyframes pop {
	0% {
		transform: scale(0.4) rotate(-15deg);
		opacity: 0;
	}
	70% {
		transform: scale(1.15) rotate(6deg);
		opacity: 1;
	}
	100% {
		transform: scale(1) rotate(0);
	}
}
h2 {
	margin: 8px 0 6px;
	font-weight: 800;
}
p {
	margin: 0 0 18px;
	color: var(--app-muted);
}
.go {
	--background: var(--app-buy);
	--background-activated: var(--app-buy-strong);
	--color: #ffffff;
}
</style>
