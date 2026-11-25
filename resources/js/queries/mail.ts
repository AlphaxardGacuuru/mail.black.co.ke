import {
	keepPreviousData,
	useInfiniteQuery,
	useMutation,
	useQuery,
	useQueryClient,
} from "@tanstack/react-query"
import type { InfiniteData } from "@tanstack/react-query"
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

export type ReadStatus = "read" | "unread"

export type MailThreadFilters = {
	folder: string
	label?: string
	q?: string
	status?: ReadStatus
}

type Paginated<T> = {
	data: T[]
	meta: {
		current_page: number
		last_page: number
		total: number
	}
}

type ThreadPages = InfiniteData<Paginated<MailThreadSummary>>

function mapThreadPages(
	existing: ThreadPages,
	update: (threads: MailThreadSummary[], pageIndex: number) => MailThreadSummary[]
): ThreadPages {
	return {
		...existing,
		pages: existing.pages.map((page, pageIndex) => ({
			...page,
			data: update(page.data, pageIndex),
		})),
	}
}

function threadsQueryKey(filters: MailThreadFilters) {
	return ["mail", "threads", filters] as const
}

const UNREAD_COUNT_KEY = ["mail", "unread-count"]

/**
 * Refetch the thread lists and the inbox's unread count. The count has its
 * own key: the list updaters below rewrite every ["mail", "threads", ...].
 */
export function invalidateThreadLists(
	queryClient: ReturnType<typeof useQueryClient>
) {
	queryClient.invalidateQueries({ queryKey: ["mail", "threads"] })
	queryClient.invalidateQueries({ queryKey: UNREAD_COUNT_KEY })
}

export function useUnreadInboxCount() {
	return useQuery({
		queryKey: UNREAD_COUNT_KEY,
		queryFn: () =>
			Axios.get<{ count: number }>(MailThreadController.unreadCount.url()).then(
				(response) => response.data.count
			),
	})
}

function updateThreadLists(
	queryClient: ReturnType<typeof useQueryClient>,
	threadId: string,
	shouldInclude: (filters: MailThreadFilters) => boolean,
	patch: (thread: MailThreadSummary) => MailThreadSummary
) {
	const queries = queryClient.getQueriesData<ThreadPages>({
		queryKey: ["mail", "threads"],
	})
	const sourceThread = queries
		.flatMap(([, existing]) => existing?.pages ?? [])
		.flatMap((page) => page.data)
		.find((thread) => thread.id === threadId)

	queries.forEach(([key, existing]) => {
		if (!existing) {
			return
		}

		const filters = key[2] as MailThreadFilters
		const shouldBeIncluded = shouldInclude(filters)
		const hasThread = existing.pages.some((page) =>
			page.data.some((thread) => thread.id === threadId)
		)

		queryClient.setQueryData(
			key,
			mapThreadPages(existing, (threads, pageIndex) => {
				if (shouldBeIncluded && !hasThread && sourceThread) {
					return pageIndex === 0 ? [patch(sourceThread), ...threads] : threads
				}

				if (!shouldBeIncluded) {
					return threads.filter((thread) => thread.id !== threadId)
				}

				return threads.map((thread) =>
					thread.id === threadId ? patch(thread) : thread
				)
			})
		)
	})
}

export function useMailThreads(filters: MailThreadFilters) {
	return useInfiniteQuery({
		queryKey: threadsQueryKey(filters),
		queryFn: ({ pageParam }) =>
			Axios.get<Paginated<MailThreadSummary>>(
				MailThreadController.index.url({
					query: {
						folder: filters.folder,
						...(filters.label ? { label: filters.label } : {}),
						...(filters.q ? { q: filters.q } : {}),
						...(filters.status ? { status: filters.status } : {}),
						page: pageParam,
					},
				})
			).then((res) => res.data),
		initialPageParam: 1,
		getNextPageParam: (lastPage) =>
			lastPage.meta.current_page < lastPage.meta.last_page
				? lastPage.meta.current_page + 1
				: undefined,
		select: (data) => data.pages.flatMap((page) => page.data),
		placeholderData: keepPreviousData,
	})
}

export function useMailThread(threadId: string | null) {
	const queryClient = useQueryClient()

	return useQuery({
		queryKey: ["mail", "thread", threadId],
		queryFn: () =>
			Axios.get<{ data: MailThread }>(
				MailThreadController.show.url(threadId as string)
			).then((res) => {
				queryClient.setQueriesData<ThreadPages>(
					{ queryKey: ["mail", "threads"] },
					(existing) =>
						existing &&
						mapThreadPages(existing, (threads) =>
							threads.map((thread) =>
								thread.id === threadId
									? { ...thread, hasUnread: false }
									: thread
							)
						)
				)

				// Opening it marked it read.
				queryClient.invalidateQueries({ queryKey: UNREAD_COUNT_KEY })

				return res.data.data
			}),
		enabled: !!threadId,
	})
}

