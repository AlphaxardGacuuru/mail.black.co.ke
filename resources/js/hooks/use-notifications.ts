import { useEchoModel } from "@laravel/echo-react"
import { useQuery, useQueryClient } from "@tanstack/react-query"
import { useEffect, useState } from "react"
import { useApp } from "@/contexts/AppContext"
import Axios from "@/lib/axios"
import {
	destroy as destroyRoute,
	index as indexRoute,
	update as updateRoute,
} from "@/routes/notifications"
import type { Notification } from "@/types"

/**
 * The signed-in user's notifications, kept live over the user's private
 * channel, plus the actions the header bell and the notifications page share.
 */
export function useNotifications() {
	const { auth } = useApp()
	const queryClient = useQueryClient()
	const { channel } = useEchoModel("App.Models.User", auth?.id ?? 0)
	const [deletingId, setDeletingId] = useState<string | null>(null)

	const query = useQuery<Notification[]>({
		queryKey: ["notifications"],
		queryFn: () =>
			Axios.get(indexRoute.url()).then((response) => response.data.data),
		enabled: !!auth,
	})

	useEffect(() => {
		channel()?.notification(() => {
			queryClient.invalidateQueries({ queryKey: ["notifications"] })
		})
	}, [auth?.id, channel, queryClient])

	const refresh = () =>
		queryClient.invalidateQueries({ queryKey: ["notifications"] })

	/**
	 * Show a change straight away, put the list back if the request fails,
	 * then refetch so it matches the server.
	 */
	const optimistically = (
		change: (current: Notification[]) => Notification[],
		request: Promise<unknown>
	) => {
		const previous = queryClient.getQueryData<Notification[]>(["notifications"])

		queryClient.setQueryData<Notification[]>(["notifications"], (current) =>
			change(current ?? [])
		)

		return request
			.catch(() =>
				queryClient.setQueryData(["notifications"], previous)
			)
			.finally(refresh)
	}

	const notifications = query.data ?? []

	return {
		notifications,
		isLoading: query.isLoading,
		unreadCount: notifications.filter((notification) => !notification.readAt)
			.length,
		deletingId,
		markAsRead: (id: string) => {
			const readAt = new Date().toISOString()

			void optimistically(
				(current) =>
					current.map((notification) =>
						notification.id === id && !notification.readAt
							? { ...notification, readAt }
							: notification
					),
				Axios.put(updateRoute.url(id))
			)
		},
		markAllAsRead: () => {
			const readAt = new Date().toISOString()

			// The API treats the id "0" as "every notification".
			void optimistically(
				(current) =>
					current.map((notification) =>
						notification.readAt ? notification : { ...notification, readAt }
					),
				Axios.put(updateRoute.url("0"))
			)
		},
		remove: (id: string) => {
			setDeletingId(id)
			void optimistically(
				(current) => current.filter((notification) => notification.id !== id),
				Axios.delete(destroyRoute.url(id))
			).finally(() => setDeletingId(null))
		},
		clearAll: () => {
			setDeletingId("0")
			// The API treats the id "0" as "every notification".
			void optimistically(
				() => [],
				Axios.delete(destroyRoute.url("0"))
			).finally(() => setDeletingId(null))
		},
	}
}
