import { LoaderCircle, Plus } from "lucide-react"
import { useState } from "react"
import type { FormEvent } from "react"
import { FilePond, registerPlugin } from "react-filepond"
import { Head } from "@/lib/spa"
import { useApp } from "@/contexts/AppContext"
import FilePondController from "@/actions/App/Http/Controllers/FilePondController"
import MailgunAccountController from "@/actions/App/Http/Controllers/Settings/MailgunAccountController"
import MailgunDomainController from "@/actions/App/Http/Controllers/Settings/MailgunDomainController"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import Heading from "@/components/heading"
import RichTextEditor from "@/components/rich-text-editor"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import PasswordInput from "@/components/password-input"
import { SelectField, SelectItem } from "@/components/ui/select"
import type { MailgunAccount, MailgunDomain } from "@/types"
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

function endpointLabel(value: string): string {
	return ENDPOINTS.find((endpoint) => endpoint.value === value)?.label ?? value
}

// ─── Domains ─────────────────────────────────────────────────────────────────

type DomainForm = {
	domain: string
	api_key: string
	endpoint: string
}

const emptyDomainForm: DomainForm = {
	domain: "",
	api_key: "",
	endpoint: "api.mailgun.net",
}

function DomainsSection({
	domains,
	onChange,
}: {
	domains: MailgunDomain[]
	onChange: (domains: MailgunDomain[]) => void
}) {
	const [editingId, setEditingId] = useState<string | null>(null)
	const [form, setForm] = useState<DomainForm>(emptyDomainForm)
	const [processing, setProcessing] = useState(false)
	const [showForm, setShowForm] = useState(false)

	function editDomain(domain: MailgunDomain): void {
		setEditingId(domain.id)
		setShowForm(true)
		setForm({ domain: domain.domain, api_key: "", endpoint: domain.endpoint })
	}

	function reset(): void {
		setEditingId(null)
		setForm(emptyDomainForm)
		setShowForm(false)
	}

	function submit(event: FormEvent<HTMLFormElement>): void {
		event.preventDefault()
		setProcessing(true)
		const route = editingId
			? MailgunDomainController.update.patch(editingId)
			: MailgunDomainController.store.post()

		Axios.request({
			url: route.url,
			method: route.method,
			data: form,
		})
			.then((response) => {
				onChange(response.data.domains)
				invalidateAuth()
				toast.success(response.data.message)
				reset()
			})
			.catch(() => toast.error("Unable to save this Mailgun domain."))
			.finally(() => setProcessing(false))
	}

	function destroy(domain: MailgunDomain): void {
		Axios.delete(MailgunDomainController.destroy.url(domain.id))
			.then((response) => {
				onChange(response.data.domains)
				invalidateAuth()
				toast.success(response.data.message)
			})
			.catch(() => toast.error("Unable to remove this domain."))
	}

	return (
		<div className="space-y-4">
			<div className="space-y-2">
				{domains.map((domain) => (
					<div
						key={domain.id}
						className="flex items-center gap-3 rounded-md border p-3">
						<div className="min-w-0 flex-1">
							<p className="truncate font-medium">{domain.domain}</p>
							<p className="text-sm text-muted-foreground">
								{endpointLabel(domain.endpoint)}
							</p>
						</div>
						<Button
							type="button"
							variant="outline"
							size="sm"
							onClick={() => editDomain(domain)}>
							Edit
						</Button>
						<Button
							type="button"
							variant="outline"
							size="sm"
							onClick={() => destroy(domain)}>
							Remove
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
					Add domain
				</Button>
			)}

			{showForm && (
				<form
					onSubmit={submit}
					className="space-y-4 rounded-md border p-4">
					<h3 className="font-medium">
						{editingId ? "Edit domain" : "Add Mailgun domain"}
					</h3>
					<Input
						label="Mailgun domain"
						required
						value={form.domain}
						onChange={(event) =>
							setForm({ ...form, domain: event.target.value })
						}
					/>
					<PasswordInput
						label="Mailgun API key"
						required={!editingId}
						value={form.api_key}
						onChange={(event) =>
							setForm({ ...form, api_key: event.target.value })
						}
					/>
					<SelectField
						label="Region"
						value={form.endpoint}
						onValueChange={(value) => setForm({ ...form, endpoint: value })}>
						{ENDPOINTS.map((endpoint) => (
							<SelectItem
								key={endpoint.value}
								value={endpoint.value}>
								{endpoint.label}
							</SelectItem>
						))}
					</SelectField>
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
							{processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
							{editingId ? "Update domain" : "Add domain"}
						</Button>
					</div>
				</form>
			)}
		</div>
	)
}

// ─── Email accounts ────────────────────────────────────────────────────────────

type AccountForm = {
	mailbox_address: string
	mail_from_name: string
	mailgun_domain_id: string
	signature: string
}

function emptyAccountForm(domains: MailgunDomain[]): AccountForm {
	return {
		mailbox_address: "",
		mail_from_name: "",
		mailgun_domain_id: domains[0]?.id ?? "",
		signature: "",
	}
}

function AccountsSection({
	domains,
	accounts,
	onChange,
}: {
	domains: MailgunDomain[]
	accounts: MailgunAccount[]
	onChange: (accounts: MailgunAccount[]) => void
}) {
	const [editingId, setEditingId] = useState<string | null>(null)
	const [form, setForm] = useState<AccountForm>(emptyAccountForm(domains))
	const [processing, setProcessing] = useState(false)
	const [showForm, setShowForm] = useState(false)

	function editAccount(account: MailgunAccount): void {
		setEditingId(account.id)
		setShowForm(true)
		setForm({
			mailbox_address: account.mailboxAddress,
			mail_from_name: account.mailFromName ?? "",
			mailgun_domain_id: account.mailgunDomainId,
			signature: account.signature ?? "",
		})
	}

	function reset(): void {
		setEditingId(null)
		setForm(emptyAccountForm(domains))
		setShowForm(false)
	}

	function handleAvatarUploaded(accountId: string, avatar: string): void {
		onChange(
			accounts.map((account) =>
				account.id === accountId ? { ...account, avatar } : account
			)
		)
		invalidateAuth()
	}

	function submit(event: FormEvent<HTMLFormElement>): void {
		event.preventDefault()
		setProcessing(true)

		// The mailbox address and domain are only meaningful when creating
		// the account — Mailgun already provisioned the mailbox under them,
		// so editing can't rename either without reprovisioning.
		const data = editingId
			? { mail_from_name: form.mail_from_name, signature: form.signature }
			: form

		const route = editingId
			? MailgunAccountController.update.patch(editingId)
			: MailgunAccountController.store.post()

		Axios.request({
			url: route.url,
			method: route.method,
			data,
		})
			.then((response) => {
				onChange(response.data.accounts)
				invalidateAuth()
				toast.success(response.data.message)
				reset()
			})
			.catch(() => toast.error("Unable to save mail account."))
			.finally(() => setProcessing(false))
	}

	return (
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
							<AvatarFallback className="">
								{account.mailboxAddress.slice(0, 2).toUpperCase()}
							</AvatarFallback>
						</Avatar>
						<div className="min-w-0 flex-1">
							<p className="truncate font-medium">
								{account.mailFromName ?? ""}
							</p>
							<p className="truncate font-medium">{account.mailboxAddress}</p>
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

			{!showForm && domains.length > 0 && (
				<Button
					type="button"
					variant="outline"
					onClick={() => {
						setForm(emptyAccountForm(domains))
						setShowForm(true)
					}}>
					<Plus className="size-4" />
					Add account
				</Button>
			)}

			{!showForm && domains.length === 0 && (
				<p className="text-sm text-muted-foreground">
					Register a Mailgun domain above before creating an email account.
				</p>
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
												const uploadedAvatar = response.data.avatar as string
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
					{!editingId && (
						<>
							<SelectField
								label="Domain"
								value={form.mailgun_domain_id}
								onValueChange={(value) =>
									setForm({ ...form, mailgun_domain_id: value })
								}>
								{domains.map((domain) => (
									<SelectItem
										key={domain.id}
										value={domain.id}>
										{domain.domain}
									</SelectItem>
								))}
							</SelectField>
							<Input
								label="Mail address"
								type="email"
								required
								value={form.mailbox_address}
								onChange={(event) =>
									setForm({ ...form, mailbox_address: event.target.value })
								}
							/>
						</>
					)}
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
							{processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
							{editingId ? "Update account" : "Add account"}
						</Button>
					</div>
				</form>
			)}
		</div>
	)
}

// ─── Page ────────────────────────────────────────────────────────────────────

export default function MailAccounts() {
	const { auth } = useApp()
	const [domains, setDomains] = useState<MailgunDomain[]>(
		auth?.mailgunDomains ?? []
	)
	const [accounts, setAccounts] = useState<MailgunAccount[]>(
		auth?.mailgunAccounts ?? []
	)

	return (
		<>
			<Head title="Mail accounts" />

			<h1 className="sr-only">Mail accounts</h1>

			<div className="space-y-10">
				<div className="space-y-6">
					<Heading
						variant="small"
						title="Mailgun domains"
						description="Register a Mailgun domain and API key once, then create email accounts under it."
					/>
					<DomainsSection
						domains={domains}
						onChange={setDomains}
					/>
				</div>

				<div className="space-y-6">
					<Heading
						variant="small"
						title="Mail accounts"
						description="Create email accounts under one of your registered domains and choose a default signature for each."
					/>
					<AccountsSection
						domains={domains}
						accounts={accounts}
						onChange={setAccounts}
					/>
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
