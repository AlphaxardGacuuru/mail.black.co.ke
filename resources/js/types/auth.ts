export type MailgunAccount = {
	id: string
	mailboxAddress: string
	mailFromName: string | null
	mailgunDomain: string
	mailgunEndpoint: string
	signature: string | null
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
