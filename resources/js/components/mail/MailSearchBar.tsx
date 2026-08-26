import { Search } from "lucide-react"
import { useEffect, useState } from "react"
import { Input } from "@/components/ui/input"

type Props = {
	value: string
	onChange: (value: string) => void
}

export default function MailSearchBar({ value, onChange }: Props) {
	const [local, setLocal] = useState(value)

	useEffect(() => {
		setLocal(value)
	}, [value])

	useEffect(() => {
		const timer = setTimeout(() => {
			if (local !== value) {
				onChange(local)
			}
		}, 300)

		return () => clearTimeout(timer)
	}, [local])

	return (
		<div className="flex items-center gap-2 px-3 py-2">
			<Search className="size-4 text-muted-foreground shrink-0" />
			<Input
				type="text"
				label="Search mail"
				value={local}
				onChange={(event) => setLocal(event.target.value)}
				className="flex-1 rounded-none border-0 text-sm shadow-none focus:border-0 focus:ring-0"
			/>
		</div>
	)
}
