import { useConnectionStatus } from "@laravel/echo-react"

/**
 * Persistent pill confirming the realtime websocket connection is live.
 */
export default function MailConnectionStatus() {
	const status = useConnectionStatus()

	if (status !== "connected") {
		return null
	}

	return (
		<div className="flex shrink-0 items-center gap-1.5 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2.5 py-1 text-xs font-medium text-emerald-600 dark:text-emerald-400">
			<span className="relative flex size-1.5">
				<span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-500 opacity-75" />
				<span className="relative inline-flex size-1.5 rounded-full bg-emerald-500" />
			</span>
			Online
		</div>
	)
}
