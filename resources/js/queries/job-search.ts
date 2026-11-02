import {
	keepPreviousData,
	useMutation,
	useQuery,
	useQueryClient,
} from "@tanstack/react-query"
import type { AxiosError } from "axios"
import JobApplicationController from "@/actions/App/Http/Controllers/Jobs/JobApplicationController"
import JobSearchController from "@/actions/App/Http/Controllers/Jobs/JobSearchController"
import Axios from "@/lib/axios"
import type {
	CoverLetterMode,
	JobApplication,
	JobApplicationTab,
	JobSearchState,
} from "@/types"

type Paginated<T> = {
	data: T[]
	meta: { current_page: number; last_page: number; total: number }
}

type ApplicationsResponse = Paginated<JobApplication> & {
	counts: Record<JobApplicationTab, number>
}

/**
 * The first validation message from a failed request, else the fallback.
 */
export function errorMessage(error: unknown, fallback: string): string {
	const data = (
		error as AxiosError<{ message?: string; errors?: Record<string, string[]> }>
	).response?.data
	const first = data?.errors ? Object.values(data.errors)[0]?.[0] : undefined

	return first ?? data?.message ?? fallback
}

export function useJobSearch() {
	return useQuery({
		queryKey: ["job-search"],
		queryFn: () =>
			Axios.get<JobSearchState>(JobSearchController.show.url()).then(
				(response) => response.data
			),
		// While the CV is being read, check back until it's done.
		refetchInterval: (query) =>
			query.state.data?.profile?.cvStatus === "reading" ? 5000 : false,
	})
}

/**
 * Settings mutations all return the whole state, which replaces the cache.
 */
function useJobSearchMutation<TVariables>(
	request: (
		variables: TVariables
	) => Promise<{ data: JobSearchState & { message: string } }>
) {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn: (variables: TVariables) =>
			request(variables).then((response) => response.data),
		onSuccess: (data) => {
			queryClient.setQueryData(["job-search"], data)
			queryClient.invalidateQueries({ queryKey: ["job-applications"] })
		},
	})
}

export type JobSearchSettings = {
	autoApply: boolean
	consent: boolean
	mode: "review" | "autopilot"
	mailgunAccountId: string | null
	preferences: {
		keywords: string[]
		locations: string[]
		remoteOk: boolean
		excludedCompanies: string[]
	}
	dailyLimit: number
	minMatchScore: number
}

export function useSaveJobSearch() {
	return useJobSearchMutation((settings: JobSearchSettings) =>
		Axios.put(JobSearchController.update.url(), settings)
	)
}

export function useUploadCv() {
	return useJobSearchMutation((file: File) => {
		const form = new FormData()
		form.append("cv", file)

		return Axios.post(JobSearchController.uploadCv.url(), form)
	})
}

export function useSaveCoverLetter() {
	return useJobSearchMutation(
		(variables: { coverLetter: string; coverLetterMode: CoverLetterMode }) =>
			Axios.put(JobSearchController.updateCoverLetter.url(), variables)
	)
}

export function useUploadCoverLetter() {
	return useJobSearchMutation((file: File) => {
		const form = new FormData()
		form.append("coverLetter", file)

		return Axios.post(JobSearchController.uploadCoverLetter.url(), form)
	})
}

export function useAddCareerPage() {
	return useJobSearchMutation((url: string) =>
		Axios.post(JobSearchController.storeCareerPage.url(), { url })
	)
}

export function useRemoveCareerPage() {
	return useJobSearchMutation((id: string) =>
		Axios.delete(JobSearchController.destroyCareerPage.url(id))
	)
}

export function useDeleteJobSearch() {
	return useJobSearchMutation(() =>
		Axios.delete(JobSearchController.destroy.url())
	)
}

export function useJobApplications(tab: JobApplicationTab, page: number) {
	return useQuery({
		queryKey: ["job-applications", tab, page],
		queryFn: () =>
			Axios.get<ApplicationsResponse>(
				JobApplicationController.index.url({ query: { tab, page } })
			).then((response) => response.data),
		placeholderData: keepPreviousData,
	})
}

type DraftEdits = { subject?: string; coverLetter?: string }

function useJobApplicationMutation<TVariables>(
	request: (
		variables: TVariables
	) => Promise<{ data: { message: string; data: JobApplication } }>
) {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn: (variables: TVariables) =>
			request(variables).then((response) => response.data),
		onSuccess: () => {
			queryClient.invalidateQueries({ queryKey: ["job-applications"] })
		},
	})
}

export function useSaveDraft() {
	return useJobApplicationMutation(
		({ id, ...edits }: DraftEdits & { id: string }) =>
			Axios.patch(JobApplicationController.update.url(id), edits)
	)
}

export function useApproveApplication() {
	return useJobApplicationMutation(
		({ id, ...edits }: DraftEdits & { id: string }) =>
			Axios.post(JobApplicationController.approve.url(id), edits)
	)
}

export function useSkipApplication() {
	return useJobApplicationMutation((id: string) =>
		Axios.post(JobApplicationController.skip.url(id))
	)
}
