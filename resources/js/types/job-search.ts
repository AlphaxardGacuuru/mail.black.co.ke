export type JobSearchMode = "review" | "autopilot"

export type CoverLetterMode = "tailor" | "as_is"

export type JobSeekerProfile = {
	autoApply: boolean
	mode: JobSearchMode
	consentedAt: string | null
	mailgunAccountId: string | null
	cvName: string | null
	cvStatus: "missing" | "reading" | "ready"
	profile: {
		full_name?: string
		headline?: string
		skills?: string[]
		target_titles?: string[]
		years_of_experience?: number
	} | null
	coverLetter: string | null
	coverLetterMode: CoverLetterMode
	preferences: {
		keywords: string[]
		locations: string[]
		remoteOk: boolean
		excludedCompanies: string[]
	}
	dailyLimit: number
	minMatchScore: number
	pausedReason: string | null
	lastDiscoveryAt: string | null
	lastAlertAt: string | null
	lastAlertSource: string | null
}

export type CompanyCareerPage = {
	id: string
	url: string
	source: "user" | "search"
	companyName: string | null
	lastFetchedAt: string | null
	lastError: string | null
}

export type JobSearchState = {
	profile: JobSeekerProfile | null
	careerPages: CompanyCareerPage[]
	alertAddress: string | null
	limits: {
		maxDailyLimit: number
		minMatchScoreFloor: number
		maxCareerPages: number
		maxLocations: number
		maxDiscoveredPages: number
		maxCvKilobytes: number
	}
}

export type JobApplicationStatus =
	| "not_matched"
	| "apply_manually"
	| "pending_approval"
	| "queued"
	| "applied"
	| "replied"
	| "skipped"
	| "failed"

export type JobApplicationTab =
	| "review"
	| "queued"
	| "sent"
	| "manual"
	| "closed"

export type JobApplication = {
	id: string
	status: JobApplicationStatus
	matchScore: number | null
	matchReason: string | null
	subject: string | null
	coverLetter: string | null
	mailThreadId: string | null
	appliedAt: string | null
	repliedAt: string | null
	createdAt: string
	posting: {
		title: string
		company: string | null
		location: string | null
		url: string
		applyEmail: string | null
		source: "board" | "company_page" | "alert"
		sourceName: string
		postedAt: string | null
	}
}
