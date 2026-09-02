import { Paperclip, RotateCw } from "lucide-react"
import { Avatar, AvatarFallback } from "@/components/ui/avatar"
import MailAttachmentChip from "@/components/mail/MailAttachmentChip"
import MailStatusIcon from "@/components/mail/MailStatusIcon"
import { Button } from "@/components/ui/button"
import type { MailMessage, MailMessageStatus } from "@/types/mail"

const FAILED_STATUSES: MailMessageStatus[] = [
	"failed",
	"temporary_failed",
	"permanent_failed",
]

type Props = {
	message: MailMessage
	isExpanded: boolean
	onToggleExpand: () => void
	onRetry: () => void
	isRetrying: boolean
}

function initials(name?: string | null, address?: string | null): string {
	const source = name?.trim() || address?.trim() || "?"
	return source.slice(0, 2).toUpperCase()
}

function formatDate(value: string | null): string {
	if (!value) {
		return ""
	}

	return new Date(value).toLocaleString(undefined, {
		month: "short",
		day: "numeric",
		hour: "numeric",
		minute: "2-digit",
	})
}

export default function MailMessageBubble({
	message,
	isExpanded,
	onToggleExpand,
	onRetry,
	isRetrying,
}: Props) {
	const fromName =
		message.from?.name ?? message.from?.address ?? "Unknown sender"

	return (
		<div className="rounded-lg border">
			<button
				type="button"
				onClick={onToggleExpand}
				className="flex w-full items-center gap-3 p-3 text-left hover:bg-muted/40">
				<Avatar className="size-8 shrink-0">
					<AvatarFallback>
						{initials(message.from?.name, message.from?.address)}
					</AvatarFallback>
				</Avatar>

				<div className="flex-1 min-w-0">
					<span className="font-medium truncate block">{fromName}</span>
					<span className="font-medium truncate block">
						{message.from?.address}
					</span>

					{!isExpanded && (
						<p className="text-sm text-muted-foreground truncate">
							{message.snippet}
						</p>
					)}

					{!isExpanded && message.hasAttachments && (
						<Paperclip className="mt-1 size-3.5 text-muted-foreground" />
					)}
				</div>
			</button>

			{isExpanded && (
				<div className="border-t p-4 space-y-3">
					<div className="text-xs text-muted-foreground space-y-0.5">
						<div>
							To:{" "}
							{message.to
								.map((address) => address.name ?? address.address)
								.join(", ")}
						</div>
						{message.cc.length > 0 && (
							<div>
								Cc:{" "}
								{message.cc
									.map((address) => address.name ?? address.address)
									.join(", ")}
							</div>
						)}
					</div>

					{message.bodyHtml ? (
						<div
							className="text-sm leading-relaxed [&_a]:underline [&_a]:text-primary [&_img]:max-w-full [&_img]:rounded [&_blockquote]:border-l-2 [&_blockquote]:pl-3 [&_blockquote]:text-muted-foreground"
							dangerouslySetInnerHTML={{ __html: message.bodyHtml }}
						/>
					) : (
						<p className="text-sm whitespace-pre-wrap">{message.bodyText}</p>
					)}

					{message.attachments.length > 0 && (
						<div className="flex flex-wrap gap-2 pt-2">
							{message.attachments.map((attachment) => (
								<MailAttachmentChip
									key={attachment.id}
									attachment={attachment}
								/>
							))}
						</div>
					)}

					{message.direction === "outbound" &&
						message.status &&
						FAILED_STATUSES.includes(message.status) && (
							<div className="flex items-center gap-2">
								<p className="flex-1 text-sm text-destructive">
									Failed to send
									{message.errorMessage ? `: ${message.errorMessage}` : ""}
								</p>
								<Button
									variant="outline"
									size="sm"
									disabled={isRetrying}
									onClick={(event) => {
										event.stopPropagation()
										onRetry()
									}}>
									<RotateCw
										className={`size-3.5 ${isRetrying ? "animate-spin" : ""}`}
									/>
									Retry
								</Button>
							</div>
						)}
				</div>
			)}

			<div className="flex items-center justify-end gap-1 px-3 pb-2">
				<span className="text-xs text-muted-foreground">
					{formatDate(
						message.sentAt ?? message.receivedAt ?? message.createdAt
					)}
				</span>
				{message.status && (
					<MailStatusIcon
						status={message.status}
						className="size-3.5"
					/>
				)}
			</div>
		</div>
	)
}
