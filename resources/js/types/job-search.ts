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
	// Applications send the CV revamped by the AI, and (with tailorCv) a
	// copy tailored to each job.
	revampedCvInUse: boolean
	tailorCv: boolean
	preferences: {
		keywords: string[]
		locations: string[]
		remoteOk: boolean
		excludedCompanies: string[]
	}
	dailyLimit: number
	minMatchScore: number
	pausedReason: string | null
	setupStep: JobSearchSetupStep | null
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
	// Without a mailbox, the user picks an address on mailboxDomain.
	needsMailbox: boolean
	mailboxDomain: string
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

// How much the user has to do for an answer on an employer's form.
export type ApplicationFormEffort =
	| "filled"
	| "check"
	| "needs_you"
	| "optional"

export type ApplicationFormField = {
	key: string
	type:
		| "text"
		| "email"
		| "tel"
		| "url"
		| "number"
		| "date"
		| "textarea"
		| "select"
		| "combobox"
		| "dropdown"
		| "radio"
		| "checkbox"
		| "file"
		| string
	label: string
	required: boolean
	options: string[]
	// The question a checkbox in a list belongs to.
	group: string | null
	answer: string
	effort: ApplicationFormEffort
	note: string | null
	edited: boolean
}

export type ApplicationForm = {
	status:
		| "preparing"
		| "ready"
		| "blocked"
		| "failed"
		| "submitting"
		| "submitted"
		| "submit_failed"
	error: string | null
	url: string | null
	captcha: string | null
	// Whether a CAPTCHA solving service is set up to submit past one.
	captchaSolved: boolean
	minutesLeft: number
	preparedAt: string | null
	submittedAt: string | null
	fields: ApplicationFormField[]
	screenshots: {
		form: string | null
		filled: string | null
		confirmation: string | null
	}
}

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
	// Applying on the employer's own form; null until it's first prepared.
	form: ApplicationForm | null
	// The CV tailored to this job; null until one is asked for.
	cv: { status: TailoredCvStatus; url: string | null } | null
	posting: {
		title: string
		company: string | null
		location: string | null
		// A shared post (often a poster) may have no link.
		url: string | null
		applyEmail: string | null
		applyInstructions: string | null
		source: "board" | "company_page" | "alert" | "shared"
		sourceName: string
		postedAt: string | null
		// Null until the job is first opened and priced.
		salary: JobSalary | null
	}
}

// What a job pays: the posting's own figure (stated) or the AI's estimate
// of the usual range for the role. min is null for "up to" figures.
export type JobSalary = {
	min: number | null
	max: number
	currency: string
	period: "month" | "year" | "week" | "day" | "hour"
	stated: boolean
	basis: string | null
}

// The whole job, loaded when it's opened.
export type JobDetails = {
	description: string | null
	industry: string | null
	closesAt: string | null
	salary: JobSalary | null
	salaryStatus: "estimating" | "ready" | "unknown"
}

export type TailoredCvStatus = "tailoring" | "ready" | "failed"

// One part of a CV: a job, a qualification, the profile, the skills.
export type CvEntry = {
	title: string
	subtitle: string
	period: string
	text: string
	bullets: string[]
}

export type CvRevampSection = {
	key: string
	heading: string
	// What the CV said; null for a section the AI added.
	original: CvEntry | null
	revamped: CvEntry
	why: string | null
	keep: "revamped" | "original"
	edited: boolean
}

// A fact the AI asked for to make the CV stronger.
export type CvRevampQuestion = {
	key: string
	// The section it's about, if any.
	section: string | null
	question: string
	answer: string
	// Already used in the current rewrite.
	used: boolean
}

export type CvRevamp = {
	status: "drafting" | "ready" | "failed"
	error: string | null
	inUse: boolean
	usedAt: string | null
	updatedAt: string
	name: string | null
	headline: string | null
	contact: string[]
	sections: CvRevampSection[]
	questions: CvRevampQuestion[]
	downloads: { pdf: string; docx: string } | null
}

export type CvRevampState = {
	revamp: CvRevamp | null
	cvName: string | null
	canRevamp: boolean
	tailorCv: boolean
}

// The steps of the job search setup wizard, in order.
export const JOB_SEARCH_SETUP_STEPS = [
	"cv",
	"address",
	"cover_letter",
	"preferences",
	"sending",
] as const

export type JobSearchSetupStep = (typeof JOB_SEARCH_SETUP_STEPS)[number]
