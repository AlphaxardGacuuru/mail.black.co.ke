import { Check, Sparkles } from "lucide-react"

import { Button } from "@/components/ui/button"
import { Link } from "@/components/ui/link"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Spinner } from "@/components/ui/spinner"
import { cn } from "@/lib/utils"

export type BillingCycle = "monthly" | "yearly"

export type SubscriptionPlanData = {
	name: string
	description: string
	features: string[]
	price: {
		monthly: number
		yearly: number
		onboarding_fee?: number
	}
}

type SubscriptionPlanProps = {
	auth?: {
		activeSubscription?: {
			name?: string | null
		} | null
	}
	subscriptionPlan: SubscriptionPlanData
	isCurrentPlan?: boolean
	billingCycle?: BillingCycle
	href?: string
	label?: string
	variant?:
		| "default"
		| "destructive"
		| "outline"
		| "secondary"
		| "ghost"
		| "transparent"
		| "link"
	className?: string
	onClick?: () => void
	loading?: boolean
	disabled?: boolean
	featured?: boolean
	containerClassName?: string
}

const formatCurrency = (amount: number): string => amount.toLocaleString()

const SubscriptionPlan = ({
	subscriptionPlan,
	isCurrentPlan,
	billingCycle = "monthly",
	href = "/admin/subscribe",
	label = "change",
	className,
	onClick,
	loading = false,
	disabled = false,
	featured = false,
	containerClassName,
}: SubscriptionPlanProps) => {
	const price =
		billingCycle === "yearly"
			? subscriptionPlan.price.yearly
			: subscriptionPlan.price.monthly
	const monthlyEquivalent = Math.round(subscriptionPlan.price.yearly / 12)

	return (
		<Card
			className={cn(
				"relative flex h-full flex-col overflow-hidden bg-card/85 shadow-sm transition-transform duration-500 ease-[cubic-bezier(0.22,1,0.36,1)] hover:scale-[1.08]",
				isCurrentPlan &&
					"border-primary/80 shadow-xl bg-linear-to-br from-primary/10 via-card to-card",
				featured && "bg-linear-to-br from-primary/10 via-card to-card",
				className,
				containerClassName
			)}>
			<div
				className={cn(
					"pointer-events-none absolute inset-x-0 top-0 h-24 bg-linear-to-b to-transparent from-primary/8"
				)}
			/>
			<CardHeader className="relative space-y-4 pb-4">
				<div className="flex items-start justify-between gap-3">
					<div className="space-y-2">
						<div className="inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/10 px-3 py-1 text-xs font-medium uppercase tracking-[0.22em] text-primary min-h-6 min-w-30">
							{isCurrentPlan ? (
								<div className="flex items-center gap-2">
									<Check className="size-3.5" />
									Active plan
								</div>
							) : featured ? (
								<div className="flex items-center gap-2">
									<Sparkles className="size-3.5" />
									Most popular
								</div>
							) : null}
						</div>
						<CardTitle className="text-2xl">{subscriptionPlan.name}</CardTitle>
						<p className="max-w-sm text-sm leading-6 text-muted-foreground">
							{subscriptionPlan.description}
						</p>
					</div>
				</div>
			</CardHeader>

			<CardContent className="relative flex flex-1 flex-col gap-6">
				<div className="rounded-2xl border border-border/60 bg-background/70 p-4">
					<p className="mt-1 text-3xl font-semibold text-primary">
						KES {formatCurrency(price)}
					</p>
					{billingCycle === "yearly" ? (
						<p className="mt-1 text-sm text-muted-foreground">
							per year{" "}
							<span className="text-emerald-600 dark:text-emerald-400">
								(KES {formatCurrency(monthlyEquivalent)}/mo)
							</span>
						</p>
					) : (
						<p className="mt-1 text-sm text-muted-foreground">per month</p>
					)}
				</div>

				<div className="space-y-3">
					<p className="text-sm font-medium uppercase tracking-[0.22em] text-muted-foreground">
						What you get
					</p>
					<ul className="space-y-3">
						{subscriptionPlan.features.map((feature) => (
							<li
								key={feature}
								className="flex items-start gap-3 text-sm leading-6 text-foreground/90">
								<span className="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-emerald-500/12 text-emerald-600 dark:text-emerald-400">
									<Check className="size-3.5" />
								</span>
								<span>{feature}</span>
							</li>
						))}
					</ul>
				</div>

				{/* {subscriptionPlan.price.onboarding_fee ? (
					<div className="rounded-2xl border border-dashed border-border/70 bg-background/60 p-4 text-sm text-muted-foreground">
						Onboarding fee: KES {formatCurrency(subscriptionPlan.price.onboarding_fee)}
					</div>
				) : null} */}

				<div className="mt-auto pt-2">
					{onClick ? (
						<Button
							type="button"
							variant={isCurrentPlan ? "default" : "outline"}
							size="lg"
							className={"w-full justify-center capitalize"}
							onClick={onClick}
							disabled={disabled || loading || isCurrentPlan}>
							{loading && <Spinner className="size-4" />}
							{isCurrentPlan ? "Current Subscription" : label}
						</Button>
					) : (
						<Link
							href={href}
							variant={featured || isCurrentPlan ? "solid" : "outline"}
							size="lg"
							className="w-full justify-center capitalize">
							{label}
						</Link>
					)}
				</div>
			</CardContent>
		</Card>
	)
}

export default SubscriptionPlan
