import type { Dispatch, SetStateAction } from "react"
import { useState } from "react"
import type {
	ColumnDef,
	ColumnFiltersState,
	OnChangeFn,
	RowSelectionState,
	SortingState,
	VisibilityState,
} from "@tanstack/react-table"
import {
	flexRender,
	getCoreRowModel,
	getFilteredRowModel,
	getPaginationRowModel,
	getSortedRowModel,
	useReactTable,
} from "@tanstack/react-table"
import {
	ArrowDown,
	ArrowUp,
	ChevronDown,
	ChevronLeft,
	ChevronRight,
	ChevronsUpDown,
	Eye,
	Pencil,
	Trash2,
} from "lucide-react"

import { cn } from "@/lib/utils"
import { Button } from "@/components/ui/button"
import { Checkbox } from "@/components/ui/checkbox"
import {
	Dialog,
	DialogClose,
	DialogContent,
	DialogDescription,
	DialogFooter,
	DialogTitle,
	DialogTrigger,
} from "@/components/ui/dialog"
import {
	DropdownMenu,
	DropdownMenuCheckboxItem,
	DropdownMenuContent,
	DropdownMenuRadioGroup,
	DropdownMenuRadioItem,
	DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { Link } from "@/components/ui/link"
import {
	Pagination,
	PaginationContent,
	PaginationEllipsis,
	PaginationItem,
	PaginationLink,
	PaginationNext,
	PaginationPrevious,
} from "@/components/ui/pagination"
import {
	Table,
	TableBody,
	TableCell,
	TableHead,
	TableHeader,
	TableRow,
} from "@/components/ui/table"

declare module "@tanstack/react-table" {
	interface ColumnMeta<TData, TValue> {
		className?: string
	}
}

type ServerPagination = {
	currentPage: number
	lastPage: number
	total: number
	pageSize: number
	onPageChange: (page: number) => void
	onPageSizeChange: (pageSize: number) => void
}

const PAGE_SIZE_OPTIONS = [5, 10, 20, 30, 40, 50]

function getPageNumbers(
	current: number,
	last: number
): (number | "ellipsis")[] {
	const delta = 1
	const range: number[] = []
	const rangeWithDots: (number | "ellipsis")[] = []
	let previous: number | undefined

	for (let i = 1; i <= last; i++) {
		if (
			i === 1 ||
			i === last ||
			(i >= current - delta && i <= current + delta)
		) {
			range.push(i)
		}
	}

	for (const page of range) {
		if (previous !== undefined) {
			if (page - previous === 2) {
				rangeWithDots.push(previous + 1)
			} else if (page - previous !== 1) {
				rangeWithDots.push("ellipsis")
			}
		}
		rangeWithDots.push(page)
		previous = page
	}

	return rangeWithDots
}

type SortDirection = "asc" | "desc"

type ColumnSort = {
	id: string
	direction: SortDirection
}

type DataTableProps<TData, TValue> = {
	columns: ColumnDef<TData, TValue>[]
	data: TData[]
	pagination?: ServerPagination
	rowSelection?: RowSelectionState
	setRowSelection?: Dispatch<SetStateAction<RowSelectionState>>
	emptyMessage?: string
	showHref?: (row: TData) => string
	editHref?: (row: TData) => string
	onDelete?: (row: TData) => void
	onBulkDelete?: (ids: (string | number)[]) => void
	getItemLabel?: (row: TData) => string
	onSortChange?: (sort: ColumnSort | null) => void
}

export function DataTable<TData extends { id: string | number }, TValue>({
	columns,
	data,
	pagination,
	rowSelection,
	setRowSelection,
	emptyMessage = "No results",
	showHref,
	editHref,
	onDelete,
	onBulkDelete,
	getItemLabel,
	onSortChange,
}: DataTableProps<TData, TValue>) {
	const [sorting, setSorting] = useState<SortingState>([])
	const [columnFilters, setColumnFilters] = useState<ColumnFiltersState>([])
	const [columnVisibility, setColumnVisibility] = useState<VisibilityState>({})
	const [internalRowSelection, setInternalRowSelection] =
		useState<RowSelectionState>({})

	const finalRowSelection = rowSelection ?? internalRowSelection
	const finalSetRowSelection = setRowSelection ?? setInternalRowSelection

	const handleSortingChange: OnChangeFn<SortingState> = (updater) => {
		setSorting((previous) => {
			const next = typeof updater === "function" ? updater(previous) : updater
			const primary = next[0]

			onSortChange?.(
				primary
					? { id: primary.id, direction: primary.desc ? "desc" : "asc" }
					: null
			)

			return next
		})
	}

	const selectionColumn: ColumnDef<TData, unknown> = {
		id: "select",
		header: ({ table: headerTable }) => (
			<Checkbox
				checked={
					headerTable.getIsAllPageRowsSelected() ||
					(headerTable.getIsSomePageRowsSelected() && "indeterminate")
				}
				onCheckedChange={(value) =>
					headerTable.toggleAllPageRowsSelected(!!value)
				}
				aria-label="Select all"
			/>
		),
		cell: ({ row }) => (
			<Checkbox
				checked={row.getIsSelected()}
				onCheckedChange={(value) => row.toggleSelected(!!value)}
				aria-label="Select row"
			/>
		),
		enableSorting: false,
		enableHiding: false,
		meta: { className: "w-10" },
	}

	const actionsColumn: ColumnDef<TData, unknown> | null =
		showHref || editHref || onDelete
			? {
					id: "actions",
					header: "Action",
					enableSorting: false,
					meta: { className: "text-center" },
					cell: ({ row }) => {
						const item = row.original
						const label = getItemLabel?.(item) ?? String(item.id)

						return (
							<div className="flex items-center justify-center gap-1">
								{showHref && (
									<Link
										href={showHref(item)}
										variant="outline"
										size="icon"
										aria-label={`View ${label}`}>
										<Eye className="size-4" />
									</Link>
								)}

								{editHref && (
									<Link
										href={editHref(item)}
										variant="outline"
										size="icon"
										aria-label={`Edit ${label}`}>
										<Pencil className="size-4" />
									</Link>
								)}

								{onDelete && (
									<Dialog>
										<DialogTrigger asChild>
											<Button
												variant="destructive"
												size="icon"
												aria-label={`Delete ${label}`}>
												<Trash2 className="size-4" />
											</Button>
										</DialogTrigger>
										<DialogContent>
											<DialogTitle>Delete {label}?</DialogTitle>
											<DialogDescription>
												This will permanently delete this record and cannot be
												undone.
											</DialogDescription>
											<DialogFooter className="gap-2">
												<DialogClose asChild>
													<Button variant="secondary">Cancel</Button>
												</DialogClose>
												<DialogClose asChild>
													<Button
														variant="destructive"
														onClick={() => onDelete(item)}>
														Delete
													</Button>
												</DialogClose>
											</DialogFooter>
										</DialogContent>
									</Dialog>
								)}
							</div>
						)
					},
				}
			: null

	const tableColumns = [
		selectionColumn,
		...columns,
		...(actionsColumn ? [actionsColumn] : []),
	]

	const table = useReactTable({
		data,
		columns: tableColumns,
		getRowId: (row) => String(row.id),
		pageCount: pagination?.lastPage ?? -1,
		manualPagination: !!pagination,
		manualSorting: !!onSortChange,
		enableMultiSort: false,
		onSortingChange: handleSortingChange,
		onColumnFiltersChange: setColumnFilters,
		getCoreRowModel: getCoreRowModel(),
		getPaginationRowModel: getPaginationRowModel(),
		getSortedRowModel: getSortedRowModel(),
		getFilteredRowModel: getFilteredRowModel(),
		onColumnVisibilityChange: setColumnVisibility,
		onRowSelectionChange: finalSetRowSelection,
		state: {
			sorting,
			columnFilters,
			columnVisibility,
			rowSelection: finalRowSelection,
		},
	})

	const pageSize = pagination
		? pagination.pageSize
		: table.getState().pagination.pageSize
	const onPageSizeChange = pagination
		? pagination.onPageSizeChange
		: (size: number) => table.setPageSize(size)

	const selectedRows = table.getFilteredSelectedRowModel().rows
	const selectedCount = selectedRows.length

	const handleBulkDelete = () => {
		onBulkDelete?.(selectedRows.map((row) => row.original.id))
		finalSetRowSelection({})
	}

	const paginationControl = (
		<div className="flex flex-col items-center justify-between gap-4 py-4 sm:flex-row sm:justify-end">
			<div className="flex w-full flex-1 flex-wrap items-center justify-center gap-3 sm:justify-start">
				<p className="text-sm text-muted-foreground">
					{selectedCount} of {table.getFilteredRowModel().rows.length} row
					{table.getFilteredRowModel().rows.length !== 1 ? "s" : ""} selected.
				</p>

				{/* Bulk Delete Dialog Start */}
				{onBulkDelete && selectedCount > 0 && (
					<Dialog>
						<DialogTrigger asChild>
							<Button
								variant="destructive"
								size="sm">
								<Trash2 className="size-4" />
								Delete selected ({selectedCount})
							</Button>
						</DialogTrigger>
						<DialogContent>
							<DialogTitle>
								Delete {selectedCount} selected{" "}
								{selectedCount === 1 ? "item" : "items"}?
							</DialogTitle>
							<DialogDescription>
								This will permanently delete the selected records and cannot be
								undone.
							</DialogDescription>
							<DialogFooter className="gap-2">
								<DialogClose asChild>
									<Button variant="secondary">Cancel</Button>
								</DialogClose>
								<DialogClose asChild>
									<Button
										variant="destructive"
										onClick={handleBulkDelete}>
										Delete
									</Button>
								</DialogClose>
							</DialogFooter>
						</DialogContent>
					</Dialog>
				)}
				{/* Bulk Delete Dialog End */}
			</div>

			<div className="flex flex-wrap items-center justify-center gap-2 sm:space-x-4">
				{/* Columns Dropdown Start */}
				<DropdownMenu>
					<DropdownMenuTrigger asChild>
						<Button
							variant="outline"
							size="sm">
							Columns <ChevronDown className="ml-2 size-4" />
						</Button>
					</DropdownMenuTrigger>
					<DropdownMenuContent align="end">
						{table
							.getAllColumns()
							.filter((column) => column.getCanHide())
							.map((column) => (
								<DropdownMenuCheckboxItem
									key={column.id}
									className="capitalize"
									checked={column.getIsVisible()}
									onCheckedChange={(value) => column.toggleVisibility(!!value)}>
									{column.id}
								</DropdownMenuCheckboxItem>
							))}
					</DropdownMenuContent>
				</DropdownMenu>
				{/* Columns Dropdown End */}

				{/* Rows per page Dropdown Start */}
				<DropdownMenu>
					<DropdownMenuTrigger asChild>
						<Button
							variant="outline"
							size="sm">
							<span className="hidden sm:inline">Rows per page: </span>
							{pageSize} <ChevronDown className="ml-2 size-4" />
						</Button>
					</DropdownMenuTrigger>
					<DropdownMenuContent align="end">
						<DropdownMenuRadioGroup
							value={pageSize.toString()}
							onValueChange={(value) => onPageSizeChange(Number(value))}>
							{PAGE_SIZE_OPTIONS.map((size) => (
								<DropdownMenuRadioItem
									key={size}
									value={size.toString()}>
									{size}
								</DropdownMenuRadioItem>
							))}
						</DropdownMenuRadioGroup>
					</DropdownMenuContent>
				</DropdownMenu>
				{/* Rows per page Dropdown End */}

				{/* Pagination Start */}
				{pagination ? (
					<div className="flex flex-wrap items-center justify-center gap-3">
						<p className="flex gap-1 items-center text-sm text-muted-foreground">
							{pagination.total} Total <span className="inline-block h-6 w-px bg-muted-foreground/80 self-center" /> Page {pagination.currentPage} of{" "}
							{Math.max(pagination.lastPage, 1)}
						</p>
						<Pagination className="mx-0 w-auto">
							<PaginationContent>
								<PaginationItem>
									<PaginationPrevious
										href="#"
										aria-disabled={pagination.currentPage <= 1}
										className={
											pagination.currentPage <= 1
												? "pointer-events-none opacity-50"
												: undefined
										}
										onClick={(event) => {
											event.preventDefault()
											pagination.onPageChange(
												Math.max(pagination.currentPage - 1, 1)
											)
										}}
									/>
								</PaginationItem>
								{getPageNumbers(
									pagination.currentPage,
									Math.max(pagination.lastPage, 1)
								).map((item, index) =>
									item === "ellipsis" ? (
										<PaginationItem key={`ellipsis-${index}`}>
											<PaginationEllipsis />
										</PaginationItem>
									) : (
										<PaginationItem key={item}>
											<PaginationLink
												href="#"
												isActive={item === pagination.currentPage}
												size="sm"
												onClick={(event) => {
													event.preventDefault()
													pagination.onPageChange(item)
												}}>
												{item}
											</PaginationLink>
										</PaginationItem>
									)
								)}
								<PaginationItem>
									<PaginationNext
										href="#"
										aria-disabled={
											pagination.currentPage >= pagination.lastPage
										}
										className={
											pagination.currentPage >= pagination.lastPage
												? "pointer-events-none opacity-50"
												: undefined
										}
										onClick={(event) => {
											event.preventDefault()
											pagination.onPageChange(pagination.currentPage + 1)
										}}
									/>
								</PaginationItem>
							</PaginationContent>
						</Pagination>
					</div>
				) : (
					<div className="flex items-center space-x-2">
						<Button
							variant="outline"
							size="sm"
							onClick={() => table.previousPage()}
							disabled={!table.getCanPreviousPage()}>
							<ChevronLeft className="size-4" />
						</Button>
						<Button
							variant="outline"
							size="sm"
							onClick={() => table.nextPage()}
							disabled={!table.getCanNextPage()}>
							<ChevronRight className="size-4" />
						</Button>
					</div>
				)}
				{/* Pagination End */}
			</div>
		</div>
	)

	return (
		<div className="relative w-full">
			{paginationControl}
			<div className="overflow-x-auto rounded-md border">
				<Table>
					<TableHeader>
						{table.getHeaderGroups().map((headerGroup) => (
							<TableRow
								key={headerGroup.id}
								className="border-border/60 bg-muted/40 hover:bg-muted/40">
								{headerGroup.headers.map((header) => {
									const canSort = header.column.getCanSort()
									const sortDirection = header.column.getIsSorted()

									return (
										<TableHead
											key={header.id}
											className={cn(
												"px-4 py-3 font-medium text-muted-foreground",
												header.column.columnDef.meta?.className
											)}>
											{header.isPlaceholder ? null : canSort ? (
												<button
													type="button"
													onClick={header.column.getToggleSortingHandler()}
													className="inline-flex items-center gap-1 hover:text-foreground">
													{flexRender(
														header.column.columnDef.header,
														header.getContext()
													)}
													{sortDirection === "asc" ? (
														<ArrowUp className="size-3.5" />
													) : sortDirection === "desc" ? (
														<ArrowDown className="size-3.5" />
													) : (
														<ChevronsUpDown className="size-3.5 opacity-50" />
													)}
												</button>
											) : (
												flexRender(
													header.column.columnDef.header,
													header.getContext()
												)
											)}
										</TableHead>
									)
								})}
							</TableRow>
						))}
					</TableHeader>
					<TableBody>
						{table.getRowModel().rows.length ? (
							table.getRowModel().rows.map((row) => (
								<TableRow
									key={row.id}
									className="border-border/40 last:border-0 hover:bg-muted/30"
									data-state={row.getIsSelected() ? "selected" : undefined}>
									{row.getVisibleCells().map((cell) => (
										<TableCell
											key={cell.id}
											className={cn(
												"px-4 py-3 whitespace-normal",
												cell.column.columnDef.meta?.className
											)}>
											{flexRender(
												cell.column.columnDef.cell,
												cell.getContext()
											)}
										</TableCell>
									))}
								</TableRow>
							))
						) : (
							<TableRow>
								<TableCell
									colSpan={tableColumns.length}
									className="h-24 px-4 py-8 text-center text-muted-foreground">
									{emptyMessage}
								</TableCell>
							</TableRow>
						)}
					</TableBody>
				</Table>
			</div>
			{paginationControl}
		</div>
	)
}
