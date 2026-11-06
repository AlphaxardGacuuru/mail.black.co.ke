import { countQuestions } from "../fields.mjs"

/**
 * Any application form: opens it with the page's "Apply" button when the
 * questions aren't shown yet, submits with its submit button, and reads
 * the thank-you page. Adapters for particular job-board systems override
 * the steps their sites do differently.
 */

// Buttons often carry an icon, which reads as a stray character.
const APPLY_BUTTON = /^[\s\W]*(apply|apply now|apply (for|to) (this )?(job|position|role)|apply online|start (your )?application|application|i['’]?m interested)[\s\W]*$/i

const SUBMIT_BUTTON = /^\s*(submit|submit (my |your )?application|send (my |your )?application|apply|apply now|send|finish|complete)\s*$/i

const CONFIRMATION = /thank(s| you)|application (has been |was )?(received|submitted|sent)|successfully (applied|submitted)|we('| ha)ve received your|application complete/i

export const generic = {
	name: "generic",

	matches: () => true,

	/**
	 * The frame holding the form, after clicking through to it.
	 */
	async openForm(page) {
		await dismissCookieBanner(page)

		if ((await countQuestions(page.mainFrame())) >= 3) {
			return page.mainFrame()
		}

		const embedded = await embeddedForm(page)

		if (embedded) {
			return embedded
		}

		const apply = page
			.getByRole("button", { name: APPLY_BUTTON })
			.or(page.getByRole("link", { name: APPLY_BUTTON }))
			.or(page.getByRole("tab", { name: APPLY_BUTTON }))
			.filter({ visible: true })
			.first()

		if (await apply.count()) {
			await follow(page, apply)
			await settle(page)
			await waitForQuestions(page)
		}

		return (await embeddedForm(page)) ?? page.mainFrame()
	},

	async submit(frame) {
		const button = frame
			.locator("button[type=submit]:visible, input[type=submit]:visible")
			.or(frame.getByRole("button", { name: SUBMIT_BUTTON }))
			.last()

		await button.click({ timeout: 10000 })
	},

	async confirmed(page, frame) {
		const texts = await Promise.all(
			[page.mainFrame(), frame].map((target) =>
				target.locator("body").innerText({ timeout: 3000 }).catch(() => "")
			)
		)

		return CONFIRMATION.test(texts.join("\n")) || /thank|success|confirm/i.test(page.url())
	},

	/**
	 * Messages the form shows next to fields it rejected.
	 */
	async errors(frame) {
		return frame
			.locator("[role=alert]:visible, [aria-invalid=true]:visible, .error:visible, .field-error:visible, [class*=error-message i]:visible")
			.evaluateAll((elements) =>
				[...new Set(elements.map((element) =>
					(element.innerText || element.getAttribute("aria-label") || element.name || "").replace(/\s+/g, " ").trim()
				))].filter(Boolean).slice(0, 10)
			)
			.catch(() => [])
	},
}

/**
 * A form from a job-board system shown inside the employer's own page.
 */
async function embeddedForm(page) {
	for (const frame of page.frames()) {
		if (frame !== page.mainFrame() && (await countQuestions(frame)) >= 3) {
			return frame
		}
	}

	return null
}

const COOKIE_BUTTON = /^\s*(accept|accept all|accept( all)? cookies|allow all|allow( all)? cookies|i agree|agree|got it|ok|okay)\s*$/i

/**
 * Cookie notices sit over the page and catch the clicks meant for it.
 */
export async function dismissCookieBanner(page) {
	const button = page
		.getByRole("button", { name: COOKIE_BUTTON })
		.filter({ visible: true })
		.first()

	if (await button.count()) {
		await button.click({ timeout: 3000 }).catch(() => {})
		await page.waitForTimeout(300)
	}
}

/**
 * Opens a link by its address, which no overlay can get in the way of, and
 * clicks anything else.
 */
async function follow(page, element) {
	const href = await element.getAttribute("href").catch(() => null)

	if (href && !/^(#|javascript:)/i.test(href)) {
		await page.goto(new URL(href, page.url()).href, { waitUntil: "domcontentloaded", timeout: 30000 })

		return
	}

	await element
		.click({ timeout: 5000 })
		.catch(() => element.click({ timeout: 5000, force: true }))
		.catch(() => {})
}

/**
 * Forms drawn by scripts can take a few seconds to appear after the click.
 */
async function waitForQuestions(page) {
	for (let tries = 0; tries < 10; tries++) {
		if ((await countQuestions(page.mainFrame())) >= 3 || (await embeddedForm(page))) {
			return
		}

		await page.waitForTimeout(800)
	}
}

export async function settle(page) {
	await page.waitForLoadState("networkidle", { timeout: 8000 }).catch(() => {})
	await page.waitForTimeout(800)
}
