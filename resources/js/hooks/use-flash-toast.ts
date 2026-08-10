import { usePage } from "@/lib/spa"
import { useEffect, useRef } from "react"
import toast from "@/lib/toast"
import type { FlashToast } from "@/types/ui"

export function useFlashToast(): void {
	const { flash } = usePage<{ flash?: { toast?: FlashToast } }>().props
	const shownMessageRef = useRef<string>("")

	useEffect(() => {
		const data = flash?.toast

		if (!data) {
			return
		}

		const messageKey = `${data.type}:${data.message}`

		if (shownMessageRef.current === messageKey) {
			return
		}

		shownMessageRef.current = messageKey
		toast[data.type](data.message)
	}, [flash?.toast])
}
