import { Head } from "@/lib/spa"
import { LoaderCircle } from "lucide-react"
import { type FormEvent, useState } from "react"
import { useApp } from "@/contexts/AppContext"
import ProfileController from "@/actions/App/Http/Controllers/Settings/ProfileController"
import { AvatarUploadField } from "@/components/avatar-upload-field"
import DeleteUser from "@/components/delete-user"
import Heading from "@/components/heading"
import InputError from "@/components/input-error"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { useInitials } from "@/hooks/use-initials"
import Axios from "@/lib/axios"
import {
	errorMessage,
	useJobSearch,
	useSaveContactDetails,
} from "@/queries/job-search"
import toast from "@/lib/toast"
import { edit } from "@/routes/profile"
import { send } from "@/routes/verification"
import type { ContactDetails } from "@/types"

export default function Profile({
	mustVerifyEmail = false,
	status,
}: {
	mustVerifyEmail: boolean
	status?: string
}) {
	const { auth } = useApp()
	const getInitials = useInitials()
	const user = {
		name: auth?.name ?? "",
		email: auth?.email ?? "",
		avatar: auth?.avatar,
		email_verified_at: auth?.email_verified_at ?? null,
	}

	const [processing, setProcessing] = useState(false)
	const [errors, setErrors] = useState<Record<string, string>>({})
	const [resendingVerification, setResendingVerification] = useState(false)

	async function handleResendVerification() {
		setResendingVerification(true)
		try {
			await Axios.request({ url: send().url, method: send().method })
		} finally {
			setResendingVerification(false)
		}
	}

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

				{/* Avatar Start */}
				<AvatarUploadField
					avatar={user.avatar}
					initials={getInitials(user.name)}
				/>
				{/* Avatar End */}

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
									disabled={resendingVerification}
									onClick={handleResendVerification}
									className="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500 disabled:pointer-events-none disabled:opacity-70">
									{resendingVerification && (
										<LoaderCircle className="mr-1 inline-block size-3 animate-spin" />
									)}
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

			<JobApplicationDetails />

			<DeleteUser />
		</>
	)
}

const EMPTY_CONTACT_DETAILS: ContactDetails = {
	phone: null,
	location: null,
	linkedin: null,
	github: null,
	portfolio: null,
	twitter: null,
}

/**
 * The details employers ask for on application forms, which the app fills
 * in when it applies for you.
 */
function JobApplicationDetails() {
	const { data, isPending } = useJobSearch()

	if (isPending) {
		return null
	}

	return (
		<ContactDetailsForm
			details={data?.profile?.contactDetails ?? EMPTY_CONTACT_DETAILS}
			cvLocation={data?.profile?.profile?.location}
		/>
	)
}

function ContactDetailsForm({
	details,
	cvLocation,
}: {
	details: ContactDetails
	cvLocation?: string
}) {
	const save = useSaveContactDetails()
	const [form, setForm] = useState(details)
	const [errors, setErrors] = useState<
		Partial<Record<keyof ContactDetails, string>>
	>({})

	function field(key: keyof ContactDetails) {
		return {
			id: key,
			name: key,
			value: form[key] ?? "",
			onChange: (event: React.ChangeEvent<HTMLInputElement>) =>
				setForm({ ...form, [key]: event.target.value }),
			error: errors[key],
		}
	}

	function submit(event: FormEvent): void {
		event.preventDefault()
		setErrors({})

		save
			.mutateAsync(form)
			.then((data) => {
				setForm(data.profile?.contactDetails ?? form)
				toast.success(data.message)
			})
			.catch((error) => {
				const raw =
					(
						error as {
							response?: { data?: { errors?: Record<string, string[]> } }
						}
					).response?.data?.errors ?? {}

				setErrors(
					Object.fromEntries(
						Object.entries(raw).map(([key, messages]) => [key, messages[0]])
					)
				)
				toast.error(errorMessage(error, "Unable to save your details."))
			})
	}

	return (
		<div className="space-y-6">
			<Heading
				variant="small"
				title="Job application details"
				description="What employers ask for on application forms. When the app applies for you, it fills these in for you."
			/>

			<form
				onSubmit={submit}
				className="space-y-6">
				{/* Phone Start */}
				<Input
					label="Phone number"
					type="tel"
					autoComplete="tel"
					{...field("phone")}
				/>
				{/* Phone End */}

				{/* Location Start */}
				<Input
					label="Location"
					autoComplete="address-level2"
					helperText={
						cvLocation && !form.location
							? `Your CV says ${cvLocation}.`
							: "City and country, e.g. Nairobi, Kenya"
					}
					{...field("location")}
				/>
				{/* Location End */}

				{/* LinkedIn Start */}
				<Input
					label="LinkedIn profile"
					inputMode="url"
					autoCapitalize="none"
					{...field("linkedin")}
				/>
				{/* LinkedIn End */}

				{/* GitHub Start */}
				<Input
					label="GitHub profile"
					inputMode="url"
					autoCapitalize="none"
					{...field("github")}
				/>
				{/* GitHub End */}

				{/* Portfolio Start */}
				<Input
					label="Portfolio or website"
					inputMode="url"
					autoCapitalize="none"
					{...field("portfolio")}
				/>
				{/* Portfolio End */}

				{/* X Start */}
				<Input
					label="X (Twitter) profile"
					inputMode="url"
					autoCapitalize="none"
					{...field("twitter")}
				/>
				{/* X End */}

				<div className="flex items-center justify-end gap-4">
					<Button disabled={save.isPending}>
						Save
						{save.isPending && (
							<LoaderCircle className="h-4 w-4 animate-spin" />
						)}
					</Button>
				</div>
			</form>
		</div>
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
