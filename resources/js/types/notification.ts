export type Notification = {
	id: string
	type: string
	notifiableType: string
	notifiableId: string | number
	data: Record<string, unknown>
	url: string | null
	from: string | null
	message: string | null
	readAt: string | null
	updatedAt: string
	createdAt: string
}
