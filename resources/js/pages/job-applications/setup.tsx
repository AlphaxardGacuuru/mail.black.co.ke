import { useNavigate } from "@tanstack/react-router"
import { Check, CircleCheck, LoaderCircle } from "lucide-react"
import { useEffect, useState } from "react"
import Heading from "@/components/heading"
import {
	ConsentField,
	CoverLetterSection,
	CvSection,
	MailboxSection,
	PreferenceFields,
	SendingFields,
	formFrom,
	hasCv,
	jobSearchPayload,
} from "@/components/job-search/setup-sections"
import type { JobSearchForm } from "@/components/job-search/setup-sections"
import { Button } from "@/components/ui/button"
import { GlassCard } from "@/components/ui/glass-card"
import { Skeleton } from "@/components/ui/skeleton"
import { useApp } from "@/contexts/AppContext"
import { JOB_APPLICATIONS_URL } from "@/lib/job-application-tabs"
import { Head } from "@/lib/spa"
import toast from "@/lib/toast"
import { cn } from "@/lib/utils"
import {
	errorMessage,
	useJobSearch,
	useSaveJobSearch,
	useSaveSetupStep,
} from "@/queries/job-search"
import type { JobSearchState } from "@/types"
import { JOB_SEARCH_SETUP_STEPS } from "@/types/job-search"
import type { JobSearchSetupStep } from "@/types/job-search"

const STEP_TITLES: Record<JobSearchSetupStep, string> = {
	cv: "Your CV",
	address: "Your address",
	cover_letter: "Cover letter",
	preferences: "What to look for",
	sending: "Turn it on",
}

/**
 * Where to pick up: the first required step that isn't done, else the
 * step the user last reached.
 */
function resumeStep(
	state: JobSearchState,
	steps: JobSearchSetupStep[]
): JobSearchSetupStep {
	if (!hasCv(state)) {
		return "cv"
	}

	if (state.needsMailbox) {
		return "address"
	}

	const saved = state.profile?.setupStep

	return saved && steps.indexOf(saved) > steps.indexOf("cover_letter")
		? saved
		: "cover_letter"
}

