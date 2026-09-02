import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import type { AxiosResponse } from "axios"
import MailThreadController from "@/actions/App/Http/Controllers/Mail/MailThreadController"
import MailMessageController from "@/actions/App/Http/Controllers/Mail/MailMessageController"
import MailLabelController from "@/actions/App/Http/Controllers/Mail/MailLabelController"
import Axios from "@/lib/axios"
import type {
	MailComposeMode,
	MailComposePayload,
	MailLabel,
	MailThread,
	MailThreadSummary,
} from "@/types/mail"

export type MailThreadFilters = {
	folder: string
	label?: string
	q?: string
	page?: number
}

type Paginated<T> = {
	data: T[]
	meta: {
		current_page: number
		last_page: number
		total: number
	}
}

function threadsQueryKey(filters: MailThreadFilters) {
	return ["mail", "threads", filters] as const
}

function updateThreadLists(
	queryClient: ReturnType<typeof useQueryClient>,
	threadId: string,
	shouldInclude: (filters: MailThreadFilters) => boolean,
	patch: (thread: MailThreadSummary) => MailThreadSummary
) {
	const queries = queryClient.getQueriesData<Paginated<MailThreadSummary>>({
		queryKey: ["mail", "threads"],
	})
	const sourceThread = queries
		.map(([, existing]) => existing?.data.find((thread) => thread.id === threadId))
		.find((thread): thread is MailThreadSummary => !!thread)

	queries.forEach(([key, existing]) => {
		if (!existing) {
			return
		}

		const filters = key[2] as MailThreadFilters
		const shouldBeIncluded = shouldInclude(filters)
		const hasThread = existing.data.some((thread) => thread.id === threadId)
		let data = existing.data

		if (shouldBeIncluded && !hasThread && sourceThread) {
			data = [patch(sourceThread), ...data]
		} else if (!shouldBeIncluded) {
			data = data.filter((thread) => thread.id !== threadId)
		} else {
			data = data.map((thread) => (thread.id === threadId ? patch(thread) : thread))
		}

		queryClient.setQueryData(key, { ...existing, data })
	})
}

export function useMailThreads(filters: MailThreadFilters) {
	return useQuery({
		queryKey: threadsQueryKey(filters),
		queryFn: () =>
			Axios.get<Paginated<MailThreadSummary>>(
				MailThreadController.index.url({
					query: {
						folder: filters.folder,
						...(filters.label ? { label: filters.label } : {}),
						...(filters.q ? { q: filters.q } : {}),
						page: filters.page ?? 1,
					},
				})
			).then((res) => res.data),
		placeholderData: keepPreviousData,
	})
}

export function useMailThread(threadId: string | null) {
	const queryClient = useQueryClient()

	return useQuery({
		queryKey: ["mail", "thread", threadId],
		queryFn: () =>
			Axios.get<{ data: MailThread }>(MailThreadController.show.url(threadId as string)).then((res) => {
				queryClient.setQueriesData<Paginated<MailThreadSummary>>(
					{ queryKey: ["mail", "threads"] },
					(existing) => {
						if (!existing) {
							return existing
						}

						return {
							...existing,
							data: existing.data.map((thread) =>
								thread.id === threadId ? { ...thread, hasUnread: false } : thread
							),
						}
					}
				)

				return res.data.data
			}),
		enabled: !!threadId,
	})
}

export function useLabels() {
	return useQuery({
		queryKey: ["mail", "labels"],
		queryFn: () => Axios.get<{ data: MailLabel[] }>(MailLabelController.index.url()).then((res) => res.data.data),
		staleTime: 5 * 60_000,
	})
}

function useOptimisticThreadMutation(
	mutationFn: (threadId: string) => Promise<AxiosResponse>,
	patch: (thread: MailThreadSummary) => MailThreadSummary,
	shouldInclude: (filters: MailThreadFilters) => boolean = () => true
) {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn,
		onMutate: async (threadId: string) => {
			await queryClient.cancelQueries({ queryKey: ["mail", "threads"] })

			const previous = queryClient.getQueriesData<Paginated<MailThreadSummary>>({
				queryKey: ["mail", "threads"],
			})

			updateThreadLists(queryClient, threadId, shouldInclude, patch)

			return { previous }
		},
		onError: (_err, _threadId, context) => {
			context?.previous.forEach(([key, data]) => {
				queryClient.setQueryData(key, data)
			})
		},
		onSettled: () => {
			queryClient.invalidateQueries({ queryKey: ["mail", "threads"] })
		},
	})
}

export function useStarMailThread(starred: boolean) {
	return useOptimisticThreadMutation(
		(id) => Axios.patch(MailThreadController.update.url(id), { isStarred: starred }),
		(thread) => ({ ...thread, isStarred: starred }),
		(filters) => filters.folder !== "starred" || starred
	)
}

