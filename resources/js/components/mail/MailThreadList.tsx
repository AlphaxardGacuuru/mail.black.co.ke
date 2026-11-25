import { MailPlus } from "lucide-react"
import MailEmptyState from "@/components/mail/MailEmptyState"
import MailRealtimeBanner from "@/components/mail/MailRealtimeBanner"
import MailSearchBar from "@/components/mail/MailSearchBar"
import MailThreadListRow from "@/components/mail/MailThreadListRow"
import ReadStatusFilter from "@/components/read-status-filter"
import { Button } from "@/components/ui/button"
import { Skeleton } from "@/components/ui/skeleton"
import { useJobSearchOnly } from "@/hooks/use-job-search-only"
import { useMailThreads } from "@/queries/mail"
import type { MailThreadFilters } from "@/queries/mail"

type Props = {
	filters: MailThreadFilters
	onFiltersChange: (filters: MailThreadFilters) => void
	selectedThreadId: string | null
	highlightThreadId?: string | null
	showIncomingBanner?: boolean
	onSelectThread: (threadId: string) => void
	onCompose: () => void
}

export default function MailThreadList({
	filters,
	onFiltersChange,
	selectedThreadId,
	highlightThreadId,
	showIncomingBanner,
	onSelectThread,
	onCompose,
}: Props) {
	const {
		data: threads = [],
		isLoading,
		hasNextPage,
		isFetchingNextPage,
		fetchNextPage,
	} = useMailThreads(filters)
	const jobSearchOnly = useJobSearchOnly()

	return (
		<div className="relative flex h-full flex-col">
			<MailRealtimeBanner visible={!!showIncomingBanner} />

			<MailSearchBar
				value={filters.q ?? ""}
				onChange={(q) => onFiltersChange({ ...filters, q })}
			/>

			<ReadStatusFilter
				value={filters.status}
				onChange={(status) => onFiltersChange({ ...filters, status })}
			/>

			<div className="flex-1 overflow-y-auto lg:p-3">
				{isLoading && (
					<div className="space-y-3">
						<Skeleton className="h-12 w-full" />
						<Skeleton className="h-12 w-full" />
						<Skeleton className="h-12 w-full" />
					</div>
				)}

				{!isLoading && threads.length === 0 && (
					<MailEmptyState
						variant={
							filters.q
								? "search-no-results"
								: filters.status
									? "filter-no-results"
									: "no-threads"
						}
					/>
				)}

				{!isLoading && threads.length > 0 && (
					<div className="space-y-2">
						{threads.map((thread) => (
							<MailThreadListRow
								key={thread.id}
								thread={thread}
								folder={filters.folder}
								isSelected={thread.id === selectedThreadId}
								isIncoming={thread.id === highlightThreadId}
								onSelect={() => onSelectThread(thread.id)}
							/>
						))}

						{hasNextPage && (
							<Button
								variant="outline"
								size="sm"
								className="w-full"
								disabled={isFetchingNextPage}
								onClick={() => fetchNextPage()}>
								{isFetchingNextPage ? "Loading..." : "Load More"}
							</Button>
						)}
					</div>
				)}
			</div>

			{!jobSearchOnly && (
				<Button
					size="icon"
					aria-label="Compose"
					title="Compose"
					className="fixed right-6 bottom-26 z-50 size-14 rounded-full shadow-lg md:bottom-6"
					onClick={onCompose}>
					<MailPlus
						className="size-6"
						strokeWidth={1.5}
					/>
				</Button>
			)}
		</div>
	)
}
