import { useState } from "react"
import { useLocation } from "react-router-dom/cjs/react-router-dom.min"
import { Eye, Edit2, Plus, Trash2 } from "lucide-react"

import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import {
	Select,
	SelectContent,
	SelectItem,
	SelectTrigger,
	SelectValue,
} from "@/components/ui/select"
import {
	Dialog,
	DialogContent,
	DialogDescription,
	DialogFooter,
	DialogHeader,
	DialogTitle,
} from "@/components/ui/dialog"

import { Link } from "@/components/ui/link"
import axios from "axios"

type Unit = {
	id: number | string
	name: string
	propertyName: string
	rent: number | string
	deposit: number | string
	type: string
	size?: { value: number; unit: string }
	bedrooms?: number
	ensuite?: number
	dsq?: boolean
	status: "vacant" | "occupied"
	tenantName?: string
}

type UnitListProps = {
	units: {
		data: Unit[]
		meta: { total: number; [key: string]: any }
	}
	apartmentTypes: Array<{ id: string; name: string }>
	setNameQuery: (value: string) => void
	setTypeQuery: (value: string) => void
	setStatusQuery: (value: string) => void
	onDeleteUnit: (unit: Unit) => void
	getPaginated: (url: string) => void
	setUnits: (units: any) => void
	activeTab?: string
	iterator: (index: number, units: any) => number | string
}

