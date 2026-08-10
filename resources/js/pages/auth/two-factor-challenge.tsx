import { Head } from "@/lib/spa"
import { useEffect, useMemo, useState } from "react"
import { useNavigate } from "@tanstack/react-router"
import { REGEXP_ONLY_DIGITS } from "input-otp"
import toast from "@/lib/toast"
import axios from "@/lib/axios"
import InputError from "@/components/input-error"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Spinner } from "@/components/ui/spinner"
import { InputOTP, InputOTPGroup, InputOTPSlot } from "@/components/ui/input-otp"
import { OTP_MAX_LENGTH } from "@/hooks/use-two-factor-auth"
import { store } from "@/actions/App/Http/Controllers/Auth/TwoFactorChallengeController"
import { useApp } from "@/contexts/AppContext"
import { invalidateAuth } from "@/middleware/auth"

export default function TwoFactorChallenge() {
	const { setLocalStorage } = useApp()
	const navigate = useNavigate()
	const params = new URLSearchParams(window.location.search)
	const pendingToken = params.get("pending_token")

	const [showRecovery, setShowRecovery] = useState(false)
	const [code, setCode] = useState("")
	const [processing, setProcessing] = useState(false)
	const [errors, setErrors] = useState<Record<string, string>>({})

	useEffect(() => {
		if (!pendingToken) {
			navigate({ to: "/login" })
		}
	}, [])

	const content = useMemo(
		() =>
			showRecovery
				? {
						title: "Recovery code",
						description:
							"Enter one of your emergency recovery codes to access your account.",
						toggleText: "Use an authentication code instead",
					}
				: {
						title: "Authentication code",
						description:
							"Enter the 6-digit code from your authenticator app.",
						toggleText: "Use a recovery code instead",
					},
		[showRecovery]
	)

	function handleToggle() {
		setShowRecovery((v) => !v)
		setCode("")
		setErrors({})
	}

	function handleSubmit(event: React.SyntheticEvent<HTMLFormElement>) {
		event.preventDefault()
		if (!pendingToken) {
			return
		}
		setProcessing(true)
		setErrors({})

		const { url, method } = store()

		axios
			.request({
				url,
				method,
				data: { pending_token: pendingToken, code },
			})
			.then((response) => {
				setLocalStorage("sanctumToken", response.data.data)
				invalidateAuth()
				toast.success(response.data.message ?? "Logged in")
				navigate({ to: "/admin/dashboard" })
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
					if (raw.pending_token) {
						toast.error(String(raw.pending_token[0] ?? "Session expired"))
						navigate({ to: "/login" })
					}
				}
			})
			.finally(() => setProcessing(false))
	}

	return (
		<>
			<Head title="Two-factor authentication" />

			<form
				onSubmit={handleSubmit}
				className="flex flex-col gap-6"
				aria-busy={processing}>
				<div className="grid gap-6">
					{showRecovery ? (
						<div className="grid gap-2">
							<Input
								label="Recovery code"
								type="text"
								value={code}
								onChange={(e) => setCode(e.target.value)}
								placeholder="xxxx-xxxx"
								autoFocus
								required
							/>
							<InputError message={errors.code} />
						</div>
					) : (
						<div className="flex flex-col items-center gap-3">
							<InputOTP
								maxLength={OTP_MAX_LENGTH}
								value={code}
								onChange={setCode}
								disabled={processing}
								pattern={REGEXP_ONLY_DIGITS}>
								<InputOTPGroup>
									{Array.from({ length: OTP_MAX_LENGTH }, (_, i) => (
										<InputOTPSlot
											key={i}
											index={i}
										/>
									))}
								</InputOTPGroup>
							</InputOTP>
							<InputError message={errors.code} />
						</div>
					)}

					<Button
						type="submit"
						className="w-full"
						disabled={processing || code.length === 0}>
						{processing && <Spinner />}
						Continue
					</Button>
				</div>

				<div className="text-center text-sm text-muted-foreground">
					or you can{" "}
					<button
						type="button"
						className="cursor-pointer text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current dark:decoration-neutral-500"
						onClick={handleToggle}>
						{content.toggleText}
					</button>
				</div>
			</form>
		</>
	)
}

TwoFactorChallenge.layout = {
	title: "Two-factor authentication",
	description: "Confirm access to your account.",
}
