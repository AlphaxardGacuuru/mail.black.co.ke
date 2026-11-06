/**
 * Finds the CAPTCHA on a form and, when a solving service is set up, has
 * it solved there and puts the answer where the page looks for it.
 *
 * Both supported services (2Captcha and CapSolver) take the same
 * createTask / getTaskResult requests; only their task names differ.
 */

const PROVIDERS = {
	"2captcha": {
		url: "https://api.2captcha.com",
		tasks: {
			recaptcha_v2: "RecaptchaV2TaskProxyless",
			recaptcha_v2_enterprise: "RecaptchaV2EnterpriseTaskProxyless",
			recaptcha_v3: "RecaptchaV3TaskProxyless",
			recaptcha_v3_enterprise: "RecaptchaV3TaskProxyless",
			hcaptcha: "HCaptchaTaskProxyless",
			turnstile: "TurnstileTaskProxyless",
		},
	},
	capsolver: {
		url: "https://api.capsolver.com",
		tasks: {
			recaptcha_v2: "ReCaptchaV2TaskProxyLess",
			recaptcha_v2_enterprise: "ReCaptchaV2EnterpriseTaskProxyLess",
			recaptcha_v3: "ReCaptchaV3TaskProxyLess",
			recaptcha_v3_enterprise: "ReCaptchaV3EnterpriseTaskProxyLess",
			turnstile: "AntiTurnstileTaskProxyLess",
		},
	},
}

// Pages that check the visitor before showing anything; no form is
// reachable behind them.
const BOT_WALLS = /captcha-delivery\.com|challenges\.cloudflare\.com\/cdn-cgi\/challenge-platform|perimeterx|px-captcha/i

/**
 * The CAPTCHA on the page, if any: its kind and site key.
 */
