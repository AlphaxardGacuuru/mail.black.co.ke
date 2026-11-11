import {
	keepPreviousData,
	useMutation,
	useQuery,
	useQueryClient,
} from "@tanstack/react-query"
import Axios from "@/lib/axios"
import type { MailgunAccount } from "@/types"

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

export type AdminUser = {
	id: string
	name: string
	email: string
	phone: string | null
	gender: "male" | "female" | "other" | null
	avatar: string | null
	verified: boolean
	mailgunAccounts: MailgunAccount[]
	createdAt: string
}

type AdminUsersResponse = {
	data: AdminUser[]
	meta: { current_page: number; last_page: number; total: number }
}

export function useAdminUsers(search: string, page = 1, perPage = 20) {
	return useQuery({
		queryKey: ["admin", "users", search, page, perPage],
		queryFn: () =>
			Axios.get<AdminUsersResponse>("api/admin/users", {
				params: { name: search || undefined, page, per_page: perPage },
			}).then((res) => res.data),
		placeholderData: keepPreviousData,
	})
}

export function useToggleUserVerified() {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn: ({ userId, verified }: { userId: string; verified: boolean }) =>
			Axios.patch<{ message: string }>(`api/admin/users/${userId}/verify`, {
				verified,
			}),
		onSuccess: () => {
			queryClient.invalidateQueries({ queryKey: ["admin", "users"] })
		},
	})
}

/**
 * Deletes someone's account and everything in it (their AI usage is kept).
 */
export function useDeleteUser() {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn: (userId: string) =>
			Axios.delete<{ message: string }>(`api/admin/users/${userId}`).then(
				(res) => res.data
			),
		onSuccess: () => {
			queryClient.invalidateQueries({ queryKey: ["admin"] })
		},
	})
}

/**
 * Removes anyone's mailbox, here and on Mailgun.
 */
export function useRemoveMailbox() {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn: (accountId: string) =>
			Axios.delete<{ message: string }>(
				`api/admin/mailgun-accounts/${accountId}`
			).then((res) => res.data),
		onSuccess: () => {
			queryClient.invalidateQueries({ queryKey: ["admin", "users"] })
		},
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

export type AdminAiUsageTotals = {
	requests: number
	replySuggestions: number
	inputTokens: number
	outputTokens: number
	costMicros: number
}

/**
 * What one feature cost in the month. An item is the thing it was used on
 * (a CV revamped, an application drafted), which can take several requests,
 * so costPerItemMicros is what one costs in practice.
 */
export type AdminAiUsageFeature = {
	feature: string
	requests: number
	users: number
	items: number
	// Google searches that grounded an answer, billed on top of tokens.
	searches: number
	costMicros: number
	costPerRequestMicros: number
	costPerItemMicros: number
}

/** One user's AI usage for the month; userId is null for system-wide requests. */
export type AdminAiUsageUser = AdminAiUsageTotals & {
	userId: string | null
	name: string
	email: string | null
	features: AdminAiUsageFeature[]
}

export type AdminAiUsageReport = {
	month: string
	users: AdminAiUsageUser[]
	features: AdminAiUsageFeature[]
	totals: AdminAiUsageTotals
}

export function useAdminAiUsage(month: string) {
	return useQuery({
		queryKey: ["admin", "ai-usage", month],
		queryFn: () =>
			Axios.get<AdminAiUsageReport>("api/admin/ai-usage", {
				params: { month },
			}).then((res) => res.data),
		placeholderData: keepPreviousData,
	})
}
