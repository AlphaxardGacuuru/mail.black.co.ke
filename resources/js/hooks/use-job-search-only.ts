import { useApp } from "@/contexts/AppContext"

/**
 * Whether the active mailbox is a job search address, which only sends job
 * applications and replies to them (no new messages or forwards).
 */
export function useJobSearchOnly(): boolean {
	const { auth } = useApp()

	return (
		auth?.mailgunAccounts?.find((account) => account.isActive)?.isPlatform ??
		false
	)
}
