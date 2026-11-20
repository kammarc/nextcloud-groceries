#!/usr/bin/env node
/**
 * Generates the app icons (SVG + PNG) without external tools.
 * The basket is defined once as stroked line segments and rasterized with a small
 * anti-aliased distance-field renderer, so PNG and SVG always match.
 *
 * Usage: node scripts/build-icons.mjs
 */
import { writeFileSync, mkdirSync } from 'node:fs'
import { join, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'
import { deflateSync } from 'node:zlib'

const root = join(dirname(fileURLToPath(import.meta.url)), '..')
const img = join(root, 'img')
mkdirSync(img, { recursive: true })

const GREEN = [46, 157, 91]
const STROKE = 32
// basket outline, handles and slats in a 512x512 box
const polylines = [
	[[96, 200], [416, 200], [382, 366], [352, 392], [160, 392], [130, 366], [96, 200]],
	[[176, 200], [232, 112]],
	[[336, 200], [280, 112]],
	[[208, 264], [208, 328]],
	[[256, 264], [256, 328]],
	[[304, 264], [304, 328]],
]

const svgPaths = polylines.map((pl) => 'M' + pl.map((p) => p.join(' ')).join('L')).join(' ')
const svg = ({ bg, stroke, rx = 112 }) => `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" width="512" height="512">${bg ? `<rect width="512" height="512" rx="${rx}" fill="${bg}"/>` : ''}<path d="${svgPaths}" fill="none" stroke="${stroke}" stroke-width="${STROKE}" stroke-linecap="round" stroke-linejoin="round"/></svg>\n`

writeFileSync(join(img, 'app-color.svg'), svg({ bg: '#2E9D5B', stroke: '#ffffff' }))
writeFileSync(join(img, 'app.svg'), svg({ stroke: '#ffffff' }))
writeFileSync(join(img, 'app-dark.svg'), svg({ stroke: '#000000' }))

const segments = polylines.flatMap((pl) => pl.slice(1).map((p, i) => [pl[i], p]))

function distToSegment(px, py, [[ax, ay], [bx, by]]) {
	const dx = bx - ax; const dy = by - ay
	const t = Math.max(0, Math.min(1, ((px - ax) * dx + (py - ay) * dy) / (dx * dx + dy * dy)))
	return Math.hypot(px - (ax + t * dx), py - (ay + t * dy))
}

function roundedRectSdf(px, py, size, r) {
	const half = size / 2
	const qx = Math.abs(px - half) - (half - r)
	const qy = Math.abs(py - half) - (half - r)
	return Math.hypot(Math.max(qx, 0), Math.max(qy, 0)) + Math.min(Math.max(qx, qy), 0) - r
}

/**
 * @param size output pixel size
 * @param scale size of the basket relative to the icon (1 = as in the SVG)
 * @param radius corner radius in 512-space (0 = square, for maskable/apple icons)
 */
function render(size, { scale = 1, radius = 112 } = {}) {
	const px = Buffer.alloc(size * size * 4)
	const k = 512 / size
	for (let y = 0; y < size; y++) {
		for (let x = 0; x < size; x++) {
			const sx = (x + 0.5) * k; const sy = (y + 0.5) * k
			const bgA = radius > 0 ? Math.max(0, Math.min(1, 0.5 - roundedRectSdf(sx, sy, 512, radius) / k)) : 1
			// map into basket space (scaled around the centre)
			const bx = 256 + (sx - 256) / scale; const by = 256 + (sy - 256) / scale
			let d = Infinity
			for (const s of segments) d = Math.min(d, distToSegment(bx, by, s))
			const fgA = Math.max(0, Math.min(1, (STROKE / 2 - d) * scale / k + 0.5))
			const i = (y * size + x) * 4
			px[i] = Math.round(GREEN[0] + (255 - GREEN[0]) * fgA)
			px[i + 1] = Math.round(GREEN[1] + (255 - GREEN[1]) * fgA)
			px[i + 2] = Math.round(GREEN[2] + (255 - GREEN[2]) * fgA)
			px[i + 3] = Math.round(255 * bgA)
		}
	}
	return png(size, size, px)
}

const crcTable = Array.from({ length: 256 }, (_, n) => {
	let c = n
	for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1
	return c >>> 0
})
const crc32 = (buf) => {
	let c = 0xffffffff
	for (const b of buf) c = crcTable[(c ^ b) & 0xff] ^ (c >>> 8)
	return (c ^ 0xffffffff) >>> 0
}
function chunk(type, data) {
	const len = Buffer.alloc(4); len.writeUInt32BE(data.length)
	const td = Buffer.concat([Buffer.from(type), data])
	const crc = Buffer.alloc(4); crc.writeUInt32BE(crc32(td))
	return Buffer.concat([len, td, crc])
}
function png(w, h, rgba) {
	const ihdr = Buffer.alloc(13)
	ihdr.writeUInt32BE(w, 0); ihdr.writeUInt32BE(h, 4)
	ihdr[8] = 8; ihdr[9] = 6; ihdr[10] = 0; ihdr[11] = 0; ihdr[12] = 0
	const raw = Buffer.alloc((w * 4 + 1) * h)
	for (let y = 0; y < h; y++) {
		raw[y * (w * 4 + 1)] = 0
		rgba.copy(raw, y * (w * 4 + 1) + 1, y * w * 4, (y + 1) * w * 4)
	}
	return Buffer.concat([
		Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]),
		chunk('IHDR', ihdr), chunk('IDAT', deflateSync(raw, { level: 9 })), chunk('IEND', Buffer.alloc(0)),
	])
}

writeFileSync(join(img, 'app-192.png'), render(192))
writeFileSync(join(img, 'app-512.png'), render(512))
writeFileSync(join(img, 'app-maskable-512.png'), render(512, { scale: 0.72, radius: 0 }))
writeFileSync(join(img, 'apple-touch-icon.png'), render(180, { scale: 0.84, radius: 0 }))
console.log('icons written to img/')
