import { Button } from "@/components/ui/button"
import type { ReadStatus } from "@/queries/mail"

const OPTIONS: { label: string; value: ReadStatus | undefined }[] = [
	{ label: "All", value: undefined },
	{ label: "Unread", value: "unread" },
	{ label: "Read", value: "read" },
]

type Props = {
	value?: ReadStatus
	onChange: (value: ReadStatus | undefined) => void
}

export default function ReadStatusFilter({ value, onChange }: Props) {
	return (
		<div className="flex items-center gap-1 px-3 pb-2">
			{OPTIONS.map((option) => (
				<Button
					key={option.label}
					type="button"
					size="sm"
					variant={value === option.value ? "secondary" : "ghost"}
					className="h-7 rounded-full px-3 text-xs"
					aria-pressed={value === option.value}
					onClick={() => onChange(option.value)}>
					{option.label}
				</Button>
			))}
		</div>
	)
}
