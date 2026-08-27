import { Check, ChevronDown } from "lucide-react"
import MailgunAccountController from "@/actions/App/Http/Controllers/Settings/MailgunAccountController"
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
					className="max-w-56 gap-1 px-2 text-sm normal-case">
					<span className="truncate">{activeAccount.mailboxAddress}</span>
					<ChevronDown className="size-4 shrink-0" />
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
						className="cursor-pointer">
						<span className="min-w-0 flex-1 truncate">
							{account.mailboxAddress}
						</span>
						{account.isActive && <Check className="size-4" />}
					</DropdownMenuItem>
				))}
			</DropdownMenuContent>
		</DropdownMenu>
	)
}