export async function detectCaptcha(page) {
	const frames = page.frames().map((frame) => frame.url())

	for (const url of frames) {
		const recaptcha = url.match(/\/recaptcha\/(api2|enterprise)\/(anchor|bframe)\?(.*)/)

		if (recaptcha) {
			const params = new URLSearchParams(recaptcha[3])
			const enterprise = recaptcha[1] === "enterprise"
			const invisible = params.get("size") === "invisible"

			// An invisible widget is either v3 (scored, never shown) or
			// invisible v2; the page's script tag tells them apart.
			const v3 = invisible && (await page.locator("script[src*='render=']").count()) > 0

			return {
				type: `recaptcha_${v3 ? "v3" : "v2"}${enterprise ? "_enterprise" : ""}`,
				siteKey: params.get("k"),
				invisible,
			}
		}

		const hcaptcha = url.match(/hcaptcha\.com\/.*[#?&]sitekey=([\w-]+)/)

		if (hcaptcha) {
			return { type: "hcaptcha", siteKey: hcaptcha[1], invisible: false }
		}
	}

	return page.evaluate(() => {
		const widget = (selector) => document.querySelector(selector)?.getAttribute("data-sitekey")

		if (widget(".cf-turnstile[data-sitekey]")) {
			return { type: "turnstile", siteKey: widget(".cf-turnstile[data-sitekey]"), invisible: false }
		}

		if (widget(".h-captcha[data-sitekey]")) {
			return { type: "hcaptcha", siteKey: widget(".h-captcha[data-sitekey]"), invisible: false }
		}

		if (widget(".g-recaptcha[data-sitekey]")) {
			return { type: "recaptcha_v2", siteKey: widget(".g-recaptcha[data-sitekey]"), invisible: false }
		}

		const v3 = [...document.scripts]
			.map((script) => script.src.match(/recaptcha\/(api|enterprise)\.js\?.*render=([\w-]+)/))
			.find(Boolean)

		if (v3 && v3[2] !== "explicit") {
			return {
				type: v3[1] === "enterprise" ? "recaptcha_v3_enterprise" : "recaptcha_v3",
				siteKey: v3[2],
				invisible: true,
			}
		}

		return null
	})
}

export function behindBotWall(page) {
	return page.frames().some((frame) => BOT_WALLS.test(frame.url()))
}

/**
 * Has the service solve the CAPTCHA and hands the page the answer.
 * Throws when it can't.
 */
export async function solveCaptcha(page, captcha, settings) {
	const provider = PROVIDERS[settings.provider]
	const taskType = provider?.tasks[captcha.type]

	if (!taskType) {
		throw new Error(`The ${settings.provider} service can't solve a ${captcha.type} CAPTCHA.`)
	}

	const token = await requestSolution(provider.url, settings.key, {
		type: taskType,
		websiteURL: page.url(),
		websiteKey: captcha.siteKey,
		...(captcha.type.startsWith("recaptcha_v2") && { isInvisible: captcha.invisible }),
		...(captcha.type.startsWith("recaptcha_v3") && {
			pageAction: settings.pageAction || "submit",
			minScore: 0.7,
			...(settings.provider === "2captcha" && { isEnterprise: captcha.type.endsWith("enterprise") }),
		}),
	})

	await page.evaluate(giveToken, { type: captcha.type, token })
	for (const frame of page.frames()) {
		if (frame !== page.mainFrame()) {
			await frame.evaluate(giveToken, { type: captcha.type, token }).catch(() => {})
		}
	}

	return token
}

async function requestSolution(baseUrl, clientKey, task) {
	const call = async (path, body) => {
		const response = await fetch(`${baseUrl}${path}`, {
			method: "POST",
			headers: { "Content-Type": "application/json" },
			body: JSON.stringify({ clientKey, ...body }),
		})
		const data = await response.json()

		if (data.errorId) {
			throw new Error(`CAPTCHA service: ${data.errorDescription || data.errorCode}`)
		}

		return data
	}

	const { taskId } = await call("/createTask", { task })

	// Solving takes 10–60 seconds; the services ask for a poll every few.
	for (let attempt = 0; attempt < 40; attempt++) {
		await new Promise((resolve) => setTimeout(resolve, 4000))

		const result = await call("/getTaskResult", { taskId })

		if (result.status === "ready") {
			return result.solution.gRecaptchaResponse || result.solution.token
		}
	}

	throw new Error("The CAPTCHA service took too long.")
}

/**
 * Runs inside the page: puts the token in the hidden fields the form
 * sends, and makes the widget's own functions return it, so a page that
 * asks for a fresh token when the form is submitted gets this one.
 */
function giveToken({ type, token }) {
	const fill = (name) => {
		const forms = [...document.forms]

		document.querySelectorAll(`[name="${name}"]`).forEach((field) => {
			field.value = token
			field.innerHTML = token
		})

		if (!document.querySelector(`[name="${name}"]`) && forms.length) {
			const field = document.createElement("textarea")

			field.name = name
			field.style.display = "none"
			field.value = token
			forms[0].appendChild(field)
		}
	}

	if (type.startsWith("recaptcha")) {
		fill("g-recaptcha-response")

		const resolve = () => Promise.resolve(token)

		for (const api of [window.grecaptcha, window.grecaptcha?.enterprise].filter(Boolean)) {
			api.execute = (...args) => {
				callbacks().forEach((callback) => callback(token))

				return resolve(...args)
			}
			api.getResponse = () => token
		}

		// Invisible v2 hands the token to a callback the page registered
		// when it drew the widget.
		function callbacks() {
			const found = []
			const search = (value, depth) => {
				if (!value || typeof value !== "object" || depth > 4) {
return
}

				for (const [key, child] of Object.entries(value)) {
					if (key === "callback" && typeof child === "function") {
found.push(child)
} else {
search(child, depth + 1)
}
				}
			}

			search(window.___grecaptcha_cfg?.clients, 0)

			const named = document.querySelector("[data-callback]")?.getAttribute("data-callback")

			if (named && typeof window[named] === "function") {
found.push(window[named])
}

			return found
		}

		callbacks().forEach((callback) => {
			try {
				callback(token)
			} catch {
				// The page's own callback failing doesn't stop the submit.
			}
		})
	}

	if (type === "hcaptcha") {
		fill("h-captcha-response")
		fill("g-recaptcha-response")

		if (window.hcaptcha) {
			window.hcaptcha.getResponse = () => token
			window.hcaptcha.execute = () => Promise.resolve({ response: token })
		}
	}

	if (type === "turnstile") {
		fill("cf-turnstile-response")

		if (window.turnstile) {
			window.turnstile.getResponse = () => token
		}
	}
}
