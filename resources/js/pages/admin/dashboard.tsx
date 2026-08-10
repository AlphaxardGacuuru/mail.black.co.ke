import { useEffect, useMemo, useState } from "react"
import { useQuery } from "@tanstack/react-query"
import { useEcho } from "@laravel/echo-react"
import {
	BarChart3,
	Building2,
	Droplets,
	Receipt,
	Sparkles,
	Users,
	Wallet,
} from "lucide-react"
import { useApp } from "@/contexts/AppContext"
import Axios from "@/lib/axios"
import {
	index as dashboardIndex,
	narration as dashboardNarrationRoute,
	properties as dashboardPropertiesRoute,
} from "@/actions/App/Http/Controllers/DashboardController"
import { index as paymentsRoute } from "@/actions/App/Http/Controllers/PaymentController"
import { index as staffRoute } from "@/actions/App/Http/Controllers/StaffController"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Link } from "@/components/ui/link"
import IncomeBar from "@/components/Dashboard/IncomeBar"
import PropertyDoughnut from "@/components/Dashboard/PropertyDoughnut"
import RentDoughnut from "@/components/Dashboard/RentDoughnut"
import ServiceChargeDoughnut from "@/components/Dashboard/ServiceChargeDoughnut"
import TenancyBar from "@/components/Dashboard/TenancyBar"
import TenancyDoughnut from "@/components/Dashboard/TenancyDoughnut"
import WaterDoughnut from "@/components/Dashboard/WaterDoughnut"
import WaterUsagePie from "@/components/Dashboard/WaterUsagePie"
import type {
	LandingDashboard,
	LandingDashboardProperties,
} from "@/components/LandingPage/data"
import type { Payment } from "@/types/payment"
import type { Unit } from "@/types/unit"

type StaffMember = {
	name: string
	phone: string
	roleNames?: string[]
}

type PaginatedResponse<T> = {
	data: T[]
}

const dashboardFallback: LandingDashboard = {
	units: {
		totalOccupied: 0,
		totalUnoccupied: 0,
		percentage: "0",
		tenantsThisYear: { labels: [], data: [] },
		vacanciesThisYear: { labels: [], data: [] },
	},
	rent: {
		paid: 0,
		due: 0,
		total: "0",
		percentage: "0",
		paidThisYear: { labels: [], data: [] },
		unpaidThisYear: { labels: [], data: [] },
	},
	water: {
		paid: 0,
		due: 0,
		total: "0",
		percentage: "0",
		usageTwoMonthsAgo: 0,
		usageLastMonth: 0,
		paidThisYear: { labels: [], data: [] },
		unpaidThisYear: { labels: [], data: [] },
	},
	serviceCharge: {
		paid: 0,
		due: 0,
		total: "0",
		percentage: "0",
		paidThisYear: { labels: [], data: [] },
		unpaidThisYear: { labels: [], data: [] },
	},
}

const dashboardPropsFallback: LandingDashboardProperties = {
	total: 0,
	ids: [],
	names: [],
	units: [],
}

