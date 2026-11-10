import { AtSign, FileText, LoaderCircle, Sparkles, Upload } from "lucide-react"
import { useEffect, useRef, useState } from "react"
import type { FormEvent } from "react"
import Heading from "@/components/heading"
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Checkbox } from "@/components/ui/checkbox"
import { Input } from "@/components/ui/input"
import { Link } from "@/components/ui/link"
import { MultiSelectField } from "@/components/ui/multi-select"
import { SelectField, SelectItem } from "@/components/ui/select"
import { Skeleton } from "@/components/ui/skeleton"
import { Switch } from "@/components/ui/switch"
import { useApp } from "@/contexts/AppContext"
import { CV_REVAMP_URL } from "@/lib/job-application-tabs"
import { KENYA_LOCATIONS } from "@/lib/kenya-locations"
import toast from "@/lib/toast"
import { cn } from "@/lib/utils"
import {
	errorMessage,
	useCreateMailbox,
	useMailboxSuggestions,
	useSaveCoverLetter,
	useUploadCoverLetter,
	useUploadCv,
} from "@/queries/job-search"
import type { JobSearchSettings } from "@/queries/job-search"
import type { CoverLetterMode, JobSearchState } from "@/types"

const ACTIVE_ACCOUNT = "active"

export function hasCv(state: JobSearchState): boolean {
	return !!state.profile && state.profile.cvStatus !== "missing"
}

/**
 * Whether the user still has setup to do, so turning job search on should
 * open the wizard rather than the settings.
 */
export function needsJobSearchSetup(state: JobSearchState): boolean {
	return !hasCv(state) || state.needsMailbox || !state.profile?.consentedAt
}

function listToText(values: string[]): string {
	return values.join(", ")
}

export function textToList(text: string): string[] {
	return text
		.split(",")
		.map((value) => value.trim())
		.filter(Boolean)
}

export type JobSearchForm = Omit<JobSearchSettings, "preferences"> & {
	keywords: string
	locations: string[]
	remoteOk: boolean
	excludedCompanies: string
}

export function formFrom(state: JobSearchState): JobSearchForm {
	const profile = state.profile

	return {
		autoApply: profile?.autoApply ?? false,
		consent: !!profile?.consentedAt,
		mode: profile?.mode ?? "review",
		mailgunAccountId: profile?.mailgunAccountId ?? null,
		keywords: listToText(profile?.preferences.keywords ?? []),
		locations: profile?.preferences.locations ?? [],
		remoteOk: profile?.preferences.remoteOk ?? false,
		excludedCompanies: listToText(profile?.preferences.excludedCompanies ?? []),
		dailyLimit: profile?.dailyLimit ?? 5,
		minMatchScore: profile?.minMatchScore ?? 75,
	}
}

// ─── CV ──────────────────────────────────────────────────────────────────────

