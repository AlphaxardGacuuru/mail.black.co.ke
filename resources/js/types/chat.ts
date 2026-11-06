export type ChatUser = {
	id: string
	name: string
	email: string
	avatar: string | null
	verified: boolean
	lastSeenAt: string | null
}

export type ChatAttachment = {
	id: string
	filename: string | null
	mimeType: string | null
	size: number | null
	downloadUrl: string
}

export type ChatMessageReplyPreview = {
	id: string
	senderId: string
	body: string | null
	hasAttachments: boolean
}

export type ChatMessage = {
	id: string
	conversationId: string
	senderId: string
	body: string | null
	attachments: ChatAttachment[]
	isRead: boolean
	isStarred: boolean
	replyTo: ChatMessageReplyPreview | null
	createdAt: string
}

export type ChatConversationLastMessage = {
	body: string | null
	senderId: string
	createdAt: string
}

export type ChatLocationShare = {
	senderId: string
	latitude: number | null
	longitude: number | null
	accuracyMeters: number | null
	lastUpdatedAt: string | null
}

export type ChatConversation = {
	id: string
	otherUser: ChatUser | null
	lastMessage: ChatConversationLastMessage | null
	unreadCount: number
	lastMessageAt: string | null
	isArchived: boolean
	otherUserLocationShare: ChatLocationShare | null
	myLocationShare: ChatLocationShare | null
}
