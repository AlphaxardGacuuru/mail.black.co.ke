import { redirect } from "@tanstack/react-router"
import { getAuth } from "@/middleware/auth"

const ADMIN_PREFIX = "/admin"
const SUBSCRIBE_PATH = "/admin/subscribe"

type AuthShape = {
	name?: unknown
	activeSubscription?: unknown
	subscriptionByPropertyIds?: unknown
	emailVerifiedAt?: unknown
	email_verified_at?: unknown
}

type RouteLocation = {
	pathname: string
}

function hasPropertySubscriptions(auth: AuthShape): boolean {
	return (
		Array.isArray(auth.subscriptionByPropertyIds) &&
		auth.subscriptionByPropertyIds.length > 0
	)
}

function hasEmailVerification(auth: AuthShape): boolean {
	return Boolean(auth.emailVerifiedAt ?? auth.email_verified_at)
}

function isExcludedPath(pathname: string): boolean {
	return (
		pathname.includes("/super/") ||
		pathname.includes("/tenant/") ||
		pathname === SUBSCRIBE_PATH
	)
}

/**
 * Redirect authenticated, verified, unsubscribed users to the subscribe page.
 * Use this in TanStack Router `beforeLoad` for protected admin routes.
 */
export async function requireSubscribed({
	location,
}: {
	location: RouteLocation
}) {
	const pathname = location.pathname

	if (!pathname.startsWith(ADMIN_PREFIX) || isExcludedPath(pathname)) {
		return
	}

	const auth = (await getAuth()) as AuthShape | null

	if (!auth) {
		return
	}

	if (
		auth.name !== "Guest" &&
		auth.activeSubscription == null &&
		hasEmailVerification(auth) &&
		!hasPropertySubscriptions(auth)
	) {
		throw redirect({ to: SUBSCRIBE_PATH })
	}
}
