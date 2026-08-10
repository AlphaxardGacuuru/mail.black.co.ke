import { Head } from "@/lib/spa"
import { ShieldCheck } from "lucide-react"
import { useEffect, useRef, useState } from "react"
import SecurityController from "@/actions/App/Http/Controllers/Settings/SecurityController"
import Heading from "@/components/heading"
import InputError from "@/components/input-error"
import PasswordInput from "@/components/password-input"
import TwoFactorRecoveryCodes from "@/components/two-factor-recovery-codes"
import TwoFactorSetupModal from "@/components/two-factor-setup-modal"
import { Button } from "@/components/ui/button"
import { useTwoFactorAuth } from "@/hooks/use-two-factor-auth"
import axios from "@/lib/axios"
import { useApp } from "@/contexts/AppContext"
import { edit } from "@/routes/security"
import { disable, enable } from '@/routes/two-factor'

type Props = {
	requiresConfirmation?: boolean
}

export default function Security({
	requiresConfirmation = false,
}: Props) {
	const { auth } = useApp()
	const twoFactorEnabled = auth?.twoFactorEnabled ?? false
	const passwordInput = useRef<HTMLInputElement>(null)
	const currentPasswordInput = useRef<HTMLInputElement>(null)

	const {
		qrCodeSvg,
		hasSetupData,
		manualSetupKey,
		clearSetupData,
		clearTwoFactorAuthData,
		fetchSetupData,
		recoveryCodesList,
		fetchRecoveryCodes,
		errors,
	} = useTwoFactorAuth()
	const [showSetupModal, setShowSetupModal] = useState<boolean>(false)
	const prevTwoFactorEnabled = useRef(twoFactorEnabled)

	const [passwordProcessing, setPasswordProcessing] = useState(false)
	const [passwordErrors, setPasswordErrors] = useState<Record<string, string>>(
		{}
	)

	const [disable2faProcessing, setDisable2faProcessing] = useState(false)
	const [enable2faProcessing, setEnable2faProcessing] = useState(false)

	useEffect(() => {
		if (prevTwoFactorEnabled.current && !twoFactorEnabled) {
			clearTwoFactorAuthData()
		}

		prevTwoFactorEnabled.current = twoFactorEnabled
	}, [twoFactorEnabled, clearTwoFactorAuthData])

	async function handlePasswordSubmit(event: React.FormEvent<HTMLFormElement>) {
		event.preventDefault()
		setPasswordProcessing(true)
		setPasswordErrors({})
		const form = event.currentTarget
		const fd = new FormData(form)
		try {
			const { action, method } = SecurityController.update.form()
			const response = await axios.request({
				url: action,
				method,
				data: Object.fromEntries(fd),
			})
			const finalUrl = (response.request as XMLHttpRequest | null)?.responseURL
			// Reset all password fields on success
			form
				.querySelectorAll<HTMLInputElement>('input[type="password"]')
				.forEach((el) => {
					el.value = ""
				})
			if (finalUrl) {
				window.location.assign(finalUrl)
			}
		} catch (err: unknown) {
			const e = err as {
				response?: {
					status?: number
					data?: { errors?: Record<string, string | string[]> }
				}
			}
			if (e.response?.status === 422) {
				const raw = e.response.data?.errors ?? {}
				const normalized = Object.fromEntries(
					Object.entries(raw).map(([k, v]) => [
						k,
						Array.isArray(v) ? String(v[0] ?? "") : String(v),
					])
				)
				setPasswordErrors(normalized)
				if (normalized.password) {
					passwordInput.current?.focus()
					form
						.querySelector<HTMLInputElement>('[name="password"]')
						?.setAttribute("value", "")
				}
				if (normalized.current_password) {
					currentPasswordInput.current?.focus()
					form
						.querySelector<HTMLInputElement>('[name="current_password"]')
						?.setAttribute("value", "")
				}
			}
		} finally {
			setPasswordProcessing(false)
		}
	}

	function handleDisable2fa() {
		setDisable2faProcessing(true)
		const { url, method } = disable()
		axios
			.request({ url, method })
			.finally(() => setDisable2faProcessing(false))
	}

	function handleEnable2fa() {
		setEnable2faProcessing(true)
		const { url, method } = enable()
		axios
			.request({ url, method })
			.then(() => setShowSetupModal(true))
			.finally(() => setEnable2faProcessing(false))
	}

	return (
		<>
			<Head title="Security settings" />

			<h1 className="sr-only">Security settings</h1>

			<div className="space-y-6">
				<Heading
					variant="small"
					title="Update password"
					description="Ensure your account is using a long, random password to stay secure"
				/>

				<form
					onSubmit={handlePasswordSubmit}
					className="space-y-6">
					<div className="grid gap-2">
						<PasswordInput
							id="current_password"
							ref={currentPasswordInput}
							name="current_password"
							className="mt-1 block w-full"
							autoComplete="current-password"
							label="Current password"
						/>

						<InputError message={passwordErrors.current_password} />
					</div>

					<div className="grid gap-2">
						<PasswordInput
							id="password"
							ref={passwordInput}
							name="password"
							className="mt-1 block w-full"
							autoComplete="new-password"
							label="New password"
						/>

						<InputError message={passwordErrors.password} />
					</div>

					<div className="grid gap-2">
						<PasswordInput
							id="password_confirmation"
							name="password_confirmation"
							className="mt-1 block w-full"
							autoComplete="new-password"
							label="Confirm password"
						/>

						<InputError message={passwordErrors.password_confirmation} />
					</div>

					<div className="flex items-center gap-4">
						<Button
							disabled={passwordProcessing}
							data-test="update-password-button">
							Save password
						</Button>
					</div>
				</form>
			</div>

			<div className="space-y-6">
					<Heading
						variant="small"
						title="Two-factor authentication"
						description="Manage your two-factor authentication settings"
					/>
					{twoFactorEnabled ? (
						<div className="flex flex-col items-start justify-start space-y-4">
							<p className="text-sm text-muted-foreground">
								You will be prompted for a secure, random pin during login,
								which you can retrieve from the TOTP-supported application on
								your phone.
							</p>

							<div className="relative inline">
								<Button
									variant="destructive"
									type="button"
									disabled={disable2faProcessing}
									onClick={handleDisable2fa}>
									Disable 2FA
								</Button>
							</div>

							<TwoFactorRecoveryCodes
								recoveryCodesList={recoveryCodesList}
								fetchRecoveryCodes={fetchRecoveryCodes}
								errors={errors}
							/>
						</div>
					) : (
						<div className="flex flex-col items-start justify-start space-y-4">
							<p className="text-sm text-muted-foreground">
								When you enable two-factor authentication, you will be prompted
								for a secure pin during login. This pin can be retrieved from a
								TOTP-supported application on your phone.
							</p>

							<div>
								{hasSetupData ? (
									<Button onClick={() => setShowSetupModal(true)}>
										<ShieldCheck />
										Continue setup
									</Button>
								) : (
									<Button
										type="button"
										disabled={enable2faProcessing}
										onClick={handleEnable2fa}>
										Enable 2FA
									</Button>
								)}
							</div>
						</div>
					)}

					<TwoFactorSetupModal
						isOpen={showSetupModal}
						onClose={() => setShowSetupModal(false)}
						requiresConfirmation={requiresConfirmation}
						twoFactorEnabled={twoFactorEnabled}
						qrCodeSvg={qrCodeSvg}
						manualSetupKey={manualSetupKey}
						clearSetupData={clearSetupData}
						fetchSetupData={fetchSetupData}
						errors={errors}
					/>
				</div>
		</>
	)
}

Security.layout = {
	breadcrumbs: [
		{
			title: "Security settings",
			href: edit(),
		},
	],
}
