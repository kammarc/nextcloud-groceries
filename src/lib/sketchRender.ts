/**
 * "Hand-drawn icons": turns an emoji (or a letter badge) into a one-colour pen sketch.
 *
 * The drawing is made on an offscreen canvas at a fixed resolution instead of with a live CSS/SVG
 * filter: filter primitives work on device pixels, so the lines came out thin on 3× phone screens
 * but thick and clogged on 1× screens and on Android. Here the line width is the same everywhere.
 *
 * The result is an alpha mask (data: URL) cached per glyph; ItemIcon uses it as `mask-image` over
 * `currentColor`, so the ink follows the text colour and light/dark mode without re-rendering.
 */

/** Canvas size in px: 3× the large icon box (52 CSS px), like a 3× phone screen. */
const SIZE = 156
/** Glyph size in the box, as in ItemIcon (40px font in a 52px box). */
const GLYPH = Math.round(SIZE * 40 / 52)
const EMOJI_FONT = '"Apple Color Emoji", "Segoe UI Emoji", "Noto Color Emoji", "Twemoji Mozilla", sans-serif'

const cache = new Map<string, string>()
/** Displacement fields for the pen wobble, the same for every icon: computed once. */
let wobble: [Float32Array, Float32Array] | undefined

/** Mask for an emoji if it was drawn before (no drawing), else undefined. */
export function cachedEmoji(emoji: string): string | undefined {
	return cache.get(`e:${emoji}`)
}

/** Mask for an emoji, or '' where canvas is not available. */
export function sketchEmoji(emoji: string): string {
	return cached(`e:${emoji}`, (ctx) => {
		ctx.font = `${GLYPH}px ${EMOJI_FONT}`
		drawCentered(ctx, emoji)
	})
}

/** Mask for the letter badge shown when an item has no emoji: a drawn circle with the letter. */
export function sketchLetter(letter: string): string {
	return cached(`l:${letter}`, (ctx) => {
		ctx.fillStyle = '#8a8a8a'
		ctx.beginPath()
		ctx.arc(SIZE / 2, SIZE / 2, SIZE / 2 - 4, 0, Math.PI * 2)
		ctx.fill()
		ctx.fillStyle = '#fff'
		ctx.font = `600 ${Math.round(SIZE * 0.46)}px sans-serif`
		drawCentered(ctx, letter)
	})
}

function cached(key: string, draw: (ctx: CanvasRenderingContext2D) => void): string {
	let url = cache.get(key)
	if (url === undefined) {
		try {
			url = render(draw)
		} catch {
			url = ''
		}
		cache.set(key, url)
	}
	return url
}

function drawCentered(ctx: CanvasRenderingContext2D, text: string) {
	ctx.textAlign = 'center'
	ctx.textBaseline = 'alphabetic'
	const m = ctx.measureText(text)
	const ascent = m.actualBoundingBoxAscent || GLYPH * 0.8
	const descent = m.actualBoundingBoxDescent || GLYPH * 0.1
	ctx.fillText(text, SIZE / 2, SIZE / 2 + (ascent - descent) / 2)
}

function render(draw: (ctx: CanvasRenderingContext2D) => void): string {
	const canvas = document.createElement('canvas')
	canvas.width = canvas.height = SIZE
	const ctx = canvas.getContext('2d', { willReadFrequently: true })
	if (!ctx) return ''
	ctx.fillStyle = '#fff'
	ctx.fillRect(0, 0, SIZE, SIZE)
	draw(ctx)

	const n = SIZE * SIZE
	const px = ctx.getImageData(0, 0, SIZE, SIZE).data
	const gray = new Float32Array(n)
	for (let i = 0; i < n; i++) gray[i] = (0.299 * px[i * 4] + 0.587 * px[i * 4 + 1] + 0.114 * px[i * 4 + 2]) / 255

	// edges: absolute Laplacian, i.e. light and dark side of every contour
	let ink = new Float32Array(n)
	const at = (x: number, y: number) => gray[clamp(y, 0, SIZE - 1) * SIZE + clamp(x, 0, SIZE - 1)]
	for (let y = 0; y < SIZE; y++) {
		for (let x = 0; x < SIZE; x++) {
			let sum = 0
			for (let dy = -1; dy <= 1; dy++) for (let dx = -1; dx <= 1; dx++) if (dx || dy) sum += at(x + dx, y + dy)
			ink[y * SIZE + x] = ramp(Math.min(1, 3 * Math.abs(8 * at(x, y) - sum)), [0, 0, 0.6, 1, 1])
		}
	}

	// soften into a pen stroke, then make it a little bolder again
	ink = blur(ink, 1.3)
	for (let i = 0; i < n; i++) ink[i] = ramp(ink[i], [0, 0.9, 1])

	// let the line wobble like a hand-held pen
	wobble ??= [noise(11), noise(29)]
	const [nx, ny] = wobble
	const out = ctx.createImageData(SIZE, SIZE)
	for (let y = 0; y < SIZE; y++) {
		for (let x = 0; x < SIZE; x++) {
			const i = y * SIZE + x
			out.data[i * 4 + 3] = Math.round(255 * sample(ink, x + nx[i], y + ny[i]))
		}
	}
	ctx.clearRect(0, 0, SIZE, SIZE)
	ctx.putImageData(out, 0, 0)
	return canvas.toDataURL('image/png')
}

