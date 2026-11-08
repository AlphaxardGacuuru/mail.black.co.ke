import { Ban, ClipboardList, Clock, Hand, MailCheck } from "lucide-react"
import type { LucideIcon } from "lucide-react"
import type { JobApplicationTab, NavItem } from "@/types"

export const JOB_APPLICATIONS_URL = "/job-applications"

export const CV_REVAMP_URL = `${JOB_APPLICATIONS_URL}/cv`

export const JOB_APPLICATION_TABS: {
	key: JobApplicationTab
	label: string
	icon: LucideIcon
	empty: string
}[] = [
	{
		key: "review",
		label: "Review",
		icon: ClipboardList,
		empty: "Nothing to review. New matches appear here.",
	},
	{
		key: "queued",
		label: "Queued",
		icon: Clock,
		empty: "Nothing waiting to be sent.",
	},
	{
		key: "sent",
		label: "Sent",
		icon: MailCheck,
		empty: "No applications sent yet.",
	},
	{
		key: "manual",
		label: "Self Apply",
		icon: Hand,
		empty: "Matches without an apply email appear here.",
	},
	{
		key: "closed",
		label: "Skipped",
		icon: Ban,
		empty: "Nothing skipped or bounced.",
	},
]

// "To review" is where the section opens, so it keeps the section's own URL
// (the one notifications link to).
export function jobApplicationTabUrl(tab: JobApplicationTab): string {
	return tab === "review"
		? JOB_APPLICATIONS_URL
		: `${JOB_APPLICATIONS_URL}/${tab}`
}

export function isJobApplicationTab(value: string): value is JobApplicationTab {
	return JOB_APPLICATION_TABS.some((tab) => tab.key === value)
}

export const jobApplicationNavItems: NavItem[] = JOB_APPLICATION_TABS.map(
	(tab) => ({
		title: tab.label,
		href: jobApplicationTabUrl(tab.key),
		icon: tab.icon,
	})
)
