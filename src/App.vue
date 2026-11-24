<template>
	<ion-app>
		<ion-router-outlet />
		<!-- yearly review: announcement card + story, available from any screen -->
		<YearReviewPrompt />
		<YearReviewReel />
		<!-- introduction on the first start (and from the app settings) -->
		<WelcomeSheet />
		<!--
			"Hand-drawn icons" app setting: turns a colour emoji into a hand-drawn line drawing in the text colour.
			Flatten onto white, find edges in the grey image (both signs of a Laplacian), make the edge strength
			the alpha, soften the stroke a little, let it wobble like a pen and fill it with the ink colour.
		-->
		<svg class="filters" width="0" height="0" aria-hidden="true" focusable="false">
			<filter id="pen-sketch" x="-10%" y="-10%" width="120%" height="120%" color-interpolation-filters="sRGB">
				<feFlood flood-color="#fff" result="paper" />
				<feComposite in="SourceGraphic" in2="paper" operator="over" result="flat" />
				<feColorMatrix in="flat" type="saturate" values="0" result="gray" />
				<feConvolveMatrix in="gray" order="3" kernelMatrix="-1 -1 -1 -1 8 -1 -1 -1 -1" preserveAlpha="true" result="outer" />
				<feConvolveMatrix in="gray" order="3" kernelMatrix="1 1 1 1 -8 1 1 1 1" preserveAlpha="true" result="inner" />
				<feComposite in="outer" in2="inner" operator="arithmetic" k2="1" k3="1" result="edges" />
				<feColorMatrix in="edges" type="matrix" values="0 0 0 0 0  0 0 0 0 0  0 0 0 0 0  3 0 0 0 0" result="ink" />
				<feComponentTransfer in="ink" result="line">
					<feFuncA type="table" tableValues="0 0 0.6 1 1" />
				</feComponentTransfer>
				<feGaussianBlur in="line" stdDeviation="0.45" result="soft" />
				<feComponentTransfer in="soft" result="stroke">
					<feFuncA type="table" tableValues="0 0.9 1" />
				</feComponentTransfer>
				<feTurbulence type="fractalNoise" baseFrequency="0.06" numOctaves="2" seed="4" result="noise" />
				<feDisplacementMap in="stroke" in2="noise" scale="3.5" xChannelSelector="R" yChannelSelector="G" result="wobbly" />
				<feFlood class="pen-ink" result="pen" />
				<feComposite in="pen" in2="wobbly" operator="in" />
			</filter>
		</svg>
	</ion-app>
</template>

<script setup lang="ts">
import { IonApp, IonRouterOutlet } from '@ionic/vue'
import WelcomeSheet from './components/WelcomeSheet.vue'
import YearReviewPrompt from './components/YearReviewPrompt.vue'
import YearReviewReel from './components/YearReviewReel.vue'
</script>

<style>
svg.filters {
	position: absolute;
	width: 0;
	height: 0;
	overflow: hidden;
	pointer-events: none;
}
/* the filter resolves its colours where the <svg> sits, so this follows light/dark mode via :root */
.pen-ink {
	flood-color: var(--ion-text-color, #222);
}
</style>
