import {
	JOB_APPLICATION_TABS,
	jobApplicationTabUrl,
} from "@/lib/job-application-tabs"
import { useJobApplicationCounts } from "@/queries/job-search"
import { useUnreadInboxCount } from "@/queries/mail"

/**
 * The count to show beside each nav link, by href: unread mail on the
 * inbox, and how many applications each tab holds.
 */
export function useNavBadges(): Record<string, number> {
	const { data: unread } = useUnreadInboxCount()
	const { data: counts } = useJobApplicationCounts()

	return {
		"/mail": unread ?? 0,
		...Object.fromEntries(
			JOB_APPLICATION_TABS.map((tab) => [
				jobApplicationTabUrl(tab.key),
				counts?.[tab.key] ?? 0,
			])
		),
	}
}

export function badgeLabel(count: number): string {
	return count > 99 ? "99+" : String(count)
}
