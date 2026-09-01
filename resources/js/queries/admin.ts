import { keepPreviousData, useQuery } from "@tanstack/react-query"
import Axios from "@/lib/axios"

export type AdminDashboardData = {
	totals: {
		mailsSent: number
		mailsDelivered: number
		mailsFailed: number
		mailsBounced: number
		mailsQueued: number
		mailsReceived: number
		totalUsers: number
		totalWebhookEvents: number
		webhookEventsLast24h: number
	}
	statusBreakdown: { status: string; count: number }[]
	dailyVolume: { date: string; sent: number; failed: number }[]
	recentFailures: {
		id: string
		subject: string | null
		to: string
		status: string
		errorMessage: string | null
		createdAt: string
	}[]
}

export function useAdminDashboard() {
	return useQuery({
		queryKey: ["admin", "dashboard"],
		queryFn: () =>
			Axios.get<{ data: AdminDashboardData }>("api/admin/dashboard").then(
				(res) => res.data.data
			),
	})
}

export type AdminWebhookEvent = {
	id: string
	providerEventId: string
	event: string
	status: string | null
	mailMessageId: string | null
	mailMessageSubject: string | null
	occurredAt: string | null
	createdAt: string
	payload: Record<string, unknown>
}

export type AdminWebhookFilters = {
	q?: string
	event?: string
	status?: string
	from?: string
	to?: string
	page?: number
	perPage?: number
}

type Paginated<T> = {
	data: T[]
	meta: {
		current_page: number
		last_page: number
		total: number
	}
}

export function useAdminWebhooks(filters: AdminWebhookFilters) {
	return useQuery({
		queryKey: ["admin", "webhooks", filters],
		queryFn: () =>
			Axios.get<Paginated<AdminWebhookEvent>>("api/admin/webhooks", {
				params: {
					...(filters.q ? { q: filters.q } : {}),
					...(filters.event ? { event: filters.event } : {}),
					...(filters.status ? { status: filters.status } : {}),
					...(filters.from ? { from: filters.from } : {}),
					...(filters.to ? { to: filters.to } : {}),
					page: filters.page ?? 1,
					perPage: filters.perPage ?? 20,
				},
			}).then((res) => res.data),
		placeholderData: keepPreviousData,
	})
}
