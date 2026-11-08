import { FileText, LoaderCircle, RotateCcw, Sparkles } from "lucide-react"
import Heading from "@/components/heading"
import { CvRevampReview } from "@/components/job-search/cv-revamp-review"
import { Button } from "@/components/ui/button"
import { GlassCard } from "@/components/ui/glass-card"
import { Link } from "@/components/ui/link"
import { Skeleton } from "@/components/ui/skeleton"
import { Head } from "@/lib/spa"
import toast from "@/lib/toast"
import { useCvRevamp, useStartCvRevamp } from "@/queries/cv-revamp"
import { errorMessage } from "@/queries/job-search"

/**
 * Revamping the user's CV with the AI: rewritten for the roles they're
 * going for, reviewed section by section, then sent to employers instead
 * of the one they uploaded.
 */
export default function CvRevampPage() {
	const { data: state, isLoading } = useCvRevamp()
	const start = useStartCvRevamp()
	const revamp = state?.revamp

	const begin = () =>
		start
			.mutateAsync(undefined)
			.then((data) => toast.success(data.message))
			.catch((error) =>
				toast.error(errorMessage(error, "Unable to revamp your CV."))
			)

	return (
		<>
			<Head title="Revamp your CV" />

			<div className="mx-auto w-full max-w-4xl space-y-6">
				<div className="flex flex-wrap items-start justify-between gap-4">
					<Heading
						title="Revamp your CV"
						description="Rewritten for the roles you're going for, so it gets more interviews. Nothing is made up: where a number would help, you're asked for it."
					/>
					<Link
						href="/settings/job-search"
						variant="outline"
						size="sm">
						Settings
					</Link>
				</div>

				{isLoading || !state ? (
					<Skeleton className="h-40 w-full rounded-xl" />
				) : !state.canRevamp ? (
					<GlassCard className="flex flex-wrap items-center gap-3 p-4 text-sm">
						<FileText className="size-5 shrink-0 text-muted-foreground" />
						<span className="min-w-0 flex-1">
							Upload your CV first. Once it's been read, it can be revamped.
						</span>
						<Link
							href="/settings/job-search"
							variant="outline"
							size="sm">
							Upload CV
						</Link>
					</GlassCard>
				) : !revamp ? (
					<GlassCard className="flex flex-wrap items-center gap-3 p-4 text-sm">
						<Sparkles className="size-5 shrink-0 text-primary" />
						<span className="min-w-0 flex-1">
							Revamp {state.cvName ?? "your CV"}: stronger bullet points, the
							words employers search for and a clean layout recruitment software
							reads. You review every change before it's used.
						</span>
						<Button
							type="button"
							size="sm"
							disabled={start.isPending}
							onClick={begin}>
							{start.isPending && <LoaderCircle className="animate-spin" />}
							Revamp my CV
						</Button>
					</GlassCard>
				) : revamp.status === "drafting" ? (
					<GlassCard className="flex items-center gap-3 p-4 text-sm text-muted-foreground">
						<LoaderCircle className="size-5 shrink-0 animate-spin" />
						Rewriting your CV. This takes about a minute.
					</GlassCard>
				) : revamp.status === "failed" ? (
					<GlassCard className="flex flex-wrap items-center gap-3 p-4 text-sm">
						<span className="min-w-0 flex-1">{revamp.error}</span>
						<Button
							type="button"
							variant="secondary"
							size="sm"
							disabled={start.isPending}
							onClick={begin}>
							<RotateCcw />
							Try again
						</Button>
					</GlassCard>
				) : (
					<CvRevampReview
						state={state}
						revamp={revamp}
					/>
				)}
			</div>
		</>
	)
}
