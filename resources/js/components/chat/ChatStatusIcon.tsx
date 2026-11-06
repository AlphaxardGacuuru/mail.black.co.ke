import { Check } from "lucide-react"
import {
	Tooltip,
	TooltipContent,
	TooltipTrigger,
} from "@/components/ui/tooltip"
import { cn } from "@/lib/utils"

type Props = {
	isRead: boolean
	className?: string
}

/**
 * A single gray tick means the message has been sent; a second, blue tick
 * appears once the recipient's last_read_at has caught up to this message.
 */
export default function ChatStatusIcon({ isRead, className }: Props) {
	const size = cn("size-3.5", className)

	return (
		<Tooltip>
			<TooltipTrigger asChild>
				<span
					aria-label={isRead ? "Read" : "Sent"}
					className="flex items-center gap-0.5">
					<Check className={cn(size, isRead ? "text-primary" : "text-muted-foreground")} />
					{isRead && (
						<Check className={cn(size, "-ml-2 text-primary")} />
					)}
				</span>
			</TooltipTrigger>
			<TooltipContent>{isRead ? "Read" : "Sent"}</TooltipContent>
		</Tooltip>
	)
}
