import { useEffect, useState } from "react"
import type { FC } from "react"
import { useQuery } from "@tanstack/react-query"
import { index as subscriptionPlansRoute } from "@/actions/App/Http/Controllers/SubscriptionPlanController"
import Axios from "@/lib/axios"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { GlassCard, GlassInner } from "@/components/ui/glass-card"
import IncomeBar from "@/components/Dashboard/IncomeBar"
import BillingTabInfo from "@/components/LandingPage/BillingTabInfo"
import OccupancyTabInfo from "@/components/LandingPage/OccupancyTabInfo"
import PropertyTabInfo from "@/components/LandingPage/PropertyTabInfo"
import { dashboard, dashboardProperties } from "@/components/LandingPage/data"
import PropertyDoughnut from "@/components/Dashboard/PropertyDoughnut"
import RentDoughnut from "@/components/Dashboard/RentDoughnut"
import ServiceChargeDoughnut from "@/components/Dashboard/ServiceChargeDoughnut"
import SubscriptionPlan from "@/components/SubscriptionPlan/SubscriptionPlan"
import type {
	BillingCycle,
	SubscriptionPlanData,
} from "@/components/SubscriptionPlan/SubscriptionPlan"
import TenantTabInfo from "@/components/LandingPage/TenantTabInfo"
import TenancyBar from "@/components/Dashboard/TenancyBar"
import TenancyDoughnut from "@/components/Dashboard/TenancyDoughnut"
import WaterTabInfo from "@/components/LandingPage/WaterTabInfo"
import WaterDoughnut from "@/components/Dashboard/WaterDoughnut"
import WaterUsagePie from "@/components/Dashboard/WaterUsagePie"
import { Link } from "@/components/ui/link"
import { cn } from "@/lib/utils"
import {
	ArrowRight,
	BarChart3,
	Building2,
	CheckCircle2,
	Droplets,
	Receipt,
	ShieldCheck,
	Sparkles,
	UserPlus,
	Users,
	Wallet,
} from "lucide-react"

type WelcomeProps = {
	canRegister?: boolean
	activeSubscription?: {
		id?: string
		name?: string | null
		price?: SubscriptionPlanData["price"]
		maxUnits?: number
		billingCycle?: string
	} | null
}

const featureHighlights = [
	{
		icon: Building2,
		title: "Property command center",
		description:
			"Track buildings, units, and occupancy health from one place without switching tools.",
	},
	{
		icon: Users,
		title: "Occupancy visibility",
		description:
			"See vacancies, tenant movement, and leasing performance before they become revenue problems.",
	},
	{
		icon: Wallet,
		title: "Confident collections",
		description:
			"Rent, service charge, and utility performance stay visible with charts built for action.",
	},
	{
		icon: Droplets,
		title: "Utility control",
		description:
			"Monitor water usage and billing with the same level of clarity as rent operations.",
	},
	{
		icon: UserPlus,
		title: "Faster tenant acquisition",
		description:
			"Move from vacancy to onboarding with a workflow designed around property teams.",
	},
]

const workflowBenefits = [
	"Portfolio-wide visibility for properties and units.",
	"Live occupancy tracking across your portfolio.",
	"Rent, service charge, and water oversight from one dashboard.",
	"A cleaner path from vacancy to signed tenant.",
]

const subscriptionPlansFallback: SubscriptionPlanData[] = [
	{
		name: "Starter",
		description:
			"For individual landlords and small teams managing a focused portfolio.",
		features: [
			"Property and unit tracking",
			"Tenant onboarding and occupancy monitoring",
			"Rent and billing overview",
			"Water usage visibility",
		],
		price: {
			monthly: 4900,
			yearly: 49000,
		},
	},
	{
		name: "Growth",
		description:
			"For scaling portfolios that need clearer collections, workflows, and reporting.",
		features: [
			"Everything in Starter",
			"Advanced billing workflows",
			"Service charge management",
			"Portfolio-wide performance charts",
			"Tenant acquisition support",
		],
		price: {
			monthly: 9900,
			yearly: 99000,
		},
	},
	{
		name: "Portfolio",
		description:
			"For operators managing multiple properties with stricter operational demands.",
		features: [
			"Everything in Growth",
			"Multi-property oversight",
			"Deeper revenue visibility",
			"Priority onboarding assistance",
			"Custom operational rollout",
		],
		price: {
			monthly: 14900,
			yearly: 149000,
			onboarding_fee: 25000,
		},
	},
]

