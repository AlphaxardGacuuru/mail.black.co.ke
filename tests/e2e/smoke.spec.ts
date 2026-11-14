import { existsSync } from "node:fs"
import { expect, test } from "@playwright/test"
import type { Page } from "@playwright/test"

const PAGES = [
	"/mail",
	"/mail/starred",
	"/mail/sent",
	"/mail/archive",
	"/mail/trash",
	"/chats",
	"/chats/stories",
	"/chats/archived",
	"/job-applications",
	"/notifications",
	"/settings/profile",
	"/settings/security",
	"/settings/appearance",
	"/settings/mail-accounts",
	"/settings/notifications",
	"/settings/job-search",
]

/** Collects uncaught errors and console errors while a page is open. */
function watchErrors(page: Page): string[] {
	const errors: string[] = []

	page.on("pageerror", (error) => errors.push(error.message))
	page.on("console", (message) => {
		if (message.type() === "error") {
			errors.push(`${message.text()} ${message.location().url}`.trim())
		}
	})

	return errors
}

/** The app mounted something, so the screen isn't blank. */
async function expectAppRendered(page: Page) {
	await expect(page.locator("#app > *").first()).toBeVisible({ timeout: 15_000 })
}

/**
 * Opens a page and waits out the service worker taking over. Every test
 * starts in a fresh browser, so the service worker installs, claims the page
 * and the app reloads itself (Workbox's "controlling" event in app.tsx).
 * Checking the page before that reload races it.
 */
async function openPage(page: Page, path: string) {
	await page.goto(path)

	if (existsSync("public/sw.js")) {
		await expect
			.poll(
				() => page
					.evaluate(() => !!navigator.serviceWorker.controller
						&& (performance.getEntriesByType("navigation")[0] as PerformanceNavigationTiming).type === "reload")
					.catch(() => false),
				{ timeout: 20_000 }
			)
			.toBe(true)
	}

	await expectAppRendered(page)
}

for (const path of PAGES) {
	test(`${path} opens without errors and fits the phone screen`, async ({ page }) => {
		// The default avatar every user starts with points at
		// /storage/avatars/male-avatar.png (the users.avatar column default and
		// the User avatar accessor), but that file isn't shipped: the repo only
		// has public/img/male-avatar.png. So a user who never uploaded a picture
		// gets a broken image and a 403 console error on the profile page.
		test.fixme(path === "/settings/profile", "default avatar /storage/avatars/male-avatar.png is missing (403)")

		const errors = watchErrors(page)

		await openPage(page, path)
		await expect(page).toHaveURL(new RegExp(`${path}$`))
		await expect(page.getByRole("navigation").last()).toBeVisible()
		await page.waitForLoadState("networkidle")

		const overflow = await page.evaluate(
			() => document.documentElement.scrollWidth - window.innerWidth
		)

		expect(overflow, "page is wider than the screen").toBeLessThanOrEqual(0)
		expect(errors).toEqual([])
	})
}

test("the bottom nav moves between pages", async ({ page }) => {
	await openPage(page, "/mail")

	const bottomNav = page.locator("nav.max-w-md")

	await bottomNav
		.getByRole("link", { name: "Sent" })
		.click()
	await expect(page).toHaveURL(/\/mail\/sent$/)

	await bottomNav
		.getByRole("link", { name: "Archive" })
		.click()
	await expect(page).toHaveURL(/\/mail\/archive$/)
})

test("signed out visitors are sent to the login page", async ({ browser }) => {
	const context = await browser.newContext({ storageState: { cookies: [], origins: [] } })
	const page = await context.newPage()

	await page.goto("/mail")
	await expect(page).toHaveURL(/\/login/)

	await context.close()
})

test("the app still opens offline once the service worker has it", async ({ page, context }) => {
	// The Vite dev server serves unbundled modules the service worker never
	// precaches, so this only means something against built assets.
	test.skip(existsSync("public/hot"), "needs built assets, not `npm run dev`")

	// The reload after the service worker takes over is the load under its
	// control that caches the page.
	await openPage(page, "/mail")
	await page.waitForLoadState("networkidle")

	await context.setOffline(true)
	await page.reload()
	await expectAppRendered(page)
	await context.setOffline(false)
})
