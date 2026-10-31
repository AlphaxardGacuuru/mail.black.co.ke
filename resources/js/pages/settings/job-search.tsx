import { formatDistanceToNow } from "date-fns"
import { FileText, LoaderCircle, Trash2 } from "lucide-react"
import { useEffect, useRef, useState } from "react"
import type { FormEvent } from "react"
import Heading from "@/components/heading"
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Checkbox } from "@/components/ui/checkbox"
import { Input } from "@/components/ui/input"
import { SelectField, SelectItem } from "@/components/ui/select"
import { Skeleton } from "@/components/ui/skeleton"
import { Switch } from "@/components/ui/switch"
import { useApp } from "@/contexts/AppContext"
import { Head } from "@/lib/spa"
import toast from "@/lib/toast"
import {
	errorMessage,
	useAddCareerPage,
	useDeleteJobSearch,
	useJobSearch,
	useRemoveCareerPage,
	useSaveJobSearch,
	useUploadCv,
} from "@/queries/job-search"
import type { JobSearchSettings } from "@/queries/job-search"
import { edit } from "@/routes/job-search"
import type { JobSearchState } from "@/types"

const ACTIVE_ACCOUNT = "active"

function listToText(values: string[]): string {
	return values.join(", ")
}

function textToList(text: string): string[] {
	return text
		.split(",")
		.map((value) => value.trim())
		.filter(Boolean)
}

type Form = Omit<JobSearchSettings, "preferences"> & {
	keywords: string
	locations: string
	remoteOk: boolean
	excludedCompanies: string
}

function formFrom(state: JobSearchState): Form {
	const profile = state.profile

	return {
		autoApply: profile?.autoApply ?? false,
		consent: !!profile?.consentedAt,
		mode: profile?.mode ?? "review",
		mailgunAccountId: profile?.mailgunAccountId ?? null,
		keywords: listToText(profile?.preferences.keywords ?? []),
		locations: listToText(profile?.preferences.locations ?? []),
		remoteOk: profile?.preferences.remoteOk ?? false,
		excludedCompanies: listToText(profile?.preferences.excludedCompanies ?? []),
		dailyLimit: profile?.dailyLimit ?? 5,
		minMatchScore: profile?.minMatchScore ?? 75,
	}
}

// ─── CV ──────────────────────────────────────────────────────────────────────

