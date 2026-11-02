import { format, formatDistanceToNow } from "date-fns"
import {
	BellRing,
	Briefcase,
	ExternalLink,
	LoaderCircle,
	MailOpen,
} from "lucide-react"
import { useEffect, useState } from "react"
import Heading from "@/components/heading"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { GlassCard } from "@/components/ui/glass-card"
import { Input } from "@/components/ui/input"
import { Link } from "@/components/ui/link"
import { Skeleton } from "@/components/ui/skeleton"
import { Head } from "@/lib/spa"
import toast from "@/lib/toast"
import { cn } from "@/lib/utils"
import {
	errorMessage,
	useApproveApplication,
	useJobApplications,
	useJobSearch,
	useSaveDraft,
	useSkipApplication,
} from "@/queries/job-search"
import type { JobApplication, JobApplicationTab } from "@/types"

const TABS: { key: JobApplicationTab; label: string; empty: string }[] = [
	{
		key: "review",
		label: "To review",
		empty: "Nothing to review. New matches appear here.",
	},
	{ key: "queued", label: "Queued", empty: "Nothing waiting to be sent." },
	{ key: "sent", label: "Sent", empty: "No applications sent yet." },
	{
		key: "manual",
		label: "Apply yourself",
		empty: "Matches without an apply email appear here.",
	},
	{ key: "closed", label: "Skipped", empty: "Nothing skipped or bounced." },
]

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
					variant="ghost"
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
						variant="outline"
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

function ApplicationCard({
	application,
	tab,
}: {
	application: JobApplication
	tab: JobApplicationTab
}) {
	const skip = useSkipApplication()
	const { posting } = application

	return (
		<li className="space-y-3 p-4 sm:p-5">
			<div className="flex flex-wrap items-start justify-between gap-2">
				<div className="min-w-0">
					<div className="font-medium">{posting.title}</div>
					<div className="text-sm text-muted-foreground">
						{[posting.company, posting.location].filter(Boolean).join(" · ")}
					</div>
				</div>
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

			<div className="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
				<a
					href={posting.url}
					target="_blank"
					rel="noopener noreferrer"
					className="inline-flex items-center gap-1 text-muted-foreground hover:text-foreground">
					<ExternalLink className="size-3.5" />
					View on {posting.sourceName}
				</a>
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
						Sent {format(new Date(application.appliedAt), "d MMM yyyy, HH:mm")}
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
				{(tab === "queued" || tab === "manual") && (
					<Button
						type="button"
						variant="ghost"
						size="sm"
						className="ml-auto"
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
			</div>
		</li>
	)
}

/**
 * Applications found by job search: drafts to review, what's queued,
 * what's been sent (replies arrive in the inbox), and matches to apply to
 * by hand.
 */
export default function JobApplications() {
	const [tab, setTab] = useState<JobApplicationTab>("review")
	const [page, setPage] = useState(1)
	const { data: settings } = useJobSearch()
	const { data, isLoading, isFetching } = useJobApplications(tab, page)
	const applications = data?.data ?? []
	const lastPage = data?.meta.last_page ?? 1
	const current = TABS.find((item) => item.key === tab) ?? TABS[0]

	return (
		<>
			<Head title="Job applications" />

			<div className="mx-auto w-full max-w-3xl space-y-6">
				<div className="flex flex-wrap items-start justify-between gap-4">
					<Heading
						title="Job applications"
						description="Jobs matched to your CV, and the applications sent from your mailbox."
					/>
					<Link
						href="/settings/job-search"
						variant="outline"
						size="sm">
						Settings
					</Link>
				</div>

				{settings && !settings.profile?.autoApply && (
					<GlassCard className="flex flex-wrap items-center gap-3 p-4 text-sm">
						<Briefcase className="size-5 shrink-0 text-muted-foreground" />
						<span className="min-w-0 flex-1">
							{settings.profile?.pausedReason
								? `Job search is paused: ${settings.profile.pausedReason}`
								: "Job search is off. Turn it on to start finding jobs."}
						</span>
						<Link
							href="/settings/job-search"
							variant="solid"
							size="sm">
							Set up
						</Link>
					</GlassCard>
				)}

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

				<div
					role="tablist"
					aria-label="Application status"
					className="flex gap-1 overflow-x-auto pb-1">
					{TABS.map((item) => {
						const count = data?.counts[item.key] ?? 0

						return (
							<button
								key={item.key}
								type="button"
								role="tab"
								aria-selected={tab === item.key}
								onClick={() => {
									setTab(item.key)
									setPage(1)
								}}
								className={cn(
									"shrink-0 rounded-full px-3 py-1.5 text-sm transition-colors",
									tab === item.key
										? "bg-primary text-primary-foreground"
										: "hover:bg-muted"
								)}>
								{item.label}
								{count > 0 && (
									<span className="ml-1.5 opacity-80">{count}</span>
								)}
							</button>
						)
					})}
				</div>

				<GlassCard
					className={cn(
						"overflow-hidden",
						isFetching && !isLoading && "opacity-70"
					)}>
					{isLoading ? (
						<div className="space-y-3 p-5">
							<Skeleton className="h-5 w-1/2" />
							<Skeleton className="h-4 w-1/3" />
							<Skeleton className="h-24 w-full" />
						</div>
					) : applications.length === 0 ? (
						<p className="p-8 text-center text-sm text-muted-foreground">
							{current.empty}
						</p>
					) : (
						<ul className="divide-y divide-border/60">
							{applications.map((application) => (
								<ApplicationCard
									key={application.id}
									application={application}
									tab={tab}
								/>
							))}
						</ul>
					)}
				</GlassCard>

				{lastPage > 1 && (
					<div className="flex items-center justify-between text-sm">
						<Button
							variant="outline"
							size="sm"
							disabled={page <= 1}
							onClick={() => setPage(page - 1)}>
							Previous
						</Button>
						<span className="text-muted-foreground">
							Page {page} of {lastPage}
						</span>
						<Button
							variant="outline"
							size="sm"
							disabled={page >= lastPage}
							onClick={() => setPage(page + 1)}>
							Next
						</Button>
					</div>
				)}
			</div>
		</>
	)
}

JobApplications.layout = {
	breadcrumbs: [{ title: "Job applications", href: "/job-applications" }],
}
