import { Plus } from "lucide-react"
import { useState } from "react"
import { FilePond, registerPlugin } from "react-filepond"
import { Head } from "@/lib/spa"
import { useApp } from "@/contexts/AppContext"
import FilePondController from "@/actions/App/Http/Controllers/FilePondController"
import MailgunAccountController from "@/actions/App/Http/Controllers/Settings/MailgunAccountController"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import Heading from "@/components/heading"
import RichTextEditor from "@/components/rich-text-editor"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { SelectField, SelectItem } from "@/components/ui/select"
import type { MailgunAccount } from "@/types"
import Axios from "@/lib/axios"
import toast from "@/lib/toast"
import { invalidateAuth } from "@/middleware/auth"
import { edit } from "@/routes/mail-accounts"

// Import React FilePond
// import { FilePond, registerPlugin } from "react-filepond"

// Import FilePond styles
import "filepond/dist/filepond.min.css"

// Import the Image EXIF Orientation and Image Preview plugins
// Note: These need to be installed separately
import FilePondPluginImageExifOrientation from "filepond-plugin-image-exif-orientation"
import FilePondPluginImagePreview from "filepond-plugin-image-preview"
import FilePondPluginFileValidateType from "filepond-plugin-file-validate-type"
import FilePondPluginImageCrop from "filepond-plugin-image-crop"
import FilePondPluginImageTransform from "filepond-plugin-image-transform"
import "filepond-plugin-image-preview/dist/filepond-plugin-image-preview.css"

// Register the plugins
registerPlugin(
	FilePondPluginImageExifOrientation,
	FilePondPluginImagePreview,
	FilePondPluginFileValidateType,
	FilePondPluginImageCrop,
	FilePondPluginImageTransform
)

const ENDPOINTS = [
	{ value: "api.mailgun.net", label: "United States" },
	{ value: "api.eu.mailgun.net", label: "Europe" },
]

type AccountForm = {
	mailbox_address: string
	mail_from_name: string
	mailgun_domain: string
	mailgun_api_key: string
	mailgun_endpoint: string
	signature: string
}

const emptyForm: AccountForm = {
	mailbox_address: "",
	mail_from_name: "",
	mailgun_domain: "",
	mailgun_api_key: "",
	mailgun_endpoint: "api.mailgun.net",
	signature: "",
}

