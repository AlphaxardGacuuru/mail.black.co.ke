import { X } from "lucide-react"
import { useState } from "react"
import type { KeyboardEvent } from "react"
import { Badge } from "@/components/ui/badge"

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

type Props = {
	label: string
	value: string[]
	onChange: (emails: string[]) => void
	placeholder?: string
	onRemove?: () => void
}

export default function MailRecipientInput({ label, value, onChange, placeholder, onRemove }: Props) {
	const [draft, setDraft] = useState("")

	function commitDraft() {
		const trimmed = draft.trim().replace(/,$/, "")

		if (trimmed && !value.includes(trimmed)) {
			onChange([...value, trimmed])
		}

		setDraft("")
	}

	function handleKeyDown(event: KeyboardEvent<HTMLInputElement>) {
		if (event.key === "Enter" || event.key === "," || event.key === "Tab") {
			if (draft.trim()) {
				event.preventDefault()
				commitDraft()
			}
		} else if (event.key === "Backspace" && draft === "" && value.length > 0) {
			onChange(value.slice(0, -1))
		}
	}

	function removeAt(index: number) {
		onChange(value.filter((_, i) => i !== index))
	}

	return (
		<div className="flex flex-wrap items-center gap-1.5 border-b py-1.5">
			<span className="text-sm text-muted-foreground shrink-0">{label}</span>

			{value.map((email, index) => (
				<Badge
					key={`${email}-${index}`}
					variant={EMAIL_RE.test(email) ? "secondary" : "destructive"}
					className="gap-1 font-normal">
					{email}
					<button
						type="button"
						onClick={() => removeAt(index)}
						className="rounded-full hover:bg-black/10">
						<X className="size-3" />
					</button>
				</Badge>
			))}

			<input
				type="text"
				value={draft}
				onChange={(event) => setDraft(event.target.value)}
				onKeyDown={handleKeyDown}
				onBlur={commitDraft}
				placeholder={value.length === 0 ? placeholder : undefined}
				className="h-7 flex-1 min-w-24 bg-transparent text-sm outline-none placeholder:text-muted-foreground"
			/>

			{onRemove && (
				<button
					type="button"
					onClick={onRemove}
					className="shrink-0 rounded-full p-0.5 text-muted-foreground hover:bg-muted hover:text-foreground">
					<X className="size-3.5" />
				</button>
			)}
		</div>
	)
}
