import {
	AlertTriangle,
	Ban,
	Check,
	Clock,
	MousePointerClick,
} from "lucide-react"
import {
	Tooltip,
	TooltipContent,
	TooltipTrigger,
} from "@/components/ui/tooltip"
import type { ReactNode } from "react"
import { cn } from "@/lib/utils"
import type { MailMessageStatus } from "@/types/mail"

type Props = {
	status: MailMessageStatus | null
	className?: string
}

function Ticks({ count, className }: { count: number; className?: string }) {
	return (
		<span className="flex items-center gap-0.5">
			{Array.from({ length: count }).map((_, index) => (
				<Check
					key={index}
					className={cn(index > 0 && "-ml-2", className)}
				/>
			))}
		</span>
	)
}

/**
 * Statuses track a Mailgun-backed outbound lifecycle: queued -> sent ->
 * delivered -> opened -> clicked (with failed/bounced as terminal error
 * states). `received` is unrelated — it marks an inbound message, whose
 * `isRead` flag reflects only whether the mailbox owner has read it locally.
 */
export default function MailStatusIcon({ status, className }: Props) {
	if (!status) {
		return null
	}

	const size = cn("size-3.5", className)
	let label: string
	let icon: ReactNode

	switch (status) {
		case "queued":
			label = "Queued"
			icon = <Clock className={cn(size, "text-muted-foreground")} />
			break

		case "sent":
			label = "Sent"
			icon = (
				<Ticks
					count={1}
					className={cn(size, "text-muted-foreground")}
				/>
			)
			break

		case "delivered":
			label = "Delivered"
			icon = (
				<Ticks
					count={2}
					className={cn(size, "text-muted-foreground")}
				/>
			)
			break

		case "opened":
			label = "Opened"
			icon = (
				<Ticks
					count={2}
					className={cn(size, "text-primary")}
				/>
			)
			break

		case "clicked":
			label = "Link clicked"
			icon = <MousePointerClick className={cn(size, "text-primary")} />
			break

		case "failed":
			label = "Failed"
			icon = (
				<Ticks
					count={1}
					className={cn(size, "text-destructive")}
				/>
			)
			break

		case "bounced":
			label = "Bounced"
			icon = (
				<Ticks
					count={2}
					className={cn(size, "text-destructive")}
				/>
			)
			break

		case "temporary_failed":
			label = "Temporary failure"
			icon = <Clock className={cn(size, "text-destructive")} />
			break

		case "permanent_failed":
			label = "Permanent failure"
			icon = <AlertTriangle className={cn(size, "text-destructive")} />
			break

		case "complained":
			label = "Spam complaint"
			icon = <Ban className={cn(size, "text-destructive")} />
			break

		case "unsubscribed":
			label = "Unsubscribed"
			icon = <Ban className={cn(size, "text-muted-foreground")} />
			break
			
		default:
			return null
	}

	return (
		<Tooltip>
			<TooltipTrigger asChild>
				<span aria-label={label}>{icon}</span>
			</TooltipTrigger>
			<TooltipContent>{label}</TooltipContent>
		</Tooltip>
	)
}
