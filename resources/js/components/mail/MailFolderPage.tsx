import { Link } from "@/components/ui/link"
import { Button } from "@/components/ui/button"
import MailShell from "@/components/mail/MailShell"
import { useApp } from "@/contexts/AppContext"
import { Head } from "@/lib/spa"
import type { MailFolderKey } from "@/types/mail"

type Props = {
	folder: MailFolderKey
	labelId?: string
}

export default function MailFolderPage({ folder, labelId }: Props) {
	const { auth } = useApp()
	const activeAccount = auth?.mailgunAccounts?.find((account) => account.isActive)

	if (auth && !activeAccount?.mailboxAddress) {
		return (
			<>
				<Head title="Mail" />

				<div className="flex h-[calc(100vh-4rem)] flex-col items-center justify-center gap-4 text-center">
					<h1 className="text-xl font-semibold">Set up your mail address first</h1>
					<p className="max-w-md text-muted-foreground">
						You need a mailbox address before you can send or receive mail. Add one in your profile
						settings to get started.
					</p>
					<Button asChild>
						<Link href="/settings/profile">Go to Settings</Link>
					</Button>
				</div>
			</>
		)
	}

	return (
		<>
			<Head title="Mail" />
			<MailShell
				folder={folder}
				labelId={labelId}
			/>
		</>
	)
}