export function useLabels() {
	return useQuery({
		queryKey: ["mail", "labels"],
		queryFn: () =>
			Axios.get<{ data: MailLabel[] }>(MailLabelController.index.url()).then(
				(res) => res.data.data
			),
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

			const previous = queryClient.getQueriesData<ThreadPages>({
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
			invalidateThreadLists(queryClient)
		},
	})
}

export function useStarMailThread(starred: boolean) {
	return useOptimisticThreadMutation(
		(id) =>
			Axios.patch(MailThreadController.update.url(id), { isStarred: starred }),
		(thread) => ({ ...thread, isStarred: starred }),
		(filters) => filters.folder !== "starred" || starred
	)
}

export function useMarkMailThreadRead(read: boolean) {
	return useOptimisticThreadMutation(
		(id) => Axios.patch(MailThreadController.update.url(id), { isRead: read }),
		(thread) => ({ ...thread, hasUnread: !read }),
		(filters) => filters.status !== (read ? "unread" : "read")
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

			const previous = queryClient.getQueriesData<ThreadPages>({
				queryKey: ["mail", "threads"],
			})

			updateThreadLists(
				queryClient,
				threadId,
				(filters) =>
					destinationFolder !== null &&
					(filters.folder === destinationFolder ||
						filters.folder === "starred"),
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
			invalidateThreadLists(queryClient)
		},
	})
}

export function useArchiveMailThread() {
	return useMoveThreadMutation(
		(id) =>
			Axios.patch(MailThreadController.update.url(id), { folder: "archive" }),
		"archive"
	)
}

export function useTrashMailThread() {
	return useMoveThreadMutation(
		(id) =>
			Axios.patch(MailThreadController.update.url(id), { folder: "trash" }),
		"trash"
	)
}

export function useRestoreMailThread() {
	return useMoveThreadMutation(
		(threadId) =>
			Axios.patch(MailThreadController.update.url(threadId), { restore: true }),
		null
	)
}

export function useDeleteMailThreadPermanently() {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn: (threadId: string) =>
			Axios.delete(MailThreadController.destroy.url(threadId)),
		onSuccess: () => {
			invalidateThreadLists(queryClient)
		},
	})
}

export function useSendMail() {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn: (payload: MailComposePayload) =>
			Axios.post(MailMessageController.store.url(), payload),
		onSuccess: () => {
			invalidateThreadLists(queryClient)
		},
	})
}

export function useRetryMailMessage(threadId: string | null) {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn: (messageId: string) =>
			Axios.post(MailMessageController.retry.url(messageId)),
		onSuccess: () => {
			invalidateThreadLists(queryClient)

			if (threadId) {
				queryClient.invalidateQueries({
					queryKey: ["mail", "thread", threadId],
				})
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

export function useReplyMail(
	mode: Exclude<MailComposeMode, "new">,
	parentMessageId: string
) {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn: (payload: MailComposePayload) =>
			Axios.post(respondAction(mode).url(parentMessageId), payload),
		onSuccess: (response) => {
			const threadId = (
				response.data?.data as { threadId?: string } | undefined
			)?.threadId

			invalidateThreadLists(queryClient)

			if (threadId) {
				queryClient.invalidateQueries({
					queryKey: ["mail", "thread", threadId],
				})
			}
		},
	})
}

export type MailReplySuggestion = {
	text: string
	html: string
}

// Asks the AI for a draft reply to a message. The server asks the AI only
// once per message and returns that same draft on later requests.
export function useSuggestReply(parentMessageId: string) {
	return useMutation({
		mutationFn: (mode: "reply" | "reply-all") =>
			Axios.post<{ data: MailReplySuggestion }>(
				MailMessageController.suggestReply.url(parentMessageId),
				{ mode }
			).then((response) => response.data),
	})
}

export function useDraftMail() {
	return useMutation({
		mutationFn: (payload: {
			instruction: string
			subject?: string
			to?: string[]
		}) =>
			Axios.post<{ data: MailReplySuggestion }>(
				MailMessageController.draft.url(),
				payload
			).then((response) => response.data),
	})
}

export type MailRevampStyle = "polish" | "formal" | "friendly" | "shorter"

export function useRevampMail() {
	return useMutation({
		mutationFn: (payload: {
			bodyHtml: string
			style: MailRevampStyle
			subject?: string
		}) =>
			Axios.post<{ data: MailReplySuggestion }>(
				MailMessageController.revamp.url(),
				payload
			).then((response) => response.data),
	})
}

export function useAddMailThreadLabel() {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn: ({
			threadId,
			labelId,
		}: {
			threadId: string
			labelId: string
		}) =>
			Axios.post(MailThreadController.attachLabel.url(threadId), { labelId }),
		onSuccess: (_data, { threadId }) => {
			queryClient.invalidateQueries({ queryKey: ["mail", "thread", threadId] })
		},
	})
}

export function useRemoveMailThreadLabel() {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn: ({
			threadId,
			labelId,
		}: {
			threadId: string
			labelId: string
		}) =>
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
		mutationFn: (labelId: string) =>
			Axios.delete(MailLabelController.destroy.url(labelId)),
		onSuccess: () => {
			queryClient.invalidateQueries({ queryKey: ["mail", "labels"] })
		},
	})
}
