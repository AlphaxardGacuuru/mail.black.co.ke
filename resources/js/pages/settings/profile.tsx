import { Head } from "@/lib/spa"
import { LoaderCircle } from "lucide-react"
import { useState } from "react"
import { useApp } from "@/contexts/AppContext"
import MailgunCredentialsController from "@/actions/App/Http/Controllers/Settings/MailgunCredentialsController"
import ProfileController from "@/actions/App/Http/Controllers/Settings/ProfileController"
import DeleteUser from "@/components/delete-user"
import Heading from "@/components/heading"
import InputError from "@/components/input-error"
import MailgunAccountManager from "@/components/mailgun-account-manager"
import RemoveMailgunCredentials from "@/components/remove-mailgun-credentials"
import PasswordInput from "@/components/password-input"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { SelectField, SelectItem } from "@/components/ui/select"
import Axios from "@/lib/axios"
import toast from "@/lib/toast"
import { edit } from "@/routes/profile"
import { send } from "@/routes/verification"

const MAILGUN_ENDPOINTS = [
	{ value: "api.mailgun.net", label: "United States (api.mailgun.net)" },
	{ value: "api.eu.mailgun.net", label: "Europe (api.eu.mailgun.net)" },
]

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
		mailboxAddress: (auth?.mailboxAddress as string | undefined) ?? "",
	}
	const mailgunConfigured = Boolean(auth?.mailgunConfigured)
	const mailgunDomain = (auth?.mailgunDomain as string | undefined) ?? ""
	const mailgunEndpoint = (auth?.mailgunEndpoint as string | undefined) || "api.mailgun.net"

	const [processing, setProcessing] = useState(false)
	const [errors, setErrors] = useState<Record<string, string>>({})

	const [mailgunProcessing, setMailgunProcessing] = useState(false)
	const [mailgunErrors, setMailgunErrors] = useState<Record<string, string>>({})
	const [selectedEndpoint, setSelectedEndpoint] = useState(mailgunEndpoint)

	function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
		event.preventDefault()
		setProcessing(true)
		setErrors({})
		const fd = new FormData(event.currentTarget)
		const { action, method } = ProfileController.update.form()

		Axios
			.request({
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

	function handleMailgunSubmit(event: React.FormEvent<HTMLFormElement>) {
		event.preventDefault()
		setMailgunProcessing(true)
		setMailgunErrors({})
		const fd = new FormData(event.currentTarget)
		const { action, method } = MailgunCredentialsController.update.form()

		Axios
			.request({
				url: action,
				method,
				data: { ...Object.fromEntries(fd), mailgun_endpoint: selectedEndpoint },
			})
			.then((response) => {
				toast.success(response.data.message)
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
					setMailgunErrors(
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
				setMailgunProcessing(false)
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
									onClick={() => Axios.request({ url: send().url, method: send().method })}
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
					title="Mailgun credentials"
					description="Set the address your mailbox sends and receives mail as, and optionally connect your own Mailgun account to send through it instead of the shared system account."
				/>

				<form
					onSubmit={handleMailgunSubmit}
					className="space-y-6">
					<div className="grid gap-2">
						<Input
							id="mailbox_address"
							label="Mail address"
							type="email"
							className="mt-1 block w-full"
							defaultValue={user.mailboxAddress}
							name="mailbox_address"
							autoComplete="off"
						/>
						<p className="text-sm text-muted-foreground">
							Used for sending and receiving mail in the Mail inbox. Separate from your login email above.
						</p>

						<InputError
							className="mt-2"
							message={mailgunErrors.mailbox_address}
						/>
					</div>

					<div className="grid gap-2">
						<Input
							id="mailgun_domain"
							label="Mailgun domain"
							className="mt-1 block w-full"
							defaultValue={mailgunDomain}
							name="mailgun_domain"
							autoComplete="off"
						/>

						<InputError
							className="mt-2"
							message={mailgunErrors.mailgun_domain}
						/>
					</div>

					<div className="grid gap-2">
						<PasswordInput
							id="mailgun_api_key"
							label="Mailgun API key"
							className="mt-1 block w-full"
							name="mailgun_api_key"
							autoComplete="off"
						/>

						<InputError
							className="mt-2"
							message={mailgunErrors.mailgun_api_key}
						/>
					</div>

					<div className="grid gap-2">
						<SelectField
							label="Region"
							defaultValue={selectedEndpoint}
							onValueChange={setSelectedEndpoint}
							error={mailgunErrors.mailgun_endpoint}>
							{MAILGUN_ENDPOINTS.map((endpoint) => (
								<SelectItem
									key={endpoint.value}
									value={endpoint.value}>
									{endpoint.label}
								</SelectItem>
							))}
						</SelectField>
					</div>

					<div className="flex items-center justify-end gap-4">
						<Button
							disabled={mailgunProcessing}
							data-test="update-mailgun-credentials-button">
							Save
							{mailgunProcessing && <LoaderCircle className="h-4 w-4 animate-spin" />}
						</Button>
					</div>
				</form>

				{mailgunConfigured && <RemoveMailgunCredentials />}
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