function Wizard({ state }: { state: JobSearchState }) {
	const navigate = useNavigate()
	const { auth } = useApp()
	const saveStep = useSaveSetupStep()
	const save = useSaveJobSearch()
	// Without a mailbox at the start, the address step stays in the list
	// after it's created, so the numbering doesn't shift.
	const [steps] = useState(() =>
		JOB_SEARCH_SETUP_STEPS.filter(
			(step) => step !== "address" || state.needsMailbox
		)
	)
	const [step, setStep] = useState(() => resumeStep(state, steps))
	const [form, setForm] = useState<JobSearchForm>(() => formFrom(state))
	const index = steps.indexOf(step)
	const address = auth?.mailgunAccounts?.find(
		(account) => account.isActive
	)?.mailboxAddress

	function goTo(next: JobSearchSetupStep): void {
		setStep(next)
		window.scrollTo({ top: 0 })
		saveStep.mutate(next)
	}

	function next(): void {
		if (step === "preferences") {
			save
				.mutateAsync(jobSearchPayload(form, false))
				.then(() => goTo(steps[index + 1]))
				.catch((error) =>
					toast.error(errorMessage(error, "Unable to save your preferences."))
				)

			return
		}

		goTo(steps[index + 1])
	}

	function finish(): void {
		save
			.mutateAsync(jobSearchPayload(form, true))
			.then((data) => {
				toast.success(data.message)
				navigate({ to: JOB_APPLICATIONS_URL as never })
			})
			.catch((error) =>
				toast.error(errorMessage(error, "Unable to turn on job search."))
			)
	}

	const canContinue =
		step === "cv"
			? hasCv(state)
			: step === "address"
				? !state.needsMailbox
				: true

	return (
		<div className="mx-auto w-full max-w-2xl space-y-6">
			<Heading
				title="Set up job search"
				description="A few steps and we'll start finding jobs that match your CV. You can leave and pick up here later."
			/>

			<ol className="flex gap-1.5">
				{steps.map((item, position) => (
					<li
						key={item}
						className="min-w-0 flex-1 space-y-1.5">
						<div
							className={cn(
								"h-1.5 rounded-full",
								position <= index ? "bg-primary" : "bg-muted"
							)}
						/>
						<div
							className={cn(
								"hidden truncate text-xs sm:block",
								position === index
									? "font-medium text-foreground"
									: "text-muted-foreground"
							)}>
							{STEP_TITLES[item]}
						</div>
					</li>
				))}
			</ol>

			<GlassCard className="space-y-6 p-4 sm:p-6">
				<p className="text-xs text-muted-foreground sm:hidden">
					Step {index + 1} of {steps.length}: {STEP_TITLES[step]}
				</p>

				{step === "cv" && <CvSection state={state} />}

				{step === "address" &&
					(state.needsMailbox ? (
						<MailboxSection state={state} />
					) : (
						<div className="flex items-center gap-3 text-sm">
							<CircleCheck className="size-5 shrink-0 text-primary" />
							<span>
								Your applications will be sent from{" "}
								<span className="font-medium">{address}</span>.
							</span>
						</div>
					))}

				{step === "cover_letter" && <CoverLetterSection state={state} />}

				{step === "preferences" && (
					<PreferenceFields
						state={state}
						form={form}
						onChange={setForm}
					/>
				)}

				{step === "sending" && (
					<div className="space-y-6">
						<SendingFields
							state={state}
							form={form}
							onChange={setForm}
						/>
						{!state.profile?.consentedAt && (
							<ConsentField
								form={form}
								onChange={setForm}
							/>
						)}
					</div>
				)}
			</GlassCard>

			<div className="flex items-center justify-between gap-2">
				<Button
					type="button"
					variant="secondary"
					disabled={index === 0}
					onClick={() => goTo(steps[index - 1])}>
					Back
				</Button>

				{step === "sending" ? (
					<Button
						type="button"
						disabled={
							save.isPending || (!state.profile?.consentedAt && !form.consent)
						}
						onClick={finish}>
						{save.isPending ? (
							<LoaderCircle className="size-4 animate-spin" />
						) : (
							<Check className="size-4" />
						)}
						Turn on job search
					</Button>
				) : (
					<Button
						type="button"
						disabled={!canContinue || save.isPending}
						onClick={next}>
						{save.isPending && <LoaderCircle className="size-4 animate-spin" />}
						{step === "cover_letter" && !state.profile?.coverLetter
							? "Skip"
							: "Next"}
					</Button>
				)}
			</div>
		</div>
	)
}

/**
 * Job search setup, one step at a time: CV, the address to apply from,
 * an optional cover letter, what to look for, then how to send and
 * turning it on. The step reached is saved, so leaving part way picks up
 * there next time.
 */
export default function JobSearchSetup() {
	const navigate = useNavigate()
	const { data, isLoading } = useJobSearch()
	const isOn = !!data?.profile?.autoApply

	// Nothing to set up once it's on; settings is where to change things.
	useEffect(() => {
		if (isOn) {
			navigate({ to: JOB_APPLICATIONS_URL as never })
		}
	}, [isOn, navigate])

	return (
		<>
			<Head title="Set up job search" />

			{isLoading || !data || isOn ? (
				<div className="mx-auto w-full max-w-2xl space-y-4">
					<Skeleton className="h-8 w-56" />
					<Skeleton className="h-2 w-full" />
					<Skeleton className="h-64 w-full" />
				</div>
			) : (
				<Wizard state={data} />
			)}
		</>
	)
}

JobSearchSetup.layout = {
	breadcrumbs: [
		{ title: "Jobs", href: JOB_APPLICATIONS_URL },
		{ title: "Set up", href: `${JOB_APPLICATIONS_URL}/setup` },
	],
}
