import { useEffect } from "react"
import { useApp } from "@/contexts/AppContext"
import { usePushNotifications } from "@/hooks/use-push-notifications"
import toast from "@/lib/toast"

const AUTO_PUSH_PROMPT_KEY = "pushNotificationsAutoPrompted"
const MAX_AUTO_PUSH_PROMPTS = 2

/**
 * Mounted app-wide (in AppSidebar) to silently prompt for push permission up to
 * MAX_AUTO_PUSH_PROMPTS times per browser, regardless of which page the user lands
 * on first.
 */
export default function AutoPushPrompt() {
	const { auth } = useApp()
	const { isSupported, permission, isSubscribed, subscribe } =
		usePushNotifications()

	useEffect(() => {
		if (!auth || !isSupported || isSubscribed || permission !== "default") {
			return
		}

		const promptCount = Number(localStorage.getItem(AUTO_PUSH_PROMPT_KEY) ?? 0)

		if (promptCount >= MAX_AUTO_PUSH_PROMPTS) {
			return
		}

		localStorage.setItem(AUTO_PUSH_PROMPT_KEY, String(promptCount + 1))

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
