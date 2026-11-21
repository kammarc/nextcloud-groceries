import { defineConfig } from 'vite'

/**
 * Small separate script for Nextcloud's personal settings page (QR code with the app address),
 * loaded there with \OCP\Util::addScript(). A classic script (IIFE), independent of the app bundle.
 */
export default defineConfig({
	publicDir: false,
	build: {
		outDir: '.',
		emptyOutDir: false,
		target: 'es2019',
		lib: {
			entry: 'src/settings.ts',
			formats: ['iife'],
			name: 'GroceriesSettings',
			fileName: () => 'js/groceries-settings.js',
		},
	},
})
