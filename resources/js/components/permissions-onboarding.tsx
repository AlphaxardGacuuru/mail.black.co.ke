import { Bell } from "lucide-react"
import { useEffect, useMemo, useState } from "react"
import { useApp } from "@/contexts/AppContext"
import { Button } from "@/components/ui/button"
import {
	Dialog,
	DialogContent,
	DialogDescription,
	DialogFooter,
	DialogHeader,
	DialogTitle,
} from "@/components/ui/dialog"
import { usePushNotifications } from "@/hooks/use-push-notifications"
import toast from "@/lib/toast"

const ONBOARDING_SHOWN_KEY = "permissionsOnboardingShown"

type PermissionStep = {
	key: string
	icon: typeof Bell
	title: string
	description: string
	isEligible: boolean
	request: () => Promise<boolean>
	onGranted: () => void
}

/**
 * Mounted app-wide (in AppSidebar). On a user's first login in this browser,
 * walks them through a short queue of native permission requests — currently
 * just push notifications — each behind its own explanatory modal so the
 * browser's own permission prompt never appears out of nowhere.
 */
export default function PermissionsOnboarding() {
	const { auth } = useApp()
	const { isSupported, permission, isSubscribed, subscribe } =
		usePushNotifications()
	const [active, setActive] = useState(false)
	const [stepIndex, setStepIndex] = useState(0)

	const steps: PermissionStep[] = useMemo(
		() => [
			{
				key: "notifications",
				icon: Bell,
				title: "Turn on notifications",
				description:
					"Enable notifications to get messages the moment they arrive.",
				isEligible: isSupported && permission === "default" && !isSubscribed,
				request: subscribe,
				onGranted: () =>
					toast.success("Notifications enabled", {
						description: "You'll get a native alert when new mail arrives.",
					}),
			},
		],
		[isSupported, permission, isSubscribed, subscribe]
	)

	useEffect(() => {
		if (!auth || active || localStorage.getItem(ONBOARDING_SHOWN_KEY)) {
			return
		}

		setActive(true)
	}, [auth, active])

	useEffect(() => {
		if (!active) {
			return
		}

		if (stepIndex >= steps.length) {
			localStorage.setItem(ONBOARDING_SHOWN_KEY, "1")
			return
		}

		if (!steps[stepIndex].isEligible) {
			setStepIndex((index) => index + 1)
		}
	}, [active, stepIndex, steps])

	const currentStep =
		active && stepIndex < steps.length ? steps[stepIndex] : null

	function skip(): void {
		setStepIndex((index) => index + 1)
	}

	async function allow(): Promise<void> {
		if (!currentStep) {
			return
		}

		const granted = await currentStep.request()

		if (granted) {
			currentStep.onGranted()
		}

		setStepIndex((index) => index + 1)
	}

	if (!currentStep) {
		return null
	}

	const Icon = currentStep.icon

	return (
		<Dialog
			open
			onOpenChange={(open) => !open && skip()}>
			<DialogContent className="sm:max-w-md">
				<DialogHeader className="flex items-center justify-center">
					<div className="mb-3 rounded-full border border-border bg-card p-0.5 shadow-sm">
						<div className="rounded-full border border-border bg-muted p-3">
							<Icon className="size-6 text-foreground" />
						</div>
					</div>
					<DialogTitle>{currentStep.title}</DialogTitle>
					<DialogDescription className="text-center">
						{currentStep.description}
					</DialogDescription>
				</DialogHeader>
				<DialogFooter className="sm:justify-center">
					<Button
						type="button"
						variant="outline"
						onClick={skip}>
						Not now
					</Button>
					<Button
						type="button"
						onClick={() => void allow()}>
						Enable
					</Button>
				</DialogFooter>
			</DialogContent>
		</Dialog>
	)
}
