import { MailPlus } from "lucide-react"
import { cn } from "@/lib/utils"

type Props = {
	visible: boolean
}

/**
 * Transient banner confirming a websocket push was received, before the new thread animates in.
 */
export default function MailRealtimeBanner({ visible }: Props) {
	if (!visible) {
		return null
	}

	return (
		<div
			className={cn(
				"pointer-events-none absolute inset-x-0 top-0 z-10 flex justify-center px-3 pt-2",
				"animate-in fade-in slide-in-from-top-2 duration-300"
			)}>
			<div className="flex items-center gap-1.5 rounded-full border border-primary/20 bg-primary/10 px-3 py-1 text-xs font-medium text-primary shadow-sm backdrop-blur-sm">
				<span className="relative flex size-2">
					<span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-primary opacity-75" />
					<span className="relative inline-flex size-2 rounded-full bg-primary" />
				</span>
				<MailPlus className="size-3.5" />
				New Email
			</div>
		</div>
	)
}
