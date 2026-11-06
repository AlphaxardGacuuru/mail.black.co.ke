import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import Axios from "@/lib/axios"
import type { ChatConversation, ChatMessage } from "@/types/chat"

type ConversationShowResponse = {
	data: {
		conversation: ChatConversation
		messages: ChatMessage[]
		meta: { currentPage: number; lastPage: number; total: number }
	}
}

export function useConversations(archived = false) {
	return useQuery({
		queryKey: ["chat", "conversations", archived],
		queryFn: () =>
			Axios.get<{ data: ChatConversation[] }>("api/chat/conversations", {
				params: { archived },
			}).then((res) => res.data.data),
	})
}

export function useConversation(id: string | null, page = 1) {
	return useQuery({
		queryKey: ["chat", "conversation", id, page],
		queryFn: () =>
			Axios.get<ConversationShowResponse>(`api/chat/conversations/${id}`, {
				params: { page },
			}).then((res) => res.data.data),
		enabled: !!id,
	})
}

export function useStartConversation() {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn: (userId: string) =>
			Axios.post<{ data: ChatConversation }>("api/chat/conversations", {
				userId,
			}).then((res) => res.data.data),
		onSuccess: () => {
			queryClient.invalidateQueries({ queryKey: ["chat", "conversations"] })
		},
	})
}

export function useSendMessage(conversationId: string) {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn: (payload: {
			body?: string
			temporaryUploadIds?: number[]
			replyToId?: string
		}) =>
			Axios.post(`api/chat/conversations/${conversationId}/messages`, payload),
		onSuccess: () => {
			queryClient.invalidateQueries({ queryKey: ["chat", "conversations"] })
			queryClient.invalidateQueries({
				queryKey: ["chat", "conversation", conversationId],
			})
		},
	})
}

export function useToggleArchiveConversation() {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn: (conversationId: string) =>
			Axios.post<{ isArchived: boolean }>(
				`api/chat/conversations/${conversationId}/archive`
			).then((res) => res.data),
		onMutate: async (conversationId) => {
			await queryClient.cancelQueries({ queryKey: ["chat", "conversations"] })

			// Whichever list this conversation is currently showing in
			// (archived or not), drop it immediately rather than waiting on
			// the request — it belongs in the other list now.
			const previous = queryClient.getQueriesData<ChatConversation[]>({
				queryKey: ["chat", "conversations"],
			})

			previous.forEach(([queryKey, conversations]) => {
				if (!conversations) {
					return
				}

				queryClient.setQueryData(
					queryKey,
					conversations.filter(
						(conversation) => conversation.id !== conversationId
					)
				)
			})

			return { previous }
		},
		onError: (_error, _conversationId, context) => {
			context?.previous.forEach(([queryKey, conversations]) => {
				queryClient.setQueryData(queryKey, conversations)
			})
		},
		onSuccess: () => {
			queryClient.invalidateQueries({ queryKey: ["chat", "conversations"] })
		},
	})
}

export function useRemoveConversation() {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn: (conversationId: string) =>
			Axios.delete(`api/chat/conversations/${conversationId}`),
		onSuccess: () => {
			queryClient.invalidateQueries({ queryKey: ["chat", "conversations"] })
		},
	})
}

export function useMarkConversationRead() {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn: (conversationId: string) =>
			Axios.post(`api/chat/conversations/${conversationId}/read`),
		onSuccess: (_data, conversationId) => {
			queryClient.invalidateQueries({ queryKey: ["chat", "conversations"] })
			queryClient.invalidateQueries({
				queryKey: ["chat", "conversation", conversationId],
			})
		},
	})
}

export function useDeleteMessage(conversationId: string) {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn: (messageId: string) =>
			Axios.delete(`api/chat/messages/${messageId}`),
		onSuccess: () => {
			queryClient.invalidateQueries({
				queryKey: ["chat", "conversation", conversationId],
			})
		},
	})
}

export function useToggleStarMessage(conversationId: string) {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn: (messageId: string) =>
			Axios.post<{ isStarred: boolean }>(
				`api/chat/messages/${messageId}/star`
			).then((res) => res.data),
		onSuccess: () => {
			queryClient.invalidateQueries({
				queryKey: ["chat", "conversation", conversationId],
			})
		},
	})
}

export function useForwardMessage() {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn: ({
			messageId,
			conversationId,
		}: {
			messageId: string
			conversationId: string
		}) =>
			Axios.post(`api/chat/messages/${messageId}/forward`, { conversationId }),
		onSuccess: (_data, { conversationId }) => {
			queryClient.invalidateQueries({ queryKey: ["chat", "conversations"] })
			queryClient.invalidateQueries({
				queryKey: ["chat", "conversation", conversationId],
			})
		},
	})
}

export function useStartLocationShare(conversationId: string) {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn: () =>
			Axios.post(`api/chat/conversations/${conversationId}/location`),
		onSuccess: () => {
			queryClient.invalidateQueries({ queryKey: ["chat", "conversations"] })
			queryClient.invalidateQueries({
				queryKey: ["chat", "conversation", conversationId],
			})
		},
	})
}

export function useStopLocationShare(conversationId: string) {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn: () =>
			Axios.delete(`api/chat/conversations/${conversationId}/location`),
		onSuccess: () => {
			queryClient.invalidateQueries({ queryKey: ["chat", "conversations"] })
			queryClient.invalidateQueries({
				queryKey: ["chat", "conversation", conversationId],
			})
		},
	})
}

// Fires every ~10s while a share is live — deliberately no invalidation here.
// The map dialog gets live position via the presence-channel whisper, not by
// refetching the whole conversation on every tick.
export function useUpdateLocationShare(conversationId: string) {
	return useMutation({
		mutationFn: (position: {
			latitude: number
			longitude: number
			accuracyMeters?: number
		}) =>
			Axios.patch(
				`api/chat/conversations/${conversationId}/location`,
				position
			),
	})
}

export type ChatUserSearchResult = {
	id: string
	name: string
	email: string
	avatar: string | null
	verified: boolean
}

export function useChatUsers(query: string) {
	return useQuery({
		queryKey: ["chat", "user-search", query],
		queryFn: () =>
			Axios.get<{ data: ChatUserSearchResult[] }>("api/chat/users", {
				params: { name: query, per_page: 20 },
			}).then((res) => res.data.data),
	})
}
