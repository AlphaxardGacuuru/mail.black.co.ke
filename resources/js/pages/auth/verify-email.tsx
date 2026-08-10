// Components
import { Head } from "@/lib/spa"
import { useState } from "react"
import { Link } from "@/components/ui/link"
import { Button } from "@/components/ui/button"
import { Spinner } from "@/components/ui/spinner"
import axios from "@/lib/axios"
import { logout } from "@/routes"
import { send } from "@/routes/verification"

export default function VerifyEmail({ status }: { status?: string }) {
	const [processing, setProcessing] = useState(false)

	async function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
		event.preventDefault()
		setProcessing(true)
		try {
			const { action, method } = send.form()
			const response = await axios.request({ url: action, method })
			const finalUrl = (response.request as XMLHttpRequest | null)?.responseURL
			if (finalUrl) {
				window.location.assign(finalUrl)
			}
		} finally {
			setProcessing(false)
		}
	}

	return (
		<>
			<Head title="Email verification" />

			{status === "verification-link-sent" && (
				<div className="mb-4 text-center text-sm font-medium text-green-600">
					A new verification link has been sent to the email address you
					provided during registration.
				</div>
			)}

			<form
				onSubmit={handleSubmit}
				className="space-y-6 text-center">
				<Button
					disabled={processing}
					variant="secondary">
					{processing && <Spinner />}
					Resend verification email
				</Button>

				<Link
					href={logout().url}
					variant="text"
					size="none"
					className="mx-auto block text-sm">
					Log out
				</Link>
			</form>
		</>
	)
}

VerifyEmail.layout = {
	title: "Verify email",
	description:
		"Please verify your email address by clicking on the link we just emailed to you.",
}
