export type MailDraft = {
	to: string[]
	cc: string[]
	bcc: string[]
	subject: string
	body: string
}

function decode(value: string): string {
	try {
		return decodeURIComponent(value)
	} catch {
		return value
	}
}

function addresses(value: string): string[] {
	return decode(value)
		.split(",")
		.map((address) => address.trim())
		.filter(Boolean)
}

function escapeHtml(text: string): string {
	return text
		.replace(/&/g, "&amp;")
		.replace(/</g, "&lt;")
		.replace(/>/g, "&gt;")
		.replace(/"/g, "&quot;")
}

/**
 * Plain text as the editor's HTML: blank lines start a paragraph, single
 * line breaks stay line breaks.
 */
function textToHtml(text: string): string {
	return text
		.trim()
		.split(/\r?\n\s*\r?\n/)
		.filter((paragraph) => paragraph.trim() !== "")
		.map(
			(paragraph) => `<p>${escapeHtml(paragraph).replace(/\r?\n/g, "<br>")}</p>`
		)
		.join("")
}

/**
 * A new message from a mailto: link (RFC 6068), e.g.
 * "mailto:a@x.com,b@x.com?cc=c@x.com&subject=Hi&body=Hello". Values are
 * percent-decoded only: unlike a query string, "+" is kept, as it's common
 * in addresses. Null when the link isn't a mailto: link.
 */
export function parseMailto(link: string): MailDraft | null {
	const match = link.trim().match(/^mailto:([^?]*)(?:\?(.*))?$/is)

	if (!match) {
		return null
	}

	const draft: MailDraft = {
		to: addresses(match[1]),
		cc: [],
		bcc: [],
		subject: "",
		body: "",
	}

	;(match[2] ?? "")
		.split("&")
		.filter(Boolean)
		.forEach((field) => {
			const [key, ...rest] = field.split("=")
			const value = rest.join("=")

			switch (decode(key).toLowerCase()) {
				case "to":
					draft.to.push(...addresses(value))
					break
				case "cc":
					draft.cc.push(...addresses(value))
					break
				case "bcc":
					draft.bcc.push(...addresses(value))
					break
				case "subject":
					draft.subject = decode(value)
					break
				case "body":
					draft.body = textToHtml(decode(value))
					break
			}
		})

	return draft
}

/**
 * The draft for the mailto: link the installed app was opened with (its
 * protocol handler sends it as ?mailto=), if any.
 */
export function mailtoDraftFromLocation(): MailDraft | null {
	const link = new URLSearchParams(window.location.search).get("mailto")

	return link ? parseMailto(link) : null
}
