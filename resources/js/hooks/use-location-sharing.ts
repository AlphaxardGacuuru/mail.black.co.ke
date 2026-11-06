import { useCallback, useEffect, useRef, useState } from "react"

export type GeolocationPermissionState = "granted" | "denied" | "prompt" | "unsupported"

export type LocationTick = {
	latitude: number
	longitude: number
	accuracyMeters: number
}

// Ticks more often than this on some devices/browsers than we want to act
// on — coalesced with a simple time-based throttle, the same pattern
// ConversationView.tsx already uses for the "typing" whisper.
const UPDATE_THROTTLE_MS = 10_000

export function useLocationSharing() {
	const isSupported =
		typeof navigator !== "undefined" && "geolocation" in navigator

	const [permission, setPermission] = useState<GeolocationPermissionState>(
		isSupported ? "prompt" : "unsupported"
	)
	const watchIdRef = useRef<number | null>(null)
	const lastSentAtRef = useRef(0)

	useEffect(() => {
		if (!isSupported || !("permissions" in navigator)) {
			return
		}

		let permissionStatus: PermissionStatus | null = null

		navigator.permissions
			.query({ name: "geolocation" as PermissionName })
			.then((status) => {
				permissionStatus = status
				setPermission(status.state as GeolocationPermissionState)
				status.onchange = () => {
					setPermission(status.state as GeolocationPermissionState)
				}
			})
			.catch(() => {
				// Permissions API for "geolocation" isn't supported in every
				// browser (e.g. older Safari) — fall back to "prompt" and let
				// the actual watchPosition() call surface grant/deny.
			})

		return () => {
			if (permissionStatus) {
				permissionStatus.onchange = null
			}
		}
	}, [isSupported])

	// Resolves true once the first position (or an error) comes back, so
	// callers know whether the permission prompt was granted or denied.
	const start = useCallback(
		(onTick: (tick: LocationTick) => void): Promise<boolean> => {
			return new Promise((resolve) => {
				if (!isSupported) {
					resolve(false)
					return
				}

				let settled = false

				watchIdRef.current = navigator.geolocation.watchPosition(
					(position) => {
						setPermission("granted")

						if (!settled) {
							settled = true
							resolve(true)
						}

						const now = Date.now()
						if (now - lastSentAtRef.current < UPDATE_THROTTLE_MS) {
							return
						}
						lastSentAtRef.current = now

						onTick({
							latitude: position.coords.latitude,
							longitude: position.coords.longitude,
							accuracyMeters: position.coords.accuracy,
						})
					},
					() => {
						setPermission("denied")

						if (!settled) {
							settled = true
							resolve(false)
						}
					},
					{ enableHighAccuracy: true, maximumAge: 5_000 }
				)
			})
		},
		[isSupported]
	)

	const stop = useCallback(() => {
		if (watchIdRef.current !== null) {
			navigator.geolocation.clearWatch(watchIdRef.current)
			watchIdRef.current = null
		}
	}, [])

	useEffect(() => stop, [stop])

	return { isSupported, permission, start, stop }
}
