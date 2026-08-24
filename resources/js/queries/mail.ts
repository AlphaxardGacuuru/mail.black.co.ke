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
						if (!existing) return existing

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
	patch: (thread: MailThreadSummary) => MailThreadSummary
) {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn,
		onMutate: async (threadId: string) => {
			await queryClient.cancelQueries({ queryKey: ["mail", "threads"] })

			const previous = queryClient.getQueriesData<Paginated<MailThreadSummary>>({
				queryKey: ["mail", "threads"],
			})

			queryClient.setQueriesData<Paginated<MailThreadSummary>>({ queryKey: ["mail", "threads"] }, (existing) => {
				if (!existing) return existing

				return {
					...existing,
					data: existing.data.map((thread) => (thread.id === threadId ? patch(thread) : thread)),
				}
			})

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
		(id) => Axios.patch((starred ? MailThreadController.star : MailThreadController.unstar).url(id)),
		(thread) => ({ ...thread, isStarred: starred })
	)
}

export function useMarkMailThreadRead(read: boolean) {
	return useOptimisticThreadMutation(
		(id) => Axios.patch((read ? MailThreadController.markRead : MailThreadController.markUnread).url(id)),
		(thread) => ({ ...thread, hasUnread: !read })
	)
}

function useRemoveFromListMutation(mutationFn: (threadId: string) => Promise<AxiosResponse>) {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn,
		onMutate: async (threadId: string) => {
			await queryClient.cancelQueries({ queryKey: ["mail", "threads"] })

			const previous = queryClient.getQueriesData<Paginated<MailThreadSummary>>({
				queryKey: ["mail", "threads"],
			})

			queryClient.setQueriesData<Paginated<MailThreadSummary>>({ queryKey: ["mail", "threads"] }, (existing) => {
				if (!existing) return existing

				return { ...existing, data: existing.data.filter((thread) => thread.id !== threadId) }
			})

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
	return useRemoveFromListMutation((id) => Axios.patch(MailThreadController.archive.url(id)))
}

export function useTrashMailThread() {
	return useRemoveFromListMutation((id) => Axios.patch(MailThreadController.trash.url(id)))
}

export function useRestoreMailThread() {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn: (threadId: string) => Axios.patch(MailThreadController.restore.url(threadId)),
		onSuccess: () => {
			queryClient.invalidateQueries({ queryKey: ["mail", "threads"] })
		},
	})
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
