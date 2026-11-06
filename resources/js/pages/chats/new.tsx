import { useState } from "react"
import { useNavigate } from "@tanstack/react-router"
import { ArrowLeft } from "lucide-react"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Spinner } from "@/components/ui/spinner"
import VerifiedBadge from "@/components/verified-badge"
import { Head } from "@/lib/spa"
import toast from "@/lib/toast"
import { useChatUsers, useStartConversation } from "@/queries/chat"

export default function ChatNew() {
	const navigate = useNavigate()
	const [query, setQuery] = useState("")
	const { data: users, isLoading } = useChatUsers(query)
	const startConversation = useStartConversation()

	function handlePick(userId: string) {
		startConversation.mutate(userId, {
			onSuccess: (conversation) => {
				navigate({ to: "/chats/$id/show", params: { id: conversation.id } })
			},
			onError: () => toast.error("Couldn't start the conversation"),
		})
	}

	return (
		<>
			<Head title="New chat" />

			<div className="flex h-[calc(100vh-4rem)] flex-col overflow-hidden rounded-lg border bg-card shadow-sm">
				<div className="flex items-center gap-3 border-b p-3">
					<Button
						variant="ghost"
						onClick={() => navigate({ to: "/chats" })}>
						<ArrowLeft className="size-5" />
						Back to chats
					</Button>
				</div>

				<div className="p-3">
					<Input
						autoFocus
						label="Search people by name"
						value={query}
						onChange={(event) => setQuery(event.target.value)}
					/>
				</div>

				<div className="flex-1 space-y-1 overflow-y-auto p-2">
					{isLoading && (
						<div className="flex justify-center py-6">
							<Spinner className="size-5 text-muted-foreground" />
						</div>
					)}

					{!isLoading && (users?.length ?? 0) === 0 && (
						<p className="py-6 text-center text-sm text-muted-foreground">
							No one found
						</p>
					)}

					{!isLoading &&
						users?.map((user) => {
							const isPickingThisUser =
								startConversation.isPending &&
								startConversation.variables === user.id

							return (
								<button
									key={user.id}
									type="button"
									disabled={startConversation.isPending}
									onClick={() => handlePick(user.id)}
									className="flex w-full items-center gap-3 rounded-md px-2 py-2 text-left hover:bg-muted disabled:opacity-50">
									<Avatar className="size-9 shrink-0">
										<AvatarImage
											src={user.avatar ?? undefined}
											alt={user.name}
										/>
										<AvatarFallback>
											{user.name.slice(0, 2).toUpperCase()}
										</AvatarFallback>
									</Avatar>
									<div className="grid min-w-0 flex-1 text-left leading-tight">
										<span className="flex min-w-0 items-center gap-1 font-medium">
											<span className="min-w-0 truncate">{user.name}</span>
											{user.verified && (
												<VerifiedBadge className="size-3.5 shrink-0" />
											)}
										</span>
										<span className="truncate text-xs text-muted-foreground">
											{user.email}
										</span>
									</div>
									{isPickingThisUser && (
										<Spinner className="size-4 shrink-0 text-muted-foreground" />
									)}
								</button>
							)
						})}
				</div>
			</div>
		</>
	)
}
