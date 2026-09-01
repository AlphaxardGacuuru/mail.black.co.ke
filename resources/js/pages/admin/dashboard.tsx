import {
	AlertTriangle,
	CheckCircle2,
	Inbox,
	Send,
	Users,
	Webhook,
	XCircle,
} from "lucide-react"
import {
	Bar,
	BarChart,
	CartesianGrid,
	XAxis,
	YAxis,
} from "recharts"
import { Head } from "@/lib/spa"
import AdminStatCard from "@/components/admin/AdminStatCard"
import Heading from "@/components/heading"
import {
	ChartContainer,
	ChartTooltip,
	ChartTooltipContent,
	type ChartConfig,
} from "@/components/ui/chart"
import { Skeleton } from "@/components/ui/skeleton"
import { useAdminDashboard } from "@/queries/admin"

const chartConfig: ChartConfig = {
	sent: { label: "Sent", color: "hsl(var(--chart-1))" },
	failed: { label: "Failed", color: "hsl(var(--chart-2))" },
}

export default function AdminDashboard() {
	const { data, isLoading } = useAdminDashboard()

	return (
		<>
			<Head title="Admin dashboard" />

			<div className="space-y-6">
				<Heading
					variant="small"
					title="Overview"
					description="Mail delivery and webhook activity across the app"
				/>

				{isLoading || !data ? (
					<div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
						{Array.from({ length: 8 }).map((_, index) => (
							<Skeleton
								key={index}
								className="h-20"
							/>
						))}
					</div>
				) : (
					<>
						<div className="grid grid-cols-1 gap-4 lg:grid-cols-4">
							<AdminStatCard
								label="Mails sent"
								value={data.totals.mailsSent}
								icon={Send}
								tone="success"
							/>
							<AdminStatCard
								label="Mails failed"
								value={data.totals.mailsFailed}
								icon={XCircle}
								tone="danger"
							/>
							<AdminStatCard
								label="Mails delivered"
								value={data.totals.mailsDelivered}
								icon={CheckCircle2}
								tone="success"
							/>
							<AdminStatCard
								label="Mails bounced"
								value={data.totals.mailsBounced}
								icon={AlertTriangle}
								tone="warning"
							/>
							<AdminStatCard
								label="Mails received"
								value={data.totals.mailsReceived}
								icon={Inbox}
							/>
							<AdminStatCard
								label="Webhook events"
								value={data.totals.totalWebhookEvents}
								icon={Webhook}
							/>
							<AdminStatCard
								label="Webhooks (24h)"
								value={data.totals.webhookEventsLast24h}
								icon={Webhook}
							/>
							<AdminStatCard
								label="Total users"
								value={data.totals.totalUsers}
								icon={Users}
							/>
						</div>

						<div className="rounded-lg border p-4">
							<Heading
								variant="small"
								title="Send volume — last 14 days"
							/>
							<ChartContainer
								config={chartConfig}
								className="h-64">
								<BarChart data={data.dailyVolume}>
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
										dataKey="sent"
										fill="var(--color-sent)"
										radius={4}
									/>
									<Bar
										dataKey="failed"
										fill="var(--color-failed)"
										radius={4}
									/>
								</BarChart>
							</ChartContainer>
						</div>

						<div className="rounded-lg border p-4">
							<Heading
								variant="small"
								title="Recent failures"
							/>

							{data.recentFailures.length === 0 ? (
								<p className="py-6 text-center text-sm text-muted-foreground">
									No recent failures.
								</p>
							) : (
								<div className="divide-y">
									{data.recentFailures.map((failure) => (
										<div
											key={failure.id}
											className="flex flex-col gap-1 py-3 text-sm">
											<div className="flex items-center justify-between gap-2">
												<span className="truncate font-medium">
													{failure.subject || "(no subject)"}
												</span>
												<span className="shrink-0 text-xs text-muted-foreground">
													{new Date(failure.createdAt).toLocaleString()}
												</span>
											</div>
											<p className="truncate text-muted-foreground">
												To: {failure.to || "—"}
											</p>
											{failure.errorMessage && (
												<p className="truncate text-red-600 dark:text-red-400">
													{failure.errorMessage}
												</p>
											)}
										</div>
									))}
								</div>
							)}
						</div>
					</>
				)}
			</div>
		</>
	)
}
