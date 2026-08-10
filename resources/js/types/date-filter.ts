export type DateFilterType =
	| "all_time"
	| "today"
	| "week"
	| "month"
	| "year"
	| "date"
	| "dateRange"

export type DateFilterParams = {
	filter?: DateFilterType
	date?: string
	startDate?: string
	endDate?: string
}