const clamp = (v: number, lo: number, hi: number) => (v < lo ? lo : v > hi ? hi : v)

/** Piecewise linear transfer function, like SVG feFunc type="table". */
function ramp(v: number, table: number[]): number {
	const k = table.length - 1
	const pos = clamp(v, 0, 1) * k
	const i = Math.min(k - 1, Math.floor(pos))
	return table[i] + (pos - i) * (table[i + 1] - table[i])
}

function blur(src: Float32Array, sigma: number): Float32Array {
	const r = Math.ceil(sigma * 3)
	const w: number[] = []
	let total = 0
	for (let i = -r; i <= r; i++) {
		const v = Math.exp(-(i * i) / (2 * sigma * sigma))
		w.push(v)
		total += v
	}
	const pass = (a: Float32Array, horizontal: boolean) => {
		const b = new Float32Array(a.length)
		for (let y = 0; y < SIZE; y++) {
			for (let x = 0; x < SIZE; x++) {
				let s = 0
				for (let i = -r; i <= r; i++) {
					const xx = horizontal ? clamp(x + i, 0, SIZE - 1) : x
					const yy = horizontal ? y : clamp(y + i, 0, SIZE - 1)
					s += a[yy * SIZE + xx] * w[i + r]
				}
				b[y * SIZE + x] = s / total
			}
		}
		return b
	}
	return pass(pass(src, true), false)
}

/** Smooth 2-octave value noise in [-A, A] px, the same for every icon (fixed seed). */
function noise(seed: number): Float32Array {
	const AMPLITUDE = 3.2
	const hash = (x: number, y: number) => {
		let h = (x * 374761393 + y * 668265263 + seed * 2147483647) | 0
		h = Math.imul(h ^ (h >>> 13), 1274126177)
		return ((h ^ (h >>> 16)) >>> 0) / 4294967295
	}
	const smooth = (t: number) => t * t * (3 - 2 * t)
	const octave = (x: number, y: number, cell: number) => {
		const gx = x / cell
		const gy = y / cell
		const x0 = Math.floor(gx)
		const y0 = Math.floor(gy)
		const tx = smooth(gx - x0)
		const ty = smooth(gy - y0)
		const top = hash(x0, y0) + tx * (hash(x0 + 1, y0) - hash(x0, y0))
		const bottom = hash(x0, y0 + 1) + tx * (hash(x0 + 1, y0 + 1) - hash(x0, y0 + 1))
		return top + ty * (bottom - top)
	}
	const field = new Float32Array(SIZE * SIZE)
	for (let y = 0; y < SIZE; y++) {
		for (let x = 0; x < SIZE; x++) {
			const v = (octave(x, y, 26) * 2 + octave(x, y, 11)) / 3
			field[y * SIZE + x] = (v * 2 - 1) * AMPLITUDE
		}
	}
	return field
}

/** Bilinear sample, transparent outside the canvas. */
function sample(a: Float32Array, x: number, y: number): number {
	const x0 = Math.floor(x)
	const y0 = Math.floor(y)
	const tx = x - x0
	const ty = y - y0
	const get = (xx: number, yy: number) => (xx < 0 || yy < 0 || xx >= SIZE || yy >= SIZE ? 0 : a[yy * SIZE + xx])
	const top = get(x0, y0) + tx * (get(x0 + 1, y0) - get(x0, y0))
	const bottom = get(x0, y0 + 1) + tx * (get(x0 + 1, y0 + 1) - get(x0, y0 + 1))
	return top + ty * (bottom - top)
}
