import { useState } from "react"
import type { ColumnDef } from "@tanstack/react-table"
import Heading from "@/components/heading"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { DataTable } from "@/components/ui/data-table"
import { Input } from "@/components/ui/input"
import { Spinner } from "@/components/ui/spinner"
import { Switch } from "@/components/ui/switch"
import { AvatarVerifiedBadge } from "@/components/verified-badge"
import { Head } from "@/lib/spa"
import toast from "@/lib/toast"
import {
	type AdminUser,
	useAdminUsers,
	useToggleUserVerified,
} from "@/queries/admin"

function initials(name?: string | null): string {
	return (name?.trim() || "?").slice(0, 2).toUpperCase()
}

export default function AdminUsers() {
	const [search, setSearch] = useState("")
	const [page, setPage] = useState(1)
	const [perPage, setPerPage] = useState(20)
	const { data, isLoading } = useAdminUsers(search, page, perPage)
	const toggleVerified = useToggleUserVerified()

	function handleToggle(userId: string, verified: boolean): void {
		toggleVerified.mutate(
			{ userId, verified },
			{
				onSuccess: (response) => toast.success(response.data.message),
				onError: () => toast.error("Couldn't update this user"),
			}
		)
	}

	const columns: ColumnDef<AdminUser>[] = [
		{
			id: "user",
			header: "User",
			enableSorting: false,
			cell: ({ row }) => (
				<div className="flex items-center gap-3">
					<div className="relative shrink-0">
						<Avatar className="size-9">
							<AvatarImage
								src={row.original.avatar ?? undefined}
								alt={row.original.name}
							/>
							<AvatarFallback>{initials(row.original.name)}</AvatarFallback>
						</Avatar>
						{row.original.verified && <AvatarVerifiedBadge />}
					</div>
					<span className="min-w-0 truncate font-medium">
						{row.original.name}
					</span>
				</div>
			),
		},
		{
			accessorKey: "email",
			header: "Email",
			cell: ({ row }) => (
				<span className="text-muted-foreground">{row.original.email}</span>
			),
		},
		{
			accessorKey: "phone",
			header: "Phone",
			cell: ({ row }) => <span className="">{row.original.phone}</span>,
		},
		{
			accessorKey: "gender",
			header: "Gender",
			cell: ({ row }) => (
				<span className="capitalize">{row.original.gender}</span>
			),
		},
		{
			accessorKey: "created_at",
			header: "Created At",
			cell: ({ row }) => <span className="">{row.original.createdAt}</span>,
		},
		{
			id: "verified",
			header: "Verified",
			enableSorting: false,
			meta: { className: "text-right" },
			cell: ({ row }) => {
				const isTogglingThisUser =
					toggleVerified.isPending &&
					toggleVerified.variables?.userId === row.original.id

				return (
					<div className="flex items-center justify-end gap-2">
						{isTogglingThisUser && (
							<Spinner className="size-4 text-muted-foreground" />
						)}
						{/* Verified Start */}
						<Switch
							checked={row.original.verified}
							disabled={toggleVerified.isPending}
							onCheckedChange={(checked) =>
								handleToggle(row.original.id, checked)
							}
							aria-label={
								row.original.verified
									? `Remove verified badge from ${row.original.name}`
									: `Verify ${row.original.name}`
							}
						/>
						{/* Verified End */}
					</div>
				)
			},
		},
	]

	return (
		<>
			<Head title="Admin users" />

			<div className="space-y-6">
				<Heading
					variant="small"
					title="Users"
					description="Browse and search everyone with an account"
				/>

				<Card>
					<CardContent className="flex flex-wrap items-center gap-4">
						<Input
							label="Search by name"
							value={search}
							onChange={(event) => {
								setSearch(event.target.value)
								setPage(1)
							}}
						/>
					</CardContent>
				</Card>

				<Card className="overflow-hidden">
					<CardHeader className="pb-4">
						<CardTitle>Users</CardTitle>
					</CardHeader>
					<CardContent>
						<DataTable
							columns={columns}
							data={data?.data ?? []}
							emptyMessage={isLoading ? "Loading…" : "No users found"}
							pagination={{
								currentPage: data?.meta.current_page ?? 1,
								lastPage: data?.meta.last_page ?? 1,
								total: data?.meta.total ?? 0,
								pageSize: perPage,
								onPageChange: setPage,
								onPageSizeChange: (size) => {
									setPerPage(size)
									setPage(1)
								},
							}}
							getItemLabel={(user) => user.name}
						/>
					</CardContent>
				</Card>
			</div>
		</>
	)
}
