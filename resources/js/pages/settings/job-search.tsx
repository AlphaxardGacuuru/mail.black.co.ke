import { useNavigate } from "@tanstack/react-router"
import { formatDistanceToNow } from "date-fns"
import {
	BellRing,
	Copy,
	ExternalLink,
	LoaderCircle,
	Trash2,
} from "lucide-react"
import { useEffect, useState } from "react"
import type { FormEvent } from "react"
import Heading from "@/components/heading"
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Skeleton } from "@/components/ui/skeleton"
import { Switch } from "@/components/ui/switch"
import { Head } from "@/lib/spa"
import toast from "@/lib/toast"
import {
	errorMessage,
	useAddCareerPage,
	useDeleteJobSearch,
	useJobSearch,
	useRemoveCareerPage,
	useSaveJobSearch,
} from "@/queries/job-search"
import {
	ConsentField,
	CoverLetterSection,
	CvSection,
	MailboxSection,
	PreferenceFields,
	SendingFields,
	formFrom,
	jobSearchPayload,
	needsJobSearchSetup,
} from "@/components/job-search/setup-sections"
import type { JobSearchForm } from "@/components/job-search/setup-sections"
import { JOB_APPLICATIONS_URL } from "@/lib/job-application-tabs"
import { edit } from "@/routes/job-search"
import type { JobSearchState } from "@/types"

// ─── Settings ────────────────────────────────────────────────────────────────

function SettingsForm({ state }: { state: JobSearchState }) {
	const save = useSaveJobSearch()
	const [form, setForm] = useState<JobSearchForm>(() => formFrom(state))
	const profile = state.profile

	// Pick up server-side changes (a reset) without losing edits in
	// progress to every background refetch.
	useEffect(() => {
		setForm((current) => ({ ...current, consent: !!profile?.consentedAt }))
	}, [profile?.consentedAt])

	// The form only shows once the toggle above it is on, so saving it
	// turns job search on.
	function submit(event: FormEvent): void {
		event.preventDefault()

		save
			.mutateAsync(jobSearchPayload(form, true))
			.then((data) => {
				toast.success(data.message)
				setForm(formFrom(data))
			})
			.catch((error) =>
				toast.error(errorMessage(error, "Unable to save your settings."))
			)
	}

	return (
		<form
			onSubmit={submit}
			className="space-y-10">
			<PreferenceFields
				state={state}
				form={form}
				onChange={setForm}
			/>

			<hr />

			<SendingFields
				state={state}
				form={form}
				onChange={setForm}
			/>

			{!profile?.consentedAt && (
				<ConsentField
					form={form}
					onChange={setForm}
				/>
			)}

			<div className="flex justify-end">
				<Button disabled={save.isPending}>
					{save.isPending && <LoaderCircle className="size-4 animate-spin" />}
					{profile?.autoApply ? "Save settings" : "Turn on job search"}
				</Button>
			</div>
		</form>
	)
}

// ─── Job alerts ──────────────────────────────────────────────────────────────

const ALERT_SITES = [
	{
		name: "LinkedIn",
		url: "https://www.linkedin.com/jobs/",
		how: "Search for a role, then set an alert. LinkedIn emails your primary address, so make this address your primary email on LinkedIn.",
	},
	{
		name: "Fuzu",
		url: "https://www.fuzu.com/kenya/jobs",
		how: "Sign up with this address and turn on job alerts.",
	},
	{
		name: "BrighterMonday",
		url: "https://www.brightermonday.co.ke/jobs",
		how: "Search for a role, then sign up for job alerts with this address.",
	},
	{
		name: "MyJobMag",
		url: "https://www.myjobmag.co.ke",
		how: "Subscribe to job alerts with this address.",
	},
	{
		name: "Indeed",
		url: "https://www.indeed.com",
		how: "For remote and international jobs: search, then create a job alert.",
	},
]

