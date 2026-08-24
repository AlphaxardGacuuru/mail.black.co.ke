import { useEffect, useMemo, useRef, useState } from "react"
import type { FormEvent } from "react"
import { useQueryClient } from "@tanstack/react-query"
import { useNavigate } from "@tanstack/react-router"
import { ArrowLeft, ArrowRight, Loader2, Sparkles } from "lucide-react"
import SubscriptionPlan from "@/components/SubscriptionPlan/SubscriptionPlan"
import type {
	BillingCycle,
	SubscriptionPlanData,
} from "@/components/SubscriptionPlan/SubscriptionPlan"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import {
	Card,
	CardContent,
	CardFooter,
	CardHeader,
	CardTitle,
} from "@/components/ui/card"
import { useApp } from "@/contexts/AppContext"
import { Head } from "@/lib/spa"
import { Input } from "@/components/ui/input"
import { Skeleton } from "@/components/ui/skeleton"
import { Spinner } from "@/components/ui/spinner"
import axios from "@/lib/axios"
import { cn } from "@/lib/utils"
import toast from "@/lib/toast"
import { useEcho } from "@laravel/echo-react"

type SubscriptionPlanRecord = SubscriptionPlanData & {
	id?: number | string
}

type AuthUser = {
	id: number | string
	name: string
	phone?: string | null
	activeSubscription?: {
		id?: number | string
		name?: string | null
	} | null
	emailVerifiedAt?: string | null
	email_verified_at?: string | null
}

type FormError = {
	field?: string
	message: string
}

type PageProps = {
	auth: AuthUser
	formErrors?: FormError[]
	setPage?: (page: { name: string; path: string[] }) => void
}

const PRODUCTION_HOSTNAME = "property.black.co.ke"

const billingCycleLabels: Record<BillingCycle, string> = {
	monthly: "Monthly",
	yearly: "Yearly",
}

function formatCurrency(amount: number): string {
	return amount.toLocaleString()
}

function isVerified(auth: AuthUser): boolean {
	return Boolean(auth.emailVerifiedAt ?? auth.email_verified_at)
}

