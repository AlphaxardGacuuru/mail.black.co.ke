import { Head } from "@/lib/spa"
import { LoaderCircle } from "lucide-react"
import { useState } from "react"
import { useApp } from "@/contexts/AppContext"
import ProfileController from "@/actions/App/Http/Controllers/Settings/ProfileController"
import DeleteUser from "@/components/delete-user"
import Heading from "@/components/heading"
import InputError from "@/components/input-error"
import MailgunAccountManager from "@/components/mailgun/MailgunAccountManager"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import Axios from "@/lib/axios"
import toast from "@/lib/toast"
import { edit } from "@/routes/profile"
import { send } from "@/routes/verification"

export default function Profile({
	mustVerifyEmail = false,
	status,
}: {
	mustVerifyEmail: boolean
	status?: string
}) {
	const { auth } = useApp()
	const user = {
		name: auth?.name ?? "",
		email: auth?.email ?? "",
		email_verified_at: auth?.email_verified_at ?? null,
	}

	const [processing, setProcessing] = useState(false)
	const [errors, setErrors] = useState<Record<string, string>>({})

	function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
		event.preventDefault()
		setProcessing(true)
		setErrors({})
		const fd = new FormData(event.currentTarget)
		const { action, method } = ProfileController.update.form()

		Axios.request({
			url: action,
			method,
			data: Object.fromEntries(fd),
		})
			.then((response: { data: { message?: string } }) => {
				toast.success(response.data.message ?? "Profile updated.")
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
			<Head title="Profile settings" />

			<h1 className="sr-only">Profile settings</h1>

			<div className="space-y-6">
				<Heading
					variant="small"
					title="Profile information"
					description="Update your name and email address"
				/>

				<form
					onSubmit={handleSubmit}
					className="space-y-6">
					<div className="grid gap-2">
						<Input
							id="name"
							label="Full name"
							className="mt-1 block w-full"
							defaultValue={user.name}
							name="name"
							required
							autoComplete="name"
						/>

						<InputError
							className="mt-2"
							message={errors.name}
						/>
					</div>

					<div className="grid gap-2">
						<Input
							id="email"
							label="Email address"
							type="email"
							className="mt-1 block w-full"
							defaultValue={user.email}
							name="email"
							required
							autoComplete="username"
							readOnly
						/>

						<InputError
							className="mt-2"
							message={errors.email}
						/>
					</div>

					{mustVerifyEmail && user.email_verified_at === null && (
						<div>
							<p className="-mt-4 text-sm text-muted-foreground">
								Your email address is unverified.{" "}
								<button
									type="button"
									onClick={() =>
										Axios.request({ url: send().url, method: send().method })
									}
									className="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500">
									Click here to resend the verification email.
								</button>
							</p>

							{status === "verification-link-sent" && (
								<div className="mt-2 text-sm font-medium text-green-600">
									A new verification link has been sent to your email address.
								</div>
							)}
						</div>
					)}

					<div className="flex items-center justify-end gap-4">
						<Button
							disabled={processing}
							data-test="update-profile-button">
							Save
							{processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
						</Button>
					</div>
				</form>
			</div>

			<div className="space-y-6">
				<Heading
					variant="small"
					title="Mail accounts"
					description="Connect multiple Mailgun accounts and choose a default signature for each one."
				/>
				<MailgunAccountManager initialAccounts={auth?.mailgunAccounts ?? []} />
			</div>

			<DeleteUser />
		</>
	)
}

Profile.layout = {
	breadcrumbs: [
		{
			title: "Profile settings",
			href: edit(),
		},
	],
}
