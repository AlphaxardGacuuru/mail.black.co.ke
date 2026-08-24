import { Check, Clock } from "lucide-react"
import { cn } from "@/lib/utils"
import type { MailMessageStatus } from "@/types/mail"

type Props = {
	status: MailMessageStatus | null
	isRead?: boolean
	className?: string
}

function Ticks({ count, className }: { count: number; className?: string }) {
	return (
		<span className="flex items-center">
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
 * There is no distinct "opened" status in MailStatus — an opened message is a
 * received one the recipient has read, so `received` + `isRead` renders the
 * triple tick in primary color instead of muted.
 */
export default function MailStatusIcon({ status, isRead, className }: Props) {
	if (!status) return null

	const size = cn("size-3.5", className)

	switch (status) {
		case "queued":
			return <Clock className={cn(size, "text-muted-foreground")} />
		case "sent":
			return <Ticks count={1} className={cn(size, "text-muted-foreground")} />
		case "delivered":
			return <Ticks count={2} className={cn(size, "text-muted-foreground")} />
		case "received":
			return <Ticks count={3} className={cn(size, isRead ? "text-primary" : "text-muted-foreground")} />
		case "failed":
			return <Ticks count={2} className={cn(size, "text-destructive")} />
		case "bounced":
			return <Ticks count={3} className={cn(size, "text-destructive")} />
		default:
			return null
	}
}
