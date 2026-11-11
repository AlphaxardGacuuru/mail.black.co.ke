export type MailgunDomain = {
	id: string
	domain: string
	endpoint: string
}

export type MailgunAccount = {
	id: string
	mailFromName: string | null
	mailboxAddress: string
	mailgunDomainId: string
	mailgunDomain: string
	mailgunEndpoint: string
	signature: string | null
	avatar: string | null
	// A job search address on the app's own domain.
	isPlatform: boolean
	isActive: boolean
}

export type User = {
	id: number
	name: string
	email: string
	avatar?: string
	email_verified_at: string | null
	verified?: boolean
	twoFactorEnabled?: boolean
	// Confirms deleting their account with their email, not a password.
	signsInWithGoogle?: boolean
	can_view_ai_usage?: boolean
	created_at: string
	updated_at: string
	mailgunDomains?: MailgunDomain[]
	mailgunAccounts?: MailgunAccount[]
	[key: string]: unknown
}

export type TwoFactorSetupData = {
	svg: string
	url: string
}

export type TwoFactorSecretKey = {
	secretKey: string
}
