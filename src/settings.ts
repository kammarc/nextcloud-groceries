/**
 * Personal settings page (templates/personal.php): draws the QR code with the app's address,
 * so the app can be opened on a phone by scanning it. Built separately as js/groceries-settings.js.
 */
import { qrPath } from './lib/qr'

const SVG = 'http://www.w3.org/2000/svg'

function render() {
	const el = document.getElementById('groceries-qr')
	const url = el?.dataset.url
	if (!el || !url) return
	const { size, d } = qrPath(url)
	const svg = document.createElementNS(SVG, 'svg')
	svg.setAttribute('viewBox', `0 0 ${size} ${size}`)
	svg.setAttribute('shape-rendering', 'crispEdges')
	svg.setAttribute('role', 'img')
	svg.setAttribute('aria-label', el.dataset.label ?? '')
	const bg = document.createElementNS(SVG, 'rect')
	bg.setAttribute('width', String(size))
	bg.setAttribute('height', String(size))
	bg.setAttribute('fill', '#ffffff')
	const path = document.createElementNS(SVG, 'path')
	path.setAttribute('d', d)
	path.setAttribute('fill', '#000000')
	svg.append(bg, path)
	el.replaceChildren(svg)
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', render)
else render()
