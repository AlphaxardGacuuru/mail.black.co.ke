import { Pencil } from "lucide-react"
import MailEmptyState from "@/components/mail/MailEmptyState"
import MailSearchBar from "@/components/mail/MailSearchBar"
import MailThreadListRow from "@/components/mail/MailThreadListRow"
import { Button } from "@/components/ui/button"
import { Skeleton } from "@/components/ui/skeleton"
import { useMailThreads } from "@/queries/mail"
import type { MailThreadFilters } from "@/queries/mail"

type Props = {
	filters: MailThreadFilters
	onFiltersChange: (filters: MailThreadFilters) => void
	selectedThreadId: string | null
	onSelectThread: (threadId: string) => void
	onCompose: () => void
	title: string
}

export default function MailThreadList({
	filters,
	onFiltersChange,
	selectedThreadId,
	onSelectThread,
	onCompose,
	title,
}: Props) {
	const { data, isLoading } = useMailThreads(filters)

	const threads = data?.data ?? []
	const meta = data?.meta

	return (
		<div className="flex h-full flex-col">
			<div className="flex items-center gap-2 border-b px-3 py-2">
				<h1 className="flex-1 font-semibold capitalize">{title}</h1>

				<Button
					size="sm"
					className="gap-2"
					onClick={onCompose}>
					<Pencil className="size-4" />
					Compose
				</Button>
			</div>

			<MailSearchBar
				value={filters.q ?? ""}
				onChange={(q) => onFiltersChange({ ...filters, q, page: 1 })}
			/>

			<div className="flex-1 overflow-y-auto">
				{isLoading && (
					<div className="space-y-3 p-3">
						<Skeleton className="h-12 w-full" />
						<Skeleton className="h-12 w-full" />
						<Skeleton className="h-12 w-full" />
					</div>
				)}

				{!isLoading && threads.length === 0 && (
					<MailEmptyState variant={filters.q ? "search-no-results" : "no-threads"} />
				)}

				{!isLoading &&
					threads.map((thread) => (
						<MailThreadListRow
							key={thread.id}
							thread={thread}
							isSelected={thread.id === selectedThreadId}
							onSelect={() => onSelectThread(thread.id)}
						/>
					))}
			</div>

			{meta && meta.last_page > 1 && (
				<div className="flex items-center justify-between border-t p-2">
					<Button
						variant="outline"
						size="sm"
						disabled={meta.current_page <= 1}
						onClick={() => onFiltersChange({ ...filters, page: meta.current_page - 1 })}>
						Previous
					</Button>
					<span className="text-xs text-muted-foreground">
						Page {meta.current_page} of {meta.last_page}
					</span>
					<Button
						variant="outline"
						size="sm"
						disabled={meta.current_page >= meta.last_page}
						onClick={() => onFiltersChange({ ...filters, page: meta.current_page + 1 })}>
						Next
					</Button>
				</div>
			)}
		</div>
	)
}
