import { AlertTriangle, Ban, Check, Clock, MousePointerClick } from "lucide-react"
import { cn } from "@/lib/utils"
import type { MailMessageStatus } from "@/types/mail"

type Props = {
	status: MailMessageStatus | null
	isRead?: boolean
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
export default function MailStatusIcon({ status, isRead, className }: Props) {
	if (!status) {
		return null
	}

	const size = cn("size-3.5", className)

	switch (status) {
		case "queued":
			return <Clock className={cn(size, "text-muted-foreground")} />
		case "sent":
			return <Ticks count={1} className={cn(size, "text-muted-foreground")} />
		case "delivered":
			return <Ticks count={2} className={cn(size, "text-muted-foreground")} />
		case "opened":
			return <Ticks count={2} className={cn(size, "text-primary")} />
		case "clicked":
			return <MousePointerClick className={cn(size, "text-primary")} />
		case "received":
			return <Ticks count={3} className={cn(size, isRead ? "text-primary" : "text-muted-foreground")} />
		case "failed":
			return <Ticks count={1} className={cn(size, "text-destructive")} />
		case "bounced":
			return <Ticks count={2} className={cn(size, "text-destructive")} />
		case "temporary_failed":
			return <Clock className={cn(size, "text-destructive")} />
		case "permanent_failed":
			return <AlertTriangle className={cn(size, "text-destructive")} />
		case "complained":
			return <Ban className={cn(size, "text-destructive")} />
		case "unsubscribed":
			return <Ban className={cn(size, "text-muted-foreground")} />
		default:
			return null
	}
}