function JobAlertsSection({ state }: { state: JobSearchState }) {
	const profile = state.profile
	const address = state.alertAddress

	function copyAddress(): void {
		if (!address) {
			return
		}

		navigator.clipboard
			.writeText(address)
			.then(() => toast.success("Address Copied"))
			.catch(() => toast.error("Unable to copy the address."))
	}

	return (
		<div className="space-y-4">
			<Heading
				variant="small"
				title="Job alerts from other sites"
				description="Turn on job alerts on these sites with your Black Mail address. Each alert is read as it arrives and its jobs are added to your matches, including from sites we can't search ourselves, like LinkedIn and Fuzu. Jobs there are applied for on the site, so they appear under Apply yourself."
			/>

			{address ? (
				<div className="flex flex-wrap items-center gap-3 rounded-lg border p-4">
					<BellRing className="size-5 shrink-0 text-muted-foreground" />
					<div className="min-w-0 flex-1">
						<div className="text-xs text-muted-foreground">
							Sign up for alerts with
						</div>
						<div className="truncate text-sm font-medium">{address}</div>
					</div>
					<Button
						type="button"
						variant="outline"
						size="sm"
						onClick={copyAddress}>
						<Copy className="size-4" />
						Copy
					</Button>
				</div>
			) : (
				<p className="text-sm text-muted-foreground">
					Add a mail account first: alerts need to arrive in your Black Mail
					inbox.
				</p>
			)}

			<ul className="divide-y rounded-lg border">
				{ALERT_SITES.map((site) => (
					<li
						key={site.name}
						className="flex items-start gap-3 p-3">
						<div className="min-w-0 flex-1">
							<div className="text-sm font-medium">{site.name}</div>
							<div className="text-xs text-muted-foreground">{site.how}</div>
						</div>
						<a
							href={site.url}
							target="_blank"
							rel="noopener noreferrer"
							className="inline-flex shrink-0 items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
							Open
							<ExternalLink className="size-3.5" />
						</a>
					</li>
				))}
			</ul>

			<p className="text-xs text-muted-foreground">
				{profile?.lastAlertAt
					? `Last alert read ${formatDistanceToNow(new Date(profile.lastAlertAt), { addSuffix: true })}${profile.lastAlertSource ? ` from ${profile.lastAlertSource}` : ""}.`
					: "No job alerts received yet. Alerts are read while job search is on."}
			</p>
		</div>
	)
}

// ─── Careers pages ───────────────────────────────────────────────────────────

function CareerPagesSection({ state }: { state: JobSearchState }) {
	const add = useAddCareerPage()
	const remove = useRemoveCareerPage()
	const [url, setUrl] = useState("")
	const addedByHand = state.careerPages.filter((page) => page.source === "user")
	const atLimit = addedByHand.length >= state.limits.maxCareerPages
	const lastSearch = state.profile?.lastDiscoveryAt

	function submit(event: FormEvent): void {
		event.preventDefault()

		add
			.mutateAsync(url.trim())
			.then((data) => {
				toast.success(data.message)
				setUrl("")
			})
			.catch((error) =>
				toast.error(errorMessage(error, "Unable to add this page."))
			)
	}

	return (
		<div className="space-y-4">
			<Heading
				variant="small"
				title="Company careers pages"
				description={`Besides job boards, these employers' own careers pages are checked once a day. Each week we search the web for more that match your roles and locations; remove any you don't want and they won't be added again. You can add up to ${state.limits.maxCareerPages} yourself.`}
			/>

			<p className="text-xs text-muted-foreground">
				{lastSearch
					? `Last searched ${formatDistanceToNow(new Date(lastSearch), { addSuffix: true })}.`
					: "The first search runs when job search is turned on."}
			</p>

			{state.careerPages.length > 0 && (
				<ul className="divide-y rounded-lg border">
					{state.careerPages.map((page) => (
						<li
							key={page.id}
							className="flex items-start gap-3 p-3">
							<div className="min-w-0 flex-1">
								<div className="flex min-w-0 items-center gap-2">
									<span className="truncate text-sm font-medium">
										{page.companyName ?? new URL(page.url).hostname}
									</span>
									{page.source === "search" && (
										<Badge
											variant="outline"
											className="shrink-0">
											Found automatically
										</Badge>
									)}
								</div>
								<div className="truncate text-xs text-muted-foreground">
									{page.url}
								</div>
								<div className="text-xs text-muted-foreground">
									{page.lastError ? (
										<span className="text-destructive">{page.lastError}</span>
									) : page.lastFetchedAt ? (
										`Checked ${formatDistanceToNow(new Date(page.lastFetchedAt), { addSuffix: true })}`
									) : (
										"Not checked yet"
									)}
								</div>
							</div>
							<button
								type="button"
								aria-label={`Remove ${page.url}`}
								disabled={remove.isPending}
								onClick={() =>
									remove
										.mutateAsync(page.id)
										.then((data) => toast.success(data.message))
										.catch(() => toast.error("Unable to remove this page."))
								}
								className="rounded-md p-2 text-destructive/80 transition-colors hover:bg-destructive/10 hover:text-destructive">
								<Trash2 className="size-4" />
							</button>
						</li>
					))}
				</ul>
			)}

			{!atLimit && (
				<form
					onSubmit={submit}
					className="flex flex-col gap-2 sm:flex-row sm:items-start">
					<Input
						label="Careers page link - (https://company.co.ke/careers)"
						type="url"
						required
						value={url}
						onChange={(event) => setUrl(event.target.value)}
					/>
					<Button
						disabled={add.isPending || !url.trim()}
						className="sm:mt-2.5">
						{add.isPending && <LoaderCircle className="size-4 animate-spin" />}
						Add
					</Button>
				</form>
			)}
		</div>
	)
}

// ─── Delete ──────────────────────────────────────────────────────────────────