export function CvSection({ state }: { state: JobSearchState }) {
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

			{profile?.cvStatus === "ready" && (
				<div className="space-y-3 text-sm">
					<div className="flex gap-3">
						<Sparkles className="mt-0.5 size-4 shrink-0 text-primary" />
						<span className="min-w-0 text-muted-foreground">
							{profile.revampedCvInUse
								? "Employers get your CV as revamped by the AI."
								: "Get more interviews: have the AI revamp your CV. You review every change first."}
						</span>
					</div>
					<div className="flex justify-end">
						<Link
							href={CV_REVAMP_URL}
							variant="outline"
							size="sm">
							{profile.revampedCvInUse ? "Revamped CV" : "Revamp with AI"}
						</Link>
					</div>
				</div>
			)}

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

// ─── Cover letter ────────────────────────────────────────────────────────────

const COVER_LETTER_MAX = 5000

export function CoverLetterSection({ state }: { state: JobSearchState }) {
	const save = useSaveCoverLetter()
	const upload = useUploadCoverLetter()
	const input = useRef<HTMLInputElement>(null)
	const profile = state.profile
	const [coverLetter, setCoverLetter] = useState(profile?.coverLetter ?? "")
	const [mode, setMode] = useState<CoverLetterMode>(
		profile?.coverLetterMode ?? "tailor"
	)

	// An uploaded letter replaces what's in the box.
	useEffect(() => {
		setCoverLetter(profile?.coverLetter ?? "")
	}, [profile?.coverLetter])

	function onFile(file: File | undefined): void {
		if (!file) {
			return
		}

		upload
			.mutateAsync(file)
			.then((data) => toast.success(data.message))
			.catch((error) =>
				toast.error(errorMessage(error, "Unable to read your cover letter."))
			)
			.finally(() => {
				if (input.current) {
					input.current.value = ""
				}
			})
	}

	function submit(event: FormEvent): void {
		event.preventDefault()

		save
			.mutateAsync({ coverLetter, coverLetterMode: mode })
			.then((data) => toast.success(data.message))
			.catch((error) =>
				toast.error(errorMessage(error, "Unable to save your cover letter."))
			)
	}

	return (
		<form
			onSubmit={submit}
			className="space-y-4">
			<Heading
				variant="small"
				title="Cover letter"
				description="Optional. Add your own letter and it's used for every application; without one, a letter is written for each job from your CV."
			/>

			<SelectField
				label="How to use it"
				value={mode}
				onValueChange={(value) => setMode(value as CoverLetterMode)}
				helperText={
					mode === "tailor"
						? "Your wording and structure are kept; only the role, the employer and which experience to highlight change for each job."
						: "Sent unchanged. Write {company} and {role} where the employer and job title go."
				}>
				<SelectItem value="tailor">Tailor it to each job</SelectItem>
				<SelectItem value="as_is">Send it as written</SelectItem>
			</SelectField>

			<div className="space-y-1.5">
				<textarea
					aria-label="Your cover letter"
					value={coverLetter}
					rows={12}
					maxLength={COVER_LETTER_MAX}
					placeholder={
						"Dear Hiring Manager,\n\nI'm applying for the {role} position at {company}…"
					}
					onChange={(event) => setCoverLetter(event.target.value)}
					className="w-full rounded-lg border border-neutral-300 bg-transparent p-4 font-nunito text-sm font-light focus:border-neutral-500 focus:outline-none focus:ring-2 focus:ring-neutral-400/20 dark:border-white/20"
				/>
				<div className="text-right text-xs text-muted-foreground">
					{coverLetter.length.toLocaleString()} /{" "}
					{COVER_LETTER_MAX.toLocaleString()}
				</div>
			</div>

			<div className="flex flex-wrap justify-end gap-2">
				<input
					ref={input}
					type="file"
					accept=".pdf,.docx,.txt,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document,text/plain"
					className="hidden"
					onChange={(event) => onFile(event.target.files?.[0])}
				/>
				<Button
					type="button"
					variant="outline"
					disabled={upload.isPending}
					onClick={() => input.current?.click()}>
					{upload.isPending ? (
						<LoaderCircle className="size-4 animate-spin" />
					) : (
						<Upload className="size-4" />
					)}
					Upload PDF or Word
				</Button>
				<Button disabled={save.isPending}>
					{save.isPending && <LoaderCircle className="size-4 animate-spin" />}
					Save cover letter
				</Button>
			</div>
		</form>
	)
}

// ─── Address ─────────────────────────────────────────────────────────────────

/**
 * For users without a mailbox: pick an address on our domain to apply
 * from. Once created it's their mail account, and this section goes away.
 */
export function MailboxSection({ state }: { state: JobSearchState }) {
	const hasCv =
		state.profile?.cvStatus !== undefined &&
		state.profile.cvStatus !== "missing"
	const suggestions = useMailboxSuggestions(hasCv)
	const create = useCreateMailbox()
	const [chosen, setChosen] = useState<string | null>(null)
	const [consent, setConsent] = useState(false)
	const address = chosen ?? suggestions.data?.[0] ?? null

	function submit(event: FormEvent): void {
		event.preventDefault()

		if (!address) {
			return
		}

		create
			.mutateAsync(address)
			.then((data) => toast.success(data.message))
			.catch((error) => {
				toast.error(errorMessage(error, "Unable to create the address."))
				// Someone may have just taken it: offer fresh ones.
				setChosen(null)
				suggestions.refetch()
			})
	}

	return (
		<form
			onSubmit={submit}
			className="space-y-4">
			<Heading
				variant="small"
				title="Your job search address"
				description="Applications are sent from this address, and employers' replies arrive in your inbox here. Use it to sign up for job alerts too."
			/>

			{!hasCv ? (
				<p className="text-sm text-muted-foreground">
					Upload your CV above first, then choose your address here.
				</p>
			) : suggestions.isLoading ? (
				<div className="space-y-2">
					<Skeleton className="h-12 w-full" />
					<Skeleton className="h-12 w-full" />
				</div>
			) : suggestions.isError || !suggestions.data?.length ? (
				<Alert variant="destructive">
					<AlertTitle>No address available right now</AlertTitle>
					<AlertDescription>
						Try again in a moment.
						<Button
							type="button"
							variant="secondary"
							size="sm"
							className="mt-2"
							onClick={() => suggestions.refetch()}>
							Try again
						</Button>
					</AlertDescription>
				</Alert>
			) : (
				<>
					{/* Address Start */}
					<div
						role="radiogroup"
						aria-label="Address"
						className="space-y-2">
						{suggestions.data.map((suggestion) => (
							<label
								key={suggestion}
								className={cn(
									"flex cursor-pointer items-center gap-3 rounded-lg border p-3 text-sm",
									suggestion === address && "border-primary bg-primary/5"
								)}>
								<input
									type="radio"
									name="address"
									value={suggestion}
									checked={suggestion === address}
									onChange={() => setChosen(suggestion)}
									className="accent-primary"
								/>
								<AtSign className="size-4 shrink-0 text-muted-foreground" />
								<span className="truncate font-medium">{suggestion}</span>
							</label>
						))}
					</div>
					{/* Address End */}

					{/* Consent Start */}
					<label className="flex items-start gap-3 text-sm">
						<Checkbox
							className="mt-0.5"
							checked={consent}
							onCheckedChange={(checked) => setConsent(checked === true)}
						/>
						<span>
							Create {address} for me. It's only for job applications and
							replies to them, and can't be changed or removed later.
						</span>
					</label>
					{/* Consent End */}

					<div className="flex justify-end">
						<Button disabled={!consent || create.isPending}>
							{create.isPending && (
								<LoaderCircle className="size-4 animate-spin" />
							)}
							Create address
						</Button>
					</div>
				</>
			)}
		</form>
	)
}

// ─── Settings fields ─────────────────────────────────────────────────────────

/**
 * The settings to save from the form, turning job search on or off.
 */
export function jobSearchPayload(
	form: JobSearchForm,
	autoApply: boolean
): JobSearchSettings {
	return {
		autoApply,
		consent: form.consent,
		mode: form.mode,
		mailgunAccountId: form.mailgunAccountId,
		preferences: {
			keywords: textToList(form.keywords),
			locations: form.locations,
			remoteOk: form.remoteOk,
			excludedCompanies: textToList(form.excludedCompanies),
		},
		dailyLimit: Number(form.dailyLimit),
		minMatchScore: Number(form.minMatchScore),
	}
}

type FieldsProps = {
	state: JobSearchState
	form: JobSearchForm
	onChange: (form: JobSearchForm) => void
}

export function PreferenceFields({ state, form, onChange }: FieldsProps) {
	return (
		<div className="space-y-4">
			<Heading
				variant="small"
				title="What to look for"
				description="Separate entries with commas. Roles from your CV are always included."
			/>
			{/* Keywords Start */}
			<Input
				label="Extra job titles or keywords - (Accountant, Finance Officer)"
				value={form.keywords}
				onChange={(event) =>
					onChange({ ...form, keywords: event.target.value })
				}
			/>
			{/* Keywords End */}
			{/* Locations Start */}
			<MultiSelectField
				label="Locations"
				options={KENYA_LOCATIONS}
				value={form.locations}
				max={state.limits.maxLocations}
				searchPlaceholder="Search counties and towns"
				helperText="Leave empty to include jobs anywhere in Kenya."
				onChange={(locations) => onChange({ ...form, locations })}
			/>
			{/* Locations End */}
			{/* Remote Start */}
			<label className="flex items-center justify-between gap-4 text-sm">
				Include remote jobs anywhere
				<Switch
					checked={form.remoteOk}
					onCheckedChange={(remoteOk) => onChange({ ...form, remoteOk })}
				/>
			</label>
			{/* Remote End */}
			{/* Excluded Companies Start */}
			<Input
				label="Never apply to these companies"
				value={form.excludedCompanies}
				onChange={(event) =>
					onChange({ ...form, excludedCompanies: event.target.value })
				}
			/>
			{/* Excluded Companies End */}
		</div>
	)
}

export function SendingFields({ state, form, onChange }: FieldsProps) {
	const { auth } = useApp()
	const accounts = auth?.mailgunAccounts ?? []

	return (
		<div className="space-y-4">
			<Heading
				variant="small"
				title="Sending"
				description="Applications go out on weekdays during working hours, spread through the day, and never twice to the same company within a month."
			/>
			{/* Before Sending Start */}
			<SelectField
				label="Before sending"
				value={form.mode}
				onValueChange={(mode) =>
					onChange({ ...form, mode: mode as JobSearchForm["mode"] })
				}>
				<SelectItem value="review">Let me review each application</SelectItem>
				<SelectItem value="autopilot">
					Send strong matches automatically
				</SelectItem>
			</SelectField>
			{/* Before Sending End */}

			{/* With one mailbox there's nothing to choose. */}
			{accounts.length > 1 && (
				<>
					{/* Send From Start */}
					<SelectField
						label="Send from"
						value={form.mailgunAccountId ?? ACTIVE_ACCOUNT}
						onValueChange={(value) =>
							onChange({
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
					{/* Send From End */}
				</>
			)}

			<div className="grid gap-4 sm:grid-cols-2">
				{/* Daily Limit Start */}
				<Input
					label="Applications per day"
					type="number"
					min={1}
					max={state.limits.maxDailyLimit}
					value={form.dailyLimit}
					helperText={`Up to ${state.limits.maxDailyLimit}`}
					onChange={(event) =>
						onChange({ ...form, dailyLimit: Number(event.target.value) })
					}
				/>
				{/* Daily Limit End */}
				{/* Minimum Match Score Start */}
				<Input
					label="Minimum match score"
					type="number"
					min={state.limits.minMatchScoreFloor}
					max={100}
					value={form.minMatchScore}
					helperText={`${state.limits.minMatchScoreFloor} to 100`}
					onChange={(event) =>
						onChange({ ...form, minMatchScore: Number(event.target.value) })
					}
				/>
				{/* Minimum Match Score End */}
			</div>
		</div>
	)
}

export function ConsentField({ form, onChange }: Omit<FieldsProps, "state">) {
	return (
		<>
			{/* Consent Start */}
			<label className="flex items-start gap-3 text-sm">
				<Checkbox
					className="mt-0.5"
					checked={form.consent}
					onCheckedChange={(checked) =>
						onChange({ ...form, consent: checked === true })
					}
				/>
				<span>
					I agree to let this app read my CV with AI and send job applications
					with it, from my mailbox, on my behalf.
				</span>
			</label>
			{/* Consent End */}
		</>
	)
}
