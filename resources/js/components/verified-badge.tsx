import { BadgeCheck } from "lucide-react"
import { cn } from "@/lib/utils"

type Props = {
	className?: string
}

/**
 * The badge in the corner of a verified user's avatar. Put it next to the
 * Avatar inside a `relative` wrapper.
 */
export function AvatarVerifiedBadge({ className }: Props) {
	return (
		<VerifiedBadge
			className={cn(
				"absolute -right-0.5 -bottom-0.5 z-20 size-3.5 rounded-full bg-background",
				className
			)}
		/>
	)
}

export default function VerifiedBadge({ className }: Props) {
	return (
		<BadgeCheck
			aria-label="Verified"
			className={cn("size-4 fill-primary text-secondary", className)}
		/>
	)
}
