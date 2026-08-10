import type { DateFilterParams } from "@/types/date-filter"

/**
 * Converts DateFilterParams into a URL query string (e.g. "?filter=month").
 * Returns an empty string when no filter is active (all_time).
 */
export function buildFilterQuery(filters: DateFilterParams): string {
	const params = new URLSearchParams()

	const filter = filters.filter ?? "all_time"

	if (filter !== "all_time") {
		params.set("filter", filter)
	}

	if (
		(filter === "today" ||
			filter === "week" ||
			filter === "month" ||
			filter === "year" ||
			filter === "date") &&
		filters.date
	) {
		params.set("date", filters.date)
	}

	if (filter === "dateRange") {
		if (filters.startDate) {
			params.set("startDate", filters.startDate)
		}

		if (filters.endDate) {
			params.set("endDate", filters.endDate)
		}
	}

	const query = params.toString()

	return query ? `?${query}` : ""
}
