<template>
	<ion-app>
		<ion-router-outlet />
		<!-- yearly review: announcement card + story, available from any screen -->
		<YearReviewPrompt />
		<YearReviewReel />
		<!-- introduction on the first start (and from the app settings) -->
		<WelcomeSheet />
	</ion-app>
</template>

<script setup lang="ts">
import { IonApp, IonRouterOutlet } from '@ionic/vue'
import WelcomeSheet from './components/WelcomeSheet.vue'
import YearReviewPrompt from './components/YearReviewPrompt.vue'
import YearReviewReel from './components/YearReviewReel.vue'
</script>
