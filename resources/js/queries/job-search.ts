import {
	keepPreviousData,
	useMutation,
	useQuery,
	useQueryClient,
} from "@tanstack/react-query"
import type { AxiosError } from "axios"
import JobApplicationController from "@/actions/App/Http/Controllers/Jobs/JobApplicationController"
import CvRevampController from "@/actions/App/Http/Controllers/Jobs/CvRevampController"
import JobApplicationFormController from "@/actions/App/Http/Controllers/Jobs/JobApplicationFormController"
import JobSearchController from "@/actions/App/Http/Controllers/Jobs/JobSearchController"
import Axios from "@/lib/axios"
import type { JobSearchSetupStep } from "@/types/job-search"
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

export function useSaveSetupStep() {
	return useJobSearchMutation((step: JobSearchSetupStep) =>
		Axios.put(JobSearchController.updateSetupStep.url(), { step })
	)
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

export function useMailboxSuggestions(enabled: boolean) {
	return useQuery({
		queryKey: ["job-search", "mailbox"],
		queryFn: () =>
			Axios.get<{ suggestions: string[] }>(
				JobSearchController.mailboxSuggestions.url()
			).then((response) => response.data.suggestions),
		enabled,
		staleTime: Infinity,
	})
}

/**
 * Creates the chosen address; it becomes the user's mail account.
 */
export function useCreateMailbox() {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn: (address: string) =>
			Axios.post<JobSearchState & { message: string }>(
				JobSearchController.storeMailbox.url(),
				{ address, consent: true }
			).then((response) => response.data),
		onSuccess: (data) => {
			queryClient.setQueryData(["job-search"], data)
			queryClient.invalidateQueries({ queryKey: ["auth"] })
		},
	})
}

/**
 * How many applications each tab holds, for the nav badges. New matches
 * arrive in the background, so it checks back every minute.
 */
export function useJobApplicationCounts() {
	return useQuery({
		queryKey: ["job-applications", "counts"],
		queryFn: () =>
			Axios.get<{ counts: Record<JobApplicationTab, number> }>(
				JobApplicationController.counts.url()
			).then((response) => response.data.counts),
		refetchInterval: 60_000,
	})
}

export function useJobApplications(tab: JobApplicationTab, page: number) {
	return useQuery({
		queryKey: ["job-applications", tab, page],
		queryFn: () =>
			Axios.get<ApplicationsResponse>(
				JobApplicationController.index.url({ query: { tab, page } })
			).then((response) => response.data),
		placeholderData: keepPreviousData,
		// Application forms and tailored CVs are made in the background;
		// check back until they're done.
		refetchInterval: (query) =>
			query.state.data?.data.some(
				(application) =>
					["preparing", "submitting"].includes(
						application.form?.status ?? ""
					) || application.cv?.status === "tailoring"
			)
				? 4000
				: false,
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

type FormAnswers = { id: string; answers?: Record<string, string> }

/**
 * Reads the employer's application form and suggests answers.
 */
export function usePrepareApplicationForm() {
	return useJobApplicationMutation((id: string) =>
		Axios.post(JobApplicationFormController.prepare.url(id))
	)
}

export function useSaveApplicationFormAnswers() {
	return useJobApplicationMutation(({ id, answers }: FormAnswers) =>
		Axios.patch(JobApplicationFormController.update.url(id), { answers })
	)
}

export function useSubmitApplicationForm() {
	return useJobApplicationMutation(({ id, answers }: FormAnswers) =>
		Axios.post(JobApplicationFormController.submit.url(id), { answers })
	)
}

/**
 * Tailor the revamped CV to this application's job.
 */
export function useTailorApplicationCv() {
	return useJobApplicationMutation((id: string) =>
		Axios.post(CvRevampController.tailor.url(id))
	)
}

export type SharedPost = { text: string; url: string; images: File[] }

/**
 * Add the jobs in a post shared from WhatsApp, Telegram and the like.
 */
export function useAddSharedPost() {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn: (post: SharedPost) => {
			const form = new FormData()
			form.append("text", post.text)
			form.append("url", post.url)
			post.images.forEach((image) => form.append("images[]", image))

			return Axios.post<{ message: string; data: JobApplication[] }>(
				JobApplicationController.storeShared.url(),
				form
			).then((response) => response.data)
		},
		onSuccess: () => {
			queryClient.invalidateQueries({ queryKey: ["job-applications"] })
		},
	})
}
