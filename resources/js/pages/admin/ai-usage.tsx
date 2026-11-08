import { ChevronDown, ChevronLeft, ChevronRight, Sparkles } from "lucide-react"
import { Fragment, useState } from "react"
import { Head } from "@/lib/spa"
import Heading from "@/components/heading"
import { Button } from "@/components/ui/button"
import { Skeleton } from "@/components/ui/skeleton"
import { cn } from "@/lib/utils"
import { useAdminAiUsage } from "@/queries/admin"
import type { AdminAiUsageFeature } from "@/queries/admin"

/** What each feature is, and what one use of it is, for pricing. */
const FEATURES: Record<string, { label: string; unit: string }> = {
	reply_suggestion: { label: "Reply suggestions", unit: "per message" },
	cv_parse: { label: "Reading CVs", unit: "per CV" },
	cv_revamp: { label: "CV revamps", unit: "per CV" },
	cv_tailor: { label: "CVs tailored to a job", unit: "per application" },
	job_match: { label: "Matching jobs", unit: "per job seeker" },
	job_application_draft: { label: "Cover letters", unit: "per application" },
	application_form: { label: "Employer form answers", unit: "per application" },
	captcha_solve: { label: "CAPTCHA solves", unit: "per application" },
	career_page_read: { label: "Reading careers pages", unit: "per page" },
	career_page_search: { label: "Finding careers pages", unit: "per search" },
	job_page_read: { label: "Reading job pages", unit: "per job" },
	job_salary: { label: "Estimating job pay", unit: "per job" },
	job_alert_read: { label: "Reading job alerts", unit: "per email" },
	shared_post_read: {
		label: "Reading shared job posts",
		unit: "per job seeker",
	},
	career_news: { label: "Career news stories", unit: "per story" },
	career_fields: { label: "Tagging career fields", unit: "per job seeker" },
}

function featureLabel(feature: string): string {
	return FEATURES[feature]?.label ?? feature.replaceAll("_", " ")
}

/** Per-use costs are often a few hundredths of a cent, so they get more places. */
function formatSmallCost(costMicros: number): string {
	return `$${(costMicros / 1_000_000).toLocaleString("en-KE", { minimumFractionDigits: 4, maximumFractionDigits: 6 })}`
}

