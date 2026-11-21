/** Shares a JSON file on phones (share sheet) and downloads it elsewhere. */
export async function saveJsonFile(fileName: string, data: unknown) {
	const file = new File([JSON.stringify(data, null, '\t')], fileName, { type: 'application/json' })
	const nav = navigator as Navigator & { canShare?: (d: unknown) => boolean }
	if (nav.canShare?.({ files: [file] })) {
		try {
			await navigator.share({ files: [file], title: fileName })
			return
		} catch {
			// cancelled or not allowed: fall back to a download
		}
	}
	const a = document.createElement('a')
	a.href = URL.createObjectURL(file)
	a.download = fileName
	document.body.appendChild(a)
	a.click()
	a.remove()
	setTimeout(() => URL.revokeObjectURL(a.href), 2000)
}

/** "Weekly shop" -> "weekly-shop" for file names. */
export function fileSlug(name: string): string {
	return name.toLowerCase().replace(/[^a-z0-9äöü]+/gi, '-').replace(/^-+|-+$/g, '') || 'export'
}

export const today = () => new Date().toISOString().slice(0, 10)
