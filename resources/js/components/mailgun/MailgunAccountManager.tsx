import { Plus } from "lucide-react"
import { useState } from "react"
import MailgunAccountController from "@/actions/App/Http/Controllers/Settings/MailgunAccountController"
import MailgunAccountAvatarUpload from "@/components/mailgun/MailgunAccountAvatarUpload"
import RichTextEditor from "@/components/rich-text-editor"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { SelectField, SelectItem } from "@/components/ui/select"
import type { MailgunAccount } from "@/types"
import Axios from "@/lib/axios"
import toast from "@/lib/toast"
import { invalidateAuth } from "@/middleware/auth"

const ENDPOINTS = [
	{ value: "api.mailgun.net", label: "United States" },
	{ value: "api.eu.mailgun.net", label: "Europe" },
]

type Props = { initialAccounts: MailgunAccount[] }

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

export default function MailgunAccountManager({ initialAccounts }: Props) {
	const [accounts, setAccounts] = useState(initialAccounts)
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
		<div className="space-y-4">
			<div className="space-y-2">
				{accounts.map((account) => (
					<div
						key={account.id}
						className="flex items-center gap-3 rounded-md border p-3">
						<MailgunAccountAvatarUpload
							accountId={account.id}
							avatar={account.avatar}
							label={account.mailboxAddress}
							onUploaded={(avatar) => handleAvatarUploaded(account.id, avatar)}
						/>
						<div className="min-w-0 flex-1">
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
					{/* Mail From Name Start */}
					<Input
						label="From name"
						value={form.mail_from_name}
						onChange={(event) =>
							setForm({ ...form, mail_from_name: event.target.value })
						}
					/>
					{/* Mail From Name End */}
					{/* Mail Address Start */}
					<Input
						label="Mail address"
						type="email"
						required
						value={form.mailbox_address}
						onChange={(event) =>
							setForm({ ...form, mailbox_address: event.target.value })
						}
					/>
					{/* Mail Address End */}
					{/* Mailgun Domain Start */}
					<Input
						label="Mailgun domain"
						required
						value={form.mailgun_domain}
						onChange={(event) =>
							setForm({ ...form, mailgun_domain: event.target.value })
						}
					/>
					{/* Mailgun Domain End */}
					{/* Mailgun API Key Start */}
					<Input
						label="Mailgun API key"
						type="password"
						required={!editingId}
						value={form.mailgun_api_key}
						onChange={(event) =>
							setForm({ ...form, mailgun_api_key: event.target.value })
						}
					/>
					{/* Mailgun API Key End */}
					{/* Mailgun Region Start */}
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
					{/* Mailgun Region End */}
					{/* Default Signature Start */}
					<label className="text-sm font-medium">Signature</label>
					<RichTextEditor
						value={form.signature}
						onChange={(signature) => setForm({ ...form, signature })}
						placeholder="Write your signature"
					/>
					{/* Default Signature End */}
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
	)
}
