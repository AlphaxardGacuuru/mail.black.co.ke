import { Head } from "@/lib/spa"
import { useState, useEffect } from "react"
import { useNavigate } from "@tanstack/react-router"
import axios from "@/lib/axios"
import InputError from "@/components/input-error"
import PasswordInput from "@/components/password-input"
import { Link } from "@/components/ui/link"
import { Button } from "@/components/ui/button"
import { Checkbox } from "@/components/ui/checkbox"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Spinner } from "@/components/ui/spinner"
import { register } from "@/routes"
import { store } from "@/routes/login"
import { request } from "@/routes/password"
import toast from "@/lib/toast"
import { useApp } from "@/contexts/AppContext"
import { invalidateAuth } from "@/middleware/auth"

type Props = {
	status?: string
	canResetPassword: boolean
	canRegister: boolean
	canGoogleLogin: boolean
	googleLoginUrl: string
}

export default function Login({
	status,
	canResetPassword = true,
	canRegister = true,
	canGoogleLogin = true,
	googleLoginUrl = "login/google/redirect",
}: Props) {
	const props = useApp()
	const navigate = useNavigate()

	const searchParams = new URLSearchParams(window.location.search)
	const tenantLogin = searchParams.has("tenant")
	const oauthError = searchParams.get("error")

	const [email, setEmail] = useState("")
	const [password, setPassword] = useState("")
	const [googleLoading, setGoogleLoading] = useState(false)
	const [processing, setProcessing] = useState(false)
	const [errors, setErrors] = useState<Record<string, string>>({})

	useEffect(() => {
		if (oauthError) {
			toast.error(oauthError)
		}
	}, [oauthError])

	function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
		event.preventDefault()
		setProcessing(true)
		setErrors({})

		const { action, method } = store.form()

		axios
			.get("/sanctum/csrf-cookie")
			.then(() => {
				axios
					.request({
						url: action,
						method,
						data: {
							email,
							password,
							device_name: "web",
						},
					})
					.then((response) => {
						if (response.data.two_factor) {
							navigate({
								to: "/auth/two-factor-challenge",
								search: { pending_token: response.data.data },
							})
							return
						}

						toast.success(response.data.message)

						props.setLocalStorage("sanctumToken", response.data.data)
						invalidateAuth()

						navigate({
							to: tenantLogin ? "/tenant/dashboard" : "/admin/dashboard",
						})
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

							const validationMessages = Object.entries(raw)
								.map(([, value]) => String(value[0] ?? ""))
								.filter(Boolean)

							setErrors(
								Object.fromEntries(
									Object.entries(raw).map(([k, v]) => [
										k,
										Array.isArray(v) ? String(v[0] ?? "") : String(v),
									])
								)
							)

							toast.error(validationMessages.join(" • "))
						}

						toast.error("Something went wrong!")
					})
					.finally(() => {
						setProcessing(false)
					})
			})
			.catch(() => {
				// Remove loader
				setProcessing(false)
			})
	}

	return (
		<>
			<Head title="Log in" />

			<form
				onSubmit={handleSubmit}
				className="flex flex-col gap-6"
				aria-busy={processing}>
				<div className="grid gap-6">
					{canGoogleLogin && (
						<div className="grid gap-3">
							<Button
								type="button"
								variant="transparent"
								className="w-full"
								disabled={googleLoading || processing}
								asChild>
								<a
									href={googleLoginUrl}
									onClick={(event) => {
										if (googleLoading || processing) {
											event.preventDefault()

											return
										}

										setGoogleLoading(true)
									}}
									className={googleLoading ? "pointer-events-none" : undefined}
									aria-disabled={googleLoading || processing}>
									{googleLoading ? (
										<>
											<Spinner />
											Redirecting to Google...
										</>
									) : (
										<>
											<svg
												aria-hidden="true"
												className="size-4"
												viewBox="0 0 24 24">
												<path
													fill="#4285F4"
													d="M23.49 12.27c0-.79-.07-1.54-.2-2.27H12v4.3h6.44a5.5 5.5 0 0 1-2.39 3.61v3h3.86c2.25-2.07 3.58-5.12 3.58-8.64Z"
												/>
												<path
													fill="#34A853"
													d="M12 24c3.24 0 5.96-1.08 7.95-2.92l-3.86-3c-1.08.72-2.46 1.14-4.09 1.14-3.14 0-5.8-2.12-6.75-4.97H1.26v3.1A12 12 0 0 0 12 24Z"
												/>
												<path
													fill="#FBBC05"
													d="M5.25 14.25A7.2 7.2 0 0 1 4.87 12c0-.78.14-1.53.38-2.25v-3.1H1.26A12 12 0 0 0 0 12c0 1.93.46 3.76 1.26 5.35l3.99-3.1Z"
												/>
												<path
													fill="#EA4335"
													d="M12 4.78c1.76 0 3.34.61 4.58 1.82l3.43-3.43C17.95 1.25 15.23 0 12 0A12 12 0 0 0 1.26 6.65l3.99 3.1c.94-2.85 3.61-4.97 6.75-4.97Z"
												/>
											</svg>
											Continue with Google
										</>
									)}
								</a>
							</Button>
							<InputError message={errors.socialite} />
							<div className="relative">
								<div className="absolute inset-0 flex items-center">
									<span className="w-full border-t" />
								</div>
								<div className="relative flex justify-center text-xs uppercase">
									<span className="bg-background px-2">
										Or continue with email
									</span>
								</div>
							</div>
						</div>
					)}

					<div className="grid gap-2">
						<Input
							id="email"
							label="Email Address"
							type="email"
							name="email"
							required
							autoFocus
							tabIndex={1}
							autoComplete="email"
							onChange={(e) => setEmail(e.target.value)}
						/>
						<InputError message={errors.email} />
					</div>

					<div className="grid gap-2">
						<PasswordInput
							id="password"
							name="password"
							required
							tabIndex={2}
							autoComplete="current-password"
							onChange={(e) => setPassword(e.target.value)}
						/>
						<InputError message={errors.password} />
						<div className="flex items-center">
							{canResetPassword && (
								<Link
									href={request().url}
									variant="text"
									size="none"
									className="ml-auto text-sm"
									tabIndex={5}>
									Forgot password?
								</Link>
							)}
						</div>
					</div>

					<div className="flex items-center space-x-3">
						<Checkbox
							id="remember"
							name="remember"
							tabIndex={3}
						/>
						<Label htmlFor="remember">Remember me</Label>
					</div>

					<Button
						type="submit"
						className="mt-4 w-full"
						tabIndex={4}
						disabled={processing}
						data-test="login-button">
						{processing && <Spinner />}
						Log in
					</Button>
				</div>

				{canRegister && (
					<div className="text-center text-sm text-muted-foreground">
						Don't have an account?{" "}
						<Link
							href={register().url}
							variant="text"
							size="none"
							tabIndex={5}>
							Sign up
						</Link>
					</div>
				)}
			</form>

			{status && (
				<div className="mb-4 text-center text-sm font-medium text-green-600">
					{status}
				</div>
			)}
		</>
	)
}

Login.layout = {
	title: "Log in to your account",
	description: "Enter your email and password below to log in",
}
