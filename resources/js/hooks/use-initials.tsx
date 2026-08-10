import { useCallback } from "react"

export type GetInitialsFn = (fullName: string) => string

export function useInitials(): GetInitialsFn {
	return useCallback((fullName: string): string => {
		return fullName
			.trim()
			.split(" ")
			.filter(Boolean)
			.slice(0, 2)
			.map((part) => part[0]?.toUpperCase() ?? "")
			.join("")
	}, [])
}
