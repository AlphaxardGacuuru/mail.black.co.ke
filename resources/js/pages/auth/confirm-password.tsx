import { Head } from "@/lib/spa"
import { useState } from "react"
import InputError from "@/components/input-error"
import PasswordInput from "@/components/password-input"
import { Button } from "@/components/ui/button"
import { Spinner } from "@/components/ui/spinner"
import axios from "@/lib/axios"
import { confirm } from "@/routes/password"

export default function ConfirmPassword() {
	const [processing, setProcessing] = useState(false)
	const [errors, setErrors] = useState<Record<string, string>>({})

	function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
		event.preventDefault()
		setProcessing(true)
		setErrors({})
		const fd = new FormData(event.currentTarget)
		const passwordField =
			event.currentTarget.querySelector<HTMLInputElement>('[name="password"]')
		const { action, method } = confirm.form()

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
					if (passwordField) {
						passwordField.value = ""
					}
				}
			})
			.finally(() => {
				setProcessing(false)
			})
	}

	return (
		<>
			<Head title="Confirm password" />

			<form onSubmit={handleSubmit}>
				<div className="space-y-6">
					<div className="grid gap-2">
						<PasswordInput
							id="password"
							label="Password"
							name="password"
							autoComplete="current-password"
							autoFocus
						/>

						<InputError message={errors.password} />
					</div>

					<div className="flex items-center">
						<Button
							className="w-full"
							disabled={processing}
							data-test="confirm-password-button">
							{processing && <Spinner />}
							Confirm password
						</Button>
					</div>
				</div>
			</form>
		</>
	)
}

ConfirmPassword.layout = {
	title: "Confirm your password",
	description:
		"This is a secure area of the application. Please confirm your password before continuing.",
}
