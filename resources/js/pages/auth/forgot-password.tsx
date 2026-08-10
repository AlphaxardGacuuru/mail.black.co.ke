// Components
import { Head } from "@/lib/spa"
import { LoaderCircle } from "lucide-react"
import { useState } from "react"
import InputError from "@/components/input-error"
import { Link } from "@/components/ui/link"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import axios from "@/lib/axios"
import { login } from "@/routes"
import { email } from "@/routes/password"

export default function ForgotPassword({ status }: { status?: string }) {
	const [processing, setProcessing] = useState(false)
	const [errors, setErrors] = useState<Record<string, string>>({})

	function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
		event.preventDefault()
		setProcessing(true)
		setErrors({})
		const fd = new FormData(event.currentTarget)

		const { action, method } = email.form()

		axios
			.request({
				url: action,
				method,
				data: Object.fromEntries(fd),
			})
			.then((response) => {
				const finalUrl = (response.request as XMLHttpRequest | null)
					?.responseURL

				if (finalUrl) {
					window.location.assign(finalUrl)
				}
			})
			.catch((err: unknown) => {
				const e = err as {
					response?: {
						status?: number
						data?: { errors?: Record<string, string | string[]> }
					}
				}

				if (e.response?.status === 422) {
					const raw = e.response.data?.errors ?? {}
					setErrors(
						Object.fromEntries(
							Object.entries(raw).map(([k, v]) => [
								k,
								Array.isArray(v) ? String(v[0] ?? "") : String(v),
							])
						)
					)
				}
			})
			.finally(() => {
				setProcessing(false)
			})
	}

	return (
		<>
			<Head title="Forgot password" />

			{status && (
				<div className="mb-4 text-center text-sm font-medium text-green-600">
					{status}
				</div>
			)}

			<div className="space-y-6">
				<form onSubmit={handleSubmit}>
					<div className="grid gap-2">
						<Input
							id="email"
							label="Email"
							type="email"
							name="email"
							autoComplete="off"
							autoFocus
						/>

						<InputError message={errors.email} />
					</div>

					<div className="my-6 flex items-center justify-start">
						<Button
							className="w-full"
							disabled={processing}
							data-test="email-password-reset-link-button">
							{processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
							Email password reset link
						</Button>
					</div>
				</form>

				<div className="space-x-1 text-center text-sm text-muted-foreground">
					<span>Or, return to</span>
					<Link
						href={login().url}
						variant="text"
						size="none">
						log in
					</Link>
				</div>
			</div>
		</>
	)
}

ForgotPassword.layout = {
	title: "Forgot password",
	description: "Enter your email to receive a password reset link",
}
