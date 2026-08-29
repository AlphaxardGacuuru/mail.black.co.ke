import { Check } from "lucide-react"
import { useState } from "react"
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
import {
	Sheet,
	SheetContent,
	SheetHeader,
	SheetTitle,
	SheetTrigger,
} from "@/components/ui/sheet"
import { useApp } from "@/contexts/AppContext"
import { useIsMobile } from "@/hooks/use-mobile"
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

function AccountDetails({ account }: { account: MailgunAccount }) {
	return (
		<>
			<AccountAvatar account={account} />
			<div className="flex min-w-0 flex-1 flex-col gap-0.5 overflow-hidden text-left">
				<div className="truncate">{account.mailFromName}</div>
				<div className="truncate text-sm text-muted-foreground">
					{account.mailboxAddress}
				</div>
			</div>
			<div>
				{account.isActive && <Check className="size-4 shrink-0 text-primary" />}
			</div>
		</>
	)
}

export function MailgunAccountSwitcher() {
	const { auth } = useApp()
	const queryClient = useQueryClient()
	const isMobile = useIsMobile()
	const [sheetOpen, setSheetOpen] = useState(false)
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
				setSheetOpen(false)
			})
			.catch(() => toast.error("Unable to switch mail account."))
	}

	if (!activeAccount) {
		return null
	}

	const trigger = (
		<Button
			variant="ghost"
			size="lg"
			aria-label="Switch mail account"
			className="max-w-56 gap-1 p-2 text-sm normal-case">
			<AccountAvatar account={activeAccount} />
		</Button>
	)

	if (isMobile) {
		return (
			<Sheet
				open={sheetOpen}
				onOpenChange={setSheetOpen}>
				<SheetTrigger asChild>{trigger}</SheetTrigger>
				<SheetContent
					side="bottom"
					className="inset-x-2 bottom-2 h-auto w-auto rounded-xl border border-white/40 bg-white/34 px-4 pb-6 shadow-[0_20px_45px_-28px_rgba(15,23,42,0.45)] backdrop-blur-xl dark:border-white/12 dark:bg-slate-950/20">
					<SheetHeader className="px-0 pt-2">
						<SheetTitle>Mail accounts</SheetTitle>
					</SheetHeader>
					<div className="flex flex-col gap-1">
						{accounts.map((account) => (
							<Button
								key={account.id}
								type="button"
								variant="ghost"
								className="h-auto w-full justify-start gap-3 py-2 px-0 normal-case"
								onClick={() => activateAccount(account)}>
								<AccountDetails account={account} />
							</Button>
						))}
					</div>
				</SheetContent>
			</Sheet>
		)
	}

	return (
		<DropdownMenu>
			<DropdownMenuTrigger asChild>{trigger}</DropdownMenuTrigger>
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
						<AccountDetails account={account} />
					</DropdownMenuItem>
				))}
			</DropdownMenuContent>
		</DropdownMenu>
	)
}
