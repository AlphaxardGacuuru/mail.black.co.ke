import { useRef, useState } from "react"
import ProfileController from "@/actions/App/Http/Controllers/Settings/ProfileController"
import Heading from "@/components/heading"
import InputError from "@/components/input-error"
import PasswordInput from "@/components/password-input"
import { Button } from "@/components/ui/button"
import {
	Dialog,
	DialogClose,
	DialogContent,
	DialogDescription,
	DialogFooter,
	DialogTitle,
	DialogTrigger,
} from "@/components/ui/dialog"
import { Label } from "@/components/ui/label"
import axios from "@/lib/axios"

export default function DeleteUser() {
	const passwordInput = useRef<HTMLInputElement>(null)
	const [processing, setProcessing] = useState(false)
	const [errors, setErrors] = useState<Record<string, string>>({})

	function resetAndClearErrors() {
		setErrors({})
		if (passwordInput.current) {
			passwordInput.current.value = ""
		}
	}

	async function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
		event.preventDefault()
		setProcessing(true)
		setErrors({})
		const fd = new FormData(event.currentTarget)
		try {
			const { action, method } = ProfileController.destroy.form()
			const response = await axios.request({
				url: action,
				method,
				data: Object.fromEntries(fd),
			})
			const finalUrl = (response.request as XMLHttpRequest | null)?.responseURL
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
				setErrors(
					Object.fromEntries(
						Object.entries(raw).map(([k, v]) => [
							k,
							Array.isArray(v) ? String(v[0] ?? "") : String(v),
						])
					)
				)
			}
			passwordInput.current?.focus()
		} finally {
			setProcessing(false)
		}
	}

	return (
		<div className="space-y-6">
			<Heading
				variant="small"
				title="Delete account"
				description="Delete your account and all of its resources"
			/>
			<div className="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10">
				<div className="relative space-y-0.5 text-red-600 dark:text-red-100">
					<p className="font-medium">Warning</p>
					<p className="text-sm">
						Please proceed with caution, this cannot be undone.
					</p>
				</div>

				<Dialog>
					<DialogTrigger asChild>
						<Button
							variant="destructive"
							data-test="delete-user-button">
							Delete account
						</Button>
					</DialogTrigger>
					<DialogContent>
						<DialogTitle>
							Are you sure you want to delete your account?
						</DialogTitle>
						<DialogDescription>
							Once your account is deleted, all of its resources and data will
							also be permanently deleted. Please enter your password to confirm
							you would like to permanently delete your account.
						</DialogDescription>

						<form
							onSubmit={handleSubmit}
							className="space-y-6">
							<div className="grid gap-2">
								<Label
									htmlFor="password"
									className="sr-only">
									Password
								</Label>

								<PasswordInput
									id="password"
									name="password"
									ref={passwordInput}
									placeholder="Password"
									autoComplete="current-password"
								/>

								<InputError message={errors.password} />
							</div>

							<DialogFooter className="gap-2">
								<DialogClose asChild>
									<Button
										variant="secondary"
										onClick={() => resetAndClearErrors()}>
										Cancel
									</Button>
								</DialogClose>

								<Button
									variant="destructive"
									disabled={processing}
									asChild>
									<button
										type="submit"
										data-test="confirm-delete-user-button">
										Delete account
									</button>
								</Button>
							</DialogFooter>
						</form>
					</DialogContent>
				</Dialog>
			</div>
		</div>
	)
}
