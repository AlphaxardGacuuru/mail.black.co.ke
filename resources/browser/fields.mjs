/**
 * Reads the questions on an application form and fills them in.
 *
 * Every field found is tagged with a data-bm-key attribute. The keys come
 * from the page's own names and their order, so reading the same form
 * again on a fresh page gives the same keys, which is how answers chosen
 * on one visit are filled in on the next.
 */

/**
 * Runs inside the page, so it can only use what's defined within it.
 */
function collect() {
	const SKIPPED_TYPES = ["hidden", "submit", "button", "search", "image", "reset"]
	const clean = (value) => (value || "").replace(/\s+/g, " ").trim()
	const text = (element) => clean(element?.innerText ?? element?.textContent)

	// Words that name a control rather than the question it asks.
	const GENERIC = /^(attach|upload|browse|choose( a)? file|no file (selected|chosen)|dropbox|google drive|enter manually|or|file-input|select\.*|–?select–?|select an? option|type here|start typing\.*|enter\.*)?\s*\*?$/i

	const usable = (label) => (label && !GENERIC.test(label) ? label : "")

	// The nearest text above the field, within its question's block.
	const headingOf = (element) => {
		let block = element.parentElement

		for (let depth = 0; block && depth < 6; depth++, block = block.parentElement) {
			const heading = [...block.querySelectorAll("label, legend, [class*=label i], [class*=question i], [class*=title i]")]
				.filter((candidate) =>
					!candidate.contains(element) &&
					candidate.compareDocumentPosition(element) & Node.DOCUMENT_POSITION_FOLLOWING &&
					usable(text(candidate))
				)
				.pop()

			if (heading) {
return text(heading)
}
		}

		return ""
	}

	// Upload boxes often have no label, only text around them ("Resume
	// *\nDrop your file here"); the first line is the question.
	const surroundingText = (element) => {
		let block = element.parentElement

		for (let depth = 0; block && depth < 5; depth++, block = block.parentElement) {
			const line = clean((block.innerText || "").split("\n").find((part) => usable(clean(part))))

			if (line) {
return line.slice(0, 200)
}
		}

		return ""
	}

	const labelOf = (element) => {
		const labelledBy = element.getAttribute("aria-labelledby")

		return (
			usable(element.id ? text(document.querySelector(`label[for="${CSS.escape(element.id)}"]`)) : "") ||
			usable(
				labelledBy
					? labelledBy
							.split(/\s+/)
							// A list button names itself with its current choice.
							.filter((id) => id !== element.id)
							.map((id) => text(document.getElementById(id)))
							.filter(Boolean)
							.join(" ")
					: ""
			) ||
			usable(text(element.closest("label"))) ||
			usable(clean(element.getAttribute("aria-label"))) ||
			usable(clean(element.placeholder)) ||
			(element.type === "file"
				? surroundingText(element) || headingOf(element)
				: headingOf(element) || surroundingText(element))
		)
	}

	const groupLabelOf = (element) => {
		const group = element.closest("fieldset, [role=radiogroup], [role=group]")
		const legend = group?.querySelector("legend, [class*=label i], [class*=question i]")

		if (legend && !legend.contains(element) && text(legend)) {
return text(legend)
}

		// Choices laid out without a group: the question is the text above
		// the first of them.
		return headingOf(group ?? element.closest("label") ?? element)
	}

	const isShown = (element) => {
		if (element.type === "file") {
return true
}

		const style = getComputedStyle(element)
		const rect = element.getBoundingClientRect()

		return (
			style.visibility !== "hidden" &&
			style.display !== "none" &&
			rect.width > 2 &&
			rect.height > 2 &&
			rect.right > 0 &&
			rect.bottom > -2000 &&
			!element.closest("[aria-hidden=true], [hidden]")
		)
	}

	// Fields meant to catch bots, which humans never see or fill, and
	// uploads that fill in the form from a CV, which would overwrite the
	// answers.
	const isSkipped = (element, label) =>
		/leave (this|it)? ?(field )?(blank|empty)|do not fill|autofill/i.test(label) ||
		/honey|hp_|bot.?field|autofill/i.test(`${element.name} ${element.id} ${element.className}`)

	const isCombobox = (element) =>
		element.getAttribute("role") === "combobox" ||
		["list", "both"].includes(element.getAttribute("aria-autocomplete") || "")

	const fields = []
	const seenGroups = new Set()
	const usedKeys = new Map()

	const keyFor = (base) => {
		const slug = clean(base).toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/^-|-$/g, "").slice(0, 60) || "field"
		const count = usedKeys.get(slug) ?? 0

		usedKeys.set(slug, count + 1)

		return count ? `${slug}-${count + 1}` : slug
	}

	// Buttons that open a list of choices stand in for <select> on many
	// sites; outside a form they're menus, not questions.
	const controls = document.querySelectorAll(
		"input, textarea, select, form button[aria-haspopup=listbox], form button[aria-haspopup=true]"
	)

	for (const element of controls) {
		const type =
			element.tagName === "BUTTON"
				? "dropdown"
				: element.tagName === "INPUT"
					? element.type || "text"
					: element.tagName.toLowerCase()

		if (SKIPPED_TYPES.includes(type) || element.disabled || element.readOnly && type !== "file") {
continue
}

		if (!isShown(element)) {
continue
}

		if (type === "radio") {
			const name = element.name || element.id

			if (seenGroups.has(name)) {
continue
}

			seenGroups.add(name)

			const options = [...document.querySelectorAll(`input[type=radio][name="${CSS.escape(element.name)}"]`)]
			const label = groupLabelOf(element)
			const key = keyFor(`radio-${element.name || label}`)

			options.forEach((option, index) => option.setAttribute("data-bm-key", `${key}::${index}`))
			fields.push({
				key,
				type: "radio",
				label,
				required: options.some((option) => option.required),
				options: options.map(labelOf),
			})

			continue
		}

		// A list button's label often ends with its current choice.
		const label = type === "dropdown"
			? labelOf(element).replace(text(element), "").trim()
			: labelOf(element)

		if (isSkipped(element, label)) {
continue
}

		// Nothing can be said about an unlabelled field nobody has to fill.
		const required =
			element.required ||
			element.getAttribute("aria-required") === "true" ||
			/\*\s*$/.test(label)

		if (!label && !required && type !== "file") {
continue
}

		const key = keyFor(`${type}-${element.name || element.id || label}`)

		element.setAttribute("data-bm-key", key)
		fields.push({
			key,
			type: isCombobox(element) ? "combobox" : type,
			label,
			required,
			options:
				type === "select"
					? [...element.options]
							.filter((option) => option.value !== "" && text(option))
							.map((option) => text(option))
					: [],
			// A checkbox in a list ("Which offices could you work from?")
			// is one choice; the list's question gives it its meaning.
			group:
				type === "checkbox" && groupLabelOf(element) !== label
					? groupLabelOf(element)
					: undefined,
			accept: type === "file" ? element.accept || "" : undefined,
			maxLength: element.maxLength > 0 ? element.maxLength : undefined,
		})
	}

	return fields
}

