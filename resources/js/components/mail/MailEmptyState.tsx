import { Inbox, MailSearch, MousePointerClick } from "lucide-react"

type Props = {
	variant: "no-threads" | "no-selection" | "search-no-results"
}

const CONTENT: Record<Props["variant"], { icon: typeof Inbox; title: string; description: string }> = {
	"no-threads": {
		icon: Inbox,
		title: "Nothing here",
		description: "This folder is empty.",
	},
	"no-selection": {
		icon: MousePointerClick,
		title: "Select a message",
		description: "Choose a conversation from the list to read it.",
	},
	"search-no-results": {
		icon: MailSearch,
		title: "No results",
		description: "Try a different search term.",
	},
}

export default function MailEmptyState({ variant }: Props) {
	const { icon: Icon, title, description } = CONTENT[variant]

	return (
		<div className="flex flex-1 flex-col items-center justify-center gap-2 p-8 text-center text-muted-foreground">
			<Icon className="size-10" />
			<p className="font-medium text-foreground">{title}</p>
			<p className="text-sm">{description}</p>
		</div>
	)
}