export function useMarkMailThreadRead(read: boolean) {
	return useOptimisticThreadMutation(
		(id) => Axios.patch(MailThreadController.update.url(id), { isRead: read }),
		(thread) => ({ ...thread, hasUnread: !read })
	)
}

function useMoveThreadMutation(
	mutationFn: (threadId: string) => Promise<AxiosResponse>,
	destinationFolder: string | null
) {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn,
		onMutate: async (threadId: string) => {
			await queryClient.cancelQueries({ queryKey: ["mail", "threads"] })

			const previous = queryClient.getQueriesData<Paginated<MailThreadSummary>>({
				queryKey: ["mail", "threads"],
			})

			updateThreadLists(
				queryClient,
				threadId,
				(filters) =>
					destinationFolder !== null &&
					(filters.folder === destinationFolder || filters.folder === "starred"),
				(thread) => thread
			)

			return { previous }
		},
		onError: (_err, _threadId, context) => {
			context?.previous.forEach(([key, data]) => {
				queryClient.setQueryData(key, data)
			})
		},
		onSettled: () => {
			queryClient.invalidateQueries({ queryKey: ["mail", "threads"] })
		},
	})
}

export function useArchiveMailThread() {
	return useMoveThreadMutation(
		(id) => Axios.patch(MailThreadController.update.url(id), { folder: "archive" }),
		"archive"
	)
}

export function useTrashMailThread() {
	return useMoveThreadMutation(
		(id) => Axios.patch(MailThreadController.update.url(id), { folder: "trash" }),
		"trash"
	)
}

export function useRestoreMailThread() {
	return useMoveThreadMutation(
		(threadId) => Axios.patch(MailThreadController.update.url(threadId), { folder: "inbox" }),
		null
	)
}

export function useDeleteMailThreadPermanently() {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn: (threadId: string) => Axios.delete(MailThreadController.destroy.url(threadId)),
		onSuccess: () => {
			queryClient.invalidateQueries({ queryKey: ["mail", "threads"] })
		},
	})
}

export function useSendMail() {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn: (payload: MailComposePayload) => Axios.post(MailMessageController.store.url(), payload),
		onSuccess: () => {
			queryClient.invalidateQueries({ queryKey: ["mail", "threads"] })
		},
	})
}

export function useRetryMailMessage(threadId: string | null) {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn: (messageId: string) => Axios.post(MailMessageController.retry.url(messageId)),
		onSuccess: () => {
			queryClient.invalidateQueries({ queryKey: ["mail", "threads"] })

			if (threadId) {
				queryClient.invalidateQueries({ queryKey: ["mail", "thread", threadId] })
			}
		},
	})
}

function respondAction(mode: MailComposeMode) {
	switch (mode) {
		case "reply":
			return MailMessageController.reply
		case "reply-all":
			return MailMessageController.replyAll
		case "forward":
			return MailMessageController.forward
		default:
			throw new Error(`respondAction called with invalid mode: ${mode}`)
	}
}

export function useReplyMail(mode: Exclude<MailComposeMode, "new">, parentMessageId: string) {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn: (payload: MailComposePayload) =>
			Axios.post(respondAction(mode).url(parentMessageId), payload),
		onSuccess: (response) => {
			const threadId = (response.data?.data as { threadId?: string } | undefined)?.threadId

			queryClient.invalidateQueries({ queryKey: ["mail", "threads"] })

			if (threadId) {
				queryClient.invalidateQueries({ queryKey: ["mail", "thread", threadId] })
			}
		},
	})
}

export function useAddMailThreadLabel() {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn: ({ threadId, labelId }: { threadId: string; labelId: string }) =>
			Axios.post(MailThreadController.attachLabel.url(threadId), { labelId }),
		onSuccess: (_data, { threadId }) => {
			queryClient.invalidateQueries({ queryKey: ["mail", "thread", threadId] })
		},
	})
}

export function useRemoveMailThreadLabel() {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn: ({ threadId, labelId }: { threadId: string; labelId: string }) =>
			Axios.delete(MailThreadController.detachLabel.url([threadId, labelId])),
		onSuccess: (_data, { threadId }) => {
			queryClient.invalidateQueries({ queryKey: ["mail", "thread", threadId] })
		},
	})
}

export function useCreateMailLabel() {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn: (payload: { name: string; color?: string }) =>
			Axios.post(MailLabelController.store.url(), payload),
		onSuccess: () => {
			queryClient.invalidateQueries({ queryKey: ["mail", "labels"] })
		},
	})
}

export function useDeleteMailLabel() {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn: (labelId: string) => Axios.delete(MailLabelController.destroy.url(labelId)),
		onSuccess: () => {
			queryClient.invalidateQueries({ queryKey: ["mail", "labels"] })
		},
	})
}
