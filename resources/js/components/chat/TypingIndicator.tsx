type Props = {
	name: string
}

export default function TypingIndicator({ name }: Props) {
	return (
		<div className="flex items-center gap-2 px-3 py-1 text-xs text-muted-foreground">
			<span className="flex gap-0.5">
				<span className="size-1.5 animate-bounce rounded-full bg-muted-foreground [animation-delay:-0.3s]" />
				<span className="size-1.5 animate-bounce rounded-full bg-muted-foreground [animation-delay:-0.15s]" />
				<span className="size-1.5 animate-bounce rounded-full bg-muted-foreground" />
			</span>
			<span>{name} is typing…</span>
		</div>
	)
}
