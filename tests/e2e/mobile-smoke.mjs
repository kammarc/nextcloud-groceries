#!/usr/bin/env node
/**
 * Browser smoke test of the installable app in iPhone and Android emulation.
 * Needs `playwright` (npm i playwright && npx playwright install chromium).
 *
 * Usage: BASE=http://localhost:8089 USER=anna PASS=secret OUT=./shots node tests/e2e/mobile-smoke.mjs
 *
 * Flow: log in to Nextcloud in the browser -> open the app (the session is converted into a
 * device app password automatically) -> create a list -> add items -> tick one -> grid view.
 */
import { chromium, devices } from 'playwright'
import { mkdirSync } from 'node:fs'

const BASE = process.env.BASE ?? 'http://localhost:8089'
const USER = process.env.USER_NAME ?? process.env.USER ?? 'anna'
const PASS = process.env.PASS ?? ''
const OUT = process.env.OUT ?? './shots'
mkdirSync(OUT, { recursive: true })

let failures = 0
const ok = (msg) => console.log('  ok   ' + msg)
const fail = (msg) => { failures++; console.log('  FAIL ' + msg) }

async function run(deviceName, mode, listName) {
	console.log(`== ${deviceName} (${mode})`)
	const browser = await chromium.launch()
	const context = await browser.newContext({ ...devices[deviceName], locale: 'de-CH' })
	// the checks below use English labels: pin the app language, independent of the account setting
	await context.addInitScript(() => localStorage.setItem('groceries:language', 'en'))
	const page = await context.newPage()
	const problems = []
	page.on('pageerror', (e) => problems.push('pageerror: ' + e.message))
	page.on('console', (m) => {
		if (m.type() === 'error' && !/favicon|apple-touch/.test(m.text())) problems.push('console: ' + m.text())
	})

	// 1. Nextcloud login (browser session)
	await page.goto(`${BASE}/index.php/login`)
	await page.fill('input[name="user"]', USER)
	await page.fill('input[name="password"]', PASS)
	await page.click('button[type="submit"]')
	await page.waitForURL((u) => !u.pathname.includes('/login'), { timeout: 30000 })

	// 2. open the app: session -> app password, lists view (only errors from the app page count)
	problems.length = 0
	await page.goto(`${BASE}/index.php/apps/groceries/`)
	await page.waitForSelector('ion-app.hydrated', { timeout: 30000 })
	const appMode = await page.evaluate(() => document.documentElement.getAttribute('mode'))
	appMode === mode ? ok(`Ionic platform mode is ${mode}`) : fail(`mode ${appMode} != ${mode}`)
	await page.getByRole('button', { name: 'New list' }).first().click()
	ok('lists view loaded (logged in via session conversion)')
	const hasCreds = await page.evaluate(() => new Promise((resolve) => {
		const req = indexedDB.open('keyval-store')
		req.onsuccess = () => {
			const tx = req.result.transaction('keyval', 'readonly').objectStore('keyval').get('groceries:credentials')
			// stored encrypted: an AES-GCM blob, never the password itself
			tx.onsuccess = () => resolve(!!tx.result?.data && !!tx.result?.iv && !('appPassword' in (tx.result ?? {})))
			tx.onerror = () => resolve(false)
		}
		req.onerror = () => resolve(false)
	}))
	hasCreds ? ok('device app password stored encrypted in IndexedDB') : fail('no encrypted app password stored')

	// 3. create a list
	const nameInput = page.locator('ion-modal ion-input input').first()
	await nameInput.fill(listName)
	await page.locator('ion-modal ion-button', { hasText: 'Create' }).click()
	await page.waitForURL(/#\/list\/\d+/, { timeout: 15000 })
	ok('list created and opened')

	// 4. add items via the add sheet
	await page.locator('ion-fab-button').click()
	const search = page.locator('ion-modal ion-searchbar input')
	await search.waitFor()
	for (const entry of ['2 l Milch', 'Bananen', 'Zahnpasta', '500 g Rinderhackfleisch', 'Zauberpulver']) {
		await search.fill(entry)
		await search.press('Enter')
		await page.waitForTimeout(150)
	}
	await page.screenshot({ path: `${OUT}/${mode}-add-sheet.png` })
	await page.locator('ion-modal ion-button', { hasText: 'Done' }).click()
	await page.waitForTimeout(800)

	const sections = (await page.locator('section.dept h3.section-title .name').allTextContents()).map((x) => x.trim())
	sections.length >= 4 ? ok(`grouped into sections: ${sections.join(' | ')}`) : fail(`sections: ${sections.join(' | ')}`)
	const milkQty = await page.locator('ion-item', { hasText: 'Milch' }).locator('.qty').first().innerText()
	milkQty.trim() === '2 l' ? ok('quantity parsed from "2 l Milch"') : fail(`milk quantity "${milkQty}"`)
	await page.waitForTimeout(3500) // one poll cycle: items confirmed by the server
	const pending = await page.locator('.item-row.pending').count()
	pending === 0 ? ok('all items synced to the server') : fail(`${pending} items still pending`)
	await page.screenshot({ path: `${OUT}/${mode}-list.png` })

	// 5. tick an item -> moves to the cart
	await page.locator('ion-item.item-row', { hasText: 'Bananen' }).click()
	await page.waitForTimeout(700)
	const cart = await page.locator('button.section-title.toggle', { hasText: /cart|Wagen/i }).first().innerText()
	if (cart.includes('(1)')) ok(`ticked item moved to cart: "${cart.trim()}"`)
	else fail(`cart: "${cart}"`)

	// 6. grid view
	await page.getByRole('button', { name: 'Grid view' }).click()
	await page.waitForTimeout(500)
	const tiles = await page.locator('.tile').count()
	tiles >= 4 ? ok(`grid view shows ${tiles} tiles`) : fail(`grid tiles: ${tiles}`)
	await page.screenshot({ path: `${OUT}/${mode}-grid.png` })

	if (problems.length) {
		fail('browser errors:\n    ' + problems.join('\n    '))
	} else {
		ok('no console/page errors (CSP, module loading)')
	}
	await browser.close()
}

await run('iPhone 13', 'ios', 'Wocheneinkauf iOS')
await run('Pixel 7', 'md', 'Wocheneinkauf Android')
console.log(failures ? `\n${failures} FAILED` : '\nALL PASSED')
process.exit(failures ? 1 : 0)
