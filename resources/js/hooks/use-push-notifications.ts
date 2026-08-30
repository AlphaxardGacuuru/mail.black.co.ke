import { useCallback, useEffect, useState } from "react"
import PushSubscriptionController from "@/actions/App/Http/Controllers/PushSubscriptionController"
import Axios from "@/lib/axios"

function urlBase64ToUint8Array(base64String: string): Uint8Array<ArrayBuffer> {
	const padding = "=".repeat((4 - (base64String.length % 4)) % 4)
	const base64 = (base64String + padding).replace(/-/g, "+").replace(/_/g, "/")

	const rawData = window.atob(base64)
	const outputArray = new Uint8Array(new ArrayBuffer(rawData.length))

	for (let i = 0; i < rawData.length; ++i) {
		outputArray[i] = rawData.charCodeAt(i)
	}

	return outputArray
}

export function usePushNotifications() {
	const isSupported =
		"serviceWorker" in navigator &&
		"PushManager" in window &&
		"Notification" in window

	const [permission, setPermission] = useState<NotificationPermission>(
		isSupported ? Notification.permission : "denied"
	)
	const [isSubscribed, setIsSubscribed] = useState(false)

	useEffect(() => {
		if (!isSupported) {
			return
		}

		navigator.serviceWorker.ready
			.then((registration) => registration.pushManager.getSubscription())
			.then((subscription) => setIsSubscribed(subscription !== null))
			.catch(() => {
				// Ignore — treated as not subscribed.
			})
	}, [isSupported])

	const subscribe = useCallback(async (): Promise<boolean> => {
		if (!isSupported) {
			return false
		}

		const result = await Notification.requestPermission()
		setPermission(result)

		if (result !== "granted") {
			return false
		}

		const vapidPublicKey = import.meta.env.VITE_VAPID_PUBLIC_KEY

		if (!vapidPublicKey) {
			return false
		}

		const registration = await navigator.serviceWorker.ready
		const subscription = await registration.pushManager.subscribe({
			userVisibleOnly: true,
			applicationServerKey: urlBase64ToUint8Array(vapidPublicKey),
		})

		const json = subscription.toJSON()

		await Axios.post(PushSubscriptionController.store.url(), {
			endpoint: json.endpoint,
			keys: json.keys,
		})

		setIsSubscribed(true)

		return true
	}, [isSupported])

	const unsubscribe = useCallback(async (): Promise<void> => {
		if (!isSupported) {
			return
		}

		const registration = await navigator.serviceWorker.ready
		const subscription = await registration.pushManager.getSubscription()

		if (!subscription) {
			setIsSubscribed(false)
			return
		}

		const endpoint = subscription.endpoint
		await subscription.unsubscribe()

		await Axios.delete(PushSubscriptionController.destroy.url(), {
			data: { endpoint },
		})

		setIsSubscribed(false)
	}, [isSupported])

	return { isSupported, permission, isSubscribed, subscribe, unsubscribe }
}