export default function MailAccounts() {
	const { auth } = useApp()
	const [accounts, setAccounts] = useState<MailgunAccount[]>(
		auth?.mailgunAccounts ?? []
	)
	const [editingId, setEditingId] = useState<string | null>(null)
	const [form, setForm] = useState<AccountForm>(emptyForm)
	const [processing, setProcessing] = useState(false)
	const [showForm, setShowForm] = useState(false)

	function editAccount(account: MailgunAccount): void {
		setEditingId(account.id)
		setShowForm(true)
		setForm({
			mailbox_address: account.mailboxAddress,
			mail_from_name: account.mailFromName ?? "",
			mailgun_domain: account.mailgunDomain,
			mailgun_api_key: "",
			mailgun_endpoint: account.mailgunEndpoint,
			signature: account.signature ?? "",
		})
	}

	function reset(): void {
		setEditingId(null)
		setForm(emptyForm)
		setShowForm(false)
	}

	function handleAvatarUploaded(accountId: string, avatar: string): void {
		setAccounts((current) =>
			current.map((account) =>
				account.id === accountId ? { ...account, avatar } : account
			)
		)
		invalidateAuth()
	}

	function submit(event: React.FormEvent<HTMLFormElement>): void {
		event.preventDefault()
		setProcessing(true)
		const route = editingId
			? MailgunAccountController.update.patch(editingId)
			: MailgunAccountController.store.post()

		Axios.request({
			url: route.url,
			method: route.method,
			data: form,
		})
			.then((response) => {
				setAccounts(response.data.accounts)
				invalidateAuth()
				toast.success(response.data.message)
				reset()
			})
			.catch(() => toast.error("Unable to save mail account."))
			.finally(() => setProcessing(false))
	}

	return (
		<>
			<Head title="Mail accounts" />

			<h1 className="sr-only">Mail accounts</h1>

			<div className="space-y-6">
				<Heading
					variant="small"
					title="Mail accounts"
					description="Connect multiple Mailgun accounts and choose a default signature for each one."
				/>

				<div className="space-y-4">
					<div className="space-y-2">
						{accounts.map((account) => (
							<div
								key={account.id}
								className="flex items-center gap-3 rounded-md border p-3">
								<Avatar className="size-18">
									<AvatarImage
										src={account.avatar ?? undefined}
										alt={account.mailboxAddress}
									/>
									<AvatarFallback>
										{account.mailboxAddress.slice(0, 2).toUpperCase()}
									</AvatarFallback>
								</Avatar>
								<div className="min-w-0 flex-1">
									<p className="truncate font-medium">
										{account.mailFromName ?? ""}
									</p>
									<p className="truncate font-medium">
										{account.mailboxAddress}
									</p>
									<p className="text-sm text-muted-foreground">
										{account.mailgunDomain}
									</p>
								</div>
								{account.isActive && (
									<span className="text-xs text-primary">Active</span>
								)}
								<Button
									type="button"
									variant="outline"
									size="sm"
									onClick={() => editAccount(account)}>
									Edit
								</Button>
							</div>
						))}
					</div>

					{!showForm && (
						<Button
							type="button"
							variant="outline"
							onClick={() => setShowForm(true)}>
							<Plus className="size-4" />
							Add account
						</Button>
					)}

					{showForm && (
						<form
							onSubmit={submit}
							className="space-y-4 rounded-md border p-4">
							<h3 className="font-medium">
								{editingId ? "Edit mail account" : "Add mail account"}
							</h3>
							{editingId && (
								<div className="p-4">
									<FilePond
										name="filepond-mailgun-account-avatar"
										labelIdle='Drag & Drop your Profile Picture or <span class="filepond--label-action text-dark"> Browse </span>'
										stylePanelLayout="compact circle"
										credits={false}
										imageCropAspectRatio="1:1"
										acceptedFileTypes={["image/*"]}
										allowRevert={true}
										allowMultiple={false}
										server={{
											process: (
												fieldName,
												file,
												_metadata,
												load,
												error,
												progress,
												abort
											) => {
												const controller = new AbortController()
												const formData = new FormData()
												formData.append(fieldName, file, file.name)

												Axios.post(
													FilePondController.updateMailgunAccountAvatar.url(
														editingId
													),
													formData,
													{
														signal: controller.signal,
														onUploadProgress: (event) => {
															if (event.total) {
																progress(true, event.loaded, event.total)
															}
														},
													}
												)
													.then((response) => {
														const uploadedAvatar = response.data
															.avatar as string
														handleAvatarUploaded(editingId, uploadedAvatar)
														load(uploadedAvatar)
													})
													.catch(() => error("Upload failed"))

												return {
													abort: () => {
														controller.abort()
														abort()
													},
												}
											},
										}}
										onerror={() =>
											toast.error("Unable to update the profile picture.")
										}
									/>
								</div>
							)}
							<Input
								label="From name"
								value={form.mail_from_name}
								onChange={(event) =>
									setForm({ ...form, mail_from_name: event.target.value })
								}
							/>
							<Input
								label="Mail address"
								type="email"
								required
								value={form.mailbox_address}
								onChange={(event) =>
									setForm({ ...form, mailbox_address: event.target.value })
								}
							/>
							<Input
								label="Mailgun domain"
								required
								value={form.mailgun_domain}
								onChange={(event) =>
									setForm({ ...form, mailgun_domain: event.target.value })
								}
							/>
							<Input
								label="Mailgun API key"
								type="password"
								required={!editingId}
								value={form.mailgun_api_key}
								onChange={(event) =>
									setForm({ ...form, mailgun_api_key: event.target.value })
								}
							/>
							<SelectField
								label="Region"
								value={form.mailgun_endpoint}
								onValueChange={(value) =>
									setForm({ ...form, mailgun_endpoint: value })
								}>
								{ENDPOINTS.map((endpoint) => (
									<SelectItem
										key={endpoint.value}
										value={endpoint.value}>
										{endpoint.label}
									</SelectItem>
								))}
							</SelectField>
							<label className="text-sm font-medium">Signature</label>
							<RichTextEditor
								value={form.signature}
								onChange={(signature) => setForm({ ...form, signature })}
								placeholder="Write your signature"
							/>
							<div className="flex justify-end gap-2">
								{editingId && (
									<Button
										type="button"
										variant="outline"
										onClick={reset}>
										Cancel
									</Button>
								)}
								<Button disabled={processing}>
									{editingId ? "Update account" : "Add account"}
								</Button>
							</div>
						</form>
					)}
				</div>
			</div>
		</>
	)
}

MailAccounts.layout = {
	breadcrumbs: [
		{
			title: "Mail accounts",
			href: edit(),
		},
	],
}
