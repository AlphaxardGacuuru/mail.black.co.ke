import { useState } from "react"
import { Button } from "@/components/ui/button"
import {
	Dialog,
	DialogContent,
	DialogDescription,
	DialogFooter,
	DialogHeader,
	DialogTitle,
} from "@/components/ui/dialog"
import { Spinner } from "@/components/ui/spinner"
import { Switch } from "@/components/ui/switch"
import { usePushNotifications } from "@/hooks/use-push-notifications"
import toast from "@/lib/toast"

export default function PushNotificationSettings() {
	const { isSupported, permission, isSubscribed, subscribe, unsubscribe } =
		usePushNotifications()
	const [processing, setProcessing] = useState(false)
	const [showPermissionPrimer, setShowPermissionPrimer] = useState(false)

	if (!isSupported) {
		return (
			<p className="text-sm text-muted-foreground">
				Push notifications aren&apos;t supported in this browser.
			</p>
		)
	}

	async function enableNotifications() {
		setProcessing(true)

		try {
			const enabled = await subscribe()

			if (enabled) {
				toast.success("Notifications enabled", {
					description: "You'll get a native alert when new mail arrives.",
				})
			} else if (permission === "denied") {
				toast.error("Notifications blocked", {
					description:
						"Allow notifications for this site in your browser settings.",
				})
			}
		} finally {
			setProcessing(false)
		}
	}

	async function handleCheckedChange(checked: boolean) {
		if (!checked) {
			setProcessing(true)

			try {
				await unsubscribe()
				toast.success("Notifications disabled")
			} finally {
				setProcessing(false)
			}

			return
		}

		if (permission === "default") {
			setShowPermissionPrimer(true)
			return
		}

		await enableNotifications()
	}

	function handlePrimerConfirm() {
		setShowPermissionPrimer(false)
		void enableNotifications()
	}

	return (
		<>
			<div className="flex items-center justify-between gap-4">
				<div className="space-y-1">
					<p className="text-sm font-medium">Push notifications</p>
					<p className="text-sm text-muted-foreground">
						{permission === "denied"
							? "Notifications are blocked. Allow them for this site in your browser settings."
							: "Get a native alert when new mail arrives."}
					</p>
				</div>
				<div className="flex items-center gap-2">
					{processing && <Spinner className="size-4 text-muted-foreground" />}
					<Switch
						checked={isSubscribed}
						disabled={processing || permission === "denied"}
						onCheckedChange={handleCheckedChange}
						aria-label="Toggle push notifications"
					/>
				</div>
			</div>

			<Dialog
				open={showPermissionPrimer}
				onOpenChange={setShowPermissionPrimer}>
				<DialogContent className="sm:max-w-md">
					<DialogHeader>
						<DialogTitle>Enable push notifications?</DialogTitle>
						<DialogDescription>
							Your browser will ask you to allow notifications for this site.
							Allow it so we can alert you when new mail arrives.
						</DialogDescription>
					</DialogHeader>
					<DialogFooter>
						<Button
							type="button"
							variant="outline"
							onClick={() => setShowPermissionPrimer(false)}>
							Not now
						</Button>
						<Button
							type="button"
							onClick={handlePrimerConfirm}>
							Allow notifications
						</Button>
					</DialogFooter>
				</DialogContent>
			</Dialog>
		</>
	)
}
