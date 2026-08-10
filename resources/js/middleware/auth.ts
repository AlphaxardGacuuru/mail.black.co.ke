import { redirect } from "@tanstack/react-router"
import { queryClient } from "@/lib/query-client"
import Axios from "@/lib/axios"
import type { User } from "@/types"

const AUTH_QUERY = {
	queryKey: ["auth"] as const,
	queryFn: (): Promise<User> =>
		Axios.get("api/auth").then((res) => res.data.data),
}

export async function getAuth(): Promise<User | null> {
	if (!localStorage.getItem("sanctumToken")) {
		return null
	}

	const cached = queryClient.getQueryData<User>(AUTH_QUERY.queryKey)

	if (cached !== undefined) {
		return cached
	}

	try {
		return await queryClient.fetchQuery(AUTH_QUERY)
	} catch {
		return null
	}
}

/** Redirect unauthenticated users to /login. Use on protected routes. */
export async function requireAuth() {
	const auth = await getAuth()
	if (!auth) {
		throw redirect({ to: "/login" })
	}
}

/** Redirect authenticated users away from guest-only routes (e.g. login, register). */
export async function requireGuest() {
	const auth = await getAuth()
	if (auth) {
		throw redirect({ to: "/admin/dashboard" })
	}
}

type SuperAdminRoleEntry = {
	name?: unknown
	roleNames?: unknown
}

type SuperAdminAuthShape = {
	name?: unknown
	roleNames?: unknown
}

type RouteLocation = {
	pathname: string
}

function isSuperAdmin(auth: SuperAdminAuthShape): boolean {
	if (!Array.isArray(auth.roleNames)) {
		return false
	}

	return auth.roleNames.some((role) => {
		if (typeof role === "string") {
			return role === "Super Admin"
		}

		if (role && typeof role === "object") {
			const typedRole = role as SuperAdminRoleEntry

			if (typedRole.name === "Super Admin") {
				return true
			}

			if (Array.isArray(typedRole.roleNames)) {
				return typedRole.roleNames.includes("Super Admin")
			}
		}

		return false
	})
}

/** Restrict /super/ routes to super admins. */
export async function requireSuperAdmin({
	location,
}: {
	location: RouteLocation
}) {
	if (!location.pathname.includes("/super/")) {
		return
	}

	const auth = (await getAuth()) as (User & SuperAdminAuthShape) | null

	if (!auth || !isSuperAdmin(auth)) {
		throw redirect({ to: "/admin/dashboard" })
	}
}

/** Clear the auth cache — call this after logout. */
export function clearAuth() {
	localStorage.removeItem("sanctumToken")
	queryClient.cancelQueries({ queryKey: AUTH_QUERY.queryKey })
	queryClient.setQueryData(AUTH_QUERY.queryKey, undefined)
}

/** Bust the auth cache — call this after storing a new token so the next render fetches fresh user data. */
export function invalidateAuth() {
	queryClient.setQueryData(AUTH_QUERY.queryKey, undefined)
	queryClient.invalidateQueries({ queryKey: AUTH_QUERY.queryKey })
}
