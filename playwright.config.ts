import { defineConfig, devices } from "@playwright/test"

/**
 * Browser smoke tests. They drive the app that's already running locally
 * (`./vendor/bin/sail up -d` plus built assets or `npm run dev`), so start
 * it before `npm run test:e2e`. Reverb has to run too
 * (`./vendor/bin/sail artisan reverb:start`), or every page logs a failed
 * WebSocket connection.
 */
export default defineConfig({
	testDir: "tests/e2e",
	fullyParallel: true,
	forbidOnly: !!process.env.CI,
	retries: process.env.CI ? 1 : 0,
	// Sail serves the app with `artisan serve`, which answers one request at
	// a time, and every page fires half a dozen API calls on load. More than
	// a couple of browsers at once queue up behind each other until pages
	// never go network idle in time.
	workers: process.env.CI ? undefined : 2,
	// The same queue, plus each fresh browser installing the service worker,
	// puts a page test at around 20s locally.
	timeout: 60_000,
	reporter: process.env.CI ? "github" : "list",
	use: {
		baseURL: process.env.E2E_BASE_URL ?? "http://localhost:8160",
		trace: "retain-on-failure",
		screenshot: "only-on-failure",
		// The installed Chrome locally, so no browser download is needed.
		channel: process.env.CI ? undefined : "chrome",
	},
	projects: [
		{
			name: "setup",
			testMatch: /auth\.setup\.ts/,
			use: {
				// The app reloads itself once a fresh service worker takes
				// control, which can wipe the login form mid-fill.
				serviceWorkers: "block",
			},
		},
		{
			// The phone the apps are mostly used on: a Galaxy Z Fold6's cover
			// screen.
			name: "phone",
			dependencies: ["setup"],
			use: {
				...devices["Galaxy S9+"],
				viewport: { width: 360, height: 780 },
				storageState: "tests/e2e/.auth/user.json",
			},
		},
	],
})
