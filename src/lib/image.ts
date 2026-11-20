/**
 * Turns a picked picture into a small square data: URL (centre crop, 160 px), so store logos
 * can be stored directly in the database and work offline.
 */
export async function imageFileToDataUrl(file: File, size = 160): Promise<string> {
	const src = await new Promise<string>((resolve, reject) => {
		const reader = new FileReader()
		reader.onload = () => resolve(String(reader.result))
		reader.onerror = () => reject(reader.error)
		reader.readAsDataURL(file)
	})
	const img = await new Promise<HTMLImageElement>((resolve, reject) => {
		const el = new Image()
		el.onload = () => resolve(el)
		el.onerror = () => reject(new Error('Unsupported image'))
		el.src = src
	})
	const canvas = document.createElement('canvas')
	canvas.width = size
	canvas.height = size
	const ctx = canvas.getContext('2d')
	if (!ctx) throw new Error('Canvas not available')
	const side = Math.min(img.naturalWidth, img.naturalHeight)
	const sx = (img.naturalWidth - side) / 2
	const sy = (img.naturalHeight - side) / 2
	ctx.imageSmoothingQuality = 'high'
	ctx.drawImage(img, sx, sy, side, side, 0, 0, size, size)
	// WebP where supported (small); Safari falls back to PNG automatically
	return canvas.toDataURL('image/webp', 0.85)
}
