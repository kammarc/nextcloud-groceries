<template>
	<ion-modal :is-open="reviewReel !== null" class="reel-modal" @didDismiss="closeYearReview">
		<div
			v-if="reviewReel"
			class="reel"
			:class="'bg-' + (index % 5)"
			@pointerdown="paused = true"
			@pointerup="onTap"
			@pointercancel="paused = false">
			<div class="bars">
				<span v-for="(s, i) in slides" :key="s.key" class="bar">
					<span class="fill" :class="{ done: i < index, active: i === index, paused }" :style="i === index ? { animationDuration: DURATION + 'ms' } : undefined" @animationend="next" />
				</span>
			</div>
			<button type="button" class="close" :aria-label="t('Close')" @pointerdown.stop @pointerup.stop @click.stop="closeYearReview">✕</button>

			<transition name="slide" mode="out-in">
				<section :key="slides[index].key" class="slide">
					<template v-if="slides[index].key === 'intro'">
						<p class="kicker">{{ data.kicker }}</p>
						<h1 class="year" :class="{ words: !/^d+$/.test(data.title) }">{{ data.title }}</h1>
						<p class="huge">{{ data.stats.totalBought }}</p>
						<p class="caption">{{ t('articles bought') }}</p>
						<p class="line">{{ t('on {trips} shopping trips', { trips: data.stats.trips }) }}</p>
					</template>

					<template v-else-if="slides[index].key === 'top'">
						<p class="kicker">{{ t('Your favourites') }}</p>
						<ol class="top">
							<li v-for="(r, i) in data.stats.topItems.slice(0, 5)" :key="r.normName" :style="{ animationDelay: i * 120 + 'ms' }">
								<span class="place">{{ i + 1 }}</span>
								<ItemIcon :icon="r.icon" :catalog-key="r.catalogKey" :category="r.category" :name="r.name" large />
								<span class="what">{{ r.name }}</span>
								<span class="times">{{ r.count }}×</span>
							</li>
						</ol>
					</template>

					<template v-else-if="slides[index].key === 'people'">
						<p class="kicker">{{ t('Team household') }}</p>
						<div v-if="champion" class="champion">
							<span class="crown">👑</span>
							<strong>{{ name(champion.user) }}</strong>
							<span>{{ t('went shopping most often: {trips} times', { trips: champion.trips }) }}</span>
						</div>
						<ul class="people">
							<li v-for="u in data.stats.users.slice(0, 5)" :key="u.user">
								<strong>{{ name(u.user) }}</strong>
								<span>{{ t('{bought} bought · {added} added', { bought: u.bought, added: u.added }) }}</span>
							</li>
						</ul>
					</template>

					<template v-else-if="slides[index].key === 'habits'">
						<p class="kicker">{{ t('Your shopping habits') }}</p>
						<p class="emoji-big">{{ topCategory?.icon }}</p>
						<p class="line">{{ t('Most bought department: {name}', { name: topCategory?.name ?? '' }) }}</p>
						<p class="line">{{ t('Favourite shopping day: {day}', { day: favouriteWeekday }) }}</p>
						<p class="line">{{ t('{count} articles per trip on average', { count: data.stats.perTrip }) }}</p>
					</template>

					<template v-else-if="slides[index].key === 'facts'">
						<p class="kicker">{{ t('Did you know?') }}</p>
						<p v-if="data.stats.busiestDay" class="line">{{ t('Biggest shopping day: {date} with {count} articles', { date: busiestDate, count: data.stats.busiestDay.count }) }}</p>
						<p class="line">{{ t('{count} articles were new on your lists', { count: data.stats.newItems }) }}</p>
						<p v-if="data.topList" class="line">{{ t('Busiest list: {name} ({count} articles)', { name: data.topList.name, count: data.topList.bought }) }}</p>
						<p class="outro">{{ data.outro }} 🛒</p>
					</template>

					<template v-else>
						<p class="emoji-big">🛒</p>
						<p class="line">{{ data.empty }}</p>
					</template>
				</section>
			</transition>
		</div>
	</ion-modal>
</template>

<script setup lang="ts">
import { IonModal } from '@ionic/vue'
import { computed, ref, watch } from 'vue'
import ItemIcon from './ItemIcon.vue'
import { lang, t } from '../i18n'
import { closeYearReview, reviewReel } from '../lib/review'
import { categoryLabel } from '../lib/sorting'

const DURATION = 6500
const index = ref(0)
const paused = ref(false)
let pressedAt = 0

const data = computed(() => reviewReel.value!)
const slides = computed(() => {
	const s = reviewReel.value?.stats
	if (!s || s.totalBought === 0) return [{ key: 'empty' }]
	const out = [{ key: 'intro' }, { key: 'top' }]
	if (s.users.length) out.push({ key: 'people' })
	out.push({ key: 'habits' }, { key: 'facts' })
	return out
})
watch(reviewReel, () => { index.value = 0; paused.value = false })

const name = (uid: string) => data.value.people[uid] ?? uid
const champion = computed(() => [...data.value.stats.users].sort((a, b) => b.trips - a.trips)[0] ?? null)
const topCategory = computed(() => {
	const c = data.value.stats.categories[0]
	return c ? categoryLabel(c.key, [], lang) : null
})
const favouriteWeekday = computed(() => {
	const w = data.value.stats.weekdays
	const i = w.indexOf(Math.max(...w))
	return new Intl.DateTimeFormat(lang, { weekday: 'long' }).format(new Date(2024, 0, 1 + i))
})
const busiestDate = computed(() => {
	const d = data.value.stats.busiestDay
	return d ? new Intl.DateTimeFormat(lang, { day: 'numeric', month: 'long' }).format(new Date(d.date + 'T12:00:00')) : ''
})

