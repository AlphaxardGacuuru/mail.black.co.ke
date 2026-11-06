import { generic } from "./generic.mjs"

/**
 * Adapters for job-board systems whose forms need steps of their own,
 * each with a matches(url) test; the first that matches is used. Steps an
 * adapter leaves out are the generic ones, which handle the forms tried so
 * far (Greenhouse, BambooHR, Workable, JazzHR, Ashby, Breezy, Zoho
 * Recruit, Pinpoint and careers-page.com).
 */
const adapters = []

export function adapterFor(url) {
	const adapter = adapters.find((candidate) => candidate.matches(url))

	return adapter ? { ...generic, ...adapter } : generic
}
