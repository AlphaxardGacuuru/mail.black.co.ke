import { useEffect } from "react"
import { useApp } from "@/contexts/AppContext"
import { usePushNotifications } from "@/hooks/use-push-notifications"
import toast from "@/lib/toast"

const AUTO_PUSH_PROMPT_KEY = "pushNotificationsAutoPrompted"

/**
 * Mounted app-wide (in AppSidebar) to silently prompt for push permission once per browser,
 * regardless of which page the user lands on first.
 */
export default function AutoPushPrompt() {
	const { auth } = useApp()
	const { isSupported, permission, isSubscribed, subscribe } =
		usePushNotifications()

	useEffect(() => {
		if (!auth || !isSupported || isSubscribed || permission !== "default") {
			return
		}

		if (localStorage.getItem(AUTO_PUSH_PROMPT_KEY)) {
			return
		}

		localStorage.setItem(AUTO_PUSH_PROMPT_KEY, "1")

		subscribe().then((enabled) => {
			if (enabled) {
				toast.success("Notifications enabled", {
					description: "You'll get a native alert when new mail arrives.",
				})
			}
		})
	}, [auth, isSupported, isSubscribed, permission, subscribe])

	return null
}
