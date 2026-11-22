import { formatDistanceToNow } from "date-fns"
import { Activity, CalendarDays, CalendarRange, LogIn } from "lucide-react"
import { useState } from "react"
import type { ColumnDef } from "@tanstack/react-table"
import { Bar, BarChart, CartesianGrid, XAxis, YAxis } from "recharts"
import AdminStatCard from "@/components/admin/AdminStatCard"
import Heading from "@/components/heading"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { Badge } from "@/components/ui/badge"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import {
	ChartContainer,
	ChartTooltip,
	ChartTooltipContent,
	type ChartConfig,
} from "@/components/ui/chart"
import { DataTable } from "@/components/ui/data-table"
import { Input } from "@/components/ui/input"
import { Skeleton } from "@/components/ui/skeleton"
import { useInitials } from "@/hooks/use-initials"
import { Head } from "@/lib/spa"
import { type AdminUserLogin, useAdminUserLogins } from "@/queries/admin"

const chartConfig: ChartConfig = {
	logins: { label: "Sign-ins", color: "var(--chart-1)" },
	users: { label: "People", color: "var(--chart-2)" },
}

const METHOD_LABELS: Record<AdminUserLogin["method"], string> = {
	google: "Google",
	password: "Password",
}

// Checked in order, so Edge and Opera are found before the Chrome they
// also mention, and Chrome before the Safari it mentions.
const BROWSERS: [RegExp, string][] = [
	[/Edg\//, "Edge"],
	[/OPR\/|Opera/, "Opera"],
	[/SamsungBrowser/, "Samsung Internet"],
	[/Firefox|FxiOS/, "Firefox"],
	[/Chrome|CriOS/, "Chrome"],
	[/Safari/, "Safari"],
]

const SYSTEMS: [RegExp, string][] = [
	[/Android/, "Android"],
	[/iPhone|iPad|iPod/, "iOS"],
	[/Windows/, "Windows"],
	[/CrOS/, "ChromeOS"],
	[/Mac OS X|Macintosh/, "macOS"],
	[/Linux/, "Linux"],
]

const match = (userAgent: string, patterns: [RegExp, string][]) =>
	patterns.find(([pattern]) => pattern.test(userAgent))?.[1]

/** A short "Chrome on Android" style label from a user agent string. */
function describeDevice(userAgent: string | null): string {
	if (!userAgent) {
		return "Unknown device"
	}

	const browser = match(userAgent, BROWSERS) ?? "Browser"
	const system = match(userAgent, SYSTEMS)

	return system ? `${browser} on ${system}` : browser
}

const timeAgo = (date: string) =>
	formatDistanceToNow(new Date(date), { addSuffix: true })

export default function AdminLogins() {
	const [search, setSearch] = useState("")
	const [page, setPage] = useState(1)
	const [perPage, setPerPage] = useState(20)
	const getInitials = useInitials()

	const { data, isLoading } = useAdminUserLogins(search, page, perPage)

	const columns: ColumnDef<AdminUserLogin>[] = [
		{
			id: "user",
			header: "User",
			enableSorting: false,
			cell: ({ row }) => (
				<div className="flex min-w-0 items-center gap-3">
					<Avatar className="size-9 shrink-0">
						<AvatarImage
							src={row.original.user.avatar ?? undefined}
							alt={row.original.user.name}
						/>
						<AvatarFallback>
							{getInitials(row.original.user.name)}
						</AvatarFallback>
					</Avatar>
					<div className="min-w-0">
						<p className="truncate font-medium">{row.original.user.name}</p>
						<p className="truncate text-xs text-muted-foreground">
							{row.original.user.email}
						</p>
					</div>
				</div>
			),
		},
		{
			id: "signedIn",
			header: "Signed in",
			enableSorting: false,
			cell: ({ row }) => (
				<span title={new Date(row.original.createdAt).toLocaleString()}>
					{timeAgo(row.original.createdAt)}
				</span>
			),
		},
		{
			id: "method",
			header: "Method",
			enableSorting: false,
			cell: ({ row }) => (
				<Badge variant="secondary">
					{METHOD_LABELS[row.original.method] ?? row.original.method}
				</Badge>
			),
		},
		{
			id: "device",
			header: "Device",
			enableSorting: false,
			cell: ({ row }) => (
				<div className="min-w-0">
					<p
						className="truncate"
						title={row.original.userAgent ?? undefined}>
						{describeDevice(row.original.userAgent)}
					</p>
					<p className="truncate text-xs text-muted-foreground">
						{row.original.ipAddress ?? "Unknown IP"}
					</p>
				</div>
			),
		},
		{
			id: "lastActive",
			header: "Last active",
			enableSorting: false,
			cell: ({ row }) => (
				<span className="text-muted-foreground">
					{row.original.user.lastActiveAt
						? timeAgo(row.original.user.lastActiveAt)
						: "Never"}
				</span>
			),
		},
	]

	const activeUsers = data?.meta.activeUsers

	return (
		<>
			<Head title="Admin logins" />

			<div className="space-y-6">
				<Heading
					variant="small"
					title="Logins"
					description="Who signs in, how and from which device, and how many people use the app"
				/>

				<div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
					{activeUsers ? (
						<>
							<AdminStatCard
								label="Active in the last 24 hours"
								value={activeUsers.day}
								icon={Activity}
								tone="success"
							/>
							<AdminStatCard
								label="Active in the last 7 days"
								value={activeUsers.week}
								icon={CalendarDays}
							/>
							<AdminStatCard
								label="Active in the last 30 days"
								value={activeUsers.month}
								icon={CalendarRange}
							/>
							<AdminStatCard
								label="Sign-ins in the last 30 days"
								value={data.meta.daily.reduce(
									(sum, day) => sum + day.logins,
									0
								)}
								icon={LogIn}
							/>
						</>
					) : (
						Array.from({ length: 4 }).map((_, index) => (
							<Skeleton
								key={index}
								className="h-20"
							/>
						))
					)}
				</div>

				<div className="rounded-lg border p-4">
					<Heading
						variant="small"
						title="Sign-ins per day"
						description="Last 30 days. People counts each user once per day."
					/>
					{data ? (
						<ChartContainer
							config={chartConfig}
							className="h-64 w-full">
							<BarChart data={data.meta.daily}>
								<CartesianGrid
									vertical={false}
									strokeDasharray="3 3"
								/>
								<XAxis
									dataKey="date"
									tickFormatter={(value: string) =>
										new Date(value).toLocaleDateString(undefined, {
											month: "short",
											day: "numeric",
										})
									}
									tickLine={false}
									axisLine={false}
								/>
								<YAxis
									allowDecimals={false}
									tickLine={false}
									axisLine={false}
								/>
								<ChartTooltip content={<ChartTooltipContent />} />
								<Bar
									dataKey="logins"
									fill="var(--color-logins)"
									radius={4}
								/>
								<Bar
									dataKey="users"
									fill="var(--color-users)"
									radius={4}
								/>
							</BarChart>
						</ChartContainer>
					) : (
						<Skeleton className="h-64" />
					)}
				</div>

				<Card className="overflow-hidden">
					<CardHeader className="gap-4 pb-4 sm:flex sm:flex-row sm:items-end sm:justify-between">
						<CardTitle>Recent sign-ins</CardTitle>
						<div className="w-full sm:max-w-xs">
							{/* Search Start */}
							<Input
								label="Search name or email"
								value={search}
								onChange={(event) => {
									setSearch(event.target.value)
									setPage(1)
								}}
							/>
							{/* Search End */}
						</div>
					</CardHeader>
					<CardContent>
						<DataTable
							columns={columns}
							data={data?.data ?? []}
							emptyMessage={isLoading ? "Loading…" : "No sign-ins yet"}
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
							getItemLabel={(login) => login.user.name}
						/>
					</CardContent>
				</Card>
			</div>
		</>
	)
}
