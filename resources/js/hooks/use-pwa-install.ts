import { useEffect, useState } from "react"

type BeforeInstallPromptEvent = Event & {
	prompt: () => Promise<void>
	userChoice: Promise<{ outcome: "accepted" | "dismissed" }>
}

export function usePwaInstall() {
	const [installPrompt, setInstallPrompt] =
		useState<BeforeInstallPromptEvent | null>(null)
	const [isInstalled, setIsInstalled] = useState(false)

	useEffect(() => {
		const isStandalone =
			window.matchMedia("(display-mode: standalone)").matches ||
			("standalone" in navigator &&
				Boolean((navigator as Navigator & { standalone?: boolean }).standalone))
		setIsInstalled(isStandalone)

		const handleBeforeInstallPrompt = (event: Event) => {
			event.preventDefault()
			setInstallPrompt(event as BeforeInstallPromptEvent)
		}

		const handleAppInstalled = () => {
			setInstallPrompt(null)
			setIsInstalled(true)
		}

		window.addEventListener("beforeinstallprompt", handleBeforeInstallPrompt)
		window.addEventListener("appinstalled", handleAppInstalled)

		return () => {
			window.removeEventListener(
				"beforeinstallprompt",
				handleBeforeInstallPrompt
			)
			window.removeEventListener("appinstalled", handleAppInstalled)
		}
	}, [])

	async function install(): Promise<boolean> {
		if (!installPrompt) {
			return false
		}

		await installPrompt.prompt()
		const choice = await installPrompt.userChoice
		setInstallPrompt(null)

		if (choice.outcome === "accepted") {
			setIsInstalled(true)
			return true
		}

		return false
	}

	return {
		canInstall: Boolean(installPrompt) && !isInstalled,
		install,
		isInstalled,
	}
}
