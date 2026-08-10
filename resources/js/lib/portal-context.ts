import type { User } from "@/types"

export type PortalKind = "super" | "admin" | "tenant"

type RoleRecord = {
	name?: unknown
	roleNames?: unknown
}

type PortalVisual = {
	kind: PortalKind
	label: string
	defaultHref: string
	headerClass: string
	shellClass: string
	badgeClass: string
}

const portalVisuals: Record<PortalKind, PortalVisual> = {
	super: {
		kind: "super",
		label: "Super",
		defaultHref: "/super/dashboard",
		headerClass:
			"border-amber-300/70 bg-amber-50/85 dark:border-amber-500/30 dark:bg-amber-950/20",
		shellClass: "bg-amber-50/35 dark:bg-amber-950/20",
		badgeClass:
			"border-amber-300/70 bg-amber-100/85 text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/15 dark:text-amber-100",
	},
	admin: {
		kind: "admin",
		label: "Admin",
		defaultHref: "/admin/dashboard",
		headerClass:
			"border-sky-300/70 bg-sky-50/85 dark:border-sky-500/30 dark:bg-sky-950/20",
		shellClass: "bg-sky-100 dark:bg-sky-950/20",
		badgeClass:
			"border-sky-300/70 bg-sky-100/85 text-sky-900 dark:border-sky-500/30 dark:bg-sky-500/15 dark:text-sky-100",
	},
	tenant: {
		kind: "tenant",
		label: "Tenant",
		defaultHref: "/tenant/dashboard",
		headerClass:
			"border-emerald-300/70 bg-emerald-50/85 dark:border-emerald-500/30 dark:bg-emerald-950/20",
		shellClass: "bg-emerald-50/35 dark:bg-emerald-950/20",
		badgeClass:
			"border-emerald-300/70 bg-emerald-100/85 text-emerald-900 dark:border-emerald-500/30 dark:bg-emerald-500/15 dark:text-emerald-100",
	},
}

function collectRoleNames(roleNames: unknown): string[] {
	if (!Array.isArray(roleNames)) {
		return []
	}

	return roleNames.flatMap((role) => {
		if (typeof role === "string") {
			return [role]
		}

		if (role && typeof role === "object") {
			const typedRole = role as RoleRecord
			const nestedRoles = Array.isArray(typedRole.roleNames)
				? typedRole.roleNames.filter(
						(nestedRole): nestedRole is string => typeof nestedRole === "string"
					)
				: []

			const roleName =
				typeof typedRole.name === "string" ? [typedRole.name] : []

			return [...roleName, ...nestedRoles]
		}

		return []
	})
}

function hasRole(auth: User | undefined, expectedRole: string): boolean {
	const normalizedExpectedRole = expectedRole.toLowerCase()

	return collectRoleNames(auth?.roleNames).some((roleName) => {
		return roleName.toLowerCase() === normalizedExpectedRole
	})
}

export function detectPortal(pathname: string): PortalKind {
	if (pathname.startsWith("/super")) {
		return "super"
	}

	if (pathname.startsWith("/tenant")) {
		return "tenant"
	}

	return "admin"
}

export function getPortalVisual(portal: PortalKind): PortalVisual {
	return portalVisuals[portal]
}

export function getAvailablePortals(
	auth: User | undefined,
	pathname: string
): PortalKind[] {
	if (!auth) {
		return []
	}

	const currentPortal = detectPortal(pathname)
	const portals: PortalKind[] = ["admin"]

	if (hasRole(auth, "Super Admin")) {
		portals.unshift("super")
	}

	if (hasRole(auth, "Tenant")) {
		portals.push("tenant")
	}

	if (!portals.includes(currentPortal)) {
		portals.push(currentPortal)
	}

	return portals
}

export function getPortalHref(
	targetPortal: PortalKind,
	currentPath: string
): string {
	const currentPathWithoutQuery = currentPath.split("?")[0] ?? currentPath
	const currentPathWithoutHash =
		currentPathWithoutQuery.split("#")[0] ?? currentPathWithoutQuery

	const switchedPath = currentPathWithoutHash.replace(
		/^\/(super|admin|tenant)(?=\/|$)/,
		`/${targetPortal}`
	)

	if (switchedPath !== currentPathWithoutHash) {
		return switchedPath
	}

	return getPortalVisual(targetPortal).defaultHref
}
