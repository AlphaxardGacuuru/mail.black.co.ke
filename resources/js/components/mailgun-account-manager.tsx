import { Plus } from "lucide-react"
import { useState } from "react"
import MailgunAccountController from "@/actions/App/Http/Controllers/Settings/MailgunAccountController"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Textarea } from "@/components/ui/textarea"
import { SelectField, SelectItem } from "@/components/ui/select"
import type { MailgunAccount } from "@/types"
import Axios from "@/lib/axios"
import toast from "@/lib/toast"

const ENDPOINTS = [
	{ value: "api.mailgun.net", label: "United States" },
	{ value: "api.eu.mailgun.net", label: "Europe" },
]

type Props = { initialAccounts: MailgunAccount[] }

type AccountForm = {
	mailbox_address: string
	mailgun_domain: string
	mailgun_api_key: string
	mailgun_endpoint: string
	signature: string
}

const emptyForm: AccountForm = {
	mailbox_address: "",
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
						<div className="min-w-0 flex-1">
							<p className="truncate font-medium">{account.mailboxAddress}</p>
							<p className="text-sm text-muted-foreground">
								{account.mailgunDomain}
							</p>
						</div>
						{account.isActive && (
							<span className="text-xs text-muted-foreground">Active</span>
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
					Add another account
				</Button>
			)}

			{showForm && (
			<form
				onSubmit={submit}
				className="space-y-4 rounded-md border p-4">
				<h3 className="font-medium">
					{editingId ? "Edit mail account" : "Add mail account"}
				</h3>
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
				<Textarea
					label="Default signature"
					value={form.signature}
					onChange={(event) =>
						setForm({ ...form, signature: event.target.value })
					}
					rows={4}
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
	)
}
