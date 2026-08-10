import { wayfinder } from "@laravel/vite-plugin-wayfinder"
import tailwindcss from "@tailwindcss/vite"
import react from "@vitejs/plugin-react"
import laravel from "laravel-vite-plugin"
import { defineConfig, loadEnv } from "vite"
import { VitePWA } from "vite-plugin-pwa"
import path from "path"

export default defineConfig(({ mode }) => {
	const env = loadEnv(mode, process.cwd(), "")
	const vitePort = env.VITE_PORT ? Number.parseInt(env.VITE_PORT, 10) : 5173

	return {
		plugins: [
			laravel({
				input: ["resources/css/app.css", "resources/js/app.tsx"],
				refresh: true,
			}),
			react({
				babel: {
					plugins: ["babel-plugin-react-compiler"],
				},
			}),
			tailwindcss(),
			wayfinder({
				formVariants: true,
			}),
			VitePWA({
				strategies: "injectManifest",
				srcDir: "resources/js",
				filename: "sw.ts",
				registerType: "autoUpdate",
				injectManifest: {
					swSrc: "resources/js/sw.ts",
					swDest: "public/sw.js",
					injectionPoint: "self.__WB_MANIFEST",
				},
				manifest: false,
				devOptions: {
					enabled: false,
				},
			}),
		],
		resolve: {
			alias: {
				"@": path.resolve(__dirname, "resources/js"),
			},
		},
		server: {
			host: "0.0.0.0",
			port: vitePort,
			hmr: {
				host: "localhost",
				clientPort: vitePort,
			},
		},
	}
})