function currentMonth(): string {
	const now = new Date()

	return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, "0")}`
}

function shiftMonth(month: string, by: number): string {
	const [year, monthNumber] = month.split("-").map(Number)
	const shifted = new Date(year, monthNumber - 1 + by, 1)

	return `${shifted.getFullYear()}-${String(shifted.getMonth() + 1).padStart(2, "0")}`
}

function monthLabel(month: string): string {
	const [year, monthNumber] = month.split("-").map(Number)

	return new Date(year, monthNumber - 1, 1).toLocaleDateString("en-KE", {
		month: "long",
		year: "numeric",
	})
}

/** Costs are stored in micro-dollars; a single request is often well under a cent. */
function formatCost(costMicros: number): string {
	const dollars = costMicros / 1_000_000

	return `$${dollars.toLocaleString("en-KE", { minimumFractionDigits: 4, maximumFractionDigits: 4 })}`
}

const formatNumber = (value: number) => value.toLocaleString("en-KE")

export default function AdminAiUsage() {
	const [month, setMonth] = useState(currentMonth)
	const isCurrentMonth = month === currentMonth()

	const { data: report, isPending, isError } = useAdminAiUsage(month)
	const [openUser, setOpenUser] = useState<string | null>(null)

	const summary = [
		{
			label: "Cost",
			value: report ? formatCost(report.totals.costMicros) : null,
		},
		{
			label: "AI requests",
			value: report ? formatNumber(report.totals.requests) : null,
		},
		{
			label: "Reply suggestions",
			value: report ? formatNumber(report.totals.replySuggestions) : null,
		},
		{
			label: "Tokens in / out",
			value: report
				? `${formatNumber(report.totals.inputTokens)} / ${formatNumber(report.totals.outputTokens)}`
				: null,
		},
	]

	return (
		<>
			<Head title="AI usage" />

			<div className="flex flex-1 flex-col gap-6">
				<div className="relative overflow-hidden rounded-2xl border bg-card px-4 py-4 shadow-xs sm:px-5">
					<div className="pointer-events-none absolute inset-0 bg-linear-to-br from-primary/8 via-transparent to-transparent" />

					<div className="relative flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
						<Heading
							variant="small"
							title="AI usage"
							description="What AI costs per feature and per user, including the Google searches that ground answers and CAPTCHA solves. Requests no single user asked for (shared searches, career news) are counted under System."
						/>
						<div className="flex items-center gap-1">
							<Button
								variant="outline"
								size="icon"
								aria-label="Previous month"
								onClick={() => setMonth((current) => shiftMonth(current, -1))}>
								<ChevronLeft />
							</Button>
							<span className="min-w-36 text-center text-sm font-medium">
								{monthLabel(month)}
							</span>
							<Button
								variant="outline"
								size="icon"
								aria-label="Next month"
								disabled={isCurrentMonth}
								onClick={() => setMonth((current) => shiftMonth(current, 1))}>
								<ChevronRight />
							</Button>
						</div>
					</div>
				</div>

				{isError ? (
					<p className="text-sm text-destructive">
						Couldn't load AI usage. You may not have access to this page.
					</p>
				) : (
					<>
						<div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
							{summary.map((stat) => (
								<div
									key={stat.label}
									className="rounded-xl border bg-card p-4 shadow-xs">
									<p className="text-xs text-muted-foreground">{stat.label}</p>
									{stat.value === null ? (
										<Skeleton className="mt-2 h-6 w-20" />
									) : (
										<p className="mt-1 text-lg font-semibold tabular-nums">
											{stat.value}
										</p>
									)}
								</div>
							))}
						</div>

						{report && report.features.length > 0 && (
							<FeatureTable features={report.features} />
						)}

						{isPending ? (
							<div
								className="space-y-2"
								aria-busy="true"
								aria-label="Loading AI usage">
								{[0, 1, 2].map((row) => (
									<Skeleton
										key={row}
										className="h-14 w-full rounded-xl"
									/>
								))}
							</div>
						) : report.users.length === 0 ? (
							<div className="flex flex-col items-center gap-2 rounded-xl border border-dashed p-10 text-center text-muted-foreground">
								<Sparkles className="size-8" />
								<p className="text-sm">
									No AI requests in {monthLabel(month)}.
								</p>
							</div>
						) : (
							<div className="overflow-x-auto rounded-xl border bg-card shadow-sm">
								<table className="w-full text-sm">
									<thead className="border-b text-left text-xs text-muted-foreground">
										<tr>
											<th className="p-3 font-medium">User</th>
											<th className="p-3 text-right font-medium">Replies</th>
											<th className="hidden p-3 text-right font-medium sm:table-cell">
												Requests
											</th>
											<th className="hidden p-3 text-right font-medium md:table-cell">
												Tokens in
											</th>
											<th className="hidden p-3 text-right font-medium md:table-cell">
												Tokens out
											</th>
											<th className="p-3 text-right font-medium">Cost</th>
										</tr>
									</thead>
									<tbody className="divide-y">
										{report.users.map((user) => {
											const key = user.userId ?? "system"
											const open = openUser === key

											return (
												<Fragment key={key}>
													<tr>
														<td className="max-w-48 p-3">
															<button
																type="button"
																aria-expanded={open}
																onClick={() => setOpenUser(open ? null : key)}
																className="flex w-full min-w-0 cursor-pointer items-start gap-1.5 text-left">
																<ChevronDown
																	className={cn(
																		"mt-0.5 size-4 shrink-0 text-muted-foreground transition-transform",
																		open && "rotate-180"
																	)}
																/>
																<span className="min-w-0">
																	<span className="block truncate font-medium">
																		{user.name}
																	</span>
																	<span className="block truncate text-xs text-muted-foreground">
																		{user.email ?? "Not tied to a user"}
																	</span>
																</span>
															</button>
														</td>
														<td className="p-3 text-right tabular-nums">
															{formatNumber(user.replySuggestions)}
														</td>
														<td className="hidden p-3 text-right tabular-nums sm:table-cell">
															{formatNumber(user.requests)}
														</td>
														<td className="hidden p-3 text-right tabular-nums md:table-cell">
															{formatNumber(user.inputTokens)}
														</td>
														<td className="hidden p-3 text-right tabular-nums md:table-cell">
															{formatNumber(user.outputTokens)}
														</td>
														<td className="p-3 text-right font-semibold tabular-nums">
															{formatCost(user.costMicros)}
														</td>
													</tr>
													{open && (
														<tr className="bg-muted/40">
															<td
																colSpan={6}
																className="p-3">
																<ul className="space-y-1 text-xs">
																	{user.features.map((feature) => (
																		<li
																			key={feature.feature}
																			className="flex justify-between gap-4">
																			<span>
																				{featureLabel(feature.feature)}{" "}
																				<span className="text-muted-foreground">
																					· {formatNumber(feature.items)} uses,{" "}
																					{formatNumber(feature.requests)}{" "}
																					requests
																				</span>
																			</span>
																			<span className="tabular-nums">
																				{formatCost(feature.costMicros)}
																			</span>
																		</li>
																	))}
																</ul>
															</td>
														</tr>
													)}
												</Fragment>
											)
										})}
									</tbody>
								</table>
							</div>
						)}
					</>
				)}
			</div>
		</>
	)
}

/**
 * What each feature cost and what one use of it costs on average: the
 * numbers to price features by.
 */
function FeatureTable({ features }: { features: AdminAiUsageFeature[] }) {
	return (
		<div className="space-y-2">
			<h2 className="text-sm font-semibold">By feature</h2>
			<div className="overflow-x-auto rounded-xl border bg-card shadow-sm">
				<table className="w-full text-sm">
					<thead className="border-b text-left text-xs text-muted-foreground">
						<tr>
							<th className="p-3 font-medium">Feature</th>
							<th className="p-3 text-right font-medium">Uses</th>
							<th className="hidden p-3 text-right font-medium sm:table-cell">
								Requests
							</th>
							<th className="hidden p-3 text-right font-medium md:table-cell">
								Users
							</th>
							<th className="p-3 text-right font-medium">Cost</th>
							<th className="p-3 text-right font-medium">Per use</th>
						</tr>
					</thead>
					<tbody className="divide-y">
						{features.map((feature) => (
							<tr key={feature.feature}>
								<td className="max-w-56 p-3">
									<p className="truncate font-medium">
										{featureLabel(feature.feature)}
									</p>
									<p className="truncate text-xs text-muted-foreground">
										{FEATURES[feature.feature]?.unit ?? "per use"}
										{feature.searches > 0 &&
											` · ${formatNumber(feature.searches)} Google searches`}
									</p>
								</td>
								<td className="p-3 text-right tabular-nums">
									{formatNumber(feature.items)}
								</td>
								<td className="hidden p-3 text-right tabular-nums sm:table-cell">
									{formatNumber(feature.requests)}
								</td>
								<td className="hidden p-3 text-right tabular-nums md:table-cell">
									{formatNumber(feature.users)}
								</td>
								<td className="p-3 text-right tabular-nums">
									{formatCost(feature.costMicros)}
								</td>
								<td className="p-3 text-right font-semibold tabular-nums">
									{formatSmallCost(feature.costPerItemMicros)}
								</td>
							</tr>
						))}
					</tbody>
				</table>
			</div>
		</div>
	)
}
