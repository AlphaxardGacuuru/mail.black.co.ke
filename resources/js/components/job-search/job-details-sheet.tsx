import { useQueryClient } from "@tanstack/react-query"
import { format } from "date-fns"
import { ExternalLink, LoaderCircle, Wallet } from "lucide-react"
import { useEffect } from "react"
import {
	Sheet,
	SheetContent,
	SheetDescription,
	SheetHeader,
	SheetTitle,
} from "@/components/ui/sheet"
import { Skeleton } from "@/components/ui/skeleton"
import { formatSalary } from "@/lib/salary"
import { useJobDetails } from "@/queries/job-search"
import type { JobApplication, JobDetails } from "@/types"

function Salary({ details }: { details: JobDetails | undefined }) {
	if (!details) {
		return <Skeleton className="h-5 w-48" />
	}

	if (details.salaryStatus === "estimating") {
		return (
			<p className="flex items-center gap-2 text-sm text-muted-foreground">
				<LoaderCircle className="size-4 animate-spin" />
				Estimating the pay…
			</p>
		)
	}

	if (!details.salary) {
		return (
			<p className="text-sm text-muted-foreground">
				The posting doesn't say what it pays.
			</p>
		)
	}

	return (
		<div className="space-y-0.5">
			<p className="font-medium">{formatSalary(details.salary)}</p>
			<p className="text-xs text-muted-foreground">
				{details.salary.stated
					? "As stated in the posting."
					: `An estimate, not from the employer. ${details.salary.basis ?? ""}`}
			</p>
		</div>
	)
}

/**
 * The whole job: what it pays, why it matched, and the posting in full.
 */
export function JobDetailsSheet({
	application,
	open,
	onOpenChange,
}: {
	application: JobApplication
	open: boolean
	onOpenChange: (open: boolean) => void
}) {
	const queryClient = useQueryClient()
	const { data: details, isLoading } = useJobDetails(application.id, open)
	const { posting } = application
	const facts = [
		posting.postedAt && `Posted ${format(new Date(posting.postedAt), "d MMM yyyy")}`,
		details?.closesAt && `Closes ${format(new Date(details.closesAt), "d MMM yyyy")}`,
		details?.industry,
	].filter(Boolean)

	// Priced just now: refresh the lists so the card shows the pay too.
	useEffect(() => {
		if (details?.salary && !posting.salary) {
			queryClient.invalidateQueries({
				queryKey: ["job-applications"],
				predicate: (query) => query.queryKey[1] !== "details",
			})
		}
	}, [details?.salary, posting.salary, queryClient])

	return (
		<Sheet
			open={open}
			onOpenChange={onOpenChange}>
			<SheetContent className="w-full gap-0 overflow-y-auto sm:max-w-xl">
				<SheetHeader className="border-b pr-10">
					<SheetTitle className="text-lg">{posting.title}</SheetTitle>
					<SheetDescription>
						{[posting.company, posting.location].filter(Boolean).join(" · ")}
					</SheetDescription>
				</SheetHeader>

				<div className="space-y-6 p-4">
					<section className="flex gap-3 rounded-lg border border-border/60 p-4">
						<Wallet className="mt-0.5 size-5 shrink-0 text-muted-foreground" />
						<Salary details={details} />
					</section>

					{(application.matchScore !== null || application.matchReason) && (
						<section className="space-y-1">
							<h3 className="text-sm font-medium">
								{application.matchScore !== null
									? `${application.matchScore}% match`
									: "Why it matched"}
							</h3>
							{application.matchReason && (
								<p className="text-sm text-muted-foreground">
									{application.matchReason}
								</p>
							)}
						</section>
					)}

					{facts.length > 0 && (
						<p className="text-sm text-muted-foreground">{facts.join(" · ")}</p>
					)}

					<section className="space-y-2">
						<h3 className="text-sm font-medium">About the job</h3>
						{isLoading ? (
							<div className="space-y-2">
								<Skeleton className="h-4 w-full" />
								<Skeleton className="h-4 w-full" />
								<Skeleton className="h-4 w-2/3" />
							</div>
						) : (
							<p className="text-sm leading-relaxed whitespace-pre-line">
								{details?.description ||
									"The posting has no description. See it on the site."}
							</p>
						)}
					</section>

					{posting.applyInstructions && (
						<section className="space-y-2">
							<h3 className="text-sm font-medium">How to apply</h3>
							<p className="text-sm whitespace-pre-line">
								{posting.applyInstructions}
							</p>
						</section>
					)}

					{posting.url && (
						<a
							href={posting.url}
							target="_blank"
							rel="noopener noreferrer"
							className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
							<ExternalLink className="size-3.5" />
							View on {posting.sourceName}
						</a>
					)}
				</div>
			</SheetContent>
		</Sheet>
	)
}
