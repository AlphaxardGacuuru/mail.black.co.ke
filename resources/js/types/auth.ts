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
	isActive: boolean
}

export type User = {
	id: number
	name: string
	email: string
	avatar?: string
	email_verified_at: string | null
	twoFactorEnabled?: boolean
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
