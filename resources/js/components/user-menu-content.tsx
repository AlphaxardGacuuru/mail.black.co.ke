import type { MouseEvent } from "react"
import { Link } from "@/components/ui/link"
import axios from "@/lib/axios"
import toast from "@/lib/toast"
import { Home, LogOut, Settings } from "lucide-react"
import { useNavigate } from "@tanstack/react-router"
import {
	DropdownMenuGroup,
	DropdownMenuItem,
	DropdownMenuLabel,
	DropdownMenuSeparator,
} from "@/components/ui/dropdown-menu"
import { UserInfo } from "@/components/user-info"
import { useMobileNavigation } from "@/hooks/use-mobile-navigation"
import { clearAuth } from "@/middleware/auth"
import { logout } from "@/routes"
import { edit } from "@/routes/profile"
import type { User } from "@/types"

type Props = {
	user: User
}

export function UserMenuContent({ user }: Props) {
	const cleanup = useMobileNavigation()
	const navigate = useNavigate()

	const handleLogout = (
		event: MouseEvent<HTMLAnchorElement | HTMLButtonElement>
	) => {
		event.preventDefault()
		cleanup()

		const route = logout()

		axios
			.request({
				url: route.url,
				method: route.method,
			})
			.then((response) => {
				toast.success(response.data?.message ?? "Logged out")
				clearAuth()
				navigate({ to: "/login" })
			})
			.catch((error: unknown) => {
				const message =
					error &&
					typeof error === "object" &&
					"response" in error &&
					error.response &&
					typeof (error as any).response === "object"
						? (error as any).response?.data?.message
						: null

				toast.error(message ?? "Unable to log out")
				clearAuth()
				navigate({ to: "/login" })
			})
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
					className="block w-full cursor-pointer"
					onClick={handleLogout}
					data-test="logout-button">
					<LogOut className="mr-2" />
					Log out
				</button>
			</DropdownMenuItem>
		</>
	)
}
