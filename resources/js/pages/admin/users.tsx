import { Trash2, X } from "lucide-react"
import { useState } from "react"
import type { ColumnDef } from "@tanstack/react-table"
import Heading from "@/components/heading"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { DataTable } from "@/components/ui/data-table"
import { Input } from "@/components/ui/input"
import { Spinner } from "@/components/ui/spinner"
import { Switch } from "@/components/ui/switch"
import { AvatarVerifiedBadge } from "@/components/verified-badge"
import { useApp } from "@/contexts/AppContext"
import { Head } from "@/lib/spa"
import toast from "@/lib/toast"
import {
	type AdminUser,
	useAdminUsers,
	useDeleteUser,
	useRemoveMailbox,
	useToggleUserVerified,
} from "@/queries/admin"
import { errorMessage } from "@/queries/job-search"
import type { MailgunAccount } from "@/types"

function initials(name?: string | null): string {
	return (name?.trim() || "?").slice(0, 2).toUpperCase()
}

export default function AdminUsers() {
	const [search, setSearch] = useState("")
	const [page, setPage] = useState(1)
	const [perPage, setPerPage] = useState(20)
	const { data, isLoading } = useAdminUsers(search, page, perPage)
	const toggleVerified = useToggleUserVerified()
	const removeMailbox = useRemoveMailbox()
	const deleteUser = useDeleteUser()
	const { auth } = useApp()

	function handleDeleteUser(user: AdminUser): void {
		if (
			!window.confirm(
				`Delete ${user.name}'s account? Their mail, chats, job search, CVs and mailboxes are deleted for good, including on Mailgun. Their AI usage is kept.`
			)
		) {
			return
		}

		deleteUser
			.mutateAsync(user.id)
			.then((data) => toast.success(data.message))
			.catch((error) =>
				toast.error(errorMessage(error, "Couldn't delete this account"))
			)
	}

	function handleRemoveMailbox(account: MailgunAccount): void {
		if (
			!window.confirm(
				`Remove ${account.mailboxAddress}? It's deleted on Mailgun too, and new mail to it won't arrive.`
			)
		) {
			return
		}

		removeMailbox
			.mutateAsync(account.id)
			.then((data) => toast.success(data.message))
			.catch((error) =>
				toast.error(errorMessage(error, "Couldn't remove this mailbox"))
			)
	}

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
			id: "mailboxes",
			header: "Mailboxes",
			enableSorting: false,
			cell: ({ row }) => (
				<div className="flex flex-wrap gap-1.5">
					{row.original.mailgunAccounts.map((account) => (
						<Badge
							key={account.id}
							variant="secondary"
							className="gap-1">
							{account.mailboxAddress}
							{account.isPlatform ? (
								<span className="text-muted-foreground">(job search)</span>
							) : (
								<button
									type="button"
									className="cursor-pointer rounded-full text-muted-foreground hover:text-destructive disabled:opacity-50"
									disabled={removeMailbox.isPending}
									onClick={() => handleRemoveMailbox(account)}
									aria-label={`Remove ${account.mailboxAddress}`}>
									<X className="size-3" />
								</button>
							)}
						</Badge>
					))}
				</div>
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
		{
			id: "actions",
			header: "",
			enableSorting: false,
			meta: { className: "text-right" },
			cell: ({ row }) =>
				row.original.id !== String(auth?.id) && (
					<Button
						type="button"
						variant="ghost"
						size="icon"
						className="text-muted-foreground hover:text-destructive"
						disabled={deleteUser.isPending}
						onClick={() => handleDeleteUser(row.original)}
						aria-label={`Delete ${row.original.name}'s account`}>
						<Trash2 />
					</Button>
				),
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
