import { useEffect, useState } from "react"
import type { ColumnDef } from "@tanstack/react-table"
import { Plus } from "lucide-react"
import { useApp } from "@/contexts/AppContext"
import Axios from "@/lib/axios"
import toast from "@/lib/toast"
import { destroy as destroyPropertyRoute } from "@/routes/properties"
import { destroyMany as destroyManyPropertiesRoute } from "@/actions/App/Http/Controllers/PropertyController"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { DataTable } from "@/components/ui/data-table"
import HeroHeading from "@/components/hero-heading"
import HeroIcon from "@/components/hero-icon"
import { Input } from "@/components/ui/input"
import { Link } from "@/components/ui/link"

type Property = {
	id: string
	name: string
	location: string | null
	serviceCharge: { service?: number | string } | null
	depositFormula: string | null
	unitCount: number
	invoiceDate: string
	email: boolean
	sms: boolean
}

type PropertiesMeta = {
	current_page: number
	per_page: number
	last_page: number
	total: number
}

type PaginatedProperties = {
	data: Property[]
	meta: PropertiesMeta
}

const emptyProperties: PaginatedProperties = {
	data: [],
	meta: { current_page: 1, per_page: 15, last_page: 1, total: 0 },
}

export default function PropertiesIndexPage() {
	const props = useApp()
	const [properties, setProperties] = useState(emptyProperties)
	const [nameQuery, setNameQuery] = useState("")
	const [page, setPage] = useState(1)
	const [perPage, setPerPage] = useState(15)
	const [sort, setSort] = useState<{
		id: string
		direction: "asc" | "desc"
	} | null>(null)

	const propertyIds = [
		...((props.auth?.propertyIds as string[]) ?? []),
		...((props.auth?.assignedPropertyIds as string[]) ?? []),
	]
	const idsParam =
		props.selectedPropertyId !== "All"
			? props.selectedPropertyId
			: propertyIds.length > 0
				? propertyIds.join(",")
				: "0"

	const fetchProperties = () => {
		const query = new URLSearchParams({
			propertyId: idsParam,
			name: nameQuery,
			page: String(page),
			perPage: String(perPage),
			...(sort ? { sort: sort.id, direction: sort.direction } : {}),
		}).toString()

		return props.getPaginated(`properties?${query}`, setProperties)
	}

	useEffect(() => {
		fetchProperties()
	}, [props.auth, idsParam, nameQuery, page, perPage, sort])

	const onNameQueryChange = (value: string) => {
		setNameQuery(value)
		setPage(1)
	}

	const onPerPageChange = (value: number) => {
		setPerPage(value)
		setPage(1)
	}

	const onSortChange = (
		nextSort: { id: string; direction: "asc" | "desc" } | null
	) => {
		setSort(nextSort)
		setPage(1)
	}

	const deleteProperty = (property: Property) => {
		Axios.delete(destroyPropertyRoute.url(property.id))
			.then(() => {
				toast.success(`${property.name} deleted successfully`)
				fetchProperties()
			})
			.catch(() => {
				toast.error(`Failed to delete ${property.name}`)
			})
	}

	const bulkDeleteProperties = (ids: (string | number)[]) => {
		Axios.delete(destroyManyPropertiesRoute.url(), { data: { ids } })
			.then(() => {
				toast.success(
					ids.length === 1
						? "1 property deleted successfully"
						: `${ids.length} properties deleted successfully`
				)
				fetchProperties()
			})
			.catch(() => {
				toast.error("Failed to delete selected properties")
			})
	}

	const columns: ColumnDef<Property>[] = [
		{
			id: "index",
			header: "#",
			enableSorting: false,
			cell: ({ row }) => (
				<span className="text-muted-foreground">
					{props.iterator(row.index, properties)}
				</span>
			),
		},
		{
			accessorKey: "name",
			header: "Name",
			cell: ({ row }) => (
				<span className="font-medium">{row.original.name}</span>
			),
		},
		{
			accessorKey: "location",
			header: "Location",
			cell: ({ row }) => (
				<span className="text-muted-foreground">{row.original.location}</span>
			),
		},
		{
			id: "serviceCharge",
			header: "Service Charge",
			enableSorting: false,
			cell: ({ row }) => (
				<span className="text-emerald-600">
					<span className="text-xs text-muted-foreground">KES </span>
					{Number(row.original.serviceCharge?.service ?? 0).toLocaleString()}
				</span>
			),
		},
		{
			accessorKey: "depositFormula",
			header: "Deposit Formula",
			cell: ({ row }) => (
				<span className="text-muted-foreground">
					{row.original.depositFormula}
				</span>
			),
		},
		{
			accessorKey: "unitCount",
			header: "Units",
		},
		{
			accessorKey: "invoiceDate",
			header: "Invoice Date",
		},
		{
			id: "invoiceChannel",
			header: "Invoice Channel",
			enableSorting: false,
			cell: ({ row }) => (
				<span className="text-muted-foreground">
					{[row.original.email && "Email", row.original.sms && "SMS"]
						.filter(Boolean)
						.join(" · ") || "—"}
				</span>
			),
		},
	]

	return (
		<div className="space-y-6">
			{/* Data Start */}
			<Card className="overflow-hidden">
				<CardContent className="flex items-center justify-between gap-4">
					<HeroHeading
						heading="Total Properties"
						data={properties.meta.total}
					/>
					<HeroIcon icon="Building" />
				</CardContent>
			</Card>
			{/* Data End */}

			{/* Filters Start */}
			<Card>
				<CardContent className="flex flex-wrap items-center gap-4">
					<Input
						id="property-name-search"
						type="text"
						label="Search by name"
						value={nameQuery}
						onChange={(event) => onNameQueryChange(event.target.value)}
					/>
				</CardContent>
			</Card>
			{/* Filters End */}

			<Card className="overflow-hidden">
				<CardHeader className="pb-4">
					<div className="flex flex-wrap items-center justify-between gap-3">
						<CardTitle>Properties</CardTitle>
						<div className="flex flex-wrap items-center gap-3">
							<Link
								href="/admin/properties/create"
								variant="solid"
								size="sm"
								icon={<Plus className="size-4" />}
								text="Add property"
							/>
						</div>
					</div>
				</CardHeader>
				<CardContent>
					<DataTable
						columns={columns}
						data={properties.data}
						emptyMessage="No properties found"
						pagination={{
							currentPage: properties.meta.current_page,
							lastPage: properties.meta.last_page,
							total: properties.meta.total,
							pageSize: perPage,
							onPageChange: setPage,
							onPageSizeChange: onPerPageChange,
						}}
						showHref={(property) => `/admin/properties/${property.id}/show`}
						editHref={(property) => `/admin/properties/${property.id}/edit`}
						onDelete={deleteProperty}
						onBulkDelete={bulkDeleteProperties}
						getItemLabel={(property) => property.name}
						onSortChange={onSortChange}
					/>
				</CardContent>
			</Card>
		</div>
	)
}

PropertiesIndexPage.layout = {
	breadcrumbs: [
		{ title: "Dashboard", href: "/admin" },
		{ title: "Properties", href: "/admin/properties" },
	],
}
