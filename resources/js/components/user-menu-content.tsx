import type { MouseEvent } from "react"
import { Link } from "@/components/ui/link"
import { Home, LogOut, Settings } from "lucide-react"
import {
	DropdownMenuGroup,
	DropdownMenuItem,
	DropdownMenuLabel,
	DropdownMenuSeparator,
} from "@/components/ui/dropdown-menu"
import { Spinner } from "@/components/ui/spinner"
import { UserInfo } from "@/components/user-info"
import { useLogout } from "@/hooks/use-logout"
import { useMobileNavigation } from "@/hooks/use-mobile-navigation"
import { edit } from "@/routes/profile"
import type { User } from "@/types"

type Props = {
	user: User
}

export function UserMenuContent({ user }: Props) {
	const cleanup = useMobileNavigation()
	const { logout, loggingOut } = useLogout()

	const handleLogout = (
		event: MouseEvent<HTMLAnchorElement | HTMLButtonElement>
	) => {
		event.preventDefault()

		if (loggingOut) {
			return
		}

		cleanup()
		logout()
	}

	return (
		<>
			<DropdownMenuLabel className="p-0 font-normal">
				<div className="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
					<UserInfo
						user={user}
						showEmail={true}
						showRole={true}
					/>
				</div>
			</DropdownMenuLabel>
			<DropdownMenuSeparator />
			<DropdownMenuGroup>
				<DropdownMenuItem asChild>
					<Link
						variant="unstyled"
						className="block w-full cursor-pointer"
						href="/">
						<Home className="mr-2" />
						Welcome Page
					</Link>
				</DropdownMenuItem>
				<DropdownMenuItem asChild>
					<Link
						variant="unstyled"
						className="block w-full cursor-pointer"
						href={edit().url}
						onClick={cleanup}>
						<Settings className="mr-2" />
						Settings
					</Link>
				</DropdownMenuItem>
			</DropdownMenuGroup>
			<DropdownMenuSeparator />
			<DropdownMenuItem asChild>
				<button
					className="block w-full cursor-pointer disabled:pointer-events-none disabled:opacity-70"
					disabled={loggingOut}
					onClick={handleLogout}
					data-test="logout-button">
					{loggingOut ? (
						<Spinner className="mr-2 inline size-4" />
					) : (
						<LogOut className="mr-2" />
					)}
					Log out
				</button>
			</DropdownMenuItem>
		</>
	)
}