/**
 * How many questions the frame shows that take typed or chosen answers;
 * uploads and tick boxes (cookie settings, newsletters) don't make a form.
 */
export async function countQuestions(frame) {
	return frame
		.evaluate(collect)
		.then((fields) => fields.filter((field) => !["file", "checkbox", "radio"].includes(field.type)).length)
		.catch(() => 0)
}

/**
 * The questions on the form, with the choices of custom dropdowns read by
 * opening them.
 */
export async function readFields(frame) {
	const fields = await frame.evaluate(collect)

	for (const field of fields) {
		if (["combobox", "dropdown"].includes(field.type) && field.options.length === 0) {
			field.options = await listedOptions(frame, field.key)
		}

		field.label = field.label.replace(/^\s*\*\s*|\s*\*\s*$/g, "").slice(0, 500)
	}

	return fields
}

const LISTED_OPTION = "[role=option]:visible, [role=menuitem]:visible, [role=menuitemradio]:visible"

async function listedOptions(frame, key) {
	const input = frame.locator(`[data-bm-key="${key}"]`)

	try {
		await input.click({ timeout: 3000 })
		await frame.waitForTimeout(400)

		const options = await frame
			.locator(LISTED_OPTION)
			.evaluateAll((elements) =>
				elements.map((element) => element.innerText.replace(/\s+/g, " ").trim()).filter(Boolean)
			)

		await input.press("Escape")

		return options.slice(0, 300)
	} catch {
		return []
	}
}

const TRUE_WORDS = /^(yes|true|on|agree|checked|1|i agree|i accept|accept)$/i

/**
 * Puts an answer into each field. Returns the questions it couldn't fill.
 */
export async function fillFields(frame, fields, answers, files) {
	const problems = []

	for (const field of fields) {
		const answer = answers[field.key]

		if (field.type === "file") {
			const path = files[answer]

			if (path) {
				await frame.locator(`[data-bm-key="${field.key}"]`).setInputFiles(path)
			}

			continue
		}

		if (answer === undefined || answer === null || String(answer).trim() === "") {
continue
}

		try {
			await fillField(frame, field, String(answer))
		} catch (error) {
			problems.push(`${field.label || field.key}: ${error.message.split("\n")[0]}`)
		}
	}

	return problems
}

async function fillField(frame, field, answer) {
	const input = frame.locator(`[data-bm-key="${field.key}"]`)

	switch (field.type) {
		case "radio": {
			const index = field.options.findIndex((option) => sameText(option, answer))

			if (index === -1) {
throw new Error(`"${answer}" isn't one of the choices`)
}

			await frame.locator(`[data-bm-key="${field.key}::${index}"]`).check({ force: true })

			return
		}

		case "checkbox":
			if (TRUE_WORDS.test(answer.trim())) {
				await input.check({ force: true })
			}

			return

		case "dropdown": {
			await input.click()
			await frame.waitForTimeout(400)

			const options = frame.locator(LISTED_OPTION)
			const index = (await options.allInnerTexts()).findIndex((option) => sameText(option, answer))

			if (index === -1) {
				await input.press("Escape")

				throw new Error(`"${answer}" isn't one of the choices`)
			}

			await options.nth(index).click()

			return
		}

		case "select": {
			const option = field.options.find((choice) => sameText(choice, answer)) ?? answer

			await input.selectOption({ label: option })

			return
		}

		case "combobox": {
			await input.click()
			await input.fill(answer)
			await frame.waitForTimeout(600)

			const option = frame
				.locator("[role=option]:visible")
				.filter({ hasText: answer })
				.first()

			if (await option.count()) {
				await option.click()
			} else {
				await input.press("Enter")
			}

			return
		}

		default:
			await input.fill(answer)
	}
}

function sameText(a, b) {
	const normalise = (value) => String(value).toLowerCase().replace(/\s+/g, " ").trim()

	return normalise(a) === normalise(b)
}
