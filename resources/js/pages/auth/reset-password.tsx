import { Head } from "@/lib/spa"
import { useState } from "react"
import InputError from "@/components/input-error"
import PasswordInput from "@/components/password-input"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Spinner } from "@/components/ui/spinner"
import axios from "@/lib/axios"
import { update } from "@/routes/password"

type Props = {
	token: string
	email: string
}

export default function ResetPassword({ token = "", email = "" }: Props) {
	const [processing, setProcessing] = useState(false)
	const [errors, setErrors] = useState<Record<string, string>>({})

	function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
		event.preventDefault()
		setProcessing(true)
		setErrors({})
		const fd = new FormData(event.currentTarget)
		const data = { ...Object.fromEntries(fd), token, email }

		const { action, method } = update.form()

		axios
			.request({ url: action, method, data })
			.then((response) => {
				const finalUrl = (response.request as XMLHttpRequest | null)?.responseURL

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
			<Head title="Reset password" />

			<form onSubmit={handleSubmit}>
				<div className="grid gap-6">
					<div className="grid gap-2">
						<Input
							id="email"
							label="Email"
							type="email"
							name="email"
							autoComplete="email"
							defaultValue={email}
							className="mt-1 block w-full"
							readOnly
						/>
						<InputError
							message={errors.email}
							className="mt-2"
						/>
					</div>

					<div className="grid gap-2">
						<PasswordInput
							id="password"
							label="Password"
							name="password"
							autoComplete="new-password"
							className="mt-1 block w-full"
							autoFocus
							placeholder="Password"
						/>
						<InputError message={errors.password} />
					</div>

					<div className="grid gap-2">
						<PasswordInput
							id="password_confirmation"
							label="Confirm password"
							name="password_confirmation"
							autoComplete="new-password"
							className="mt-1 block w-full"
							placeholder="Confirm password"
						/>
						<InputError
							message={errors.password_confirmation}
							className="mt-2"
						/>
					</div>

					<Button
						type="submit"
						className="mt-4 w-full"
						disabled={processing}
						data-test="reset-password-button">
						{processing && <Spinner />}
						Reset password
					</Button>
				</div>
			</form>
		</>
	)
}

ResetPassword.layout = {
	title: "Reset password",
	description: "Please enter your new password below",
}
