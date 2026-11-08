import type { JobSalary } from "@/types"

const PERIODS: Record<JobSalary["period"], { long: string; short: string }> = {
	month: { long: "a month", short: "/mo" },
	year: { long: "a year", short: "/yr" },
	week: { long: "a week", short: "/wk" },
	day: { long: "a day", short: "/day" },
	hour: { long: "an hour", short: "/hr" },
}

function amount(value: number, compact: boolean): string {
	return value.toLocaleString("en-KE", {
		notation: compact ? "compact" : "standard",
		maximumFractionDigits: compact ? 1 : 0,
	})
}

/**
 * "KES 90,000 – 150,000 a month", or compact for a card: "KES 90K–150K/mo".
 * An estimate is marked with "~".
 */
export function formatSalary(salary: JobSalary, compact = false): string {
	const range =
		salary.min && salary.min !== salary.max
			? `${amount(salary.min, compact)}${compact ? "–" : " – "}${amount(salary.max, compact)}`
			: `${salary.min ? "" : "up to "}${amount(salary.max, compact)}`
	const period = PERIODS[salary.period] ?? PERIODS.month

	return `${salary.stated ? "" : "~"}${salary.currency} ${range}${compact ? period.short : ` ${period.long}`}`
}
