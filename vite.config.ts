/// <reference types="vitest" />
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
// @ts-expect-error plain ES module without type declarations
import { thirdPartyNotices } from './scripts/notices-plugin.mjs'

/**
 * Builds a single ES module (js/groceries-main.js) and stylesheet (css/groceries-main.css)
 * straight into the app folders Nextcloud serves. Everything is inlined into one bundle so
 * the strict Nextcloud CSP (nonce on the entry script) does not need to cover lazy chunks.
 */
export default defineConfig(({ mode }) => ({
	plugins: [vue(), thirdPartyNotices()],
	base: './',
	publicDir: false,
	build: {
		outDir: '.',
		emptyOutDir: false,
		sourcemap: mode === 'development',
		target: ['es2020', 'safari15', 'chrome100'],
		cssCodeSplit: false,
		chunkSizeWarningLimit: 2000,
		rollupOptions: {
			input: 'src/main.ts',
			output: {
				format: 'es',
				inlineDynamicImports: true,
				entryFileNames: 'js/groceries-main.js',
				assetFileNames: (info) => (info.names?.[0] ?? info.name ?? '').endsWith('.css')
					? 'css/groceries-main.css'
					: 'js/assets/[name]-[hash][extname]',
			},
		},
	},
	test: {
		environment: 'node',
		include: ['tests/js/**/*.test.ts'],
	},
}))
