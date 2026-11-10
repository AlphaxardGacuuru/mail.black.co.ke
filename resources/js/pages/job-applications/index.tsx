import { format, formatDistanceToNow } from "date-fns"
import {
	BellRing,
	Briefcase,
	ChevronDown,
	CircleCheck,
	ExternalLink,
	FileText,
	LoaderCircle,
	MailOpen,
	Plus,
	Sparkles,
	Wallet,
} from "lucide-react"
import { useEffect, useState } from "react"
import Heading from "@/components/heading"
import { ApplicationFormPanel } from "@/components/job-search/application-form-panel"
import { JobDetailsSheet } from "@/components/job-search/job-details-sheet"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { GlassCard } from "@/components/ui/glass-card"
import { Input } from "@/components/ui/input"
import { Link } from "@/components/ui/link"
import { Skeleton } from "@/components/ui/skeleton"
import {
	CV_REVAMP_URL,
	JOB_APPLICATION_TABS,
	JOB_APPLICATIONS_URL,
} from "@/lib/job-application-tabs"
import { formatSalary } from "@/lib/salary"
import { Head } from "@/lib/spa"
import toast from "@/lib/toast"
import { cn } from "@/lib/utils"
import {
	errorMessage,
	useApproveApplication,
	useJobApplications,
	useJobSearch,
	useMarkApplied,
	useSaveDraft,
	useSkipApplication,
	useTailorApplicationCv,
} from "@/queries/job-search"
import type { JobApplication, JobApplicationTab } from "@/types"

function ScoreBadge({ score }: { score: number | null }) {
	if (score === null) {
		return null
	}

	return (
		<Badge
			variant={score >= 85 ? "default" : "secondary"}
			title="How well this job matches your CV">
			{score}% match
		</Badge>
	)
}

function DraftEditor({ application }: { application: JobApplication }) {
	const approve = useApproveApplication()
	const saveDraft = useSaveDraft()
	const skip = useSkipApplication()
	const [subject, setSubject] = useState(application.subject ?? "")
	const [coverLetter, setCoverLetter] = useState(application.coverLetter ?? "")
	const busy = approve.isPending || saveDraft.isPending || skip.isPending
	const edited =
		subject !== (application.subject ?? "") ||
		coverLetter !== (application.coverLetter ?? "")

	// The draft arrives a moment after the match; show it once it does.
	useEffect(() => {
		setSubject(application.subject ?? "")
		setCoverLetter(application.coverLetter ?? "")
	}, [application.subject, application.coverLetter])

	if (!application.coverLetter) {
		return (
			<div className="flex items-center gap-2 text-sm text-muted-foreground">
				<LoaderCircle className="size-4 animate-spin" />
				Writing the application…
			</div>
		)
	}

	return (
		<div className="space-y-3">
			<Input
				label="Subject"
				value={subject}
				onChange={(event) => setSubject(event.target.value)}
			/>
			<textarea
				aria-label="Application email"
				value={coverLetter}
				rows={10}
				onChange={(event) => setCoverLetter(event.target.value)}
				className="w-full rounded-lg border border-neutral-300 bg-transparent p-4 font-nunito text-sm font-light focus:border-neutral-500 focus:outline-none focus:ring-2 focus:ring-neutral-400/20 dark:border-white/20"
			/>
			<p className="text-xs text-muted-foreground">
				Sent to {application.posting.applyEmail} with your CV attached.
			</p>
			<div className="flex flex-wrap justify-end gap-2">
				<Button
					type="button"
					variant="secondary"
					disabled={busy}
					onClick={() =>
						skip
							.mutateAsync(application.id)
							.then((data) => toast.success(data.message))
							.catch((error) =>
								toast.error(
									errorMessage(error, "Unable to skip this application.")
								)
							)
					}>
					Skip
				</Button>
				{edited && (
					<Button
						type="button"
						variant="secondary"
						disabled={busy}
						onClick={() =>
							saveDraft
								.mutateAsync({ id: application.id, subject, coverLetter })
								.then((data) => toast.success(data.message))
								.catch((error) =>
									toast.error(errorMessage(error, "Unable to save the draft."))
								)
						}>
						Save draft
					</Button>
				)}
				<Button
					type="button"
					disabled={busy || !subject.trim() || !coverLetter.trim()}
					onClick={() =>
						approve
							.mutateAsync({ id: application.id, subject, coverLetter })
							.then((data) => toast.success(data.message))
							.catch((error) =>
								toast.error(
									errorMessage(error, "Unable to approve this application.")
								)
							)
					}>
					{approve.isPending && (
						<LoaderCircle className="size-4 animate-spin" />
					)}
					Approve & send
				</Button>
			</div>
		</div>
	)
}

