/**
 * Opens a job application form in a headless browser, for
 * App\Services\Jobs\ApplicationForms\BrowserService.
 *
 *   inspect: reads the form's questions and takes a screenshot of it.
 *   submit:  fills in the answers, solves the CAPTCHA when a solving
 *            service is set up, submits, and screenshots the result.
 *
 * Takes its instructions as JSON on stdin and prints one JSON result on
 * stdout. Failures the user should see come back as { error }.
 */

import { lookup } from "node:dns/promises"
import { isIP } from "node:net"
import process from "node:process"
import { chromium } from "playwright"
import { adapterFor } from "./adapters/index.mjs"
import { settle } from "./adapters/generic.mjs"
import { behindBotWall, detectCaptcha, solveCaptcha } from "./captcha.mjs"
import { fillFields, readFields } from "./fields.mjs"

const input = JSON.parse(await readStdin())

const browser = await chromium.launch({ args: ["--disable-dev-shm-usage"] })

// The caller's timeout kills the process; this ends it a little earlier
// with a readable message and the browser closed.
const deadline = setTimeout(async () => {
	print({ error: "The application form took too long to respond." })
	await browser.close().catch(() => {})
	process.exit(0)
}, input.timeoutSeconds * 1000)

try {
	const page = await browser.newPage({
		viewport: { width: 1280, height: 900 },
		locale: "en-GB",
		timezoneId: "Africa/Nairobi",
	})

	await guardPrivateNetwork(page)
	await page.goto(input.url, { waitUntil: "domcontentloaded", timeout: 30000 })
	await settle(page)

	print(input.mode === "submit" ? await submit(page) : await inspect(page))
} catch (error) {
	print({ error: error.message.split("\n")[0] })
} finally {
	clearTimeout(deadline)
	await browser.close().catch(() => {})
}

async function inspect(page) {
	if (behindBotWall(page)) {
		return { url: page.url(), blocker: "bot_wall", fields: [] }
	}

	const adapter = adapterFor(page.url())
	const frame = await adapter.openForm(page)
	const fields = await readFields(frame)
	const captcha = await detectCaptcha(page)
	const signIn = await frame.locator("input[type=password]:visible").count()

	await page.screenshot({ path: input.screenshots.form, fullPage: true })

	return {
		url: frame.url(),
		adapter: adapter.name,
		captcha: captcha?.type ?? null,
		blocker: signIn ? "sign_in" : fields.length === 0 ? "no_form" : null,
		fields,
	}
}

async function submit(page) {
	const adapter = adapterFor(page.url())
	const frame = await adapter.openForm(page)
	const fields = await readFields(frame)

	// The form is filled by the keys it had when the answers were chosen;
	// if its questions have changed since, the answers may not fit.
	const current = new Set(fields.map((field) => field.key))
	const missing = input.fields.filter((field) => field.required && !current.has(field.key))

	if (missing.length) {
		return {
			error: `The form has changed since it was prepared (${missing.map((field) => field.label).slice(0, 3).join(", ")}). Prepare it again.`,
		}
	}

	const problems = await fillFields(frame, fields, input.answers, input.files)

	await page.screenshot({ path: input.screenshots.filled, fullPage: true })

	if (problems.length) {
		return { error: `Some answers didn't fit the form: ${problems.join("; ")}` }
	}

	const captcha = await detectCaptcha(page)

	if (captcha) {
		if (!input.captcha?.key) {
			return { error: "This form has a CAPTCHA and no CAPTCHA service is set up. Apply on the site." }
		}

		await solveCaptcha(page, captcha, input.captcha)
	}

	await adapter.submit(frame)
	await settle(page)
	await page.waitForTimeout(2000)

	const confirmed = await adapter.confirmed(page, frame)

	await page.screenshot({ path: input.screenshots.confirmation, fullPage: true })

	if (!confirmed) {
		const errors = await adapter.errors(frame)

		return {
			error: errors.length
				? `The form wasn't accepted: ${errors.join("; ")}`
				: "No confirmation came back after submitting. Check the screenshot, and the site, before trying again.",
			submitted: false,
		}
	}

	return { submitted: true, url: page.url(), captcha: captcha?.type ?? null }
}

/**
 * Job links come from other people's websites, so the browser may only
 * reach public addresses, never this server's own network.
 */
async function guardPrivateNetwork(page) {
	const verdicts = new Map()

	const isPublic = async (host) => {
		if (!verdicts.has(host)) {
			verdicts.set(
				host,
				(isIP(host) ? Promise.resolve([{ address: host }]) : lookup(host, { all: true }))
					.then((addresses) => addresses.length > 0 && addresses.every(({ address }) => !isPrivate(address)))
					.catch(() => false)
			)
		}

		return verdicts.get(host)
	}

	await page.context().route("**/*", async (route) => {
		const url = new URL(route.request().url())

		if (["data:", "blob:"].includes(url.protocol)) {
			return route.continue()
		}

		const allowed =
			["http:", "https:"].includes(url.protocol) &&
			["", "80", "443"].includes(url.port) &&
			(await isPublic(url.hostname.replace(/^\[|\]$/g, "")))

		return allowed ? route.continue() : route.abort("blockedbyclient")
	})
}

function isPrivate(address) {
	if (address.includes(":")) {
		const lower = address.toLowerCase()

		return (
			lower === "::1" ||
			lower === "::" ||
			/^f[cd]/.test(lower) ||
			/^fe[89ab]/.test(lower) ||
			(lower.startsWith("::ffff:") && isPrivate(lower.slice(7)))
		)
	}

	const [a, b] = address.split(".").map(Number)

	return (
		a === 0 ||
		a === 10 ||
		a === 127 ||
		(a === 100 && b >= 64 && b <= 127) ||
		(a === 169 && b === 254) ||
		(a === 172 && b >= 16 && b <= 31) ||
		(a === 192 && b === 168) ||
		a >= 224
	)
}

function print(result) {
	process.stdout.write(`${JSON.stringify(result)}\n`)
}

async function readStdin() {
	let data = ""

	for await (const chunk of process.stdin) {
		data += chunk
	}

	return data
}