export default function DashboardPage() {
	const { auth, selectedPropertyId } = useApp()

	const propertyIds = [
		...((auth?.propertyIds as string[]) ?? []),
		...((auth?.assignedPropertyIds as string[]) ?? []),
	]
	const idsParam =
		selectedPropertyId !== "All"
			? selectedPropertyId
			: propertyIds.length > 0
				? propertyIds.join(",")
				: "0"

	const { data: dashboard = dashboardFallback } = useQuery({
		queryKey: ["dashboard", idsParam],
		queryFn: () =>
			Axios.get(dashboardIndex(idsParam).url).then(
				(res) => res.data.data as LandingDashboard
			),
		enabled: !!auth,
	})

	const { data: dashboardProps = dashboardPropsFallback } = useQuery({
		queryKey: ["dashboard-properties", idsParam],
		queryFn: () =>
			Axios.get(dashboardPropertiesRoute(idsParam).url).then(
				(res) => res.data.data as LandingDashboardProperties
			),
		enabled: !!auth,
	})

	const { data: payments } = useQuery({
		queryKey: ["payments-dashboard", idsParam],
		queryFn: () =>
			Axios.get(paymentsRoute({ query: { propertyId: idsParam } }).url).then(
				(res) => res.data as PaginatedResponse<Payment>
			),
		enabled: !!auth,
	})

	const { data: staffData } = useQuery({
		queryKey: ["staff-dashboard", idsParam],
		queryFn: () =>
			Axios.get(staffRoute({ query: { propertyId: idsParam } }).url).then(
				(res) => res.data as PaginatedResponse<StaffMember>
			),
		enabled: !!auth,
	})

	const unitList = (dashboard.units as unknown as { list?: Unit[] })?.list ?? []

	const [narrationText, setNarrationText] = useState("")
	const [narrationLoading, setNarrationLoading] = useState(false)
	const [narrationStreamId, setNarrationStreamId] = useState("")

	const narrationChannel = useMemo(() => {
		if (!auth?.id || !narrationStreamId) {
			return "dashboard-narration.0.idle"
		}

		return `dashboard-narration.${auth.id}.${narrationStreamId}`
	}, [auth?.id, narrationStreamId])

	useEcho(
		narrationChannel,
		["stream_start", "text_delta", "stream_end", "error"],
		(event: {
			type?: string
			delta?: string
			error?: string
		}) => {
			switch (event.type) {
				case "text_delta": {
					if (event.delta) {
						setNarrationText((previous) => `${previous}${event.delta}`)
					}
					break
				}

				case "stream_end": {
					setNarrationLoading(false)
					break
				}

				case "error": {
					setNarrationLoading(false)
					break
				}

				default:
					break
			}
		}
	)

	useEffect(() => {
		if (!auth) {
			setNarrationText("")
			setNarrationLoading(false)
			setNarrationStreamId("")
			return
		}

		const nextStreamId =
			typeof crypto !== "undefined" && "randomUUID" in crypto
				? crypto.randomUUID()
				: `${Date.now()}-${Math.random().toString(36).slice(2)}`

		setNarrationText("")
		setNarrationLoading(true)
		setNarrationStreamId(nextStreamId)

		const narrationUrl = new URL(
			dashboardNarrationRoute(idsParam).url,
			window.location.origin
		)
		narrationUrl.searchParams.set("streamId", nextStreamId)

		void Axios.get(`${narrationUrl.pathname}${narrationUrl.search}`).catch(() => {
			setNarrationLoading(false)
		})
	}, [auth, idsParam])

	return (
		<div className="space-y-6">
			{/* AI Narration Card */}
			<Card className="ai-shimmer-sweep border relative z-10 overflow-hidden border-primary/20 bg-primary/5">
				<CardHeader className="pb-2">
					<div className="flex items-center gap-2">
						<div className="rounded-xl bg-primary/10 p-2 text-primary">
							<Sparkles className="icon-shimmer size-5" />
						</div>
						<CardTitle className="text-md text-shimmer">Portfolio Insight</CardTitle>
					</div>
				</CardHeader>
				<CardContent className="space-y-3 pb-4">
					{narrationLoading && narrationText.length === 0 ? (
						<div className="space-y-2">
							<div className="h-4 w-3/4 animate-pulse rounded bg-muted" />
							<div className="h-4 w-1/2 animate-pulse rounded bg-muted" />
						</div>
					) : (
						<>
							<p className="text-sm leading-6 whitespace-pre-line text-muted-foreground">
								{narrationText}
								{narrationLoading && (
									<span className="ml-0.5 inline-block h-2 w-1 animate-pulse rounded bg-primary/70 align-middle" />
								)}
							</p>
						</>
					)}
				</CardContent>
			</Card>

			{/* Doughnuts — all six in one responsive row */}
			<div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
				<Card className="overflow-hidden">
					<CardHeader>
						<div className="flex items-center gap-2">
							<div className="rounded-xl bg-primary/10 p-2 text-primary">
								<Building2 className="size-6" />
							</div>
							<CardTitle className="text-md">Properties</CardTitle>
						</div>
					</CardHeader>
					<CardContent className="px-3 pb-4">
						<PropertyDoughnut dashboardProperties={dashboardProps} />
					</CardContent>
				</Card>

				<Card className="overflow-hidden">
					<CardHeader>
						<div className="flex items-center gap-2">
							<div className="rounded-xl bg-sky-500/10 p-2 text-sky-600">
								<Users className="size-6" />
							</div>
							<CardTitle className="text-md">Tenancy</CardTitle>
						</div>
					</CardHeader>
					<CardContent className="px-3 pb-4">
						<TenancyDoughnut
							dashboard={dashboard}
							dashboardProperties={dashboardProps}
						/>
					</CardContent>
				</Card>

				<Card className="overflow-hidden">
					<CardHeader>
						<div className="flex items-center gap-2">
							<div className="rounded-xl bg-emerald-500/10 p-2 text-emerald-600">
								<Wallet className="size-6" />
							</div>
							<CardTitle className="text-md">Rent</CardTitle>
						</div>
					</CardHeader>
					<CardContent className="px-3 pb-4">
						<RentDoughnut dashboard={dashboard} />
					</CardContent>
				</Card>

				<Card className="overflow-hidden">
					<CardHeader>
						<div className="flex items-center gap-2">
							<div className="rounded-xl bg-cyan-500/10 p-2 text-cyan-600">
								<Droplets className="size-6" />
							</div>
							<CardTitle className="text-md">Water billing</CardTitle>
						</div>
					</CardHeader>
					<CardContent className="px-3 pb-4">
						<WaterDoughnut dashboard={dashboard} />
					</CardContent>
				</Card>

				<Card className="overflow-hidden">
					<CardHeader>
						<div className="flex items-center gap-2">
							<div className="rounded-xl bg-orange-500/10 p-2 text-orange-600">
								<Receipt className="size-6" />
							</div>
							<CardTitle className="text-md">Service charge</CardTitle>
						</div>
					</CardHeader>
					<CardContent className="px-3 pb-4">
						<ServiceChargeDoughnut dashboard={dashboard} />
					</CardContent>
				</Card>

				<Card className="overflow-hidden">
					<CardHeader>
						<div className="flex items-center gap-2">
							<div className="rounded-xl bg-cyan-500/10 p-2 text-cyan-600">
								<Droplets className="size-6" />
							</div>
							<CardTitle className="text-md">Water usage</CardTitle>
						</div>
					</CardHeader>
					<CardContent className="px-3 pb-4">
						<WaterUsagePie dashboard={dashboard} />
					</CardContent>
				</Card>
			</div>

			{/* Bar charts */}
			<div className="grid gap-6 lg:grid-cols-2">
				<Card className="overflow-hidden">
					<CardHeader>
						<div className="flex items-center gap-3">
							<div className="rounded-2xl bg-primary/10 p-3 text-primary">
								<Users className="size-5" />
							</div>
							<div>
								<p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
									This year
								</p>
								<CardTitle className="mt-1">Tenancy trends</CardTitle>
							</div>
						</div>
					</CardHeader>
					<CardContent>
						<TenancyBar dashboard={dashboard} />
					</CardContent>
				</Card>

				<Card className="overflow-hidden">
					<CardHeader>
						<div className="flex items-center gap-3">
							<div className="rounded-2xl bg-primary/10 p-3 text-primary">
								<BarChart3 className="size-5" />
							</div>
							<div>
								<p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
									This year
								</p>
								<CardTitle className="mt-1">Income overview</CardTitle>
							</div>
						</div>
					</CardHeader>
					<CardContent>
						<IncomeBar dashboard={dashboard} />
					</CardContent>
				</Card>
			</div>

			{/* Tables */}
			<div className="grid gap-6 lg:grid-cols-2">
				<Card className="overflow-hidden">
					<CardHeader className="pb-4">
						<div className="flex items-center justify-between gap-3">
							<CardTitle>Units</CardTitle>
							<Link
								href="/units"
								variant="text"
								size="none"
								className="text-sm">
								View all
							</Link>
						</div>
					</CardHeader>
					<CardContent className="p-0">
						<div className="overflow-x-auto">
							<table className="w-full text-sm">
								<thead>
									<tr className="border-b border-border/60 bg-muted/40">
										<th className="px-4 py-3 text-left font-medium text-muted-foreground">
											Name
										</th>
										<th className="px-4 py-3 text-left font-medium text-muted-foreground">
											Rent
										</th>
										<th className="px-4 py-3 text-left font-medium text-muted-foreground">
											Type
										</th>
										<th className="px-4 py-3 text-left font-medium text-muted-foreground">
											Tenant
										</th>
									</tr>
								</thead>
								<tbody>
									{unitList.slice(0, 10).map((unit, key) => (
										<tr
											key={key}
											className="border-b border-border/40 last:border-0 hover:bg-muted/30">
											<td className="px-4 py-3 font-medium">{unit.name}</td>
											<td className="px-4 py-3 text-emerald-600">
												<span className="text-xs text-muted-foreground">
													KES{" "}
												</span>
												{unit.rent}
											</td>
											<td className="px-4 py-3 capitalize text-muted-foreground">
												{unit.type}
											</td>
											<td className="px-4 py-3">
												{unit.tenantId ? (
													<span className="rounded-full bg-emerald-500/10 px-2 py-0.5 text-xs text-emerald-700 dark:text-emerald-400">
														{unit.tenantName}
													</span>
												) : (
													<span className="rounded-full bg-amber-500/10 px-2 py-0.5 text-xs text-amber-700 dark:text-amber-400">
														Vacant
													</span>
												)}
											</td>
										</tr>
									))}
									{unitList.length === 0 && (
										<tr>
											<td
												colSpan={4}
												className="px-4 py-8 text-center text-muted-foreground">
												No units found
											</td>
										</tr>
									)}
								</tbody>
							</table>
						</div>
					</CardContent>
				</Card>

				<Card className="overflow-hidden">
					<CardHeader className="pb-4">
						<div className="flex items-center justify-between gap-3">
							<CardTitle>Recent payments</CardTitle>
							<Link
								href="/payments"
								variant="text"
								size="none"
								className="text-sm">
								View all
							</Link>
						</div>
					</CardHeader>
					<CardContent className="p-0">
						<div className="overflow-x-auto">
							<table className="w-full text-sm">
								<thead>
									<tr className="border-b border-border/60 bg-muted/40">
										<th className="px-4 py-3 text-left font-medium text-muted-foreground">
											Tenant
										</th>
										<th className="px-4 py-3 text-left font-medium text-muted-foreground">
											Unit
										</th>
										<th className="px-4 py-3 text-left font-medium text-muted-foreground">
											Amount
										</th>
										<th className="px-4 py-3 text-left font-medium text-muted-foreground">
											Date
										</th>
									</tr>
								</thead>
								<tbody>
									{payments?.data?.slice(0, 10).map((payment, key) => (
										<tr
											key={key}
											className="border-b border-border/40 last:border-0 hover:bg-muted/30">
											<td className="px-4 py-3 font-medium">
												{payment.tenantName}
											</td>
											<td className="px-4 py-3 text-muted-foreground">
												{payment.unitName}
											</td>
											<td className="px-4 py-3 text-emerald-600">
												<span className="text-xs text-muted-foreground">
													KES{" "}
												</span>
												{payment.amount}
											</td>
											<td className="px-4 py-3 text-muted-foreground">
												{payment.paidOn}
											</td>
										</tr>
									))}
									{!payments?.data?.length && (
										<tr>
											<td
												colSpan={4}
												className="px-4 py-8 text-center text-muted-foreground">
												No payments found
											</td>
										</tr>
									)}
								</tbody>
							</table>
						</div>
					</CardContent>
				</Card>
			</div>

			<Card className="overflow-hidden">
				<CardHeader className="pb-4">
					<div className="flex items-center justify-between gap-3">
						<CardTitle>Staff</CardTitle>
						<Link
							href="/staff"
							variant="text"
							size="none"
							className="text-sm">
							View all
						</Link>
					</div>
				</CardHeader>
				<CardContent className="p-0">
					<div className="overflow-x-auto">
						<table className="w-full text-sm">
							<thead>
								<tr className="border-b border-border/60 bg-muted/40">
									<th className="px-4 py-3 text-left font-medium text-muted-foreground">
										Name
									</th>
									<th className="px-4 py-3 text-left font-medium text-muted-foreground">
										Phone
									</th>
									<th className="px-4 py-3 text-left font-medium text-muted-foreground">
										Roles
									</th>
								</tr>
							</thead>
							<tbody>
								{staffData?.data?.slice(0, 10).map((member, key) => (
									<tr
										key={key}
										className="border-b border-border/40 last:border-0 hover:bg-muted/30">
										<td className="px-4 py-3 font-medium">{member.name}</td>
										<td className="px-4 py-3 text-muted-foreground">
											{member.phone}
										</td>
										<td className="px-4 py-3">
											<div className="flex flex-wrap gap-1">
												{member.roleNames?.map((role, i) => (
													<span
														key={i}
														className="rounded-full bg-primary/10 px-2 py-0.5 text-xs text-primary">
														{role}
													</span>
												))}
											</div>
										</td>
									</tr>
								))}
								{!staffData?.data?.length && (
									<tr>
										<td
											colSpan={3}
											className="px-4 py-8 text-center text-muted-foreground">
											No staff found
										</td>
									</tr>
								)}
							</tbody>
						</table>
					</div>
				</CardContent>
			</Card>
		</div>
	)
}

DashboardPage.layout = {
	breadcrumbs: [
		{
			title: "Dashboard",
			href: "/admin",
		},
	],
}
