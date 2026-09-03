import { useState } from "react"
import { Switch } from "@/components/ui/switch"
import { usePushNotifications } from "@/hooks/use-push-notifications"
import toast from "@/lib/toast"

export default function PushNotificationSettings() {
	const { isSupported, permission, isSubscribed, subscribe, unsubscribe } =
		usePushNotifications()
	const [processing, setProcessing] = useState(false)

	if (!isSupported) {
		return (
			<p className="text-sm text-muted-foreground">
				Push notifications aren&apos;t supported in this browser.
			</p>
		)
	}

	async function handleCheckedChange(checked: boolean) {
		setProcessing(true)

		try {
			if (checked) {
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
			} else {
				await unsubscribe()
				toast.success("Notifications disabled")
			}
		} finally {
			setProcessing(false)
		}
	}

	return (
		<div className="flex items-center justify-between gap-4">
			<div className="space-y-1">
				<p className="text-sm font-medium">Push notifications</p>
				<p className="text-sm text-muted-foreground">
					{permission === "denied"
						? "Notifications are blocked. Allow them for this site in your browser settings."
						: "Get a native alert when new mail arrives."}
				</p>
			</div>
			<Switch
				checked={isSubscribed}
				disabled={processing || permission === "denied"}
				onCheckedChange={handleCheckedChange}
				aria-label="Toggle push notifications"
			/>
		</div>
	)
}
