import { Bell, Download } from "lucide-react"
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
import { useIsMobile } from "@/hooks/use-mobile"
import { usePwaInstall } from "@/hooks/use-pwa-install"
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
 * Mounted app-wide (in AppSidebar). Once per site visit, walks an authenticated
 * user through native permission requests behind explanatory modals so browser
 * prompts never appear out of nowhere.
 */
export default function PermissionsOnboarding() {
	const { auth } = useApp()
	const isMobile = useIsMobile()
	const { canInstall, install, isInstalled } = usePwaInstall()
	const { isSupported, permission, isSubscribed, subscribe } =
		usePushNotifications()
	const [active, setActive] = useState(false)
	const [stepIndex, setStepIndex] = useState(0)

	const steps: PermissionStep[] = useMemo(() => {
		const installStep: PermissionStep | null =
			isMobile && !isInstalled && canInstall
				? {
						key: "install-pwa",
						icon: Download,
						title: "Install Black Mail",
						description:
							"Add the app to your home screen for quick access and a fuller mobile experience.",
						isEligible: true,
						request: install,
						onGranted: () =>
							toast.success("Black Mail installed", {
								description: "You can keep using it from your home screen.",
							}),
					}
				: null

		const notificationsStep: PermissionStep = {
			key: "notifications",
			icon: Bell,
			title: "Turn on notifications",
			description:
				"Enable notifications to get messages the moment they arrive.",
			isEligible: isSupported && permission !== "granted" && !isSubscribed,
			request: subscribe,
			onGranted: () =>
				toast.success("Notifications enabled", {
					description: "You'll get a native alert when new mail arrives.",
				}),
		}

		return [...(installStep ? [installStep] : []), notificationsStep]
	}, [
		canInstall,
		install,
		isInstalled,
		isMobile,
		isSubscribed,
		isSupported,
		permission,
		subscribe,
	])

	useEffect(() => {
		if (
			!auth ||
			active ||
			sessionStorage.getItem(ONBOARDING_SHOWN_KEY)
		) {
			return
		}

		sessionStorage.setItem(ONBOARDING_SHOWN_KEY, "1")
		setStepIndex(0)
		setActive(true)
	}, [auth, active])

	useEffect(() => {
		if (!active) {
			return
		}

		if (stepIndex >= steps.length) {
			setActive(false)
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
