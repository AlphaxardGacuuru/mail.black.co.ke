import { Check, ChevronDown } from "lucide-react"
import MailgunAccountController from "@/actions/App/Http/Controllers/Settings/MailgunAccountController"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { Button } from "@/components/ui/button"
import {
	DropdownMenu,
	DropdownMenuContent,
	DropdownMenuItem,
	DropdownMenuLabel,
	DropdownMenuSeparator,
	DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { useApp } from "@/contexts/AppContext"
import { useQueryClient } from "@tanstack/react-query"
import Axios from "@/lib/axios"
import toast from "@/lib/toast"
import { invalidateAuth } from "@/middleware/auth"
import type { MailgunAccount } from "@/types"

function AccountAvatar({ account }: { account: MailgunAccount }) {
	return (
		<Avatar className="size-10 shrink-0">
			<AvatarImage
				src={account.avatar ?? undefined}
				alt={account.mailboxAddress}
			/>
			<AvatarFallback className="text-[16px] uppercase">
				{account.mailboxAddress.slice(0, 2)}
			</AvatarFallback>
		</Avatar>
	)
}

export function MailgunAccountSwitcher() {
	const { auth } = useApp()
	const queryClient = useQueryClient()
	const accounts = auth?.mailgunAccounts ?? []
	const activeAccount =
		accounts.find((account) => account.isActive) ?? accounts[0]

	function activateAccount(account: MailgunAccount): void {
		if (account.isActive) {
			return
		}

		Axios.post(MailgunAccountController.activate.url(account.id))
			.then(() => {
				invalidateAuth()
				queryClient.invalidateQueries({ queryKey: ["mail", "threads"] })
				queryClient.invalidateQueries({ queryKey: ["mail", "thread"] })
			})
			.catch(() => toast.error("Unable to switch mail account."))
	}

	if (!activeAccount) {
		return null
	}

	return (
		<DropdownMenu>
			<DropdownMenuTrigger asChild>
				<Button
					variant="ghost"
					size="lg"
					className="max-w-56 gap-1 p-2 text-sm normal-case">
					<AccountAvatar account={activeAccount} />
				</Button>
			</DropdownMenuTrigger>
			<DropdownMenuContent
				align="end"
				className="w-72">
				<DropdownMenuLabel>Mail accounts</DropdownMenuLabel>
				<DropdownMenuSeparator />
				{accounts.map((account) => (
					<DropdownMenuItem
						key={account.id}
						onClick={() => activateAccount(account)}
						className="cursor-pointer gap-2">
						<AccountAvatar account={account} />
						<div className="flex flex-col gap-0.5 overflow-hidden">
							<div className="min-w-0 flex-1 truncate">
								{account.mailFromName}
							</div>
							<div className="min-w-0 flex-1 truncate">
								{account.mailboxAddress}
							</div>
						</div>
						{account.isActive && <Check className="size-4 text-primary" />}
					</DropdownMenuItem>
				))}
			</DropdownMenuContent>
		</DropdownMenu>
	)
}