function DeleteSection({
	state,
	onDeleted,
}: {
	state: JobSearchState
	onDeleted: () => void
}) {
	const destroy = useDeleteJobSearch()

	function deleteEverything(): void {
		if (
			!window.confirm(
				"Delete your CV, preferences and all job applications? Mail already sent stays in your mailbox."
			)
		) {
			return
		}

		destroy
			.mutateAsync(undefined)
			.then((data) => {
				toast.success(data.message)
				onDeleted()
			})
			.catch(() => toast.error("Unable to delete your job search data."))
	}

	return (
		<div className="space-y-4">
			<Heading
				variant="small"
				title="Delete job search data"
				description="Removes your CV, what was read from it, your preferences, careers pages and applications."
			/>
			<Button
				type="button"
				variant="destructive"
				disabled={destroy.isPending || !state.profile}
				onClick={deleteEverything}>
				{destroy.isPending && <LoaderCircle className="size-4 animate-spin" />}
				Delete my data
			</Button>
		</div>
	)
}

// ─── Page ────────────────────────────────────────────────────────────────────

/**
 * Turning on only reveals the setup below; saving it turns job search on.
 * Turning off saves straight away, stopping anything not yet sent.
 */
function JobSearchToggle({
	state,
	on,
	onChange,
}: {
	state: JobSearchState
	on: boolean
	onChange: (on: boolean) => void
}) {
	const navigate = useNavigate()
	const save = useSaveJobSearch()
	const profile = state.profile

	function turnOff(): void {
		save
			.mutateAsync(jobSearchPayload(formFrom(state), false))
			.then((data) => {
				toast.success(data.message)
				onChange(false)
			})
			.catch((error) =>
				toast.error(errorMessage(error, "Unable to turn off job search."))
			)
	}

	return (
		<div className="space-y-4">
			{profile?.pausedReason && !profile.autoApply && (
				<Alert variant="destructive">
					<AlertTitle>Job search was paused</AlertTitle>
					<AlertDescription>{profile.pausedReason}</AlertDescription>
				</Alert>
			)}

			{/* Job Search Start */}
			<label className="flex items-center justify-between gap-4 rounded-lg border p-4">
				<span>
					<span className="block text-sm font-medium">Turn on job search</span>
					<span className="block text-xs text-muted-foreground">
						{on && !profile?.autoApply
							? "Finish the steps below, then turn it on at the bottom of the page."
							: "Finds jobs that match your CV and applies by email from your mailbox. Replies arrive in your inbox."}
					</span>
				</span>
				<Switch
					checked={on}
					disabled={save.isPending}
					onCheckedChange={(checked) =>
						checked && needsJobSearchSetup(state)
							? navigate({ to: `${JOB_APPLICATIONS_URL}/setup` as never })
							: checked || !profile?.autoApply
								? onChange(checked)
								: turnOff()
					}
				/>
			</label>
			{/* Job Search End */}
		</div>
	)
}

function JobSearchSections({ state }: { state: JobSearchState }) {
	// Remounts the settings form after a delete so it starts blank again.
	const [formKey, setFormKey] = useState(0)
	const autoApply = !!state.profile?.autoApply
	const [on, setOn] = useState(autoApply)

	// Follow the server when it turns job search on or pauses it.
	useEffect(() => setOn(autoApply), [autoApply])

	return (
		<div className="space-y-10">
			<Heading
				variant="small"
				title="Job search"
				description="Find jobs that match your CV on Kenyan job boards and employers' careers pages, then apply by email from your mailbox. Replies arrive in your inbox."
			/>

			<JobSearchToggle
				state={state}
				on={on}
				onChange={setOn}
			/>

			{on ? (
				<JobSearchSetup
					state={state}
					formKey={formKey}
					onDeleted={() => setFormKey((key) => key + 1)}
				/>
			) : (
				// Saved data stays deletable while job search is off.
				state.profile && (
					<>
						<hr />

						<DeleteSection
							state={state}
							onDeleted={() => setFormKey((key) => key + 1)}
						/>
					</>
				)
			)}
		</div>
	)
}

function JobSearchSetup({
	state,
	formKey,
	onDeleted,
}: {
	state: JobSearchState
	formKey: number
	onDeleted: () => void
}) {
	return (
		<>
			<hr />

			<CvSection state={state} />

			<hr />

			<CoverLetterSection state={state} />

			<hr />

			{state.needsMailbox && (
				<>
					<MailboxSection state={state} />

					<hr />
				</>
			)}

			<SettingsForm
				key={formKey}
				state={state}
			/>

			<hr />

			<JobAlertsSection state={state} />

			<hr />

			<CareerPagesSection state={state} />

			<hr />

			<DeleteSection
				state={state}
				onDeleted={onDeleted}
			/>
		</>
	)
}

export default function JobSearch() {
	const { data, isLoading } = useJobSearch()

	return (
		<>
			<Head title="Job search" />

			<h1 className="sr-only">Job search</h1>

			{isLoading || !data ? (
				<div className="space-y-4">
					<Skeleton className="h-6 w-40" />
					<Skeleton className="h-20 w-full" />
					<Skeleton className="h-14 w-full" />
				</div>
			) : (
				<JobSearchSections state={data} />
			)}
		</>
	)
}

JobSearch.layout = {
	breadcrumbs: [
		{
			title: "Job search",
			href: edit(),
		},
	],
}
