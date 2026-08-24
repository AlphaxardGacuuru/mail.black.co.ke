import { useState } from "react"
import MailgunCredentialsController from "@/actions/App/Http/Controllers/Settings/MailgunCredentialsController"
import { Button } from "@/components/ui/button"
import {
	Dialog,
	DialogClose,
	DialogContent,
	DialogDescription,
	DialogFooter,
	DialogTitle,
	DialogTrigger,
} from "@/components/ui/dialog"
import Axios from "@/lib/axios"

export default function RemoveMailgunCredentials() {
	const [processing, setProcessing] = useState(false)

	async function handleRemove() {
		setProcessing(true)
		try {
			const { action, method } = MailgunCredentialsController.destroy.form()
			const response = await Axios.request({ url: action, method })
			const finalUrl = (response.request as XMLHttpRequest | null)?.responseURL
			if (finalUrl) {
				window.location.assign(finalUrl)
			}
		} finally {
			setProcessing(false)
		}
	}

	return (
		<Dialog>
			<DialogTrigger asChild>
				<Button
					variant="secondary"
					data-test="remove-mailgun-credentials-button">
					Remove Mailgun credentials
				</Button>
			</DialogTrigger>
			<DialogContent>
				<DialogTitle>Remove your Mailgun credentials?</DialogTitle>
				<DialogDescription>
					Your mailbox will go back to sending mail through the shared system
					account. You can reconnect your own Mailgun account at any time.
				</DialogDescription>

				<DialogFooter className="gap-2">
					<DialogClose asChild>
						<Button variant="secondary">Cancel</Button>
					</DialogClose>

					<Button
						variant="destructive"
						disabled={processing}
						onClick={handleRemove}
						data-test="confirm-remove-mailgun-credentials-button">
						Remove
					</Button>
				</DialogFooter>
			</DialogContent>
		</Dialog>
	)
}
