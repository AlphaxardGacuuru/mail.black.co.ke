import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { useInitials } from "@/hooks/use-initials"
import { cn } from "@/lib/utils"
import type { User } from "@/types"

export function UserInfo({
	user,
	showEmail = false,
	showRole = false,
	isSubscribed = false,
}: {
	user: User
	showEmail?: boolean
	showRole?: boolean
	isSubscribed?: boolean
}) {
	const getInitials = useInitials()

	return (
		<>
			<div
				className={cn(
					"relative shrink-0",
					isSubscribed &&
						"after:pointer-events-none after:absolute after:-inset-1 after:rounded-full after:bg-linear-to-r after:from-primary after:to-secondary after:animate-[spin_3s_linear_infinite] after:content-['']"
				)}>
				<Avatar className="relative z-10 h-8 w-8 overflow-hidden rounded-full cursor-pointer">
					<AvatarImage
						src={user.avatar}
						alt={user.name}
					/>
					<AvatarFallback className="rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white">
						{getInitials(user.name)}
					</AvatarFallback>
				</Avatar>
			</div>
			<div className="grid flex-1 text-left text-sm leading-tight cursor-pointer">
				<span className="truncate font-medium">{user.name}</span>
				{showEmail && (
					<span className="truncate text-xs text-muted-foreground">
						{user.email}
					</span>
				)}
				{/* Role Names Start */}
				{showRole && Array.isArray(user.roleNames) && (
					<div>
						{user.roleNames.map(
							(role: { roleNames: string[] }, key: number) => (
								<div key={key}>
									{role.roleNames?.map((roleName, index) => (
										<h6
											key={index}
											className="fs-6 d-inline text-wrap me-1">
											{roleName}
											{index < role.roleNames.length - 1 && ","}
										</h6>
									))}
								</div>
							)
						)}
					</div>
				)}
				{/* Role Names End */}
			</div>
		</>
	)
}