/**
 * The CV tailored to this job: a link to it once made, else a button to
 * make one (when the revamped CV is in use).
 */
function TailoredCv({ application }: { application: JobApplication }) {
	const { data: settings } = useJobSearch()
	const tailor = useTailorApplicationCv()
	const { cv } = application

	if (cv?.status === "tailoring" || tailor.isPending) {
		return (
			<p className="flex items-center gap-2 text-sm text-muted-foreground">
				<LoaderCircle className="size-4 animate-spin" />
				Tailoring your CV to this job…
			</p>
		)
	}

	if (cv?.status === "ready" && cv.url) {
		return (
			<a
				href={cv.url}
				target="_blank"
				rel="noopener noreferrer"
				className="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground">
				<FileText className="size-4" />
				CV tailored to this job
			</a>
		)
	}

	if (!settings?.profile?.revampedCvInUse) {
		return null
	}

	return (
		<div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
			{cv?.status === "failed" && <span>Your CV couldn't be tailored.</span>}
			<Button
				type="button"
				variant="secondary"
				size="sm"
				onClick={() =>
					tailor
						.mutateAsync(application.id)
						.then((data) => toast.success(data.message))
						.catch((error) =>
							toast.error(errorMessage(error, "Unable to tailor your CV."))
						)
				}>
				<Sparkles />
				{cv?.status === "failed" ? "Try again" : "Tailor my CV to this job"}
			</Button>
		</div>
	)
}

function ApplicationCard({
	application,
	tab,
}: {
	application: JobApplication
	tab: JobApplicationTab
}) {
	const skip = useSkipApplication()
	const markApplied = useMarkApplied()
	const { posting } = application
	// Applied to on the site by the user: kept under "Self Apply", done.
	const selfApplied = tab === "manual" && !!application.appliedAt
	// An "apply yourself" match opens to fill in the employer's form.
	const opens = tab === "manual" && !!posting.url && !selfApplied
	const [open, setOpen] = useState(false)
	const [detailsOpen, setDetailsOpen] = useState(false)

	function setApplied(applied: boolean) {
		markApplied
			.mutateAsync({ id: application.id, applied })
			.then((data) => toast.success(data.message))
			.catch((error) =>
				toast.error(errorMessage(error, "Unable to update this job."))
			)
	}

	return (
		<li>
			<GlassCard
				className={cn(
					"space-y-3 p-4 sm:p-5",
					// Still to apply to stands out; done fades back.
					tab === "manual" &&
						!selfApplied &&
						"border-primary/60 ring-1 ring-primary/30 dark:border-primary/60",
					selfApplied && "opacity-60 transition-opacity hover:opacity-100"
				)}>
				<div className="flex flex-wrap items-start justify-between gap-2">
					<button
						type="button"
						onClick={() => setDetailsOpen(true)}
						className="group min-w-0 cursor-pointer text-left">
						<div className="font-medium group-hover:underline">
							{posting.title}
						</div>
						<div className="text-sm text-muted-foreground">
							{[posting.company, posting.location].filter(Boolean).join(" · ")}
						</div>
						{posting.salary && (
							<div
								className="mt-1 inline-flex items-center gap-1 text-sm text-muted-foreground"
								title={
									posting.salary.stated
										? "As stated in the posting"
										: "An estimate, not from the employer"
								}>
								<Wallet className="size-3.5" />
								{formatSalary(posting.salary, true)}
							</div>
						)}
					</button>
					<JobDetailsSheet
						application={application}
						open={detailsOpen}
						onOpenChange={setDetailsOpen}
					/>
					<div className="flex items-center gap-2">
						{application.status === "replied" && <Badge>Replied</Badge>}
						{application.status === "failed" && (
							<Badge variant="destructive">Bounced</Badge>
						)}
						<ScoreBadge score={application.matchScore} />
					</div>
				</div>

				{application.matchReason && (
					<p className="text-sm text-muted-foreground">
						{application.matchReason}
					</p>
				)}

				{tab === "review" && <DraftEditor application={application} />}

				{tab === "manual" && posting.applyInstructions && (
					<p className="text-sm whitespace-pre-line">
						<span className="font-medium">How to apply: </span>
						{posting.applyInstructions}
					</p>
				)}

				{(tab === "review" || tab === "queued" || tab === "manual") &&
					!selfApplied && <TailoredCv application={application} />}

				{opens && open && <ApplicationFormPanel application={application} />}

				{opens && !open && application.form?.status === "ready" && (
					<button
						type="button"
						onClick={() => setOpen(true)}
						className="cursor-pointer text-sm text-muted-foreground hover:text-foreground">
						Answers ready to review
						{application.form.minutesLeft > 0 &&
							` · about ${application.form.minutesLeft} min left`}
					</button>
				)}

				<div className="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
					{/* Applying on the site is the only way to act on these. A
				    shared poster may have no link at all. */}
					{posting.url && tab === "manual" && (
						<Link
							href={posting.url}
							target="_blank"
							rel="noopener noreferrer"
							variant="outline"
							size="sm"
							className="normal-case">
							Apply on {posting.sourceName}
							<ExternalLink />
						</Link>
					)}
					{posting.url && tab !== "manual" && (
						<a
							href={posting.url}
							target="_blank"
							rel="noopener noreferrer"
							className="inline-flex items-center gap-1 text-muted-foreground hover:text-foreground">
							<ExternalLink className="size-3.5" />
							View on {posting.sourceName}
						</a>
					)}
					{application.mailThreadId && (
						<Link
							variant="unstyled"
							href={`/mail/${application.mailThreadId}/show`}
							className="inline-flex items-center gap-1 text-muted-foreground hover:text-foreground">
							<MailOpen className="size-3.5" />
							Open conversation
						</Link>
					)}
					{application.appliedAt && (
						<span className="text-muted-foreground">
							{selfApplied ? "Applied" : "Sent"}{" "}
							{format(new Date(application.appliedAt), "d MMM yyyy, HH:mm")}
						</span>
					)}
					{tab !== "sent" && tab !== "review" && (
						<span className="text-muted-foreground">
							Matched{" "}
							{formatDistanceToNow(new Date(application.createdAt), {
								addSuffix: true,
							})}
						</span>
					)}
				</div>

				{/* What to do with it, always last and on the right: letting it
			    go, then applying. */}
				{(tab === "queued" || tab === "manual") && (
					<div className="flex flex-wrap items-center justify-end gap-2">
						{selfApplied ? (
							<Button
								type="button"
								variant="ghost"
								size="sm"
								disabled={markApplied.isPending}
								onClick={() => setApplied(false)}>
								Not applied yet
							</Button>
						) : (
							<Button
								type="button"
								variant="secondary"
								size="sm"
								disabled={skip.isPending}
								onClick={() =>
									skip
										.mutateAsync(application.id)
										.then((data) => toast.success(data.message))
										.catch((error) =>
											toast.error(
												errorMessage(error, "Unable to skip this application.")
											)
										)
								}>
								{tab === "manual" ? "Dismiss" : "Don't send"}
							</Button>
						)}
						{tab === "manual" && !selfApplied && (
							<div className="flex flex-wrap items-center gap-2">
								<Button
									type="button"
									variant="secondary"
									size="sm"
									disabled={markApplied.isPending}
									onClick={() => setApplied(true)}>
									<CircleCheck />
									I've applied
								</Button>
								{opens && (
									<Button
										type="button"
										size="sm"
										aria-expanded={open}
										onClick={() => setOpen(!open)}>
										<ChevronDown
											className={cn(
												"transition-transform",
												open && "rotate-180"
											)}
										/>
										{open ? "Hide the form" : "Apply for me"}
									</Button>
								)}
							</div>
						)}
					</div>
				)}
			</GlassCard>
		</li>
	)
}

/**
 * Applications found by job search: drafts to review, what's queued,
 * what's been sent (replies arrive in the inbox), and matches to apply to
 * by hand. Each tab has its own URL, linked from the sidebar and bottom nav.
 */
export default function JobApplications({
	tab = "review",
}: {
	tab?: JobApplicationTab
}) {
	const [page, setPage] = useState(1)
	const { data: settings } = useJobSearch()
	const { data, isLoading, isFetching } = useJobApplications(tab, page)
	const applications = data?.data ?? []
	const lastPage = data?.meta.last_page ?? 1
	const current =
		JOB_APPLICATION_TABS.find((item) => item.key === tab) ??
		JOB_APPLICATION_TABS[0]

	// Until job search is on there's nothing to show but the way to set it up,
	// like mail without a mailbox.
	if (settings && !settings.profile?.autoApply) {
		return (
			<>
				<Head title="Jobs" />

				<div className="flex min-h-[calc(100vh-4rem)] flex-1 flex-col items-center justify-center gap-4 text-center">
					<Briefcase className="size-10 text-muted-foreground" />
					<h1 className="text-xl font-semibold">
						{settings.profile?.pausedReason
							? "Job search is paused"
							: "Let jobs find you"}
					</h1>
					<p className="max-w-md text-muted-foreground">
						{settings.profile?.pausedReason ??
							"Upload your CV and we'll find jobs that match it on Kenyan job boards and careers pages, then apply by email for you."}
					</p>
					<Link
						href={
							settings.profile?.pausedReason
								? "/settings/job-search"
								: `${JOB_APPLICATIONS_URL}/setup`
						}
						variant="solid">
						{settings.profile?.pausedReason
							? "Turn job search back on"
							: settings.profile?.setupStep
								? "Continue setup"
								: "Set up job search"}
					</Link>
				</div>
			</>
		)
	}

	return (
		<>
			<Head title="Jobs" />

			<div className="mx-auto w-full max-w-3xl space-y-6">
				<div className="flex flex-wrap items-start justify-between gap-4">
					<Heading
						title="Jobs"
						description="Jobs matched to your CV, and the applications sent from your mailbox."
					/>
					<div className="flex flex-wrap gap-2">
						<Link
							href={CV_REVAMP_URL}
							variant="outline"
							size="sm">
							<Sparkles />
							Revamp CV
						</Link>
						<Link
							href="/settings/job-search"
							variant="outline"
							size="sm">
							Settings
						</Link>
					</div>
				</div>

				{settings?.profile?.autoApply && !settings.profile.lastAlertAt && (
					<GlassCard className="flex flex-wrap items-center gap-3 p-4 text-sm">
						<BellRing className="size-5 shrink-0 text-muted-foreground" />
						<span className="min-w-0 flex-1">
							Get more matches: turn on job alerts on LinkedIn, Fuzu and other
							sites with your Black Mail address, and the jobs in them are added
							here.
						</span>
						<Link
							href="/settings/job-search"
							variant="outline"
							size="sm">
							Show me how
						</Link>
					</GlassCard>
				)}

				{isLoading || !settings ? (
					<GlassCard className="space-y-3 p-5">
						<Skeleton className="h-5 w-1/2" />
						<Skeleton className="h-4 w-1/3" />
						<Skeleton className="h-24 w-full" />
					</GlassCard>
				) : applications.length === 0 ? (
					<GlassCard className="p-8 text-center text-sm text-muted-foreground">
						{current.empty}
					</GlassCard>
				) : (
					<ul className={cn("space-y-3", isFetching && "opacity-70")}>
						{applications.map((application) => (
							<ApplicationCard
								key={application.id}
								application={application}
								tab={tab}
							/>
						))}
					</ul>
				)}

				{lastPage > 1 && (
					<div className="flex items-center justify-between text-sm">
						<Button
							variant="secondary"
							size="sm"
							disabled={page <= 1}
							onClick={() => setPage(page - 1)}>
							Previous
						</Button>
						<span className="text-muted-foreground">
							Page {page} of {lastPage}
						</span>
						<Button
							variant="secondary"
							size="sm"
							disabled={page >= lastPage}
							onClick={() => setPage(page + 1)}>
							Next
						</Button>
					</div>
				)}
			</div>

			{settings && (
				<Link
					href={`${JOB_APPLICATIONS_URL}/add`}
					variant="solid"
					size="icon"
					aria-label="Add a job"
					title="Add a job"
					className="fixed right-6 bottom-26 z-50 size-14 rounded-full shadow-lg md:bottom-6">
					<Plus
						className="size-6"
						strokeWidth={1.5}
					/>
				</Link>
			)}
		</>
	)
}

JobApplications.layout = {
	breadcrumbs: [{ title: "Jobs", href: JOB_APPLICATIONS_URL }],
}