function CvSection({ state }: { state: JobSearchState }) {
	const upload = useUploadCv()
	const input = useRef<HTMLInputElement>(null)
	const profile = state.profile

	function onFile(file: File | undefined): void {
		if (!file) {
			return
		}

		upload
			.mutateAsync(file)
			.then((data) => toast.success(data.message))
			.catch((error) =>
				toast.error(errorMessage(error, "Unable to upload your CV."))
			)
			.finally(() => {
				if (input.current) {
					input.current.value = ""
				}
			})
	}

	return (
		<div className="space-y-4">
			<Heading
				variant="small"
				title="Your CV"
				description={`A PDF up to ${Math.round(state.limits.maxCvKilobytes / 1024)} MB. It's stored privately and only sent to employers you apply to.`}
			/>

			<div className="flex flex-wrap items-center gap-3 rounded-lg border p-4">
				<FileText className="size-5 shrink-0 text-muted-foreground" />
				<div className="min-w-0 flex-1">
					<div className="truncate text-sm font-medium">
						{profile?.cvName ?? "No CV uploaded"}
					</div>
					{profile?.cvStatus === "reading" && (
						<div className="flex items-center gap-1.5 text-xs text-muted-foreground">
							<LoaderCircle className="size-3 animate-spin" />
							Reading your CV…
						</div>
					)}
					{profile?.cvStatus === "ready" && profile.profile?.headline && (
						<div className="truncate text-xs text-muted-foreground">
							{profile.profile.headline}
						</div>
					)}
				</div>
				<input
					ref={input}
					type="file"
					accept="application/pdf,.pdf"
					className="hidden"
					onChange={(event) => onFile(event.target.files?.[0])}
				/>
				<Button
					type="button"
					variant="outline"
					disabled={upload.isPending}
					onClick={() => input.current?.click()}>
					{upload.isPending && <LoaderCircle className="size-4 animate-spin" />}
					{profile?.cvName ? "Replace CV" : "Upload CV"}
				</Button>
			</div>

			{profile?.cvStatus === "ready" &&
				(profile.profile?.target_titles?.length ?? 0) > 0 && (
					<div className="space-y-2">
						<div className="text-xs text-muted-foreground">
							Roles we'll look for, from your CV:
						</div>
						<div className="flex flex-wrap gap-1.5">
							{profile.profile?.target_titles?.map((title) => (
								<Badge
									key={title}
									variant="secondary">
									{title}
								</Badge>
							))}
						</div>
					</div>
				)}
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
				description={`Besides MyJobMag, these employers' own careers pages are checked once a day. Each week we search the web for more that match your roles and locations; remove any you don't want and they won't be added again. You can add up to ${state.limits.maxCareerPages} yourself.`}
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
						label="Careers page link"
						type="url"
						required
						value={url}
						placeholder="https://company.co.ke/careers"
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

// ─── Page ────────────────────────────────────────────────────────────────────

function SettingsForm({ state }: { state: JobSearchState }) {
	const { auth } = useApp()
	const save = useSaveJobSearch()
	const destroy = useDeleteJobSearch()
	const [form, setForm] = useState<Form>(() => formFrom(state))
	const accounts = auth?.mailgunAccounts ?? []
	const profile = state.profile

	// Pick up server-side changes (a pause, a reset) without losing edits
	// in progress to every background refetch.
	useEffect(() => {
		setForm((current) => ({
			...current,
			autoApply: profile?.autoApply ?? false,
			consent: !!profile?.consentedAt,
		}))
	}, [profile?.autoApply, profile?.consentedAt])

	function submit(event: FormEvent): void {
		event.preventDefault()

		save
			.mutateAsync({
				autoApply: form.autoApply,
				consent: form.consent,
				mode: form.mode,
				mailgunAccountId: form.mailgunAccountId,
				preferences: {
					keywords: textToList(form.keywords),
					locations: textToList(form.locations),
					remoteOk: form.remoteOk,
					excludedCompanies: textToList(form.excludedCompanies),
				},
				dailyLimit: Number(form.dailyLimit),
				minMatchScore: Number(form.minMatchScore),
			})
			.then((data) => {
				toast.success(data.message)
				setForm(formFrom(data))
			})
			.catch((error) =>
				toast.error(errorMessage(error, "Unable to save your settings."))
			)
	}

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
				setForm(formFrom(data))
			})
			.catch(() => toast.error("Unable to delete your job search data."))
	}

	return (
		<div className="space-y-10">
			<form
				onSubmit={submit}
				className="space-y-10">
				<div className="space-y-4">
					<Heading
						variant="small"
						title="Job search"
						description="Find jobs that match your CV on MyJobMag and the careers pages below, then apply by email from your mailbox. Replies arrive in your inbox."
					/>

					{profile?.pausedReason && !form.autoApply && (
						<Alert variant="destructive">
							<AlertTitle>Job search was paused</AlertTitle>
							<AlertDescription>{profile.pausedReason}</AlertDescription>
						</Alert>
					)}

					<label className="flex items-center justify-between gap-4 rounded-lg border p-4">
						<span>
							<span className="block text-sm font-medium">
								Apply for jobs for me
							</span>
							<span className="block text-xs text-muted-foreground">
								Turning this off stops anything not yet sent.
							</span>
						</span>
						<Switch
							checked={form.autoApply}
							onCheckedChange={(autoApply) => setForm({ ...form, autoApply })}
						/>
					</label>

					{!profile?.consentedAt && (
						<label className="flex items-start gap-3 text-sm">
							<Checkbox
								className="mt-0.5"
								checked={form.consent}
								onCheckedChange={(checked) =>
									setForm({ ...form, consent: checked === true })
								}
							/>
							<span>
								I agree to let this app read my CV with AI and send job
								applications with it, from my mailbox, on my behalf.
							</span>
						</label>
					)}

					<SelectField
						label="Before sending"
						value={form.mode}
						onValueChange={(mode) =>
							setForm({ ...form, mode: mode as Form["mode"] })
						}>
						<SelectItem value="review">
							Let me review each application
						</SelectItem>
						<SelectItem value="autopilot">
							Send strong matches automatically
						</SelectItem>
					</SelectField>

					<SelectField
						label="Send from"
						value={form.mailgunAccountId ?? ACTIVE_ACCOUNT}
						onValueChange={(value) =>
							setForm({
								...form,
								mailgunAccountId: value === ACTIVE_ACCOUNT ? null : value,
							})
						}>
						<SelectItem value={ACTIVE_ACCOUNT}>
							My active mail account
						</SelectItem>
						{accounts.map((account) => (
							<SelectItem
								key={account.id}
								value={account.id}>
								{account.mailboxAddress}
							</SelectItem>
						))}
					</SelectField>
				</div>

				<div className="space-y-4">
					<Heading
						variant="small"
						title="What to look for"
						description="Separate entries with commas. Roles from your CV are always included."
					/>
					<Input
						label="Extra job titles or keywords"
						value={form.keywords}
						placeholder="Accountant, Finance Officer"
						onChange={(event) =>
							setForm({ ...form, keywords: event.target.value })
						}
					/>
					<Input
						label="Locations"
						value={form.locations}
						placeholder="Nairobi, Kiambu"
						onChange={(event) =>
							setForm({ ...form, locations: event.target.value })
						}
					/>
					<label className="flex items-center justify-between gap-4 text-sm">
						Include remote jobs anywhere
						<Switch
							checked={form.remoteOk}
							onCheckedChange={(remoteOk) => setForm({ ...form, remoteOk })}
						/>
					</label>
					<Input
						label="Never apply to these companies"
						value={form.excludedCompanies}
						onChange={(event) =>
							setForm({ ...form, excludedCompanies: event.target.value })
						}
					/>
				</div>

				<div className="space-y-4">
					<Heading
						variant="small"
						title="Limits"
						description="Applications go out on weekdays during working hours, spread through the day, and never twice to the same company within a month."
					/>
					<div className="grid gap-4 sm:grid-cols-2">
						<Input
							label="Applications per day"
							type="number"
							min={1}
							max={state.limits.maxDailyLimit}
							value={form.dailyLimit}
							helperText={`Up to ${state.limits.maxDailyLimit}`}
							onChange={(event) =>
								setForm({ ...form, dailyLimit: Number(event.target.value) })
							}
						/>
						<Input
							label="Minimum match score"
							type="number"
							min={state.limits.minMatchScoreFloor}
							max={100}
							value={form.minMatchScore}
							helperText={`${state.limits.minMatchScoreFloor} to 100`}
							onChange={(event) =>
								setForm({ ...form, minMatchScore: Number(event.target.value) })
							}
						/>
					</div>
				</div>

				<div className="flex justify-end">
					<Button disabled={save.isPending}>
						{save.isPending && <LoaderCircle className="size-4 animate-spin" />}
						Save settings
					</Button>
				</div>
			</form>

			<CvSection state={state} />

			<CareerPagesSection state={state} />

			<div className="space-y-4">
				<Heading
					variant="small"
					title="Delete job search data"
					description="Removes your CV, what was read from it, your preferences, careers pages and applications."
				/>
				<Button
					type="button"
					variant="destructive"
					disabled={destroy.isPending || !profile}
					onClick={deleteEverything}>
					{destroy.isPending && (
						<LoaderCircle className="size-4 animate-spin" />
					)}
					Delete my data
				</Button>
			</div>
		</div>
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
				<SettingsForm state={data} />
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
