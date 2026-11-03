import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import NotificationPreferenceController from "@/actions/App/Http/Controllers/Settings/NotificationPreferenceController"
import Axios from "@/lib/axios"
import type { NotificationChannel, NotificationPreferencesState } from "@/types"

const QUERY_KEY = ["notification-preferences"]

export function useNotificationPreferences() {
	return useQuery({
		queryKey: QUERY_KEY,
		queryFn: () =>
			Axios.get<NotificationPreferencesState>(
				NotificationPreferenceController.show.url()
			).then((response) => response.data),
	})
}

type Change = { category: string; channel: NotificationChannel; enabled: boolean }

/**
 * Saves one switch at a time. The switch flips straight away and flips back
 * if saving fails.
 */
export function useUpdateNotificationPreference() {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn: ({ category, channel, enabled }: Change) =>
			Axios.put<NotificationPreferencesState & { message: string }>(
				NotificationPreferenceController.update.url(),
				{ preferences: { [category]: { [channel]: enabled } } }
			).then((response) => response.data),
		onMutate: async ({ category, channel, enabled }) => {
			await queryClient.cancelQueries({ queryKey: QUERY_KEY })
			const previous =
				queryClient.getQueryData<NotificationPreferencesState>(QUERY_KEY)

			queryClient.setQueryData<NotificationPreferencesState>(
				QUERY_KEY,
				(state) =>
					state && {
						...state,
						categories: state.categories.map((item) =>
							item.key === category
								? { ...item, channels: { ...item.channels, [channel]: enabled } }
								: item
						),
					}
			)

			return { previous }
		},
		onError: (_error, _change, context) => {
			queryClient.setQueryData(QUERY_KEY, context?.previous)
		},
		onSuccess: (data) => {
			queryClient.setQueryData(QUERY_KEY, data)
		},
	})
}
