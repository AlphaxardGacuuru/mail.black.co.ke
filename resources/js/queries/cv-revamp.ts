import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import CvRevampController from "@/actions/App/Http/Controllers/Jobs/CvRevampController"
import Axios from "@/lib/axios"
import type { CvRevampState } from "@/types"

const KEY = ["cv-revamp"]

/**
 * The user's CV revamped by the AI. While it's being written, check back
 * until it's done.
 */
export function useCvRevamp() {
	return useQuery({
		queryKey: KEY,
		queryFn: () =>
			Axios.get<CvRevampState>(CvRevampController.show.url()).then(
				(response) => response.data
			),
		refetchInterval: (query) =>
			query.state.data?.revamp?.status === "drafting" ? 4000 : false,
	})
}

/**
 * Every change returns the whole state, which replaces the cache. Using the
 * revamped CV changes what job search shows too.
 */
function useCvRevampMutation<TVariables>(
	request: (
		variables: TVariables
	) => Promise<{ data: CvRevampState & { message: string } }>
) {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn: (variables: TVariables) =>
			request(variables).then((response) => response.data),
		onSuccess: (data) => {
			queryClient.setQueryData(KEY, data)
			queryClient.invalidateQueries({ queryKey: ["job-search"] })
		},
	})
}

/** Revamp the CV: the first time, or again with the answers given. */
export function useStartCvRevamp() {
	return useCvRevampMutation(() => Axios.post(CvRevampController.store.url()))
}

export type CvRevampChanges = {
	sections?: Record<
		string,
		{ keep?: "revamped" | "original"; text?: string; bullets?: string[] }
	>
	answers?: Record<string, string>
}

export function useUpdateCvRevamp() {
	return useCvRevampMutation((changes: CvRevampChanges) =>
		Axios.patch(CvRevampController.update.url(), changes)
	)
}

export function useUseCvRevamp() {
	return useCvRevampMutation(() => Axios.post(CvRevampController.use.url()))
}

export function useStopUsingCvRevamp() {
	return useCvRevampMutation(() =>
		Axios.delete(CvRevampController.stopUsing.url())
	)
}

export function useUpdateCvTailoring() {
	return useCvRevampMutation((tailorCv: boolean) =>
		Axios.put(CvRevampController.updateTailoring.url(), { tailorCv })
	)
}
