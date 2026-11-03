import { jobApplicationTabUrl } from "@/lib/job-application-tabs"
import { useJobApplicationCounts } from "@/queries/job-search"
import { useUnreadInboxCount } from "@/queries/mail"
import type { JobApplicationTab } from "@/types"

// Only tabs with something to act on: drafts to approve, applications you
// can still stop, and jobs to apply to yourself.
const BADGED_TABS: JobApplicationTab[] = ["review", "queued", "manual"]

/**
 * The count to show beside each nav link, by href: unread mail on the
 * inbox, and the job application tabs waiting on you.
 */
export function useNavBadges(): Record<string, number> {
	const { data: unread } = useUnreadInboxCount()
	const { data: counts } = useJobApplicationCounts()

	return {
		"/mail": unread ?? 0,
		...Object.fromEntries(
			BADGED_TABS.map((tab) => [jobApplicationTabUrl(tab), counts?.[tab] ?? 0])
		),
	}
}

export function badgeLabel(count: number): string {
	return count > 99 ? "99+" : String(count)
}
