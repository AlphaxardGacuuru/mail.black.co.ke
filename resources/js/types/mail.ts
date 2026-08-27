export type MailAddress = {
	address: string
	name?: string | null
}

export type MailAttachment = {
	id: string
	filename: string | null
	mimeType: string | null
	size: number | null
	isInline: boolean
	downloadUrl: string
}

export type MailLabel = {
	id: string
	name: string
	color: string | null
}

export type MailMessageStatus =
	| "queued"
	| "sent"
	| "delivered"
	| "opened"
	| "clicked"
	| "failed"
	| "bounced"
	| "temporary_failed"
	| "permanent_failed"
	| "complained"
	| "unsubscribed"
	| "received"

export type MailMessage = {
	id: string
	threadId: string
	direction: "inbound" | "outbound"
	folder: string
	from: MailAddress | null
	to: MailAddress[]
	cc: MailAddress[]
	bcc: MailAddress[]
	subject: string | null
	bodyHtml: string | null
	bodyText: string | null
	snippet: string | null
	status: MailMessageStatus | null
	errorMessage: string | null
	isRead: boolean
	isStarred: boolean
	hasAttachments: boolean
	attachments: MailAttachment[]
	labels: MailLabel[]
	sentAt: string | null
	receivedAt: string | null
	createdAt: string
}

export type MailThreadSummary = {
	id: string
	subject: string | null
	snippet: string | null
	from: MailAddress | null
	hasUnread: boolean
	isStarred: boolean
	messageCount: number
	hasAttachments: boolean
	lastMessageAt: string | null
	status: MailMessageStatus | null
	isRead: boolean
}

export type MailThread = MailThreadSummary & {
	messages: MailMessage[]
}

export type MailFolderKey = "inbox" | "starred" | "sent" | "archive" | "trash"

export type MailComposeMode = "new" | "reply" | "reply-all" | "forward"

export type MailComposePayload = {
	to?: string[]
	cc?: string[]
	bcc?: string[]
	subject: string
	bodyHtml: string
	temporaryUploadIds?: number[]
}
