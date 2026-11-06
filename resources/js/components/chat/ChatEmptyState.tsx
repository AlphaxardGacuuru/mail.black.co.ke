import { Archive, MessageCircle, MessagesSquare, SearchX } from "lucide-react"

type Props = {
	variant:
		| "no-conversations"
		| "no-archived-conversations"
		| "no-selection"
		| "search-no-results"
}

const CONTENT: Record<
	Props["variant"],
	{ icon: typeof MessageCircle; title: string; description: string }
> = {
	"no-conversations": {
		icon: MessagesSquare,
		title: "No conversations yet",
		description: "Start a new chat to say hello.",
	},
	"no-archived-conversations": {
		icon: Archive,
		title: "No archived chats",
		description: "Conversations you archive will show up here.",
	},
	"no-selection": {
		icon: MessageCircle,
		title: "Select a conversation",
		description: "Choose a chat from the list to start messaging.",
	},
	"search-no-results": {
		icon: SearchX,
		title: "No results",
		description: "Try a different search term.",
	},
}

export default function ChatEmptyState({ variant }: Props) {
	const { icon: Icon, title, description } = CONTENT[variant]

	return (
		<div className="flex flex-1 flex-col items-center justify-center gap-2 p-8 text-center text-muted-foreground">
			<Icon className="size-10" />
			<p className="font-medium text-foreground">{title}</p>
			<p className="text-sm">{description}</p>
		</div>
	)
}