const Welcome: FC<WelcomeProps> = ({ activeSubscription = null }) => {
	const { data: subscriptionPlans = subscriptionPlansFallback } = useQuery({
		queryKey: ["subscription-plans"],
		queryFn: () =>
			Axios.get(subscriptionPlansRoute().url).then(
				(res) => res.data.data as SubscriptionPlanData[]
			),
		placeholderData: subscriptionPlansFallback,
	})

	const [billingCycle, setBillingCycle] = useState<BillingCycle>("monthly")

	const yearlyDiscountPercent = Math.round(
		(subscriptionPlans.reduce((sum, plan) => {
			const annualised = plan.price.monthly * 12
			return sum + (annualised - plan.price.yearly) / annualised
		}, 0) /
			Math.max(subscriptionPlans.length, 1)) *
			100
	)

	const magnifyHoverClass =
		"transition-transform duration-500 ease-[cubic-bezier(0.22,1,0.36,1)] hover:scale-[1.08]"

	const totalUnits = dashboardProperties.units.reduce(
		(total, units) => total + units,
		0
	)
	const [isCoarsePointer, setIsCoarsePointer] = useState(false)
	const [cursor, setCursor] = useState({
		x: 0,
		y: 0,
		visible: false,
	})

	useEffect(() => {
		const media = window.matchMedia("(pointer: coarse)")

		const updatePointerMode = () => {
			setIsCoarsePointer(media.matches)
		}

		updatePointerMode()
		media.addEventListener("change", updatePointerMode)

		return () => {
			media.removeEventListener("change", updatePointerMode)
		}
	}, [])

	useEffect(() => {
		if (isCoarsePointer) {
			return
		}

		const handleMouseMove = (event: MouseEvent) => {
			setCursor({
				x: event.clientX,
				y: event.clientY,
				visible: true,
			})
		}

		const handleMouseLeave = () => {
			setCursor((previous) => ({ ...previous, visible: false }))
		}

		window.addEventListener("mousemove", handleMouseMove)
		document.addEventListener("mouseleave", handleMouseLeave)

		return () => {
			window.removeEventListener("mousemove", handleMouseMove)
			document.removeEventListener("mouseleave", handleMouseLeave)
		}
	}, [isCoarsePointer])

	return (
		<div className="relative isolate min-h-screen overflow-hidden bg-background text-foreground">
			{!isCoarsePointer && (
				<div
					aria-hidden="true"
					className="pointer-events-none fixed z-50 hidden md:block border-white/80"
					style={{
						left: cursor.x,
						top: cursor.y,
						transform: "translate(-50%, -50%)",
						opacity: cursor.visible ? 0.8 : 0,
						transition:
							"left 420ms cubic-bezier(0.16,1,0.3,1), top 420ms cubic-bezier(0.16,1,0.3,1), opacity 320ms ease",
					}}>
					<div className="relative flex items-center justify-center">
						<div className="relative size-36 rounded-full border-2 border-white/70 bg-white/18 shadow-[0_30px_60px_-24px_rgba(15,23,42,0.75)] backdrop-blur-xl backdrop-saturate-150 dark:border-white/35 dark:bg-slate-900/22">
							<div className="absolute -inset-3 rounded-full bg-white/22 blur-2xl dark:bg-white/12" />
							<div className="relative z-10 flex size-full items-center justify-center overflow-hidden rounded-full border border-white/70 bg-white/26 text-center backdrop-blur-xl backdrop-saturate-200 dark:border-white/30 dark:bg-slate-900/20">
								<div className="absolute inset-0 bg-[radial-gradient(circle_at_32%_28%,rgba(255,255,255,0.78),transparent_42%),radial-gradient(circle_at_72%_72%,rgba(255,255,255,0.22),transparent_58%),linear-gradient(135deg,rgba(255,255,255,0.35),transparent_55%)] dark:bg-[radial-gradient(circle_at_32%_28%,rgba(255,255,255,0.34),transparent_42%),radial-gradient(circle_at_72%_72%,rgba(255,255,255,0.14),transparent_58%),linear-gradient(135deg,rgba(255,255,255,0.18),transparent_55%)]" />
							</div>
							<div className="absolute left-1/2 top-1/2 h-10 w-10 -translate-x-1/2 -translate-y-1/2 rounded-full border border-white/80 bg-white/24 backdrop-blur-sm dark:border-white/35 dark:bg-white/10" />
						</div>
					</div>
				</div>
			)}

			{/* START: Page Backdrop Elements */}
			<div className="pointer-events-none absolute inset-0 z-0 overflow-hidden">
				<div className="absolute -left-40 -top-28 h-80 w-80 rounded-full bg-primary/36 blur-3xl dark:bg-primary/28" />
				<div className="absolute -right-24 top-36 h-96 w-96 rounded-full bg-secondary/70 blur-3xl dark:bg-secondary/35" />
				<div className="absolute bottom-0 left-1/3 h-80 w-80 rounded-full bg-foreground/18 blur-3xl dark:bg-foreground/12" />
				<div className="bg-motion-drift bg-motion-delay-1 absolute left-[43%] top-[7%] h-44 w-44 -translate-x-1/2 rounded-[2.5rem] bg-white/58 dark:bg-white/22" />
				<div className="bg-motion-rotate bg-motion-delay-2 absolute right-[3%] top-[15%] h-36 w-36 rotate-12 rounded-3xl bg-secondary/85 dark:bg-secondary/40" />
				<div className="bg-motion-float bg-motion-delay-3 absolute bottom-[38%] left-[36%] h-48 w-48 -rotate-12 rounded-full bg-primary/46 dark:bg-primary/34" />
				<div className="bg-motion-drift absolute bottom-[12%] right-[20%] h-56 w-56 rounded-[3rem] bg-secondary/60 dark:bg-secondary/25" />
				{/* Brand Background Gradient Start */}
				<div className="absolute inset-0 bg-[radial-gradient(circle_at_12%_10%,rgba(194,24,91,0.18),transparent_35%),radial-gradient(circle_at_88%_14%,rgba(224,242,254,0.55),transparent_40%),linear-gradient(to_bottom,transparent,rgba(194,24,91,0.08),transparent)] dark:bg-[radial-gradient(circle_at_12%_10%,rgba(194,24,91,0.22),transparent_35%),radial-gradient(circle_at_88%_14%,rgba(224,242,254,0.22),transparent_40%),linear-gradient(to_bottom,transparent,rgba(194,24,91,0.12),transparent)]" />
				{/* Brand Background Gradient End */}
				<div className="absolute inset-0 bg-[radial-gradient(rgba(255,255,255,0.75)_1px,transparent_1px)] bg-size-[16px_16px] opacity-[0.12] dark:opacity-[0.05]" />
				<div className="absolute inset-0 bg-[linear-gradient(120deg,rgba(255,255,255,0.32)_0%,transparent_30%,rgba(148,163,184,0.2)_50%,transparent_70%,rgba(255,255,255,0.22)_100%)] opacity-[0.26] dark:opacity-[0.12]" />
				<div className="absolute inset-0 bg-[repeating-linear-gradient(45deg,rgba(15,23,42,0.1)_0_1px,transparent_1px_12px)] opacity-[0.5] dark:opacity-[0.12]" />
				<div className="absolute inset-0 bg-[repeating-linear-gradient(135deg,rgba(15,23,42,0.1)_0_1px,transparent_1px_12px)] opacity-[0.5] dark:opacity-[0.12]" />
			</div>
			{/* END: Page Backdrop Elements */}

			{/* START: Hero Section */}
			<section
				data-cursor-label="Portfolio overview"
				className="relative z-10 mx-auto max-w-7xl px-4 pb-18 pt-10 sm:px-6 lg:px-8 lg:pb-24 lg:pt-16">
				<div className="grid gap-10 lg:grid-cols-[1.1fr_0.9fr] lg:items-center">
					<div className="space-y-8">
						<div className="inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/8 px-4 py-2 text-sm text-primary shadow-sm backdrop-blur-sm">
							<Sparkles className="size-4" />
							<span>Built for modern property operations</span>
						</div>

						<div className="space-y-5">
							<h1 className="max-w-4xl text-4xl font-semibold tracking-tight text-balance sm:text-5xl lg:text-6xl">
								<span className="bg-linear-to-r from-primary via-foreground to-primary bg-clip-text text-transparent dark:via-white">
									Property management. Beautifully reimagined.
								</span>
							</h1>
							<p className="max-w-2xl text-lg leading-8 text-muted-foreground sm:text-xl">
								From tenant onboarding to final billing, experience property
								management that just works.
							</p>
							<p className="max-w-xl text-base leading-7 text-muted-foreground/90">
								Operate your portfolio with live occupancy insight, cleaner
								utility tracking, and revenue visibility that feels intentional
								on both light and dark surfaces.
							</p>
						</div>

						<div className="flex flex-wrap gap-3">
							<Link
								href="/register"
								variant="solid"
								size="lg"
								className={`capitalize shadow-lg shadow-primary/20 ${magnifyHoverClass}`}>
								start now
								<ArrowRight className="size-4" />
							</Link>
							<Link
								href="#platform"
								variant="outline"
								size="lg"
								className={`border-border/70 bg-background/70 backdrop-blur-sm ${magnifyHoverClass}`}>
								explore the platform
							</Link>
						</div>

						<div className="grid gap-3 sm:grid-cols-3">
							<GlassCard
								className={`p-4 ${magnifyHoverClass}`}>
								<p className="text-xs uppercase tracking-[0.24em] text-muted-foreground">
									Portfolio coverage
								</p>
								<p className="mt-2 text-3xl font-semibold text-primary">
									{dashboardProperties.total}
								</p>
								<p className="mt-1 text-sm text-muted-foreground">
									properties in one view
								</p>
							</GlassCard>
							<GlassCard
								className={`p-4 ${magnifyHoverClass}`}>
								<p className="text-xs uppercase tracking-[0.24em] text-muted-foreground">
									Occupancy health
								</p>
								<p className="mt-2 text-3xl font-semibold text-primary">
									{dashboard.units.percentage}%
								</p>
								<p className="mt-1 text-sm text-muted-foreground">
									occupied units right now
								</p>
							</GlassCard>
							<GlassCard
								className={`p-4 ${magnifyHoverClass}`}>
								<p className="text-xs uppercase tracking-[0.24em] text-muted-foreground">
									Collections rate
								</p>
								<p className="mt-2 text-3xl font-semibold text-primary">
									{dashboard.rent.percentage}%
								</p>
								<p className="mt-1 text-sm text-muted-foreground">
									rent paid this year
								</p>
							</GlassCard>
						</div>
					</div>

					<div
						className="relative"
						data-cursor-label="Live property overview">
						{/* START: First Card Local Backdrop Elements */}
						<div className="pointer-events-none absolute -inset-x-8 -inset-y-10 z-0 overflow-visible">
							<div className="bg-motion-float bg-motion-delay-1 absolute left-[2%] top-[8%] h-32 w-32 rounded-[1.6rem] bg-white/62 dark:bg-white/24" />
							<div className="bg-motion-rotate bg-motion-delay-2 absolute -right-[2%] top-[14%] h-28 w-28 rotate-12 rounded-2xl bg-sky-200/66 dark:bg-sky-400/32" />
							<div className="bg-motion-drift bg-motion-delay-3 absolute bottom-[14%] left-[14%] h-36 w-36 -rotate-12 rounded-full bg-primary/48 dark:bg-primary/36" />
							<div className="bg-motion-float absolute -bottom-[2%] right-[4%] h-40 w-40 rounded-[2.1rem] bg-slate-200/58 dark:bg-slate-300/26" />
						</div>
						{/* END: First Card Local Backdrop Elements */}

						<Card
							className={`relative z-10 overflow-hidden ${magnifyHoverClass}`}>
							<CardHeader className="relative z-10 space-y-5 pb-3">
								<div className="flex items-center justify-between gap-3">
									<div>
										<p className="text-xs uppercase tracking-[0.24em] text-muted-foreground">
											Live overview
										</p>
										<CardTitle className="mt-2 text-2xl">
											Property Overview
										</CardTitle>
									</div>
									<div className="rounded-2xl border border-primary/20 bg-primary/10 p-3 text-primary">
										<BarChart3 className="size-6" />
									</div>
								</div>
								<div className="grid gap-3 sm:grid-cols-2">
									<GlassInner className="p-4">
										<p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
											Total properties
										</p>
										<p className="mt-2 text-3xl font-semibold text-primary">
											{dashboardProperties.total}
										</p>
									</GlassInner>
									<GlassInner className="p-4">
										<p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
											Total units
										</p>
										<p className="mt-2 text-3xl font-semibold text-emerald-600 dark:text-emerald-400">
											{totalUnits}
										</p>
									</GlassInner>
								</div>
							</CardHeader>
							<CardContent className="relative z-10 space-y-5">
								<GlassInner className="rounded-3xl p-4">
									<PropertyDoughnut dashboardProperties={dashboardProperties} />
								</GlassInner>
								<div className="grid gap-3 sm:grid-cols-2">
									<GlassInner className="p-4">
										<p className="text-sm font-medium">Active properties</p>
										<p className="mt-2 text-sm leading-6 text-muted-foreground">
											{dashboardProperties.names.join(", ")}
										</p>
									</GlassInner>
									<GlassInner className="p-4">
										<p className="text-sm font-medium">Occupancy snapshot</p>
										<p className="mt-2 text-2xl font-semibold text-primary">
											{dashboard.units.totalOccupied}
										</p>
										<p className="text-sm text-muted-foreground">
											occupied units across the portfolio
										</p>
									</GlassInner>
								</div>
							</CardContent>
						</Card>

						<GlassCard
							className={`absolute -bottom-6 -left-6 z-20 hidden w-56 p-4 lg:block ${magnifyHoverClass}`}>
							<div className="flex items-start gap-3">
								<div className="rounded-2xl bg-primary/10 p-2 text-primary">
									<ShieldCheck className="size-5" />
								</div>
								<div>
									<p className="text-sm font-medium">Operator clarity</p>
									<p className="mt-1 text-sm text-muted-foreground">
										Metrics and charts stay readable in both light and dark
										mode.
									</p>
								</div>
							</div>
						</GlassCard>
					</div>
				</div>
			</section>
			{/* END: Hero Section */}

			{/* START: Feature Highlights Section */}
			<section
				data-cursor-label="Property feature intelligence"
				className="relative z-10 border-y border-white/25 bg-white/38 py-16 backdrop-blur-xl dark:border-white/10 dark:bg-slate-950/28">
				<div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
					<div className="mx-auto max-w-3xl text-center">
						<p className="text-sm font-medium uppercase tracking-[0.28em] text-primary">
							Everything Property Management. One Platform
						</p>
						<h2 className="mt-4 text-3xl font-semibold tracking-tight sm:text-4xl">
							Designed for the realities of running property portfolios.
						</h2>
						<p className="mt-4 text-lg leading-8 text-muted-foreground">
							Take inspiration from product-grade landing pages without losing
							the practical focus a property management system needs.
						</p>
					</div>

					<div className="mt-10 grid gap-6 md:grid-cols-2 xl:grid-cols-5">
						{featureHighlights.map(({ icon: Icon, title, description }) => (
							<GlassCard
								key={title}
								className="group p-6 transition-transform duration-500 ease-[cubic-bezier(0.22,1,0.36,1)] hover:-translate-y-1 hover:scale-[1.08] hover:shadow-lg">
								<div className="flex size-12 items-center justify-center rounded-2xl bg-primary/10 text-primary">
									<Icon className="size-5" />
								</div>
								<h3 className="mt-5 text-lg font-semibold">{title}</h3>
								<p className="mt-3 text-sm leading-6 text-muted-foreground">
									{description}
								</p>
							</GlassCard>
						))}
					</div>
				</div>
			</section>
			{/* END: Feature Highlights Section */}

			{/* START: Platform Walkthrough Section */}
			<section
				data-cursor-label="Workflow analytics"
				id="platform"
				className="relative z-10 mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8 lg:py-20">
				<div className="mb-10 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
					<div className="max-w-3xl">
						<p className="text-sm font-medium uppercase tracking-[0.28em] text-primary">
							Platform walkthrough
						</p>
						<h2 className="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">
							Five workflows, one coherent property operating system.
						</h2>
						<p className="mt-4 text-lg leading-8 text-muted-foreground">
							Each area keeps the same visual language while surfacing the
							numbers that matter most to property teams.
						</p>
					</div>
					<GlassCard
						className={`px-5 py-4 ${magnifyHoverClass}`}>
						<p className="text-xs uppercase tracking-[0.22em] text-muted-foreground">
							Portfolio metrics
						</p>
						<p className="mt-2 text-2xl font-semibold text-primary">
							{dashboard.serviceCharge.percentage}%
						</p>
						<p className="text-sm text-muted-foreground">
							service charge collection performance
						</p>
					</GlassCard>
				</div>

				<div className="grid gap-6 xl:grid-cols-2">
					<Card className={`overflow-hidden ${magnifyHoverClass}`}>
						<CardHeader className="pb-4">
							<div className="flex items-center gap-3">
								<div className="rounded-2xl bg-primary/10 p-3 text-primary">
									<Building2 className="size-5" />
								</div>
								<div>
									<p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
										Foundation
									</p>
									<CardTitle className="mt-1">Property Management</CardTitle>
								</div>
							</div>
						</CardHeader>
						<CardContent className="space-y-6">
							<PropertyTabInfo />
							<GlassInner className="rounded-3xl p-4">
								<IncomeBar dashboard={dashboard} />
							</GlassInner>
						</CardContent>
					</Card>

					<Card className={`overflow-hidden ${magnifyHoverClass}`}>
						<CardHeader className="pb-4">
							<div className="flex items-center gap-3">
								<div className="rounded-2xl bg-primary/10 p-3 text-primary">
									<Users className="size-5" />
								</div>
								<div>
									<p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
										Occupancy
									</p>
									<CardTitle className="mt-1">Occupancy Management</CardTitle>
								</div>
							</div>
						</CardHeader>
						<CardContent className="space-y-6">
							<OccupancyTabInfo />
							<GlassInner className="rounded-3xl p-4">
								<TenancyBar dashboard={dashboard} />
							</GlassInner>
							<GlassInner className="rounded-3xl p-4">
								<TenancyDoughnut
									dashboard={dashboard}
									dashboardProperties={dashboardProperties}
								/>
							</GlassInner>
						</CardContent>
					</Card>

					<Card
						className={`overflow-hidden xl:col-span-2 ${magnifyHoverClass}`}>
						<CardHeader className="pb-4">
							<div className="flex items-center gap-3">
								<div className="rounded-2xl bg-primary/10 p-3 text-primary">
									<Receipt className="size-5" />
								</div>
								<div>
									<p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
										Revenue
									</p>
									<CardTitle className="mt-1">Billing</CardTitle>
								</div>
							</div>
						</CardHeader>
						<CardContent className="space-y-6">
							<BillingTabInfo />
							<div className="grid gap-4 lg:grid-cols-3">
								<GlassInner className="rounded-3xl p-4">
									<RentDoughnut dashboard={dashboard} />
								</GlassInner>
								<GlassInner className="rounded-3xl p-4">
									<WaterDoughnut dashboard={dashboard} />
								</GlassInner>
								<GlassInner className="rounded-3xl p-4">
									<ServiceChargeDoughnut dashboard={dashboard} />
								</GlassInner>
							</div>
						</CardContent>
					</Card>

					<Card className={`overflow-hidden ${magnifyHoverClass}`}>
						<CardHeader className="pb-4">
							<div className="flex items-center gap-3">
								<div className="rounded-2xl bg-primary/10 p-3 text-primary">
									<Droplets className="size-5" />
								</div>
								<div>
									<p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
										Utilities
									</p>
									<CardTitle className="mt-1">Water Management</CardTitle>
								</div>
							</div>
						</CardHeader>
						<CardContent className="space-y-6">
							<WaterTabInfo />
							<GlassInner className="rounded-3xl p-4">
								<WaterUsagePie dashboard={dashboard} />
							</GlassInner>
						</CardContent>
					</Card>

					<Card className={`overflow-hidden ${magnifyHoverClass}`}>
						<CardHeader className="pb-4">
							<div className="flex items-center gap-3">
								<div className="rounded-2xl bg-primary/10 p-3 text-primary">
									<UserPlus className="size-5" />
								</div>
								<div>
									<p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
										Growth
									</p>
									<CardTitle className="mt-1">Tenant Acquisition</CardTitle>
								</div>
							</div>
						</CardHeader>
						<CardContent className="space-y-6">
							<TenantTabInfo />
							<GlassInner className="rounded-3xl p-4">
								<TenancyDoughnut
									dashboard={dashboard}
									dashboardProperties={dashboardProperties}
								/>
							</GlassInner>
						</CardContent>
					</Card>
				</div>
			</section>
			{/* END: Platform Walkthrough Section */}

			{/* START: Pricing Section */}
			<section
				data-cursor-label="Subscription plans"
				className="relative z-10 mx-auto max-w-7xl px-4 pb-20 sm:px-6 lg:px-8">
				<div className="mb-8 max-w-3xl">
					<p className="text-sm font-medium uppercase tracking-[0.28em] text-primary">
						Pricing
					</p>
					<h2 className="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">
						Choose a plan that fits the way you manage property.
					</h2>
					<p className="mt-4 text-lg leading-8 text-muted-foreground">
						Simple, transparent pricing for landlords, agents, and property
						managers. No hidden fees, just the tools you need to run a tighter
						portfolio.
					</p>
				</div>

				<Card
					className={`overflow-hidden bg-linear-to-br from-primary/18 via-white/52 to-white/36 shadow-xl dark:from-primary/20 dark:via-slate-950/40 dark:to-slate-950/28 ${magnifyHoverClass}`}>
					<CardContent className="grid gap-8 p-8 lg:grid-cols-[1fr_auto] lg:items-center lg:p-10">
						<div>
							<p className="text-sm font-medium uppercase tracking-[0.28em] text-primary">
								Everything in one place
							</p>
							<h2 className="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">
								Manage your properties with confidence, from rent collection to
								tenant screening.
							</h2>
							<p className="mt-4 max-w-2xl text-lg leading-8 text-muted-foreground">
								Whether you own one unit or a hundred, Black Property gives you
								the tools to stay organised, collect on time, and keep your
								tenants happy.
							</p>
							<div className="mt-6 grid gap-3 sm:grid-cols-2">
								{workflowBenefits.map((benefit) => (
									<GlassInner
										key={benefit}
										className={`flex items-start gap-3 p-4 ${magnifyHoverClass}`}>
										<CheckCircle2 className="mt-0.5 size-5 shrink-0 text-primary" />
										<p className="text-sm leading-6 text-foreground/90">
											{benefit}
										</p>
									</GlassInner>
								))}
							</div>
						</div>

						<div className="flex flex-col gap-3 lg:min-w-64">
							<Link
								href="/admin/dashboard"
								variant="solid"
								size="lg"
								className={`w-full justify-center capitalize ${magnifyHoverClass}`}>
								launch dashboard
								<ArrowRight className="size-4" />
							</Link>
							<Link
								href="#platform"
								variant="outline"
								size="lg"
								className={`w-full justify-center capitalize ${magnifyHoverClass}`}>
								review modules
							</Link>
						</div>
					</CardContent>
				</Card>

				<div className="mt-8 flex flex-col items-center gap-6">
					<div className="inline-flex items-center rounded-full border border-border/60 bg-muted/50 p-1">
						<button
							onClick={() => setBillingCycle("monthly")}
							className={cn(
								"cursor-pointer rounded-full px-5 py-1.5 text-sm font-medium transition-colors",
								billingCycle === "monthly"
									? "bg-background text-foreground shadow-sm"
									: "text-muted-foreground hover:text-foreground"
							)}>
							Monthly
						</button>
						<button
							onClick={() => setBillingCycle("yearly")}
							className={cn(
								"flex cursor-pointer items-center gap-2 rounded-full px-5 py-1.5 text-sm font-medium transition-colors",
								billingCycle === "yearly"
									? "bg-background text-foreground shadow-sm"
									: "text-muted-foreground hover:text-foreground"
							)}>
							Yearly
							<span className="rounded-full bg-emerald-500/15 px-2 py-0.5 text-xs font-semibold text-emerald-600 dark:text-emerald-400">
								Save {yearlyDiscountPercent}%
							</span>
						</button>
					</div>

					<div className="grid w-full gap-6 xl:grid-cols-3">
						{subscriptionPlans.map((subscriptionPlan, planIndex) => (
							<SubscriptionPlan
								key={subscriptionPlan.name}
								auth={{ activeSubscription }}
								subscriptionPlan={subscriptionPlan}
								billingCycle={billingCycle}
								featured={planIndex === 1}
								label={planIndex === 1 ? "start growing" : "choose plan"}
								href="/register"
							/>
						))}
					</div>
				</div>
			</section>
			{/* END: Pricing Section */}
		</div>
	)
}

export default Welcome
