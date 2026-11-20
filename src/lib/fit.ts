/**
 * How much a tile has to shrink its label (and icon) so a name fits a ~100 px tile.
 * German compounds like "Spülmaschinentabs" are the problem: one long word cannot wrap
 * at spaces, so long words get a smaller font, a smaller icon and hyphenation.
 */
export function nameFit(name: string): '' | 'long' | 'xlong' {
	const longestWord = Math.max(0, ...name.split(/[\s/-]+/).map((w) => w.length))
	if (longestWord > 13 || name.length > 26) return 'xlong'
	if (longestWord > 9 || name.length > 18) return 'long'
	return ''
}