function next() {
	if (index.value < slides.value.length - 1) index.value++
	else closeYearReview()
}

function prev() {
	if (index.value > 0) index.value--
}

/** Short tap: left third = back, otherwise forward. Holding only pauses. */
function onTap(ev: PointerEvent) {
	const held = Date.now() - pressedAt
	paused.value = false
	if (held > 350) return
	if (ev.clientX < window.innerWidth / 3) prev()
	else next()
}
watch(paused, (p) => { if (p) pressedAt = Date.now() })
</script>

<style scoped>
.reel-modal {
	--width: 100%;
	--height: 100%;
	--border-radius: 0;
}
.reel {
	position: relative;
	height: 100%;
	padding: calc(var(--ion-safe-area-top, 0px) + 18px) 24px calc(var(--ion-safe-area-bottom, 0px) + 24px);
	color: #ffffff;
	overflow: hidden;
	user-select: none;
	-webkit-user-select: none;
	touch-action: manipulation;
	transition: background 0.6s ease;
}
.bg-0 {
	background: linear-gradient(160deg, var(--app-buy) 0%, var(--app-bg) 75%);
}
.bg-1 {
	background: linear-gradient(200deg, var(--app-recent) 0%, var(--app-sheet) 80%);
}
.bg-2 {
	background: linear-gradient(150deg, #7a4fd6 0%, var(--app-bg) 80%);
}
.bg-3 {
	background: linear-gradient(190deg, var(--app-accent) 0%, var(--app-buy-strong) 45%, var(--app-sheet) 95%);
}
.bg-4 {
	background: linear-gradient(170deg, var(--app-buy-strong) 0%, #2a1240 85%);
}
.bars {
	display: flex;
	gap: 4px;
}
.bar {
	flex: 1;
	height: 3px;
	border-radius: 2px;
	background: rgba(255, 255, 255, 0.3);
	overflow: hidden;
}
.fill {
	display: block;
	height: 100%;
	width: 0;
	background: #ffffff;
}
.fill.done {
	width: 100%;
}
.fill.active {
	animation-name: progress;
	animation-timing-function: linear;
	animation-fill-mode: forwards;
}
.fill.active.paused {
	animation-play-state: paused;
}
@keyframes progress {
	from {
		width: 0;
	}
	to {
		width: 100%;
	}
}
.close {
	position: absolute;
	top: calc(var(--ion-safe-area-top, 0px) + 30px);
	right: 16px;
	width: 40px;
	height: 40px;
	border: none;
	border-radius: 50%;
	background: rgba(0, 0, 0, 0.25);
	color: #ffffff;
	font-size: 18px;
}
.slide {
	display: flex;
	flex-direction: column;
	justify-content: center;
	height: calc(100% - 40px);
	text-align: center;
}
.kicker {
	margin: 0 0 8px;
	font-size: 15px;
	font-weight: 700;
	letter-spacing: 0.08em;
	text-transform: uppercase;
	opacity: 0.85;
}
.year {
	margin: 0;
	font-size: 64px;
	font-weight: 900;
}
/* a period name instead of a year number */
.year.words {
	font-size: 38px;
	line-height: 1.1;
}
.huge {
	margin: 24px 0 0;
	font-size: 96px;
	font-weight: 900;
	line-height: 1;
	animation: rise 0.7s ease both;
}
.caption {
	margin: 4px 0 18px;
	font-size: 20px;
	font-weight: 600;
}
.line {
	margin: 10px 0;
	font-size: 19px;
	line-height: 1.35;
}
.emoji-big {
	margin: 0;
	font-size: 84px;
	animation: rise 0.6s ease both;
}
.top {
	margin: 12px 0 0;
	padding: 0;
	list-style: none;
	text-align: start;
}
.top li {
	display: flex;
	align-items: center;
	gap: 14px;
	margin: 10px 0;
	padding: 10px 14px;
	border-radius: 16px;
	background: rgba(255, 255, 255, 0.14);
	animation: rise 0.5s ease both;
}
.place {
	width: 26px;
	font-size: 26px;
	font-weight: 900;
}
.what {
	flex: 1;
	font-size: 19px;
	font-weight: 700;
}
.times {
	font-size: 18px;
	font-weight: 800;
}
.champion {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 4px;
	margin: 8px 0 20px;
	font-size: 18px;
}
.champion strong {
	font-size: 30px;
}
.crown {
	font-size: 56px;
	animation: rise 0.6s ease both;
}
.people {
	margin: 0;
	padding: 0;
	list-style: none;
}
.people li {
	display: flex;
	flex-direction: column;
	margin: 8px 0;
	padding: 10px 14px;
	border-radius: 14px;
	background: rgba(255, 255, 255, 0.14);
}
.outro {
	margin-top: 36px;
	font-size: 28px;
	font-weight: 900;
}
@keyframes rise {
	from {
		transform: translateY(24px);
		opacity: 0;
	}
	to {
		transform: none;
		opacity: 1;
	}
}
.slide-enter-active,
.slide-leave-active {
	transition: opacity 0.25s ease, transform 0.25s ease;
}
.slide-enter-from {
	opacity: 0;
	transform: translateX(30px);
}
.slide-leave-to {
	opacity: 0;
	transform: translateX(-30px);
}
</style>
