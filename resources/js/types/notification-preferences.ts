export type NotificationChannel = "inApp" | "push" | "email"

export type NotificationCategory = {
	key: string
	group: string
	label: string
	description: string
	channels: Record<NotificationChannel, boolean>
	// Channels that can't be switched on, with the reason
	blocked: Partial<Record<NotificationChannel, string>>
}

export type NotificationPreferencesState = {
	categories: NotificationCategory[]
	email: string
}
