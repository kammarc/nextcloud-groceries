import { encode } from 'uqr'

/**
 * QR code of `text` as a single SVG path: one unit per module, quiet zone included.
 * Shared by the app (QrCode.vue) and the personal settings page (settings.ts).
 */
export function qrPath(text: string): { size: number, d: string } {
	const { data, size } = encode(text, { ecc: 'M', border: 2 })
	let d = ''
	data.forEach((row, y) => row.forEach((dark, x) => {
		if (dark) d += `M${x} ${y}h1v1h-1z`
	}))
	return { size, d }
}
