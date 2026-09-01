import type { ColumnDef } from "@tanstack/react-table"
import { Search } from "lucide-react"
import { useEffect, useState } from "react"
import { Head } from "@/lib/spa"
import Heading from "@/components/heading"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { DataTable } from "@/components/ui/data-table"
import {
	Dialog,
	DialogContent,
	DialogHeader,
	DialogTitle,
} from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import {
	SelectField,
	SelectItem,
} from "@/components/ui/select"
import type { AdminWebhookEvent } from "@/queries/admin"
import { useAdminWebhooks } from "@/queries/admin"

const EVENT_OPTIONS = [
	"accepted",
	"delivered",
	"opened",
	"clicked",
	"failed",
	"complained",
	"unsubscribed",
	"permanent_failure",
	"temporary_failure",
]

const STATUS_OPTIONS = [
	"queued",
	"sent",
	"delivered",
	"opened",
	"clicked",
	"failed",
	"bounced",
	"temporary_failed",
	"permanent_failed",
	"complained",
	"unsubscribed",
	"received",
]

const FAILURE_STATUSES = new Set([
	"failed",
	"bounced",
	"temporary_failed",
	"permanent_failed",
	"complained",
])

const SUCCESS_STATUSES = new Set(["sent", "delivered", "opened", "clicked"])

function statusTone(
	status: string | null
): "default" | "destructive" | "secondary" {
	if (!status) {
		return "secondary"
	}
	if (FAILURE_STATUSES.has(status)) {
		return "destructive"
	}
	if (SUCCESS_STATUSES.has(status)) {
		return "default"
	}
	return "secondary"
}

export default function AdminWebhooks() {
	const [q, setQ] = useState("")
	const [debouncedQ, setDebouncedQ] = useState("")
	const [event, setEvent] = useState<string>("")
	const [status, setStatus] = useState<string>("")
	const [from, setFrom] = useState("")
	const [to, setTo] = useState("")
	const [page, setPage] = useState(1)
	const [perPage, setPerPage] = useState(20)
	const [selectedPayload, setSelectedPayload] =
		useState<AdminWebhookEvent | null>(null)

	useEffect(() => {
		const timer = setTimeout(() => setDebouncedQ(q), 300)
		return () => clearTimeout(timer)
	}, [q])

	useEffect(() => {
		setPage(1)
	}, [debouncedQ, event, status, from, to])

	const { data, isLoading } = useAdminWebhooks({
		q: debouncedQ,
		event,
		status,
		from,
		to,
		page,
		perPage,
	})

	const hasFilters = q || event || status || from || to

	function clearFilters() {
		setQ("")
		setEvent("")
		setStatus("")
		setFrom("")
		setTo("")
	}

	const columns: ColumnDef<AdminWebhookEvent>[] = [
		{
			accessorKey: "event",
			header: "Event",
			cell: ({ row }) => (
				<Badge variant="secondary">{row.original.event}</Badge>
			),
		},
		{
			accessorKey: "status",
			header: "Status",
			cell: ({ row }) =>
				row.original.status ? (
					<Badge variant={statusTone(row.original.status)}>
						{row.original.status}
					</Badge>
				) : (
					<span className="text-muted-foreground">—</span>
				),
		},
		{
			id: "message",
			header: "Message",
			cell: ({ row }) => (
				<span className="block max-w-64 truncate">
					{row.original.mailMessageSubject || "—"}
				</span>
			),
		},
		{
			accessorKey: "providerEventId",
			header: "Provider event ID",
			cell: ({ row }) => (
				<span className="block max-w-48 truncate font-mono text-xs text-muted-foreground">
					{row.original.providerEventId}
				</span>
			),
		},
		{
			accessorKey: "occurredAt",
			header: "Occurred at",
			cell: ({ row }) => (
				<span className="whitespace-nowrap text-sm">
					{row.original.occurredAt
						? new Date(row.original.occurredAt).toLocaleString()
						: new Date(row.original.createdAt).toLocaleString()}
				</span>
			),
		},
		{
			id: "payload",
			header: "Payload",
			enableSorting: false,
			cell: ({ row }) => (
				<Button
					variant="outline"
					size="sm"
					onClick={() => setSelectedPayload(row.original)}>
					View
				</Button>
			),
		},
	]

	return (
		<>
			<Head title="Admin webhooks" />

			<div className="space-y-4">
				<Heading
					variant="small"
					title="Webhooks"
					description="Every Mailgun event received by the app"
				/>

				<div className="flex flex-col gap-3 rounded-lg border p-3 sm:flex-row sm:flex-wrap sm:items-end">
					<div className="flex min-w-0 items-center gap-2 px-3 sm:min-w-56 sm:flex-1">
						<Search className="size-4 shrink-0 text-muted-foreground" />
						<Input
							type="text"
							label="Search"
							value={q}
							onChange={(e) => setQ(e.target.value)}
						/>
					</div>

					<SelectField
						label="Event"
						value={event || "all"}
						onValueChange={(v) => setEvent(v === "all" ? "" : v)}
						containerClassName="w-full sm:w-52"
						triggerClassName="w-full">
						<SelectItem value="all">All events</SelectItem>
						{EVENT_OPTIONS.map((option) => (
							<SelectItem
								key={option}
								value={option}>
								{option}
							</SelectItem>
						))}
					</SelectField>

					<SelectField
						label="Status"
						value={status || "all"}
						onValueChange={(v) => setStatus(v === "all" ? "" : v)}
						containerClassName="w-full sm:w-44"
						triggerClassName="w-full">
						<SelectItem value="all">All statuses</SelectItem>
						{STATUS_OPTIONS.map((option) => (
							<SelectItem
								key={option}
								value={option}>
								{option}
							</SelectItem>
						))}
					</SelectField>

					<div className="flex flex-col gap-2 sm:flex-row sm:items-center">
						<Input
							type="date"
							label="From"
							value={from}
							onChange={(e) => setFrom(e.target.value)}
						/>
						<Input
							type="date"
							label="To"
							value={to}
							onChange={(e) => setTo(e.target.value)}
						/>
					</div>

					{hasFilters && (
						<Button
							variant="ghost"
							size="sm"
							onClick={clearFilters}>
							Clear filters
						</Button>
					)}
				</div>

				<DataTable
					columns={columns}
					data={data?.data ?? []}
					emptyMessage={isLoading ? "Loading…" : "No webhooks found"}
					pagination={
						data
							? {
									currentPage: data.meta.current_page,
									lastPage: data.meta.last_page,
									total: data.meta.total,
									pageSize: perPage,
									onPageChange: setPage,
									onPageSizeChange: (size) => {
										setPerPage(size)
										setPage(1)
									},
								}
							: undefined
					}
				/>
			</div>

			<Dialog
				open={!!selectedPayload}
				onOpenChange={(open) => !open && setSelectedPayload(null)}>
				<DialogContent className="max-h-[80vh] max-w-2xl overflow-y-auto">
					<DialogHeader>
						<DialogTitle>
							{selectedPayload?.event} — {selectedPayload?.providerEventId}
						</DialogTitle>
					</DialogHeader>
					<pre className="overflow-x-auto rounded-md bg-muted p-4 text-xs">
						{JSON.stringify(selectedPayload?.payload, null, 2)}
					</pre>
				</DialogContent>
			</Dialog>
		</>
	)
}