export default function Subscribe({
	auth: initialAuth,
	formErrors = [],
	setPage,
}: PageProps) {
	const { auth: globalAuth, setLocalStorage } = useApp()
	const queryClient = useQueryClient()
	const navigate = useNavigate()
	const auth = (globalAuth as AuthUser | undefined) ?? initialAuth

	const [plans, setPlans] = useState<SubscriptionPlanRecord[]>([])
	const [billingCycle, setBillingCycle] = useState<BillingCycle>("monthly")
	const [wizardStep, setWizardStep] = useState<1 | 2 | 3>(1)
	const [selectedPlanId, setSelectedPlanId] = useState<number | string | null>(
		null
	)
	const [loadingPlans, setLoadingPlans] = useState(true)
	const [savingPlanId, setSavingPlanId] = useState<number | string | null>(null)
	const [phone, setPhone] = useState(auth.phone ?? "")
	const [updatingPhone, setUpdatingPhone] = useState(false)
	const [paying, setPaying] = useState(false)
	const [simulating, setSimulating] = useState(false)
	const [waitingForPayment, setWaitingForPayment] = useState(false)

	const mountedRef = useRef(true)
	const awaitingPaymentRef = useRef(false)
	const stkLocationRef = useRef<string | null>(null)
	const plansSectionRef = useRef<HTMLDivElement | null>(null)

	const currentPlan = useMemo(
		() => plans.find((plan) => plan.id === auth.activeSubscription?.id) ?? null,
		[auth.activeSubscription?.id, plans]
	)

	const yearlyDiscountPercent = Math.round(
		(plans.reduce((sum, plan) => {
			const annualised = plan.price.monthly * 12
			return sum + (annualised - plan.price.yearly) / annualised
		}, 0) /
			Math.max(plans.length, 1)) *
			100
	)

	const selectedPlan = useMemo(
		() =>
			plans.find(
				(plan) =>
					selectedPlanId !== null && String(plan.id) === String(selectedPlanId)
			) ?? null,
		[plans, selectedPlanId]
	)

	const effectivePlan = selectedPlan ?? currentPlan
	const phoneError = formErrors.find(
		(error) => error.field === "phone"
	)?.message
	const hasSelectedPlan = Boolean(selectedPlan)
	const canProceedToPhone = hasSelectedPlan
	const canProceedToPayment =
		hasSelectedPlan && Boolean(auth.phone) && isVerified(auth)

	const wizardSteps = [
		{
			id: 1 as const,
			label: "Choose plan",
			description: "Pick a billing cycle and select a subscription.",
			completed: hasSelectedPlan,
		},
		{
			id: 2 as const,
			label: "Confirm phone",
			description: "Update the number that will receive the STK prompt.",
			completed: Boolean(auth.phone),
		},
		{
			id: 3 as const,
			label: "Review & pay",
			description: "Confirm the details and start payment.",
			completed: Boolean(effectivePlan && auth.phone && isVerified(auth)),
		},
	] satisfies Array<{
		id: 1 | 2 | 3
		label: string
		description: string
		completed: boolean
	}>

	useEffect(() => {
		if (!auth?.id) {
			return
		}

		mountedRef.current = true
		setPage?.({ name: "Subscribe", path: ["dashboard", "subscribe"] })

		const loadPlans = () => {
			setLoadingPlans(true)

			axios
				.get("api/subscription-plans")
				.then((plansResponse) => {
					const fetchedPlans = plansResponse.data
						.data as SubscriptionPlanRecord[]

					if (!mountedRef.current) {
						return
					}

					setPlans(fetchedPlans)

					return axios.get(
						`api/user-subscription-plans?userId=${auth.id}&status=pending`
					)
				})
				.then((pendingResponse) => {
					const pendingPlan = pendingResponse?.data.data?.[0] as
						| { subscriptionPlanId?: number | string }
						| undefined

					if (pendingPlan?.subscriptionPlanId !== undefined) {
						setSelectedPlanId(pendingPlan.subscriptionPlanId)
					} else {
						setSelectedPlanId(null)
					}
				})
				.catch(() => {
					setSelectedPlanId(null)
				})
				.catch(() => {
					if (mountedRef.current) {
						toast.error("Failed to load subscription plans.")
					}
				})
				.finally(() => {
					if (mountedRef.current) {
						setLoadingPlans(false)
					}
				})
		}

		loadPlans()

		return () => {
			mountedRef.current = false
			awaitingPaymentRef.current = false
		}
	}, [auth?.id, setPage])

	useEffect(() => {
		setPhone(auth.phone ?? "")
	}, [auth.phone])

	function refreshAuth() {
		return axios.get("/api/auth").then((response) => {
			const nextAuth = response.data.data as AuthUser

			queryClient.setQueryData(["auth"], nextAuth)
			setLocalStorage("auth", nextAuth)

			return nextAuth
		})
	}

	function handlePlanSelection(plan: SubscriptionPlanRecord) {
		const isSelected =
			selectedPlanId !== null && String(selectedPlanId) === String(plan.id)
		const nextSelectedId = isSelected ? null : (plan.id ?? null)

		setSavingPlanId(plan.id ?? null)

		const duration = billingCycle === "yearly" ? 12 : 1
		axios
			.post("/api/user-subscription-plans", {
				userId: auth.id,
				subscriptionPlanId: plan.id,
				duration,
				type: "paid",
				save: !isSelected,
			})
			.then((response) => {
				setSelectedPlanId(nextSelectedId)
				setWizardStep(nextSelectedId ? 2 : 1)
				const message = response.data.message ?? "Subscription plan updated."
				toast.success(message, { duration: 6000 })
			})
			.catch(() => {
				toast.error("Failed to save this subscription plan.")
			})
			.finally(() => {
				if (mountedRef.current) {
					setSavingPlanId(null)
				}
			})
	}

	function goToStep(step: 1 | 2 | 3): void {
		if (step === 2 && !canProceedToPhone) {
			toast.error("Choose a subscription plan first.")
			return
		}

		if (step === 3 && !canProceedToPayment) {
			toast.error("Confirm your phone number before continuing.")
			return
		}

		setWizardStep(step)
	}

	function handleUpdatePhone(event: FormEvent<HTMLFormElement>) {
		event.preventDefault()
		setUpdatingPhone(true)

		axios
			.put(`/api/users/${auth.id}`, {
				phone,
			})
			.then((response) => {
				const message = response.data.message ?? "Phone number updated."
				toast.success(message)
				return refreshAuth()
			})
			.catch((error) => {
				const backendMessage =
					typeof error === "object" &&
					error !== null &&
					"response" in error &&
					typeof (error as { response?: { data?: { message?: unknown } } })
						.response?.data?.message === "string"
						? ((error as { response?: { data?: { message?: string } } })
								.response?.data?.message ?? "")
						: ""
				const message = backendMessage || "Failed to update phone number."

				toast.error(message)
			})
			.finally(() => {
				if (mountedRef.current) {
					setUpdatingPhone(false)
				}
			})
	}

	function handleSimulatePayment() {
		setSimulating(true)

		axios
			.post("/api/mpesa-transactions", {
				data: {
					id: "49b2bf39-0bff-4f37-8b19-43ca21ab3bf2",
					type: "incoming_payment",
					attributes: {
						initiation_time: "2020-10-21T09:30:34.331+03:00",
						status: "Success",
						event: {
							type: "Incoming Payment Request",
							resource: {
								id: "f39-0bff-44ef4-0629-481f-83cd-d101f",
								reference: "OJL7OW3J59",
								origination_time: "2020-10-21T09:30:40+03:00",
								sender_phone_number: "+254700364446",
								amount: "5000.0",
								currency: "KES",
								till_number: "K000000",
								system: "Lipa Na M-PESA",
								status: "Received",
								sender_first_name: "Joe",
								sender_middle_name: null,
								sender_last_name: "Buyer",
							},
							errors: null,
						},
						metadata: {
							customer_id: "123456789",
							reference: "123456",
							notes: "Payment for invoice 12345",
						},
						_links: {
							callback_url:
								"https://webhook.site/675d4ef4-0629-481f-83cd-d101f55e4bc8",
							self: "https://sandbox.kopokopo.com/api/v1/incoming_payments/49b2bf39-0bff-4f37-8b19-43ca21ab3bf2",
						},
					},
				},
			})
			.then((response) => {
				const message = response.data.message ?? "Payment simulated."
				toast.success(message)
				return refreshAuth()
			})
			.catch(() => {
				toast.error("Failed to simulate payment.")
			})
			.finally(() => {
				if (mountedRef.current) {
					setSimulating(false)
				}
			})
	}

	useEcho(
		`mpesa-transaction-created.${auth.id}`,
		"MpesaTransactionCreatedEvent",
		(event: { mpesaTransaction?: { status?: string; errors?: string } }) => {
			if (!mountedRef.current || !awaitingPaymentRef.current) {
				return
			}

			const paymentState = event.mpesaTransaction?.status

			if (paymentState === "Failed") {
				awaitingPaymentRef.current = false
				setWaitingForPayment(false)
				const message =
					event.mpesaTransaction?.errors ?? "The payment request failed."
				toast.error(message)
				return
			}

			refreshAuth()
				.then((refreshedAuth) => {
					if (!mountedRef.current || !awaitingPaymentRef.current) {
						return
					}

					if (!refreshedAuth?.activeSubscription?.id) {
						return
					}

					awaitingPaymentRef.current = false
					setWaitingForPayment(false)
					toast.success(
						"Payment Received. Subscription Activated Successfully."
					)

					window.setTimeout(() => {
						navigate({ to: "/mail" })
					}, 2500)
				})
				.catch(() => {
					if (mountedRef.current) {
						toast.error("Failed to refresh your subscription status.")
					}
				})
		}
	)

	function handleStartPayment() {
		if (!selectedPlan) {
			toast.error("Choose a subscription plan first.")
			return
		}

		if (!auth.phone) {
			toast.error("Please update your phone number first.")
			return
		}

		if (!isVerified(auth)) {
			toast.error("Verify your email address before completing payment.")
			return
		}

		const amount =
			billingCycle === "yearly"
				? selectedPlan.price.yearly
				: selectedPlan.price.monthly

		setPaying(true)
		setWaitingForPayment(true)
		awaitingPaymentRef.current = true
		stkLocationRef.current = null

		axios
			.post("/api/stk-push", {
				amount,
			})
			.then((response) => {
				const location = response.data.data?.location ?? null
				stkLocationRef.current = location
				const message =
					response.data.message ?? "Payment request sent to your phone."
				toast.success(message)
			})
			.catch(() => {
				awaitingPaymentRef.current = false
				setWaitingForPayment(false)
				toast.error("Failed to send the payment request.")
			})
			.finally(() => {
				if (mountedRef.current) {
					setPaying(false)
				}
			})
	}

	return (
		<>
			<Head title="Subscribe" />

			<div className="space-y-8">
				<section className="relative overflow-hidden rounded-3xl border border-border/60 bg-card/80 p-6 shadow-sm backdrop-blur-sm sm:p-8">
					<div className="pointer-events-none absolute inset-x-0 top-0 h-24 bg-linear-to-b from-primary/10 to-transparent" />
					<div className="relative flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
						<div className="max-w-3xl space-y-4">
							<Badge
								variant="secondary"
								className="w-fit gap-1.5">
								<Sparkles className="size-3.5" />
								Subscription
							</Badge>
							<div className="space-y-2">
								<h1 className="text-3xl font-semibold tracking-tight sm:text-4xl">
									Choose a plan that fits your portfolio.
								</h1>
								<p className="max-w-2xl text-sm leading-6 text-muted-foreground">
									Compare billing cycles, pick a plan, and finish the payment
									flow without leaving the app.
								</p>
							</div>
						</div>

						<div className="grid gap-3 sm:grid-cols-2 lg:min-w-md lg:grid-cols-2">
							<Card className="border-border/60 bg-background/70 shadow-none">
								<CardHeader className="space-y-2 pb-3">
									<p className="text-xs uppercase tracking-[0.22em] text-muted-foreground">
										Active plan
									</p>
									<CardTitle className="text-lg">
										{currentPlan?.name ??
											auth.activeSubscription?.name ??
											"None"}
									</CardTitle>
								</CardHeader>
								<CardContent className="pt-0 text-sm text-muted-foreground">
									{currentPlan
										? "Your current subscription is reflected in the pricing grid below."
										: "No active subscription found for this account yet."}
								</CardContent>
							</Card>

							<Card className="border-border/60 bg-background/70 shadow-none">
								<CardHeader className="space-y-2 pb-3">
									<p className="text-xs uppercase tracking-[0.22em] text-muted-foreground">
										Selected plan
									</p>
									<CardTitle className="text-lg">
										{effectivePlan?.name ?? "None selected"}
									</CardTitle>
								</CardHeader>
								<CardContent className="pt-0 text-sm text-muted-foreground">
									{effectivePlan
										? `KES ${formatCurrency(
												billingCycle === "yearly"
													? effectivePlan.price.yearly
													: effectivePlan.price.monthly
											)} billed ${billingCycleLabels[billingCycle].toLowerCase()}`
										: "Choose a plan to unlock payment controls."}
								</CardContent>
							</Card>
						</div>
					</div>
				</section>

				{/* Wizard Steps Start */}
				<div
					ref={plansSectionRef}
					className="space-y-6">
					<div className="flex flex-col gap-3 rounded-2xl border border-border/60 bg-card/70 p-3 shadow-sm sm:flex-row sm:flex-wrap sm:items-center">
						{wizardSteps.map((step) => {
							const isActive = wizardStep === step.id

							return (
								<button
									key={step.id}
									type="button"
									onClick={() => goToStep(step.id)}
									className={cn(
										"flex w-full min-w-0 items-start gap-3 rounded-xl border px-4 py-3 text-left transition-colors sm:flex-1 cursor-pointer",
										isActive
											? "border-primary/80 bg-primary/5"
											: "border-border/60 bg-background/60 hover:bg-background"
									)}>
									<span
										className={cn(
											"mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full text-sm font-semibold",
											isActive
												? "bg-primary text-primary-foreground"
												: step.completed
													? "bg-emerald-500/15 text-emerald-600 dark:text-emerald-400"
													: "bg-muted text-muted-foreground"
										)}>
										{step.id}
									</span>
									<span className="min-w-0">
										<span className="block text-sm font-medium">
											{step.label}
										</span>
										<span className="block text-xs text-muted-foreground">
											{step.description}
										</span>
									</span>
								</button>
							)
						})}
					</div>
					{/* Wizard Steps End */}

					{/* Wizard Start */}
					<Card className="overflow-hidden border-border/60 bg-card/85 shadow-sm backdrop-blur-sm">
						<CardHeader className="space-y-4">
							<div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
								<div className="space-y-2">
									<CardTitle className="text-2xl">
										{wizardStep === 1 && "Step 1. Choose a plan"}
										{wizardStep === 2 && "Step 2. Confirm phone details"}
										{wizardStep === 3 && "Step 3. Review and pay"}
									</CardTitle>
									<p className="max-w-2xl text-sm leading-6 text-muted-foreground">
										{wizardStep === 1 &&
											"Pick a billing cycle and choose the subscription you want to continue with."}
										{wizardStep === 2 &&
											"Update the phone number that should receive the payment prompt."}
										{wizardStep === 3 &&
											"Review the selected plan and confirm the payment details before checkout."}
									</p>
								</div>

								<div className="flex flex-wrap items-center gap-3">
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
								</div>
							</div>
						</CardHeader>

						<CardContent className="space-y-6">
							{/* Wizard Step 1 Start */}
							{wizardStep === 1 && (
								<>
									{loadingPlans ? (
										<div className="grid gap-6 lg:grid-cols-2 xl:grid-cols-3">
											{Array.from({ length: 3 }).map((_, index) => (
												<Card
													key={index}
													className="border-border/60 bg-card/70 shadow-sm">
													<CardHeader className="space-y-4 pb-4">
														<Skeleton className="h-5 w-20" />
														<Skeleton className="h-8 w-44" />
														<Skeleton className="h-4 w-full" />
													</CardHeader>
													<CardContent className="space-y-4">
														<Skeleton className="h-16 w-full rounded-2xl" />
														<Skeleton className="h-28 w-full rounded-2xl" />
														<Skeleton className="h-11 w-full rounded-md" />
													</CardContent>
												</Card>
											))}
										</div>
									) : plans.length > 0 ? (
										<div className="grid gap-6 lg:grid-cols-5 xl:grid-cols-6">
											{plans.map((plan, index) => {
												return (
													<SubscriptionPlan
														key={plan.id ?? plan.name}
														subscriptionPlan={plan}
														isCurrentPlan={selectedPlanId == plan.id}
														billingCycle={billingCycle}
														featured={index === 1}
														label="choose plan"
														onClick={() => {
															void handlePlanSelection(plan)
														}}
														loading={
															savingPlanId !== null &&
															String(savingPlanId) === String(plan.id)
														}
														disabled={
															savingPlanId !== null &&
															String(savingPlanId) !== String(plan.id)
														}
													/>
												)
											})}
										</div>
									) : (
										<div className="rounded-2xl border border-dashed border-border/70 bg-background/60 p-4 text-sm text-muted-foreground">
											We could not find any subscription plans for this account.
										</div>
									)}

									<div className="flex justify-end gap-3">
										<Button
											type="button"
											onClick={() => goToStep(2)}
											disabled={!canProceedToPhone}
											className="min-w-32">
											Continue
											<ArrowRight className="ml-2 size-4" />
										</Button>
									</div>
								</>
							)}
							{/* Wizard Step 1 End */}

							{/* Wizard Step 2 Start */}
							{wizardStep === 2 && (
								<div className="grid grid-cols-2 gap-6">
									<Card className="border-border/60 bg-card/85 shadow-sm backdrop-blur-sm p-12">
										<CardHeader className="space-y-2">
											<CardTitle className="text-xl">Selected plan</CardTitle>
											<p className="text-sm text-muted-foreground">
												Confirm the plan you selected in the previous step.
											</p>
										</CardHeader>
										<CardContent>
											{effectivePlan ? (
												<SubscriptionPlan
													key={effectivePlan.id}
													subscriptionPlan={effectivePlan}
													isCurrentPlan={selectedPlanId == effectivePlan.id}
													billingCycle={billingCycle}
													// featured={effectivePlan.featured}
													label="choose plan"
													onClick={() => {
														void handlePlanSelection(effectivePlan)
													}}
													loading={
														savingPlanId !== null &&
														String(savingPlanId) === String(effectivePlan.id)
													}
													disabled={
														savingPlanId !== null &&
														String(savingPlanId) !== String(effectivePlan.id)
													}
												/>
											) : (
												"Choose a plan in the previous step to continue."
											)}
										</CardContent>
									</Card>

									<Card className="border-border/60 bg-card/85 shadow-sm backdrop-blur-sm">
										<CardHeader className="space-y-2">
											<CardTitle className="text-xl">Payment details</CardTitle>
											<p className="text-sm text-muted-foreground">
												Keep your M-Pesa number updated so payment requests
												reach the right phone.
											</p>
										</CardHeader>
										<CardContent>
											<form
												onSubmit={handleUpdatePhone}
												className="space-y-5">
												<Input
													id="phone"
													name="phone"
													type="tel"
													label="M-Pesa phone number"
													value={phone}
													onChange={(event) => setPhone(event.target.value)}
													error={phoneError}
													helperText="Use the number that should receive the STK prompt."
												/>

												<div className="flex flex-col gap-3 sm:flex-row">
													<Button
														type="submit"
														variant="outline"
														className="flex-1"
														disabled={updatingPhone}>
														{updatingPhone
															? "Updating phone..."
															: "Update phone"}
														{updatingPhone && <Spinner />}
													</Button>
												</div>
											</form>
										</CardContent>

										<CardFooter className="">
											<div className="w-full flex justify-between gap-3">
												<Button
													type="button"
													variant="outline"
													className="sm:w-36"
													onClick={() => setWizardStep(1)}>
													<ArrowLeft className="ml-2 size-4" />
													Back
												</Button>
												<Button
													type="button"
													className="sm:w-40"
													disabled={!canProceedToPayment}
													onClick={() => goToStep(3)}>
													Continue
													<ArrowRight className="ml-2 size-4" />
												</Button>
											</div>
										</CardFooter>
									</Card>
								</div>
							)}
							{/* Wizard Step 2 End */}

							{/* Wizard Step 3 Start */}
							{wizardStep === 3 && (
								<div className="grid grid-cols-2 gap-6">
									<Card className="border-border/60 bg-card/85 shadow-sm backdrop-blur-sm p-12">
										<CardHeader className="space-y-2">
											<CardTitle className="text-xl">Selected plan</CardTitle>
											<p className="text-sm text-muted-foreground">
												Confirm the plan you selected in the previous step.
											</p>
										</CardHeader>
										<CardContent>
											{effectivePlan ? (
												<SubscriptionPlan
													key={effectivePlan.id}
													subscriptionPlan={effectivePlan}
													isCurrentPlan={selectedPlanId == effectivePlan.id}
													billingCycle={billingCycle}
													// featured={effectivePlan.featured}
													label="choose plan"
													onClick={() => {
														void handlePlanSelection(effectivePlan)
													}}
													loading={
														savingPlanId !== null &&
														String(savingPlanId) === String(effectivePlan.id)
													}
													disabled={
														savingPlanId !== null &&
														String(savingPlanId) !== String(effectivePlan.id)
													}
												/>
											) : (
												<div className="rounded-2xl border border-dashed border-border/70 bg-background/60 p-4 text-sm text-muted-foreground">
													Select a plan from the pricing grid to continue.
												</div>
											)}
										</CardContent>
									</Card>

									<Card className="border-border/60 bg-card/85 shadow-sm backdrop-blur-sm">
										<CardHeader className="space-y-2">
											<p className="text-sm text-muted-foreground">
												Start the STK push after you have selected a plan and
												confirmed your phone number.
											</p>
										</CardHeader>
										<CardContent className="space-y-4">
											<div className="rounded-2xl border border-dashed border-border/70 bg-background/60 p-4 text-sm text-muted-foreground">
												{!isVerified(auth)
													? "Verify your email before paying."
													: !auth.phone
														? "Add an M-Pesa number before paying."
														: "Use the pay button below to start checkout."}
											</div>

											<Button
												type="button"
												variant="success"
												className="w-full"
												onClick={() => {
													void handleStartPayment()
												}}
												disabled={
													!selectedPlan ||
													!auth.phone ||
													!isVerified(auth) ||
													paying
												}>
												Pay with M-Pesa
												{paying && <Spinner />}
											</Button>

											{window.location.hostname !== PRODUCTION_HOSTNAME && (
												<Button
													type="button"
													variant="outline"
													className="w-full"
													onClick={() => {
														void handleSimulatePayment()
													}}
													disabled={simulating}>
													Simulate payment
													{simulating && <Spinner />}
												</Button>
											)}

											{waitingForPayment && (
												<div className="flex items-center gap-3 rounded-2xl border border-border/60 bg-background/70 p-4 text-sm text-muted-foreground">
													<Loader2 className="size-4 animate-spin text-primary" />
													<span>
														Do not close the page while we confirm your
														subscription.
													</span>
												</div>
											)}

											{waitingForPayment && (
												<Button
													type="button"
													variant="warning"
													className="w-full"
													onClick={() => {
														awaitingPaymentRef.current = false
														setWaitingForPayment(false)
														toast.info("Stopped waiting for payment.")
													}}>
													Cancel waiting
												</Button>
											)}
										</CardContent>

										<CardFooter className="">
											<div className="w-full flex justify-between gap-3">
												<Button
													type="button"
													variant="outline"
													className="sm:w-36"
													onClick={() => setWizardStep(2)}>
													<ArrowLeft className="mr-2 size-4" />
													Back
												</Button>
												<Button
													type="button"
													variant="default"
													className="sm:w-36"
													onClick={() => setWizardStep(2)}>
													Finish
													<ArrowRight className="ml-2 size-4" />
												</Button>
											</div>
										</CardFooter>
									</Card>
								</div>
							)}
							{/* Wizard Step 3 End */}
						</CardContent>
					</Card>
					{/* Wizard End */}
				</div>
			</div>
		</>
	)
}