export default function UnitList({
	units,
	apartmentTypes,
	setNameQuery,
	setTypeQuery,
	setStatusQuery,
	onDeleteUnit,
	getPaginated,
	setUnits,
	activeTab,
	iterator,
}: UnitListProps) {
	const location = useLocation()
	const [deleteDialogOpen, setDeleteDialogOpen] = useState(false)
	const [selectedUnit, setSelectedUnit] = useState<Unit | null>(null)
	const [isDeleting, setIsDeleting] = useState(false)

	const handleDeleteClick = (unit: Unit) => {
		setSelectedUnit(unit)
		setDeleteDialogOpen(true)
	}

	const handleConfirmDelete = () => {
		if (!selectedUnit) return

		setIsDeleting(true)
		axios
			.delete(`/api/units/${selectedUnit.id}`)
			.then(() => {
				onDeleteUnit(selectedUnit)
				setDeleteDialogOpen(false)
				setSelectedUnit(null)
			})
			.catch(() => {
				// Error handling
			})
			.finally(() => {
				setIsDeleting(false)
			})
	}

	return (
		<div className={`space-y-6 ${activeTab ? "" : ""}`}>
			{/* Stats Card */}
			<div className="rounded-lg border bg-card p-4 shadow-sm">
				<div className="flex justify-between items-center">
					<div>
						<p className="text-sm text-muted-foreground">Total Units</p>
						<h3 className="text-2xl font-bold">{units.meta?.total || 0}</h3>
					</div>
				</div>
			</div>

			{/* Filters */}
			<div className="rounded-lg border bg-card p-4 shadow-sm">
				<div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
					{/* Name Filter */}
					<div className="space-y-2">
						<label htmlFor="name" className="text-sm font-medium">
							Name
						</label>
						<Input
							id="name"
							type="text"
							placeholder="Search by Name"
							onChange={(e) => setNameQuery(e.target.value)}
						/>
					</div>

					{/* Type Filter */}
					<div className="space-y-2">
						<label htmlFor="type" className="text-sm font-medium">
							Type
						</label>
						<Select onValueChange={setTypeQuery}>
							<SelectTrigger id="type">
								<SelectValue placeholder="Select type" />
							</SelectTrigger>
							<SelectContent>
								<SelectItem value="">All</SelectItem>
								{apartmentTypes.map((type) => (
									<SelectItem key={type.id} value={String(type.id)}>
										{type.name}
									</SelectItem>
								))}
							</SelectContent>
						</Select>
					</div>

					{/* Status Filter */}
					{!location.pathname.match("/tenant/") && (
						<div className="space-y-2">
							<label htmlFor="status" className="text-sm font-medium">
								Status
							</label>
							<Select onValueChange={setStatusQuery}>
								<SelectTrigger id="status">
									<SelectValue placeholder="Select status" />
								</SelectTrigger>
								<SelectContent>
									<SelectItem value="">All</SelectItem>
									<SelectItem value="vacant">Vacant</SelectItem>
									<SelectItem value="occupied">Occupied</SelectItem>
								</SelectContent>
							</Select>
						</div>
					)}
				</div>
			</div>

			{/* Table */}
			<div className="rounded-lg border bg-card overflow-hidden shadow-sm">
				{units.data?.length > 0 ? (
					<div className="overflow-x-auto">
						<table className="w-full text-sm">
							<thead className="border-b bg-muted/50">
								<tr>
									<th className="px-4 py-3 text-left font-medium">#</th>
									<th className="px-4 py-3 text-left font-medium">Name</th>
									<th className="px-4 py-3 text-left font-medium">Property</th>
									<th className="px-4 py-3 text-left font-medium">Rent</th>
									<th className="px-4 py-3 text-left font-medium">Deposit</th>
									<th className="px-4 py-3 text-left font-medium">Type</th>
									<th className="px-4 py-3 text-left font-medium">Size</th>
									<th className="px-4 py-3 text-left font-medium">Ensuite</th>
									<th className="px-4 py-3 text-left font-medium">DSQ</th>
									<th className="px-4 py-3 text-left font-medium">Tenant</th>
									<th className="px-4 py-3 text-center font-medium">Actions</th>
								</tr>
								{!location.pathname.match("/tenant/") && (
									<tr className="border-b bg-muted/30">
										<td colSpan={10}></td>
										<td className="px-4 py-3 text-right">
											<Link href="/units/create">
												<Button size="sm" variant="default">
													<Plus className="h-4 w-4 mr-2" />
													Add Unit
												</Button>
											</Link>
										</td>
									</tr>
								)}
							</thead>
							<tbody>
								{units.data?.map((unit, key) => (
									<tr key={key} className="border-b hover:bg-muted/30 transition-colors">
										<td className="px-4 py-3 text-muted-foreground">
											{iterator(key, units)}
										</td>
										<td className="px-4 py-3 font-medium">{unit.name}</td>
										<td className="px-4 py-3">{unit.propertyName}</td>
										<td className="px-4 py-3 text-green-600">
											KES {Number(unit.rent).toLocaleString()}
										</td>
										<td className="px-4 py-3 text-green-600">
											KES {Number(unit.deposit).toLocaleString()}
										</td>
										<td className="px-4 py-3 capitalize">{unit.type}</td>
										<td className="px-4 py-3 capitalize">
											{unit.size?.unit
												? `${unit.size?.value} ${unit.size?.unit}`
												: unit.bedrooms === 0
													? "Studio"
													: `${unit.bedrooms} Bed`}
										</td>
										<td className="px-4 py-3">{unit.ensuite}</td>
										<td className="px-4 py-3">{unit.dsq ? "Yes" : "No"}</td>
										<td className="px-4 py-3">
											{unit.status === "vacant" ? (
												<span className="inline-block px-3 py-1 rounded text-sm bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
													Vacant
												</span>
											) : (
												<span className="inline-block px-3 py-1 rounded text-sm bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
													{unit.tenantName}
												</span>
											)}
										</td>
										<td className="px-4 py-3">
											{!location.pathname.match("/tenant/") && (
												<div className="flex justify-center gap-2">
													<Link href={`/units/${unit.id}/show`}>
														<Button size="sm" variant="ghost">
															<Eye className="h-4 w-4" />
														</Button>
													</Link>
													<Link href={`/units/${unit.id}/edit`}>
														<Button size="sm" variant="ghost">
															<Edit2 className="h-4 w-4" />
														</Button>
													</Link>
													<Button
														size="sm"
														variant="ghost"
														className="text-destructive hover:text-destructive"
														onClick={() => handleDeleteClick(unit)}>
														<Trash2 className="h-4 w-4" />
													</Button>
												</div>
											)}
										</td>
									</tr>
								))}
							</tbody>
						</table>
					</div>
				) : (
					<div className="flex flex-col items-center justify-center py-12 px-4">
						<p className="text-muted-foreground">No units found</p>
					</div>
				)}
			</div>

			{/* Delete Dialog */}
			<Dialog open={deleteDialogOpen} onOpenChange={setDeleteDialogOpen}>
				<DialogContent>
					<DialogHeader>
						<DialogTitle>Delete Unit</DialogTitle>
						<DialogDescription>
							Are you sure you want to delete &quot;{selectedUnit?.name}&quot;? This action cannot be undone.
						</DialogDescription>
					</DialogHeader>
					<DialogFooter>
						<Button
							variant="outline"
							onClick={() => setDeleteDialogOpen(false)}
							disabled={isDeleting}>
							Cancel
						</Button>
						<Button
							variant="destructive"
							onClick={handleConfirmDelete}
							disabled={isDeleting}>
							{isDeleting ? "Deleting..." : "Delete"}
						</Button>
					</DialogFooter>
				</DialogContent>
			</Dialog>
		</div>
	)
}
