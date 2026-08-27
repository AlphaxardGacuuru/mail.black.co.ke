import { Bold, Italic, Link, List, ListOrdered, Underline } from "lucide-react"
import { useEffect, useRef } from "react"
import { Button } from "@/components/ui/button"
import { cn } from "@/lib/utils"

type Props = {
	value: string
	onChange: (value: string) => void
	placeholder?: string
}

type ToolbarAction = {
	command: string
	icon: typeof Bold
	label: string
}

const toolbarActions: ToolbarAction[] = [
	{ command: "bold", icon: Bold, label: "Bold" },
	{ command: "italic", icon: Italic, label: "Italic" },
	{ command: "underline", icon: Underline, label: "Underline" },
	{ command: "insertUnorderedList", icon: List, label: "Bulleted list" },
	{ command: "insertOrderedList", icon: ListOrdered, label: "Numbered list" },
]

export default function RichTextEditor({
	value,
	onChange,
	placeholder,
}: Props) {
	const editorRef = useRef<HTMLDivElement>(null)

	useEffect(() => {
		if (editorRef.current && editorRef.current.innerHTML !== value) {
			editorRef.current.innerHTML = value
		}
	}, [value])

	function runCommand(command: string, commandValue?: string): void {
		editorRef.current?.focus()
		document.execCommand(command, false, commandValue)
		onChange(editorRef.current?.innerHTML ?? "")
	}

	function insertLink(): void {
		const url = window.prompt("Enter a URL")

		if (url) {
			runCommand("createLink", url)
		}
	}

	return (
		<div className="overflow-hidden rounded-lg border border-neutral-300 dark:border-white/20">
			<div className="flex flex-wrap items-center gap-1 border-b bg-muted/30 p-1">
				{toolbarActions.map(({ command, icon: Icon, label }) => (
					<Button
						key={command}
						type="button"
						variant="ghost"
						size="icon"
						aria-label={label}
						title={label}
						className="size-8"
						onMouseDown={(event) => event.preventDefault()}
						onClick={() => runCommand(command)}>
						<Icon className="size-4" />
					</Button>
				))}
				<Button
					type="button"
					variant="ghost"
					size="icon"
					aria-label="Insert link"
					title="Insert link"
					className="size-8"
					onMouseDown={(event) => event.preventDefault()}
					onClick={insertLink}>
					<Link className="size-4" />
				</Button>
			</div>
			<div
				ref={editorRef}
				contentEditable
				role="textbox"
				aria-multiline="true"
				data-placeholder={placeholder}
				className={cn(
					"min-h-32 p-4 text-base font-light font-nunito text-neutral-900 outline-none dark:text-white",
					"empty:before:pointer-events-none empty:before:text-neutral-500 empty:before:content-[attr(data-placeholder)] dark:empty:before:text-white/50"
				)}
				onInput={(event) => onChange(event.currentTarget.innerHTML)}
			/>
		</div>
	)
}
